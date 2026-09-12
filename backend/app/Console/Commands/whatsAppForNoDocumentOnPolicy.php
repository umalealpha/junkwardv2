<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Log;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Policy;
use AlphaDirect\Customer;
use AlphaDirect\Http\Controllers\WhatsAppController;
use AlphaDirect\Models\whatsAppModel;


class whatsAppForNoDocumentOnPolicy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsAppForNoDocumentOnPolicy:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'whatsApp For No Document On Policy';

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
        Log::info('Cron Started for processing Whats App document.');

        $cron = new CronStatus();
        $cron->name = "whatsAppForNoDocumentOnPolicy:cron";
        $cron->start = \Carbon\Carbon::now();
         $cron->save();
        $jobs = Policy::join('policy_term', 'policy_term.policy_id', '=', 'policies.id')
        ->join('customer', 'customer.id', '=', 'policies.customer_id')
        ->where('policies.status', '1')
        ->where('policy_term.status','Active')->doesntHave('kyc')
        ->where('policy_term.term_end_date', '<',  \Carbon\Carbon::now()->addDays(2)->toDateTimeString())
        ->select(array(
            'policies.id', 
            'policies.policyNumber', 
            'policies.product_id',
            'policies.customer_id', 
            'customer.firstName', 
            'customer.lastName',
            'customer.cellphone' ))->get()->chunk('50');
            foreach($jobs as $job){
                foreach($job as $val)
                    {
                        
                    if( $val->product_id==3){
                        $dataCreatePolicy =[
                            "type"=>"template",
                            "subType"=>"motor_policy_kyc_cron",
                            "mobileNumber"=>'267'.$val->cellphone ,
                            "firstName"=> $val->firstName,
                            "lastName"=>$val->lastName,
                            "policyNumber"=>$val->policyNumber,
                            "customer_id"=>$val->customer_id
                        ];

                    }else if($val->product_id!=3){
                    $dataCreatePolicy =[
                        "type"=>"template",
                        "subType"=>"instant_policy_kyc_cron",
                        "mobileNumber"=>'267'.$val->cellphone ,
                        "firstName"=> $val->firstName,
                        "lastName"=>$val->lastName,
                        "policyNumber"=>$val->policyNumber,
                        "customer_id"=>$val->customer_id
                        ];
                    }   
                     $WhatsAppController=  new WhatsAppController();
                     $WhatsAppController->sendMessage($dataCreatePolicy);
        
                     echo "message send on ".$val->cellphone."\n";
                    }
            }
     
         
         $jobs = Policy::join('policy_term', 'policy_term.policy_id', '=', 'policies.id')
         ->join('customer', 'customer.id', '=', 'policies.customer_id')
         ->join('customer_kyc', 'customer_kyc.customer_id', '=', 'policies.customer_id')
         ->where('policies.status', '1')->where('policies.product_id', '3')->where('policy_term.status','Active')->where('policy_term.term_end_date', '<',  \Carbon\Carbon::now()->addDays(2)->toDateTimeString())
         ->WhereNull('customer_kyc.proof_residence')->WhereNull('customer_kyc.driving_license')->WhereNull('customer_kyc.proof_income')->chunk(50, function($records){
             foreach($records as $job)
             {
                $to = \Carbon\Carbon::now()->toDateTimeString();
                $from = \Carbon\Carbon::now()->subDays(30)->toDateTimeString();
                $checkLog=whatsAppModel::where('policyNumber',$job->policyNumber)
                ->where('template_type','motor_policy_except_kyc_cron')
                ->whereBetween('created_at',[$from, $to])->get();
                if(count($checkLog) == 0){
                     if(isset( $job->customer)){
                         $dataCreatePolicy =[
                             "type"=>"template",
                             "subType"=>"motor_policy_except_kyc_cron",
                             "mobileNumber"=>'267'.$job->customer->cellphone ,
                             "firstName"=> $job->customer->firstName,
                             "lastName"=>$job->customer->lastName,
                             "policyNumber"=>$job->policyNumber,
                             "customer_id"=>$job->customer_id
                         ];
 
                      $WhatsAppController=  new WhatsAppController();
                      $WhatsAppController->sendMessage($dataCreatePolicy);
         
                      echo "message send on ".$job->customer->cellphone."\n";
                      
                     }
                }
                
             }
          });
         $cron->end = \Carbon\Carbon::now();
         $cron->save();
 
    }
}
