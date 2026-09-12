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
use AlphaDirect\PolicyCellPhone;
use AlphaDirect\Stores;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;

class WithoutCellphoneDetailsPolicyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'withoutcellphoneDetailsPolicy:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'without cellphone Details Policy';

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
        $cron->name = "withoutcellphoneDetailsPolicy:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $policyId = [];


        $policies = Policy::where('product_id',5)->where('status',1)->get();
        
        if($policies->count() > 0){
            foreach($policies as $policy){
                $PolicyCellPhone  = PolicyCellphone::where('policy_id',$policy->id)
                          ->where('device_type', '!=', null)
                          ->where('imei', '!=', null)
                          ->where('cell_phone_make', '!=', null)
                          ->where('cell_phone_model', '!=', null)
                          ->where('cell_phone_front', '!=', null)
                          ->where('cell_phone_back', '!=', null)
                          ->where('cell_phone_left', '!=', null)
                          ->where('cell_phone_right', '!=', null)
                          ->where('cell_phone_top', '!=', null)
                          ->where('cell_phone_bottom', '!=', null)
                          ->where('phone_value', '!=', null)
                          ->first();
               if( isset($PolicyCellPhone) && $PolicyCellPhone != null){

               }else{
                $policyId[] = $policy->id;
               }
                
                
                
               
            }
        }
        $policys = Policy::leftJoin('policy_cellphone','policy_cellphone.policy_id','policies.id')->whereIn('policies.id',$policyId)->orderby('policies.id','desc')->get([
            'policies.id','policies.status','policies.policyNumber',
            'policies.storeID','policies.product_id','policies.plan_id',
            'policies.agent_id','policies.serial_code',
            'policies.created_at','policies.sum_assured','policies.customer_id',
            'policy_cellphone.imei',
            'policy_cellphone.cell_phone_front','policy_cellphone.cell_phone_back',
            'policy_cellphone.cell_phone_left','policy_cellphone.cell_phone_right',
            'policy_cellphone.cell_phone_top','policy_cellphone.cell_phone_bottom',
        ]);
       //dd($policys);
        $report = [
            'policies' => $policys,
           
            'title'    => 'Without Cellphone Details Policy Reports'
        ];

        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'policies-'.$date.'/without_cellphone_details_report.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.without_cellphone_details_report', $report)->setPaper('a3', 'landscape');
        Storage::disk('s3')->put($path, $pdf->output(), 'public');
       // dd($path);
        $attachments = array();
        array_push($attachments, $path);

        if(count($policys) > 0){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'without_cellphone_details_report';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
            ////*************Email send new fuction END **************/////
        }
     


       $cron->end = \Carbon\Carbon::now();
       $cron->save(); 

        return 1;
    
    }
}
