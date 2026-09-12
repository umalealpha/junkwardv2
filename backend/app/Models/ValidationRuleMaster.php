<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ValidationRuleMaster extends Model
{
    use HasFactory;
    protected $table ='tb_prvalidationrulemasters';
    public $timestamps = false;
    protected $primaryKey = "n_PrValidationRuleMaster_PK";

    protected $casts = [
        'd_EffectiveDateFrom'  => 'date',
        'd_EffectiveDateTo'  => 'date',
    ];

    public function getDEffectiveDateFromAttribute($value)
    {
        return (new Carbon($value))->format(config('constants.date.format'));
    }

    public function getDEffectiveDateToAttribute($value)
    {
        return (new Carbon($value))->format(config('constants.date.format'));
    }

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

    public function detail()
    {
        return $this->hasMany(ValidationRuleDetail::class, 'n_PrValidationRuleMaster_FK', 'n_PrValidationRuleMaster_PK');
    }
}
