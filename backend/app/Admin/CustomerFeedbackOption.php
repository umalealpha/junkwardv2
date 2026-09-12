<?php

namespace AlphaDirect\Admin;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class CustomerFeedbackOption extends Model
{
    protected $table = 'customer_feedback_options';
    public $guarded = ['id'];

    public function suboptions()
    {
        return $this->hasMany('AlphaDirect\Admin\CustomerFeedbackSubOption', 'option_id', 'id');
    }

    public static function getActiveSuboptions()
    {
        return CustomerFeedbackOption::with(['suboptions' => function($q) {
            $q->whereStatus(1); 
        }])->whereStatus(1)->orderBy('id', 'desc')->get();
    }

    public static function getOptions($column,$value){
        try{
            $options = CustomerFeedbackOption::where($column,$value)->orderBy('name', 'ASC')->get(array('id','name'));
            return ['Response'=>'success','options'=>$options];
        }catch(\Exception $e){
            return ['Response'=>'error','Message'=>$e->getMessage()];
        }
    }

    /* public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d/m/Y');
    } */

    public function getNameAttribute($value)
    {
        return ucwords($value);
    }

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = ucwords($value);
    }
}
