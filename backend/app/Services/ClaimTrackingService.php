<?php

namespace AlphaDirect\Services;

use AlphaDirect\Events\SendSms;
use AlphaDirect\Models\ClaimAccessLink;
use AlphaDirect\Models\ClaimTrackingAccessLog;
use AlphaDirect\Models\ClaimTrackingOtp;
use AlphaDirect\Models\ClaimTrackingSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * ClaimTrackingService — the engine behind the claimant self-service status
 * page (Claims Tracker -> Graphite Phase 2). Fully additive and gated by the
 * `claimant_tracking` runtime flag (Admin > Integrations, default OFF).
 *
 * Flow (all public, unauthenticated, rate-limited at the route):
 *   1. issueLink(claimId)        -> opaque, 90-day, revocable per-claim token
 *   2. requestOtp(identifier)    -> resolve claim (by link token OR claim
 *                                   reference), send a single-use 6-digit code
 *                                   to the claim's REGISTERED contact via SMS
 *                                   (Infobip) and/or email (Mailgun)
 *   3. verifyOtp(identifier,code)-> constant-time check; on success mint a
 *                                   short-lived session token scoped to THAT
 *                                   ONE claim
 *   4. getStatus(sessionToken)   -> read-only, claimant-safe status payload
 *
 * Design guarantees:
 *   - We REUSE the existing delivery paths only: event(SendSms) -> the Infobip
 *     listener (SSM INFOBIP_KEY), and Laravel Mail -> Mailgun. No second mailer.
 *   - OTP codes and session tokens are stored HASHED (sha256 + app.key), never
 *     in plaintext — same convention as PublicOtpService.
 *   - Fail-closed + no enumeration: requestOtp ALWAYS returns a generic success
 *     so a caller cannot tell a real claim reference from a fake one. verify
 *     collapses "no link / no otp / wrong code" into one generic error.
 *   - DPA: the claimant only ever sees THEIR OWN claim's status/stage/dates.
 *     No reserve/paid, no internal notes, no PII beyond the reference they hold.
 */
class ClaimTrackingService
{
    /** OTP validity window (seconds). */
    public const OTP_TTL_SECONDS = 300;      // 5 minutes

    /** Minimum gap between OTP sends for one link (seconds). */
    public const RESEND_COOLDOWN_SEC = 60;

    /**
     * Max OTP sends per link per day. The cooldown caps the burst rate; this
     * caps the DAILY TOTAL so an attacker rotating IPs (route throttles are
     * per-IP) cannot run up unbounded SMS cost or harass a claimant's registered
     * phone. Generous enough for a legit claimant's retries; resets each day.
     */
    public const MAX_OTP_SENDS_PER_DAY = 10;

    /** Wrong-code lockout threshold per OTP. */
    public const MAX_ATTEMPTS = 5;

    /** Session token lifetime after a correct OTP (seconds). */
    public const SESSION_TTL_SECONDS = 900;  // 15 minutes (claimants read slowly)

    /** Integration key for the runtime toggle + config fallback. */
    public const FLAG = 'claimant_tracking';

    /** Generic message shown regardless of whether a claim matched. */
    private const GENERIC_SENT_MESSAGE =
        'If we found a matching claim, a verification code has been sent to the phone number or email we have on file.';

    /**
     * Is the claimant self-service feature currently armed? Runtime toggle
     * (Admin > Integrations) wins; falls back to config('services.claimant_tracking.enabled')
     * which is env('CLAIMANT_TRACKING_ENABLED', false). Default OFF.
     */
    public static function isEnabled(): bool
    {
        return IntegrationSettings::isEnabled(self::FLAG);
    }

    // ─── 1. Link issuance ─────────────────────────────────────────────────

    /**
     * Create a fresh access link for a claim. Used by ops/console and (later)
     * automatically on claim registration. Returns the model (its `token` is
     * the credential to embed in the claimant URL).
     */
    public function issueLink(int $claimId, ?string $issuedBy = null, ?int $ttlDays = null, string $channel = 'system'): ClaimAccessLink
    {
        $claim = $this->findClaim($claimId);
        $customerId = $claim->customer_id ?? null;

        $link = new ClaimAccessLink();
        $link->claim_id      = $claimId;
        $link->customer_id   = $customerId;
        $link->status        = 'active';
        $link->issued_by     = $issuedBy ?: 'system';
        $link->issue_channel = $channel;
        if ($ttlDays !== null) {
            $link->expires_at = Carbon::now()->addDays($ttlDays);
        }
        $link->save();

        ClaimTrackingAccessLog::record('link_issued', $link->id, $claimId, $channel, null, null, [
            'issued_by'  => $link->issued_by,
            'expires_at' => optional($link->expires_at)->toIso8601String(),
        ]);

        return $link;
    }

    /**
     * Return a usable link for a claim, creating one if none exists yet.
     * Used by the reference-entry path (claimant types their claim number).
     */
    public function getOrCreateUsableLinkForClaim(int $claimId): ClaimAccessLink
    {
        // purpose 'status' only — a claim-form link (purpose 'fill_form') is
        // minted without an OTP requirement and must never be handed back as a
        // status credential. Legacy rows default to 'status', so unaffected.
        $existing = ClaimAccessLink::where('claim_id', $claimId)
            ->where('purpose', 'status')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now());
            })
            ->orderByDesc('id')
            ->first();

        if ($existing && $existing->isUsable()) {
            return $existing;
        }

        return $this->issueLink($claimId, 'self-service', null, 'reference');
    }

    /**
     * Send the claimant the tracking LINK itself (not an OTP) — used when a
     * link is auto-issued on claim registration. SEND-GATED: does nothing
     * unless the `claimant_tracking` flag is armed, so with the flag OFF the
     * link is created/queued but never delivered. First-success across SMS
     * (Infobip) then email (Mailgun), reusing the exact same delivery paths as
     * the OTP flow (no second mailer). Never throws.
     *
     * @return array{sent:bool, channel?:?string, reason?:string}
     */
    public function sendLinkNotification(ClaimAccessLink $link): array
    {
        // Belt-and-braces flag gate (the caller also checks): any future caller
        // is safe — a link notification is never sent while the flag is OFF.
        if (!self::isEnabled()) {
            return ['sent' => false, 'reason' => 'flag_off'];
        }

        $contact = $this->resolveContact($link);
        if (empty($contact['cellphone']) && empty($contact['email'])) {
            Log::info('[ClaimTracking] link not sent — no contact on file', ['claim_id' => $link->claim_id]);
            return ['sent' => false, 'reason' => 'no_contact'];
        }

        $url       = rtrim((string) config('app.url'), '/') . '/claim-status/' . $link->token;
        $reference = $contact['reference'] ?? '';
        $body = 'Alpha Direct Claims: you can now track your claim'
            . ($reference ? " {$reference}" : '') . " online here: {$url}"
            . ' You will be asked for a one-time code to confirm it is you. Do not share this link.';

        $channel = null;

        // SMS via the existing SendSms event -> Infobip listener (SSM key).
        if (!empty($contact['cellphone'])) {
            try {
                event(new SendSms('+' . $this->e164($contact['cellphone']), $body, ['source' => 'claim_tracking_link']));
                $channel = 'sms';
            } catch (\Throwable $e) {
                Log::warning('[ClaimTracking] link SMS send failed', ['claim_id' => $link->claim_id, 'msg' => $e->getMessage()]);
            }
        }

        // Email via Laravel Mail -> Mailgun (same transport the app uses).
        if ($channel === null && !empty($contact['email'])) {
            try {
                $email = $contact['email'];
                Mail::raw($body, function ($m) use ($email) {
                    $m->to($email)->subject('Track your Alpha Direct claim');
                });
                $channel = 'email';
            } catch (\Throwable $e) {
                Log::warning('[ClaimTracking] link email send failed', ['claim_id' => $link->claim_id, 'msg' => $e->getMessage()]);
            }
        }

        ClaimTrackingAccessLog::record('link_notified', $link->id, $link->claim_id, $channel, null, null, [
            'delivered' => (bool) $channel,
        ]);

        return ['sent' => (bool) $channel, 'channel' => $channel];
    }

    /** Revoke a link so it can no longer be used. */
    public function revokeLink(string $token, ?string $revokedBy = null): bool
    {
        $link = ClaimAccessLink::where('token', $token)->first();
        if (!$link) {
            return false;
        }
        $link->status     = 'revoked';
        $link->revoked_at = Carbon::now();
        $link->revoked_by = $revokedBy ?: 'system';
        $link->save();

        ClaimTrackingAccessLog::record('link_revoked', $link->id, $link->claim_id, null, null, null, [
            'revoked_by' => $link->revoked_by,
        ]);

        return true;
    }

    // ─── 2. OTP request ───────────────────────────────────────────────────

    /**
     * Send an OTP for the claim identified by $identifier (a link token OR a
     * claim reference). ALWAYS returns a generic success so the endpoint can
     * never be used to enumerate valid references. Real outcomes are logged.
     */
    public function requestOtp(string $identifier, ?string $ip = null, ?string $ua = null): array
    {
        $generic = ['ok' => true, 'message' => self::GENERIC_SENT_MESSAGE, 'expires_in' => self::OTP_TTL_SECONDS];

        $link = $this->resolveUsableLink($identifier, true);
        if (!$link) {
            // No matching/usable claim. Log a denied probe (no PII) and still
            // return the generic message.
            ClaimTrackingAccessLog::record('denied', null, null, null, $ip, $ua, ['stage' => 'request_otp']);
            return $generic;
        }

        ClaimTrackingAccessLog::record('otp_requested', $link->id, $link->claim_id, null, $ip, $ua);

        // Cooldown — silently skip a resend inside the window (still generic).
        if ($link->otp_last_sent_at && Carbon::parse($link->otp_last_sent_at)->gt(Carbon::now()->subSeconds(self::RESEND_COOLDOWN_SEC))) {
            return array_merge($generic, ['cooldown' => true]);
        }

        // Per-link DAILY send cap. The cooldown above bounds the burst rate; this
        // bounds the daily TOTAL, so an attacker rotating IPs (route throttles are
        // per-IP) still cannot run up unbounded SMS cost or harass a claimant's
        // registered phone. Fail closed — no send, no leak (generic response).
        $sentToday = ClaimTrackingOtp::where('access_link_id', $link->id)
            ->where('created_at', '>=', Carbon::now()->startOfDay())
            ->count();
        if ($sentToday >= self::MAX_OTP_SENDS_PER_DAY) {
            ClaimTrackingAccessLog::record('denied', $link->id, $link->claim_id, null, $ip, $ua, [
                'stage'  => 'request_otp',
                'reason' => 'daily_send_cap',
            ]);
            return $generic;
        }

        $contact = $this->resolveContact($link);
        if (!$contact['cellphone'] && !$contact['email']) {
            Log::info('[ClaimTracking] no contact on file for claim', ['claim_id' => $link->claim_id]);
            return $generic; // fail closed, no leak
        }

        // Generate + persist a hashed, single-use code. Void any prior unconsumed
        // OTP for this link so only the newest code can verify.
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        ClaimTrackingOtp::where('access_link_id', $link->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => Carbon::now(), 'updated_at' => Carbon::now()]);

        $otp = new ClaimTrackingOtp();
        $otp->access_link_id  = $link->id;
        $otp->code_hash       = $this->hash($code);
        $otp->expires_at      = Carbon::now()->addSeconds(self::OTP_TTL_SECONDS);
        $otp->attempts        = 0;
        $otp->ip              = $ip;
        $otp->user_agent      = $ua ? substr($ua, 0, 255) : null;
        $otp->delivery_status = 'queued';
        $otp->save();

        [$delivered, $providerRef] = $this->deliver($link, $contact, $code);

        $otp->delivered_via   = $delivered;
        $otp->delivery_status = $delivered ? 'sent' : 'failed';
        $otp->delivery_ref    = $providerRef;
        $otp->save();

        $link->otp_last_sent_at = Carbon::now();
        $link->otp_send_count   = ($link->otp_send_count ?? 0) + 1;
        $link->save();

        ClaimTrackingAccessLog::record(
            $delivered ? 'otp_sent' : 'denied',
            $link->id,
            $link->claim_id,
            $delivered,
            $ip,
            $ua,
            ['delivered' => (bool) $delivered]
        );

        return $generic;
    }

    // ─── 3. OTP verification ──────────────────────────────────────────────

    /**
     * Verify a submitted code for $identifier. On success returns a session
     * token scoped to that one claim. All failures collapse to a generic error
     * (no enumeration): 'invalid' for any miss; 'expired'/'locked' only once a
     * real OTP row for a real link is in play.
     */
    public function verifyOtp(string $identifier, string $code, ?string $ip = null, ?string $ua = null): array
    {
        $link = $this->resolveUsableLink($identifier, false);
        if (!$link) {
            ClaimTrackingAccessLog::record('otp_failed', null, null, null, $ip, $ua, ['reason' => 'no_link']);
            return ['ok' => false, 'error' => 'invalid'];
        }

        $otp = ClaimTrackingOtp::where('access_link_id', $link->id)
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->first();

        if (!$otp) {
            ClaimTrackingAccessLog::record('otp_failed', $link->id, $link->claim_id, null, $ip, $ua, ['reason' => 'no_pending_otp']);
            return ['ok' => false, 'error' => 'invalid'];
        }

        if ($otp->isExpired()) {
            $otp->consumed_at = Carbon::now();
            $otp->save();
            ClaimTrackingAccessLog::record('otp_failed', $link->id, $link->claim_id, null, $ip, $ua, ['reason' => 'expired']);
            return ['ok' => false, 'error' => 'expired'];
        }

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            $otp->consumed_at = Carbon::now();
            $otp->save();
            ClaimTrackingAccessLog::record('otp_failed', $link->id, $link->claim_id, null, $ip, $ua, ['reason' => 'locked']);
            return ['ok' => false, 'error' => 'locked'];
        }

        if (!hash_equals($otp->code_hash, $this->hash($code))) {
            $otp->increment('attempts');
            ClaimTrackingAccessLog::record('otp_failed', $link->id, $link->claim_id, null, $ip, $ua, ['reason' => 'wrong_code']);
            return ['ok' => false, 'error' => 'invalid', 'attempts_left' => max(0, self::MAX_ATTEMPTS - ($otp->attempts))];
        }

        // Success — consume the OTP and mint a claim-scoped session token.
        $otp->consumed_at = Carbon::now();
        $otp->save();

        $token = Str::random(64);
        $session = new ClaimTrackingSession();
        $session->access_link_id = $link->id;
        $session->claim_id       = $link->claim_id;   // <-- the ONLY claim this session can read
        $session->token_hash     = $this->hash($token);
        $session->expires_at     = Carbon::now()->addSeconds(self::SESSION_TTL_SECONDS);
        $session->ip             = $ip;
        $session->user_agent     = $ua ? substr($ua, 0, 255) : null;
        $session->save();

        ClaimTrackingAccessLog::record('otp_verified', $link->id, $link->claim_id, null, $ip, $ua);

        return [
            'ok'         => true,
            'token'      => $token,
            'expires_in' => self::SESSION_TTL_SECONDS,
        ];
    }

    // ─── 4. Read-only status ──────────────────────────────────────────────

    /**
     * Return the claimant-safe status payload for a valid session token.
     * The session's claim_id is the single source of scope — there is no way
     * to pass a different claim id in.
     */
    public function getStatus(string $sessionToken, ?string $ip = null, ?string $ua = null): array
    {
        $session = ClaimTrackingSession::where('token_hash', $this->hash($sessionToken))->first();
        if (!$session || !$session->isValid()) {
            return ['ok' => false, 'error' => 'session_invalid'];
        }

        $claim = $this->findClaim((int) $session->claim_id);
        if (!$claim) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        // Touch the link view counters (best-effort).
        try {
            ClaimAccessLink::where('id', $session->access_link_id)->update([
                'last_viewed_at' => Carbon::now(),
                'view_count'     => DB::raw('view_count + 1'),
                'updated_at'     => Carbon::now(),
            ]);
        } catch (\Throwable $e) {
            // non-fatal
        }

        ClaimTrackingAccessLog::record('status_viewed', $session->access_link_id, $session->claim_id, null, $ip, $ua);

        return [
            'ok'    => true,
            'claim' => $this->buildStatusPayload($claim),
        ];
    }

    // ─── Payload builder (the DPA boundary) ───────────────────────────────

    /**
     * Build the ONLY fields a claimant may see. Deliberately hand-picked — no
     * financials (reserve/paid), no internal notes, no PII (name/omang/bank),
     * no other claims. Everything here is either the reference the claimant
     * already holds, a status label, or a stage date.
     */
    private function buildStatusPayload($claim): array
    {
        $timeline = $this->buildTimeline($claim);
        $current  = $this->currentStage($timeline, (string) $claim->status);

        return [
            'reference'     => $claim->claim_number,
            'type'          => $this->friendlyType($claim->claim_type),
            'registered_on' => $this->dateOnly($claim->reported_date ?? $claim->registered_claim ?? $claim->created_at),
            'status'        => $this->friendlyStatus((string) $claim->status),
            'sub_status'    => $claim->claim_sub_status ?: null,
            'current_stage' => $current['label'] ?? null,
            'next_step'     => $this->nextStep($timeline, (string) $claim->status),
            'last_updated'  => $this->dateOnly($claim->updated_at),
            'timeline'      => $timeline,
        ];
    }

    /**
     * High-level milestones from claim_tracker_workflow (Phase 1 SLA timeline).
     * Only stage NAMES + dates are exposed — never comments or internal fields.
     * Degrades gracefully to a status-only view if there is no workflow row.
     */
    private function buildTimeline($claim): array
    {
        $wf = null;
        try {
            $wf = DB::table('claim_tracker_workflow')->where('claim_id', $claim->id)->first();
        } catch (\Throwable $e) {
            // table missing (feature just-migrated) — status-only view
        }

        // Ordered, claimant-friendly milestones. Each maps to a workflow date
        // column; a present date means "done".
        $milestones = [
            ['key' => 'registered',   'label' => 'Claim received',           'date' => $this->dateOnly($claim->reported_date ?? $claim->registered_claim ?? $claim->created_at)],
            ['key' => 'assessor',     'label' => 'Assessor assigned',        'date' => $wf ? $this->dateOnly($wf->assessor_allotment_date ?? null) : null],
            ['key' => 'assessment',   'label' => 'Vehicle/loss assessed',    'date' => $wf ? $this->dateOnly($wf->physical_assessment ?? null) : null],
            ['key' => 'quote',        'label' => 'Repair quote received',    'date' => $wf ? $this->dateOnly($wf->quote_finalisation ?? $wf->quote_request_date ?? null) : null],
            ['key' => 'report',       'label' => 'Assessment report done',   'date' => $wf ? $this->dateOnly($wf->assessment_report_date ?? null) : null],
            ['key' => 'po',           'label' => 'Repair authorised',        'date' => $wf ? $this->dateOnly($wf->po_issue_date ?? $wf->po_generation_date ?? null) : null],
            ['key' => 'repair',       'label' => 'Repairs / replacement',    'date' => $wf ? $this->dateOnly($wf->parts_delivery_date ?? $wf->replacement_date ?? null) : null],
            ['key' => 'completed',    'label' => 'Completed',                'date' => $wf ? $this->dateOnly($wf->job_end_date ?? null) : null],
        ];

        // A closed/approved claim is fully done even if some stage dates were
        // never captured in the workflow table.
        $terminal = in_array($claim->status, ['Closed', 'Approved'], true);

        return array_map(function ($m) use ($terminal) {
            return [
                'key'   => $m['key'],
                'label' => $m['label'],
                'date'  => $m['date'],
                'done'  => $m['date'] !== null || ($terminal && $m['key'] === 'completed'),
            ];
        }, $milestones);
    }

    /** The last milestone with a date is the "current" stage. */
    private function currentStage(array $timeline, string $status): array
    {
        $current = ['label' => 'Claim received'];
        foreach ($timeline as $m) {
            if (!empty($m['done']) && $m['date'] !== null) {
                $current = $m;
            }
        }
        if (in_array($status, ['Closed', 'Approved'], true)) {
            $current = ['label' => 'Completed'];
        }
        if ($status === 'Rejected') {
            $current = ['label' => 'Assessment complete'];
        }
        return $current;
    }

    /** Friendly, non-committal next-step guidance for the claimant. */
    private function nextStep(array $timeline, string $status): string
    {
        switch ($status) {
            case 'Approved':
                return 'Your claim has been approved. Our team will be in touch about the next steps.';
            case 'Rejected':
                return 'A decision has been made on your claim. Our team will contact you with the details.';
            case 'Closed':
                return 'This claim has been completed and closed. Thank you.';
            case 'Reopen':
                return 'Your claim has been reopened and is being reviewed again.';
            default:
                // Find the first not-yet-done milestone.
                foreach ($timeline as $m) {
                    if (empty($m['done'])) {
                        return 'Next: ' . $m['label'] . '. We will keep you updated.';
                    }
                }
                return 'Your claim is being processed. We will keep you updated.';
        }
    }

    // ─── Delivery (reuses existing SMS + email code paths) ────────────────

    /**
     * Deliver the code. First-success across SMS (Infobip) then email
     * (Mailgun). Returns [deliveredChannel|null, providerRef|null].
     */
    private function deliver(ClaimAccessLink $link, array $contact, string $code): array
    {
        $reference = $contact['reference'] ?? '';
        $body = "Alpha Direct Claims: your verification code is {$code}. "
            . ($reference ? "Use it to view claim {$reference}. " : '')
            . 'It expires in 5 minutes. Do not share this code.';

        // SMS via the existing SendSms event -> Infobip listener (SSM key).
        // extradata carries claim context so the (passive, flag-gated) claims
        // notification recorder can log it with a clean trigger_key + claim id.
        if (!empty($contact['cellphone'])) {
            try {
                event(new SendSms('+' . $this->e164($contact['cellphone']), $body, [
                    'source'       => 'claim_tracking',
                    'hook'         => 'claim_tracking_otp',
                    'claim_id'     => $link->claim_id,
                    'claim_number' => $contact['reference'] ?? null,
                ]));
                return ['sms', null];
            } catch (\Throwable $e) {
                Log::warning('[ClaimTracking] SMS send failed', ['claim_id' => $link->claim_id, 'msg' => $e->getMessage()]);
            }
        }

        // Email via Laravel Mail -> Mailgun (same transport the app uses).
        if (!empty($contact['email'])) {
            try {
                $email = $contact['email'];
                Mail::raw($body, function ($m) use ($email) {
                    $m->to($email)->subject('Your Alpha Direct claim verification code');
                });
                return ['email', null];
            } catch (\Throwable $e) {
                Log::warning('[ClaimTracking] email send failed', ['claim_id' => $link->claim_id, 'msg' => $e->getMessage()]);
            }
        }

        return [null, null];
    }

    /** Resolve the claimant's registered contact from the claim's customer. */
    private function resolveContact(ClaimAccessLink $link): array
    {
        $out = ['cellphone' => null, 'email' => null, 'reference' => null];

        $claim = $this->findClaim((int) $link->claim_id);
        if ($claim) {
            $out['reference'] = $claim->claim_number;
            $customerId = $link->customer_id ?: ($claim->customer_id ?? null);
            if ($customerId) {
                // Table is `customer` (singular) — `customers` does not exist on
                // the prod schema, so the plural name 500'd every OTP/link send.
                $cust = DB::table('customer')->where('id', $customerId)->first(['cellphone', 'email']);
                if ($cust) {
                    $out['cellphone'] = $cust->cellphone ?: null;
                    $out['email']     = (isset($cust->email) && filter_var($cust->email, FILTER_VALIDATE_EMAIL)) ? $cust->email : null;
                }
            }
        }

        return $out;
    }

    // ─── Resolution helpers ───────────────────────────────────────────────

    /**
     * Resolve $identifier to a usable link. Tries: (1) link token, then
     * (2) claim reference (claim_number, then external_ref). When $createForRef
     * is true a missing link is created for a matched reference (request path);
     * on the verify path we only reuse an existing usable link.
     */
    private function resolveUsableLink(string $identifier, bool $createForRef): ?ClaimAccessLink
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        // (1) Treat as an opaque link token.
        //
        // Scoped to purpose 'status' deliberately. Links now carry a purpose,
        // and a claim-form link (purpose 'fill_form') is minted WITHOUT an OTP
        // requirement — so without this filter a form token could be fed into
        // the status flow and be treated as a status credential. Existing rows
        // all default to 'status', so the legacy flow is unaffected.
        $link = ClaimAccessLink::where('token', $identifier)
            ->where('purpose', 'status')
            ->first();
        if ($link) {
            return $link->isUsable() ? $link : null;
        }

        // (2) Treat as a claim reference.
        $claim = DB::table('claims')
            ->where('claim_number', $identifier)
            ->orWhere('external_ref', $identifier)
            ->orderByDesc('id')
            ->first(['id']);
        if (!$claim) {
            return null;
        }

        if ($createForRef) {
            return $this->getOrCreateUsableLinkForClaim((int) $claim->id);
        }

        // purpose 'status' only — see the note on the token branch above.
        $existing = ClaimAccessLink::where('claim_id', $claim->id)
            ->where('purpose', 'status')
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        return ($existing && $existing->isUsable()) ? $existing : null;
    }

    /** Load a claim row with only the fields the status view needs. */
    private function findClaim(int $claimId)
    {
        try {
            return DB::table('claims')->where('id', $claimId)->first([
                'id', 'claim_number', 'external_ref', 'claim_type', 'status',
                'claim_sub_status', 'customer_id', 'reported_date',
                'registered_claim', 'created_at', 'updated_at',
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ─── Formatting / crypto ──────────────────────────────────────────────

    private function friendlyStatus(string $status): string
    {
        $map = [
            'Pending'  => 'Received',
            'Open'     => 'In progress',
            'Approved' => 'Approved',
            'Rejected' => 'Decision made',
            'Closed'   => 'Completed',
            'Reopen'   => 'Reopened',
        ];
        return $map[$status] ?? 'In progress';
    }

    private function friendlyType(?string $type): ?string
    {
        if (!$type) {
            return null;
        }
        return ucwords(strtolower(str_replace('_', ' ', $type)));
    }

    private function dateOnly($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Digits-only E.164-ish formatting matching PublicOtpService. */
    private function e164(string $cellphone): string
    {
        $digits = preg_replace('/\D/', '', $cellphone);
        if (strlen($digits) === 8) {
            return '267' . $digits; // bare BW local number
        }
        return $digits;
    }

    /** sha256(value + app.key) — same hashing convention as PublicOtpService. */
    private function hash(string $value): string
    {
        return hash('sha256', $value . config('app.key'));
    }
}
