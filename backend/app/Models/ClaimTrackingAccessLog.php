<?php

namespace AlphaDirect\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * claim_tracking_access_logs — append-only audit trail for the claimant
 * self-service flow (link issue / OTP send / verify / view / revoke / denied).
 * The tracker kept an access log; the migration plan cold-archives these for
 * 7 years. Writes are best-effort: an audit hiccup must never break the
 * claimant flow, and we deliberately keep rows lean (the `audits` table is
 * already the DB-cost driver — this is a separate, small table).
 *
 * NEVER store the OTP code or claimant PII in `meta`.
 */
class ClaimTrackingAccessLog extends Model
{
    public $timestamps = false;

    protected $table = 'claim_tracking_access_logs';

    protected $guarded = ['id'];

    public const EVENTS = [
        'link_issued',
        'otp_requested',
        'otp_sent',
        'otp_verified',
        'otp_failed',
        'status_viewed',
        'link_revoked',
        'denied',
    ];

    /**
     * Record an event. Swallows storage errors (best-effort audit).
     *
     * @param  array  $meta  Non-PII context only (masked destination, reason).
     */
    public static function record(
        string $event,
        ?int $accessLinkId = null,
        ?int $claimId = null,
        ?string $channel = null,
        ?string $ip = null,
        ?string $userAgent = null,
        array $meta = []
    ): void {
        try {
            static::create([
                'access_link_id' => $accessLinkId,
                'claim_id'       => $claimId,
                'event'          => $event,
                'channel'        => $channel,
                'ip'             => $ip,
                'user_agent'     => $userAgent ? substr($userAgent, 0, 255) : null,
                'meta'           => empty($meta) ? null : json_encode($meta),
                'created_at'     => Carbon::now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('claim_tracking access log write failed', [
                'event' => $event,
                'msg'   => $e->getMessage(),
            ]);
        }
    }
}
