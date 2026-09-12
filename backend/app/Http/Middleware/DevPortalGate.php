<?php

namespace AlphaDirect\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate middleware for the /dev portal.
 *
 * The portal exposes four feature areas (endpoints, openapi, logs,
 * docs) and each has a role that grants access. A user needs at
 * least ONE of the dev-family roles to see the landing page; the
 * controller then gates each feature individually via
 * DevPortalGate::can($feature, $user) so a QA tester can see
 * endpoints without also seeing error logs.
 *
 * Access is granted when ANY of:
 *   - APP_ENV is local / development / dev / staging / testing
 *   - DEV_PORTAL_ENABLED=1 in the env (prod escape hatch)
 *   - the authenticated user has one of the dev-family roles below
 *
 * Otherwise returns 404 (not 403) so the portal stays undiscoverable.
 *
 * Roles (Spatie):
 *   developer         — full access to every page
 *   dev_viewer        — endpoints + openapi + docs (read-only API reference)
 *   dev_log_viewer    — logs + docs (support/ops, debugging prod incidents)
 *   dev_docs_viewer   — docs only (product / QA readonly reference)
 */
class DevPortalGate
{
    /** Roles that grant top-level portal access. */
    public const ROLES = [
        'developer',
        'dev_viewer',
        'dev_log_viewer',
        'dev_docs_viewer',
    ];

    /** Per-feature role requirements. */
    public const FEATURE_ROLES = [
        'endpoints' => ['developer', 'dev_viewer'],
        'openapi'   => ['developer', 'dev_viewer'],
        'logs'      => ['developer', 'dev_log_viewer'],
        'docs'      => ['developer', 'dev_viewer', 'dev_log_viewer', 'dev_docs_viewer'],
    ];

    /**
     * Features every logged-in backend user can see without a dev role.
     * The whole Laravel admin is behind auth:web, so anyone reaching
     * /dev/* is already an internal staff user — these features are
     * purely documentation and safe to share.
     */
    public const PUBLIC_FEATURES = ['swagger', 'openapi'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->bypass($request)) {
            return $next($request);
        }
        abort(404);
    }

    /**
     * Non-prod envs and the DEV_PORTAL_ENABLED escape hatch bypass
     * role checks entirely (useful on dev boxes without any users
     * provisioned).
     */
    public static function bypass(Request $request): bool
    {
        $env = strtolower((string) app()->environment());
        if (in_array($env, ['local', 'development', 'dev', 'staging', 'testing'], true)) {
            return true;
        }
        if (env('DEV_PORTAL_ENABLED')) {
            return true;
        }
        // Swagger / OpenAPI are readable by any authenticated backend
        // user — no dev role required. The admin is already auth-gated.
        if ($request->user() && str_starts_with(ltrim($request->path(), '/'), 'dev/') &&
            in_array(basename($request->path()), static::PUBLIC_FEATURES, true)) {
            return true;
        }
        return static::userHasAnyRole($request->user(), static::ROLES);
    }

    /**
     * Per-feature authorisation. Controller calls this before
     * rendering each page so a user with only `dev_log_viewer`
     * can't open /dev/endpoints.
     */
    public static function can(string $feature, $user): bool
    {
        if (in_array($feature, static::PUBLIC_FEATURES, true)) {
            return true;
        }
        if (!isset(static::FEATURE_ROLES[$feature])) {
            return false;
        }
        $env = strtolower((string) app()->environment());
        if (in_array($env, ['local', 'development', 'dev', 'staging', 'testing'], true)) {
            return true;
        }
        if (env('DEV_PORTAL_ENABLED')) {
            return true;
        }
        return static::userHasAnyRole($user, static::FEATURE_ROLES[$feature]);
    }

    private static function userHasAnyRole($user, array $roles): bool
    {
        if (!$user || !method_exists($user, 'hasRole')) return false;
        foreach ($roles as $role) {
            if ($user->hasRole($role)) return true;
        }
        return false;
    }
}
