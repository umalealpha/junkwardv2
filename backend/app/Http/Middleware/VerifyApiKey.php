<?php

namespace AlphaDirect\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Read from config (not env()) so the key resolves correctly under
        // `php artisan config:cache` in production. env() returns null once
        // the config is cached, which would 401 every partner request.
        $apiKey   = (string) config('services.partner.api_key');
        $provided = (string) $request->header('api-key');

        // Constant-time comparison to avoid leaking the key via timing.
        if ($apiKey === '' || ! hash_equals($apiKey, $provided)) {
            return response()->json(['status' => false, 'message' => 'Invalid API KEY'], 401);
        }

        return $next($request);
    }
}
