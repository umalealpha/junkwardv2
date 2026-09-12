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
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;

class AdiDuplicatePolicyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'adiduplicatepolicy:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Adi Duplicate Policy';

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
        $cron->name = "adiduplicatepolicy:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $policyId = [];
        $policyIdforLegel = [];
        $customers = Customer::all(['id']);
        foreach($customers as  $customer){
            $policies = Policy::where('product_id',1)->where('status',1)->where('customer_id',$customer->id)->orderBy('id','DESC')->get(['id']);
            if($policies->count() > 1){
                foreach($policies as $policy){
                    $policyId[] =   $policy->id;
                }
              
            }
            $Legalpolicies = Policy::where('product_id',4)->where('status',1)->where('customer_id',$customer->id)->orderBy('id','DESC')->get(['id']);
            if($Legalpolicies->count() > 1){
                foreach($Legalpolicies as $policyLegal){
                    $policyIdforLegel[] =   $policyLegal->id;
                }
              
            }

        }
       
        $AdiDuplicatepolicy = Policy::whereIn('id',$policyId)->orderBy('customer_id','DESC')->get();
        $LegalDuplicatepolicy = Policy::whereIn('id',$policyIdforLegel)->orderBy('customer_id','DESC')->get();

        $report = [
            'policies' => $AdiDuplicatepolicy,
            'policiesLegal' => $LegalDuplicatepolicy,
         
            'title'    => 'Adi and Legal Duplicate Policy Reports'
        ];

        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'policies-'.$date.'/adi_duplicate_policy_report.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.adi_duplicate_policy_report', $report);
        Storage::disk('s3')->put($path, $pdf->output(), 'public');
       //  dd($path);
        $attachments = array();
        array_push($attachments, $path);
        if(count($AdiDuplicatepolicy) > 0 || count($LegalDuplicatepolicy) > 0 ){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'adi_duplicate_policy_report';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
            ////*************Email send new fuction END **************/////
        }
     /*  $email = array();
        if(env('APP_STATUS') == 'Production') {
            $email = array(
                'satyajeetbcd@gmail.com',
                'kkatolkar@alphadirect.co.bw', */
               /*   'aprasad@alphadirect.co.bw',
                'sshah@alphadirect.co.bw'
              'pganesharajah@alphadirect.co.bw',
                'aiyer@alphadirect.co.bw',
                'kphatshwane@alphadirect.co.bw',
                'arjuniyer@alphadirect.co.bw',
                'nbarot@theriskco.com',
                'gchilala@alphadirect.co.zm' */
        /*    );
        }else{
            $email = array('satyajeetbcd@gmail.com','kkatolkar@alphadirect.co.bw');
        }

        if(count($email) > 0 && (count($AdiDuplicatepolicy) > 0 || count($LegalDuplicatepolicy) > 0  )){
            foreach($email as $d){
                if($d){
                            $data = new \stdClass();
                            $data->user_id = null;
                            $data->hook = 'adi_duplicate_policy_report';
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
