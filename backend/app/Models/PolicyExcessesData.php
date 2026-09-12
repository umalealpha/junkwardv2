<?php

namespace AlphaDirect\Models;

use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use OwenIt\Auditing\Contracts\Auditable;

class PolicyExcessesData extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'policy_excesses_data';
    protected $guarded = [];

    /** Skip churn so Logs tab only shows real edits. */
    protected $auditExclude = ['updated_at', 'updated_by'];

    /** Resolve policy_number from policy_id (column on this table). */
    private static array $cachedPolicyNumber = [];
    public function transformAudit(array $data): array
    {
        if (Arr::has($data, 'new_values') || Arr::has($data, 'old_values')) {
            $pid = $this->policy_id;
            if ($pid) {
                if (!isset(self::$cachedPolicyNumber[$pid])) {
                    self::$cachedPolicyNumber[$pid] = DB::table('policies')->where('id', $pid)->value('policyNumber');
                }
                $data['policy_id']     = $pid;
                $data['policy_number'] = self::$cachedPolicyNumber[$pid];
            }
        }
        return $data;
    }
}
