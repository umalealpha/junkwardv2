<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\CustomerBanking;
use AlphaDirect\PaymentTransactionSonali as PaymentTransaction;
use AlphaDirect\PolicySonali as Policy;
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
use AlphaDirect\LedgerSonali as Ledger;
use AlphaDirect\Models\CronStatus;
use DB;
use Illuminate\Support\Facades\Log;

class deleteDuplicatePayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deleteDuplicatePayment:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $cron->name = "deleteDuplicatePayment:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Policy Id updated into payment_transactions table started');
        ini_set('max_execution_time', 0);
        try{
            $policies = DB::select(
                'SELECT id,
                policy_id, COUNT(policy_id),
                trans_type,  COUNT(trans_type),
                accounting_date,      COUNT(accounting_date)
            FROM
                policy_ledger_sonali
            GROUP BY 
                policy_id , 
                trans_type , 
                accounting_date
            HAVING  COUNT(policy_id) > 1
                AND COUNT(trans_type) > 1
                AND COUNT(accounting_date) > 1;'
            );
            
            foreach ($policies as  $policy) {
                $pID =  $policy->id;
                $deleteEntry = Ledger::where('id',$pID)->delete();
                sleep(1);
            }
            //return($policies);
        }
        catch(Exception $e){
            \Illuminate\Support\Facades\DB::rollBack();
            return $e->getMessage();
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
