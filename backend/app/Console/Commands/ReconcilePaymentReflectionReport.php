<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * READ-ONLY payment-reflection reconciliation report.
 *
 * For every policy it compares what was collected (qualifying `payment_transactions`)
 * against what is posted to the `policy_ledger` (the Account Statement source), and
 * writes a CSV of policies whose money does not tie out. It WRITES NOTHING to the
 * database and is NOT scheduled — a pure diagnostic.
 *
 * The measure (see D:\tmp\Payment-Reflection-Bulletproof-Design-2026-07-18.md, revised
 * 2026-07-20 after adversarial verification exposed reversal double-counting):
 *   collected  = Σ payment_transactions.amount with a success status, is_refund=0,
 *                amount<>1, not soft-deleted, AND NOT a reversal artifact
 *                (is_reverse<>1, reveral_transaction_id NULL, CompanyRef<>'Reversed').
 *   reflected  = Σ policy_ledger 'Payment' credit whose status is NOT 'Reversed'.
 *   gap_credit = collected - reflected      <-- THE tie-out; the report FLAGS on this.
 * A reversed collection is excluded from BOTH sides, so it nets to 0 without any debit
 * subtraction (the old gap_netted subtracted 'Reverse Payment' + 'Refund' debits, which
 * broke on before-ledger reversals — a voided payment with no offsetting debit showed a
 * false gap, and a refunded-but-genuinely-unposted payment was hidden). gap_net (still
 * emitted as an INFO column) = gap_credit - Reverse debit - Refund debit; do NOT flag on
 * it. Both sides key on policy_id. This deliberately does NOT use the `is_ledger` flag
 * (churn) nor raw trans_ref row-matching (overstates). A refunded payment keeps its
 * Payment credit in `reflected` (the refund is a separate debit), so its credit is still
 * required on the statement.
 *
 * NOTE: this covers the payment_transactions -> ledger tie-out. Collections that never
 * created a payment_transactions row at all (e.g. a RealPay installment marked 'S' with
 * no tx) are a separate check — use `realpay:reconcile-diagnostic {policyNumber}`.
 */
class ReconcilePaymentReflectionReport extends Command
{
    protected $signature = 'realpay:reconcile-report
        {--from-id=0 : only policies with id greater than this}
        {--to-id=0 : only policies with id up to this (0 = no upper bound)}
        {--product= : restrict to a single product_id}
        {--min-gap=1 : only report policies with gap_credit >= this (positive = collected-but-not-reflected)}
        {--chunk=2000 : policies per batch}
        {--report=storage/app/reconcile_payment_reflection.csv : CSV output path}';

    protected $description = 'READ-ONLY: report per-policy payment-vs-ledger reflection gaps (writes a CSV, no DB writes).';

    /** Canonical success casings (mirrors AccountStatementService::SUCCESS_STATUSES). */
    private array $successStatuses = ['Success', 'SUCCESS', 'success', 'S'];

    public function handle()
    {
        $fromId  = (int) $this->option('from-id');
        $toId    = (int) $this->option('to-id');
        $product = $this->option('product');
        $minGap  = (float) $this->option('min-gap');
        $chunk   = max(100, (int) $this->option('chunk'));
        $path    = $this->option('report');

        $fh = @fopen($path, 'w');
        if (! $fh) {
            $this->error("Cannot open report path: {$path}");
            return 1;
        }
        fputcsv($fh, [
            'policy_id', 'policyNumber', 'product_id', 'status',
            'qual_count', 'qual_sum', 'ledger_payment_credit', 'ledger_reverse', 'ledger_refund',
            'gap_vs_credit', 'gap_netted',
        ]);

        $scanned = 0;
        $flagged = 0;
        $gapTotal = 0.0;
        $lastId = $fromId;

        while (true) {
            $pq = DB::table('policies')
                ->select('id', 'policyNumber', 'product_id', 'status')
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
            $ids = $policies->pluck('id')->all();
            $lastId = (int) end($ids);

            // Qualifying collections per policy (success, real payment, not deleted),
            // EXCLUDING reversal artifacts so reversed/bounced money is never counted as
            // "collected". A reversal (ReverseTransactionModal) sets is_reverse=1 on the
            // original tx and creates a duplicate row with reveral_transaction_id set
            // (both status='Success', is_refund=0) — counting either would inflate the gap
            // and let the repair post a phantom credit. Mirrors AccountStatementService.
            $pay = DB::table('payment_transactions')
                ->selectRaw('policy_id, COUNT(*) AS n, ROUND(SUM(amount), 2) AS s')
                ->whereIn('policy_id', $ids)
                ->whereIn('status', $this->successStatuses)
                ->where('is_refund', 0)
                ->where('amount', '<>', 1)
                ->whereNull('deleted_at')
                ->where(function ($q) {
                    $q->whereNull('is_reverse')->orWhere('is_reverse', '<>', 1);
                })
                ->whereNull('reveral_transaction_id')
                ->where(function ($q) {
                    $q->whereNull('CompanyRef')->orWhere('CompanyRef', '<>', 'Reversed');
                })
                ->groupBy('policy_id')
                ->get()->keyBy('policy_id');

            // Ledger components per policy. The 'Payment' credit sum EXCLUDES rows whose
            // status is 'Reversed' (the reversal path flips the original credit to
            // status='Reversed'), exactly as the statement does — so a reversed payment
            // contributes 0 to both sides and nets out without needing a debit subtraction.
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

                // gap_credit is THE payment-reflection tie-out now that both sides
                // exclude reversals: collected (non-reversed) minus reflected (non-reversed
                // Payment credit). This is exactly what the repair closes, so report and
                // repair agree by construction. gap_net (also subtracting Reverse/Refund
                // debits) is kept as an INFORMATIONAL column only — do NOT flag on it, or a
                // refunded-but-genuinely-unposted payment would be hidden.
                $gapCredit = round($qs - $pc, 2);
                $gapNet    = round($qs - $pc - $rv - $rf, 2);

                // Flag POSITIVE gaps only (collected − reflected >= min-gap = collected-but-not-
                // reflected). Negative gaps = ledger richer than payment_transactions (legacy/
                // migrated history, not a reflection problem) — excluded, matching the detector.
                if ($gapCredit >= $minGap) {
                    fputcsv($fh, [
                        $p->id, $p->policyNumber, $p->product_id, $p->status,
                        $qn, $qs, $pc, $rv, $rf, $gapCredit, $gapNet,
                    ]);
                    $flagged++;
                    $gapTotal += $gapCredit;
                }
            }
            $this->info("scanned up to policy id {$lastId} — flagged {$flagged} so far");
        }

        fclose($fh);
        $this->line('');
        $this->info('================ RECONCILE REPORT (read-only) ================');
        $this->line('Policies scanned      : ' . $scanned);
        $this->line('Policies flagged      : ' . $flagged);
        $this->line('Net gap total (BWP)   : ' . round($gapTotal, 2) . '  (positive = collected-but-not-on-statement)');
        $this->line('Report                : ' . $path);
        $this->info('==============================================================');
        $this->warn('Diagnostic only — no records were changed. gap_credit (collected minus reflected, both excluding reversals) is the tie-out measure; gap_net is informational.');
        return 0;
    }
}
