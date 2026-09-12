<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Claim;
use AlphaDirect\Ledger;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * ONE-OFF: deactivate non-paying MIS (Instant Insurance) policies.
 *
 * Sets `policies.status = 0` — the system's existing DEACTIVATED state, the
 * same one DeactivePolicyPaymentDone already watches. This is NOT a
 * cancellation: no CANCEL action, no endorsement, no notice, no refund
 * workflow, and the policy/coverage tree is untouched.
 *
 * WHY status = 0 STOPS INVOICING
 *   Both invoice writers gate on `status = 1`:
 *     - createInvoiceAutoRenewPolicyMonthly::handle()   (`invoiceMonthly:domcom`)
 *     - DomComMonthlyAutoRenew::generateInvoice()       (~line 716)
 *   Once status is 0 neither raises another Invoice / Invoice VAT /
 *   Accounts Receivable row.
 *
 * KNOWN GAP (deliberately NOT changed here)
 *   DomComMonthlyAutoRenew's population query has no `p.status` filter, so a
 *   deactivated policy still mints monthly RENEW policy_actions with no
 *   invoice behind them. Fixing that means editing the renewal cron — out of
 *   scope for this one-off. Raise separately.
 *
 * SELECTION — mirrors the signed-off SQL exactly
 *   Population : policyNumber LIKE 'MIS%' AND status = 1
 *                (no deleted_at filter — `policies` and `claims` have no such
 *                 column; that is why the reference query commented it out)
 *   Non-paying : no qualifying payment 2026-04-01..2026-07-31 in EITHER
 *                the paygate feed or the accounting ledger.
 *   Qualifying : paygate -> UPPER(status) IN ('SUCCESS','1'),
 *                           COALESCE(is_reverse,0)=0, COALESCE(is_refund,0)=0,
 *                           CAST(REPLACE(amount,',','.')) > P1
 *                ledger  -> trans_type 'Payment',
 *                           COALESCE(status,'') <> 'Reversed',
 *                           COALESCE(credit,0) > P1
 *
 * PERFORMANCE
 *   The two "who paid in the window" sets are loaded ONCE into memory (two
 *   narrow, date-bounded queries) and every policy is then tested in PHP.
 *   That is 4 queries for the whole run instead of two per policy — the
 *   difference between ~66,000 lookups and 4 across 33k policies. Claims are
 *   pre-loaded the same way.
 *
 * USAGE — dry-run is the default; --apply is required to write.
 *   Policy-wise first:
 *     php artisan mis:deactivate-nonpaying --policy=MIS2020000243
 *     php artisan mis:deactivate-nonpaying --policy=MIS2020000243 --apply
 *   Bulk, safest cohort first:
 *     php artisan mis:deactivate-nonpaying --batch=never-paid --limit=100 --apply
 *   Bulk from the audit export:
 *     php artisan mis:deactivate-nonpaying --file=storage/app/mis_33070.csv --apply
 *
 * Every run writes a CSV of exactly what it did (or would do) to
 * storage/app/mis-deactivation/ and mirrors a summary into the Laravel log.
 * Policies are saved through Eloquent, so the OwenIt audit trail records the
 * 1 -> 0 transition per policy.
 */
class DeactivateNonPayingMisPolicies extends Command
{
    protected $signature = 'mis:deactivate-nonpaying
        {--policy= : single policy id or policyNumber — the policy-wise run}
        {--file= : path to a CSV/TXT of policy numbers (the audit export)}
        {--exclude= : path to a CSV/TXT of policy numbers to NEVER touch}
        {--batch=all : all|never-paid|dormant — which cohort to pull}
        {--from=2026-04-01 : payment test window start (inclusive)}
        {--to=2026-07-31 : payment test window end (inclusive)}
        {--min-amount=1 : a payment only counts ABOVE this amount (P1)}
        {--dormant-before=2024-01-01 : --batch=dormant means last paid before this}
        {--limit=0 : stop after this many policies (0 = no limit)}
        {--chunk=1000 : policies loaded per batch}
        {--strict-reversals : additionally exclude rows with reveral_transaction_id}
        {--wide-success : also treat RealPay "S" as a success status}
        {--ignore-claims : do NOT skip policies with a recent claim}
        {--check-balance : also skip policies in credit (slow; audit shows all zero)}
        {--skip-ledger : test paygate only (ONLY if policy_ledger is unreachable)}
        {--apply : actually write status=0. Omit for a dry run.}
        {--report= : CSV output path (default storage/app/mis-deactivation/<ts>.csv)}';

    protected $description = 'ONE-OFF: set status=0 on non-paying MIS policies (dry-run by default). Not a cancellation.';

    private string $from;
    private string $to;
    private float  $minAmount;
    private bool   $apply;
    private bool   $skipLedger;
    private bool   $ignoreClaims;
    private bool   $checkBalance;
    private bool   $strictReversals;

    /** Success casings. Default mirrors the signed-off SQL: UPPER(status) IN ('SUCCESS','1'). */
    private array $successStatuses = ['Success', 'SUCCESS', 'success', '1'];

    /** policyNumber => true, for everyone who took paygate money in the window. */
    private array $paidPaygate = [];
    /** policy_id => true, for everyone with a ledger receipt in the window. */
    private array $paidLedger = [];
    /** policy_id => true, recent claims. */
    private array $recentClaims = [];
    /** policyNumber => true, has a future-dated payment row (the PDF's corrupt records). */
    private array $futureDated = [];
    /** policyNumber => true, manual carve-out list (--exclude). Never deactivated. */
    private array $excluded = [];

    private bool $ledgerReadable = true;

    /** @var resource|null */
    private $fh = null;

    private array $tally = [];

    public function handle()
    {
        $this->from            = Carbon::parse($this->option('from'))->format('Y-m-d');
        $this->to              = Carbon::parse($this->option('to'))->format('Y-m-d');
        $this->minAmount       = (float) $this->option('min-amount');
        $this->apply           = (bool) $this->option('apply');
        $this->skipLedger      = (bool) $this->option('skip-ledger');
        $this->ignoreClaims    = (bool) $this->option('ignore-claims');
        $this->checkBalance    = (bool) $this->option('check-balance');
        $this->strictReversals = (bool) $this->option('strict-reversals');

        if ($this->option('wide-success')) {
            $this->successStatuses[] = 'S';
        }

        $limit = (int) $this->option('limit');
        $chunk = max(100, (int) $this->option('chunk'));

        if (! $this->openReport()) {
            return 1;
        }

        // Manual carve-out. Read BEFORE anything else so a bad path fails the
        // run rather than silently deactivating policies Finance ring-fenced.
        if ($exPath = $this->option('exclude')) {
            $ex = $this->readList($exPath);
            if ($ex === []) {
                $this->error("--exclude given but no policy numbers read from: {$exPath}");
                $this->closeReport();
                return 1;
            }
            $this->excluded = array_flip($ex);
            $this->info('Excluding ' . count($this->excluded) . ' policies (manual carve-out)');
        }

        $mode = $this->apply ? 'APPLY (WRITING status=0)' : 'DRY RUN (no writes)';
        $this->info("MIS deactivation — {$mode}");
        $this->line("  window     : {$this->from} .. {$this->to}");
        $this->line('  qualifying : payment > ' . $this->minAmount . ' in paygate'
            . ($this->skipLedger ? '  [LEDGER TEST DISABLED]' : ' or ledger'));
        $this->line('  success    : ' . implode(', ', $this->successStatuses));
        $this->line('');

        Log::info('mis:deactivate-nonpaying started', [
            'mode' => $this->apply ? 'apply' : 'dry-run',
            'from' => $this->from, 'to' => $this->to,
            'min_amount' => $this->minAmount,
            'skip_ledger' => $this->skipLedger,
            'strict_reversals' => $this->strictReversals,
            'success_statuses' => $this->successStatuses,
        ]);

        $this->loadPaymentSets();

        if (! $this->skipLedger && ! $this->ledgerReadable) {
            $this->error('policy_ledger could not be read — refusing to run.');
            $this->error('Pass --skip-ledger only if you accept a paygate-only test.');
            $this->closeReport();
            return 1;
        }

        $processed = 0;

        if ($ref = $this->option('policy')) {
            $policy = Policy::where('id', $ref)->orWhere('policyNumber', $ref)->first();
            if (! $policy) {
                $this->error("Policy not found: {$ref}");
                $this->closeReport();
                return 1;
            }
            $this->loadClaims([$policy->id]);
            $this->processOne($policy);
            $processed = 1;
        } else {
            $numbers = $this->option('file') ? $this->readList($this->option('file')) : null;
            if ($numbers !== null && $numbers === []) {
                $this->error('No policy numbers read from --file.');
                $this->closeReport();
                return 1;
            }

            $lastId = 0;
            while (true) {
                $rows = $this->baseQuery($numbers)
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->limit($chunk)
                    ->get();

                if ($rows->isEmpty()) {
                    break;
                }

                $this->loadClaims($rows->pluck('id')->all());

                foreach ($rows as $policy) {
                    $lastId = $policy->id;
                    $this->processOne($policy);
                    $processed++;

                    if ($limit > 0 && $processed >= $limit) {
                        break 2;
                    }
                }

                $this->line("  ... {$processed} processed");
            }
        }

        $this->closeReport();
        $this->renderTally($processed);

        return 0;
    }

    // ------------------------------------------------------- set pre-loads

    /**
     * Two narrow, date-bounded queries that answer "who took money in the
     * window" for the entire run. Also captures future-dated paygate rows so
     * the report can flag the PDF's corrupt records.
     */
    private function loadPaymentSets(): void
    {
        $this->line('Loading payment sets...');

        $pg = PaymentTransaction::query()
            ->where('policyNumber', 'like', 'MIS%')
            ->whereIn('status', $this->successStatuses)
            ->where(function ($q) {
                $q->whereNull('is_reverse')->orWhere('is_reverse', 0);
            })
            ->where(function ($q) {
                $q->whereNull('is_refund')->orWhere('is_refund', 0);
            })
            ->whereBetween('new_payment_date', [$this->from, $this->to])
            ->whereRaw("CAST(REPLACE(COALESCE(amount,'0'), ',', '.') AS DECIMAL(15,2)) > ?", [$this->minAmount]);

        if ($this->strictReversals) {
            $pg->whereNull('reveral_transaction_id');
        }

        $this->paidPaygate = array_flip($pg->distinct()->pluck('policyNumber')->all());
        $this->line('  paygate payers in window : ' . count($this->paidPaygate));

        // Future-dated rows — the audit's "corrupt records to fix" (2028/2030).
        $this->futureDated = array_flip(
            PaymentTransaction::query()
                ->where('policyNumber', 'like', 'MIS%')
                ->whereIn('status', $this->successStatuses)
                ->where('new_payment_date', '>', Carbon::now()->format('Y-m-d'))
                ->distinct()->pluck('policyNumber')->all()
        );
        if ($this->futureDated) {
            $this->warn('  future-dated payment rows : ' . count($this->futureDated)
                . ' (flagged in the report, not treated as money)');
        }

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
            // policy_ledger is a V1 legacy table (see Ledger.php) and may not
            // exist on this connection. Unknown != clear — fail safe.
            $this->ledgerReadable = false;
            Log::error('mis:deactivate-nonpaying ledger set unreadable', ['error' => $e->getMessage()]);
            $this->error('  ledger read FAILED: ' . $e->getMessage());
        }
    }

    /** @param array<int> $policyIds */
    private function loadClaims(array $policyIds): void
    {
        if ($this->ignoreClaims || $policyIds === []) {
            return;
        }

        try {
            // NB: `claims` has NO deleted_at column (verified against
            // Graphite_live 31-Jul-2026) — filtering on it is a fatal 1054.
            $this->recentClaims = array_flip(
                Claim::whereIn('policy_id', $policyIds)
                    ->where('created_at', '>=', Carbon::parse($this->to)->subYear()->format('Y-m-d'))
                    ->distinct()->pluck('policy_id')->all()
            );
        } catch (\Throwable $e) {
            Log::warning('mis:deactivate-nonpaying claim lookup failed', ['error' => $e->getMessage()]);
            $this->recentClaims = [];
        }
    }

    // ------------------------------------------------------------ selection

    private function baseQuery(?array $numbers)
    {
        // Mirrors the signed-off SQL: MIS prefix + status = 1.
        // `policies` has NO deleted_at column (verified against Graphite_live
        // 31-Jul-2026) — that is why the reference query commented the filter
        // out; adding it is a fatal 1054, not a safety net.
        $q = Policy::query()->where('policyNumber', 'like', 'MIS%');

        if ($numbers !== null) {
            $q->whereIn('policyNumber', $numbers);
        } else {
            $q->where('status', 1);
        }

        $batch = (string) $this->option('batch');
        if ($batch === 'never-paid') {
            $q->whereNotExists(function ($s) {
                $s->selectRaw('1')->from('payment_transactions as pt')
                    ->whereColumn('pt.policyNumber', 'policies.policyNumber')
                    ->whereNull('pt.deleted_at')
                    ->whereIn('pt.status', $this->successStatuses);
            });
        } elseif ($batch === 'dormant') {
            $before = Carbon::parse($this->option('dormant-before'))->format('Y-m-d');
            $q->whereNotExists(function ($s) use ($before) {
                $s->selectRaw('1')->from('payment_transactions as pt')
                    ->whereColumn('pt.policyNumber', 'policies.policyNumber')
                    ->whereNull('pt.deleted_at')
                    ->whereIn('pt.status', $this->successStatuses)
                    ->where('pt.new_payment_date', '>=', $before);
            });
        }

        return $q;
    }

    // ------------------------------------------------------------ per policy

    private function processOne(Policy $policy): void
    {
        $reason = $this->assess($policy);

        if ($reason !== null) {
            $this->record($policy, 'SKIPPED', $reason);
            return;
        }

        if (! $this->apply) {
            $this->record($policy, 'WOULD-DEACTIVATE', 'no qualifying payment in window');
            return;
        }

        try {
            $policy->status = 0;
            $policy->save();   // Eloquent -> OwenIt audit row for 1 -> 0

            Log::info('mis:deactivate-nonpaying deactivated', [
                'policy_id'    => $policy->id,
                'policyNumber' => $policy->policyNumber,
                'window'       => "{$this->from}..{$this->to}",
            ]);

            $this->record($policy, 'DEACTIVATED', 'status 1 -> 0');
        } catch (\Throwable $e) {
            Log::error('mis:deactivate-nonpaying FAILED', [
                'policy_id'    => $policy->id,
                'policyNumber' => $policy->policyNumber,
                'error'        => $e->getMessage(),
            ]);
            $this->record($policy, 'ERROR', $e->getMessage());
        }
    }

    /** Every reason NOT to deactivate. null = safe to switch off. */
    private function assess(Policy $policy): ?string
    {
        if (strpos((string) $policy->policyNumber, 'MIS') !== 0) {
            return 'not an MIS policy';
        }

        // Manual carve-out wins over every other test, including a clean
        // non-payment result. Finance ring-fenced these deliberately.
        if (isset($this->excluded[$policy->policyNumber])) {
            return 'on the manual exclusion list — ring-fenced';
        }

        if ((int) $policy->status !== 1) {
            return 'already inactive (status=' . var_export($policy->status, true) . ')';
        }

        if (isset($this->paidPaygate[$policy->policyNumber])) {
            return 'PAID in window (paygate) — do not touch';
        }

        if (! $this->skipLedger && isset($this->paidLedger[$policy->id])) {
            return 'PAID in window (ledger) — do not touch';
        }

        if (! $this->ignoreClaims && isset($this->recentClaims[$policy->id])) {
            return 'claim in last 12 months — needs a human decision';
        }

        if ($this->checkBalance) {
            $balance = $this->balanceFor($policy->id);
            if ($balance === null) {
                return 'balance unreadable';
            }
            if ($balance < -0.01) {
                return 'CREDIT balance — money owed to customer';
            }
        }

        return null;
    }

    private function balanceFor(int $policyId): ?float
    {
        try {
            $row = Ledger::where('policy_id', $policyId)
                ->whereNull('deleted_at')
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN trans_type='Invoice' THEN invoice_amount END),0)
                  - COALESCE(SUM(CASE WHEN trans_type='Payment'
                                       AND (status IS NULL OR status <> 'Reversed')
                                      THEN credit END),0) AS bal
                ")->first();

            return $row ? (float) $row->bal : 0.0;
        } catch (\Throwable $e) {
            return null;
        }
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
            $dir = storage_path('app/mis-deactivation');
            if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                $this->error("Cannot create report directory: {$dir}");
                return false;
            }
            $path = $dir . '/mis-deactivation-'
                . ($this->apply ? 'apply' : 'dryrun') . '-'
                . Carbon::now()->format('Ymd-His') . '.csv';
        }

        $this->fh = @fopen($path, 'w');
        if (! $this->fh) {
            $this->error("Cannot open report path: {$path}");
            return false;
        }

        fputcsv($this->fh, [
            'policy_id', 'policyNumber', 'status_before', 'premium_freq', 'premium',
            'active_since', 'future_dated_payment', 'outcome', 'reason', 'run_at',
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
                $policy->premium_freq,
                $policy->premium,
                $policy->policyActivatedDate
                    ? Carbon::parse($policy->policyActivatedDate)->format('Y-m-d')
                    : '',
                isset($this->futureDated[$policy->policyNumber]) ? 'YES' : '',
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

        Log::info('mis:deactivate-nonpaying finished', [
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
