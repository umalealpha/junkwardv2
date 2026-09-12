<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Events\ChargeTokenRecurrentEvent;
use AlphaDirect\Events\CreateTokenEvent;
use AlphaDirect\Events\PullAccountEvent;
use AlphaDirect\Events\SubscriptionTokenEvent;
use AlphaDirect\Http\Controllers\DpoPaymentController;
use AlphaDirect\Policy;
use AlphaDirect\ScheduleTransaction;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use AlphaDirect\Models\PendingReccuringDPO;
use Illuminate\Support\Facades\Artisan;
use AlphaDirect\Customer;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Events\VerifyTokenEvent;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\PaymentTransaction;
use Illuminate\Support\Facades\Mail;
use AlphaDirect\Mail\MailTemplate;
use Illuminate\Support\Facades\Storage;
use stdClass;
use PDF;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;

class PendingRecurringTokenCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policy:pendingRecurringTokenCreate {id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collect activated policies whose payment method is dpo and proceed to generate subscription token.';

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
        $cron->name = "policy:pendingRecurringTokenCreate";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $transId = $this->argument('id');

      
        $policies = PendingReccuringDPO::where('status',0)->get();
        $missingTokenTransactions = PendingReccuringDPO::whereIn('status', [0, 1, 3])   //2=success

        ->whereIn('email', [null, ''])
        ->get();
       
        $this->info('Running...');

        $dpo = new DpoPaymentController();

       
        if(count($policies) > 0)
        {
            foreach($policies as $policy)
            {
                if( !Policy::where('policyNumber',$policy->policy_number)->where('status',2)->exists())
                {
                $this->info('Policy Number:'. $policy->policy_number );
               // DB::beginTransaction();
              //  try{
                    //add valiation for one time payment in

                        $createToken = CreateTokenEvent::dispatch($policy);
                        $createToken = isset($createToken[0]) ? $createToken[0] : null;

                    if(isset($createToken) && $createToken['status'] == 1)
                    {
                            $data = [
                                'policy_number'    => $policy->policy_number,
                                "customer_id"      => $createToken['customer_id'],
                                "amount"           => $createToken['amount'],
                                "dpo_error_code"   => $createToken['dpo_error_code'],
                                "reason"           => $createToken['reason'],
                                "token"            => $createToken['token'],
                                "TransactionToken" => $createToken['TransactionToken'],
                                "TransID"          => $createToken['TransID'],
                                "api_name"         => 'subscriptionToken',
                                "response_json"    => $createToken['response_json'],
                                "request_json"     => $createToken['request_json'],
                                "CompanyRef"       => $policy->policy_number.'/'.$policy->installment.'/'.$policy->retry_count,
                                "email"            => $createToken['email'] ,
                            ];

                            $subscriptionTokenEvent = SubscriptionTokenEvent::dispatch($data);
                            $subscriptionTokenEvent = isset($subscriptionTokenEvent[0]) ? $subscriptionTokenEvent[0] : null;

                            if(isset($subscriptionTokenEvent) && $subscriptionTokenEvent['status'] == 1)
                            {
                                $data['subscriptionToken']        = $subscriptionTokenEvent['subscriptionToken'];
                                $data['customerToken']            = $subscriptionTokenEvent['customerToken'];
                                      $policy->subscription_token = $subscriptionTokenEvent['subscriptionToken'];
                                      $policy->customer_token     = $subscriptionTokenEvent['customerToken'];
                                      $policy->token              = $data['TransactionToken'];
                                    //   $policy->status             = 1;    // payment in progress                                                                                //in progress
                                    //   $policy->reason             = isset($subscriptionTokenEvent['reason']) ? $subscriptionTokenEvent['reason'] : null;  //in progress
                                $policy->save();

                                $this->info('Subscription Token generated for policy: '. $policy->policy_number);
                                 activity('Policy')
                                ->performedOn($policy)
                                ->log('Subscription Token generated for policy: '. $policy->policy_number. ' successfully');


                        $transId = $this->argument('id');
                        $policies4 = PendingReccuringDPO::where('id',$policy->id)->where('status',0)->where('email','!=',null)->get();
                        $policies_id = null;
                        if(count($policies4) > 0){

                            foreach($policies4 as $policy1){
                                $policy2 = Policy::where('policyNumber',$policy1->policy_number)->where('status',1)->exists();
                                if($policy2 == true){
                                    $policies_id = $policy1->id;
                                }
                            }
                        }
                        $policies = PendingReccuringDPO::whereIn('id',[$policies_id])->get();
                       

                        // dd($missingTokenTransactions);
                        $dpo      = new DpoPaymentController();

                        if(count($policies) > 0)
                        {



                            $this->info('Running...');

                            foreach($policies as $policy)
                            {
                            //  DB::beginTransaction();
                            //   try{
                                    $this->info('Policy Number:'. $policy->policy_number );
                                    $check = PaymentTransaction::where('policyNumber', $policy->policyNumber)
                                            ->where('paymentMethod', 'DPO')
                                            ->whereDate('paymentDate', Carbon::today())
                                            ->exists();
                                    // $check = false; // comment it before it goes live

                                    if($check === false)
                                    {
                                        if($policy->subscription_token != null && $policy->token != null && $policy->customer_token != null && $policy->retry_count < 5){
                                            $data = [
                                                'policy_number'     => $policy->policy_number,
                                                "customer_id"       => $policy->customer_id,
                                                "customerToken"     => $policy->customer_token,
                                                "amount"            => $policy->premium,
                                                "token"             => $policy->token,
                                                "TransactionToken"  => $policy->token,
                                                "api_name"          => 'chargeTokenRecurrent',
                                                "CompanyRef"        => $policy->policy_number.'/'.$policy->installment.'/'.$policy->retry_count,
                                                "email"             => $policy->email,
                                                "subscriptionToken" => $policy->subscription_token,
                                            ];

                                            $chargeTokenRecurrentEvent = ChargeTokenRecurrentEvent::dispatch($data);

                                            $chargeTokenRecurrentEvent = $chargeTokenRecurrentEvent[0];

                                            if($chargeTokenRecurrentEvent['status'] == 1)
                                            {
                                                $verifyTokenEvent     = VerifyTokenEvent::dispatch($data);
                                                $verifyTokenEvent     = $verifyTokenEvent['0'];
                                                // dd($verifyTokenEvent);

                                                if($verifyTokenEvent['status'] == 1)
                                                {
                                                    $policy->billing_date = Carbon::today()->format('Y-m-d H:i:s');
                                                    $policy->status       = 2; //payment successful
                                                    $policy->reason       = $verifyTokenEvent['reason'];
                                                    $policy->email        = $policy->email;
                                                    $policy->save();

                                                    $transaction                          = new PaymentTransaction();
                                                    $transaction->policyNumber            = $policy->policy_number;
                                                    $transaction->policy_id = $policy->policy_id;
                                                    $transaction->referenceNumber         = $policy->token;
                                                    $transaction->TransactionToken        = $policy->token;
                                                    $transaction->amount                  = $policy->premium;
                                                    $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                                    $transaction->paymentMethod           = 'DPO';
                                                    $transaction->numberOfInstalmentsPaid = $policy->installment;
                                                    $transaction->paymentFrequency        =  1;
                                                    $transaction->status                  = 'SUCCESS';
                                                    $transaction->save();

                                                    $account = PullAccountEvent::dispatch($data);
                                                    $this->info('Policy Number:'. $policy->policy_number . ' Payment Done.');
                                                    activity('Policy')
                                                    ->performedOn($policy)
                                                    ->log('Policy Number:'. $policy->policy_number . ' Payment Done.');

                                                   
                                                }else
                                                {

                                                    //update next billing date
                                                    $policy->retry_count  = $policy->retry_count + 1; //0 to 4
                                                    $policy->billing_date = $dpo->nextBillingDate($policy->retry_count, $policy->billing_date); // 2 days, 5 days, 15 days, 30 days retry payment
                                                    $policy->status       = 3; //failed
                                                    $policy->reason       = $verifyTokenEvent['reason'];
                                                    $policy->save();

                                                    $transaction                          = new PaymentTransaction();
                                                    $transaction->policyNumber            = $policy->policy_number;
                                                    $transaction->policy_id = $policy->policy_id;
                                                    $transaction->referenceNumber         = $policy->token;
                                                    $transaction->TransactionToken        = $policy->token;
                                                    $transaction->amount                  = $policy->premium;
                                                    $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                                    $transaction->paymentMethod           = 'DPO';
                                                    $transaction->note                    = $verifyTokenEvent['reason'];
                                                    $transaction->numberOfInstalmentsPaid = $policy->installment;
                                                    $transaction->status                  = 'FAILED';
                                                    $transaction->payment_transaction_id  = $policy->id;
                                                    $transaction->save();

                                                    PullAccountEvent::dispatch($data);

                                                    $this->error('Payment failed for policy: '. $policy->policy_number );
                                                    activity('Policy')
                                                    ->performedOn($policy)
                                                    ->log('Payment failed for policy: '. $policy->policy_number);
                                                }
                                            }else
                                            {
                                                //update next billing date
                                                $policy->retry_count  = $policy->retry_count + 1; //0 to 4
                                                $policy->billing_date = $dpo->nextBillingDate($policy->retry_count, $policy->billing_date); // 2 days, 5 days, 15 days, 30 days retry payment
                                                $policy->status       = 3; //failed
                                                $policy->reason       = isset($chargeTokenRecurrentEvent['reason']) ? $chargeTokenRecurrentEvent['reason'] : null;
                                                $policy->save();

                                                $transaction                          = new PaymentTransaction();
                                                $transaction->policyNumber            = $policy->policy_number;
                                                $transaction->policy_id = $policy->policy_id;
                                                $transaction->referenceNumber         = $policy->token;
                                                $transaction->TransactionToken        = $policy->token;
                                                $transaction->amount                  = $policy->premium;
                                                $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                                $transaction->paymentMethod           = 'DPO';
                                                $transaction->note                    = isset($chargeTokenRecurrentEvent['reason']) ? $chargeTokenRecurrentEvent['reason'] : null;
                                                $transaction->numberOfInstalmentsPaid = $policy->installment;
                                                $transaction->status                  = 'FAILED' ;
                                                $transaction->payment_transaction_id  = $policy->id;
                                                $transaction->save();

                                                PullAccountEvent::dispatch($data);

                                                $this->error('Payment failed for policy: '. $policy->policy_number );
                                                activity('Policy')
                                                ->performedOn($policy)
                                                ->log('Payment failed for policy: '. $policy->policy_number);
                                            }
                                        }
                                        elseif($policy->subscription_token != null && $policy->token != null && $policy->customer_token != null && $policy->retry_count < 5){

                                            $data = [
                                                'policy_number'     => $policy->policy_number,
                                                "customer_id"       => $policy->customer_id,
                                                "customerToken"     => $policy->customer_token,
                                                "amount"            => $policy->premium,
                                                "token"             => $policy->token,
                                                "TransactionToken"  => $policy->token,
                                                "api_name"          => 'chargeTokenRecurrent',
                                                "CompanyRef"        => $policy->policy_number.'/'.$policy->installment.'/'.$policy->retry_count,
                                                "email"             => $policy->email,
                                                "subscriptionToken" => $policy->subscription_token,
                                            ];

                                            $chargeTokenRecurrentEvent = ChargeTokenRecurrentEvent::dispatch($data);

                                            $chargeTokenRecurrentEvent = $chargeTokenRecurrentEvent[0];

                                            if($chargeTokenRecurrentEvent['status'] == 1)
                                            {
                                                $verifyTokenEvent     = VerifyTokenEvent::dispatch($data);
                                                $verifyTokenEvent     = $verifyTokenEvent['0'];
                                                // dd($verifyTokenEvent);

                                                if($verifyTokenEvent['status'] == 1)
                                                {
                                                    $policy->billing_date = Carbon::today()->format('Y-m-d H:i:s');
                                                    $policy->status       = 2; //payment successful
                                                    $policy->reason       = $verifyTokenEvent['reason'];
                                                    $policy->save();

                                                    $transaction                          = new PaymentTransaction();
                                                    $transaction->policyNumber            = $policy->policy_number;
                                                    $transaction->policy_id = $policy->policy_id;
                                                    $transaction->referenceNumber         = $policy->token;
                                                    $transaction->TransactionToken        = $policy->token;
                                                    $transaction->amount                  = $policy->premium;
                                                    $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                                    $transaction->paymentMethod           = 'DPO';
                                                    $transaction->numberOfInstalmentsPaid = $policy->installment;
                                                    $transaction->paymentFrequency        =  1;
                                                    $transaction->status                  = 'SUCCESS';
                                                    $transaction->save();

                                                    $account = PullAccountEvent::dispatch($data);
                                                    $this->info('Policy Number:'. $policy->policy_number . ' Payment Done.');
                                                    activity('Policy')
                                                    ->performedOn($policy)
                                                    ->log('Policy Number:'. $policy->policy_number . ' Payment Done.');

                                                    /* if ($policy->email != null) {
                                                        $data               = new stdClass();
                                                        $data->user_id      = $policy->id;
                                                        $data->policy_id    = $policy->policy_id;
                                                        $data->customer_id  = $policy->customer_id;
                                                        $data->premium      = $policy->premium;
                                                        $data->billing_date = $policy->billing_date;
                                                        $data->installment  = $policy->installment;
                                                        $data->hook         = 'payment_done';
                                                        $data->attachment   = null;
                                                        $email              = env('APP_STATUS') == 'Production' ? $policy->email : 'rfartode@alphadirect.co.bw';
                                                        Mail::to($email)->send(new MailTemplate($data));
                                                    } */
                                                }else
                                                {

                                                    //update next billing date
                                                    $policy->retry_count  = $policy->retry_count + 1; //0 to 4
                                                    $policy->billing_date = $dpo->nextBillingDate($policy->retry_count, $policy->billing_date); // 2 days, 5 days, 15 days, 30 days retry payment
                                                    $policy->status       = 3; //failed
                                                    $policy->reason       = $verifyTokenEvent['reason'];
                                                    $policy->save();

                                                    $transaction                          = new PaymentTransaction();
                                                    $transaction->policyNumber            = $policy->policy_number;
                                                    $transaction->policy_id = $policy->policy_id;
                                                    $transaction->referenceNumber         = $policy->token;
                                                    $transaction->TransactionToken        = $policy->token;
                                                    $transaction->amount                  = $policy->premium;
                                                    $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                                    $transaction->paymentMethod           = 'DPO';
                                                    $transaction->note                    = $verifyTokenEvent['reason'];
                                                    $transaction->numberOfInstalmentsPaid = $policy->installment;
                                                    $transaction->status                  = 'FAILED';
                                                    $transaction->payment_transaction_id  = $policy->id;
                                                    $transaction->save();

                                                    PullAccountEvent::dispatch($data);

                                                    $this->error('Payment failed for policy: '. $policy->policy_number );
                                                    activity('Policy')
                                                    ->performedOn($policy)
                                                    ->log('Payment failed for policy: '. $policy->policy_number);
                                                }
                                            }else
                                            {
                                                //update next billing date
                                                $policy->retry_count  = $policy->retry_count + 1; //0 to 4
                                                $policy->billing_date = $dpo->nextBillingDate($policy->retry_count, $policy->billing_date); // 2 days, 5 days, 15 days, 30 days retry payment
                                                $policy->status       = 3; //failed
                                                $policy->reason       = isset($chargeTokenRecurrentEvent['reason']) ? $chargeTokenRecurrentEvent['reason'] : null;
                                                $policy->save();

                                                $transaction                          = new PaymentTransaction();
                                                $transaction->policyNumber            = $policy->policy_number;
                                                $transaction->policy_id = $policy->policy_id;
                                                $transaction->referenceNumber         = $policy->token;
                                                $transaction->TransactionToken        = $policy->token;
                                                $transaction->amount                  = $policy->premium;
                                                $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                                $transaction->paymentMethod           = 'DPO';
                                                $transaction->note                    = isset($chargeTokenRecurrentEvent['reason']) ? $chargeTokenRecurrentEvent['reason'] : null;
                                                $transaction->numberOfInstalmentsPaid = $policy->installment;
                                                $transaction->status                  = 'FAILED' ;
                                                $transaction->payment_transaction_id  = $policy->id;
                                                $transaction->save();

                                                PullAccountEvent::dispatch($data);

                                                $this->error('Payment failed for policy: '. $policy->policy_number );
                                                activity('Policy')
                                                ->performedOn($policy)
                                                ->log('Payment failed for policy: '. $policy->policy_number);
                                            }
                                        }
                                        else{
                                            $policy->retry_count  = $policy->retry_count + 1; //0 to 4
                                            $policy->billing_date = $dpo->nextBillingDate($policy->retry_count, $policy->billing_date); // 2 days, 5 days, 15 days, 30 days retry payment
                                            $policy->status       = 3; //failed

                                            if ($policy->subscription_token == null) {
                                                $policy->reason       = 'missing subscription token';
                                            } elseif ($policy->token == null) {
                                                $policy->reason       = 'missing token';
                                            } elseif ($policy->customer_token == null) {
                                                $policy->reason       = 'missing customer token';
                                            } elseif ($policy->retry_count > 4) {
                                                $policy->reason       = 'retry count is greater than 4';
                                            } else {
                                                $policy->reason       = 'Retry Transaction Failed';
                                            }
                                            $policy->save();

                                            $transaction                          = new PaymentTransaction();
                                            $transaction->policyNumber            = $policy->policy_number;
                                            $transaction->policy_id = $policy->policy_id;
                                            $transaction->amount                  = $policy->premium;
                                            $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                            $transaction->paymentMethod           = 'DPO';
                                            $transaction->note                    = $policy->reason;
                                            $transaction->numberOfInstalmentsPaid = $policy->installment;
                                            $transaction->paymentFrequency        =  1;
                                            $transaction->status                  = 'FAILED' ;
                                            $transaction->payment_transaction_id  = $policy->id;
                                            $transaction->save();

                                            activity('Policy')
                                            ->performedOn($policy)
                                            ->log('Policy is rescheduled for payment:'. $policy->policy_number);

                                            $this->error('Failed to create token for policy to proceed payment: '. $policy->policy_number );
                                        }

                                    }else{
                                        $this->info('Payment record exists for Policy Number:'. $policy->policy_number );
                                    }
                                //    DB::commit();

                                    $pendingDPO = PendingReccuringDPO::where('id',$policy->id)->first();
                                    if($pendingDPO != null){
                                        $scheTrx =   ScheduleTransaction::where('id',$pendingDPO->id)->first();
                                        $scheTrx->billing_date = $pendingDPO->billing_date;
                                        $scheTrx->status = $pendingDPO->status;
                                        $scheTrx->reason = $pendingDPO->reason;
                                        $scheTrx->token = $pendingDPO->token;
                                        $scheTrx->subscription_token = $pendingDPO->subscription_token;
                                        $scheTrx->customer_token = $pendingDPO->customer_token;
                                        $scheTrx->retry_count = $pendingDPO->retry_count;
                                        $scheTrx->save();
                                    }

                            //  }
                            //   catch(\Exception $ex)
                            //   {
                            //      DB::rollback();
                            //  }
                            
                            
                            
                            } //end foreach

                            
                        }
                        else
                        {
                            $this->info('No policies are there to proceed');
                        }





                            }else //failed
                            {
                                $policy->retry_count  = $policy->retry_count + 1; //0 to 4
                                $policy->billing_date = $dpo->nextBillingDate($policy->retry_count, $policy->billing_date); // 2 days, 5 days, 15 days, 30 days retry payment
                                $policy->status       = 3; //failed
                                $policy->reason       = 'failed to generate subscription token';
                                $policy->save();

                                $this->error('Failed to create subscription token for policy: '. $policy->policy_number );

                                activity('Policy')
                                ->performedOn($policy)
                                ->log('Failed to create subscription token for policy: '. $policy->policy_number);
                            }
                    }else{

                        $policy->retry_count  = $policy->retry_count + 1; //0 to 4
                        $policy->billing_date = $dpo->nextBillingDate($policy->retry_count, $policy->billing_date); // 2 days, 5 days, 15 days, 30 days retry payment
                        $policy->status       = 3; //failed
                        $policy->reason       = 'missing required tokens';
                        $policy->save();

                        activity('Policy')
                        ->performedOn($policy)
                        ->log('Policy is rescheduled for payment:'. $policy->policy_number);

                        $this->error('Failed to create token for policy to proceed payment: '. $policy->policy_number );
                    }
                //    DB::commit();
             //   }catch(Exception $ex)
             //   {
             //       $this->error($ex->getMessage());
            //        DB::rollback();
            //    }
             }
            }
        }else{
            $this->info('No policies are there to proceed');
        }

        //pdf

        $transactions = [];
       if (isset($policies)) {
            foreach ($policies as $key => $policy) {
                if ($policy->retry_count > 4) {
                    $payment_transaction = PaymentTransaction::where('payment_transaction_id',$policy->id)
                            ->where('policyNumber',$policy->policy_number)
                            ->orderBy('id','desc')
                            ->skip(1)
                            ->take(1)
                            ->first();
                    if (isset($payment_transaction)) {
                        $payment_transaction->email = $policy->email;
                        $payment_transaction->retry_count = $policy->retry_count;
                        $payment_transaction->status = $policy->status;
                        array_push($transactions,$payment_transaction);
                    }
                } else {
                    array_push($transactions,$policy);
                }
            }
        } 
        
        $report = [
            'policies'                 => $policies,
            'missingTokenTransactions' => $missingTokenTransactions,
            'successTransactions' => $policies->where('status', 2)->count(),
            'failedTransactions' => $policies->where('status', '!=', 2)->count(),
            'transactions'       => $transactions,
        ]; 

        $result = bin2hex(random_bytes(20));
        $date = Carbon::now()->timestamp;
        $path = 'paymentByDpo-'.$date.$result.'/paymentByDpo.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.dpoTransactionsReport', $report)->setPaper('a3', 'landscape');
        Storage::disk('s3')->put($path, $pdf->output(), 'public');

        $attachments = array();
        array_push($attachments, $path);
        if(count($policies) > 0 || count($missingTokenTransactions) > 0 || count($transactions) > 0){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'dpo_transaction_today';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
            ////*************Email send new fuction END **************/////
        }
      /*  $email = array();
        if(env('APP_STATUS') == 'Production') {
            $email = array(
                'kkatolkar@alphadirect.co.bw',
                'pganesharajah@alphadirect.co.bw',
                'arjuniyer@alphadirect.co.bw',
                'nbarot@theriskco.com',
                'rfartode@alphadirect.co.bw',
                'aiyer@alphadirect.co.bw'
            );
        }else{
        $email = array( 'rfartode@alphadirect.co.bw', 'lambatnikita@gmail.com', 'aprasad@alphadirect.co.bw','satyajeetbcd@gmail.com');
        }

      if(count($email) > 0  && (count($policies) > 0 || count($missingTokenTransactions) > 0 || count($transactions) > 0)) {
            foreach($email as $d){
                if($d){
                    $data2 = new \stdClass();
                    $data2->user_id = null;
                    $data2->hook = 'dpo_transaction_today';
                    $data2->customer_id = null;
                    $data2->attachment = $attachments;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data2->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data2);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data2]);
                    event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data2->hook]));
                  //  $sent = \Illuminate\Support\Facades\Mail::to($d)->send(new MailTemplate($data));
                }
            }
            $cron->mail_send = 1;
            $cron->save();
        }  */

        Storage::disk('s3')->delete($path);

        //endpdf
        $cron->end = \Carbon\Carbon::now();
        $cron->save(); 

    }
}
