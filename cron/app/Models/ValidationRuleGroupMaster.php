<?php

namespace AlphaDirect\Models;

use AlphaDirect\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ValidationRuleGroupMaster extends Model
{
    use HasFactory;
    protected $table = "tb_prvalidationrulegroupmasters";
    public $timestamps = false;
    protected $primaryKey = "n_PrValidationRuleGroupMasters_PK";

    public static function boot() {
        parent::boot();
        /**
         * Write code on Method
         */
        static::creating(function($model) {
            $model->n_CreatedUser = \Auth::user()->id;
            $model->d_CreatedDate = now();
        });
        /**
         * Write code on Method
         *
         * @return response()
         */
        static::updating(function($model) {
            $model->n_UpdatedUser = \Auth::user()->id;
            $model->d_UpdatedDate = now();
        });
    }

    /**
     * Get the product associated with the validation-rule-group.
     */
    public function product()
    {
        return $this->hasOne(Product::class, 'id', 'n_Product_FK');
    }
}
