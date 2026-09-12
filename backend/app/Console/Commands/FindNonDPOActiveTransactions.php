<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\CustomerBanking;
use Illuminate\Console\Command;
use AlphaDirect\Models\PolicyUpgradeMotorcomp;
use AlphaDirect\Policy;
use AlphaDirect\ScheduleTransaction;
use Log;

class FindNonDPOActiveTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:find-non-dpo-active';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find payments where the method is not DPO and scheduleTransaction is Active';

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
      $policies =  PolicyUpgradeMotorcomp::where('old_payment_type', 'DPO')->get();
      if( count($policies) > 0 ){
          foreach($policies as $upgradeMot){
                     $policy = Policy::where('id', $upgradeMot->policy_id)->where('status','!=',2)->first();
                     $customerBanking = CustomerBanking::where('policy_id', $upgradeMot->policy_id)
                     ->orderBy('id','desc')->first();
                     if($customerBanking && $customerBanking->billing != 'DPO'){
                        if(ScheduleTransaction::where('policy_number', $policy->policyNumber)
                        ->whereIn('status', [0, 1, 3,5])->exists()){
                        ScheduleTransaction::where('policy_number', $policy->policyNumber)
                            ->whereIn('status', [0, 1, 3,5])
                            ->chunkById(100, function ($transactions) {
                                foreach ($transactions as $transaction) {
                                        $transaction->status = 4;
                                        $transaction->save();
                         
                                    
                                }
                            });
                            Log::info("Processed transactions Cancel for policy: {$policy->policyNumber}");
                        }

                     }

          }
      }
    }
}
