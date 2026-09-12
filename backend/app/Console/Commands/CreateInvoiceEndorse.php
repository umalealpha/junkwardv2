<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;

use AlphaDirect\Models\LedgerArchive;
use AlphaDirect\Policy;
use Http\Client\Exception;
use Carbon\Carbon;
use AlphaDirect\Ledger;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\SubLedger;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Models\policyActionEndorse;
use Log;

class CreateInvoiceEndorse extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoicedomcom:enorse';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Invoice DomCom Endorse';

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

       // try {

                $cron = new CronStatus();
                $cron->name = "Invoices DomCom Start";
                $cron->start = Carbon::now();
                $cron->save();
                Log::info('Invoices DomCom started');

                $today = Carbon::now()->format('d');//Carbon::today();            
                $date = Carbon::now()->format('Y-m-d');
                //whereDay('created_at', $today) 
                $policiesbyCreteated = Policy::
                whereIn('product_id',[7,8])
                ->where('status', 1)
                ->where('id','20670')
                ->select(array('id', 'customer_id', 'product_id', 'plan_id', 'premium_freq', 'created_at', 'updated_at', 'first_premium', 'annual_premium' , 'premium', 'vat', 'vat_percent', 'policyNumber', 'policyActivatedDate', 'is_sys_act_generated', 'billingStartDate', 'status'))
                ->get();
            //whereDay('billingStartDate', $today) 
                $policiesbyBilled = Policy::
                whereIn('product_id',[7,8])
                ->where('status', 1)
                ->where('id','20670')
                ->select(array('id', 'customer_id', 'product_id', 'plan_id', 'premium_freq', 'created_at', 'updated_at', 'first_premium', 'annual_premium' ,'premium', 'vat', 'vat_percent', 'policyNumber', 'policyActivatedDate', 'is_sys_act_generated', 'billingStartDate', 'status'))
                ->get();

                $merged = $policiesbyBilled->merge($policiesbyCreteated)->collect();
                $policies = $merged->chunk(1000);
                foreach($policies as $records) {
                    foreach($records as $policy)
                    {
                        $policy_id = $policy->id;  

                        $policyActionEndorse = policyAction::where('policy_id', $policy_id)->where('status', 'ISSUED')->where('transaction_type', 'ENDORSE')->get();  
                        foreach($policyActionEndorse as $policyActionEndorses){
                        $policyAction = policyActionEndorse::where('policy_id', $policy_id)->where('status', 'ISSUED')->get();  

                        foreach($policyAction as $policyActions){

                        $termId = isset($policyActions->term_id ) ? $policyActions->term_id : 0;
                        $actionId = isset($policyActions->id ) ? $policyActions->id : 0;
                        
                        $check_invoice_exists = Ledger::where('policy_id', $policy_id)
                        ->where('action_id', $policyActions->id)
                        ->where('trans_type', 'Invoice')
                        // ->where('invoice_date', $date)
                        ->count();
                        echo  'MM'.$policyActions->id.'AM'.$check_invoice_exists.'\n' ;
                        // premium_freq 5 (QUARTERLY) added — this gate only ever
                        // admitted 1 (MONTHLY), so an ENDORSE on a quarterly
                        // DomCom policy never raised an invoice at all.
                        if($check_invoice_exists ==0 && ($policy->premium_freq == 1 || $policy->premium_freq == 5)){
                            $ledger = Ledger::where('policy_id', $policy_id)->orderBy('id', 'DESC')->where('trans_type', 'Invoice')->first(array('invoice_no', 'banking_id', 'invoice_date'));
                            $ledger_count = Ledger::where('policy_id', $policy_id)->where('trans_type', 'Invoice')->count();
                            // if($ledger == NULL)
                            // {
                            //     $ledger = LedgerArchive::where('policy_id', $policy_id)->orderBy('id', 'DESC')->where('trans_type', 'Invoice')->first(array('invoice_no', 'banking_id', 'invoice_date'));
                            //     $ledger_count = LedgerArchive::where('policy_id', $policy_id)->where('trans_type', 'Invoice')->count();
                            //     //$created_date = Carbon::parse($ledger->invoice_date)->addMonthsNoOverflow()->format('Y-m-d');.
                            // }
                             $policy = Policy::where('id', $policy_id)->first();
                             if($ledger != NULL)
                            {
                                 $invoice_no = $ledger->invoice_no;
                                 $invoice_no++;
                                 $banking_id = $ledger->banking_id;
 
                            } else {
                                 $invoice_no = $policy->policyNumber.'-'.sprintf('%03d', 1);
                                 $banking_id = CustomerBanking::where('customer_id', $policy->customer_id)->first(array('id'));
                                 if($banking_id != NULL)
                                     $banking_id = $banking_id->id;
                                 else
                                     $banking_id = NULL;
                            }
                            $balance = 0;
                             $balance = Ledger::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
                            if ($balance != null) {
                                $balance = $balance->balance;
                            } else {
                                //$balance = LedgerArchive::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
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
                            $record['policy_id'] = $policy->id;
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

                            $amt = str_replace(',', '',number_format(((float)$policyActions->premium - (float)$policy->vat), 2));
                            $record['debit'] = str_replace(',', '',$amt);
                            $balance = number_format(((float)str_replace(',', '',$balance) - (float)str_replace(',', '',$amt)), 2);
                            $record['balance'] = str_replace(',', '',$balance);
                            $data[] = $record;


                            //----SUB-LEDGER
                            $subRecord['customer_id'] = $policy->customer_id;
                            $subRecord['account_id'] = NULL;
                            $subRecord['policy_id'] = $policy->id;
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
                            $record['policy_id'] = $policy->id;
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

                            $record['debit'] = str_replace(',', '',$amt);
                            $balance = str_replace(',', '',number_format(((float)str_replace(',', '',$balance) - (float)str_replace(',', '',$amt)), 2));
                            $record['balance'] = str_replace(',', '',$balance);

                            $data[] = $record;

                            //----SUB-LEDGER

                            $subRecord = array();

                            $subRecord['customer_id'] = $policy->customer_id;
                            $subRecord['account_id'] = NULL;
                            $subRecord['policy_id'] = $policy->id;
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
                            $record['policy_id'] = $policy->id;
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

                            $amt = number_format(((float)str_replace(',', '',$policyActions->premium) - (float)str_replace(',', '',$policy->vat)), 2);

                            $record['debit'] = $policyActions->premium;
                            $record['balance'] = str_replace(',', '',$balance);

                            $data[] = $record;

                            //----SUB-LEDGER

                            $subRecord = array();
                            $subRecord['customer_id'] = $policy->customer_id;
                            $subRecord['account_id'] = NULL;
                            $subRecord['policy_id'] = $policy->id;
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
                            echo "Invoices  ".$policyActions->id."Count".$check_invoice_exists;

                            Ledger::insert($data);
                            SubLedger::insert($subData);
                            Log::info('Invoices data',$data);
                        
                    }
                        }
                       
                    }
                }
            }
                $cron->end = Carbon::now();
                $cron->save();
                Log::info('Invoices end');
            
        // } catch (\Exception $e) {
        //     return false;
        // }
    }
}
