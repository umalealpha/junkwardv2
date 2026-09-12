<?php

namespace Modules\Incentive\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class IncentiveAgent extends Model
{
    use HasFactory;
	use SoftDeletes;


    protected $table ="incentive_agent";

    protected static function newFactory()
    {
        return \Modules\Incentive\Database\factories\IncentiveFactory::new();
    }

	public function agent(){
		return $this->belongsTo(\Modules\Incentive\Entities\Agents::class,'agent_id');
	}

	public function incentiveType(){
		return $this->belongsTo(\Modules\Incentive\Entities\Incentive::class,'incentive_type');
	}

	public function policyDetails(){
		return $this->belongsTo(\Modules\Incentive\Entities\Policies::class,'policy','policyNumber');
	}

	public function product(){
		return $this->belongsTo(\AlphaDirect\Product::class,'product_id');
	}

	public function plan(){
		return $this->belongsTo(\AlphaDirect\Productplan::class,'plan_id');
	}

    public function scopeActivated($query)
    {
        return $query->where('status', 1);
    }
}
