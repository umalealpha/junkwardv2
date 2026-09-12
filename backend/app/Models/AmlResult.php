<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmlResult extends Model
{
    use HasFactory;

    protected $fillable = ['kyc_case_id','query','results','max_score','datasets','target','programId'];

    protected $casts = [
        'query'   => 'array', // Remove encryption to avoid size constraints
        'results' => 'array', // Remove encryption to avoid size constraints
        'max_score' => 'decimal:3',
        'datasets' => 'array', // Remove encryption to avoid size constraints
        'target' => 'json', // Store raw API response as JSON to preserve exact value
        'programId' => 'array',
    ];

    public function kycCase(){ return $this->belongsTo(KycCase::class); }
}
