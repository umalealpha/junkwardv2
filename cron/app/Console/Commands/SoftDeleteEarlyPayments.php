<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\CronStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Log;

class SoftDeleteEarlyPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'softDeleteEarlyPayments:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Soft delete payments made before billingStartDate for policies flagged as status=3';

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
        $cron->name = "softDeleteEarlyPayments:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

        Log::info('Cron started for Soft delete payments made before billingStartDate for policies.');

        $this->info("Starting early payment & ledger cleanup...");

        // Step 1: Fetch domgcomg policyNumbers where status = 3
        $policyNumbers = DB::table('Graphite_live.realpay_transactions_excel_data')
            ->where('status', 3)
            ->pluck('domgcomg')
            ->toArray();

        if (empty($policyNumbers)) {
            $this->info("No flagged policy numbers found.");
            return;
        }

        // Step 2: Fetch policy info (policyNumber => [id, billingStartDate])
        $policyMap = DB::table('Graphite_live.policies')
            ->whereIn('policyNumber', $policyNumbers)
            ->get()
            ->keyBy('policyNumber');

        if ($policyMap->isEmpty()) {
            $this->info("No matching policy records found.");
            return;
        }

        // Step 3: Fetch and filter early payments
        // $payments = DB::table('Graphite_live.payment_transactions')
        //     ->whereIn('policyNumber', array_keys($policyMap->toArray()))
        //     ->where('paymentMethod','RealPay')
        //     ->get()
        //     ->filter(function ($payment) use ($policyMap) {
        //         $policy = $policyMap[$payment->policyNumber] ?? null;
        //         return $policy && $payment->paymentDate < $policy->billingStartDate;
        //     });

        $payments = DB::table('Graphite_live.payment_transactions')
            ->whereIn('policyNumber', array_keys($policyMap->toArray()))
            ->where(function ($query) {
                $query->where('paymentMethod', 'RealPay')
                    ->orWhere('paymentMethod', 'Realpay');
            })
            ->whereNull('deleted_at')
            ->get()
            ->filter(function ($payment) use ($policyMap) {
                $policy = $policyMap[$payment->policyNumber] ?? null;
                return $policy && $payment->paymentDate < $policy->billingStartDate;
            });


        if ($payments->isEmpty()) {
            $this->info("No early payments found.");
            return;
        }

        // Step 4: Soft delete payments + corresponding ledger entries
        foreach ($payments as $payment) {
            // Soft delete payment
            DB::table('Graphite_live.payment_transactions')
                ->where('id', $payment->id)
                ->update(['deleted_at' => Carbon::now()]);
            $this->info("Soft-deleted payment ID: {$payment->id}");

            // Get corresponding policy ID
            $policy = $policyMap[$payment->policyNumber];
            if (!$policy) continue;

            // Soft delete matching policy_ledger entries
            $ledgers = DB::table('Graphite_live.policy_ledger')
                ->where('policy_id', $policy->id)
                ->where('trans_ref', $payment->referenceNumber)
                ->where('trans_type', 'Payment')
                ->whereNull('deleted_at')
                ->get();

            foreach ($ledgers as $ledger) {
                DB::table('Graphite_live.policy_ledger')
                    ->where('id', $ledger->id)
                    ->update(['deleted_at' => Carbon::now()]);
                $this->info("Soft-deleted ledger ID: {$ledger->id}");
            }
        }

        $this->info("Cleanup complete.");

        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
