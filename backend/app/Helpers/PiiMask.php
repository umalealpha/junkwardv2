<?php

namespace AlphaDirect\Helpers;

use AlphaDirect\Services\AuthGate;
use Illuminate\Support\Facades\Auth;

/**
 * Server-side PII masking for customer/policyholder personal data
 * (DPA / PoPIA). Non-privileged staff must NEVER receive the raw values —
 * masking happens here in the API layer, not in the browser, so the
 * sensitive data never leaves the server for an unprivileged session.
 *
 * Privileged = Super Admin / Manager / Admin (AuthGate::ADMIN_ROLES,
 * the same set already trusted for password login + the /admin gate).
 * Everyone else sees masked values:
 *   Prathap Ganesharajah -> Prath** Ganes***
 *   72727 0011 (72727 0011) -> 72***0011
 *   email -> p***@domain ; omang/passport -> 23****09 ; dob/address -> hidden
 *
 * FAIL-CLOSED: if we cannot positively confirm the user is privileged
 * (no auth user, hasAnyRole missing), we MASK.
 */
class PiiMask
{
    public static function privileged(): bool
    {
        $u = Auth::user();
        if (!$u || !method_exists($u, 'hasAnyRole')) {
            return false; // fail-closed
        }
        try {
            // Admin roles, OR anyone holding the permission to correct
            // customer/KYC records — staff who are authorised to fix a
            // wrong omang/DOB/phone need to see the real value, not a
            // masked placeholder, otherwise "editing" just overwrites
            // real data with the mask.
            return $u->hasAnyRole(AuthGate::ADMIN_ROLES)
                || $u->can('customer-edit')
                || $u->can('customer-kyc-edit');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Mask one name token: reveal first few letters, then ***. */
    public static function namePart(?string $s): ?string
    {
        $s = trim((string) $s);
        if ($s === '') return $s;
        $keep = min(5, max(1, (int) floor(mb_strlen($s) * 0.55)));
        return mb_substr($s, 0, $keep) . '***';
    }

    /** Mask a full name word-by-word. */
    public static function name(?string $full): ?string
    {
        if ($full === null) return null;
        $parts = preg_split('/\s+/', trim($full));
        if (!$parts || $parts === ['']) return $full;
        return trim(implode(' ', array_map(fn ($p) => self::namePart($p), $parts)));
    }

    /** Phone: keep first 2 + last 4, mask the middle (727270011 -> 72***0011). */
    public static function phone(?string $s): ?string
    {
        if ($s === null) return null;
        $d = preg_replace('/\D/', '', $s);
        if (mb_strlen($d) < 6) return $d === '' ? $s : '****';
        return mb_substr($d, 0, 2) . '***' . mb_substr($d, -4);
    }

    /** ID / Omang / passport: reveal the LAST 5, mask the rest
     *  (Omang 123456789 -> ****56789). Lets an agent verify identity against
     *  what the caller states without exposing the whole number (DPO call-centre
     *  design 2026-06-22). Values <= 5 chars are returned as-is (nothing to hide).
     *  NB: a 9-digit Omang leaves only 4 digits hidden — accepted trade-off for
     *  call-centre verification (flagged by Bharath B.). */
    public static function idnum(?string $s): ?string
    {
        $s = (string) ($s ?? '');
        if ($s === '') return $s ?: null;
        if (mb_strlen($s) <= 5) return $s;
        return '****' . mb_substr($s, -5);
    }

    /** Email: first char + *** + domain (p***@alphadirect.co.bw). */
    public static function email(?string $s): ?string
    {
        if (!$s || !str_contains($s, '@')) return $s ? '****' : $s;
        [$local, $dom] = explode('@', $s, 2);
        return mb_substr($local, 0, 1) . '***@' . $dom;
    }

    /** Date of birth / address — fully hidden for non-privileged. */
    public static function hidden($_ = null): string
    {
        return '••••••';
    }

    /** Bank account number: keep first 4 + last 4, mask the middle
     *  (622200000111 -> 6222****0111); 5-8 digits keep last 4 only. */
    public static function bankAccount(?string $s): ?string
    {
        if ($s === null) return null;
        $d = preg_replace('/\s+/', '', trim((string) $s));
        if ($d === '') return $s;
        if (mb_strlen($d) <= 4) return '****';
        if (mb_strlen($d) <= 8) return '****' . mb_substr($d, -4);
        return mb_substr($d, 0, 4) . '****' . mb_substr($d, -4);
    }

    /** Branch / sort code: keep first 2 + last 2 (282267 -> 28****67). */
    public static function branchCode(?string $s): ?string
    {
        $s = trim((string) ($s ?? ''));
        if ($s === '') return $s ?: null;
        if (mb_strlen($s) <= 4) return '****';
        return mb_substr($s, 0, 2) . '****' . mb_substr($s, -2);
    }

    /** Bank name: reveal first 3 chars then *** (First National Bank -> Fir***). */
    public static function bankName(?string $s): ?string
    {
        $s = trim((string) ($s ?? ''));
        if ($s === '') return $s ?: null;
        return mb_substr($s, 0, 3) . '***';
    }

    // ---- conditional wrappers: return raw when privileged, masked otherwise ----
    // DPO directive 2026-06-22: NAME + TELEPHONE are the ONLY fields shown in full
    // to every user; everything else (banking, ID, email, DOB, address) is masked
    // for non-privileged staff. Privileged roles + the audited DataAccessRequest
    // reveal flow still see raw values.
    public static function ifName(?string $v): ?string         { return $v; }
    public static function ifPhone(?string $v): ?string        { return $v; }
    public static function ifId(?string $v): ?string           { return self::privileged() ? $v : self::idnum($v); }
    public static function ifEmail(?string $v): ?string        { return self::privileged() ? $v : self::email($v); }
    public static function ifHidden($v)                        { return self::privileged() ? $v : (($v === null || $v === '') ? $v : self::hidden()); }
    public static function ifBankAccount(?string $v): ?string  { return self::privileged() ? $v : self::bankAccount($v); }
    public static function ifBranchCode(?string $v): ?string   { return self::privileged() ? $v : self::branchCode($v); }
    public static function ifBankName(?string $v): ?string     { return self::privileged() ? $v : self::bankName($v); }
}
