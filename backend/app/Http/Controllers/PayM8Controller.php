<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use AlphaDirect\City;
use AlphaDirect\Customer;
use AlphaDirect\CustomerBanking;
use AlphaDirect\PolicyBundled;
use AlphaDirect\DpoPayment;
use AlphaDirect\Events\CancelScheduleTransactionEvent;
use AlphaDirect\Events\CancelTokenEvent;
use AlphaDirect\Events\ChargeTokenRecurrentEvent;
use AlphaDirect\Events\CreateTokenEvent;
use AlphaDirect\Events\PullAccountEvent;
use AlphaDirect\Events\ScheduleTransactionEvent;
use AlphaDirect\Events\SubscriptionTokenEvent;
use AlphaDirect\Events\VerifyTokenEvent;
use AlphaDirect\Helper;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Models\OneTimePaymentURL;
use AlphaDirect\PaymentActivityLog;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\PaymentUrls;
use AlphaDirect\Policy;
use AlphaDirect\Productplan;
use AlphaDirect\ScheduleTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use AlphaDirect\Imports\DpoExcelImport;
use AlphaDirect\Models\NgeniusTransection;
use AlphaDirect\Models\NgeniusCard;
use AlphaDirect\Models\NgeniusWebhook;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Models\UpdateContract;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use AlphaDirect\Models\PayMProcess;
use AlphaDirect\Models\PayMSchuduleTransection;
use AlphaDirect\Models\MerchantBranches;
use AlphaDirect\Models\PayM8Banks;
use AlphaDirect\Models\PayMContract;
use Yajra\DataTables\DataTables;
use AlphaDirect\User;

class PayM8Controller extends Controller
{
    
       
    function getBillingDate($policy)
    {
        $today = Carbon::today();
        $billingDay = $policy->billing_day;
        $billingDate = Carbon::createFromDate($today->year, $today->month, $billingDay);
        if ($billingDate < $today) {
            $billingDate->addMonth();
        }
        return Carbon::parse($policy->billingStartDate) >= $today
            ? Carbon::parse($policy->billingStartDate)
            : $billingDate;
    }
    function generateInstallments($policy) 
    {
        $installments = [];
        $installments[]=$this->generatefirstInstallments($policy);
        $date = $this->getBillingDate($policy);
        
       
        $amountInCents = (int)$policy->premium*100;
        if($policy->product_id == 3){
            if($policy->premium_freq == null || $policy->premium_freq == 1){
                if($policy->BillingStart=="Later"){ 
                    for ($i = 0; $i <= 11; $i++) {
                        $installments[] = [
                            "AmountInCents" => (string) $amountInCents,
                            "PaymentDate" => $date->format('Y-m-d')
                        ];
                        $date->addMonth();
                    }

                } else {
                    for ($i = 0; $i == 11; $i++) {
                        $installments[] = [
                            "AmountInCents" => (string) $amountInCents,
                            "PaymentDate" => $date->format('Y-m-d')
                        ];
                        $date->addMonth();
                    }
                }
                    
            }else if($policy->premium_freq == 2){
                
                $date->addMonth(4);
                if($policy->BillingStart=="Later"){
                    for ($i = 1; $i <= 3; $i++) {
                        $installments[] = [
                            "AmountInCents" => (string) $amountInCents,
                            "PaymentDate" => $date->format('Y-m-d')
                        ];
                        $date->addMonth(4);
                    }
                }else{
                    for ($i = 1; $i < 3; $i++) {
                        $installments[] = [
                            "AmountInCents" => (string) $amountInCents,
                            "PaymentDate" => $date->format('Y-m-d')
                        ];
                        $date->addMonth(4);
                    }
                }
                

            }else if($policy->premium_freq == 3){
                $installments[] = [
                    "AmountInCents" => (string) $amountInCents,
                    "PaymentDate" => $date->format('Y-m-d')
                ];

            }else{

                for ($i = 0; $i <= 11; $i++) {
                    $installments[] = [
                        "AmountInCents" => (string) $amountInCents,
                        "PaymentDate" => $date->format('Y-m-d')
                    ];
                    $date->addMonth();
                }
            }
            

        }else{
            for ($i = 0; $i < 99; $i++) {
                $installments[] = [
                    "AmountInCents" => (string) $amountInCents,
                    "PaymentDate" => $date->format('Y-m-d')
                ];
                $date->addMonth();
            }
        }
        
    
        return  $installments;
    }
    function generatefirstInstallments($policy) 
    {
        if($policy->product_id == 3){
            if($policy->premium_freq == 3){
                $finst = []; 
            }else{
                if($policy->BillingStart=="Later"){ 
                    $finst =   [
                        "AmountInCents"=> 100,
                        "PaymentDate"=> Carbon::today()->format('Y-m-d')
                    ];

                } else {
                 if($policy->first_premium == 0){
                    $finst =   [
                       
                    ];
                 }else{
                    $finst =   [
                        "AmountInCents"=> (int)$policy->first_premium*100,
                        "PaymentDate"=> Carbon::today()->format('Y-m-d')
                    ];
                 }
                    
                }
             
            }
        }else{
            if($policy->BillingStart=="Later"){  
                $finst =   [
                    "AmountInCents"=> 100,
                    "PaymentDate"=> Carbon::today()->format('Y-m-d')
                ];
             } else {
                $finst =   [
                    "AmountInCents"=> (int)$policy->premium*100,
                    "PaymentDate"=> Carbon::today()->format('Y-m-d')
                ];
            }
        
        }
      return  $finst;
    }
    function generatePayload($policyId) 
    {
        $policy = Policy::where('id',$policyId)->with(['customer','profile',])->first();
        $IdentityNumber = null;
        $passportNumber = null;
        if($policy->profile->omang){
            $IdentityNumber = $policy->profile->omang;
        }else{
            $passportNumber = $policy->profile->passport;
        }
        $customeranking = CustomerBanking::where('policy_id', $policyId)->first();
        
        return json_encode([
            "MerchantContractNumber" => $policy->policyNumber,
            "DateAdjustmentDirection" => "MoveForward",
            "MerchantClientProfile" => [
                "FirstNames" => $policy->customer->firstName,
                "Surname" => $policy->customer->lastName,
                "EmailAddress" => $policy->customer->email,
                "MerchantClientReference" => $policy->policyNumber,
                "ContactNumber" => $policy->customer->cellphone,
                "CountryOfCitizenship" => "Botswana",
                "IdentityNumber" => $IdentityNumber,
                "PassportNumber" => $passportNumber
            ],
            "MerchantBranchProductNumber" => config('services.paym8.product'),//"TD7SGG",
            "PrimaryChannel" => [
                "PaymentChannel" => config('services.paym8.channel'),
                "PaymentChannelAction" => "Create",
                "BankAccountDetails" => [
                    "BankId" =>$customeranking->branchCode,
                    "AccountNumber" => $customeranking->accountNumber,
                    "AccountType" => $customeranking->accountNumber == 2 ? "savings":"Cheque",
                    "FirstName" => $policy->customer->firstName,
                    "Surname" => $policy->customer->lastName
                ]
            ],
       
            "Installments" => $this->generateInstallments($policy),
            "StartDate" => Carbon::today()->format('Y-m-d'),
            "PermanentFailureCallback" => env("APP_URL")."paym/webhook",
            "MessageReference" => (string) Str::uuid()
        ]);
    }
    public function createAdHocPayment($policyId)
    {
        try {
            $url = config('services.paym8.url').'PaymentsService/api/V1/PaymentArrangements/CreateAdHoc';
            $policy = Policy::where('id',$policyId)->whereIn('status',[0,1])->first();
            
            if (!$policy) {
                return response()->json([
                    'success' => false,
                    'message' => 'Policy not found or invalid status',
                    'error_code' => 'POLICY_NOT_FOUND'
                ], 404);
            }

            $payload = $this->generatePayload($policyId);
            $paym = new PayMProcess();
            $paym->policyNumber = $policy->policyNumber;
            $paym->request = $payload;
            $paym->url = $url;
            $paym->save();
            
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'Authorization: Basic '.config('services.paym8.key')
                ],
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);
            
            $paym->response = $response;
            $paym->save(); 
            
            // Handle cURL errors
            if ($error) {
                $paym->status = -1;
                $paym->save();
                return response()->json([
                    'success' => false,
                    'message' => 'Network error occurred',
                    'error' => $error,
                    'error_code' => 'CURL_ERROR'
                ], 500);
            }

            // Handle HTTP errors
            if ($httpCode !== 200) {
                $paym->status = -1;
                $paym->save();
                return response()->json([
                    'success' => false,
                    'message' => 'API request failed',
                    'http_code' => $httpCode,
                    'response' => $response,
                    'error_code' => 'HTTP_ERROR'
                ], $httpCode >= 400 && $httpCode < 500 ? $httpCode : 500);
            }

            $resp = json_decode($response, true);
            
            // Handle JSON decode errors
            if (json_last_error() !== JSON_ERROR_NONE) {
                $paym->status = -1;
                $paym->save();
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid JSON response from API',
                    'error' => json_last_error_msg(),
                    'error_code' => 'JSON_ERROR'
                ], 500);
            }

            // Handle API success response
            if (isset($resp['resultToString']) && $resp['resultToString'] == 'Success') {
                $paym->status = 1;
                $paym->save();
        
                $data = $resp['data'] ?? [];
        
                $contract = PayMContract::create([
                    'policyNumber' => $data['merchantClientProfile']['merchantClientReference'] ?? null,
                    'contractid' => $data['id'] ?? null,
                    'status' => $data['statusToString'] ?? null,
                    'primaryChannelTypeToString' => $data['primaryChannelTypeToString'] ?? null,
                    'firstPaymentAmountInCents' => $data['firstPaymentAmountInCents'] ?? 0,
                    'firstPaymentDate' => isset($data['firstPaymentDate']) 
                        ? Carbon::createFromTimestampMs(explode('+', str_replace(['/Date(', ')/'], '', $data['firstPaymentDate']))[0]) 
                        : null,
                    'lastPaymentDate' => isset($data['lastPaymentDate']) 
                        ? Carbon::createFromTimestampMs(explode('+', str_replace(['/Date(', ')/'], '', $data['lastPaymentDate']))[0]) 
                        : null,
                    'merchantContractNumber' => $data['merchantContractNumber'] ?? null,
                ]);
                
                $id = $data['id'] ?? null;
                $policyNumber = $data['merchantClientProfile']['merchantClientReference'] ?? null;
                
                if ($id && $policyNumber) {
                    $this->fetchPayM8Installments($id, $policyNumber);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Payment arrangement created successfully',
                    'data' => [
                        'contract' => $contract,
                        'paym8_response' => $resp
                    ]
                ], 201);
            }

            // Handle API failure response
            $paym->status = 0;
            $paym->save();
            
            $errorMessage = $resp['message'] ?? $resp['resultToString'] ?? 'Unknown error from PayM8 API';
            $errorCode = $resp['result'] ?? 'UNKNOWN_ERROR';
            
            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'error_code' => $errorCode,
                'api_response' => $resp
            ], 400);

        } catch (\Exception $e) {
           

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
                'error_code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }
  
    public function cancelPaymentArrangement(Request $request, $id)
    {
        
            $paympayContract = PayMContract::where("id",$request->contractId)->first();
           
            if($paympayContract){
            $url = config('services.paym8.url').'PaymentsService/api/V1/PaymentArrangements/Cancel';

            $payload = json_encode([
                "Id" => $paympayContract->contractid,
                "MessageReference" => (string) Str::uuid()
            ]);

            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'Authorization: Basic '.config('services.paym8.key')
                ],
            ]);

            $response = curl_exec($curl);
            $error = curl_error($curl);

            curl_close($curl);

            if ($error) {
                return response()->json(['error' => $error], 500);
            }
            $responseData = json_decode($response, true);

            
            if (isset($responseData['result']) && $responseData['result'] === 0) {
                $paympayContract->update([
                    'status' => $responseData['data']['statusToString'], 
                    'action_by' => auth()->user()->id
                   
                ]);
                $this->fetchPayM8Installments($paympayContract->contractid, $paympayContract->policyNumber);
                return response()->json([
                    'message' => 'Contract cancelled successfully',
                    'data' => $paympayContract
                ]);
            }
            return response()->json(['error' => 'Cancellation failed', 'response' => $responseData], 400);
        }
        return response()->json(['error' => 'Contract not found'], 404);
    }
    public function cancelPaymentArrangement2($id)
    {
        
            $paympayContract = PayMContract::where("id",$id)->first();
           
            if($paympayContract){
            $url = config('services.paym8.url').'PaymentsService/api/V1/PaymentArrangements/Cancel';

            $payload = json_encode([
                "Id" => $paympayContract->contractid,
                "MessageReference" => (string) Str::uuid()
            ]);

            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'Authorization: Basic '.config('services.paym8.key')
                ],
            ]);

            $response = curl_exec($curl);
            $error = curl_error($curl);

            curl_close($curl);

            if ($error) {
                return response()->json(['error' => $error], 500);
            }
            $responseData = json_decode($response, true);

            
            if (isset($responseData['result']) && $responseData['result'] === 0) {
                $paympayContract->update([
                    'status' => $responseData['data']['statusToString'], 
                    'action_by' => auth()->user()->id ?? null,
                ]);
                $this->fetchPayM8Installments($paympayContract->contractid, $paympayContract->policyNumber);
                return response()->json([
                    'message' => 'Contract cancelled successfully',
                    'data' => $paympayContract
                ]);
            }
            return response()->json(['error' => 'Cancellation failed', 'response' => $responseData], 400);
        }
        return response()->json(['error' => 'Contract not found'], 404);
    }

    public function cancelScheduleTransaction(Request $request, $id)
    {
        try {
            $scheduleTransaction = PayMSchuduleTransection::where("id", $request->scheduleId)->first();
           
            if($scheduleTransaction){
                $url = config('services.paym8.url').'PaymentsService/api/V1/Installments/Cancel';

                $payload = json_encode([
                    "Id" => $scheduleTransaction->installmentid,
                    "MessageReference" => (string) Str::uuid()
                ]);

                $curl = curl_init();

                curl_setopt_array($curl, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_HTTPHEADER => [
                        'Accept: application/json',
                        'Content-Type: application/json',
                        'Authorization: Basic '.config('services.paym8.key')
                    ],
                ]);

                $response = curl_exec($curl);
                $error = curl_error($curl);

                curl_close($curl);

                if ($error) {
                    return response()->json(['error' => $error], 500);
                }
                $responseData = json_decode($response, true);

                
                if (isset($responseData['result']) && $responseData['result'] === 0) {
                    $scheduleTransaction->update([
                        'status' => 'Cancelled',
                        'action_by' => auth()->user()->id ?? null,
                    ]);
                    
                    return response()->json([
                        'message' => 'Schedule transaction cancelled successfully',
                        'data' => $scheduleTransaction
                    ]);
                }
                return response()->json(['error' => 'Cancellation failed', 'response' => $responseData], 400);
            }
            return response()->json(['error' => 'Schedule transaction not found'], 404);
        } catch (\Exception $ex) {
            return response()->json(['error' => 'An error occurred: ' . $ex->getMessage()], 500);
        }
    }
    public function getPaymentArrangement(Request $request, $id)
    {
        $url = config('services.paym8.url')."PaymentsService/api/V1/PaymentArrangements/Get/$id";

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic '.config('services.paym8.key'),
                'Content-Type: application/json'
            ],
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);

        curl_close($curl);

        if ($error) {
            return response()->json(['error' => $error], 500);
        }

        return response()->json(json_decode($response, true));
    }
    public function merchantBranches()
    {
       $curl = curl_init();
    
        curl_setopt_array($curl, [
            CURLOPT_URL => config('services.paym8.url').'PaymentsService/api/V1/MasterData/GetMerchantBranches',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
                        CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Basic '.config('services.paym8.key'),
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode === 200 && $response) {
            $resp = json_decode($response, true);

            if (isset($resp['data']) && is_array($resp['data'])) {
                foreach ($resp['data'] as $item) {
                    MerchantBranches::updateOrCreate(
                        ['merchantid' => $item['id']], 
                        [
                            'branchName' => $item['branchName'] ?? '',
                            'country' => $item['country'] ?? '',
                        ]
                    );
                }
                return true;
            } else {
                Log::error('Invalid data format from API response.', ['response' => $response]);
            }
        } else {
            Log::error('Failed to fetch merchant branches.', ['http_code' => $httpCode, 'response' => $response]);
        }
    }
    function paym8Banks($paymentChannel = 'SameDayDebitOrder')
    {
        $mbranches = MerchantBranches::all();

        if (count($mbranches) > 0) {
            $allResponses = [];

            foreach ($mbranches as $mbranch) {
                $url = config('services.paym8.url')."PaymentsService/api/V1/MasterData/GetBanks/?branchId={$mbranch->merchantid}&paymentChannel=$paymentChannel";

                $curl = curl_init();

                curl_setopt_array($curl, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => [
                        'Accept: application/json',
                        'Authorization: Basic '.config('services.paym8.key'),
                        'Content-Type: application/json',
                    ],
                ]);

                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

                if (curl_errno($curl)) {
                    $error = 'cURL Error: ' . curl_error($curl);
                    Log::error($error);
                    $allResponses[] = ['merchantid' => $mbranch->merchantid, 'error' => $error];
                    continue;
                }

                curl_close($curl);

                if ($httpCode !== 200) {
                    $error = "HTTP Error: $httpCode";
                    Log::error($error, ['url' => $url]);
                    $allResponses[] = ['merchantid' => $mbranch->merchantid, 'error' => $error];
                    continue;
                }

                $decodedResponse = json_decode($response, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $error = 'JSON Decode Error: ' . json_last_error_msg();
                    Log::error($error, ['response' => $response]);
                    $allResponses[] = ['merchantid' => $mbranch->merchantid, 'error' => $error];
                    continue;
                }

                // Store the bank data in the database
                if (!empty($decodedResponse['data'])) {
                    foreach ($decodedResponse['data'] as $bank) {
                        PayM8Banks::updateOrCreate(
                            ['bankid' => $bank['id']], 
                            [
                                'bankName' => $bank['bankName'],
                                'country' => $bank['country'],
                                'alias' => $bank['alias'],
                                'merchantid' => $mbranch->merchantid
                            ]
                        );
                    }
                }
               
                $allResponses[] = [
                    'merchantid' => $mbranch->merchantid,
                    'response' => $decodedResponse
                ];
            }

            return $allResponses;
        }

        return ['error' => 'No merchant branches found.'];
    }
    public function fetchPayM8Installments($id, $policyNumber)
    {
        $url = config('services.paym8.url').'PaymentsService/api/V1/Installments/Search';
        
        $payload = json_encode([
            'PaymentArrangementId' => $id,
            'PageListArgs' => [
                'PageNumber' => 1,
                'PageSize' => 500,
            ]
        ]);
    
        $headers = [
            'Accept: application/json',
            'Authorization: Basic '.config('services.paym8.key'),
            'Content-Type: application/json',
        ];
    
        $curl = curl_init();
    
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
    
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
        if (curl_errno($curl)) {
            $errorMessage = curl_error($curl);
            curl_close($curl);
            return response()->json(['error' => $errorMessage], 500);
        }
    
        curl_close($curl);
    
        if ($httpCode !== 200) {
            return response()->json(['error' => 'Request failed with status code ' . $httpCode], $httpCode);
        }
    
        $data = json_decode($response, true);
    
        // Check if data exists and is an array
        if (isset($data['data']['data']) && is_array($data['data']['data'])) {
            
            // Use database transaction for better performance and consistency
            DB::beginTransaction();
            
            try {
                // Get existing installment IDs for this policy to determine what needs updating
                $existingInstallmentIds = PayMSchuduleTransection::whereIn(
                    'installmentid', 
                    collect($data['data']['data'])->pluck('installmentId')->filter()
                )->pluck('installmentid', 'installmentid')->toArray();
                
                $recordsToInsert = [];
                $recordsToUpdate = [];
                $now = Carbon::now();
                
                foreach ($data['data']['data'] as $key => $item) {
                    $installmentId = $item['installmentId'] ?? null;
                    
                    if (!$installmentId) {
                        continue; // Skip if no installment ID
                    }
                    
                    $recordData = [
                        'policyNumber' => $policyNumber,
                        'installment' => $key + 1,
                        'installmentid' => $installmentId,
                        'paymentArrangementId' => $item['paymentArrangementId'] ?? null,
                        'premium' => isset($item['installmentAmount']) ? $item['installmentAmount'] / 100 : null,
                        'billing_date' => isset($item['paymentDate']) 
                            ? Carbon::createFromTimestamp(substr($item['paymentDate'], 6, 10))->format('Y-m-d') 
                            : null,
                        'status' => $item['statusToString'] ?? null,
                        'updated_at' => $now,
                    ];
                    
                    if (isset($existingInstallmentIds[$installmentId])) {
                        // Existing record - add to update batch
                        $recordsToUpdate[] = $recordData;
                    } else {
                        // New record - add to insert batch
                        $recordData['created_at'] = $now;
                        $recordsToInsert[] = $recordData;
                    }
                }
                
                // Bulk insert new records
                if (!empty($recordsToInsert)) {
                    // Process in chunks to avoid memory issues
                    collect($recordsToInsert)->chunk(100)->each(function ($chunk) {
                        PayMSchuduleTransection::insert($chunk->toArray());
                    });
                }
                
                // Bulk update existing records
                if (!empty($recordsToUpdate)) {
                    foreach ($recordsToUpdate as $updateRecord) {
                        PayMSchuduleTransection::where('installmentid', $updateRecord['installmentid'])
                            ->update([
                                'policyNumber' => $updateRecord['policyNumber'],
                                'installment' => $updateRecord['installment'],
                                'paymentArrangementId' => $updateRecord['paymentArrangementId'],
                                'premium' => $updateRecord['premium'],
                                'billing_date' => $updateRecord['billing_date'],
                                'status' => $updateRecord['status'],
                                'updated_at' => $updateRecord['updated_at'],
                            ]);
                    }
                }
                
                DB::commit();
                
               
                
            } catch (\Exception $e) {
                DB::rollback();
              
                return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
            }
            
        } else {
            return response()->json(['error' => 'No valid data found in response'], 400);
        }
    
        return response()->json(['success' => 'Data stored successfully', 'data' => $data]);
    }
    public function getBankList()
   {
        $banks = PayM8Banks::all();

        return response()->json(['status' => 'success', 'banks' => $banks]);
   }
   public function getClientContractList($id)
   {
       try {
          
           
           $realpayContracts = PayMContract::where('policyNumber',$id)->get();

         


           return DataTables::of($realpayContracts)

           

           ->addColumn('actions', function ($realpayContracts) {

              if($realpayContracts->status == "Active"){

                $actions = '<button class="btn btn-danger" 
                data-id="' . $realpayContracts->id . '" 
                style="float:right; margin-top: 2%; margin-right: 10px;" 
                type="button" id="paymcancelContract">Cancel</button>';
                }else if($realpayContracts->status == "Cancelled"){
                    if($realpayContracts->action_by){
                        $actions = "Cancelled By ". User::find($realpayContracts->action_by)->firstName." ". User::find($realpayContracts->action_by)->lastName;
                    }else{
                        $actions = '';
                    }
                }else{
                    $actions = '';
              }
           
               return $actions;
           })
           ->editColumn('created_at', function ($realpayContracts) {
            if ($realpayContracts->created_at != null) {
                return   \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $realpayContracts->created_at)->format('Y-m-d H:i') ;
            }
        })

           ->rawColumns(['status','actions','created_at'])
           ->make(true);

       } catch (\Exception $ex) {
           return $ex;
       }
   }
   public function getSchuduleList($id)
   {
       try {
          
           
           $realpayContracts = PayMSchuduleTransection::where('policyNumber',$id)->get();

         


           return DataTables::of($realpayContracts)

           

           ->addColumn('actions', function ($realpayContracts) {
              
            $actions = "";
                
            if($realpayContracts->status == "Future"){
                $actions = '<button class="btn btn-danger" 
                    data-id="' . $realpayContracts->id . '" 
                    style="float:right; margin-top: 2%; margin-right: 10px;" 
                    type="button" id="paymCancelSchedule">Cancel</button>';
            }
            if($realpayContracts->status == "Cancelled"){
                if($realpayContracts->action_by){
                    $actions = "Cancelled By ". User::find($realpayContracts->action_by)->firstName." ". User::find($realpayContracts->action_by)->lastName;
                }else{
                    $actions = '';
                }
            }
               

               return $actions;
           })
           ->editColumn('created_at', function ($realpayContracts) {
            if ($realpayContracts->created_at != null) {
                return   \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $realpayContracts->created_at)->format('Y-m-d H:i') ;
            }
        })

           ->rawColumns(['status','actions','created_at'])
           ->make(true);

       } catch (\Exception $ex) {
           return $ex;
       }
   }
   public function getUpdateSchuduleList($id)
   {
        $realpayContracts = PayMContract::where('policyNumber',$id)->orderBy('id','desc')->first();
        if($realpayContracts){
            $this->fetchPayM8Installments($realpayContracts->contractid, $id);
            return response()->json(['status' => 'success'],200);
        }
        return response()->json(['status' => 'error'],400);
   }
   public function redoPaymentFromStart(Request $request)
    {
        try {
            if(!isset($request->searchValue) || $request->searchValue == null){
                return response()->json(['status' => false, 'message'=>'Please provide policy number.' ], 403);
            }
    
            if(!Policy::where('policyNumber', $request->searchValue)->exists())
            {
                return response()->json(['status' => false, 'message'=>'Policy number not found.' ], 400);
            }
            $policy_check = Policy::where('policyNumber',$request->searchValue)->first();
            
                
            $clientContracts = PayMContract::where('policyNumber', $policy_check->policyNumber)->where('status',"Active")
                    ->orderBy('id','desc')
                    ->first();
            if($clientContracts){
                return response()->json(['status' => false, 'message'=>'Policy number Contract already created' ], 400);
            }
            $customerBanking = CustomerBanking::where('policy_id',$policy_check->id)->orderBy('id', 'DESC')->first();
            // Capture the outgoing payment method BEFORE we overwrite it, for the
            // payment-conversion audit log below.
            $oldPaymentMethod = ($customerBanking && $customerBanking->billing != null) ? $customerBanking->billing : null;
            if(!$customerBanking){
                $customerBanking = new CustomerBanking();
                $customerBanking->policy_id = $policy_check->id;
            }
            $customerBanking->branchCode = $request->branchCode;
            $customerBanking->accountNumber = $request->accountNumber;
            $customerBanking->accountType = $request->accountType;
            $customerBanking->billing = "PayM8";
            $customerBanking->save();

            // Payment-conversion audit (parity with graphiteBWV8) — the agent-driven
            // PayM8 reprocess from start now sends a verified agent_id.
            $updateCOntract = new UpdateContract();
            $updateCOntract->policyNumber       = $policy_check->policyNumber;
            $updateCOntract->new_payment_method = "PayM8_start";
            $updateCOntract->old_payment_method = $oldPaymentMethod;
            $updateCOntract->agent              = (isset($request->agent_id) && $request->agent_id != null) ? $request->agent_id : null;
            $updateCOntract->save();

              
            $policy = Policy::where('policyNumber',$request->searchValue)->first();
            $policy->billingStartDate = \Carbon\Carbon::createFromFormat('d/m/Y', $request->billing_date)->format('Y-m-d');
            $policy->BillingStart = $request->BillingStart;
            if($request->BillingStart=="Later"){
                $policy->isVirtualBox = 1;
            }else{
                $policy->isVirtualBox = null;
            }
            $policy->save();

            $this->createAdHocPayment($policy->id);

            return response()->json(['status' => true, 'message' => 'PayM8 Contract created successfully', 'type' => 'success'], 200);
              

          

        } catch (\Exception $ex) {
            return response()->json(['status' => '401', 'message' => $ex->getMessage()], 401);
        }
    }
    public function webhook(Request $request)
    {
        $paym = new PayMProcess();
        $paym->response = json_encode($request->all());
        $paym->save();

        // Previously this webhook only stored the raw payload and returned HTTP 400,
        // which (a) told PayM8 the delivery FAILED (retry storm) and (b) created no
        // payment_transactions — collections landed only via the 7-day poller
        // (paymtransaction:cron), often outside the ledger sweep's date window.
        // Now: on a captured event, create the canonical payment_transactions row so
        // it can reach the ledger/statement, and ACK with HTTP 200. The poller stays
        // the backstop. Defensive: the payload shape is not documented in-repo, so we
        // tolerate a few nesting shapes and no-op (still 200) if fields are absent.
        try {
            $payload = $request->all();
            $txn = $payload;
            if (isset($payload['data']) && is_array($payload['data'])) {
                $txn = $payload['data'];
            } elseif (isset($payload['transaction']) && is_array($payload['transaction'])) {
                $txn = $payload['transaction'];
            }

            $installmentId = $txn['installmentId'] ?? ($payload['installmentId'] ?? null);
            $outcome       = $txn['status'] ?? ($payload['status'] ?? null);

            // Only act on a captured/successful outcome (4 or 7 — the same codes the
            // poller treats as SUCCESS). Idempotent via referenceNumber existence.
            if ($installmentId != null && ($outcome == 4 || $outcome == 7)
                && !PaymentTransaction::where('referenceNumber', $installmentId)->exists()) {
                $paymschtr = PayMSchuduleTransection::where('installmentid', $installmentId)->first();
                if ($paymschtr) {
                    $policy = Policy::where('policyNumber', $paymschtr->policyNumber)->first();
                    if ($policy) {
                        $today = Carbon::now()->format('Y-m-d');
                        $paymentTransaction = new PaymentTransaction();
                        $paymentTransaction->policyNumber            = $paymschtr->policyNumber;
                        $paymentTransaction->policy_id               = $policy->id;
                        $paymentTransaction->referenceNumber         = $installmentId;
                        $paymentTransaction->amount                  = $paymschtr->premium;
                        $paymentTransaction->status                  = 'SUCCESS';
                        $paymentTransaction->paymentDate             = Carbon::now()->format('Y-m-d H:i:s');
                        $paymentTransaction->new_payment_date        = $today;
                        $paymentTransaction->paymentMethod           = 'PayM8';
                        $paymentTransaction->is_ledger               = 0;
                        $paymentTransaction->numberOfInstalmentsPaid = $paymschtr->installment;
                        $paymentTransaction->paymentFrequency        = $policy->premium_freq;
                        $paymentTransaction->TransID                 = $txn['id'] ?? null;
                        $paymentTransaction->note                    = $txn['outcomeCode'] ?? null;
                        $paymentTransaction->reason                  = $txn['outcomeDescription'] ?? null;
                        $paymentTransaction->save();
                    }
                }
            }
        } catch (\Exception $ex) {
            Log::error('PayM8 webhook tx creation failed: ' . $ex->getMessage());
        }

        return response()->json(['status' => 'success'], 200);
    }
   

}
