<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Ledger;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Http\Client\Exception;
use Illuminate\Support\Facades\DB;
use AlphaDirect\Models\CronStatus;


class ProcessPaymentCompanyRef extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessPaymentCompanyRef:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process payment transactions to duplicate and update CompanyRef field';

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
        $cron->name  = "ProcessPaymentCompanyRef:cron";
        $cron->start = \Carbon\Carbon::now();
        // Step 1: Get all transactions where product_id in (7,8) AND CompanyRef is not null
        $records = DB::table('payment_transactions as pt')
            ->join('policies as p', 'p.id', '=', 'pt.policy_id')
            ->whereIn('p.product_id', [7, 8])
            ->whereNotNull(columns: 'pt.CompanyRef')
            ->where('pt.policy_id', '=', 117575)
            ->where('pt.is_reverse', '!=', 1)
             //->whereDate('pt.created_at', '!=', Carbon::today())
             ->whereDate('pt.created_at', Carbon::parse('2025-11-11'))
            ->select('pt.*')
            ->get();
   // dd(count($records));
        if ($records->isEmpty()) {
            $this->info("No records found.");
            return;
        }

        foreach ($records as $rec) {

            // Step 2: Insert duplicate row
           DB::table('payment_transactions')->insert([
                'policyNumber'       => $rec->policyNumber ?? null,
                'policy_id'          => $rec->policy_id,
                'referenceNumber'    => $rec->referenceNumber,
                'amount'             => $rec->amount,
                'status'             => $rec->status,
                'paymentDate'        => $rec->paymentDate ?? null,
                'new_payment_date'   => $rec->new_payment_date ?? null,
                'paymentMethod'      => $rec->paymentMethod ?? null,
                'cashRecipient'      => $rec->cashRecipient ?? null,
                'note'               => $rec->note ?? null,
                'is_ledger'          => $rec->is_ledger ?? 0,
                'paymentLoggedBy'    => $rec->paymentLoggedBy ?? null,
                'is_refund'          => $rec->is_refund ?? 0,
                'created_at'         => now(),
                'updated_at'         => now(),
                'CompanyRef'         => $rec->CompanyRef,
                'paymentAlreadyLog'  => $rec->paymentAlreadyLog ?? null,
                'request_type'       => $rec->request_type ?? null,
                'reveral_transaction_id' => $rec->id ?? null,
            ]);


            // Step 3: Update original row (CompanyRef = NULL)
            DB::table('payment_transactions')
                ->where('id', $rec->id)
                ->update([
                    'CompanyRef' => null,
                    'updated_at' => now(),
                    'is_reverse' => 1,
                    'reversal_date' => now(),
                    'reversal_comment' => 'Automated reversal to duplicate CompanyRef',
                    'reversal_by' => 'System',
                ]);
        }

        $this->info("Duplicate + update process completed.");
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }

}
