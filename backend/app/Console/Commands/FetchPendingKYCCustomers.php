<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use AlphaDirect\EmailSMSLogs;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\KYC;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\PendingCustomerKYCLogs;
use AlphaDirect\EmailBroadcasting;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use AlphaDirect\Models\CronStatus;

class FetchPendingKYCCustomers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetchpendingkyccustomers:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch pending kyc customers';

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


        try{
            $cron = new CronStatus();
            $cron->name = "fetchpendingkyccustomers:cron";
            $cron->start = \Carbon\Carbon::now();
            $cron->save();
            $data = KYC::getKYCDataByStatus(0);
            if($data['Status'] == 'Success' && count($data['KYCData']) > 0){
                if(!empty($data['KYCData'])){
                    foreach($data['KYCData'] as $key=>$d){
                        $check = PendingCustomerKYCLogs::where('customer_id',$d->customer_id)->exists();
                        if($check == false && $d->customer_id != null){
                            $insert = [
                                'customer_id'=>$d->customer_id
                            ];
                            $addLog = PendingCustomerKYCLogs::addLog($insert);
                            $k = Customer::where('id',$d->customer_id)->first();

                            if($k != null){
                                if($k->cellphone != null){
                                    $sms = new SmsMessaging();
                                    $res = $sms->SendSMSEmailKYCPending(23, $k->firstName.' '.$k->lastName, $k->cellphone);
                                    $data = [
                                        'customer_id'=>$k->id,
                                        'log_type'=>'sms',
                                        'content_type'=>'pending_kyc',
                                        'last_sent_date'=>Carbon::now()->format('Y-m-d'),
                                    ];

                                    $data['next_send_date'] = EmailSMSLogs::getNextEMailSMSSendDate($data);
                                    $log = EmailSMSLogs::addLog($data);
                                }

                                if($k->email){
                                    $data = new \stdClass();
                                    $data->user_id = null;
                                    $data->hook = 'kyc_pending_status_email';
                                    $data->customer_id = $k->id;
                                    $data->attachment = null;
                                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                                    $markdown = new MailTemplate($data);
                                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                                    event(new \AlphaDirect\Events\SendMail($k->email,$emailTemplate->subject,"",$html,null,['hook' => $data->hook]));
                                   // $sent = \Illuminate\Support\Facades\Mail::to($k->email)->send(new MailTemplate($data));

                                    $data = [
                                        'customer_id'=>$k->id,
                                        'log_type'=>'email',
                                        'content_type'=>'pending_kyc',
                                        'last_sent_date'=>Carbon::now()->format('Y-m-d'),
                                    ];

                                    $data['next_send_date'] = Carbon::now()->addDays(1)->format('Y-m-d');
                                    $log = EmailSMSLogs::addLog($data);

                                }
                            }
                        }
                    }
                }
            }
            $cron->end = \Carbon\Carbon::now();
            $cron->save();

            return true;
        }catch(\Exception $ex){
            return false;
        }

    }
}
