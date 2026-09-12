<?php

namespace Modules\Cashback\Entities;

use Endroid\QrCode\Builder\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerCashback extends Model
{
    use HasFactory;
	use SoftDeletes;

    protected $table ="customer_cashback";

    protected static function newFactory()
    {
        return \Modules\Cashback\Database\factories\CashbackFactory::new();
    }

	public function agent(){
		return $this->belongsTo(\Modules\Cashback\Entities\Agents::class,'agent_id');
	}

	public function cashbackType(){
		return $this->belongsTo(\Modules\Cashback\Entities\Cashback::class,'cashback_type');
	}

	public function policyDetails(){
		return $this->belongsTo(\Modules\Cashback\Entities\Policies::class,'policy','id');
	}

    public function product(){
		return $this->belongsTo(\AlphaDirect\Product::class,'product_id');
	}

	public function plan(){
		return $this->belongsTo(\AlphaDirect\Productplan::class,'plan_id');
    }

    public function customer(){
        return $this->belongsTo(\AlphaDirect\Customer::class,'customer_id');
    }
}
