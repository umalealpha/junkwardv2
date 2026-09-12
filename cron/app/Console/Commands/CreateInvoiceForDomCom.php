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
    protected $signature = 'invoice:domcom';

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
            $policies = PolicyAction::select(
                'policy_actions.*',
                'p.premium_freq',
                'p.customer_id',
                'p.vat',
                'p.policyNumber'
            )
                ->join('policies as p', 'p.id', '=', 'policy_actions.policy_id')
                ->leftJoin('policy_ledger as pl', function ($join) {
                    $join->on('pl.policy_id', '=', 'policy_actions.policy_id')
                        ->on('pl.action_id', '=', 'policy_actions.id');
                })
                ->whereIn('p.product_id', [7, 8])
                // ->where('p.id',99628)
                ->where('policy_actions.deleted_at', null)
                ->where('policy_actions.status', 'ISSUED')
                ->where('policy_actions.effective_from', '>', '2025-06-30')
                ->whereNull('pl.id')
                ->where('policy_actions.premium', '!=', 0)
                ->orderBy('policy_actions.id', 'DESC')
                ->get();
            ;

           // dd(count($policies));
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
                    // premium_freq 5 (QUARTERLY) added to match the backend copy
                    // (backend/app/Console/Commands/CreateInvoiceForDomCom.php).
                    // Without it every quarterly DomCom action was skipped in
                    // silence. NOTE: this cron copy still has no --policy /
                    // --action / --dry-run options and still swallows exceptions
                    // — the backend copy is the one to run for a manual gap-fill.
                    if ($check_invoice_exists == 0 && ($policy->premium_freq == 1 || $policy->premium_freq == 2 || $policy->premium_freq == 3 || $policy->premium_freq == 5)) {
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
                        //echo "Invoice created for Policy ID: " . $policy_id . " Invoice No: " . $invoice_no . "\n";
                    }



                }
            }

            $cron->end = Carbon::now();
            $cron->save();
            Log::info('Invoices end', $data);

        } catch (\Exception $e) {
            return false;
        }
    }
}
