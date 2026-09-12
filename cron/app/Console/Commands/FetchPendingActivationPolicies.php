<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\EmailSMSLogs;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\PendingActivationPolicies;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Policy;
use AlphaDirect\PolicyPendingActivationSMSEmailLogs;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Log;
use DB;
use AlphaDirect\Models\CronStatus;

class FetchPendingActivationPolicies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetchpendingactivationpolicies:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetching entries that are in pending activation status';

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
    public function handle()
    {
        $cron = new CronStatus();
        $cron->name = "fetchpendingactivationpolicies:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started for fetching entries that are in pending activation status');
        $array = Policy::leftJoin('customer','customer.id','=','policies.customer_id')
            ->where('status','!=',1)->limit(5)
            ->orderBy('policies.id','DESC')
            ->get(array(
                'policies.id as policy_id',
                'policies.policyNumber',
                'policies.customer_id',
                'customer.cellphone',
                'customer.email',
                )
            );

        if(!empty($array)){
            foreach ($array as $key=>$arr){
                $check = PendingActivationPolicies::where('policy_id',$arr->policy_id)->count();
                if($check == 0) {
                    $data = array(
                        'policy_id'=>$arr->policy_id,
                        'customer_id'=>$arr->customer_id,
                    );
                    $add = PendingActivationPolicies::addLog($data);

                    $sms = new SmsMessaging();
                    $res = $sms->sendPolicyPendingSMS(22, ucwords($arr->firstName . ' ' . $arr->lastName), $arr->policyNumber, $arr->cellphone);

                    $data = [
                        'customer_id'=>$arr->customer_id,
                        'log_type'=>'sms',
                        'content_type'=>'policy_pending_activation',
                        'last_sent_date'=>Carbon::now()->format('Y-m-d'),
                    ];

                    $data['next_send_date'] = EmailSMSLogs::getNextEMailSMSSendDate($data);
                    $log = EmailSMSLogs::addLog($data);

                    if($arr->email){
                        $data = new \stdClass();
                        $data->user_id = $arr->policy_id;
                        $data->hook = 'policy_pending_activation';
                        $data->customer_id = $arr->customer_id;
                        $data->attachment = null;
                        $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                        $markdown = new MailTemplate($data);
                        $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                        event(new \AlphaDirect\Events\SendMail($arr->email,$emailTemplate->subject,"",$html,null,['hook' => $data->hook]));
                       // $sent = \Illuminate\Support\Facades\Mail::to($arr->email)->send(new MailTemplate($data));

                        $data = [
                            'customer_id'=>$arr->customer_id,
                            'log_type'=>'email',
                            'content_type'=>'policy_pending_activation',
                            'last_sent_date'=>Carbon::now()->format('Y-m-d'),
                        ];

                        $data['next_send_date'] = Carbon::now()->addDays(1)->format('Y-m-d');
                        $log = EmailSMSLogs::addLog($data);

                    }

                }
            }
        }
         $cron->end = \Carbon\Carbon::now();
         $cron->save();
    }
}
