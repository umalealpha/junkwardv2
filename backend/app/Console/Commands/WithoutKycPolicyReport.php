<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\PolicyTerm;
use AlphaDirect\Policy;
use AlphaDirect\Customer;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Storage;
use PDF;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use Log;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Vehicle;
use Illuminate\Support\Facades\DB;
use AlphaDirect\KYC;
use AlphaDirect\Stores;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;

class WithoutKycPolicyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'withoutkycpolicyreport:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'withoutkycpolicyreport:cron';

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
        $cron->name = "withoutkycpolicyreport:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $policyId = [];
        $policies = Policy::whereNotIn('product_id',['7','8'])->whereBetween('created_at', [Carbon::parse('today')->subDay(1)->format('Y-m-d')  , Carbon::parse('today')->subDay(1)->format('Y-m-d') . ' 23:59:59' ])
        ->get();
        if($policies->count() > 0){
            foreach($policies as $policy){
                $kyc  = KYC::where('customer_id',$policy->customer_id)->where(function ($query) {
                           $query->where('omang', '!=', null)
                          ->orWhere('passport', '!=', null);
                })->first();
               if( isset($kyc) && $kyc != null){

               }else{
                $policyId[] = $policy->id;
               }
                
                
                
               
            }
        }
        $policys = Policy::whereIn('id',$policyId)->get();
       
        $report = [
            'policies' => $policys,
           
            'title'    => 'Without KYC Docs Policy Reports'
        ];

        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'policies-'.$date.'/without_kyc_policy_report.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.without_kyc_policy_report', $report);
        Storage::disk('s3')->put($path, $pdf->output(), 'public');
       // dd($path);
        $attachments = array();
        array_push($attachments, $path);

        if(count($policy) > 0){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'without_kyc_policy_report';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
            ////*************Email send new fuction END **************/////
        }
     /*  $email = array();
        if(env('APP_STATUS') == 'Production') {
            $email = array(
                'satyajeetbcd@gmail.com',
                'kkatolkar@alphadirect.co.bw', */
               /*   'aprasad@alphadirect.co.bw',
                'sshah@alphadirect.co.bw',
              'pganesharajah@alphadirect.co.bw',
                'aiyer@alphadirect.co.bw',
                'kphatshwane@alphadirect.co.bw',
                'arjuniyer@alphadirect.co.bw',
                'nbarot@theriskco.com',
                'gchilala@alphadirect.co.zm' */
     /*       );
        }else{
            $email = array('satyajeetbcd@gmail.com','kkatolkar@alphadirect.co.bw');
        }

        if(count($email) > 0 && count($policys) > 0 ){
            foreach($email as $d){
                if($d){
                            $data = new \stdClass();
                            $data->user_id = null;
                            $data->hook = 'without_kyc_policy_report';
                            $data->customer_id = null;
                            $data->attachment = $attachments;
                            $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                            $markdown = new MailTemplate($data);
                            $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                            event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data->hook]));
                }
            }
            $cron->mail_send = 1;
            $cron->save();
        }  */

       // Storage::disk('s3')->delete($path); 


       $cron->end = \Carbon\Carbon::now();
       $cron->save(); 

        return 1;
    }
}
