<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use OwenIt\Auditing\Contracts\Auditable;

class VerifyingCancelPoliciesPayment extends Model
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'verifying_cancel_policies_payment';
    protected $fillable = [];
    protected $guarded = ['id'];
}
