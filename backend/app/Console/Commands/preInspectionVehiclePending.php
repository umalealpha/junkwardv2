<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
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
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\WhatsAppController;
use Log;
use AlphaDirect\Models\whatsAppModel;

class preInspectionVehiclePending extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'preInspectionVehiclePending:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'preInspection Vehicle Pending';

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
        $cron->name = "preInspectionVehiclePending:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started preInspection Vehicle Pending');

        $array = Vehicle::leftJoin('policies','policies.id','=','vehicle.policy_id')
            ->join('customer','customer.id','=','vehicle.customer_id')
            ->where('policies.product_id','=',3)
            ->where('policies.status','=',1)
            ->where('vehicle.compliance','=',2)
            ->limit(5)
            ->get(array(
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
            $to = \Carbon\Carbon::now()->toDateTimeString();
            $from = \Carbon\Carbon::now()->subDays(30)->toDateTimeString();
            $checkLog=whatsAppModel::where('policyNumber',$arr->policyNumber)
            ->where('template_type','vehicle_preinspection_pending_cron')
            ->whereBetween('created_at',[$from, $to])->get();
            if(count($checkLog) == 0){
                if($arr->cellphone != null){
                  
                    if($arr != null && $arr->firstName != null && $arr->lastName != null && $arr->make != null && $arr->model != null && $arr->policyNumber != null && $arr->cellphone != null){
                        $dataInspectiion =[
                            "type"=>"template",
                            "subType"=>"vehicle_preinspection_pending_cron",
                            "mobileNumber"=>'267'.$arr->cellphone ,
                            "firstName"=> $arr->firstName,
                            "lastName"=>$arr->lastName,
                            "policyNumber"=>$arr->policyNumber,
                            "make"=>$arr->make,
                            "model"=>$arr->model
                        ];                        
                        
                     $WhatsAppController=  new WhatsAppController();
                     $WhatsAppController->sendMessage($dataInspectiion);

                    echo "Vehicle inspection for policyNumber".$arr->policyNumber."\n";

                    }
                }
            }
        }
        Log::info('Cron Finished preInspection Vehicle Pending');


        $cron->end = \Carbon\Carbon::now();
        $cron->save();

    }
}
