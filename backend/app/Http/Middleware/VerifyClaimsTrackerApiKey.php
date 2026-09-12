<?php

namespace AlphaDirect\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Gates the /api/claims-tracker/* route group.
 *
 * Uses a dedicated env var API_KEY_CLAIMS_TRACKER, NOT the shared API_KEY
 * used by VerifyApiKey. Two reasons:
 *   1. Smaller blast radius — rotating Claims Tracker's key doesn't break
 *      WhatsApp, BizSure lookup write-back, or any other VerifyApiKey
 *      consumer.
 *   2. Fail-closed — if the env var is missing on this environment, the
 *      endpoint returns 503 rather than silently comparing against an
 *      empty string.
 *
 * Compare submitted vs configured key with hash_equals() to avoid a timing
 * side-channel. Port of graphiteBWV8 VerifyClaimsTrackerApiKey
 * (feat/claims-tracker-api).
 */
class VerifyClaimsTrackerApiKey
{
    public function handle($request, Closure $next)
    {
        $submitted = $request->header('api-key');
        if (! $submitted) {
            return response()->json(
                ['status' => false, 'message' => 'Invalid API KEY'],
                401
            );
        }

        $expected = env('API_KEY_CLAIMS_TRACKER');
        if (! $expected) {
            // Fail closed if the env var isn't set on this server. Better
            // than silently accepting any key because the comparison would
            // be against an empty string.
            return response()->json([
                'status'  => false,
                'message' => 'Claims Tracker API is not configured on this environment',
            ], 503);
        }

        if (! hash_equals((string) $expected, (string) $submitted)) {
            Log::warning('[VerifyClaimsTrackerApiKey] Bad key', [
                'ip'   => $request->ip(),
                'path' => $request->path(),
            ]);
            return response()->json(
                ['status' => false, 'message' => 'Invalid API KEY'],
                401
            );
        }

        return $next($request);
    }
}
