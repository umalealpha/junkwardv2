<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;
use AlphaDirect\Customer;
use AlphaDirect\CustomerBanking;
use AlphaDirect\KYC;
use AlphaDirect\User;
use AlphaDirect\Models\LlmApi;
use AlphaDirect\Policy;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Productplan;
use Log;
use Exception;
use DB;

class LlmApiCrontroller extends Controller
{
    protected $LLM_API_URL = "http://admin.insurance.co.bw/api/";
    protected $LLM_API_TOKEN = "rPMHslJ0uYJ8i3J9D37a2Z572nprQLdz";
    protected $LLM_API_SPONSOR_EMAIL = "arjuniyer@alphadirect.co.bw";

    public function RegisterAgent($ids)
    {
      try {
        $ids = $ids;
        if(isset($ids) && $ids != []){
            $agents = User::whereIn('id',$ids)->get();
                if(count($agents) > 0){
                    foreach($agents as $agent){
                        $agent_id = $agent->id;
                        $email =$agent->email;
                        $first_name = $agent->firstName;
                        $last_name = $agent->lastName;
                        $llmApi = new LlmApi();
                        $llmApi->agent_id = $agent_id;
                        // $token ='token: '.env('LLM_API_TOKEN');
                        $token ='token: '.$this->LLM_API_TOKEN;
                            $fields = [
                                // 'sponsor_email' => env('LLM_API_SPONSOR_EMAIL'),
                                'sponsor_email' => $this->LLM_API_SPONSOR_EMAIL,
                                'email' => $email,
                                'password' => '12345678',
                                'agent_id' => $agent_id,
                                'type' => 'call_center',
                                'first_name' => $first_name,
                                'last_name' => $last_name,
                            ];

                            $llmApi->post_data = json_encode($fields);
                            $llmApi->status = 0;
                            $llmApi->save();
                            // $url = env('LLM_API_URL').'registerAgent';
                            $url = $this->LLM_API_URL.'registerAgent';
                            $headers = [
                                $token,
                            ];

                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            $response = curl_exec($ch);

                            curl_close($ch);
                            $resp = json_encode($response);
                            $resp2 = json_decode($response);
                            $llmApi->response_data = $resp;
                            //$llmApi->save();
                            if(isset($resp2->status) && $resp2->status == true){
                                $llmApi->status = 1;
                                $agent->llmUserStatus = 1;
                                $agent->save();
                            }
                           $llmApi->save();

                    }
                    return true;
                }
                return true;
         }
         return true;
        } catch (\Exception $ex) {
            Log::error(json_encode($ex->getMessage()));
            return null;
        }

     }
     public function RegisterCustomer($customer_id,$agent_id = 1)
    {
       // Log::info($customer_id.'-'.$agent_id);
      try {
        $id = $customer_id;

        if(isset($id) && $id != null){
            $customer = Customer::where('id',$id)->first();
                if($customer){
                        $email =$customer->email;

                        if (empty($email)) {
                            $firstName = preg_replace('/\s+/', '', strtolower($customer->firstName));
                            $lastName = preg_replace('/\s+/', '', strtolower($customer->lastName));

                            $email = $firstName . $lastName . '@mlmapicustomer.com';
                        }

                        $first_name = $customer->firstName;
                        $last_name = $customer->lastName;
                        $llmApi = new LlmApi();
                        $llmApi->agent_id = $agent_id;
                        $llmApi->customer_id = $customer->id;
                        // $token ='token: '.env('LLM_API_TOKEN');
                        $token ='token: '.$this->LLM_API_TOKEN;
                           $fields = [
                                'agent_id' => $agent_id,
                                'email' => $email,
                                'password' => '12345678',
                                'customer_id' => $customer->id,
                                'type' => 'customer',
                                'first_name' => $first_name,
                                'last_name' => $last_name,
                            ];
                            $llmApi->post_data = json_encode($fields);
                            $llmApi->status = 0;
                            $llmApi->save();
                            // $url =  env('LLM_API_URL').'register';
                            $url = $this->LLM_API_URL.'register';
                            $headers = [
                                $token,
                            ];
                           // Log::info($customer_id.'-'.$agent_id);
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            $response = curl_exec($ch);

                            curl_close($ch);
                            $resp = json_encode($response);
                            $resp2 = json_decode($response);
                            $llmApi->response_data = $resp;
                            $llmApi->save();
                            if(isset($resp2->status) && $resp2->status == true){
                            $llmApi->status = 1;
                            $customer->llmCustomerStatus = 1;
                            $customer->save();
                            }
                           $llmApi->save();
                           //Log::info($customer_id.'-'.$agent_id);
                           return true;

                }
                return true;
         }
         return true;
        } catch (\Exception $ex) {
            Log::error(json_encode($ex->getMessage()));
            return null;
        }
     }
     public function SalePolicy($policyNumber)
    {
      try {
            $id = $policyNumber;
            $agent_id = 1;
            if(isset($id) && $id != null){
                $policy = Policy::where('policyNumber',$id)->first();
                    if($policy){
                        if($policy->agent_id != null){
                            $agent_id = $policy->agent_id;
                        }

                        $banking = CustomerBanking::where('policy_id', $policy->id)->first();

                        if (!$banking) {
                            return response()->json(['error' => 'Banking not found.'], 404);
                        }

                        if (empty($banking->billing)) {
                            $banking->billing = 'CASH';
                        }

                        $payment_method = $this->getPaymentUniqueId($banking->billing);

                        $categoryId = $this->getUniqueCategoryIdByPlanId($policy->plan_id);

                            $llmApi = new LlmApi();
                            $llmApi->agent_id = $agent_id;
                            $llmApi->customer_id = $policy->customer_id;
                            $llmApi->policyNumber = $policy->policyNumber;
                            $fields = [
                                'customer_id' => $policy->customer_id,
                                'amount' => $policy->premium,
                                'order_id' => $policy->policyNumber,
                                'agent_id' => $agent_id,
                                'category' => $categoryId,
                                'payment_method' => $payment_method
                            ];
                                $llmApi->post_data = json_encode($fields);
                                $llmApi->status = 0;
                                $llmApi->save();
                                // $token ='token: '.env('LLM_API_TOKEN');
                                $token ='token: '.$this->LLM_API_TOKEN;
                                // $url = env('LLM_API_URL').'sales';
                                $url = $this->LLM_API_URL.'sales';
                                $headers = [
                                    $token,
                                ];

                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $url);
                                curl_setopt($ch, CURLOPT_POST, 1);
                                curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
                                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                $response = curl_exec($ch);

                                curl_close($ch);

                                $resp2 = json_decode($response);
                                $llmApi->response_data = $response;
                                $llmApi->save();
                                //dd($resp2);
                               if(isset($resp2->status) && $resp2->status == true){
                                $llmApi->status = 1;
                                $llmApi->save();
                                $policy->llmSaleStatus = 1;
                                $policy->save();
                                }
                                return true;
                       }
                       return true;
             }
             return true;
        } catch (\Exception $ex) {
            Log::error(json_encode($ex->getMessage()));
            return null;
        }
     }
     public function PaymentConfirmation($trxn)
     {
       try{
        $agent_id = 1;
        if(LlmApi::where('policyNumber',$trxn->policyNumber)->where('payment_transection_id','!=',null)->exists()){
          $this->RecurringPremiumPayment($trxn);
        }else{
            $status = $trxn->status;
            $policy = Policy::where('policyNumber',$trxn->policyNumber)->first();

                if($policy){
                        if($status == "success" || $status == "Success" || $status == "SUCCESS" || $status == 1){
                            $status = "COMPLETED";
                        }else{
                            $status = "FAILED";
                        }

                            $llmApi = new LlmApi();
                            $llmApi->agent_id = $agent_id;

                            $fields = [
                                'customer_id' => $policy->customer_id,
                                'status' => $status,
                                'order_id' => $policy->policyNumber,
                                'payment_reference_number' => $trxn->referenceNumber,
                            ];
                            $llmApi->post_data = json_encode($fields);
                            $llmApi->policyNumber = $policy->policyNumber;
                            $llmApi->payment_transection_id = $trxn->id;
                            $llmApi->customer_id = $policy->customer_id;
                            $llmApi->agent_id = $policy->agent_id;
                            $llmApi->status = 0;
                            $llmApi->save();
                            // $url = env('LLM_API_URL').'paymentConfirmation';
                            $url = $this->LLM_API_URL.'paymentConfirmation';
                            // $token ='token: '.env('LLM_API_TOKEN');
                            $token ='token: '.$this->LLM_API_TOKEN;
                            $headers = [
                                $token,
                            ];

                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            $response = curl_exec($ch);

                            curl_close($ch);
                            $resp = json_encode($response);
                            $resp2 = json_decode($response);
                            $llmApi->response_data = $resp;
                            $llmApi->save();
                            if(isset($resp2->status) && $resp2->status == true){
                                $llmApi->status = 1;  //status 1 is payment conform
                                $llmApi->save();

                              $transaction = PaymentTransaction::where('policyNumber', $trxn->policyNumber)->where('id',$trxn->id)->first();
                              if (isset($transaction)) {
                                $transaction->llmPaymentStatus = 1;
                                $transaction->save();
                              }

                            }


                           return true;

                }
                return true;
         }
         return true;
        }catch(Exception $ex){
            Log::error(json_encode($ex->getMessage()));
            return null;

        }
     }
     public function RecurringPremiumPayment($trxn)
    {

        try{
             $policy = Policy::where('policyNumber',$trxn->policyNumber)->first();
             if($policy){

                        $banking = CustomerBanking::where('policy_id', $policy->id)->first();

                        if (!$banking) {
                            return response()->json(['error' => 'Banking not found.'], 404);
                        }

                        $payment_method = $this->getPaymentUniqueId($trxn->paymentMethod);

                        $categoryId = $this->getUniqueCategoryIdByPlanId($policy->plan_id);

                        $llmApi = new LlmApi();
                            $fields = [
                                    'customer_id' => $policy->customer_id,
                                    'amount' => $trxn->amount,
                                    'order_id' => $trxn->policyNumber,
                                    'payment_reference_number' => $trxn->referenceNumber,
                                    'category'                 => $categoryId,
                                    'payment_method'           => $payment_method
                                ];
                            $llmApi->post_data = json_encode($fields);
                            $llmApi->policyNumber = $trxn->policyNumber;
                            $llmApi->payment_transection_id = $trxn->id;
                            $llmApi->customer_id = $policy->customer_id;
                            $llmApi->agent_id = $policy->agent_id;
                            $llmApi->status = 0;
                            $llmApi->save();
                            // $url = env('LLM_API_URL').'recurringPremiumPayment';
                            $url = $this->LLM_API_URL.'recurringPremiumPayment';
                            // $token ='token: '.env('LLM_API_TOKEN');
                            $token ='token: '.$this->LLM_API_TOKEN;
                            $headers = [
                                $token,
                            ];

                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                            $response = curl_exec($ch);
                            if (curl_errno($ch) == CURLE_OPERATION_TIMEDOUT) {
                                Log::info("Timeout occurred for URL");
                                curl_close($ch);
                                return null;

                            } elseif (curl_errno($ch)) {
                                Log::info("cURL error");
                                curl_close($ch);
                                return null;

                            }
                            curl_close($ch);
                            $resp = json_encode($response);
                            $resp2 = json_decode($response);
                            $llmApi->response_data = $resp;
                            $llmApi->save();
                            if(isset($resp2->status) && $resp2->status == true){
                              $llmApi->status = 1;
                              $llmApi->save();

                              $transaction = PaymentTransaction::where('policyNumber', $trxn->policyNumber)->where('id',$trxn->id)->first();
                              if (isset($transaction)) {
                                $transaction->llmPaymentStatus = 1;
                                $transaction->save();
                              }
                            }

                           return true;
                        }
                        return true;
                    }catch(Exception $ex)
                    {
                        Log::error(json_encode($ex->getMessage()));
                        return null;

                    }
    }

    public function registerAgentsApi(Request $request)
{
    $ids = $request->input('ids');

    // Optional validation
    if (!is_array($ids) || empty($ids)) {
        return response()->json(['error' => 'Invalid or empty agent ID list.'], 400);
    }

    try {
        $result = $this->RegisterAgent($ids);
        return response()->json(['success' => $result]);
    } catch (\Exception $ex) {
        return response()->json(['error' => $ex->getMessage(), 'line' => $ex->getLine()], 500);
    }
}

public function registerCustomerFromApi(Request $request)
{
    $customer_id = $request->input('customer_id');
    $agent_id = $request->input('agent_id', 1); // default to 1 if not passed

    $success = $this->RegisterCustomer($customer_id, $agent_id);

    if ($success) {
        return response()->json(['message' => 'Customer registered successfully.']);
    } else {
        return response()->json(['message' => 'Customer not registered.'], 422);
    }
}

public function apiSalePolicy(Request $request)
{
    try {
        $policyNumber = $request->input('policyNumber');

        if (!$policyNumber) {
            return response()->json(['error' => 'Policy number is required.'], 400);
        }

        $agent_id = 1;
        $policy = Policy::where('policyNumber', $policyNumber)->first();

        if (!$policy) {
            return response()->json(['error' => 'Policy not found.'], 404);
        }

        if ($policy->agent_id !== null) {
            $agent_id = $policy->agent_id;
        }

        $banking = CustomerBanking::where('policy_id', $policy->id)->first();

        if (!$banking) {
            return response()->json(['error' => 'Banking not found.'], 404);
        }

        $payment_method = $this->getPaymentUniqueId($banking->billing);

        $categoryId = $this->getUniqueCategoryIdByPlanId($policy->plan_id);

        $llmApi = new LlmApi();
        $llmApi->agent_id = $agent_id;
        $llmApi->customer_id = $policy->customer_id;
        $llmApi->policyNumber = $policy->policyNumber;

        $fields = [
            'customer_id' => $policy->customer_id,
            'amount' => $policy->premium,
            'order_id' => $policy->policyNumber,
            'agent_id' => $agent_id,
            'category' => $categoryId,
            'payment_method' => $payment_method
        ];

        $llmApi->post_data = json_encode($fields);
        $llmApi->status = 0;
        $llmApi->save();

        // $token = 'token: ' . env('LLM_API_TOKEN');
        $token ='token: '.$this->LLM_API_TOKEN;
        // $url = env('LLM_API_URL') . 'sales';
        $url = $this->LLM_API_URL . 'sales';
        $headers = [$token];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $resp2 = json_decode($response);
        $llmApi->response_data = $response;
        $llmApi->save();

        if (isset($resp2->status) && $resp2->status == true) {
            $llmApi->status = 1;
            $llmApi->save();
        }

        return response()->json([
            // 'message' => 'Sale posted successfully.',
            'status' => $resp2->status ?? false,
            'llmApi_id' => $llmApi->id,
            'response' => $resp2,
        ]);
    } catch (\Throwable $e) {
        Log::error('SalePolicy API error', [
            'message' => $e->getMessage(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'error' => 'An error occurred while processing the sale policy.',
            'details' => $e->getMessage(),
            'line' => $e->getLine(),
        ], 500);
    }
}

public function apiPaymentConfirmation(Request $request)
{
    try {
        $policyNumber = $request->input('policyNumber');
        $status       = $request->input('status');
        $referenceNo  = $request->input('referenceNumber');
        $trxnId       = $request->input('id'); // transaction ID

        if (!$policyNumber || !$status || !$referenceNo || !$trxnId) {
            return response()->json(['error' => 'Missing required fields.'], 400);
        }

        $agent_id = 1;

        $existing = LlmApi::where('policyNumber', $policyNumber)
            ->whereNotNull('payment_transection_id')
            ->exists();

        if ($existing) {
            // You can call the recurring method or mock it
            // $this->RecurringPremiumPayment((object) $request->all());
            return response()->json(['message' => 'Recurring payment handled.']);
        }

        $policy = Policy::where('policyNumber', $policyNumber)->first();

        if (!$policy) {
            return response()->json(['error' => 'Policy not found.'], 404);
        }

        if ($policy->agent_id !== null) {
            $agent_id = $policy->agent_id;
        }

        // Normalize status
        $normalizedStatus = strtolower($status) === 'success' || $status == 1 ? 'COMPLETED' : 'FAILED';

        // Prepare record
        $llmApi = new LlmApi();
        $llmApi->agent_id = $agent_id;
        $llmApi->customer_id = $policy->customer_id;
        $llmApi->policyNumber = $policyNumber;
        $llmApi->payment_transection_id = $trxnId;

        $fields = [
            'customer_id'               => $policy->customer_id,
            'status'                    => $normalizedStatus,
            'order_id'                  => $policyNumber,
            'payment_reference_number' => $referenceNo,
        ];

        $llmApi->post_data = json_encode($fields);
        $llmApi->status = 0;
        $llmApi->save();

        // Send to LLM API
        // $url = env('LLM_API_URL') . 'paymentConfirmation';
        $url = $this->LLM_API_URL . 'paymentConfirmation';
        // $token = 'token: ' . env('LLM_API_TOKEN');
        $token ='token: '.$this->LLM_API_TOKEN;
        $headers = [$token];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $llmApi->response_data = $response;
        $llmApi->save();

        $resp2 = json_decode($response);
        if (isset($resp2->status) && $resp2->status == true) {
            $llmApi->status = 1;
            $llmApi->save();
        }

        return response()->json([
            // 'message'   => 'Payment confirmation sent.',
            'status'    => $resp2->status ?? false,
            'llmApi_id' => $llmApi->id,
            'response'  => $resp2,
        ]);
    } catch (\Throwable $e) {
        Log::error('PaymentConfirmation Error: ' . $e->getMessage());
        return response()->json([
            'error'   => 'Something went wrong.',
            'details' => $e->getMessage(),
        ], 500);
    }
}

public function apiRecurringPremiumPayment(Request $request)
{
    try {
        $policyNumber   = $request->input('policyNumber');
        $amount         = $request->input('amount');
        $referenceNo    = $request->input('referenceNumber');
        $trxnId         = $request->input('id');
        $payment_type = $request->input('paymentMethod');

        if (!$policyNumber || !$amount || !$referenceNo || !$trxnId) {
            return response()->json(['error' => 'Missing required fields.'], 400);
        }

        $policy = Policy::where('policyNumber', $policyNumber)->first();

        if (!$policy) {
            return response()->json(['error' => 'Policy not found.'], 404);
        }

        $agent_id = 1;

        if ($policy->agent_id !== null) {
            $agent_id = $policy->agent_id;
        }


        $banking = CustomerBanking::where('policy_id', $policy->id)->first();

        if (!$banking) {
            return response()->json(['error' => 'Banking not found.'], 404);
        }

        $payment_method = $this->getPaymentUniqueId($payment_type);

        $categoryId = $this->getUniqueCategoryIdByPlanId($policy->plan_id);

        $llmApi = new LlmApi();
        $fields = [
            'customer_id'               => $policy->customer_id,
            'amount'                    => $amount,
            'order_id'                  => $policyNumber,
            'payment_reference_number' => $referenceNo,
            'category'                 => $categoryId,
            'payment_method'           => $payment_method
        ];

        $llmApi->post_data = json_encode($fields);
        $llmApi->policyNumber = $policyNumber;
        $llmApi->payment_transection_id = $trxnId;
        $llmApi->customer_id = $policy->customer_id;
        $llmApi->agent_id = $agent_id;
        $llmApi->status = 0;
        $llmApi->save();

        // $url = env('LLM_API_URL') . 'recurringPremiumPayment';
        $url = $this->LLM_API_URL . 'recurringPremiumPayment';
        // $token = 'token: ' . env('LLM_API_TOKEN');
        $token ='token: '.$this->LLM_API_TOKEN;
        $headers = [$token];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $response = curl_exec($ch);

        if (curl_errno($ch) == CURLE_OPERATION_TIMEDOUT) {
            Log::info("Timeout occurred for recurringPremiumPayment API.");
            curl_close($ch);
            return response()->json(['error' => 'Request timeout.'], 504);
        } elseif (curl_errno($ch)) {
            Log::info("cURL error occurred during recurringPremiumPayment.");
            curl_close($ch);
            return response()->json(['error' => 'Request failed due to cURL error.'], 502);
        }

        curl_close($ch);

        $llmApi->response_data = $response;
        $llmApi->save();

        $resp2 = json_decode($response);
        if (isset($resp2->status) && $resp2->status == true) {
            $llmApi->status = 1;
            $llmApi->save();
        }

        return response()->json([
            // 'message'   => 'Recurring premium payment processed.',
            'status'    => $resp2->status ?? false,
            'llmApi_id' => $llmApi->id,
            'response'  => $resp2,
        ]);
    } catch (\Throwable $ex) {
        Log::error('RecurringPremiumPayment Error: ' . $ex->getMessage());
        return response()->json([
            'error'   => 'Something went wrong.',
            'details' => $ex->getMessage(),
        ], 500);
    }
}

 public function kycStatusApi(Request $request)
    {
       // Log::info($customer_id.'-'.$agent_id);
      //try {
            $policy = Policy::where('policyNumber',$request->policyNumber)->first();
                if($policy){
                    $kyc = KYC::where('customer_id',$policy->customer_id)->first();

                        if (!$kyc) {
                            return response()->json(['error' => 'kyc not found.'], 404);
                        }

                        $normalizedStatus = strtolower($kyc->status) === 'approve' ? 'COMPLETED' : 'FAILED';

                        $llmApi = new LlmApi();
                        $llmApi->agent_id = $policy->agent_id;
                        $llmApi->customer_id = $policy->customer_id;
                        $llmApi->policyNumber = $policy->policyNumber;
                        // $token ='token: '.env('LLM_API_TOKEN');
                        $token ='token: '.$this->LLM_API_TOKEN;
                           $fields = [
                                'agent_id' => $policy->agent_id,
                                'kyc_status' => $normalizedStatus
                            ];
                            $llmApi->post_data = json_encode($fields);
                            $llmApi->status = 0;
                            $llmApi->save();
                            // $url =  env('LLM_API_URL').'kyc-status';
                            $url =  $this->LLM_API_URL.'kyc-status';
                            $headers = [
                                $token,
                            ];
                           // Log::info($customer_id.'-'.$agent_id);
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            $response = curl_exec($ch);

                            curl_close($ch);
                            $resp = json_encode($response);
                            $resp2 = json_decode($response);
                            $llmApi->response_data = $resp;
                            $llmApi->save();
                            if(isset($resp2->status) && $resp2->status == true){
                            $llmApi->status = 1;
                            }
                           $llmApi->save();
                           //Log::info($customer_id.'-'.$agent_id);
                        //    return true;

                }
                 return response()->json([
                    'status'    => $resp2->status ?? false,
                    'llmApi_id' => $llmApi->id,
                    'response'  => $resp2,
                ]);
        // } catch (\Exception $ex) {
        //     return response()->json(['error' => $ex->getMessage(), 'line' => $ex->getLine()]);
        // }
     }

      public function kycStatus($policyNumber)
    {
      try {
            $policy = Policy::where('policyNumber',$policyNumber)->first();
                if($policy){
                    $kyc = KYC::where('customer_id',$policy->customer_id)->first();

                        if (!$kyc) {
                            return response()->json(['error' => 'kyc not found.'], 404);
                        }

                        $normalizedStatus = strtolower($kyc->status) === 'approve' ? 'COMPLETED' : 'FAILED';

                        $llmApi = new LlmApi();
                        $llmApi->agent_id = $policy->agent_id;
                        $llmApi->customer_id = $policy->customer_id;
                        $llmApi->policyNumber = $policy->policyNumber;
                        // $token ='token: '.env('LLM_API_TOKEN');
                        $token ='token: '.$this->LLM_API_TOKEN;
                           $fields = [
                                'agent_id' => $policy->agent_id,
                                'kyc_status' => $normalizedStatus
                            ];
                            $llmApi->post_data = json_encode($fields);
                            $llmApi->status = 0;
                            $llmApi->save();
                            // $url =  env('LLM_API_URL').'kyc-status';
                            $url =  $this->LLM_API_URL.'kyc-status';
                            $headers = [
                                $token,
                            ];
                           // Log::info($customer_id.'-'.$agent_id);
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            $response = curl_exec($ch);

                            curl_close($ch);
                            $resp = json_encode($response);
                            $resp2 = json_decode($response);
                            $llmApi->response_data = $resp;
                            $llmApi->save();
                            if(isset($resp2->status) && $resp2->status == true){
                            $llmApi->status = 1;
                            }
                           $llmApi->save();
                           //Log::info($customer_id.'-'.$agent_id);
                           return true;

                }
                return true;
        } catch (\Exception $ex) {
            return response()->json(['error' => $ex->getMessage(), 'line' => $ex->getLine()]);
        }
    }

    // function getPaymentUniqueId($paymentMethod)
    // {
    //     // Define the mapping
    //     $paymentMap = [
    //         'VCS'      => 'V34C86S',
    //         'DPO'      => 'D50P5O5',
    //         'REALPAY'  => 'RE3AL7Y',
    //         'N-GENIUS'  => 'N56GE127',
    //         'CASH'     => 'CA34SH78',
    //         'ORANGEMONEY' => 'O5NG271Y'
    //     ];

    //     // Normalize input to uppercase
    //     $key = strtoupper(trim($paymentMethod));

    //     // Return unique ID or null if not found
    //     return $paymentMap[$key] ?? null;
    // }

    function getPaymentUniqueId($paymentMethod)
    {
        // Normalize the input
        $key = strtoupper(trim($paymentMethod));

        // Query the database
        $record = DB::table('payment_methods')
            ->whereRaw('UPPER(TRIM(name)) = ?', [$key])
            ->first();

        // Return unique_code if found
        return $record->unique_code ?? null;
    }


//     function getUniqueCategoryIdByPlanId(int $planId): ?string
// {
//     $planIdToCategoryId = [
//         1  => 'ADIP49B',   // Accidental Death Insurance - Bronze
//         // 2  => 'MPIP49B',   // Third Party Car Insurance
//         // 3  => 'MPIP49B',   // Third Party Car Insurance
//         4  => 'MPIP49B',   // Third Party Car Insurance - bronze
//         5  => 'LEINP49',   // Legal Insurance - Bronze
//         // 6  => 'LEINP49',   // Legal Insurance
//         // 7  => 'FINP79G',   // Funeral Insurance
//         8  => 'MOTCOMP',   // Motor Comprehensive
//         9  => 'CPDIP49',   // Mobile & Device Insurance - Bronze
//         // 10 => null,        // Tyre and Rim - not mapped
//         // 11 => null,        // Tyre and Rim - not mapped
//         // 12 => 'ADIP79G',   // Accidental Death Insurance - ADI_P79
//         13 => 'ADIP79G',   // Accidental Death Insurance - Gold
//         // 14 => null,        // Commercial Insurance - not mapped
//         // 15 => null,        // Domestic Insurance - not mapped
//         16 => 'MPIP79G',   // Third Party Car Insurance - P79
//         // 17 => 'CPDIP49',   // Mobile Device Insurance - Platinum
//         // 18 => 'LEINP49',   // Legal Insurance - P75 Group
//         // 19 => null,        // Hospital Cashback - not mapped
//         // 20 => null,        // Health in a Box (Basic) - not mapped
//         // 21 => null,        // Health in a Box (Plus) - not mapped
//         // 22 => 'LEINP49',   // Legal Insurance - Platinum
//     ];

//     return $planIdToCategoryId[$planId] ?? null;
// }

    function getUniqueCategoryIdByPlanId(int $planId): ?string
    {
        $record = Productplan::where('id', $planId)->value('plan_unique_id');

        return $record ?? null;
    }

}
