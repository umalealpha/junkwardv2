<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\CellphoneDeviceStatus;
use AlphaDirect\EmailSMSLogs;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\PendingDevicePreinspection;
use AlphaDirect\PendingVehiclePreinspectionLogs;
use AlphaDirect\PolicyCellPhone;
use AlphaDirect\Vehicle;
use Carbon\Carbon;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\WhatsAppController;
use AlphaDirect\Models\whatsAppModel;


class preInspectionDevicePending extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'preInspectionDevicePending:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'preInspection Device Pending';

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
        $cron->name = "preInspectionDevicePending";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $array = PolicyCellPhone::leftJoin('policies','policies.id','=','policy_cellphone.policy_id')
            ->leftJoin('customer','customer.id','=','policy_cellphone.customer_id')
            ->get(array(
                    'customer.id as customer_id',
                    'customer.firstName',
                    'customer.lastName',
                    'customer.cellphone',
                    'customer.email',
                    'policy_cellphone.policy_id',
                )
            );

        foreach($array as $arr){
            $to = \Carbon\Carbon::now()->toDateTimeString();
            $from = \Carbon\Carbon::now()->subDays(30)->toDateTimeString();
            $checkLog=whatsAppModel::where('policyNumber',$arr->policyNumber)
            ->where('template_type','device_preinspection_pending_cron')
            ->whereBetween('created_at',[$from, $to])->get();
            if(count($checkLog) == 0){
        
                if($arr->cellphone != null){
                    if($arr != null && $arr->firstName != null && $arr->lastName != null && $arr->make != null && $arr->model != null && $arr->policyNumber != null && $arr->cellphone != null){

                        $dataCreatePolicy =[
                            "type"=>"template",
                            "subType"=>"device_preinspection_pending_cron",
                            "mobileNumber"=>'267'.$arr->cellphone ,
                            "firstName"=> $arr->firstName,
                            "lastName"=>$arr->lastName,
                            "policyNumber"=>$arr->policyNumber,
                            "make"=>$arr->make,
                            "model"=>$arr->model
                        ];                        
                        
                     $WhatsAppController=  new WhatsAppController();
                     $WhatsAppController->sendMessage($dataCreatePolicy);

                    echo "Device inspection for policyNumber".$arr->policyNumber."\n";

                    }
                }

                }

        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
