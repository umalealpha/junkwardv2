<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantBranches extends Model
{
    use HasFactory;
    protected $table = 'merchant_branches';
    protected $fillable = [
        'merchantid',
        'branchName',
        'country'
       
    ];
}
