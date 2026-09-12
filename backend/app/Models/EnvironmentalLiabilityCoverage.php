<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use AlphaDirect\Models\PolicyCoverage;

class EnvironmentalLiabilityCoverage extends Model
{
    use HasFactory;

    protected $table = 'environmental_liability_coverages';

    protected $fillable = [];

    protected $guarded = ['id'];

    protected $casts = [
        'sites'              => 'array',
        'coverage_sections'  => 'array',
        'deductibles'        => 'array',
        'pollutants_covered' => 'array',
        'key_exclusions'     => 'array',
        'endorsements'       => 'array',
        'inception_date'     => 'date',
        'expiry_date'        => 'date',
        'today_date'         => 'date',
        'retroactive_date'   => 'date',
        'premium_due_date'   => 'date',
        'approved_at'        => 'datetime',
    ];

    public static function getEnvironmentalLiabilityTotal($policyId)
    {
        $data = self::where('policy_id', $policyId)
            ->where('policy_coverage_id', '!=', null)
            ->get();

        return $data->sum('premium');
    }

    public function policyCoverage()
    {
        return $this->belongsTo(PolicyCoverage::class, 'policy_coverage_id');
    }

    public static function getPremium($policyId, $actionId, $termId)
    {
        $premium = 0;
        $dataPremium = self::join('policy_coverages as pc', 'pc.policy_id', '=', 'environmental_liability_coverages.policy_id')
            ->where('environmental_liability_coverages.policy_id', $policyId)
            ->where('environmental_liability_coverages.action_id', $actionId)
            ->where('environmental_liability_coverages.term_id', $termId)
            ->where('environmental_liability_coverages.status', '0')
            ->get();
        if ($dataPremium->count() > 0) {
            $premium = $dataPremium->sum('premium');
        }
        return $premium;
    }
}
