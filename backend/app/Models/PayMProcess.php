<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayMProcess extends Model
{
    use HasFactory;
    protected $table = 'pay_m_processes';
    protected $fillable = [
        'policyNumber',
        'request',
        'url',
        'response',
        'status',
    ];
}
