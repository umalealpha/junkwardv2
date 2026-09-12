<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Ledger;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\CustomerBanking;
use Carbon\Carbon;

/**
 * reconciliation:repair — the self-heal / backfill engine for the
 * payment_transactions -> policy_ledger ('Payment') leg.
 *
 * Design basis: D:\tmp\Payment-Reflection-Bulletproof-Design-2026-07-18.md
 * (invariant #1 + §3 "universal recurring reconciliation") and
 * D:\tmp\sync_review_ledger_statement_sink.md §5. The MEASURE that decides
 * "is there a gap" is ReconcilePaymentReflectionReport (read-only). This
 * command is the WRITE side of the same invariant, and it complements
 * RecoverMissedRealpayCollections which handles the *other* leg
 * (source -> payment_transactions; a RealPay installment 'S' with no tx).
 *
 * WHAT IT DOES
 *   For every "qualifying" payment_transactions row (the exact qualifying set
 *   ReconcilePaymentReflectionReport uses: status in the canonical success set,
 *   is_refund=0, amount<>1, deleted_at NULL) that has NO live policy_ledger
 *   'Payment' row keyed by its referenceNumber, it posts the missing Payment
 *   ledger row so the collection shows on the Account Statement.
 *
 * SAFETY MODEL (do not weaken without re-reading the design docs + the
 * "Zero risk on live data" standing rule):
 *   1. DRY-RUN BY DEFAULT. Nothing is written unless --execute is passed.
 *      A dry-run mutates nothing and prints/exports the exact set of rows it
 *      WOULD post, for Finance review.
 *   2. IDEMPOTENT (firstOrCreate-style). Dedup key = (policy_id, trans_type,
 *      trans_ref). Before each insert we re-check for ANY physical Payment
 *      ledger row for that key (live OR soft-deleted). Re-running is a no-op.
 *      The companion migration adds UNIQUE(policy_id, trans_type, trans_ref)
 *      so double-posting is impossible at the DB level once enabled.
 *   3. NO CUSTOMER MESSAGES. Every write is wrapped in
 *      PaymentTransaction::withoutEvents() so NO model observers fire
 *      (payment email / LLM WhatsApp confirmation live on
 *      PaymentTransaction::created()). Because events are suppressed the
 *      writes are not auto-audited by the model observer, so we log our own
 *      activity() trail + a per-row CSV report instead — same trade-off
 *      RecoverMissedRealpayCollections accepts.
 *   4. SOFT-DELETED ROWS ARE NEVER RESURRECTED. If the only Payment ledger row
 *      for a ref is soft-deleted, someone deliberately removed it; we skip and
 *      flag it (SKIP-SOFT-DELETED) for manual Finance review rather than
 *      re-inserting (which would also violate the unique index).
 *   5. SCOPEABLE. Prefer running against a Finance-signed list (--signed-list,
 *      typically the flagged-policy column of the reconcile-report CSV) or a
 *      single --policy. Global runs are allowed but must be Finance-signed and
 *      off-hours.
 *   6. NIGHTLY CRONS UNTOUCHED. This does not modify or disable any scheduled
 *      poster; its own trans_ref dedup means the crons and this command cannot
 *      double-post each other.
 *
 * Intended run: manual, off-hours, Finance-signed scope. NOT scheduled.
 */
class RepairPaymentReflectionLedger extends Command
{
    protected $signature = 'reconciliation:repair
        {--execute : actually write the missing ledger rows (omit for a dry-run — the default)}
        {--policy= : restrict to a single policyNumber}
        {--signed-list= : path to a Finance-signed CSV/txt of policyNumbers (one per line or first column) to scope the run}
        {--product= : restrict to a single product_id}
        {--from-id=0 : only payment_transactions with id greater than this}
        {--to-id=0 : only payment_transactions with id up to this (0 = no upper bound)}
        {--limit=0 : maximum gap rows to act on (0 = no limit)}
        {--chunk=2000 : payment_transactions scanned per batch}
        {--report= : write a per-row CSV report to this path}';

    protected $description = 'Self-heal the payment_transactions -> policy_ledger Payment leg: post missing Payment ledger rows idempotently (dry-run by default, no customer messages).';

    /** Canonical success casings — mirrors AccountStatementService::SUCCESS_STATUSES + the reconcile report. */
    private array $successStatuses = ['Success', 'SUCCESS', 'success', 'S'];

    public function handle()
    {
        $execute = (bool) $this->option('execute');
        $limit   = (int) $this->option('limit');
        $chunk   = max(100, (int) $this->option('chunk'));
        $fromId  = (int) $this->option('from-id');
        $toId    = (int) $this->option('to-id');
        $product = $this->option('product');
        $mode    = $execute ? 'EXECUTE (writing to DB)' : 'DRY-RUN (no writes)';

        $signedList = $this->loadSignedList();
        if ($signedList === false) {
            return 1; // bad path — message already printed
        }

        $this->warn("reconciliation:repair — mode: {$mode}");
        if ($signedList !== null) {
            $this->info('Scoped to signed list: ' . count($signedList) . ' policyNumbers');
        }
        if ($this->option('policy')) {
            $this->info('Scoped to single policy: ' . $this->option('policy'));
        }
        Log::info("reconciliation:repair started — mode={$mode}", [
            'signed_list' => $signedList !== null ? count($signedList) : null,
            'policy'      => $this->option('policy'),
            'product'     => $product,
        ]);

        $stats = [
            'scanned'         => 0, // qualifying gap rows seen
            'would_post'      => 0, // gaps we would/did act on
            'posted'          => 0, // Payment ledger rows written
            'already_posted'  => 0, // a live row appeared since selection (race) — no-op
            'soft_deleted'    => 0, // only a soft-deleted row exists — skipped for manual review
            'errors'          => 0,
        ];

        $reportFh = null;
        if ($this->option('report')) {
            $reportFh = @fopen($this->option('report'), 'w');
            if (! $reportFh) {
                $this->error('Cannot open report path: ' . $this->option('report'));
                return 1;
            }
            fputcsv($reportFh, [
                'tx_id', 'policy_id', 'policyNumber', 'product_id', 'referenceNumber',
                'amount', 'tx_status', 'payment_date', 'action', 'ledger_id',
            ]);
        }

        $lastId = $fromId;
        while (true) {
            $rows = $this->gapQuery($signedList, $product, $fromId, $toId, $chunk, $lastId);
            if ($rows->isEmpty()) {
                break;
            }
            $lastId = (int) $rows->last()->tx_id;

            foreach ($rows as $r) {
                if ($limit > 0 && $stats['would_post'] >= $limit) {
                    break 2;
                }
                $stats['scanned']++;
                $stats['would_post']++;

                $cleanAmount = str_replace(',', '', (string) $r->amount);
                $payDate     = $this->resolveDate($r);
                $action      = $execute ? 'PENDING' : 'WOULD-POST';
                $ledgerId    = null;

                if ($execute) {
                    try {
                        [$action, $ledgerId] = $this->postIfMissing($r, $cleanAmount, $payDate, $stats);
                    } catch (\Throwable $e) {
                        $stats['errors']++;
                        $action = 'ERROR';
                        Log::error("reconciliation:repair {$r->policyNumber} ref {$r->referenceNumber}: " . $e->getMessage());
                        $this->error("  [error] {$r->policyNumber} ref {$r->referenceNumber}: " . $e->getMessage());
                    }
                }

                $line = [
                    $r->tx_id, $r->policy_id, $r->policyNumber, $r->product_id, $r->referenceNumber,
                    $cleanAmount, $r->tx_status, $payDate, $action, $ledgerId,
                ];
                if ($reportFh) {
                    fputcsv($reportFh, $line);
                }
            }

            $this->info("scanned up to payment_transactions id {$lastId} — gaps seen {$stats['scanned']}, posted {$stats['posted']}");
        }

        if ($reportFh) {
            fclose($reportFh);
        }

        // A single summary activity entry per run (per-row entries are written in postIfMissing()).
        if ($execute && $stats['posted'] > 0) {
            activity('Ledger Repair')
                ->withProperties($stats)
                ->log('reconciliation:repair posted ' . $stats['posted'] . ' missing Payment ledger row(s) (tx->ledger backfill)');
        }

        $this->printSummary($stats, $mode);
        if ($this->option('report')) {
            $this->info('Report written: ' . $this->option('report'));
        }
        Log::info('reconciliation:repair finished', $stats);
        return 0;
    }

    /**
     * The gap set: qualifying payment_transactions with NO live policy_ledger
     * 'Payment' row keyed on referenceNumber. Keyset-paginated by pt.id ASC so
     * the oldest orphans drain first (design §D) and writes never affect a
     * not-yet-scanned chunk.
     */
    private function gapQuery(?array $signedList, $product, int $fromId, int $toId, int $chunk, int $lastId)
    {
        $q = DB::table('payment_transactions as pt')
            ->join('policies as pa', 'pa.id', '=', 'pt.policy_id')
            ->leftJoin('policy_ledger as pl', function ($j) {
                $j->on('pl.policy_id', '=', 'pt.policy_id')
                  ->where('pl.trans_type', '=', 'Payment')
                  ->whereColumn('pl.trans_ref', 'pt.referenceNumber')
                  ->whereNull('pl.deleted_at');
            })
            ->whereNull('pt.deleted_at')
            ->whereIn('pt.status', $this->successStatuses)
            ->where('pt.is_refund', 0)
            ->where('pt.amount', '<>', 1)
            ->whereNotNull('pt.referenceNumber')
            ->where('pt.referenceNumber', '<>', '')
            ->whereNull('pl.id')                    // <-- the GAP
            ->where('pt.id', '>', $lastId)
            ->orderBy('pt.id')
            ->limit($chunk)
            ->select(
                'pt.id as tx_id',
                'pt.policy_id',
                'pt.referenceNumber',
                'pt.amount',
                'pt.status as tx_status',
                'pt.new_payment_date',
                'pt.paymentDate',
                'pt.created_at as tx_created',
                'pa.policyNumber',
                'pa.customer_id',
                'pa.premium',
                'pa.product_id'
            );

        if ($fromId > 0) {
            $q->where('pt.id', '>', $fromId);
        }
        if ($toId > 0) {
            $q->where('pt.id', '<=', $toId);
        }
        if ($product !== null && $product !== '') {
            $q->where('pa.product_id', (int) $product);
        }
        if ($this->option('policy')) {
            $q->where('pa.policyNumber', $this->option('policy'));
        }
        if ($signedList !== null) {
            $q->whereIn('pa.policyNumber', $signedList);
        }

        return $q->get();
    }

    /**
     * firstOrCreate-style, dedup by (policy_id, trans_type='Payment', trans_ref).
     * Re-checks for ANY physical row (live or soft-deleted) so it is idempotent
     * and never collides with the unique index. Write wrapped in withoutEvents.
     *
     * @return array{0:string,1:?int} [action, ledgerId]
     */
    private function postIfMissing(object $r, string $cleanAmount, string $payDate, array &$stats): array
    {
        $existing = Ledger::where('policy_id', $r->policy_id)
            ->where('trans_type', 'Payment')
            ->where('trans_ref', $r->referenceNumber)
            ->first(); // Ledger has no SoftDeletes trait -> returns soft-deleted rows too

        if ($existing) {
            if ($existing->deleted_at !== null) {
                $stats['soft_deleted']++;
                return ['SKIP-SOFT-DELETED', (int) $existing->id];
            }
            $stats['already_posted']++;
            return ['ALREADY-POSTED', (int) $existing->id];
        }

        $bankingId = $this->bankingId($r);

        $ledgerId = null;
        PaymentTransaction::withoutEvents(function () use ($r, $cleanAmount, $payDate, $bankingId, &$ledgerId, &$stats) {
            $ledger = new Ledger();
            $ledger->customer_id     = $r->customer_id;
            $ledger->account_id      = null;
            $ledger->policy_id       = $r->policy_id;
            $ledger->claim_id        = null;
            $ledger->banking_id      = $bankingId;      // nullable — never blocks posting (design §B / P6)
            $ledger->account_name    = null;
            $ledger->accounting_date = $payDate;
            $ledger->trans_type      = 'Payment';
            $ledger->amount_type     = null;
            $ledger->trans_ref       = $r->referenceNumber;   // canonical key (never any other column)
            $ledger->orig_trans      = $r->referenceNumber;
            $ledger->unallocated     = null;
            $ledger->system_date     = $payDate;
            $ledger->trans_sub_type  = null;
            $ledger->eff_date        = $payDate;
            $ledger->invoice_file    = null;
            $ledger->invoice_date    = null;
            $ledger->invoice_no      = null;
            $ledger->invoice_amount  = null;
            $ledger->premium         = $r->premium;
            $ledger->due_amount      = null;
            $ledger->pmts_adjust     = null;
            $ledger->due_date        = null;
            $ledger->status          = 'Paid';
            $ledger->debit           = null;
            $ledger->credit          = $cleanAmount;
            $ledger->balance         = null;             // statement recomputes running balance
            $ledger->save();
            $ledgerId = (int) $ledger->id;

            // Re-arm the flag to "posted". Query-builder update -> no model events.
            PaymentTransaction::where('id', $r->tx_id)->update(['is_ledger' => 1]);

            $stats['posted']++;
        });

        // Manual audit trail (events were suppressed above).
        activity('Ledger Repair')
            ->withProperties([
                'tx_id'           => $r->tx_id,
                'policy_id'       => $r->policy_id,
                'policyNumber'    => $r->policyNumber,
                'referenceNumber' => $r->referenceNumber,
                'amount'          => $cleanAmount,
                'ledger_id'       => $ledgerId,
            ])
            ->log('reconciliation:repair posted missing Payment ledger row (tx->ledger backfill)');

        return ['POSTED', $ledgerId];
    }

    /** Banking id if present (policy first, then customer); NULL never blocks the post. */
    private function bankingId(object $r): ?int
    {
        try {
            $id = CustomerBanking::where('policy_id', $r->policy_id)->value('id');
            if (! $id) {
                $id = CustomerBanking::where('customer_id', $r->customer_id)->value('id');
            }
            return $id ? (int) $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Post date: new_payment_date, else paymentDate, else created_at — as Y-m-d. */
    private function resolveDate(object $r): string
    {
        $raw = $r->new_payment_date ?: ($r->paymentDate ?: $r->tx_created);
        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable $e) {
            return Carbon::now()->format('Y-m-d');
        }
    }

    /**
     * Parse the optional Finance-signed list of policyNumbers.
     * @return array<string>|null|false  list, null if not supplied, false on error
     */
    private function loadSignedList()
    {
        $path = $this->option('signed-list');
        if (! $path) {
            return null;
        }
        if (! is_file($path) || ! is_readable($path)) {
            $this->error("signed-list not found or unreadable: {$path}");
            return false;
        }
        $out = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $first = trim(explode(',', $line)[0]);
            $first = trim($first, "\"' \t");
            if ($first === '' || strcasecmp($first, 'policyNumber') === 0 || strcasecmp($first, 'policy_id') === 0) {
                continue; // skip blanks + a header row
            }
            $out[$first] = true;
        }
        $list = array_keys($out);
        if (empty($list)) {
            $this->error("signed-list contained no policyNumbers: {$path}");
            return false;
        }
        return $list;
    }

    private function printSummary(array $s, string $mode): void
    {
        $this->line('');
        $this->info('================ REPAIR SUMMARY (' . $mode . ') ================');
        foreach ([
            'scanned'        => 'Qualifying gap rows scanned',
            'would_post'     => ($mode[0] === 'E' ? 'Gap rows actioned' : 'Gap rows that WOULD be posted'),
            'posted'         => '  Payment ledger rows written',
            'already_posted' => '  already had a live row (race, no-op)',
            'soft_deleted'   => '  skipped (only a soft-deleted row exists)',
            'errors'         => 'Errors',
        ] as $k => $label) {
            $this->line(str_pad($label, 46) . ': ' . $s[$k]);
        }
        $this->info('==============================================================');
        if ($mode[0] !== 'E') {
            $this->warn('DRY-RUN: nothing was written. Re-run with --execute (off-hours, Finance-signed scope) to apply.');
        }
    }
}
