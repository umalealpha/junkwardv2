<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Log;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\User;
use AlphaDirect\Http\Controllers\LlmApiCrontroller;
use AlphaDirect\Policy;

class LlmApiCustomerRegisterCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'llmApiCustomerRegister:cron';

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
        $cron->name = "llmApiCustomerRegister:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $customers = Customer::where('llmCustomerStatus',null)->limit(1)->get();
        Log::info(count($customers));
        if ($customers->count() > 0) {
            $llmController = new LlmApiCrontroller();

            foreach ($customers as $customer) {
                $policy = Policy::where('customer_id',$customer->id)->first('agent_id');
                // $llmController->RegisterCustomer($customer->id,$policy->agent_id);
                if ($policy) {
                    $llmController->RegisterCustomer($customer->id, $policy->agent_id);
                }
            }
        }

        $cron->end = \Carbon\Carbon::now();
        $cron->save();

    }
}
