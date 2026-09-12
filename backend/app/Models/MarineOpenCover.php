<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use AlphaDirect\Models\PolicyCoverage;

class MarineOpenCover extends Model
{
    use HasFactory;

    protected $table = 'marine_cargo_open_coverages';

    protected $fillable = [];

    protected $guarded = ['id'];

    protected $casts = [
        'clauses' => 'array',
        'policy_period_from' => 'date',
        'policy_period_to' => 'date',
        'today_date' => 'date',
        'signing_date' => 'date',
    ];

    public function policyCoverage()
    {
        return $this->belongsTo(PolicyCoverage::class, 'policy_coverage_id');
    }

    public static function getMarineOpenCoverTotal($policyId)
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
