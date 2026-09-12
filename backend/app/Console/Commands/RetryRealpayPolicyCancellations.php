<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Policy;
use AlphaDirect\Services\RealPayPolicyCancellationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Re-drive RealPay contract cancellations that failed when the policy was
 * cancelled.
 *
 * RealPayPolicyCancellationService cancels the debit order inline with the
 * policy cancellation, but the call it makes is an external one: RealPay can be
 * down, the OAuth can fail, the DELETE can be rejected. When that happens the
 * service deliberately does NOT block the policy cancellation — it logs under
 * '[REALPAY POLICY CANCEL]' and leaves realpay_cancel_requests.cancel_status = 2.
 * This command is the other half of that promise: something has to come back
 * and finish the job, or "logged for follow-up" just means "lost".
 *
 * WHY NOT realpay:stop-dead-mandates
 *   That command is the bulk backlog sweep, and its Rule B carve-out ring-fences
 *   any contract that collected within --quiet-days (default 90) — protection
 *   against killing a paying customer's cover during a mass sweep. A policy an
 *   agent cancelled this morning has almost certainly collected inside that
 *   window, so the sweep will never touch it. The two are complements: this
 *   command finishes cancellations that were explicitly requested and failed;
 *   that one finds mandates nobody ever asked to cancel.
 *
 * POPULATION — a cancelled policy that still looks collectable, by any of:
 *   - a realpay_cancel_requests row at cancel_status 2 (failed) or 0 (queued
 *     for `cancelrealpaycontract:cron`, which is commented out in Kernel and
 *     has never drained), or
 *   - a realpay_client_contracts row still at status 1, or
 *   - a realpay_contract_installments row still at InstalmentStatus 'A'.
 *
 * The service is the judge, not this command: for each policy it re-reads the
 * portal, and a contract RealPay already reports as dead is reconciled locally
 * rather than cancelled again. So a run over a policy that has since been fixed
 * by hand is a no-op that also cleans up its stale rows.
 *
 * SAFETY MODEL — same as the other RealPay commands
 *   - report-only by default; --apply required before any RealPay call
 *   - --limit caps policies per run (default 100); the backlog drains across
 *     runs and a cancelled contract leaves the population, so re-running is
 *     idempotent and failures self-retry
 *   - --policy targets a single policy id or policyNumber for a one-off fix
 *   - CSV report of every decision under storage/app/realpay-cancel-retry/
 *
 * USAGE
 *   php artisan realpay:retry-policy-cancellations                  (report-only)
 *   php artisan realpay:retry-policy-cancellations --apply
 *   php artisan realpay:retry-policy-cancellations --policy=MIS2021012345 --apply
 */
class RetryRealpayPolicyCancellations extends Command
{
    protected $signature = 'realpay:retry-policy-cancellations
        {--policy= : single policy id or policyNumber}
        {--days=0 : only policies cancelled within this many days (0 = no age limit)}
        {--limit=100 : max policies per run (0 = no limit)}
        {--requested-only : only policies with a failed/queued cancel request — the safe set for an unattended run}
        {--apply : actually cancel on RealPay. Omit for a report-only run.}
        {--report= : CSV output path (default storage/app/realpay-cancel-retry/<ts>.csv)}
        {--no-report : suppress the CSV}';

    protected $description = 'Retry RealPay contract cancellations that failed when their policy was cancelled (report-only by default).';

    /** @var resource|null */
    private $fh = null;

    private int $cleared = 0;
    private int $stillFailing = 0;
    private int $skipped = 0;

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $limit = (int) $this->option('limit');

        $policies = $this->population();

        if ($limit > 0) {
            $policies = array_slice($policies, 0, $limit);
        }

        $mode = $apply ? 'APPLY (CANCELLING ON REALPAY)' : 'REPORT-ONLY (no RealPay calls)';
        $this->info('RealPay policy-cancellation retry — ' . $mode);
        $this->line('  candidates : ' . count($policies));
        $this->line('');

        if ($policies === []) {
            $this->info('Nothing outstanding.');
            return 0;
        }

        if (! $this->openReport()) {
            return 1;
        }

        $service = app(RealPayPolicyCancellationService::class);

        foreach ($policies as $row) {
            $policy = Policy::find($row->id);

            if (! $policy) {
                $this->skipped++;
                continue;
            }

            if (! $apply) {
                $this->skipped++;
                $this->line(sprintf('  %-20s would retry (%s)', $policy->policyNumber, $row->why));
                $this->writeRow($policy, 'WOULD-RETRY', $row->why, '');
                continue;
            }

            $result = $service->cancelForPolicy($policy, 'retry-command');

            if (! empty($result['ok'])) {
                $this->cleared++;
                $this->line(sprintf('  %-20s CLEARED — %s', $policy->policyNumber, $result['message']));
                $this->writeRow($policy, 'CLEARED', $row->why, $result['message']);
            } else {
                $this->stillFailing++;
                $this->warn(sprintf('  %-20s STILL FAILING — %s', $policy->policyNumber, $result['message']));
                $this->writeRow($policy, 'STILL-FAILING', $row->why, $result['message']);
            }

            // RealPay's gateway throttles hard; the sweep commands pace at the
            // same rate.
            usleep(250000);
        }

        $this->closeReport();

        $this->line('');
        $this->info(sprintf(
            'Done. cleared=%d still_failing=%d skipped=%d',
            $this->cleared,
            $this->stillFailing,
            $this->skipped
        ));

        Log::info(RealPayPolicyCancellationService::LOG . 'retry command finished', [
            'apply'         => $apply,
            'candidates'    => count($policies),
            'cleared'       => $this->cleared,
            'still_failing' => $this->stillFailing,
        ]);

        // Non-zero only when something is still debiting a cancelled customer,
        // so a scheduled run surfaces in monitoring rather than passing quietly.
        return $this->stillFailing > 0 ? 1 : 0;
    }

    /**
     * Cancelled policies that still look collectable on RealPay.
     *
     * Three independent signals, unioned — each one has been the only evidence
     * in at least one real case, and no single table can be trusted alone (the
     * status conventions on realpay_client_contracts disagree between writers).
     *
     * @return array<int, object{id: int, policyNumber: string, why: string}>
     */
    private function population(): array
    {
        $single = trim((string) $this->option('policy'));
        $days   = (int) $this->option('days');

        $base = function () use ($single, $days) {
            $q = DB::table('policies as p')->where('p.status', 2);

            if ($single !== '') {
                $q->where(function ($w) use ($single) {
                    $w->where('p.policyNumber', $single);
                    if (ctype_digit($single)) {
                        $w->orWhere('p.id', (int) $single);
                    }
                });
            } elseif ($days > 0) {
                $q->where('p.updated_at', '>=', Carbon::now()->subDays($days));
            }

            return $q;
        };

        $found = [];

        $add = function ($rows, string $why) use (&$found) {
            foreach ($rows as $row) {
                if (isset($found[$row->id])) {
                    $found[$row->id]->why .= '+' . $why;
                    continue;
                }

                $row->why      = $why;
                $found[$row->id] = $row;
            }
        };

        // 1. An explicit cancellation that failed (2) or was queued for the
        //    cron that never runs (0).
        $add(
            $base()
                ->join('realpay_cancel_requests as rcr', 'rcr.policy_id', '=', 'p.id')
                ->whereIn('rcr.cancel_status', [0, 2])
                ->select('p.id', 'p.policyNumber')
                ->distinct()
                ->get(),
            'failed-cancel-request'
        );

        // Signals 2 and 3 are evidence that a cancelled policy is still
        // collectable, NOT evidence that anyone asked for the contract to be
        // cancelled — so they carry the same ambiguity realpay:stop-dead-mandates
        // ring-fences with its Rule B carve-out: a policy cancelled in error
        // that is still paying. The scheduled run passes --requested-only and
        // stays out of that territory; an operator sweeping a known backlog can
        // include them deliberately.
        if ($this->option('requested-only')) {
            return array_values($found);
        }

        // 2. A contract our own records still call live.
        $add(
            $base()
                ->join('realpay_client_contracts as rcc', 'rcc.policy_id', '=', 'p.id')
                ->where('rcc.status', 1)
                ->select('p.id', 'p.policyNumber')
                ->distinct()
                ->get(),
            'active-contract-row'
        );

        // 3. An instalment still queued to take money.
        $add(
            $base()
                ->join('realpay_contract_installments as rci', 'rci.clientNumber', '=', 'p.policyNumber')
                ->where('rci.InstalmentStatus', 'A')
                ->select('p.id', 'p.policyNumber')
                ->distinct()
                ->get(),
            'active-instalment'
        );

        return array_values($found);
    }

    // ──────────────────────────────────────────────────────────────────
    // Report
    // ──────────────────────────────────────────────────────────────────

    private function openReport(): bool
    {
        if ($this->option('no-report')) {
            return true;
        }

        $path = (string) $this->option('report');

        if ($path === '') {
            $dir = storage_path('app/realpay-cancel-retry');
            if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                $this->error('Could not create the report directory: ' . $dir);
                return false;
            }
            $path = $dir . DIRECTORY_SEPARATOR . Carbon::now()->format('Ymd_His') . '.csv';
        }

        $this->fh = @fopen($path, 'w');

        if ($this->fh === false) {
            $this->fh = null;
            $this->error('Could not open the report file: ' . $path);
            return false;
        }

        fputcsv($this->fh, ['policy_id', 'policy_number', 'outcome', 'reason_selected', 'message']);
        $this->line('  report     : ' . $path);

        return true;
    }

    private function writeRow(Policy $policy, string $outcome, string $why, string $message): void
    {
        if ($this->fh === null) {
            return;
        }

        fputcsv($this->fh, [$policy->id, $policy->policyNumber, $outcome, $why, $message]);
    }

    private function closeReport(): void
    {
        if ($this->fh !== null) {
            fclose($this->fh);
            $this->fh = null;
        }
    }
}
