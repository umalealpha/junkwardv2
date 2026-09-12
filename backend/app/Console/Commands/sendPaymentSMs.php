<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use AlphaDirect\Policy;
use AlphaDirect\User;
use AlphaDirect\VcsNewTransaction;
use Http\Client\Exception;
use Illuminate\Console\Command;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use AlphaDirect\Models\CronStatus;

class sendPaymentSMs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendpaymentsms:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send sms for Debit status';

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
        $cron->name = "sendpaymentsms:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
       try{
           $data = VcsNewTransaction::whereDate('updated_at','>=','2020-11-20')->where('is_smsSent',0)->get();
           if($data != null){
               foreach($data as $d){
                    $policy = Policy::where('policyNumber',$d->policyNumber)->first(array('customer_id'));
                    if($policy && $policy->customer_id != null){
                        $customer = Customer::where('id',$policy->customer_id)->first(array('firstName','lastName','cellphone'));
                        if($customer && $customer->cellphone){

                            if($d->status == "Success")
                                $msg = "Dear ". $customer->firstName.' '.$customer->lastName .", this  message serves as notice that we have received your payment of ". $d->amount ." BWP for policy number ". $d->policyNumber .".";
                            else
                                $msg = "Dear ". $customer->firstName.' '.$customer->lastName .", this  message serves as notice that we have not received your payment of ". $d->amount ." BWP for policy number ". $d->policyNumber .".";

                            // $sms_status = InfobipSms::send('+267' . $customer->cellphone, $msg);
                            $sms_status = event(new \AlphaDirect\Events\SendSms('+267' . $customer->cellphone, $msg));
                            $d->is_smsSent = 1;
                            $d->save();

//                            activity('Policy Status')
//                                ->performedOn($policy)
//                                ->causedBy(User::where('id', 1)->first())
//                                ->log($msg);
                        }
                    }
               }
           }
       }
       catch(Exception $e){

       }
       $cron->end = \Carbon\Carbon::now();
        $cron->save(); 
    }
}
