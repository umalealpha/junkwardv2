<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarineCargoOnceOff extends Model
{
    use HasFactory;

    protected $table = 'marine_cargo_once_off_claims';
    protected $guarded = ['id'];
}
