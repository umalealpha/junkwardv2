<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kycclone extends Model
{
    use HasFactory;
    protected $table = 'customer_kyc_clone';

    protected $fillable = [
        'customer_kyc_id',
        'customer_id',
        'omang',
        'omangBack',
        'omangNumber'
    ];
}
