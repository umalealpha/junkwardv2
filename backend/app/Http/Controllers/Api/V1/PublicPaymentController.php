<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\PublicOtpService;
use AlphaDirect\Services\PublicPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public payment-initiation endpoints for start.alphadirect.co.bw.
 *
 * Auth: Bearer session token from a successful OTP / magic-link verify
 * (purpose=payment_authorize). The session's cellphone must match the
 * policy's customer cellphone — enforced inside PublicPaymentService.
 *
 *   POST /api/v1/public/payments/dpo/initiate
 *     body: { policy_number, amount, context, email }
 *     200 → { ok: true, gateway: 'dpo', reference, redirect_url, expires_at }
 *     401 → { ok: false, error: 'session_required' | 'session_invalid_or_expired' }
 *     403 → { ok: false, error: 'session_policy_mismatch' }
 *     422 → { ok: false, error: 'gateway_declined' | 'gateway_misconfigured', message }
 *     503 → { ok: false, error: 'gateway_unreachable' }
 *
 *   POST /api/v1/public/payments/ngenius/initiate
 *     Same shape as DPO. Returns redirect_url to N-Genius hosted page.
 *
 *   POST /api/v1/public/payments/vcs/initiate
 *     Same shape, but VCS returns redirect_html (not a URL) — FE writes
 *     it to a hidden iframe / data: URL. redirect_url is null in this path.
 *
 * The FE consumes redirect_url with window.location.assign() (or for VCS
 * renders redirect_html in a frame).
 *
 * RealPay direct-debit is at /api/v1/public/realpay/initiate (separate
 * namespace because it's a contract-create, not a card-redirect).
 */
class PublicPaymentController extends Controller
{
    public function __construct(
        private PublicOtpService     $otp,
        private PublicPaymentService $payments,
    ) {}

    public function initiateDpo(Request $request): JsonResponse
    {
        return $this->dispatchGateway($request, 'dpo');
    }

    public function initiateNgenius(Request $request): JsonResponse
    {
        return $this->dispatchGateway($request, 'ngenius');
    }

    public function initiateVcs(Request $request): JsonResponse
    {
        return $this->dispatchGateway($request, 'vcs');
    }

    /**
     * Shared dispatch: validate, auth via OTP Bearer, delegate to the
     * gateway's initiate* on PublicPaymentService. All three gateways
     * share the same body contract — only the underlying service call
     * differs in how it talks to the upstream provider.
     */
    private function dispatchGateway(Request $request, string $gateway): JsonResponse
    {
        $request->validate([
            'policy_number' => 'required|string|max:64',
            'amount'        => 'required|numeric|min:0.01|max:1000000',
            'context'       => 'required|string|in:' . implode(',', PublicPaymentService::CONTEXTS),
            'email'         => 'required|email|max:160',
        ]);

        $bearer = $this->extractBearer($request);
        if (!$bearer) return response()->json(['ok' => false, 'error' => 'session_required'], 401);
        $policyNumber = $request->input('policy_number');

        // Two callers: (a) the customer, holding an OTP session whose cellphone
        // must match the policy; (b) partner-company staff (Alpha Transit
        // Cover), holding a partner session — allowed only for a policy their
        // own company issued (atc_shipments.company_code). For (b) we build the
        // session envelope from the policy's customer so the downstream
        // cellphone-match checks in PublicPaymentService stay unchanged.
        $session = $this->otp->validateToken($bearer);
        if (!$session) {
            $session = $this->partnerSessionFor($bearer, $policyNumber);
        }
        if (!$session) return response()->json(['ok' => false, 'error' => 'session_invalid_or_expired'], 401);

        $amount       = (float) $request->input('amount');
        $context      = $request->input('context');
        $email        = $request->input('email');

        $result = match ($gateway) {
            'dpo'     => $this->payments->initiateDpo($session, $policyNumber, $amount, $context, $email, $request->ip(), $request->headers->get('origin') ?: $request->headers->get('referer')),
            'ngenius' => $this->payments->initiateNgenius($session, $policyNumber, $amount, $context, $email),
            'vcs'     => $this->payments->initiateVcs($session, $policyNumber, $amount, $context, $email),
        };

        $status = match ($result['error'] ?? null) {
            null                          => 200,
            'session_policy_mismatch'     => 403,
            'policy_not_found'            => 404,
            'gateway_unreachable'         => 503,
            'already_paid'                => 409,
            'gateway_misconfigured',
            'gateway_bad_response',
            'gateway_declined',
            'gateway_not_allowed',
            'invalid_amount',
            'invalid_context'             => 422,
            default                       => 400,
        };

        return response()->json($result, $status);
    }

    /**
     * Resolve a partner bearer to a payment session for a policy the
     * partner's company issued. Null when the token is not a partner token
     * or the policy is not theirs (caller returns 401 either way — no leak).
     */
    private function partnerSessionFor(string $bearer, string $policyNumber): ?array
    {
        $partner = app(\AlphaDirect\Services\Partner\PartnerAuthService::class)->resolveToken($bearer);
        if (!$partner) return null;
        $ship = \Illuminate\Support\Facades\DB::connection('mysql_system')->table('atc_shipments')
            ->where('company_code', $partner->company->company_code)
            ->where('policy_number', $policyNumber)
            ->orderByDesc('id')->first(['policy_id']);
        if (!$ship) {
            \Illuminate\Support\Facades\Log::warning('public_payments.partner_policy_mismatch', ['company' => $partner->company->company_code, 'policy' => $policyNumber]);
            return null;
        }
        $cell = \Illuminate\Support\Facades\DB::table('policies')->join('customer', 'customer.id', '=', 'policies.customer_id')
            ->where('policies.id', $ship->policy_id)->value('customer.cellphone');
        return ['cellphone' => (string) $cell, 'purpose' => 'partner_payment', 'partner_user_id' => $partner->id];
    }

    private function extractBearer(Request $request): ?string
    {
        $auth = $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) return trim($m[1]);
        return null;
    }
}
