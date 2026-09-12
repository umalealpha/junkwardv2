<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayMSchuduleTransection extends Model
{
    use HasFactory;
    protected $table = 'pay_m_schudule_transections';
    protected $fillable = [
        'policyNumber',
        'installment',
        'installmentid',
        'paymentArrangementId',
        'premium',
        'billing_date',
        'status',
        'action_by',
    ];
    
}
