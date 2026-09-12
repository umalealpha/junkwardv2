<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\Admin\RealPayController;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractDetails;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\RealpayTransactionToAddInTxLog;
use Illuminate\Console\Command;
use Log;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\RealpayTransactionDummyData;

class AddRealpayReconsilationTxIntoTxLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'AddRealpayReconsilationTxIntoTxLog:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This cron is used for realpay reconsilation';

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
        $cron->name = "AddRealpayReconsilationTxIntoTxLog:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started to add realpay transaction.');

        RealpayTransactionDummyData::where('is_imported',0)->orderBy('id','desc')->chunkById(100, function($data){
            Log::info("Chunk - ".count($data));
            dump("Chunk - ".count($data));
            if (isset($data)) {
                foreach ($data as $key => $record) {
                    $realpayInstallment = RealpayContractInstallments::where('clientNumber',$record->clientNumber)->where('contractNumber',$record->contractNumber)->where('InstalmentSequence',$record->instSeq)->first();

                    if(str_contains($record->clientNumber, '/')){
                        $policyNumber = strtok($record->clientNumber, '/');
                    } else {
                        $policyNumber = $record->clientNumber;
                    }

                    $policy = Policy::where('policyNumber',$policyNumber)->first();

                    // if (isset($policy) && $policy->status != 2) {
                        dump("ID ".$record->id." ClientNumber ".$record->clientNumber);
                        Log::info("ID ".$record->id." Add realpay transaction for ClientNumber ".$record->clientNumber);

                        if (isset($realpayInstallment)) {

                            if (isset($policy)) { //  && $policy->status == 0
                                $realpayPayment = RealpayPaymentRequest::where('clientNumber',$record->clientNumber)->first();

                                // if (isset($realpayPayment)) {
                                    // $realpayClientContract = RealpayClientContracts::where('client_number',$record->clientNumber)->where('contract_number',$record->contractNumber)->first();
                                    // $realpayContract = RealpayContractDetails::where('ClientNumber',$record->clientNumber)->where('ContractNumber',$record->contractNumber)->first();

                                    // if (isset($realpayClientContract) || isset($realpayContract)) {

                                    //     $record['InstalmentReferenceNumber'] = $realpayInstallment->InstalmentReferenceNumber;

                                    //     $realpayCon = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                                    //     $cancelContract = $realpayCon->realpayReconsiliationTxLogCheck($record);
                                    // } else {

                                        // $logData = [
                                        //     'policy_id'=>$policy->id,
                                        //     'client_number'=>$policy->policyNumber,
                                        //     'contract_number'=>$record->contractNumber,
                                        //     'status'=>1,
                                        // ];

                                        // $addLog = RealpayClientContracts::addLog($logData);

                                        $record['InstalmentReferenceNumber'] = $realpayInstallment->InstalmentReferenceNumber;

                                        $realpayCon = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                                        $cancelContract = $realpayCon->realpayReconsiliationTxLogCheck($record);
                                    // }
                                // } else {
                                //     $payRequest = new RealpayPaymentRequest();
                                //     $payRequest->policy_id = $policy->id;
                                //     $payRequest->clientNumber = $record->clientNumber;
                                //     $payRequest->client_response_sequence = null;
                                //     $payRequest->first_premium = $policy->leftout_premium;
                                //     $payRequest->premium = $record->installmentAmount;
                                //     $payRequest->billing_day = $policy->billing_day;
                                //     $payRequest->billing_date = $policy->billingStartDate;
                                //     $payRequest->first_premium_contract = null;
                                //     $payRequest->contract = $record->contractNumber;
                                //     $payRequest->status = 1;
                                //     $payRequest->response = 1;
                                //     $payRequest->frequency = $policy->premium_freq;
                                //     $payRequest->clientCreated = 1;
                                //     $payRequest->contractCreated = 1;
                                //     $payRequest->save();

                                //     $logData = [
                                //         'policy_id'=>$policy->id,
                                //         'client_number'=>$policy->policyNumber,
                                //         'contract_number'=>$record->contractNumber,
                                //         'status'=>1,
                                //     ];

                                //     $addLog = RealpayClientContracts::addLog($logData);

                                //     $record['InstalmentReferenceNumber'] = $realpayInstallment->InstalmentReferenceNumber;

                                //     $realpayCon = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                                //     $cancelContract = $realpayCon->realpayReconsiliationTxLogCheck($record);
                                // }
                            }

                        } else {
                            // $payRequest = new RealpayPaymentRequest();
                            // $payRequest->policy_id = $policy->id;
                            // $payRequest->clientNumber = $record->clientNumber;
                            // $payRequest->client_response_sequence = null;
                            // $payRequest->first_premium = $policy->leftout_premium;
                            // $payRequest->premium = $record->installmentAmount;
                            // $payRequest->billing_day = $policy->billing_day;
                            // $payRequest->billing_date = $policy->billingStartDate;
                            // $payRequest->first_premium_contract = null;
                            // $payRequest->contract = $record->contractNumber;
                            // $payRequest->status = 1;
                            // $payRequest->response = 1;
                            // $payRequest->frequency = $policy->premium_freq;
                            // $payRequest->clientCreated = 1;
                            // $payRequest->contractCreated = 1;
                            // $payRequest->save();

                            // $logData = [
                            //     'policy_id'=>$policy->id,
                            //     'client_number'=>$policy->policyNumber,
                            //     'contract_number'=>$record->contractNumber,
                            //     'status'=>1,
                            // ];

                            // $addLog = RealpayClientContracts::addLog($logData);

                            // $new = new RealpayContractInstallments();
                            // $new->clientNumber = $record->clientNumber;
                            // $new->contractNumber = $record->contractNumber;
                            // $new->InstalmentReferenceNumber = NULL; //$realpayInstallment->InstalmentReferenceNumber;
                            // $new->InstalmentSequence = $record->instSeq;
                            // $new->CTCAmount = $record->installmentAmount;
                            // $new->InstalmentActionDate = $record->installmentDate;
                            // $new->TrackingCode = $record->tracking;
                            // $new->InstalmentAmount = $record->installmentAmount;
                            // $new->InstalmentStatus = $record->currentStatus;
                            // $new->save();

                            $ins = [
                                'clientNumber' => $record->clientNumber,
                                'contractNumber' => $record->contractNumber,
                                'contractSequence' => $record->contractSequence,
                                'instalmentSequence' => $record->instSeq
                            ];

                            $realpayCon = new RealPayController();
                            $getInstl = $realpayCon->getRealpayInstallmentsWithContractSequence($ins);
                            // $getInstl = $realpayCon->getRealpayInstallments($ins);

                            if (isset($getInstl) || isset($getInstl['InstalmentGetResponse'])) {
                                foreach($getInstl['InstalmentGetResponse'] as $key => $installment){
                                    if ($installment['InstalmentSequence'] == $record->instSeq) {
                                        $record['InstalmentReferenceNumber'] = $installment['InstalmentReferenceNumber'];
                                        Log::info("policyNumber - ".$record->clientNumber. " and instl no - " .$record['InstalmentReferenceNumber']);
                                    }
                                }

                                $record['policy_id'] = $policy->id;

                                $realpayCon = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                                $cancelContract = $realpayCon->realpayReconsiliationTxLogCheck($record);
                            } else {
                                $saveData = RealpayTransactionDummyData::where('id',$record->id)->first();
                                $saveData->is_imported = 2; // installment ref no present
                                $saveData->save();
                            }
                            // else {
                            //     $getInstl = $realpayCon->getRealpayInstallmentsForInstantProduct($ins);
                            //     foreach($getInstl['InstalmentGetResponse'] as $key => $installment){
                            //         if ($installment['InstalmentSequence'] == $record->instSeq) {
                            //             $record['InstalmentReferenceNumber'] = $installment['InstalmentReferenceNumber'];
                            //             Log::info("policyNumber - ".$record->clientNumber. " and instl no - " .$record['InstalmentReferenceNumber']);
                            //         }
                            //     }
                            // }

                            // $record['policy_id'] = $policy->id;

                            // $realpayCon = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                            // $cancelContract = $realpayCon->realpayReconsiliationTxLogCheck($record);

                        }
                    // }

                    // $saveData = RealpayTransactionDummyData::where('id',$record->id)->first();
                    // $saveData->is_imported = 3; // cron run for entry
                    // $saveData->save();

                    $deleteEntry = RealpayTransactionDummyData::where('id',$record->id)->delete();
                    sleep(1);
                }
            }
        });

        $cron->end = \Carbon\Carbon::now();
        // $cron->save();
    }
}
