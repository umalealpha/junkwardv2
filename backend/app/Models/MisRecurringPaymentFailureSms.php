<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per failed recurring collection on an MIS policy for which the
 * "payment failed" SMS was attempted.
 *
 * (gateway, event_reference) is UNIQUE — that index, not a SELECT, is what
 * stops duplicate SMS on webhook replay / cron re-runs.
 *
 * @see \AlphaDirect\Services\MisRecurringPaymentFailureNotifier
 */
class MisRecurringPaymentFailureSms extends Model
{
    protected $table = 'mis_recurring_payment_failure_sms';

    protected $fillable = [
        'policyNumber',
        'policy_id',
        'customer_id',
        'gateway',
        'event_reference',
        'to_cellphone',
        'message',
        'status',
        'note',
        'sms_email_log_id',
    ];
}
