<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EarCoverage extends Model
{
    use HasFactory;
    
    protected $table = 'ear_coverages';
    
    protected $fillable = [];
    
    protected $guarded = ['id'];
    
    protected $casts = [
        'section1_items' => 'array',
        'section3_items' => 'array',
        'reinsurance_fire_treaty' => 'boolean',
    ];
}

