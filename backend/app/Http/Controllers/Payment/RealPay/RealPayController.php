<?php

namespace AlphaDirect\Http\Controllers\Payment\RealPay;

use AlphaDirect\CustomerBanking;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Controllers\frontendPay\ClaimController;
use AlphaDirect\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use AlphaDirect\Customer;
use AlphaDirect\Banks;
use AlphaDirect\BankBranches;
use Redirect;

class RealPayController extends Controller
{
    public function addClientRealPay($policy, $premium)
    {


        $threeMonthsLater = Carbon::now()->addDay(90)->toDateString();


        $customer = Customer::where('id', $policy->customer_id)->with('profile', 'banking')->first();

        if ($customer['profile']['omang'] == null) {


            $id = $customer['profile']['passport'];
        } else {

            $id = $customer['profile']['omang'];
        }
        $urlValue = \Config::get('values.graphite_url');
        $client = new \GuzzleHttp\Client();
        $url = $urlValue . "realpay/activateClient";


        $requestContent = [

            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],

            'form_params' => [
                'fname' => $customer['firstName'],
                'lname' => $customer['lastName'],
                'idNumber' => $id,
                'bankNum' => $customer['banking']['bankName'],
                'bankBranchNum' => $customer['banking']['branchCode'],
                'bankAccountNum' =>  $customer['banking']['accountNumber'],
                'bankAccountType' => $customer['banking']['accountType'],
                'clientNumber' => $policy->policyNumber,
                'empCode' => 'OT',
                'generateInstallmentsYn' => 'Y',
                'firstActionDate' => $threeMonthsLater,
                'tracking' => '44',
                'product' => 'FNBNDOBW',
                'numberOfInstallments' => '99',
                'installmentFrequency' => 'M',
                'contractNumber' => $policy->policyNumber,
                'installmentAmount' => (string) $premium,
            ],
        ];



        try {
            $apiRequest = $client->request('POST', $url, $requestContent);

            $response = $apiRequest->getBody()->getContents();

            if ($response == 'OK') {

                return Redirect::route('admin.policy.edit', $policy->id)->with('success', 'Real Pay Account Created');
            } else {
                return Redirect::route('admin.policy.edit', $policy->id)->with('error', 'Realpay account creation failed');
            }



            return $response;
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }

        $response = $request->getBody('response');

        return $response;
    }



    public function appAddClientRealPay($policy, $premium)
    {


        $threeMonthsLater = Carbon::now()->addDay(90)->toDateString();


        $customer = Customer::where('id', $policy->customer_id)->with('profile', 'banking')->first();


        $client = new \GuzzleHttp\Client();

        $urlValue = \Config::get('values.graphite_url');
        $url = $urlValue . "realpay/activateClient";


        $requestContent = [

            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],

            'form_params' => [
                'fname' => $customer['firstName'],
                'lname' => $customer['lastName'],
                'idNumber' => $customer['profile']['omang'],
                'bankNum' => $customer['banking']['bankName'],
                'bankBranchNum' => $customer['banking']['branchCode'],
                'bankAccountNum' =>  $customer['banking']['accountNumber'],
                'bankAccountType' => $customer['banking']['accountType'],
                'clientNumber' => $policy->policyNumber,
                'empCode' => 'OT',
                'generateInstallmentsYn' => 'Y',
                'ctcPercentage' => '0',
                'firstActionDate' => $threeMonthsLater,
                'tracking' => '44',
                'product' => 'FNBNDOBW',
                'numberOfInstallments' => '99',
                'installmentFrequency' => 'M',
                'installmentAmount' => $premium,
            ],
        ];

        try {


            return response()->json($requestContent);
            $apiRequest = $client->request('POST', $url, $requestContent);

            $response = $apiRequest->getBody()->getContents();

            if ($response == 'OK') {

                return response()->json(['code' => 200, 'description' => 'Successful Transaction'], 200);
            } else {
                return response()->json('Realpay adding client encouted a problem.', 401);
            }
            return $response;
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }

        $response = $request->getBody('response');

        return $response;
    }

    public function leftOutPremiumPayment($policy,$premium,$date)
    {
        $customer = Customer::where('id', $policy->customer_id)->with('profile')->first();
        $customerBanking = CustomerBanking::where('customer_id', $customer->id)->orderBy('id', 'DESC')->first();


        $xml = '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
                    <soap:Body xmlns:ns1="http://realpay_trlp/RP_WS.wsdl">
                    <ns1:addEditClientsElement>
                        <ns1:pUsername>intgalpha</ns1:pUsername>
                        <ns1:pPassword>61efb93ac14777d47a59d400fdfff45b</ns1:pPassword>
                        <ns1:pMerchantNumber>16244</ns1:pMerchantNumber>
                        <ns1:pRequestdata>
                            <ns1:bankAccountType>'. $customerBanking->accountType .'</ns1:bankAccountType>
                            <ns1:clientNumber>'. $policy->policyNumber .'</ns1:clientNumber>
                            <ns1:bankNum>'. $customerBanking->bankName .'</ns1:bankNum>
                            <ns1:empCode>OT</ns1:empCode>
                            <ns1:clientName>'.$customer['firstName'].' '.$customer['lastName'].'</ns1:clientName>
                            <ns1:bankBranchNum>'. $customerBanking->branchCode .'</ns1:bankBranchNum>
                            <ns1:bankAccountNum>'. $customerBanking->accountNumber .'</ns1:bankAccountNum>
                            <ns1:idNumber>'. $customer['profile']['omang'] .'</ns1:idNumber>
                            <ns1:bankAccountName>'.$customer['firstName'].' '.$customer['lastName'].'</ns1:bankAccountName>
                        </ns1:pRequestdata>
                    </ns1:addEditClientsElement>
                </soap:Body>
               </soap:Envelope>';

        $options = [
            'headers' => [
                'Content-Type' => 'application/soap+xml',
            ],
            'body' => $xml,
        ];


        $client = new \GuzzleHttp\Client();
        $apiRequest = $client->request('POST', 'https://www.realpaycollect.com:7774/RP_WS-RP_WS-WS/RP_WSSoap12HttpPort', $options);
        $response = $apiRequest->getBody()->getContents();

        $xml = simplexml_load_string($response);
        $contractNumber = Carbon::now()->getTimestamp();
        $xml = '';
        $xml .= '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
                          <soap:Body xmlns:ns1="http://realpay_trlp/RP_WS.wsdl">
                          <ns1:addContractsElement>
                          <ns1:pUsername>intgalpha</ns1:pUsername>
                          <ns1:pPassword>61efb93ac14777d47a59d400fdfff45b</ns1:pPassword>
                          <ns1:pMerchantNumber>16244</ns1:pMerchantNumber>
                          <ns1:pRequestdata>
                             <ns1:product>FNBNDOBW</ns1:product>
                             <ns1:clientNumber>'. $policy->policyNumber .'</ns1:clientNumber>
                             <ns1:generateInstallmentsYn>Y</ns1:generateInstallmentsYn>
                             <ns1:ctcPercentage>0</ns1:ctcPercentage>
                             <ns1:firstActionDate>'. $date .'</ns1:firstActionDate>
                             <ns1:tracking>44</ns1:tracking>
                             <ns1:numberOfInstallments>1</ns1:numberOfInstallments>
                             <ns1:contractNumber>'. $contractNumber .'</ns1:contractNumber>
                             <ns1:installmentFrequency>M</ns1:installmentFrequency>
                             <ns1:installmentAmount>' . $premium . '</ns1:installmentAmount>
                             </ns1:pRequestdata>
                             </ns1:addContractsElement>
                             </soap:Body>
                             </soap:Envelope>';

        $options = [
            'headers' => [
                'Content-Type' => 'application/soap+xml',
            ],
            'body' => $xml,
        ];

        $client = new \GuzzleHttp\Client();
        $apiRequest = $client->request('POST', 'https://www.realpaycollect.com:7774/RP_WS-RP_WS-WS/RP_WSSoap12HttpPort', $options);
        $response = $apiRequest->getBody()->getContents();
        $xml = simplexml_load_string($response);

        return true;
    }

    public function addClientRealPayAlphaFePay($policy, $premium, $customerExist,$existingPolicy,$leftOut)
    {
        try{

//            if($customerExist == 1 && $existingPolicy != '' )
//                return $this->editInstallment($policy,$existingPolicy, $premium); // temporary commented by karthik

            $customer = Customer::where('id', $policy->customer_id)->with('profile')->first();
            $customerBanking = CustomerBanking::where('customer_id', $customer->id)->orderBy('id', 'DESC')->first();


            $xml = '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
                    <soap:Body xmlns:ns1="http://realpay_trlp/RP_WS.wsdl">
                    <ns1:addEditClientsElement>
                        <ns1:pUsername>intgalpha</ns1:pUsername>
                        <ns1:pPassword>61efb93ac14777d47a59d400fdfff45b</ns1:pPassword>
                        <ns1:pMerchantNumber>16244</ns1:pMerchantNumber>
                        <ns1:pRequestdata>
                            <ns1:bankAccountType>'. $customerBanking->accountType .'</ns1:bankAccountType>
                            <ns1:clientNumber>'. $policy->policyNumber .'</ns1:clientNumber>
                            <ns1:bankNum>'. $customerBanking->bankName .'</ns1:bankNum>
                            <ns1:empCode>OT</ns1:empCode>
                            <ns1:clientName>'.$customer['firstName'].' '.$customer['lastName'].'</ns1:clientName>
                            <ns1:bankBranchNum>'. $customerBanking->branchCode .'</ns1:bankBranchNum>
                            <ns1:bankAccountNum>'. $customerBanking->accountNumber .'</ns1:bankAccountNum>
                            <ns1:idNumber>'. $customer['profile']['omang'] .'</ns1:idNumber>
                            <ns1:bankAccountName>'.$customer['firstName'].' '.$customer['lastName'].'</ns1:bankAccountName>
                        </ns1:pRequestdata>
                    </ns1:addEditClientsElement>
                </soap:Body>
               </soap:Envelope>';

            $options = [
                'headers' => [
                    'Content-Type' => 'application/soap+xml',
                ],
                'body' => $xml,
            ];


            $client = new \GuzzleHttp\Client();
            $apiRequest = $client->request('POST', 'https://www.realpaycollect.com:7774/RP_WS-RP_WS-WS/RP_WSSoap12HttpPort', $options);
            $response = $apiRequest->getBody()->getContents();

            $xml = simplexml_load_string($response);
            $this->addClientContract($policy, $premium,$leftOut);

        }catch(RequestException $re) {
            // For handling exception.

            return response()->json(['status' => '401', 'message' => 'Error with transaction occured']);
        }



    }

    public function addClientContract($policy,$premium,$leftOut){

        try{
            $paymentDate = new ClaimController();
            $date = $paymentDate->getLeftOutPaymentDate();

            $contractNumber = Carbon::now()->getTimestamp();

           if($leftOut != null && $policy->premium_freq == 1){
               $l = '';
               $l .= '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
                    <soap:Body xmlns:ns1="http://realpay_trlp/RP_WS.wsdl">
                    <ns1:addContractsElement>
                          <ns1:pUsername>intgalpha</ns1:pUsername>
                          <ns1:pPassword>61efb93ac14777d47a59d400fdfff45b</ns1:pPassword>
                          <ns1:pMerchantNumber>16244</ns1:pMerchantNumber>
                          <ns1:pRequestdata>
                          <ns1:product>FNBNDOBW</ns1:product>
                    <ns1:clientNumber>'. $policy->policyNumber .'</ns1:clientNumber>
                    <ns1:generateInstallmentsYn>Y</ns1:generateInstallmentsYn>
                    <ns1:ctcPercentage>0</ns1:ctcPercentage>
                    <ns1:firstActionDate>'. $date .'</ns1:firstActionDate>
                    <ns1:tracking>44</ns1:tracking>
                    <ns1:numberOfInstallments>1</ns1:numberOfInstallments>
                    <ns1:contractNumber>LEFTOUT_'. $contractNumber .'</ns1:contractNumber>
                    <ns1:installmentFrequency>M</ns1:installmentFrequency>
                    <ns1:installmentAmount>'. $leftOut .'</ns1:installmentAmount>
                    </ns1:pRequestdata>
                    </ns1:addContractsElement>
                    </soap:Body>
                    </soap:Envelope>';
//<ns1:installmentAmount>'. $leftOut .'</ns1:installmentAmount>
               $options = [
                   'headers' => [
                       'Content-Type' => 'application/soap+xml',
                   ],
                   'body' => $l,
               ];


               $client = new \GuzzleHttp\Client();
               $apiRequest = $client->request('POST', 'https://www.realpaycollect.com:7774/RP_WS-RP_WS-WS/RP_WSSoap12HttpPort', $options);
               $response = $apiRequest->getBody()->getContents();
               $res = simplexml_load_string($response);
           }


            $threeMonthsLater = Carbon::now()->addDay(90)->toDateString();
            if($policy->billingStartDate)
                $billingDate = $policy->billingStartDate;
            else
                $billingDate = Carbon::now()->toDateString();


            $xml = '';
            $xml .= '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
                          <soap:Body xmlns:ns1="http://realpay_trlp/RP_WS.wsdl">
                          <ns1:addContractsElement>
                          <ns1:pUsername>intgalpha</ns1:pUsername>
                          <ns1:pPassword>61efb93ac14777d47a59d400fdfff45b</ns1:pPassword>
                          <ns1:pMerchantNumber>16244</ns1:pMerchantNumber>
                          <ns1:pRequestdata>
                             <ns1:product>FNBNDOBW</ns1:product>
                             <ns1:clientNumber>'. $policy->policyNumber .'</ns1:clientNumber>
                             <ns1:generateInstallmentsYn>Y</ns1:generateInstallmentsYn>
                             <ns1:ctcPercentage>0</ns1:ctcPercentage>
                             <ns1:firstActionDate>'. $billingDate .'</ns1:firstActionDate>
                             <ns1:tracking>44</ns1:tracking>';
            if($policy->premium_freq == 1){
                $xml .=    '<ns1:numberOfInstallments>99</ns1:numberOfInstallments>
                             <ns1:contractNumber>'. $contractNumber .'</ns1:contractNumber>
                             <ns1:installmentFrequency>M</ns1:installmentFrequency>';
            }
            elseif($policy->premium_freq == 2){
                $xml .= '<ns1:numberOfInstallments>3</ns1:numberOfInstallments>
                            <ns1:contractNumber>'. $contractNumber .'</ns1:contractNumber>
                            <ns1:installmentFrequency>M</ns1:installmentFrequency>';
            }
            elseif($policy->premium_freq == 3) {
                $xml .= '<ns1:numberOfInstallments>1</ns1:numberOfInstallments>
                            <ns1:contractNumber>'. $contractNumber .'</ns1:contractNumber>
                            <ns1:installmentFrequency>M</ns1:installmentFrequency>';

            }
            else{
                $xml .= '<ns1:numberOfInstallments>99</ns1:numberOfInstallments>
                            <ns1:contractNumber>'. $contractNumber .'</ns1:contractNumber>
                            <ns1:installmentFrequency>M</ns1:installmentFrequency>';
            }
            //$xml .=  '<ns1:installmentAmount>' . $premium . '</ns1:installmentAmount>

            $xml .=  '<ns1:installmentAmount>' . $premium . '</ns1:installmentAmount>
                          </ns1:pRequestdata>
                          </ns1:addContractsElement>
                          </soap:Body>
                          </soap:Envelope>';

            $options = [
                'headers' => [
                    'Content-Type' => 'application/soap+xml',
                ],
                'body' => $xml,
            ];

            $client = new \GuzzleHttp\Client();
            $apiRequest = $client->request('POST', 'https://www.realpaycollect.com:7774/RP_WS-RP_WS-WS/RP_WSSoap12HttpPort', $options);
            $response = $apiRequest->getBody()->getContents();
            $xml = simplexml_load_string($response);

            $referenceNumber = CustomerBanking::where('policy_id',$policy->id)->first();
            if($referenceNumber){
                if($referenceNumber->contract_number == null){
                    $referenceNumber->contract_number = $contractNumber;
                    $referenceNumber->client_number = $policy->policyNumber;
                    $referenceNumber->save();
                }
            }

            $actions = new ClaimController();
            $isPerformed = $actions->actionAfterPolicyCreateFromQuoteRealPay($policy->id);

            //return response()->json(['message'=>'Transaction Successfull','policy_id'=>$policy->id], 200);

        }catch(RequestException $re) {
            // For handling exception.

            return response()->json(['status' => '401', 'message' => 'Error with transaction occured']);

        }
    }

    public function getContracts($policy){
        $xml = '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
                <soap:Body xmlns:ns1="http://realpay_trlp/RP_WS.wsdl">
                <ns1:getContractsElement>
                    <ns1:pUsername>intgalpha</ns1:pUsername>
                    <ns1:pPassword>61efb93ac14777d47a59d400fdfff45b</ns1:pPassword>
                    <ns1:pMerchantNumber>16244</ns1:pMerchantNumber>
                    <ns1:pClientNumber>'. $policy->policyNumber .'</ns1:pClientNumber>
                    <ns1:pContractNumber></ns1:pContractNumber>
                    </ns1:getContractsElement>
                </soap:Body>
                </soap:Envelope>';

        $options = [
            'headers' => [
                'Content-Type' => 'application/soap+xml',
            ],
            'body' => $xml,
        ];

        $client = new \GuzzleHttp\Client();
        $apiRequest = $client->request('POST', 'https://www.realpaycollect.com:7774/RP_WS-RP_WS-WS/RP_WSSoap12HttpPort', $options);
        $response = $apiRequest->getBody()->getContents();
        $xml     = simplexml_load_string($response);

        $output = array();
        foreach($xml->xpath('//env:Envelope/env:Body/ns0:getContractsResponseElement/ns0:result/ns0:presponsedataOut') as $key => $header) {
            $output[$key]['clientNumber'] = $header->xpath('ns0:clientNumber')[0][0];
            $output[$key]['contractNumber'] = $header->xpath('ns0:contractNumber')[0][0];
            $output[$key]['numberOfInstallments'] = $header->xpath('ns0:numberOfInstallments')[0][0];
        }

        $json = json_encode($output);
        $array = json_decode($json,TRUE);

        /*added*/
        $data = array();
        $incI = 0;
        foreach($array AS $arrKey => $arrData){

            $data[$incI]['clientNumber'] = $arrData['clientNumber'][0];
            $data[$incI]['contractNumber'] = $arrData['contractNumber'][0];
            $data[$incI]['numberOfInstallments'] = $arrData['numberOfInstallments'][0];
            $incI++;

        }

        $activeContracts = array();

        /*   foreach($data as $d) {
               if($d['status'][0] == 'A') {
                   array_push($activeContracts, $d);
               }
           }*/

        return $data;
    }

    public function editInstallment($policy,$existingPolicy, $premium)
    {
        $activeContracts = $this->getContracts($existingPolicy);

        $banking = CustomerBanking::where('policy_id',$existingPolicy->id)->first();

        $allActiveInstallments = array();

        /*$activeInstallments = $this->getInstallments($banking);*/

        foreach($activeContracts as $a){
            $i = $this->getInstallments($a);
            foreach ($i as $ins){
                if($ins['status'] == 'A')
                    array_push($allActiveInstallments,$ins);
            }
        }

        //dd($allActiveInstallments);

        $numberOfInstallmentstobeadded = 0;
        $editCount = 0;

        if($allActiveInstallments != null){
            $numberOfActiveInstallments = count($allActiveInstallments);

            if($policy->premium_freq == 1)
                $premium_freq = 99;
            elseif ($policy->premium_freq == 2)
                $premium_freq = 3;
            elseif($policy->premium_freq == 3)
                $premium_freq = 1;
            elseif($policy->premium_freq == null)
                $premium_freq = 99;
            else
                $premium_freq = $numberOfActiveInstallments;


            if($premium_freq > $numberOfActiveInstallments)
                $numberOfInstallmentstobeadded = $premium_freq - $numberOfActiveInstallments;
            elseif($premium_freq < $numberOfActiveInstallments)
                $editCount = $premium_freq;
            else
                $editCount = $numberOfActiveInstallments;

            //dd('edit count = '.$editCount.' '.'edit count = '.$numberOfActiveInstallments);

            $mergeDetails = CustomerBanking::where('policy_id',$existingPolicy->id)->first();
            $referenceNumber = CustomerBanking::where('policy_id',$policy->id)->first();
            if($referenceNumber){
                if($referenceNumber->contract_number == null){
                    $referenceNumber->contract_number = $mergeDetails->contract_number;
                    $referenceNumber->client_number = $mergeDetails->client_number;
                    $referenceNumber->merge_ref = 1;
                    $referenceNumber->save();
                }
            }

            $actionDate = '';
            foreach($allActiveInstallments as $key=>$i){

                if($editCount == $key ){
                    $actionDate = Carbon::parse($i['actionDate'])->format('Y-m-d');
                    break;
                }

                $xml = '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
                      <soap:Body xmlns:ns1="http://realpay_trlp/RP_WS.wsdl">
                         <ns1:editInstallmentsElement>
                            <ns1:pUsername>intgalpha</ns1:pUsername>
                            <ns1:pPassword>61efb93ac14777d47a59d400fdfff45b</ns1:pPassword>
                            <ns1:pMerchantNumber>16244</ns1:pMerchantNumber>
                            <ns1:pRequestdata>
                                <ns1:installmentReferenceNumber>'. $i['refNum'] .'</ns1:installmentReferenceNumber>
                                <ns1:ctcAmount></ns1:ctcAmount>
                                <ns1:actionDate></ns1:actionDate>
                                <ns1:tracking></ns1:tracking>
                                <ns1:status></ns1:status>
                                <ns1:installmentAmount>'. ($i['totalInstallmentAmount']  +  $premium) .'</ns1:installmentAmount>
                            </ns1:pRequestdata>
                         </ns1:editInstallmentsElement>
                      </soap:Body>
                    </soap:Envelope>';

                $options = [
                    'headers' => [
                        'Content-Type' => 'application/soap+xml',
                    ],
                    'body' => $xml,
                ];

                $client = new \GuzzleHttp\Client();
                $apiRequest = $client->request('POST', 'https://www.realpaycollect.com:7774/RP_WS-RP_WS-WS/RP_WSSoap12HttpPort', $options);
            }
            //$response = $apiRequest->getBody()->getContents();

            if($numberOfInstallmentstobeadded > 0){
                return $this->addEditClientContract($policy,$existingPolicy,$actionDate,$numberOfInstallmentstobeadded);
            }

            return response()->json(['code' => 200, 'description' => 'Transaction Successful'], 200);

        }
        else{
            $customerExist = 0;
            $existingPolicy = '';
            $this->addClientRealPayAlphaFePay($policy, $premium, $customerExist, $existingPolicy,$leftout = 0);
        }
    }

    public function addEditClientContract($policy,$existingPolicy,$date,$numberofInstallments){

        try{

            $threeMonthsLater = Carbon::now()->addDay(90)->toDateString();
            if($date)
                $billingDate = Carbon::parse($date)->addMonth(1)->format('Y-m-d');
            else
                $billingDate = Carbon::now()->addDay(30)->toDateString();

            $contractNumber = Carbon::now()->getTimestamp();
            $xml = '';
            $xml .= '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
                          <soap:Body xmlns:ns1="http://realpay_trlp/RP_WS.wsdl">
                          <ns1:addContractsElement>
                          <ns1:pUsername>intgalpha</ns1:pUsername>
                          <ns1:pPassword>61efb93ac14777d47a59d400fdfff45b</ns1:pPassword>
                          <ns1:pMerchantNumber>16244</ns1:pMerchantNumber>
                          <ns1:pRequestdata>
                             <ns1:product>FNBNDOBW</ns1:product>
                             <ns1:clientNumber>'.$existingPolicy->policyNumber.'</ns1:clientNumber>
                             <ns1:generateInstallmentsYn>Y</ns1:generateInstallmentsYn>
                             <ns1:ctcPercentage>0</ns1:ctcPercentage>
                             <ns1:firstActionDate>'.$billingDate.'</ns1:firstActionDate>
                             <ns1:tracking>44</ns1:tracking>
                             <ns1:numberOfInstallments>'.$numberofInstallments.'</ns1:numberOfInstallments>
                             <ns1:contractNumber>'.$contractNumber.'</ns1:contractNumber>
                             <ns1:installmentFrequency>M</ns1:installmentFrequency>
                             <ns1:installmentAmount>'.$policy->premium.'</ns1:installmentAmount>
                             </ns1:pRequestdata>
                             </ns1:addContractsElement>
                             </soap:Body>
                      </soap:Envelope>';

            $options = [
                'headers' => [
                    'Content-Type' => 'application/soap+xml',
                ],
                'body' => $xml,
            ];

            $client = new \GuzzleHttp\Client();
            $apiRequest = $client->request('POST', 'https://www.realpaycollect.com:7774/RP_WS-RP_WS-WS/RP_WSSoap12HttpPort', $options);
            $response = $apiRequest->getBody()->getContents();
            $xml = simplexml_load_string($response);

            $referenceNumber = CustomerBanking::where('policy_id',$policy->id)->first();
            if($referenceNumber){
                if($referenceNumber->reference_number == null){
                    $referenceNumber->reference_number = $contractNumber;
                    $referenceNumber->save();
                }
            }
            return response()->json('Transaction Successfull', 200);

        }catch(RequestException $re) {
            // For handling exception.

            return response()->json(['status' => '401', 'message' => 'Error with transaction occured']);

        }
    }

    /*function to add installments__By: Karthik*/
    public function addInstallments($policy,$existingPolicy,$numberOfInstallmentstobeadded,$dateForNewInstallments)
    {
        $banking = CustomerBanking::where('policy_id',$existingPolicy->id)->first();
        for($i = 1; $i <= $numberOfInstallmentstobeadded; $i++){
            $dateForNewInstallments = $dateForNewInstallments + 30;
            $addDate = Carbon::now()->addDay($dateForNewInstallments)->toDateString();
            $xml = '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
                        <soap:Body xmlns:ns1="http://realpay_trlp/RP_WS.wsdl">
                        <ns1:addInstallmentsElement>
                            <ns1:pUsername>intgalpha</ns1:pUsername>
                            <ns1:pPassword>61efb93ac14777d47a59d400fdfff45b</ns1:pPassword>
                            <ns1:pMerchantNumber>16244</ns1:pMerchantNumber>
                            <ns1:pRequestdata>
                                <ns1:ctcAmount></ns1:ctcAmount>
                                <ns1:clientNumber>'. $banking->client_number .'</ns1:clientNumber>
                                <ns1:actionDate>'.$addDate.'</ns1:actionDate>
                                <ns1:contractNumber>'. $banking->contract_number .'</ns1:contractNumber>
                                <ns1:installmentAmount>'. $policy->premium .'</ns1:installmentAmount>
                            </ns1:pRequestdata>
                        </ns1:addInstallmentsElement>
                        </soap:Body>
                    </soap:Envelope>';

            $options = [
                'headers' => [
                    'Content-Type' => 'application/soap+xml',
                ],
                'body' => $xml,
            ];

            $client = new \GuzzleHttp\Client();
            $apiRequest = $client->request('POST', 'https://www.realpaycollect.com:7774/RP_WS-RP_WS-WS/RP_WSSoap12HttpPort', $options);
            $response = $apiRequest->getBody()->getContents();


        }
        return response()->json(['code' => 200, 'description' => 'Successful Transaction'], 200);
    }

    public function getInstallments($banking)
    {
        //61efb93ac14777d47a59d400fdfff45b
        //intalpha
        $xml = '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
                    <soap:Body xmlns:ns1="http://realpay_trlp/RP_WS.wsdl">
                        <ns1:getInstallmentsElement>
                            <ns1:pUsername>intgalpha</ns1:pUsername>
                            <ns1:pPassword>61efb93ac14777d47a59d400fdfff45b</ns1:pPassword>
                            <ns1:pMerchantNumber>16244</ns1:pMerchantNumber>
                            <ns1:pClientNumber>'. $banking['clientNumber'] .'</ns1:pClientNumber>
                        <ns1:pContractNumber>'. $banking['contractNumber'] .'</ns1:pContractNumber>
                    <ns1:pInstallmentReferenceNumber></ns1:pInstallmentReferenceNumber>
                    </ns1:getInstallmentsElement>
                    </soap:Body>
                </soap:Envelope>';

        $options = [
            'headers' => [
                'Content-Type' => 'application/soap+xml',
            ],
            'body' => $xml,
        ];

        $client = new \GuzzleHttp\Client();
        $apiRequest = $client->request('POST', 'https://www.realpaycollect.com:7774/RP_WS-RP_WS-WS/RP_WSSoap12HttpPort', $options);
        $response = $apiRequest->getBody()->getContents();
        $xml     = simplexml_load_string($response);

        $output = array();
        foreach($xml->xpath('//env:Envelope/env:Body/ns0:getInstallmentsResponseElement/ns0:result/ns0:presponsedataOut') as $key => $header) {
            $output[$key]['clientNumber'] = $header->xpath('ns0:clientNumber')[0][0];
            $output[$key]['refNum'] = $header->xpath('ns0:installmentReferenceNumber')[0][0];
            $output[$key]['totalInstallmentAmount'] = $header->xpath('ns0:totalInstallmentAmount')[0][0];
            $output[$key]['actionDate'] = $header->xpath('ns0:actionDate')[0][0];
            $output[$key]['status'] = $header->xpath('ns0:status')[0][0];
        }

        $json = json_encode($output);
        $array = json_decode($json,TRUE);

        /*added*/
        $data = array();
        $incI = 0;
        foreach($array AS $arrKey => $arrData){

            $data[$incI]['clientNumber'] = $arrData['clientNumber'][0];
            $data[$incI]['refNum'] = $arrData['refNum'][0];
            $data[$incI]['totalInstallmentAmount'] = $arrData['totalInstallmentAmount'][0];
            $data[$incI]['actionDate'] = $arrData['actionDate'][0];
            $data[$incI]['status'] = $arrData['status'][0];
            $incI++;

        }

        $activeInstallments = array();

        foreach($data as $d) {
            if($d['status'][0] == 'A') {
                array_push($activeInstallments, $d);
            }

        }
        return $activeInstallments;
    }


    public function getTransactionsReport(Request $request)
    {

        $todaysDate = Carbon::today()->toDateString();
        $tomorrowsDate = Carbon::today()->addDay(1)->toDateString();

        $client = new \GuzzleHttp\Client();
        $urlValue = \Config::get('values.graphite_url');

        $url = $urlValue . "realpay/getTransactionsReport";

        $requestContent = [

            'form_params' => [
                'pStartDate' => '2019-08-24',
                'pEndDate' => '2019-08-30',

            ],
        ];

        try {

            $apiRequest = $client->request('GET', $url, $requestContent);

            $data = json_decode($apiRequest->getBody()->getContents(), true);
            $trans = $data[0];
            $collection = collect($trans);

            foreach ($collection as $json) {
            }

            return $collection[0];
        } catch (RequestException $re) {
            // For handling exception.

            return response()->json(['status' => '401', 'message' => 'Error with transaction occured']);
        }

        $response = $request->getBody('response');

        return $response;
    }
    public function storeRealPayTransaction(Request $request)
    {
        try {
            $realPay = new RealPay();
            $realPay->uniqueRecId = $request->uniqueRecId;
            $realPay->policyNumber = $request->clientNumber;
            $realPay->installmentRef = $request->installmentRef;
            $realPay->status = $request->status;
            if ($realPay->save()) {

                $transaction = new Transaction();
                $transaction->orangeTransactionId = $realPay->id;
                $transaction->transactionType = 'realPay'; //request->transactionType;
                $transaction->policyNumber = $request->clientNumber;
                $transaction->amount = $request->amount;
                $transaction->status = $request->status;
                $transaction->save();
            }
        } catch (Exception $re) {
            return response()->json(["message" => "Error with orange callback url"]);
        }
    }

    public function getRealPayBanks()
    {

        try {

            $banks = Banks::all();


            return response()->json(['code' => 200, 'banks' => $banks], 200);
        } catch (TeacherNotFoundException $e) {

            return response()->json('An error has occured with RealPay: getting banks', 400);
        }
    }

    public function getRealPayBranches(Request $request)
    {
        try {
            $branches = BankBranches::where('bank_id', $request->bank_id)->get();
            return response()->json(['code' => 200, 'branches' => $branches], 200);
        } catch (TeacherNotFoundException $e) {

            return response()->json('An error has occured with RealPay get branches', 400);
        }
    }
}