<?php

namespace AlphaDirect\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * VerifyOmniCallbackToken — authenticates the Omni (alpha-finance) refund-paid
 * callback: POST /api/v1/webhooks/omni/refund-paid.
 *
 * Omni presents `Authorization: Bearer <GRAPHITE_REFUND_CALLBACK_TOKEN>`
 * (customer_refunds/services.py::post_refund_back_to_graphite). We compare it
 * against services.omni_refunds.callback_token.
 *
 * Security properties (mirrors VerifySwiftlySignature):
 *   - Constant-time comparison (hash_equals) — no timing oracle.
 *   - FAIL-CLOSED: if the token isn't configured we refuse (500) rather than
 *     silently accepting. Missing/blank/mismatched bearer is a 401.
 *
 * This is the only thing between the internet and a handler that posts money
 * events onto policies, so it is intentionally strict.
 */
class VerifyOmniCallbackToken
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('services.omni_refunds.callback_token');

        if ($expected === '') {
            Log::error('Omni refund callback rejected: callback_token not configured');
            return response()->json(['error' => 'Callback verification unavailable'], 500);
        }

        $got = trim((string) $request->bearerToken());
        if ($got === '') {
            Log::warning('Omni refund callback rejected: missing bearer token', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Missing token'], 401);
        }

        if (!hash_equals($expected, $got)) {
            Log::warning('Omni refund callback rejected: invalid token', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Invalid token'], 401);
        }

        return $next($request);
    }
}
