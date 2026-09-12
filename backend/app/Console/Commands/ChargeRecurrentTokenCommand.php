<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use AlphaDirect\DpoErrorCodes;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Events\ChargeTokenRecurrentEvent;
use AlphaDirect\Events\PullAccountEvent;
use AlphaDirect\Events\VerifyTokenEvent;
use AlphaDirect\Http\Controllers\DpoPaymentController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\ScheduleTransaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use AlphaDirect\Mail\MailTemplate;
use Illuminate\Support\Facades\Storage;
use stdClass;
use PDF;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;
use AlphaDirect\Services\MisRecurringPaymentFailureNotifier;

class ChargeRecurrentTokenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policy:chargeRecurrentToken {id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recurrent payment proceed';

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
        $cron->name = "policy:chargeRecurrentToken";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $transId = $this->argument('id');

        if(isset($transId) && ScheduleTransaction::where('id', $transId)->exists())
        {
            $policies = ScheduleTransaction::where('id', $transId)->get();
        }else{
            $policies = ScheduleTransaction::getInactiveScheduledTransactions();
        }

        $missingTokenTransactions = ScheduleTransaction::whereIn('status', [0, 1, 3])   //2=success
                                ->whereDate('billing_date', '=', Carbon::today())
                                // ->whereBetween('billing_date', [$from, $to])
                                ->where('retry_count', '<', 6)
                                ->whereIn('email', [null, ''])
                                ->get();
        // dd($missingTokenTransactions);
        $dpo      = new DpoPaymentController();

        if(count($policies) > 0)
        {
            $this->info('Running...');

            foreach($policies as $policy)
            {
                if( !Policy::where('policyNumber',$policy->policy_number)->where('status',2)->exists())
                {
                if ($policy->status != 5) {
                    DB::beginTransaction();
                    try{
                        $this->info('Policy Number:'. $policy->policy_number );
                        $check = PaymentTransaction::where('policyNumber', $policy->policyNumber)
                                ->where('paymentMethod', 'DPO')
                                ->whereDate('paymentDate', Carbon::today())
                                ->exists();
                        // $check = false; // comment it before it goes live

                        if($check === false)
                        {
                            if($policy->subscription_token != null && $policy->token != null && $policy->customer_token != null && $policy->retry_count < 5){
                                // ─── DOUBLE-CHARGE FIX (atomic claim + unique CompanyRef) ───
                                $previousStatus  = $policy->status;
                                $chargeAttemptId = (int) round(microtime(true) * 1000);
                                $claimed = \DB::table('scheduled_transactions')
                                    ->where('id', $policy->id)
                                    ->where('status', $previousStatus)
                                    ->update(['status' => 9, 'updated_at' => \Carbon\Carbon::now()]);
                                if ($claimed === 0) {
                                    \Log::warning('dpo.schedule_transaction.row_already_claimed', [
                                        'id' => $policy->id, 'policy_number' => $policy->policy_number, 'cmd' => 'ChargeRecurrentTokenCommand',
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

                                        $customer_update = Customer::where('id',$policy->customer_id)->where('allow_email_update',0)->first();
                                        if (isset($customer_update)) {
                                            $customer_update->allow_email_update = 1;
                                            $customer_update->save();
                                        }

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
                        DB::commit();

                        // Failed recurring collection -> notify the customer (MIS only).
                        // status 3 is what every failure branch above sets. Fired after
                        // commit so we never tell a customer about a debit attempt that
                        // was rolled back. Keyed on (schedule id, installment,
                        // retry_count) so re-running this cron over a row that is still
                        // sitting at status 3 sends nothing a second time.
                        if ($policy->status == 3) {
                            app(MisRecurringPaymentFailureNotifier::class)->notify(
                                (string) $policy->policy_number,
                                MisRecurringPaymentFailureNotifier::GATEWAY_DPO,
                                MisRecurringPaymentFailureNotifier::dpoEventReference(
                                    $policy->id,
                                    $policy->installment,
                                    $policy->retry_count
                                )
                            );
                        }
                    }
                    catch(\Exception $ex)
                    {
                        DB::rollback();
                    }
                }
              }
            } //end foreach
        }
        else
        {
            $this->info('No policies are there to proceed');
        }

        $transactions = [];
        if (isset($policies)) {
            foreach ($policies as $key => $policy) {
                $customerData = Customer::where('id',$policy->customer_id)->first();
                if (isset($customerData)) {
                    $policy['customer_name'] = $customerData->firstName . ' ' . $customerData->lastName;
                    $policy['cellphone'] = $customerData->cellphone;
                }
                if ($policy->status != 5) {
                    if ($policy->retry_count > 4) {
                        $payment_transaction = PaymentTransaction::where('payment_transaction_id',$policy->id)
                                ->where('policyNumber',$policy->policy_number)
                                ->orderBy('id','desc')
                                ->skip(1)
                                ->take(1)
                                ->first();
                        if (isset($payment_transaction)) {
                            $customerData = Customer::where('id',$policy->customer_id)->first();
                            if (isset($customerData)) {
                                $payment_transaction['customer_name'] = $customerData->firstName . ' ' . $customerData->lastName;
                                $payment_transaction['cellphone'] = $customerData->cellphone;
                            }
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
        }

        $report = [
            'policies'                 => $policies,
            'missingTokenTransactions' => $missingTokenTransactions,
            'successTransactions' => $policies->where('status', 2)   //2=success
                                    ->count(),
            'failedTransactions' => $policies->where('status', '!=', 2)   //1=failed
                                    ->count(),
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
        if(count($policies) > 0 ||count($missingTokenTransactions) > 0){
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
                'aprasad@alphadirect.co.bw',
                'pganesharajah@alphadirect.co.bw',
                'arjuniyer@alphadirect.co.bw',
                'nbarot@theriskco.com',
                'rfartode@alphadirect.co.bw',
                'aiyer@alphadirect.co.bw'
            );
        }else{
        $email = array('kkatolkar@alphadirect.co.bw','aprasad@alphadirect.co.bw', 'satyajeetbcd@gmail.com');
        }

        if(count($email) > 0  && (count($policies) > 0 ||count($missingTokenTransactions) > 0)  ) {
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
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
