<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use AlphaDirect\City;
use AlphaDirect\Customer;
use AlphaDirect\CustomerBanking;
use AlphaDirect\PolicyBundled;
use AlphaDirect\DpoPayment;
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

class FlutterwavePaymentController extends Controller
{
    public function pay(Request $request)
    {
        //dd($request);
        $email = $request->email;
        $amount = $request->amount;
        $name = $request->name;
        $request = [
            'tx_ref'           => time(),
            'amount'           => $amount,
            'currency'         => 'ZMW',
            'payment_options'  => 'card',
            'redirect_url'     => Route('flutterprocess'),
            'customer'         => [ 'email'        => $email,
                                    'name'         => $name  ],
            'meta'             => [ 'price'        => $amount ],
            'customizations'   => [ 'title'        => "Alphadirect",
                                    'description'  => "FlutterWave Integration",
                                    'logo'         => "https://devgraphite.alphadirect.co.bw/images/logo.png", ]
                   ];
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.flutterwave.com/v3/payments',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($request),
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer '.env('FLUTTERWAVE_SECRET_KEY'),
            'Content-Type: application/json'
        ),
        ));
   // dd($curl);
        $response = curl_exec($curl);
       // dd($response);
        curl_close($curl);
        $res = json_decode($response);
        if($res->status == 'success')
        {
            $link = $res->data->link;
            dd($link);
            return Redirect($link);
        }
        else
        {
            return Redirect::back()->with('error', 'We can not process your payment');
        }
    }
    public function process(Request $request)
    {
       if($request->status == 'cancelled')
        {
             echo 'You cancel the payment';
           // return Redirect('/')->with('error', 'You cancel the payment');
        }
        elseif($request->status == 'successful')
        {
            $txid = $request->transaction_id;
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://api.flutterwave.com/v3/transactions/{$txid}/verify",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => array(
                  "Content-Type: application/json",
                  'Authorization: Bearer '.env('FLUTTERWAVE_SECRET_KEY'),
                ),
              ));
              $response = curl_exec($curl);
              curl_close($curl);
              $res = json_decode($response);
              if($res->status)
              {
                $amountPaid = $res->data->charged_amount;
                $amountToPay = $res->data->meta->price;
                if($amountPaid >= $amountToPay){
                    echo 'Payment successful';
                }else{
                    echo 'Fraud transactio detected';
                }
              }
              else
              {
                  echo 'Can not process payment';
            }
        }
    }
}
