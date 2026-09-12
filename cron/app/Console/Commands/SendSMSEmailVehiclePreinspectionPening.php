<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use AlphaDirect\EmailSMSLogs;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\KYC;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\PendingCustomerKYCLogs;
use AlphaDirect\PendingVehiclePreinspectionLogs;
use AlphaDirect\Vehicle;
use Carbon\Carbon;
use Illuminate\Console\Command;
use AlphaDirect\Models\CronStatus;

class SendSMSEmailVehiclePreinspectionPening extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendsmsemailvehiclepreinspectionprending:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send sms email vehicle preinspection pending';

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
        $cron->name = "sendsmsemailvehiclepreinspectionprending:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $array = PendingVehiclePreinspectionLogs::leftJoin('policies','policies.id','=','pending_preinspections_logs.policy_id')
            ->join('vehicle','vehicle.policy_id','=','pending_preinspections_logs.policy_id')
            ->join('customer','customer.id','=','pending_preinspections_logs.customer_id')
            ->where('policies.product_id','=',3)
            ->limit(5)
            ->get(array(
                'pending_preinspections_logs.id as data_id',
                'policies.id as policy_id',
                'policies.policyNumber',
                'customer.id as customer_id',
                'customer.firstName',
                'customer.lastName',
                'customer.cellphone',
                'customer.email',
                'vehicle.make',
                'vehicle.model',
                'vehicle.model',
                'policies.product_id',
                )
            );

        foreach($array as $arr){
            $status = Vehicle::where('policy_id',$arr->policy_id)
                ->orderBy('id','DESC')
                ->first(array('policy_id','customer_id','compliance'));

            if($status->compliance != 1){
                if($arr->cellphone != null){
                    $log = EmailSMSLogs:: whereBetween(\Illuminate\Support\Facades\DB::raw('date(next_send_date)') , [Carbon::parse('today')
                        ->format('Y-m-d')  , Carbon::parse('today')
                        ->format('Y-m-d') ])
                        ->where('customer_id',$arr->customer_id)
                        ->where('log_type','sms')
                        ->where('content_type','vehicle_preinspection_pending')
                        ->orderBy('id','DESC')
                        ->first();

                    if($arr != null && $arr->firstName != null && $arr->lastName != null && $arr->make != null && $arr->model != null && $arr->policyNumber != null && $arr->cellphone != null){
                        $sms = new SmsMessaging();
                        $res = $sms->SendSMSEmailVehicleInspectionPending(25, ucwords($arr->firstName . ' ' . $arr->lastName),$arr->make,$arr->model,$arr->policyNumber, $arr->cellphone);

                        $data = [
                            'customer_id'=>$arr->customer_id,
                            'log_type'=>'sms',
                            'content_type'=>'vehicle_preinspection_pending',
                            'last_sent_date'=>Carbon::now()->format('Y-m-d'),
                        ];

                        $data['next_send_date'] = EmailSMSLogs::getNextEMailSMSSendDate($data);
                        $log = EmailSMSLogs::addLog($data);
                    }
                }

                if($arr->email){
                    $data = new \stdClass();
                    $data->user_id = $arr->policy_id;
                    $data->hook = 'vehicle_preinspection_pending';
                    $data->customer_id = $arr->customer_id;
                    $data->attachment = null;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                    event(new \AlphaDirect\Events\SendMail($arr->email,$emailTemplate->subject,"",$html,null,['policyNumber' => $arr->policyNumber,'hook' => $data->hook]));
                   // $sent = \Illuminate\Support\Facades\Mail::to($arr->email)->send(new MailTemplate($data));
                    $data = [
                        'customer_id'=>$arr->customer_id,
                        'log_type'=>'email',
                        'content_type'=>'vehicle_preinspection_pending',
                        'last_sent_date'=>Carbon::now()->format('Y-m-d'),
                    ];

                    $data['next_send_date'] = EmailSMSLogs::getNextEMailSMSSendDate($data);
                    $log = EmailSMSLogs::addLog($data);
                }

            }else{
                $delete = PendingVehiclePreinspectionLogs::where('id',$arr->data_id)->delete();
            }
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();



    }
}
