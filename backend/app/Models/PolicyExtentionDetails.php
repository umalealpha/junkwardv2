<?php

namespace AlphaDirect\Models;

use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use AlphaDirect\Models\Extention;
use AlphaDirect\Models\TbCvgpcLimits;
use OwenIt\Auditing\Contracts\Auditable;

class PolicyExtentionDetails extends Model implements Auditable
{
    use SoftDeletes;
    use HasFactory;
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

    protected $table = 'policy_extention_detail';
    protected $guarded = [];

    public function extention()
    {
        return $this->belongsTo(Extention::class,'extentions_id','id')->where('type','=','Extention')->where('s_DISPLAYTOUSER',1)->orderBy('n_DisplaySequence','asc');
    }

    public function perils()
    {
        return $this->belongsTo(Extention::class,'extentions_id','id')->where('type','=','Perils')->orderBy('n_DisplaySequence','asc');
    }

    public function extentionCvgpclimits()
    {
        return $this->belongsTo(TbCvgpcLimits::class,'extention_limit_id','n_PCLimitId_PK');
    }



}
