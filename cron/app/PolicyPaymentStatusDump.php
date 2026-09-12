<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Activitylog\Traits\LogsActivity;

class PolicyPaymentStatusDump extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    use LogsActivity;

    protected static $logAttributes = ['policyNumber', 'policy_status'];
    protected $table = 'policy_payment_status_dump';
    protected $fillable = ['policyNumber','premium','premium_freq','total_prem_due_customer','total_payment_paid_by_cust','balance','failed_tx_count','policy_status'];
}
