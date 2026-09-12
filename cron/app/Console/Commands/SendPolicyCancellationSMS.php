<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\CustomerFeedback;
use AlphaDirect\GFSEmails;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Models\CancelPolicy;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\RealpayCancelRequests;
use AlphaDirect\RealpayFailedTransEmails;
use AlphaDirect\RealpayLogs;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\VATMemoLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PDF;
use Auth;
use File;
use Log;
use DB;
use AlphaDirect\Models\CronStatus;
class SendPolicyCancellationSMS extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendpolicycancellationsms:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send policy cancellation sms';

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
        $cron->name = "sendpolicycancellationsms:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $policies = DB::select(DB::raw(
            'SELECT c.firstName,c.cellphone,p.policyNumber,p.status,cp.reason,cp.status as cancellation_status FROM cancel_policies cp
             INNER JOIN policies p ON p.policyNumber = cp.policyNumber
             INNER JOIN customer c ON c.id = p.customer_id
             where cp.status = 0 and cp.sms_sent = 0;'
        ));

        if(count($policies) > 0){
            foreach($policies as $key=>$data){

                $policy = Policy::where('policyNumber',$data->policyNumber)
                    ->first(['id','policyNumber','product_id','status','customer_id']);

                if(env('APP_STATUS') == 'Production') {
                    $templateId = 39;
                }else{
                    $templateId = 40;
                }

                if($data->cellphone != null){
                    $sms = new SmsMessaging();
                    $sms->SendSMSForCancellation($templateId, $policy->policyNumber, $data->firstName, $data->cellphone);

                    $update = DB::table('cancel_policies')->where('policyNumber',$policy->policyNumber)->update(['sms_sent' => 1]);
                }
           }
        }
         $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
