<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

class AdGroupKycShareholder extends Model
{
    protected $table = 'ad_group_kyc_shareholders';

    protected $fillable = [
        'submission_id',
        'full_name',
        'residential_address',
        'date_of_birth',
        'nationality',
        'ownership_percentage',
        'pip_declaration',
        'source_of_wealth',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'ownership_percentage' => 'decimal:2',
    ];
}


