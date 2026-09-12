<?php

namespace AlphaDirect\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * claim_access_links — the opaque, time-limited, revocable per-claim link token
 * a claimant is given to reach the public status page (Claims Tracker Phase 2).
 *
 * The token is the durable credential. It maps to exactly one claim; a correct
 * OTP against this link mints a session that can ONLY read that claim. Additive
 * and gated by the `claimant_tracking` runtime flag — see the create migration.
 */
class ClaimAccessLink extends Model
{
    protected $table = 'claim_access_links';

    protected $guarded = ['id'];

    protected $casts = [
        'expires_at'       => 'datetime',
        'revoked_at'       => 'datetime',
        'otp_last_sent_at' => 'datetime',
        'last_viewed_at'   => 'datetime',
    ];

    /** Default link lifetime — mirrors the tracker's ~90-day link expiry. */
    public const DEFAULT_TTL_DAYS = 90;

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->token)) {
                // 48 random bytes -> 64-char URL-safe token. Brute-force proof,
                // still fits in an SMS alongside the URL prefix.
                $model->token = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
            }
            if (empty($model->status)) {
                $model->status = 'active';
            }
            if (empty($model->expires_at)) {
                $model->expires_at = Carbon::now()->addDays(self::DEFAULT_TTL_DAYS);
            }
        });
    }

    public function otps(): HasMany
    {
        return $this->hasMany(ClaimTrackingOtp::class, 'access_link_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ClaimTrackingSession::class, 'access_link_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && Carbon::parse($this->expires_at)->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->status === 'revoked' || $this->revoked_at !== null;
    }

    /**
     * A link is usable only if active, not revoked, and not past expiry.
     * Everything else fails closed.
     */
    public function isUsable(): bool
    {
        return $this->status === 'active' && !$this->isRevoked() && !$this->isExpired();
    }
}
