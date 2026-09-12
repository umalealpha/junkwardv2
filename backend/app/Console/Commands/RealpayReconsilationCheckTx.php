<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use AlphaDirect\DummyRealpayExcelTxData;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\RealpayTransactionDummyData;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Log;
use AlphaDirect\Models\CronStatus;
class RealpayReconsilationCheckTx extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'RealpayReconsilationCheckTx:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Realpay Reconsilation Check Transactions';

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
        $cron->name = "RealpayReconsilationCheckTx:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started to update dpo transaction dummy data.');

        $data = RealpayTransactionDummyData::orderBy('id','desc')->get();
        if (isset($data)) {
            foreach ($data as $key => $item) {
                $realpayInstallment = RealpayContractInstallments::where('clientNumber',$item->clientNumber)->where('contractNumber',$item->contractNumber)->where('InstalmentSequence',$item->instSeq)->first();
                if (isset($realpayInstallment)) {
                    $paymentTx = PaymentTransaction::where('policyNumber',$item->clientNumber)->where('referenceNumber',$realpayInstallment->InstalmentReferenceNumber)->first();

                    $customer = null;
                    if (isset($item->clientNumber)) {
                        $policy = Policy::where('policyNumber',$item->clientNumber)->first();
                        $customer = Customer::where('id',$policy->customer_id)->first();
                    }

                    $update = DummyRealpayExcelTxData::where('clientNumber',$item->clientNumber)->where('contractNumber',$item->contractNumber)->where('instSeq',$item->instSeq)->first();
                    if (isset($update)) {
                        $update->clientName = isset($item->clientName) ? $item->clientName : null;
                        $update->merchant = isset($item->merchant) ? $item->merchant : null;
                        $update->clientNumber = isset($item->clientNumber) ? $item->clientNumber : null;
                        $update->installmentDate = isset($item->installmentDate) ? Carbon::parse($item->installmentDate)->format('Y-m-d') : null;
                        $update->contractNumber = isset($item->contractNumber) ? $item->contractNumber : null;
                        $update->contractSequence = isset($item->contractSequence) ? $item->contractSequence : null;
                        $update->instSeq = isset($item->instSeq) ? $item->instSeq : null;
                        $update->installmentAmount = isset($item->installmentAmount) ? $item->installmentAmount : null;
                        $update->totalAmount = isset($item->totalAmount) ? $item->totalAmount : null;
                        $update->collectedAmount = isset($item->collectedAmount) ? $item->collectedAmount : null;
                        $update->currentCycleHits = isset($item->currentCycleHits) ? $item->currentCycleHits : null;
                        $update->hitsAllowed = isset($item->hitsAllowed) ? $item->hitsAllowed : null;
                        $update->tracking = isset($item->tracking) ? $item->tracking : null;
                        $update->reportStatus = isset($item->reportStatus) ? $item->reportStatus : null;
                        $update->currentStatus = isset($item->currentStatus) ? $item->currentStatus : null;
                        $update->result = isset($item->result) ? $item->result : null;
                        $update->clientBank = isset($item->clientBank) ? $item->clientBank : null;
                        $update->save();

                    } else {
                        $add = new DummyRealpayExcelTxData();
                        $add->clientName = isset($item->clientName) ? $item->clientName : null;
                        $add->merchant = isset($item->merchant) ? $item->merchant : null;
                        $add->clientNumber = isset($item->clientNumber) ? $item->clientNumber : null;
                        $add->installmentDate = isset($item->installmentDate) ? Carbon::parse($item->installmentDate)->format('Y-m-d') : null;
                        $add->contractNumber = isset($item->contractNumber) ? $item->contractNumber : null;
                        $add->contractSequence = isset($item->contractSequence) ? $item->contractSequence : null;
                        $add->instSeq = isset($item->instSeq) ? $item->instSeq : null;
                        $add->installmentAmount = isset($item->installmentAmount) ? $item->installmentAmount : null;
                        $add->totalAmount = isset($item->totalAmount) ? $item->totalAmount : null;
                        $add->collectedAmount = isset($item->collectedAmount) ? $item->collectedAmount : null;
                        $add->currentCycleHits = isset($item->currentCycleHits) ? $item->currentCycleHits : null;
                        $add->hitsAllowed = isset($item->hitsAllowed) ? $item->hitsAllowed : null;
                        $add->tracking = isset($item->tracking) ? $item->tracking : null;
                        $add->reportStatus = isset($item->reportStatus) ? $item->reportStatus : null;
                        $add->currentStatus = isset($item->currentStatus) ? $item->currentStatus : null;
                        $add->result = isset($item->result) ? $item->result : null;
                        $add->clientBank = isset($item->clientBank) ? $item->clientBank : null;
                        $add->save();
                    }

                    if (isset($paymentTx)) {
                        $status = $paymentTx->status;
                        if ($paymentTx->amount == $item->installmentAmount) {
                            // if ($paymentTx->status == "SUCCESS" || $paymentTx->status == "S" || $paymentTx->status == "Success") {
                            //     $status = 'Paid';
                            // }
                            if ($paymentTx->status == $item->currentStatus) {
                                $removeEntry = RealpayTransactionDummyData::where('clientNumber',$item->clientNumber)->where('contractNumber',$item->contractNumber)->where('instSeq',$item->instSeq)->delete();
                                // dd($item,$paymentTx);
                            } else {
                                $item->reason = "Status not matching";
                            }
                        } else {
                            $item->reason = "Amount not matching";
                        }
                    } else {
                        $item->reason = "Transaction not found";
                    }

                    $item->graphite_amount = isset($paymentTx->amount) ? $paymentTx->amount : null;
                    $item->graphite_date = isset($paymentTx->paymentDate) ? $paymentTx->paymentDate : null;
                    if (isset($customer)) {
                        $item->graphite_cust_email = isset($customer->email) ? $customer->email : null;
                    }
                    $item->save();

                    if (isset($customer) && isset($item['customeremail'])) {
                        if ($customer->email != $item['customeremail']) {
                            $customer->email = $item['customeremail'];
                            $customer->save();
                        }
                    }
                }

                sleep(1);
            }
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
