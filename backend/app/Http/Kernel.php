<?php

namespace AlphaDirect\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        // \AlphaDirect\Http\Middleware\TrustHosts::class,
        \AlphaDirect\Http\Middleware\TrustProxies::class,
        \Fruitcake\Cors\HandleCors::class,
        \AlphaDirect\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \AlphaDirect\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            \AlphaDirect\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \AlphaDirect\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
			\AlphaDirect\Http\Middleware\PasswordValidation::class,
        ],

        'api' => [
            //\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            \AlphaDirect\Http\Middleware\TrackAPIRequest::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            // Reject DELETE requests made via a `dev-portal` Sanctum token.
            // Swagger UI already disables DELETE client-side; this is the
            // backend enforcement so curl with a dev-portal PAT can't
            // bypass that by hitting the API directly.
            \AlphaDirect\Http\Middleware\BlockDeleteFromDevPortal::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => \AlphaDirect\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \AlphaDirect\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'VerifyApiKey' =>\AlphaDirect\Http\Middleware\VerifyApiKey::class,
        'VerifyClaimsTrackerApiKey' => \AlphaDirect\Http\Middleware\VerifyClaimsTrackerApiKey::class,
        'VerifyMotolinkPushKey' => \AlphaDirect\Http\Middleware\VerifyMotolinkPushKey::class,
        'XssSanitizer' => \AlphaDirect\Http\Middleware\XssSanitization::class,
        'webhook.buffer' => \AlphaDirect\Http\Middleware\WebhookBuffer::class,
        // Verifies the HMAC-SHA256 X-Webhook-Signature on inbound Swiftly
        // early-payment webhooks. Fail-closed (refuses if secret unset).
        'swiftly.signature' => \AlphaDirect\Http\Middleware\VerifySwiftlySignature::class,
        // Verifies the shared bearer token on the Omni (alpha-finance)
        // refund-paid callback. Fail-closed (refuses if token unset).
        'omni.callback' => \AlphaDirect\Http\Middleware\VerifyOmniCallbackToken::class,
        // Verifies the shared bearer token on inbound Alpha Transit Cover
        // (courier GIT) events. Fail-closed (refuses if token unset).
        'atc.webhook' => \AlphaDirect\Http\Middleware\VerifyAlphaTransitToken::class,
        // Partner-portal session (courier / retailer staff signed in on start).
        'partner.auth' => \AlphaDirect\Http\Middleware\PartnerAuth::class,
        'pii.mask' => \AlphaDirect\Http\Middleware\RekycDataMasking::class,
        'checkmenu' => \AlphaDirect\Http\Middleware\MenuChecking::class,
        'policy.action' => \AlphaDirect\Http\Middleware\EnsurePolicyActionAllowed::class,
        // Spatie role/permission aliases — required by the /admin/* route
        // group gate added 2026-05-26 (UAT finding: V2 backend admin
        // panel was reachable by any authenticated frontend user).
        'role' => \Spatie\Permission\Middlewares\RoleMiddleware::class,
        'permission' => \Spatie\Permission\Middlewares\PermissionMiddleware::class,
        'role_or_permission' => \Spatie\Permission\Middlewares\RoleOrPermissionMiddleware::class,
        // Defense-in-depth lock on the legacy V1 admin panel mounted
        // under /admin/* on the V2 backend domain. Default-deny;
        // requires V1_ADMIN_PANEL_ENABLED=true env flag to unlock.
        // UAT 2026-05-27 (closes second half of Prathap §2.2).
        'block.v1_admin' => \AlphaDirect\Http\Middleware\BlockV1AdminPanel::class,
        // Sanctum ability gates — re-enabled 2026-06-09 for the Finance
        // ERP API. `abilities` checks ALL listed abilities are on the token;
        // `ability` checks ANY. Service tokens for alpha-finance carry only
        // `finance:read`, so the /api/v1/finance/* group is gated with
        // ->middleware('ability:finance:read').
        'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
        'ability'   => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,

    ];
}
