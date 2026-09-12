<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerKycDelete extends Model
{
    use HasFactory;
    protected $table = 'customer_kyc_delete';

    protected $fillable = [];
}
