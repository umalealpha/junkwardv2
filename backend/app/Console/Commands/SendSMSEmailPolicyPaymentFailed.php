<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\PaymentFailedstatusLogs;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use AlphaDirect\Policy;
use Log;
use AlphaDirect\Models\CronStatus;

class SendSMSEmailPolicyPaymentFailed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendsmsemailpolicypaymentfailed:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Emails and SMS of policy payment failure';

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
        $cron->name = "sendsmsemailpolicypaymentfailed:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started for sending SMS for payment failed');

        $pay_transactions = PaymentTransaction::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('today')
            ->format('Y-m-d')  , Carbon::parse('today')
            ->format('Y-m-d') ])
            ->whereIn('status',['Failed','FAILED','F'])
            ->get(array('policyNumber','created_at','amount','referenceNumber'));

        foreach($pay_transactions as $key=>$transaction){
            $check = PaymentFailedstatusLogs::where('reference_number',$transaction->referenceNumber)->count();
            if($check == 0) {
                $insertData = array(
                    "policyNumber" => $transaction->policyNumber,
                    "amount" => $transaction->amount,
                    "reference_number" => $transaction->referenceNumber,
                    "sms_sent" => 0,
                    "email_sent" => 0,
                );
                $response = PaymentFailedstatusLogs::addFailedPaymentLogs($insertData);
            }

        }




        /*Send payment status message*/
//        foreach($pay_transactions as $key=>$transaction){
//            $policy = Policy::join('customer','customer.id','=','policies.customer_id')
//                ->where('policies.policyNumber',$transaction->policyNumber)
//                ->first(array(
//                    'policies.policyNumber',
//                    'customer.firstName',
//                    'customer.lastName',
//                    'customer.email',
//                    'customer.cellphone'
//                ));
//
//            if($policy->cellphone != null) {
//                $sms = new SmsMessaging();
//                $res = $sms->sendPaymentFailedSMS(21, $policy->firstName . ' ' . $policy->lastName, $policy->policyNumber, $policy->cellphone, $transaction->amount);
//            }
//        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save(); 
    }
}
