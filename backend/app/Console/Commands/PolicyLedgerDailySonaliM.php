<?php


namespace AlphaDirect\Console\Commands;

use AlphaDirect\CustomerBanking;
use AlphaDirect\LedgerSonali as Ledger;
use AlphaDirect\PolicySonali as Policy;
use AlphaDirect\PaymentTransactionSonali as PaymentTransaction;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\Region;
use AlphaDirect\SubLedgerSonali as SubLedger;
use Carbon\Carbon;
use Http\Client\Exception;
use Illuminate\Console\Command;
use AlphaDirect\Mail\LedgerDailyReport;
use AlphaDirect\Mail\SendPO;
use Illuminate\Support\Facades\Mail;
use AlphaDirect\Models\CronStatus;
use DB;
class PolicyLedgerDailySonaliM extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'PolicyLedgerDailySonali:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'New Policy Ledger Job';

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
        $cron->name  = "PolicyLedgerDailySonali:cron";
        $cron->start = \Carbon\Carbon::now();
        $today = Carbon::today();
        //$now = Carbon::now()->toDateTimeString();
        $dt  = Carbon::now();
        $now = $dt->toDateString();


        /*$today = '2023-11-20';//Carbon::today();
        $now   = '2024-01-20';*/
        $toDayDate = date('d');
        $invoices = array();

       
        ini_set('max_execution_time', 0);
        try{
            $ledger = Policy::select('id')
            ->where('product_id',3)
            ->whereIn('id',[8532,8539,8541,8546,8560,8564,8575,8577,8582,8586,8588,8613,8616,8623,8625,
            8626,8627,8632,8634,8637,8644,8670,8679,8680,8697,8698,8701,8703,8709,8742,
            8744,8745,8746,8748,8752,8759,8763,8764,8767,8768,8770,8775,8777,8778,8781,
            8794,8796,8799,8803,8809])
            ->limit(100)
            ->get()
            ->toArray();
            
            if(count($ledger) > 0)
            {
                foreach ($ledger as $lData) 
                {
                    $policyId = $lData['id'];
                    $policies = Policy::join('policy_term', 'policy_term.policy_id', '=', 'policies_sonali.id')
                    // ->whereMonth('policies_sonali.created_at',2)
                    // ->whereYear('policies_sonali.created_at',2021)
                    /*->whereDay('policies_sonali.created_at', $toDayDate)
                    ->orwhereDay('policies_sonali.billingStartDate', $toDayDate)   */         
                    ->Where('product_id',3)
                    ->Where('policies_sonali.id',$policyId)
                    ///->Where('policy_term.id',60)
                    //->Where('policy_term.id',327)
                    ->select(array(                
                        'policies_sonali.id',                 
                        'policies_sonali.customer_id', 
                        'policies_sonali.product_id', 
                        'policies_sonali.plan_id', 
                        'policy_term.frequency as premium_freq', 
                        'policies_sonali.created_at', 
                        'policies_sonali.updated_at', 
                        'policies_sonali.first_premium', 
                        'policies_sonali.premium', 
                        'policies_sonali.vat', 
                        'policies_sonali.vat_percent', 
                        'policies_sonali.policyNumber', 
                        'policies_sonali.policyActivatedDate', 
                        'policies_sonali.is_sys_act_generated', 
                        'policies_sonali.billingStartDate', 
                        'policies_sonali.status',
                        'policies_sonali.term_start_date',
                        'policies_sonali.term_end_date',
                        'policy_term.id as pTID', 
                        'policy_term.id AS termId',
                        'policy_term.term_start_date AS termSData',
                        'policy_term.term_end_date AS termEData',
                        'policy_term.premium AS policyPremiumTerm',
                        'policy_term.vat AS policyPremiumVat',
                        'policy_term.policyActivatedDate AS policyTermActivatedDate',
                        'policy_term.billing_start_date AS policyTermbillingStartDate',
                        'policy_term.first_premium AS policyTermFirstPremium', ))
                    //->limit(100)
                    ->orderBy('policy_term.term_start_date','ASC')
                    ->get()
                    ->chunk(20);
                        
                foreach($policies as $records)
                {
                    foreach($records as $k => $policy)
                    {
                        
                        sleep(1);
                        //\Illuminate\Support\Facades\DB::beginTransaction();    
                        

                        $createdDate        = date("d-m-Y", strtotime($policy->created_at));
                        $policyActivatedDate = isset($policy->policyActivatedDate) ? date("d-m-Y", strtotime($policy->policyActivatedDate)) : '';
                        $billingStartDate = isset($policy->billingStartDate) ? date("d-m-Y", strtotime($policy->billingStartDate)) : '';
                        $termStartDate = isset($policy->termSData) ? date("d-m-Y", strtotime($policy->termSData)) : $billingStartDate;
                        $termEndDate   = isset($policy->termEData) ? date("d-m-Y", strtotime($policy->termEData)) : '';
                        $policyTermActivatedDate = isset($policy->policyTermActivatedDate) ? date("d-m-Y", strtotime($policy->policyTermActivatedDate)) : '';
                        $policyTermbillingStartDate = isset($policy->policyTermbillingStartDate) ? date("d-m-Y", strtotime($policy->policyTermbillingStartDate)) : '';
                         
                        $first_invoice = 0;
                        //echo "\n".$policy->id;
                        $ledger = Ledger::where('policy_id', $policy->id)                        
                        ->where('trans_type', 'Invoices')
                        ->orderBy('id', 'DESC')
                        ->first(array('invoice_no', 'banking_id', 'invoice_date'));
                        $ledgerCount   = Ledger::where('policy_id', $policy->id)
                        ->where('trans_type', 'Invoices')->count();
                       
                        $diffInMonths = Carbon::parse($termEndDate)->diffInMonths(Carbon::parse($termStartDate));
                        
                        /*if($diffInMonths == 0)
                        {
                            $diffInMonths = $this->getDateDiff($termStartDate,$termEndDate,'Y');
                            if($diffInMonths > 0)
                                $diffInYears = $diffInMonths;

                        }*/
                        if($ledgerCount > 0)
                        {
                            $diffInMonths = $diffInMonths - $ledgerCount;;
                        }

                        //echo $diffInMonths."=".$diffInYears;
                        //echo $diffInMonths;
                        $vatCheckDate = '2021-03-31';
                        $vatCheckDateCount = 0;
                        $is_policy_renewed = 0;

                        $cancelled_status = true ;

                        $premium = $policy->premium;
                        $vat     = $policy->vat;
                        $billingSameAsCreatedDate = 0;
                        $original_created_date    = $createdDate;
                        
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

                        if($createdDate > $vatCheckDate && $policy->vat_percent == 12)
                        {
                            $vatCheckDateCount = 1;
                            if($policy->product_id == 3)
                            {
                                $product = Product::where('id', $policy->product_id)->first(array('region_id'));
                                $region_vat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
                                $policyPremium = $policy->premium;
                                if($policy->premium_freq == 1)
                                    $policyPremium = $policyPremium/1.08; //Remove only in case of monthly frequency
                                $policyPremium = $policyPremium/1.12;

                                $premiumWithoutVAT = $policyPremium;

                                $policyPremium = $premiumWithoutVAT*(1 + ($region_vat/100)); //Add new VAT : 14%
                                if($policy->premium_freq == 1)
                                    $policyPremium = $policyPremium*1.08; // Add service Tax 1.08
                                $policy->premium = $policyPremium;
                                $policy->vat = number_format($policyPremium - $premiumWithoutVAT, 2);
                                
                            } else {
                                $plan = Productplan::where('id', $policy->plan_id)->first(array('premium'));
                                $policy->vat = number_format($policy->premium - $plan->premium, 2);
                            }
                        }

                        $proRataStatus = 0;
                        if($policy->premium_freq  == 1  && $policy->product_id == 3 ) //Montlhy Installments
                        {

                            if($policy->first_premium == NULL || $policy->first_premium == "" || $policy->first_premium < 1) //When First Premium is NULL
                            {
                                $policy->first_premium_wvat = $policy->premium;
                                $policy->premium = $policy->first_premium_wvat;
                                $firstPremium    = $policy->first_premium_wvat;
                            }  
                            else
                            {
                                $firstPremium    = $policy->premium;
                            } 

                            if($k == 0)
                            {
                                
                                if($policyTermbillingStartDate >= $policyTermActivatedDate)
                                {
                                    $proRataStatus = 1;
                                    $proRataPremium = $policy->premium_freq;  
                                    $diffInMonths =  12;
                                }
                                else
                                {
                                    $diffInMonths =  11; 
                                }
                                
                            }
                            else
                            {
                                    $diffInMonths =  11; 
                            }

                            $firstPremium = $firstPremium/1.08;
                            $firstPremium = $firstPremium/(1 + ($policy->vat_percent/100));
                            $policy->vat = number_format($policy->first_premium_wvat - $firstPremium, 2);
                           
                        }
                        else if($policy->premium_freq   == 2  && $policy->product_id == 3 ) // 3 Installments, Motor Comp
                        {
                            $diffInMonths = 3;
                        }
                        else if($policy->premium_freq   == 3  && $policy->product_id == 3 ) // Yearly Installment, Motor Comp
                        {
                            $diffInMonths = 1;
                        }                        

                        $balance = Ledger::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
                        if ($balance != null) {
                            $balance = $balance->balance;
                        } else {
                            $balance = 0;
                        }
                       
                        
                        for ($i=0; $i <= $diffInMonths ; $i++) { 
                            
                            $finalPremium = '';
                            $newInvoiceDatePro = '';
                            $output = [];
                            
                            
                            //$newInvoiceDate = date("Y-m-d",  strtotime("+".$i." month", strtotime($policyTermbillingStartDate)));  
                            if($policy->premium_freq  == 1)
                            {
                                $newInvoiceDate = date("Y-m-d",  strtotime("+".$i." month", strtotime($termStartDate)));  
                            }
                            else
                            {
                                $newInvoiceDate = date("Y-m-d",  strtotime($termStartDate));
                            }
                            
                            //$newInvoiceDate = date("Y-m-d",  strtotime($createdDate));

                            $finalPremium = $policy->policyPremiumTerm;                               
                            $output[] = $newInvoiceDate;
                            
                            
                            //Check if Invoice with same date already exists
                            $checkInvoiceExists = Ledger::where('policy_id', $policy->id)
                            ->where('trans_type', 'Invoices')
                            ->whereDate('invoice_date', $newInvoiceDate)->count();
                            echo "\n".$newInvoiceDate." => ".$checkInvoiceExists;  
                            
                                if($proRataStatus == 1 && $k == 0 && $i == 0 && $checkInvoiceExists == 0)
                                {
                                    
                                    $finalPremium = $policy->policyTermFirstPremium;
                                    $newInvoiceDate = date("Y-m-d",strtotime($policyTermActivatedDate));                                 
                                    
                                    /* $amt = str_replace(',', '',number_format(((float)$policy->policyPremiumTerm - (float)$policy->policyPremiumVat), 2));*/
                                    
                                
                                    $balance = '';/*number_format(((float)str_replace(',', '',$balance) - (float)str_replace(',', '',$finalPremium)), 2);*/
                                    
                                    $insurancePremium = str_replace(',', '',number_format(((float)$policy->premium - (float)$policy->vat), 2));
                                    $insuranceVat = $policy->vat;

                                    $transactions = PaymentTransaction::where('policyNumber', $policy->policyNumber)->where('is_ledger', 0)->where('amount', '!=', 1)->orderBy('paymentDate', 'asc')->get();
                                    

                                    $record = new Ledger;
                                    $record->customer_id = $policy->customer_id;
                                    $record->account_id = NULL;
                                    $record->policy_id = $policy->id;
                                    $record->claim_id = NULL;
                                    $record->banking_id = $banking_id;
                                    $record->account_name = NULL;
                                    $record->accounting_date = date("Y-m-d", strtotime($policy->created_at));
                                    //
                                    $record->amount_type = NULL;
                                    $record->trans_ref = NULL;
                                    $record->orig_trans = NULL;
                                    $record->unallocated = NULL;
                                    $record->system_date = date("Y-m-d", strtotime($policy->created_at));
                                    $record->trans_sub_type = NULL;
                                    $record->eff_date = date("Y-m-d", strtotime($policy->created_at));
                                    $record->invoice_file = NULL;
                                    $record->invoice_date = $newInvoiceDate;
                                    $record->invoice_no = $invoice_no;
                                    $record->invoice_amount = NULL;
                                    $record->premium = $finalPremium;
                                    $record->due_amount = NULL;
                                    $record->pmts_adjust = NULL;
                                    $record->due_date = NULL;
                                    $record->status = 'Pending';
                                    $record->debit = $finalPremium;
                                    $record->credit = NULL;
                                    $record->balance = NULL;
                                    $record->trans_type = 'Invoices';
                                    $record->premium_freq = $policy->premium_freq;
                                    $record->prorata_status = 1;
                                    $record->save();

                                    
                                    //----SUB-LEDGER
                                    $subRecord[0]['customer_id'] = $policy->customer_id;
                                    $subRecord[0]['account_id'] = NULL;
                                    $subRecord[0]['policy_id'] = $policy->id;
                                    $subRecord[0]['claim_id'] = NULL;
                                    $subRecord[0]['banking_id'] = $banking_id;
                                    $subRecord[0]['account_name'] = 'Insurance Sales A/C';
                                    $subRecord[0]['accounting_date'] = date("Y-m-d", strtotime($policy->created_at));
                                    $subRecord[0]['trans_type'] = 'Insurance Premium';
                                    $subRecord[0]['trans_ref'] = NULL;
                                    $subRecord[0]['system_date'] = date("Y-m-d", strtotime($policy->created_at));
                                    $subRecord[0]['credit'] = $insurancePremium;
                                    $subRecord[0]['debit'] = NULL;


                                    $subRecord[1]['customer_id'] = $policy->customer_id;
                                    $subRecord[1]['account_id'] = NULL;
                                    $subRecord[1]['policy_id'] = $policy->id;
                                    $subRecord[1]['claim_id'] = NULL;
                                    $subRecord[1]['banking_id'] = $banking_id;
                                    $subRecord[1]['account_name'] = 'VAT Control A/C';
                                    $subRecord[1]['accounting_date'] = date("Y-m-d", strtotime($policy->created_at));
                                    $subRecord[1]['trans_type'] = 'VAT on Insurance Premium';
                                    $subRecord[1]['trans_ref'] = NULL;
                                    $subRecord[1]['system_date'] = date("Y-m-d", strtotime($policy->created_at));
                                    $subRecord[1]['credit'] = $insuranceVat;
                                    $subRecord[1]['debit'] = NULL;

                                    

                                    
                                    $subRecord[2]['customer_id'] = $policy->customer_id;
                                    $subRecord[2]['account_id'] = NULL;
                                    $subRecord[2]['policy_id'] = $policy->id;
                                    $subRecord[2]['claim_id'] = NULL;
                                    $subRecord[2]['banking_id'] = $banking_id;
                                    $subRecord[2]['account_name'] = 'Accounts Receivable A/C';
                                    $subRecord[2]['accounting_date'] = date("Y-m-d", strtotime($policy->created_at));
                                    $subRecord[2]['trans_type'] = 'Accounts Receivable';
                                    $subRecord[2]['trans_ref'] = NULL;
                                    $subRecord[2]['system_date'] = date("Y-m-d", strtotime($policy->created_at));
                                    $subRecord[2]['credit'] = NULL;
                                    $subRecord[2]['debit'] = $policy->premium;

                                    $subData = $subRecord;
                                    SubLedger::insert($subData);
                                    
                                    $invoice_no++;                               
                                }
                                else if($checkInvoiceExists == 0  && 
                                $policy->product_id == 3 && 
                                $newInvoiceDate <= $now  && 
                                date('Y-m', strtotime($newInvoiceDate)) < date('Y-m', strtotime($termEndDate))  )
                                {
                                    
                                /* $amt = str_replace(',', '',number_format(((float)$policy->policyPremiumTerm - (float)$policy->policyPremiumVat), 2));*/
                                    //echo " => ".$newInvoiceDate;
                                
                                    $balance = '';/*number_format(((float)str_replace(',', '',$balance) - (float)str_replace(',', '',$finalPremium)), 2);*/
                                    
                                    $insurancePremium = str_replace(',', '',number_format(((float)$policy->premium - (float)$policy->vat), 2));
                                    $insuranceVat = $policy->vat;

                                    $transactions = PaymentTransaction::where('policyNumber', $policy->policyNumber)->where('is_ledger', 0)->where('amount', '!=', 1)->orderBy('paymentDate', 'asc')->get();
                                    

                                    $record = new Ledger;
                                    $record->customer_id = $policy->customer_id;
                                    $record->account_id = NULL;
                                    $record->policy_id = $policy->id;
                                    $record->claim_id = NULL;
                                    $record->banking_id = $banking_id;
                                    $record->account_name = NULL;
                                    $record->accounting_date = $newInvoiceDate;
                                    //
                                    $record->amount_type = NULL;
                                    $record->trans_ref = NULL;
                                    $record->orig_trans = NULL;
                                    $record->unallocated = NULL;
                                    $record->system_date = $newInvoiceDate;
                                    $record->trans_sub_type = NULL;
                                    $record->eff_date = $newInvoiceDate;
                                    $record->invoice_file = NULL;
                                    $record->invoice_date = $newInvoiceDate;
                                    $record->invoice_no = $invoice_no;
                                    $record->invoice_amount = NULL;
                                    $record->premium = $finalPremium;
                                    $record->due_amount = NULL;
                                    $record->pmts_adjust = NULL;
                                    $record->due_date = NULL;
                                    $record->status = 'Pending';
                                    $record->debit = $finalPremium;
                                    $record->credit = NULL;
                                    $record->balance = NULL;
                                    $record->trans_type = 'Invoices';
                                    $record->premium_freq = $policy->premium_freq;
                                    $record->prorata_status = 0;
                                    $record->save();

                                    
                                    //----SUB-LEDGER
                                    $subRecord[0]['customer_id'] = $policy->customer_id;
                                    $subRecord[0]['account_id'] = NULL;
                                    $subRecord[0]['policy_id'] = $policy->id;
                                    $subRecord[0]['claim_id'] = NULL;
                                    $subRecord[0]['banking_id'] = $banking_id;
                                    $subRecord[0]['account_name'] = 'Insurance Sales A/C';
                                    $subRecord[0]['accounting_date'] = date("Y-m-d", strtotime($policy->created_at));
                                    $subRecord[0]['trans_type'] = 'Insurance Premium';
                                    $subRecord[0]['trans_ref'] = NULL;
                                    $subRecord[0]['system_date'] = date("Y-m-d", strtotime($policy->created_at));
                                    $subRecord[0]['credit'] = $insurancePremium;
                                    $subRecord[0]['debit'] = NULL;


                                    $subRecord[1]['customer_id'] = $policy->customer_id;
                                    $subRecord[1]['account_id'] = NULL;
                                    $subRecord[1]['policy_id'] = $policy->id;
                                    $subRecord[1]['claim_id'] = NULL;
                                    $subRecord[1]['banking_id'] = $banking_id;
                                    $subRecord[1]['account_name'] = 'VAT Control A/C';
                                    $subRecord[1]['accounting_date'] = date("Y-m-d", strtotime($policy->created_at));
                                    $subRecord[1]['trans_type'] = 'VAT on Insurance Premium';
                                    $subRecord[1]['trans_ref'] = NULL;
                                    $subRecord[1]['system_date'] = date("Y-m-d", strtotime($policy->created_at));
                                    $subRecord[1]['credit'] = $insuranceVat;
                                    $subRecord[1]['debit'] = NULL;

                                    

                                    
                                    $subRecord[2]['customer_id'] = $policy->customer_id;
                                    $subRecord[2]['account_id'] = NULL;
                                    $subRecord[2]['policy_id'] = $policy->id;
                                    $subRecord[2]['claim_id'] = NULL;
                                    $subRecord[2]['banking_id'] = $banking_id;
                                    $subRecord[2]['account_name'] = 'Accounts Receivable A/C';
                                    $subRecord[2]['accounting_date'] = date("Y-m-d", strtotime($policy->created_at));
                                    $subRecord[2]['trans_type'] = 'Accounts Receivable';
                                    $subRecord[2]['trans_ref'] = NULL;
                                    $subRecord[2]['system_date'] = date("Y-m-d", strtotime($policy->created_at));
                                    $subRecord[2]['credit'] = NULL;
                                    $subRecord[2]['debit'] = $policy->premium;

                                    $subData = $subRecord;
                                    //SubLedger::insert($subData);
                                    
                                    $invoice_no++;
                                }
                        }
                        
                    }
                }
            }
        }
            return 0;
        }
        catch(Exception $e){
            //\Illuminate\Support\Facades\DB::rollBack();
            return $e->getMessage()
            ;
        }
    }
    public function getDateDiff($date1='',$date2='',$flag='')
    {
        $diff = abs(strtotime($date2)-strtotime($date1));
        $years = floor($diff / (365*60*60*24));
        $months = floor(($diff - $years * 365*60*60*24) / (30*60*60*24));

        $days = floor(($diff - $years * 365*60*60*24 - $months*30*60*60*24) / (60*60*24));
        $output = '';
        if($flag == 'D')
        {
            $output = $days;
        }
        else if($flag == 'M')
        {
            $output = $months;
        }
        else
        {
            $output = $years;
        }

        return $output;
    }
}
