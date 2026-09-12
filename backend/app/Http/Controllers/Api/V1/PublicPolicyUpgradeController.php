<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Controllers\FrontendPay\CustomerController;
use AlphaDirect\Policy;
use AlphaDirect\Services\PublicOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Fixed-tier "Gold" upgrades for start-v2 — TP → TP Gold, ADI → ADI Gold,
 * Cellphone P49 → P99.
 *
 * This is the V2 session-gated front door for what the legacy site exposed
 * as the unauthenticated POST /api/{TpToTpGold,AdiToAdiGold,cellphoneupgrade}
 * endpoints (FrontendPay\CustomerController). Those endpoints carry the
 * proven business logic — premium recompute for the target plan, the
 * "payment method must match the existing mandate" rule, PolicyUpgrade
 * audit row, document regen, and the per-gateway payment handoff — so we
 * REUSE them verbatim rather than re-deriving the rules here. This wrapper
 * only adds:
 *
 *   1. Auth — a Bearer session token (same model as PublicPolicyController
 *      ::detail). The token's cellphone MUST match the policy holder's, so
 *      a token can only upgrade the holder's own policy.
 *   2. Field mapping — the FE posts snake_case V2 keys (agent_id,
 *      payment_method, bank_name, …); the legacy methods read the legacy
 *      names (agentId, payment_method, bankName, …). We translate.
 *   3. Response normalisation — the legacy methods return assorted
 *      { status, message, url? } / gateway shapes. We collapse them to the
 *      single { ok, policy_number, redirect_url?, message } contract the
 *      FE upgradesApi.createUpgrade expects.
 *
 * NOTE: a successful upgrade is an outward-facing, hard-to-reverse action —
 * it mutates the live policy (plan_id / premium / VAT), writes a banking
 * row, regenerates AND emails/SMSs the policy document, and may initiate a
 * gateway payment. Only call it for a genuinely-verified holder.
 */
class PublicPolicyUpgradeController extends Controller
{
    public function __construct(private PublicOtpService $otp) {}

    /** upgrade_kind → the legacy CustomerController method that performs it. */
    private const KIND_METHOD = [
        'tp_gold'   => 'upgradeTpToTpGold',
        'adi_gold'  => 'upgradeAdiToAgiGold',
        'cellphone' => 'cellphoneUpgrade',
    ];

    /**
     * POST /api/v1/public/policies/{policyNumber}/upgrade
     * Bearer: payment_authorize session token (cellphone-bound).
     * Body: { upgrade_kind, agent_id?, payment_method?, bank_name?,
     *         branch_code?, account_type?, account_number?, dpo_email?, … }
     *
     * Returns: { ok, policy_number?, redirect_url?, message?, error? }
     */
    public function upgrade(Request $request, string $policyNumber): JsonResponse
    {
        $validated = $request->validate([
            'upgrade_kind'   => 'required|string|in:tp_gold,adi_gold,cellphone',
            'payment_method' => 'nullable|string|in:DPO,RealPay,PayM8,N-Genius,VCS',
            'agent_id'       => 'nullable|string|max:10',
            'bank_name'      => 'nullable|string|max:120',
            'branch_code'    => 'nullable|string|max:60',
            'account_type'   => 'nullable|string|max:20',
            'account_number' => 'nullable|string|max:40',
            'dpo_email'      => 'nullable|email|max:190',
        ]);

        // ── 1. Auth: valid session token bound to the holder's cellphone ──
        $bearer = $this->extractBearer($request);
        if (!$bearer) {
            return response()->json(['ok' => false, 'error' => 'session_required'], 401);
        }
        $session = $this->otp->validateToken($bearer);
        if (!$session) {
            return response()->json(['ok' => false, 'error' => 'session_invalid_or_expired'], 401);
        }

        $policy = Policy::with('customer:id,cellphone')
            ->where('policyNumber', $policyNumber)
            ->first();
        if (!$policy) {
            return response()->json(['ok' => false, 'error' => 'not_found'], 404);
        }

        $customerCell = $this->normalize((string) ($policy->customer->cellphone ?? ''));
        $sessionCell  = $this->normalize((string) ($session['cellphone'] ?? ''));
        if (!$customerCell || $customerCell !== $sessionCell) {
            Log::warning('public_policies.upgrade_token_mismatch', [
                'policy'  => $policyNumber,
                'session' => substr(hash('sha256', $sessionCell), 0, 8),
            ]);
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        // ── 2. Translate V2 snake_case → the legacy method's field names ──
        $kind   = $validated['upgrade_kind'];
        $method = self::KIND_METHOD[$kind];
        $legacy = new Request($request->all());
        $legacy->merge([
            'policyNumber'   => $policyNumber,
            'agentId'        => $request->input('agent_id'),
            'payment_method' => $request->input('payment_method'),
            // Legacy upgradeTpToTpGold reads BOTH `payment_method` and the
            // mis-cased `Payment_method` (its PayM8 branch checks the latter),
            // so set both to be safe.
            'Payment_method' => $request->input('payment_method'),
            'bankName'       => $request->input('bank_name'),
            'branchCode'     => $request->input('branch_code'),
            'accountNumber'  => $request->input('account_number'),
            'accountType'    => $request->input('account_type'),
            'dpo_email'      => $request->input('dpo_email'),
        ]);

        Log::info('public_policies.upgrade_start', [
            'policy'  => $policyNumber,
            'kind'    => $kind,
            'method'  => $request->input('payment_method'),
            'session' => substr(hash('sha256', $sessionCell), 0, 8),
        ]);

        // ── 3. Run the proven legacy upgrade + normalise the response ─────
        // Resolve through the container so the legacy controller's six
        // interface constructor deps are auto-wired.
        try {
            /** @var CustomerController $customerController */
            $customerController = app(CustomerController::class);
            $result = $customerController->{$method}($legacy);
        } catch (\Throwable $e) {
            Log::error('public_policies.upgrade_failed', [
                'policy' => $policyNumber,
                'kind'   => $kind,
                'error'  => $e->getMessage(),
            ]);
            return response()->json([
                'ok'    => false,
                'error' => 'upgrade_failed',
            ], 500);
        }

        $response = $this->normalise($result, $policy->policyNumber);

        // On success, bust the policy caches so the admin policy view (and
        // any rememberPolicy/-ByNumber reader) immediately reflects the new
        // plan + premium instead of the pre-upgrade snapshot. The Policy
        // model's saved-observer already does this on the save inside the
        // legacy method, but we invalidate explicitly here too so the
        // upgrade flow is deterministic regardless of how the BE saved.
        if ($response->getData(true)['ok'] ?? false) {
            \AlphaDirect\Services\CacheService::forgetPolicy($policy->id);
            \Illuminate\Support\Facades\Cache::forget("policy_number_{$policy->policyNumber}");
        }

        return $response;
    }

    /**
     * Collapse the legacy method's varied return (a JsonResponse with
     * { status:"success"|"error", message, url? }, or a gateway
     * redirect/Response) into the FE's { ok, policy_number, redirect_url?,
     * message } shape.
     */
    private function normalise($result, string $policyNumber): JsonResponse
    {
        $httpStatus = 200;
        $data = [];

        if ($result instanceof \Symfony\Component\HttpFoundation\Response) {
            $httpStatus = $result->getStatusCode();
            if (method_exists($result, 'getData')) {
                $decoded = $result->getData(true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
            // A gateway redirect (RealPay / N-Genius sometimes return one)
            // — surface its target as redirect_url.
            if ($result instanceof \Symfony\Component\HttpFoundation\RedirectResponse) {
                $data['url'] = $result->getTargetUrl();
            }
        }

        $legacyStatus = $data['status'] ?? null;
        $ok = $httpStatus >= 200 && $httpStatus < 300 && $legacyStatus !== 'error';

        $out = [
            'ok'            => $ok,
            'policy_number' => $data['policyNumber'] ?? $policyNumber,
            // url (legacy) / redirect_url (gateway) — null when the upgrade
            // completes without a hosted-payment redirect (e.g. DPO keeps the
            // existing card mandate). The FE shows a "done" card in that case.
            'redirect_url'  => $data['url'] ?? $data['redirect_url'] ?? null,
            'message'       => $data['message'] ?? null,
        ];
        if (!$ok) {
            $out['error'] = $data['message'] ?? 'upgrade_failed';
        }

        return response()->json($out, $ok ? 200 : ($httpStatus >= 400 ? $httpStatus : 400));
    }

    private function extractBearer(Request $request): ?string
    {
        $auth = $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /** BW cellphone normaliser — strips +267/267 prefix + non-digits.
     *  Mirrors PublicPolicyController::normalize so the match is identical. */
    private function normalize(string $cellphone): string
    {
        $digits = preg_replace('/\D/', '', $cellphone);
        if (str_starts_with($digits, '267') && strlen($digits) === 11) {
            return substr($digits, 3);
        }
        return $digits;
    }
}
