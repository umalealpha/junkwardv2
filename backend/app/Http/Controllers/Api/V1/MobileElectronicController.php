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
 * Mobile and Electronic Device Insurance — dedicated public API for the
 * start.alphadirect.co.bw flow.
 *
 * Mirrors ThirdPartyCarController / LegalInsuranceController / HCB
 * (product_id=5 is a legacy retail product owned by graphiteBWV8).
 *
 * WHY THIS EXISTS: before this endpoint, the only way to buy device cover
 * was via /create-bundle, which merely STAGES a BQ-* bundle quote — the
 * real `policies` + `policy_cellphone` rows were only created later by
 * MaterialiseBundleQuoteJob AT PAYMENT TIME. So a standalone "create a
 * device policy now (pending payment)" had no path: the customer got a
 * staged quote, not a policy. This gives product 5 the same synchronous,
 * pending-payment create the other dedicated products have.
 *
 *   POST /api/v1/public/policies/mobile-electronic/premium-options
 *   POST /api/v1/public/policies/mobile-electronic/calculate-premium
 *   POST /api/v1/public/policies/create-mobile-electronic
 *
 * IMPORTANT: product 5 is NOT added to PublicBundleCreateController's
 * DEDICATED_ENDPOINT_PRODUCTS gate, because device cover is still a valid
 * line inside a multi-product bundle (MaterialiseBundleQuoteJob::seedDevices
 * handles it). Blocking it there would break the bundle device flow. This
 * endpoint serves only the single-product case.
 *
 * Premium is plan-based (the P49 bronze / P99 platinum cover tiers), NOT a
 * percentage of device value — same shape as Third Party Car. We still
 * capture the insured device (type / IMEI / make / model / value) into the
 * `policy_cellphone` table so the policy references the covered device and
 * the post-payment preinspection workflow has its IMEI. Mints MIS- so DPO
 * initiate resolves it natively.
 *
 * Bearer auth: purpose=payment_authorize session token (same as TP Car).
 */
class MobileElectronicController extends Controller
{
    use ResolvesMatiIdentity;
    use ResolvesBillingStartDate;

    private const PRODUCT_ID = 5;
    private const COUNTRY_BW = 28;
    private const VAT_PERCENT = 14.0;

    /**
     * Plan ids accepted by createPolicy — the cover tiers for product 5.
     * Hardcoded so a tampered FE can't pass an arbitrary plan_id.
     * Update when product_plans change.
     *   9  → P49_cellphone_insurance_bronze
     *   17 → P99_cellphone_insurance_platinum
     */
    private const ALLOWED_PLAN_IDS = [9, 17];

    public function __construct(private PublicOtpService $otp) {}

    /**
     * Plan catalogue for the FE — the accepted product-5 plans with the
     * VAT-inclusive premium so the FE can render the cover-tier picker.
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
            'product'     => ['id' => self::PRODUCT_ID, 'name' => 'Mobile and Electronic Device Insurance'],
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
     * Persist a Mobile/Electronic Device policy. Writes inside one
     * transaction: customer → customer_profile → policies → policy_cellphone.
     * Schema-drift-safe via array_intersect_key on each table.
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
            'planId'            => ['required', 'integer', 'in:' . implode(',', self::ALLOWED_PLAN_IDS)],
            'paymentMethod'     => ['required', 'string', 'in:DPO,RealPay,VCS,Orange,Flutterwave'],

            // Device — the insured asset. Plan-based premium (no per-value
            // rating), but we capture identity for preinspection + claims.
            'device'                 => ['required', 'array'],
            'device.deviceType'      => ['required', 'string', 'in:Cellphone,Tablet,Laptop'],
            'device.imei'            => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9]+$/'],
            'device.make'            => ['required', 'string', 'max:64'],
            'device.model'           => ['nullable', 'string', 'max:64'],
            'device.value'           => ['required', 'numeric', 'min:0', 'max:15000'],
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
        // Double-clicks / payment retries must not mint a second identical
        // policy. Resolve the existing customer by identity first (this flow
        // creates a fresh customer per submit) then short-circuit.
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
        // A customer may hold only ONE non-cancelled Mobile/Electronic policy.
        if (\AlphaDirect\Policy::customerHasActiveProductByIdentity(
            $request->input('omang'), $request->input('passport'), $sessionPhone, self::PRODUCT_ID
        )) {
            return response()->json([
                'ok'      => false,
                'error'   => 'duplicate_product',
                'message' => 'This customer already has an active Mobile and Electronic Device Insurance policy.',
            ], 422);
        }

        // Policy number — MIS{YYYY}{6-digit-padded next id}. Same best-effort
        // estimator as Legal / HCB / ACD / TP Car.
        $latestPolicyId = (int) (DB::table('policies')->max('id') ?? 0);
        $policyNumber   = 'MIS' . Carbon::now()->year . str_pad((string) ($latestPolicyId + 1), 6, '0', STR_PAD_LEFT);

        $device = (array) $request->input('device');

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
                'product_id'       => self::PRODUCT_ID,
                'plan_id'          => (int) $request->input('planId'),
                'policyNumber'     => $policyNumber,
                'status'           => 0,                             // pending payment
                'premium'          => $total,
                'annual_premium'   => $total * 12,
                'premium_freq'     => 'monthly',
                'vat_percent'      => (string) $vatPercent,
                'has_member'       => 0,
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

            // 4. policy_cellphone — the insured device. Mirrors the column
            // mapping MaterialiseBundleQuoteJob::seedDevices uses so claims
            // and preinspection see the same shape regardless of which
            // creation path issued the policy. `status` is an int(10) column
            // (0 = pending, 1 = active) — we set 0 to match the policy's own
            // pending-payment status; payment flips it. The preinspection
            // task is created at payment time (not here), same as the bundle
            // flow.
            if (DB::getSchemaBuilder()->hasTable('policy_cellphone')) {
                $deviceCols = DB::getSchemaBuilder()->getColumnListing('policy_cellphone');
                $deviceRow  = array_intersect_key([
                    'policy_id'        => $policyId,
                    'customer_id'      => $customerId,
                    'device_type'      => (string) ($device['deviceType'] ?? ''),
                    'imei'             => strtoupper((string) ($device['imei'] ?? '')),
                    'phone_value'      => (string) ($device['value'] ?? '0'),
                    'cell_phone_make'  => (string) ($device['make'] ?? ''),
                    'cell_phone_model' => (string) ($device['model'] ?? ''),
                    'status'           => 0, // pending payment
                    'created_at'       => Carbon::now(),
                    'updated_at'       => Carbon::now(),
                ], array_flip($deviceCols));
                DB::table('policy_cellphone')->insert($deviceRow);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('MobileElectronic.createPolicy.db_error', [
                'error'   => $e->getMessage(),
                'payload' => $request->except(['data']),
            ]);
            return response()->json([
                'ok'      => false,
                'error'   => 'db_error',
                'message' => $e->getMessage(),
            ], 500);
        }

        Log::info('MobileElectronic.createPolicy.success', [
            'policyNumber' => $policyNumber,
            'customer_id'  => $customerId,
            'policy_id'    => $policyId,
            'plan_id'      => (int) $request->input('planId'),
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
