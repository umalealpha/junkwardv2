<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\ExpiredRealpayPoliciesDummy;
use AlphaDirect\Policy;
use AlphaDirect\RealpayCancelRequests;
use AlphaDirect\RealpayClientContracts;
use Illuminate\Console\Command;
use AlphaDirect\RealpayContractDetails;
use AlphaDirect\RealpayPaymentRequest;
use DateTime;
use AlphaDirect\Models\CronStatus;
use Illuminate\Http\Request;

class CancelExpiredPoliciesContract extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'CancelExpiredPoliciesContract:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This cron is used for cancelling expired policies contract';

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
        $cron->name = "CancelExpiredPoliciesContract:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $policy_data = ExpiredRealpayPoliciesDummy::get();

        foreach ($policy_data as $key => $policy) {
            $this->info("Policy Number: ". $policy->policyNumber);
            $clientContracts = RealpayClientContracts::where('client_number',$policy->policyNumber)->orderBy('id','desc')->skip(1)->take(1)->first();

            if (!isset($clientContracts)) {
                $clientContracts = RealpayContractDetails::where('ClientNumber',$policy->policyNumber)->orderBy('id','desc')->skip(1)->take(1)->first();
                if (isset($clientContracts)) {
                    $clientContracts->contract_number = $clientContracts->ContractNumber;
                }
            }

            $request = new Request();
            $request['clientNumber'] = isset($policy->policyNumber) ? $policy->policyNumber : null;

            $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
            $getcontract = $realpay->getContractInfoForMotorComp($request);

            if (!isset($getcontract)) {
                if($clientContracts != null){
                    $now = new DateTime();
                    $now->format('Y-m-d');
                    $Token = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                    $fetchToken = $Token->clientAuth();
                    if($fetchToken['token_type'] && $fetchToken['access_token'])
                        $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
                    else
                        $token = null;

                    $successResult = 0;
                    $contractFailed = array();

                    if (isset($clientContracts)) {
                        // foreach ($clientContracts as $key => $contract) {

                            $curl = curl_init();

                            curl_setopt_array($curl, array(
                                CURLOPT_URL => config('realpay.base_url')."/maintain/contracts/".config('realpay.product')."?ClientNumber=".$policy->policyNumber."&ContractNumber=".$clientContracts->contract_number."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_ENCODING => "",
                                CURLOPT_MAXREDIRS => 10,
                                CURLOPT_TIMEOUT => 0,
                                CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_CUSTOMREQUEST => "DELETE",
                                CURLOPT_POSTFIELDS =>"{\r\n  \"ClientPutRequest\": [\r\n    {\r\n      \"ClientNumber\": \"L00012\",\r\n      \"ContractNumber\": \"C1603\",\r\n      \"FrequencyCode\": \"MNTH\",\r\n      \"CollectionDay\": 25,\r\n      \"TrackingCode\": \"03\",\r\n      \"FirstCollectionDate\": \"YYYY-MM-DD HH24:MI\",\r\n      \"FirstCollectionAmount\": 123.45,\r\n      \"InstalmentStartDate\": \"YYYY-MM-DD HH24:MI\",\r\n      \"InstalmentAmount\": 123.45,\r\n      \"NumberOfInstalments\": 1,\r\n      \"CTCPercentage\": 1\r\n    }\r\n  ]\r\n}",
                                CURLOPT_HTTPHEADER => array(
                                    "Content-Type: application/json",
                                    "Accept: application/json",
                                    "Authorization: ".$token
                                ),
                            ));

                            $response = curl_exec($curl);
                            $data = json_decode($response, true);
                            $success = sizeof($data['ContractDeleteResponse'][0]['Successful']) > 0;
                            curl_close($curl);

                            if($success == true){
                                $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                                $update = $realpay->actionAfterCancellingContract($clientContracts->contract_number);

                                $policy_data = Policy::where('policyNumber',$policy->policyNumber)->first();

                                $can = RealpayCancelRequests::where('policy_id',$policy_data->id)->where('contract',$clientContracts->contract_number)->first();
                                if (!isset($can)) {
                                    $can = new RealpayCancelRequests();
                                    $can->policy_id = $policy_data->id;
                                    $can->leftout_premium_contract = null;
                                    $can->contract = $clientContracts->contract_number;
                                    $can->cancel_status = 0;
                                    $can->save();
                                }
                            }
                            else{
                                $successResult ++;
                            }

                        // }
                    }
                }
            } else {
                if($clientContracts != null){
                    $now = new DateTime();
                    $now->format('Y-m-d');
                    $Token = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                    $fetchToken = $Token->clientAuthForMotorComp();
                    if($fetchToken['token_type'] && $fetchToken['access_token'])
                        $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
                    else
                        $token = null;

                    $successResult = 0;
                    $contractFailed = array();

                    if (isset($clientContracts)) {
                        // foreach ($clientContracts as $key => $contract) {

                            $curl = curl_init();

                            curl_setopt_array($curl, array(
                                CURLOPT_URL => config('realpay.start.base_url')."/maintain/contracts/".config('realpay.start.product')."?ClientNumber=".$policy->policyNumber."&ContractNumber=".$clientContracts->contract_number."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version'),
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_ENCODING => "",
                                CURLOPT_MAXREDIRS => 10,
                                CURLOPT_TIMEOUT => 0,
                                CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_CUSTOMREQUEST => "DELETE",
                                CURLOPT_POSTFIELDS =>"{\r\n  \"ClientPutRequest\": [\r\n    {\r\n      \"ClientNumber\": \"L00012\",\r\n      \"ContractNumber\": \"C1603\",\r\n      \"FrequencyCode\": \"MNTH\",\r\n      \"CollectionDay\": 25,\r\n      \"TrackingCode\": \"03\",\r\n      \"FirstCollectionDate\": \"YYYY-MM-DD HH24:MI\",\r\n      \"FirstCollectionAmount\": 123.45,\r\n      \"InstalmentStartDate\": \"YYYY-MM-DD HH24:MI\",\r\n      \"InstalmentAmount\": 123.45,\r\n      \"NumberOfInstalments\": 1,\r\n      \"CTCPercentage\": 1\r\n    }\r\n  ]\r\n}",
                                CURLOPT_HTTPHEADER => array(
                                    "Content-Type: application/json",
                                    "Accept: application/json",
                                    "Authorization: ".$token
                                ),
                            ));

                            $response = curl_exec($curl);
                            $data = json_decode($response, true);
                            $success = sizeof($data['ContractDeleteResponse'][0]['Successful']) > 0;
                            curl_close($curl);

                            if($success == true){
                                $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                                $update = $realpay->actionAfterCancellingContract($clientContracts->contract_number);

                                $policy_data = Policy::where('policyNumber',$policy->policyNumber)->first();

                                $can = RealpayCancelRequests::where('policy_id',$policy_data->id)->where('contract',$clientContracts->contract_number)->first();
                                if (!isset($can)) {
                                    $can = new RealpayCancelRequests();
                                    $can->policy_id = $policy_data->id;
                                    $can->leftout_premium_contract = null;
                                    $can->contract = $clientContracts->contract_number;
                                    $can->cancel_status = 0;
                                    $can->save();
                                }
                            }
                            else{
                                $successResult ++;
                            }

                        // }
                    }
                }
            }

            sleep(1);
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
