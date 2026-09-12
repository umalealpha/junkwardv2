<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;

class PaymentVendor extends Model
{
    protected $table = 'paymentvendor';

    protected $fillable = [
        'vendorName', 'vendorLabel', 'status', 'email', 'telephone',
    ];

}
