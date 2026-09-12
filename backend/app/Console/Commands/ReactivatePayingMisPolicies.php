<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Ledger;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ONE-OFF: reactivate deactivated MIS policies that are still PAYING.
 *
 * CFO instruction 10 Aug 2026 ("RealPay on cancelled MIS policies - permanent
 * fix + immediate actions"): policies on the attached list collect via RealPay
 * every month but sit deactivated in Graphite — the cover must be restored.
 *
 * This is the mirror image of mis:deactivate-nonpaying and reuses its safety
 * model, plus the silent-write pattern from ReactivateLegalPolicies:
 *
 *   - dry-run by default; --apply required to write
 *   - every policy on the input file is re-tested against LIVE data at run
 *     time — a policy is only reactivated if it is currently status 0 AND has
 *     a qualifying payment in the window (paygate or ledger)
 *   - status 2 (cancelled) is NEVER touched — reported as HELD for a human
 *     decision; cancellations can carry endorsements/refunds
 *   - the status write uses the query builder so PolicyObserver::updated()
 *     does NOT fire — 1,900+ customers must not receive a "policy activated"
 *     SMS for a silent data correction. The observer's caches are cleared by
 *     hand and the change is written to the activity log instead.
 *   - every run writes a per-policy CSV report + a summary to the Laravel log
 *
 * USAGE
 *   php artisan mis:reactivate-paying --file=/tmp/activate_2197.csv
 *   php artisan mis:reactivate-paying --file=/tmp/activate_2197.csv --apply
 */
class ReactivatePayingMisPolicies extends Command
{
    protected $signature = 'mis:reactivate-paying
        {--file= : path to a CSV/TXT of policy numbers (the verified list)}
        {--policy= : single policy id or policyNumber — policy-wise run}
        {--from=2026-04-01 : payment test window start (inclusive)}
        {--to= : payment test window end (inclusive; default today)}
        {--min-amount=1 : a payment only counts ABOVE this amount (P1)}
        {--wide-success : also treat RealPay "S" as a success status}
        {--skip-ledger : test paygate only (ONLY if policy_ledger is unreachable)}
        {--apply : actually write status=1. Omit for a dry run.}
        {--report= : CSV output path (default storage/app/mis-reactivation/<ts>.csv)}';

    protected $description = 'ONE-OFF: reactivate deactivated MIS policies with verified payments (dry-run by default, silent write).';

    private string $from;
    private string $to;
    private float $minAmount;
    private bool $apply;
    private bool $skipLedger;

    /** Success casings — mirrors mis:deactivate-nonpaying. */
    private array $successStatuses = ['Success', 'SUCCESS', 'success', '1'];

    /** policyNumber => true, paygate money in the window. */
    private array $paidPaygate = [];
    /** policy_id => true, ledger receipt in the window. */
    private array $paidLedger = [];

    private bool $ledgerReadable = true;

    /** @var resource|null */
    private $fh = null;

    private array $tally = [];

    public function handle()
    {
        $this->from       = Carbon::parse($this->option('from'))->format('Y-m-d');
        $this->to         = Carbon::parse($this->option('to') ?: Carbon::now())->format('Y-m-d');
        $this->minAmount  = (float) $this->option('min-amount');
        $this->apply      = (bool) $this->option('apply');
        $this->skipLedger = (bool) $this->option('skip-ledger');

        if ($this->option('wide-success')) {
            $this->successStatuses[] = 'S';
        }

        if (! $this->option('file') && ! $this->option('policy')) {
            $this->error('Provide --file=<list> or --policy=<one policy>. Refusing a blanket run.');
            return 1;
        }

        if (! $this->openReport()) {
            return 1;
        }

        $mode = $this->apply ? 'APPLY (WRITING status=1)' : 'DRY RUN (no writes)';
        $this->info("MIS reactivation — {$mode}");
        $this->line("  window     : {$this->from} .. {$this->to}");
        $this->line('  qualifying : payment > ' . $this->minAmount . ' in paygate'
            . ($this->skipLedger ? '  [LEDGER TEST DISABLED]' : ' or ledger'));
        $this->line('  success    : ' . implode(', ', $this->successStatuses));
        $this->line('');

        Log::info('mis:reactivate-paying started', [
            'mode' => $this->apply ? 'apply' : 'dry-run',
            'from' => $this->from, 'to' => $this->to,
            'min_amount' => $this->minAmount,
            'skip_ledger' => $this->skipLedger,
            'success_statuses' => $this->successStatuses,
        ]);

        if ($ref = $this->option('policy')) {
            $numbers = null;
            $policies = Policy::where('id', $ref)->orWhere('policyNumber', $ref)->get();
            if ($policies->isEmpty()) {
                $this->error("Policy not found: {$ref}");
                $this->closeReport();
                return 1;
            }
        } else {
            $numbers = $this->readList($this->option('file'));
            if ($numbers === []) {
                $this->error('No policy numbers read from --file.');
                $this->closeReport();
                return 1;
            }
        }

        $this->loadPaymentSets($numbers);

        if (! $this->skipLedger && ! $this->ledgerReadable) {
            $this->error('policy_ledger could not be read — refusing to run.');
            $this->error('Pass --skip-ledger only if you accept a paygate-only test.');
            $this->closeReport();
            return 1;
        }

        $processed = 0;

        if ($ref) {
            foreach ($policies as $policy) {
                $this->processOne($policy);
                $processed++;
            }
        } else {
            $found = [];
            foreach (array_chunk($numbers, 1000) as $chunk) {
                foreach (Policy::whereIn('policyNumber', $chunk)->get() as $policy) {
                    $found[$policy->policyNumber] = true;
                    $this->processOne($policy);
                    $processed++;
                }
                $this->line("  ... {$processed} processed");
            }
            foreach ($numbers as $num) {
                if (! isset($found[$num])) {
                    $this->tally['NOT-FOUND'] = ($this->tally['NOT-FOUND'] ?? 0) + 1;
                    if ($this->fh) {
                        fputcsv($this->fh, ['', $num, '', '', '', 'NOT-FOUND', 'no such policyNumber', Carbon::now()->toDateTimeString()]);
                    }
                }
            }
        }

        $this->closeReport();
        $this->renderTally($processed);

        return 0;
    }

    // ------------------------------------------------------- payment pre-load

    private function loadPaymentSets(?array $numbers): void
    {
        $this->line('Loading payment sets...');

        $pg = PaymentTransaction::query()
            ->whereIn('status', $this->successStatuses)
            ->where(function ($q) {
                $q->whereNull('is_reverse')->orWhere('is_reverse', 0);
            })
            ->where(function ($q) {
                $q->whereNull('is_refund')->orWhere('is_refund', 0);
            })
            ->whereBetween('new_payment_date', [$this->from, $this->to])
            ->whereRaw("CAST(REPLACE(COALESCE(amount,'0'), ',', '.') AS DECIMAL(15,2)) > ?", [$this->minAmount]);

        if ($numbers !== null) {
            $pg->whereIn('policyNumber', $numbers);
        } else {
            $pg->where('policyNumber', 'like', 'MIS%');
        }

        $this->paidPaygate = array_flip($pg->distinct()->pluck('policyNumber')->all());
        $this->line('  paygate payers in window : ' . count($this->paidPaygate));

        if ($this->skipLedger) {
            return;
        }

        try {
            $this->paidLedger = array_flip(
                Ledger::query()
                    ->where('trans_type', 'Payment')
                    ->whereNull('deleted_at')
                    ->where(function ($q) {
                        $q->whereNull('status')->orWhere('status', '<>', 'Reversed');
                    })
                    ->where('credit', '>', $this->minAmount)
                    ->whereBetween('accounting_date', [$this->from, $this->to])
                    ->distinct()->pluck('policy_id')->all()
            );
            $this->line('  ledger payers in window  : ' . count($this->paidLedger));
        } catch (\Throwable $e) {
            $this->ledgerReadable = false;
            Log::error('mis:reactivate-paying ledger set unreadable', ['error' => $e->getMessage()]);
            $this->error('  ledger read FAILED: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------ per policy

    private function processOne(Policy $policy): void
    {
        $reason = $this->assess($policy);

        if ($reason !== null) {
            [$outcome, $why] = $reason;
            $this->record($policy, $outcome, $why);
            return;
        }

        if (! $this->apply) {
            $this->record($policy, 'WOULD-REACTIVATE', 'deactivated with verified payment in window');
            return;
        }

        $oldStatus = (int) $policy->status;

        try {
            DB::transaction(function () use ($policy, $oldStatus) {
                // Query-builder update → bypasses PolicyObserver, so no
                // "policy activated" notification goes to the customer for a
                // silent data correction. Only the status flag changes;
                // schedules and RealPay/DPO contracts are left untouched —
                // their mandates are already live (that is the whole point).
                DB::table('policies')
                    ->where('id', $policy->id)
                    ->update([
                        'status'     => 1,
                        'updated_at' => Carbon::now(),
                    ]);

                // Clear the caches PolicyObserver::saved() would have cleared.
                Cache::forget("policy_{$policy->id}");
                Cache::forget("policy_number_{$policy->policyNumber}");
                Cache::forget('dashboard_policy_counts');

                // Audit trail (system action, no causer).
                activity('Policy')
                    ->performedOn($policy)
                    ->log("Reactivated (status {$oldStatus} → 1): paying customer wrongly deactivated — CFO list 10 Aug 2026 (RealPay collecting on deactivated MIS)");
            });

            Log::info('mis:reactivate-paying reactivated', [
                'policy_id'    => $policy->id,
                'policyNumber' => $policy->policyNumber,
                'old_status'   => $oldStatus,
                'window'       => "{$this->from}..{$this->to}",
            ]);

            $this->record($policy, 'REACTIVATED', "status {$oldStatus} -> 1");
        } catch (\Throwable $e) {
            Log::error('mis:reactivate-paying FAILED', [
                'policy_id'    => $policy->id,
                'policyNumber' => $policy->policyNumber,
                'error'        => $e->getMessage(),
            ]);
            $this->record($policy, 'ERROR', $e->getMessage());
        }
    }

    /**
     * Every reason NOT to reactivate. null = safe to switch on.
     *
     * @return array{0:string,1:string}|null [outcome, reason] or null
     */
    private function assess(Policy $policy): ?array
    {
        if (strpos((string) $policy->policyNumber, 'MIS') !== 0) {
            return ['SKIPPED', 'not an MIS policy'];
        }

        $status = (int) $policy->status;

        if ($status === 1) {
            return ['SKIPPED', 'already active'];
        }

        if ($status === 2) {
            // Cancellations can carry endorsements, notices and refund
            // workflow — restoring one is an underwriting decision, not a
            // data correction.
            return ['HELD', 'CANCELLED (status=2) — needs a human decision, not touched'];
        }

        if ($status !== 0) {
            return ['SKIPPED', 'unexpected status=' . var_export($policy->status, true)];
        }

        $paid = isset($this->paidPaygate[$policy->policyNumber])
            || (! $this->skipLedger && isset($this->paidLedger[$policy->id]));

        if (! $paid) {
            // On the list but no money visible in the window — the list may be
            // stale. Unknown != paying; fail safe, leave it deactivated.
            return ['SKIPPED', 'no qualifying payment in window — left deactivated'];
        }

        return null;
    }

    // ------------------------------------------------------------------ io

    private function readList(string $path): array
    {
        if (! is_file($path)) {
            $this->error("File not found: {$path}");
            return [];
        }

        $out = [];
        $fh = fopen($path, 'r');
        while (($row = fgetcsv($fh)) !== false) {
            foreach ($row as $cell) {
                $cell = trim((string) $cell);
                if (strpos($cell, 'MIS') === 0) {
                    $out[$cell] = true;   // key = dedupe
                }
            }
        }
        fclose($fh);

        $out = array_keys($out);
        $this->info('Read ' . count($out) . " policy numbers from {$path}");
        return $out;
    }

    private function openReport(): bool
    {
        $path = $this->option('report');
        if (! $path) {
            $dir = storage_path('app/mis-reactivation');
            if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                $this->error("Cannot create report directory: {$dir}");
                return false;
            }
            $path = $dir . '/mis-reactivation-'
                . ($this->apply ? 'apply' : 'dryrun') . '-'
                . Carbon::now()->format('Ymd-His') . '.csv';
        }

        $this->fh = @fopen($path, 'w');
        if (! $this->fh) {
            $this->error("Cannot open report path: {$path}");
            return false;
        }

        fputcsv($this->fh, [
            'policy_id', 'policyNumber', 'status_before', 'premium', 'active_since',
            'outcome', 'reason', 'run_at',
        ]);
        $this->info("Report: {$path}");
        return true;
    }

    private function record(Policy $policy, string $outcome, string $reason): void
    {
        $this->tally[$outcome] = ($this->tally[$outcome] ?? 0) + 1;

        if ($this->fh) {
            fputcsv($this->fh, [
                $policy->id,
                $policy->policyNumber,
                $policy->getOriginal('status'),
                $policy->premium,
                $policy->policyActivatedDate
                    ? Carbon::parse($policy->policyActivatedDate)->format('Y-m-d')
                    : '',
                $outcome,
                $reason,
                Carbon::now()->toDateTimeString(),
            ]);
        }

        if ($this->option('policy')) {
            $this->line("  {$policy->policyNumber}  {$outcome}  — {$reason}");
        }
    }

    private function closeReport(): void
    {
        if ($this->fh) {
            fclose($this->fh);
            $this->fh = null;
        }
    }

    private function renderTally(int $processed): void
    {
        $this->line('');
        $this->info("Processed: {$processed}");
        foreach ($this->tally as $outcome => $count) {
            $this->line(sprintf('  %-18s %6d', $outcome, $count));
        }

        Log::info('mis:reactivate-paying finished', [
            'mode'      => $this->apply ? 'apply' : 'dry-run',
            'processed' => $processed,
            'tally'     => $this->tally,
        ]);

        if (! $this->apply) {
            $this->line('');
            $this->warn('DRY RUN — nothing was written. Re-run with --apply to commit.');
        }
    }
}
