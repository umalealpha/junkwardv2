<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only trail for a placement. Never updated, never deleted.
 *
 * `notified_to` is written even when the mail fails, so it can always be proved
 * who was supposed to be told and whether the message actually left.
 */
class FacPlacementEvent extends Model
{
    protected $table = 'fac_placement_events';
    protected $guarded = ['id'];

    protected $casts = [
        'payload'           => 'array',
        'notification_sent' => 'boolean',
    ];

    public function placement()
    {
        return $this->belongsTo(FacPlacement::class, 'fac_placement_id');
    }
}
