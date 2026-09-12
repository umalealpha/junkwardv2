<?php

namespace Modules\Incentive\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Agents extends Model
{
    use HasFactory;


    protected $table ="users";

	public function getNameAttribute($value)
    {
        return $this->firstName. ' '.$this->lastName;
    }

    protected static function newFactory()
    {
        return \Modules\Incentive\Database\factories\IncentiveFactory::new();
    }

    // public function incentiveAgent(){
	// 	return $this->belongsTo(incentiveAgent::class,'agent_id');
	// }

    public function incentiveAgent()
    {
        return $this->hasMany(\Modules\Incentive\Entities\IncentiveAgent::class,'agent_id');
    }
}
