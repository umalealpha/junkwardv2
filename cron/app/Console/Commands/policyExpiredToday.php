<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\DpoPaymentController;
use AlphaDirect\Http\Controllers\Payment\VCS\PaymentController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Models\PolicyRenewal;
use AlphaDirect\Models\User;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\PolicyTerm;
use AlphaDirect\RealpayCancelRequests;
use AlphaDirect\RealpayFailedTransEmails;
use AlphaDirect\RealpayLogs;
use AlphaDirect\Transaction;
use Illuminate\Support\Facades\Storage;
use PDF;
use Auth;
use File;
use Log;
use AlphaDirect\Models\CronStatus;

class policyExpiredToday extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policyExpiredToday:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expiring policy';

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
        $cron->name = "policyExpiredToday:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started for policy expiring');
        $today = Carbon::now()->format('Y-m-d');
        $policies = Policy::where('expiry_date','<=',$today)->where('status',1)->where('product_id',3)->get();

        if (isset($policies)) {
            foreach ($policies as $key => $policy) {
                $termsNotExpired = 0;
                $this->info("Policy Number: ". $policy->policyNumber);

                $policyTerm = PolicyTerm::where('policy_id',$policy->id)->where('term_end_date','>',$today)->get();
                if (isset($policyTerm)) {
                    $termsNotExpired = 1;
                }

                if ($termsNotExpired == 0) {
                    $policyController = new PolicyController();
                    $cancelPayment = $policyController->CancelPaymentsForPolicy($policy);

                    if ($cancelPayment->getData()->status == true) {
                        $policy->status = 3;
                        $policy->save();
                    }

                    activity('Policy')
                    ->performedOn($policy)
                    ->log('Policy expired');

                    event(new \AlphaDirect\Events\policyLifecycle($policy->id , "Expired"));
                }
                sleep(1);
            }
        }
          $cron->end = \Carbon\Carbon::now();
          $cron->save();
    }
}
