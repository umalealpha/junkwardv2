<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use AlphaDirect\DpoErrorCodes;
use AlphaDirect\Events\ChargeTokenRecurrentEvent;
use AlphaDirect\Events\CreateTokenEvent;
use AlphaDirect\Events\PullAccountEvent;
use AlphaDirect\Events\SubscriptionTokenEvent;
use AlphaDirect\Http\Controllers\DpoPaymentController;
use AlphaDirect\Policy;
use AlphaDirect\ScheduleTransaction;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use AlphaDirect\Mail\MailTemplate;
use Illuminate\Support\Facades\Storage;
use stdClass;
use PDF;
use AlphaDirect\EmailBroadcasting;
use Intervention\Image\Size;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;

class GenerateSubscriptionTokenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policy:generateSubscriptionToken {id?}';

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
        $cron->name = "policy:generateSubscriptionToken";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $transId = $this->argument('id');

        if(isset($transId) && ScheduleTransaction::where('id', $transId)->exists())
        {
            $policies = ScheduleTransaction::where('id', $transId)->get();
        }else{
            $policies = ScheduleTransaction::getInactiveScheduledTransactions();
        }
        $this->info('Running...');

        $dpo = new DpoPaymentController();

        // dd($policies);

        $dpoErrorCodesTx = [];

        if(count($policies) > 0)
        {
            foreach($policies as $policy)
            {
             if( !Policy::where('policyNumber',$policy->policy_number)->where('status',2)->exists())
               {

                $transactionReason = $policy->reason;

                if (isset($policy) && $policy->reason == 'Invalid expiry date') {

                    $dataCheck = [];

                    $createTok = CreateTokenEvent::dispatch($policy);
                    $createTok = isset($createTok[0]) ? $createTok[0] : null;

                        if(isset($createTok) && $createTok['status'] == 1)
                        {
                            $dataCheck = [
                                'policy_number'    => $policy->policy_number,
                                "customer_id"      => $createTok['customer_id'],
                                "amount"           => $createTok['amount'],
                                "dpo_error_code"   => $createTok['dpo_error_code'],
                                "reason"           => $createTok['reason'],
                                "token"            => $createTok['token'],
                                "TransactionToken" => $createTok['TransactionToken'],
                                "TransID"          => $createTok['TransID'],
                                "api_name"         => 'subscriptionToken',
                                "response_json"    => $createTok['response_json'],
                                "request_json"     => $createTok['request_json'],
                                "CompanyRef"       => $policy->policy_number.'/'.$policy->installment.'/'.$policy->retry_count,
                                "email"            => $createTok['email'] ,
                            ];
                        } else {
                            $dataCheck  = null;
                        }

                    if (isset($dataCheck)) {
                        $subscriptionTokEnt = SubscriptionTokenEvent::dispatch($dataCheck);
                        $subscriptionTokEnt = isset($subscriptionTokEnt[0]) ? $subscriptionTokEnt[0] : null;

                        if($subscriptionTokEnt['status'] == 1)
                        {
                            $dataCheck['customerToken']     = $subscriptionTokEnt['customerToken'];
                            $dataCheck['reason']            = $subscriptionTokEnt['reason'];
                        } else {
                            $dataCheck['customerToken']            = null;
                        }

                        $account = PullAccountEvent::dispatch($dataCheck);
                        $this->info('checking status Running...');
                        if (isset($account) && isset($account[0]['status']) == 1) {
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

                                if ($subscriptionToken != $policy->subscription_token) {
                                    $this->info('reset status Running...');
                                    $resetStatus = ScheduleTransaction::where('id', $policy->id)->first();
                                    if (isset($resetStatus)) {
                                        $resetStatus->reason = null;
                                        $resetStatus->status = 0;
                                        $resetStatus->save();
                                    }

                                    $transactionReason = null;
                                }
                            }
                        }

                    }
                }

                $checkCodes = DpoErrorCodes::where('reason', $transactionReason)->first();

                if (!isset($checkCodes)) {
                    $this->info('Policy Number:'. $policy->policy_number );
                    DB::beginTransaction();
                    try{
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
                        DB::commit();
                    }catch(Exception $ex)
                    {
                        $this->error($ex->getMessage());
                        DB::rollback();
                    }
                }  else {
                    $policy->status = 5;    // payment that can not be paid due to dpo error codes
                    $policy->save();

                    $customerData = Customer::where('id',$policy->customer_id)->first();
                    if (isset($customerData)) {
                        $policy['customer_name'] = $customerData->firstName . ' ' . $customerData->lastName;
                        $policy['cellphone'] = $customerData->cellphone;
                    }

                    array_push($dpoErrorCodesTx,$policy);
                }
              }
            }
        }else{
            $this->info('No policies are there to proceed');
        }



        $report = [
            'dpoFailedTransactions'    => $dpoErrorCodesTx
        ];

        $result = bin2hex(random_bytes(20));
        $date = Carbon::now()->timestamp;
        $path = 'failedPaymentByDpo-'.$date.$result.'/failedPaymentByDpo.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.dpoFailedTransactionsReport', $report)->setPaper('a3', 'landscape');
        Storage::disk('s3')->put($path, $pdf->output(), 'public');

        $attachments = array();
        array_push($attachments, $path);
      if(count($dpoErrorCodesTx) > 0){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'dpo_failed_transaction_today';
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
            $email = array('kkatolkar@alphadirect.co.bw','aprasad@alphadirect.co.bw');
        }

        if(count($email) > 0  && count($dpoErrorCodesTx) > 0 ) {
            foreach($email as $d){
                if($d){
                    $data2 = new \stdClass();
                    $data2->user_id = null;
                    $data2->hook = 'dpo_failed_transaction_today';
                    $data2->customer_id = null;
                    $data2->attachment = $attachments;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data2->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data2);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data2]);
                    event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data2->hook]));
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
