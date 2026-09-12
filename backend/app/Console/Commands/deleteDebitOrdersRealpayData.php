<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\DummyRealpayData;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\RealpayContractInstallments;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Log;

class deleteDebitOrdersRealpayData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deleteDebitOrdersRealpayData:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all installments before policy inception date from realpay contract installments table and payment transaction table which were added in database at the time of debit orders integration.';

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
        Log::info('Starting the Realpay debit orders deleting data processing job.');

        $arr = ['COMG2024110310','COMG2024104074','COMG2024104000','COMG2024100367','COMG2024104025','COMG2024112066','COMG2024103661','COMG2024104106','COMG2024112841','COMG2024104062','COMG2024113295','DOMG2024115990','COMG2024099860','COMG2024111678','COMG2024104023','COMG2024103062','COMG2024110294','COMG2024103065','COMG2024105951'];
        // Fetch all records from DummyRealpayData
        $dummyRecords = DummyRealpayData::whereIn('PolicyNumber',$arr)->get();
        // $dummyRecords = DummyRealpayData::where('status','!=',1)->get();

        foreach ($dummyRecords as $dummyRecord) {
            $clientNumber = $dummyRecord->realpay_client_number;
            $contractNumber = $dummyRecord->realpay_contract_number;
            $policyInceptionDate = Carbon::parse($dummyRecord->policy_inception_date);

            // Delete records from RealpayContractInstallments before the policy inception date
            $deletedCount = RealpayContractInstallments::where('clientNumber', $clientNumber)
                ->where('contractNumber', $contractNumber)
                ->where('InstalmentActionDate', '<', $policyInceptionDate)
                ->delete();

            $deletedPaymentCount = PaymentTransaction::where('policyNumber', $dummyRecord->PolicyNumber)
                ->where('paymentDate', '<', $policyInceptionDate)
                ->delete();


            // $dummyRecord->status = 1; // entries deleted from realpay installment table so status 1
            $dummyRecord->status = 2; // both table entries deleted payment transactions and realpay installment table so status 2
            $dummyRecord->save();

            Log::info("Deleted {$deletedCount} records from RealpayContractInstallments for Client: {$clientNumber}, Contract: {$contractNumber} before {$policyInceptionDate->toDateString()}.");

            sleep(1);
        }

        Log::info('Realpay debit orders deleting data processing job completed.');

    }
}
