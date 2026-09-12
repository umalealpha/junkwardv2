<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use AlphaDirect\Models\PolicyCoverage;

class DirectorsOfficersLiabilityCoverage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'directors_officers_liability_coverages';

    protected $fillable = [];

    protected $guarded = ['id'];

    protected $casts = [
        'insuring_clauses' => 'array',
        'extensions' => 'array',
        'coverage_extensions' => 'array',
        'inception_date' => 'date',
        'expiry_date' => 'date',
        'today_date' => 'date',
        'backdated_continuity_date' => 'date',
    ];

    public function policyCoverage()
    {
        return $this->belongsTo(PolicyCoverage::class, 'policy_coverage_id');
    }

    public static function getDirectorsOfficersLiabilityTotal($policyId)
    {
        return self::where('policy_id', $policyId)
            ->whereNotNull('policy_coverage_id')
            ->sum('premium');
    }

    public static function getPremium($policyId, $actionId, $termId)
    {
        return self::where('policy_id', $policyId)
            ->where('action_id', $actionId)
            ->where('term_id', $termId)
            ->where('status', '0')
            ->sum('premium');
    }
}
