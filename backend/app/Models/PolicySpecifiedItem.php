<?php

namespace AlphaDirect\Models;

use DB;
use AlphaDirect\Models\CoverageMaster;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use OwenIt\Auditing\Contracts\Auditable;

class PolicySpecifiedItem extends Model implements Auditable
{
    use HasFactory;
   use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    /** Skip auto-stamps so Rate doesn't flood audits. */
    protected $auditExclude = [
        'updated_at', 'updated_by',
        'pro_rate_premium', 'endors_flag', 'previousActionIdCov',
    ];

    private static array $cachedPidByPc = [];
    private static array $cachedPolicyNumber = [];
    public function transformAudit(array $data): array
    {
        if (Arr::has($data, 'new_values') || Arr::has($data, 'old_values')) {
            $pcId = $this->policy_coverage_id;
            if ($pcId) {
                if (!isset(self::$cachedPidByPc[$pcId])) {
                    self::$cachedPidByPc[$pcId] = DB::table('policy_coverages')->where('id', $pcId)->value('policy_id');
                }
                $pid = self::$cachedPidByPc[$pcId];
                if ($pid) {
                    if (!isset(self::$cachedPolicyNumber[$pid])) {
                        self::$cachedPolicyNumber[$pid] = DB::table('policies')->where('id', $pid)->value('policyNumber');
                    }
                    $data['policy_id']     = $pid;
                    $data['policy_number'] = self::$cachedPolicyNumber[$pid];
                }
            }
        }
        return $data;
    }

    protected $guarded = ['id'];

    public static function boot() {
        parent::boot();

        static::creating(function($model) {
            $model->calculated_value = ($model->sum_insured * $model->rate) / 100;
            // DOM/COM CRITICAL-02 audit stamps — only when the columns exist
            // (install-safe for environments that haven't run the migration).
            $uid = \Illuminate\Support\Facades\Auth::id();
            if ($uid) {
                if (\Schema::hasColumn('policy_specified_items', 'created_by') && empty($model->created_by)) {
                    $model->created_by = $uid;
                }
                if (\Schema::hasColumn('policy_specified_items', 'updated_by')) {
                    $model->updated_by = $uid;
                }
            }
        });
        // added by snehal on 9-11-25
        static::updating(function($model) {
            $model->calculated_value = ($model->sum_insured * $model->rate) / 100;
            $uid = \Illuminate\Support\Facades\Auth::id();
            if ($uid && \Schema::hasColumn('policy_specified_items', 'updated_by')) {
                $model->updated_by = $uid;
            }
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
