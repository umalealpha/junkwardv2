<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Ledger;
use AlphaDirect\Policy;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\Region;
use AlphaDirect\SubLedger;
use Carbon\Carbon;
use Http\Client\Exception;
use AlphaDirect\Mail\LedgerDailyReport;
use AlphaDirect\Mail\SendPO;
use Illuminate\Support\Facades\Mail;
use AlphaDirect\Models\CronStatus;
use DB;

class updateLedgerBalanceComDom extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'updateLedgerBalanceComDom:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Ledger Balance ComDom';

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
        $cron->name  = "updateLedgerBalanceComDom:cron";
        $cron->start = \Carbon\Carbon::now();
        $today = Carbon::today();
        $dt  = Carbon::now();
        $now = $dt->toDateString();

        ini_set('max_execution_time', 0);
            //$policyId = 10516;
             $ledger = Ledger::where('policy_id',103813)
            ->groupBy('policy_id')
            ->get('policy_id')
            ->toArray();
            
            if(count($ledger) > 0)
            {
                foreach ($ledger as $lData) 
                {
                    $policyId = $lData['policy_id'];
                    $ledger = Ledger::where('policy_id', $policyId)
                    ->whereIn('trans_type',['Payment','Invoice','Credit Note'])
                    ->orderBy('accounting_date','ASC')
                    ->get(array('trans_type','id','credit','debit','balance'))
                    ->toArray();
                    if(count($ledger) > 0)
                    {
                        $tbalance=0;
                        foreach ($ledger as $key => $lData) 
                        {
                            $credit    = isset($lData['credit']) ? $lData['credit'] : 0;
                            $tbalance -= $lData['credit'];

                            if(isset($lData['trans_type']) && $lData['trans_type']=='Credit Note' )
                            {
                                $credit = isset($lData['debit']) ? $lData['debit'] : 0;
                                $tbalance -= $lData['debit'];
                            }
                            // $balance   = isset($lData['balance']) ? $lData['balance'] : 0;
                            if(isset($lData['trans_type']) && $lData['trans_type']=='Invoice' ){
                            $debit     = isset($lData['debit']) ? $lData['debit'] : 0;
                            $tbalance += $lData['debit'];
                            }
                            $ledgerId  = $lData['id'];
                           // $chkbala   = $debit - $credit;  
                            
                           // $tbalance += $chkbala;
                            // $finalTotal = $tbalance  - $credit;
                            $finalBal = str_replace(',', '',number_format($tbalance, 2));
                            // echo   $credit.'--'.$debit.'--'.$finalBal;
                            // echo "\n";
                            $lStatus =Ledger::where('policy_id',$policyId)
                            ->where('id',$ledgerId)
                            ->update(array('balance'=>$finalBal));

                        }

                    }
                }
            }
           $cron->end = \Carbon\Carbon::now();
           $cron->save();
    }
}
