<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyRenewal extends Model
{
    use HasFactory;

    protected $table = 'policy_renewals';
    protected $guarded = ['id'];


    public function policy()
    {
        return $this->hasOne('AlphaDirect\Policy', 'policyNumber', 'policyNumber');
    }

    public function scopePolicyNumber($query,$policy_number){
        $query->where('policyNumber',$policy_number);
    }

}
