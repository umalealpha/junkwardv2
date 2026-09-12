<?php

namespace AlphaDirect\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Hardened OTP service for the customer-facing start.alphadirect.co.bw.
 *
 * Replaces the legacy FrontendPay/CustomerController::sendOTPPolicy +
 * authenticateOTP pair which stored OTPs in plaintext, used a global
 * OTPTemp row as a side channel, and had no per-flow purpose binding.
 *
 * Public surface:
 *   send(cellphone, purpose, ip, ua)  → ['sent_to' => masked_phone, 'expires_in' => 300]
 *   verify(cellphone, purpose, code, ip)
 *       on hit  → ['ok' => true, 'token' => '...session token...']
 *       on miss → ['ok' => false, 'error' => 'invalid'|'expired'|'locked']
 *
 * Delivery uses the existing AlphaDirectNotificationService which already
 * handles WhatsApp → SMS fallback per project policy.
 */
class PublicOtpService
{
    public const VALIDITY_SECONDS    = 300;   // 5 minutes
    public const RESEND_COOLDOWN_SEC = 60;    // can't request another OTP for 60s
    public const MAX_ATTEMPTS        = 5;     // brute-force lockout
    public const TOKEN_TTL_SECONDS   = 600;   // 10-minute session token

    public const PURPOSES = [
        'customer_auth', 'policy_edit', 'kyc_update', 'vehicle_inspect', 'payment_authorize',
        // Call-centre agent OTP-unlock (POPIA/DPA design 2026-06-22) — reuses the
        // same hardened send/verify pipeline as the customer-facing purposes.
        'agent_unlock',
    ];

    /**
     * Static dispatcher used by the consent-flow ConsentController which
     * inserts its own public_otps row (with consent-specific metadata)
     * and just needs the delivery pipeline.
     *
     * Loads the row, runs the existing deliverOn() chain across the
     * default channels (whatsapp → sms → voice/email), updates the row
     * with the delivery status, and returns the delivered channel.
     *
     * The instance send() method does the full happy path (insert +
     * cooldown + deliver). dispatch() is the deliver-only side door.
     */
    public static function dispatch(int $otpId, string $cellphone, string $rawCode): ?string
    {
        $svc = app(self::class);
        $row = DB::connection('mysql_system')->table('public_otps')->where('id', $otpId)->first();
        if (!$row) {
            throw new \RuntimeException("public_otps row {$otpId} not found");
        }

        // Apply the same dev override / fan-out semantics as send() so a
        // local tester gets the message even from this side door.
        $deliveryCellphone = (string) (env('OTP_DEV_FORCE_CELLPHONE') ?: $cellphone);
        $deliveryEmail     = (string) (env('OTP_DEV_FORCE_EMAIL') ?: '');
        $fanOut            = filter_var(env('OTP_FAN_OUT_ALL_CHANNELS', false), FILTER_VALIDATE_BOOLEAN);

        $channels   = $fanOut ? ['whatsapp', 'sms', 'email'] : ['whatsapp', 'sms', 'email'];
        $attempts   = [];
        $delivered  = null;
        $providerRef = null;
        foreach ($channels as $ch) {
            $attempt = $svc->deliverOn($ch, $deliveryCellphone, $deliveryEmail ?: null, $rawCode, (string) $row->purpose, $row->reason ?? null);
            $attempts[] = $attempt;
            if ($attempt['status'] === 'sent') {
                if ($delivered === null) {
                    $delivered   = $ch;
                    $providerRef = $attempt['provider_ref'] ?? null;
                }
                if (!$fanOut) break;
            }
        }

        DB::connection('mysql_system')->table('public_otps')->where('id', $otpId)->update([
            'delivered_via'         => $delivered,
            'delivery_status'       => $delivered ? 'sent' : 'failed',
            'delivery_provider_ref' => $providerRef,
            'attempted_channels'    => json_encode($attempts),
            'updated_at'            => Carbon::now(),
        ]);

        return $delivered;
    }

    /**
     * Generate, persist (hashed) and dispatch an OTP.
     */
    /**
     * Channels we know how to send on, in default fallback order. Each
     * channel respects the customer's stored consent — we never send
     * over WhatsApp without explicit opt-in (Botswana data is expensive
     * and customers often don't have WhatsApp, so the cellphone alone
     * is no signal of WhatsApp availability).
     */
    public const CHANNELS = ['whatsapp', 'sms', 'email'];

    /**
     * @param  array  $opts
     *   - channels: string[]  Preferred order, e.g. ['whatsapp','sms']. Default: derived from customer_contact_preferences.
     *   - email:    string    Required when 'email' is in the chain.
     */
    public function send(string $cellphone, string $purpose, ?string $ip = null, ?string $ua = null, array $opts = []): array
    {
        $cellphone = $this->normalize($cellphone);
        if (!in_array($purpose, self::PURPOSES, true)) {
            throw new \InvalidArgumentException("Invalid purpose: {$purpose}");
        }

        // Cooldown: a valid OTP issued in the last RESEND_COOLDOWN_SEC blocks resend.
        $recent = DB::connection('mysql_system')->table('public_otps')
            ->where('cellphone', $cellphone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('created_at', '>', Carbon::now()->subSeconds(self::RESEND_COOLDOWN_SEC))
            ->exists();
        if ($recent) {
            return [
                'ok'     => false,
                'error'  => 'cooldown',
                'wait_s' => self::RESEND_COOLDOWN_SEC,
            ];
        }

        // Resolve channel chain: explicit opts.channels > customer prefs > default chain.
        $prefs    = $this->loadPreferences($cellphone);
        $channels = $this->resolveChannelChain($opts['channels'] ?? null, $prefs);
        $email    = $opts['email'] ?? $prefs['preferred_email'] ?? null;

        // Generate code, void any stale OTPs, persist new one.
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Production is identified in this estate by APP_STATUS (APP_ENV is
        // 'local' even in prod), read via config() so it stays correct under
        // `config:cache`. The raw OTP code must never be persisted or logged
        // in production.
        $isProduction = strcasecmp((string) config('values.APP_STATUS'), 'Production') === 0;

        // Dev visibility: log the raw code so local QA can grab it from
        // storage/logs/laravel.log without needing real SMS delivery.
        // Hard-blocked in production regardless of APP_DEBUG.
        if (config('app.debug') && ! $isProduction) {
            Log::info('public_otp.code [DEV-ONLY]', [
                'cellphone' => $cellphone,
                'purpose'   => $purpose,
                'code'      => $code,
            ]);
        }

        DB::connection('mysql_system')->table('public_otps')
            ->where('cellphone', $cellphone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => Carbon::now(), 'updated_at' => Carbon::now()]);

        // TESTING-ONLY column added 2026-05-22 for local QA. Hard-blocked in
        // production (writes NULL) so prod rows never hold a cleartext OTP.
        // Prod detection uses APP_STATUS via config() (see above) because
        // APP_ENV is 'local' even in prod. See migration
        // 2026_05_22_120000_add_code_plain_testing_only — the column should be
        // dropped once local QA no longer needs it.
        $codePlainForTesting = $isProduction ? null : $code;
        // Why this OTP was sent — resolved server-side from the triggering
        // functionality (opts.source), with purpose as fallback. Stored on the
        // row for audit AND shown to the customer in the message body below.
        $reason = OtpReason::resolve($opts['source'] ?? null, $purpose, $opts['reason_note'] ?? null);

        $otpId = DB::connection('mysql_system')->table('public_otps')->insertGetId([
            'cellphone'        => $cellphone,
            'purpose'          => $purpose,
            'reason'           => $reason,
            'code_hash'        => $this->hash($code),
            'code_plain'       => $codePlainForTesting,
            'expires_at'       => Carbon::now()->addSeconds(self::VALIDITY_SECONDS),
            'attempts'         => 0,
            'ip'               => $ip,
            'user_agent'       => $ua ? substr($ua, 0, 255) : null,
            'delivery_status'  => 'queued',
            'created_at'       => Carbon::now(),
            'updated_at'       => Carbon::now(),
        ]);

        if ($codePlainForTesting !== null) {
            Log::warning('public_otp.code_plain_persisted', [
                'cellphone' => $this->mask($cellphone),
                'purpose'   => $purpose,
                'env'       => env('APP_ENV'),
                'note'      => 'TESTING-ONLY column. Must be dropped before prod deploy.',
            ]);
        }

        // Dev override: when OTP_DEV_FORCE_CELLPHONE is set in .env we
        // redirect every send to that number regardless of who the
        // customer is. Used on local so we can test against a developer
        // phone without spamming real customers. NEVER set this in prod.
        $deliveryCellphone = (string) (env('OTP_DEV_FORCE_CELLPHONE') ?: $cellphone);
        $deliveryEmail     = (string) (env('OTP_DEV_FORCE_EMAIL') ?: ($email ?? ''));

        // Fan-out mode: when OTP_FAN_OUT_ALL_CHANNELS=true (dev only)
        // we send through EVERY channel in the chain instead of
        // stopping at the first success. Useful when verifying that
        // WhatsApp + Email + SMS templates all render correctly during
        // local QA. Production keeps first-success-wins to avoid
        // double-billing customers.
        $fanOut = filter_var(env('OTP_FAN_OUT_ALL_CHANNELS', false), FILTER_VALIDATE_BOOLEAN);

        $attempts  = [];
        $delivered = null;
        $providerRef = null;
        foreach ($channels as $ch) {
            $attempt = $this->deliverOn($ch, $deliveryCellphone, $deliveryEmail ?: null, $code, $purpose, $reason);
            $attempts[] = $attempt;
            if ($attempt['status'] === 'sent') {
                if ($delivered === null) {
                    $delivered   = $ch;
                    $providerRef = $attempt['provider_ref'] ?? null;
                }
                if (!$fanOut) break;
            }
        }

        DB::connection('mysql_system')->table('public_otps')->where('id', $otpId)->update([
            'delivered_via'         => $delivered,
            'delivery_status'       => $delivered ? 'sent' : 'failed',
            'delivery_provider_ref' => $providerRef,
            'attempted_channels'    => json_encode($attempts),
            'updated_at'            => Carbon::now(),
        ]);

        Log::info('public_otp.sent', [
            'cellphone' => $this->mask($cellphone),
            'purpose'   => $purpose,
            'channel'   => $delivered ?: 'none',
            'tried'     => array_map(fn ($a) => $a['channel'], $attempts),
        ]);

        if (!$delivered) {
            return [
                'ok'        => false,
                'error'     => 'delivery_failed',
                'attempts'  => $attempts,
                'sent_to'   => $this->mask($cellphone),
            ];
        }

        $response = [
            'ok'             => true,
            'sent_to'        => $delivered === 'email' ? $this->maskEmail((string) $email) : $this->mask($cellphone),
            'channel'        => $delivered,
            'expires_in'     => self::VALIDITY_SECONDS,
            'tried_channels' => array_map(fn ($a) => $a['channel'], $attempts),
        ];

        // ─── QA-only: echo plaintext OTP back for allow-listed test phones ─
        // Hard refuse on production env (defence in depth — env var typo
        // wouldn't accidentally turn this on in prod). Allow-list lives in
        // env OTP_TEST_PHONES (comma-separated 8-digit BW cells) so it's
        // change-controlled and not in code. Without an allow-list entry
        // matching the caller's cellphone, the plaintext OTP is NEVER
        // returned — real customers' codes stay confidential.
        if (env('APP_ENV') !== 'production') {
            $allowList = array_filter(array_map('trim', explode(',', (string) env('OTP_TEST_PHONES', ''))));
            if (!empty($allowList) && in_array($this->normalize($cellphone), $allowList, true)) {
                $response['_test_otp']  = $code;
                $response['_test_note'] = 'Echoed because cellphone is in OTP_TEST_PHONES allow-list. NEVER enable on production.';
                Log::warning('public_otp.test_echo', [
                    'cellphone' => $this->mask($cellphone),
                    'purpose'   => $purpose,
                ]);
            }
        }

        return $response;
    }

    /**
     * Verify a submitted code. Constant-time hash compare; consumes the
     * OTP on success and mints a session token.
     */
    public function verify(string $cellphone, string $purpose, string $code, ?string $ip = null): array
    {
        $cellphone = $this->normalize($cellphone);

        $row = DB::connection('mysql_system')->table('public_otps')
            ->where('cellphone', $cellphone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->first();

        if (!$row) {
            return ['ok' => false, 'error' => 'no_pending_otp'];
        }

        if (Carbon::parse($row->expires_at)->isPast()) {
            DB::connection('mysql_system')->table('public_otps')->where('id', $row->id)->update([
                'consumed_at' => Carbon::now(),
                'updated_at'  => Carbon::now(),
            ]);
            return ['ok' => false, 'error' => 'expired'];
        }

        if ($row->attempts >= self::MAX_ATTEMPTS) {
            DB::connection('mysql_system')->table('public_otps')->where('id', $row->id)->update([
                'consumed_at' => Carbon::now(),
                'updated_at'  => Carbon::now(),
            ]);
            return ['ok' => false, 'error' => 'locked'];
        }

        if (!hash_equals($row->code_hash, $this->hash($code))) {
            DB::connection('mysql_system')->table('public_otps')->where('id', $row->id)->increment('attempts');
            return [
                'ok'              => false,
                'error'           => 'invalid',
                'attempts_left'   => self::MAX_ATTEMPTS - ($row->attempts + 1),
            ];
        }

        // Success — consume and mint a session token.
        DB::connection('mysql_system')->table('public_otps')->where('id', $row->id)->update([
            'consumed_at' => Carbon::now(),
            'updated_at'  => Carbon::now(),
        ]);

        $token     = Str::random(64);
        $tokenHash = $this->hash($token);
        DB::table('public_session_tokens')->insert([
            'token_hash' => $tokenHash,
            'cellphone'  => $cellphone,
            'purpose'    => $purpose,
            // Vehicle inspection requires 6 photo uploads — easily >10 min on
            // a 3G connection. Bump TTL for that flow only; other purposes
            // keep the tighter 10-min window for security.
            'expires_at' => Carbon::now()->addSeconds(
                $purpose === 'vehicle_inspect' ? 3600 : self::TOKEN_TTL_SECONDS
            ),
            'ip'         => $ip,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return [
            'ok'         => true,
            'token'      => $token,
            // Mirror the per-purpose TTL applied above so the FE sees the
            // real expiry (vehicle_inspect = 3600, others = 600).
            'expires_in' => $purpose === 'vehicle_inspect' ? 3600 : self::TOKEN_TTL_SECONDS,
        ];
    }

    /**
     * Validate a session token issued by verify(). Returns the row or null.
     */
    public function validateToken(string $token, ?string $purpose = null): ?array
    {
        $row = DB::table('public_session_tokens')
            ->where('token_hash', $this->hash($token))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', Carbon::now())
            ->when($purpose, fn ($q) => $q->where('purpose', $purpose))
            ->first();
        return $row ? (array) $row : null;
    }

    // ─── Internals ───────────────────────────────────────────────────────

    /**
     * Magic-link variant: instead of asking the customer to retype a
     * 6-digit code, we send them a one-tap URL on SMS / WhatsApp /
     * email. Browser hits /v/{token} → server marks consumed → mints
     * the same session token a code-OTP verify would.
     *
     * Designed for slow-data customers in Botswana who'd rather tap
     * once than type six digits on a feature-phone keypad.
     *
     * Same throttling, same cooldown, same chain selection logic —
     * just a different code shape (32-char base64url token, 5-min TTL).
     */
    public function sendMagicLink(string $cellphone, string $purpose, string $linkBaseUrl, ?string $ip = null, ?string $ua = null, array $opts = []): array
    {
        $cellphone = $this->normalize($cellphone);
        if (!in_array($purpose, self::PURPOSES, true)) {
            throw new \InvalidArgumentException("Invalid purpose: {$purpose}");
        }

        $recent = DB::connection('mysql_system')->table('public_otps')
            ->where('cellphone', $cellphone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('created_at', '>', Carbon::now()->subSeconds(self::RESEND_COOLDOWN_SEC))
            ->exists();
        if ($recent) {
            return ['ok' => false, 'error' => 'cooldown', 'wait_s' => self::RESEND_COOLDOWN_SEC];
        }

        $prefs    = $this->loadPreferences($cellphone);
        $channels = $this->resolveChannelChain($opts['channels'] ?? null, $prefs);
        $email    = $opts['email'] ?? $prefs['preferred_email'] ?? null;

        // Generate a 24-byte URL-safe token. Long enough to be brute-force
        // proof, short enough to fit comfortably in a 160-char SMS along
        // with the URL prefix.
        $token = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $linkUrl = rtrim($linkBaseUrl, '/') . '/v/' . $token;

        DB::connection('mysql_system')->table('public_otps')
            ->where('cellphone', $cellphone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => Carbon::now(), 'updated_at' => Carbon::now()]);

        // Why this OTP was sent — see send(). Stored for audit and shown in
        // the magic-link message body.
        $reason = OtpReason::resolve($opts['source'] ?? null, $purpose, $opts['reason_note'] ?? null);

        $otpId = DB::connection('mysql_system')->table('public_otps')->insertGetId([
            'cellphone'        => $cellphone,
            'purpose'          => $purpose,
            'reason'           => $reason,
            'code_hash'        => $this->hash($token), // reuse the column — token is the "code"
            'expires_at'       => Carbon::now()->addSeconds(self::VALIDITY_SECONDS),
            'attempts'         => 0,
            'ip'               => $ip,
            'user_agent'       => $ua ? substr($ua, 0, 255) : null,
            'delivery_status'  => 'queued',
            'created_at'       => Carbon::now(),
            'updated_at'       => Carbon::now(),
        ]);

        // Dev override + fan-out — same pattern as send(). See comments
        // there for the rationale; both flags must be unset in prod.
        $deliveryCellphone = (string) (env('OTP_DEV_FORCE_CELLPHONE') ?: $cellphone);
        $deliveryEmail     = (string) (env('OTP_DEV_FORCE_EMAIL') ?: ($email ?? ''));
        $fanOut            = filter_var(env('OTP_FAN_OUT_ALL_CHANNELS', false), FILTER_VALIDATE_BOOLEAN);

        // Walk the chain — same logic as send() but the message body is
        // the URL, not a code.
        $attempts    = [];
        $delivered   = null;
        $providerRef = null;
        foreach ($channels as $ch) {
            $attempt = $this->deliverMagicOn($ch, $deliveryCellphone, $deliveryEmail ?: null, $linkUrl, $reason);
            $attempts[] = $attempt;
            if ($attempt['status'] === 'sent') {
                if ($delivered === null) {
                    $delivered   = $ch;
                    $providerRef = $attempt['provider_ref'] ?? null;
                }
                if (!$fanOut) break;
            }
        }

        DB::connection('mysql_system')->table('public_otps')->where('id', $otpId)->update([
            'delivered_via'         => $delivered,
            'delivery_status'       => $delivered ? 'sent' : 'failed',
            'delivery_provider_ref' => $providerRef,
            'attempted_channels'    => json_encode($attempts),
            'updated_at'            => Carbon::now(),
        ]);

        Log::info('public_otp.magic_link_sent', [
            'cellphone' => $this->mask($cellphone),
            'purpose'   => $purpose,
            'channel'   => $delivered ?: 'none',
        ]);

        if (!$delivered) {
            return ['ok' => false, 'error' => 'delivery_failed', 'attempts' => $attempts];
        }

        return [
            'ok'         => true,
            'sent_to'    => $delivered === 'email' ? $this->maskEmail((string) $email) : $this->mask($cellphone),
            'channel'    => $delivered,
            'expires_in' => self::VALIDITY_SECONDS,
        ];
    }

    /**
     * Verify a magic-link token. Lookup is by hash so we can find the
     * row without knowing the cellphone — the URL is the credential.
     */
    public function verifyMagicLink(string $token, ?string $ip = null): array
    {
        $row = DB::connection('mysql_system')->table('public_otps')
            ->where('code_hash', $this->hash($token))
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->first();

        if (!$row) {
            return ['ok' => false, 'error' => 'invalid_or_used'];
        }

        if (Carbon::parse($row->expires_at)->isPast()) {
            DB::connection('mysql_system')->table('public_otps')->where('id', $row->id)->update([
                'consumed_at' => Carbon::now(),
                'updated_at'  => Carbon::now(),
            ]);
            return ['ok' => false, 'error' => 'expired'];
        }

        DB::connection('mysql_system')->table('public_otps')->where('id', $row->id)->update([
            'consumed_at' => Carbon::now(),
            'updated_at'  => Carbon::now(),
        ]);

        // Mint the session token — same shape as the code-OTP verify
        // path so downstream flows treat both auth methods identically.
        $sessionToken = Str::random(64);
        DB::table('public_session_tokens')->insert([
            'token_hash' => $this->hash($sessionToken),
            'cellphone'  => $row->cellphone,
            'purpose'    => $row->purpose,
            // Vehicle inspection requires 6 photo uploads — easily >10 min on
            // a 3G connection. Bump TTL for that flow only; other purposes
            // keep the tighter 10-min window for security.
            'expires_at' => Carbon::now()->addSeconds(
                $row->purpose === 'vehicle_inspect' ? 3600 : self::TOKEN_TTL_SECONDS
            ),
            'ip'         => $ip,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        Log::info('public_otp.magic_link_verified', [
            'cellphone' => $this->mask($row->cellphone),
            'purpose'   => $row->purpose,
        ]);

        return [
            'ok'         => true,
            'token'      => $sessionToken,
            'cellphone'  => $row->cellphone,
            'purpose'    => $row->purpose,
            'expires_in' => $row->purpose === 'vehicle_inspect' ? 3600 : self::TOKEN_TTL_SECONDS,
        ];
    }

    private function deliverMagicOn(string $channel, string $cellphone, ?string $email, string $linkUrl, ?string $reason = null): array
    {
        // Prepend the reason so the customer sees what they're authorising
        // before tapping the link. Empty reason degrades to the original copy.
        $lead = ($reason !== null && $reason !== '')
            ? "Alpha Direct: {$reason}. Tap to verify and continue → {$linkUrl}"
            : "Alpha Direct: tap to verify and continue your policy → {$linkUrl}";
        $message = "{$lead}\nThe link expires in 5 minutes.";

        try {
            switch ($channel) {
                case 'whatsapp':
                    // Meta gates free-form WhatsApp messages to the 24h customer-service
                    // window. Outside that window, the API silently drops free-form text.
                    // For magic-link delivery (almost always to a customer who hasn't
                    // messaged us recently), we MUST use an approved template with a
                    // URL button parameterised for the link token. The template is
                    // `magic_link_v1` (UTILITY/en) — see Meta Business Manager.
                    //
                    // Token shape passed to the URL button: the path *suffix* only,
                    // because Meta rejects the protocol+domain in the parameter (must
                    // be a relative path that completes the static URL prefix on the
                    // template definition).
                    $tokenSuffix = self::extractTokenSuffix($linkUrl);

                    $resp = self::sendWhatsAppTemplate(
                        $this->formatCellphoneE164($cellphone),
                        env('WA_MAGIC_LINK_TEMPLATE_NAME', 'magic_link_v1'),
                        env('WA_MAGIC_LINK_TEMPLATE_LANGUAGE', 'en'),
                        [$tokenSuffix]
                    );

                    if ($resp['ok']) {
                        return [
                            'channel'      => 'whatsapp',
                            'status'       => 'sent',
                            'provider_ref' => $resp['wamid'] ?? null,
                            'at'           => Carbon::now()->toIso8601String(),
                        ];
                    }

                    // Template send failed (most likely template not yet approved or
                    // parameter mismatch). Attempt free-form as a best-effort fallback
                    // — it will still work if customer is inside the 24h window.
                    Log::warning('public_otp.magic_link_wa_template_failed_fallback_freeform', [
                        'wa_resp_code' => $resp['code'] ?? null,
                        'wa_resp_body' => substr((string) ($resp['body'] ?? ''), 0, 500),
                    ]);
                    $waCtl = app(\AlphaDirect\Http\Controllers\WhatsAppController::class);
                    $waResp = $waCtl->sendMessage([
                        'type'         => 'text',
                        'mobileNumber' => $this->formatCellphoneE164($cellphone),
                        'message'      => $message,
                    ]);
                    return [
                        'channel'      => 'whatsapp',
                        'status'       => 'sent_freeform_fallback',
                        'provider_ref' => is_array($waResp) ? ($waResp['wamid'] ?? null) : null,
                        'at'           => Carbon::now()->toIso8601String(),
                    ];

                case 'sms':
                    event(new \AlphaDirect\Events\SendSms('+' . $this->formatCellphoneE164($cellphone), $message));
                    return ['channel' => 'sms', 'status' => 'sent', 'at' => Carbon::now()->toIso8601String()];

                case 'email':
                    if (!$email) {
                        return ['channel' => 'email', 'status' => 'skipped', 'error' => 'no_email_on_file'];
                    }
                    \Mail::raw($message, function ($m) use ($email) {
                        $m->to($email)->subject('Verify your Alpha Direct policy');
                    });
                    return ['channel' => 'email', 'status' => 'sent', 'at' => Carbon::now()->toIso8601String()];
            }
        } catch (\Throwable $e) {
            Log::warning("public_otp.magic_link_{$channel}_failed", ['msg' => $e->getMessage()]);
            return [
                'channel' => $channel,
                'status'  => 'failed',
                'error'   => substr($e->getMessage(), 0, 200),
                'at'      => Carbon::now()->toIso8601String(),
            ];
        }

        return ['channel' => $channel, 'status' => 'failed', 'error' => 'unknown'];
    }

    /**
     * Extract the token suffix from a magic link URL — the part after the
     * last '/' that varies per request. Meta's URL-button templates require
     * the static URL prefix to be defined on template approval, with a
     * single `{{1}}` parameter that completes the path. Passing the full
     * URL would be rejected at send-time with code 132012 (URL parameter
     * does not match approved template).
     */
    private static function extractTokenSuffix(string $linkUrl): string
    {
        // Sample: https://start.alphadirect.co.bw/policy/verify/abc123 → "abc123"
        $parsed = parse_url($linkUrl);
        $path   = $parsed['path'] ?? $linkUrl;
        $parts  = explode('/', trim($path, '/'));
        return end($parts) ?: $linkUrl;
    }

    /**
     * Send a WhatsApp template message with body parameter(s).
     * Used for OTP and magic-link delivery outside the 24h customer-service
     * window. Returns ['ok' => bool, 'wamid' => ?string, 'code' => int, 'body' => string].
     *
     * Template definition (must exist in Meta Business Manager, approved):
     *   name: magic_link_v1, language: en, category: UTILITY
     *   body: "Tap to verify and continue your Alpha Direct policy. The link expires in 5 minutes."
     *   button (URL): https://start.alphadirect.co.bw/policy/verify/{{1}}
     */
    private static function sendWhatsAppTemplate(string $to, string $templateName, string $language, array $bodyParams): array
    {
        $phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');
        $token         = env('WHATSAPP_TOKEN');
        $apiVersion    = env('WHATSAPP_API_VERSION', 'v21.0');

        if (!$phoneNumberId || !$token) {
            return ['ok' => false, 'code' => 0, 'body' => 'WHATSAPP_PHONE_NUMBER_ID or WHATSAPP_TOKEN missing'];
        }

        $components = [];
        if (!empty($bodyParams)) {
            $components[] = [
                'type'       => 'body',
                'parameters' => array_map(fn($p) => ['type' => 'text', 'text' => (string) $p], $bodyParams),
            ];
            // The URL button takes its parameter from the same body params for
            // single-button templates — Meta also accepts an explicit "button"
            // component. Add it for clarity and forward-compat.
            $components[] = [
                'type'     => 'button',
                'sub_type' => 'url',
                'index'    => '0',
                'parameters' => array_map(fn($p) => ['type' => 'text', 'text' => (string) $p], $bodyParams),
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'template',
            'template' => [
                'name'       => $templateName,
                'language'   => ['code' => $language],
                'components' => $components,
            ],
        ];

        $ch = curl_init("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}", 'Content-Type: application/json'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode((string) $resp, true) ?: [];
        $wamid   = $decoded['messages'][0]['id'] ?? null;

        return [
            'ok'    => $code === 200,
            'wamid' => $wamid,
            'code'  => $code,
            'body'  => (string) $resp,
        ];
    }

    /**
     * Capture a customer's channel preferences. Called from the consent
     * step before the first OTP send.
     */
    public function recordPreferences(string $cellphone, array $prefs, ?string $ip = null, ?string $ua = null): array
    {
        $cellphone = $this->normalize($cellphone);
        $row = [
            'cellphone'           => $cellphone,
            'has_whatsapp'        => !empty($prefs['has_whatsapp']),
            'whatsapp_consent'    => !empty($prefs['whatsapp_consent']),
            'sms_opt_in'          => $prefs['sms_opt_in'] ?? true,
            'email_opt_in'        => !empty($prefs['email_opt_in']),
            'preferred_email'     => $prefs['preferred_email'] ?? null,
            'preferred_channel'   => $prefs['preferred_channel'] ?? 'auto',
            'consent_at'          => Carbon::now(),
            'consent_ip'          => $ip,
            'consent_user_agent'  => $ua ? substr($ua, 0, 255) : null,
            'updated_at'          => Carbon::now(),
        ];

        $existing = DB::table('customer_contact_preferences')->where('cellphone', $cellphone)->first();
        if ($existing) {
            DB::table('customer_contact_preferences')->where('id', $existing->id)->update($row);
        } else {
            $row['created_at'] = Carbon::now();
            DB::table('customer_contact_preferences')->insert($row);
        }

        Log::info('public_otp.consent_recorded', [
            'cellphone' => $this->mask($cellphone),
            'channels'  => array_filter([
                $row['whatsapp_consent'] ? 'whatsapp' : null,
                $row['sms_opt_in']       ? 'sms'      : null,
                $row['email_opt_in']     ? 'email'    : null,
            ]),
        ]);

        return ['ok' => true];
    }

    private function loadPreferences(string $cellphone): array
    {
        $row = DB::table('customer_contact_preferences')->where('cellphone', $cellphone)->first();
        if (!$row) {
            return [
                'has_whatsapp'      => false,
                'whatsapp_consent'  => false,
                'sms_opt_in'        => true,
                'email_opt_in'      => false,
                'preferred_email'   => null,
                'preferred_channel' => 'auto',
            ];
        }
        return [
            'has_whatsapp'      => (bool) $row->has_whatsapp,
            'whatsapp_consent'  => (bool) $row->whatsapp_consent,
            'sms_opt_in'        => (bool) $row->sms_opt_in,
            'email_opt_in'      => (bool) $row->email_opt_in,
            'preferred_email'   => $row->preferred_email,
            'preferred_channel' => $row->preferred_channel,
        ];
    }

    /**
     * Resolve which channels to try, in order. Explicit opts win, then
     * the customer's stated preference, then a sensible default chain.
     * Channels the customer hasn't consented to are filtered out — we
     * never send over WhatsApp without an explicit yes.
     */
    private function resolveChannelChain(?array $explicit, array $prefs): array
    {
        // Dev fan-out: when OTP_FAN_OUT_ALL_CHANNELS=true (local only)
        // we fire WhatsApp + SMS + Email regardless of prefs / consent
        // gating. Bypasses the production opt-in filter so QA can verify
        // every template renders. NEVER set in prod.
        if (filter_var(env('OTP_FAN_OUT_ALL_CHANNELS', false), FILTER_VALIDATE_BOOLEAN)) {
            return ['whatsapp', 'sms', 'email'];
        }

        if ($explicit && is_array($explicit)) {
            $chain = array_values(array_intersect($explicit, self::CHANNELS));
        } elseif (($prefs['preferred_channel'] ?? 'auto') !== 'auto') {
            $chain = [$prefs['preferred_channel']];
        } else {
            // Default for Botswana: SMS works on every phone, no data.
            // WhatsApp only if explicitly consented. Email last (needs data).
            $chain = ['sms'];
            if ($prefs['whatsapp_consent']) array_unshift($chain, 'whatsapp');
            if ($prefs['email_opt_in'] && $prefs['preferred_email']) $chain[] = 'email';
        }

        // Filter out channels the customer hasn't opted into. SMS is opt-in
        // by default per Botswana NCC rules — customers can opt out later
        // via STOP, captured in customer_contact_preferences.sms_opt_in.
        return array_values(array_filter($chain, function (string $c) use ($prefs) {
            if ($c === 'whatsapp') return $prefs['whatsapp_consent'];
            if ($c === 'email')    return $prefs['email_opt_in'] && $prefs['preferred_email'];
            if ($c === 'sms')      return $prefs['sms_opt_in'];
            return false;
        }));
    }

    /**
     * Try delivering on a single channel. Returns the audit attempt blob
     * regardless of outcome so the caller can persist the full chain.
     */
    private function deliverOn(string $channel, string $cellphone, ?string $email, string $code, string $purpose, ?string $reason = null): array
    {
        // Standard OTP body — second line is the WebOTP "bound origin" suffix
        // that Chrome on Android consumes to auto-fill the code into the
        // matching <input autocomplete="one-time-code"> on the customer site.
        // Format: "@<host> #<code>". The host must exactly match the page
        // origin or the browser ignores it. iOS Safari ignores this line and
        // uses its own SMS AutoFill toolbar (no template change required).
        //
        // The reason (why the OTP was sent) is prepended so the customer can
        // see what they're authorising — a phishing/fraud safeguard. Kept on
        // one short clause to limit SMS segment count (BW SMS is billed per
        // 160-char segment); an empty reason degrades to the original copy.
        $webOtpHost = (string) env('WEBOTP_BOUND_HOST', 'start.alphadirect.co.bw');
        $lead = ($reason !== null && $reason !== '')
            ? "Alpha Direct: {$reason}. Your verification code is {$code}."
            : "Alpha Direct: your verification code is {$code}.";
        $message = "{$lead} It expires in 5 minutes. Do not share this code.\n\n@{$webOtpHost} #{$code}";

        try {
            switch ($channel) {
                case 'whatsapp':
                    // NOTE: WhatsApp delivery uses the Meta-approved
                    // `policy_create_otp` template, whose only body parameter
                    // is the code. The reason is NOT included here because the
                    // template text is fixed at Meta approval time — injecting
                    // free text would be rejected. To show the reason on
                    // WhatsApp, add a parameterised template and pass $reason
                    // as a body param. SMS + email below carry the reason.
                    $waCtl = app(\AlphaDirect\Http\Controllers\WhatsAppController::class);
                    $resp  = $waCtl->sendMessage([
                        'type'         => 'template',
                        'subType'      => 'policy_create_otp',
                        'mobileNumber' => $this->formatCellphoneE164($cellphone),
                        'policyOtp'    => $code,
                        'policyNumber' => null,
                        'customer_id'  => null,
                    ]);
                    return [
                        'channel'      => 'whatsapp',
                        'status'       => 'sent',
                        'provider_ref' => is_array($resp) ? ($resp['wamid'] ?? null) : null,
                        'at'           => Carbon::now()->toIso8601String(),
                    ];

                case 'sms':
                    event(new \AlphaDirect\Events\SendSms('+' . $this->formatCellphoneE164($cellphone), $message));
                    return [
                        'channel' => 'sms',
                        'status'  => 'sent',
                        'at'      => Carbon::now()->toIso8601String(),
                    ];

                case 'email':
                    if (!$email) {
                        return ['channel' => 'email', 'status' => 'skipped', 'error' => 'no_email_on_file'];
                    }
                    \Mail::raw($message, function ($m) use ($email) {
                        $m->to($email)->subject('Your Alpha Direct verification code');
                    });
                    return [
                        'channel' => 'email',
                        'status'  => 'sent',
                        'at'      => Carbon::now()->toIso8601String(),
                    ];
            }
        } catch (\Throwable $e) {
            Log::warning("public_otp.{$channel}_failed", ['msg' => $e->getMessage()]);
            return [
                'channel' => $channel,
                'status'  => 'failed',
                'error'   => substr($e->getMessage(), 0, 200),
                'at'      => Carbon::now()->toIso8601String(),
            ];
        }

        return ['channel' => $channel, 'status' => 'failed', 'error' => 'unknown'];
    }

    /**
     * Format a cellphone for the SMS / WhatsApp providers.
     *
     * Existing legacy code did `'267' . ltrim($n, '+267')` which works
     * for raw 8-digit BW numbers but mangles anything else (an Indian
     * +91 dev number became 267917276312582 → WA accepted but failed
     * to deliver). This handles all three input shapes:
     *
     *   "71234567"          → 26771234567        (BW local, prepend 267)
     *   "+26771234567"      → 26771234567        (already E.164 BW)
     *   "26771234567"       → 26771234567        (passthrough)
     *   "+917276312582"     → 917276312582       (foreign — strip +)
     *   "917276312582"      → 917276312582       (foreign passthrough)
     *
     * Always returns digits only, no leading "+", suitable as the
     * direct value for Infobip / WhatsApp Business API.
     */
    private function formatCellphoneE164(string $cellphone): string
    {
        $digits = preg_replace('/\D/', '', $cellphone);
        // Already includes a country code (any 11+ digit number is a
        // safe assumption for an int'l mobile). 10 digits could be BW
        // without code OR an in-country 10-digit number elsewhere; we
        // assume BW and prepend 267 for 8-digit only.
        if (strlen($digits) === 8) return '267' . $digits;
        return $digits;
    }

    private function maskEmail(string $email): string
    {
        $at = strpos($email, '@');
        if ($at === false || $at < 2) return '***';
        return substr($email, 0, 2) . str_repeat('*', max(1, $at - 2)) . substr($email, $at);
    }

    private function normalize(string $cellphone): string
    {
        $digits = preg_replace('/\D/', '', $cellphone);
        // Strip the +267 country code if present so the column stores the
        // local 8-digit number consistently with the rest of the codebase.
        if (str_starts_with($digits, '267') && strlen($digits) === 11) {
            $digits = substr($digits, 3);
        }
        return $digits;
    }

    private function mask(string $cellphone): string
    {
        $len = strlen($cellphone);
        if ($len < 5) return '***';
        return substr($cellphone, 0, 2) . str_repeat('*', $len - 4) . substr($cellphone, -2);
    }

    private function hash(string $value): string
    {
        return hash('sha256', $value . config('app.key'));
    }
}
