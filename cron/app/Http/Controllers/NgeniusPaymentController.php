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

class NgeniusPaymentController extends Controller
{
     public function NgeniusPayment(Request $request)
    {
        
       if(!isset($request->searchValue) || $request->searchValue == null){
            return response()->json(['status' => false, 'message'=>'Please provide policy number.' ], 403);
        }
        if(!Policy::where('policyNumber', $request->searchValue)->exists())
        {
            return response()->json(['status' => false, 'message'=>'Policy number not found.' ], 400);
        }
        
        if(Policy::where('policyNumber', $request->searchValue)->where('premium_freq',2)->exists() )
        {
            return response()->json(['status' => false, 'message'=>'Policy number payment not allow change premium  frequency monthly or yearly' ], 400);
        }
        if(Policy::where('policyNumber', $request->searchValue)->where('status',2)->exists()){
            return response()->json(['status' => false, 'message'=>'Cancelled Policy Not Allowed' ], 400); 
            
         }
        $policyNumber = $request->searchValue;
        $policy       = Policy::FindPolicyWithCustomer($policyNumber);
        
        
        if($policy->premium_freq == 3){
            if(isset($request->leadSource) && $request->leadSource == "start.alphadirect.co.bw"){
                $leadSource = "start";
            }else{
                $leadSource = "quote";
            }
            $payData =  $this->RequestAccessToken($policyNumber,$leadSource);
            // dd($payData);
            return response()->json(['status' => true, 'type'=>'direct','url' => $payData->_links->payment->href ], 200);

        }else{
            $amount = $this->NgeniusTransectionAmount($policyNumber);
           
            $data = base64_encode($policyNumber.'_'.$amount);

             if(isset($request->leadSource) && $request->leadSource == 'graphite')
                {
                    $link = env('START_URL').'ngeniusPayment?'.$data;
                }
                elseif(isset($request->leadSource) && $request->leadSource == 'MobileApp')
                {
                    $link = env('START_URL').'ngeniusPayment?'.$data;
                }
                elseif(isset($request->leadSource) && $request->leadSource == 'start.alphadirect.co.bw')
                {
                    $link = env('START_URL').'ngeniusPayment?'.$data;
                }
                else{
                    $link = env('LIVEQUOTE_URL').'ngeniusPayment.php?'.$data;
                }
           
          //  $link = env('START_URL').'ngeniusPayment.php?'.$data;
           return response()->json(['status' => true, 'type'=>'recurring', 'url' => $link ,'policyNumber'=>$policyNumber], 200);
        }

    }
    public function RequestAccessToken($policyNumber,$leadSource)
    {
        $leadSource = $leadSource;
        $outlet = env('NGENIUS_TWOSTAGE_OUTLETS_KEY'); 
        $apikey = env('NGENIUS_API_KEY'); 
        $idData = $this->identify();
        if (isset($idData->access_token)) { 
        $token = $idData->access_token;
        $payData = $this->pay($token, $outlet,$policyNumber,$leadSource);
        return $payData;
        }else{
        return response()->json(['status' => false, 'message'=>'Server Error' ], 400);
        }
    }
    function identify() 
    {
       
        $apikey = env('NGENIUS_API_KEY'); 
       
        $idUrl = "https://api-gateway.sandbox.stanbicbank.co.bw/identity/auth/access-token";
        $idHead = array(
            "content-type: application/vnd.ni-identity.v1+json",
            "accept: application/vnd.ni-identity.v1+json",
            "authorization: Basic ".$apikey
            ); 
        $idPost = null; 
        $idOutput = $this->invokeCurlRequest("POST", $idUrl, $idHead, $idPost);
        return $idOutput;
    }
    function pay($token, $outlet,$policyNumber,$leadSource)
    {
       
        $policy = Policy::FindPolicyWithCustomer($policyNumber);
        if($policy->customer->email != null){
        $email = $policy->customer->email;
        }else{
        $email = null;
        }
        $amountx         = $this->NgeniusTransectionAmount($policyNumber);
        $amount2 = $amountx;
        $amount = $amountx * 100;
        $ord = new \stdClass;
        $ord->action = "SALE";
        $ord->amount = new \stdClass;
        $ord->amount->currencyCode = "BWP";
        $ord->amount->value = (int)$amount;
        $ord->emailAddress = $email;
        $ord->merchantAttributes = new \stdClass;
        $ord->merchantAttributes->redirectUrl = env("GRAPHITE_URL")."api/NgeniusResponse";
        $ord->merchantAttributes->merchantOrderReference = $outlet;
        $ord->merchantAttributes->cancelUrl =  env("GRAPHITE_URL")."api/NgeniusCancelResponse";
        $ord->merchantAttributes->skip3DS =  true;
        $ord->merchantAttributes->skipConfirmationPage =  true;
        $ord->merchantAttributes->policyNumber =  $policyNumber;
        $ord->merchantAttributes->leadSource =  $leadSource;
        $ord->billingAddress = new \stdClass;
        $ord->billingAddress->firstName = $policy->customer->firstName;
        $ord->billingAddress->lastName = $policy->customer->lastName;
        
        $payUrl = "https://api-gateway.sandbox.stanbicbank.co.bw/transactions/outlets/$outlet/orders";
        $payHead = array("Authorization: Bearer ".$token, "Content-Type: application/vnd.ni-payment.v2+json", "Accept: application/vnd.ni-payment.v2+json");
        $payPost = json_encode($ord); 
        $payOutput = $this->invokeCurlRequest("POST", $payUrl, $payHead, $payPost, true);
        //data input save
        $ord->amount->value = $amount2;
        $trx = new NgeniusTransection();
        $trx->policy_id =  $policy->id;
        $trx->policy_number =  $policyNumber;
        $trx->input =  json_encode($ord);
        $trx->output =  json_encode($payOutput);
        $trx->save();
        return $payOutput;

     }
    function invokeCurlRequest($type, $url, $headers, $post) 
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);       
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        if ($type == "POST" || $type == "PUT") {
        curl_setopt($ch, CURLOPT_POST, 1);
        if ($type == "PUT") {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        }
        }else{
        curl_setopt($ch, CURLOPT_POST, 0);
        }
        $server_output = curl_exec ($ch);
        curl_close ($ch);
        return json_decode($server_output);
    }

    public function NgeniusResponse(Request $request)
    {
        if(isset($request->ref)){
        $ref = $request->ref;
        $idData = $this->identify();
        if (isset($idData->access_token)) { 
        $token = $idData->access_token;
        }else{
        $token = null;
        return response()->json(['status' => false, 'message'=>'Server Error' ], 400);
        }
        $outlet = env('NGENIUS_TWOSTAGE_OUTLETS_KEY'); 
      
        $RetrieveUrl = "https://api-gateway.sandbox.ngenius-payments.com/transactions/outlets/$outlet/orders/$ref";
        $payHead = array("Authorization: Bearer ".$token, "Content-Type: application/vnd.ni-payment.v2+json", "Accept: application/vnd.ni-payment.v2+json");
        $payPost = null; 
        $payOutput = $this->invokeCurlRequest("GET",  $RetrieveUrl, $payHead, $payPost, true);
        if($payOutput->_embedded->payment[0]->state == "CAPTURED"){
            $policyNumber = $payOutput->merchantAttributes->policyNumber;
            $policy         = Policy::FindPolicyWithCustomer($policyNumber);          
            $trx = NgeniusTransection::where('policy_number',$policyNumber)->orderby('id','desc')->first();
            $trx->output = json_encode($payOutput);
            $trx->reference = $payOutput->reference;
            $trx->outletId = $payOutput->outletId;
            $trx->status = 1;
            $trx->save();
           /* $paymentTransaction                          = new PaymentTransaction();
            $paymentTransaction->policyNumber            = $policy->policyNumber;
            $paymentTransaction->referenceNumber         = $payOutput->reference;
            $paymentTransaction->amount                  = ($payOutput->_embedded->payment[0]->amount->value) / 100;
            $paymentTransaction->status                  = "SUCCESS";
            $paymentTransaction->paymentDate             =  Carbon::now();
            $paymentTransaction->paymentMethod           = 'N-Genius';
            $paymentTransaction->paymentFrequency        = $policy->premium_freq;
            $paymentTransaction->TransID                 = $payOutput->reference;
            $paymentTransaction->CCDapproval             = null;
            $paymentTransaction->PnrID                   = null;
            $paymentTransaction->TransactionToken        = null;
            $paymentTransaction->CompanyRef              = $payOutput->reference;
            $paymentTransaction->save(); */
         /*   if($policy->status == 0){
            $policy->policyActivatedDate = Carbon::now();
            $policy->status = 1;
            $policy->save();
            } */
            $notification = array(
                'message'      => 'Payment Done successfully',
                'alert-type'   => 'success',
                'policyNumber' => $policyNumber,
                'amount'       => ($payOutput->_embedded->payment[0]->amount->value) / 100
            );
            //$sms = new SmsMessaging();
            //$sms->sendPaySuccessSMS(4, $policy->customer->cellphone, ($payOutput->_embedded->payment[0]->amount->value) / 100);
           
            if(isset($request->leadSource) && $request->leadSource == 'graphite')
            {
                // return redirect()->route('admin.policy.payWithDpo')->with('success', 'Payment done successfully.');
            }
            elseif(isset($request->leadSource) && $request->leadSource == 'MobileApp')
            {
                return response()->json(['status' => true, 'message' => 'Payment Done succesfully.', 'type' => 'success'], 200);
            }
            elseif(isset($payOutput->merchantAttributes->leadSource) && $payOutput->merchantAttributes->leadSource == 'start')
            {
                return redirect(env('START_URL').'thank-you-payment-successful?policyNumber='.$policyNumber.'&amount='. ($payOutput->_embedded->payment[0]->amount->value) / 100 );
            }
            else{
                $notification = array(
                    'message'      => 'Payment Done successfully',
                    'alert-type'   => 'success',
                    'policyNumber' => $policyNumber,
                    'amount'       => ($payOutput->_embedded->payment[0]->amount->value) / 100
                );
                return redirect(env('LIVEQUOTE_URL').'thank_you_payment_successfull.php?policyNumber='.$policyNumber )->with('notification', $notification);
            }
            return redirect(env('LIVEQUOTE_URL').'thank_you_payment_successfull.php?policyNumber='.$policyNumber )->with('notification', $notification);
        }elseif($payOutput->_embedded->payment[0]->state == "FAILED" ){
            $policyNumber = $payOutput->merchantAttributes->policyNumber;
            $policy         = Policy::FindPolicyWithCustomer($policyNumber);
            $trx = NgeniusTransection::where('policy_number',$policyNumber)->orderby('id','desc')->first();
            $trx->output = json_encode($payOutput);
            $trx->reference = $payOutput->reference;
            $trx->outletId = $payOutput->outletId;
            $trx->status = 0;
            $trx->save();
           /* $paymentTransaction                          = new PaymentTransaction();
            $paymentTransaction->policyNumber            = $policy->policyNumber;
            $paymentTransaction->referenceNumber         = $payOutput->reference;
            $paymentTransaction->amount                  = ($payOutput->_embedded->payment[0]->amount->value) / 100;
            $paymentTransaction->status                  = "FAILED";
            $paymentTransaction->paymentDate             =  Carbon::now();
            $paymentTransaction->paymentMethod           = 'N-Genius';
            $paymentTransaction->paymentFrequency        = $policy->premium_freq;
            $paymentTransaction->TransID                 = $payOutput->reference;
            $paymentTransaction->CCDapproval             = null;
            $paymentTransaction->PnrID                   = null;
            $paymentTransaction->TransactionToken        = null;
            $paymentTransaction->CompanyRef              = $payOutput->reference;
            $paymentTransaction->save();*/
            $notification = array(
                'message'      => 'Payment Failed',
                'alert-type'   => 'error',
                'policyNumber' => $policyNumber,
                'amount'       => ($payOutput->_embedded->payment[0]->amount->value) / 100
            );
            //$sms              = new SmsMessaging();
           // $sms->sendPaymentFailedSMS(21, $policy->cstomer->firstName, $policy->policyNumber, $policy->cstomer->cellphone, ($payOutput->_embedded->payment[0]->amount->value) / 100);
          
           return redirect(env('LIVEQUOTE_URL').'payment_failed.php?policyNumber='.$policyNumber )->with('notification', $notification);
           }else{
           return response()->json(['status' => false, 'message'=>'Server Error' ], 403);
        }
       }else{
        return response()->json(['status' => false, 'message'=>'Server Error' ], 403);
       }
    }
    public function NgeniusCancelResponse(Request $request)
    {
        if(isset($request->ref)){
            $ref = $request->ref;
            $idData = $this->identify();
            if (isset($idData->access_token)) { 
            $token = $idData->access_token;
            }else{
            $token = null;
            return response()->json(['status' => false, 'message'=>'Server Error' ], 400);
            }
            $outlet = env('NGENIUS_TWOSTAGE_OUTLETS_KEY'); 
          
            $RetrieveUrl = "https://api-gateway.sandbox.ngenius-payments.com/transactions/outlets/$outlet/orders/$ref";
            $payHead = array("Authorization: Bearer ".$token, "Content-Type: application/vnd.ni-payment.v2+json", "Accept: application/vnd.ni-payment.v2+json");
            $payPost = null; 
            $payOutput = $this->invokeCurlRequest("GET",  $RetrieveUrl, $payHead, $payPost, true);
            if(isset($payOutput->merchantAttributes->leadSource) && $payOutput->merchantAttributes->leadSource == 'start')
            {
                return redirect(env('START_URL'));
            }else{
                return redirect(env('LIVEQUOTE_URL'));
            }
        }else{
            return redirect(env('LIVEQUOTE_URL'));
        }
       
       
    }
    public function NgeniusRecurring(Request $request)
    {  
      
        if(!isset($request->searchValue) || $request->searchValue == null){
            return response()->json(['status' => false, 'message'=>'Please provide policy number.' ], 403);
        }
        if(!Policy::where('policyNumber', $request->searchValue)->exists())
        {
            return response()->json(['status' => false, 'message'=>'Policy number not found.' ], 400);
        }
       
        if(Policy::where('policyNumber', $request->searchValue)->where('premium_freq',2)->exists() || Policy::where('policyNumber', $request->searchValue)->where('premium_freq',3)->exists() )
        {
            return response()->json(['status' => false, 'message'=>'Policy number payment not allow change premium  frequency monthly' ], 400);
        }
        if(NgeniusTransection::where('policy_number', $request->searchValue)->where('status',1)->where('recurring_data','!=',null)->exists() )
        {
            return response()->json(['status' => false, 'message'=>'Policy number payment Already Done' ], 400);
        }
        
        $policyNumber = $request->searchValue;
        $policy       = Policy::where('policyNumber',$policyNumber)->first();
        
        $email          = $policy->customer->email;
        $frequencyid    = $policy->premium_freq;
        if(isset($request->amount))
        {
        $amount         = (float)str_replace(',' ,'' , number_format($request->amount ,2 ,'.', ','));
        }else{
        $amount         = $this->NgeniusTransectionAmount($policyNumber);
        }
        $amount2 = $amount;
        $amount = $amount*100;
        $cardNumber     = str_replace(' ' ,'' ,base64_decode($request->cardNumber));
        $expiry         = $request->expiry;
        $cvv            = base64_decode($request->cvv);
        $cardholderName = $request->cardholderName;
        if($frequencyid == 1){
        $frequency = "MONTHLY";
        }else{
        $frequency = "MONTHLY";
        }
        $outletRecurring = env('NGENIUS_RECURRING_OUTLETS_KEY');
        $idData = $this->identify();
        $data = '{
            "order": {
                "action":"SALE",
                "channel": "MoTo",
                "type": "RECURRING",
                "frequency": "'.$frequency.'",
                "emailAddress": "'.$email.'",
                "amount": {
                    "currencyCode":"BWP",
                    "value": "'.$amount.'" 
                },
                "billingAddress": {
                    "firstName": "'.$policy->customer->firstName.'",
                    "lastName": "'.$policy->customer->lastName.'",
                    "city": "'.$policy->profile->address.'"
                },
                "merchantAttributes": {
                    "policyNumber":"'.$policy->policyNumber.'"
                }
               },
            "payment":{
                "pan":"'.$cardNumber.'",
                "expiry":"'.$expiry.'",
                "cvv":"'.$cvv.'",
                "cardholderName":"'.$cardholderName.'"
            }
            
        }';
        $cardNumber = $this->ccMasking($cardNumber);
        $data2 = '{
            "order": {
                "action":"SALE",
                "channel": "MoTo",
                "type": "RECURRING",
                "frequency": "'.$frequency.'",
                "emailAddress": "'.$email.'",
                "amount": {
                    "currencyCode":"BWP",
                    "value": "'.$amount2.'" 
                },
                "billingAddress": {
                    "firstName": "'.$policy->customer->firstName.'",
                    "lastName": "'.$policy->customer->lastName.'",
                    "city": "'.$policy->profile->address.'"
                },
                "merchantAttributes": {
                    "policyNumber":"'.$policy->policyNumber.'"
                }
               
            },
            "payment":{
                "pan":"'.$cardNumber.'",
                "expiry":"'.$expiry.'",
                "cardholderName":"'.$cardholderName.'"
            }
           

        }';

       
        $card = new NgeniusCard();
        $card->policy_id  = $policy->id;
        $card->policy_number  = $policyNumber;
        $card->card_number  = $cardNumber;
        $card->expiry_date  = $expiry;
        $card->name_on_card  = $cardholderName;
        $card->save();


        $trx = new NgeniusTransection();
        $trx->policy_id =  $policy->id;
        $trx->policy_number =  $policyNumber;
        $trx->input =  $data2;
        if (isset($idData->access_token)) { 
            $token = $idData->access_token;
            $payData = $this->RecurringPay($token, $outletRecurring,$data);

            $trx->output =  json_encode($payData);
            if(isset( $payData->state ) && ($payData->state == "ACTIVE")){
                $trx->status =  1;
                $trx->recurring_data =  json_encode($payData);
                $trx->reference =  $payData->reference;
                $trx->outletId =  $payData->outletId;
                $trx->save();
                //payment transeaction
              /*  $paymentTransaction                          = new PaymentTransaction();
                $paymentTransaction->policyNumber            = $policy->policyNumber;
                $paymentTransaction->referenceNumber         = $payData->reference;
                $paymentTransaction->amount                  = $amount2;
                $paymentTransaction->status                  = "SUCCESS";
                $paymentTransaction->paymentDate             =  Carbon::now();
                $paymentTransaction->paymentMethod           = 'N-Genius';
                $paymentTransaction->paymentFrequency        = $policy->premium_freq;
                $paymentTransaction->TransID                 = $payData->reference;
                $paymentTransaction->CCDapproval             = null;
                $paymentTransaction->PnrID                   = null;
                $paymentTransaction->TransactionToken        = null;
                $paymentTransaction->CompanyRef              = $payData->reference;
                $paymentTransaction->save(); */
                $card->card_status  = 1;
                $card->save();
              /*  if($policy->status == 0){
                $policy->policyActivatedDate = Carbon::now();
                $policy->billingStartDate = Carbon::now();
                $policy->status = 1;
                $policy->save();
                }
                */
                if(isset($request->leadSource) && $request->leadSource == "start.alphadirect.co.bw"){
                    $url =  env('START_URL').'thank-you-payment-success?policyNumber='.$policyNumber;
                }else{
                    $url =  env('LIVEQUOTE_URL').'thank_you_payment_successfull.php?policyNumber='.$policyNumber;
                }
                return response()->json(['status' => true,  'url' => $url], 200);
              
                
            }elseif(isset( $payData->state ) && ($payData->state == "FAILED")){
                $trx->status =  0;
                $trx->recurring_data =  json_encode($payData);
                $trx->reference =  $payData->reference;
                $trx->outletId =  $payData->outletId;
                $trx->save();
              /*  $paymentTransaction                          = new PaymentTransaction();
                $paymentTransaction->policyNumber            = $policy->policyNumber;
                $paymentTransaction->referenceNumber         = $payData->reference;
                $paymentTransaction->amount                  = $amount2;
                $paymentTransaction->status                  = "FAILED";
                $paymentTransaction->paymentDate             =  Carbon::now();
                $paymentTransaction->paymentMethod           = 'N-Genius';
                $paymentTransaction->paymentFrequency        = $policy->premium_freq;
                $paymentTransaction->TransID                 = $payData->reference;
                $paymentTransaction->CCDapproval             = null;
                $paymentTransaction->PnrID                   = null;
                $paymentTransaction->TransactionToken        = null;
                $paymentTransaction->CompanyRef              = $payData->reference;
                $paymentTransaction->save(); */
                $card->card_status  = 0;
                $card->save();
                return response()->json(['status' => false, 'message' => 'Payment failed, please try again later', 'type' => 'error'], 401);
            }else{
                $trx->status =  0;
                $trx->recurring_data =  null;  
                return response()->json(['status' => false, 'message' => 'Payment failed, please try again later', 'type' => 'error'], 401);
            }
            $trx->save();
            return response()->json(['status' => false, 'message' => 'Payment failed, please try again later', 'type' => 'error'], 401);
        }else{
            $trx->output =  null;
            $trx->status =  0;
            $trx->save();
            return response()->json(['status' => false, 'message' => 'Payment failed, please try again later', 'type' => 'error'], 401);
        }
    }
public function RecurringPay($token, $outletRecurring,$data)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api-gateway.sandbox.ngenius-payments.com/recurring-payment/outlets/'.$outletRecurring.'/orders',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS =>$data,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer '.$token,
            'Content-Type: application/json'
        ),
        ));
        $response = json_decode(curl_exec($curl));
        curl_close($curl);
        return $response;
    }
    public function NgeniusTransectionAmount($policyNumber)
    {
        $policy       = Policy::FindPolicyWithCustomer($policyNumber);
        if($policy->product_id == 3)
        {
        $frequency  = $policy->premium_freq;
        $policyController = new PolicyController;
        $premiumArr = $policyController->getMotorComprehensivePremium($policy->policyNumber);
        switch($frequency){
        case 1:
            $premium = (float)str_replace(',' ,'' , number_format($premiumArr['monthly'] ,2 ,'.', ','));
        break;

        case 3:
            $premium = (float)str_replace(',' ,'' , number_format($premiumArr['annual'] ,2 ,'.', ','));
        break;

        default:
        break;
        }
        }else{
            if($policy->BillingStart == 'Immediate' )
            {
                $premium =  (float)str_replace(',' ,'' ,number_format($policy->premium ,2 ,'.', ',')) ;
            }else{
                $premium = (float)str_replace(',' ,'' ,number_format($policy->premium ,2 ,'.', ',')) ;
            }
        }
        if ($policy->is_bundled == 1) {
            $PolicyBundled = PolicyBundled::where('policy_id',$policy->id)->where('product_id',3)->first();
            if($PolicyBundled != null && $PolicyBundled->frequency_mc == 1){
                $premium =  $policy->premium;
            }
        }

        $amount        = (float)str_replace(',' ,'' ,number_format($premium ,2 ,'.', ',')) ;
      
     return $amount;
    }
    public function NgeniusRecurringGetdata(Request $request)
    {

                $idData = $this->identify();
                if (isset($idData->access_token)) { 
                $token = $idData->access_token;
                }else{
                    $token = null;
                    return response()->json(['status' => false, 'message'=>'Server Error' ], 400);
                }
                $ngTrx = NgeniusTransection::where('policy_number',$request->policyNumber)->orderby('id','desc')->first();
               
                if(isset($ngTrx) && $ngTrx->reference != null){
                $outletid = $ngTrx->outletId;
                $ref = $ngTrx->reference;
                $curl = curl_init();
                curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api-gateway.sandbox.ngenius-payments.com/recurring-payment/outlets/'.$outletid.'/orders/'.$ref,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_POSTFIELDS => '',
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer '.$token
                ),
                ));
                $response = curl_exec($curl);
                curl_close($curl);
                //dd(($response));
                return json_decode($response);
            }else{
                return response()->json(['status' => false, 'message'=>'Data Not Found' ], 400); 
            }
              
    }
    public function NgeniusRecurringDeletedata(Request $request)
    {
                $idData = $this->identify2();
            if(isset($idData->access_token)) { 
                $token = $idData->access_token;
            }else{
                $token = null;
                return response()->json(['status' => false, 'message'=>'Server Error' ], 400);
            }
                $ngTrx = NgeniusTransection::where('policy_number',$request->policyNumber)->orderby('id','desc')->first();
        if(isset($ngTrx) && $ngTrx->reference != null){
                $outletid = $ngTrx->outletId ;
                $ref = $ngTrx->reference;
                $curl = curl_init();
                curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api-gateway.sandbox.ngenius-payments.com/recurring-payment/outlets/'.$outletid.'/orders/'.$ref,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_POSTFIELDS => '',
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer '.$token
                ),
                ));
                $response = json_decode(curl_exec($curl));
                $info = curl_getinfo($curl);
                curl_close($curl);
               //dd($info["http_code"]);
                if(isset($response->code) && $response->code == 400){
                return response()->json(['status' => false, 'message'=>'Cannot cancel cancelled order' ], 400);
            }else{
                return response()->json(['status' => true, 'message'=>'Successfully canceled recurring payment' ], 200);
            }
         }else{
                return response()->json(['status' => false, 'message'=>'Data Not Found' ], 400);   
         }
          
              
    }
    public function NgeniusRecurringDeletedata2($policyNumber)
    {
                $idData = $this->identify2();
            if(isset($idData->access_token)) { 
                $token = $idData->access_token;
            }else{
                $token = null;
             return 2;   
            }
                $ngTrx = NgeniusTransection::where('policy_number',$policyNumber)->where('recurring_data','!=',null)->where('status',1)->orderby('id','desc')->first();
            if(isset($ngTrx) && $ngTrx->reference != null){
                $outletid = $ngTrx->outletId ;
                $ref = $ngTrx->reference;
                $curl = curl_init();
                curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api-gateway.sandbox.ngenius-payments.com/recurring-payment/outlets/'.$outletid.'/orders/'.$ref,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_POSTFIELDS => '',
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer '.$token
                ),
                ));
                $response = json_decode(curl_exec($curl));
                $info = curl_getinfo($curl);
                curl_close($curl);
            if($info["http_code"] == 400 || $info["http_code"] == 404){
                return 0;
            }elseif($info["http_code"] == 204 || $info["http_code"] == 200){
                $ngTrx->status = 2;
                $ngTrx->save();
                return 1;
            }else{
                return 0;
            }
         }else{
                return 0; 
         }
          
              
    }

    public function identify2()
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://api-gateway.sandbox.stanbicbank.co.bw/identity/auth/access-token',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 60,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS =>'{
            "grantType": "password",
            "realmName": "StanbicBankBotswana",
            "userName": "'.env('NGENIUS_USERNAME').'",
            "password": "F9#vsf100}@3",
            "clientId": "NI_PORTAL_ID"
        }',
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/vnd.ni-identity.v1+json',
            'Accept: application/vnd.ni-identity.v1+json'
          ),
        ));
        $response = json_decode(curl_exec($curl));
        curl_close($curl);
        return $response;
    }
    function ccMasking($cardNumber, $maskingCharacter = 'X') {
       
        return substr($cardNumber, 0, 4) . str_repeat($maskingCharacter, strlen($cardNumber) - 8) . substr($cardNumber, -4);
    }
    public function ngeniuswebhook(Request $request)
    {
        
           $hook =   new NgeniusWebhook();
           $hook->output = json_encode($request->all());
           $hook->save();
           $hook->policy_number  = $request->order['merchantAttributes']['policyNumber'];
           if($request->eventName == "CAPTURED"){ $status = 1; }else{ $status = 0; }
           $hook->status  = $status;
           $hook->save();
           $ref = $request->order['reference'];
           $orderType = $request->order['type'];

           $verify = $this->ngeniuswebhookresponseVerify($ref,$orderType);

           return response()->json(['status' => true ], 200);
    }
    public function ngeniuswebhookresponseVerify($ref,$orderType)
    {
       if(isset($ref)){
        $ref = $ref;
        $orderType = $orderType;
        $idData = $this->identify();
        if (isset($idData->access_token)) { 
        $token = $idData->access_token;
        }else{
        $token = null;
        return 0;
        }
        if($orderType == "RECURRING"){
            $outlet = env('NGENIUS_RECURRING_OUTLETS_KEY'); 
        }else{
            $outlet = env('NGENIUS_TWOSTAGE_OUTLETS_KEY'); 
        }
        
      
        $RetrieveUrl = "https://api-gateway.sandbox.ngenius-payments.com/transactions/outlets/$outlet/orders/$ref";
        $payHead = array("Authorization: Bearer ".$token, "Content-Type: application/vnd.ni-payment.v2+json", "Accept: application/vnd.ni-payment.v2+json");
        $payPost = null; 
        $payOutput = $this->invokeCurlRequest("GET",  $RetrieveUrl, $payHead, $payPost, true);
        //dd($payOutput);
        if($payOutput->_embedded->payment[0]->state == "CAPTURED"){
            $policyNumber = $payOutput->merchantAttributes->policyNumber;
            $policy         = Policy::where('policyNumber',$policyNumber)->first();          
            $trx = NgeniusTransection::where('policy_number',$policyNumber)->orderby('id','desc')->first();
            $trx->output = json_encode($payOutput);
            $trx->reference = $payOutput->reference;
            $trx->outletId = $payOutput->outletId;
            $trx->status = 1;
            $trx->save();
            if(PaymentTransaction::where('referenceNumber',$payOutput->reference)->exists()){}else{
            $paymentTransaction                          = new PaymentTransaction();
            $paymentTransaction->policyNumber            = $policy->policyNumber;
            $paymentTransaction->referenceNumber         = $payOutput->reference;
            $paymentTransaction->amount                  = ($payOutput->_embedded->payment[0]->amount->value) / 100;
            $paymentTransaction->status                  = "SUCCESS";
            $paymentTransaction->paymentDate             =  Carbon::now();
            $paymentTransaction->paymentMethod           = 'N-Genius';
            $paymentTransaction->paymentFrequency        = $policy->premium_freq;
            $paymentTransaction->TransID                 = $payOutput->reference;
            $paymentTransaction->CCDapproval             = null;
            $paymentTransaction->PnrID                   = null;
            $paymentTransaction->TransactionToken        = null;
            $paymentTransaction->CompanyRef              = $payOutput->reference;
            $paymentTransaction->save();
            if($policy->status == 0){
            $policy->policyActivatedDate = Carbon::now();
            $policy->billingStartDate = Carbon::now();
            $policy->expiry_date = Carbon::now()->addYearNoOverflow();
            $policy->status = 1;
            $policy->save();
            }
        }
            return 1;
        
           
        }elseif($payOutput->_embedded->payment[0]->state == "FAILED" ){
            $policyNumber = $payOutput->merchantAttributes->policyNumber;
            $policy         = Policy::FindPolicyWithCustomer($policyNumber);
            
            $paymentTransaction                          = new PaymentTransaction();
            $paymentTransaction->policyNumber            = $policy->policyNumber;
            $paymentTransaction->referenceNumber         = $payOutput->reference;
            $paymentTransaction->amount                  = ($payOutput->_embedded->payment[0]->amount->value) / 100;
            $paymentTransaction->status                  = "FAILED";
            $paymentTransaction->paymentDate             =  Carbon::now();
            $paymentTransaction->paymentMethod           = 'N-Genius';
            $paymentTransaction->paymentFrequency        = $policy->premium_freq;
            $paymentTransaction->TransID                 = $payOutput->reference;
            $paymentTransaction->CCDapproval             = null;
            $paymentTransaction->PnrID                   = null;
            $paymentTransaction->TransactionToken        = null;
            $paymentTransaction->CompanyRef              = $payOutput->reference;
            $paymentTransaction->reason = $payOutput->_embedded->payment[0]->authResponse->resultMessage;
            $paymentTransaction->note = "Error Code is ".$payOutput->_embedded->payment[0]->authResponse->resultCode;

            $paymentTransaction->save();
            return 0;
        }else{
            return 0;
        }
        }else{
            return 0; 
        }
  }

}
