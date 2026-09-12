<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use AlphaDirect\Models\Benefit;
use AlphaDirect\Models\RewardTier;

class CustomerBenefit extends Model
{
    protected $table = 'customer_rewards';
    protected $fillable = [
        'customer_id', 'benefit_id', 'status', 'expiry_date', 'claim_date'
    ];

    public function benefit()
    {
        return $this->belongsTo(Benefit::class);
    }

    public function tier()
    {
        return $this->belongsTo(RewardTier::class, 'reward_tier_id');
    }

    public function customer()
    {
        return $this->belongsTo(\AlphaDirect\Customer::class);
    }
} 