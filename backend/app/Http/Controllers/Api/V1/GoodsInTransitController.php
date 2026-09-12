<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\PublicOtpService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * GoodsInTransitController — the start.alphadirect purchase flow for Alpha
 * Transit Cover (per-shipment goods-in-transit, 14 days, one-time premium).
 *
 *   POST public/policies/goods-in-transit/rating-options   (public)
 *   POST public/policies/goods-in-transit/calculate-premium (public)
 *   POST public/policies/create-goods-in-transit           (OTP session bearer)
 *
 * Unlike the fixed-premium instant products (TPC/Legal/HCB), GIT is
 * value-rated: premium = max(declared_value × rate, minimum) per goods
 * category and zone — the same rating table the Alpha Transit courier
 * platform uses (routes/policies.js RATES), so both sales channels price
 * identically. Plans are the two goods categories (GITSTD / GITELE), looked
 * up by plan_unique_id, never by id.
 *
 * Policies mint the GIT prefix (GIT{YYYY}{6-digit next id}) — distinct from
 * the courier channel's ATC- numbers so MIS reporting can split channels —
 * and premium_freq='once' so the one-time premium can never enter the
 * recurring debit-order schedule (activatePaidPolicyAndSchedule skips 'once').
 */
class GoodsInTransitController extends Controller
{
    /**
     * Preferred id — the seed pins 25 where it's free, but falls back to
     * auto-increment when a foreign product already owns 25 in an env. Always
     * resolve through productId(), never use this constant in queries/writes:
     * writing 25 in such an env would attribute GIT policies to the wrong
     * product (wrong VAT region, wrong dedup scope, wrong FE tab gating).
     */
    public const PRODUCT_ID  = 25;
    private const PRODUCT_NAME = 'Alpha Transit Cover';
    private const PRODUCT_SLUG = 'alpha-transit-cover';
    private ?int $resolvedProductId = null;
    private const VAT_PERCENT = 14.0; // fallback when regions.vat is unavailable

    public const COVER_DAYS = 14;
    public const VALUE_MIN  = 200;

    // plan_unique_id → goods category
    public const PLAN_CATEGORY = ['GITSTD' => 'STD', 'GITELE' => 'ELE'];

    public const ZONES = [
        'BW'    => 'Botswana',
        'ZA_GT' => 'South Africa — Gauteng',
        'ZA_OT' => 'South Africa — Other',
        'NA'    => 'Namibia',
        'ZW'    => 'Zimbabwe',
        'ZM'    => 'Zambia',
        'LS'    => 'Lesotho',
        'SZ'    => 'Eswatini',
    ];

    // Verbatim from the Alpha Transit platform (routes/policies.js) so both
    // channels rate identically: r = rate, mn = minimum premium, ef = excess
    // floor, ep = excess % of value, mx = max declared value for the zone.
    private const RATES = [
        'STD' => [
            'BW'    => ['r' => 0.0125, 'mn' => 20, 'ef' => 250, 'ep' => 0.15, 'mx' => 50000],
            'ZA_GT' => ['r' => 0.0200, 'mn' => 40, 'ef' => 500, 'ep' => 0.15, 'mx' => 75000],
            'ZA_OT' => ['r' => 0.0250, 'mn' => 50, 'ef' => 750, 'ep' => 0.15, 'mx' => 75000],
            'NA'    => ['r' => 0.0175, 'mn' => 35, 'ef' => 400, 'ep' => 0.15, 'mx' => 75000],
            'ZW'    => ['r' => 0.0225, 'mn' => 45, 'ef' => 600, 'ep' => 0.15, 'mx' => 50000],
            'ZM'    => ['r' => 0.0175, 'mn' => 35, 'ef' => 400, 'ep' => 0.15, 'mx' => 75000],
            'LS'    => ['r' => 0.0225, 'mn' => 45, 'ef' => 600, 'ep' => 0.15, 'mx' => 50000],
            'SZ'    => ['r' => 0.0200, 'mn' => 40, 'ef' => 500, 'ep' => 0.15, 'mx' => 75000],
        ],
        'ELE' => [
            'BW'    => ['r' => 0.0200, 'mn' => 40, 'ef' => 250, 'ep' => 0.15, 'mx' => 50000],
            'ZA_GT' => ['r' => 0.0300, 'mn' => 70, 'ef' => 500, 'ep' => 0.15, 'mx' => 75000],
            'ZA_OT' => ['r' => 0.0350, 'mn' => 85, 'ef' => 750, 'ep' => 0.15, 'mx' => 75000],
            'NA'    => ['r' => 0.0275, 'mn' => 60, 'ef' => 400, 'ep' => 0.15, 'mx' => 75000],
            'ZW'    => ['r' => 0.0325, 'mn' => 80, 'ef' => 600, 'ep' => 0.15, 'mx' => 50000],
            'ZM'    => ['r' => 0.0275, 'mn' => 60, 'ef' => 400, 'ep' => 0.15, 'mx' => 75000],
            'LS'    => ['r' => 0.0325, 'mn' => 80, 'ef' => 600, 'ep' => 0.15, 'mx' => 50000],
            'SZ'    => ['r' => 0.0300, 'mn' => 70, 'ef' => 500, 'ep' => 0.15, 'mx' => 75000],
        ],
    ];

    public function __construct(
        private PublicOtpService $otp,
        private \AlphaDirect\Services\Partner\PartnerAuthService $partnerAuth,
    ) {
    }

    /**
     * POST public/policies/goods-in-transit/rating-options
     * Categories (as plans), zones and band limits for the FE form.
     */
    public function ratingOptions(): JsonResponse
    {
        $plans = DB::table('product_plans')
            ->whereIn('plan_unique_id', array_keys(self::PLAN_CATEGORY))
            ->where('status', 1)
            ->get(['id', 'name', 'plan_unique_id', 'premium', 'sum_assured'])
            ->map(function ($p) {
                $code = self::PLAN_CATEGORY[$p->plan_unique_id] ?? null;
                // Customer-facing from-price = the cheapest possible premium
                // (the lowest zone minimum), VAT-inclusive — the DB `premium`
                // column stores the ex-VAT figure for the plans lookup.
                $fromPremium = $code
                    ? min(array_column(self::RATES[$code], 'mn'))
                    : (float) $p->premium;
                return [
                    'id'           => (int) $p->id,
                    'name'         => $p->name,
                    'code'         => $code,
                    'from_premium' => (float) $fromPremium,
                    'max_value'    => (float) $p->sum_assured,
                ];
            })->values();

        // Full rate card so the FE can show the customer how the premium is
        // built BEFORE they type a value. All money figures are VAT-inclusive
        // (the rated amount is final).
        $rates = [];
        foreach (self::RATES as $category => $zones) {
            foreach ($zones as $zone => $cfg) {
                // round() — bare r*100 leaks float artifacts (1.7500000000000002).
                $rates[$category][$zone] = [
                    'rate_percent'   => round($cfg['r'] * 100, 2),
                    'min_premium'    => $cfg['mn'],
                    'max_value'      => $cfg['mx'],
                    'excess_floor'   => $cfg['ef'],
                    'excess_percent' => round($cfg['ep'] * 100, 2),
                ];
            }
        }

        return response()->json([
            'ok'          => true,
            'product'     => ['id' => $this->productId(), 'name' => 'Alpha Transit Cover'],
            'plans'       => $plans,
            'zones'       => collect(self::ZONES)->map(fn ($label, $code) => ['code' => $code, 'label' => $label])->values(),
            'rates'       => $rates,
            'value_min'   => self::VALUE_MIN,
            'cover_days'  => self::COVER_DAYS,
            'vat_percent' => $this->vatPercent(),
        ]);
    }

    /**
     * POST public/policies/goods-in-transit/calculate-premium
     * { goodsCategory: STD|ELE, fromZone, toZone, declaredValue }
     */
    public function calculatePremium(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'goodsCategory' => 'required|string|in:' . implode(',', array_values(self::PLAN_CATEGORY)),
            'fromZone'      => 'required|string|in:' . implode(',', array_keys(self::ZONES)),
            'toZone'        => 'required|string|in:' . implode(',', array_keys(self::ZONES)),
            'declaredValue' => 'required|numeric|min:' . self::VALUE_MIN,
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'error' => 'validation', 'messages' => $v->errors()], 422);
        }

        $category = strtoupper((string) $request->input('goodsCategory'));
        $from     = (string) $request->input('fromZone');
        $to       = (string) $request->input('toZone');
        $value    = round((float) $request->input('declaredValue'), 2);

        // Same zone rule as the ATC platform: a domestic run rates as BW;
        // a cross-border run rates on the foreign leg.
        $zone = ($from === 'BW' && $to === 'BW') ? 'BW' : ($from !== 'BW' ? $from : $to);
        $cfg  = self::RATES[$category][$zone] ?? null;
        if (!$cfg) {
            return response()->json(['ok' => false, 'error' => 'invalid_route'], 422);
        }
        if ($value > $cfg['mx']) {
            return response()->json([
                'ok'        => false,
                'error'     => 'value_out_of_band',
                'message'   => "Declared value above P{$cfg['mx']} for this route — contact underwriting for facultative cover.",
                'max_value' => $cfg['mx'],
            ], 422);
        }

        // The ATC rating table is VAT-INCLUSIVE (confirmed 3 Sep 2026): the
        // rated amount IS the amount the customer pays. VAT is backed out for
        // the breakdown, mirroring how the platform's P81.25 sample is final.
        $total      = round(max($value * $cfg['r'], $cfg['mn']), 2);
        $excess     = max($cfg['ef'], (int) round($value * $cfg['ep']));
        $vatPercent = $this->vatPercent();
        $subtotal   = round($total / (1 + $vatPercent / 100), 2);

        return response()->json([
            'ok'            => true,
            'zone'          => $zone,
            'rate'          => $cfg['r'],
            'declared_value'=> $value,
            'sum_insured'   => $value,
            'subtotal'      => $subtotal,
            'vat_percent'   => $vatPercent,
            'vat_amount'    => round($total - $subtotal, 2),
            'total_premium' => $total,
            'excess'        => $excess,
            'premium_freq'  => 'once',
            'cover_days'    => self::COVER_DAYS,
            'max_value'     => $cfg['mx'],
        ]);
    }

    /**
     * POST public/policies/create-goods-in-transit  (Bearer = OTP session)
     *
     * KYC fields follow the TPC payload names; the shipment block carries the
     * insured transit. The purchasing customer is the sender.
     */
    public function createPolicy(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'firstName'                => 'required|string|max:80',
            'lastName'                 => 'required|string|max:80',
            'middleName'               => 'nullable|string|max:80',
            'omang'                    => 'nullable|string|max:20',
            'passport'                 => 'nullable|string|max:20',
            'idType'                   => 'nullable|string|max:20',
            'dob'                      => 'nullable|date',
            'gender'                   => 'nullable|string|max:10',
            'phone'                    => 'required|string|max:16',
            'email'                    => 'nullable|email|max:160',
            'address'                  => 'nullable|string|max:255',
            'state'                    => 'nullable|string|max:120',
            'city'                     => 'nullable|string|max:120',
            'shipment'                 => 'required|array',
            'shipment.receiverName'    => 'required|string|max:120',
            'shipment.receiverPhone'   => 'nullable|string|max:20',
            'shipment.fromZone'        => 'required|string|in:' . implode(',', array_keys(self::ZONES)),
            'shipment.fromTown'        => 'nullable|string|max:120',
            'shipment.toZone'          => 'required|string|in:' . implode(',', array_keys(self::ZONES)),
            'shipment.toTown'          => 'nullable|string|max:120',
            'shipment.goodsCategory'   => 'required|string|in:' . implode(',', array_values(self::PLAN_CATEGORY)),
            'shipment.goodsDescription'=> 'nullable|string|max:500',
            'shipment.declaredValue'   => 'required|numeric|min:' . self::VALUE_MIN,
            'shipment.weightKg'        => 'nullable|numeric|min:0',
            'shipment.courierWaybill'  => 'nullable|string|max:64',
            'shipment.courierName'     => 'nullable|string|max:120',
            'paymentMethod'            => 'nullable|string|max:20',
            // The VAT-inclusive total the customer saw on the quote box. The
            // server recomputes and refuses to issue at a different figure.
            'expectedPremium'          => 'nullable|numeric|min:0',
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'error' => 'validation', 'messages' => $v->errors()], 422);
        }

        if (!$request->filled('omang') && !$request->filled('passport')) {
            return response()->json(['ok' => false, 'error' => 'omang_or_passport_required'], 422);
        }

        // ─── Partner authentication ───────────────────────────────────────
        // Alpha Transit Cover is a PARTNER-ONLY product (Bharath, 2026-09-07):
        // courier staff sign in on start with email + password and issue the
        // policy on the customer's behalf. The partner session token arrives
        // as the Bearer (minted by POST public/partner/login) and replaces the
        // customer OTP session the other instant products use — there is no
        // customer-phone match because the customer is not the one submitting.
        $bearer = $this->extractBearer($request);
        if (!$bearer) {
            return response()->json(['ok' => false, 'error' => 'partner_unauthenticated', 'message' => 'Partner sign-in required.'], 401);
        }
        $partner = $this->partnerAuth->resolveToken($bearer);
        if (!$partner) {
            return response()->json(['ok' => false, 'error' => 'partner_unauthenticated', 'message' => 'Partner session expired. Please sign in again.'], 401);
        }
        if (!$partner->company->sellsProduct($this->productId())) {
            return response()->json(['ok' => false, 'error' => 'product_not_assigned', 'message' => 'Your company is not enabled for Alpha Transit Cover.'], 403);
        }
        $sessionPhone = $this->normalize((string) $request->input('phone'));

        // ─── Server-side premium recalc (never trust the client) ──────────
        $ship  = (array) $request->input('shipment');
        $rated = $this->calculatePremium(new Request([
            'goodsCategory' => $ship['goodsCategory'] ?? '',
            'fromZone'      => $ship['fromZone'] ?? '',
            'toZone'        => $ship['toZone'] ?? '',
            'declaredValue' => $ship['declaredValue'] ?? 0,
        ]))->getData(true);
        if (empty($rated['ok'])) {
            return response()->json($rated, 422);
        }
        $total      = (float) $rated['total_premium'];
        $vatPercent = (float) $rated['vat_percent'];

        // Quote-vs-issue verification: if the customer confirmed a different
        // figure than today's rating produces (rate change, stale quote), the
        // policy is NOT issued — the FE sends them back to re-check the price.
        if ($request->filled('expectedPremium')
            && abs((float) $request->input('expectedPremium') - $total) > 0.01) {
            Log::warning('GIT create refused: quoted premium differs from rated premium', [
                'expected' => (float) $request->input('expectedPremium'),
                'rated'    => $total,
            ]);
            return response()->json([
                'ok'             => false,
                'error'          => 'premium_changed',
                'message'        => 'The premium for this shipment has changed since your quote.',
                'quoted_premium' => round((float) $request->input('expectedPremium'), 2),
                'total_premium'  => $total,
                'subtotal'       => (float) $rated['subtotal'],
                'vat_amount'     => (float) $rated['vat_amount'],
                'vat_percent'    => $vatPercent,
            ], 409);
        }

        // ─── Duplicate-submit guard ────────────────────────────────────────
        $dup = \AlphaDirect\Policy::recentInstantDuplicateByIdentity(
            $request->input('omang'),
            $request->input('passport'),
            $sessionPhone,
            $this->productId(),
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

        // GIT{YYYY}{6-digit next id} — same best-effort estimator as the
        // other instant products; the unique index is the collision backstop.
        $latestPolicyId = (int) (DB::table('policies')->max('id') ?? 0);
        $policyNumber   = 'GIT' . Carbon::now()->year . str_pad((string) ($latestPolicyId + 1), 6, '0', STR_PAD_LEFT);

        $category   = strtoupper((string) $ship['goodsCategory']);
        $planId     = $this->planIdForCategory($category);
        $coverStart = Carbon::now();
        $coverEnd   = Carbon::now()->addDays(self::COVER_DAYS);
        $value      = round((float) $ship['declaredValue'], 2);

        try {
            DB::beginTransaction();

            $customerId = DB::table('customer')->insertGetId([
                'firstName'  => $request->input('firstName'),
                'middleName' => $request->input('middleName'),
                'lastName'   => $request->input('lastName'),
                'email'      => $request->input('email'),
                'cellphone'  => $sessionPhone,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            if (DB::getSchemaBuilder()->hasTable('customer_profile')) {
                $profileCols = DB::getSchemaBuilder()->getColumnListing('customer_profile');
                DB::table('customer_profile')->insert(array_intersect_key([
                    'customer_id' => $customerId,
                    'gender'      => $this->genderCode($request->input('gender')),
                    'dob'         => $request->filled('dob') ? Carbon::parse($request->input('dob'))->format('Y-m-d') : null,
                    'omang'       => $request->input('omang'),
                    'passport'    => $request->input('passport'),
                    'id_type'     => $request->input('idType'),
                    'address'     => $request->input('address'),
                    'state'       => $request->input('state'),
                    'city'        => $request->input('city'),
                    'created_at'  => Carbon::now(),
                    'updated_at'  => Carbon::now(),
                ], array_flip($profileCols)));
            }

            $policiesCols = DB::getSchemaBuilder()->getColumnListing('policies');
            $policyId = DB::table('policies')->insertGetId(array_intersect_key([
                'customer_id'      => $customerId,
                'product_id'       => $this->productId(),
                'plan_id'          => $planId,
                'policyNumber'     => $policyNumber,
                'status'           => 0, // pending payment — DPO return activates
                'premium'          => $total,
                'annual_premium'   => $total,   // one-time premium, 14-day term
                'premium_freq'     => 'once',   // must never enter the debit-order schedule
                'payment_method'   => 'DPO',    // card only — no RealPay/PayM8 for one-off cover
                'vat_percent'      => (string) $vatPercent,
                'sum_assured'      => $value,
                'has_member'       => 0,
                'has_vehicle'      => 0,
                'leadSource'       => 'Partner',
                'agency_id'        => $partner->company->agency_id,
                'billing_day'      => (int) $coverStart->format('d'),
                'billingStartDate' => $coverStart->format('Y-m-d'),
                'term_start_date'  => $coverStart->format('Y-m-d'),
                'term_end_date'    => $coverEnd->format('Y-m-d'),
                'expiry_date'      => $coverEnd->format('Y-m-d'),
                'kyc_customer'     => '1',
                'kyc_recipient'    => '0',
                'created_at'       => Carbon::now(),
                'updated_at'       => Carbon::now(),
            ], array_flip($policiesCols)));

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('GIT create failed', ['error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => 'create_failed'], 500);
        }

        // Shipment record (V2 ops DB) — the transit risk detail the legacy
        // schema can't hold. Non-fatal: a failure is logged critical for
        // backfill, the policy itself is already durably created.
        try {
            DB::connection('mysql_system')->table('atc_shipments')->insert([
                'atc_policy_id'     => null,
                'channel'           => 'start',
                'policy_id'         => $policyId,
                'policy_number'     => $policyNumber,
                'company_code'      => $partner->company->company_code,
                'issued_by_email'   => $partner->email,
                'issued_by_name'    => $partner->name,
                'payment_status'    => 'unpaid',
                'sender_name'       => trim($request->input('firstName') . ' ' . $request->input('lastName')),
                'sender_phone'      => $sessionPhone,
                'sender_email'      => $request->input('email'),
                'receiver_name'     => $ship['receiverName'],
                'receiver_phone'    => $ship['receiverPhone'] ?? null,
                'from_zone'         => $ship['fromZone'],
                'from_town'         => $ship['fromTown'] ?? null,
                'to_zone'           => $ship['toZone'],
                'to_town'           => $ship['toTown'] ?? null,
                'goods_category'    => $category,
                'goods_description' => isset($ship['goodsDescription']) ? mb_substr((string) $ship['goodsDescription'], 0, 500) : null,
                'declared_value'    => $value,
                'weight_kg'         => isset($ship['weightKg']) ? (float) $ship['weightKg'] : null,
                'sum_insured'       => $value,
                'premium'           => $total,
                'excess'            => (float) $rated['excess'],
                'rate'              => (float) $rated['rate'],
                'currency'          => 'BWP',
                'cover_start'       => $coverStart->format('Y-m-d'),
                'cover_end'         => $coverEnd->format('Y-m-d'),
                'courier_waybill'   => $ship['courierWaybill'] ?? null,
                'service_type'      => $ship['courierName'] ?? null,
                'issued_at'         => Carbon::now(),
                'created_at'        => Carbon::now(),
                'updated_at'        => Carbon::now(),
            ]);
        } catch (\Throwable $e) {
            Log::critical('GIT shipment record failed — backfill needed', [
                'policy_number' => $policyNumber,
                'error'         => $e->getMessage(),
            ]);
        }

        try {
            app(\AlphaDirect\Services\CustomerNotificationService::class)
                ->notifyPolicyCreated($policyId, $policyNumber, $total);
        } catch (\Throwable $e) {
            Log::warning('GIT create notification failed: ' . $e->getMessage());
        }

        return response()->json([
            'ok'            => true,
            'policy_number' => $policyNumber,
            'amount_to_pay' => $total,
            'subtotal'      => (float) $rated['subtotal'],
            'vat_percent'   => $vatPercent,
            'vat_amount'    => (float) $rated['vat_amount'],
            'premium_freq'  => 'once',
            'policy'        => [
                'id'              => $policyId,
                'policyNumber'    => $policyNumber,
                'product_id'      => $this->productId(),
                'plan_id'         => $planId,
                'premium'         => $total,
                'sum_assured'     => $value,
                'term_start_date' => $coverStart->format('Y-m-d'),
                'term_end_date'   => $coverEnd->format('Y-m-d'),
            ],
            'shipment'      => [
                'zone'   => $rated['zone'],
                'excess' => (float) $rated['excess'],
            ],
        ], 201);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function planIdForCategory(string $category): ?int
    {
        $uniqueId = array_search($category, self::PLAN_CATEGORY, true);
        if ($uniqueId === false) {
            return null;
        }
        try {
            $id = DB::table('product_plans')->where('plan_unique_id', $uniqueId)->value('id');
            return $id ? (int) $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * products.region_id → regions.vat, falling back to the 14% default —
     * same resolution as ThirdPartyCarController::vatPercent().
     */
    /**
     * Resolve the Alpha Transit product id: prefer 25 when that row really is
     * ATC; otherwise fall back to slug/name (the seed's auto-increment path).
     * Cached per request. Mirrors AtcEventProcessor::productId() so both
     * channels always attribute to the same product row.
     */
    private function productId(): int
    {
        if ($this->resolvedProductId !== null) {
            return $this->resolvedProductId;
        }

        try {
            $row = DB::table('products')->where('id', self::PRODUCT_ID)->first(['id', 'name']);
            if ($row && stripos((string) $row->name, 'transit') !== false) {
                return $this->resolvedProductId = (int) $row->id;
            }

            $q  = DB::table('products');
            $id = \Illuminate\Support\Facades\Schema::hasColumn('products', 'slug')
                ? $q->where('slug', self::PRODUCT_SLUG)->orWhere('name', self::PRODUCT_NAME)->value('id')
                : $q->where('name', self::PRODUCT_NAME)->value('id');
            if ($id) {
                return $this->resolvedProductId = (int) $id;
            }
        } catch (\Throwable $e) {
            Log::error('GIT product id resolution failed: ' . $e->getMessage());
        }

        // Last resort — the seeded default. Only reachable when the products
        // table is unreadable; better a pinned id than a hard 500 on quote.
        return $this->resolvedProductId = self::PRODUCT_ID;
    }

    private function vatPercent(): float
    {
        try {
            $regionId = DB::table('products')->where('id', $this->productId())->value('region_id');
            if ($regionId) {
                $vat = DB::table('regions')->where('id', $regionId)->value('vat');
                if ($vat !== null && $vat !== '') {
                    return (float) $vat;
                }
            }
        } catch (\Throwable $e) {
            // fall through to default
        }
        return self::VAT_PERCENT;
    }

    private function extractBearer(Request $request): ?string
    {
        $token = trim((string) $request->bearerToken());
        return $token !== '' ? $token : null;
    }

    private function normalize(string $v): string
    {
        $digits = preg_replace('/\D+/', '', $v);
        return substr($digits, -8);
    }

    private function genderCode(?string $gender): ?string
    {
        if (!$gender) {
            return null;
        }
        $g = strtoupper(substr(trim($gender), 0, 1));
        return in_array($g, ['M', 'F'], true) ? $g : null;
    }
}
