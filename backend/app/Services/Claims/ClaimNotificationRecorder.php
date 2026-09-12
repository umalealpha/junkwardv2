<?php

namespace AlphaDirect\Services\Claims;

use AlphaDirect\Models\ClaimNotificationLog;
use AlphaDirect\Services\IntegrationSettings;
use Illuminate\Support\Facades\Log;

/**
 * ClaimNotificationRecorder — passively records claim notifications Graphite
 * already sends, into `claim_notification_log`, for the Claims Notifications
 * dashboard (Claims Tracker -> Graphite migration).
 *
 * DESIGN GUARANTEES (all deliberate):
 *   1. PASSIVE — this only ever INSERTs a log row. It never sends anything,
 *      never mutates the event, never returns a value the send path depends on.
 *   2. DARK by default — every entry point returns immediately unless the
 *      `claims_notifications` runtime flag is ON (Admin > Integrations, default
 *      OFF). So nothing is written until an admin arms it.
 *   3. CLAIM-SCOPED — it records ONLY sends whose extradata carries a claim
 *      marker (numeric claim_id, or a hook/source/trigger_key starting with
 *      'claim', or an explicit trigger_key). General policy/payment traffic
 *      through the same SendSms/SendMail events is ignored.
 *   4. FAIL-SAFE — the whole body is wrapped so a logging hiccup can NEVER
 *      throw into the send listener that called it.
 *   5. DPA — the stored recipient is always MASKED (never a raw phone/email).
 *
 * Wired from SendSmsFired / SendMailFired via recordSms() / recordEmail(), which
 * receive the dispatched event plus whatever the listener already resolved
 * (provider message id + status) — no extra provider calls are made.
 */
class ClaimNotificationRecorder
{
    public const FLAG = 'claims_notifications';

    /** Is the feature armed? Dark (false) by default. */
    public static function enabled(): bool
    {
        return IntegrationSettings::isEnabled(
            (string) config('claims_notifications.integration_key', self::FLAG),
            (bool) config('claims_notifications.enabled', false),
        );
    }

    /**
     * Record an SMS send. Called at the end of SendSmsFired.
     *
     * @param string      $to           destination (masked before storage)
     * @param array       $extradata    the SendSms event's extradata
     * @param string      $status       provider status ('sent'|'PENDING'|'CURL_ERROR'|'suppressed'|...)
     * @param string|null $providerMsgId Infobip messageId when available
     * @param string|null $reason       failure/suppression reason
     */
    public static function recordSms(string $to, array $extradata, string $status = 'sent', ?string $providerMsgId = null, ?string $reason = null): void
    {
        self::record('sms', 'infobip', $to, $extradata, $status, $providerMsgId, $reason, self::smsCost($status));
    }

    /**
     * Record an email send. Called at the end of SendMailFired.
     *
     * @param string      $to           destination (masked before storage)
     * @param array       $extradata    the SendMail event's extradata
     * @param string      $status       'sent'|'PENDING'|'failed'|...
     * @param string|null $providerMsgId Mailgun message id when available
     */
    public static function recordEmail(string $to, array $extradata, string $status = 'sent', ?string $providerMsgId = null, ?string $reason = null): void
    {
        self::record('email', 'mailgun', $to, $extradata, $status, $providerMsgId, $reason, 0.0);
    }

    /**
     * Core insert. Silently no-ops unless the flag is on AND the send carries a
     * claim marker. Never throws.
     */
    private static function record(
        string  $channel,
        string  $provider,
        string  $to,
        array   $extradata,
        string  $status,
        ?string $providerMsgId,
        ?string $reason,
        ?float  $costUnits
    ): void {
        try {
            if (!self::enabled()) {
                return; // dark until armed
            }
            if (!self::isClaimContext($extradata)) {
                return; // not a claim notification — ignore
            }

            ClaimNotificationLog::create([
                'claim_id'        => self::intOrNull($extradata['claim_id'] ?? null),
                'claim_number'    => self::strOrNull($extradata['claim_number'] ?? ($extradata['claim_ref'] ?? null)),
                'channel'         => $channel,
                'provider'        => $provider,
                'trigger_key'     => self::triggerKey($extradata),
                'recipient'       => self::mask($to, $channel),
                'template_key'    => self::strOrNull($extradata['template_key'] ?? ($extradata['template'] ?? null)),
                'provider_msg_id' => $providerMsgId !== null ? mb_substr($providerMsgId, 0, 191) : null,
                'status'          => self::normaliseStatus($status),
                'reason'          => $reason !== null ? mb_substr($reason, 0, 255) : null,
                'cost_units'      => $costUnits,
                'delivered_at'    => null, // set later if a DLR is reconciled
            ]);
        } catch (\Throwable $e) {
            // Passive by contract — a logging failure must never affect the send.
            Log::warning('[ClaimNotificationRecorder] record failed', [
                'channel' => $channel,
                'msg'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * A send is a claim notification when its extradata has a numeric claim_id,
     * an explicit trigger_key, or a hook/source starting with 'claim'.
     */
    private static function isClaimContext(array $extradata): bool
    {
        if (self::intOrNull($extradata['claim_id'] ?? null) !== null) {
            return true;
        }
        if (!empty($extradata['trigger_key'])) {
            return true;
        }
        foreach (['hook', 'source', 'type'] as $k) {
            $v = $extradata[$k] ?? null;
            if (is_string($v) && stripos($v, 'claim') === 0) {
                return true;
            }
        }
        return false;
    }

    /** Resolve the trigger_key from the configured extradata fields. */
    private static function triggerKey(array $extradata): string
    {
        $fields = (array) config('claims_notifications.trigger_key_fields', ['trigger_key', 'hook', 'source', 'type']);
        foreach ($fields as $f) {
            $v = $extradata[$f] ?? null;
            if (is_string($v) && trim($v) !== '') {
                return mb_substr(trim($v), 0, 80);
            }
        }
        return 'unknown';
    }

    /**
     * Mask a destination so no raw contact PII is stored (DPA).
     *   phone: +2677***123   (keep country/prefix hint + last 3)
     *   email: j***@d***.com  (keep first char of local + tld)
     */
    private static function mask(string $to, string $channel): ?string
    {
        $to = trim($to);
        if ($to === '') {
            return null;
        }

        if ($channel === 'email' || str_contains($to, '@')) {
            $parts = explode('@', $to, 2);
            $local = $parts[0] ?? '';
            $domain = $parts[1] ?? '';
            $localMasked = $local !== '' ? (mb_substr($local, 0, 1) . '***') : '***';
            // keep the TLD, mask the domain label
            $domainMasked = $domain;
            if ($domain !== '') {
                $dot = strrpos($domain, '.');
                $tld = $dot !== false ? mb_substr($domain, $dot) : '';
                $domainMasked = (mb_substr($domain, 0, 1) . '***') . $tld;
            }
            return mb_substr($localMasked . '@' . $domainMasked, 0, 120);
        }

        // Phone: keep a leading + and the first 4 chars, mask the middle, keep last 3.
        $plus = str_starts_with($to, '+') ? '+' : '';
        $digits = preg_replace('/\D/', '', $to);
        if (strlen($digits) <= 5) {
            return $plus . str_repeat('*', max(0, strlen($digits)));
        }
        $head = mb_substr($digits, 0, 4);
        $tail = mb_substr($digits, -3);
        return mb_substr($plus . $head . '***' . $tail, 0, 120);
    }

    /**
     * Normalise the assorted provider status strings into the dashboard's
     * vocabulary. Infobip groupName values (PENDING/DELIVERED/...) and our own
     * 'suppressed'/'skipped'/'failed' all pass through here.
     */
    private static function normaliseStatus(string $status): string
    {
        $s = strtolower(trim($status));
        return match ($s) {
            '', 'ok', 'sent'                                  => 'sent',
            'pending', 'queued', 'accepted'                   => 'pending',
            'delivered', 'delivered_to_handset', 'delivered_to_operator' => 'delivered',
            'suppressed', 'not_sent_dev', 'dev_suppressed'    => 'suppressed',
            'skipped', 'no_recipient'                         => 'skipped',
            'curl_error', 'failed', 'error', 'rejected',
            'undeliverable', 'expired'                        => 'failed',
            default                                            => mb_substr($s, 0, 24),
        };
    }

    /** Estimated SMS cost for the roll-up; suppressed/skipped sends cost nothing. */
    private static function smsCost(string $status): float
    {
        $s = self::normaliseStatus($status);
        if (in_array($s, ['suppressed', 'skipped', 'failed'], true)) {
            return 0.0;
        }
        return (float) config('claims_notifications.sms_cost_units', 0.20);
    }

    private static function intOrNull($v): ?int
    {
        if ($v === null || $v === '' || !is_numeric($v)) {
            return null;
        }
        return (int) $v;
    }

    private static function strOrNull($v): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = trim((string) $v);
        return $v === '' ? null : mb_substr($v, 0, 80);
    }
}
