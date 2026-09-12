<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use AlphaDirect\Models\PolicyCoverage;

class BondsCoverage extends Model
{
    use HasFactory;

    protected $table = 'bonds_coverages';

    protected $fillable = [];

    protected $guarded = ['id'];

    protected $casts = [
        'bond_schedule'    => 'array',
        'extensions_endorsements' => 'array',
        'inception_date'   => 'date',
        'expiry_date'      => 'date',
        'today_date'       => 'date',
        'approved_at'      => 'datetime',
    ];

    public static function getBondsTotal($policyId)
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
        $dataPremium = self::join('policy_coverages as pc', 'pc.policy_id', '=', 'bonds_coverages.policy_id')
            ->where('bonds_coverages.policy_id', $policyId)
            ->where('bonds_coverages.action_id', $actionId)
            ->where('bonds_coverages.term_id', $termId)
            ->where('bonds_coverages.status', '0')
            ->get();
        if ($dataPremium->count() > 0) {
            $premium = $dataPremium->sum('premium');
        }
        return $premium;
    }
}
