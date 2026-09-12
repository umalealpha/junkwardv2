<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayM8Banks extends Model
{
    use HasFactory;
    protected $table = 'pay_m8_banks';

    protected $fillable = [ 'bankid','bankName','country','alias','merchantid' ];
}
