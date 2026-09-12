<?php

namespace AlphaDirect\Models;

use DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use OwenIt\Auditing\Contracts\Auditable;

class V2PdfJob extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    /**
     * V2-owned table — lives on graphite-v2-prod, not on V1's read replica.
     * Without this, every V2PdfJob::create() / save() / delete() hits the
     * default 'mysql' connection (V1 replica) and fails with 1290 read-only.
     */
    protected $connection = 'mysql_system';

    /**
     * Skip churn fields — `progress` ticks every few seconds while a PDF
     * renders and `message` is a human status string that flips constantly.
     * The Logs tab cares about job creation + final status (completed /
     * failed), not the in-flight noise.
     */
    protected $auditExclude = ['progress', 'message', 'updated_at'];

    /** Resolve policy_number from this job's policy_id. */
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
                // Surface the human-friendly verb on the Logs row.
                $data['tags'] = ($this->document_title ?: 'Quote Sheet') . ' ' . ($this->auditEvent ?? 'updated');
            }
        }
        return $data;
    }

    protected $fillable = [
        'policy_id',
        'term_id',
        'action_id',
        'created_by_user_id',
        'status',
        'progress',
        'message',
        'file_name',
        'document_title',
    ];

    protected $dates = ['created_at', 'updated_at'];

    /**
     * User who triggered this job. Nullable — cron/system-triggered jobs
     * leave created_by_user_id NULL.
     *
     * No connection override on this relation by design: User lives on the
     * 'mysql' connection (V1 legacy read), which is fine for a read-only
     * lookup of firstName/lastName. The new latestForPolicy() endpoint also
     * resolves the name via an explicit JOIN to avoid an N+1.
     */
    public function createdBy()
    {
        return $this->belongsTo(\AlphaDirect\User::class, 'created_by_user_id', 'id');
    }
}