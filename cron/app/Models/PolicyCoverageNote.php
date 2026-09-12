<?php
namespace AlphaDirect;
namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use Illuminate\Support\Arr;
use AlphaDirect\Policy;
use Illuminate\Support\Facades\DB;

class PolicyCoverageNote extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    use SoftDeletes;
    protected $guarded = [];

    public function scopePolicyCoverage($query,$policy_coverage_id){
        $query->where('policy_coverage_id',$policy_coverage_id);
    }

    // Mirrors backend/app/Models/PolicyCoverageNote::scopeCoverageLevel().
    // Coverage-level note rows (not tied to a vehicle). Legacy data stores
    // motor_id as 0 OR NULL for these, so match both. Motor (22/27)
    // per-vehicle notes have a real motor_id (> 0) and are excluded here.
    public function scopeCoverageLevel($query){
        return $query->where(function($q){
            $q->whereNull('motor_id')->orWhere('motor_id', 0);
        });
    }

    public function transformAudit(array $data): array
    {
         $this->coverType = PolicyCoverage::where('id',$this->policy_coverage_id)->first();
       
        if (Arr::has($data, 'new_values')) {
        if($this->auditEvent != 'created'){
        if(isset($this->coverType)){
            $s_ScreenName=DB::table('policy_coverages')
            ->join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverages.coverage_id')
            ->where('policy_coverages.id', $this->policy_coverage_id)
            ->first('tb_cvgpccoverages.s_ScreenName');
            $policy = Policy::where('id', $this->coverType->policy_id)->first();
            if($policy != null){
            $data['policy_id'] = $policy->id;
            $data['tags'] = $s_ScreenName->s_ScreenName.'Notes Updated';
            $data['policy_number'] = $policy->policyNumber;
     }}}
        }
        //to store customer as a causer in case of APP/API call
        // if (Arr::has($data, 'new_values.lead_source')) {
        //     $data['customer_id'] = $data['new_values']['customer_id'];
        // }

        return $data;

    }
}
