<?php

namespace AlphaDirect\Http\Middleware;

use AlphaDirect\Services\Partner\PartnerAuthService;
use Closure;
use Illuminate\Http\Request;

/**
 * `partner.auth` — requires a valid partner session bearer token (minted by
 * POST /api/v1/public/partner/login). Attaches the PartnerUser to the
 * request as `partner_user`. 401 otherwise.
 */
class PartnerAuth
{
    public function __construct(private PartnerAuthService $auth) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $this->auth->resolveToken($request->bearerToken());
        if (!$user) {
            return response()->json(['ok' => false, 'error' => 'partner_unauthenticated', 'message' => 'Partner sign-in required.'], 401);
        }
        $request->attributes->set('partner_user', $user);
        return $next($request);
    }
}
