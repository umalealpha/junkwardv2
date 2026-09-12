<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RefundRequestEvent — the strict who/when/what audit trail of the Customer
 * Refund Engine. One row per action/transition; append-only (no updated_at,
 * never edited or deleted). Belt-and-braces with the Spatie activity log —
 * this table is the authoritative, queryable SOP trail.
 */
class RefundRequestEvent extends Model
{
    protected $table = 'refund_request_events';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'meta'       => 'array',
        'created_at' => 'datetime',
    ];

    public function refundRequest()
    {
        return $this->belongsTo(RefundRequest::class, 'refund_request_id');
    }
}
