<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditNote extends Model
{
    use HasFactory;
    protected $table = 'credit_note';

    protected $fillable = [
        'customer_id',
        'policy_id',
        'invoice_no',
        'transaction_effective_date',
        'transaction_end_date',
        'earned_premium',
        'unearned_premium',
        'created_at',
        'updated_at'
    ];
}
