<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Claim;
use AlphaDirect\ClaimReservesCoverage;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Ledger;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\Region;
use AlphaDirect\SubLedger;
use Carbon\Carbon;
use Http\Client\Exception;
use Illuminate\Console\Command;
use AlphaDirect\Mail\LedgerDailyReport;
use AlphaDirect\Mail\SendPO;
use Illuminate\Support\Facades\Mail;
use AlphaDirect\Models\CronStatus;

class PolicyLossRatio extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'PolicyLossRatio:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Policy Loss Ratio';

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
        $cron->name = "PolicyLossRatio:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $today = Carbon::today();
        $now = Carbon::now();
        $invoices = array();

        ini_set('max_execution_time', 0);
        try{
            $totalPremiumPayment = PaymentTransaction::where('amount', '!=', 1)->where('is_refund', 0)->where('status', 'Success')->sum('amount');
            $totalClaims = Claim::whereIn('claim_type', ['Accident', 'Key Loss'])->count();

            $totalPayment = Claim::join('claim_reserves_coverages', 'claim_reserves_coverages.claim_id', 'claims.id')
                            ->whereIn('claims.claim_type', ['Accident', 'Key Loss'])
                            ->sum('reserve_amt');



            dd($totalPremiumPayment.'-'.$totalClaims.'-'.$totalPayment);

            return 'success';
        }catch(Exception $e){
            \Illuminate\Support\Facades\DB::rollBack();
            return $e->getMessage();
        }
         $cron->end = \Carbon\Carbon::now();
         $cron->save();
    }
}
