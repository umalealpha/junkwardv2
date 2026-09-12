<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\Payment\VCS\PaymentController;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\VcsTransactionToAddInTxLog;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Log;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\VcsTransactionDummyData;
use Carbon\Carbon;
use AlphaDirect\Transaction;

class AddVcsReconsilationTxIntoTxLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'AddVcsReconsilationTxIntoTxLog:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This cron is used for vcs reconsilation';

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
        $cron->name = "AddVcsReconsilationTxIntoTxLog:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started to add transaction.');

        $data = VcsTransactionDummyData::orderBy('id','desc')->get();

        if (isset($data)) {
            foreach ($data as $key => $record) {
                echo "Record Id - ".$record->id;
                $paymentTx = PaymentTransaction::where('referenceNumber',$record->reference)->first();

                if (isset($paymentTx)) {
                    // $status = $paymentTx->status;
                    if ($paymentTx->amount != $record->amount) {
                        $paymentTx->amount = $record->amount;
                    }

                    $transactiondate = Carbon::parse($record->transactiondate)->format("Y-m-d");

                    if ($paymentTx->paymentDate != $transactiondate) {
                        $paymentTx->paymentDate = $transactiondate;
                        $paymentTx->new_payment_date = $transactiondate;
                    }

                    // // if ($status != $record['status']) {
                    // //     $paymentTx->status = $record['status'];
                    // // }
                    $paymentTx->save();

                } else {
                    // $request = new Request();
                    // $request['p2'] = $record->reference ? $record->reference : null;
                    // $request['p5'] = isset($record->name) ? $record->name : null;
                    // $request['p3'] = isset($record->response) ? $record->response : null;
                    // $request['p4'] = null;
                    // $request['p8'] = isset($record->goods) ? $record->goods : null;
                    // $request['p6'] = isset($record->amount) ? $record->amount : null;
                    // $request['p12'] = isset($code) ? $code : null;
                    // $request['m1'] = null;
                    // $request['p11'] = null;

                    // $vcs = new PaymentController;
                    // if (str_contains($record->reference,'-')) {
                    //     if ($record->status == "Auth Declined") {
                    //         $save = $vcs->declinedCallbackMonthlyVcs($request, "Recurring");
                    //     } elseif ($record->status == "Payment Done") {
                    //         $save = $vcs->acceptedCallbackMonthlyVcs($request, "Recurring");
                    //     }
                    // } else {
                    //     if ($record->status == "Auth Declined") {
                    //         $save = $vcs->declinedCallback($request);
                    //     } elseif ($record->status == "Payment Done") {
                    //         $save = $vcs->acceptedCallback($request);
                    //     }
                    // }

                    if ($record->status == "Payment Done") {
                        $record['status'] = 'Success';
                    } elseif ($record->status == "Payment Failed") {
                        $record['status'] = 'Failed';
                    }

                    // $vcs = new PaymentController();
                    // $policyNumber = $vcs->getReferenceNumber($record->originalreference);

                    $referenceNumberRow = Transaction::where('referenceNumber', $record->originalreference)->first();

                    if (isset($referenceNumberRow)) {
                        $policyNumber = $referenceNumberRow->policyNumber;

                        dump("ID ".$record->id." PolicyNumber - ".$policyNumber);

                        $paymentData = array();
                        $paymentData['policyNumber'] = $policyNumber;
                        $paymentData['referenceNumber'] = $record->reference;
                        $paymentData['amount'] = isset($record->amount) ? $record->amount : null;
                        $paymentData['status'] = $record->status ;
                        $paymentData['paymentDate'] = $record->transactiondate;
                        $paymentData['paymentMethod'] = 'VCS';
                        $paymentData['numberOfInstalmentsPaid'] = '0';
                        $paymentData['note'] = NULL;
                        $paymentData['send_sms_email'] = 1;

                        $policyController = new PolicyController();
                        $policyController->updatePaymentTransactions($paymentData);
                    }
                }

                $deleteEntry = VcsTransactionDummyData::where('id',$record->id)->delete();
                sleep(1);
            }
        }

        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
