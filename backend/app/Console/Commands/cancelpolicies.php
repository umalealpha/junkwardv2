<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Services\AlphaDirectNotificationService;
use AlphaDirect\CustomerFeedback;
use AlphaDirect\GFSEmails;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\Payment\VCS\PaymentController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Models\CancelPolicy;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\RealpayCancelRequests;
use AlphaDirect\RealpayFailedTransEmails;
use AlphaDirect\RealpayLogs;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\Transaction;
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
class cancelpolicies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cancelpoliciescron:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This cron is used for cancelling policies';

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
    // to cancel non paying policies, policy numbers will be pulled from database
    public function handle()
    {
        $cron = new CronStatus();
        $cron->name = "cancelpoliciescron:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $policies = DB::select(
            'SELECT c.firstName, c.cellphone, p.policyNumber, p.status,
                    p.id as policy_id, p.policyNumber as policy_number,
                    cp.reason, cp.status as cancellation_status
             FROM cancel_policies cp
             INNER JOIN policies p ON p.policyNumber = cp.policyNumber
             INNER JOIN customer c ON c.id = p.customer_id
             WHERE cp.status = 0'
        );
        if(count($policies) > 0){
           foreach($policies as $key=>$data){
                try{
                    if($data){
                        sleep(1);
                            $trans = PaymentTransaction::where('policyNumber',$data->policyNumber)
                                ->first(['paymentMethod']);
                                #$method = $trans->paymentMethod;
                                if( isset($trans->paymentMethod) && !empty($trans->paymentMethod )){
                                    $check = DB::table('customer_banking')
                                            ->where('policy_id', (int) $data->policy_id)
                                            ->orderBy('id', 'desc')
                                            ->first();
                                        if (isset($check->billing) && $check->billing != null) {
                                            $trans->paymentMethod = $check->billing;
                                        } else {
                                            $trans->paymentMethod = "VCS";
                                        }
                                   }else{
                                    $trans = new PaymentTransaction;
                                    $trans->paymentMethod = "VCS";
                                   }
                            switch($trans->paymentMethod){
                                case 'RealPay':
                                    $realpay = RealpayPaymentRequest::where('policy_id',$data->policy_id)
                                        ->orderBy('id','desc')
                                        ->first();

                                    if($realpay && $realpay->status == 1){
                                        $log = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                                        $log->cancelRealpayContract($data->policy_id);
                                        $addLog = $log->logEvent($data->policy_id, 2);

                                        if ($addLog) {
                                            $request                           = new RealpayCancelRequests();
                                            $request->policy_id                = $data->policy_id;
                                            $request->leftout_premium_contract = null;
                                            $request->contract                 = $realpay->contract;
                                            $request->cancel_status            = 0;
                                            $request->save();
                                        }
                                    }

                                    break;
                                case 'VCS':
                                    $transctionsRow = Transaction::where('policyNumber', $data->policyNumber)->orderBy('id', 'desc')->first();
                                    if(isset($transctionsRow->referenceNumber) && !empty($transctionsRow->referenceNumber)){
                                    $referenceNumber = $transctionsRow->referenceNumber;
                                    $vcs = new PaymentController;
                                    $vcs->suspendTransactionOnVCS($referenceNumber);
                                    }
                                    break;

                                default :
                                    break;
                            }

                        }
                        $policy = Policy::where('id',$data->policy_id)->first();
                        $policy->status = 2;
                        $cancelled = $policy->save();
                        $update = DB::table('cancel_policies')->where('policyNumber',$policy->policyNumber)->update(['status' => 1]);

                        if($cancelled){
                            $pc = new PolicyController();
                            $update = $pc->updatePolicyDates($policy->policyNumber, 2);

                            $feedback = new CustomerFeedback();
                            $feedback->policy_id = $policy->id;
                            $feedback->customer_id = $policy->customer_id;
                            $feedback->product_id = $policy->product_id;

                            if ($data->reason != null) {
                                $feedback->reason = $data->reason;
                            }

                            $feedback->save();

                            if($data->cellphone){
                                // WhatsApp → Email → SMS priority
                                app(AlphaDirectNotificationService::class)->policyCancelled(
                                    $data->firstName,
                                    $policy->policyNumber,
                                    $data->cellphone,
                                    null,
                                    null
                                );
                                $update = DB::table('cancel_policies')->where('policyNumber',$policy->policyNumber)->update(['sms_sent' => 1]);
                            }


                    }
                }catch(\Exception $ex){
                    $update = DB::table('cancel_policies')->where('policyNumber',$data->policyNumber)->update(
                        ['status' => 1,'reason'=>$ex->getMessage().'-'.$ex->getLine()]);
                }
                #dd($policy->policyNumber);
           }
        }
       $cron->end = \Carbon\Carbon::now();
       $cron->save();
    }
}
