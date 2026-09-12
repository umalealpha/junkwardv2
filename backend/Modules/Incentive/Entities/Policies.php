<?php

namespace Modules\Incentive\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Policies extends Model
{
    use HasFactory;

    protected $fillable = [];
    protected $table ='policies';
	
    protected static function newFactory()
    {
        return \Modules\Incentive\Database\factories\PoliciesFactory::new();
    }
}
