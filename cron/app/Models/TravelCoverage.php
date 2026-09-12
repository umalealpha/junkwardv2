<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TravelCoverage extends Model
{
    use HasFactory;
    
    protected $table = 'travel_coverages';
    
    protected $fillable = [];
    
    protected $guarded = ['id'];
    
    protected $casts = [
        'benefits' => 'array',
        'custom_benefits' => 'array',
        'effective_from' => 'date',
        'expiry' => 'date',
    ];
    public static function getTravelTotal($policyId)
    {
        try{
        $data = TravelCoverage::where('policy_id', $policyId)
        ->where('policy_coverage_id','!=',null)
        ->first();
        $total = 0;
        // $benefits = !empty($data->benefits)    ? json_decode($data->benefits, true) : [];
        // $custom_benefits = !empty($data->custom_benefits) ? json_decode($data->custom_benefits, true) : [];
        // if (!is_null($benefits)) {
        //     foreach($benefits as $benefit){
        //         $total += (int) $benefit['sum_insured'];
        //     }
        // }
        // if (!is_null($custom_benefits)) {
        //     foreach($custom_benefits as $custom_benefit){
        //         $total += (int)$custom_benefit['sum_insured'];
        //     }
        // }
        $total += (int) $data->policy_amount;
        $total += (int) $data->vat;
        return $total;
        }catch(\Exception $e){
            return 0;
        }
    }
}

