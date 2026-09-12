<?php

namespace Modules\Incentive\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Incentive extends Model
{
    use HasFactory;
	use SoftDeletes;
    protected $fillable = ['incentive_type','product_id','plan_id','payment_type','incentive_value','deleted_at','created_at','updated_at'
	];
	
    protected $table ="incentive";
	
    protected static function newFactory()
    {
        return \Modules\Incentive\Database\factories\IncentiveFactory::new();
    }
	
	public function product(){
		return $this->belongsTo(\AlphaDirect\Product::class,'product_id');
	}
	
	public function plan(){
		return $this->belongsTo(\AlphaDirect\Productplan::class,'plan_id');
	}
}