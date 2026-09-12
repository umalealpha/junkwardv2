<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\PolicyTerm;
use AlphaDirect\Policy;
use AlphaDirect\Customer;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Storage;
use PDF;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use Log;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Vehicle;
use Illuminate\Support\Facades\DB;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;

class VehicleDuplicatePolicyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vehicleduplicatepolicy:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Vehicle Duplicate Policy';

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
        $cron->name = "vehicleduplicatepolicy:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $policyId = [];
        $vehicles = Vehicle::where('vehiclePlate','!=', null)->select('vehiclePlate', DB::raw('count(*) as total'))
        ->groupBy('vehiclePlate')->orderBy('vehiclePlate','desc')
        ->get();
        if($vehicles->count() > 0){
            foreach($vehicles as $vehicle){
              if($vehicle->total > 1){
                $vehiclesmulti = Vehicle::leftjoin('policies','policies.id','vehicle.policy_id')->where('policies.status',1)->where('vehicle.vehiclePlate',$vehicle->vehiclePlate)->get(['vehicle.policy_id']);
                if($vehiclesmulti->count() >1){
                    foreach($vehiclesmulti as  $vehicleSingle){
                        $policyId[] = $vehicleSingle->policy_id;
                    }
                }
              }

            }
        }

        $TpDuplicatepolicy = Policy::leftjoin('vehicle','vehicle.policy_id','policies.id')->whereIn('policies.id',$policyId)->where('policies.status',1)->where('policies.product_id',2)->where('vehicle.vehiclePlate','!=',null)->orderBy('vehicle.vehiclePlate','desc')->get();
        $MotorCompDuplicatepolicy = Policy::leftjoin('vehicle','vehicle.policy_id','policies.id')->whereIn('policies.id',$policyId)->where('policies.status',1)->where('policies.product_id',3)->where('vehicle.vehiclePlate','!=',null)->orderBy('vehicle.vehiclePlate','desc')->get();

        $report = [
            'policies' => $TpDuplicatepolicy,
            'policiesMotorComp' => $MotorCompDuplicatepolicy,
            'title'    => 'Vehicle(Motor 3rd Party & Motor Comprehensive) Duplicate Policy Reports'
        ];

        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'policies-'.$date.'/vehicle_duplicate_policy_report.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.vehicle_duplicate_policy_report', $report);
        Storage::disk('s3')->put($path, $pdf->output(), 'public');
        //dd($path);
        $attachments = array();
        array_push($attachments, $path);

        if(count($TpDuplicatepolicy) > 0){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'vehicle_duplicate_policy_report';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
           
            ////*************Email send new fuction END **************/////
         }
      /* $email = array();
        if(env('APP_STATUS') == 'Production') {
            $email = array(
                'satyajeetbcd@gmail.com',
                'kkatolkar@alphadirect.co.bw', */
               /*   'aprasad@alphadirect.co.bw',
                'sshah@alphadirect.co.bw'
              'pganesharajah@alphadirect.co.bw',
                'aiyer@alphadirect.co.bw',
                'kphatshwane@alphadirect.co.bw',
                'arjuniyer@alphadirect.co.bw',
                'nbarot@theriskco.com',
                'gchilala@alphadirect.co.zm' */
      /*      );
        }else{
            $email = array('satyajeetbcd@gmail.com','kkatolkar@alphadirect.co.bw');
        }

        if(count($email) > 0 && count($TpDuplicatepolicy) > 0 ){
            foreach($email as $d){
                if($d){
                            $data = new \stdClass();
                            $data->user_id = null;
                            $data->hook = 'vehicle_duplicate_policy_report';
                            $data->customer_id = null;
                            $data->attachment = $attachments;
                            $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                            $markdown = new MailTemplate($data);
                            $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                            event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data->hook]));
                }
            }
             $cron->mail_send = 1;
            $cron->save();
        } */

       Storage::disk('s3')->delete($path); 


       $cron->end = \Carbon\Carbon::now();
       $cron->save(); 

        return 1;
    }
}
