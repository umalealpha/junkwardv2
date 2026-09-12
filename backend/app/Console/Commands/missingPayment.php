<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\CustomerBanking;
use AlphaDirect\LedgerSonali as Ledger;
use AlphaDirect\PaymentTransactionSonali as PaymentTransaction;
use AlphaDirect\PolicySonali as Policy;
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
use AlphaDirect\Models\PaymentTransactionArchiveSonali as PaymentTransactionArchive;
use DB;

class missingPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'missingPayment:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'missingPayment';

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
          $cron->name  = "missingPayment:cron";
          $cron->start = \Carbon\Carbon::now();

            
            //$policyId = 10516;
            $ledger = Ledger::get('policy_id','')
            ->groupBy('policy_id')
            ->whereIn('policy_id',[8532,8539,8541,8546,8560,8564,8575,8577,8582,8586,8588,8613,8616,8623,8625,
            8626,8627,8632,8634,8637,8644,8670,8679,8680,8697,8698,8701,8703,8709,8742,
            8744,8745,8746,8748,8752,8759,8763,8764,8767,8768,8770,8775,8777,8778,8781,
            8794,8796,8799,8803,8809])
            ->toArray();
            
            if(count($ledger) > 0)
            {
                foreach ($ledger as $lData) 
                {
                    $policyId = $lData[0]['policy_id'];
                    $getLedgerData = Ledger::where('policy_id',$policyId )->get()->toArray();
                    if(count($getLedgerData) > 0)
                    {
                        foreach ($getLedgerData as $payment) {
                            $paymentDate = $payment['invoice_date'];

                            $getYear  =  date('Y', strtotime($paymentDate));
                            $getMonth =  date('m', strtotime($paymentDate));
                            $getDay   =  date('d', strtotime($paymentDate));

                            $getDoublePayment = PaymentTransaction::where('policy_id',$policyId)
                            ->whereYear('new_payment_date', $getYear)
                            ->whereMonth('new_payment_date', $getMonth)
                            ->whereRaw('LOWER(status) = (?)', 'success')
                            ->get()->toArray();

                            $getDoublePaymentFromTrans = PaymentTransactionArchive::where('policy_id',$policyId)
                            ->whereYear('new_payment_date', $getYear)
                            ->whereMonth('new_payment_date', $getMonth)
                            ->whereRaw('LOWER(status) = (?)', 'success')
                            ->get()->toArray();
                            
                            if(count($getDoublePayment) > 1)
                            {
                                $exits = $this->checkExits($getYear,$getMonth,$policyId);                        
                                if(count($exits) != count($getDoublePayment))
                                {
                                    for ($i=1; $i <count($getDoublePayment) ; $i++) { 


                                        $policy = Policy::where('id', $policyId)->first(array('id','customer_id','premium_freq'));

                                        $banking_id = CustomerBanking::where('customer_id', $policy->customer_id)->first(array('id'));

                                        if($banking_id != NULL)
                                            $banking_id = $banking_id->id;
                                        else
                                            $banking_id = NULL;

                                        $invoice_no = $policy->policyNumber.'-'.sprintf('%03d', 1);

                                        $record = new Ledger;
                                        $record->customer_id = $policy->customer_id;
                                        $record->account_id = NULL;
                                        $record->policy_id = $policyId;
                                        $record->claim_id = NULL;
                                        $record->banking_id = $banking_id;
                                        $record->account_name = NULL;
                                        $record->accounting_date = date("Y-m-d", strtotime($getDoublePayment[$i]['new_payment_date']));
                                        //
                                        $record->amount_type = NULL;
                                        $record->trans_ref  = $getDoublePayment[$i]['referenceNumber'];
                                        $record->orig_trans = $getDoublePayment[$i]['referenceNumber'];
                                        $record->unallocated = NULL;
                                        $record->system_date = date("Y-m-d", strtotime($getDoublePayment[$i]['new_payment_date']));
                                        $record->trans_sub_type = NULL;
                                        $record->eff_date = date("Y-m-d", strtotime($getDoublePayment[$i]['new_payment_date']));
                                        $record->invoice_file = NULL;
                                        $record->invoice_date = date("Y-m-d", strtotime($getDoublePayment[$i]['new_payment_date']));;
                                        $record->invoice_no = '';
                                        $record->invoice_amount = $getDoublePayment[$i]['amount'];
                                        $record->premium = $getDoublePayment[$i]['amount'];
                                        $record->due_amount = NULL;
                                        $record->pmts_adjust = NULL;
                                        $record->due_date = NULL;
                                        $record->status = 'Paid';
                                        $record->debit = NULL;
                                        $record->credit = $getDoublePayment[$i]['amount'];
                                        $record->balance = NULL;
                                        $record->trans_type = 'Payment';
                                        $record->premium_freq = $policy->premium_freq;
                                        $record->prorata_status = '';
                                        $record->save();


                                        $record = new Ledger;
                                        $record->customer_id = $policy->customer_id;
                                        $record->account_id = NULL;
                                        $record->policy_id = $policyId;
                                        $record->claim_id = NULL;
                                        $record->banking_id = $banking_id;
                                        $record->account_name = NULL;
                                        $record->accounting_date = date("Y-m-d", strtotime($getDoublePayment[$i]['new_payment_date']));
                                        //
                                        $record->amount_type = NULL;
                                        $record->trans_ref  = $getDoublePayment[$i]['referenceNumber'];
                                        $record->orig_trans = $getDoublePayment[$i]['referenceNumber'];
                                        $record->unallocated = NULL;
                                        $record->system_date = date("Y-m-d", strtotime($getDoublePayment[$i]['new_payment_date']));
                                        $record->trans_sub_type = NULL;
                                        $record->eff_date = date("Y-m-d", strtotime($getDoublePayment[$i]['new_payment_date']));
                                        $record->invoice_file = NULL;
                                        $record->invoice_date = date("Y-m-d", strtotime($getDoublePayment[$i]['new_payment_date']));;
                                        $record->invoice_no = '';
                                        $record->invoice_amount = $getDoublePayment[$i]['amount'];
                                        $record->premium = $getDoublePayment[$i]['amount'];
                                        $record->due_amount = NULL;
                                        $record->pmts_adjust = NULL;
                                        $record->due_date = NULL;
                                        $record->status = 'Paid';
                                        $record->debit = $getDoublePayment[$i]['amount'];
                                        $record->credit = NULL;
                                        $record->balance = NULL;
                                        $record->trans_type = 'Invoices';
                                        $record->premium_freq = $policy->premium_freq;
                                        $record->prorata_status = '';
                                        $record->save();
                                    }
                                }
                            }

                            if(count($getDoublePaymentFromTrans) > 1)
                            {
                                $exits = $this->checkExits($getYear,$getMonth,$policyId);                        
                                if(count($exits) != count($getDoublePaymentFromTrans))
                                {
                                    for ($i=1; $i <count($getDoublePaymentFromTrans) ; $i++) { 


                                        $policy = Policy::where('id', $policyId)->first(array('id','customer_id','premium_freq'));

                                        $banking_id = CustomerBanking::where('customer_id', $policy->customer_id)->first(array('id'));

                                        if($banking_id != NULL)
                                            $banking_id = $banking_id->id;
                                        else
                                            $banking_id = NULL;

                                        $invoice_no = $policy->policyNumber.'-'.sprintf('%03d', 1);

                                        $record = new Ledger;
                                        $record->customer_id = $policy->customer_id;
                                        $record->account_id = NULL;
                                        $record->policy_id = $policyId;
                                        $record->claim_id = NULL;
                                        $record->banking_id = $banking_id;
                                        $record->account_name = NULL;
                                        $record->accounting_date = date("Y-m-d", strtotime($getDoublePaymentFromTrans[$i]['new_payment_date']));
                                        //
                                        $record->amount_type = NULL;
                                        $record->trans_ref  = $getDoublePaymentFromTrans[$i]['referenceNumber'];
                                        $record->orig_trans = $getDoublePaymentFromTrans[$i]['referenceNumber'];
                                        $record->unallocated = NULL;
                                        $record->system_date = date("Y-m-d", strtotime($getDoublePaymentFromTrans[$i]['new_payment_date']));
                                        $record->trans_sub_type = NULL;
                                        $record->eff_date = date("Y-m-d", strtotime($getDoublePaymentFromTrans[$i]['new_payment_date']));
                                        $record->invoice_file = NULL;
                                        $record->invoice_date = date("Y-m-d", strtotime($getDoublePaymentFromTrans[$i]['new_payment_date']));;
                                        $record->invoice_no = '';
                                        $record->invoice_amount = $getDoublePaymentFromTrans[$i]['amount'];
                                        $record->premium = $getDoublePaymentFromTrans[$i]['amount'];
                                        $record->due_amount = NULL;
                                        $record->pmts_adjust = NULL;
                                        $record->due_date = NULL;
                                        $record->status = 'Paid';
                                        $record->debit = NULL;
                                        $record->credit = $getDoublePaymentFromTrans[$i]['amount'];
                                        $record->balance = NULL;
                                        $record->trans_type = 'Payment';
                                        $record->premium_freq = $policy->premium_freq;
                                        $record->prorata_status = '';
                                        $record->save();

                                        $record = new Ledger;
                                        $record->customer_id = $policy->customer_id;
                                        $record->account_id = NULL;
                                        $record->policy_id = $policyId;
                                        $record->claim_id = NULL;
                                        $record->banking_id = $banking_id;
                                        $record->account_name = NULL;
                                        $record->accounting_date = date("Y-m-d", strtotime($getDoublePaymentFromTrans[$i]['new_payment_date']));
                                        //
                                        $record->amount_type = NULL;
                                        $record->trans_ref  = $getDoublePaymentFromTrans[$i]['referenceNumber'];
                                        $record->orig_trans = $getDoublePaymentFromTrans[$i]['referenceNumber'];
                                        $record->unallocated = NULL;
                                        $record->system_date = date("Y-m-d", strtotime($getDoublePaymentFromTrans[$i]['new_payment_date']));
                                        $record->trans_sub_type = NULL;
                                        $record->eff_date = date("Y-m-d", strtotime($getDoublePaymentFromTrans[$i]['new_payment_date']));
                                        $record->invoice_file = NULL;
                                        $record->invoice_date = date("Y-m-d", strtotime($getDoublePaymentFromTrans[$i]['new_payment_date']));;
                                        $record->invoice_no = '';
                                        $record->invoice_amount = $getDoublePaymentFromTrans[$i]['amount'];
                                        $record->premium = $getDoublePaymentFromTrans[$i]['amount'];
                                        $record->due_amount = NULL;
                                        $record->pmts_adjust = NULL;
                                        $record->due_date = NULL;
                                        $record->status = 'Paid';
                                        $record->debit = $getDoublePaymentFromTrans[$i]['amount'];
                                        $record->credit = NULL;
                                        $record->balance = NULL;
                                        $record->trans_type = 'Invoices';
                                        $record->premium_freq = $policy->premium_freq;
                                        $record->prorata_status = '';
                                        $record->save();
                                    }
                                }
                            }
                            
                        }
                    }
                }
            }
    }
    public function checkExits($getYear,$getMonth,$policyId)
    {
       $getLedgerData = Ledger::where('policy_id',$policyId )
       ->whereYear('invoice_date',$getYear )
       ->whereMonth('invoice_date',$getMonth )
        ->get()->toArray();
        return $getLedgerData;
    }
}
