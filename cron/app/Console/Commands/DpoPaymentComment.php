<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Events\ChargeTokenRecurrentEvent;
use AlphaDirect\Events\CreateTokenEvent;
use AlphaDirect\Events\PullAccountEvent;
use AlphaDirect\Events\SubscriptionTokenEvent;
use AlphaDirect\Events\VerifyTokenEvent;
use AlphaDirect\Http\Controllers\DpoPaymentController;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\ScheduleTransaction;
use PDF;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use stdClass;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;

class DpoPaymentComment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dpo:pay {billing_date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pay with policy';

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
        $cron->name = "dpo:pay";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $this->info('Running...');
        $billing_date = $this->argument('billing_date');
        // dd($billing_date);
        if(isset($billing_date))
        {
            $policies = ScheduleTransaction::getInactiveScheduledTransactions($billing_date);
            // dd($policies);
        }else{
            $policies = ScheduleTransaction::getInactiveScheduledTransactions(null);
        }

        $dpo = new DpoPaymentController();

        if(count($policies) > 0)
        {
            foreach($policies as $policy)
            {
                $this->info('Policy Number:'. $policy->policy_number );
                DB::beginTransaction();
                try{
                    $check = PaymentTransaction::where('policyNumber', $policy->policyNumber)
                                ->where('paymentMethod', '=', 'DPO')
                                ->whereDate('created_at', Carbon::today())
                                ->exists();
                    //add valiation for one time payment in

                        $createToken = CreateTokenEvent::dispatch($policy);
                        $createToken = $createToken[0];

                    if($createToken['status'] == 1)
                    {

                        $data = [
                                "policy_number"    => $policy->policy_number,
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

                            if(isset($policy->subscription_token))
                            {
                                    $data['subscriptionToken']        = $policy->subscription_token;
                                    $data['customerToken']            = $policy->customer_token;
                                          $policy->subscription_token = $policy->subscription_token;
                                          $policy->customer_token     = $policy->customer_token;
                                          $policy->token              = $data['TransactionToken'];
                                          $policy->status             = 1;                                                                                    //in progress
                                          $policy->reason             = isset($subscriptionTokenEvent['reason']) ? $subscriptionTokenEvent['reason'] : null;  //in progress
                                    $policy->save();
                                    $this->info('Subscription Token generated for policy: '. $policy->policy_number);
                                    activity('Policy')
                                    ->performedOn($policy)
                                    ->log('Subscription Token generated for policy: '. $policy->policy_number. ' successfully');

                                    if($check == false)
                                    {
                                        if($policy->subscription_token != null && $policy->token != null && $policy->customer_token != null && $policy->retry_count < 5){
                                            // ─── DOUBLE-CHARGE FIX (atomic claim + unique CompanyRef) ───
                                            // Ported from backend/app/Console/Commands/DpoPaymentComment.php.
                                            // The cron container is the one that actually runs dpo:pay, so the
                                            // guard MUST live here: atomically claim the scheduled_transactions
                                            // row (id + prior status -> 9) so an overlapping/retried run cannot
                                            // charge the same instalment twice. The chargeAttemptId also makes
                                            // the DPO CompanyRef unique per attempt.
                                            $previousStatus  = $policy->status;
                                            $chargeAttemptId = (int) round(microtime(true) * 1000);
                                            $claimed = \DB::table('scheduled_transactions')
                                                ->where('id', $policy->id)
                                                ->where('status', $previousStatus)
                                                ->update(['status' => 9, 'updated_at' => \Carbon\Carbon::now()]);
                                            if ($claimed === 0) {
                                                \Log::warning('dpo.schedule_transaction.row_already_claimed', [
                                                    'id' => $policy->id, 'policy_number' => $policy->policy_number, 'cmd' => 'DpoPaymentComment',
                                                ]);
                                                continue;
                                            }
                                            $policy->status = 9;

                                            $data = [
                                                'policy_number'     => $policy->policy_number,
                                                "customer_id"       => $policy->customer_id,
                                                "customerToken"     => $policy->customer_token,
                                                "amount"            => $policy->premium,
                                                "token"             => $policy->token,
                                                "TransactionToken"  => $policy->token,
                                                "api_name"          => 'chargeTokenRecurrent',
                                                "CompanyRef"        => $policy->policy_number.'/'.$policy->installment.'/'.$policy->retry_count.'/'.$chargeAttemptId,
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
                                                    $transaction->referenceNumber         = $policy->token;
                                                    $transaction->TransactionToken        = $policy->token;
                                                    $transaction->amount                  = $policy->premium;
                                                    $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                                    $transaction->paymentMethod           = 'DPO';
                                                    $transaction->numberOfInstalmentsPaid = $policy->installment;
                                                    $transaction->paymentFrequency        =  1;
                                                    $transaction->status                  = 'SUCCESS';
                                                    // Make this inline DPO tx reachable by the ledger sweep:
                                                    // without is_ledger=0 the column defaults to NULL and the
                                                    // sweep's `where('is_ledger',0)` never matches (NULL=0 is
                                                    // false in SQL); new_payment_date lets the DOM/COM sweep +
                                                    // reporting post it to the Account Statement.
                                                    $transaction->is_ledger               = 0;
                                                    $transaction->new_payment_date        = Carbon::today()->format('Y-m-d');
                                                    $transaction->save();

                                                    $account = PullAccountEvent::dispatch($data);
                                                    $this->info('Policy Number:'. $policy->policy_number . ' Payment Done.');
                                                    activity('Policy')
                                                    ->performedOn($policy)
                                                    ->log('Policy Number:'. $policy->policy_number . ' Payment Done.');

                                                    if ($policy->email != null) {
                                                        $data               = new \stdClass();
                                                        $data->user_id      = $policy->id;
                                                        $data->policy_id    = $policy->policy_id;
                                                        $data->customer_id  = $policy->customer_id;
                                                        $data->premium      = $policy->premium;
                                                        $data->billing_date = $policy->billing_date;
                                                        $data->installment  = $policy->installment;
                                                        $data->hook         = 'payment_done';
                                                        $data->attachment   = null;
                                                        $email              = env('APP_STATUS') == 'Production' ? $policy->email : 'sshah@alphadirect.co.bw';
                                                        Mail::to($email)->send(new MailTemplate($data));
                                                    }
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
                                                    $transaction->referenceNumber         = $policy->token;
                                                    $transaction->TransactionToken        = $policy->token;
                                                    $transaction->amount                  = $policy->premium;
                                                    $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                                    $transaction->paymentMethod           = 'DPO';
                                                    $transaction->note                    = $verifyTokenEvent['reason'];
                                                    $transaction->numberOfInstalmentsPaid = $policy->installment;
                                                    $transaction->status                  = 'FAILED';
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
                                                $transaction->referenceNumber         = $policy->token;
                                                $transaction->TransactionToken        = $policy->token;
                                                $transaction->amount                  = $policy->premium;
                                                $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                                $transaction->paymentMethod           = 'DPO';
                                                $transaction->note                    = isset($chargeTokenRecurrentEvent['reason']) ? $chargeTokenRecurrentEvent['reason'] : null;
                                                $transaction->numberOfInstalmentsPaid = $policy->installment;
                                                $transaction->status                  = 'FAILED' ;
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
                                            $policy->reason       = 'missing required tokens';
                                            $policy->save();

                                            $transaction                          = new PaymentTransaction();
                                            $transaction->policyNumber            = $policy->policy_number;
                                            $transaction->amount                  = $policy->premium;
                                            $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                            $transaction->paymentMethod           = 'DPO';
                                            $transaction->note                    = $policy->reason;
                                            $transaction->numberOfInstalmentsPaid = $policy->installment;
                                            $transaction->paymentFrequency        =  1;
                                            $transaction->status                  = 'FAILED' ;
                                            $transaction->save();
                                            activity('Policy')
                                            ->performedOn($policy)
                                            ->log('Policy is rescheduled for payment:'. $policy->policy_number);

                                            $this->error('Failed to create token for policy to proceed payment: '. $policy->policy_number );
                                        }

                                    }else{
                                        $this->info('Payment record exists for Policy Number:'. $policy->policy_number );
                                    }
                            }
                            else{
                                $subscriptionTokenEvent = SubscriptionTokenEvent::dispatch($data);
                                $subscriptionTokenEvent = $subscriptionTokenEvent[0];

                                if($subscriptionTokenEvent['status'] == 1)
                                {

                                    $data['subscriptionToken']      = $subscriptionTokenEvent['subscriptionToken'];
                                    $data['customerToken']          = $subscriptionTokenEvent['customerToken'];
                                    $policy->subscription_token     = $subscriptionTokenEvent['subscriptionToken'];
                                    $policy->customer_token         = $subscriptionTokenEvent['customerToken'];
                                    $policy->token                  = $data['TransactionToken'];
                                    $policy->status                 = 1;                                                                                    //in progress
                                    $policy->reason                 = isset($subscriptionTokenEvent['reason']) ? $subscriptionTokenEvent['reason'] : null;  //in progress
                                    $policy->save();

                                    ScheduleTransaction::where('policy_number', $policy->policy_number)
                                                        ->update([
                                                            'subscription_token'     => $data['subscriptionToken']
                                                        ]);

                                    $this->info('Subscription Token generated for policy: '. $policy->policy_number);
                                    activity('Policy')
                                    ->performedOn($policy)
                                    ->log('Subscription Token generated for policy: '. $policy->policy_number. ' successfully');

                                    if($check == false)
                                    {
                                        if($policy->subscription_token != null && $policy->token != null && $policy->customer_token != null && $policy->retry_count < 5){
                                            // ─── DOUBLE-CHARGE FIX (atomic claim + unique CompanyRef) ───
                                            // Ported from backend/app/Console/Commands/DpoPaymentComment.php.
                                            // The cron container is the one that actually runs dpo:pay, so the
                                            // guard MUST live here: atomically claim the scheduled_transactions
                                            // row (id + prior status -> 9) so an overlapping/retried run cannot
                                            // charge the same instalment twice. The chargeAttemptId also makes
                                            // the DPO CompanyRef unique per attempt.
                                            $previousStatus  = $policy->status;
                                            $chargeAttemptId = (int) round(microtime(true) * 1000);
                                            $claimed = \DB::table('scheduled_transactions')
                                                ->where('id', $policy->id)
                                                ->where('status', $previousStatus)
                                                ->update(['status' => 9, 'updated_at' => \Carbon\Carbon::now()]);
                                            if ($claimed === 0) {
                                                \Log::warning('dpo.schedule_transaction.row_already_claimed', [
                                                    'id' => $policy->id, 'policy_number' => $policy->policy_number, 'cmd' => 'DpoPaymentComment',
                                                ]);
                                                continue;
                                            }
                                            $policy->status = 9;

                                            $data = [
                                                'policy_number'     => $policy->policy_number,
                                                "customer_id"       => $policy->customer_id,
                                                "customerToken"     => $policy->customer_token,
                                                "amount"            => $policy->premium,
                                                "token"             => $policy->token,
                                                "TransactionToken"  => $policy->token,
                                                "api_name"          => 'chargeTokenRecurrent',
                                                "CompanyRef"        => $policy->policy_number.'/'.$policy->installment.'/'.$policy->retry_count.'/'.$chargeAttemptId,
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
                                                    $transaction->referenceNumber         = $policy->token;
                                                    $transaction->TransactionToken        = $policy->token;
                                                    $transaction->amount                  = $policy->premium;
                                                    $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                                    $transaction->paymentMethod           = 'DPO';
                                                    $transaction->numberOfInstalmentsPaid = $policy->installment;
                                                    $transaction->paymentFrequency        =  1;
                                                    $transaction->status                  = 'SUCCESS';
                                                    // Make this inline DPO tx reachable by the ledger sweep:
                                                    // without is_ledger=0 the column defaults to NULL and the
                                                    // sweep's `where('is_ledger',0)` never matches (NULL=0 is
                                                    // false in SQL); new_payment_date lets the DOM/COM sweep +
                                                    // reporting post it to the Account Statement.
                                                    $transaction->is_ledger               = 0;
                                                    $transaction->new_payment_date        = Carbon::today()->format('Y-m-d');
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
                                                        $email              = env('APP_STATUS') == 'Production' ? $policy->email : 'sshah@alphadirect.co.bw';
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
                                                    $transaction->referenceNumber         = $policy->token;
                                                    $transaction->TransactionToken        = $policy->token;
                                                    $transaction->amount                  = $policy->premium;
                                                    $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                                    $transaction->paymentMethod           = 'DPO';
                                                    $transaction->note                    = $verifyTokenEvent['reason'];
                                                    $transaction->numberOfInstalmentsPaid = $policy->installment;
                                                    $transaction->status                  = 'FAILED';
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
                                                $transaction->referenceNumber         = $policy->token;
                                                $transaction->TransactionToken        = $policy->token;
                                                $transaction->amount                  = $policy->premium;
                                                $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                                $transaction->paymentMethod           = 'DPO';
                                                $transaction->note                    = isset($chargeTokenRecurrentEvent['reason']) ? $chargeTokenRecurrentEvent['reason'] : null;
                                                $transaction->numberOfInstalmentsPaid = $policy->installment;
                                                $transaction->status                  = 'FAILED' ;
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
                                            $policy->reason       = 'Failed to generate subscription token';
                                            $policy->save();

                                            $transaction                          = new PaymentTransaction();
                                            $transaction->policyNumber            = $policy->policy_number;
                                            $transaction->amount                  = $policy->premium;
                                            $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                            $transaction->paymentMethod           = 'DPO';
                                            $transaction->note                    = $policy->reason;
                                            $transaction->numberOfInstalmentsPaid = $policy->installment;
                                            $transaction->paymentFrequency        =  1;
                                            $transaction->status                  = 'FAILED' ;
                                            $transaction->save();
                                            activity('Policy')
                                            ->performedOn($policy)
                                            ->log('Policy is rescheduled for payment:'. $policy->policy_number);

                                            $this->error('Failed to create token for policy to proceed payment: '. $policy->policy_number );
                                        }

                                    }else{
                                        $this->info('Payment record exists for Policy Number:'. $policy->policy_number );
                                    }
                                }else
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
                            }
                    }else{
                        $policy->retry_count  = $policy->retry_count + 1; //0 to 4
                        $policy->billing_date = $dpo->nextBillingDate($policy->retry_count, $policy->billing_date); // 2 days, 5 days, 15 days, 30 days retry payment
                        $policy->status       = 3; //failed
                        $policy->reason       = 'Failed to create token';
                        $policy->save();

                        activity('Policy')
                        ->performedOn($policy)
                        ->log('Policy is rescheduled for payment:'. $policy->policy_number);

                        $this->error('Failed to create token for policy to proceed payment: '. $policy->policy_number );
                    }
                    DB::commit();
                }catch(Exception $ex)
                {
                    $this->error($ex->getMessage());
                    DB::rollback();
                }
            }

        }else{
            $this->info('No policies are there to proceed');
        }

        /* ------------------------------ */
        $report = [
            'policies'=>$policies,
            'successTransactions' => $policies->where('status', 2)   //2=success
                                    ->count(),
            'failedTransactions' => $policies->where('status', '!=', 2)   //1=failed
                                    ->count()
        ];

        $path = 'paymentByDpo-'.Carbon::today().'.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.dpoTransactionsReport', $report)->setPaper('a3', 'landscape');
        Storage::disk('s3')->put($path, $pdf->output(), 'public');

        $attachments = array();
        array_push($attachments, $path);
        if(count($policies) > 0 ){
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
                'sshah@alphadirect.co.bw',
                'aiyer@alphadirect.co.bw'
            );
        }else{
            $email = array('sshah@alphadirect.co.bw');
        }

        if(count($email) > 0 && count($policies) > 0) {
            foreach($email as $d){
                if($d){
                    $data2              = new \stdClass();
                    $data2->user_id     = null;
                    $data2->hook        = 'dpo_transaction_today';
                    $data2->customer_id = null;
                    $data2->attachment  = $attachments;
                    $emailTemplate      = EmailBroadcasting::where('hook_slug', $data2->hook)->first(array('subject'));
                    $markdown           = new MailTemplate($data2);
                    $html               = $markdown->render('Mail.mailTemplate',['data'=>$data2]);
                    event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data2->hook]));
                  //  $sent = \Illuminate\Support\Facades\Mail::to($d)->send(new MailTemplate($data));
                }
            }
            $cron->mail_send = 1;
            $cron->save();
        } */

        Storage::disk('s3')->delete($path);
        $cron->end = \Carbon\Carbon::now();
        $cron->save(); 

    }



}
