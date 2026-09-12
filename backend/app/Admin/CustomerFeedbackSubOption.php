<?php

namespace AlphaDirect\Admin;

use Illuminate\Database\Eloquent\Model;

class CustomerFeedbackSubOption extends Model
{
    protected $table = 'customer_feedback_sub_options';
    public $guarded = ['id'];

    public function option()
    {
        return $this->belongsTo('AlphaDirect\Admin\CustomerFeedbackOption');
    }
    
    public function getNameAttribute($value)
    {
        return ucwords($value);
    }

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = ucwords($value);
    }
}
