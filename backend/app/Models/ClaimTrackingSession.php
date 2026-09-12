<?php

namespace AlphaDirect\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * claim_tracking_sessions — a short-lived, claim-SCOPED session token minted
 * after a correct OTP. Stored HASHED (sha256 + app.key). Its whole purpose is
 * to bind a verified claimant to EXACTLY ONE claim: every status read checks
 * the requested claim against this row's claim_id and fails closed on mismatch.
 */
class ClaimTrackingSession extends Model
{
    protected $table = 'claim_tracking_sessions';

    protected $guarded = ['id'];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function link(): BelongsTo
    {
        return $this->belongsTo(ClaimAccessLink::class, 'access_link_id');
    }

    public function isValid(): bool
    {
        return $this->revoked_at === null && !Carbon::parse($this->expires_at)->isPast();
    }
}
