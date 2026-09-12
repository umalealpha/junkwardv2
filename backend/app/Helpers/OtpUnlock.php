<?php

namespace AlphaDirect\Helpers;

use AlphaDirect\Services\AuthGate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Call-centre OTP-unlock state — POPIA / Botswana DPA design (2026-06-22).
 *
 * A non-privileged agent must OTP-unlock a policy (the customer reads back an
 * OTP on the call) before sensitive in-policy actions / full PII reveal.
 * Admins + Managers (AuthGate::ADMIN_ROLES) bypass entirely — they are always
 * "unlocked" and never prompted.
 *
 * State lives in the CACHE, not the session: the admin API authenticates with
 * stateless Sanctum tokens, so a Laravel session would not persist the unlock
 * across requests. Keyed by agent + policy, with a fixed TTL (the working
 * session). Idle/call-end auto-mask was descoped (CFO 2026-06-22), so a flat
 * TTL is used rather than an inactivity timer.
 */
class OtpUnlock
{
    /** Purpose passed to PublicOtpService (must be in its PURPOSES list). */
    public const OTP_PURPOSE = 'agent_unlock';

    /** How long an unlock lasts once granted. */
    public const TTL_MINUTES = 30;

    /** Admins + Managers bypass OTP (CFO decision 2026-06-22). */
    public static function privileged(): bool
    {
        $u = Auth::user();
        if (!$u || !method_exists($u, 'hasAnyRole')) {
            return false; // fail-closed
        }
        try {
            return $u->hasAnyRole(AuthGate::ADMIN_ROLES);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function cacheKey(int $policyId, ?int $userId = null): string
    {
        $userId = $userId ?? (int) (Auth::id() ?? 0);
        return "otp_unlock:{$userId}:{$policyId}";
    }

    /** True if the calling agent may see/act on this policy unmasked right now. */
    public static function active(int $policyId): bool
    {
        if (self::privileged()) {
            return true;
        }
        if (!Auth::id()) {
            return false;
        }
        return Cache::get(self::cacheKey($policyId)) !== null;
    }

    /** Record a successful OTP unlock for the current agent + policy. */
    public static function markUnlocked(int $policyId): void
    {
        if (!Auth::id()) {
            return;
        }
        Cache::put(
            self::cacheKey($policyId),
            ['unlocked_at' => now()->toIso8601String()],
            now()->addMinutes(self::TTL_MINUTES)
        );
    }
}
