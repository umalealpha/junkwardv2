<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\PolicyTerm;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Storage;
use PDF;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use Log;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;

class ExpiredPolicyPaymentReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'expiredpolicyPaymentReport:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'expiredpolicyPaymentReports';

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
        $cron->name = "expiredpolicyPaymentReport:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Expired Policy Payment Recieved Report Cron Running...');

        $policyId = [];
        $polices = [];
        $trxnsToday = PaymentTransaction::whereIn('status',['SUCCESS','Success','success','1'])->whereBetween('created_at' , [Carbon::parse('today')->format('Y-m-d')  , Carbon::parse('today')->format('Y-m-d') . ' 23:59:59' ])->get(['id','policyNumber','status','created_at']);
        if($trxnsToday->count() > 0){
        foreach( $trxnsToday as $trx){
                if($trx->policyNumber != null){
                    $policy = Policy::whereIn('status',[2,3])->where('policyNumber',$trx->policyNumber)->first(['id']);
                    if($policy){
                        $policyId[] = $policy->id;
                    }
                }
        }
       }
      // dd($policyId == []);
        if($policyId != []){
        $polices = Policy::whereIn('id',$policyId)->get(['id','policyNumber','status','premium','product_id','policyActivatedDate','expiry_date','isPaymentCancel']);
        $policyCount  = $polices->count();
        }else{
        $policyCount = 0;
        }
      
       Log::info('Total Number of Expired Policy Payment Recieved  :'.$policyCount); 
       $report = [
            'policies' => $polices,
           
            'title'    => 'Expired/Cancelled Policies Still Receiving Payments '
        ];

        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'policies-'.$date.'/expiredPayment.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.expiredPoliciesPaymentReport', $report)->setPaper('a3', 'landscape');
        Storage::disk('s3')->put($path, $pdf->output(), 'public');
        // dd($path);
        $attachments = array();
        array_push($attachments, $path);
        if(count($polices) > 0){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'expired_policy_payment_recieved';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
            ////*************Email send new fuction END **************/////
        }
      /*  $email = array();
        if(env('APP_STATUS') == 'Production') {
            $email = array(
                'satyajeetbcd@gmail.com',
                'kkatolkar@alphadirect.co.bw', */
              /*  'aprasad@alphadirect.co.bw',
                'sshah@alphadirect.co.bw'
                'pganesharajah@alphadirect.co.bw',
                'aiyer@alphadirect.co.bw',
                'kphatshwane@alphadirect.co.bw',
                'arjuniyer@alphadirect.co.bw',
                'nbarot@theriskco.com',
                'gchilala@alphadirect.co.zm' */
       /*     );
        }else{
            $email = array('satyajeetbcd@gmail.com','kkatolkar@alphadirect.co.bw');
        }

        if(count($email) > 0 && count($polices) > 0 ) {
            foreach($email as $d){
                if($d){
                            $data = new \stdClass();
                            $data->user_id = null;
                            $data->hook = 'expired_policy_payment_recieved';
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
        } */

       // Storage::disk('s3')->delete($path); 


       $cron->end = \Carbon\Carbon::now();
       $cron->save(); 

        return 1;
    }
}
