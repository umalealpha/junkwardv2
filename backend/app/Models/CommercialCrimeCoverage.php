<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use AlphaDirect\Models\PolicyCoverage;

class CommercialCrimeCoverage extends Model
{
    use HasFactory;

    protected $table = 'commercial_crime_coverages';

    protected $fillable = [];

    protected $guarded = ['id'];

    protected $casts = [
        'insuring_clauses'        => 'array',
        'excess_layers'           => 'array',
        'endorsements_extensions' => 'array',
        'inception_date'          => 'date',
        'expiry_date'             => 'date',
        'today_date'              => 'date',
        'retroactive_date'        => 'date',
        'approved_at'             => 'datetime',
    ];

    public static function getCommercialCrimeTotal($policyId)
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
        $dataPremium = self::join('policy_coverages as pc', 'pc.policy_id', '=', 'commercial_crime_coverages.policy_id')
            ->where('commercial_crime_coverages.policy_id', $policyId)
            ->where('commercial_crime_coverages.action_id', $actionId)
            ->where('commercial_crime_coverages.term_id', $termId)
            ->where('commercial_crime_coverages.status', '0')
            ->get();
        if ($dataPremium->count() > 0) {
            $premium = $dataPremium->sum('premium');
        }
        return $premium;
    }
}
