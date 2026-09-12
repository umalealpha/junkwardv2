<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class MotorTraders extends Model
{
    use SoftDeletes;
    use HasFactory;
    protected $table = 'motor_traders';
    protected $guarded = [];
}
