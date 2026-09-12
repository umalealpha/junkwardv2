<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfessionalIndemnityCoverage extends Model
{
    use HasFactory;
    
    protected $table = 'professional_indemnity_coverages';
    
    protected $fillable = [];
    
    protected $guarded = ['id'];
    
    protected $casts = [
        'descriptions' => 'array',
        'insured_persons' => 'array',
        'extensions' => 'array',
        'additional_extensions' => 'array',
        'excesses' => 'array',
        'retroactive_date' => 'date',
    ];
    public static function getProfessionalIndemnityTotal($policyId)
    {
        $data = ProfessionalIndemnityCoverage::where('policy_id', $policyId)
        ->where('policy_coverage_id','!=',null)
        ->first();
        
        $total = 0;
        if($data){
            $insured_persons = $data->insured_persons;
            $premium = $data->premium;
            $total += $premium;
            // foreach($insured_persons as $row){
            //     $total += $row['limit_of_liability'];
            // }
        } 
        return $total;
    }
    public static function getlimitIndemnity($policyId)
    {
        $data = ProfessionalIndemnityCoverage::where('policy_id', $policyId)
        ->where('policy_coverage_id','!=',null)
        ->get();
        $total = 0;
        
        foreach($data as $row){
            $total += $row->limit_of_liability;
        }
        return $total;
    }

    public static function getlimitIndemnityCert($policyId)
    {
        $data = ProfessionalIndemnityCoverage::where('policy_id', $policyId)
        ->where('policy_coverage_id','!=',null)
        ->get();
        $total = 0;
        
        $allLimits = [];

        foreach ($data as $row) {
            foreach ($row->insured_persons as $insured_person) {
                $allLimits[] = floatval($insured_person['limit_of_liability'] ?? 0);
            }
        }

            $maxLimit = !empty($allLimits) ? max($allLimits) : 0;
        return $maxLimit;
    }
}
