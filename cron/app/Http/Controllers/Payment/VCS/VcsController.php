<?php

namespace AlphaDirect\Http\Controllers\Payment\VCS;

use AlphaDirect\Customer;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Policy;
use AlphaDirect\Transaction;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\VcsTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Redirect;
use AlphaDirect\Helper;
use SoapClient;

class VcsController extends Controller
{

    private function get_current_local_time()
    {

        $ip = file_get_contents("http://ipecho.net/plain");

        $url = 'http://ip-api.com/json/' . $ip;

        $tz = file_get_contents($url);

        $tz = json_decode($tz, true)['timezone'];

        $transactionTime = Carbon::now($tz);

        return $transactionTime;
    }

    public function graphiteVcsPayment($policyNumber, $premium, $policyId,$trialPeriod)
    {


        $appStatus = env('APP_STATUS');
        $urlValue = \Config::get('values.graphite_url');


        switch($appStatus){

            case 'Development': 

            $client = new \GuzzleHttp\Client();
            $url = "https://www.vcs.co.za/vvonline/vcspay.aspx";
            $time = $this->get_current_local_time();
            $date = Carbon::now()->addDay($trialPeriod)->toDateString();
    
            $requestContent = [
    
                'form_params' => [
                    'p1' => '9A00',
                    'p2' => $policyNumber,
                    'p3' => 'Instant Insurance',
                    'p4' => $premium,
                    'p5' => 'BWP',
                    'p6' => 'U',
                    'p7' => 'M',
                    'NextOccurDate' => str_replace('-', '/', $date),
                    'Mobile' => 'Y',
                    'UrlsProvided' => 'Y',
                    'ApprovedUrl' => $urlValue . 'api/graphiteAccepted',
                    'DeclinedUrl' => $urlValue . 'api/graphiteDeclined',
                ],
            ];
            try {
    
                $apiRequest = $client->request('POST', $url, $requestContent);
                $response = $apiRequest->getBody()->getContents();
                return $response;
            } catch (RequestException $re) {
                // For handling exception.
                return response()->json([
                    "code" => "401",
                    "message" => "An error has occured",
                ]);
            }


            break;

            case 'Production':
            $client = new \GuzzleHttp\Client();
            $url = "https://www.vcs.co.za/vvonline/vcspay.aspx";
            $time = $this->get_current_local_time();
            $date = Carbon::now()->addDay($trialPeriod)->toDateString();
    
            $requestContent = [
    
                'form_params' => [
                    'p1' => '3345',
                    'p2' => $policyNumber,
                    'p3' => 'Instant Insurance',
                     'p4' => $premium,
                    'p5' => 'BWP',
                     'p6' => 'U',
                    'p7' => 'M',
                    'NextOccurDate' => str_replace('-', '/', $date),
                    'Mobile' => 'Y',
                    'UrlsProvided' => 'Y',
                    'ApprovedUrl' => $urlValue . 'api/graphiteAccepted',
                    'DeclinedUrl' => $urlValue . 'api/graphiteDeclined',
                ],
            ];
            try {
    
                $apiRequest = $client->request('POST', $url, $requestContent);
                $response = $apiRequest->getBody()->getContents();
                return $response;
            } catch (RequestException $re) {
                // For handling exception.
                return response()->json([
                    "code" => "401",
                    "message" => "An error has occured",
                ]);
            }

            break;

            default:

            return response()->json('Something went wron with app status 1');

            break;
        }

        
     

        $response = $request->getBody('response');
        return response()->json(['policyNumber' => $policyNumber, 'response' => $response, 'nextOccurDate' => $date], 200);
    }
    public function tempVcsPayment($policyNumber, $premium, $policyId,$trialPeriod)
    {


        $appStatus = env('APP_STATUS');
        $urlValue = \Config::get('values.graphite_url');

        switch($appStatus){

            case 'Development': 

            $client = new \GuzzleHttp\Client();
            $url = "https://www.vcs.co.za/vvonline/vcspay.aspx";
            $time = $this->get_current_local_time();
            $date = Carbon::now()->addDay(30)->toDateString();
    
            $requestContent = [
    
                'form_params' => [
                    'p1' => '9A00',
                    'p2' => $policyNumber,
                    'p3' => 'Instant Insurance',
                    'p4' => $premium,
                    'p6' => 'U',
                    'p7' => 'M',
                    'NextOccurDate' => str_replace('-', '/', $date),
                    'Mobile' => 'Y',
                    'UrlsProvided' => 'Y',
                    'ApprovedUrl' => $urlValue . 'api/tempGraphiteAccepted',
                    'DeclinedUrl' => $urlValue . 'api/tempGraphiteDeclined',
                ],
            ];
                try {
    
                $apiRequest = $client->request('POST', $url, $requestContent);
                $response = $apiRequest->getBody()->getContents();

                return $response;
            } catch (RequestException $re) {
                // For handling exception.
                return response()->json([
                    "code" => "401",
                    "message" => "An error has occured",
                ]);
            }


            break;

            case 'Production':
            $client = new \GuzzleHttp\Client();
            $url = "https://www.vcs.co.za/vvonline/vcspay.aspx";
            $time = $this->get_current_local_time();
            $date = Carbon::now()->addDay(30)->toDateString();
    
            $requestContent = [
    
                'form_params' => [
                    'p1' => '3345',
                    'p2' => $policyNumber,
                    'p3' => 'Instant Insurance',
                     'p4' => $premium,
                     'p5' => 'BWP',
                     'p6' => 'U',
                    'p7' => 'M',
                    'NextOccurDate' => str_replace('-', '/', $date),
                    'Mobile' => 'Y',
                    'UrlsProvided' => 'Y',
                    'ApprovedUrl' => $urlValue . 'api/tempGraphiteAccepted',
                    'DeclinedUrl' => $urlValue . 'api/tempGraphiteDeclined',
                ],
            ];
    
            try {
    
                $apiRequest = $client->request('POST', $url, $requestContent);
                $response = $apiRequest->getBody()->getContents();
                return $response;
            } catch (RequestException $re) {
                // For handling exception.
                return response()->json([
                    "code" => "401",
                    "message" => "An error has occured",
                ]);
            }

            break;

            default:

            return response()->json('Something went wron with app status 2');

            break;
        }

        
     

        $response = $request->getBody('response');
        return response()->json(['policyNumber' => $policyNumber, 'response' => $response, 'nextOccurDate' => $date], 200);
    }

    public function vcsTestPayment(Request $request){



        $appStatus = env('APP_STATUS');


        $envVariable = env('GRAPHITE_URL');

        $urlValue = env('GRAPHITE_URL');

        switch($appStatus){

            case 'Development': 

            $client = new \GuzzleHttp\Client();
            $url = "https://www.vcs.co.za/vvonline/vcspay.aspx";
            $time = $this->get_current_local_time();
            $date = Carbon::now()->addDay(30)->toDateString();

            $requestContent = [
    
                'form_params' => [
                    'p1' => '9A00',
                    'p2' => $request->policyNumber,
                    'p3' => 'Instant Insurance',
                    'p4' => $request->premium,
                    'p6' => 'U',
                    'p7' => 'M',
                    'NextOccurDate' => str_replace('-', '/', $date),
                    'Mobile' => 'Y',
                    'UrlsProvided' => 'Y',
                    'ApprovedUrl' => $urlValue . 'api/graphiteAccepted',
                    'DeclinedUrl' => $urlValue . 'api/graphiteDeclined',
                ],
            ];
    
            try {
    
                $apiRequest = $client->request('POST', $url, $requestContent);
                $response = $apiRequest->getBody()->getContents();

                return $response;
            } catch (RequestException $re) {
                // For handling exception.
                return response()->json([
                    "code" => "401",
                    "message" => "An error has occured",
                ]);
            }


            break;

            case 'Production':
            $client = new \GuzzleHttp\Client();
            $url = "https://www.vcs.co.za/vvonline/vcspay.aspx";
            $time = $this->get_current_local_time();
            $date = Carbon::now()->addDay($trialPeriod)->toDateString();
    
            $requestContent = [
    
                'form_params' => [
                    'p1' => '3345',
                    'p2' => $policyNumber,
                    'p3' => 'Instant Insurance',
                     'p4' => $premium,
                    'p5' => 'BWP',
                    'p6' => 'U',
                    'p7' => 'M',
                    'NextOccurDate' => str_replace('-', '/', $date),
                    'Mobile' => 'Y',
                    'UrlsProvided' => 'Y',
                    'ApprovedUrl' => $urlValue . 'api/graphiteAccepted',
                    'DeclinedUrl' => $urlValue . 'api/graphiteDeclined',
                ],
            ];
                try {
    
                $apiRequest = $client->request('POST', $url, $requestContent);
                $response = $apiRequest->getBody()->getContents();

                return $response;
            } catch (RequestException $re) {
                // For handling exception.
                return response()->json([
                    "code" => "401",
                    "message" => "An error has occured",
                ]);
            }

            break;

            default:

            return response()->json('Something went wron with app status3');
        }

    }

    public function appPayment($policyNumber, $premium, $cellphone)
    {

        $appStatus = env('APP_STATUS');
        $urlValue = \Config::get('values.graphite_url');

        switch($appStatus){

            case 'Development': 

            $client = new \GuzzleHttp\Client();
            $url = "https://www.vcs.co.za/vvonline/vcspay.aspx";
            $time = $this->get_current_local_time();
            $threeMonthsLater = Carbon::now()->addDay(30)->toDateString();
            $urlValue = \Config::get('values.graphite_url');
    
    
            $requestContent = [
    
                'form_params' => [
                    'p1' => '9A00',
                    'p2' => $policyNumber,
                    'p3' => 'Instant Insurance',
                     'p4' => $premium,
                        'p5' => 'BWP',
                    'p6' => 'U',
                    'p7' => 'M',
                    'NextOccurDate' => str_replace('-', '/', $threeMonthsLater),
                    'Mobile' => 'Y',
                    'UrlsProvided' => 'Y',
                    'ApprovedUrl' => $urlValue . 'api/vcsAccepted',
                    'DeclinedUrl' => $urlValue . 'api/vcsDeclined',
    
                ],
            ];
    
            try {
    
                $apiRequest = $client->request('POST', $url, $requestContent);
    
                $response = $apiRequest->getBody()->getContents();
    
                //dd($response);
    
                return response()->json(['policyNumber' => $policyNumber, 'response' => $response, 'nextOccurDate' => $threeMonthsLater, 'cellphone' => $cellphone], 200);
            } catch (RequestException $re) {
                // For handling exception.
                return response()->json(
                    "An error has occured, please try again later",
                    401
                );
            }
    
            $response = $request->getBody('response');
    
            return $response;

            break;

            case 'Production':
            $client = new \GuzzleHttp\Client();
            $url = "https://www.vcs.co.za/vvonline/vcspay.aspx";
            $time = $this->get_current_local_time();
            $threeMonthsLater = Carbon::now()->addDay(30)->toDateString();
            $urlValue = \Config::get('values.graphite_url');
    
    
            $requestContent = [
    
                'form_params' => [
                    'p1' => '3345',
                    'p2' => $policyNumber,
                    'p3' => 'Instant Insurance',
                     'p4' => $premium,
                        'p5' => 'BWP',
                    'p6' => 'U',
                    'p7' => 'M',
                    'NextOccurDate' => str_replace('-', '/', $threeMonthsLater),
                    'Mobile' => 'Y',
                    'UrlsProvided' => 'Y',
                    'ApprovedUrl' => $urlValue . 'api/vcsAccepted',
                    'DeclinedUrl' => $urlValue . 'api/vcsDeclined',
    
                ],
            ];
    
            try {
    
                $apiRequest = $client->request('POST', $url, $requestContent);
    
                $response = $apiRequest->getBody()->getContents();

                return response()->json(['policyNumber' => $policyNumber, 'response' => $response, 'nextOccurDate' => $threeMonthsLater, 'cellphone' => $cellphone], 200);
            } catch (RequestException $re) {
                // For handling exception.
                return response()->json(
                    "An error has occured, please try again later",
                    401
                );
            }
    
            $response = $request->getBody('response');
    
            return $response;

            break;

            default:

            return response()->json('Something went wron with app status4');

            break;
        }

        
     

        $response = $request->getBody('response');
        return response()->json(['policyNumber' => $policyNumber, 'response' => $response, 'nextOccurDate' => $date], 200);







        /////////////////////

      
    }

    public function vcsAccepeted(Request $request)
    {

        $policy = Policy::where('policyNumber', $request->p2)->first();
        $customer = Customer::where('id',$policy->customer_id)->first();


        

        try {
            $vcs = new VcsTransaction();
            $vcs->policyNumber = $request->p2;
            $vcs->amount = $request->p6;
            $vcs->status = 'SUCCESS';
            $vcs->paymentDescription = $request->p3;
            $vcs->save();

            $transaction = new Transaction();
            $transaction->vcsTransaction_id = $vcs->id;
            $transaction->transactionType = 'VCS'; //request->transactionType;
            $transaction->customer_id = $policy->customer_id; //request->transactionType;
            $transaction->policyNumber = $request->p2; ////request->policyNumber;
            $transaction->amount = $request->p6; ////request->policyNumber;
            $transaction->status = 'SUCCESS';
            $transaction->paymentDescription = $request->p3;
            $transaction->save();
        } catch (Exception $re) {

            return response()->json(["message" => "Error with VCS Accepted callback"]);
        }

        $tempUrl = new SmsMessaging;

        $tempUrl->sendPolicyActivation($customer->cellphone, $policy->policyNumber);
            // Action 1 for Policy Processed
        Helper::ledgerStore($policy->customer_id, 'POLICY', $policy->id, $policy->product_id, 'NEWBUSINESS');

        return response()->json([
            "code" => "200",
            "message" => "Successful transaction",
        ], 200);
    }

    public function vscGraphiteAccepeted(Request $request)
    {

        $policy = Policy::where('policyNumber', $request->p2)->first();

        try {
            $vcs = new VcsTransaction();
            $vcs->policyNumber = $request->p2;
            $vcs->amount = $request->p6;
            $vcs->status = 'SUCCESS';
            $vcs->paymentDescription = $request->p3;
            $vcs->save();

            $transaction = new Transaction();
            $transaction->vcsTransaction_id = $vcs->id;
            $transaction->transactionType = 'VCS'; //request->transactionType;
            $transaction->customer_id = $policy->customer_id; //request->transactionType;
            $transaction->policyNumber = $request->p2; ////request->policyNumber;
            $transaction->amount = $request->p6; ////request->policyNumber;
            $transaction->status = 'SUCCESS';
            $transaction->paymentDescription = $request->p3;
            $transaction->save();
        } catch (Exception $re) {

            return response()->json(["message" => "Error with VCS Accepted callback"]);
        }

        return Redirect::route('admin.policy.edit', $policy->id)->with('success', 'Payment Recieved');
    }
    
    public function tempGraphiteAccepeted(Request $request)
    {

        $policy = Policy::where('policyNumber', $request->p2)->first();
        $customer = Customer::where('id',$policy->customer_id)->first();
       

        try {
            $vcs = new VcsTransaction();
            $vcs->policyNumber = $request->p2;
            $vcs->amount = $request->p6;
            $vcs->status = 'SUCCESS';
            $vcs->paymentDescription = $request->p3;
            $vcs->save();

            $transaction = new Transaction();
            $transaction->vcsTransaction_id = $vcs->id;
            $transaction->transactionType = 'VCS'; //request->transactionType;
            $transaction->customer_id = $policy->customer_id; //request->transactionType;
            $transaction->policyNumber = $request->p2; ////request->policyNumber;
            $transaction->amount = $request->p6; ////request->policyNumber;
            $transaction->status = 'SUCCESS';
            $transaction->paymentDescription = $request->p3;
            $transaction->save();
        } catch (Exception $re) {

            return response()->json(["message" => "Error with VCS Accepted callback"]);
        }

        $tempUrl = new SmsMessaging;

        $tempUrl->sendPolicyActivation($customer->cellphone, $policy->policyNumber);
            // Action 1 for Policy Processed
        Helper::ledgerStore($policy->customer_id, 'POLICY', $policy->id, $policy->product_id, 'NEWBUSINESS');

        $status = 'YES';
        
        return view('thankyou',compact('status'));

    }

    public function vscGraphiteDeclined(Request $request)
    {

        $policy = Policy::where('policyNumber', $request->p2)->first();

        try {
            $vcs = new VcsTransaction();
            $vcs->policyNumber = $request->p2;
            $vcs->amount = $request->p6;
            $vcs->status = 'FAILED';
            $vcs->paymentDescription = $request->p3;
            $vcs->save();

            $transaction = new Transaction();
            $transaction->vcsTransaction_id = $vcs->id;
            $transaction->transactionType = 'VCS'; //request->transactionType;
            $transaction->customer_id = $policy->customer_id; //request->transactionType;
            $transaction->policyNumber = $request->p2; ////request->policyNumber;
            $transaction->amount = $request->p6; ////request->policyNumber;
            $transaction->status = 'FAILED';
            $transaction->paymentDescription = $request->p3;
            $transaction->save();
        } catch (Exception $re) {

            return response()->json(["message" => "Error with VCS Accepted callback"]);
        }

        return Redirect::route('admin.policy.edit', $policy->id)->with('error', $request->p3);
    }

    public function tempGraphiteDeclined(Request $request)
    {

        $policy = Policy::where('policyNumber', $request->p2)->first();

        try {
            $vcs = new VcsTransaction();
            $vcs->policyNumber = $request->p2;
            $vcs->amount = $request->p6;
            $vcs->status = 'FAILED';
            $vcs->paymentDescription = $request->p3;
            $vcs->save();

            $transaction = new Transaction();
            $transaction->vcsTransaction_id = $vcs->id;
            $transaction->transactionType = 'VCS'; //request->transactionType;
            $transaction->customer_id = $policy->customer_id; //request->transactionType;
            $transaction->policyNumber = $request->p2; ////request->policyNumber;
            $transaction->amount = $request->p6; ////request->policyNumber;
            $transaction->status = 'FAILED';
            $transaction->paymentDescription = $request->p3;
            $transaction->save();
        } catch (Exception $re) {

            return response()->json(["message" => "Error with VCS Accepted callback"]);
        }

        $status = 'NO';
        
        return view('thankyou',compact('status'));  
      }

    public function vcsDeclined(Request $request)
    {

        $policy = Policy::where('policyNumber', $request->p2)->first();
        $customer = Customer::where('id',$policy->customer_id)->first();


        

        try {
            $vcs = new VcsTransaction();
            $vcs->policyNumber = $request->p2;
            $vcs->amount = $request->p6;
            $vcs->status = 'SUCCESS';
            $vcs->paymentDescription = $request->p3;
            $vcs->save();

            $transaction = new Transaction();
            $transaction->vcsTransaction_id = $vcs->id;
            $transaction->transactionType = 'VCS'; //request->transactionType;
            $transaction->customer_id = $policy->customer_id; //request->transactionType;
            $transaction->policyNumber = $request->p2; ////request->policyNumber;
            $transaction->amount = $request->p6; ////request->policyNumber;
            $transaction->status = 'SUCCESS';
            $transaction->paymentDescription = $request->p3;
            $transaction->save();
        } catch (Exception $re) {

            return response()->json(["message" => "Error with VCS Accepted callback"]);
        }

        $tempUrl = new SmsMessaging;

        $tempUrl->sendPolicyFailActivation($customer->cellphone, $policy->policyNumber);
            // Action 1 for Policy Processed
        Helper::ledgerStore($policy->customer_id, 'POLICY', $policy->id, $policy->product_id, 'NEWBUSINESS');

        
        return response()->json([
            "code" => "401",
            "message" => "Unsuccessful transaction",
        ], 400);
    }

    public function storeVcsTransaction(Request $request)
    {
        try {
            $vcs = new VcsTransaction();
            $vcs->policyNumber = $request->p2;
            $vcs->amount = $request->p6;
            $vcs->status = $request->p3;

            if ($vcs->save) {
                $transaction = new Transaction();
                $transaction->vcsTransaction_id = $vcs->id;
                $transaction->transactionType = 'VCS'; //request->transactionType;
                $transaction->policyNumber = $request->p2; ////request->policyNumber;
                $transaction->amount = $request->p6;
                $transaction->status = $request->p3;
                $transaction->save();
            }
        } catch (Exception $re) {

            return response()->json(["message" => "Error with orange callback url"]);
        }
    }

    public function chatbotVcsPayment($policyNumber, $premium)
    {
        $appStatus = env('APP_STATUS');

    
        switch($appStatus){

            case 'Development': 
            $client = new \GuzzleHttp\Client();
            $url = "https://www.vcs.co.za/vvonline/vcspay.aspx";
            $time = $this->get_current_local_time();
            $threeMonthsLater = Carbon::now()->addDay(30)->toDateString();
            $urlValue = \Config::get('values.graphite_url');
    
            $requestContent = [
    
                'form_params' => [
                    'p1' => '9A00',
                    'p2' => $policyNumber,
                    'p3' => 'Instant Insurance',
                    'p4' => $premium,
                    'p5' => 'BWP',
                    'p6' => 'U',
                    'p7' => 'M',
                    'NextOccurDate' => str_replace('-', '/', $threeMonthsLater),
                    'Mobile' => 'Y',
                    'UrlsProvided' => 'Y',
                    'ApprovedUrl' => $urlValue . 'api/chatbotVcsAccepeted',
                    'DeclinedUrl' => $urlValue . 'api/chatbotVcsDeclined',
                ],
            ];
    
            try {
    
                $apiRequest = $client->request('POST', $url, $requestContent);
    
                $response = $apiRequest->getBody()->getContents();
    
                return response()->json(['code' => 200, 'paymentMethod' => 'VCS', 'responseBody' => $response]);
    
                return $response;
            } catch (RequestException $re) {
                // For handling exception.
                return response()->json([
                    "code" => "401",
                    "message" => "An error has occured",
                ]);
            }


            break;

            case 'Production':
            $client = new \GuzzleHttp\Client();
            $url = "https://www.vcs.co.za/vvonline/vcspay.aspx";
            $time = $this->get_current_local_time();
            $threeMonthsLater = Carbon::now()->addDay(30)->toDateString();
            $urlValue = \Config::get('values.graphite_url');
    
            $requestContent = [
    
                'form_params' => [
                    'p1' => '3345',
                    'p2' => $policyNumber,
                    'p3' => 'Instant Insurance',
                    'p4' => $premium,
                    'p5' => 'BWP',
                    'p6' => 'U',
                    'p7' => 'M',
                    'NextOccurDate' => str_replace('-', '/', $threeMonthsLater),
                    'Mobile' => 'Y',
                    'UrlsProvided' => 'Y',
                    'ApprovedUrl' => $urlValue . 'api/chatbotVcsAccepeted',
                    'DeclinedUrl' => $urlValue . 'api/chatbotVcsDeclined',
                ],
            ];
    
            try {
    
                $apiRequest = $client->request('POST', $url, $requestContent);
    
                $response = $apiRequest->getBody()->getContents();
    
                return response()->json(['code' => 200, 'paymentMethod' => 'VCS', 'responseBody' => $response]);
    
                return $response;
            } catch (RequestException $re) {
                // For handling exception.
                return response()->json([
                    "code" => "401",
                    "message" => "An error has occured",
                ]);
            }

            break;

            default:

            return response()->json('Something went wron with app status5');

            break;
        }
    }

    public function chatbotVcsAccepeted(Request $request)
    {

        $policy = Policy::where('policyNumber', $request->p2)->first();
        $customer = Customer::where('id',$policy->customer_id)->first();

        $tempUrl = new SmsMessaging;

        $tempUrl->sendPolicyActivation($customer->cellphone, $policy->policyNumber);



        Helper::ledgerStore($policy->customer_id, 'POLICY', $policy->id, $policy->product_id, 'NEWBUSINESS');


        try {
            $vcs = new VcsTransaction();
            $vcs->policyNumber = $request->p2;
            $vcs->amount = $request->p6;
            $vcs->status = 'SUCCESS';
            $vcs->paymentDescription = $request->p3;
            $vcs->save();

            $transaction = new Transaction();
            $transaction->vcsTransaction_id = $vcs->id;
            $transaction->transactionType = 'VCS'; //request->transactionType;
            $transaction->customer_id = $policy->customer_id; //request->transactionType;
            $transaction->policyNumber = $request->p2; ////request->policyNumber;
            $transaction->amount = $request->p6; ////request->policyNumber;
            $transaction->status = 'SUCCESS';
            $transaction->paymentDescription = $request->p3;
            $transaction->save();
        } catch (Exception $re) {

            return response()->json(["message" => "Error with VCS Accepted callback"]);
        }

        $client = new \GuzzleHttp\Client();
        $urlValue = \Config::get('values.graphite_url');
        $url =  $urlValue . 'chatbot/vcsAccepted';


        $requestContent = [

            'form_params' => [
                'code' => '200',
                'policyNumber' => $request->p2,
            ],
        ];

        try {

            $apiRequest = $client->request('POST', $url, $requestContent);

            $response = $apiRequest->getBody()->getContents();

            return response()->json('Please close this window to return to the conversation thread.', 200);

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

    public function chatbotVcsDeclined(Request $request)
    {

        $policy = Policy::where('policyNumber', $request->p2)->first();

        try {
            $vcs = new VcsTransaction();
            $vcs->policyNumber = $request->p2;
            $vcs->amount = $request->p6;
            $vcs->status = 'FAILED';
            $vcs->paymentDescription = $request->p3;
            $vcs->save();

            $transaction = new Transaction();
            $transaction->vcsTransaction_id = $vcs->id;
            $transaction->transactionType = 'VCS'; //request->transactionType;
            $transaction->customer_id = $policy->customer_id; //request->transactionType;
            $transaction->policyNumber = $request->p2; ////request->policyNumber;
            $transaction->amount = $request->p6; ////request->policyNumber;
            $transaction->status = 'FAILED';
            $transaction->paymentDescription = $request->p3;
            $transaction->save();
        } catch (Exception $re) {

            return response()->json(["message" => "Error with VCS Accepted callback"]);
        }

        $client = new \GuzzleHttp\Client();
        $urlValue = \Config::get('values.graphite_url');
        $url = $urlValue . 'chatbot/vcsDeclined';


        $requestContent = [

            'form_params' => [
                'code' => '400',
                'policyNumber' => $request->p2,
                'errorMessage' => $request->p3
            ],
        ];

        try {

            $apiRequest = $client->request('POST', $url, $requestContent);

            $response = $apiRequest->getBody()->getContents();

            //dd($response);
            return response()->json('Please close this window to return to the conversation thread.', 200);
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }

    }



    public function editTransaction(Request $request)
    {

        $today = Carbon::now()->addDay(1)->toDateString();


        $client = new \GuzzleHttp\Client();
        $url = "https://www.vcs.co.za/wscs/svc_virtualrecur.asmx";

        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
          <soap12:Body>
            <UpdateCCTransaction xmlns="https://www.vcs.co.za/wscs/svc_virtualrecur.asmx">
              <UpdateCCRequest>
                <UserName>kamleshk</UserName>
                <Password>M6iRVcTAMwD5b5d</Password>
                <UserID>3345</UserID>
                <ReferenceNumber>' . $request->referenceNumber . '</ReferenceNumber>
                <Amount>' . $request->Amount . '</Amount>
                <StartDate>' . str_replace('-', '/', $today) . '</StartDate>
              </UpdateCCRequest>
            </UpdateCCTransaction>
          </soap12:Body>
        </soap12:Envelope>';

        $requestContent = [

            'form_params' => [
                'UserName' => 'kamleshk',
                'Password' => 'M6iRVcTAMwD5b5d',
                'UserId' => '3345',
                'ReferenceNumber' => $request->referenceNumber,
            ],
        ];

        $options = [
            'headers' => [
                'Content-Type' => 'application/soap+xml',
            ],
            'body' => $xml,
        ];

        try {

            $apiRequest = $client->request('POST', $url, $options);

            $response = $apiRequest->getBody()->getContents();

            return $response;
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }

        $response = $request->getBody('response');
        return response()->json(['policyNumber' => $policyNumber, 'response' => $response, 'nextOccurDate' => $threeMonthsLater], 200);
    }
    public function getTransactionList(Request $request)
    {

        $today = Carbon::now()->addDay(60)->toDateString();


        $client = new \GuzzleHttp\Client();
        $url = "https://www.vcs.co.za/wscs/svc_virtualrecur.asmx";

        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
          <soap12:Body>
            <GetCCTransactionList xmlns="https://www.vcs.co.za/wscs/svc_virtualrecur.asmx">
              <GetCCListRequest UserName="kamleshk3" Password='.env('PAYMENT_ENV_PASSWORD').' UserID="3385" />
            </GetCCTransactionList>
          </soap12:Body>
        </soap12:Envelope>';

        $requestContent = [

            'form_params' => [
                'UserName' => 'kamleshk',
                'Password' => 'M6iRVcTAMwD5b5d',
                'UserId' => '3345',
                'ReferenceNumber' => $request->referenceNumber,
            ],
        ];

        $options = [
            'headers' => [
                'Content-Type' => 'application/soap+xml',
            ],
            'body' => $xml,
        ];

        try {

            $apiRequest = $client->request('POST', $url, $options);

            $response = $apiRequest->getBody()->getContents();
            $document = new \DOMDocument();
            $document->loadXML($response);
            $xpath = new \DOMXpath($document);

            $items = [];
            // iterate the Table nodes
            foreach ($xpath->evaluate('//NewDataSet/Table') as $tableNode) {
                $items[] = [
                    // read CMan_Code as string 
                    'code' => trim($xpath->evaluate('string(CardNo)', $tableNode)),
                  
                ];

            }


            return $xpath;
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }

        $response = $request->getBody('response');
        return response()->json(['policyNumber' => $policyNumber, 'response' => $response, 'nextOccurDate' => $threeMonthsLater], 200);
    }

    public function deleteTransaction(Request $request)
    {

        $today = Carbon::now()->addDay(1)->toDateString();


        $client = new \GuzzleHttp\Client();
        $url = "https://www.vcs.co.za/wscs/svc_virtualrecur.asmx";

        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
          <soap12:Body>
            <DeleteCCTransaction xmlns="https://www.vcs.co.za/wscs/svc_virtualrecur.asmx">
              <DeleteCCRequest>
                <UserName>kamleshk</UserName>
                <Password>M6iRVcTAMwD5b5d</Password>
                <UserID>3345</UserID>
                <ReferenceNumber>' . $request->referenceNumber . '</ReferenceNumber>
              </DeleteCCRequest>
            </DeleteCCTransaction>
          </soap12:Body>
        </soap12:Envelope>';

        $requestContent = [

            'form_params' => [
                'UserName' => 'kamleshk',
                'Password' => 'M6iRVcTAMwD5b5d',
                'UserId' => '3345',
                'ReferenceNumber' => $request->referenceNumber,
            ],
        ];

        $options = [
            'headers' => [
                'Content-Type' => 'application/soap+xml',
            ],
            'body' => $xml,
        ];

        try {

            $apiRequest = $client->request('POST', $url, $options);

            $response = $apiRequest->getBody()->getContents();
            return $response;
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }
    }


   
}
