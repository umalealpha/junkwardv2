<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ADPricing extends Model
{
    use HasFactory;

    protected $table = 'ad_pricings';

    protected $fillable = [
        'gender',
        'age',
        'adult_dependent',
        'child_dependent',
        'total_dependents',
        'premium',
        'total_premium',
        'product',
        'age_from',
        'age_to',
        'main'
    ];
}
