<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;

use AlphaDirect\Models\LedgerArchive;
use AlphaDirect\Policy;
use Http\Client\Exception;
use Carbon\Carbon;
use AlphaDirect\Ledger;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Models\PolicyActions;
use AlphaDirect\SubLedger;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Models\PolicyAction;
use Log;
class CreateInvoiceForDomCom extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:domcom
        {--policy= : Only this policy ID}
        {--action= : Only this policy action ID}
        {--dry-run : List the matching actions without writing any invoice}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Invoices DomCom';

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

        try {

            $cron = new CronStatus();
            $cron->name = "Invoices DomCom Start";
            $cron->start = Carbon::now();
            $cron->save();
            Log::info('Invoices DomCom started');

            $today = Carbon::now()->format('d');//Carbon::today();            
            $date = Carbon::now()->format('Y-m-d');
            // A scoped run (--policy / --action) targets one known gap, so it tests
            // for a missing LIVE INVOICE. The unscoped daily run keeps the original
            // "no policy_ledger row at all" semantics, so its blast radius and
            // behaviour are unchanged.
            $scoped = $this->option('policy') || $this->option('action');

            // premium_freq 5 (QUARTERLY) and 6 (MANUAL INPUT) were never invoiced
            // here — the gate below allowed only 1/2/3, so those actions matched
            // the query, were listed by --dry-run, then fell through and wrote
            // nothing at all.
            //
            // Frequencies (canonical list = LookupController::premium_frequencies):
            //   1 MONTHLY · 2 3-INSTALMENT · 3 ANNUAL · 4 SEMIANNUAL
            //   5 QUARTERLY · 6 MANUAL INPUT
            //
            // Why 5 and 6 belong here: this command is the gap filler for the
            // ACTION-WISE issue-time invoice path, Helper::generateInvoiceDomComIssued,
            // which already invoices 1/2/3/5/6. Anything that path is meant to
            // raise, this one must be able to repair — otherwise a missed action
            // on a quarterly or manual-input policy can never be filled. Quarterly
            // is nominally owned by invoice:domcomq, but that command exists ONLY
            // in cron/ and only ever reads the NEWBUSINESS action, so it cannot
            // repair a RENEW or ENDORSE gap.
            //
            // Amounts are unaffected: this command derives them from
            // $policyAction->premium less $policy->vat regardless of frequency,
            // exactly as the issue-time path does.
            //
            // Restricted to SCOPED runs on purpose: the unscoped daily batch keeps
            // its 1/2/3 behaviour untouched, so this cannot widen what the
            // scheduler writes — only what a deliberate --policy/--action gap-fill
            // can reach.
            // 5 (QUARTERLY) is now allowed in BOTH modes: the daily batch was
            // silently leaving every quarterly action uninvoiced forever, which
            // is what stranded COMG2024128523's two RENEW actions. 6 (MANUAL
            // INPUT) stays scoped-only — a manual-billing policy should not be
            // auto-invoiced by the scheduler without a business decision.
            $allowedFreq = $scoped ? [1, 2, 3, 5, 6] : [1, 2, 3, 5];

            $policiesQuery = PolicyAction::select(
                'policy_actions.*',
                'p.premium_freq',
                'p.customer_id',
                'p.vat',
                'p.policyNumber'
            )
                ->join('policies as p', 'p.id', '=', 'policy_actions.policy_id')
                ->leftJoin('policy_ledger as pl', function ($join) use ($scoped) {
                    $join->on('pl.policy_id', '=', 'policy_actions.policy_id')
                        ->on('pl.action_id', '=', 'policy_actions.id');
                    if ($scoped) {
                        $join->where('pl.trans_type', '=', 'Invoice')
                            ->whereNull('pl.deleted_at');
                    }
                })
                ->whereIn('p.product_id', [7, 8])
                ->where('policy_actions.deleted_at', null)
                ->where('policy_actions.status', 'ISSUED')
                ->where('policy_actions.effective_from', '>=', Carbon::now()->subMonths(24)->startOfMonth()->toDateString())
                ->whereNull('pl.id')
                ->where('policy_actions.premium', '!=', 0)
                ->orderBy('policy_actions.id', 'DESC');

            // Optional scoping so the command can be run for one policy / one action
            // instead of every qualifying action in the last 24 months.
            if ($this->option('policy')) {
                $policiesQuery->where('p.id', $this->option('policy'));
            }
            if ($this->option('action')) {
                $policiesQuery->where('policy_actions.id', $this->option('action'));
            }

            $policies = $policiesQuery->get();

            // A scoped run is a manual gap-fill: say out loud how many actions
            // matched. Previously an empty match printed nothing and exited 0,
            // so "no invoice created" was indistinguishable from "ran fine".
            if ($scoped) {
                $this->info($policies->count() . ' ISSUED action(s) with no live invoice matched.');
                if ($policies->isEmpty()) {
                    $this->warn('Nothing matched. The filters are: product 7/8, status ISSUED, not deleted, '
                        . 'premium != 0 (NULL premium never matches), effective_from >= '
                        . Carbon::now()->subMonths(24)->startOfMonth()->toDateString()
                        . ', and no existing live Invoice row for the action.');
                }
            }

            $data = array();

            if ($this->option('dry-run')) {
                $this->info('DRY RUN — ' . $policies->count() . ' ISSUED action(s) with no invoice:');
                foreach ($policies as $p) {
                    $willInvoice = in_array((int) $p->premium_freq, $allowedFreq, true);
                    $this->line(sprintf(
                        '  policy %s (%s) · action %s · eff %s · freq %s · premium %s · %s',
                        $p->policy_id,
                        $p->policyNumber,
                        $p->id,
                        $p->effective_from,
                        $p->premium_freq,
                        $p->premium,
                        $willInvoice ? 'WILL INVOICE' : 'SKIP (premium_freq not ' . implode('/', $allowedFreq) . ')'
                    ));
                }
                $cron->end = Carbon::now();
                $cron->save();
                return 0;
            }

            foreach ($policies as $policy) {
                $policy_id = $policy->policy_id;
                $actionId = $policy->id;
                $policyAction = PolicyAction::where('policy_id', $policy_id)->where('id', $actionId)->where('status', 'ISSUED')->whereNull('deleted_at')->get();
                // dd($policyAction);
                foreach ($policyAction as $policyActions) {
                    $termId = isset($policyActions->term_id) ? $policyActions->term_id : 0;
                    $actionId = isset($policyActions->id) ? $policyActions->id : 0;
                    // ->where('action_id', $policyActions->id)
                    $check_invoice_exists = Ledger::where('policy_id', $policy_id)
                        ->where('trans_type', 'Invoice')
                        ->where('action_id', $actionId)
                        ->whereNull('deleted_at')
                        ->count();
                    if ($check_invoice_exists == 0 && in_array((int) $policy->premium_freq, $allowedFreq, true)) {
                        $ledger = Ledger::where('policy_id', $policy_id)->orderBy('id', 'DESC')->where('trans_type', 'Invoice')->first(array('invoice_no', 'banking_id', 'invoice_date'));
                        $ledger_count = Ledger::where('policy_id', $policy_id)->where('trans_type', 'Invoice')->count();

                        $policy = Policy::where('id', $policy_id)->first();
                        if ($ledger != NULL) {
                            $invoice_no = $ledger->invoice_no;
                            $invoice_no++;
                            $banking_id = $ledger->banking_id;

                        } else {
                            $invoice_no = $policy->policyNumber . '-' . sprintf('%03d', 1);
                            $banking_id = CustomerBanking::where('customer_id', $policy->customer_id)->first(array('id'));
                            if ($banking_id != NULL)
                                $banking_id = $banking_id->id;
                            else
                                $banking_id = NULL;
                        }
                        $balance = 0;
                        $balance = Ledger::where('policy_id', $policy_id)->orderBy('id', 'DESC')->first(array('balance'));
                        if ($balance != null) {
                            $balance = $balance->balance;
                        } else {
                            //$balance = LedgerArchive::where('policy_id', $policy_id)->orderBy('id', 'DESC')->first(array('balance'));
                            if ($balance != null) {
                                $balance = $balance->balance;
                            } else {
                                $balance = 0;
                            }
                        }
                        $data = array();
                        $record = array();
                        $subData = array();
                        $subRecord = array();

                        //-------------------------------PREMIUM--------------------------------------
                        $record['customer_id'] = $policy->customer_id;
                        $record['account_id'] = NULL;
                        $record['policy_id'] = $policy_id;
                        $record['term_id'] = $termId;
                        $record['action_id'] = $actionId;
                        $record['claim_id'] = NULL;
                        $record['banking_id'] = $banking_id;
                        $record['account_name'] = NULL;
                        $record['accounting_date'] = Carbon::parse($date);
                        $record['trans_type'] = 'Invoice Premium';
                        $record['amount_type'] = NULL;
                        $record['trans_ref'] = NULL;
                        $record['orig_trans'] = NULL;
                        $record['unallocated'] = NULL;
                        $record['system_date'] = Carbon::parse($date);
                        $record['trans_sub_type'] = NULL;
                        $record['eff_date'] = Carbon::parse($date);
                        $record['invoice_file'] = NULL;
                        $record['invoice_date'] = NULL;
                        $record['invoice_no'] = NULL;
                        $record['invoice_amount'] = NULL;
                        $record['premium'] = $policyActions->premium;
                        $record['other_charges'] = NULL;
                        $record['due_amount'] = NULL;
                        $record['pmts_adjust'] = NULL;
                        $record['due_date'] = NULL;
                        $record['status'] = 'Pending';
                        $record['credit'] = NULL;

                        $amt = str_replace(',', '', number_format(((float) $policyActions->premium - (float) $policy->vat), 2));
                        $record['debit'] = str_replace(',', '', $amt);
                        $balance = number_format(((float) str_replace(',', '', $balance) - (float) str_replace(',', '', $amt)), 2);
                        $record['balance'] = str_replace(',', '', $balance);

                        $data[] = $record;


                        //----SUB-LEDGER
                        $subRecord['customer_id'] = $policy->customer_id;
                        $subRecord['account_id'] = NULL;
                        $subRecord['policy_id'] = $policy_id;
                        $subRecord['term_id'] = $termId;
                        $subRecord['action_id'] = $actionId;
                        $subRecord['claim_id'] = NULL;
                        $subRecord['banking_id'] = $banking_id;
                        $subRecord['account_name'] = 'Insurance Sales A/C';
                        $subRecord['accounting_date'] = Carbon::parse($date);
                        $subRecord['trans_type'] = 'Insurance Premium';
                        $subRecord['trans_ref'] = NULL;
                        $subRecord['system_date'] = Carbon::parse($date);
                        $subRecord['credit'] = $amt;
                        $subRecord['debit'] = NULL;

                        $subData[] = $subRecord;

                        //-------------------------------PREMIUM--------------------------------------
                        //-------------------------------VAT--------------------------------------

                        $record = array();

                        $record['customer_id'] = $policy->customer_id;
                        $record['account_id'] = NULL;
                        $record['policy_id'] = $policy_id;
                        $record['term_id'] = $termId;
                        $record['action_id'] = $actionId;
                        $record['claim_id'] = NULL;
                        $record['banking_id'] = $banking_id;
                        $record['account_name'] = NULL;
                        $record['accounting_date'] = Carbon::parse($date);
                        $record['trans_type'] = 'Invoice VAT';
                        $record['amount_type'] = NULL;
                        $record['trans_ref'] = NULL;
                        $record['orig_trans'] = NULL;
                        $record['unallocated'] = NULL;
                        $record['system_date'] = Carbon::parse($date);
                        $record['trans_sub_type'] = NULL;
                        $record['eff_date'] = Carbon::parse($date);
                        $record['invoice_file'] = NULL;
                        $record['invoice_date'] = NULL;
                        $record['invoice_no'] = NULL;
                        $record['invoice_amount'] = NULL;
                        $record['premium'] = $policyActions->premium;
                        $record['other_charges'] = NULL;
                        $record['due_amount'] = NULL;
                        $record['pmts_adjust'] = NULL;
                        $record['due_date'] = NULL;
                        $record['status'] = 'Pending';
                        $record['credit'] = NULL;

                        $amt = floatval($policy->vat);

                        $record['debit'] = str_replace(',', '', $amt);
                        $balance = str_replace(',', '', number_format(((float) str_replace(',', '', $balance) - (float) str_replace(',', '', $amt)), 2));
                        $record['balance'] = str_replace(',', '', $balance);

                        $data[] = $record;

                        //----SUB-LEDGER

                        $subRecord = array();

                        $subRecord['customer_id'] = $policy->customer_id;
                        $subRecord['account_id'] = NULL;
                        $subRecord['policy_id'] = $policy_id;
                        $subRecord['term_id'] = $termId;
                        $subRecord['action_id'] = $actionId;
                        $subRecord['claim_id'] = NULL;
                        $subRecord['banking_id'] = $banking_id;
                        $subRecord['account_name'] = 'VAT Control A/C';
                        $subRecord['accounting_date'] = Carbon::parse($date);
                        $subRecord['trans_type'] = 'VAT on Insurance Premium';
                        $subRecord['trans_ref'] = NULL;
                        $subRecord['system_date'] = Carbon::parse($date);
                        $subRecord['credit'] = $amt;
                        $subRecord['debit'] = NULL;

                        $subData[] = $subRecord;

                        //-------------------------------VAT--------------------------------------
                        //-------------------------------INVOICE--------------------------------------

                        $record = array();
                        $record['customer_id'] = $policy->customer_id;
                        $record['account_id'] = NULL;
                        $record['policy_id'] = $policy_id;
                        $record['term_id'] = $termId;
                        $record['action_id'] = $actionId;
                        $record['claim_id'] = NULL;
                        $record['banking_id'] = $banking_id;
                        $record['account_name'] = NULL;
                        $record['accounting_date'] = Carbon::parse($date);
                        $record['trans_type'] = 'Invoice';
                        $record['amount_type'] = NULL;
                        $record['trans_ref'] = NULL;
                        $record['orig_trans'] = NULL;
                        $record['unallocated'] = NULL;
                        $record['system_date'] = Carbon::parse($date);
                        $record['trans_sub_type'] = NULL;
                        $record['eff_date'] = Carbon::parse($date);
                        $record['invoice_file'] = 1;
                        $record['invoice_date'] = Carbon::parse($policyActions->effective_from);
                        $record['invoice_no'] = $invoice_no;
                        $record['invoice_amount'] = $policyActions->premium;
                        $record['premium'] = $policyActions->premium;
                        $record['other_charges'] = NULL;
                        $record['due_amount'] = $policyActions->premium;
                        $record['pmts_adjust'] = NULL;
                        $record['due_date'] = NULL;
                        $record['status'] = 'Pending';
                        $record['credit'] = NULL;

                        $amt = number_format(((float) str_replace(',', '', $policyActions->premium) - (float) str_replace(',', '', $policy->vat)), 2);

                        $record['debit'] = $policyActions->premium;
                        $record['balance'] = str_replace(',', '', $balance);

                        $data[] = $record;

                        //----SUB-LEDGER

                        $subRecord = array();
                        $subRecord['customer_id'] = $policy->customer_id;
                        $subRecord['account_id'] = NULL;
                        $subRecord['policy_id'] = $policy_id;
                        $subRecord['term_id'] = $termId;
                        $subRecord['action_id'] = $actionId;
                        $subRecord['claim_id'] = NULL;
                        $subRecord['banking_id'] = $banking_id;
                        $subRecord['account_name'] = 'Accounts Receivable A/C';
                        $subRecord['accounting_date'] = Carbon::parse($date);
                        $subRecord['trans_type'] = 'Accounts Receivable';
                        $subRecord['trans_ref'] = NULL;
                        $subRecord['system_date'] = Carbon::parse($date);
                        $subRecord['credit'] = NULL;
                        $subRecord['debit'] = $policyActions->premium;

                        $subData[] = $subRecord;

                        $subData[0]['trans_ref'] = $invoice_no;
                        $subData[1]['trans_ref'] = $invoice_no;
                        $subData[2]['trans_ref'] = $invoice_no;
                        //-------------------------------INVOICE-------------------------------------
                        Ledger::insert($data);
                        SubLedger::insert($subData);
                        Log::info('Invoices data', $data);
                        echo "Invoice created for Policy ID: " . $policy_id . " Invoice No: " . $invoice_no . "\n";
                    } elseif ($scoped) {
                        // Manual gap-fill: name the reason instead of falling
                        // through silently.
                        $this->warn($check_invoice_exists > 0
                            ? "action {$actionId}: skipped — a live Invoice row already exists."
                            : "action {$actionId}: skipped — premium_freq is {$policy->premium_freq}, only "
                                . implode('/', $allowedFreq) . ' are invoiced here.');
                    }



                }
            }

            $cron->end = Carbon::now();
            $cron->save();
            Log::info('Invoices end', $data);

        } catch (\Throwable $e) {
            // Was `catch (\Exception $e) { return false; }` — every failure
            // (DB unreachable, read-only host, bad column, duplicate key) exited
            // silently with no output, so a run that wrote nothing looked
            // identical to a successful one. Report and fail loudly instead.
            $this->error('invoice:domcom failed — ' . $e->getMessage());
            $this->line('  at ' . $e->getFile() . ':' . $e->getLine());
            Log::error('invoice:domcom failed', [
                'policy' => $this->option('policy'),
                'action' => $this->option('action'),
                'error'  => $e->getMessage(),
                'file'   => $e->getFile() . ':' . $e->getLine(),
            ]);
            return 1;
        }
    }
}
