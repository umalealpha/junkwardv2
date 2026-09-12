<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A document hung off a FAC placement — proof of client payment, our payment
 * advice to the reinsurer, or the slip itself. Stored on S3 (private).
 */
class FacPlacementAttachment extends Model
{
    use SoftDeletes;

    protected $table = 'fac_placement_attachments';
    protected $guarded = ['id'];

    protected $casts = [
        'payment_date' => 'date',
        'amount'       => 'decimal:2',
    ];

    public function placement()
    {
        return $this->belongsTo(FacPlacement::class, 'fac_placement_id');
    }
}
