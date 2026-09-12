<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParCoverage extends Model
{
    use HasFactory;
    
    protected $table = 'par_coverages';
    
    protected $fillable = [];
    
    protected $guarded = ['id'];
    
    protected $casts = [
        'insured_items' => 'array',
        'section2_items' => 'array',
        'reinsurance_fire_treaty' => 'boolean',
    ];
}

