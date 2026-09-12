<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\ClaimTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public, unauthenticated claimant self-service status API (Claims Tracker
 * Phase 2). No admin auth — the claimant proves ownership with an OTP sent to
 * the claim's registered contact, and receives a session scoped to that one
 * claim.
 *
 * Every endpoint is:
 *   - GATED by the `claimant_tracking` runtime flag (default OFF). When off,
 *     the whole surface 404s so the feature is invisible until armed.
 *   - RATE-LIMITED at the route (see routes/api_v1.php).
 *   - FAIL-CLOSED + non-enumerating (generic responses; see the service).
 *
 * Routes (prefix /api/public/v1/claim-tracking):
 *   POST request-otp  { reference | token }            -> generic 200
 *   POST verify-otp   { reference | token, code }       -> { token } | error
 *   POST status       { session_token }                 -> read-only payload
 */
class ClaimTrackingController extends Controller
{
    public function __construct(private ClaimTrackingService $svc) {}

    /** Step 1 — send an OTP to the claim's registered contact. */
    public function requestOtp(Request $request): JsonResponse
    {
        if ($off = $this->guardFlag()) {
            return $off;
        }
        $request->validate([
            'reference' => 'required_without:token|nullable|string|max:120',
            'token'     => 'required_without:reference|nullable|string|max:120',
        ]);

        $identifier = (string) ($request->input('token') ?: $request->input('reference'));
        $result = $this->svc->requestOtp($identifier, $request->ip(), (string) $request->userAgent());

        // Always 200 with a generic message (no enumeration).
        return response()->json($result, 200);
    }

    /** Step 2 — verify the code; on success returns a claim-scoped session token. */
    public function verifyOtp(Request $request): JsonResponse
    {
        if ($off = $this->guardFlag()) {
            return $off;
        }
        $request->validate([
            'reference' => 'required_without:token|nullable|string|max:120',
            'token'     => 'required_without:reference|nullable|string|max:120',
            'code'      => 'required|string|regex:/^\d{4,6}$/',
        ]);

        $identifier = (string) ($request->input('token') ?: $request->input('reference'));
        $result = $this->svc->verifyOtp($identifier, (string) $request->input('code'), $request->ip(), (string) $request->userAgent());

        $status = ($result['ok'] ?? false) ? 200 : (($result['error'] ?? '') === 'locked' ? 429 : 401);
        return response()->json($result, $status);
    }

    /** Step 3 — read-only claim status for a valid session token. */
    public function status(Request $request): JsonResponse
    {
        if ($off = $this->guardFlag()) {
            return $off;
        }
        $token = (string) ($request->input('session_token') ?: $request->bearerToken());
        if ($token === '') {
            return response()->json(['ok' => false, 'error' => 'session_invalid'], 401);
        }

        $result = $this->svc->getStatus($token, $request->ip(), (string) $request->userAgent());
        return response()->json($result, ($result['ok'] ?? false) ? 200 : 401);
    }

    /**
     * 404 the whole surface when the feature is off — the endpoints simply
     * don't exist to an unauthorised caller.
     */
    private function guardFlag(): ?JsonResponse
    {
        if (!ClaimTrackingService::isEnabled()) {
            return response()->json(['ok' => false, 'error' => 'not_found'], 404);
        }
        return null;
    }
}
