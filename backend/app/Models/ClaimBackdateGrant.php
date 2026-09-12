<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * claim_backdate_grants — a time-limited permission to backdate claim stage
 * dates (per-user or ALL request-role users). Ported from the Claims Tracker.
 * Lives on the default connection alongside the `claims` table.
 */
class ClaimBackdateGrant extends Model
{
    protected $table = 'claim_backdate_grants';

    protected $guarded = ['id'];

    protected $casts = [
        'granted_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /** Currently-active grants (not expired, not revoked). */
    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at !== null && $this->expires_at->isFuture();
    }
}
