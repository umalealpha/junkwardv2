<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;

use AlphaDirect\Models\LedgerArchive;
use AlphaDirect\Policy;
use Http\Client\Exception;
use Carbon\Carbon;
use AlphaDirect\Ledger;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\SubLedger;
use AlphaDirect\Models\CronStatus;
use Log;
use DB;

class CleanDuplicateInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ledger:clean-duplicates';
    protected $description = 'Soft delete duplicate invoice entries from policy_ledger';
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
        try {
        $cron = new CronStatus();
        $cron->name = "Invoices DomCom Start";
        // $cron->start = Carbon::now();
        // $cron->save();
        Log::info('Invoices DomCom started');

        $today = Carbon::now()->format('d');//Carbon::today();    
        $today = Carbon::now()->toDateString();
        $ids = ['COMG2024111852']; // Replace this with your actual $ids source 'DOMG2024101353' 

        $validPolicies = Policy::
             whereIn('product_id', [7, 8])
            ->where('status', 1)
            ->whereIn('policyNumber', $ids)
            ->where('created_at', '>=', '2024-07-01 00:00:00')
            ->get('id');
            if ($validPolicies->isEmpty()) {
            $this->info('No matching policies found.');
            return;
            }
        // Step 2: Find invoice_dates with multiple entries in policy_ledger for valid policies ->where('id',412)
        foreach ($validPolicies as $policy) {
            $policyActions = PolicyAction::where('policy_id', $policy->id)
            ->where('status', 'ISSUED')
            ->get();
        
        foreach ($policyActions as $policyAction) {
            $premiumFreq = $policy->premium_freq;
            $from = Carbon::parse($policyAction->effective_from);
            $to = Carbon::parse($policyAction->effective_to);
            //     $ledgers = Ledger::where('policy_id', $policy->id)
            //         ->where('trans_type', 'Invoice')
            //         ->whereNull('deleted_at')
            //         ->whereBetween('invoice_date', [$from, $to])
            //         ->get();

            //     // Group ledgers according to frequency
            //   if ($premiumFreq == 3) { // Annual frequency
            //     // Group all as a single period
            //     $grouped = collect(['annual_period' => $ledgers]);
            // } elseif ($premiumFreq == 5) { // Quarterly
            //     $grouped = $ledgers->groupBy(function ($ledger) use ($from) {
            //         $monthsDiff = $from->diffInMonths(Carbon::parse($ledger->invoice_date));
            //         return 'Q' . floor($monthsDiff / 3);
            //     });
            // } else { // Monthly
            //     $grouped = $ledgers->groupBy(function ($ledger) {
            //         return Carbon::parse($ledger->invoice_date)->format('Y-m');
            //     });
            // }
        //     if (count($ledgers) > 0) {
        //         foreach ($grouped as $period => $periodLedgers) {
        //         //if ($periodLedgers->count() > 1) {
        //             $sorted = $periodLedgers->sortBy('created_at')->values();
        //             foreach ($sorted as $index => $ledger) {
        //                // $isZero = $ledger->invoice_amount == '0.00';
        //                 $isPending = strtolower($ledger->status) === 'pending';

        //                 // Keep the first valid one, delete others
        //                 if ($index === 0) {
        //                     echo "Keeping Ledger ID: {$ledger->id}($period)<br/>";
        //                     continue;
        //                 }

        //                 echo "Deleting Duplicate Ledger ID: {$ledger->id} ($period)<br/>";
        //                 //Ledger::where('id', $ledger->id)->update(['deleted_at' => Carbon::now()]);
        //             }
        //        // }
        //     }
        // } else
        {
                $ledgers = Ledger::where('policy_id', $policy->id)
                                ->where('trans_type', 'Invoice')
                                ->whereNull('deleted_at')
                                ->where('action_id', $policyAction->id)
                                ->get()
                                ->groupBy(function ($ledger) {
                                    // Group by both action_id and invoice_date
                                    return $ledger->action_id;
                                });
                foreach ($ledgers as $key => $group) {
                    if ($group->count() > 1) {
                        // Sort by created_at to consistently keep the first created
                        $sorted = $group;//->sortBy('action_id')->values();
                        // Keep the first record, soft-delete the rest
                        foreach ($sorted as $index => $ledger) {
                            echo "Ledger ID: {$ledger->id} ({$index})<br/>";
                            if ($index === 0) {
                                echo "Keeping Ledger ID: {$ledger->id} ({$key})<br/>";
                            } else {
                                echo "Soft-deleting Duplicate Ledger ID: {$ledger->id} ({$key})<br/>";
                                
                                Ledger::where('id', $ledger->id)->update(['deleted_at' => Carbon::now()]);
                            }
                        }
                    }
                }

            }
           
        }
        // $duplicateDates =Ledger::select('invoice_date')
        //     ->where('trans_type', 'Invoice')
        //     ->where('policy_id', $policy->id)
        //     ->where('status','!=','Paid')
        //     ->groupBy('invoice_date')
        //     ->havingRaw('COUNT(*) > 1')
        //     ->pluck('invoice_date');
        //     dd($duplicateDates);
        // if ($duplicateDates->isEmpty()) {
        //     $this->info('No duplicate invoice dates found.');
        //     return;
        // }
    
        // Step 3: Soft delete the duplicates
        // $affected = DB::table('policy_ledger')
        //     ->where('tran_type', 'Invoice')
        //     ->whereIn('invoice_date', $duplicateDates)
        //     ->whereIn('policy_number', $validPolicies)
        //     ->whereNull('deleted_at')
        //     ->update(['deleted_at' => $today]);
    
        }
        // $cron->end = Carbon::now();
        // $cron->save();
    } catch (\Exception $e) {
        return false;
    }
    }
    
}
