<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\CustomerBanking;
use AlphaDirect\Policy;
use AlphaDirect\PolicyPremiumReratingLog;
use AlphaDirect\RealpayClientContracts;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;

class RealpayControllerNew extends Controller
{
    public function clientAuth(){
        try{
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.base_url')."/oauth/token",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "grant_type=client_credentials",
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Basic ".config('realpay.client_auth'),
                    "Content-Type: application/x-www-form-urlencoded"
                ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            return json_decode($response,true);
        }catch(\Exception $e){
            return response()->json(['Status' => 'Failed','Description'=>$e->getMessage()], 401);
        }
    }

    public function addClient($data){
        try{

            $checkClientExist = $this->checkClientExists($data['clientNumber']);

            if($checkClientExist == true){
                return false;
            }

            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return false;

            if($data['omang'] != null){
                $id = $data['omang'];
                $idType = 'I';
            }else{
                $id = $data['passport'];
                $idType = 'P';
            }

            $clientNumber = $data['clientNumber'];
            $clientName = $data['customerName'];
            $cellphone = $data['cellphone'];
            $email = $data['email'];
            $bankCode = $data['bankCode'];
            $branchCode = $data['branchCode'];
            $accountType = $data['accountType'];
            $accountNumber = $data['accountNumber'];
            $accountHolderName = $data['accountHolderName'];

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.base_url')."/maintain/clients/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS =>"{\r\n  \"ClientPostRequest\": [\r\n    {\r\n      
           \"ClientNumber\": \"$clientNumber\",\r\n       
           \"ClientName\": \"$clientName\",\r\n      
           \"IDType\": \"$idType\",\r\n      
           \"IDNumber\": \"$id\",\r\n      
           \"CellphoneNumber\": \"$cellphone\",\r\n      
           \"EMail\": \"$email\",\r\n      
           \"BankCode\": \"$bankCode\",\r\n      
           \"BranchCode\": \"$branchCode\",\r\n      
           \"AccountType\": \"$accountType\",\r\n      
           \"AccountNumber\": \"$accountNumber\",\r\n
           \"AccountHolderName\": \"$accountHolderName\",\r\n      
           \"EmployeeGroupCode\": \"OT\",\r\n    
           }\r\n  
           ]\r\n
           }",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: ".$token
                ),
            ));

            $response = curl_exec($curl);
            $data = json_decode($response,true);
            curl_close($curl);

            return true;
        }catch(\Exception $ex){
            return false;
        }
    }

    public function addContract($data){
        $fetchToken = $this->clientAuth();

        if($fetchToken['token_type'] && $fetchToken['access_token'])
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return false;
        //return null;

        $policy = Policy::where('id',$data['policy_id'])->first();
        $rerate = PolicyPremiumReratingLog::where('policy_number',$policy->policyNumber)->first();

        $firstCollectionAmount = ($data['first_premium'] != null) ? $data['first_premium'] : $data['premium'];

        $frequency = 'MNTH';

        $billing_day = \Carbon\Carbon::createFromFormat('Y-m-d', $data['billingDay'])->format('d');

        $premium = $data['premium'];

        if($data['frequency'] == 2){
            $numberOfInstallments = '3';
            //$premium = $data['premium'];
        }
        elseif($data['frequency'] == 3){
            $frequency = 'YEAR';
            $numberOfInstallments = '99';
        }
        else{
            $numberOfInstallments = '99';
        }

        if($billing_day == 31 || $billing_day == 30 || $billing_day == 29){
            $billing_day = 99;
        }

        $billingDate = $data['billingDay'];

        $clientNumber = $data['clientNumber'];
        $contractNumber = RealpayClientContracts::getContractNumber($policy->id);

        if(isset($data['first_collection_date']) && $data['first_collection_date'] != null)
            $firstCollectionDate = $data['first_collection_date'];
        else
            $firstCollectionDate = $billingDate;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => config('realpay.base_url')."/maintain/contracts/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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
                      \"ClientNumber\": \"$clientNumber\",\r\n
                      \"ContractNumber\": \"$contractNumber\",\r\n
                      \"FrequencyCode\": \"$frequency\",\r\n
                      \"CollectionDay\": \"$billing_day\",\r\n
                      \"TrackingCode\": \"44\",\r\n
                      \"FirstCollectionDate\": \"$firstCollectionDate\",\r\n
                      \"FirstCollectionAmount\": \"$firstCollectionAmount\",\r\n
                      \"InstalmentStartDate\": \"$billingDate\",\r\n
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
        $resdata = json_decode($response, true);

        curl_close($curl);

        $logData = [
            'policy_id'=>$policy->id,
            'client_number'=>$policy->policyNumber,
            'contract_number'=>$contractNumber,
            'rate_id'=>$data['rate_id'],
            'status'=>1,
        ];

        $rate_id = $data['rate_id'];

        $addLog = RealpayClientContracts::addLog($logData);

        //update customer banking details here
        $banking = CustomerBanking::where('policy_id',$policy->id)->first();
        $banking->bankName = $data['bank'];
        $banking->branchCode = $data['branches'];
        $banking->accountType = $data['AccountType'];
        $banking->billingStartDate = $rerate->new_date;
        $banking->billing_day = $billing_day;
        $banking->billing = "RealPay";
        $banking->accountNumber = $data['AccountNumber'];
        $banking->save();

        $policy->premium_freq = $data['frequency'];
        $policy->first_premium_wvat = $data['first_premium'];
        $policy->premium = $data['premium'];
        $policy->billingStartDate = $billingDate;
        $policy->policyDocument = null;
        $policy->sum_assured = $rerate->sum_assured;
        $policy->save();

        $rerateTable = (new RealpayClientContracts())->getTable();
        $update = \Illuminate\Support\Facades\DB::table($rerateTable)
            ->where('policy_id','=',$policy->id)
            ->where('rate_id','!=',$rate_id)
            ->orWhere('rate_id','=',null)
            ->update(array('status' => 0));

        if(sizeof($data['ContractPostResponse'][0]['Successful'][0]['ContractInstalments']) > 0){
            $contract = $this->storeContractDetails($data['ContractPostResponse'][0]['Successful'][0]);
            $installments = $this->storeInstallments($data['ContractPostResponse'][0]['Successful'][0]);
        }



        return true;
    }

    public function addContractInstalment($data){

    }

    public function updateClient($data){

    }

    public function checkClientExists($clientNumber)
    {
        try{
            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return false;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.base_url').'/maintain/clients/'.config('realpay.product')."?ClientNumber=".$policy->policyNumber."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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
            $data = json_decode($response,true);

            if(empty($data['ClientGetResponse'])){
                return false;
            }else{
                return true;
            }
        }catch(\Exception $ex){
            return false;
        }
    }

    public function processRealpayPayment($id){
        try{
            
        }catch(\Exception $ex){

        }
    }




}
