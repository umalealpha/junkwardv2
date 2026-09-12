<?php

namespace AlphaDirect\Services;

use AlphaDirect\Config;
use AlphaDirect\User;
use Illuminate\Support\Facades\Cache;

/**
 * Authorisation gate for password-based login.
 *
 * Policy
 * ------
 * Default: ONLY users with an admin-equivalent role may authenticate by
 * username + password. Everyone else must use Microsoft SSO. This is
 * the UAT 2026-05-26 hardening — keeps the password surface small and
 * forces normal users through Azure AD (MFA, group-policy, audit log).
 *
 * Emergency override
 * ------------------
 * If SSO is down, an on-call developer can run
 *   php artisan auth:fallback enable
 * (via the deploy.yml run-artisan workflow target) which flips the
 * `auth.password_login_fallback` row in the `config` table to '1'.
 * That re-opens password login for everyone for up to 60 seconds of
 * cache lag, then takes full effect. Run
 *   php artisan auth:fallback disable
 * the moment SSO is restored.
 *
 * Cache
 * -----
 * The fallback flag is cached for 60 seconds — long enough to keep
 * login traffic off the DB on every authentication, short enough that
 * an emergency toggle propagates fast.
 */
class AuthGate
{
    private const FALLBACK_CONFIG_KEY = 'auth.password_login_fallback';
    private const CACHE_KEY = 'auth_password_login_fallback';
    private const CACHE_TTL_SECONDS = 60;

    /**
     * Roles allowed to bypass the SSO-only policy. Mirrors the role
     * list used on the /admin route gate in routes/web.php.
     *
     * Public: PiiMask::privileged() reads this constant from outside this
     * class. It was `private` until 2026-06-18, which made every external
     * read throw "Cannot access private constant" — PiiMask's own
     * fail-closed catch swallowed that error silently, so PiiMask::privileged()
     * always returned false and every user (including Super Admin) saw
     * masked PII everywhere.
     */
    public const ADMIN_ROLES = ['Super Admin', 'Manager', 'Admin'];

    // ─── Policy cancellation / reinstatement authorisation (2026-08) ───────
    // Cancellation is product-scoped: Motor Comprehensive (product 3) and the
    // rest of the instant retail book use separate permissions so the two can
    // be granted to different people. Reinstatement reuses the existing
    // `policy_reinstate` permission. Managers/Admins bypass via role.
    public const CANCEL_INSTANT_PERM    = 'policy_cancel_instant';
    public const CANCEL_MOTOR_COMP_PERM = 'policy_cancel_motor_comp';
    public const REINSTATE_PERM         = 'policy_reinstate';
    public const MOTOR_COMP_PRODUCT_ID  = 3;
    public const INSTANT_PRODUCT_IDS    = [1, 2, 4, 5, 9];

    // ─── Payment reversal / refund authorisation (2026-08) ─────────────────
    // Sensitive money-movement (reverse a payment, manual cash refund). Unlike
    // most gates, ONLY Super Admin bypasses here — Managers/Admins do NOT get
    // it unless individually granted. Everyone else needs `payment_reversal`.
    public const SUPERADMIN_ROLE       = 'Super Admin';
    public const PAYMENT_REVERSAL_PERM = 'payment_reversal';

    /** Admin-equivalent role holder (Super Admin / Manager / Admin). */
    public static function isAdminOrManager(?User $user): bool
    {
        return $user !== null && $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Fail-CLOSED authorisation check for a single permission: admin-equivalent
     * roles always pass; otherwise the user must hold $permission. Any error
     * (e.g. the permission row is absent) denies — the opposite of the legacy
     * cancel check which failed OPEN.
     */
    public static function canPerform(?User $user, ?string $permission): bool
    {
        if ($user === null || $permission === null || $permission === '') {
            return false;
        }
        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return true;
        }
        try {
            return $user->hasPermissionTo($permission);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Which cancellation permission applies to a policy's product line.
     * Returns null for products outside the cancellable instant book, so the
     * caller can deny (or restrict to admin-only) rather than guess.
     */
    public static function cancelPermissionForProduct(?int $productId): ?string
    {
        if ($productId === self::MOTOR_COMP_PRODUCT_ID) {
            return self::CANCEL_MOTOR_COMP_PERM;
        }
        if (in_array((int) $productId, self::INSTANT_PRODUCT_IDS, true)) {
            return self::CANCEL_INSTANT_PERM;
        }
        return null;
    }

    /**
     * Fail-CLOSED gate for reversing/refunding a payment. Deliberately narrower
     * than canPerform(): ONLY the Super Admin role bypasses — Managers/Admins
     * do NOT. Everyone else must hold the `payment_reversal` permission.
     */
    public static function canReversePayment(?User $user): bool
    {
        if ($user === null) {
            return false;
        }
        if ($user->hasRole(self::SUPERADMIN_ROLE)) {
            return true;
        }
        try {
            return $user->hasPermissionTo(self::PAYMENT_REVERSAL_PERM);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Should this user be allowed to authenticate via password?
     * Returns true when either:
     *   1. The emergency fallback flag is enabled, OR
     *   2. The user has one of the admin-equivalent roles.
     */
    public static function shouldAllowPasswordLogin(User $user): bool
    {
        if (self::isFallbackEnabled()) {
            return true;
        }
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Is this user SSO-only — i.e. barred from password login by the policy
     * above (everyone without an admin-equivalent role)?
     *
     * Deliberately role-only: it does NOT consider the emergency fallback
     * flag. A user's credential is still Microsoft Entra even while the
     * fallback is open, and an SSO outage is the worst possible moment to
     * start forcing local password rotations on them.
     *
     * Used by the PasswordValidation middleware: an SSO-only user has no
     * usable local password, so the 90-day local rotation is meaningless
     * and the /reset-password screen (which asks for a Current Password
     * they are not even allowed to log in with) is a dead end for them.
     */
    public static function isSsoOnly(?User $user): bool
    {
        return $user !== null && ! self::isAdminOrManager($user);
    }

    /**
     * Read the fallback flag (cached). Public so the artisan command
     * and any health-check / status endpoint can inspect it.
     */
    public static function isFallbackEnabled(): bool
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            $row = Config::where('key', self::FALLBACK_CONFIG_KEY)->first();
            return $row !== null && (string) $row->value === '1';
        });
    }

    public static function enableFallback(): void
    {
        Config::updateOrCreate(
            ['key' => self::FALLBACK_CONFIG_KEY],
            ['value' => '1']
        );
        Cache::forget(self::CACHE_KEY);
    }

    public static function disableFallback(): void
    {
        Config::updateOrCreate(
            ['key' => self::FALLBACK_CONFIG_KEY],
            ['value' => '0']
        );
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Standard 403 response body the login endpoints return when a
     * non-admin tries to authenticate by password. Frontend reads the
     * `sso_required` flag and redirects to the Microsoft SSO flow.
     */
    public static function ssoRequiredResponse(): array
    {
        return [
            'message'      => 'Password login is restricted to administrators. Please sign in with Microsoft SSO.',
            'sso_required' => true,
            'sso_url'      => '/auth/microsoft',
        ];
    }
}
