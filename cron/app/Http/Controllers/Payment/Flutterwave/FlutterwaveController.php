<?php

namespace AlphaDirect\Http\Controllers\Payment\Flutterwave;

use AlphaDirect\Customer;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Policy;
use AlphaDirect\Transaction;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Http\Controllers\Payment\VCS\PaymentController;
use AlphaDirect\VcsTransaction;
use AlphaDirect\FlutterWave;
use AlphaDirect\Productplan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Redirect;
use AlphaDirect\Helper;
use Illuminate\Support\Facades\Log;

class FlutterwaveController extends Controller
{

    public function handlePayment($policyNumber , $planName , $ravePlanId){

        $policy_data= new PaymentController;

        $data = $policy_data->calculatePremium($policyNumber);
        $premium = number_format($data->premium, 2, '.', '');
        $narration = $planName;
        $public_key = env('PUBLIC_KEY');
        $encryption_key = env('ENCRYPTION_KEY');
        $staging = '0';

        return response()->json(['premium'=> $premium ,
                                 'country'=>'ZA',
                                 'staging'=>$staging,
                                 'ravePlanId'=>$ravePlanId,
                                 'currency'=>'USD',
                                 'narration'=> $narration,
                                 'publicKey' => $public_key,
                                 'encryptionKey'=> $encryption_key,
                                 'txRef'=> $policyNumber,
                                 'policyNumber'=> $policyNumber,
                                
                                ],200);

    }

    public function testHandlePayment(Request $request){

        $policy_data= new PaymentController;

        $data = $policy_data->calculatePremium($request->policyNumber);

        $premium = floatval($data->premium) ;
        $narration = $request->planName;
        $rave_plan_id = '3398';
        $public_key = 'FLWPUBK_TEST-443d59bd6851974485f12dd4cb1d5fc5-X';
        $secret_key = 'FLWSECK_TEST-20c40e2104c45d0c953b0bff3759b08c-X';
        $staging = '0';


        return response()->json(['premium'=> $premium ,
                                 'country'=>'ZA',
                                 'staging'=>$staging,
                                 'planId'=>$rave_plan_id,
                                 'currency'=>'USD',
                                 'narration'=> $narration,
                                 'publicKey' => $public_key,
                                 'secretKey '=> $secret_key,
                                 'txRef'=> $request->policyNumber,
                                 'policyNumber'=> $request->policyNumber,
                                ],200);

    }
    
    public function acceptedCallback(Request $request){
        \Log::info($request->all());


        $flutterwave = new FlutterWave();
        $flutterwave->amount = $request->amount;
        $flutterwave->txRef = $request->txRef;
        $flutterwave->status = $request->status;
        $flutterwave->description = $request->description;
        $flutterwave->flwRef = $request->flwRef;
        $flutterwave->orderRef = $request->orderRef;
        $flutterwave->save();

        $transaction = new Transaction();
        $transaction->flutterwave_id = $flutterwave->id;
        $transaction->amount = $request->amount;
        $transaction->status = 'SUCCESS';
        $transaction->transactionType = 'Flutterwave'; //request->transactionType;
        $transaction->policyNumber = $request->txRef;
        $transaction->save(); 
      
        return response()->json([
            "code" => "200",
            "message" => "Successful transaction",
        ], 200);

    }
    public function declinedCallback(Request $request){

        \Log::info($request->all());
        return response()->json([
            "code" => "401",
            "message" => "Unsuccessful transaction",
        ], 401);

    }


}
  

