<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\EmailSMSLogs;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\PendingActivationPolicies;
use Carbon\Carbon;
use Illuminate\Console\Command;
use AlphaDirect\Policy;
use Log;
use AlphaDirect\Models\CronStatus;

class SendSMSEmailPolicyPendingActivation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendsmsemailpolicypendingactivation:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends sms and email to customers regrading policy pending status';

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
        $cron->name = "sendsmsemailpolicypendingactivation:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $array = PendingActivationPolicies::leftJoin('policies','policies.id','=','pending_policy_activation.policy_id')
            ->leftJoin('customer','customer.id','=','pending_policy_activation.customer_id')
            ->limit(5)
            ->get(array(
                'pending_policy_activation.id as pending_id',
                'policies.id as policy_id',
                'policies.policyNumber',
                'policies.customer_id as customer_id',
                'policies.status as policy_status',
                'customer.cellphone',
                'customer.email',
                'customer.firstName',
                'customer.lastName',
            ));

        foreach($array as $key=>$arr){
            if($arr->status == 1){
                $delete = PendingActivationPolicies::where('id',$arr->pending_id)->delete();
            }else{
                if($arr->cellphone != null) {

                    $log = EmailSMSLogs:: whereBetween(\Illuminate\Support\Facades\DB::raw('date(next_send_date)') , [Carbon::parse('today')
                        ->format('Y-m-d')  , Carbon::parse('today')
                        ->format('Y-m-d') ])
                        ->where('customer_id',$arr->customer_id)
                        ->where('log_type','sms')
                        ->where('content_type','policy_pending_activation')
                        ->orderBy('id','DESC')
                        ->first();

                    if($log != null){
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
                    }

                    if($arr->email) {
                        $data = new \stdClass();
                        $data->user_id = $arr->policy_id;
                        $data->hook = 'policy_pending_activation';
                        $data->customer_id = $arr->customer_id;
                        $data->attachment = null;
                        $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                        $markdown = new MailTemplate($data);
                        $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                        event(new \AlphaDirect\Events\SendMail($arr->email,$emailTemplate->subject,"",$html,null,['policyNumber' => $arr->policyNumber,'hook' => $data->hook]));

                      //  $sent = \Illuminate\Support\Facades\Mail::to($arr->email)->send(new MailTemplate($data));

                        $data = [
                            'customer_id' => $arr->customer_id,
                            'log_type' => 'email',
                            'content_type' => 'policy_pending_activation',
                            'last_sent_date' => Carbon::now()->format('Y-m-d'),
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

