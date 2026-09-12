<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class RealpayPaymentRequest extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;
    //realpay_payment_request
    protected $table ='realpay_payment_request';
    protected $id ='id';
    protected $fillable = [];
}
