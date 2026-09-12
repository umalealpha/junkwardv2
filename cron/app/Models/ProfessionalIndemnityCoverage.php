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
        ->get();
        $total = 0;        
        foreach($data as $row){           
            // insured_persons
            if (!empty($row->insured_persons) && is_array($row->insured_persons)) {
                foreach ($row->insured_persons as $item) {

                    $value = $item['limit_of_liability'] ?? 0;

                    // Convert to numeric safely
                    if (is_numeric($value)) {
                        $value = (float) $value;
                    } else {
                        $value = (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
                    }

                    $total += $value;
                }
            }

            // extensions
            if (!empty($row->extensions) && is_array($row->extensions)) {
                foreach ($row->extensions as $item) {
            
                    $value = $item['premium'] ?? 0;
            
                    // normalize to float
                    $value = is_numeric($value)
                        ? (float) $value
                        : (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
            
                    $total += $value;
                }
            }
            
            // additional_extensions
            if (!empty($row->additional_extensions) && is_array($row->additional_extensions)) {
                foreach ($row->additional_extensions as $item) {
                    $value = $item['premium'] ?? 0;
                    $total += $value;
                }
            }
            // excesses
            if (!empty($row->excesses) && is_array($row->excesses)) {
                foreach ($row->excesses as $item) {
            
                    $value = $item['minimum_excess'] ?? 0;
            
                    $value = is_numeric($value)
                        ? (float) $value
                        : (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
            
                    $total += $value;
                }
            }
            
        }
        
        return $total;
    }
}
