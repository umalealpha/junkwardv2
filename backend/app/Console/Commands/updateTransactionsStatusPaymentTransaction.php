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



class updateTransactionsStatusPaymentTransaction extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'updateTransactionsStatusPaymentTransaction:cron';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Command description';

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
      $cron->name  = "updateTransactionsStatusPaymentTransaction:cron";
      $cron->start = \Carbon\Carbon::now();

       $ledger = Ledger::where('trans_type', 'Invoices')
      ->where('trans_type', 'Invoices')
      ->whereIn('policy_id',[8532,8539,8541,8546,8560,8564,8575,8577,8582,8586,8588,8613,8616,8623,8625,
      8626,8627,8632,8634,8637,8644,8670,8679,8680,8697,8698,8701,8703,8709,8742,
      8744,8745,8746,8748,8752,8759,8763,8764,8767,8768,8770,8775,8777,8778,8781,
      8794,8796,8799,8803,8809])
      //->where('policy_id', 10516)
      //->orderBy('invoice_date','DESC')
      ->get(array('id','invoice_date', 'invoice_no', 'invoice_date','policy_id','premium','premium_freq','prorata_status'))
      ->toArray();

      if(count($ledger) > 0)
      {
        foreach ($ledger as $lData) 
        {
          $invoiceDate = $lData['invoice_date'];
          $premium     = isset($lData['premium']) ? $lData['premium'] : '';
          $premium_freq     = isset($lData['premium_freq']) ? $lData['premium_freq'] : '';
          $policyId    = $lData['policy_id'];
          $ledgerId    = $lData['id'];
          $proRataStatus = $lData['prorata_status'];
          

          $getYear  =  date('Y', strtotime($invoiceDate));
          $getMonth =  date('m', strtotime($invoiceDate));
          $getDay   =  date('d', strtotime($invoiceDate));

            if($premium_freq == 1)
            {
              $getLedgerData = PaymentTransaction::where('policy_id',$policyId)
              ->whereYear('new_payment_date',$getYear)
              ->whereMonth('new_payment_date',$getMonth)
              ->whereRaw('LOWER(status) = (?)', 'success')
              ->get(array('status','referenceNumber','amount','new_payment_date','policyStatus','id','paymentDate','policyNumber','is_refund'))->toArray();
            }
            else
            {
              $getLedgerData = PaymentTransaction::where('policy_id',$policyId)
              ->whereYear('new_payment_date',$getYear)
              //->whereMonth('new_payment_date',$getMonth)
              ->whereRaw('LOWER(status) = (?)', 'success')
              ->get(array('status','referenceNumber','amount','new_payment_date','policyStatus','id','paymentDate','policyNumber','is_refund'))->toArray();
            }
            
            
            $custData = Policy::where('id', $policyId)->orderBy('id', 'DESC')->first(array('balance','created_at','customer_id'));
            $customerId = $custData->customer_id;
            $createdAt  = $custData->created_at;

            $banking_id = CustomerBanking::where('customer_id', $customerId)->first(array('id'));
            if($banking_id != NULL)
                $banking_id = $banking_id->id;
            else
                $banking_id = NULL;

            $balance = Ledger::where('policy_id', $policyId)->orderBy('id', 'DESC')->first(array('balance'));
            if ($balance != null) {
                $balance = $balance->balance;
            } else {
                $balance = 0;
            }

            if(count($getLedgerData) > 1)
            {
                 
                
                foreach ($getLedgerData as  $ledgerPaymentArch) 
                {

                  $policyStatus     = isset($ledgerPaymentArch['policyStatus']) ? $ledgerPaymentArch['policyStatus'] : '';
                  $status     = isset($ledgerPaymentArch['status']) ? strtolower($ledgerPaymentArch['status']) : '';
                  $amount     = isset($ledgerPaymentArch['amount']) ? $ledgerPaymentArch['amount'] : '';
                  $archID     = isset($ledgerPaymentArch['id']) ? $ledgerPaymentArch['id'] : '';

                  $isRefund     = isset($ledgerPaymentArch['is_refund']) ? $ledgerPaymentArch['is_refund'] : '';
                  
                  $referenceNumber = isset($ledgerPaymentArch['referenceNumber']) ? $ledgerPaymentArch['referenceNumber'] : '';
                  $newPaymentDate = isset($ledgerPaymentArch['new_payment_date']) ? $ledgerPaymentArch['new_payment_date'] : '';

                  $getTermYear  =  date('Y', strtotime($newPaymentDate));
                  $getTermMonth =  date('m', strtotime($newPaymentDate));
                  $getTermNextMonth =  date("m",  strtotime("+1 month", strtotime($newPaymentDate)));

                  $getNextMonth =  $getMonth + 1;
                  $getTermDay   =  date('d', strtotime($newPaymentDate));

                  

                  $newTermDateMatch        = $getTermMonth."-".$getTermYear;
                  $newTermNextDateMatch    = $getTermNextMonth."-".$getTermYear;

                  $newLedgerDateMatch         = $getMonth."-".$getYear;
                  $newLedgerNextDateMatch     = $getNextMonth."-".$getYear;
                  $proDate = '';
                  if($proRataStatus != null)
                  {
                    $proDate = $getTermMonth."-".$getTermYear;
                  }
                  $checkExit = Ledger::where('policy_id', $policyId)
                  ->where('trans_type', 'Payment')
                  ->where('trans_ref', $referenceNumber)
                  ->where('orig_trans', $referenceNumber)
                  ->get(array('balance'));

                  if($newTermDateMatch == $newLedgerDateMatch && count($getLedgerData) > 1 && $isRefund == 0 && count($checkExit) == 0)
                  {

                          $proAmt = 0;
                          if($proRataStatus != null)
                          {
                            $proAmt = $amount;
                            $amount = $amount;
                          }                       
                          else
                          {
                            $totalSumFinal =$this->getTotal($getTermYear,$getTermMonth,$policyId);
                            //$amount = $totalSumFinal - $amount;
                            $amount = $amount;
                          }
                          
                          $transactionAmount = str_replace(',', '',(float)$amount);
                          $totalBalance = str_replace(',', '',number_format(($transactionAmount - abs($premium)), 2));
                          $totalBalance = number_format((abs($premium) - abs($transactionAmount)), 2);

                          
                          $record =  array();
                          $record['debit'] = $premium;
                          $record['credit'] = NULL;
                          $record['balance'] = NULL;

                          $record['status'] = 'Paid';
                          $record['odoo_status'] = 'Paid';
                          $record['trans_ref'] = $referenceNumber;
                          $record['orig_trans'] = $referenceNumber;
                          /*$record['accounting_date'] = $ledgerPaymentArch['paymentDate'];
                          $record['system_date'] = $ledgerPaymentArch['paymentDate'];
                          $record['eff_date'] = $ledgerPaymentArch['paymentDate'];*/
                          $record['pmts_adjust'] = $amount;
                          $record['invoice_amount'] = $premium;
                          $data = $record;
                          $lStatus =Ledger::where('policy_id',$policyId)
                          ->where('id',$ledgerId)
                          ->update($data);

                            $record = new Ledger;
                            $record->customer_id = $customerId;
                            $record->account_id = NULL;
                            $record->policy_id = $policyId;
                            $record->claim_id = NULL;
                            $record->banking_id = $banking_id;
                            $record->account_name = NULL;
                            $record->accounting_date = $ledgerPaymentArch['paymentDate'];
                            $record->amount_type = NULL;
                            $record->trans_ref = $referenceNumber;
                            $record->orig_trans = $referenceNumber;
                            $record->unallocated = NULL;
                            $record->system_date = $ledgerPaymentArch['paymentDate'];
                            $record->trans_sub_type = NULL;
                            $record->eff_date = $ledgerPaymentArch['paymentDate'];
                            $record->invoice_file = NULL;
                            $record->invoice_date = NULL;
                            $record->invoice_no = NULL;
                            $record->invoice_amount = NULL;
                            $record->premium = $premium;
                            $record->due_amount = NULL;
                            $record->pmts_adjust = NULL;
                            $record->due_date = NULL;
                            $record->status = 'Paid';
                            $record->debit = NULL;
                            $record->credit = $amount;
                            $record->balance = NULL;
                            $record->trans_type = 'Payment';
                            $record->premium_freq = $premium_freq;
                            $record->prorata_status = 0;
                            $record->save();
                          
                          
                  }
                  else if($newTermDateMatch == $newLedgerNextDateMatch  && $premium_freq == 3 && $isRefund == 0 && count($checkExit) == 0)
                  {
                       $transactionAmount = str_replace(',', '',(float)$amount);
                          $totalBalance = str_replace(',', '',number_format(($transactionAmount - abs($premium)), 2));
                          $totalBalance = number_format((abs($premium) - abs($transactionAmount)), 2);

                          
                          $record =  array();
                          $record['debit'] = $premium;
                          $record['credit'] = NULL;
                          $record['balance'] = NULL;

                        $record['status'] = 'Paid';
                        $record['odoo_status'] = 'Paid';
                        $record['trans_ref'] = $referenceNumber;
                        $record['orig_trans'] = $referenceNumber;
                        /*$record['accounting_date'] = $ledgerPaymentArch['paymentDate'];
                        $record['system_date'] = $ledgerPaymentArch['paymentDate'];
                        $record['eff_date'] = $ledgerPaymentArch['paymentDate'];*/
                        $record['pmts_adjust'] = $ledgerPaymentArch['amount'];
                        $record['invoice_amount'] = $premium;
                        $data = $record;
                        $lStatus = Ledger::whereYear('invoice_date', $getYear)
                          ->whereMonth('invoice_date', $getMonth)
                          ->where('policy_id',$policyId)
                          ->where('id',$ledgerId)
                          ->update($data);

                            $record = new Ledger;
                            $record->customer_id = $customerId;
                            $record->account_id = NULL;
                            $record->policy_id = $policyId;
                            $record->claim_id = NULL;
                            $record->banking_id = $banking_id;
                            $record->account_name = NULL;
                            $record->accounting_date = $ledgerPaymentArch['paymentDate'];
                            $record->amount_type = NULL;
                            $record->trans_ref = $referenceNumber;
                            $record->orig_trans = $referenceNumber;
                            $record->unallocated = NULL;
                            $record->system_date = $ledgerPaymentArch['paymentDate'];
                            $record->trans_sub_type = NULL;
                            $record->eff_date = $ledgerPaymentArch['paymentDate'];
                            $record->invoice_file = NULL;
                            $record->invoice_date = NULL;
                            $record->invoice_no = NULL;
                            $record->invoice_amount = NULL;
                            $record->premium = $premium;
                            $record->due_amount = NULL;
                            $record->pmts_adjust = NULL;
                            $record->due_date = NULL;
                            $record->status = 'Paid';
                            $record->debit = NULL;
                            $record->credit = $amount;
                            $record->balance = NULL;
                            $record->trans_type = 'Payment';
                            $record->premium_freq = $premium_freq;
                            $record->prorata_status = 0;
                            $record->save();

                        if($lStatus)
                          {
                            PaymentTransaction::where('id',$archID)->update(['policyStatus'=>'1']);
                          }
                  }
                  else if($status == 'success' && $isRefund == 1 && count($checkExit) == 0)
                  {
                            $transactionAmount = str_replace(',', '',(float)$amount);
                          $totalBalance = str_replace(',', '',number_format(($transactionAmount - abs($premium)), 2));
                          $totalBalance = number_format((abs($premium) - abs($transactionAmount)), 2);
                        
                            $record = new Ledger;
                            // $record->customer_id = $5 tcustomerId;
                            $record->account_id = NULL;
                            $record->policy_id = $policyId;
                            $record->claim_id = NULL;
                            $record->banking_id = $banking_id;
                            $record->account_name = NULL;
                            $record->accounting_date = date("Y-m-d", strtotime($createdAt));
                            //
                            $record->amount_type = NULL;
                            $record->trans_ref = NULL;
                            $record->orig_trans = NULL;
                            $record->unallocated = NULL;
                            $record->system_date = date("Y-m-d", strtotime($createdAt));
                            $record->trans_sub_type = NULL;
                            $record->eff_date = date("Y-m-d", strtotime($createdAt));
                            $record->invoice_file = NULL;
                            $record->invoice_date = NULL;
                            $record->invoice_no = NULL;
                            $record->invoice_amount = NULL;
                            $record->premium = $premium;
                            $record->due_amount = NULL;
                            $record->pmts_adjust = NULL;
                            $record->due_date = NULL;
                            $record->status = 'Paid';
                            $record->debit = $premium;
                            $record->credit = NULL;
                            $record->balance = NULL;
                            $record->trans_type = 'Refund';
                            $record->premium_freq = $premium_freq;
                            $record->prorata_status = 0;
                            $record->save();
                  }
                        
                }
            }
            else 
            {

              foreach ($getLedgerData as  $ledgerPaymentArch) 
                {

                  $policyStatus     = isset($ledgerPaymentArch['policyStatus']) ? $ledgerPaymentArch['policyStatus'] : '';
                  $status     = isset($ledgerPaymentArch['status']) ? strtolower($ledgerPaymentArch['status']) : '';
                  $amount     = isset($ledgerPaymentArch['amount']) ? $ledgerPaymentArch['amount'] : '';
                  $archID     = isset($ledgerPaymentArch['id']) ? $ledgerPaymentArch['id'] : '';

                  $isRefund     = isset($ledgerPaymentArch['is_refund']) ? $ledgerPaymentArch['is_refund'] : '';

                  $referenceNumber = isset($ledgerPaymentArch['referenceNumber']) ? $ledgerPaymentArch['referenceNumber'] : '';
                  $newPaymentDate = isset($ledgerPaymentArch['new_payment_date']) ? $ledgerPaymentArch['new_payment_date'] : '';

                  $getTermYear  =  date('Y', strtotime($newPaymentDate));
                  $getTermMonth =  date('m', strtotime($newPaymentDate));
                  $getTermNextMonth =  date("m",  strtotime("+1 month", strtotime($newPaymentDate)));

                  $getNextMonth =  $getMonth + 1;
                  $getTermDay   =  date('d', strtotime($newPaymentDate));

                  

                  $newTermDateMatch        = $getTermMonth."-".$getTermYear;
                  $newTermNextDateMatch    = $getTermNextMonth."-".$getTermYear;

                  $newLedgerDateMatch         = $getMonth."-".$getYear;
                  $newLedgerNextDateMatch     = $getNextMonth."-".$getYear;
                  
                  $checkExit = Ledger::where('policy_id', $policyId)
                  ->where('trans_type', 'Payment')
                  ->where('trans_ref', $referenceNumber)
                  ->where('orig_trans', $referenceNumber)
                  ->get(array('balance'));
                  
                    if($newPaymentDate == $invoiceDate &&  $isRefund == 0 && count($checkExit) == 0)
                    {
                        
                        $transactionAmount = str_replace(',', '',(float)$amount);
                        $totalBalance = str_replace(',', '',number_format(($transactionAmount - abs($premium)), 2));
                        $totalBalance = number_format((abs($premium) - abs($transactionAmount)), 2);

                        
                        $record =  array();
                        $record['debit'] = $premium;
                        $record['credit'] = NULL;
                        $record['balance'] = NULL;

                        $record['status'] = 'Paid';
                        $record['odoo_status'] = 'Paid';
                        $record['trans_ref'] = $referenceNumber;
                        $record['orig_trans'] = $referenceNumber;
                        /*$record['accounting_date'] = $ledgerPaymentArch['paymentDate'];
                        $record['system_date'] = $ledgerPaymentArch['paymentDate'];
                        $record['eff_date'] = $ledgerPaymentArch['paymentDate'];*/
                        $record['pmts_adjust'] = $ledgerPaymentArch['amount'];
                        $record['invoice_amount'] = $premium;
                        $data = $record;
                        $lStatus = Ledger::whereYear('invoice_date', $getYear)
                          ->whereMonth('invoice_date', $getMonth)
                          ->where('policy_id',$policyId)
                          ->where('id',$ledgerId)
                          ->update($data);

                            $record = new Ledger;
                            $record->customer_id = $customerId;
                            $record->account_id = NULL;
                            $record->policy_id = $policyId;
                            $record->claim_id = NULL;
                            $record->banking_id = $banking_id;
                            $record->account_name = NULL;
                            $record->accounting_date = $ledgerPaymentArch['paymentDate'];
                            $record->amount_type = NULL;
                            $record->trans_ref = $referenceNumber;
                            $record->orig_trans = $referenceNumber;
                            $record->unallocated = NULL;
                            $record->system_date = $ledgerPaymentArch['paymentDate'];
                            $record->trans_sub_type = NULL;
                            $record->eff_date = $ledgerPaymentArch['paymentDate'];
                            $record->invoice_file = NULL;
                            $record->invoice_date = NULL;
                            $record->invoice_no = NULL;
                            $record->invoice_amount = NULL;
                            $record->premium = $premium;
                            $record->due_amount = NULL;
                            $record->pmts_adjust = NULL;
                            $record->due_date = NULL;
                            $record->status = 'Paid';
                            $record->debit = NULL;
                            $record->credit = $amount;
                            $record->balance = NULL;
                            $record->trans_type = 'Payment';
                            $record->premium_freq = $premium_freq;
                            $record->prorata_status = 0;
                            $record->save();

                        if($lStatus)
                          {
                            PaymentTransaction::where('id',$archID)->update(['policyStatus'=>'1']);
                          }
                    }
                    else if($newTermDateMatch == $newLedgerDateMatch && $isRefund == 0 && count($checkExit) == 0)
                    {
                       
                        $transactionAmount = str_replace(',', '',(float)$amount);
                        $totalBalance = str_replace(',', '',number_format(($transactionAmount - abs($premium)), 2));
                        $totalBalance = number_format((abs($premium) - abs($transactionAmount)), 2);

                        
                        $record =  array();
                        $record['debit'] = $premium;
                        $record['credit'] = NULL;
                        $record['balance'] = NULL;

                        $record['status'] = 'Paid';
                        $record['odoo_status'] = 'Paid';
                        $record['trans_ref'] = $referenceNumber;
                        $record['orig_trans'] = $referenceNumber;
                        /*$record['accounting_date'] = $ledgerPaymentArch['paymentDate'];
                        $record['system_date'] = $ledgerPaymentArch['paymentDate'];
                        $record['eff_date'] = $ledgerPaymentArch['paymentDate'];*/
                        $record['pmts_adjust'] = $ledgerPaymentArch['amount'];
                        $record['invoice_amount'] = $premium;
                        $data = $record;
                        $lStatus = Ledger::whereYear('invoice_date', $getYear)
                          ->whereMonth('invoice_date', $getMonth)
                          ->where('policy_id',$policyId)
                          ->where('id',$ledgerId)
                          ->update($data);

                          $record = new Ledger;
                            $record->customer_id = $customerId;
                            $record->account_id = NULL;
                            $record->policy_id = $policyId;
                            $record->claim_id = NULL;
                            $record->banking_id = $banking_id;
                            $record->account_name = NULL;
                            $record->accounting_date = $ledgerPaymentArch['paymentDate'];
                            $record->amount_type = NULL;
                            $record->trans_ref = $referenceNumber;
                            $record->orig_trans = $referenceNumber;
                            $record->unallocated = NULL;
                            $record->system_date = $ledgerPaymentArch['paymentDate'];
                            $record->trans_sub_type = NULL;
                            $record->eff_date = $ledgerPaymentArch['paymentDate'];
                            $record->invoice_file = NULL;
                            $record->invoice_date = NULL;
                            $record->invoice_no = NULL;
                            $record->invoice_amount = NULL;
                            $record->premium = $premium;
                            $record->due_amount = NULL;
                            $record->pmts_adjust = NULL;
                            $record->due_date = NULL;
                            $record->status = 'Paid';
                            $record->debit = NULL;
                            $record->credit = $amount;
                            $record->balance = NULL;
                            $record->trans_type = 'Payment';
                            $record->premium_freq = $premium_freq;
                            $record->prorata_status = 0;
                            $record->save();

                        if($lStatus)
                          {
                            PaymentTransaction::where('id',$archID)->update(['policyStatus'=>'1']);
                          }
                    }
                    else if($newTermDateMatch == $newLedgerNextDateMatch  && $premium_freq == 3 && $isRefund == 0 && count($checkExit) == 0)
                    {
                        
                        $transactionAmount = str_replace(',', '',(float)$amount);
                        $totalBalance = str_replace(',', '',number_format(($transactionAmount - abs($premium)), 2));
                        $totalBalance = number_format((abs($premium) - abs($transactionAmount)), 2);

                        
                        $record =  array();
                        $record['debit'] = $premium;
                        $record['credit'] = NULL;
                        $record['balance'] = NULL;

                        $record['status'] = 'Paid';
                        $record['odoo_status'] = 'Paid';
                        $record['trans_ref'] = $referenceNumber;
                        $record['orig_trans'] = $referenceNumber;
                       /* $record['accounting_date'] = $ledgerPaymentArch['paymentDate'];
                        $record['system_date'] = $ledgerPaymentArch['paymentDate'];
                        $record['eff_date'] = $ledgerPaymentArch['paymentDate'];*/
                        $record['pmts_adjust'] = $ledgerPaymentArch['amount'];
                        $record['invoice_amount'] = $premium;

                        
                         $data = $record;   
                        $lStatus = Ledger::whereYear('invoice_date', $getYear)
                          ->whereMonth('invoice_date', $getMonth)
                          ->where('policy_id',$policyId)
                          ->where('id',$ledgerId)
                          ->update($data);


                           $record = new Ledger;
                            $record->customer_id = $customerId;
                            $record->account_id = NULL;
                            $record->policy_id = $policyId;
                            $record->claim_id = NULL;
                            $record->banking_id = $banking_id;
                            $record->account_name = NULL;
                            $record->accounting_date = $ledgerPaymentArch['paymentDate'];
                            $record->amount_type = NULL;
                            $record->trans_ref = $referenceNumber;
                            $record->orig_trans = $referenceNumber;
                            $record->unallocated = NULL;
                            $record->system_date = $ledgerPaymentArch['paymentDate'];
                            $record->trans_sub_type = NULL;
                            $record->eff_date = $ledgerPaymentArch['paymentDate'];
                            $record->invoice_file = NULL;
                            $record->invoice_date = NULL;
                            $record->invoice_no = NULL;
                            $record->invoice_amount = NULL;
                            $record->premium = $premium;
                            $record->due_amount = NULL;
                            $record->pmts_adjust = NULL;
                            $record->due_date = NULL;
                            $record->status = 'Paid';
                            $record->debit = NULL;
                            $record->credit = $amount;
                            $record->balance = NULL;
                            $record->trans_type = 'Payment';
                            $record->premium_freq = $premium_freq;
                            $record->prorata_status = 0;
                            $record->save();
                        if($lStatus)
                          {
                            PaymentTransaction::where('id',$archID)->update(['policyStatus'=>'1']);
                          }
                    }
                    else if($status == 'success' && $isRefund == 1)
                  {
                            $transactionAmount = str_replace(',', '',(float)$amount);
                            $totalBalance = str_replace(',', '',number_format(($transactionAmount - abs($premium)), 2));
                            $totalBalance = number_format((abs($premium) - abs($transactionAmount)), 2);
                        
                            $record = new Ledger;
                            $record->customer_id = $customerId;
                            $record->account_id = NULL;
                            $record->policy_id = $policyId;
                            $record->claim_id = NULL;
                            $record->banking_id = $banking_id;
                            $record->account_name = NULL;
                            $record->accounting_date = date("Y-m-d", strtotime($createdAt));
                            //
                            $record->amount_type = NULL;
                            $record->trans_ref = NULL;
                            $record->orig_trans = NULL;
                            $record->unallocated = NULL;
                            $record->system_date = date("Y-m-d", strtotime($createdAt));
                            $record->trans_sub_type = NULL;
                            $record->eff_date = date("Y-m-d", strtotime($createdAt));
                            $record->invoice_file = NULL;
                            $record->invoice_date = NULL;
                            $record->invoice_no = NULL;
                            $record->invoice_amount = NULL;
                            $record->premium = $premium;
                            $record->due_amount = NULL;
                            $record->pmts_adjust = NULL;
                            $record->due_date = NULL;
                            $record->status = 'Paid';
                            $record->debit = $premium;
                            $record->credit = NULL;
                            $record->balance = NULL;
                            $record->trans_type = 'Refund';
                            $record->premium_freq = $premium_freq;
                            $record->prorata_status = 0;
                            $record->save();
                  }

                }
            }
            
        }

        //$isExits =$this->checkExits($policyId);
        
      } 
  }
  public  function getTotal($getTermYear,$getTermMonth,$policyId)
  {
    $totalSum = PaymentTransaction::where('policy_id',$policyId)
    ->whereYear('new_payment_date',$getTermYear)
    ->whereMonth('new_payment_date',$getTermMonth)
    ->whereRaw('LOWER(status) = (?)', 'success')
    ->sum('amount');
    return $totalSum;
    
  }

  
}