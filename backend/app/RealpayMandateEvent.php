<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Append-only record of a RealPay payload that reached the mandate layer, and
 * what the mandate layer decided about it.
 *
 * No transition logic lives here on purpose — rows are written before any state
 * mutation and are only ever updated to stamp the outcome
 * (processing_status / error / deliveries).
 */
class RealpayMandateEvent extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;

    protected $auditTimestamps = true;

    protected $table = 'realpay_mandate_events';
    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
    ];

    /** Outcome vocabulary for processing_status. */
    public const PENDING   = 'pending';
    public const APPLIED   = 'applied';
    public const DUPLICATE = 'duplicate';
    public const IGNORED   = 'ignored';
    public const FAILED    = 'failed';

    public function mandate()
    {
        return $this->belongsTo(RealpayMandate::class, 'mandate_id');
    }
}
