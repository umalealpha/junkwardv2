<?php

namespace Modules\Cashback\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Agents extends Model
{
    use HasFactory;


    protected $table ="users";

	public function getNameAttribute($value)
    {
        return $this->firstName. ' '.$this->lastName;
    }

    protected static function newFactory()
    {
        return \Modules\Cashback\Database\factories\IncentiveFactory::new();
    }

}
