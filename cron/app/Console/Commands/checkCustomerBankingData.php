<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\CustomerBanking;
use AlphaDirect\CustomerFeedback;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\GFSEmails;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\KYC;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Models\CancelPolicy;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\RealpayCancelRequests;
use AlphaDirect\RealpayFailedTransEmails;
use AlphaDirect\RealpayLogs;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\VATMemoLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PDF;
use Auth;
use File;
use Log;
use DB;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;
class checkcustomerbankingdata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'checkcustomerbankingdata:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checking customer banking data';

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
     * @return mixed
     */
    // to update customers banking data in system
    public function handle()
    {
         $cron = new CronStatus();
        $cron->name = "checkcustomerbankingdata:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $data = DB::select(DB::raw(
            'select p.id as policyId,p.policyNumber as policy_number,p.customer_id as customer_id,cb.* from policies p
            left join customer_banking cb
            on p.id = cb.policy_id
            where cb.policy_id is null limit 100;'
        ));

        $records = [];
        $pc = new PolicyController();

        if(count($data) > 0){
            foreach($data as $key=>$d){
                sleep(1);
                try{
                    $billling = 'Payment not done';

                    $getBilling = $pc->getPolicyPaymentDetails($d->policy_number);

                    if($getBilling['status'] == true && $d->policy_number){
                        $billling = $getBilling['paymentMethod'];
                    }

                    $banking = new CustomerBanking();
                    $banking->customer_id = $d->customer_id;
                    $banking->policy_id = $d->policyId;
                    $banking->billing = $billling;
                    $banking->save();

                    if($banking->save() && $d->policy_number)
                        array_push($records,$d->policy_number);
                }catch(\Exception $ex){

                }
            }
        }

        $data = [
          'records'=>$records
        ];

        $todayDate = Carbon::now()->timestamp;

        $file = 'KYC/'.$todayDate.'/KYCData.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.kyccrondata', $data);
        Storage::disk('s3')->put($file, $pdf->output(), 'public');
        $attachments = array();
        array_push($attachments, $file);
        if(count($records) > 0 ){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'daily_kyc_report';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);

            ////*************Email send new fuction END **************/////
         }
     /*   if(env('APP_STATUS') == 'Production') {
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

         if(count($email) > 0 && count($records) > 0 ) {
            foreach($email as $d){
                if($d){
                    $data = new \stdClass();
                    $data->user_id = null;
                    $data->hook = 'daily_kyc_report';
                    $data->customer_id = null;
                    $data->attachment = $attachments;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                    event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data->hook]));
                    //  $sent = \Illuminate\Support\Facades\Mail::to($d)->send(new MailTemplate($data));
                }
            }
            $cron->mail_send = 1;
            $cron->save();
        } */
        Storage::disk('s3')->delete($file);
         $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
