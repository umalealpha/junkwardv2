<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\PolicyActivateCancelledDate;
use Carbon\Carbon;
use Illuminate\Console\Command;
use AlphaDirect\Models\CronStatus;

class SendSMSEmailPolicyCancelled extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendsmsemailpolicycancelled:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send sms email policy cancelled';

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
        $cron->name = "sendsmsemailpolicycancelled:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $data = PolicyActivateCancelledDate::join('policies','policies.policyNumber','=','policyactivatecancelleddates.policyNumber')
            ->join('customer','customer.id','=','policies.customer_id')
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('date(policyactivatecancelleddates.cancelled_date)') , [Carbon::parse('today')
                ->format('Y-m-d')  , Carbon::parse('today')
                ->format('Y-m-d') ])
            ->get(array('customer.id','customer.email','customer.cellphone','policies.policyNumber','policies.customer_id'));

        foreach($data as $key=>$policy){
            if($policy->cellphone != null) {
                $sms = new SmsMessaging();
                $res = $sms->SendSMSEmailPolicyCancelled(24, ucwords($policy->firstName . ' ' . $policy->lastName), $policy->policyNumber, $policy->cellphone);
            }
        }

        $cron->end = \Carbon\Carbon::now();
        $cron->save();

    }
}
