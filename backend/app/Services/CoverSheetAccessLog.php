<?php

namespace AlphaDirect\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The only writer of policy_document_access_logs.
 *
 * Every step of cover-sheet document access goes through here, so the trail
 * is complete by construction rather than by remembering to log: the QR being
 * resolved, an OTP asked for / sent / failed / verified, a portal login
 * accepted, a session minted or refused, and the document served.
 *
 * Two destinations on every call, deliberately:
 *   - a row in policy_document_access_logs — the durable, queryable audit a
 *     reviewer or the CFO reads, and the DPA record of who opened what;
 *   - a Laravel log line under `cover_sheet.<event>` — the operational view,
 *     which mirrors the `ncd_otp.*` convention already used for OTP signing.
 *
 * Never throws. A logging failure must not take down a customer's download,
 * so a broken or un-migrated table degrades to the Laravel log alone. That is
 * the one case where the DB trail can have a gap, and it is recorded loudly.
 *
 * Never stores secrets: no OTP codes, no raw QR tokens, no session tokens.
 * Cellphones are masked at the boundary; `session_ref` is a random handle,
 * not a derivation of any token.
 */
class CoverSheetAccessLog
{
    public const TABLE = 'policy_document_access_logs';

    /**
     * V2-owned table. The default connection is V1's read replica and
     * rejects writes with 1290, so every access here is explicit.
     */
    public const CONNECTION = 'mysql_system';

    // ─── Token lifecycle ─────────────────────────────────────────────────
    public const EVENT_TOKEN_ISSUED  = 'TOKEN_ISSUED';
    public const EVENT_TOKEN_REVOKED = 'TOKEN_REVOKED';

    // ─── The client's visit ──────────────────────────────────────────────
    /** QR resolved to a live policy; the verification page was shown. */
    public const EVENT_SCAN            = 'SCAN';
    /** QR could not be honoured — unknown, malformed or revoked token. */
    public const EVENT_SCAN_REJECTED   = 'SCAN_REJECTED';

    public const EVENT_OTP_REQUESTED   = 'OTP_REQUESTED';
    public const EVENT_OTP_SENT        = 'OTP_SENT';
    public const EVENT_OTP_SEND_FAILED = 'OTP_SEND_FAILED';
    public const EVENT_OTP_VERIFIED    = 'OTP_VERIFIED';
    public const EVENT_OTP_FAILED      = 'OTP_FAILED';

    /** Verified by signing in to the customer portal instead of an OTP. */
    public const EVENT_LOGIN_VERIFIED  = 'LOGIN_VERIFIED';
    public const EVENT_LOGIN_REJECTED  = 'LOGIN_REJECTED';

    public const EVENT_SESSION_ISSUED   = 'SESSION_ISSUED';
    public const EVENT_SESSION_REJECTED = 'SESSION_REJECTED';

    public const EVENT_DOWNLOAD_STARTED = 'DOWNLOAD_STARTED';
    public const EVENT_DOWNLOAD_SERVED  = 'DOWNLOAD_SERVED';
    public const EVENT_DOWNLOAD_FAILED  = 'DOWNLOAD_FAILED';

    /** Cover sheet PDF generated / regenerated for a policy. */
    public const EVENT_SHEET_GENERATED  = 'SHEET_GENERATED';
    public const EVENT_SHEET_FAILED     = 'SHEET_FAILED';

    public const OUTCOME_OK     = 'ok';
    public const OUTCOME_DENIED = 'denied';
    public const OUTCOME_ERROR  = 'error';

    /** A successful step. */
    public static function ok(string $event, array $ctx = []): void
    {
        self::write($event, self::OUTCOME_OK, null, $ctx);
    }

    /**
     * A step refused on purpose — wrong code, revoked token, someone else's
     * policy. Expected in normal operation; not an error.
     */
    public static function denied(string $event, string $reason, array $ctx = []): void
    {
        self::write($event, self::OUTCOME_DENIED, $reason, $ctx);
    }

    /** Something broke: storage missing, SMS gateway down, exception. */
    public static function error(string $event, string $reason, array $ctx = []): void
    {
        self::write($event, self::OUTCOME_ERROR, $reason, $ctx);
    }

    /**
     * A fresh handle tying every event of one client visit together. Random,
     * never derived from a token, so it is safe to log and to show on screen.
     */
    public static function newSessionRef(): string
    {
        return (string) Str::lower(Str::random(24));
    }

    /**
     * Mask a cellphone for storage: keep the country prefix and the last
     * four digits, star the middle. Short or empty input yields null rather
     * than a value that could be mistaken for a real number.
     */
    public static function maskCellphone(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw);
        if ($digits === null || strlen($digits) < 7) {
            return null;
        }

        return substr($digits, 0, 4) . str_repeat('*', max(3, strlen($digits) - 8)) . substr($digits, -4);
    }

    // ─── Internals ───────────────────────────────────────────────────────

    /**
     * Write one row and one log line. Accepts context loosely so call sites
     * stay readable; unknown keys are folded into `meta` rather than dropped,
     * so a caller can never silently lose detail.
     */
    private static function write(string $event, string $outcome, ?string $reason, array $ctx): void
    {
        $columns = [
            'policy_id', 'token_id', 'action_id', 'customer_id', 'user_id',
            'cellphone_masked', 'session_ref', 'ip', 'user_agent',
        ];

        $row = [
            'event'      => $event,
            'outcome'    => $outcome,
            'reason'     => $reason,
            'created_at' => now(),
        ];
        foreach ($columns as $col) {
            $row[$col] = $ctx[$col] ?? null;
        }

        // Anything the caller passed that is not a column belongs in meta,
        // plus an explicit meta array if one was given.
        $meta = (array) ($ctx['meta'] ?? []);
        foreach ($ctx as $key => $value) {
            if ($key !== 'meta' && !in_array($key, $columns, true)) {
                $meta[$key] = $value;
            }
        }
        $row['user_agent'] = $row['user_agent'] === null ? null : mb_substr((string) $row['user_agent'], 0, 255);
        $row['meta'] = $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_SLASHES);

        // The Laravel log line goes out first: if the insert throws, the event
        // is still recorded somewhere.
        $logCtx = array_filter([
            'policy_id'  => $row['policy_id'],
            'token_id'   => $row['token_id'],
            'outcome'    => $outcome,
            'reason'     => $reason,
            'session'    => $row['session_ref'],
            'cellphone'  => $row['cellphone_masked'],
            'ip'         => $row['ip'],
            'meta'       => $meta === [] ? null : $meta,
        ], fn ($v) => $v !== null);

        $channel = 'cover_sheet.' . strtolower($event);
        if ($outcome === self::OUTCOME_OK) {
            Log::info($channel, $logCtx);
        } else {
            Log::warning($channel, $logCtx);
        }

        try {
            DB::connection(self::CONNECTION)->table(self::TABLE)->insert($row);
        } catch (\Throwable $e) {
            // Loud, because the durable trail now has a hole in it.
            Log::error('cover_sheet.audit_write_failed', [
                'event' => $event,
                'error' => $e->getMessage(),
            ] + $logCtx);
        }
    }
}
