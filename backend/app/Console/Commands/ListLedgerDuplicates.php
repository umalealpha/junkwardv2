<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * reconciliation:list-ledger-duplicates — READ-ONLY companion to the
 * UNIQUE(policy_id, trans_type, trans_ref) idempotency migration.
 *
 * The migration REFUSES to add the unique index while duplicate keys exist
 * (a blind ALTER would fail with error 1062). Run this FIRST to see exactly
 * which (policy_id, trans_type, trans_ref) groups collide, so Finance/IT can
 * dedup them deliberately before the constraint is enabled.
 *
 * It counts PHYSICAL rows (including soft-deleted) because a MySQL/MariaDB
 * unique index is enforced on physical rows regardless of deleted_at. Rows
 * where policy_id OR trans_ref is NULL are ignored — a composite unique index
 * does not constrain rows that have a NULL in any indexed column.
 *
 * Writes NOTHING. NOT scheduled.
 */
class ListLedgerDuplicates extends Command
{
    protected $signature = 'reconciliation:list-ledger-duplicates
        {--trans-type= : restrict to a single trans_type (e.g. Payment)}
        {--limit=0 : max duplicate groups to print/export (0 = all)}
        {--report= : write the duplicate groups to this CSV path}';

    protected $description = 'READ-ONLY: list policy_ledger (policy_id, trans_type, trans_ref) duplicates that would block the idempotency unique index.';

    public function handle()
    {
        $transType = $this->option('trans-type');
        $limit     = (int) $this->option('limit');

        $base = DB::table('policy_ledger')
            ->whereNotNull('policy_id')
            ->whereNotNull('trans_ref');
        if ($transType) {
            $base->where('trans_type', $transType);
        }

        // Duplicate groups (what the unique index would reject).
        $groupsQuery = (clone $base)
            ->select('policy_id', 'trans_type', 'trans_ref')
            ->selectRaw('COUNT(*) AS n')
            ->selectRaw('SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) AS live_n')
            ->selectRaw('SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) AS deleted_n')
            ->selectRaw('GROUP_CONCAT(id ORDER BY id) AS ledger_ids')
            ->groupBy('policy_id', 'trans_type', 'trans_ref')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('n');

        $groupCount = (clone $base)
            ->select('policy_id', 'trans_type', 'trans_ref')
            ->groupBy('policy_id', 'trans_type', 'trans_ref')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        if ($limit > 0) {
            $groupsQuery->limit($limit);
        }
        $groups = $groupsQuery->get();

        $fh = null;
        if ($this->option('report')) {
            $fh = @fopen($this->option('report'), 'w');
            if (! $fh) {
                $this->error('Cannot open report path: ' . $this->option('report'));
                return 1;
            }
            fputcsv($fh, ['policy_id', 'trans_type', 'trans_ref', 'total_rows', 'live_rows', 'soft_deleted_rows', 'ledger_ids']);
        }

        $extraRows = 0;
        $byType = [];
        foreach ($groups as $g) {
            $extraRows += ((int) $g->n - 1); // rows that must be removed to satisfy the index
            $byType[$g->trans_type] = ($byType[$g->trans_type] ?? 0) + 1;
            if ($fh) {
                fputcsv($fh, [$g->policy_id, $g->trans_type, $g->trans_ref, $g->n, $g->live_n, $g->deleted_n, $g->ledger_ids]);
            }
        }
        if ($fh) {
            fclose($fh);
        }

        $this->line('');
        $this->info('========== policy_ledger IDEMPOTENCY DUPLICATE CHECK (read-only) ==========');
        $this->line('Duplicate key groups (policy_id, trans_type, trans_ref) : ' . $groupCount);
        $this->line('Groups shown/exported                                   : ' . $groups->count());
        $this->line('Excess rows to remove before the unique index           : ' . $extraRows);
        foreach ($byType as $type => $cnt) {
            $this->line('  by trans_type "' . $type . '"' . str_repeat(' ', max(1, 30 - strlen($type))) . ': ' . $cnt . ' groups (shown)');
        }
        if ($this->option('report')) {
            $this->line('Report                                                  : ' . $this->option('report'));
        }
        $this->info('==========================================================================');

        if ($groupCount === 0) {
            $this->info('No duplicates — the idempotency migration can be run safely.');
        } else {
            $this->warn('Duplicates present. Dedup them (Finance/IT sign-off) BEFORE running the idempotency migration; the migration will abort otherwise.');
        }
        return 0;
    }
}
