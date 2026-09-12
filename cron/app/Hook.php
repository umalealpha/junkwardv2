<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;

class Hook extends Model
{
    protected $table = 'hooks';
    protected $fillable = [];
    protected $guarded = ['id'];
}
