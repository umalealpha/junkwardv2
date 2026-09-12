<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayMContract extends Model
{
    use HasFactory;
    protected $table = 'pay_m_contracts';
    protected $fillable = [
        'policyNumber',
        'contractid',
        'status',
        'primaryChannelTypeToString',
        'firstPaymentAmountInCents',
        'firstPaymentDate',
        'lastPaymentDate',
        'merchantContractNumber',
        'action_by',
    ];
}
