<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use AlphaDirect\EmailSMSLogs;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\PendingVehiclePreinspectionLogs;
use AlphaDirect\Vehicle;
use Carbon\Carbon;
use Illuminate\Console\Command;
use AlphaDirect\Models\CronStatus;

class FetchPendingPreinspection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetchpendingpreinspection:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch pending preinspection';

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
        $cron->name = "fetchpendingpreinspection:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
       $data = Vehicle::getDataWithComplianceStatus(0);

        if(isset($data['VehicleData'])){
            if($data['Status'] =='Success' && count($data['VehicleData']) > 0){
               if(!empty($data['VehicleData'])){
                   foreach($data['VehicleData'] as $key=>$d){
                       $check = PendingVehiclePreinspectionLogs::where('vehiclePlate',$d->vehiclePlate)->exists();
                       if($check == false){
                           $insertData = [
                               'customer_id'=>$d->customer_id,
                               'policy_id'=>$d->policy_id,
                               'vehiclePlate'=>$d->vehiclePlate,
                           ];
                           $add = PendingVehiclePreinspectionLogs::addLog($insertData);

                           $customer = Customer::leftJoin('policies','policies.customer_id','=','customer.id')
                               ->where('customer.id',$d->customer_id)
                               ->first(array(
                                   'customer.id',
                                   'customer.firstName',
                                   'customer.lastName',
                                   'customer.cellphone',
                                   'customer.email',
                                   'policies.policyNumber',
                                   'policies.id as policy_id'
                               ));

                           if($customer != null && $customer->firstName != null && $customer->lastName != null && $d->make != null && $d->model != null && $customer->policyNumber != null && $customer->cellphone != null){
                               $sms = new SmsMessaging();
                               $res = $sms->SendSMSEmailVehicleInspectionPending(25, ucwords($customer->firstName . ' ' . $customer->lastName),$d->make,$d->model,$customer->policyNumber, $customer->cellphone);

                               $data = [
                                   'customer_id'=>$customer->id,
                                   'log_type'=>'sms',
                                   'content_type'=>'vehicle_preinspection_pending',
                                   'last_sent_date'=>Carbon::now()->format('Y-m-d'),
                               ];

                               $data['next_send_date'] = EmailSMSLogs::getNextEMailSMSSendDate($data);
                               $log = EmailSMSLogs::addLog($data);
                           }

                           if($customer->email){
                               $data = new \stdClass();
                               $data->user_id = $customer->policy_id;
                               $data->hook = 'vehicle_preinspection_pending';
                               $data->customer_id = $customer->id;
                               $data->attachment = null;
                               $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                               $markdown = new MailTemplate($data);
                               $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                               event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,null,['hook' => $data->hook]));
                              // $sent = \Illuminate\Support\Facades\Mail::to($customer->email)->send(new MailTemplate($data));

                               $data = [
                                   'customer_id'=>$customer->id,
                                   'log_type'=>'email',
                                   'content_type'=>'vehicle_preinspection_pending',
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
    }
}
