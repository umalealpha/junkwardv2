<?php

namespace AlphaDirect\Services\Partner;

use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Events\SendMail;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Models\Partner\PartnerUser;
use AlphaDirect\Models\Partner\PartnerUserToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Partner-portal credentials + sessions.
 *
 *  - Admin never types a password: sendCredentials() emails an HMAC-signed
 *    24h set-password link. Unlike the HR-portal scheme this was modelled
 *    on, the signature also covers password_set_at, so a link is
 *    SINGLE-USE: the moment a password is set every outstanding link dies.
 *  - login() is email + password, lockout after 5 failures / 15 min. Every
 *    failure path returns the same generic error (no account enumeration);
 *    unknown e-mails still pay a bcrypt check so timing is uniform.
 *  - Sessions are 64-char random tokens stored as sha256 hashes in
 *    partner_user_tokens; sliding TOKEN_TTL_HOURS, hard-capped at
 *    TOKEN_MAX_HOURS from issue.
 */
class PartnerAuthService
{
    public const TOKEN_TTL_HOURS   = 12;   // sliding — extended on use
    public const TOKEN_MAX_HOURS   = 24;   // absolute — never beyond issue + 24h
    public const LINK_TTL_HOURS    = 24;
    public const MAX_FAILED_LOGINS = 5;
    public const LOCK_MINUTES      = 15;

    /** bcrypt of a random string — only used to equalise timing on failure paths. */
    private const DUMMY_HASH = '$2y$12$xFPlFRHIB3WI.RiPzvafhOiRBHd3ELLZ04SHvXIlFdnsHOULz6ahC';

    // ─── Credentials email ──────────────────────────────────────────────

    public function setPasswordLink(PartnerUser $user): string
    {
        $expires   = now()->addHours(self::LINK_TTL_HOURS)->timestamp;
        $signature = $this->linkSignature($user->email, $expires, $user->password_set_at?->timestamp ?? 0);
        $q = http_build_query([
            'email'     => base64_encode($user->email),
            'expires'   => $expires,
            'signature' => $signature,
        ]);
        return rtrim($this->spaBase(), '/') . '/partner/set-password?' . $q;
    }

    public function loginLink(): string
    {
        return rtrim($this->spaBase(), '/') . '/partner/login';
    }

    public function sendCredentials(PartnerUser $user): void
    {
        $user->loadMissing('company');
        $template = EmailBroadcasting::where('hook_slug', 'partner_login_new')->first();
        if (!$template) {
            throw new \RuntimeException('Email template not found for hook: partner_login_new (run migrations)');
        }

        $d = new \stdClass();
        $d->user_id              = null;
        $d->customer_id          = null;
        $d->hook                 = 'partner_login_new';
        $d->email                = $user->email;
        $d->attachment           = null;
        $d->partner_company_name = $user->company->name ?? '';
        $d->partner_user_name    = $user->name;
        $d->partner_email        = $user->email;
        $d->partner_set_link     = $this->setPasswordLink($user);
        $d->partner_login_link   = $this->loginLink();

        $html = (new MailTemplate($d))->render('Mail.mailTemplate', ['data' => $d]);
        event(new SendMail($user->email, $template->subject, '', $html, null, ['hook' => 'partner_login_new']));
    }

    /** Verify the signed set-password link. Returns the user or null. */
    public function resolveSetPasswordLink(string $emailB64, int $expires, string $signature): ?PartnerUser
    {
        $email = base64_decode($emailB64, true);
        if ($email === false || now()->timestamp > $expires) {
            return null;
        }
        $user = PartnerUser::where('email', $email)->where('is_active', true)->first();
        // Signature is bound to the account's current password_set_at, so a
        // link minted before the last password set no longer verifies.
        $expected = $this->linkSignature($email, $expires, $user?->password_set_at?->timestamp ?? 0);
        if (!$user || !hash_equals($expected, $signature)) {
            return null;
        }
        return $user;
    }

    public function setPassword(PartnerUser $user, string $password): void
    {
        $user->forceFill([
            'password'        => Hash::make($password),
            'password_set_at' => now(),
            'failed_logins'   => 0,
            'locked_until'    => null,
        ])->save();
        // A new password invalidates every open session.
        PartnerUserToken::where('partner_user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    // ─── Login / sessions ───────────────────────────────────────────────

    /** @return array{ok:bool, error?:string, message?:string, token?:string, expires_in?:int, user?:PartnerUser} */
    public function login(string $email, string $password, ?string $ip): array
    {
        $user    = PartnerUser::with('company')->where('email', mb_strtolower(trim($email)))->first();
        $generic = ['ok' => false, 'error' => 'invalid_credentials', 'message' => 'Email or password is incorrect.'];

        if (!$user || !$user->is_active || !$user->company || !$user->company->status) {
            // Burn a bcrypt verify so unknown / inactive accounts take as long
            // as a wrong password on a real one (timing-based enumeration).
            Hash::check($password, self::DUMMY_HASH);
            Log::info('partner.login_failed', ['email' => $this->mask($email), 'reason' => $user ? 'inactive' : 'unknown', 'ip' => $ip]);
            return $generic;
        }
        if ($user->isLocked()) {
            Hash::check($password, self::DUMMY_HASH);
            Log::info('partner.login_failed', ['email' => $this->mask($user->email), 'reason' => 'locked', 'ip' => $ip]);
            return $generic; // real reason stays server-side; the lock still expires after LOCK_MINUTES
        }
        if (!$user->password) {
            Hash::check($password, self::DUMMY_HASH);
            Log::info('partner.login_failed', ['email' => $this->mask($user->email), 'reason' => 'password_not_set', 'ip' => $ip]);
            return $generic;
        }
        if (!Hash::check($password, $user->password)) {
            $fails = $user->failed_logins + 1;
            $user->forceFill([
                'failed_logins' => $fails,
                'locked_until'  => $fails >= self::MAX_FAILED_LOGINS ? now()->addMinutes(self::LOCK_MINUTES) : null,
            ])->save();
            Log::info('partner.login_failed', ['email' => $this->mask($user->email), 'reason' => 'bad_password', 'fails' => $fails, 'ip' => $ip]);
            return $generic;
        }

        $user->forceFill([
            'failed_logins' => 0,
            'locked_until'  => null,
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ])->save();

        $plain = Str::random(64);
        PartnerUserToken::create([
            'partner_user_id' => $user->id,
            'token_hash'      => hash('sha256', $plain),
            'expires_at'      => now()->addHours(self::TOKEN_TTL_HOURS),
            'ip'              => $ip,
        ]);

        return ['ok' => true, 'token' => $plain, 'expires_in' => self::TOKEN_TTL_HOURS * 3600, 'user' => $user];
    }

    /** Resolve a bearer token to an active partner user (sliding expiry). */
    public function resolveToken(?string $plain): ?PartnerUser
    {
        if (!$plain) {
            return null;
        }
        $row = PartnerUserToken::where('token_hash', hash('sha256', $plain))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', Carbon::now())
            ->first();
        if (!$row) {
            return null;
        }
        $user = PartnerUser::with('company')->find($row->partner_user_id);
        if (!$user || !$user->is_active || !$user->company || !$user->company->status) {
            return null;
        }
        // Sliding window, hard-capped at TOKEN_MAX_HOURS from issue.
        $hardCap = $row->created_at->copy()->addHours(self::TOKEN_MAX_HOURS);
        if (now()->greaterThanOrEqualTo($hardCap)) {
            $row->forceFill(['revoked_at' => now()])->save();
            return null;
        }
        $slide = now()->addHours(self::TOKEN_TTL_HOURS);
        $row->forceFill(['last_used_at' => now(), 'expires_at' => $slide->min($hardCap)])->save();
        return $user;
    }

    public function revokeToken(?string $plain): void
    {
        if (!$plain) {
            return;
        }
        PartnerUserToken::where('token_hash', hash('sha256', $plain))->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    public function revokeAllForUser(int $userId): void
    {
        PartnerUserToken::where('partner_user_id', $userId)->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    // ─── Internals ──────────────────────────────────────────────────────

    private function linkSignature(string $email, int $expires, int $passwordSetAt): string
    {
        return hash_hmac('sha256', 'partner|' . $email . '|' . $expires . '|' . $passwordSetAt, config('app.key'));
    }

    /** DPA minimisation: first two chars + domain, e.g. pa***@theriskco.com */
    private function mask(string $email): string
    {
        $email = mb_strtolower(trim($email));
        if (!str_contains($email, '@')) {
            return mb_substr($email, 0, 2) . '***';
        }
        [$local, $domain] = explode('@', $email, 2);
        return mb_substr($local, 0, 2) . '***@' . $domain;
    }

    private function spaBase(): string
    {
        return \AlphaDirect\Helper::startSpaBase();
    }
}
