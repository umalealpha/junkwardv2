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

    // DOM/COM CRITICAL-02 — stamp created_by/updated_by when the columns
    // are present. The table gets them via migration 2026_04_19_100003.
    protected static function booted()
    {
        parent::boot();
        static::creating(function ($m) {
            $uid = \Illuminate\Support\Facades\Auth::id();
            if ($uid) {
                if (\Schema::hasColumn('policy_coverage_notes', 'created_by') && empty($m->created_by)) {
                    $m->created_by = $uid;
                }
                if (\Schema::hasColumn('policy_coverage_notes', 'updated_by')) {
                    $m->updated_by = $uid;
                }
            }
        });
        static::updating(function ($m) {
            $uid = \Illuminate\Support\Facades\Auth::id();
            if ($uid && \Schema::hasColumn('policy_coverage_notes', 'updated_by')) {
                $m->updated_by = $uid;
            }
        });
    }

    public function scopePolicyCoverage($query,$policy_coverage_id){
        $query->where('policy_coverage_id',$policy_coverage_id);
    }

    // Coverage-level note rows (not tied to a vehicle). Legacy data stores
    // motor_id as 0 OR NULL for these, so match both — mirrors how the edit
    // screen reads them (empty($motor_id)). Motor (22/27) per-vehicle notes
    // have a real motor_id (> 0) and are excluded here.
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
            // Null-safe — the JOIN can return nothing when the coverage row
            // points at a coverage_id that's missing from tb_cvgpccoverages
            // (happens during replication before all FKs are settled).
            $screenName = $s_ScreenName?->s_ScreenName ?? 'Coverage';
            $data['tags'] = $screenName . ' Notes Updated';
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
