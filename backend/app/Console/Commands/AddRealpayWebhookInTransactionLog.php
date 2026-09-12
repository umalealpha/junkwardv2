<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\Admin\RealPayController;
use AlphaDirect\Policy;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\RealpayWebHookResponses;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\PaymentTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AddRealpayWebhookInTransactionLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'AddRealpayWebhookInTransactionLog:cron {policyNumber} {clientNumber}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This cron is used for adding transaction data into payment transactions from realpay webhook response table';

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
        $cron->name = "AddRealpayWebhookInTransactionLog:cron";
        $cron->start = \Carbon\Carbon::now();
        // $cron->save();

        $policyNumber = $this->argument('policyNumber');
        $clientNumber = $this->argument('clientNumber');
        // $policyNumber = $data['policyNumber'];
        // $clientNumber = $data['clientNumber'];

        $realpayWebhookData = RealpayWebHookResponses::where('policyNumber',$clientNumber)->where('status','!=','Active')->get();

        // if (isset($realpayWebhookData) && $realpayWebhookData->isNotEmpty() ) {

        //     foreach ($realpayWebhookData as $key => $webhook) {

        //         $realpayInstallment = RealpayContractInstallments::where('clientNumber',$clientNumber)->where('InstalmentReferenceNumber',$webhook->instalmentRefNumber)->first();
        //         // dd($webhook,$realpayInstallment);
        //         if (isset($realpayInstallment)) {
        //             switch ($webhook->status){
        //                 case 'SUCCESS':
        //                     $realpayInstallment->InstalmentStatus = 'S';
        //                     break;
        //                 case 'PROCESSING':
        //                     $realpayInstallment->InstalmentStatus = 'W';
        //                     break;
        //                 case 'FAILED':
        //                     $realpayInstallment->InstalmentStatus = 'F';
        //                     break;
        //                 case 'RETRY':
        //                     $realpayInstallment->InstalmentStatus = 'R';
        //                     break;
        //                 case 'ACTIVE':
        //                     $realpayInstallment->InstalmentStatus = 'A';
        //                     break;
        //                 case 'CANCELLED':
        //                     $realpayInstallment->InstalmentStatus = 'I';
        //                     break;
        //                 case 'ERROR':
        //                     $realpayInstallment->InstalmentStatus = 'E';
        //                     break;
        //                 default :
        //                     $realpayInstallment->InstalmentStatus = $webhook->status;
        //                     break;
        //             }

        //             // $realpayInstallment->InstalmentStatus = $webhook->status;
        //             $realpayInstallment->save();
        //         }

        //         $webhook_status = Str::contains($webhook->status, ['SUCCESS', 'FAILED', 'CANCELLED']);
        //         // dd($webhook_status,$webhook->status);
        //         if ($webhook_status == true || $webhook->status == 'S' || $webhook->status == 'F' || $webhook->status == 'I') {
        //             $status = $webhook->status;
        //             $paid = 0;
        //             if ($webhook->status == 'SUCCESS' || $webhook->status == 'S') {
        //                 $status = 'SUCCESS';
        //                 $paid = 1;
        //             } elseif ($webhook->status == 'FAILED' || $webhook->status == 'F') {
        //                 $status = 'FAILED';
        //                 $paid = 0;
        //             } elseif ($webhook->status == 'CANCELLED' || $webhook->status == 'I') {
        //                 $status = 'CANCELLED';
        //                 $paid = 0;
        //             }

        //             $policy_data = Policy::where('policyNumber',$policyNumber)->first();

        //             if (isset($webhook->installmentAmount)) {
        //                 $paymentData['policyNumber'] = $policyNumber;
        //                 $paymentData['policy_id'] = $policy_data->id;
        //                 $paymentData['referenceNumber'] = $webhook->instalmentRefNumber;
        //                 $paymentData['amount'] = $webhook->installmentAmount;
        //                 $paymentData['status'] = $status;
        //                 $paymentData['paymentDate'] = \Carbon\Carbon::parse($webhook->instalmentActionDate)->format('Y-m-d');
        //                 $paymentData['paymentMethod'] = 'RealPay';
        //                 $paymentData['numberOfInstalmentsPaid'] = $paid;
        //                 $paymentData['note'] = 'TRANSACTION ' . $status;

        //                 $policyController = new PolicyController();
        //                 $saveData = $policyController->updatePaymentTransactions($paymentData);
        //             }
        //         }
        //     }
        // } else {
            $policy = Policy::where('policyNumber',$policyNumber)->first();

            $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
            if (isset($policy)) {
                $getcontract = null;
                $request = new Request();
                $request['clientNumber'] = isset($clientNumber) ? $clientNumber : null;
                $request['policy_number'] = isset($policyNumber) ? $policyNumber : null;

                $getcontract = $realpay->getContractInfo($request);
                if (!isset($getcontract->getData()->Status) || $getcontract->getData()->Status !== 'Success') {
                    $getcontract = $realpay->getContractInfoForInstantProduct($request);
                }

                // if ($policy->product_id == 3) {
                //     $getcontract = $realpay->getContractInfo($request);
                // } else {
                //     $getcontract = $realpay->getContractInfoForInstantProduct($request);
                // }

                if (isset($getcontract) && $getcontract->getData()->Status == 'Success') {
                    $contractData = $getcontract->getData()->contracts;
                    if (isset($contractData) && $contractData > 0) {
                        foreach ($contractData as $key => $con_contract) {
                            $contract = (array) $con_contract;
                            foreach ($contract['ContractInstalments'] as $key => $con_instalment) {
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
                                    $realpayConInstallment = RealpayContractInstallments::where('clientNumber',$clientNumber)->where('contractNumber',$contract['ContractNumber'])->where('InstalmentSequence',$instalment['InstalmentSequence'])->first();
                                    if (isset($realpayConInstallment)) {
                                        $previousStatus = $realpayConInstallment->InstalmentStatus;
                                        $realpayConInstallment->InstalmentStatus = $instalment['InstalmentStatus'];
                                        $realpayConInstallment->save();

                                        // Audit trail — only log on real status transitions so re-runs
                                        // of the cron don't spam the activity log. Causer is null (cron,
                                        // no auth context).
                                        if ($previousStatus !== $instalment['InstalmentStatus']) {
                                            try {
                                                activity('RealPay installment ' . $status)
                                                    ->performedOn($policy)
                                                    ->log('RealPay installment ' . $status
                                                        . ': ContractNumber - ' . ($contract['ContractNumber'] ?? '?')
                                                        . ', InstalmentSequence - ' . ($instalment['InstalmentSequence'] ?? '?')
                                                        . ', Reference - ' . ($instalment['InstalmentReferenceNumber'] ?? '?')
                                                        . ', Amount - P ' . number_format((float) ($instalment['InstalmentAmount'] ?? 0), 2, '.', '')
                                                        . ', Action Date - ' . $instPaymentDate);
                                            } catch (\Throwable $e) {
                                                \Illuminate\Support\Facades\Log::warning('realpay installment activity log failed: ' . $e->getMessage());
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        // }

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

        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
