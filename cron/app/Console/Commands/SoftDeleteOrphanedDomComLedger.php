<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use AlphaDirect\Models\CronStatus;

/**
 * SoftDeleteOrphanedDomComLedger
 *
 * Soft-deletes ledger / sub-ledger invoice rows whose parent policy_action
 * was soft-deleted but the invoice was left live. A live invoice under a
 * dead action is counted in ageing → wrong / phantom ageing balances.
 *
 * RULE: if the action is deleted, the invoice it raised is not needed.
 *
 * Scope: DOM/COM only (product_id 7 = COM, 8 = DOM).
 *
 * Gotchas baked in:
 *   - policy_ledger.action_id / policy_subledger.action_id are STRING columns
 *     while policy_actions.id is BIGINT → join via CAST(action_id AS UNSIGNED).
 *   - Neither Ledger nor SubLedger uses the SoftDeletes trait, so we stamp
 *     deleted_at (+ updated_at) directly with an UPDATE.
 *   - policy_subledger.deleted_at is not guaranteed to exist (no migration
 *     adds it) → Schema::hasColumn guard, skip the table if absent.
 *
 * Idempotent: only touches rows where deleted_at IS NULL, so re-running is a
 * no-op once cleaned.
 *
 * Scheduling: registered via the cron_kernels table / Cron Portal
 * (cron_name = 'ledger:soft-delete-orphaned-domcom'); the Kernel auto-loads
 * this command from the Commands directory.
 */
class SoftDeleteOrphanedDomComLedger extends Command
{
    protected $signature = 'ledger:soft-delete-orphaned-domcom
                            {--dry-run : Count affected rows without writing}
                            {--policy= : Restrict to a single policy_id}';

    protected $description = 'Soft-delete DOM/COM (product 7/8) ledger & sub-ledger invoices whose parent action was soft-deleted (prevents wrong ageing)';

    /** DOM/COM product ids. 7 = COM (Commercial), 8 = DOM (Domestic). */
    private const PRODUCTS = [7, 8];

    private bool $dryRun = false;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $policyId     = $this->option('policy') ? (int) $this->option('policy') : null;

        $cronStatus = null;
        if (!$this->dryRun) {
            try {
                $cronStatus = CronStatus::create([
                    'name'  => 'ledger:soft-delete-orphaned-domcom',
                    'start' => now(),
                ]);
            } catch (\Exception $e) {
                // CronStatus is observability only — never block the cleanup on it.
            }
        }

        $this->info('========================================');
        $this->info('ORPHANED DOM/COM LEDGER CLEANUP' . ($this->dryRun ? ' [DRY RUN]' : ''));
        $this->info('Started: ' . now());
        if ($policyId) {
            $this->info("Scoped to policy_id = {$policyId}");
        }
        $this->info('========================================');

        try {
            $policyFilter = $policyId ? ' AND p.id = ' . $policyId : '';

            // ── 1. policy_ledger ──────────────────────────────────────────
            $ledgerCount = $this->cleanTable('policy_ledger', $policyFilter);

            // ── 2. policy_subledger (only if it has a deleted_at column) ───
            $subCount = 0;
            if (Schema::hasColumn('policy_subledger', 'deleted_at')) {
                $subCount = $this->cleanTable('policy_subledger', $policyFilter);
            } else {
                $this->warn('policy_subledger has no deleted_at column — skipped.');
                Log::warning('SoftDeleteOrphanedDomComLedger: policy_subledger has no deleted_at column, skipped.');
            }

            $this->info('----------------------------------------');
            $this->info('SUMMARY' . ($this->dryRun ? ' [DRY RUN — no writes]' : ''));
            $this->info("policy_ledger    rows soft-deleted: {$ledgerCount}");
            $this->info("policy_subledger rows soft-deleted: {$subCount}");
            $this->info('Finished: ' . now());
            $this->info('========================================');

            if (!$this->dryRun) {
                Log::info("SoftDeleteOrphanedDomComLedger completed: ledger={$ledgerCount} subledger={$subCount}"
                    . ($policyId ? " policy={$policyId}" : ''));
            }

            if ($cronStatus) {
                $cronStatus->update(['end' => now()]);
            }
            return 0;
        } catch (\Throwable $e) {
            $this->error('FAILED: ' . $e->getMessage() . ' at line ' . $e->getLine());
            Log::error('SoftDeleteOrphanedDomComLedger failed: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            if ($cronStatus) {
                $cronStatus->update(['end' => now()]);
            }
            return 1;
        }
    }

    /**
     * Count (dry-run) or soft-delete live invoice rows on $table whose parent
     * action is soft-deleted, restricted to DOM/COM products.
     *
     * The action_id column is a string, so it is cast to UNSIGNED for the join
     * to policy_actions.id, and guarded against non-numeric / empty values.
     */
    private function cleanTable(string $table, string $policyFilter): int
    {
        $products = implode(',', self::PRODUCTS);

        $where = "
            FROM {$table} l
            INNER JOIN policy_actions pa
                ON pa.id = CAST(l.action_id AS UNSIGNED)
            INNER JOIN policies p
                ON p.id = l.policy_id
            WHERE pa.deleted_at IS NOT NULL      -- parent action soft-deleted
              AND l.deleted_at IS NULL           -- invoice still live
              AND l.action_id IS NOT NULL
              AND l.action_id <> ''
              AND l.action_id REGEXP '^[0-9]+$'  -- numeric action_id only
              AND p.product_id IN ({$products})
              {$policyFilter}
        ";

        $count = (int) DB::selectOne("SELECT COUNT(*) AS cnt {$where}")->cnt;
        $this->info(($this->dryRun ? '[dry] ' : '') . "{$table}: {$count} orphaned invoice row(s)");

        if (!$this->dryRun && $count > 0) {
            DB::statement("
                UPDATE {$table} l
                INNER JOIN policy_actions pa
                    ON pa.id = CAST(l.action_id AS UNSIGNED)
                INNER JOIN policies p
                    ON p.id = l.policy_id
                SET l.deleted_at = NOW(), l.updated_at = NOW()
                WHERE pa.deleted_at IS NOT NULL
                  AND l.deleted_at IS NULL
                  AND l.action_id IS NOT NULL
                  AND l.action_id <> ''
                  AND l.action_id REGEXP '^[0-9]+$'
                  AND p.product_id IN ({$products})
                  {$policyFilter}
            ");
        }

        return $count;
    }
}
