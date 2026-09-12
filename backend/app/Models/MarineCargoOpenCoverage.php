<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use AlphaDirect\Models\PolicyCoverage;

class MarineCargoOpenCoverage extends Model
{
    use HasFactory;

    protected $table = 'marine_cargo_open_coverages';

    protected $fillable = [];

    protected $guarded = ['id'];

    protected $casts = [
        'clauses' => 'array',
        'signing_date' => 'date',
        'policy_period_from' => 'date',
        'policy_period_to' => 'date',
        'today_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public static function getMarineCargoOpenTotal($policyId)
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
        $dataPremium = self::join("policy_coverages as pc", "pc.policy_id", "=", "marine_cargo_open_coverages.policy_id")
            ->where('marine_cargo_open_coverages.policy_id', $policyId)
            ->where('marine_cargo_open_coverages.action_id', $actionId)
            ->where('marine_cargo_open_coverages.term_id', $termId)
            ->where('marine_cargo_open_coverages.status', '0')
            ->get();
        if ($dataPremium->count() > 0) {
            $premium = $dataPremium->sum('premium');
        }
        return $premium;
    }
}
