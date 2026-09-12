<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyUpgradeMotorcomp extends Model
{
    use HasFactory;
    protected $table = 'policy_upgrade_motorcomp';
    protected $guarded = ['id'];
    protected $fillable = [];
}
