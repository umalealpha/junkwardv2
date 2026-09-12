<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Claim;
use AlphaDirect\Customer;
use AlphaDirect\EmailSMSLogs;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Models\OneTimePaymentURL;
use AlphaDirect\Models\PolicyRenewal;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PDF;
use Auth;
use File;
use Log;
use AlphaDirect\Models\CronStatus;
class SendSMSEmailRerating extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendsmsemailrerating:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send sms email rerating';

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
        $cron->name = "sendsmsemailrerating:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $data = PolicyRenewal::where('is_rated',1)->orderBy('id', 'desc')->take(1)->get();

        if(env('APP_STATUS') == 'Production') {
            $templateId = 37;
        }else{
            $templateId = 38;
        }

        foreach($data as $key=>$d) {
            $customerData = Policy::join('customer', 'customer.id', 'policies.customer_id')
                ->where('policies.policyNumber', $d->policyNumber)
                ->first(array(
                    'customer.id as customer_id',
                    'customer.email',
                    'customer.cellphone',
                    'customer.firstName',
                    'customer.middleName',
                    'customer.lastName',
                    'policies.policyNumber',
                ));

            $policyCon = new PolicyController();
            $link = $policyCon->generateSendPaymentURL($customerData->policyNumber, null,'repay');

            if ($customerData->cellphone) {
                $sms = new SmsMessaging();
                $res = $sms->sendPolicyRenewalSMS($templateId,$customerData->firstName.' '.$customerData->middleName.' '.$customerData->lastName,$customerData->policyNumber,$customerData->cellphone,$link);
                $d->sms_sent = 1;
                $d->save();
            }

            $plink = OneTimePaymentURL::where('policyNumber',$customerData->policyNumber)
                ->orderBy('id','desc')
                ->first();

            if ($customerData->email) {
                $data = new \stdClass();
                $data->user_id = $plink->id;
                $data->hook = 'send_renewal';
                $data->customer_id = $d->customer_id;
                $data->attachment = null;
                $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                $markdown = new MailTemplate($data);
                $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                event(new \AlphaDirect\Events\SendMail($customerData->email,$emailTemplate->subject,"",$html,null,['policyNumber' => $customerData->policyNumber,'hook' => $data->hook]));
               // $sent = \Illuminate\Support\Facades\Mail::to($customerData->email)->send(new MailTemplate($data));
                $data = [
                    'customer_id'=>$d->customer_id,
                    'log_type'=>'email',
                    'content_type'=>'send_renewal',
                    'last_sent_date'=>Carbon::now()->format('Y-m-d'),
                ];

                $d->email_sent = 1;
                $d->save();

                $log = EmailSMSLogs::addLog($data);
            }
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();

    }
}
