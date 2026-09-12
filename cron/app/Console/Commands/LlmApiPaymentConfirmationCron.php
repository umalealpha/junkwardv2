<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Customer;
use Carbon\Carbon;
use Log;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\User;
use AlphaDirect\Http\Controllers\LlmApiCrontroller;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\Models\LlmApi;

class LlmApiPaymentConfirmationCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'llmApiPaymentConfirmationCron:cron';

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
        $cron->name = "llmApiPaymentConfirmationCron:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

        try {
            $controller = new LlmApiCrontroller();

            $policies = Policy::where('llmSaleStatus', 1)->limit(100)->get();

            foreach ($policies as $policy) {
                $transactions = PaymentTransaction::where('policyNumber', $policy->policyNumber)->get();

                foreach ($transactions as $trxn) {
                    $exists = LlmApi::where('policyNumber', $trxn->policyNumber)
                                    ->whereNotNull('payment_transection_id')
                                    // ->where('payment_transection_id', $trxn->id)
                                    ->exists();

                    if ($exists) {
                        Log::info("Recurring payment triggered for transaction ID: {$trxn->id}");
                        $controller->RecurringPremiumPayment($trxn);
                    } else {
                        Log::info("Payment confirmation triggered for transaction ID: {$trxn->id}");
                        $controller->PaymentConfirmation($trxn);
                    }
                }
                sleep(1);
            }

            Log::info("LLM payment confirmation cron completed successfully.");
        } catch (\Exception $e) {
            Log::error("LLM payment confirmation cron failed: " . $e->getMessage());
        }

        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
