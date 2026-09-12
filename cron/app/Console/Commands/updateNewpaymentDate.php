<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Log;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Vehicle;
use Illuminate\Support\Facades\DB;
use AlphaDirect\PolicyCellPhone;
use AlphaDirect\Stores;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;
use AlphaDirect\PaymentTransaction;

class updateNewpaymentDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'updatenewpaymentdate:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update new payment date';

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
        $cron->name = "updatenewpaymentdate:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

         $trxns =  PaymentTransaction::where('new_payment_date',null)->where('paymentDate','!=',null)->orderBy('id','desc')->get(['id','paymentDate','new_payment_date']);
         if(count($trxns)> 0){
            Log::info('Cron Started for updatenewpaymentdate:cron'.count($trxns));
            foreach($trxns as $trxn){
                $trxn->new_payment_date = \Carbon\Carbon::parse($trxn->paymentDate)->format('Y-m-d');
                $trxn->save();
            }

         }
         Log::info('Cron end for updatenewpaymentdate:cron ');

        $cron->end = \Carbon\Carbon::now();
        $cron->save();

    }
}
