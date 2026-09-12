<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EarnPremium extends Model
{
    use HasFactory;
    protected $table = 'earned_premiums';

    public function policyData()
    {
        return $this->hasMany('AlphaDirect\Policy', 'id');
    }
}
