<?php

namespace AlphaDirect\Models;

use AlphaDirect\Models\CoverageMaster;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PolicySpecifiedItem extends Model
{
    use HasFactory;
   use SoftDeletes;

    protected $guarded = ['id'];

    public static function boot() {
        parent::boot();

        static::creating(function($model) {
            $model->calculated_value = ($model->sum_insured * $model->rate) / 100;
        });
        // added by snehal on 9-11-25
        static::updating(function($model) {
        $model->calculated_value = ($model->sum_insured * $model->rate) / 100;
    });
    }

    public function specifiedItem()
    {
        return $this->belongsTo(SpecifiedCoveragesItems::class,'specified_coverage_id');
    }
//    public static function savePolicySpecifiedItems($policy_id,$termId,$actionId,$specified_items_data,$specified_items_with_rate){
//        static::policy($policy_id)->Term($termId)->Action($actionId)->delete();
//        $data = [];
//        if(empty($specified_items_data)) return ;
//        foreach ($specified_items_data as $riskAddressId => $addressWiseDatas){
//            foreach ($addressWiseDatas as $coverageId => $coverageData){
//                foreach ($coverageData as $index => $indexWiseData){
//                    foreach ($indexWiseData as $key => $specifiedData){
//                        $data[] = [
//                            'risk_address_id' => $riskAddressId,
//                            'coverage_id' => $coverageId,
//                            'specified_coverage_id' => $specifiedData['selected_item'],
//                            'sum_insured' => $specifiedData['sum_insured'],
//                            'rate' => $specified_items_with_rate[$coverageId][$specifiedData['selected_item']] ?? 0,
//                            'calculated_value' => (($specifiedData['sum_insured']*($specified_items_with_rate[$coverageId][$specifiedData['selected_item']] ?? 0))/100) ?? 0,
//                            'policy_id' => $policy_id,
//                            'term_id' => $termId,
//                            'action_id' => $actionId,
//                            'created_at' => now(),
//                            'updated_at' => now()
//                        ];
//                    }
//                }
//            }
//        }
//        if (!empty($data)){
//            Static::insert($data);
//        }
//    }

    public function scopeByPolicyCoverage($query,$policy_coverage_id){
        $query->where('policy_coverage_id',$policy_coverage_id);
    }

    public function specifiedCoveragesItems() {
        return $this->belongsTo('AlphaDirect\Models\SpecifiedCoveragesItems', 'specified_coverage_id', 'id');
    }

    public function policyCoverage() {
        return $this->belongsTo('AlphaDirect\Models\PolicyCoverage', 'policy_coverage_id', 'id');
    }

}
