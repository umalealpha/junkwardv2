<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Customer;
use AlphaDirect\DpoErrorCodes;
use AlphaDirect\DPOPaymentDoneData;
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
use Illuminate\Support\Facades\Mail;
use AlphaDirect\Mail\MailTemplate;
use Illuminate\Support\Facades\Storage;
use stdClass;
use PDF;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Events\VerifyTokenEvent;
use AlphaDirect\PaymentTransaction;
use Intervention\Image\Size;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;
use Log;
use AlphaDirect\Models\DpoTransactionReport;
use AlphaDirect\Services\MisRecurringPaymentFailureNotifier;

class ProcessDPOPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policy:processDPOpayment {id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Processing dpo payments';

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
        Log::info('Cron Started for processing dpo payments.');

        $cron = new CronStatus();
        $cron->name = "policy:processDPOpayment";
        $cron->start = \Carbon\Carbon::now();
         $cron->save();
        $transId = $this->argument('id');

        if(isset($transId) && ScheduleTransaction::where('id', $transId)->exists())
        {
            $policies = ScheduleTransaction::where('id', $transId)->get();
        }else{
            $policies = ScheduleTransaction::getInactiveScheduledTransactions();
        }

        // $policies = ScheduleTransaction::where('policy_number','MIS2023047251')->get();
        // dd($policies);

        $missingTokenTransactions = ScheduleTransaction::whereIn('status', [0, 1, 3])   //2=success,  0 = no debited yet, 1= subscription token generated, 3 = retried
        ->whereDate('billing_date', '=', Carbon::today())
        // ->whereBetween('billing_date', [$from, $to])
        ->where('retry_count', '<', 6)
        ->whereIn('email', [null, ''])
        ->get();

        $this->info('Running...');

        $dpo = new DpoPaymentController();

        // dd($policies);

        $dpoErrorCodesTx = [];
        $paymentDonePolicies = [];
        $paymentSuccessArr = [];
        $paymentFailedArr = [];

        if(count($policies) > 0)
        {
            foreach($policies as $policy)
            {
                
                $dpoReport =new DpoTransactionReport();
                $dpoReport->policy_number = $policy->policy_number;
                $dpoReport->save();
               
                $this->info('Policy Number:'. $policy->policy_number );
                if( !Policy::where('policyNumber',$policy->policy_number)->where('status',2)->exists()) {
          
                    $transactionReason = $policy->reason;

                

                    if($transactionReason == 'Invalid expiry date'){
                        $checkCodes = null;
                    } else {
                        $checkCodes = DpoErrorCodes::where('reason', $transactionReason)->first();
                    }

                    $checkCodes = DpoErrorCodes::where('reason', $transactionReason)->first();
			
                    if (!isset($checkCodes)) {
                        $this->info('Policy Number:'. $policy->policy_number." " .$policy->id." " .$policy->status );

                        DB::beginTransaction();
                        try{
                            
               

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

                                    if($subscriptionTokenEvent['status'] == 1)
                                    {
                                        $data['customerToken']     = $subscriptionTokenEvent['customerToken'];
                                        $data['reason']            = $subscriptionTokenEvent['reason'];
                                    } else {
                                        $data['customerToken']            = null;
                                    }

                                    $account = PullAccountEvent::dispatch($data);

                                    $subscriptionTokenEvent = null;

                                    if (isset($account) && isset($account[0]['options'])) {

                                    $optionData = (array) $account[0]['options'];
                                    $subscriptionToken = null;
                                    if(array_keys($optionData) !== range(0, count($optionData) - 1))
                                        {
                                            $subscriptionToken = $optionData['subscriptionToken'];
                                        }else{
                                            $optionData = (array) $optionData[count($optionData) - 1];
                                            $subscriptionToken = $optionData['subscriptionToken'];
                                        }

                                        $subscriptionTokenEvent = [
                                            'status' => $account[0]['status'],
                                            'subscriptionToken' => $subscriptionToken,
                                            'customerToken'   => $account[0]['customerToken'],
                                            'reason'          => $data['reason'],
                                        ];

                                    }

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

                                        /* Process payment for policy after generating subscription token */

                                        $this->info('Policy Number:'. $policy->policy_number );
                                        $check = PaymentTransaction::where('policyNumber', $policy->policy_number)
                                                ->where('paymentMethod', 'DPO')
                                                ->whereDate('paymentDate', Carbon::today())
                                                ->where('amount',$policy->premium)
                                                // ->where('referenceNumber',$policy->token)
                                                ->exists();
                                        // $check = false; // comment it before it goes live

                                        $policyDetails = ScheduleTransaction::where('policy_number',$policy->policy_number)->where('id',$policy->id)->first();

                                        if (isset($policyDetails)) {
                                            if($check === false)
                                            {
                                                if($policyDetails->subscription_token != null && $policyDetails->token != null && $policyDetails->customer_token != null && $policyDetails->retry_count < 5){
                                                    // ─── DOUBLE-CHARGE FIX (1/3) ──────────────────────────
                                                    // Atomically claim this row before charging. status=9
                                                    // means "charging_in_progress" — parallel runs filter
                                                    // on status IN (0,1,3) so they won't re-pick it. If
                                                    // claim fails (0 rows updated), another run already
                                                    // owns it; skip without charging.
                                                    // retry_count is left alone here — the existing
                                                    // failure handlers below increment it naturally; we
                                                    // only need atomicity, not to pre-account.
                                                    $previousStatus  = $policyDetails->status;
                                                    $chargeAttemptId = (int) round(microtime(true) * 1000);
                                                    $claimed = DB::table('scheduled_transactions')
                                                        ->where('id', $policyDetails->id)
                                                        ->where('status', $previousStatus)
                                                        ->update([
                                                            'status'     => 9,
                                                            'updated_at' => Carbon::now(),
                                                        ]);
                                                    if ($claimed === 0) {
                                                        Log::warning('dpo.schedule_transaction.row_already_claimed', [
                                                            'id'             => $policyDetails->id,
                                                            'policy_number'  => $policyDetails->policy_number,
                                                            'was_status'     => $previousStatus,
                                                        ]);
                                                        continue; // another worker owns this row
                                                    }
                                                    $policyDetails->status = 9; // keep in-memory in sync

                                                    $data = [
                                                        'policy_number'     => $policyDetails->policy_number,
                                                        "customer_id"       => $policyDetails->customer_id,
                                                        "customerToken"     => $policyDetails->customer_token,
                                                        "amount"            => $policyDetails->premium,
                                                        "token"             => $policyDetails->token,
                                                        "TransactionToken"  => $policyDetails->token,
                                                        "api_name"          => 'chargeTokenRecurrent',
                                                        // ─── DOUBLE-CHARGE FIX (2/3) ──────────────────────
                                                        // CompanyRef now carries a millisecond-unique suffix
                                                        // so DPO logs / our reconciliation can always tell
                                                        // duplicates apart even if the atomic claim is
                                                        // somehow bypassed.
                                                        "CompanyRef"        => $policyDetails->policy_number.'/'.$policyDetails->installment.'/'.$policyDetails->retry_count.'/'.$chargeAttemptId,
                                                        "email"             => $policyDetails->email,
                                                        "subscriptionToken" => $policyDetails->subscription_token,
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
                                                            $policyDetails->billing_date = Carbon::today()->format('Y-m-d H:i:s');
                                                            $policyDetails->status       = 2; //payment successful
                                                            $policyDetails->reason       = $verifyTokenEvent['reason'];
                                                            $policyDetails->email        = $policyDetails->email;
                                                            $policyDetails->save();

                                                            $transaction                          = new PaymentTransaction();
                                                            $transaction->policyNumber            = $policyDetails->policy_number;
                                                            $transaction->referenceNumber         = $policyDetails->token;
                                                            $transaction->TransactionToken        = $policyDetails->token;
                                                            $transaction->amount                  = $policyDetails->premium;
                                                            $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                                            $transaction->paymentMethod           = 'DPO';
                                                            $transaction->numberOfInstalmentsPaid = $policyDetails->installment;
                                                            $transaction->paymentFrequency        =  1;
                                                            $transaction->status                  = 'SUCCESS';
                                                            $transaction->save();

                                                            $dpoReport->status = 'SUCCESS';
                                                            $customerdatamain = Customer::where('id',$policy->customer_id)->first();
                                                            $dpoReport->email = $policy->email;
                                                            $dpoReport->customer_name = $customerdatamain->firstName.' '.$customerdatamain->lastName;
                                                            $dpoReport->cellphone = $customerdatamain->cellphone;
                                                            $dpoReport->premium = $policyDetails->premium;
                                                            $dpoReport->save();

                                                            $customer_update = Customer::where('id',$policyDetails->customer_id)->where('allow_email_update',0)->first();
                                                            if (isset($customer_update)) {
                                                                $customer_update->allow_email_update = 1;
                                                                $customer_update->save();
                                                            }

                                                            $account = PullAccountEvent::dispatch($data);
                                                            $this->info('Policy Number:'. $policyDetails->policy_number . ' Payment Done.');
                                                            activity('Policy')
                                                            ->performedOn($policyDetails)
                                                            ->log('Policy Number:'. $policyDetails->policy_number . ' Payment Done.');

                                                           
                                                        }else
                                                        {

                                                            //update next billing date
                                                            $policyDetails->retry_count  = $policyDetails->retry_count + 1; //0 to 4
                                                            $policyDetails->billing_date = $dpo->nextBillingDate($policyDetails->retry_count, $policyDetails->billing_date); // 2 days, 5 days, 15 days, 30 days retry payment
                                                            $policyDetails->status       = 3; //failed
                                                            $policyDetails->reason       = $verifyTokenEvent['reason'];
                                                            $policyDetails->save();

                                                            $transaction                          = new PaymentTransaction();
                                                            $transaction->policyNumber            = $policyDetails->policy_number;
                                                            $transaction->referenceNumber         = $policyDetails->token;
                                                            $transaction->TransactionToken        = $policyDetails->token;
                                                            $transaction->amount                  = $policyDetails->premium;
                                                            $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                                            $transaction->paymentMethod           = 'DPO';
                                                            $transaction->note                    = $verifyTokenEvent['reason'];
                                                            $transaction->numberOfInstalmentsPaid = $policyDetails->installment;
                                                            $transaction->status                  = 'FAILED';
                                                            $transaction->payment_transaction_id  = $policyDetails->id;
                                                            $transaction->save();

                                                            $dpoReport->status = 'FAILED';
                                                            $customerdatamain = Customer::where('id',$policy->customer_id)->first();
                                                            $dpoReport->email = $policy->email;
                                                            $dpoReport->customer_name = $customerdatamain->firstName.' '.$customerdatamain->lastName;
                                                            $dpoReport->cellphone = $customerdatamain->cellphone;
                                                            $dpoReport->premium = $policyDetails->premium;
                                                            $dpoReport->reason = $transaction->note;
                                                            $dpoReport->retry_count = $policyDetails->retry_count -1;
                                                            $dpoReport->save();
                                                            PullAccountEvent::dispatch($data);

                                                            $this->error('Payment failed for policy: '. $policyDetails->policy_number );
                                                            activity('Policy')
                                                            ->performedOn($policyDetails)
                                                            ->log('Payment failed for policy: '. $policyDetails->policy_number);
                                                        }
                                                    }else
                                                    {
                                                        //update next billing date
                                                        $policyDetails->retry_count  = $policyDetails->retry_count + 1; //0 to 4
                                                        $policyDetails->billing_date = $dpo->nextBillingDate($policyDetails->retry_count, $policyDetails->billing_date); // 2 days, 5 days, 15 days, 30 days retry payment
                                                        $policyDetails->status       = 3; //failed
                                                        $policyDetails->reason       = isset($chargeTokenRecurrentEvent['reason']) ? $chargeTokenRecurrentEvent['reason'] : null;
                                                        $policyDetails->save();

                                                        $transaction                          = new PaymentTransaction();
                                                        $transaction->policyNumber            = $policyDetails->policy_number;
                                                        $transaction->referenceNumber         = $policyDetails->token;
                                                        $transaction->TransactionToken        = $policyDetails->token;
                                                        $transaction->amount                  = $policyDetails->premium;
                                                        $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                                        $transaction->paymentMethod           = 'DPO';
                                                        $transaction->note                    = isset($chargeTokenRecurrentEvent['reason']) ? $chargeTokenRecurrentEvent['reason'] : null;
                                                        $transaction->numberOfInstalmentsPaid = $policyDetails->installment;
                                                        $transaction->status                  = 'FAILED' ;
                                                        $transaction->payment_transaction_id  = $policyDetails->id;
                                                        $transaction->save();

                                                        $dpoReport->status = 'FAILED';
                                                        $customerdatamain = Customer::where('id',$policy->customer_id)->first();
                                                        $dpoReport->email = $policy->email;
                                                        $dpoReport->customer_name = $customerdatamain->firstName.' '.$customerdatamain->lastName;
                                                        $dpoReport->cellphone = $customerdatamain->cellphone;
                                                        $dpoReport->premium = $policyDetails->premium;
                                                        $dpoReport->reason = $transaction->note;
                                                        $dpoReport->retry_count = $policyDetails->retry_count -1;
                                                        $dpoReport->save();

                                                        PullAccountEvent::dispatch($data);

                                                        $this->error('Payment failed for policy: '. $policyDetails->policy_number );
                                                        activity('Policy')
                                                        ->performedOn($policyDetails)
                                                        ->log('Payment failed for policy: '. $policyDetails->policy_number);
                                                    }
                                                }
                                                elseif($policyDetails->subscription_token != null && $policyDetails->token != null && $policyDetails->customer_token != null && $policyDetails->retry_count < 5){
                                                    // ─── DOUBLE-CHARGE FIX (1/3) — same as primary path ───
                                                    $previousStatus  = $policyDetails->status;
                                                    $chargeAttemptId = (int) round(microtime(true) * 1000);
                                                    $newRetryCount   = $policyDetails->retry_count + 1;
                                                    $claimed = DB::table('scheduled_transactions')
                                                        ->where('id', $policyDetails->id)
                                                        ->where('status', $previousStatus)
                                                        ->where('retry_count', $policyDetails->retry_count)
                                                        ->update([
                                                            'status'      => 9,
                                                            'retry_count' => $newRetryCount,
                                                            'updated_at'  => Carbon::now(),
                                                        ]);
                                                    if ($claimed === 0) {
                                                        Log::warning('dpo.schedule_transaction.row_already_claimed', [
                                                            'id'                  => $policyDetails->id,
                                                            'policy_number'       => $policyDetails->policy_number,
                                                            'previous_status'     => $previousStatus,
                                                            'previous_retry_count'=> $policyDetails->retry_count,
                                                            'path'                => 'fallback',
                                                        ]);
                                                        continue;
                                                    }
                                                    $policyDetails->retry_count = $newRetryCount;

                                                    $data = [
                                                        'policy_number'     => $policyDetails->policy_number,
                                                        "customer_id"       => $policyDetails->customer_id,
                                                        "customerToken"     => $policyDetails->customer_token,
                                                        "amount"            => $policyDetails->premium,
                                                        "token"             => $policyDetails->token,
                                                        "TransactionToken"  => $policyDetails->token,
                                                        "api_name"          => 'chargeTokenRecurrent',
                                                        // ─── DOUBLE-CHARGE FIX (2/3) ──────────────────────
                                                        "CompanyRef"        => $policyDetails->policy_number.'/'.$policyDetails->installment.'/'.$newRetryCount.'/'.$chargeAttemptId,
                                                        "email"             => $policyDetails->email,
                                                        "subscriptionToken" => $policyDetails->subscription_token,
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
                                                            $policyDetails->billing_date = Carbon::today()->format('Y-m-d H:i:s');
                                                            $policyDetails->status       = 2; //payment successful
                                                            $policyDetails->reason       = $verifyTokenEvent['reason'];
                                                            $policyDetails->save();

                                                            $transaction                          = new PaymentTransaction();
                                                            $transaction->policyNumber            = $policyDetails->policy_number;
                                                            $transaction->referenceNumber         = $policyDetails->token;
                                                            $transaction->TransactionToken        = $policyDetails->token;
                                                            $transaction->amount                  = $policyDetails->premium;
                                                            $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                                            $transaction->paymentMethod           = 'DPO';
                                                            $transaction->numberOfInstalmentsPaid = $policyDetails->installment;
                                                            $transaction->paymentFrequency        =  1;
                                                            $transaction->status                  = 'SUCCESS';
                                                            $transaction->save();

                                                            $dpoReport->status = 'SUCCESS';
                                                            $customerdatamain = Customer::where('id',$policy->customer_id)->first();
                                                            $dpoReport->email = $policy->email;
                                                            $dpoReport->customer_name = $customerdatamain->firstName.' '.$customerdatamain->lastName;
                                                            $dpoReport->cellphone = $customerdatamain->cellphone;
                                                            $dpoReport->premium = $policyDetails->premium;
                                                            $dpoReport->save();

                                                            $account = PullAccountEvent::dispatch($data);
                                                            $this->info('Policy Number:'. $policyDetails->policy_number . ' Payment Done.');
                                                            activity('Policy')
                                                            ->performedOn($policyDetails)
                                                            ->log('Policy Number:'. $policyDetails->policy_number . ' Payment Done.');

                                                           
                                                        }else
                                                        {

                                                            //update next billing date
                                                            $policyDetails->retry_count  = $policyDetails->retry_count + 1; //0 to 4
                                                            $policyDetails->billing_date = $dpo->nextBillingDate($policyDetails->retry_count, $policyDetails->billing_date); // 2 days, 5 days, 15 days, 30 days retry payment
                                                            $policyDetails->status       = 3; //failed
                                                            $policyDetails->reason       = $verifyTokenEvent['reason'];
                                                            $policyDetails->save();

                                                            $transaction                          = new PaymentTransaction();
                                                            $transaction->policyNumber            = $policyDetails->policy_number;
                                                            $transaction->referenceNumber         = $policyDetails->token;
                                                            $transaction->TransactionToken        = $policyDetails->token;
                                                            $transaction->amount                  = $policyDetails->premium;
                                                            $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                                            $transaction->paymentMethod           = 'DPO';
                                                            $transaction->note                    = $verifyTokenEvent['reason'];
                                                            $transaction->numberOfInstalmentsPaid = $policyDetails->installment;
                                                            $transaction->status                  = 'FAILED';
                                                            $transaction->payment_transaction_id  = $policyDetails->id;
                                                            $transaction->save();

                                                            $dpoReport->status = 'FAILED';
                                                            $customerdatamain = Customer::where('id',$policy->customer_id)->first();
                                                            $dpoReport->email = $policy->email;
                                                            $dpoReport->customer_name = $customerdatamain->firstName.' '.$customerdatamain->lastName;
                                                            $dpoReport->cellphone = $customerdatamain->cellphone;
                                                            $dpoReport->premium = $policyDetails->premium;
                                                            $dpoReport->reason = $transaction->note;
                                                            $dpoReport->retry_count = $policyDetails->retry_count -1;
                                                            $dpoReport->save();

                                                            PullAccountEvent::dispatch($data);

                                                            $this->error('Payment failed for policy: '. $policyDetails->policy_number );
                                                            activity('Policy')
                                                            ->performedOn($policyDetails)
                                                            ->log('Payment failed for policy: '. $policyDetails->policy_number);
                                                        }
                                                    }else
                                                    {
                                                        //update next billing date
                                                        $policyDetails->retry_count  = $policyDetails->retry_count + 1; //0 to 4
                                                        $policyDetails->billing_date = $dpo->nextBillingDate($policyDetails->retry_count, $policyDetails->billing_date); // 2 days, 5 days, 15 days, 30 days retry payment
                                                        $policyDetails->status       = 3; //failed
                                                        $policyDetails->reason       = isset($chargeTokenRecurrentEvent['reason']) ? $chargeTokenRecurrentEvent['reason'] : null;
                                                        $policyDetails->save();

                                                        $transaction                          = new PaymentTransaction();
                                                        $transaction->policyNumber            = $policyDetails->policy_number;
                                                        $transaction->referenceNumber         = $policyDetails->token;
                                                        $transaction->TransactionToken        = $policyDetails->token;
                                                        $transaction->amount                  = $policyDetails->premium;
                                                        $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                                        $transaction->paymentMethod           = 'DPO';
                                                        $transaction->note                    = isset($chargeTokenRecurrentEvent['reason']) ? $chargeTokenRecurrentEvent['reason'] : null;
                                                        $transaction->numberOfInstalmentsPaid = $policyDetails->installment;
                                                        $transaction->status                  = 'FAILED' ;
                                                        $transaction->payment_transaction_id  = $policyDetails->id;
                                                        $transaction->save();

                                                        $dpoReport->status = 'FAILED';
                                                        $customerdatamain = Customer::where('id',$policy->customer_id)->first();
                                                        $dpoReport->email = $policy->email;
                                                        $dpoReport->customer_name = $customerdatamain->firstName.' '.$customerdatamain->lastName;
                                                        $dpoReport->cellphone = $customerdatamain->cellphone;
                                                        $dpoReport->premium = $policyDetails->premium;
                                                        $dpoReport->reason = $transaction->note;
                                                        $dpoReport->retry_count = $policyDetails->retry_count -1;
                                                        $dpoReport->save();

                                                        PullAccountEvent::dispatch($data);

                                                        $this->error('Payment failed for policy: '. $policyDetails->policy_number );
                                                        activity('Policy')
                                                        ->performedOn($policyDetails)
                                                        ->log('Payment failed for policy: '. $policyDetails->policy_number);
                                                    }
                                                }
                                                else{
                                                    $policyDetails->retry_count  = $policyDetails->retry_count + 1; //0 to 4
                                                    $policyDetails->billing_date = $dpo->nextBillingDate($policyDetails->retry_count, $policyDetails->billing_date); // 2 days, 5 days, 15 days, 30 days retry payment
                                                    $policyDetails->status       = 3; //failed

                                                    if ($policyDetails->subscription_token == null) {
                                                        $policyDetails->reason       = 'missing subscription token';
                                                    } elseif ($policyDetails->token == null) {
                                                        $policyDetails->reason       = 'missing token';
                                                    } elseif ($policyDetails->customer_token == null) {
                                                        $policyDetails->reason       = 'missing customer token';
                                                    } elseif ($policyDetails->retry_count > 4) {
                                                        $policyDetails->reason       = 'retry count is greater than 4';
                                                    } else {
                                                        $policyDetails->reason       = 'Retry Transaction Failed';
                                                    }
                                                    $policyDetails->save();

                                                    $transaction                          = new PaymentTransaction();
                                                    $transaction->policyNumber            = $policyDetails->policy_number;
                                                    $transaction->amount                  = $policyDetails->premium;
                                                    $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                                    $transaction->paymentMethod           = 'DPO';
                                                    $transaction->note                    = $policyDetails->reason;
                                                    $transaction->numberOfInstalmentsPaid = $policyDetails->installment;
                                                    $transaction->paymentFrequency        =  1;
                                                    $transaction->status                  = 'FAILED' ;
                                                    $transaction->payment_transaction_id  = $policyDetails->id;
                                                    $transaction->save();

                                                    $dpoReport->status = 'FAILED';
                                                    $customerdatamain = Customer::where('id',$policy->customer_id)->first();
                                                    $dpoReport->email = $policy->email;
                                                    $dpoReport->customer_name = $customerdatamain->firstName.' '.$customerdatamain->lastName;
                                                    $dpoReport->cellphone = $customerdatamain->cellphone;
                                                    $dpoReport->premium = $policyDetails->premium;
                                                    $dpoReport->reason = $transaction->note;
                                                    $dpoReport->retry_count = $policyDetails->retry_count -1;
                                                    $dpoReport->save();

                                                    activity('Policy')
                                                    ->performedOn($policyDetails)
                                                    ->log('Policy is rescheduled for payment:'. $policyDetails->policy_number);

                                                    $this->error('Failed to create token for policy to proceed payment: '. $policyDetails->policy_number );
                                                }

                                            }else{
                                                $this->info('Payment record exists for Policy Number:'. $policyDetails->policy_number );
                                            }
                                        }

                                    }else //failed
                                    {
                                        $policy->retry_count  = $policy->retry_count + 1; //0 to 4
                                        $policy->billing_date = $dpo->nextBillingDate($policy->retry_count, $policy->billing_date); // 2 days, 5 days, 15 days, 30 days retry payment
                                        $policy->status       = 3; //failed
                                        $policy->reason       = 'failed to generate subscription token';
                                        $policy->save();

                                        $dpoReport->status = 'FAILED';
                                        $customerdatamain = Customer::where('id',$policy->customer_id)->first();
                                        $dpoReport->email = $policy->email;
                                        $dpoReport->customer_name = $customerdatamain->firstName.' '.$customerdatamain->lastName;
                                        $dpoReport->cellphone = $customerdatamain->cellphone;
                                        $dpoReport->premium = $policy->premium;
                                        $dpoReport->reason = 'failed to generate subscription token';
                                        $dpoReport->retry_count = $policy->retry_count -1;
                                        $dpoReport->save();

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

                                $dpoReport->status = 'FAILED';
                                $customerdatamain = Customer::where('id',$policy->customer_id)->first();
                                $dpoReport->email = $policy->email;
                                $dpoReport->customer_name = $customerdatamain->firstName.' '.$customerdatamain->lastName;
                                $dpoReport->cellphone = $customerdatamain->cellphone;
                                $dpoReport->premium = $policy->premium;
                                $dpoReport->reason = 'missing required tokens';
                                $dpoReport->retry_count = $policy->retry_count -1;
                                $dpoReport->save();
                                activity('Policy')
                                ->performedOn($policy)
                                ->log('Policy is rescheduled for payment:'. $policy->policy_number);

                                $this->error('Failed to create token for policy to proceed payment: '. $policy->policy_number );
                            }
                            DB::commit();
                        }catch(Exception $ex)
                        {
                            Log::error(json_encode($ex->getMessage()));
                           //  $this->error($ex->getMessage());
                             DB::rollback();
                        }
                    }  else {
                        $policy->status = 5;    // payment that can not be paid due to dpo error codes
                        $policy->save();
                       
                        $dpoReport->status = 'HOLD';
                        $customerdatamain = Customer::where('id',$policy->customer_id)->first();
                        $dpoReport->email = $policy->email;
                        $dpoReport->customer_name = $customerdatamain->firstName.' '.$customerdatamain->lastName;
                        $dpoReport->cellphone = $customerdatamain->cellphone;
                        $dpoReport->premium = $policy->premium;
                        $dpoReport->reason = $policy->reason;
                        $dpoReport->retry_count = $policy->retry_count;
                        $dpoReport->save();


                        $customerData = Customer::where('id',$policy->customer_id)->first();
                        if (isset($customerData)) {
                            $policy['customer_name'] = $customerData->firstName . ' ' . $customerData->lastName;
                            $policy['cellphone'] = $customerData->cellphone;
                        }

                        //array_push($dpoErrorCodesTx,$policy);
                    }

                   // $policyPayment = ScheduleTransaction::where('policy_number',$policy->policy_number)->where('id',$policy->id)->first();

                    //array_push($paymentDonePolicies,$policyPayment);

                    $addLog = new DPOPaymentDoneData();
                    $addLog->policy_id = $policy->policy_id;
                    $addLog->policy_number = $policy->policy_number;
                    $addLog->customer_id = $policy->customer_id;
                    $addLog->installment = $policy->installment;
                    $addLog->retry_count = $policy->retry_count;
                    $addLog->premium = $policy->premium;
                    $addLog->email = $policy->email;
                    $addLog->city = $policy->city;
                    $addLog->token = $policy->token;
                    $addLog->subscription_token = $policy->subscription_token;
                    $addLog->customer_token = $policy->customer_token;
                    $addLog->billing_date = $policy->billing_date;
                    $addLog->payment_method = $policy->payment_method;
                    $addLog->reason = $policy->reason;
                    $addLog->status = $policy->status;
                    $addLog->created_at = Carbon::now()->format("Y-m-d H:i:s");
                    $addLog->save();

                    // Failed recurring collection -> notify the customer (MIS only).
                    // Re-read the schedule row rather than trusting $policy: the
                    // charge branches above mutate a re-fetched $policyDetails copy,
                    // so the outer $policy still holds the pre-charge status here.
                    // Runs after DB::commit(), and the notifier is keyed on
                    // (schedule id, installment, retry_count) so a re-run over a row
                    // still sitting at status 3 sends nothing a second time.
                    $settled = ScheduleTransaction::where('id', $policy->id)->first();
                    if ($settled != null && $settled->status == 3) {
                        app(MisRecurringPaymentFailureNotifier::class)->notify(
                            (string) $settled->policy_number,
                            MisRecurringPaymentFailureNotifier::GATEWAY_DPO,
                            MisRecurringPaymentFailureNotifier::dpoEventReference(
                                $settled->id,
                                $settled->installment,
                                $settled->retry_count
                            )
                        );
                    }

                    // $paymentSuccess = ScheduleTransaction::where('policy_number',$policy->policy_number)->where('id',$policy->id)->where('status', 2)->first();
                    // if (isset($paymentSuccess)) {
                    //    // array_push($paymentSuccessArr,$paymentSuccess);
                    // }

                    // $paymentFailed = ScheduleTransaction::where('policy_number',$policy->policy_number)->where('id',$policy->id)->where('status', '!=', 2)->first();
                    // if (isset($paymentFailed)) {
                    //     //array_push($paymentFailedArr,$paymentFailed);
                    // }
                }

                sleep(1);
            }
        }else{
            $this->info('No policies are there to proceed');
        }

        //$transactions = [];
        // if (isset($paymentDonePolicies)) {
        //     foreach ($paymentDonePolicies as $key => $policy) {
        //         $customerData = Customer::where('id',$policy->customer_id)->first();
        //         if (isset($customerData)) {
        //             $policy['customer_name'] = $customerData->firstName . ' ' . $customerData->lastName;
        //             $policy['cellphone'] = $customerData->cellphone;
        //         }
        //         if ($policy->status != 5) {
        //             if ($policy->retry_count > 4) {
        //                 $payment_transaction = PaymentTransaction::where('payment_transaction_id',$policy->id)
        //                         ->where('policyNumber',$policy->policy_number)
        //                         ->orderBy('id','desc')
        //                         ->skip(1)
        //                         ->take(1)
        //                         ->first();
        //                 if (isset($payment_transaction)) {
        //                     $customerData = Customer::where('id',$policy->customer_id)->first();
        //                     if (isset($customerData)) {
        //                         $payment_transaction['customer_name'] = $customerData->firstName . ' ' . $customerData->lastName;
        //                         $payment_transaction['cellphone'] = $customerData->cellphone;
        //                     }
        //                     $payment_transaction->email = $policy->email;
        //                     $payment_transaction->retry_count = $policy->retry_count;
        //                     $payment_transaction->status = $policy->status;
        //                     array_push($transactions,$payment_transaction);
        //                 }
        //             } else {
        //                 array_push($transactions,$policy);
        //             }
        //         }
        //     }
        // }
        $start = now()->startOfDay();
        $end =  now()->endOfDay();
        $paymentDonePolicies = DpoTransactionReport::whereBetween('created_at', [$start, $end])->whereIn('status', ['SUCCESS','FAILED','HOLD'])->get();
        $paymentSuccessArr = DpoTransactionReport::whereBetween('created_at', [$start, $end])->where('status', 'SUCCESS')->get();
        $paymentFailedArr = DpoTransactionReport::whereBetween('created_at', [$start, $end])->where('status', 'FAILED')->get();
        $dpoErrorCodesTx = DpoTransactionReport::whereBetween('created_at', [$start, $end])->where('status', 'HOLD')->get();
        $transactions = DpoTransactionReport::whereBetween('created_at', [$start, $end])->whereIn('status', ['SUCCESS','FAILED'])->get();
        $report = [
            'policies'                 => $paymentDonePolicies,
            'missingTokenTransactions' => $missingTokenTransactions,
            'successTransactions' => count($paymentSuccessArr),   
            'failedTransactions' => count($paymentFailedArr),   
            'transactions'       => $transactions,
            'dpoFailedTransactions'    => $dpoErrorCodesTx,
        ];

        $result = bin2hex(random_bytes(20));
        $date = Carbon::now()->timestamp;
        $path = 'paymentByDpo-'.$date.$result.'/paymentByDpo.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.dpoTransactionsReportsecond', $report)->setPaper('a3', 'landscape');
        Storage::disk('s3')->put($path, $pdf->output(), 'public');

        $attachments = array();
        array_push($attachments, $path);
        if(count($paymentDonePolicies) > 0 || count($missingTokenTransactions) > 0){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'dpo_transaction_today';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
            ////*************Email send new fuction END **************/////
        }
      

        // Storage::disk('s3')->delete($path);
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
