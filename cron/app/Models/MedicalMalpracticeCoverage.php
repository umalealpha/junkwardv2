<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicalMalpracticeCoverage extends Model
{
    use HasFactory;
    
    protected $table = 'medical_malpractice_coverages';
    
    protected $fillable = [];
    
    protected $guarded = ['id'];
    
    protected $casts = [
        'extensions' => 'array',
        'specific_deductibles' => 'array',
        'risk_details' => 'array',
        'retroactive_date' => 'date',
        'anniversary_renewal_date' => 'date',
    ];
    public static function getMedicalTotal($policyId)
    {
        $data = MedicalMalpracticeCoverage::where('policy_id', $policyId)
        ->where('policy_coverage_id','!=',null)
        ->get();
    
        $total = 0;
        
        // Sum column directly
        $total += $data->sum('annual_premium');
        
        // foreach ($data as $row) {        
        //     // risk_details
        //     if (!empty($row->risk_details) && is_array($row->risk_details)) {
        //         foreach ($row->risk_details as $item) {
        //             $value = $item['value'] ?? 0;
            
        //             // Remove commas / currency symbols if any
        //             $value = preg_replace('/[^\d.]/', '', $value);
            
        //             $total += is_numeric($value) ? (float) $value : 0;
        //         }
        //     }
        
        //     // specific_deductibles
        //     if (!empty($row->specific_deductibles) && is_array($row->specific_deductibles)) {
        //         foreach ($row->specific_deductibles as $item) {
        //             $total += $item['value'] ?? 0;
        //         }
        //     }
        //     // extensions
        //     if (!empty($row->extensions) && is_array($row->extensions)) {
        //         foreach ($row->extensions as $item) {
        //             $value = $item['basis_of_deductible'] ?? 0;
            
        //             // Remove non-numeric characters (%, commas, text)
        //             $value = is_numeric($value)
        //                 ? (float) $value
        //                 : (float) preg_replace('/[^0-9.\-]/', '', $value);
            
        //             $total += $value;
        //         }
        //     }
            
        // }
       return $total;
    }
    public static function getlimitIndemnity($policyId)
    {
        $total = 0;
        $data = MedicalMalpracticeCoverage::where('policy_id', $policyId)
        ->where('policy_coverage_id','!=',null)
        ->first();
        if($data){
            $extensions = $data->extensions ?? [];
            $specific_deductibles = $data->specific_deductibles ?? [];
            if($extensions && is_array($extensions)){
                foreach($extensions as $extension){
                    $total += $extension['limit_of_indemnity'] ?? 0;
                }
            }
            if($specific_deductibles && is_array($specific_deductibles)){
                foreach($specific_deductibles as $specific_deductible){
                    $total += $specific_deductible['limit_of_indemnity'] ?? 0;
                }
            }
        }
        return $total;
    }
}

