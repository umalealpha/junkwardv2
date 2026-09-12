<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class MotorCompCustomerKYCInspectionPayment extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'motor_comp_kyc_preinspection_payment_status';
    protected $fillable = [];
    protected $guarded = ['id'];
}
