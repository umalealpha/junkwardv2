<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DpoTransactionReport extends Model
{
    use HasFactory;
    protected $table = 'dpo_transaction_reports';
    protected $fillable = [
        'policy_number',
        'customer_name',
        'cellphone',
        'status',
        'premium',
        'email',
        'retry_count',
        'reason',
    ];
}
