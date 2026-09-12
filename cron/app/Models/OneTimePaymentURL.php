<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OneTimePaymentURL extends Model
{
    use HasFactory;

    protected $table = 'one_time_payment_link';
}
