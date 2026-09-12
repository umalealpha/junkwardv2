<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Ledger;
use AlphaDirect\Policy;

/**
 * ONE-TIME cleanup (GRA-0132 backlog).
 *
 * Before the auto-renew cron learned to stop on a cancelled policy, DOM/COM
 * (product 7 / 8) policies that had an ISSUED CANCEL kept getting renewed every
 * cycle — the first bogus RENEW became the latest ISSUED action, hid the
 * CANCEL, and the policy renewed forever. Those stacked RENEW / ANNIVERSARY-RENEW
 * rows (and their invoices) are wrong and pollute the audit trail.
 *
 * This command finds every ISSUED RENEW / ANNIVERSARY-RENEW created (by id, i.e.
 * creation order) AFTER the latest ISSUED CANCEL on a DOM/COM policy where NO
 * REINSTATE / REISSUE was issued after that cancel (a reinstate legitimately
 * clears a cancel and is left alone), then for each one:
 *
 *   1. SKIPS it if its invoice has had payment / credit activity — that must go
 *      through "Reverse Invoice" first so an allocated payment is never orphaned
 *      (mirrors PolicyCreateController::deleteRenew).
 *   2. Soft-deletes its Invoice / Invoice VAT / Invoice Premium ledger rows
 *      (deleted_at only — invoice numbers stay in audit history).
 *   3. Soft-deletes the action row and its full replicated coverage tree
 *      (mirrors PolicyCreateController::softDeleteActionCascade).
 *
 * Everything is soft-delete, wrapped per-action in a transaction, and logged
 * via the activity log + Laravel log so the cleanup itself is fully auditable.
 *
 * SAFE BY DEFAULT: runs as a preview (dry-run). Pass --apply to actually write.
 *
 *   php artisan policy:cleanup-renew-after-cancel              # preview only
 *   php artisan policy:cleanup-renew-after-cancel --policy=99604
 *   php artisan policy:cleanup-renew-after-cancel --apply      # perform cleanup
 */
class CleanupRenewAfterCancel extends Command
{
    protected $signature = 'policy:cleanup-renew-after-cancel
                            {--apply : Actually perform the soft-deletes. Without this the command only previews.}
                            {--policy= : Limit to a single policy (id or policyNumber).}';

    protected $description = 'One-time GRA-0132 cleanup: soft-delete DOM/COM RENEW/ANNIVERSARY-RENEW actions (and discard their invoices) that were wrongly issued AFTER a cancel.';

    public function handle(): int
    {
        $apply    = (bool) $this->option('apply');
        $policyRef = $this->option('policy');
        $tag      = $apply ? '' : '[DRY RUN] ';

        $this->info("{$tag}Finding DOM/COM renewals issued after an un-cleared cancel…");

        $targets = $this->findTargetActions($policyRef);

        if (empty($targets)) {
            $this->info('Nothing to clean — no matching renewals found.');
            return Command::SUCCESS;
        }

        // Group target actions by policy for readable output / per-policy logging.
        $byPolicy = [];
        foreach ($targets as $row) {
            $byPolicy[$row->policy_id][] = $row;
        }

        $this->info(sprintf(
            "%sFound %d renewal action(s) across %d policy(ies).",
            $tag, count($targets), count($byPolicy)
        ));

        $deletedActions  = 0;
        $discardedInvoices = 0;
        $skippedPaid     = 0;
        $skippedPolicies = [];

        foreach ($byPolicy as $policyId => $rows) {
            $this->line(sprintf('Policy %d — %d renewal(s):', $policyId, count($rows)));

            foreach ($rows as $row) {
                $action = PolicyAction::find($row->action_id);
                if (!$action || $action->deleted_at !== null) {
                    continue; // already gone / soft-deleted since the scan
                }

                // 1️⃣ Payment-activity guard (audit-safe). Never discard an
                //    invoice with a payment/credit or a non-pending invoice on
                //    it — leave the whole action in place for manual reversal.
                if ($this->hasPaymentActivity($policyId, $action->id)) {
                    $skippedPaid++;
                    $skippedPolicies[$policyId] = true;
                    $this->warn(sprintf(
                        '  SKIP action id=%d (%s %s→%s) — invoice has payment/credit; reverse it manually first.',
                        $action->id, $action->transaction_type,
                        $action->effective_from, $action->effective_to
                    ));
                    continue;
                }

                if (!$apply) {
                    $this->line(sprintf(
                        '  [DRY RUN] Would delete action id=%d (%s %s→%s) + discard its invoice.',
                        $action->id, $action->transaction_type,
                        $action->effective_from, $action->effective_to
                    ));
                    $deletedActions++;
                    continue;
                }

                try {
                    DB::transaction(function () use ($policyId, $action, &$discardedInvoices) {
                        // 2️⃣ Discard invoice rows (soft-delete only).
                        $n = Ledger::where('policy_id', $policyId)
                            ->where('action_id', $action->id)
                            ->whereIn('trans_type', ['Invoice', 'Invoice VAT', 'Invoice Premium'])
                            ->whereNull('deleted_at')
                            ->update(['deleted_at' => now()]);
                        $discardedInvoices += $n;

                        // 3️⃣ Soft-delete the action + its coverage tree.
                        $this->softDeleteActionCascade($action);
                    });

                    activity('GRA-0132 Cleanup')
                        ->performedOn(Policy::find($policyId))
                        ->causedBy(auth()->user())
                        ->log("One-time cleanup: soft-deleted {$action->transaction_type} action id={$action->id} (issued after cancel) and discarded its invoice");

                    Log::info("CleanupRenewAfterCancel: deleted action {$action->id} (policy {$policyId})");

                    $this->line(sprintf(
                        '  Deleted action id=%d (%s %s→%s) + invoice.',
                        $action->id, $action->transaction_type,
                        $action->effective_from, $action->effective_to
                    ));
                    $deletedActions++;
                } catch (\Throwable $e) {
                    Log::error('CleanupRenewAfterCancel failed', [
                        'policy_id' => $policyId, 'action_id' => $action->id,
                        'error' => $e->getMessage(),
                    ]);
                    $this->error(sprintf('  FAILED action id=%d — %s', $action->id, $e->getMessage()));
                }
            }
        }

        $this->newLine();
        $verb = $apply ? 'Deleted' : 'Would delete';
        $this->info("{$verb} {$deletedActions} renewal action(s).");
        if ($apply) {
            $this->info("Discarded {$discardedInvoices} invoice ledger row(s).");
        }
        if ($skippedPaid > 0) {
            $this->warn(sprintf(
                'Skipped %d action(s) across %d policy(ies) with payment/credit on the invoice — reverse those manually.',
                $skippedPaid, count($skippedPolicies)
            ));
        }
        if (!$apply) {
            $this->newLine();
            $this->comment('Preview only. Re-run with --apply to perform the cleanup.');
        }

        return Command::SUCCESS;
    }

    /**
     * The GRA-0132 target set: ISSUED RENEW / ANNIVERSARY-RENEW rows created
     * (by id) after the latest ISSUED CANCEL on a DOM/COM policy that has NOT
     * been reinstated/reissued since that cancel. Returns one row per renewal
     * action. Mirrors the auto-renew cron's cancel-detection logic exactly.
     */
    private function findTargetActions($policyRef): array
    {
        $sql = <<<SQL
SELECT r.id            AS action_id,
       r.policy_id     AS policy_id,
       r.transaction_type,
       r.status,
       r.effective_from,
       r.effective_to
FROM policies p
JOIN (
    SELECT policy_id, MAX(id) AS cancel_id
    FROM policy_actions
    WHERE status = 'ISSUED' AND transaction_type = 'CANCEL' AND deleted_at IS NULL
    GROUP BY policy_id
) c ON c.policy_id = p.id
JOIN policy_actions r
       ON r.policy_id = p.id
      AND r.status = 'ISSUED'
      AND r.transaction_type IN ('RENEW','ANNIVERSARY-RENEW')
      AND r.deleted_at IS NULL
      AND r.id > c.cancel_id
WHERE p.product_id IN (7,8)
  AND NOT EXISTS (
      SELECT 1 FROM policy_actions pr
      WHERE pr.policy_id = p.id
        AND pr.status = 'ISSUED'
        AND pr.transaction_type IN ('REINSTATE','REISSUE')
        AND pr.deleted_at IS NULL
        AND pr.id > c.cancel_id
  )
SQL;

        $bindings = [];
        if ($policyRef !== null && $policyRef !== '') {
            $sql .= " AND (p.id = ? OR p.policyNumber = ?)";
            $bindings = [$policyRef, $policyRef];
        }

        $sql .= " ORDER BY r.policy_id, r.id";

        return DB::select($sql, $bindings);
    }

    /**
     * Never discard an invoice that has had payment / credit activity, or whose
     * invoice row is no longer Pending. Verbatim copy of the guard in
     * PolicyCreateController::deleteRenew so this command can never orphan an
     * allocated payment.
     */
    private function hasPaymentActivity(int $policyId, int $actionId): bool
    {
        return Ledger::where('policy_id', $policyId)
                ->where('action_id', $actionId)
                ->whereIn('trans_type', ['Payment', 'Refund', 'Credit Note'])
                ->whereNull('deleted_at')
                ->exists()
            || Ledger::where('policy_id', $policyId)
                ->where('action_id', $actionId)
                ->where('trans_type', 'Invoice')
                ->whereNull('deleted_at')
                ->where('status', '!=', 'Pending')
                ->exists();
    }

    /**
     * Soft-delete an action row and every action-versioned child row. Mirrors
     * PolicyCreateController::softDeleteActionCascade (kept self-contained here
     * so this one-time command doesn't reach into a controller). Soft-delete
     * where the table has deleted_at; hard-delete the few that don't.
     */
    private function softDeleteActionCascade(PolicyAction $action): void
    {
        $aid   = $action->id;
        $stamp = ['deleted_at' => now(), 'updated_at' => now()];
        $pcIds = DB::table('policy_coverages')->where('action_id', $aid)->pluck('id');

        $softOrHard = function (string $table, string $col, $val) use ($stamp) {
            if (!\Schema::hasTable($table)) return;
            if (\Schema::hasColumn($table, 'deleted_at')) {
                DB::table($table)->where($col, $val)->update($stamp);
            } else {
                DB::table($table)->where($col, $val)->delete();
            }
        };
        $softOrHardIn = function (string $table, string $col, $values) use ($stamp) {
            if (!\Schema::hasTable($table) || count($values) === 0) return;
            if (\Schema::hasColumn($table, 'deleted_at')) {
                DB::table($table)->whereIn($col, $values)->update($stamp);
            } else {
                DB::table($table)->whereIn($col, $values)->delete();
            }
        };

        if ($pcIds->isNotEmpty()) {
            $ids = $pcIds->all();
            $softOrHardIn('policy_coverage_detail',   'policy_coverage_id', $ids);
            $softOrHardIn('policy_extention_detail',  'policy_coverage_id', $ids);
            $softOrHardIn('policy_specified_items',   'policy_coverage_id', $ids);
            $softOrHardIn('motor',                    'policy_coverage_id', $ids);
            $softOrHardIn('motor_traders',            'policy_coverage_id', $ids);
            $softOrHardIn('motor_traders_internal',   'policy_coverage_id', $ids);
            $softOrHardIn('policy_coverage_notes',    'policy_coverage_id', $ids);
            $softOrHardIn('policy_coverage_entities', 'policy_coverage_id', $ids);
            $softOrHardIn('policy_coverages_data',    'policyCoverageID',   $ids);
            foreach (\AlphaDirect\Services\SpecialistEndorse\SpecialistCoverageRegistry::tableNames() as $t) {
                $softOrHardIn($t, 'policy_coverage_id', $ids);
            }
        }

        $softOrHard('policy_coverages',   'action_id', $aid);
        $softOrHard('risk_address',       'action_id', $aid);
        $softOrHard('vehicle',            'action_id', $aid);
        $softOrHard('policy_beneficiary', 'action_id', $aid);
        $softOrHard('policy_cellphone',   'action_id', $aid);

        if (\Schema::hasColumn('policy_actions', 'deleted_at')) {
            $action->update(['deleted_at' => now()]);
        } else {
            $action->delete();
        }
    }
}
