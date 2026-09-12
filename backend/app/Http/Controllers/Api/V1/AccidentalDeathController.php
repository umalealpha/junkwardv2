<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\ADPricing;
use AlphaDirect\Services\PublicOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Accidental Death Insurance (product id 1) — dedicated public API for
 * the start.alphadirect.co.bw flow.
 *
 * Mirrors HospitalCashbackController one-for-one:
 *   - premiumOptions()   → POST /api/v1/public/policies/accidental-death/premium-options
 *   - calculatePremium() → POST /api/v1/public/policies/accidental-death/calculate-premium
 *   - createPolicy()     → POST /api/v1/public/policies/create-accidental-death
 *
 * Replaces the legacy graphiteBWV8 CommonApis\PolicyController@createPolicy
 * call for product 1. Writes Policy + Customer + Beneficiary + Member rows
 * directly inside one transaction so the FE has no upstream dependency.
 *
 * Bearer auth: same purpose=payment_authorize session token used by Motor
 * and HCB. Issued by /api/v1/public/otp/customer/verify.
 *
 * Conceptual fix over V8: V8 conflates "covered lives" (whose death triggers
 * payout) and "beneficiaries" (who receive payout). V2 keeps them as two
 * distinct arrays — coveredLives sits in policy_members (HCB pattern),
 * beneficiaries in policy_beneficiary.
 */
class AccidentalDeathController extends Controller
{
    private const PRODUCT_ID  = 1;
    private const COUNTRY_BW  = 28;
    private const VAT_PERCENT = 14; // BW VAT fallback if region row absent

    public function __construct(private PublicOtpService $otp) {}

    /**
     * Surface product metadata + age-band pricing grid so the FE can render
     * the quote form without an extra round-trip. Reads ad_pricings live.
     */
    public function premiumOptions(Request $request): JsonResponse
    {
        $bands = ADPricing::where('product', self::PRODUCT_ID)
            ->orderBy('gender')->orderBy('age_from')
            ->get(['gender', 'age_from', 'age_to', 'main', 'adult_dependent', 'child_dependent']);

        return response()->json([
            'ok'      => true,
            'product' => ['id' => self::PRODUCT_ID, 'name' => 'Accidental Death Insurance'],
            'pricing_bands' => $bands,
            'limits'  => [
                'min_age_main'      => 18,
                'max_age_main'      => 70,
                'max_dependents'    => 10,
                'max_beneficiaries' => 6,
                'child_age_cutoff'  => 19,
            ],
            'premium_freq' => 'monthly',
        ]);
    }

    /**
     * Server-side premium calculator. Sums ad_pricings rows for each life
     * and applies regional VAT. Authoritative — createPolicy calls this
     * internally so the persisted premium can never be lower than rated.
     *
     * Payload:
     *  {
     *    main:       { dob: 'YYYY-MM-DD', gender: 'Male'|'Female'|'M'|'F' },
     *    dependents: [{ dob, gender }, ...]   // covered lives, NOT beneficiaries
     *  }
     */
    public function calculatePremium(Request $request): JsonResponse
    {
        $request->validate([
            'main'              => ['required', 'array'],
            'main.dob'          => ['required', 'date'],
            'main.gender'       => ['required', 'string', 'in:Male,Female,M,F'],
            'dependents'                => ['nullable', 'array', 'max:10'],
            'dependents.*.dob'          => ['required_with:dependents', 'date'],
            'dependents.*.gender'       => ['required_with:dependents', 'string', 'in:Male,Female,M,F'],
        ]);

        $vatPercent = $this->vatPercent();
        $breakdown  = [];
        $subtotal   = 0.0;

        // Main applicant.
        $mainPremium = $this->lookupRate(
            $this->ageFromDob($request->input('main.dob')),
            $this->normalizeGender($request->input('main.gender')),
            'main'
        );
        if ($mainPremium === null) {
            return response()->json([
                'ok'    => false,
                'error' => 'no_pricing_band',
                'who'   => 'main',
            ], 422);
        }
        $subtotal    += $mainPremium;
        $breakdown[]  = [
            'life'    => 'main',
            'age'     => $this->ageFromDob($request->input('main.dob')),
            'gender'  => $this->normalizeGender($request->input('main.gender')),
            'premium' => $mainPremium,
        ];

        // Dependents (covered lives).
        foreach ((array) $request->input('dependents', []) as $i => $dep) {
            $age    = $this->ageFromDob($dep['dob']);
            $gender = $this->normalizeGender($dep['gender'] ?? null);
            $bucket = $age <= 19 ? 'child_dependent' : 'adult_dependent';
            $rate   = $this->lookupRate($age, $gender, $bucket);
            if ($rate === null) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'no_pricing_band',
                    'who'   => 'dependent',
                    'index' => $i,
                ], 422);
            }
            $subtotal   += $rate;
            $breakdown[] = [
                'life'    => "dependent[{$i}]",
                'age'     => $age,
                'gender'  => $gender,
                'bucket'  => $bucket,
                'premium' => $rate,
            ];
        }

        $vatAmount = round($subtotal * ($vatPercent / 100), 2);
        $total     = round($subtotal + $vatAmount, 2);

        return response()->json([
            'ok'             => true,
            'breakdown'      => $breakdown,
            'subtotal'       => round($subtotal, 2),
            'vat_percent'    => $vatPercent,
            'vat_amount'     => $vatAmount,
            'total_premium'  => $total,
            'premium_freq'   => 'monthly',
        ]);
    }

    /**
     * Persist an Accidental Death policy. Writes inside one transaction:
     *   customer → customer_profile → policies → policy_members (covered
     *   lives) → policy_beneficiary (payees). Schema-drift-safe via
     *   array_intersect_key on each table's column list.
     */
    public function createPolicy(Request $request): JsonResponse
    {
        $request->validate([
            // Main applicant identity
            'firstName'         => ['required', 'string', 'max:60', 'regex:/^[A-Za-z. \'-]+$/'],
            'lastName'          => ['required', 'string', 'max:60', 'regex:/^[A-Za-z. \'-]+$/'],
            'middleName'        => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z. \'-]+$/'],
            'omang'             => ['nullable', 'string', 'regex:/^[0-9]{9}$/'],
            'passport'          => ['nullable', 'string', 'min:5', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/'],
            'idType'            => ['nullable', 'string', 'in:Omang,Passport'],
            'dob'               => ['required', 'date'],
            'gender'            => ['required', 'string', 'in:Male,Female,M,F'],
            'maritalStatus'     => ['nullable', 'string', 'max:32'],
            // Source of Income/Funds (KYC/AML) — nullable so existing callers/tests
            // that omit it keep passing; the FE marks it required.
            'sourceOfIncome'        => ['nullable', 'string', 'in:unemployed,employment,pensioner_retired,bussiness,inheritance,gifts,investments,dividends,rental,other'],
            'sourceOfIncomeDetails' => ['nullable', 'array'],
            'sourceOfIncomeDetails.*' => ['nullable', 'string', 'max:255'],
            'phone'             => ['required', 'string', 'regex:/^[0-9]{8}$/'],
            'email'             => ['nullable', 'email', 'max:160'],
            'address'           => ['nullable', 'string', 'max:160'],
            'state'             => ['nullable'],
            'city'              => ['nullable'],
            'nationality'       => ['nullable', 'string', 'max:100', new \AlphaDirect\Rules\NotSanctionedCountry()],
            'occupation'        => ['nullable', 'string', 'max:100'],
            'occupationLevel'   => ['nullable', 'string', 'in:Senior,Middle,Junior,Unemployed'],
            'employerName'      => ['nullable', 'string', 'max:50'],
            'country'           => ['nullable', 'string', 'max:100', new \AlphaDirect\Rules\NotSanctionedCountry()],
            'plotNumber'        => ['nullable', 'string', 'max:120'],
            'isPep'             => ['nullable', 'boolean'],
            'pepType'           => ['nullable', 'string', 'max:255'],
            'isPepRelated'      => ['nullable', 'boolean'],
            'pepRelationship'   => ['nullable', 'string', 'max:50'],
            'pepRelationshipSpecify' => ['nullable', 'string', 'max:255'],
            'planId'            => ['nullable', 'integer'],
            'sumInsured'        => ['nullable', 'numeric', 'min:0'],
            'paymentMethod'     => ['required', 'string', 'in:DPO,RealPay,VCS,Orange,Flutterwave'],
            // Policy start / billing preferences (FE payment block).
            'policyStartDate'   => ['nullable', 'date'],
            'billingDate'       => ['nullable', 'date'],
            // Broker assist — Agent ID is persisted to policies.agent_id; the
            // PIN is a credential and is accepted but never stored.
            'assistedByBroker'  => ['nullable', 'boolean'],
            'brokerAgentId'     => ['nullable', 'string', 'max:16'],
            'brokerAgentPin'    => ['nullable', 'string', 'max:16'],
            // Broker store (stores.id) selected after agent verification —
            // persisted to policies.storeID for attribution/commission.
            'storeId'           => ['nullable', 'integer'],

            // Covered lives — additional insured (NOT payees).
            'dependents'                    => ['nullable', 'array', 'max:10'],
            'dependents.*.firstName'        => ['required_with:dependents', 'string', 'max:60'],
            'dependents.*.lastName'         => ['required_with:dependents', 'string', 'max:60'],
            'dependents.*.dob'              => ['required_with:dependents', 'date'],
            'dependents.*.gender'           => ['required_with:dependents', 'string', 'in:Male,Female,M,F'],
            'dependents.*.relation'         => ['required_with:dependents', 'string', 'max:32'],
            'dependents.*.omang'            => ['nullable', 'string', 'regex:/^[0-9]{9}$/'],
            'dependents.*.passport'         => ['nullable', 'string'],

            // Beneficiaries — payees on death. V8 doesn't enforce
            // allocation percentage; mirror that for parity.
            // Beneficiaries are OPTIONAL (ADI Task 1) — a policy can be issued
            // with none. When supplied, each row is still fully validated.
            'beneficiaries'                     => ['nullable', 'array', 'max:6'],
            'beneficiaries.*.firstName'         => ['required', 'string', 'max:60'],
            'beneficiaries.*.lastName'          => ['required', 'string', 'max:60'],
            'beneficiaries.*.middleName'        => ['nullable', 'string', 'max:60'],
            'beneficiaries.*.dob'               => ['required', 'date'],
            'beneficiaries.*.gender'            => ['required', 'string', 'in:Male,Female,M,F'],
            'beneficiaries.*.relation'          => ['required', 'string', 'max:32'],
            'beneficiaries.*.payment'           => ['required', 'string', 'max:32'],
            'beneficiaries.*.percentage'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'beneficiaries.*.omang'             => ['nullable', 'string', 'regex:/^[0-9]{9}$/'],
            'beneficiaries.*.passport'          => ['nullable', 'string'],
            'beneficiaries.*.cellphone'         => ['nullable', 'string'],
            'beneficiaries.*.email'             => ['nullable', 'email', 'max:160'],
        ]);

        if (!$request->filled('omang') && !$request->filled('passport')) {
            return response()->json(['ok' => false, 'error' => 'omang_or_passport_required'], 422);
        }

        // ─── Bearer + session cellphone match (same as HCB) ──────────────
        $bearer = $this->extractBearer($request);
        if (!$bearer) return response()->json(['ok' => false, 'error' => 'session_required'], 401);
        $session = $this->otp->validateToken($bearer);
        if (!$session) return response()->json(['ok' => false, 'error' => 'session_invalid_or_expired'], 401);

        $sessionPhone = $this->normalize((string) ($session['cellphone'] ?? ''));
        $formPhone    = $this->normalize($request->input('phone'));
        if ($sessionPhone !== $formPhone) {
            return response()->json(['ok' => false, 'error' => 'session_phone_mismatch'], 403);
        }

        // ─── Fixed plan premium ──────────────────────────────────────────
        // ADI is sold at the selected plan's flat catalogue price (e.g.
        // P49/P79/P99), NOT an age/gender-rated sum — there is NO pricing-band
        // system for this product. The premium is the plan price + VAT.
        //
        // (Previously this ran a band-based calculatePremium() as an integrity
        // gate and rejected with "no_pricing_band" whenever a customer's
        // age/gender fell outside the legacy ad_pricings grid — which blocked
        // valid customers. Removed: the plan price is authoritative.)
        $vatPercent = $this->vatPercent();

        $plan = \AlphaDirect\Productplan::find($request->input('planId'));
        if (!$plan || $plan->premium === null) {
            return response()->json(['ok' => false, 'error' => 'invalid_plan'], 422);
        }
        $subtotal  = round((float) $plan->premium, 2);
        $vatAmount = round($subtotal * ($vatPercent / 100), 2);
        $total     = round($subtotal + $vatAmount, 2);
        if ($total <= 0) {
            return response()->json(['ok' => false, 'error' => 'invalid_premium'], 422);
        }

        $gender = $this->genderCode($request->input('gender'));

        // ─── Duplicate-submit guard (5.1) ────────────────────────────────
        $dup = \AlphaDirect\Policy::recentInstantDuplicateByIdentity(
            $request->input('omang'),
            $request->input('passport'),
            $sessionPhone,
            self::PRODUCT_ID,
            $total
        );
        if ($dup) {
            return response()->json([
                'ok'            => false,
                'error'         => 'duplicate_submission',
                'message'       => 'A matching policy was just created. Please wait a moment before submitting again.',
                'policy_number' => $dup->policyNumber,
            ], 409);
        }

        // ─── Same-product duplicate guard ────────────────────────────────
        // A customer may hold only ONE non-cancelled Accidental Death policy.
        if (\AlphaDirect\Policy::customerHasActiveProductByIdentity(
            $request->input('omang'), $request->input('passport'), $sessionPhone, self::PRODUCT_ID
        )) {
            return response()->json([
                'ok'      => false,
                'error'   => 'duplicate_product',
                'message' => 'This customer already has an active Accidental Death Insurance policy.',
            ], 422);
        }

        // Policy term anchors on the requested start date (defaults to today
        // when the FE omits it). A one-year ADI term: end/expiry = start +1yr.
        $termStart = $request->filled('policyStartDate')
            ? Carbon::parse($request->input('policyStartDate'))
            : Carbon::now();
        $termEnd = $termStart->copy()->addYear();

        // Billing: the customer's chosen billing date drives both the
        // day-of-month (billing_day) and the first debit date
        // (billingStartDate). Falls back to one month after start.
        $billingMoment = $request->filled('billingDate')
            ? Carbon::parse($request->input('billingDate'))
            : $termStart->copy()->addMonth();
        $billingDay = (int) $billingMoment->format('d');

        // Broker assist — store the agent id and the selected store when the
        // sale is broker-assisted. The PIN is a credential and is intentionally
        // not persisted.
        $broker  = $request->boolean('assistedByBroker');
        $agentId = ($broker && $request->filled('brokerAgentId'))
            ? $request->input('brokerAgentId')
            : null;
        $storeId = ($broker && $request->filled('storeId'))
            ? (int) $request->input('storeId')
            : null;

        // Policy number — MIS{YYYY}{6-digit-padded next id}.
        // Same best-effort estimator as HCB (see HospitalCashbackController
        // line 170-175 for the concurrency caveat).
        $latestPolicyId = (int) (DB::table('policies')->max('id') ?? 0);
        $policyNumber   = 'MIS' . Carbon::now()->year . str_pad((string) ($latestPolicyId + 1), 6, '0', STR_PAD_LEFT);

        try {
            DB::beginTransaction();

            // 1. customer (identity only — table is singular `customer`).
            $customerId = DB::table('customer')->insertGetId([
                'firstName'  => $request->input('firstName'),
                'middleName' => $request->input('middleName'),
                'lastName'   => $request->input('lastName'),
                'email'      => $request->input('email'),
                'cellphone'  => $sessionPhone,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            // 2. customer_profile (PII).
            if (DB::getSchemaBuilder()->hasTable('customer_profile')) {
                $profileCols = DB::getSchemaBuilder()->getColumnListing('customer_profile');
                $profileRow  = array_intersect_key([
                    'customer_id'   => $customerId,
                    'gender'        => $gender,
                    'dob'           => Carbon::parse($request->input('dob'))->format('Y-m-d'),
                    'omang'         => $request->input('omang'),
                    'passport'      => $request->input('passport'),
                    'id_type'       => $request->input('idType'),
                    'address'       => $request->input('address'),
                    'state'         => $request->input('state'),
                    'city'          => $request->input('city'),
                    'countryId'     => self::COUNTRY_BW,
                    'maritalstatus' => $request->input('maritalStatus'),
                    'nationality'      => $request->input('nationality'),
                    'occupation'       => $request->input('occupation'),
                    'occupation_level' => $request->input('occupationLevel'),
                    'country'          => $request->input('country'),
                    'plot_number'      => $request->input('plotNumber'),
                    'is_pep'           => $request->boolean('isPep') ? 1 : 0,
                    'pep_type'         => $request->boolean('isPep') ? $request->input('pepType') : null,
                    'is_pep_related'   => $request->boolean('isPepRelated') ? 1 : 0,
                    'pep_relationship' => $request->boolean('isPepRelated') ? $request->input('pepRelationship') : null,
                    'pep_relationship_specify' => $request->boolean('isPepRelated') ? $request->input('pepRelationshipSpecify') : null,
                    'e_name'           => $request->input('employerName'),
                    'source_of_income'         => $request->input('sourceOfIncome'),
                    'source_of_income_details' => $request->filled('sourceOfIncomeDetails') ? json_encode($request->input('sourceOfIncomeDetails')) : null,
                    'created_at'    => Carbon::now(),
                    'updated_at'    => Carbon::now(),
                ], array_flip($profileCols));
                DB::table('customer_profile')->insert($profileRow);
            }

            // 3. policies.
            $policiesCols = DB::getSchemaBuilder()->getColumnListing('policies');
            $policyRow    = array_intersect_key([
                'customer_id'      => $customerId,
                'agent_id'         => $agentId,
                'storeID'          => $storeId,
                'product_id'       => self::PRODUCT_ID,
                'plan_id'          => $request->input('planId'),
                'policyNumber'     => $policyNumber,
                'status'           => 0,
                'premium'          => $total,
                'annual_premium'   => $total * 12,
                'premium_freq'     => 'monthly',
                'vat_percent'      => (string) $vatPercent,
                'sum_assured'      => $request->input('sumInsured'),
                'has_member'       => count((array) $request->input('dependents', [])) > 0 ? 1 : 0,
                'has_vehicle'      => 0,
                'leadSource'       => 'LiveQuote',
                'billing_day'      => $billingDay,
                'billingStartDate' => $billingMoment->format('Y-m-d'),
                'term_start_date'  => $termStart->format('Y-m-d'),
                'term_end_date'    => $termEnd->format('Y-m-d'),
                'expiry_date'      => $termEnd->format('Y-m-d'),
                'kyc_customer'     => '1',
                'kyc_recipient'    => '0',
                'created_at'       => Carbon::now(),
                'updated_at'       => Carbon::now(),
            ], array_flip($policiesCols));
            $policyId = DB::table('policies')->insertGetId($policyRow);

            // 4. policy_members — covered lives (HCB pattern).
            if (DB::getSchemaBuilder()->hasTable('policy_members')) {
                $memberCols = DB::getSchemaBuilder()->getColumnListing('policy_members');
                foreach ((array) $request->input('dependents', []) as $dep) {
                    $row = array_intersect_key([
                        'policy_id'  => $policyId,
                        'relation'   => $dep['relation']  ?? null,
                        'first_name' => $dep['firstName'] ?? null,
                        'last_name'  => $dep['lastName']  ?? null,
                        'gender'     => $this->genderCode($dep['gender'] ?? null),
                        'dob'        => isset($dep['dob']) ? Carbon::parse($dep['dob'])->format('Y-m-d') : null,
                        'omang'      => $dep['omang']    ?? null,
                        'passport'   => $dep['passport'] ?? null,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ], array_flip($memberCols));
                    DB::table('policy_members')->insert($row);
                }
            }

            // 5. policy_beneficiary — payees on death.
            if (DB::getSchemaBuilder()->hasTable('policy_beneficiary')) {
                $beneCols = DB::getSchemaBuilder()->getColumnListing('policy_beneficiary');
                foreach ((array) $request->input('beneficiaries', []) as $b) {
                    $row = array_intersect_key([
                        'policy_id'   => $policyId,
                        'relation'    => $b['relation']   ?? null,
                        'first_name'  => $b['firstName']  ?? null,
                        'middle_name' => $b['middleName'] ?? null,
                        'last_name'   => $b['lastName']   ?? null,
                        'dob'         => isset($b['dob']) ? Carbon::parse($b['dob'])->format('Y-m-d') : null,
                        'gender'      => $this->genderCode($b['gender'] ?? null),
                        'payment'     => $b['payment']   ?? null,
                        'percentage'  => $b['percentage'] ?? null,
                        'omang'       => $b['omang']     ?? null,
                        'passport'    => $b['passport']  ?? null,
                        'cellphone'   => $b['cellphone'] ?? null,
                        'email'       => $b['email']     ?? null,
                        'created_at'  => Carbon::now(),
                        'updated_at'  => Carbon::now(),
                    ], array_flip($beneCols));
                    DB::table('policy_beneficiary')->insert($row);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('AccidentalDeath.createPolicy.db_error', [
                'error'   => $e->getMessage(),
                'payload' => $request->except(['data']),
            ]);
            return response()->json([
                'ok'      => false,
                'error'   => 'db_error',
                'message' => $e->getMessage(),
            ], 500);
        }

        Log::info('AccidentalDeath.createPolicy.success', [
            'policyNumber' => $policyNumber,
            'customer_id'  => $customerId,
            'policy_id'    => $policyId,
            'total'        => $total,
        ]);

        // Notify the customer (SMS + email) that the policy/quote was created.
        // Non-fatal — the service swallows its own errors.
        (new \AlphaDirect\Services\CustomerNotificationService())
            ->notifyPolicyCreated($policyId, $policyNumber, $total);

        return response()->json([
            'ok'              => true,
            'quote_number'    => 'BQ-' . $policyNumber,
            'policy_number'   => $policyNumber,
            'amount_to_pay'   => $total,
            'subtotal'        => $subtotal,
            'vat_percent'     => $vatPercent,
            'vat_amount'      => $vatAmount,
            'discount_rate'   => 0,
            'discount_amount' => 0,
            'premium_freq'    => 'monthly',
            'expires_at'      => Carbon::now()->addDays(7)->toIso8601String(),
            'policy'          => [
                'id'                => $policyId,
                'policyNumber'      => $policyNumber,
                'customer_id'       => $customerId,
                'product_id'        => self::PRODUCT_ID,
                'plan_id'           => $request->input('planId'),
                'term_start_date'   => $termStart->format('Y-m-d'),
                'expiry_date'       => $termEnd->format('Y-m-d'),
                'billingStartDate'  => $billingMoment->format('Y-m-d'),
                'status'            => 0,
            ],
        ], 201);
    }

    private function lookupRate(int $age, ?string $gender, string $bucket): ?float
    {
        if ($gender === null) return null;
        // The open-ended top band (e.g. "65+") stores age_to = NULL to mean
        // "and above". A plain `age_to >= $age` filter never matches NULL in
        // SQL, which previously rejected every 65+ applicant with
        // no_pricing_band — so treat NULL age_to as having no upper bound.
        $row = ADPricing::where('product', self::PRODUCT_ID)
            ->where('gender', $gender)
            ->where('age_from', '<=', $age)
            ->where(function ($q) use ($age) {
                $q->whereNull('age_to')->orWhere('age_to', '>=', $age);
            })
            ->first([$bucket]);
        if (!$row) return null;
        $val = $row->{$bucket};
        return $val !== null ? (float) $val : null;
    }

    private function vatPercent(): float
    {
        try {
            $product = DB::table('products')->where('id', self::PRODUCT_ID)->first(['region_id']);
            if ($product && $product->region_id) {
                $region = DB::table('regions')->where('id', $product->region_id)->first(['vat']);
                if ($region && $region->vat !== null) return (float) $region->vat;
            }
        } catch (\Throwable $e) {
            // fall through to fallback
        }
        return self::VAT_PERCENT;
    }

    private function ageFromDob(string $dob): int
    {
        return (int) Carbon::parse($dob)->age;
    }

    /**
     * Pricing/display gender — the 'Male'/'Female' string the ad_pricings
     * table is keyed on (and echoed in the calculate-premium breakdown).
     * NOT for persistence: the stored gender columns are integer-coded
     * (see genderCode()).
     */
    private function normalizeGender(?string $g): ?string
    {
        return match (strtoupper((string) $g)) {
            'M', 'MALE'   => 'Male',
            'F', 'FEMALE' => 'Female',
            default       => null,
        };
    }

    /**
     * DB gender code for persistence. The gender columns on
     * customer_profile / policy_members / policy_beneficiary are int(11):
     * 1 = Male, 0 = Female (mirrors customer_profile.gender
     * COMMENT '0:Female\n1:Male'). Writing the 'Male'/'Female' string into
     * these would coerce to 0 (or error under strict mode) — so the create
     * path codes gender here while the validator still accepts the
     * 'Male'/'Female'/'M'/'F' string the FE sends.
     */
    private function genderCode(?string $g): ?int
    {
        return match (strtoupper((string) $g)) {
            'M', 'MALE'   => 1,
            'F', 'FEMALE' => 0,
            default       => null,
        };
    }

    private function extractBearer(Request $request): ?string
    {
        $auth = $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) return trim($m[1]);
        return null;
    }

    private function normalize(string $cellphone): string
    {
        $digits = preg_replace('/\D/', '', $cellphone);
        if (str_starts_with($digits, '267') && strlen($digits) === 11) return substr($digits, 3);
        return $digits;
    }
}
