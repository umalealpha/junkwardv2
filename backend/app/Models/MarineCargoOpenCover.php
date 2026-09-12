<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarineCargoOpenCover extends Model
{
    use HasFactory;

    protected $table = 'marine_cargo_open_cover_claims';
    protected $guarded = ['id'];
}
