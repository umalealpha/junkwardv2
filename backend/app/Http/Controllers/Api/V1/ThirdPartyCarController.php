<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Traits\ResolvesMatiIdentity;
use AlphaDirect\Http\Traits\ResolvesBillingStartDate;
use AlphaDirect\Http\Requests\Api\V1\CreateThirdPartyCarRequest;
use AlphaDirect\Services\PublicOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Third Party Car Insurance — dedicated public API for the
 * start.alphadirect.co.bw flow.
 *
 * Mirrors LegalInsuranceController / HospitalCashbackController
 * (product_id=2 is a legacy retail product owned by graphiteBWV8).
 * Routing it through /create-bundle stages a BQ-* quote that the
 * payment service can't resolve (no BQ- branch in initiateDpo) and
 * the generic materialiser doesn't capture the vehicle row — so this
 * product gets its own endpoint that mints a MIS- policy_number which
 * DPO initiate handles natively.
 *
 *   POST /api/v1/public/policies/third-party-car/premium-options
 *   POST /api/v1/public/policies/third-party-car/calculate-premium
 *   POST /api/v1/public/policies/create-third-party-car
 *
 * Unlike Motor Comprehensive (PublicPolicyCreateController::createMotor),
 * third-party cover does NOT require a sum-insured valuation — premium
 * comes from the chosen cover-limit plan. We still capture core vehicle
 * identity (plate / make / model / year) into the `vehicle` table so the
 * policy references the insured car.
 *
 * Bearer auth: purpose=payment_authorize session token (same as
 * Legal / ACD / HCB).
 */
class ThirdPartyCarController extends Controller
{
    use ResolvesMatiIdentity;
    use ResolvesBillingStartDate;

    private const PRODUCT_ID = 2;
    private const COUNTRY_BW = 28;
    private const VAT_PERCENT = 14.0;

    /**
     * Plan ids accepted by createPolicy. Per scope (2026-05-26): the
     * P49 / P79 / P99 cover tiers for product 2. Hardcoded so a tampered
     * FE can't pass an arbitrary plan_id. Update when product_plans change.
     *   4  → P49_P1000000_Cover_bronze
     *   16 → P79_P3000000_Cover
     *   29 → P99_P4000000_Cover_platinum
     *
     * Public so CreateThirdPartyCarRequest can reference the canonical list.
     */
    public const ALLOWED_PLAN_IDS = [4, 16, 29];

    public function __construct(private PublicOtpService $otp) {}

    /**
     * Plan catalogue for the FE — the accepted product-2 plans with the
     * VAT-inclusive premium so the FE can render the cover-limit picker.
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
                'id'           => (int) $p->id,
                'name'         => $p->name,
                'base_premium' => round($base, 2),
                'vat_amount'   => $vat,
                'premium'      => round($base + $vat, 2),
            ];
        })->values();

        return response()->json([
            'ok'          => true,
            'product'     => ['id' => self::PRODUCT_ID, 'name' => 'Third Party Car Insurance'],
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
     * Persist a Third Party Car Insurance policy. Writes inside one
     * transaction: customer → customer_profile → policies → vehicle.
     * Schema-drift-safe via array_intersect_key on each table.
     *
     * Field validation is handled by CreateThirdPartyCarRequest. The
     * omang/passport cross-field guard stays here to preserve the existing
     * {ok:false, error:'omang_or_passport_required'} response shape that the
     * FE relies on (a FormRequest after-validator would emit Laravel's
     * standard {message, errors:{}} shape instead).
     */
    public function createPolicy(CreateThirdPartyCarRequest $request): JsonResponse
    {
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

        // Policy number — MIS{YYYY}{6-digit-padded next id}. Same best-effort
        // estimator as Legal / HCB / ACD.
        $latestPolicyId = (int) (DB::table('policies')->max('id') ?? 0);
        $policyNumber   = 'MIS' . Carbon::now()->year . str_pad((string) ($latestPolicyId + 1), 6, '0', STR_PAD_LEFT);

        $vehicle   = (array) $request->input('vehicle');
        $hasSpouse = $request->filled('spouse');

        // ─── Plate duplicate guard ───────────────────────────────────────
        // Block issuing a new policy for a plate already held by a NON-cancelled
        // policy (parity with graphiteBWV8). A cancelled policy frees the plate.
        // Normalise to the same shape this controller persists (upper, no space).
        $normPlate = strtoupper(preg_replace('/\s+/', '', (string) ($vehicle['plate'] ?? '')));
        if ($normPlate !== '' && \AlphaDirect\Policy::plateInUseByActivePolicy($normPlate)) {
            return response()->json([
                'ok'      => false,
                'error'   => 'vehicle_already_insured',
                'message' => 'A policy is already held for this vehicle registration.',
            ], 422);
        }

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
                    // Employer details (BUG-035) — same columns Legal writes. The
                    // top-level employerName (general KYC) takes precedence over the
                    // legacy debit-order employer block; both map to e_name.
                    'e_name'           => $request->input('employerName') ?: $request->input('employer.employerName'),
                    'emp_no'           => $request->input('employer.employeeNo'),
                    'emp_phone'        => $request->input('employer.employerTel'),
                    'salary_pay_date'  => $request->input('employer.salaryPayDate'),
                    // Driver's licence (BUG-033 / 034 / 041 / 042).
                    'driving_license'    => $request->input('drivingLicenseNumber'),
                    'license_class'      => $request->input('licenseClass'),
                    'license_valid_from' => $request->filled('licenseValidFrom') ? Carbon::parse($request->input('licenseValidFrom'))->format('Y-m-d') : null,
                    'license_valid_to'   => $request->filled('licenseValidTo')   ? Carbon::parse($request->input('licenseValidTo'))->format('Y-m-d')   : null,
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
                'has_vehicle'      => 1,
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

            // 4. vehicle — the insured car. Third-party doesn't carry a
            // sum-insured valuation, so estimated_value is left null; we
            // persist plate / make / model / year for policy reference and
            // claims matching.
            if (DB::getSchemaBuilder()->hasTable('vehicle')) {
                $vehicleCols = DB::getSchemaBuilder()->getColumnListing('vehicle');
                $plate = strtoupper(preg_replace('/\s+/', '', (string) ($vehicle['plate'] ?? '')));
                $vehicleRow = array_intersect_key([
                    'customer_id'          => $customerId,
                    'policy_id'            => $policyId,
                    'vehiclePlate'         => $plate,
                    'vehicle_registration' => $plate,
                    'vehicleRegistration'  => $plate,
                    'make'                 => $vehicle['make']  ?? null,
                    'model'                => $vehicle['model'] ?? null,
                    'year'                 => isset($vehicle['year']) ? (int) $vehicle['year'] : null,
                    'is_private'           => 1,
                    'status'               => 0,
                    'created_at'           => Carbon::now(),
                    'updated_at'           => Carbon::now(),
                ], array_flip($vehicleCols));
                DB::table('vehicle')->insert($vehicleRow);
            }

            // 5. Spouse / life partner as policy_beneficiary (BUG-036) — same
            // pattern LegalInsuranceController uses, populating the
            // legalOmangExpiry / legalPassportExpiry columns when supplied.
            if ($hasSpouse && DB::getSchemaBuilder()->hasTable('policy_beneficiary')) {
                $spouse   = (array) $request->input('spouse');
                $beneCols = DB::getSchemaBuilder()->getColumnListing('policy_beneficiary');
                $beneRow  = array_intersect_key([
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
                DB::table('policy_beneficiary')->insert($beneRow);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ThirdPartyCar.createPolicy.db_error', [
                'error'   => $e->getMessage(),
                'payload' => $request->except(['data']),
            ]);
            return response()->json([
                'ok'      => false,
                'error'   => 'db_error',
                'message' => $e->getMessage(),
            ], 500);
        }

        Log::info('ThirdPartyCar.createPolicy.success', [
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
     * DB gender code. The gender column on customer_profile is int(11):
     * 1 = Male, 0 = Female (mirrors customer_profile.gender
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
