<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\Admin\RealPayController;
use AlphaDirect\Policy;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\RealpayWebHookResponses;
use Illuminate\Support\Str;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Models\RealpayTransactionsExcelData;
use AlphaDirect\PaymentTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Log;

class FetchDomgComgPoliciesInstlStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetchDomgComgPoliciesInstlStatus:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This cron is used for adding transaction data into payment transactions from realpay webhook response table for DOMG/COMG policies';

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
        $cron->name = "fetchDomgComgPoliciesInstlStatus:cron";
        $cron->start = \Carbon\Carbon::now();
        // $cron->save();

        Log::info('Cron started for importing realpay transactions in database from uploaded excel for dom com policies');

        $records = RealpayTransactionsExcelData::where('status','!=',3)->where('domgcomg','DOMG2024125272')->get();

        if (isset($records)) {
            foreach ($records as $key => $record) {

                $policyNumber = $record->domgcomg;
                $clientNumber = $record->clientnumber;

                $realpayWebhookData = RealpayWebHookResponses::where('policyNumber',$clientNumber)->where('status','!=','Active')->get();

                $policy = Policy::where('policyNumber',$policyNumber)->first();

                $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                if (isset($policy)) {
                    $getcontract = null;
                    $request = new Request();
                    $request['clientNumber'] = isset($clientNumber) ? $clientNumber : null;
                    $request['policy_number'] = isset($policyNumber) ? $policyNumber : null;

                    // $getcontract = $realpay->getContractInfo($request);
                    // if (!isset($getcontract->getData()->Status) || $getcontract->getData()->Status !== 'Success') {
                    //     $getcontract = $realpay->getContractInfoForInstantProduct($request);
                    // }

                    $realpayController = new RealPayController();
                    $getcontract = $realpayController->getInstallmentsFromContractSequence($record);
                    // if (isset($getcontract) && $getcontract->getData()->Status == 'Success') {
                    //     $contractData = $getcontract->getData()->contracts;
                    //     if (isset($contractData) && $contractData > 0) {
                    //         foreach ($contractData as $key => $con_contract) {
                    //             $contract = (array) $con_contract;
                                foreach ($getcontract['InstalmentGetResponse'] as $key => $con_instalment) {
                                    $instalment = (array) $con_instalment;
                                    if ($instalment['InstalmentStatus'] == "S" || $instalment['InstalmentStatus'] == "F" || $instalment['InstalmentStatus'] == "I") {
                                        $status = $instalment['InstalmentStatus'];
                                        $paid = 0;
                                        if ($instalment['InstalmentStatus'] == 'S') {
                                            $status = 'SUCCESS';
                                            $paid = 1;
                                        } elseif ($instalment['InstalmentStatus'] == 'F') {
                                            $status = 'FAILED';
                                            $paid = 0;
                                        } elseif ($instalment['InstalmentStatus'] == 'I') {
                                            $status = 'CANCELLED';
                                            $paid = 0;
                                        }

                                        $paymentData['policyNumber'] = $policyNumber;
                                        $paymentData['policy_id'] = $policy->id;
                                        $paymentData['referenceNumber'] = $instalment['InstalmentReferenceNumber'];
                                        $paymentData['amount'] = $instalment['InstalmentAmount'];
                                        $paymentData['status'] = $status;
                                        $paymentData['paymentDate'] = \Carbon\Carbon::parse($instalment['InstalmentActionDate'])->format('Y-m-d');
                                        $paymentData['paymentMethod'] = 'RealPay';
                                        $paymentData['numberOfInstalmentsPaid'] = $paid;
                                        $paymentData['note'] = 'TRANSACTION ' . $status;

                                        $policyController = new PolicyController();
                                        $saveData = $policyController->updatePaymentTransactions($paymentData);

                                        $instPaymentDate = \Carbon\Carbon::parse($instalment['InstalmentActionDate'])->format('Y-m-d');
                                        $realpayConInstallment = RealpayContractInstallments::where('clientNumber',$clientNumber)->where('InstalmentSequence',$instalment['InstalmentSequence'])->first();
                                        if (isset($realpayConInstallment)) {
                                            $realpayConInstallment->InstalmentStatus = $instalment['InstalmentStatus'];
                                            $realpayConInstallment->save();
                                        } else {
                                            RealpayTransactionsExcelData::where('id', $record->id)->update(['status' => 4]);
                                        }
                                    }
                                }
                    //         }
                    //     }
                    // }
                }

                $update_policy_status = Policy::where('policyNumber',$policyNumber)->first();
                if (isset($update_policy_status) && $update_policy_status->status == 0) {
                    $getTranx = PaymentTransaction::where('policyNumber', $policyNumber)->where('status','SUCCESS')->get();
                    if (isset($getTranx)) {
                        if($policy->product_id!=3 && $policy->product_id!=5){
                        $update_policy_status->status = 1;
                        }else if($policy->product_id==3 || $policy->product_id==5){
                            $chk=PolicyController::checkMotorpolicyStatus($policy->product_id,$policy->id,2);
                        $update_policy_status->status = $chk;
                        }
                        $update_policy_status->policyActivatedDate = Carbon::now();
                        $update_policy_status->expiry_date = Carbon::now()->addYear()->format('Y-m-d');
                        $update_policy_status->save();

                        activity('Policy Status')
                        ->performedOn($update_policy_status)
                        ->log('Policy activated');
                    }
                }

                RealpayTransactionsExcelData::where('id', $record->id)->update(['status' => 3]);
            }
        }

        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
