<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Customer;
use Carbon\Carbon;
use Log;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\User;
use AlphaDirect\Http\Controllers\LlmApiCrontroller;
use AlphaDirect\Policy;

class LlmApiPolicySaleRegisterCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'llmApiPolicySaleRegister:cron';

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
        $cron->name = "llmApiPolicySaleRegister:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

        try {
            $policies = Policy::whereNull('llmSaleStatus')
                            ->limit(100)
                            ->get();

            Log::info("Found " . $policies->count() . " policies for LLM sale registration.");

            $controller = new LlmApiCrontroller();

            foreach ($policies as $policy) {

                Log::info("Registering customer ID: {$policy->customer_id} for policy: {$policy->policyNumber}");

                $controller->RegisterCustomer($policy->customer_id, $policy->agent_id ?? 1);

                $success = $controller->SalePolicy($policy->policyNumber);

                if ($success) {
                    Log::info("LLM Sale registered successfully for policy: " . $policy->policyNumber);
                } else {
                    Log::warning("LLM Sale registration failed for policy: " . $policy->policyNumber);
                }
            }

            Log::info("LLM policy sale cron job completed.");

        } catch (\Exception $e) {
            Log::error("LLM policy sale cron failed: " . $e->getMessage());
        }

        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
