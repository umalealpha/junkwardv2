<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExcelNgeniusAddTrxn extends Model
{
    use HasFactory;
    protected $table = 'excel_ngenius_add_trxn';
    protected $fillable = ['policy_id','policyNumber','reference','action','added_by','status'
    ];
}
