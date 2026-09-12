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
use AlphaDirect\PaymentTransaction;

class PolicyDeactivePaymentSuccess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policydeactivepaymentsuccess:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Policy Deactive Payment Success';

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
        $cron->name = "policydeactivepaymentsuccess:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $policyId = [];
        $policies = Policy::where('status', 0)->get(['id','policyNumber','status']);
        if($policies->count() > 0){
            foreach($policies as $policy){
             $trxn =  PaymentTransaction::where('policyNumber',$policy->policyNumber)->where(function($query)
                {
                    $query->where('status', 'Success')
                    ->orWhere('status','SUCCESS' );

                })->first();
               if($trxn != null){
                $policyId[] = $policy->id;
               }
          }
        }
        $policys = Policy::whereIn('id',$policyId)->get();
       
        $report = [
            'policies' => $policys,
           
            'title'    => 'Policy Deactive Payment Success Policy Reports'
        ];

        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'policies-'.$date.'/deactive_payment_success_policy_report.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.deactive_payment_success_policy_report', $report);
        Storage::disk('s3')->put($path, $pdf->output(), 'public');
        //dd($path);
        $attachments = array();
        array_push($attachments, $path);

        if(count($policys) > 0){
            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'deactive_payment_success_policy_report';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
            ////*************Email send new fuction END **************/////
        }
        Storage::disk('s3')->delete($path);
       $cron->end = \Carbon\Carbon::now();
       $cron->save(); 

        return 1;
    }
}
