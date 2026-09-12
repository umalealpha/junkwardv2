<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

class AdGroupKycDirector extends Model
{
    protected $table = 'ad_group_kyc_directors';

    protected $fillable = [
        'submission_id',
        'full_name',
        'residential_address',
        'date_of_birth',
        'nationality',
        'pip_declaration',
        'source_of_wealth',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];
}


