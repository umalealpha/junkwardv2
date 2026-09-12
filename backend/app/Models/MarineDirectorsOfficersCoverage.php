<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use AlphaDirect\Models\PolicyCoverage;
class MarineDirectorsOfficersCoverage extends Model
{
    use HasFactory;

    protected $table = 'marine_directors_officers_coverages';

    protected $fillable = [];

    protected $guarded = ['id'];

    protected $casts = [
        'insuring_clauses' => 'array',
        'extensions' => 'array',
        'coverage_extensions' => 'array',
        'section1_items' => 'array',
        'insured_persons_listing' => 'array',
        'extra_cover_section1' => 'array',
        'section2_items' => 'array',
        'extra_cover_section2' => 'array',
        'section3_items' => 'array',
        'extra_cover_section3' => 'array',
        'extra_cover_all_sections' => 'array',
        'excess_details' => 'array',
        'misc_items' => 'array',
        'inception_date' => 'date',
        'expiry_date' => 'date',
        'backdated_continuity_date' => 'date',
    ];

    public static function getMarineDirectorsOfficersTotal($policyId)
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
        $dataPremium =  self::join("policy_coverages as pc", "pc.policy_id", "=", "marine_directors_officers_coverages.policy_id")
                    ->where('marine_directors_officers_coverages.policy_id', $policyId)
                    ->where('marine_directors_officers_coverages.action_id', $actionId)
                    ->where('marine_directors_officers_coverages.term_id', $termId)
                    ->where('marine_directors_officers_coverages.status', '0')
                    ->get();
        if($dataPremium->count() > 0){
            $premium = $dataPremium->sum('premium');
        }
        return $premium;
    }
}
