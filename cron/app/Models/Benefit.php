<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use AlphaDirect\Models\RewardTier;

class Benefit extends Model
{
    protected $fillable = ['tag', 'type', 'image', 'price', 'point','status'];

    public function tiers()
    {
        return $this->belongsToMany(RewardTier::class, 'reward_tier_benefit');
    }
} 