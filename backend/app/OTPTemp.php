<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OTPTemp extends Model
{
    use HasFactory;
    protected $table = 'o_t_p_temps';
    protected $guarded = [];
}
