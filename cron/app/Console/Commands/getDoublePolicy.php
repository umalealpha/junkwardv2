<?php

namespace AlphaDirect\Console\Commands;
use AlphaDirect\Models\CronStatus;
use Illuminate\Console\Command;
use AlphaDirect\Customer;
use Carbon\Carbon;
use Log;
use DB;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\Http\Controllers\CronController;

class getDoublePolicy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'getDoublePolicy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'getDoublePolicy';

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
        $cron->name = "getDoublePolicy";
        $cron->start = Carbon::now();
        $cron->save();
        Log::info('Daily policy duplicate cron started');

        // $date = \Carbon\Carbon::now()->timestamp;
        // $fileName = 'Created-'.$date.'-duplicate_policy.csv';
        // $path = storage_path('app/public/');
        // $attachpath = $fileName;
        // $file = fopen($path.$fileName, 'w');

        $columns = array('Policy Id', 'Policy Number','Customer Name ','Customer Email' ,'Realpay', 'DPO', 'Ngenius');
        // fputcsv($file, $columns);


        $policies =  DB::select(DB::raw("select policy_id
        from duplicate_policy  
        GROUP  BY policy_id HAVING COUNT(policy_id) > 1"));
        if (count($policies)>0) {
            foreach($policies as $policy)
            {
               
                $policiesData =  DB::select(DB::raw("select id,customer_id,policy_id,policyNumber,realpay,dpo,ngenius
                from duplicate_policy where policy_id = $policy->policy_id"));
                $realpayStatus = $dpoStatus = $ngeniusStatus = 'NO';
                foreach($policiesData as $policyData)
                {
                    $customer_profile = Customer::where('id',$policyData->customer_id)->first();
                    $customeName = $customer_profile->firstName." ".$customer_profile->lastName;
                    $customeEmail = $customer_profile->email;
                    $policy->policy_id     = $policyData->policy_id;
                    $policy->policyNumber  = $policyData->policyNumber;
                    $policy->customerName  = $customeName;
                    $policy->customerEmail = $customeEmail;

                    if($policyData->policy_id == $policy->policy_id)
                    {
                        if($policyData->realpay == 'YES')
                        {
                            $realpayStatus  = $policyData->realpay;
                        }
                        else if($policyData->dpo == 'YES')
                        {
                            $dpoStatus  = $policyData->dpo;
                        }
                        else if($policyData->ngenius == 'YES')
                        {
                            $ngeniusStatus  = $policyData->ngenius;                    
                        }                        
                    }  
                    $results[] = [
                    'policy_id'=>$policy->policy_id, 
                    'policyNumber'=>$policy->policyNumber, 
                    'customerName'=>$policy->customerName,
                    'customerEmail'=>$policy->customerEmail,
                    'realpayStatus'=>$realpayStatus, 
                    'dpoStatus'=>$dpoStatus, 
                    'ngeniusStatus'=>$ngeniusStatus] ;                
                }
                
                //fputcsv($file, $data);
            }
            $pages = "Policy Id,Policy Number,Customer Name,Customer Email,Realpay,DPO,Ngenius\n";
            foreach ($results as $where) {
                $pages .="{$where['policy_id']},{$where['policyNumber']},{$where['customerName']},{$where['customerEmail']},{$where['realpayStatus']},{$where['dpoStatus']},{$where['ngeniusStatus']}\n"; 
            }  
            dd($pages);
            $date = \Carbon\Carbon::now()->timestamp;
            $path = 'policies-'.$date.'_'.$startDate.'_'.$endDate.'_transection_report.csv';
            Storage::disk('s3')->put($path, $pages, 'public');
            
           
            $attachments = array();
            array_push($attachments, $path);
      
                ////*************Email send new fuction **************/////
                $cronSendMail = new CronController();
                $hook = 'duplicate_policy';
                $cronSendMail->AllCronMail($attachments,$hook,$cron);
                ////*************Email send new fuction END **************///// 
        }
        $cron->end = Carbon::now();
        $cron->save();

    }
}
