<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BundledRerate extends Model
{
    use HasFactory;
    protected $table = 'bundled_rerate';
    protected $guarded = ['id'];
}
