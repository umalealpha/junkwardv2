<?php

namespace AlphaDirect\Console\Commands;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Customer;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\Models\PolicyRenewal;
use Illuminate\Console\Command;
use Log;
use AlphaDirect\Models\CronStatus;


class SendBeneficiaryUpdateSMS extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendbeneficiaryupdatesms:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send SMS for beneficiary update';

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
        $cron->name = "sendbeneficiaryupdatesms:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

        \Log::info('Cron started : Send SMS for beneficiary update');
        $data=Policy::join('customer','customer.id' ,'=', 'policies.customer_id')
            ->where('policies.has_member',1)
            ->where('policies.status',1)
            ->get(array('policies.policyNumber','policies.policyActivatedDate','customer.firstName','customer.lastName','customer.cellphone','customer.email'));
        //dd($data);
        if(env('APP_STATUS') == 'Production') {
            $templetId =38;
        }else{
            $templetId =39;
        }

        if(count($data)) {
            foreach ($data as $key => $d) {
                if($d->policyActivatedDate){
                    $date = \Carbon::parse($d->policyActivatedDate)->addYear(1)->format('Y-m-d');
                }else{
                    $pay = PaymentTransaction::where('policyNumber',$d->policyNumber)->first(array('paymentDate'));
                    if($pay != null){
                        $date = \Carbon::parse($d->policyActivatedDate)->addYear(1)->format('Y-m-d');
                    }else{
                        $date = null;
                    }
                }

                if($date != null){
                    $current_date=\Carbon::now()->format('Y-m-d');
                    if($current_date == $date)
                    {
                           if($d->cellphone != null)
                           {
                                $sms = new SmsMessaging();
                                $res=$sms->SendBeneficiaryUpdateSMS($templetId, ucwords($d->firstName . ' ' . $d->lastName), $d->policyNumber, $d->cellphone);
                           }

                    }else{
                        //echo 'Not';
                    }
                }
            }
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();

        //return Command::SUCCESS;
    }
}
