<?php

namespace AlphaDirect\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Gates the MotoLink assessment PUSH route (/api/claims-tracker/assessment).
 *
 * Deliberately a SEPARATE key from VerifyClaimsTrackerApiKey. The cover-lookup
 * and create routes are read/create and share API_KEY_CLAIMS_TRACKER; the push
 * route WRITES assessment data onto claims, so it gets its own dedicated
 * MOTOLINK_PUSH_KEY. Smaller blast radius: rotating MotoLink's push key can't
 * affect the cover-lookup key MotoLink also holds, or any other consumer.
 *
 * Same shape as VerifyClaimsTrackerApiKey:
 *   - `api-key` request header.
 *   - hash_equals() comparison (no timing side-channel).
 *   - fail CLOSED: 401 with no key, 503 if MOTOLINK_PUSH_KEY is unset on this
 *     environment (never compare against an empty string).
 */
class VerifyMotolinkPushKey
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

        $expected = env('MOTOLINK_PUSH_KEY');
        if (! $expected) {
            // Fail closed if the env var isn't set on this server, rather than
            // silently accepting any key by comparing against an empty string.
            return response()->json([
                'status'  => false,
                'message' => 'MotoLink push API is not configured on this environment',
            ], 503);
        }

        if (! hash_equals((string) $expected, (string) $submitted)) {
            Log::warning('[VerifyMotolinkPushKey] Bad key', [
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
