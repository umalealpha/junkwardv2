<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\sentPolicyDocuments;
use AlphaDirect\Http\Controllers\Admin\DocumentController;
use AlphaDirect\Http\Controllers\Admin\PolicyController;

class sendPolicyDocumentforPayamentSuccessAndPolicyActive extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendpolicydocumentforpayamentsuccess:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'sendPolicyDocumentforPayamentSuccess';

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
        $cron->name = "sendpolicydocumentforpayamentsuccess:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $policyController = new PolicyController();
        $paymentT = PaymentTransaction::whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])->whereIn('status',['1','success','SUCCESS','Success'])->get();
        if(count($paymentT) > 0){
            foreach($paymentT as $payment){
                $policy = Policy::where('policyNumber',$payment->policyNumber)
                ->where('status', 1)->whereNotIn('product_id',[3, 7, 8])->first();
                if($policy){
                  $totaltrxn =  PaymentTransaction::whereIn('status',['1','success','SUCCESS','Success'])->where('policyNumber',$policy->policyNumber)->get();
                    if(count($totaltrxn) == 1){
                      

                                  $sent = $policyController->sendPolicyDocument($policy->id,"System");
                                  if($sent === true){
                                      $sentDocs = new sentPolicyDocuments();
                                      $sentDocs->policyNumber = $policy->id;
                                      $sentDocs->doc = "Policy Document";
                                      $sentDocs->sentBy = "System";
                                      $sentDocs->save();
                                  }
                           
                    }
                }

            }

        }



        
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
