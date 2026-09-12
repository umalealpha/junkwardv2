<?php

namespace AlphaDirect\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * VerifySwiftlySignature — authenticates inbound Swiftly early-payment webhooks.
 *
 * Swiftly signs every notification with:
 *     X-Webhook-Signature: hex( HMAC-SHA256(webhook_secret, raw_json_body) )
 *
 * Security properties (per Swiftly guidance, 2026-06-17, and our own review):
 *   - HMAC is computed over the RAW request body bytes (getContent()), BEFORE
 *     any JSON decoding — a single whitespace difference must fail.
 *   - Comparison is constant-time (hash_equals) to prevent timing attacks.
 *   - FAIL-CLOSED: if the shared secret is not configured we refuse the request
 *     (500) rather than silently accepting — the opposite of the VerifyApiKey
 *     fail-open bug. A missing/blank/mismatched signature is a 401.
 *
 * This is the ONLY thing standing between the public internet and the
 * webhook handler, so it is intentionally strict.
 */
class VerifySwiftlySignature
{
    public function handle(Request $request, Closure $next)
    {
        $secret = config('services.swiftly.webhook_secret');

        if (empty($secret)) {
            // Misconfiguration — never accept unverifiable webhooks.
            Log::error('Swiftly webhook rejected: webhook_secret not configured');
            return response()->json(['error' => 'Webhook verification unavailable'], 500);
        }

        $received = (string) $request->header('X-Webhook-Signature', '');
        if ($received === '') {
            Log::warning('Swiftly webhook rejected: missing X-Webhook-Signature header', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Missing signature'], 401);
        }

        $computed = hash_hmac('sha256', $request->getContent(), (string) $secret);

        if (!hash_equals($computed, $received)) {
            Log::warning('Swiftly webhook rejected: invalid signature', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        return $next($request);
    }
}
