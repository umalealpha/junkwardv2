<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use AlphaDirect\Http\Controllers\Admin\DocumentController;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Policy;
use AlphaDirect\EmailSMSLogs;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\PolicyCoverCancelNote;
use AlphaDirect\Product;
use AlphaDirect\sentPolicyDocumentLogs;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use DB;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\sentPolicyDocuments;

class SendUpdatedPolicyDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendupdatedpolicydocument:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send updated policy document';

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
        $cron->name = "sendupdatedpolicydocument:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $policies = Policy::where('status', 1)
        ->whereNotIn('product_id',[3, 7, 8])
        ->whereBetween('created_at' , [ "2025-06-01 00:00:00"  , "2025-06-20 23:59:59"])
        ->get(array('id','policyNumber', 'product_id'));

       
        $policyController = new PolicyController();
        if (count($policies) > 0) {

            foreach ($policies as $policy) {
                try {
                    $d = new DocumentController();
                    
                    if (!sentPolicyDocuments::where('policyNumber', $policy->policyNumber)->where('doc','Policy Document')->exists()) {
                        $isGenerated = $d->generatePolicyDocument($policy->id);
                    }
                        $sent = $policyController->sendPolicyDocument($policy->id, "System");
                   
                } catch (\Exception $ex) {
                   
                }
            }
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
