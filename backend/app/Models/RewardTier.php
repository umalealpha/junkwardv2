<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use AlphaDirect\Models\Benefit;

class RewardTier extends Model
{
    protected $table = 'tiers';
    protected $fillable = ['name', 'label', 'description', 'condition1', 'condition2', 'condition3', 'condition4', 'level_point', 'image', 'status'];

    public function benefits()
    {
        return $this->belongsToMany(Benefit::class, 'reward_tier_benefit');
    }
} 