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

class AddRealpayWebhookInTransactionLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'AddRealpayWebhookInTransactionLog:cron';

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
        $cron->save();
        $policyNumber = "MIS2022034798";
        $clientNumber = "MIS2022034798";

        $realpayWebhook = RealpayWebHookResponses::where('policyNumber',$clientNumber)->where('status','!=','Active')->get();

        if (isset($realpayWebhook) && $realpayWebhook->isNotEmpty() ) {

            $realpayCont = new RealPayController();
            $fetchToken = $realpayCont->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/clients/" . env('REALPAY_PRODUCT') . "?ClientNumber=" . $clientNumber . "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: ".$token
                ),
            ));

            $response = curl_exec($curl);
            $clientInfo = json_decode($response,true);
            curl_close($curl);
            #dd($clientData);
            if (isset($clientInfo['ClientGetResponse']) && $clientInfo['ClientGetResponse'] != NULL) {
                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/contracts/" . env('REALPAY_PRODUCT') . "?ClientNumber=" . $clientNumber . "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "GET",
                    CURLOPT_HTTPHEADER => array(
                        "Content-Type: application/json",
                        "Accept: application/json",
                        "Authorization: ".$token
                    ),
                ));

                $response = curl_exec($curl);
                $contractInfo = json_decode($response,true);
                curl_close($curl);


                // if (isset($contractInfo['ContractGetResponse']) && $contractInfo['ContractGetResponse'] > 0) {
                //     foreach ($contractInfo['ContractGetResponse'] as $key => $contract) {
                //         foreach ($contract['ContractInstalments'] as $key => $instalments) {
                //             $realpayInstallment = RealpayWebHookResponses::where('instalmentRefNumber',$instalments['InstalmentReferenceNumber'])->first();
                //             if (isset($realpayInstallment)) {
                //                 $realpayInstallment->installmentAmount = $instalments['InstalmentAmount'];
                //                 $realpayInstallment->save();
                //             }
                //         }
                //     }
                // }
            }

            $realpayConInstallment = RealpayContractInstallments::where('clientNumber',$clientNumber)->get();
            if (isset($realpayConInstallment)) {
                foreach ($realpayConInstallment as $key => $instalmentCon) {
                    $instalmentCon->InstalmentStatus = "A";
                    $instalmentCon->save();
                }
            }

            $realpayWebhookData = RealpayWebHookResponses::where('policyNumber',$clientNumber)->where('status','!=','Active')->get();

            if (isset($realpayWebhookData) && $realpayWebhookData->isNotEmpty() ) {

                foreach ($realpayWebhookData as $key => $webhook) {

                    $realpayInstallment = RealpayContractInstallments::where('clientNumber',$clientNumber)->where('InstalmentReferenceNumber',$webhook->instalmentRefNumber)->get();
                    // dd($webhook,$realpayInstallment);
                    if (isset($realpayInstallment)) {
                        foreach ($realpayInstallment as $key => $instalment) {
                            $instalment->InstalmentStatus = $webhook->status;
                            $instalment->save();
                        }
                    }

                    $webhook_status = Str::contains($webhook->status, ['SUCCESS', 'FAILED', 'CANCELLED']);
                    // dd($webhook_status,$webhook->status);
                    if ($webhook_status == true || $webhook->status == 'S' || $webhook->status == 'F' || $webhook->status == 'I') {
                        $status = $webhook->status;
                        $paid = 0;
                        if ($webhook->status == 'SUCCESS' || $webhook->status == 'S') {
                            $status = 'SUCCESS';
                            $paid = 1;
                        } elseif ($webhook->status == 'FAILED' || $webhook->status == 'F') {
                            $status = 'FAILED';
                            $paid = 0;
                        } elseif ($webhook->status == 'CANCELLED' || $webhook->status == 'I') {
                            $status = 'CANCELLED';
                            $paid = 0;
                        }

                        if (isset($webhook->installmentAmount)) {
                            $paymentData['policyNumber'] = $policyNumber;
                            $paymentData['referenceNumber'] = $webhook->instalmentRefNumber;
                            $paymentData['amount'] = $webhook->installmentAmount;
                            $paymentData['status'] = $status;
                            $paymentData['paymentDate'] = \Carbon\Carbon::parse($webhook->instalmentActionDate)->format('Y-m-d');
                            $paymentData['paymentMethod'] = 'RealPay';
                            $paymentData['numberOfInstalmentsPaid'] = $paid;
                            $paymentData['note'] = 'TRANSACTION ' . $status;

                            // $policyController = new PolicyController();
                            // $saveData = $policyController->updatePaymentTransactions($paymentData);
                        }
                    }
                }
            }
        } else {
            $policy = Policy::where('policyNumber',$policyNumber)->first();

            if (isset($policy)) {
                    $realpayController = new RealPayController();
                    $fetchToken = $realpayController->clientAuth();
                    if($fetchToken['token_type'] && $fetchToken['access_token'])
                        $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
                    else
                        return null;

                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/clients/" . env('REALPAY_PRODUCT') . "?ClientNumber=" . $clientNumber . "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "GET",
                        CURLOPT_HTTPHEADER => array(
                            "Content-Type: application/json",
                            "Accept: application/json",
                            "Authorization: ".$token
                        ),
                    ));

                    $response = curl_exec($curl);
                    $clientData = json_decode($response,true);
                    curl_close($curl);
                    #dd($clientData);
                    if (isset($clientData['ClientGetResponse']) && $clientData['ClientGetResponse'] != NULL) {
                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                            CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/contracts/" . env('REALPAY_PRODUCT') . "?ClientNumber=" . $clientNumber . "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => "",
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => "GET",
                            CURLOPT_HTTPHEADER => array(
                                "Content-Type: application/json",
                                "Accept: application/json",
                                "Authorization: ".$token
                            ),
                        ));

                        $response = curl_exec($curl);
                        $contractData = json_decode($response,true);
                        curl_close($curl);
                         #dd($contractData['ContractGetResponse']);

                        if (isset($contractData['ContractGetResponse']) && $contractData['ContractGetResponse'] > 0) {
                            foreach ($contractData['ContractGetResponse'] as $key => $contract) {
                                foreach ($contract['ContractInstalments'] as $key => $instalment) {
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
                                        $paymentData['referenceNumber'] = $instalment['InstalmentReferenceNumber'];
                                        $paymentData['amount'] = $instalment['InstalmentAmount'];
                                        $paymentData['status'] = $status;
                                        $paymentData['paymentDate'] = \Carbon\Carbon::parse($instalment['InstalmentActionDate'])->format('Y-m-d');
                                        $paymentData['paymentMethod'] = 'RealPay';
                                        $paymentData['numberOfInstalmentsPaid'] = $paid;
                                        $paymentData['note'] = 'TRANSACTION ' . $status;

                                        $policyController = new PolicyController();
                                        $saveData = $policyController->updatePaymentTransactions($paymentData);
                                    }
                                }
                            }
                        }
                    }
            }
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
