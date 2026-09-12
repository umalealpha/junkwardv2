<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\CellphoneDeviceStatus;
use AlphaDirect\Customer;
use AlphaDirect\EmailSMSLogs;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Policy;
use AlphaDirect\PendingDevicePreinspection;
use Carbon\Carbon;
use Illuminate\Console\Command;
use DB;
use AlphaDirect\Models\CronStatus;

class FetchPendingDevicePreinspection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetchpendingdevicepreinspection:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch pending device preinspection';

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
        $cron->name = "fetchpendingdevicepreinspection:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $devices = CellphoneDeviceStatus::join('policy_cellphone','policy_cellphone.id','=','policy_cellphone_device_status.policy_cellphone_id')
            ->where('policy_cellphone_device_status.status','=',0)
            ->limit(2)
            ->get(array(
                    'policy_cellphone.id',
                    'policy_cellphone.customer_id',
                    'policy_cellphone.policy_id',
                    'policy_cellphone.cell_phone_make',
                    'policy_cellphone.cell_phone_model',
                    'policy_cellphone.device_type',
                    'policy_cellphone_device_status.status')
            );

        if(!empty($devices)){
            foreach($devices as $key=>$device){
                $check = PendingDevicePreinspection::where('device_id',$device->id)->exists();
                if($check == false){
                    $data = [
                        'customer_id'=>$device->customer_id,
                        'device_id'=>$device->id,
                    ];

                    $add = PendingDevicePreinspection::addLog($data);

                    $customer = Policy::join('customer','customer.id','=','policies.customer_id')
                        ->where('policies.id',$device->policy_id)
                        ->first(
                            array(
                                'policies.policyNumber',
                                'customer.firstName',
                                'customer.lastName',
                                'customer.cellphone',
                                'customer.email',
                                'customer.id',
                            )
                        );

                    if($customer->cellphone != null){
                        $sms = new SmsMessaging();
                        $res = $sms->SendSMSEmailDeviceInspectionPending(26, $customer->firstName.' '.$customer->lastName,$device->make,$device->model,$customer->policyNumber,$customer->cellphone);
                        $data = [
                            'customer_id'=>$customer->id,
                            'log_type'=>'sms',
                            'content_type'=>'pending_device_preinspection',
                            'last_sent_date'=>Carbon::now()->format('Y-m-d'),
                        ];

                        $data['next_send_date'] = EmailSMSLogs::getNextEMailSMSSendDate($data);

                        $log = EmailSMSLogs::addLog($data);
                    }

                    if($customer->email){
                        $data = new \stdClass();
                        $data->user_id = $device->id;
                        $data->hook = 'pending_device_preinspection';
                        $data->customer_id = $customer->id;
                        $data->attachment = null;
                        $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                        $markdown = new MailTemplate($data);
                        $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                        event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,null,['hook' => $data->hook]));
                        //$sent = \Illuminate\Support\Facades\Mail::to($customer->email)->send(new MailTemplate($data));

                        $data = [
                            'customer_id'=>$customer->id,
                            'log_type'=>'email',
                            'content_type'=>'pending_device_preinspection',
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
