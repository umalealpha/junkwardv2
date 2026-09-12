<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class MotorTradersInternal extends Model
{
    use SoftDeletes;
    use HasFactory;
    protected $table = 'motor_traders_internal';
    protected $guarded = [];
}
