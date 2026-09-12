<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\CustomerBanking;
use AlphaDirect\Ledger;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Models\PolicyRenewal;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\PolicyTerm;
use AlphaDirect\Region;
use AlphaDirect\SubLedger;
use AlphaDirect\BeforeUpdatePolicy;
use AlphaDirect\Models\LedgerArchive;
use AlphaDirect\Models\SubledgerArchive;
use AlphaDirect\PolicyActivateCancelledDate;
use Carbon\Carbon;
use Http\Client\Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RedBeanPHP\Util\Transaction;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Models\CronStatus;

class UpdatePolicyIdTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'UpdatePolicyIdTransactions:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Policy Id Transactions';

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
        $cron->name = "UpdatePolicyIdTransactions:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Policy Id updated into payment_transactions table started');
        ini_set('max_execution_time', 0);
        try{
            $policies = DB::statement(
                'UPDATE graphite_live.payment_transactions pt
                INNER JOIN policies p ON pt.policyNumber = p.policyNumber
                SET pt.policy_id = p.id
                WHERE pt.policy_id IS NULL;'
            );
            return($policies);
        }
        catch(Exception $e){
            \Illuminate\Support\Facades\DB::rollBack();
            return $e->getMessage();
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
