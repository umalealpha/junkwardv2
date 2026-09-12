<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\CustomerBanking;
use AlphaDirect\Ledger;
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
use AlphaDirect\Models\CronStatus;
use DB;

class deleteDuplicateTransactionsFromTrans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deleteDuplicateTransactionsFromTrans:cron';

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
        $cron->name  = "deleteDuplicateTransactionsFromTrans:cron";
        $cron->start = \Carbon\Carbon::now();
        $today = Carbon::today();
        //$now = Carbon::now()->toDateTimeString();
        $dt  = Carbon::now();
        $now = $dt->toDateString();
        /*$today = '2023-11-20';//Carbon::today();
        $now   = '2024-01-20';*/
        $toDayDate = date('d');
        $invoices = array();

       
        ini_set('max_execution_time', 0);
        try{
            $policies = Policy::select(array('id'))
            ->whereMonth('created_at',3)
            ->whereYear('created_at',2021)->get();
            if(count($policies) > 0 )
            {
                foreach ($policies as  $pData) {
                    $pID = $pData->id;                    
                    $duplicatePolices = DB::select('call getDuplicateEntryForTransactions(?)',[$pID]);
                    echo $pID.'-'.count($duplicatePolices).'<br>';
                    if(count($duplicatePolices) > 0)
                    {
                        foreach ($duplicatePolices as $dData) { 
                            $duplicatePID = $dData->id;                           
                            PaymentTransaction::where('id', $duplicatePID)->delete();
                        }
                        
                    }
                }
                sleep(1);
            }
        }
        catch(Exception $e){
            Illuminate\Support\Facades\DB::rollBack();
            return $e->getMessage()
            ;
        }
    }
}
