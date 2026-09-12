<?php

namespace Modules\Cashback\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cashback extends Model
{
    use HasFactory;
	use SoftDeletes;
    protected $fillable = ['cashback_type','product_id','plan_id','payment_type','cashback_value','deleted_at','created_at','updated_at'
	];

    protected $table ="cashback";

    protected static function newFactory()
    {
        return \Modules\Cashback\Database\factories\CashbackFactory::new();
    }

	public function product(){
		return $this->belongsTo(\AlphaDirect\Product::class,'product_id');
	}

	public function plan(){
		return $this->belongsTo(\AlphaDirect\Productplan::class,'plan_id');
	}
}
