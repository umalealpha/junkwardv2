<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Audits extends Model
{
    use HasFactory;
   // protected $connection = 'mysql4';
    protected $table ='audits';

    public function user(){
        return $this->belongsTo(\AlphaDirect\User::class,'user_id');
    }

    public function user_data(){
        return $this->hasOne(\AlphaDirect\User::class,'id');
    }
}
