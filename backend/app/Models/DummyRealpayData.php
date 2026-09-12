<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DummyRealpayData extends Model
{
    use HasFactory;
    protected $table = 'dummy_realpay_data';

    protected $fillable = [
        'PolicyNumber',
        'realpay_client_number',
        'realpay_contract_number',
        'policy_inception_date',
        'status',
    ];
}
