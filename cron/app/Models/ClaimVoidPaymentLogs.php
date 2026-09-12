<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ClaimVoidPaymentLogs extends Model
{
    use HasFactory;
    // use \OwenIt\Auditing\Auditable;
    protected $auditTimestamps = true;

    protected $table ='claim_void_payment_logs';

    protected $fillable = [];
}
