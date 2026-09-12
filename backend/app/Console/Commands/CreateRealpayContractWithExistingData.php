<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\CustomerBanking;
use AlphaDirect\ExpiredRealpayPoliciesDummy;
use AlphaDirect\Policy;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayPaymentRequest;
use Carbon\Carbon;
use DB;
use AlphaDirect\RealpayContractDetails;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\Models\CronStatus;

class CreateRealpayContractWithExistingData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'createRealpayContractWithExistingData:cron {policyNumber}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Realpay Contract With Existing Client Data';

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
        $cron->name = "createContractForInstantWithExistingData:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();

        $policyNumber = $this->argument('policyNumber');

        if (isset($policyNumber)) {
            $this->info("Policy Number: ". $policyNumber);
            $policy = Policy::where('policyNumber',$policyNumber)->first();
            if (isset($policy)) {
                if ($policy->product_id == 3) {
                    $getClient = $realpay->retrieveClient($policy->policyNumber);
                    if (!isset($getClient)) {
                        $getClient = $realpay->retrieveClientForInstantProduct($policy->policyNumber);
                    }
                } else {
                    $getClient = $realpay->retrieveClientForInstantProduct($policy->policyNumber);
                }

                if (isset($getClient)) {
                    $updateClient = RealpayPaymentRequest::where('policy_id', $policy->id)->first();
                    if (isset($updateClient)) {
                        $updateClient->clientNumber = $policy->policyNumber;
                        $updateClient->response = 1;
                        $updateClient->clientCreated = 1;
                        $updateClient->status = 1;
                        $updateClient->save();
                    }

                    $req_billing_day = Carbon::now()->format('d');
                    $req_billingDay = "2023-01-01";

                    $contractNo = null;
                    $reaClientContract = RealpayClientContracts::where('policy_id',$policy->id)->orderBy('id','desc')->first();
                    if (isset($reaClientContract)) {
                        $contractNo = $reaClientContract->contract_number;
                    } else {
                        $contractDetails = RealpayContractDetails::where('ClientNumber',$policy->policyNumber)->orderBy('id','desc')->first();
                        if (isset($contractDetails)) {
                            $contractNo = $contractDetails->ContractNumber;
                        }
                    }

                    $ins = [
                        'clientNumber' => isset($updateClient->clientNumber) ? $updateClient->clientNumber : $policy->policyNumber,
                        'contractNumber' => isset($updateClient->contract) ? $updateClient->contract : $contractNo,
                    ];

                    $today = Carbon::now();
                    $inslt_premium = $policy->premium;

                    $getInstallmentDb = RealpayContractInstallments::where('clientNumber',$ins['clientNumber'])->where('contractNumber',$ins['contractNumber'])->where('InstalmentActionDate','>=',$today)->first();
                    if (isset($getInstallmentDb)) {
                        $req_billingDay = Carbon::parse($getInstallmentDb->InstalmentActionDate)->format('Y-m-d');
                        $inslt_premium = $getInstallmentDb->InstalmentAmount;
                    } else {
                        if ($policy->product_id == 3) {
                            $getInstallment = $realpay->getRealpayInstallments($ins);
                            if (!isset($getInstallment['InstalmentGetResponse'])) {
                                $getClient = $realpay->retrieveClientForInstantProduct($policy->policyNumber);
                            }
                        } else {
                            $getInstallment = $realpay->getRealpayInstallmentsForInstantProduct($ins);
                        }

                        if (isset($getInstallment['InstalmentGetResponse'])) {
                            foreach ($getInstallment['InstalmentGetResponse'] as $key => $installment) {
                                if ($installment['InstalmentActionDate'] >= $today && $installment['InstalmentStatus'] == 'I') {
                                    $req_billingDay = Carbon::parse($installment['InstalmentActionDate'])->format('Y-m-d');
                                    $inslt_premium = $installment['InstalmentAmount'];
                                    break;
                                }
                            }
                        }
                    }

                    $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

                    if (isset($customerBanking)) {
                        $customerBanking->bankName = $getClient[0]['BankCode'];
                        $customerBanking->branchCode = $getClient[0]['BranchCode'];
                        $customerBanking->accountType = $getClient[0]['AccountType'];
                        $customerBanking->accountNumber = $getClient[0]['AccountNumber'];
                        $customerBanking->billing = "RealPay";
                        $customerBanking->save();
                    }

                    if ($policy->product_id == 3) {
                        $fetchToken = $realpay->clientAuthForMotorComp();
                        if($fetchToken['token_type'] && $fetchToken['access_token'])
                            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
                        else
                            return null;

                        $firstCollectionAmount = isset($inslt_premium) ? $inslt_premium : $policy->premium;
                        $numberOfInstallments = '12';
                        $frequency = 'MNTH';
                        $premium = isset($inslt_premium) ? $inslt_premium : $policy->premium;

                        if($policy->premium_freq != null){

                            if($policy->premium_freq == 2){
                                $numberOfInstallments = '3';
                            }
                            elseif($policy->premium_freq == 3){
                                $frequency = 'YEAR';
                                $numberOfInstallments = '1';
                            }
                            elseif($policy->premium_freq == 1){
                                $numberOfInstallments = '12';
                            }
                        }

                        $billing_day = '';
                        if ($req_billingDay != NULL) {
                            $billing_day = \Carbon\Carbon::createFromFormat('Y-m-d', $req_billingDay)->format('d');
                        }

                        if($billing_day == 31 || $billing_day == 30 || $billing_day == 29){
                            $billing_day = 99;
                        }

                        $firstcollDate = $req_billingDay;
                        $contractNumber = RealpayClientContracts::getContractNumber($policy->id);

                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                            CURLOPT_URL => config('realpay.start.base_url')."/maintain/contracts/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version'),
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => "",
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => "POST",
                            CURLOPT_POSTFIELDS =>"{\r\n
                        \"ContractPostRequest\": [\r\n
                            {\r\n
                                    \"ClientNumber\": \"$policy->policyNumber\",\r\n
                                    \"ContractNumber\": \"$contractNumber\",\r\n
                                    \"FrequencyCode\": \"$frequency\",\r\n
                                    \"CollectionDay\": \"$billing_day\",\r\n
                                    \"TrackingCode\": \"44\",\r\n
                                    \"FirstCollectionDate\": \"$firstcollDate\",\r\n
                                    \"FirstCollectionAmount\": \"$firstCollectionAmount\",\r\n
                                    \"InstalmentStartDate\": \"$req_billingDay\",\r\n
                                    \"InstalmentAmount\": $premium,\r\n
                                    \"NumberOfInstalments\": \"$numberOfInstallments\",\r\n
                                    \"CTCPercentage\": 1\r\n
                                    }\r\n
                                ]\r\n}",
                            CURLOPT_HTTPHEADER => array(
                                "Content-Type: application/json",
                                "Accept: application/json",
                                "Authorization: " . $token
                            ),
                        ));
                        $response = curl_exec($curl);
                        $data = json_decode($response, true);

                        curl_close($curl);
                    } else {
                        $fetchToken = $realpay->clientAuthForInstantProduct();
                        if($fetchToken['token_type'] && $fetchToken['access_token'])
                            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
                        else
                            return null;

                        $firstCollectionAmount = isset($inslt_premium) ? $inslt_premium : $policy->premium;
                        $numberOfInstallments = '99';
                        $frequency = 'MNTH';
                        $premium = isset($inslt_premium) ? $inslt_premium : $policy->premium;

                        $billing_day = '';
                        if ($req_billingDay != NULL) {
                            $billing_day = \Carbon\Carbon::createFromFormat('Y-m-d', $req_billingDay)->format('d');
                        }

                        if($billing_day == 31 || $billing_day == 30 || $billing_day == 29){
                            $billing_day = 99;
                        }

                        $firstcollDate = $req_billingDay;
                        $contractNumber = RealpayClientContracts::getContractNumber($policy->id);

                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                            CURLOPT_URL => config('realpay.start.base_url')."/maintain/contracts/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version'),
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => "",
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => "POST",
                            CURLOPT_POSTFIELDS =>"{\r\n
                        \"ContractPostRequest\": [\r\n
                            {\r\n
                                    \"ClientNumber\": \"$policy->policyNumber\",\r\n
                                    \"ContractNumber\": \"$contractNumber\",\r\n
                                    \"FrequencyCode\": \"$frequency\",\r\n
                                    \"CollectionDay\": \"$billing_day\",\r\n
                                    \"TrackingCode\": \"44\",\r\n
                                    \"FirstCollectionDate\": \"$firstcollDate\",\r\n
                                    \"FirstCollectionAmount\": \"$firstCollectionAmount\",\r\n
                                    \"InstalmentStartDate\": \"$req_billingDay\",\r\n
                                    \"InstalmentAmount\": $premium,\r\n
                                    \"NumberOfInstalments\": \"$numberOfInstallments\",\r\n
                                    \"CTCPercentage\": 1\r\n
                                    }\r\n
                                ]\r\n}",
                            CURLOPT_HTTPHEADER => array(
                                "Content-Type: application/json",
                                "Accept: application/json",
                                "Authorization: " . $token
                            ),
                        ));
                        $response = curl_exec($curl);
                        $data = json_decode($response, true);

                        curl_close($curl);
                    }

                    $update = RealpayPaymentRequest::where('policy_id', $policy->id)->first();
                    $installmentStatus = null;

                    if (sizeof($data['ContractPostResponse'][0]['Successful']) > 0  && isset($data['ContractPostResponse'][0]['Successful'][0]['ContractInstalments'])) {
                        if ($data['ContractPostResponse'][0]['Successful'][0]['ContractInstalments'][0]['InstalmentStatus'] == 'S') {
                            $installmentStatus =  'Successfull';
                        }
                    }
                    if (sizeof($data['ContractPostResponse'][0]['Successful']) > 0 && sizeof($data['ContractPostResponse'][0]['Failed']) == 0) {
                        if (isset($update)) {
                            $update->contract = $contractNumber;
                            $update->contract_response_sequence = $data['APIResponse']['CallSequence'];
                            $update->status = 1;
                            $update->contractCreated = 1;
                            $update->save();
                        }
                        if(sizeof($data['ContractPostResponse'][0]['Successful'][0]['ContractInstalments']) > 0){
                            $contract = $realpay->storeContractDetails($data['ContractPostResponse'][0]['Successful'][0]);
                            $installments = $realpay->storeInstallments($data['ContractPostResponse'][0]['Successful'][0]);
                        }

                        $logData = [
                            'policy_id'=>$policy->id,
                            'client_number'=>$policy->policyNumber,
                            'contract_number'=>$contractNumber,
                            //'rate_id'=>$data['rate_id'],
                            'status'=>1,
                        ];

                        $addLog = RealpayClientContracts::addLog($logData);

                        if($contractNumber != null) {
                            $Table = (new RealpayClientContracts())->getTable();
                            DB::table($Table)->where('client_number', $policy->policyNumber)
                                ->where('contract_number', '!=',$contractNumber)
                                ->update(array('status' => 0));
                        }

                    } else {

                        if($data['ContractPostResponse'][0]['Failed'][0]['Failures'][0]['FailureCode'] == 'TAK1'){
                            $logData = [
                                'policy_id'=>$policy->id,
                                'client_number'=>$policy->policyNumber,
                                'contract_number'=>$contractNumber,
                                'status'=>1,
                            ];

                            $addLog = RealpayClientContracts::addLog($logData);

                            if($contractNumber != null) {
                                $Table = (new RealpayClientContracts())->getTable();
                                DB::table($Table)->where('client_number', $policy->policyNumber)
                                    ->where('contract_number', '!=',$contractNumber)
                                    ->update(array('status' => 0));
                            }
                        }
                    }
                }
            }

            sleep(1);

        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
