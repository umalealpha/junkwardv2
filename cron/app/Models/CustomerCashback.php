<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerCashback extends Model
{
    use HasFactory;

    public $table='customer_cashback';

    public function customer()
    {
        return $this->belongsTo('AlphaDirect\Customer');
    }

    public function scopeActivated($query)
    {
        return $query->where('status', 1);
    }
}
