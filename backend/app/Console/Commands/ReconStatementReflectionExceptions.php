<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Routine task: payment -> ledger -> statement REFLECTION tie-out, written into the
 * existing Reconciliation Exceptions module (recon_exception_* tables) for Finance
 * to review — the same home as the RealPay-mandate exceptions (recon:realpay-exceptions).
 *
 * This is a NEW exception TYPE inside the SAME module, not a new module. It flags
 * policies whose collected payments are NOT reflected on the Account Statement.
 *
 * The measure is IDENTICAL to the diagnostic `realpay:reconcile-report`
 * (ReconcilePaymentReflectionReport) — see D:\tmp\Payment-Reflection-Bulletproof-Design-2026-07-18.md
 * (reversal-aware revision 2026-07-20):
 *   collected  = Σ payment_transactions.amount, success status (Success/SUCCESS/success/S —
 *                mirrors AccountStatementService), is_refund=0, amount<>1, not soft-deleted,
 *                AND NOT a reversal artifact (reveral_transaction_id NULL, is_reverse<>1,
 *                CompanyRef<>'Reversed').
 *   reflected  = Σ policy_ledger 'Payment' credit whose status is NOT 'Reversed'.
 *   gap_credit = collected - reflected   <-- THE tie-out; the command FLAGS on this.
 * A reversed collection is excluded from BOTH sides so it nets to 0 without any debit
 * subtraction. A genuinely collected-but-unposted payment shows a positive gap_credit; a
 * ledger credit with no matching collection shows a negative one. gap_netted (gap_credit
 * minus Reverse/Refund debits) is emitted as an INFO detail only — the command does NOT
 * flag on it. Kept in lock-step with ReconcilePaymentReflectionReport.
 *
 * READ-ONLY against the operational tables (policies, payment_transactions, policy_ledger,
 * products). It ONLY writes the recon_exception_* tables — exactly like recon:realpay-exceptions.
 * It never touches payment_transactions or policy_ledger.
 *
 * Idempotent per (source, run_date): re-running the same day regenerates the run's
 * exceptions UNLESS Finance has already closed the run.
 *
 * NOT scheduled here (scheduling is left to review — the cron container owns the live
 * scheduler). Run manually or from the module's "Generate" flow.
 */
class ReconStatementReflectionExceptions extends Command
{
    protected $signature = 'recon:statement-reflection-exceptions
        {--dry : Compute and print a summary without writing}
        {--min-gap=1 : only flag policies with gap_credit >= this (BWP) — positive gap = collected-but-not-reflected}
        {--chunk=2000 : policies per batch}
        {--product= : restrict to a single product_id}
        {--from-id=0 : only policies with id greater than this}
        {--to-id=0 : only policies with id up to this (0 = no upper bound)}';

    protected $description = 'Flag policies whose collected payments are not reflected on the statement; write them into the Exceptions module for Finance review';

    /** Canonical success casings (mirrors AccountStatementService::SUCCESS_STATUSES). */
    private array $successStatuses = ['Success', 'SUCCESS', 'success', 'S'];

    private const SOURCE     = 'payment_reflection';
    private const FLAG_CODE  = 'STATEMENT_REFLECTION';
    // Direction-aware labels — a policy can be OFF in either direction and the generic
    // label would mislead Finance. gap_credit > 0: collected more than the statement shows
    // (genuine under-reflection). gap_credit < 0: statement shows more than was collected
    // (over-credit / possible double-post). Both are surfaced; the label must match the sign.
    private const FLAG_LABEL     = 'Collected payment not reflected on statement';
    private const FLAG_LABEL_NEG = 'Statement credit exceeds collected payments';

    public function handle(): int
    {
        $dry     = (bool) $this->option('dry');
        $minGap  = (float) $this->option('min-gap');
        $chunk   = max(100, (int) $this->option('chunk'));
        $product = $this->option('product');
        $fromId  = (int) $this->option('from-id');
        $toId    = (int) $this->option('to-id');
        $runDate = now()->toDateString();

        $runId = null;

        try {
            // Product id -> name so the module's "Product" column is human-readable.
            $productNames = DB::table('products')->pluck('name', 'id')->toArray();

            // ── Non-dry: create/reuse the run row up front so we can stream inserts. ──
            if (!$dry) {
                $existing = DB::table('recon_exception_runs')
                    ->where('source', self::SOURCE)->where('run_date', $runDate)->first();
                if ($existing && $existing->status === 'closed') {
                    $this->warn("Run for {$runDate} is already closed by Finance — not overwriting.");
                    return self::SUCCESS;
                }
                $now = now();
                if ($existing) {
                    $runId = $existing->id;
                    DB::table('recon_exceptions')->where('run_id', $runId)->delete();
                    DB::table('recon_exception_runs')->where('id', $runId)->update([
                        'status' => 'generating', 'error' => null, 'updated_at' => $now,
                    ]);
                } else {
                    $runId = DB::table('recon_exception_runs')->insertGetId([
                        'source'       => self::SOURCE,
                        'period_label' => 'Statement reflection — ' . $now->format('d M Y'),
                        'run_date'     => $runDate,
                        'status'       => 'generating',
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ]);
                }
            }

            $scanned    = 0;
            $flagged    = 0;
            $gapTotal   = 0.0;
            $posCount   = 0;   // collected but not on statement (gap > 0)
            $negCount   = 0;   // on statement but not collected (gap < 0)
            $bySeverity = ['critical' => 0, 'high' => 0, 'medium' => 0];
            $lastId     = $fromId;
            $buffer     = [];  // pending recon_exceptions rows (non-dry)

            while (true) {
                $pq = DB::table('policies')
                    ->select('id', 'policyNumber', 'product_id', 'status', 'customer_id')
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->limit($chunk);
                if ($toId > 0) {
                    $pq->where('id', '<=', $toId);
                }
                if ($product !== null && $product !== '') {
                    $pq->where('product_id', (int) $product);
                }
                $policies = $pq->get();
                if ($policies->isEmpty()) {
                    break;
                }
                $ids    = $policies->pluck('id')->all();
                $lastId = (int) end($ids);

                // Qualifying collections per policy (success, real payment, not deleted),
                // EXCLUDING reversal artifacts so reversed/bounced money isn't counted as
                // collected — mirrors ReconcilePaymentReflectionReport + AccountStatementService.
                $pay = DB::table('payment_transactions')
                    ->selectRaw('policy_id, COUNT(*) AS n, ROUND(SUM(amount), 2) AS s')
                    ->whereIn('policy_id', $ids)
                    ->whereIn('status', $this->successStatuses)
                    ->where('is_refund', 0)
                    ->where('amount', '<>', 1)
                    ->whereNull('deleted_at')
                    ->whereNull('reveral_transaction_id')
                    ->where(function ($q) { $q->whereNull('is_reverse')->orWhere('is_reverse', '<>', 1); })
                    ->where(function ($q) { $q->whereNull('CompanyRef')->orWhere('CompanyRef', '<>', 'Reversed'); })
                    ->groupBy('policy_id')
                    ->get()->keyBy('policy_id');

                // Ledger components per policy. Payment credit EXCLUDES status='Reversed'
                // (the reversal flips the original credit to 'Reversed'), as the statement does.
                $led = DB::table('policy_ledger')
                    ->selectRaw("policy_id,
                        ROUND(SUM(CASE WHEN trans_type='Payment' AND COALESCE(status,'') <> 'Reversed' THEN COALESCE(credit,0) ELSE 0 END), 2) AS pay,
                        ROUND(SUM(CASE WHEN trans_type='Reverse Payment' THEN COALESCE(debit,0) ELSE 0 END), 2) AS rev,
                        ROUND(SUM(CASE WHEN trans_type='Refund' THEN COALESCE(debit,0) ELSE 0 END), 2) AS ref")
                    ->whereIn('policy_id', $ids)
                    ->whereNull('deleted_at')
                    ->groupBy('policy_id')
                    ->get()->keyBy('policy_id');

                foreach ($policies as $p) {
                    $scanned++;
                    $qn = isset($pay[$p->id]) ? (int) $pay[$p->id]->n : 0;
                    $qs = isset($pay[$p->id]) ? (float) $pay[$p->id]->s : 0.0;
                    $pc = isset($led[$p->id]) ? (float) $led[$p->id]->pay : 0.0;
                    $rv = isset($led[$p->id]) ? (float) $led[$p->id]->rev : 0.0;
                    $rf = isset($led[$p->id]) ? (float) $led[$p->id]->ref : 0.0;

                    // gap_credit = collected (reversal-excluded) − reflected (non-reversed Payment
                    // credit) — THE tie-out now both sides exclude reversals. Flag on it, NOT gap_net
                    // (gap_net additionally subtracts Reverse/Refund debits and would hide a
                    // refunded-but-genuinely-unposted payment). Mirrors ReconcilePaymentReflectionReport.
                    $gapCredit = round($qs - $pc, 2);
                    $gapNet    = round($qs - $pc - $rv - $rf, 2);

                    // Flag POSITIVE gaps only = collected-but-NOT-reflected (the genuine
                    // under-reflection Finance acts on). NEGATIVE gaps (ledger 'Payment' credit
                    // exceeds collected) are dominated by legacy/migrated ledger history that
                    // payment_transactions doesn't hold — verified on prod 2026-07-21: ~57k such,
                    // e.g. ledger 854,958 vs payments 26,033 — NOT over-credits, so excluded here.
                    // (Over-credit/double-post detection is a separate check, not this measure.)
                    if ($gapCredit < $minGap) {
                        continue;
                    }

                    $flagged++;
                    $gapTotal += $gapCredit;
                    $gapCredit > 0 ? $posCount++ : $negCount++;

                    $severity = abs($gapCredit) >= 5000 ? 'critical' : (abs($gapCredit) >= 1000 ? 'high' : 'medium');
                    $bySeverity[$severity]++;

                    if ($dry) {
                        continue; // count only; no row built
                    }

                    $now = now();
                    $productLabel = $productNames[$p->product_id] ?? ('Product ' . $p->product_id);
                    $productLabel = mb_substr((string) $productLabel, 0, 120); // matches column width
                    $buffer[] = [
                        'run_id'          => $runId,
                        'product'         => $productLabel,
                        'flag_code'       => self::FLAG_CODE,
                        'flag_label'      => $gapCredit > 0 ? self::FLAG_LABEL : self::FLAG_LABEL_NEG,
                        'policy_number'   => $p->policyNumber,
                        'customer_id'     => $p->customer_id,
                        'contract_number' => null,
                        'severity'        => $severity,
                        // Reuse the module's numeric columns:
                        //   graphite_value = what the statement reflects (ledger Payment credit)
                        //   realpay_value  = what was collected (qualifying payment_transactions)
                        //   variance       = gap_credit (the tie-out measure; reversals excluded both sides)
                        'graphite_value'  => $pc,
                        'realpay_value'   => $qs,
                        'variance'        => $gapCredit,
                        'detail'          => json_encode([
                            'product_id'            => (int) $p->product_id,
                            'policy_status'         => $p->status,
                            'qual_count'            => $qn,
                            'qual_sum'              => $qs,
                            'ledger_payment_credit' => $pc,
                            'ledger_reverse'        => $rv,
                            'ledger_refund'         => $rf,
                            'gap_vs_credit'         => $gapCredit,
                            'gap_netted'            => $gapNet,
                            'direction'             => $gapCredit > 0 ? 'collected_not_on_statement' : 'on_statement_not_collected',
                            'note'                  => 'graphite_value = non-reversed ledger Payment credit on statement; realpay_value = qualifying collected (reversals excluded); variance = gap_credit (reversals excluded both sides).',
                        ]),
                        'status'          => 'open',
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ];

                    if (count($buffer) >= 500) {
                        DB::table('recon_exceptions')->insert($buffer);
                        $buffer = [];
                    }
                }
                $this->info("scanned up to policy id {$lastId} — flagged {$flagged} so far");
            }

            if (!$dry && $buffer) {
                DB::table('recon_exceptions')->insert($buffer);
                $buffer = [];
            }

            $totals = [
                self::FLAG_CODE     => $flagged,
                'scanned'           => $scanned,
                'flagged'           => $flagged,
                'net_gap_total'     => round($gapTotal, 2),
                'positive_gap_count'=> $posCount, // collected but not on statement
                'negative_gap_count'=> $negCount, // on statement but not collected
                'by_severity'       => $bySeverity,
                'min_gap'           => $minGap,
            ];

            if ($dry) {
                $this->info("DRY RUN — {$flagged} of {$scanned} policies flagged (nothing written):");
                $this->line(json_encode($totals, JSON_PRETTY_PRINT));
                $this->warn('Diagnostic only — gap_credit (collected minus reflected, reversals excluded both sides) is the tie-out measure.');
                return self::SUCCESS;
            }

            DB::table('recon_exception_runs')->where('id', $runId)->update([
                'status'          => 'ready',
                'exception_count' => $flagged,
                'open_count'      => $flagged,
                'totals'          => json_encode($totals),
                'updated_at'      => now(),
            ]);

            $this->info("Run #{$runId} ready — {$flagged} statement-reflection exceptions written for {$runDate}.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('recon:statement-reflection-exceptions failed', ['err' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            if (!empty($runId)) {
                DB::table('recon_exception_runs')->where('id', $runId)->update([
                    'status' => 'failed', 'error' => $e->getMessage(), 'updated_at' => now(),
                ]);
            }
            $this->error('Failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
