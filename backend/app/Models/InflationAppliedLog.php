<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only record of every sum-insured line an inflation rule has uplifted.
 *
 * Doubles as the idempotency guard: alreadyApplied() is what stops a re-run
 * compounding 10% into 21%, at LINE level rather than action level, so a rule
 * added later can still land on an action an earlier rule already touched.
 *
 * Not Auditable — the table is itself the audit trail, and rows are never
 * updated or deleted.
 */
class InflationAppliedLog extends Model
{
    protected $table   = 'inflation_applied_log';
    protected $guarded = [];

    protected $casts = [
        'rule_id'        => 'integer',
        'policy_id'      => 'integer',
        'action_id'      => 'integer',
        'row_id'         => 'integer',
        'coverage_id'    => 'integer',
        'pct'            => 'float',
        'si_before'      => 'float',
        'si_after'       => 'float',
        'premium_before' => 'float',
        'premium_after'  => 'float',
    ];

    public function rule()
    {
        return $this->belongsTo(InflationRateMaster::class, 'rule_id');
    }

    /**
     * Has this exact rule already been applied to this exact row of this
     * action? Returns the earlier log row (so the caller can report the
     * percent that was used), or null.
     */
    public static function alreadyApplied(
        int $actionId,
        int $rowId,
        int $ruleId,
        string $sourceTable = 'policy_coverage_detail'
    ): ?self {
        return static::where('action_id', $actionId)
            ->where('source_table', $sourceTable)
            ->where('row_id', $rowId)
            ->where('rule_id', $ruleId)
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * All prior applications on an action, keyed "<source_table>:<row_id>:<rule_id>",
     * so a run can check hundreds of lines without a query per line.
     *
     * @param  array<int,int> $actionIds
     * @return array<string,self>
     */
    public static function guardMap(array $actionIds): array
    {
        if (empty($actionIds)) {
            return [];
        }

        $map = [];

        static::whereIn('action_id', $actionIds)
            ->orderBy('id')
            ->get()
            ->each(function (self $row) use (&$map) {
                $map[$row->action_id . ':' . $row->source_table . ':' . $row->row_id . ':' . $row->rule_id] = $row;
            });

        return $map;
    }

    public static function guardKey(
        int $actionId,
        int $rowId,
        int $ruleId,
        string $sourceTable = 'policy_coverage_detail'
    ): string {
        return $actionId . ':' . $sourceTable . ':' . $rowId . ':' . $ruleId;
    }
}
