<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use AlphaDirect\Customer;

class CustomerTierCalculation extends Model
{
    protected $table = 'customer_tier_calculations';

    protected $fillable = [
        'customer_id',
        'tier_id',
        'points',
        'calculated_at',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function tier()
    {
        return $this->belongsTo(RewardTier::class, 'tier_id');
    }
} 