<?php

namespace AlphaDirect\Http\Middleware;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Force a JSON 401 for anything under /api/* — regardless of Accept header.
     *
     * Why: Swagger UI's `Try it out` fetches /api/v1/... with an Accept
     * header of (star)/(star) (no valid JSON preference),
     * so `$request->expectsJson()` returns false, and the default redirect
     * chain sends the user to `route('login')`. If they're already logged
     * in via web session (which they almost always are, because the admin
     * portal shares cookies with Swagger), the login route bounces them
     * to /admin/dashboard and we return the entire dashboard HTML as the
     * "API response". Devs see a 200 text/html blob instead of a 401 JSON
     * error, which makes the API look completely broken when it isn't.
     *
     * Fix: override `unauthenticated()` so any request whose path starts
     * with `api/` throws the exception with a null redirect target, which
     * Laravel's exception handler renders as JSON 401 regardless of the
     * Accept header. Non-API paths keep the old browser-friendly redirect.
     */
    protected function unauthenticated($request, array $guards)
    {
        if ($this->isApiRequest($request)) {
            throw new AuthenticationException(
                'Unauthenticated.', $guards, null // redirectTo=null => JSON 401
            );
        }
        parent::unauthenticated($request, $guards);
    }

    protected function redirectTo($request)
    {
        if ($this->isApiRequest($request) || $request->expectsJson()) {
            return null; // 401 JSON — no redirect
        }
        return route('login');
    }

    private function isApiRequest(Request $request): bool
    {
        // $request->is('api/*') matches /api/v1/... /api/v2/... etc.
        // Also cover explicit bearer tokens (any "Authorization: Bearer …"
        // request is definitely an API client, never a browser page load).
        return $request->is('api/*')
            || str_starts_with((string) $request->header('Authorization', ''), 'Bearer ');
    }
}
