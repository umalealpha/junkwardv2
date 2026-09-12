<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\PaymentTransaction;
use AlphaDirect\ScheduleTransaction;
use Illuminate\Console\Command;
use AlphaDirect\Models\CronStatus;

class correctScheduleTransactionEntries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'correctScheduleTransactionEntries:cron {policyNumber?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Correct Schedule Transaction Entries';

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
        $cron->name = "correctScheduleTransactionEntries:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        try {
            $this->info('Running...');
            $policyNumber = $this->argument('policyNumber');
            $payment_transactions = PaymentTransaction::where('policyNumber', $policyNumber)->get();
            if (isset($payment_transactions)) {
                foreach ($payment_transactions as $key => $payment_transaction) {
                    $this->info('Running...payment transaction...');
                    $scheduledTrans = ScheduleTransaction::where('billing_date', $payment_transaction->paymentDate)
                    ->where('policy_number', $payment_transaction->policyNumber)->first();

                    if (isset($scheduledTrans)) {
                        if ($scheduledTrans->status == 1 && $scheduledTrans->reason == 'Success') {
                            $this->info('Running...scheduled transaction...');
                            $scheduledTrans->status = $payment_transaction->status;
                            $scheduledTrans->reason = $payment_transaction->note;
                            $scheduledTrans->save();
                            $this->info('Running...end scheduled transaction...');
                        }
                    }

                }
            }

        } catch (\Exception $ex) {
            //throw $th;
        }
         $cron->end = \Carbon\Carbon::now();
         $cron->save();
    }
}
