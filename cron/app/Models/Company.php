<?php

namespace AlphaDirect\Models;

use AlphaDirect\Customer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use AlphaDirect\City;

class Company extends Model
{
    use HasFactory;
    protected $casts = [
        'status' => 'boolean',
    ];
    protected $guarded = [];

    public static function boot() {
        parent::boot();
        /**
         * Write code on Method
         */
        static::creating(function($model) {
            $model->created_by = \Auth::user()->id ?? 0;
        });
        /**
         * Write code on Method
         *
         * @return response()
         */
        static::updating(function($model) {
            $model->updated_by = \Auth::user()->id;
        });
    }

    public function subCompanies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Static::class, 'parent_id');
    }

    public function parentCompany(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Static::class, 'parent_id');
    }

    public function getStatus(){
        return $this->status ? 'Activated' : 'Deactivated' ;
    }

    public function scopeMainCompanyOnly($query){
        $query->whereNull('parent_id');
    }
    public function scopeSubCompanyOnly($query){
        $query->whereNotNull('parent_id');
    }

    public function scopeActivated($query){
        $query->where('status',1);
    }
    public function scopeName($query,$name){
        $query->where('name',$name);
    }
     public function cities()
    {
        return $this->hasOne(City::class,'id','city');
    }
}
