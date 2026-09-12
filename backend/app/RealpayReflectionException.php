<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;

/**
 * A RealPay debit that Graphite failed to reflect as a payment.
 *
 * Written by AlphaDirect\Services\RealpayPaymentRecorder whenever a confirmed
 * collection cannot be turned into a payment_transactions row. Cleared (via
 * markResolved) when the payment is finally written — by a later webhook
 * delivery or by `realpay:reconcile-reflection --commit`.
 *
 * Deliberately NOT Auditable: this table is itself the audit trail, and rows
 * are written on a path that is already failing. A second write that could
 * throw is the last thing that path needs.
 */
class RealpayReflectionException extends Model
{
    protected $table = 'realpay_reflection_exceptions';
    protected $guarded = ['id'];

    protected $casts = [
        'occurrences' => 'integer',
        'policy_id'   => 'integer',
        'resolved_at' => 'datetime',
    ];

    /** Why a reflection failed. Free-form by design — see the migration. */
    public const REASON_POLICY_UNRESOLVED = 'policy_unresolved';
    public const REASON_WRITE_FAILED      = 'write_failed';
    public const REASON_EXCEPTION         = 'exception';

    public function scopeOpen($query)
    {
        return $query->whereNull('resolved_at');
    }
}
