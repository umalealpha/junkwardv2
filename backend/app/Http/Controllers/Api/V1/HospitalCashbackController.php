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
 * Hospital Cashback Insurance — dedicated public API for the start.alphadirect.co.bw flow.
 *
 * Mirrors the alphaFEV2 ActivatetsosologoController functions:
 *   - calculatePremium()    → POST /api/v1/public/policies/hospital-cashback/calculate-premium
 *   - hospital_cashback()   → returns the pricing-ladder lookup used by the FE form
 *                             POST /api/v1/public/policies/hospital-cashback/premium-options
 *   - store()               → POST /api/v1/public/policies/create-hospital-cashback
 *                             (replaces the legacy /api/createPolicy call; clean V1 shape;
 *                              persists the Policy + Customer rows directly)
 *
 * Designed alongside PublicPolicyCreateController::createMotor so the FE
 * sees the same response envelope (`ok`, `policy_number`, `amount_to_pay`,
 * `quote_number`).
 *
 * Bearer auth: same purpose=payment_authorize session token used by the
 * Motor flow. Issued by /api/v1/public/otp/customer/verify.
 */
class HospitalCashbackController extends Controller
{
    use ResolvesMatiIdentity;
    use ResolvesBillingStartDate;

    public function __construct(private PublicOtpService $otp) {}

    /**
     * Pricing ladder for the Adults Plan. The legacy backend exposes
     * this via /api/getHospitalCashbackPremium → MobileAppController.
     * We surface the same shape on the V1 namespace so the FE has one
     * canonical source. Values mirror the live production rates.
     */
    public function premiumOptions(Request $request): JsonResponse
    {
        return response()->json([
            'ok'      => true,
            'product' => ['id' => 9, 'name' => 'Hospital Cashback Insurance'],
            'plan'    => ['id' => 19, 'name' => 'Hospital Cash Assurance (Adults Plan)'],
            'rates'   => [
                ['id' => '1', 'relation' => 'Policy Holder',              'premium' => \AlphaDirect\Services\HcbPremiumLadder::POLICY_HOLDER_RATE],
                ['id' => '2', 'relation' => 'Spouse/Immediate Dependent', 'premium' => \AlphaDirect\Services\HcbPremiumLadder::SPOUSE_RATE],
                ['id' => '3', 'relation' => 'Children (Max Up to 6)',     'premium' => \AlphaDirect\Services\HcbPremiumLadder::CHILD_RATE],
            ],
            'limits'  => [
                'max_spouse'   => \AlphaDirect\Services\HcbPremiumLadder::MAX_SPOUSE,
                'max_children' => \AlphaDirect\Services\HcbPremiumLadder::MAX_CHILDREN,
            ],
        ]);
    }

    /**
     * Server-side premium calculator. Mirrors the alphaFEV2
     * ActivatetsosologoController::calculatePremium pass-through but
     * computes the total directly here (no upstream hop) for speed and
     * to avoid the legacy /calculate-premium 400 we hit when payload
     * shapes drift.
     *
     * Payload: { coApplicants: [{ relation: "spouse"|"child" }, ...] }
     *  - Policy Holder is always counted (P99).
     *  - Spouse: P89. Capped at 1.
     *  - Children: P49 each. Capped at 6.
     */
    public function calculatePremium(Request $request): JsonResponse
    {
        $coApplicants = (array) $request->input('coApplicants', []);
        $spouseCount = 0; $childCount = 0;
        foreach ($coApplicants as $c) {
            $rel = strtolower((string) ($c['relation'] ?? ''));
            if ($rel === 'spouse')   $spouseCount++;
            elseif ($rel === 'child') $childCount++;
        }

        $breakdown = \AlphaDirect\Services\HcbPremiumLadder::compute($spouseCount, $childCount);

        return response()->json([
            'ok'             => true,
            'breakdown'      => [
                'policy_holder' => $breakdown['policy_holder'],
                'spouse'        => $breakdown['spouse'],
                'children'      => $breakdown['children'],
            ],
            'total_premium'  => $breakdown['total_premium'],
            'premium_freq'   => 'monthly',
        ]);
    }

    /**
     * Create an HCB policy. Replaces the alphaFEV2
     * ActivatetsosologoController::store call to the legacy
     * /api/createPolicy endpoint — which crashes at
     * CommonApis/PolicyController::createPolicy line 394
     * (`$request->data['activationCode']` on null) and uses
     * case-sensitive vendor names. This controller writes Policy +
     * Customer rows directly inside a transaction, so no upstream
     * dependency, no null-array crash, no vendor-name foot-gun.
     */
    public function createPolicy(Request $request): JsonResponse
    {
        $request->validate([
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
            'planId'            => ['required', 'integer', 'in:19'],
            'paymentMethod'     => ['required', 'string', 'in:DPO,RealPay'],

            // Broker assist — Agent ID is persisted to policies.agent_id and the
            // selected store to policies.storeID; the PIN is a credential and is
            // accepted but never stored.
            'assistedByBroker'  => ['nullable', 'boolean'],
            'brokerAgentId'     => ['nullable', 'string', 'max:16'],
            'brokerAgentPin'    => ['nullable', 'string', 'max:16'],
            'storeId'           => ['nullable', 'integer'],
            'coApplicants'                  => ['nullable', 'array', 'max:7'],
            'coApplicants.*.relation'       => ['required_with:coApplicants', 'string', 'in:spouse,child'],
            'coApplicants.*.firstName'      => ['required_with:coApplicants', 'string', 'max:60'],
            'coApplicants.*.lastName'       => ['required_with:coApplicants', 'string', 'max:60'],
            'coApplicants.*.gender'         => ['required_with:coApplicants', 'string', 'in:Male,Female,M,F'],
            'coApplicants.*.dob'            => ['required_with:coApplicants', 'date'],
            'coApplicants.*.omang'          => ['nullable', 'string', 'regex:/^[0-9]{9}$/'],
            'coApplicants.*.passport'       => ['nullable', 'string'],
        ]);

        if (!$request->filled('omang') && !$request->filled('passport')) {
            return response()->json(['ok' => false, 'error' => 'omang_or_passport_required'], 422);
        }

        // Bearer / OTP session check — same pattern as createMotor.
        $bearer = $this->extractBearer($request);
        if (!$bearer) return response()->json(['ok' => false, 'error' => 'session_required'], 401);
        $session = $this->otp->validateToken($bearer);
        if (!$session) return response()->json(['ok' => false, 'error' => 'session_invalid_or_expired'], 401);

        $sessionPhone = $this->normalize((string) ($session['cellphone'] ?? ''));
        $formPhone    = $this->normalize($request->input('phone'));
        if ($sessionPhone !== $formPhone) {
            return response()->json(['ok' => false, 'error' => 'session_phone_mismatch'], 403);
        }

        // Server-side premium (do not trust client-supplied amount).
        $premiumResp = $this->calculatePremium($request)->getData(true);
        $total       = (int) ($premiumResp['total_premium'] ?? 0);
        if ($total <= 0) {
            return response()->json(['ok' => false, 'error' => 'invalid_premium'], 422);
        }

        // Persisted gender columns (customer_profile, policy_members) are
        // int(11): 1 = Male, 0 = Female (mirrors customer_profile.gender
        // COMMENT '0:Female\n1:Male'). Code on write — the FE still sends,
        // and the validator still accepts, the 'Male'/'Female'/'M'/'F' string.
        $genderCode = static fn (?string $g) => match (strtoupper((string) $g)) {
            'M', 'MALE'   => 1,
            'F', 'FEMALE' => 0,
            default       => null,
        };
        $gender = $genderCode($request->input('gender'));

        // ─── Duplicate-submit guard (5.1) ────────────────────────────────
        // HCB has no PRODUCT_ID const — product 9 (see policies row below).
        $dup = \AlphaDirect\Policy::recentInstantDuplicateByIdentity(
            $request->input('omang'),
            $request->input('passport'),
            $sessionPhone,
            9,
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

        // HCB is product 9 — a legacy retail product owned by graphiteBWV8.
        // Per company convention (mirrors V8 AddWizard.php:551-563), legacy
        // products mint MIS<year><6-digit-padded-next-policy-id>. Reading the
        // latest Policy.id and adding 1 is a best-effort estimator; the actual
        // autoincrement id assigned on insert may differ under concurrent
        // creates. Matches V8 behaviour 1:1 — neither side guarantees the
        // suffix equals the row's final id, just that it's monotonic.
        $latestPolicyId = (int) (DB::table('policies')->max('id') ?? 0);
        $policyNumber = 'MIS' . Carbon::now()->year . str_pad((string) ($latestPolicyId + 1), 6, '0', STR_PAD_LEFT);

        // Customer-selected billing start date (future, ≤45 days; falls back
        // to +1 month). billing_day mirrors the chosen day-of-month.
        $billingStartDate = $this->resolveBillingStartDate($request);
        $billingDay       = (int) Carbon::parse($billingStartDate)->format('d');

        try {
            DB::beginTransaction();

            // ─── 1. customer (identity only — table is singular `customer`) ──
            $customerId = DB::table('customer')->insertGetId([
                'firstName'   => $request->input('firstName'),
                'middleName'  => $request->input('middleName'),
                'lastName'    => $request->input('lastName'),
                'email'       => $request->input('email'),
                'cellphone'   => $sessionPhone,
                'mati_identity' => $this->resolveMatiIdentity($request),
                'created_at'  => Carbon::now(),
                'updated_at'  => Carbon::now(),
            ]);

            // ─── 2. customer_profile (PII — separate table) ──────────────────
            if (DB::getSchemaBuilder()->hasTable('customer_profile')) {
                $profileCols = DB::getSchemaBuilder()->getColumnListing('customer_profile');
                $profileRow  = [
                    'customer_id' => $customerId,
                    'gender'      => $gender,
                    'dob'         => Carbon::parse($request->input('dob'))->format('Y-m-d'),
                    'omang'       => $request->input('omang'),
                    'passport'    => $request->input('passport'),
                    'id_type'     => $request->input('idType'),
                    'address'     => $request->input('address'),
                    'state'       => $request->input('state'),
                    'city'        => $request->input('city'),
                    'countryId'   => 28, // Botswana
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
                    'created_at'  => Carbon::now(),
                    'updated_at'  => Carbon::now(),
                ];
                // Only insert columns that actually exist on the table to avoid
                // schema drift between staging snapshots and prod.
                $profileRow = array_intersect_key($profileRow, array_flip($profileCols));
                DB::table('customer_profile')->insert($profileRow);
            }

            // ─── 3. policies (the actual policy) ─────────────────────────────
            // Broker assist — persist the agent id and selected store when the
            // sale is broker-assisted. The PIN is intentionally not persisted.
            $broker  = $request->boolean('assistedByBroker');
            $agentId = ($broker && $request->filled('brokerAgentId')) ? $request->input('brokerAgentId') : null;
            $storeId = ($broker && $request->filled('storeId')) ? (int) $request->input('storeId') : null;

            $policiesCols = DB::getSchemaBuilder()->getColumnListing('policies');
            $policyRow = [
                'customer_id'        => $customerId,
                'agent_id'           => $agentId,
                'storeID'            => $storeId,
                'product_id'         => 9,
                'plan_id'            => 19,
                'policyNumber'       => $policyNumber,
                'status'             => 0,                       // pending payment
                'premium'            => $total,
                'annual_premium'     => $total * 12,
                'premium_freq'       => 'monthly',
                'vat_percent'        => '14',
                'has_member'         => count((array) $request->input('coApplicants', [])) > 0 ? 1 : 0,
                'has_vehicle'        => 0,
                'leadSource'         => 'LiveQuote',
                'billing_day'        => $billingDay,
                'billingStartDate'   => $billingStartDate,
                'term_start_date'    => Carbon::now()->format('Y-m-d'),
                'term_end_date'      => Carbon::now()->addYear()->format('Y-m-d'),
                'expiry_date'        => Carbon::now()->addYear()->format('Y-m-d'),
                'kyc_customer'       => '1',
                'kyc_recipient'      => '0',
                'created_at'         => Carbon::now(),
                'updated_at'         => Carbon::now(),
            ];
            $policyRow = array_intersect_key($policyRow, array_flip($policiesCols));
            $policyId = DB::table('policies')->insertGetId($policyRow);

            // Persist co-applicants on the canonical hospital_Cashback_coapplicants
            // table — NOT policy_members, which the policy-detail page never reads
            // and which silently dropped omang/passport (see
            // docs/HCB-001-coapplicant-findings.md). Premium is already correct in
            // $policyRow above, so skip the service's recalculation here.
            if (count((array) $request->input('coApplicants', [])) > 0) {
                $newPolicy = \AlphaDirect\Policy::find($policyId);
                foreach ((array) $request->input('coApplicants', []) as $c) {
                    \AlphaDirect\Services\HcbCoapplicantService::add($newPolicy, [
                        'relation'   => $c['relation']  ?? null,
                        'first_name' => $c['firstName'] ?? null,
                        'last_name'  => $c['lastName']  ?? null,
                        'gender'     => $c['gender']    ?? null,
                        'dob'        => $c['dob']       ?? null,
                        'omang'      => $c['omang']     ?? null,
                        'passport'   => $c['passport']  ?? null,
                    ], null, 'System (HCB creation)', recalculate: false);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('HospitalCashback.createPolicy.db_error', [
                'error'   => $e->getMessage(),
                'payload' => $request->except(['data']),
            ]);
            return response()->json([
                'ok'      => false,
                'error'   => 'db_error',
                'message' => $e->getMessage(),
            ], 500);
        }

        Log::info('HospitalCashback.createPolicy.success', [
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
            'ok'             => true,
            'quote_number'   => 'BQ-' . $policyNumber,
            'policy_number'  => $policyNumber,
            'amount_to_pay'  => $total,
            'subtotal'       => $total,
            'discount_rate'  => 0,
            'discount_amount'=> 0,
            'premium_freq'   => 'monthly',
            'expires_at'     => Carbon::now()->addDays(7)->toIso8601String(),
            'policy'         => [
                'id'                => $policyId,
                'policyNumber'      => $policyNumber,
                'customer_id'       => $customerId,
                'product_id'        => 9,
                'plan_id'           => 19,
                'term_start_date'   => Carbon::now()->format('Y-m-d'),
                'expiry_date'       => Carbon::now()->addYear()->format('Y-m-d'),
                'billingStartDate'  => $billingStartDate,
                'status'            => 0,
            ],
        ], 201);
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
