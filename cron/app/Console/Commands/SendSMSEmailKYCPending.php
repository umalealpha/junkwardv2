<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\EmailSMSLogs;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\KYC;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\PendingCustomerKYCLogs;
use Carbon\Carbon;
use Illuminate\Console\Command;
use AlphaDirect\Models\CronStatus;

class SendSMSEmailKYCPending extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendsmsemailkycpending:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send sms email kyc pending';

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
        $cron->name = "sendsmsemailkycpending:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $kyc = PendingCustomerKYCLogs::join('customer','customer.id','=','pending_kyc_customers.customer_id')
            ->limit(5)
            ->get(array('pending_kyc_customers.id as pending_id','customer.id as customer_id','customer.firstName','customer.lastName','customer.cellphone','customer.email'));

        foreach($kyc as $k){
            $checkKYCStatus = KYC::where('customer_id',$k->customer_id)->orderBy('id','DESC')->first(array('customer_id','compliance'));
            if($checkKYCStatus->compliance != 1){
                if($k->cellphone != null){
                    $log = EmailSMSLogs:: whereBetween(\Illuminate\Support\Facades\DB::raw('date(next_send_date)') , [Carbon::parse('today')
                        ->format('Y-m-d')  , Carbon::parse('today')
                        ->format('Y-m-d') ])
                        ->where('customer_id',$k->customer_id)
                        ->where('log_type','sms')
                        ->where('content_type','pending_kyc')
                        ->orderBy('id','DESC')
                        ->first();

                    if($log != null){
                        $sms = new SmsMessaging();
                        $res = $sms->SendSMSEmailKYCPending(23, $k->firstName.' '.$k->lastName, $k->cellphone);
                        $data = [
                            'customer_id'=>$k->customer_id,
                            'log_type'=>'sms',
                            'content_type'=>'pending_kyc',
                            'last_sent_date'=>Carbon::now()->format('Y-m-d'),
                        ];

                        $data['next_send_date'] = EmailSMSLogs::getNextEMailSMSSendDate($data);
                        $log = EmailSMSLogs::addLog($data);
                    }
                }

                if($k->email){
                    $data = new \stdClass();
                    $data->user_id = null;
                    $data->hook = 'kyc_pending_status_email';
                    $data->customer_id = $k->customer_id;
                    $data->attachment = null;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                    event(new \AlphaDirect\Events\SendMail($k->email,$emailTemplate->subject,"",$html,null,['hook' => $data->hook]));
                   // $sent = \Illuminate\Support\Facades\Mail::to($k->email)->send(new MailTemplate($data));
                    $data = [
                        'customer_id'=>$k->customer_id,
                        'log_type'=>'email',
                        'content_type'=>'pending_kyc',
                        'last_sent_date'=>Carbon::now()->format('Y-m-d'),
                    ];

                    $data['next_send_date'] = EmailSMSLogs::getNextEMailSMSSendDate($data);
                    $log = EmailSMSLogs::addLog($data);
                }

            }else{
                $delete = PendingCustomerKYCLogs::where('id',$kyc->pending_id)->delete();
            }
        }
         $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
