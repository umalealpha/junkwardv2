<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\UserLoginLog;
use AlphaDirect\Services\AuthGate;
use AlphaDirect\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
            'device'   => 'nullable|string|max:255',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            $this->recordAuthEvent('failed', $user, $request, $request->email);
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (isset($user->active) && $user->active != 1) {
            $this->recordAuthEvent('failed', $user, $request, $request->email);
            return response()->json(['message' => 'Account inactive'], 403);
        }

        // SSO-only policy (UAT 2026-05-26). Only admin-equivalent roles may
        // authenticate by password unless the emergency fallback is enabled
        // — toggled via `php artisan auth:fallback enable` when SSO is down.
        if (!AuthGate::shouldAllowPasswordLogin($user)) {
            Log::info('[AuthController] password login blocked — SSO required', [
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->toArray(),
            ]);
            $this->recordAuthEvent('sso_blocked', $user, $request);
            return response()->json(AuthGate::ssoRequiredResponse(), 403);
        }

        $deviceName = $request->input('device', 'api');

        // Don't revoke existing tokens — lets the user have multiple tabs / devices
        // open without invalidating each other. Prune only tokens older than 60 days
        // to prevent unbounded accumulation.
        $user->tokens()->where('created_at', '<', now()->subDays(60))->delete();

        $token = $user->createToken($deviceName);

        // Track last successful login — guarded so a missing column on a
        // partially-migrated environment doesn't 500 the login flow.
        if (Schema::hasColumn('users', 'last_login_at')) {
            $user->forceFill(['last_login_at' => now()])->saveQuietly();
        }

        $this->recordAuthEvent('login', $user, $request);

        $roles = $user->roles->pluck('name')->toArray();
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();

        return response()->json([
            'token'      => $token->plainTextToken,
            'expires_at' => now()->addMinutes(config('sanctum.expiration', 43200))->toIso8601String(),
            'user'       => [
                'id'          => $user->id,
                'name'        => trim($user->firstName . ' ' . $user->lastName),
                'email'       => $user->email,
                'role'        => $roles[0] ?? null,
                'roles'       => $roles,
                'permissions' => $permissions,
            ],
            'lookups'    => $this->getLoginLookups(),
        ]);
    }

    /**
     * Lookup data embedded in login/SSO responses — saves 10+ API calls later.
     * Both /auth/login and /auth/sso/exchange must return this so dropdowns
     * (Agency, Plan, Product, etc.) populate regardless of auth method.
     */
    private function getLoginLookups(): array
    {
        return Cache::remember('login_lookups_v2', 3600, function () {
            return [
                'agencies' => \AlphaDirect\Agency::where('status', 1)->select('id', 'name')->orderBy('name')->get(),
                'states' => \AlphaDirect\State::where('country_id', 28)->select('id', 'name')->orderBy('name')->get(),
                'companies' => \AlphaDirect\Models\Company::where('status', 1)->whereNull('parent_id')->select('id', 'name')->orderBy('name')->get(),
                'banks' => \AlphaDirect\Banks::select('id', 'bank_name as name')->orderBy('bank_name')->get(),
                'products' => \AlphaDirect\Product::whereIn('id', [7,8,16,17,18,19,20,22,23,24])->select('id', 'name')->get(),
                'plans' => \AlphaDirect\Productplan::whereIn('product_id', [7,8,16,17,18,19,20,22,23,24])->select('id', 'product_id', 'name')->get(),
                'hear_about_alpha' => \AlphaDirect\Lookup::where('key', 'hear_about_alphadirect')->where('status', 1)->select('id', 'value as name')->get(),
                'currently_insured' => \AlphaDirect\Lookup::where('key', 'are_you_currently_insured')->where('status', 1)->select('id', 'value as name')->get(),
                'uw_statuses' => [
                    ['id' => 'UWOPEN', 'name' => 'UW Open'],
                    ['id' => 'PENDINGUW', 'name' => 'Pending UW'],
                    ['id' => 'APPROVEDUW', 'name' => 'Approved UW'],
                    ['id' => 'AGENTREQUESTCHANGE', 'name' => 'Agent Request Change'],
                    ['id' => 'AGENTSUBERROR', 'name' => 'Agent Sub Error'],
                    ['id' => 'UNACCEPTABLE', 'name' => 'Unacceptable'],
                    ['id' => 'APPDIDNOTACCEPT', 'name' => 'App Did Not Accept'],
                    ['id' => 'APPWITHDRAWNPREUP', 'name' => 'App Withdrawn Pre-UW'],
                    ['id' => 'APPWITHDRAWNUN', 'name' => 'App Withdrawn UN'],
                ],
            ];
        });
    }

    /**
     * One-time password setup/reset — protected by APP_SETUP_KEY env var.
     * ONLY works when APP_SETUP_KEY is set in the environment.
     * Remove APP_SETUP_KEY from env to disable this endpoint after use.
     */
    public function setupPassword(Request $request): JsonResponse
    {
        $setupKey = env('APP_SETUP_KEY');
        if (!$setupKey) {
            return response()->json(['error' => 'Setup endpoint disabled'], 403);
        }

        $request->validate([
            'setup_key'    => 'required|string',
            'email'        => 'required|email',
            'new_password' => 'required|string|min:6',
        ]);

        if ($request->setup_key !== $setupKey) {
            return response()->json(['error' => 'Invalid setup key'], 403);
        }

        $user = User::whereRaw('LOWER(email) = ?', [strtolower($request->email)])->first();

        if (!$user) {
            // Return similar emails to help diagnose
            $similar = User::whereRaw('LOWER(email) LIKE ?', ['%' . strtolower(explode('@', $request->email)[0]) . '%'])
                ->select('id', 'email', 'firstName', 'lastName')
                ->limit(5)
                ->get();
            return response()->json(['error' => 'User not found', 'similar' => $similar], 404);
        }

        $user->password = \Illuminate\Support\Facades\Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'Password updated',
            'user'    => ['id' => $user->id, 'email' => $user->email, 'name' => trim($user->firstName . ' ' . $user->lastName)],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->recordAuthEvent('logout', $request->user(), $request);
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        $roles = $user->roles->pluck('name')->toArray();
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();

        return response()->json([
            'id'          => $user->id,
            'name'        => trim($user->firstName . ' ' . $user->lastName),
            'email'       => $user->email,
            'role'        => $roles[0] ?? null,
            'roles'       => $roles,
            'permissions' => $permissions,
            // Ship the lookups bundle here too (same builder/cache as login) so
            // the SPA can refresh `cached_lookups` on every load — the client
            // cache otherwise only updated at login and went stale silently,
            // causing empty City/province and "No options" plan dropdowns until
            // the user logged out and back in. Mirrors how AdminLayout already
            // refreshes permissions/roles from this endpoint.
            'lookups'     => $this->getLoginLookups(),
        ]);
    }

    /**
     * SSO: Generate a one-time token for the current session user.
     * Called from the Graphite backend when user clicks "React Portal" link.
     * The token is valid for 60 seconds and can only be used once.
     */
    public function generateSsoToken(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $token = Str::random(64);
        Cache::put("sso_token:{$token}", $user->id, 60); // 60 seconds TTL

        $reactUrl = config('services.react_portal.url', 'http://localhost:3000');

        return response()->json([
            'sso_url' => "{$reactUrl}/sso?token={$token}",
            'token'   => $token,
            'expires' => 60,
        ]);
    }

    /**
     * SSO: Exchange a one-time SSO token for a Sanctum API token.
     * Called from the React frontend when it receives ?token= in the URL.
     */
    public function exchangeSsoToken(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string|size:64']);

        $ssoToken = $request->input('token');
        $cacheKey = "sso_token:{$ssoToken}";

        $userId = Cache::pull($cacheKey); // pull = get + delete (one-time use)

        if (!$userId) {
            return response()->json(['error' => 'Invalid or expired SSO token'], 401);
        }

        $user = User::find($userId);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Create Sanctum token for React
        $user->tokens()->where('name', 'sso')->delete();
        $sanctumToken = $user->createToken('sso');

        // Same last-login tracking as the password-login path.
        if (Schema::hasColumn('users', 'last_login_at')) {
            $user->forceFill(['last_login_at' => now()])->saveQuietly();
        }

        $this->recordAuthEvent('login', $user, $request);

        $roles = $user->roles->pluck('name')->toArray();
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();

        return response()->json([
            'token'      => $sanctumToken->plainTextToken,
            'expires_at' => now()->addMinutes(config('sanctum.expiration', 43200))->toIso8601String(),
            'user'       => [
                'id'          => $user->id,
                'name'        => trim($user->firstName . ' ' . $user->lastName),
                'email'       => $user->email,
                'role'        => $roles[0] ?? null,
                'roles'       => $roles,
                'permissions' => $permissions,
            ],
            'lookups'    => $this->getLoginLookups(),
        ]);
    }

    /**
     * Record an auth activity event (login / logout / failed / sso_blocked)
     * with the real client IP + user-agent. Best-effort: a logging failure
     * must NEVER break the auth flow, so it is wrapped in a try/catch.
     */
    private function recordAuthEvent(string $event, ?User $user, Request $request, ?string $email = null): void
    {
        try {
            UserLoginLog::create([
                'user_id'    => $user?->id,
                'email'      => $email ?? $user?->email,
                'event'      => $event,
                'ip_address' => $this->clientIp($request),
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('auth.activity_log_failed', ['event' => $event, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * Real client IP. Behind Cloudflare the visitor IP is in CF-Connecting-IP;
     * $request->ip() returns the Cloudflare edge even with trust-all proxies.
     * Prefer CF-Connecting-IP, then the left-most X-Forwarded-For, then ip().
     * Validated so a spoofed/garbage header falls through rather than storing junk.
     */
    private function clientIp(Request $request): ?string
    {
        $cf = $request->header('CF-Connecting-IP');
        if ($cf && filter_var($cf, FILTER_VALIDATE_IP)) {
            return $cf;
        }

        $xff = $request->header('X-Forwarded-For');
        if ($xff) {
            $first = trim(explode(',', $xff)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }

        return $request->ip();
    }
}
