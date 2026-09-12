<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Services\Cancel\PostCancelCleanupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PERMANENT nightly safety net for the "nothing renews after a cancel" rule.
 *
 * The rule is enforced at source the moment a CANCEL is issued (both issue
 * paths call PostCancelCleanupService), so on a healthy system this command
 * finds nothing. It exists because renewals are also written by crons, batch
 * screens and manual operator actions — any of which can land a RENEW /
 * ANNIVERSARY-RENEW on an already-cancelled policy after the cancel was
 * issued. This pass catches those within 24 hours instead of letting them
 * accumulate into another GRA-0132 backlog.
 *
 * All the rules (scope, guards, cascade) live in PostCancelCleanupService —
 * this command only decides WHICH policies to hand it.
 *
 * Replaces the one-time `policy:cleanup-renew-after-cancel` (GRA-0132), which
 * was DOM/COM-only and ISSUED-only. That command is left in place for its
 * historical audit trail; this one supersedes it and also covers specialist
 * products and non-ISSUED renewals.
 *
 * SAFE BY DEFAULT: previews unless --apply is passed. The scheduler passes it.
 *
 *   php artisan policy:cleanup-after-cancel                 # preview only
 *   php artisan policy:cleanup-after-cancel --policy=99604
 *   php artisan policy:cleanup-after-cancel --apply         # perform cleanup
 */
class CleanupTransactionsAfterCancel extends Command
{
    protected $signature = 'policy:cleanup-after-cancel
                            {--apply : Actually perform the soft-deletes. Without this the command only previews.}
                            {--policy= : Limit to a single policy (id or policyNumber).}';

    protected $description = 'Soft-delete DOM/COM + specialist RENEW / ANNIVERSARY-RENEW transactions that sit after an un-cleared ISSUED CANCEL (and discard their unpaid invoices).';

    public function handle(): int
    {
        $apply     = (bool) $this->option('apply');
        $policyRef = $this->option('policy');
        $tag       = $apply ? '' : '[DRY RUN] ';

        $service   = new PostCancelCleanupService();
        $policyIds = $this->candidatePolicyIds($policyRef);

        if (empty($policyIds)) {
            $this->info('Nothing to clean — no renewals found after an un-cleared cancel.');
            return Command::SUCCESS;
        }

        $this->info(sprintf('%sScanning %d cancelled policy(ies)…', $tag, count($policyIds)));

        $removed = 0;
        $skipped = 0;
        $invoices = 0;

        foreach ($policyIds as $policyId) {
            $report = $service->cleanupPolicy((int) $policyId, $apply);

            if (empty($report['removed']) && empty($report['skipped'])) {
                continue;
            }

            $this->line(sprintf('Policy %d (cancel action id=%s):', $policyId, $report['cancel_action_id']));

            foreach ($report['removed'] as $row) {
                $this->line(sprintf(
                    '  %s action id=%d (%s %s %s→%s).',
                    $apply ? 'Removed' : 'Would remove',
                    $row['action_id'], $row['transaction_type'], $row['status'],
                    $row['effective_from'], $row['effective_to']
                ));
                $removed++;
            }
            foreach ($report['skipped'] as $row) {
                $this->warn(sprintf(
                    '  SKIP action id=%d (%s %s) — %s',
                    $row['action_id'], $row['transaction_type'], $row['status'], $row['reason']
                ));
                $skipped++;
            }

            $invoices += $report['invoices_discarded'];
        }

        $this->newLine();
        $this->info(sprintf('%s%d renewal action(s).', $apply ? 'Removed ' : 'Would remove ', $removed));
        if ($apply) {
            $this->info("Discarded {$invoices} invoice ledger row(s).");
            Log::info('policy:cleanup-after-cancel finished', [
                'removed' => $removed, 'skipped' => $skipped, 'invoices' => $invoices,
            ]);
        }
        if ($skipped > 0) {
            $this->warn(sprintf('Skipped %d action(s) — payment/credit on the invoice, or an error. Reverse those invoices manually.', $skipped));
        }
        if (!$apply) {
            $this->newLine();
            $this->comment('Preview only. Re-run with --apply to perform the cleanup.');
        }

        return Command::SUCCESS;
    }

    /**
     * Policies in scope that carry at least one live renewal after their latest
     * un-cleared ISSUED CANCEL. Pre-filtered in SQL so the nightly run touches
     * only the handful of policies that actually need work — the service then
     * re-derives every rule per policy (this query is a shortlist, not the
     * authority).
     */
    private function candidatePolicyIds($policyRef): array
    {
        $products = implode(',', PostCancelCleanupService::products());
        $types    = "'" . implode("','", PostCancelCleanupService::REMOVABLE_TYPES) . "'";
        $clearing = "'" . implode("','", PostCancelCleanupService::CANCEL_CLEARING_TYPES) . "'";

        $sql = <<<SQL
SELECT DISTINCT p.id AS policy_id
FROM policies p
JOIN (
    SELECT policy_id, MAX(id) AS cancel_id
    FROM policy_actions
    WHERE status = 'ISSUED' AND transaction_type = 'CANCEL' AND deleted_at IS NULL
    GROUP BY policy_id
) c ON c.policy_id = p.id
JOIN policy_actions cx ON cx.id = c.cancel_id
JOIN policy_actions r
       ON r.policy_id = p.id
      AND r.transaction_type IN ({$types})
      AND r.deleted_at IS NULL
      AND r.id <> c.cancel_id
      AND (r.id > c.cancel_id OR r.effective_from > cx.effective_from)
WHERE p.product_id IN ({$products})
  AND NOT EXISTS (
      SELECT 1 FROM policy_actions pr
      WHERE pr.policy_id = p.id
        AND pr.status = 'ISSUED'
        AND pr.transaction_type IN ({$clearing})
        AND pr.deleted_at IS NULL
        AND pr.id > c.cancel_id
  )
SQL;

        $bindings = [];
        if ($policyRef !== null && $policyRef !== '') {
            $sql .= " AND (p.id = ? OR p.policyNumber = ?)";
            $bindings = [$policyRef, $policyRef];
        }

        $sql .= ' ORDER BY p.id';

        return array_map(
            static fn($row) => (int) $row->policy_id,
            DB::select($sql, $bindings)
        );
    }
}
