<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Services\BackdatedEndorse\BackdatedEndorseRefresher;
use Illuminate\Console\Command;

/**
 * Preview (dry-run) the forward propagation an ISSUED backdated ENDORSE would
 * apply to its downstream RENEW / ENDORSE / ANNIVERSARY-RENEW batches — the
 * target actions, per-batch premium deltas and the planned ledger adjustments —
 * WITHOUT writing anything. Read-only, so it is safe to run against any
 * environment (it only SELECTs).
 *
 * Usage:
 *   php artisan endorse:dry-run-refresh 35195
 *   php artisan endorse:dry-run-refresh 35195 --json   (full machine-readable report)
 */
class EndorseRefreshDryRunCommand extends Command
{
    protected $signature = 'endorse:dry-run-refresh
                            {action : policy_actions.id of the ISSUED ENDORSE to preview}
                            {--json : Output the full RefreshReport as JSON}';

    protected $description = 'Dry-run BackdatedEndorseRefresher for an ENDORSE action (read-only preview; no DB writes).';

    public function handle(): int
    {
        $actionId = (int) $this->argument('action');
        $action = PolicyAction::where('id', $actionId)->first();
        if (!$action) {
            $this->error("PolicyAction {$actionId} not found.");
            return self::FAILURE;
        }

        $this->info(
            "Dry-run BackdatedEndorseRefresher for action {$actionId} "
            . "({$action->transaction_type}/{$action->status}, policy {$action->policy_id}) — NO WRITES."
        );

        $report = (new BackdatedEndorseRefresher())->dryRun($action);

        if ($this->option('json')) {
            $this->line(json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $this->table(['Metric', 'Value'], [
            ['source_action_id', $report->sourceActionId],
            ['target_actions_processed', $report->targetActionsProcessed],
            ['endorses_updated', $report->endorsesUpdated],
            ['renews_updated', $report->renewsUpdated],
            ['fields_updated', $report->fieldsUpdated],
            ['fields_skipped_protected', $report->fieldsSkippedProtected],
            ['cancelled_rows_skipped', $report->cancelledRowsSkipped],
            ['total_premium_delta (abs sum)', $report->totalPremiumDelta],
            ['errors', count($report->errors)],
        ]);

        if (!empty($report->errors)) {
            $this->warn('Errors:');
            foreach ($report->errors as $e) {
                $this->line(' - ' . ($e['message'] ?? ''));
            }
        }

        $this->info('Decisions: ' . count($report->decisions) . ' (run with --json for full per-row detail).');

        return self::SUCCESS;
    }
}
