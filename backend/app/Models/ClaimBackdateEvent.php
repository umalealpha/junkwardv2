<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * claim_backdate_events — one row per successful backdate. Ported from the
 * Claims Tracker. `created_at` only (events are immutable).
 */
class ClaimBackdateEvent extends Model
{
    protected $table = 'claim_backdate_events';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /** Decoded field diffs. */
    public function changes(): array
    {
        $decoded = json_decode($this->changes_json ?? '[]', true);

        return is_array($decoded) ? $decoded : [];
    }
}
