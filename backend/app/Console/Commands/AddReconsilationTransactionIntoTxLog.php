<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\DPOTransactionDummyData;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\DpoPaymentController;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\TransactionToAddInTxLog;
use Illuminate\Console\Command;
use Log;
use Illuminate\Http\Request;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Policy;

class AddReconsilationTransactionIntoTxLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'AddReconsilationTransactionIntoTxLog:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This cron is used for dpo reconsilation';

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
        $cron->name = "AddReconsilationTransactionIntoTxLog:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started to add dpo transaction.');

        DPOTransactionDummyData::where('is_imported',0)->orderBy('id','desc')->chunkById(100, function($data){
            Log::info("Chunk - ".count($data));
            dump("Chunk - ".count($data));
            if (isset($data)) {
                foreach ($data as $key => $record) {
                    dump("policyNumber ".$record->policynumber);
                    $policy = Policy::where('policyNumber',$record->policynumber)->first();
                    // $policyNumber = null;
                    // if (isset($record->bookingref)) {
                    //     if(str_contains($record->bookingref, '/')){
                    //         $policyNumber = strtok($record->bookingref, '/');
                    //     } else {
                    //         $policy_number = $data->bookingref;
                    //     }
                    // }

                    // $paymentTx = PaymentTransaction::where('policyNumber',$record->policynumber)->where('referenceNumber',$record->token)->first();

                    $paymentTx = PaymentTransaction::where('referenceNumber',$record->token)->first();

                    if (isset($paymentTx)) {
                        // $status = $paymentTx->status;
                        // if ($paymentTx->amount != $record->total) {
                        //     $paymentTx->amount = $record->total;
                        // }

                        // if ($paymentTx->status == "SUCCESS" || $paymentTx->status == "S" || $paymentTx->status == "Success") {
                        //     $status = 'Paid';
                        // }
                        // if ($status != $record->status) {
                        //     $paymentTx->status = $record->status;
                        // }

                        // $paymentTx->save();

                        // $saveData = DPOTransactionDummyData::where('id',$record->id)->first();
                        // $saveData->is_imported = 2; // entry updated or entry present
                        // $saveData->save();

                    } else {
                        Log::info("Record added for policyNumber ".$record->policynumber);
                        dump("Record added for policyNumber ".$record->policynumber);
                        if ($record->status == "SUCCESS" || $record->status == "S" || $record->status == "Success") {
                            $record->status = 'Paid';
                        }

                        // $request = new Request();
                        // $request['policy_number'] = isset($record->policynumber) ? base64_encode($record->policynumber) : null;
                        // $request['amount'] = isset($record->total) ? $record->total : null;
                        // $request['status'] = isset($record->status) ? $record->status : null;
                        // $request['leadSource'] = 'Excel';
                        // $request['TransID'] = isset($record->token) ? $record->token : null;
                        // $request['CCDapproval'] = isset($record->approval) ? $record->approval : null;
                        // $request['PnrID'] = isset($record->policynumber) ? $record->policynumber : null;
                        // $request['TransactionToken'] = isset($record->token) ? $record->token : null;
                        // $request['CompanyRef'] = isset($record->policynumber) ? $record->policynumber : null;


                        $paymentData['policyNumber'] = isset($record->policynumber) ? $record->policynumber : null;
                        $paymentData['policy_id'] = isset($policy->id) ? $policy->id : null;
                        $paymentData['referenceNumber'] = isset($record->token) ? $record->token : null;
                        $paymentData['amount'] = isset($record->total) ? $record->total : null;
                        $paymentData['status'] = isset($record->status) ? $record->status : null;
                        $paymentData['paymentDate'] = \Carbon\Carbon::parse($record->paymentdate)->format('Y-m-d');
                        $paymentData['paymentMethod'] = 'DPO';
                        $paymentData['numberOfInstalmentsPaid'] = NULL;
                        $paymentData['note'] = 'TRANSACTION ' . $record->status;
                        $paymentData['send_sms_email'] = 1;

                        $policyController = new PolicyController();
                        $saveEntry = $policyController->updatePaymentTransactions($paymentData);

                        $saveData = DPOTransactionDummyData::where('id',$record->id)->first();
                        $saveData->is_imported = 1; // entry added
                        $saveData->save();

                    }

                    $deleteEntry = DPOTransactionDummyData::where('id',$record->id)->delete();

                    sleep(1);
                }
            }
        });

        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
