<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use AlphaDirect\Models\PolicyCoverage;

class MedicalEvacuationCoverage extends Model
{
    use HasFactory;

    protected $table = 'medical_evacuation_coverages';

    protected $fillable = [];

    protected $guarded = ['id'];

    protected $casts = [
        'description_items' => 'array',
        'extension_items'   => 'array',
        'inception_date'    => 'date',
        'expiry_date'       => 'date',
        'today_date'        => 'date',
        'approved_at'       => 'datetime',
    ];

    public static function getMedicalEvacuationTotal($policyId)
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
        $dataPremium = self::join('policy_coverages as pc', 'pc.policy_id', '=', 'medical_evacuation_coverages.policy_id')
            ->where('medical_evacuation_coverages.policy_id', $policyId)
            ->where('medical_evacuation_coverages.action_id', $actionId)
            ->where('medical_evacuation_coverages.term_id', $termId)
            ->where('medical_evacuation_coverages.status', '0')
            ->get();
        if ($dataPremium->count() > 0) {
            $premium = $dataPremium->sum('premium');
        }
        return $premium;
    }
}
