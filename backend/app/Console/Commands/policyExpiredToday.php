<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\DpoPaymentController;
use AlphaDirect\Http\Controllers\ExcelImportController;
use AlphaDirect\Http\Controllers\Payment\VCS\PaymentController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Models\PolicyRenewal;
use AlphaDirect\Models\User;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\PolicyTerm;
use AlphaDirect\RealpayCancelRequests;
use AlphaDirect\RealpayFailedTransEmails;
use AlphaDirect\RealpayLogs;
use AlphaDirect\Transaction;
use Illuminate\Support\Facades\Storage;
use PDF;
use Auth;
use File;
use Log;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Models\ExpiredPoliciesImportJobs;

class policyExpiredToday extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policyExpiredToday:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expiring policy';

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
        $cron->name = "policyExpiredToday:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

        Log::info('Cron Started for policy expiring.');

        $today = Carbon::now()->format('Y-m-d');

        $policyArr = array();
        ExpiredPoliciesImportJobs::where('expiry_date','<',$today)->where('can_expired',1)->where('is_renewed',0)->chunkById(100, function($policies) use (&$policyArr) {
            if (isset($policies)) {
                foreach ($policies as $key => $policy) {
                    $termsNotExpired = 0;
                    $this->info("Policy Number: ". $policy->policyNumber);

                    // $today = Carbon::now()->format('Y-m-d');
                    // $policyTerm = PolicyTerm::where('policy_id',$policy->id)->where('term_end_date','>',$today)->get();

                    array_push($policyArr,$policy);
                        // $policyController = new PolicyController();
                        // $cancelPayment = $policyController->CancelPaymentsForPolicy($policy);

                        // if (isset($cancelPayment->getData()->status) && $cancelPayment->getData()->status == true) {

                        //     $policy->can_expired = 2;
                        //     $policy->save();

                        //     $policy_data = Policy::where('policyNumber',$policy->policyNumber)->first();
                        //     $policy_data->status = 3;
                        //     $policy_data->save();

                        //     $this->info("Policy Number: ". $policy->policyNumber." has been expired.");

                        //     $customer = Customer::where('id',$policy_data->customer_id)->first();
                        //     if (isset($customer)) {
                        //         $email_data = [
                        //             'email'=>$customer->email,
                        //             'cellphone'=>$customer->cellphone,
                        //             'customer_id'=>$policy->customer_id,
                        //             'policyNumber'=>$policy->policyNumber,
                        //         ];
                        //         $excelController = new ExcelImportController();
                        //         $sendsmsemail = $excelController->sendPolicyExpiredSmsEmail($email_data);
                        //     }

                        // }

                        // activity('Policy')
                        // ->performedOn($policy)
                        // ->log('Policy expired');

                        // event(new \AlphaDirect\Events\policyLifecycle($policy->id , "Expired"));
                    // sleep(1) removed — pure DB operation, no rate limiting needed
                }

            }

        });

        if (count($policyArr) > 0 ) {

            $data = [
                'policies'=>$policyArr,
                // 'weeklyPolicy' =>$policies_weekly_data,
            ];

            $date = \Carbon\Carbon::now()->timestamp;
            $path = 'Policy/created-'.$date.'/PoliciesExpired.pdf';

            libxml_use_internal_errors(true);
            $pdf = PDF::loadView('admin.notes.policies_expired', $data);

            Storage::disk('s3')->put($path, $pdf->output(), 'public');
            // dd($path);
            $attachments = array();
            array_push($attachments, $path);
            $email = array();
            if(env('APP_STATUS') == 'Production') {
                $email = array(
                    'aprasad@alphadirect.co.bw',
                    'kkatolkar@alphadirect.co.bw',
                    // 'amunzara@alphadirect.co.bw',
                    // 'pmaswibilili@alphadirect.co.bw',
                    // 'arjuniyer@alphadirect.co.bw',
                    // 'nbarot@theriskco.com',
                    // 'pganesharajah@alphadirect.co.bw',
                    // 'tmotlogelwa@alphadirect.co.bw',
                    // 'lntabeni@alphadirect.co.bw',
                    // 'kbotana@alphadirect.co.bw',
                    // 'aiyer@alphadirect.co.bw',
                );
            }else{
                $email = array('aprasad@alphadirect.co.bw');
            }

            if(count($email) > 0) {
                foreach($email as $d){
                    if($d){
                        $data = new \stdClass();
                        $data->user_id = null;
                        $data->hook = 'policies_expired';
                        $data->customer_id = null;
                        $data->attachment = $attachments;
                        $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                        $markdown = new MailTemplate($data);
                        $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                        event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data->hook]));
                        //   $sent = \Illuminate\Support\Facades\Mail::to($d)->send(new MailTemplate($data));
                    }
                }
            }

            Storage::disk('s3')->delete($path);
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
