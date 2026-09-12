<?php

namespace AlphaDirect\Models;

use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
//use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use OwenIt\Auditing\Contracts\Auditable;

class Motor extends Model implements Auditable
{
  // use SoftDeletes;
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    /** Skip auto-stamps so Rate doesn't flood audits with unchanged-row noise. */
    protected $auditExclude = [
        'updated_at', 'updated_by',
        'pro_rate_premium', 'endors_flag', 'previousActionIdCov',
    ];

    /** Resolve policy_number through the parent policy_coverages row. */
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

    protected $table = 'motor';
    protected $guarded = [];

    /**
     * Read-side normalisation of the cover type.
     *
     * The quote sheet / policy schedule blades match this attribute against
     * the canonical tokens ('third_party_only', 'Third_fire_and_theft') both
     * to PRINT the Type of Cover cell and to gate the third-party-only
     * blocks. Rows written by the V2 wizard before the canonicalisation fix
     * hold the display label instead, which matched nothing — the cell came
     * out blank and a third-party vehicle rendered as comprehensive.
     * Folding the alias here makes those rows read correctly without waiting
     * for policy:fix-motor-type-of-cover. Unknown values pass through.
     */
    public function getTypeOfCoverAttribute($value)
    {
        return \AlphaDirect\Support\MotorCoverType::normalize($value);
    }

    /** Same for the *_main baseline column. */
    public function getTypeOfCoverMainAttribute($value)
    {
        return \AlphaDirect\Support\MotorCoverType::normalize($value);
    }

    /**
     * Write-side normalisation — every Eloquent writer lands a canonical
     * token whatever it was handed. Covers the Excel coverage import
     * (CoverageImport::saveMotorData does a bare Motor::create() with the
     * sheet's dropdown LABEL — "Third party only" — and, unlike the motor
     * traders importers, applies no mapping of its own).
     * Note: replicate() copies raw attributes, so endorse/renew replication
     * still carries a row forward byte-for-byte.
     */
    public function setTypeOfCoverAttribute($value)
    {
        $this->attributes['type_of_cover'] = \AlphaDirect\Support\MotorCoverType::normalize($value);
    }

    /** Same for the *_main baseline column. */
    public function setTypeOfCoverMainAttribute($value)
    {
        $this->attributes['type_of_cover_main'] = \AlphaDirect\Support\MotorCoverType::normalize($value);
    }
    public function policyCoverage()
    {
        return $this->belongsTo(PolicyCoverage::class, 'policy_coverage_id');
    }

}
