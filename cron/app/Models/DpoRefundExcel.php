<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DpoRefundExcel extends Model
{
    use HasFactory;
    protected $table = 'dpo_refund_excel';

    protected $fillable = [
                            'policy_id',
                            'dpo_ref',
                            'action',
                            'added_by',
                            'status',
                            'created_at',
                            'updated_at'
                          ];
    
}
