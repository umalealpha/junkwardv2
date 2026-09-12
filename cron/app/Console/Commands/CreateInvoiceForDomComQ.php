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
use Log;
class CreateInvoiceForDomComQ extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:domcomq';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Invoices DomCom Quarterly';

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
                $cron->name = "Invoices DomCom  Quarterly Start";
                $cron->start = Carbon::now();
                $cron->save();
                Log::info('Invoices DomCom Quarterly started');

                $today = Carbon::today();            
                $date = Carbon::now()->format('Y-m-d');
                $policiesbyCreteated = Policy::where('premium_freq', 5) 
                ->whereIn('product_id',[7,8])
                ->where('status', 1)
                //->where('id', 101407)
                ->select(array('id', 'customer_id', 'product_id', 'plan_id', 'premium_freq', 'created_at', 'updated_at', 'first_premium', 'annual_premium' , 'premium', 'vat', 'vat_percent', 'policyNumber', 'policyActivatedDate', 'is_sys_act_generated', 'billingStartDate', 'status'))
                ->get()->chunk(1000);
                $policies = $policiesbyCreteated;
                
                
                foreach($policies as $records) {
                    foreach($records as $policy)
                    {
                        $policy_id = $policy->id;  
                        $policy_status = $policy->status;  

                        $created_at = $policy->created_at; 
                        $billingStartDate = $policy->billingStartDate; 

                        $policyAction = PolicyActions::where('policy_id', $policy_id)
                        ->where('status', 'ISSUED')
                        ->where('action_type', 'NEWBUSINESS')
                        ->first();  
                        
                        $policyActionStatus = isset($policyAction->status ) ? $policyAction->status : '';
                        $termId = isset($policyAction->term_id ) ? $policyAction->term_id : 0;
                        $actionId = isset($policyAction->id ) ? $policyAction->id : 0;
                        $policyPremium = isset($policyAction->premium ) ? $policyAction->premium : 0;
                        
                        $effective_from = isset($policyAction->effective_from ) ? $policyAction->effective_from : '';
                        $effective_to = isset($policyAction->effective_to ) ? $policyAction->effective_to : '';

                        $check_invoice_exists = Ledger::where('policy_id', $policy_id)
                        ->where('action_id', $actionId)
                        ->where('trans_type', 'Invoice')
                        // ->where('invoice_date', $date)
                        ->count();
                        
                        if($check_invoice_exists == 1 )
                        {
                            $invoice_start_date =   date('Y-m-d', strtotime("+3 months", strtotime($effective_from)));
                            $invoice_end_date   =   date('Y-m-d', strtotime("+3 months", strtotime($effective_to)));
                        }
                        else
                        {
                            $check_invoice_exists = Ledger::where('policy_id', $policy_id)
                            ->where('trans_type', 'Invoice')
                            ->first();

                            $invoice_start_date =   date('Y-m-d', strtotime("+3 months", strtotime($check_invoice_exists->invoice_date)));
                            $invoice_end_date   =   date('Y-m-d', strtotime("+6 months", strtotime($check_invoice_exists->invoice_date)));
                        }

                        $ledger = Ledger::where('policy_id', $policy_id)->orderBy('id', 'DESC')->where('trans_type', 'Invoice')->first(array('invoice_no', 'banking_id', 'invoice_date'));
                            $ledger_count = Ledger::where('policy_id', $policy_id)->where('trans_type', 'Invoice')->count();
                            if($ledger == NULL)
                            {
                                $ledger = LedgerArchive::where('policy_id', $policy_id)->orderBy('id', 'DESC')->where('trans_type', 'Invoice')->first(array('invoice_no', 'banking_id', 'invoice_date'));
                                $ledger_count = LedgerArchive::where('policy_id', $policy_id)->where('trans_type', 'Invoice')->count();
                                //$created_date = Carbon::parse($ledger->invoice_date)->addMonthsNoOverflow()->format('Y-m-d');.
                            }
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
                            $balance = Ledger::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
                            if ($balance != null) {
                                $balance = $balance->balance;
                            } else {
                                $balance = LedgerArchive::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
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
                            $record['premium'] = $policyPremium;
                            $record['other_charges'] = NULL;
                            $record['due_amount'] = NULL;
                            $record['pmts_adjust'] = NULL;
                            $record['due_date'] = NULL;
                            $record['status'] = 'Pending';
                            $record['credit'] = NULL;

                            $amt = str_replace(',', '',number_format(((float)$policyPremium - (float)$policy->vat), 2));
                            $record['debit'] = str_replace(',', '',$amt);
                            $balance = number_format(((float)str_replace(',', '',$balance) - (float)str_replace(',', '',$amt)), 2);
                            $record['balance'] = str_replace(',', '',$balance);
                            $record['action_by'] = 'sonali-invoice';

                        
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
                            $record['premium'] = $policyPremium;
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
                            $record['action_by'] = 'sonali-invoice';

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
                            $record['invoice_date'] = Carbon::parse($invoice_start_date);
                            $record['invoice_no'] = $invoice_no;
                            $record['invoice_amount'] = $policyPremium;
                            $record['premium'] = $policyPremium;
                            $record['other_charges'] = NULL;
                            $record['due_amount'] = $policyPremium;
                            $record['pmts_adjust'] = NULL;
                            $record['due_date'] = NULL;
                            $record['status'] = 'Pending';
                            $record['credit'] = NULL;

                            $amt = number_format(((float)str_replace(',', '',$policyPremium) - (float)str_replace(',', '',$policy->vat)), 2);

                            $record['debit'] = $policyPremium;
                            $record['balance'] = str_replace(',', '',$balance);
                            $record['action_by'] = 'sonali-invoice';

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
                            $subRecord['debit'] = $policyPremium;

                            $subData[] = $subRecord;

                            $subData[0]['trans_ref'] = $invoice_no;
                            $subData[1]['trans_ref'] = $invoice_no;
                            $subData[2]['trans_ref'] = $invoice_no;
                            //-------------------------------INVOICE-------------------------------------

                            if($policyActionStatus == 'ISSUED' && $policy_status == 1)
                            {
                                Ledger::insert($data);
                                SubLedger::insert($subData);
                            }
                            
                            Log::info('Invoices Quarterly data',$data);
                          
                    }
                }
                $cron->end = Carbon::now();
                $cron->save();
                Log::info('Invoices end Quarterly',$data);
            
        } catch (\Exception $e) {
            return false;
        }
    }
}
