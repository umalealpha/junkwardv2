<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Policy;
use AlphaDirect\ScheduleTransaction;
use Log;

class CancelScheduledTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cancel:scheduled-transactions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel scheduled transactions for specific policies status 2';

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
        
        Policy::where('status', 2)->chunk(1000, function ($policies) {
            foreach ($policies as $policy) {
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
                    Log::info("Processed transactions for policy: {$policy->policyNumber}");
                }
                //$this->info("Processed transactions for policy: {$policy->policyNumber}");
            }
        });

        //$this->info('All scheduled transactions processed successfully.');
    }
}
