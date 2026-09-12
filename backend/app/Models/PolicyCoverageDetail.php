<?php

namespace AlphaDirect\Models;

use DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use AlphaDirect\Models\CoverageMaster;
use OwenIt\Auditing\Contracts\Auditable;

class PolicyCoverageDetail extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    /**
     * Skip auto-stamped fields so Rate (which restamps every row's
     * pro_rate_premium / endors_flag) doesn't generate hundreds of
     * "no real change" audit rows per click.
     */
    protected $auditExclude = [
        'updated_at', 'updated_by',
        'pro_rate_premium', 'endors_flag', 'previousActionIdCov',
    ];

    /** Audit row → policy_number resolver via parent policy_coverage. */
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

    protected $table      = "policy_coverage_detail";
    // V1 legacy table; uses default 'mysql' (V1 replica) post-pivot.
    protected $guarded    = [];


    public function scopePolicyCoverage($query,$policy_coverage_id){
        $query->where('policy_coverage_id',$policy_coverage_id);
    }

    public function coverage()
    {
        return $this->belongsTo(CoverageMaster::class, 'coverage_id');
    }

}
