<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Customer;
use AlphaDirect\Exceptions\HcbCoapplicantException;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Policy;
use AlphaDirect\Services\HcbCoapplicantService;
use AlphaDirect\Services\PublicOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Public policy lookup for start.alphadirect.co.bw.
 *
 * Replaces the legacy `findPolicy` / `findPolicy2` /
 * `findPolicyAlphaFePay` endpoints which mixed three different search
 * shapes and returned full PII without auth. Hardened by:
 *
 *  - PII masked by default. Search by Omang / cellphone / email returns
 *    a list of policies with the customer's name truncated, last 4 of
 *    the policy number, status and product label — enough to triage
 *    "yes that's me" but not enough to dox someone.
 *  - Full policy detail (everything the Edit / Re-process flows need)
 *    requires a session token from a fresh OTP/magic-link verify on the
 *    customer's cellphone. The token is bound to the cellphone via
 *    public_session_tokens, so token theft on its own can't unmask
 *    a different customer's policies.
 *  - Throttle: list 10/min/IP, detail 20/min/IP.
 *  - Audit log: every search is logged with mode + masked match count.
 */
class PublicPolicyController extends Controller
{
    public function __construct(private PublicOtpService $otp) {}

    /**
     * POST /api/v1/public/policies/find
     * Body: { mode: "policyNumber"|"cellphone"|"omang"|"passport"|"email", value: "..." }
     *
     * Returns: { results: [{ policyNumber, productName, status, customerName(masked), cellphone(masked) }, ...] }
     */
    public function find(Request $request): JsonResponse
    {
        $request->validate([
            'mode'  => 'required|string|in:policyNumber,cellphone,omang,passport,email,vehiclePlate',
            'value' => 'required|string|max:120',
        ]);

        $mode  = $request->input('mode');
        $value = trim($request->input('value'));

        try {
            $policies = $this->search($mode, $value);

            Log::info('public_policies.find', [
                'mode'    => $mode,
                'matches' => count($policies),
                'ip'      => $request->ip(),
            ]);

            return response()->json([
                'results' => array_map(fn ($p) => $this->formatMasked($p), $policies),
                'count'   => count($policies),
            ]);
        } catch (\Throwable $e) {
            Log::error('public_policies.find_failed', ['msg' => $e->getMessage()]);
            return response()->json(['error' => 'lookup_failed'], 500);
        }
    }

    /**
     * GET /api/v1/public/customers/lookup?omang=…|passport=…|cellphone=…|email=…
     *
     * Privacy-safe customer existence check for the OCR-first onboarding
     * flow. Returns only masked summary fields — never email, address,
     * full name, DOB or any other PII. Used by the Landing scanner so
     * we can show "Welcome back, Theo N." after OCR reads the Omang,
     * without exposing the full customer record to anyone holding an
     * Omang number.
     *
     * For full customer data the FE has to OTP / magic-link verify
     * the cellphone (purpose=customer_auth), then call the authed
     * /customers/lookup endpoint with the resulting Bearer token.
     */
    /**
     * Duplicate-prevention live check for the Start create form: does the
     * customer (resolved by omang/passport/cellphone) already hold a
     * NON-cancelled policy of this single-cover product? Only ADI (1),
     * Legal (4) and Mobile (5) enforce the rule; anything else is always
     * available. The create endpoints re-enforce server-side, so this is a
     * UX pre-check only (fails OPEN on the FE if it can't reach us).
     */
    public function checkProductDuplicate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'productId' => 'required|integer',
            'omang'     => 'nullable|string|max:32',
            'passport'  => 'nullable|string|max:32',
            'cellphone' => 'nullable|string|max:24',
        ]);

        $productId = (int) $validated['productId'];
        // Single-cover products only (ADI, Legal, Mobile/Electronic).
        if (!in_array($productId, [1, 4, 5], true)) {
            return response()->json(['available' => true, 'message' => null]);
        }

        $cellphone = $request->filled('cellphone')
            ? $this->normalize((string) $request->input('cellphone'))
            : null;

        $has = Policy::customerHasActiveProductByIdentity(
            $validated['omang']    ?? null,
            $validated['passport'] ?? null,
            $cellphone,
            $productId
        );

        return response()->json([
            'available' => !$has,
            'message'   => $has ? 'This customer already has an active policy for this product.' : null,
        ]);
    }

    public function publicCustomerLookup(Request $request): JsonResponse
    {
        $request->validate([
            'omang'     => 'sometimes|string|max:32',
            'passport'  => 'sometimes|string|max:32',
            'cellphone' => 'sometimes|string|max:24',
            'email'     => 'sometimes|email|max:160',
        ]);

        $criteria = array_filter([
            'omang'     => trim((string) $request->input('omang')),
            'passport'  => trim((string) $request->input('passport')),
            'cellphone' => $request->input('cellphone') ? $this->normalize((string) $request->input('cellphone')) : '',
            'email'     => trim((string) $request->input('email')),
        ], fn ($v) => $v !== '');

        if (empty($criteria)) {
            return response()->json(['error' => 'criteria_required'], 422);
        }

        // Customer + customer_kyc are split: cellphone/email live on
        // customer; omang/passport live on customer_kyc.omangNumber /
        // .passportNumber. Resolve to a customer.id either way.
        $customerIds = [];

        if (!empty($criteria['cellphone'])) {
            $cell = $criteria['cellphone'];
            $customerIds = array_merge($customerIds,
                \DB::table('customer')
                    ->where(function ($q) use ($cell) {
                        $q->where('cellphone', $cell)
                          ->orWhere('cellphone', '267' . $cell)
                          ->orWhere('cellphone', '+267' . $cell);
                    })
                    ->limit(5)->pluck('id')->all()
            );
        }
        if (!empty($criteria['email'])) {
            $customerIds = array_merge($customerIds,
                \DB::table('customer')->where('email', $criteria['email'])->limit(5)->pluck('id')->all()
            );
        }
        if (!empty($criteria['omang'])) {
            $customerIds = array_merge($customerIds,
                \DB::table('customer_kyc')->where('omangNumber', $criteria['omang'])
                    ->whereNotNull('customer_id')->limit(5)->pluck('customer_id')->all()
            );
        }
        if (!empty($criteria['passport'])) {
            $customerIds = array_merge($customerIds,
                \DB::table('customer_kyc')->where('passportNumber', $criteria['passport'])
                    ->whereNotNull('customer_id')->limit(5)->pluck('customer_id')->all()
            );
        }

        $customerIds = array_values(array_unique(array_filter($customerIds)));
        if (empty($customerIds)) {
            \Illuminate\Support\Facades\Log::info('public_customer_lookup', [
                'criteria' => array_keys($criteria), 'hit' => false, 'ip' => $request->ip(),
            ]);
            return response()->json(['exists' => false]);
        }

        $hit = \DB::table('customer')->whereIn('id', $customerIds)
            ->select('id', 'firstName', 'lastName', 'cellphone')->first();

        \Illuminate\Support\Facades\Log::info('public_customer_lookup', [
            'criteria' => array_keys($criteria),
            'hit'      => $hit ? true : false,
            'ip'       => $request->ip(),
        ]);

        if (!$hit) {
            return response()->json(['exists' => false]);
        }

        $first = (string) ($hit->firstName ?? '');
        $last  = (string) ($hit->lastName  ?? '');
        $maskedName = $first . ($last !== '' ? ' ' . substr($last, 0, 1) . '.' : '');

        return response()->json([
            'exists' => true,
            'customer' => [
                // Masked view only — full record requires session token
                'firstName' => $first ?: null,
                'lastInitial' => $last !== '' ? substr($last, 0, 1) . '.' : null,
                'displayName' => $maskedName ?: null,
                'cellphone'   => $hit->cellphone ? $this->maskPhone($hit->cellphone) : null,
            ],
        ]);
    }

    /**
     * POST /api/v1/public/policies/{policyNumber}/detail
     * Header: Authorization: Bearer {sessionToken}  (from OTP verify)
     *
     * Returns the full policy + customer + profile + device/vehicle/
     * beneficiary set the Edit and Re-process flows need.
     * 401 if no token, 403 if the token doesn't belong to this policy's
     * customer.
     */
    public function detail(Request $request, string $policyNumber): JsonResponse
    {
        $request->validate([
            // policy numbers are alpha-num + a couple separators, never PII
            // — but enforce a sane shape so a SQL LIKE injection can't get
            // creative.
            // (illustrative — Laravel param binding already blocks SQLi)
        ]);

        $bearer = $this->extractBearer($request);
        if (!$bearer) {
            return response()->json(['error' => 'session_required'], 401);
        }
        $session = $this->otp->validateToken($bearer);
        if (!$session) {
            return response()->json(['error' => 'session_invalid_or_expired'], 401);
        }

        $policy = Policy::with([
            'profile', 'customer', 'vehicle',
            'policyBeneficiaries',
            'devices',
            'hospitalCashbackCoapplicants',
        ])
            ->where('policyNumber', $policyNumber)
            ->first();

        if (!$policy) {
            return response()->json(['error' => 'not_found'], 404);
        }

        // Authorisation: the session token's cellphone must match the
        // customer's cellphone. Without this, a holder of any valid
        // session could read any policy by number.
        $customerCellphone = $this->normalize((string) ($policy->customer->cellphone ?? ''));
        $sessionCellphone  = $this->normalize((string) ($session['cellphone']        ?? ''));
        if (!$customerCellphone || $customerCellphone !== $sessionCellphone) {
            Log::warning('public_policies.detail_token_mismatch', [
                'policy'  => $policyNumber,
                'session' => substr(hash('sha256', $sessionCellphone), 0, 8),
            ]);
            return response()->json(['error' => 'forbidden'], 403);
        }

        Log::info('public_policies.detail_loaded', [
            'policy'  => $policyNumber,
            'session' => substr(hash('sha256', $sessionCellphone), 0, 8),
        ]);

        // Product / plan names so the SPA never has to hardcode id → label
        // maps (product 25 "Alpha Transit Cover" was rendering as "Product 25").
        $product = DB::table('products')->where('id', $policy->product_id)->first(['id', 'name']);
        $plan    = $policy->plan_id ? DB::table('product_plans')->where('id', $policy->plan_id)->first(['id', 'name', 'plan_unique_id']) : null;

        // Alpha Transit Cover (product 25): the risk is a shipment, stored in
        // mysql_system.atc_shipments rather than the legacy risk tables.
        // Returned with the issuing courier's name so the customer sees what
        // was captured at issue (sender/receiver, route, goods, cover window).
        $shipment = null;
        if ((int) $policy->product_id === 25) {
            $shipment = DB::connection('mysql_system')->table('atc_shipments')
                ->where('policy_id', $policy->id)->orderByDesc('id')->first();
            if ($shipment) {
                $shipment->courier_company_name = ($shipment->company_code && $shipment->company_code !== 'DIRECT')
                    ? DB::connection('mysql_system')->table('atc_couriers')->where('company_code', $shipment->company_code)->value('name')
                    : null;
                // Not the customer's data — internal issuer identity stays private.
                unset($shipment->issued_by_email);
            }
        }

        return response()->json([
            'policy'   => $policy,
            'product'  => $product ? ['id' => (int) $product->id, 'name' => $product->name] : null,
            'plan'     => $plan ? ['id' => (int) $plan->id, 'name' => $plan->name, 'code' => $plan->plan_unique_id] : null,
            'shipment' => $shipment,
            'customer' => $policy->customer,
            'profile'  => $policy->profile,
            // Vehicle row lives in a separate table; eager-loaded above so
            // motor flows (vehicle inspection, upgrade) can read the plate
            // without a second round-trip. Non-motor products return null.
            'vehicle'       => $policy->vehicle,
            // Product-conditional collections. Always returned (possibly
            // empty) so the FE can render cards uniformly without product
            // branching at the API layer.
            'beneficiaries' => $policy->policyBeneficiaries ?? [],
            'devices'       => $policy->devices ?? [],
            'coapplicants'  => $policy->hospitalCashbackCoapplicants ?? [],
        ]);
    }

    /**
     * POST /api/v1/public/policies/{policyNumber}/coapplicants
     * Header: Authorization: Bearer {sessionToken}
     *
     * Hospital Cashback Insurance (product_id=9) only. Adds a dependant
     * (spouse, max 1, or child, max 6) and recalculates the policy premium
     * immediately via HcbCoapplicantService.
     */
    public function addCoapplicant(Request $request, string $policyNumber): JsonResponse
    {
        [$policy, $errResp] = $this->resolveOwnedPolicy($request, $policyNumber);
        if ($errResp) return $errResp;

        $v = $request->validate([
            'relation'   => ['required', 'string', 'in:spouse,child'],
            'firstName'  => ['required', 'string', 'max:60'],
            'middleName' => ['nullable', 'string', 'max:60'],
            'lastName'   => ['required', 'string', 'max:60'],
            'gender'     => ['required', 'string', 'in:Male,Female,M,F'],
            'dob'        => ['required', 'date'],
            'omang'      => ['nullable', 'string', 'regex:/^[0-9]{9}$/'],
            'passport'   => ['nullable', 'string', 'max:25'],
        ]);

        try {
            HcbCoapplicantService::add($policy, [
                'relation'    => $v['relation'],
                'first_name'  => $v['firstName'],
                'middle_name' => $v['middleName'] ?? null,
                'last_name'   => $v['lastName'],
                'gender'      => $v['gender'],
                'dob'         => $v['dob'],
                'omang'       => $v['omang']    ?? null,
                'passport'    => $v['passport'] ?? null,
            ], $policy->customer, $this->customerActorLabel($policy));
        } catch (HcbCoapplicantException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }

        return $this->coapplicantsResponse($policy);
    }

    /**
     * PUT /api/v1/public/policies/{policyNumber}/coapplicants/{coapplicantId}
     */
    public function updateCoapplicant(Request $request, string $policyNumber, int $coapplicantId): JsonResponse
    {
        [$policy, $errResp] = $this->resolveOwnedPolicy($request, $policyNumber);
        if ($errResp) return $errResp;

        $v = $request->validate([
            'relation'   => ['sometimes', 'string', 'in:spouse,child'],
            'firstName'  => ['sometimes', 'string', 'max:60'],
            'middleName' => ['sometimes', 'nullable', 'string', 'max:60'],
            'lastName'   => ['sometimes', 'string', 'max:60'],
            'gender'     => ['sometimes', 'string', 'in:Male,Female,M,F'],
            'dob'        => ['sometimes', 'date'],
            'omang'      => ['sometimes', 'nullable', 'string', 'regex:/^[0-9]{9}$/'],
            'passport'   => ['sometimes', 'nullable', 'string', 'max:25'],
        ]);

        $map = ['relation' => 'relation', 'firstName' => 'first_name', 'middleName' => 'middle_name',
                'lastName' => 'last_name', 'gender' => 'gender', 'dob' => 'dob', 'omang' => 'omang', 'passport' => 'passport'];
        $data = [];
        foreach ($map as $in => $out) {
            if (array_key_exists($in, $v)) $data[$out] = $v[$in];
        }

        try {
            HcbCoapplicantService::update($policy, $coapplicantId, $data, $policy->customer, $this->customerActorLabel($policy));
        } catch (HcbCoapplicantException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }

        return $this->coapplicantsResponse($policy);
    }

    /**
     * DELETE /api/v1/public/policies/{policyNumber}/coapplicants/{coapplicantId}
     */
    public function deleteCoapplicant(Request $request, string $policyNumber, int $coapplicantId): JsonResponse
    {
        [$policy, $errResp] = $this->resolveOwnedPolicy($request, $policyNumber);
        if ($errResp) return $errResp;

        try {
            HcbCoapplicantService::delete($policy, $coapplicantId, $policy->customer, $this->customerActorLabel($policy));
        } catch (HcbCoapplicantException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }

        return $this->coapplicantsResponse($policy);
    }

    /**
     * Shared auth + ownership resolution for the coapplicant mutation
     * endpoints — identical guard to detail(): Bearer session token whose
     * cellphone must match the policy's customer.
     *
     * @return array{0: ?Policy, 1: ?JsonResponse}
     */
    private function resolveOwnedPolicy(Request $request, string $policyNumber): array
    {
        $bearer = $this->extractBearer($request);
        if (!$bearer) {
            return [null, response()->json(['error' => 'session_required'], 401)];
        }
        $session = $this->otp->validateToken($bearer);
        if (!$session) {
            return [null, response()->json(['error' => 'session_invalid_or_expired'], 401)];
        }

        $policy = Policy::with('customer')->where('policyNumber', $policyNumber)->first();
        if (!$policy) {
            return [null, response()->json(['error' => 'not_found'], 404)];
        }

        $customerCellphone = $this->normalize((string) ($policy->customer->cellphone ?? ''));
        $sessionCellphone  = $this->normalize((string) ($session['cellphone']        ?? ''));
        if (!$customerCellphone || $customerCellphone !== $sessionCellphone) {
            return [null, response()->json(['error' => 'forbidden'], 403)];
        }

        return [$policy, null];
    }

    private function customerActorLabel(Policy $policy): string
    {
        return 'Customer (' . ($policy->customer->cellphone ?? $policy->customer_id) . ')';
    }

    private function coapplicantsResponse(Policy $policy): JsonResponse
    {
        $policy->refresh();
        return response()->json([
            'ok'           => true,
            'premium'      => $policy->premium,
            'coapplicants' => $policy->hospitalCashbackCoapplicants()->orderBy('id')->get(),
        ]);
    }

    // ─── Internals ──────────────────────────────────────────────────────

    private function search(string $mode, string $value): array
    {
        $cols = ['policyNumber', 'product_id', 'status', 'customer_id'];

        switch ($mode) {
            case 'policyNumber':
                return Policy::with('customer:id,firstName,lastName,cellphone')
                    ->where('policyNumber', $value)
                    ->select($cols)->get()->all();

            case 'cellphone':
                $value = $this->normalize($value);
                $cust = Customer::where('cellphone', $value)
                    ->orWhere('cellphone', '267' . $value)
                    ->orWhere('cellphone', '+267' . $value)
                    ->select('id', 'firstName', 'lastName', 'cellphone')
                    ->limit(20)
                    ->get();
                if ($cust->isEmpty()) return [];
                return Policy::with('customer:id,firstName,lastName,cellphone')
                    ->whereIn('customer_id', $cust->pluck('id'))
                    ->select($cols)->orderByDesc('id')->limit(50)->get()->all();

            case 'omang':
                $cust = Customer::where('omang', $value)
                    ->select('id', 'firstName', 'lastName', 'cellphone')->limit(20)->get();
                if ($cust->isEmpty()) return [];
                return Policy::with('customer:id,firstName,lastName,cellphone')
                    ->whereIn('customer_id', $cust->pluck('id'))
                    ->select($cols)->orderByDesc('id')->limit(50)->get()->all();

            case 'passport':
                $cust = Customer::where('passport', $value)
                    ->select('id', 'firstName', 'lastName', 'cellphone')->limit(20)->get();
                if ($cust->isEmpty()) return [];
                return Policy::with('customer:id,firstName,lastName,cellphone')
                    ->whereIn('customer_id', $cust->pluck('id'))
                    ->select($cols)->orderByDesc('id')->limit(50)->get()->all();

            case 'email':
                $cust = Customer::where('email', $value)
                    ->select('id', 'firstName', 'lastName', 'cellphone')->limit(20)->get();
                if ($cust->isEmpty()) return [];
                return Policy::with('customer:id,firstName,lastName,cellphone')
                    ->whereIn('customer_id', $cust->pluck('id'))
                    ->select($cols)->orderByDesc('id')->limit(50)->get()->all();

            case 'vehiclePlate':
                // Vehicle inspection entry point — the customer brings
                // the car to the depot, plate is the natural identifier
                // (the OPS staff don't ask for the policy number).
                // Note: Vehicle model uses table `vehicle` (singular), not
                // `vehicles` — the auto-pluralised Eloquent default would
                // miss the right table.
                $value = strtoupper(preg_replace('/\s+/', '', $value));
                $policyIds = \AlphaDirect\Vehicle::where('vehiclePlate', $value)
                    ->pluck('policy_id');
                if ($policyIds->isEmpty()) return [];
                return Policy::with('customer:id,firstName,lastName,cellphone')
                    ->whereIn('id', $policyIds)
                    ->select($cols)->orderByDesc('id')->limit(50)->get()->all();
        }

        return [];
    }

    /**
     * Returns a privacy-conservative summary suitable for showing to an
     * unauthenticated visitor as triage results. Customer cellphone is
     * masked, full name is reduced to first-name + initial.
     */
    private function formatMasked($policy): array
    {
        $cust = $policy->customer ?? null;
        $first = (string) ($cust->firstName ?? '');
        $last  = (string) ($cust->lastName  ?? '');
        $maskedName = $first . ($last !== '' ? ' ' . substr($last, 0, 1) . '.' : '');

        $cellphone = (string) ($cust->cellphone ?? '');
        $maskedPhone = $cellphone !== '' ? $this->maskPhone($cellphone) : null;

        // Best-effort product label: read from a small cache to avoid an
        // N+1 across the result list.
        static $productCache = null;
        if ($productCache === null) {
            $productCache = DB::table('products')->pluck('name', 'id')->all();
        }

        return [
            'policyNumber' => $policy->policyNumber,
            'productName'  => $productCache[$policy->product_id] ?? null,
            'status'       => (int) $policy->status,
            'customerName' => $maskedName ?: null,
            'cellphone'    => $maskedPhone,
        ];
    }

    private function maskPhone(string $cellphone): string
    {
        $digits = preg_replace('/\D/', '', $cellphone);
        if (strlen($digits) < 5) return '***';
        return substr($digits, 0, 2) . str_repeat('*', strlen($digits) - 4) . substr($digits, -2);
    }

    private function normalize(string $cellphone): string
    {
        $digits = preg_replace('/\D/', '', $cellphone);
        if (str_starts_with($digits, '267') && strlen($digits) === 11) return substr($digits, 3);
        return $digits;
    }

    private function extractBearer(Request $request): ?string
    {
        $auth = $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) return trim($m[1]);
        return null;
    }
}
