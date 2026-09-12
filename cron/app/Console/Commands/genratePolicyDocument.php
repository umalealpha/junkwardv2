<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\sentPolicyDocuments;
use AlphaDirect\Http\Controllers\Admin\DocumentController;
use AlphaDirect\Transaction;
use Illuminate\Console\Command;
use AlphaDirect\Policy;
use AlphaDirect\User;
use AlphaDirect\Accounts;
use Illuminate\Support\Facades\DB;
use Log;
use AlphaDirect\Models\CronStatus;
class genratePolicyDocument extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generatepolicydocument:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This cron is used to generate and send policy document';

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
        $cron->name = "generatepolicydocument:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron started to generate policy documents');

        $policy =  DB::select(DB::raw("select policies.id,policies.policyNumber,policyDocument,payment_transactions.status,policies.status from policies
        inner join payment_transactions on
        policies.policyNumber = payment_transactions.policyNumber
        where policies.product_id = 3 AND payment_transactions.status = 'SUCCESS'
        AND policies.status != 2 AND (policies.policyDocument is NULL || policies.policyDocument = '') order by id desc limit 0,1"));

       /*  $policy =  DB::select(DB::raw("select policies.id,policies.policyNumber,policyDocument,transactions.status,policies.status from policies
        inner join transactions on
        policies.policyNumber = transactions.policyNumber
        where policies.product_id = 3 AND transactions.status = 'SUCCESS' AND policies.status != 2 order by id desc limit 0,1")); */
        $controller = new DocumentController();
       if (count($policy)>0) {
           $isGenerated = $controller->generatePolicyDocument($policy['0']->id);

                           if($isGenerated != null){

                                  $sent = $controller->sendPolicyDocument($policy['0']->id,"System");
                                  if($sent = true){
                                      $sentDocs = new sentPolicyDocuments();
                                      $sentDocs->policyNumber = $policy['0']->id;
                                      $sentDocs->doc = "Policy Document";
                                      $sentDocs->sentBy = "System";
                                      $sentDocs->save();
                                  }
                           }
       }
       $cron->end = \Carbon\Carbon::now();
       $cron->save();
    }
}
