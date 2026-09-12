<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RealpayTransactionsExcelData extends Model
{
    use HasFactory;
    protected $table = 'realpay_transactions_excel_data';

    protected $fillable = [
        'product',
        'beneficiarynumber',
        'tracking_startdate',
        'transaction_date',
        'contractsequence',
        'clientnumber',
        'domgcomg',
        'amountcollected',
        'status'
    ];
}
