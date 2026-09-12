<?php

namespace AlphaDirect\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * VerifyAlphaTransitToken — authenticates the Alpha Transit Cover platform's
 * event webhook: POST /api/v1/webhooks/alpha-transit/event.
 *
 * The ATC platform presents `Authorization: Bearer <GRAPHITE_API_KEY>`
 * (services/webhook.js::sendWebhook). We compare it against
 * services.alpha_transit.webhook_token.
 *
 * Security properties (mirrors VerifyOmniCallbackToken):
 *   - Constant-time comparison (hash_equals) — no timing oracle.
 *   - FAIL-CLOSED: if the token isn't configured we refuse (500) rather than
 *     silently accepting. Missing/blank/mismatched bearer is a 401.
 *
 * This endpoint creates customers, policies, payments and claims, so it is
 * intentionally strict.
 */
class VerifyAlphaTransitToken
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('services.alpha_transit.webhook_token');

        if ($expected === '') {
            Log::error('Alpha Transit webhook rejected: webhook_token not configured');
            return response()->json(['error' => 'unauthorized', 'message' => 'Webhook verification unavailable'], 500);
        }

        $got = trim((string) $request->bearerToken());
        if ($got === '') {
            Log::warning('Alpha Transit webhook rejected: missing bearer token', ['ip' => $request->ip()]);
            return response()->json(['error' => 'unauthorized', 'message' => 'Missing token'], 401);
        }

        if (!hash_equals($expected, $got)) {
            Log::warning('Alpha Transit webhook rejected: invalid token', ['ip' => $request->ip()]);
            return response()->json(['error' => 'unauthorized', 'message' => 'Invalid token'], 401);
        }

        return $next($request);
    }
}
