<?php

namespace AlphaDirect\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * claim_tracking_otps — single-use, short-TTL, HASHED one-time codes scoped to
 * a claim_access_link. The plaintext code is never persisted (only its
 * sha256(code + app.key) hash), mirroring PublicOtpService. Verification is a
 * constant-time hash compare with an attempt-count lockout.
 */
class ClaimTrackingOtp extends Model
{
    protected $table = 'claim_tracking_otps';

    protected $guarded = ['id'];

    protected $casts = [
        'expires_at'  => 'datetime',
        'consumed_at' => 'datetime',
        'attempts'    => 'integer',
    ];

    public function link(): BelongsTo
    {
        return $this->belongsTo(ClaimAccessLink::class, 'access_link_id');
    }

    public function isExpired(): bool
    {
        return Carbon::parse($this->expires_at)->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}
