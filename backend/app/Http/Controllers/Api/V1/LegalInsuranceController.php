<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Traits\ResolvesMatiIdentity;
use AlphaDirect\Http\Traits\ResolvesBillingStartDate;
use AlphaDirect\Services\PublicOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Legal Insurance — dedicated public API for the start.alphadirect.co.bw flow.
 *
 * Mirrors the AccidentalDeathController + HospitalCashbackController pattern
 * (product_id=4 is a legacy retail product owned by graphiteBWV8; the generic
 * MaterialiseBundleQuoteJob cannot reconstruct the spouse-as-legal-beneficiary
 * row required for Legal payouts, so this product must NOT go through
 * /create-bundle).
 *
 *   POST /api/v1/public/policies/legal-insurance/calculate-premium
 *      Server-side recompute of premium from the chosen plan + VAT.
 *
 *   POST /api/v1/public/policies/create-legal-insurance
 *      Persists customer + customer_profile + policies + (when married)
 *      policy_beneficiary row for the spouse. Returns a MIS-prefixed
 *      policy_number the FE then hands to /payments/dpo/initiate.
 *
 * Bearer auth: purpose=payment_authorize session token issued by
 * /api/v1/public/otp/customer/verify (same as ACD / HCB).
 */
class LegalInsuranceController extends Controller
{
    use ResolvesMatiIdentity;
    use ResolvesBillingStartDate;

    private const PRODUCT_ID = 4;
    private const COUNTRY_BW = 28;
    private const VAT_PERCENT = 14.0; // fallback if region row missing

    /**
     * Plan ids accepted by createPolicy. Sourced from product_plans WHERE
     * product_id=4 as of 2026-05-26. Hardcoded so a tampered FE cannot
     * pass an arbitrary plan_id (e.g. a 0-premium plan from a different
     * product). Update when product_plans rows change.
     */
    private const ALLOWED_PLAN_IDS = [5, 6, 7, 18, 23];

    public function __construct(private PublicOtpService $otp) {}

    /**
     * Plan catalogue for the FE — returns the rows currently in
     * product_plans for product_id=4 plus the VAT-inclusive premium so
     * the FE can render the picker without a separate /products call.
     */
    public function premiumOptions(Request $request): JsonResponse
    {
        $vatPercent = $this->vatPercent();
        $plans = DB::table('product_plans')
            ->where('product_id', self::PRODUCT_ID)
            ->whereIn('id', self::ALLOWED_PLAN_IDS)
            ->orderBy('premium')
            ->get(['id', 'name', 'premium']);

        $rates = $plans->map(function ($p) use ($vatPercent) {
            $base = (float) $p->premium;
            $vat  = round($base * $vatPercent / 100, 2);
            return [
                'id'              => (int) $p->id,
                'name'            => $p->name,
                'base_premium'    => round($base, 2),
                'vat_amount'      => $vat,
                'premium'         => round($base + $vat, 2),
            ];
        })->values();

        return response()->json([
            'ok'          => true,
            'product'     => ['id' => self::PRODUCT_ID, 'name' => 'Legal Insurance'],
            'vat_percent' => $vatPercent,
            'plans'       => $rates,
        ]);
    }

    /**
     * Server-side premium calculator. Takes a plan_id, looks up the base
     * premium, applies VAT. Never trusts a client-supplied amount.
     */
    public function calculatePremium(Request $request): JsonResponse
    {
        $planId = (int) $request->input('planId', 0);
        if (!in_array($planId, self::ALLOWED_PLAN_IDS, true)) {
            return response()->json(['ok' => false, 'error' => 'invalid_plan_id'], 422);
        }

        $plan = DB::table('product_plans')
            ->where('id', $planId)
            ->where('product_id', self::PRODUCT_ID)
            ->first(['id', 'name', 'premium']);
        if (!$plan) {
            return response()->json(['ok' => false, 'error' => 'plan_not_found'], 422);
        }

        $vatPercent = $this->vatPercent();
        $base       = (float) $plan->premium;
        $vatAmount  = round($base * $vatPercent / 100, 2);
        $total      = round($base + $vatAmount, 2);

        return response()->json([
            'ok'             => true,
            'plan'           => ['id' => (int) $plan->id, 'name' => $plan->name],
            'subtotal'       => round($base, 2),
            'vat_percent'    => $vatPercent,
            'vat_amount'     => $vatAmount,
            'total_premium'  => $total,
            'premium_freq'   => 'monthly',
        ]);
    }

    /**
     * Persist a Legal Insurance policy. Writes inside one transaction:
     *   customer → customer_profile → policies → policy_beneficiary
     *   (spouse only, when maritalStatus=married). Schema-drift-safe via
     *   array_intersect_key on each table's column list.
     */
    public function createPolicy(Request $request): JsonResponse
    {
        $request->validate([
            // Main applicant identity
            'firstName'         => ['required', 'string', 'max:60', 'regex:/^[A-Za-z. \'-]+$/'],
            'lastName'          => ['required', 'string', 'max:60', 'regex:/^[A-Za-z. \'-]+$/'],
            'middleName'        => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z. \'-]+$/'],
            // Omang business rule: 9 digits, 5th digit encodes gender (1 or 2).
            'omang'             => ['nullable', 'string', 'regex:/^[0-9]{4}[12][0-9]{4}$/'],
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
            'planId'            => ['required', 'integer', 'in:' . implode(',', self::ALLOWED_PLAN_IDS)],
            'paymentMethod'     => ['required', 'string', 'in:DPO,RealPay,VCS,Orange,Flutterwave'],

            // Broker assist — Agent ID is persisted to policies.agent_id and the
            // selected store to policies.storeID; the PIN is a credential and is
            // accepted but never stored.
            'assistedByBroker'  => ['nullable', 'boolean'],
            'brokerAgentId'     => ['nullable', 'string', 'max:16'],
            'brokerAgentPin'    => ['nullable', 'string', 'max:16'],
            'storeId'           => ['nullable', 'integer'],

            // Employer details — Legal Insurance is salary-deducted, so the
            // FE collects the employer + pay date. Persisted to customer_profile
            // (e_name / emp_no / emp_phone / salary_pay_date).
            'employer'                     => ['nullable', 'array'],
            'employer.employerName'        => ['nullable', 'string', 'max:50'],
            'employer.employeeNo'          => ['nullable', 'string', 'max:12'],
            'employer.employerTel'         => ['nullable', 'string', 'max:12'],
            'employer.salaryPayDate'       => ['nullable', 'string', 'max:10'],

            // Spouse — Legal Insurance covers the spouse when the main
            // applicant is married. Capture is optional but the LegalOmang /
            // LegalPassport columns get the spouse's identity if supplied.
            'spouse'                       => ['nullable', 'array'],
            'spouse.firstName'             => ['required_with:spouse', 'string', 'max:60'],
            'spouse.lastName'              => ['required_with:spouse', 'string', 'max:60'],
            'spouse.middleName'            => ['nullable', 'string', 'max:60'],
            'spouse.dob'                   => ['required_with:spouse', 'date'],
            'spouse.gender'                => ['required_with:spouse', 'string', 'in:Male,Female,M,F'],
            'spouse.omang'                 => ['nullable', 'string', 'regex:/^[0-9]{4}[12][0-9]{4}$/'],
            'spouse.passport'              => ['nullable', 'string'],
            'spouse.cellphone'             => ['nullable', 'string'],
            'spouse.email'                 => ['nullable', 'email', 'max:160'],
            'spouse.omangExpiry'           => ['nullable', 'date'],
            'spouse.passportExpiry'        => ['nullable', 'date'],
        ]);

        if (!$request->filled('omang') && !$request->filled('passport')) {
            return response()->json(['ok' => false, 'error' => 'omang_or_passport_required'], 422);
        }

        // ─── Bearer + session cellphone match ────────────────────────────
        $bearer = $this->extractBearer($request);
        if (!$bearer) return response()->json(['ok' => false, 'error' => 'session_required'], 401);
        $session = $this->otp->validateToken($bearer);
        if (!$session) return response()->json(['ok' => false, 'error' => 'session_invalid_or_expired'], 401);

        $sessionPhone = $this->normalize((string) ($session['cellphone'] ?? ''));
        $formPhone    = $this->normalize($request->input('phone'));
        if ($sessionPhone !== $formPhone) {
            return response()->json(['ok' => false, 'error' => 'session_phone_mismatch'], 403);
        }

        // ─── Server-side premium recalc (never trust client) ─────────────
        $rated = $this->calculatePremium($request)->getData(true);
        if (empty($rated['ok'])) {
            return response()->json($rated, 422);
        }
        $total      = (float) ($rated['total_premium'] ?? 0);
        $vatPercent = (float) ($rated['vat_percent']   ?? self::VAT_PERCENT);
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
        // A customer may hold only ONE non-cancelled Legal Insurance policy.
        if (\AlphaDirect\Policy::customerHasActiveProductByIdentity(
            $request->input('omang'), $request->input('passport'), $sessionPhone, self::PRODUCT_ID
        )) {
            return response()->json([
                'ok'      => false,
                'error'   => 'duplicate_product',
                'message' => 'This customer already has an active Legal Insurance policy.',
            ], 422);
        }

        // Policy number — MIS{YYYY}{6-digit-padded next id}. Same best-effort
        // estimator as HCB / ACD; under concurrent creates the suffix may
        // not equal the row's final autoincrement id, matching V8 behaviour.
        $latestPolicyId = (int) (DB::table('policies')->max('id') ?? 0);
        $policyNumber   = 'MIS' . Carbon::now()->year . str_pad((string) ($latestPolicyId + 1), 6, '0', STR_PAD_LEFT);

        $hasSpouse = $request->filled('spouse');

        // Customer-selected billing start date (future, ≤45 days; falls back
        // to +1 month). billing_day mirrors the chosen day-of-month.
        $billingStartDate = $this->resolveBillingStartDate($request);
        $billingDay       = (int) Carbon::parse($billingStartDate)->format('d');

        try {
            DB::beginTransaction();

            // 1. customer (identity only).
            $customerId = DB::table('customer')->insertGetId([
                'firstName'  => $request->input('firstName'),
                'middleName' => $request->input('middleName'),
                'lastName'   => $request->input('lastName'),
                'email'      => $request->input('email'),
                'cellphone'  => $sessionPhone,
                'mati_identity' => $this->resolveMatiIdentity($request),
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
                    // Top-level employerName (general KYC) takes precedence over the
                    // legacy debit-order employer block; both map to e_name.
                    'e_name'          => $request->input('employerName') ?: $request->input('employer.employerName'),
                    'emp_no'          => $request->input('employer.employeeNo'),
                    'emp_phone'       => $request->input('employer.employerTel'),
                    'salary_pay_date' => $request->input('employer.salaryPayDate'),
                    'source_of_income'         => $request->input('sourceOfIncome'),
                    'source_of_income_details' => $request->filled('sourceOfIncomeDetails') ? json_encode($request->input('sourceOfIncomeDetails')) : null,
                    'created_at'    => Carbon::now(),
                    'updated_at'    => Carbon::now(),
                ], array_flip($profileCols));
                DB::table('customer_profile')->insert($profileRow);
            }

            // 3. policies.
            // Broker assist — persist the agent id and selected store when the
            // sale is broker-assisted. The PIN is intentionally not persisted.
            $broker  = $request->boolean('assistedByBroker');
            $agentId = ($broker && $request->filled('brokerAgentId')) ? $request->input('brokerAgentId') : null;
            $storeId = ($broker && $request->filled('storeId')) ? (int) $request->input('storeId') : null;

            $policiesCols = DB::getSchemaBuilder()->getColumnListing('policies');
            $policyRow    = array_intersect_key([
                'customer_id'      => $customerId,
                'agent_id'         => $agentId,
                'storeID'          => $storeId,
                'product_id'       => self::PRODUCT_ID,
                'plan_id'          => (int) $request->input('planId'),
                'policyNumber'     => $policyNumber,
                'status'           => 0,                             // pending payment
                'premium'          => $total,
                'annual_premium'   => $total * 12,
                'premium_freq'     => 'monthly',
                'vat_percent'      => (string) $vatPercent,
                'has_member'       => $hasSpouse ? 1 : 0,
                'has_vehicle'      => 0,
                'leadSource'       => 'LiveQuote',
                'billing_day'      => $billingDay,
                'billingStartDate' => $billingStartDate,
                'term_start_date'  => Carbon::now()->format('Y-m-d'),
                'term_end_date'    => Carbon::now()->addYear()->format('Y-m-d'),
                'expiry_date'      => Carbon::now()->addYear()->format('Y-m-d'),
                'kyc_customer'     => '1',
                'kyc_recipient'    => '0',
                'created_at'       => Carbon::now(),
                'updated_at'       => Carbon::now(),
            ], array_flip($policiesCols));
            $policyId = DB::table('policies')->insertGetId($policyRow);

            // 4. Spouse as policy_beneficiary (the Legal-Insurance pattern —
            // V8's BundleProductController::processLegalBeneficiary captures
            // the spouse in the same beneficiary table, populating the
            // legalOmangExpiry / legalPassportExpiry columns).
            if ($hasSpouse && DB::getSchemaBuilder()->hasTable('policy_beneficiary')) {
                $spouse   = (array) $request->input('spouse');
                $beneCols = DB::getSchemaBuilder()->getColumnListing('policy_beneficiary');
                $row = array_intersect_key([
                    'policy_id'           => $policyId,
                    'relation'            => 'spouse',
                    'first_name'          => $spouse['firstName']  ?? null,
                    'middle_name'         => $spouse['middleName'] ?? null,
                    'last_name'           => $spouse['lastName']   ?? null,
                    'dob'                 => isset($spouse['dob']) ? Carbon::parse($spouse['dob'])->format('Y-m-d') : null,
                    'gender'              => $this->genderCode($spouse['gender'] ?? null),
                    'cellphone'           => $spouse['cellphone'] ?? null,
                    'email'               => $spouse['email']     ?? null,
                    'omang'               => $spouse['omang']     ?? null,
                    'passport'            => $spouse['passport']  ?? null,
                    'legalOmangExpiry'    => isset($spouse['omangExpiry'])    ? Carbon::parse($spouse['omangExpiry'])->format('Y-m-d')    : null,
                    'legalPassportExpiry' => isset($spouse['passportExpiry']) ? Carbon::parse($spouse['passportExpiry'])->format('Y-m-d') : null,
                    'created_at'          => Carbon::now(),
                    'updated_at'          => Carbon::now(),
                ], array_flip($beneCols));
                DB::table('policy_beneficiary')->insert($row);
            }

            // 5. policy_legal — only when the table exists on this deploy.
            // Older snapshots don't ship it; insertion is best-effort and
            // skipped silently otherwise. Mirrors the V8 Legal model
            // (Legal.php → table policy_legal).
            if (DB::getSchemaBuilder()->hasTable('policy_legal')) {
                $legalCols = DB::getSchemaBuilder()->getColumnListing('policy_legal');
                $legalRow  = array_intersect_key([
                    'policy_id'  => $policyId,
                    'plan_id'    => (int) $request->input('planId'),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ], array_flip($legalCols));
                if (!empty($legalRow)) {
                    DB::table('policy_legal')->insert($legalRow);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('LegalInsurance.createPolicy.db_error', [
                'error'   => $e->getMessage(),
                'payload' => $request->except(['data']),
            ]);
            return response()->json([
                'ok'      => false,
                'error'   => 'db_error',
                'message' => $e->getMessage(),
            ], 500);
        }

        Log::info('LegalInsurance.createPolicy.success', [
            'policyNumber' => $policyNumber,
            'customer_id'  => $customerId,
            'policy_id'    => $policyId,
            'plan_id'      => (int) $request->input('planId'),
            'total'        => $total,
            'has_spouse'   => $hasSpouse,
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
            'subtotal'        => $rated['subtotal']   ?? $total,
            'vat_percent'     => $vatPercent,
            'vat_amount'      => $rated['vat_amount'] ?? 0,
            'discount_rate'   => 0,
            'discount_amount' => 0,
            'premium_freq'    => 'monthly',
            'expires_at'      => Carbon::now()->addDays(7)->toIso8601String(),
            'policy'          => [
                'id'                => $policyId,
                'policyNumber'      => $policyNumber,
                'customer_id'       => $customerId,
                'product_id'        => self::PRODUCT_ID,
                'plan_id'           => (int) $request->input('planId'),
                'term_start_date'   => Carbon::now()->format('Y-m-d'),
                'expiry_date'       => Carbon::now()->addYear()->format('Y-m-d'),
                'billingStartDate'  => $billingStartDate,
                'status'            => 0,
            ],
        ], 201);
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

    /**
     * DB gender code. The gender columns on customer_profile / policy_members
     * are int(11): 1 = Male, 0 = Female (mirrors customer_profile.gender
     * COMMENT '0:Female\n1:Male'). The FE still sends, and the validator still
     * accepts, the 'Male'/'Female'/'M'/'F' string — we code it on write.
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
