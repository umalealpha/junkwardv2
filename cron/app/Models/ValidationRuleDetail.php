<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ValidationRuleDetail extends Model
{
    use HasFactory;
    protected $table = 'tb_prvalidationruledetails';
    public $timestamps = false;

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
}
