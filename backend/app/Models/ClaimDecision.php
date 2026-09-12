<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * claim_decisions — the formal approve / repudiate / reverse decision layer
 * ported from the standalone Claims Tracker (see migration
 * create_claim_decisions_table for the full model notes).
 *
 * Append-only: one row per decision event. The CURRENT decision for a claim is
 * the latest row with reversed_at = NULL. This layer is SEPARATE from Graphite's
 * claim status/sub-status workflow — it never drives claims.status.
 *
 * Lives on the default connection alongside the `claims` table.
 */
class ClaimDecision extends Model
{
    protected $table = 'claim_decisions';

    protected $guarded = ['id'];

    protected $casts = [
        'decision_date' => 'datetime',
        'reversed_at'   => 'datetime',
    ];

    /** The claim this decision belongs to. */
    public function claim()
    {
        return $this->belongsTo(\AlphaDirect\Claim::class, 'claim_id');
    }

    /** True while this decision is the active (un-reversed) one. */
    public function isActive(): bool
    {
        return $this->reversed_at === null;
    }
}
