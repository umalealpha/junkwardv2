<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Ledger;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Http\Client\Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Models\CronStatus;


class LedgerPaymentTransDomCom extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'LedgerPaymentTransDomCom:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ledger Payment Transaction DomCom';

    /**
     * Products whose payments this cron posts into policy_ledger.
     *
     * This is the ACTION-WISE invoiced family — the same set carried by
     * PolicyLedgerDaily/InvoiceGenerator::NO_AUTO_INVOICE_PRODUCTS, Helper.php's
     * issue-time invoice list and ledger:delete-null-action-invoices. Those
     * products are deliberately EXCLUDED from the calendar-driven
     * PolicyLedger / PolicyLedgerDaily run, which is where every other product's
     * payments get posted — so this cron is their ONLY payment→ledger path.
     *
     * It previously listed just [7, 8], which meant specialist policies
     * (16-20, 22, 23, 24) had NO payment-posting path at all: their receipts
     * lived only in payment_transactions, showed up in the Transaction Logs tab,
     * and never reached policy_ledger — so Ledger > Account/Receivable View and
     * the account statement showed the invoices with no payments against them.
     *
     * Product 21 (Health in a Box Plus) is deliberately NOT listed — it is
     * calendar-billed by PolicyLedgerDaily, which already posts its payments.
     * Adding it here would double-credit. Keep this list in lockstep with
     * backend/app/Console/Commands/LedgerPaymentTransDomCom.php. THIS is the
     * runtime copy — the scheduler that fires it lives in cron/app/Console/Kernel.php.
     */
    protected const LEDGER_PAYMENT_PRODUCTS = [7, 8, 16, 17, 18, 19, 20, 22, 23, 24];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        
        $cron = new CronStatus();
        $cron->name  = "LedgerPaymentTransDomCom:cron";
        $cron->start = \Carbon\Carbon::now();
        $today = Carbon::today();
        $dt  = Carbon::now();
        $now = $dt->toDateString();

        // $ledger = Ledger::whereIn('policy_id',$ids)
        // ->groupBy('policy_id')
        // ->get('policy_id');

        $ledger = DB::table('payment_transactions as pt')
        ->join('policies as pa', 'pt.policy_id', '=', 'pa.id')
        ->leftJoin('policy_ledger as pl', function ($join) {
            $join->on('pl.policy_id', '=', 'pt.policy_id')
                 ->where('pl.trans_type', '=', 'Payment')
                 ->whereColumn('pl.trans_ref', 'pt.referenceNumber');
        })
        ->whereIn('pa.product_id', self::LEDGER_PAYMENT_PRODUCTS)
        //->where('pa.premium_freq', 1)
        // ->where('pa.status', 2)
        ->whereNull('pl.id')
        // is_ledger is the canonical "already posted" flag (same as PolicyLedgerDaily/PolicyLedger).
        // Keying on it — not a yesterday-only date window — means a payment the cron missed
        // (cron down, policy created later, product only just added to the list above) still
        // posts on the next run instead of being skipped forever. Matches the runtime copy in
        // cron/app/Console/Commands/LedgerPaymentTransDomCom.php, which this backend mirror
        // had drifted from.
        ->where('pt.is_ledger', 0)
        // Accept the full canonical success set (incl. legacy 'S'); mirrors
        // AccountStatementService::SUCCESS_STATUSES. LOWER(status)='success'
        // excluded legacy 'S'-status DomCom payments, which then never posted.
        ->whereIn('pt.status', ['Success', 'SUCCESS', 'success', 'S'])
        // Exclude reversal artifacts: the reversal flow keeps a LIVE duplicate row
        // (reveral_transaction_id set) and flags the original is_reverse=1; posting either
        // credits bounced/reversed money (before-ledger reversals have no offsetting debit).
        // Mirrors PolicyLedgerDaily + ReconcilePaymentReflectionReport + AccountStatementService.
        ->whereNull('pt.reveral_transaction_id')
        ->where(function ($q) { $q->whereNull('pt.is_reverse')->orWhere('pt.is_reverse', '<>', 1); })
        ->where(function ($q) { $q->whereNull('pt.CompanyRef')->orWhere('pt.CompanyRef', '<>', 'Reversed'); })
        ->select(
            'pt.id as payment_id',
            'pt.referenceNumber',
            'pt.policy_id',
            'pa.policyNumber'
        )
        ->get();
       // dd($ledger);
        // The outer query returns ONE ROW PER UNPOSTED PAYMENT, but the loop below
        // already drains every unposted payment of a policy in one pass. Iterating
        // it raw re-scanned the same policy once per payment — O(n²) per policy,
        // which only stayed survivable while the scope was "yesterday, products 7/8".
        // Collapsing to distinct policy ids keeps the nightly run linear now that it
        // catches up on is_ledger=0 across the whole product family.
        $policyIds = collect($ledger)->pluck('policy_id')->filter()->unique()->values();

        $posted = 0; $already = 0; $skipped = 0; $failed = 0;

        try {
            foreach ($policyIds as $policyId) {
                $pendingPayments = PaymentTransaction::where('policy_id', $policyId)
                    // ->whereYear('new_payment_date', $getYear)
                    // ->whereMonth('new_payment_date', $getMonth)
                    ->where('is_ledger', 0)
                    ->whereIn('status', ['Success', 'SUCCESS', 'success', 'S'])
                    // Exclude reversal artifacts so bounced/reversed money is never credited (see outer query).
                    ->whereNull('reveral_transaction_id')
                    ->where(function ($q) { $q->whereNull('is_reverse')->orWhere('is_reverse', '<>', 1); })
                    ->where(function ($q) { $q->whereNull('CompanyRef')->orWhere('CompanyRef', '<>', 'Reversed'); })
                    ->get();

                if ($pendingPayments->isEmpty()) {
                    continue;
                }

                // The outer query joins `policies` raw, so a row always exists — but
                // Policy carries a soft-delete scope, so this can still come back NULL.
                // It used to dereference straight into ->customer_id and fatal, killing
                // the whole night's run at that policy.
                $policy = Policy::where('id', $policyId)->first(array('id', 'customer_id', 'premium_freq'));
                if ($policy === null) {
                    $skipped += $pendingPayments->count();
                    Log::warning('LedgerPaymentTransDomCom: policy missing/soft-deleted, payments skipped', [
                        'policy_id' => $policyId,
                        'payments'  => $pendingPayments->count(),
                    ]);
                    continue;
                }

                // Depends only on the customer — hoisted out of the payment loop.
                $banking_id = CustomerBanking::where('customer_id', $policy->customer_id)->first(array('id'));

                if($banking_id != NULL)
                    $banking_id = $banking_id->id;
                else
                    $banking_id = NULL;

                foreach ($pendingPayments as $value) {
                    // Per-payment isolation: one unsaveable row must not abort the
                    // remaining payments (and every later policy) for the night.
                    try {
                        // new_payment_date is the settlement date, but it is not always
                        // populated. strtotime(null) returns false, which date() then
                        // renders as 1970-01-01 — a payment posted at epoch corrupts the
                        // statement's chronological ordering and its running balance.
                        $paidOnRaw = $value->new_payment_date ?: ($value->paymentDate ?: $value->created_at);
                        $paidOnTs  = $paidOnRaw ? strtotime((string) $paidOnRaw) : false;
                        if ($paidOnTs === false) {
                            $skipped++;
                            Log::warning('LedgerPaymentTransDomCom: payment has no usable date, not posted', [
                                'payment_id' => $value->id,
                                'policy_id'  => $policyId,
                            ]);
                            continue;
                        }
                        $paidOn = date('Y-m-d', $paidOnTs);

                        $getLedgerDataCnt = Ledger::where('policy_id', $policyId)->where('trans_ref', $value->referenceNumber)->where('trans_type','Payment')->count();
                        if($getLedgerDataCnt==0){
                                $record = new Ledger;
                                $record->customer_id = $policy->customer_id;
                                $record->account_id = NULL;
                                $record->policy_id = $policy->id;
                                $record->claim_id = NULL;
                                $record->banking_id = $banking_id;
                                $record->account_name = NULL;
                                $record->accounting_date = $paidOn;
                                //
                                $record->amount_type = NULL;
                                $record->trans_ref  = $value->referenceNumber;
                                $record->orig_trans = $value->referenceNumber;
                                $record->unallocated = NULL;
                                $record->system_date = $paidOn;
                                $record->trans_sub_type = NULL;
                                $record->eff_date = $paidOn;
                                $record->invoice_file = NULL;
                                $record->invoice_date = $paidOn;
                                $record->invoice_no = '';
                                $record->invoice_amount = $value->amount;
                                $record->premium = $value->amount;
                                $record->due_amount = NULL;
                                $record->pmts_adjust = NULL;
                                $record->due_date = NULL;
                                $record->status = 'Paid';
                                $record->debit = NULL;
                                $record->credit = $value->amount;
                                $record->balance = NULL;
                                $record->trans_type = 'Payment';
                            // $record->premium_freq = $policy->premium_freq;
                            //  $record->prorata_status = '';
                                $record->save();

                                PaymentTransaction::where('id', $value->id)->update(['is_ledger' => 1]);
                                $posted++;

                        } else {
                                // Already in the ledger (posted by one of the gateway
                                // crons) but still flagged is_ledger=0, so it was being
                                // re-checked every single night forever. Flag it truthfully:
                                // it also makes Transaction Logs offer the correct
                                // after-ledger reversal path for this payment.
                                PaymentTransaction::where('id', $value->id)->update(['is_ledger' => 1]);
                                $already++;
                        }
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::error('LedgerPaymentTransDomCom: failed to post payment to ledger', [
                            'payment_id' => $value->id ?? null,
                            'policy_id'  => $policyId,
                            'error'      => $e->getMessage(),
                        ]);
                    }
                }
            }
        } finally {
            // Always stamp the end time — a mid-run fatal used to leave the Cron
            // Portal showing a run that started and never finished.
            $cron->end = \Carbon\Carbon::now();
            $cron->save();
        }

        Log::info('LedgerPaymentTransDomCom: done', [
            'policies' => $policyIds->count(),
            'posted'   => $posted,
            'already'  => $already,
            'skipped'  => $skipped,
            'failed'   => $failed,
        ]);
        $this->info("LedgerPaymentTransDomCom: policies={$policyIds->count()} posted={$posted} already={$already} skipped={$skipped} failed={$failed}");
        return 0;
    }
}
