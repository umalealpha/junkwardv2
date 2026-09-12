<?php


namespace AlphaDirect\Console\Commands;

use AlphaDirect\CustomerBanking;
use AlphaDirect\LedgerSonali as Ledger;
use AlphaDirect\PolicySonali as Policy;
use AlphaDirect\PaymentTransactionSonali as PaymentTransaction;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\Region;
use AlphaDirect\SubLedgerSonali as SubLedger;
use Carbon\Carbon;
use Http\Client\Exception;
use Illuminate\Console\Command;
use AlphaDirect\Mail\LedgerDailyReport;
use AlphaDirect\Mail\SendPO;
use Illuminate\Support\Facades\Mail;
use AlphaDirect\Models\CronStatus;
use DB;
class UpdateBalance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'UpdateBalance:cron';

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
        $cron->name  = "UpdateBalance:cron";
        $cron->start = \Carbon\Carbon::now();
        $today = Carbon::today();
        $dt  = Carbon::now();
        $now = $dt->toDateString();

        ini_set('max_execution_time', 0);
            //$policyId = 10516;
            $ledger = Ledger::get('policy_id')
            ->whereIn('policy_id',[60099])
            ->groupBy('policy_id')
            ->toArray();
            
            if(count($ledger) > 0)
            {
                foreach ($ledger as $lData) 
                {
                    $policyId = $lData[0]['policy_id'];
                    $ledger = Ledger::where('policy_id', $policyId)
                    ->orderBy('system_date','ASC')
                    ->get(array('id','credit','debit','balance'))
                    ->toArray();
                    dd($ledger);
                    if(count($ledger) > 0)
                    {
                        $tbalance=0;
                        foreach ($ledger as $key => $lData) 
                        {
                            $credit    = isset($lData['credit']) ? $lData['credit'] : 0;
                            $balance   = isset($lData['balance']) ? $lData['balance'] : 0;
                            $debit     = isset($lData['debit']) ? $lData['debit'] : 0;
                            $ledgerId  = $lData['id'];
                            $chkbala   = $debit - $credit;  
                            
                            $tbalance += $chkbala;
                            $finalTotal = $tbalance  - $credit;
                            $finalBal = str_replace(',', '',number_format($tbalance, 2));
                            $lStatus =Ledger::where('policy_id',$policyId)
                            ->where('id',$ledgerId)
                            ->update(array('balance'=>$finalBal));

                        }

                    }
                }
            }
    }
}
