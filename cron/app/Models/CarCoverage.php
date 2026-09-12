<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarCoverage extends Model
{
    use HasFactory;
    
    protected $table = 'car_coverages';
    
    protected $fillable = [];
    
    protected $guarded = ['id'];
    
    protected $casts = [
        'section1_items' => 'array',
        'section2_items' => 'array',
        'section3_items' => 'array',
        'section3_contract_works' => 'array',
        'plant_list_items' => 'array',
        'reinsurance_fire_treaty' => 'boolean',
    ];
}

