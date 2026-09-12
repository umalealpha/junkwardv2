<?php

namespace AlphaDirect\Http\Controllers\Payment\VCS;

use AlphaDirect\Activation;
use AlphaDirect\Claim;
use AlphaDirect\Customer;
use AlphaDirect\Helper;
use AlphaDirect\Http\Controllers\Admin\DocumentController;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\Admin\CustomerController;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Ledger;
use AlphaDirect\PolicyBundled;
use AlphaDirect\Policy;
use AlphaDirect\PolicyPaymentStatusDump;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\Region;
use AlphaDirect\Transaction;
use AlphaDirect\VcsCardDetails;
use AlphaDirect\VcsModel;
use AlphaDirect\VcsTransaction;
use AlphaDirect\VcsNewTransaction;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Models\OrangeMandate;
use AlphaDirect\Models\OrangeDispute;
use AlphaDirect\Pay;
use AlphaDirect\vcsEventLog;
use Auth;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Redirect;
use stdClass;
use AlphaDirect\CustomerGeneratedActivationCode;
use AlphaDirect\Events\CancelOrangeScheduleTransactionEvent;
use AlphaDirect\Events\ScheduleTransactionEvent;
use AlphaDirect\Events\TestOrangeScheduleTransactionEvent;
use AlphaDirect\KYC;
use AlphaDirect\TrackAPIRequestModel;
use SoapClient;
use AlphaDirect\Vehicle;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\ScheduleTransaction;
use AlphaDirect\Events\CancelScheduleTransactionEvent;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Models\UpdateContract;
use AlphaDirect\PolicyCellPhone;

class PaymentController extends Controller
{
    /*
     * calculates premium for policy number
     * param: policy number
     * return: generated values
     */
    public function calculatePremium($policyNumber)
    {
        $policy_data = Policy::where('policyNumber', $policyNumber)->first();
        $customer = Customer::where('id', $policy_data->customer_id)->first();
        $activation_data = Activation::where('serial_code', $policy_data->serial_code)->orderBy('id', 'DESC')->first();
        $product = Product::where('id', $activation_data->product_id)->first(array('id', 'name', 'region_id', 'premium_type_id', 'has_vehicle', 'has_member'));
        $plans = Productplan::where('product_id', $product->id)->where('id', $activation_data->product_plan_id)->first(array('id', 'name', 'slug', 'premium'));
        $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
        if ($product->premium_type_id == 11) {

            $premium = round(($plans->premium * ($regionVat / 100)) + $plans->premium, 2);
        } else {
            $premium = ($plans->premium * ($regionVat / 100)) + $plans->premium;
            $policy_data->premium = $premium;
            $policy_data->vat = $plans->get('premium') * ($regionVat / 100);
            $policy_data->sum_assured = $plans->sum_assured;
        }
        $data = new stdClass();
        $data->premium = $premium;
        $data->cellphone = $customer->cellphone;
        $data->billingStartDate = $policy_data->billingStartDate;
        return $data;
    }

    public function getPaymentEnv()
    {
        $appStatus = env('APP_STATUS');
        switch ($appStatus) {

            case 'Development':
                $terminal_id = '9A00';
                $username = 'kamleshk2';
                $password = env('PAYMENT_ENV_PASSWORD');
                break;

            case 'Production':
                $terminal_id = '3345';
                $username = 'kamleshk';
                $password = env('PAYMENT_ENV_PASSWORD');
                break;

            default:
                $terminal_id = '3345';
                $username = 'kamleshk';
                $password = env('PAYMENT_ENV_PASSWORD');
        }
        $response = new stdClass();
        $response->terminal_id = $terminal_id;
        $response->username = $username;
        $response->password = $password;
        return $response;
    }

    public function generate_string($input = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $strength = 15)
    {
        $input_length = strlen($input);
        $random_string = '';
        for ($i = 0; $i < $strength; $i++) {
            $random_character = $input[mt_rand(0, $input_length - 1)];
            $random_string .= $random_character;
        }

        return $random_string;
    }

    public function saveReferenceNumber($referenceNumber, $policyNumber)
    {
        if (Transaction::where('referenceNumber', $referenceNumber)->exists()) {
            return false;
        } else {
            $transaction = new Transaction();
            $transaction->policyNumber = $policyNumber;
            $transaction->referenceNumber = $referenceNumber;
            $transaction->save();
            return true;
        }
    }

    public function getTextBetweenTags($string, $start, $end)
    {
        $string = ' ' . $string;
        $ini = strpos((string)$string, $start);
        if ($ini == 0) {
            return '';
        }

        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }

    public function getredirectUrlByLeadSource($leadSource)
    {
        switch ($leadSource) {
            case 'GRAPHITE_URL':
                return env('GRAPHITE_URL');
                break;

            case 'start.alphadirect.co.bw':
                return env('START_URL');
                break;

            case 'PAY_URL':
                return env('PAY_URL');
                break;

            default:
                return env('GRAPHITE_URL');
                break;
        }
    }

    //Function to process payment for mobile app and graphite
    public function handlePayment($policyNumber, $leadsource = null, $flag = null)
    {

        $envState = $this->getPaymentEnv();
        $terminal_id = $envState->terminal_id;
        $username = $envState->username;
        $password = $envState->password;

        $urlValue = env('GRAPHITE_URL');

        $policy_data = Policy::where('policyNumber', $policyNumber)->first();
        $customer = Customer::where('id', $policy_data->customer_id)->first();
        $leadsource = $leadsource == null ? $policy_data->leadSource : $leadsource;
        $policy_data->leadSource = $leadsource;
        $policy_data->save();
        $product = Product::where('id', $policy_data->product_id)->first(array('id', 'name', 'has_activation_code', 'region_id', 'premium_type_id', 'has_vehicle', 'has_member'));
        if ($product->has_activation_code == '1') {
            $activation_data = Activation::where('serial_code', $policy_data->serial_code)->orderBy('id', 'DESC')->first();
            if ($activation_data == null) {
                $activation_data = Activation::where('activation_code', $policy_data->serial_code)->orderBy('id', 'DESC')->first();
            }
            $noOfDays = $activation_data->trial_periods;
        } else {
            $noOfDays = 0;
        }

        //Real Date
        if ($policy_data->billingStartDate != null) {
            $PolicyBillingdate = $policy_data->billingStartDate;
        } else {
            $PolicyBillingdate = Carbon::now()->addDay($noOfDays)->toDateString();
        }

        $plans = Productplan::where('product_id', $product->id)->where('id', $policy_data->plan_id)->first(array('id', 'name', 'slug', 'premium'));
        $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
        if ($product->premium_type_id == 11) {
            $premium = round(($plans->premium * ($regionVat / 100)) + $plans->premium, 2);
        } else {

            $policy_data->premium = $policy_data->premium;
            $policy_data->vat = $plans->get('premium') * ($regionVat / 100);
            $policy_data->sum_assured = $plans->sum_assured;
        }
        $plan_name = $plans->slug;

        if ($flag == 'REFERENCE_NUMBER_EXIST') {
            while (true) {
                $referenceNumber = $this->generate_string();
                $run_loop = $this->saveReferenceNumber($referenceNumber, $policyNumber);
                if ($run_loop) {
                    break;
                } else {
                }
            }
            $transctionsRow = Transaction::where('policyNumber', $policyNumber)->orderBy('id', 'desc')->first();
            $transctionsRow->referenceNumber = $referenceNumber;
            $transctionsRow->save();
        } else {
            while (true) {
                $referenceNumber = $this->generate_string();
                $run_loop = $this->saveReferenceNumber($referenceNumber, $policyNumber);
                if ($run_loop) {
                    break;
                } else {
                }
            }
        }
        $PolicyBillingdate = date('Y/m/d', strtotime(str_replace('/', '-', $policy_data->billingStartDate)));
        $client = new \GuzzleHttp\Client();
        $url = "https://www.vcs.co.za/vvonline/vcspay.aspx";
        $requestContent = [
            'form_params' => [
                'p1' => $terminal_id, // it should be 3345
                'p2' => $referenceNumber,
                'p3' => $plan_name,
                // 'p4' => $premium,
                'p4' => '1',
                'p5' => 'BWP',
                //'IsRegistration'=>'Y',
                'p6' => 'U',
                //'p7' => 'D',
                'p7' => 'M', // remove comments
                'p8' => $customer->cellphone,
                'CardholderName' => $customer->firstName . ' ' . $customer->middleName . ' ' . $customer->lastName,
                //'p7' => 'O', //reference and amount
                //'p8' => $request->phone,
                //'p9' => 'Greetings, Alpha Direct has secured your Tsosologo fee and you will be billled monthly. Welcome to the Alpha Direct.',
                # 'p10' => $urlValue . 'api/declinedCallback',
                'p12' => 'Y',
                'p13' => $premium,
                'NextOccurDate' => $PolicyBillingdate,                                  //str_replace('-', '/', date('y-m-d',$PolicyBillingdate)), // remove comments
                //   'NextOccurDate' => '2019/10/31',
                'Budget' => 'N',
                'Mobile' => 'Y',
                'UrlsProvided' => 'Y',
                'ApprovedUrl' => $urlValue . 'api/acceptedCallback',
                'DeclinedUrl' => $urlValue . 'api/declinedCallback',
            ],
        ];

        // return response()->json($requestContent);

        try {

            $apiRequest = $client->request('POST', $url, $requestContent);
            $response = $apiRequest->getBody()->getContents();
            if ($leadsource == 'loadPaymentForm') {
                return $response;
            }
            if ($leadsource == 'MobileApp') {
                $text = $this->gettextBetweenTags($response, '<html>', '</html>');
                //\Log::info($text);
                // include flutterwave if you want to give flutterwave web view it in code
                return response()->json(['policyNumber' => $policyNumber, 'response' => $text, 'nextOccurDate' => '', 'cellphone' => ''], 200);
            } else {

                return $response;
            }

            //return $response;
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }
    }


    //Function to process payment for mobile app with activation code generated
    public function handlePaymentForActivationCodeGenerated($policyNumber, $leadsource = null, $flag = null)
    {

        $envState = $this->getPaymentEnv();
        $terminal_id = $envState->terminal_id;
        $username = $envState->username;
        $password = $envState->password;

        $urlValue = env('GRAPHITE_URL');

        $policy_data = Policy::where('policyNumber', $policyNumber)->first();
        $customer = Customer::where('id', $policy_data->customer_id)->first();
        $leadsource = $leadsource == null ? $policy_data->leadSource : $leadsource;
        $policy_data->leadSource = $leadsource;
        $policy_data->save();
        $product = Product::where('id', $policy_data->product_id)->first(array('id', 'name', 'has_activation_code', 'region_id', 'premium_type_id', 'has_vehicle', 'has_member'));
        if ($product->has_activation_code == '1') {
            $activation_data = Activation::where('serial_code', $policy_data->serial_code)->orderBy('id', 'DESC')->first();
            if ($activation_data == null) {
                $activation_data = Activation::where('activation_code', $policy_data->serial_code)->orderBy('id', 'DESC')->first();
            }
            $noOfDays = $activation_data->trial_periods;
        } else {
            $noOfDays = 0;
        }

        //Real Date
        if ($policy_data->billingStartDate != null) {
            $PolicyBillingdate = $policy_data->billingStartDate;
        } else {
            $PolicyBillingdate = Carbon::now()->addDay($noOfDays)->toDateString();
        }

        $plans = Productplan::where('product_id', $product->id)->where('id', $policy_data->plan_id)->first(array('id', 'name', 'slug', 'premium'));
        $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;

        if ($product->premium_type_id == 11 && $policy_data->is_bundled == 0) {
            $premium = round(($plans->premium * ($regionVat / 100)) + $plans->premium, 2);
        } else {
            $premium = round($policy_data->premium, 2);
            //$policy_data->vat = round(($plans->get('premium') * ($regionVat / 100)), 2);
            //$policy_data->sum_assured = round($plans->sum_assured, 2);
        }
        $plan_name = $plans->slug;

        if ($flag == 'REFERENCE_NUMBER_EXIST') {
            while (true) {
                $referenceNumber = $this->generate_string();
                $run_loop = $this->saveReferenceNumber($referenceNumber, $policyNumber);
                if ($run_loop) {
                    break;
                } else {
                }
            }
            $transctionsRow = Transaction::where('policyNumber', $policyNumber)->orderBy('id', 'desc')->first();
            $transctionsRow->referenceNumber = $referenceNumber;
            $transctionsRow->save();
        } else {
            while (true) {
                $referenceNumber = $this->generate_string();
                $run_loop = $this->saveReferenceNumber($referenceNumber, $policyNumber);
                if ($run_loop) {
                    break;
                } else {
                }
            }
        }
        if ($policy_data->is_bundled == 1) {
            $premium = $policy_data->premium;
            $PolicyBundled = PolicyBundled::where('policy_id',$policy_data->id)->where('product_id',3)->first();
            if($PolicyBundled != NULL && $PolicyBundled->product_id == 3 && $PolicyBundled->frequency_mc == 1){
                $firstPremium =  $policy_data->first_premium_wvat;
            }
        }
        if ($policy_data->billingStartDate == null) {
            $policy_data->billingStartDate = date('Y-m-d');
        }
        $PolicyBillingdate = date('Y/m/d', strtotime(str_replace('/', '-', $policy_data->billingStartDate)));
        $client = new \GuzzleHttp\Client();
        $url = "https://www.vcs.co.za/vvonline/vcspay.aspx";
        $requestContent = [
            'form_params' => [
                'p1' => $terminal_id, // it should be 3345
                'p2' => $referenceNumber,
                'p3' => $plan_name,
                'p4' => isset($firstPremium) ? $firstPremium : $premium,
                //'p4' => '1',
                'p5' => 'BWP',
                //'IsRegistration'=>'Y',
                'p6' => 'U',
                //'p7' => 'D',
                'p7' => 'M', // remove comments
                'p8' => $customer->cellphone,
                'CardholderName' => $customer->firstName . ' ' . $customer->middleName . ' ' . $customer->lastName,
                //'p7' => 'O', //reference and amount
                //'p8' => $request->phone,
                //'p9' => 'Greetings, Alpha Direct has secured your Tsosologo fee and you will be billled monthly. Welcome to the Alpha Direct.',
                # 'p10' => $urlValue . 'api/declinedCallback',
                'p13' => $premium,
                'NextOccurDate' => $PolicyBillingdate,                                  //str_replace('-', '/', date('y-m-d',$PolicyBillingdate)), // remove comments
                //   'NextOccurDate' => '2019/10/31',
                'Budget' => 'N',
                'Mobile' => 'Y',
                'UrlsProvided' => 'Y',
                'ApprovedUrl' => $urlValue . 'api/acceptedCallback',
                'DeclinedUrl' => $urlValue . 'api/declinedCallback',
            ],
        ];

        // return response()->json($requestContent);

        try {

            $apiRequest = $client->request('POST', $url, $requestContent);
            $response = $apiRequest->getBody()->getContents();
            if ($leadsource == 'loadPaymentForm') {
                return $response;
            }
            if ($leadsource == 'LiveQuote') {
                return $response;
            } elseif ($leadsource == 'MobileApp') {
                $text = $this->gettextBetweenTags($response, '<html>', '</html>');
                //\Log::info($text);
                // include flutterwave if you want to give flutterwave web view it in code
                return response()->json(['policyNumber' => $policyNumber, 'response' => $text, 'nextOccurDate' => '', 'cellphone' => ''], 200);
            } else {

                return $response;
            }

            //return $response;
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }
    }

    //Function to process payment for mobile app and graphite
    public function handlePaymentForStart($policyNumber, $leadsource = 'start.alphadirect.co.bw', $flag = null, $billing_date)
    {

        $envState    = $this->getPaymentEnv();
        $terminal_id = $envState->terminal_id;
        $username    = $envState->username;
        $password    = $envState->password;
        if ($leadsource == 'LiveQuote') {
            $urlValue  = env('LIVEQUOTE_URL');
            $urlValue1 = env('GRAPHITE_URL');

            $cancelledUrl = $urlValue . 'payment_pending.php';
            $approvedUrl  = $urlValue1 . 'api/acceptedCallback';
            $declinedUrl  = $urlValue1 . 'api/declinedCallBack';
        } else {
            $urlValue     = env('START_URL');
            $urlValue1    = env('GRAPHITE_URL');
            $cancelledUrl = $urlValue . 'cancelled';
            $approvedUrl  = $urlValue1 . 'api/acceptedCallback';
            $declinedUrl  = $urlValue1 . 'api/declinedCallBack';
        }


        $policy_data = Policy::where('policyNumber', $policyNumber)->orderBy('id', 'DESC')->first();
        $policy_data->billingStartDate = $billing_date;
        $policy_data->save();
        $customer    = Customer::where('id', $policy_data->customer_id)->first();
        $leadsource  = $leadsource == null ? $policy_data->leadSource : $leadsource;
        $product     = Product::where('id', $policy_data->product_id)->first(array('id', 'has_activation_code', 'name', 'region_id', 'premium_type_id', 'has_vehicle', 'has_member'));
        if ($product->has_activation_code == '1') {
            $activation_data = Activation::where('serial_code', $policy_data->serial_code)->orWhere('activation_code', $policy_data->serial_code)->orderBy('id', 'DESC')->first();
            $noOfDays        = $activation_data->trial_periods;
        } else {
            $noOfDays = 0;
        }
        //Real Date
        if ($billing_date) {
            $PolicyBillingdate = date('Y/m/d', strtotime(str_replace('/', '-', $billing_date)));
        } else {
            $PolicyBillingdate = Carbon::now()->addDay($noOfDays)->toDateString();
        }

        $plans = Productplan::where('product_id', $product->id)->where('id', $policy_data->plan_id)->first(array('id', 'name', 'slug', 'premium'));

        $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
        if ($product->premium_type_id == 11) {
            $premium = round(($plans->premium * ($regionVat / 100)) + $plans->premium, 2);
            $plan_name = $plans->slug;
        } else {
            $premium = $policy_data->premium ;
            $plan_name = "Motor Comp";
          //  $policy_data->premium     = $policy_data->premium;
           // $policy_data->vat         = $policy_data->premium * ($regionVat / 100);
            //$policy_data->sum_assured = $plans->sum_assured;
        }


        if ($flag == 'REFERENCE_NUMBER_EXIST') {
            while (true) {
                $referenceNumber = $this->generate_string();
                $run_loop = $this->saveReferenceNumber($referenceNumber, $policyNumber);
                if ($run_loop) {
                    break;
                } else {
                }
            }
            $transctionsRow = Transaction::where('policyNumber', $policyNumber)->orderBy('id', 'desc')->first();
            $transctionsRow->referenceNumber = $referenceNumber;
            $transctionsRow->save();
        } else {
            while (true) {
                $referenceNumber = $this->generate_string();
                $run_loop = $this->saveReferenceNumber($referenceNumber, $policyNumber);
                if ($run_loop) {
                    break;
                } else {
                }
            }
        }

        //dd(str_replace('-', '/', $PolicyBillingdate));

        $client = new \GuzzleHttp\Client();
        $url = "https://www.vcs.co.za/vvonline/vcspay.aspx";
        $requestContent = [
            'form_params' => [
                'p1' => $terminal_id, // it should be 3345 for recurring payment
                'p2' => $referenceNumber,
                'p3' => $plan_name,
                // 'p4' => $premium,
                'p4' => '1',
                'p5' => 'BWP',
                //'IsRegistration'=>'Y',
                'p6' => 'U',
                //'p7' => 'D',
                'p7' => 'M', // remove comments
                'p8' => $customer->cellphone,
                'CardholderName' => $customer->firstName . ' ' . $customer->middleName . ' ' . $customer->lastName,
                //'p7' => 'O', //reference and amount
                //'p8' => $request->phone,
                //'p9' => 'Greetings, Alpha Direct has secured your Tsosologo fee and you will be billled monthly. Welcome to the Alpha Direct.',
                'p10' => $cancelledUrl,
                'p12' => 'Y',
                'p13' => $premium,
                'NextOccurDate' => str_replace('-', '/', $PolicyBillingdate), // remove comments
                //   'NextOccurDate' => '2019/10/31',
                'Budget' => 'N',
                'Mobile' => 'Y',
                'UrlsProvided' => 'Y',
                'ApprovedUrl' => $approvedUrl,
                'DeclinedUrl' => $declinedUrl,
            ],
        ];

        // return response()->json($requestContent);

        try {

            $apiRequest = $client->request('POST', $url, $requestContent);
            $response = $apiRequest->getBody()->getContents();

            if ($leadsource == 'LiveQuote') {
                return $response;
            } else if ($leadsource == 'MobileApp') {
                $text = $this->gettextBetweenTags($response, '<html>', '</html>');
                // \Log::info($text);
                // include flutterwave if you want to give flutterwave web view it in code
                return response()->json(['policyNumber' => $policyNumber, 'response' => $text, 'nextOccurDate' => '', 'cellphone' => ''], 200);
            } else {

                return response()
                    ->json(['code' => 200, 'message' => $response])
                    ->withCallback('callbackPayment');
            }

            //return $response;
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }
    }

    //Function to process payment for mobile app and graphite
    public function handlePaymentForStartActivationCodeGenerated($policyNumber, $leadsource = 'start.alphadirect.co.bw', $premium = null, $flag = null, $billingStart, $billing_date)
    {

        $envState = $this->getPaymentEnv();
        $terminal_id = $envState->terminal_id;
        $username = $envState->username;
        $password = $envState->password;

        $urlValue = env('START_URL');
        $urlValue1 = env('GRAPHITE_URL');


        $policy_data = Policy::where('policyNumber', $policyNumber)->first();
        $customer = Customer::where('id', $policy_data->customer_id)->first();
        $leadsource = $leadsource == null ? $policy_data->leadSource : $leadsource;
        $product = Product::where('id', $policy_data->product_id)->first(array('id', 'has_activation_code', 'name', 'region_id', 'premium_type_id', 'has_vehicle', 'has_member'));
        if ($product->has_activation_code == '1') {
            $activation_data = Activation::where('serial_code', $policy_data->serial_code)->orWhere('activation_code', $policy_data->serial_code)->orderBy('id', 'DESC')->first();
            $noOfDays = $activation_data->trial_periods;
        } else {
            $noOfDays = 0;
        }
        //Real Date
        if ($billing_date != null) {

            $PolicyBillingdate = date('Y-m-d', strtotime(str_replace('/', '-', $billing_date)));
        } else {
            $PolicyBillingdate = Carbon::now()->addDay($noOfDays)->toDateString();
        }

        $plans = Productplan::where('product_id', $product->id)->where('id', $policy_data->plan_id)->first(array('id', 'name', 'slug', 'premium'));
        $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
        if ($product->premium_type_id == 11) {
            if (isset($billingStart) && $billingStart == "Immediate") {
                $premium = round(($plans->premium * ($regionVat / 100)) + $plans->premium, 2);
            } else {
                $premium = "1";
            }
        } else {

            $policy_data->premium = $policy_data->premium;
            $policy_data->vat = $plans->get('premium') * ($regionVat / 100);
            $policy_data->sum_assured = $plans->sum_assured;
        }
        $plan_name = $plans->slug;

        if ($flag == 'REFERENCE_NUMBER_EXIST') {
            while (true) {
                $referenceNumber = $this->generate_string();
                $run_loop = $this->saveReferenceNumber($referenceNumber, $policyNumber);
                if ($run_loop) {
                    break;
                } else {
                }
            }
            $transctionsRow = Transaction::where('policyNumber', $policyNumber)->orderBy('id', 'desc')->first();
            $transctionsRow->referenceNumber = $referenceNumber;
            $transctionsRow->save();
        } else {
            while (true) {
                $referenceNumber = $this->generate_string();
                $run_loop = $this->saveReferenceNumber($referenceNumber, $policyNumber);
                if ($run_loop) {
                    break;
                } else {
                }
            }
        }


        $client = new \GuzzleHttp\Client();
        $url = "https://www.vcs.co.za/vvonline/vcspay.aspx";
        $requestContent = [
            'form_params' => [
                'p1' => $terminal_id, // it should be 3345 for recurring payment
                'p2' => $referenceNumber,
                'p3' => $plan_name,
                'p4' => $premium,
                //'p4' => '1',
                'p5' => 'BWP',
                //'IsRegistration'=>'Y',
                'p6' => 'U',
                //'p7' => 'D',
                'p7' => 'M', // remove comments
                'p8' => $customer->cellphone,
                'CardholderName' => $customer->firstName . ' ' . $customer->middleName . ' ' . $customer->lastName,
                //'p7' => 'O', //reference and amount
                //'p8' => $request->phone,
                //'p9' => 'Greetings, Alpha Direct has secured your Tsosologo fee and you will be billled monthly. Welcome to the Alpha Direct.',
                'p10' => $urlValue . 'cancelled',
                'p12' => 'N',
                'p13' => $premium,
                'NextOccurDate' => str_replace('-', '/', $PolicyBillingdate), // remove comments
                //   'NextOccurDate' => '2019/10/31',
                'Budget' => 'N',
                'Mobile' => 'Y',
                'UrlsProvided' => 'Y',
                'ApprovedUrl' => $urlValue1 . 'api/acceptedCallback',
                'DeclinedUrl' => $urlValue1 . 'api/declinedCallBack',
            ],
        ];

        // return response()->json($requestContent);

        try {

            $apiRequest = $client->request('POST', $url, $requestContent);
            $response = $apiRequest->getBody()->getContents();

            if ($leadsource == 'loadPaymentForm') {
                return $response;
            } else if ($leadsource == 'MobileApp') {
                $text = $this->gettextBetweenTags($response, '<html>', '</html>');
                //\Log::info($text);
                // include flutterwave if you want to give flutterwave web view it in code
                return response()->json(['policyNumber' => $policyNumber, 'response' => $text, 'nextOccurDate' => '', 'cellphone' => ''], 200);
            } else {

                return response()
                    ->json(['code' => 200, 'message' => $response])
                    ->withCallback('callbackPayment');
            }

            //return $response;
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }
    }


    //Function to process payment for mobile app and graphite
    public function updateContractVCS(Request $request)
    {
        $policyNumber = $request->policyNumber;
        $leadsource = $request->leadSource;
        $billing_date = $request->billing_date;
        $envState = $this->getPaymentEnv();
        $terminal_id = $envState->terminal_id;
        $username = $envState->username;
        $password = $envState->password;
        $billingStart = "Immediate";

        $urlValue = env('START_URL');
        $urlValue1 = env('GRAPHITE_URL');


        $policy_data = Policy::where('policyNumber', $policyNumber)->first();
        /*  if($policy_data){
              $request->transactionType = "recurring";
              $this->suspendVcsTransaction($request);
          } */
        $customer = Customer::where('id', $policy_data->customer_id)->first();
        $leadsource = $leadsource == null ? $policy_data->leadSource : $leadsource;
        $product = Product::where('id', $policy_data->product_id)->first(array('id', 'has_activation_code', 'name', 'region_id', 'premium_type_id', 'has_vehicle', 'has_member'));
        if ($product->has_activation_code == '1') {
            $activation_data = Activation::where('serial_code', $policy_data->serial_code)->orWhere('activation_code', $policy_data->serial_code)->orderBy('id', 'DESC')->first();
            $noOfDays = $activation_data->trial_periods;
        } else {
            $noOfDays = 0;
        }
        //Real Date
        if ($billing_date != null) {

            $PolicyBillingdate = date('Y-m-d', strtotime(str_replace('/', '-', $billing_date)));
        } else {
            $PolicyBillingdate = Carbon::now()->addDay($noOfDays)->toDateString();
        }

        $plans = Productplan::where('product_id', $product->id)->where('id', $policy_data->plan_id)->first(array('id', 'name', 'slug', 'premium'));
        $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
        if ($product->premium_type_id == 11) {
            if (isset($billingStart) && $billingStart == "Immediate") {
                $premium = round(($plans->premium * ($regionVat / 100)) + $plans->premium, 2);
            } else {
                $premium = "1";
            }
        } else {

            $policy_data->premium = $policy_data->premium;
            $policy_data->vat = $plans->get('premium') * ($regionVat / 100);
            $policy_data->sum_assured = $plans->sum_assured;
        }
        $plan_name = $plans->slug;

        while (true) {
            $referenceNumber = $this->generate_string();
            $run_loop = $this->saveReferenceNumber($referenceNumber, $policyNumber);
            if ($run_loop) {
                break;
            } else {
            }
        }
        $transctionsRow = Transaction::where('policyNumber', $policyNumber)->orderBy('id', 'desc')->first();
        $transctionsRow->referenceNumber = $referenceNumber;
        $transctionsRow->save();



        $client = new \GuzzleHttp\Client();
        $url = "https://www.vcs.co.za/vvonline/vcspay.aspx";
        $requestContent = [
            'form_params' => [
                'p1' => $terminal_id, // it should be 3345 for recurring payment
                'p2' => $referenceNumber,
                'p3' => $plan_name,
                'p4' => $premium,
                //'p4' => '1',
                'p5' => 'BWP',
                //'IsRegistration'=>'Y',
                'p6' => 'U',
                //'p7' => 'D',
                'p7' => 'M', // remove comments
                'p8' => $customer->cellphone,
                'CardholderName' => $customer->firstName . ' ' . $customer->middleName . ' ' . $customer->lastName,
                //'p7' => 'O', //reference and amount
                //'p8' => $request->phone,
                //'p9' => 'Greetings, Alpha Direct has secured your Tsosologo fee and you will be billled monthly. Welcome to the Alpha Direct.',
                'p10' => $urlValue . 'cancelled',
                'p12' => 'N',
                'p13' => $premium,
                'NextOccurDate' => str_replace('-', '/', $PolicyBillingdate), // remove comments
                //   'NextOccurDate' => '2019/10/31',
                'Budget' => 'N',
                'Mobile' => 'Y',
                'UrlsProvided' => 'Y',
                'ApprovedUrl' => $urlValue1 . 'api/acceptedCallback',
                'DeclinedUrl' => $urlValue . 'paymentpending',
            ],
        ];

        // return response()->json($requestContent);

        try {

            $apiRequest = $client->request('POST', $url, $requestContent);
            $response = $apiRequest->getBody()->getContents();

            if ($leadsource == 'loadPaymentForm') {
                return $response;
            } else if ($leadsource == 'MobileApp') {
                $text = $this->gettextBetweenTags($response, '<html>', '</html>');
                //\Log::info($text);
                // include flutterwave if you want to give flutterwave web view it in code
                return response()->json(['policyNumber' => $policyNumber, 'response' => $text, 'nextOccurDate' => '', 'cellphone' => ''], 200);
            } else {

                return response()
                    ->json(['code' => 200, 'message' => $response])
                    ->withCallback('callbackPayment');
            }

            //return $response;
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }
    }

    /*
     * Handle payment function to process payment outside of GRaphite scope. I.e. Pay.alphadirect.co.bw
     */
    //$premium,policyNumber
    public function handlePaymentForPay(Request $request)
    {
        $policyNumber = $request->policyNumber;
        $premium = $request->premium;
        $envState = $this->getPaymentEnv();
        // dd($envState);

        $terminal_id = $envState->terminal_id;
        $username = $envState->username;
        $password = $envState->password;

        $urlValue = env('GRAPHITE_URL');
        $PAY_URL = env('PAY_URL');
        while (true) {
            $referenceNumber = $this->generate_string();
            $run_loop = $this->saveReferenceNumber($referenceNumber, $policyNumber);
            if ($run_loop) {
                break;
            }
        }

        $payTr = new Pay();
        $payTr->policyNumber = $request->policyNumber;
        $payTr->premium = $request->premium;
        $payTr->cellphone = $request->cellphone;
        $payTr->fullname = $request->full_name;
        $payTr->transaction_id = $referenceNumber;
        $payTr->status = 'Pending';
        $payTr->save();

        $client = new \GuzzleHttp\Client();
        $url = "https://www.vcs.co.za/vvonline/vcspay.aspx";
        $requestContent = [
            'form_params' => [
                'p1' => $terminal_id, // it should be 3345
                'p2' => $referenceNumber,
                'p3' => "Payment for " . $policyNumber,
                'p4' => $premium,
                //'p4' => '1',
                'p5' => 'BWP',
                //'IsRegistration'=>'Y',
                // 'p6' => '01',
                //'p7' => 'D',
                //'p7' => 'M', // remove comments
                //'p7' => 'O', //reference and amount
                //'p8' => $request->phone,
                //'p9' => 'Greetings, Alpha Direct has secured your Tsosologo fee and you will be billled monthly. Welcome to the Alpha Direct.',
                'p10' => $urlValue . 'api/Paycancelled',
                'p11' => 'accounts@alphadirect.co.bw',
                //'p12' => 'N',
                //'p13' => $premium,
                //'NextOccurDate' => str_replace('-', '/', $PolicyBillingdate), // remove comments
                //   'NextOccurDate' => '2019/10/31',
                'Mobile' => 'Y',
                'CardholderEmail' => 'accounts@alphadirect.co.bw',
                'Budget' => 'N',
                'Mobile' => 'Y',
                'UrlsProvided' => 'Y',
                'ApprovedUrl' => $urlValue . 'api/Paythankyou',
                'DeclinedUrl' => $urlValue . 'api/Paydeclined',
            ],
        ];

        // return response()->json($requestContent);

        try {

            $apiRequest = $client->request('POST', $url, $requestContent);
            $VCSform = $apiRequest->getBody()->getContents();
            return response()
                ->json(['code' => 200, 'message' => $VCSform])
                ->withCallback($request->input('callback'));
            //  return $response;
            //return $response;
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }
    }

    /*
     * Handle payment function to process payment outside of GRaphite scope. I.e. Pay.alphadirect.co.bw
     */
    //$premium,policyNumber
    public function handlePaymentForPayWithDebt(Request $request)
    {
        $policyNumber = $request->policyNumber;
        $premium = $request->premium;
        $envState = $this->getPaymentEnv();
        if (strpos($policyNumber, 'MIS') == true) {
            $policy_data = Policy::where('policyNumber', $policyNumber)->first();
        }
        $terminal_id = $envState->terminal_id;
        $username = $envState->username;
        $password = $envState->password;

        $urlValue = env('GRAPHITE_URL');
        $PAY_URL = env('PAY_URL');
        while (true) {
            $referenceNumber = $this->generate_string();
            $run_loop = $this->saveReferenceNumber($referenceNumber, $policyNumber);
            if ($run_loop) {
                break;
            }
        }

        $client = new \GuzzleHttp\Client();
        $url = "https://www.vcs.co.za/vvonline/vcspay.aspx";
        $requestContent = [
            'form_params' => [
                'p1' => $terminal_id, // it should be 3345
                'p2' => $referenceNumber,
                'p3' => "Payment for " . $policyNumber,
                'p4' => $premium,
                //'p4' => '1',
                'p5' => 'BWP',
                //'IsRegistration'=>'Y',
                // 'p6' => '01',
                //'p7' => 'D',
                //'p7' => 'M', // remove comments
                //'p7' => 'O', //reference and amount
                //'p8' => $request->phone,
                //'p9' => 'Greetings, Alpha Direct has secured your Tsosologo fee and you will be billled monthly. Welcome to the Alpha Direct.',
                'p10' => $urlValue . 'api/Paycancelled',
                'p11' => 'accounts@alphadirect.co.bw',
                //'p12' => 'N',
                //'p13' => $premium,
                //'NextOccurDate' => str_replace('-', '/', $PolicyBillingdate), // remove comments
                //   'NextOccurDate' => '2019/10/31',
                'Mobile' => 'Y',
                'CardholderEmail' => 'accounts@alphadirect.co.bw',
                'Budget' => 'N',
                'Mobile' => 'Y',
                'UrlsProvided' => 'Y',
                'ApprovedUrl' => $urlValue . 'api/Paythankyou',
                'DeclinedUrl' => $urlValue . 'api/Paydeclined',
            ],
        ];

        // return response()->json($requestContent);

        try {

            $apiRequest = $client->request('POST', $url, $requestContent);
            $VCSform = $apiRequest->getBody()->getContents();
            return response()
                ->json(['code' => 200, 'message' => $VCSform])
                ->withCallback($request->input('callback'));
            //  return $response;
            //return $response;
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }
    }

    public function testHandlePaymentForQuoteVariable(Request $request)
    {
        $policyNumber = $request->policyNumber;
        return $this->handlePaymentForQuoteVariable($policyNumber, null);
    }

    public function handlePaymentForQuoteVariable($policyNumber, $leadsource = null, $flag = null)
    {

        $envState = $this->getPaymentEnv();
        $terminal_id = $envState->terminal_id;
        $username = $envState->username;
        $password = $envState->password;

        $urlValue = env('GRAPHITE_URL');
        $policy_data = Policy::where('policyNumber', $policyNumber)->first();
        $quoteNumber = $policy_data->quoteNumber;
        $vehicle = Vehicle::where('policy_id', $policy_data->id)->first();
        $customer = Customer::where('id', $policy_data->customer_id)->first();
        $leadsource = $leadsource == null ? $policy_data->leadSource : $leadsource;
        //Real Date
        $PolicyBillingdate = $policy_data->billingStartDate;

        $plan_name = "Motor comprehensive for " . $vehicle->vehiclePlate; // needs to be changed  for motor insurance else display product name

        if ($flag == 'REFERENCE_NUMBER_EXIST') {
            while (true) {
                $referenceNumber = $this->generate_string();
                $run_loop = $this->saveReferenceNumber($referenceNumber, $policyNumber);
                if ($run_loop) {
                    break;
                } else {
                }
            }
            $transctionsRow = Transaction::where('policyNumber', $policyNumber)->orderBy('id', 'desc')->first();
            $transctionsRow->referenceNumber = $referenceNumber;
            $transctionsRow->save();
        } else {
            while (true) {
                $referenceNumber = $this->generate_string();
                $run_loop = $this->saveReferenceNumber($referenceNumber, $policyNumber);
                if ($run_loop) {
                    break;
                } else {
                }
            }
        }
        $prem_freq = $this->getFreq($policy_data->premium_freq);
        if ($prem_freq == 'M') {
            $firstPremium = $policy_data->first_premium_wvat;
        }
        $premium = $policy_data->first_premium_wvat;
        $quoteNumberData = MotorComprehensiveQuotes::where('quoteNumber', $quoteNumber)->first();
        $occurances = '';
        if ($prem_freq == 'Y') {
            $prem_freq = "Y";
            $occurances = 'U';
            $PolicyBillingdate = date("Y-m-d", strtotime("+1 year", strtotime($PolicyBillingdate)));
            $premium = $quoteNumberData->premiumAnnually;
        } else if ($prem_freq == 'Q') {
            $prem_freq = "M";
            $occurances = '2';
            $PolicyBillingdate = date("Y-m-d", strtotime("+1 month", strtotime($PolicyBillingdate)));
            $premium = $quoteNumberData->premium3Inst;
        } else {
            $firstPremium = $policy_data->first_premium_wvat;
            if (date('Y-m-d') >= date('Y-m-d', strtotime($policy_data->billingStartDate))) {
                $firstPremium = $quoteNumberData->premiumMonthly;
                $PolicyBillingdate = date("Y-m-d", strtotime("+1 month", strtotime($PolicyBillingdate)));
            }
            $occurances = 'U';
            $premium = $quoteNumberData->premiumMonthly;
        }

        if ($policy_data->is_bundled == 1) {
            $premium = $policy_data->premium;
            $PolicyBundled = PolicyBundled::where('policy_id',$policy_data->id)->where('product_id',3)->first();
            if($PolicyBundled != NULL && $PolicyBundled->product_id == 3 && $PolicyBundled->frequency_mc == 1){
                $firstPremium =  $policy_data->first_premium_wvat;
            }
        }

        $client = new \GuzzleHttp\Client();
        $url = "https://www.vcs.co.za/vvonline/vcspay.aspx";
        $requestContent = [
            'form_params' => [
                'p1' => $terminal_id, // it should be 3345
                'p2' => $referenceNumber,
                'p3' => $plan_name,
                'p4' => isset($firstPremium) ? $firstPremium : $premium, // this needs to be changed current month premium
                #'p4' => '1',
                'p5' => 'BWP',
                //'IsRegistration'=>'Y',
                'p6' => $occurances,
                //'p7' => 'D',
                'p7' => $prem_freq, // remove comments
                'p8' => $customer->cellphone,
                'CardholderName' => $customer->firstName . ' ' . $customer->middleName . ' ' . $customer->lastName,
                //'p7' => 'O', //reference and amount
                //'p8' => $request->phone,
                //'p9' => 'Greetings, Alpha Direct has secured your Tsosologo fee and you will be billled monthly. Welcome to the Alpha Direct.',
                # 'p10' => $urlValue . 'api/declinedCallback',
                'p10' => 'https://www.alphadirect.co.bw/payment_pending.php?p2=' . $referenceNumber, //this needs to be changed
                'p11' => 'accounts@alphadirect.co.bw',
                'p13' => $premium,
                'NextOccurDate' => stripslashes(str_replace('-', '/', $PolicyBillingdate)), // remove comments
                //   'NextOccurDate' => '2019/10/31',
                'Budget' => 'N',
                'Mobile' => 'Y',
                'UrlsProvided' => 'Y',
                'ApprovedUrl' => $urlValue . 'api/acceptedCallback',
                'DeclinedUrl' => $urlValue . 'api/declinedCallback',
            ],
        ];

        // return response()->json($requestContent);

        try {

            $apiRequest = $client->request('POST', $url, $requestContent);
            $response = $apiRequest->getBody()->getContents();
            if ($leadsource == 'MobileApp') {
                $text = $this->gettextBetweenTags($response, '<html>', '</html>');
                //\Log::info($text);
                // include flutterwave if you want to give flutterwave web view it in code
                return response()->json(['policyNumber' => $policyNumber, 'response' => stripslashes($text), 'nextOccurDate' => '', 'cellphone' => ''], 200);
            } else {


                return response()
                    ->json(['code' => 200, 'message' => $response])
                    ->withCallback('callbackPayment');
            }

            //return $response;
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }
    }

    public function vcsOnceOffForStart(Request $request)
    {
        $envState = $this->getPaymentEnv();
        $terminal_id = $envState->terminal_id;
        $urlValue = env('GRAPHITE_URL');
        $policy_data = Policy::where('policyNumber', $request->policyNumber)->first();
        if($policy_data == NULL) {
            return response()->json([
                "code" => "401",
                "message" => "Invalid Policy Number",
                "title" => "Invalid Policy Number",
                "description" => "Please enter valid Policy Number"
            ], 401);
        }
        $policyNumber = $request->policyNumber;
        $customer = Customer::where('id', $policy_data->customer_id)->first();
        $leadsource = $request->leadsource == null ? $policy_data->leadSource : $request->leadsource;
        //Real Date
        $banking = CustomerBanking::where('policy_id', $policy_data->id)->first();

        $updateCOntract =  new UpdateContract();
        $updateCOntract->policyNumber = $policyNumber;
        $updateCOntract->new_payment_method = "VCS_from_vcsOnceOffForStart";
        if(isset($banking) && $banking->billing != null){
            $updateCOntract->old_payment_method = $banking->billing;
        }else{
            $updateCOntract->old_payment_method = null;
        }
        if(isset($request->agent_id) && $request->agent_id != null){
            $updateCOntract->agent = $request->agent_id;
        }else{
            $updateCOntract->agent = null;
        }
        $updateCOntract->save();
        $product     = Product::where('id', $policy_data->product_id)->first(array('id', 'has_activation_code', 'name', 'region_id', 'premium_type_id', 'has_vehicle', 'has_member'));
        if ($product->has_activation_code == '1') {
            $activation_data = Activation::where('serial_code', $policy_data->serial_code)->orWhere('activation_code', $policy_data->serial_code)->orderBy('id', 'DESC')->first();
            $noOfDays        = $activation_data->trial_periods;
        } else {
            $noOfDays = 0;
        }
        $plans = Productplan::where('product_id', $product->id)->where('id', $policy_data->plan_id)->first(array('id', 'name', 'slug', 'premium'));

        $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
        if ($product->premium_type_id == 11) {
            $premium = round(($plans->premium * ($regionVat / 100)) + $plans->premium, 2);
        } else {

            $policy_data->premium     = $policy_data->premium;
            $policy_data->vat         = $plans->get('premium') * ($regionVat / 100);
            $policy_data->sum_assured = $plans->sum_assured;
        }
        $plan_name = "Once off for " . $request->policyNumber; // needs to be changed  for motor insurance else display product name
        if (!isset($request->referenceNumber)) {
            while (true) {
                $referenceNumber = $this->generate_string();
                $run_loop = $this->saveReferenceNumber($referenceNumber, $request->policyNumber);
                if ($run_loop) {
                    break;
                } else {
                }
            }
            $transctionsRow = Transaction::where('policyNumber', $request->policyNumber)->orderBy('id', 'desc')->first();
            $transctionsRow->referenceNumber = $referenceNumber;
            $transctionsRow->save();
        }

        $client = new \GuzzleHttp\Client();
        $url = "https://www.vcs.co.za/vvonline/vcspay.aspx";
        $requestContent = [
            'form_params' => [
                'p1' => $terminal_id, // it should be 3345
                'p2' => $referenceNumber,
                'p3' => $plan_name,
                 'p4' => $request->amount, // this needs to be changed current month premium
               # 'p4' => '1',
                'p5' => 'BWP',
                //'IsRegistration'=>'Y',
                'p8' => $customer->cellphone,
                'CardholderName' => $customer->firstName . ' ' . $customer->middleName . ' ' . $customer->lastName,
                //'p7' => 'O', //reference and amount
                //'p8' => $request->phone,
                //'p9' => 'Greetings, Alpha Direct has secured your Tsosologo fee and you will be billled monthly. Welcome to the Alpha Direct.',
                # 'p10' => $urlValue . 'api/declinedCallback',
                'p10' => 'https://start.alphadirect.co.bw/paymentpending?p2=' . $referenceNumber, //this needs to be changed
                'p11' => 'accounts@alphadirect.co.bw',
                //'NextOccurDate' => str_replace('-', '/', $PolicyBillingdate), // remove comments
                //   'NextOccurDate' => '2019/10/31',
                'p13' => $premium,
                'NextOccurDate' => str_replace('-', '/', $request->billing_date), // remove comments
                'm1' => "card update",
                'Budget' => 'N',
                'Mobile' => 'Y',
                'UrlsProvided' => 'Y',
                'ApprovedUrl' => $urlValue . 'api/acceptedCallback',
                'DeclinedUrl' => $urlValue . 'api/declinedCallback',
            ],
        ];

        // return response()->json($requestContent);

        try {

            $apiRequest = $client->request('POST', $url, $requestContent);
            $response = $apiRequest->getBody()->getContents();
            $response = trim(preg_replace('/\s\s+/', ' ', $response));

            if ($leadsource == 'MobileApp') {
                $text = $this->gettextBetweenTags($response, '<html>', '</html>');
                //\Log::info($text);
                // include flutterwave if you want to give flutterwave web view it in code
                return response()->json(['policyNumber' => $policyNumber, 'message' => $text], 200);
            } else {

                return response()
                    ->json(['code' => 200, 'message' => $response])
                    ->withCallback('callbackPayment');
            }

            //return $response;
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }
    }

    public function handlePaymentForQuoteVariableWhatsapp($policyNumber, $leadsource = null, $flag = null)
    {

        $envState = $this->getPaymentEnv();
        $terminal_id = $envState->terminal_id;
        $username = $envState->username;
        $password = $envState->password;

        $urlValue = env('GRAPHITE_URL');
        $policy_data = Policy::where('policyNumber', $policyNumber)->first();
        $quoteNumber = $policy_data->quoteNumber;
        $vehicle = Vehicle::where('policy_id', $policy_data->id)->first();
        $customer = Customer::where('id', $policy_data->customer_id)->first();
        $leadsource = $leadsource == null ? $policy_data->leadSource : $leadsource;
        //Real Date
        $PolicyBillingdate = $policy_data->billingStartDate;

        $plan_name = "Motor comprehensive for " . $vehicle->vehiclePlate; // needs to be changed  for motor insurance else display product name

        if ($flag == 'REFERENCE_NUMBER_EXIST') {
            while (true) {
                $referenceNumber = $this->generate_string();
                $run_loop = $this->saveReferenceNumber($referenceNumber, $policyNumber);
                if ($run_loop) {
                    break;
                } else {
                }
            }
            $transctionsRow = Transaction::where('policyNumber', $policyNumber)->orderBy('id', 'desc')->first();
            $transctionsRow->referenceNumber = $referenceNumber;
            $transctionsRow->save();
        } else {
            while (true) {
                $referenceNumber = $this->generate_string();
                $run_loop = $this->saveReferenceNumber($referenceNumber, $policyNumber);
                if ($run_loop) {
                    break;
                } else {
                }
            }
        }
        $prem_freq = $this->getFreq($policy_data->premium_freq);
        $occurances = '';
        if ($prem_freq == 'Q') {
            $prem_freq = "M";
            $occurances = '2';
        } else {
            $occurances = 'U';
        }
        $client = new \GuzzleHttp\Client();
        $url = "https://www.vcs.co.za/vvonline/vcspay.aspx";
        $requestContent = [
            'form_params' => [
                'p1' => $terminal_id, // it should be 3345
                'p2' => $referenceNumber,
                'p3' => $plan_name,
                'p4' => $policy_data->first_premium_wvat, // this needs to be changed current month premium
                #'p4' => '1',
                'p5' => 'BWP',
                //'IsRegistration'=>'Y',
                'p6' => $occurances,
                //'p7' => 'D',
                'p7' => $prem_freq, // remove comments
                'p8' => $customer->cellphone,
                'CardholderName' => $customer->firstName . ' ' . $customer->middleName . ' ' . $customer->lastName,
                //'p7' => 'O', //reference and amount
                //'p8' => $request->phone,
                //'p9' => 'Greetings, Alpha Direct has secured your Tsosologo fee and you will be billled monthly. Welcome to the Alpha Direct.',
                # 'p10' => $urlValue . 'api/declinedCallback',
                'p10' => 'https://www.alphadirect.co.bw/payment_pending.php?p2=' . $referenceNumber, //this needs to be changed
                'p11' => 'accounts@alphadirect.co.bw',
                'p13' => $policy_data->premium + $policy_data->vat,
                'NextOccurDate' => str_replace('-', '/', $PolicyBillingdate), // remove comments
                //   'NextOccurDate' => '2019/10/31',
                'Budget' => 'N',
                'Mobile' => 'Y',
                'UrlsProvided' => 'Y',
                'ApprovedUrl' => $urlValue . 'api/acceptedCallback',
                'DeclinedUrl' => $urlValue . 'api/declinedCallback',
            ],
        ];

        // return response()->json($requestContent);

        try {

            $apiRequest = $client->request('POST', $url, $requestContent);
            $response = $apiRequest->getBody()->getContents();

            if ($leadsource == 'MobileApp') {
                $text = $this->gettextBetweenTags($response, '<html>', '</html>');
                //\Log::info($text);
                // include flutterwave if you want to give flutterwave web view it in code
                return response()->json(['policyNumber' => $policyNumber, 'response' => $text, 'nextOccurDate' => '', 'cellphone' => ''], 200);
            } else {

                return $response;
            }

            //return $response;
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }
    }

    public function getFreq($freq)
    {
        switch ($freq) {
            case '1':
                return 'M';
                break;

            case '2':
                return 'Q';
                break;

            case '3':
                return 'Y';
                break;

            default:
                return 'M';
                break;
        }
    }

    public function Paycancelled(Request $request)
    {
        $PAY_URL = env('PAY_URL');
        if (isset($request->p2) && ($request->p2 != '')) {
            $payTr = PaymentTransaction::where('transaction_id', $request->p2)->first();
            $payTr->status = 'Cancelled';
            $payTr->save();
        }
        return Redirect::to($PAY_URL . "paymentpending?p2=" . $request->p2);
    }

    public function Paythankyou(Request $request)
    {
        $PAY_URL = env('PAY_URL');
        if (isset($request->p2) && ($request->p2 != '')) {
            $payTr = PaymentTransaction::where('transaction_id', $request->p2)->first();
            $payTr->status = 'Success';
            $payTr->save();

            $sms = new SmsMessaging();
            $sms->sendPaySuccessSMS(4, $payTr->cellphone, $payTr->premium);
        }
        return Redirect::to($PAY_URL . "thankyou?p2=" . $request->p2);
    }

    public function Paydeclined(Request $request)
    {
        $PAY_URL = env('PAY_URL');
        if (isset($request->p2) && ($request->p2 != '')) {
            $payTr = PaymentTransaction::where('transaction_id', $request->p2)->first();
            $payTr->status = 'Declined';
            $payTr->save();
        }
        return Redirect::to($PAY_URL . "paymentdeclined?p2=" . $request->p2);
    }
    /*
        Do not touch this api
    */
    public function getPremiumByProductPlan($planId)
    {
        $plans = Productplan::where('id', $planId)->first(array('id', 'product_id', 'name', 'slug', 'premium'));
        $product = Product::where('id', $plans->product_id)->first(array('region_id', 'premium_type_id'));
        $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
        if ($product->premium_type_id == 11) {
            $premium = round(($plans->premium * ($regionVat / 100)) + $plans->premium, 2);
        }
        return $premium;
    }

        /*
        Do not touch this api
    */
    public function getADGroupedPremiumByProductPlan($planId)
    {
        $plans = Productplan::where('id', $planId)->first(array('id', 'product_id', 'name', 'slug', 'premium'));
        $product = Product::where('id', $plans->product_id)->first(array('region_id', 'premium_type_id'));
        $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
        if ($product->premium_type_id == 15) {
            $premium = round(($plans->premium * ($regionVat / 100)) + $plans->premium, 2);
        }
        return $premium;
    }

        /*
        Do not touch this api
    */
    public function getHospitalCashbackPremiumByProductPlan($planId)
    {
        $plans = Productplan::where('id', $planId)->first(array('id', 'product_id', 'name', 'slug', 'premium', 'premiumAndRelation'));
        $product = Product::where('id', $plans->product_id)->first(array('region_id', 'premium_type_id','product_type_id'));
        $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;

        $premiumAndRelation = json_decode($plans->premiumAndRelation, true); // Decode JSON to array

        if ($product->product_type_id == 4) {
            foreach ($premiumAndRelation as &$item) {
                $item['premium'] = round(($item['premium'] * ($regionVat / 100)) + $item['premium'], 2);
            }
        }

        return $premiumAndRelation; // Return the updated array with VAT included
    }

    public function handleAuth($policyNumber, $premium)
    {

        $policy_data = Policy::where('policyNumber', $policyNumber)->first();
        $policyNumber = $policyNumber;

        $activation_data = Activation::where('serial_code', $policy_data->serial_code)->orderBy('id', 'DESC')->first();
        $noOfDays = $activation_data->trial_periods;
        $PolicyBillingdate = Carbon::now()->addDay($noOfDays)->toDateString();

        $product = Product::where('id', $activation_data->product_id)->first(array('id', 'name', 'region_id', 'premium_type_id', 'has_vehicle', 'has_member'));
        $plans = Productplan::where('product_id', $product->id)->where('id', $activation_data->product_plan_id)->first(array('id', 'name', 'slug', 'premium'));
        $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
        if ($product->premium_type_id == 11) {
            $premium = round(($plans->premium * ($regionVat / 100)) + $plans->premium, 2);
        } else {
            $policy_data->premium = $premium;
            $policy_data->vat = $plans->get('premium') * ($regionVat / 100);
            $policy_data->sum_assured = $plans->sum_assured;
        }
        $plan_name = $plans->slug;
        $PolicyBillingdate = Carbon::now()->addDay(30)->toDateString();
        $envState = $this->getPaymentEnv();
        $terminal_id = $envState->terminal_id;
        $username = $envState->username;
        $password = $envState->password;

        $client = new \GuzzleHttp\Client();
        $url = "https://www.vcs.co.za/vvonline/ccxmldemand.asp";
        $requestContent = [

            'form_params' => [
                'p1' => $terminal_id,
                'p2' => 'ARUNTRIAL1601',
                'p3' => 'ARUNTRIAL1601',
                'p4' => '1',
                'p5' => 'BWP',
                'p6' => 'U',
                'p7' => 'M',
                //'p8' => $request->phone,
                //'p9' => 'Greetings, Alpha Direct has secured your Tsosologo fee and you will be billled monthly. Welcome to the Alpha Direct.',
                'p12' => 'Y',
                //'p13'=> $request->occurenceAmount,
                'NextOccurDate' => str_replace('-', '/', $PolicyBillingdate),
                'Mobile' => 'Y',
                'UrlsProvided' => 'Y',
                'ApprovedUrl' => $urlValue . 'api/acceptedCallback',
                'DeclinedUrl' => $urlValue . 'api/declinedCallback',
            ],
        ];

        try {

            $apiRequest = $client->request('POST', $url, $requestContent);
            return response()->json(['policyNumber' => 'DIS838383', 'response' => $response, 'nextOccurDate' => '', 'cellphone' => ''], 200);
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }
    }

    public function getReferenceNumber($referenceNumber)
    {

        $referenceNumberRow = Transaction::where('referenceNumber', $referenceNumber)->first();

        return $referenceNumberRow->policyNumber;
    }

    public function restoreVCS(Request $request)
    {
        if ($request->p1 == "3385") {
            $this->declinedCallbackMonthlyVcs($request, "First");
        } else {
            $this->declinedCallBack($request);
        }
    }


    public function acceptedCallback(Request $request)
    {

        $status = 'SUCCESS';

        try {
            $referenceNumber = $request->p2;
            $policyNumber    = $this->getReferenceNumber($referenceNumber);
            $policy          = Policy::where('policyNumber', $policyNumber)->first();
            $initiating      = Transaction::where('referenceNumber', $referenceNumber)->first();

            // Guard: only honour a callback that maps to a real policy AND a
            // payment this system actually initiated (the Transaction row is
            // created with the reference when the customer starts the VCS
            // payment). An unknown/forged reference must NOT create a
            // transaction or activate a policy.
            if (!$policy || !$initiating) {
                \Log::warning('vcs.acceptedCallback.unknown_reference', ['ref' => $referenceNumber]);
                return response()->json(['error' => 'Unknown payment reference'], 404);
            }

            // Idempotency: the initiating Transaction is created with no status
            // and is set to SUCCESS only by saveTransaction(). If it is already
            // SUCCESS this is a repeat callback — do NOT create a duplicate
            // VcsTransaction, re-activate the policy, or re-post the NEWBUSINESS
            // ledger entry. Fall through to the normal redirect below.
            if (strtoupper((string) $initiating->status) !== 'SUCCESS') {
                $this->saveTransaction($request, $status);

                $policyController = new PolicyController(); //call the action function to update the status of the Policy to active
                $policyController->action($policy->id, 1); //set the Policy status to active

                Helper::ledgerStore($policy->customer_id, 'POLICY', $policy->id, $policy->product_id, 'NEWBUSINESS');
            } else {
                \Log::info('vcs.acceptedCallback.duplicate_ignored', ['ref' => $referenceNumber]);
            }

            $request->p2      = $policyNumber;
            $user             = Customer::where('id', $policy->customer_id)->first();
            $encoded_id       = base64_encode($policy->customer_id);
            $encoded_policyId = base64_encode($policy->policyNumber);
            $product_plan     = Productplan::where('id', $policy->plan_id)->first(array('sum_assured', 'premium', 'slug'));

            // $sms = new SmsMessaging();
            // $sms->sendTsosologoSMS(4, $user->cellphone, $policy->policyNumber, $product_plan->slug, $user->firstName, '', '');

            $source = $policy['leadSource'];

            //Commeneted out for test purposes (18 October 2019)
            switch ($source) {

                case 'Graphite':

                    return Redirect::route('admin.policy.edit', $policy->id)->with('success', 'Payment Recieved');

                    break;

                case 'Alpha Fe':
                    return response()->json('HEY');
                    break;

                case 'USSD':
                    return response()->json(['accepted' => 'tempGraphiteAccepted'], 200);
                    break;

                case 'G-ACT':

                    return redirect()->route('thankyou');

                    break;

                case 'start.alphadirect.co.bw':
                    return redirect()->away(env('START_URL') . 'uploadKYC/' . $encoded_id);
                    break;

                case 'LiveQuote':
                    //                    $docs = new DocumentController();
                    //                    $generate = $docs->generatePolicyDocument($policy->id);
                    //                    if($generate != null)
                    //                        $send = $docs->sendPolicyDocument($policy->id);
                    return redirect()->away('https://devquote2.alphadirect.co.bw/payment_success.php?q=' . $encoded_policyId); //this needs to be changed
                    break;

                case 'pay.alphadirect.co.bw':
                    return redirect()->away('https://pay.alphadirect.co.bw/thankyou?p2=' . $referenceNumber); //this needs to be changed
                    break;


                case 'Chatbot':

                    $client = new \GuzzleHttp\Client();
                    $urlValue = \Config::get('values.graphite_url');
                    $url = $urlValue . 'chatbot/vcsAccepted';

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
                        return response()->json(["code" => "401", "message" => "An error has occured"]);
                    }

                    $response = $request->getBody('response');

                    return $response;
                    break;

                case 'MobileApp':
                    return response()->json([
                        "code" => "200",
                        "message" => "Successful transaction",
                    ], 200);

                    break;

                default:
                    return '';
            }
        } catch (Exception $re) {
            return response()->json(["message" => "Error with VCS Accepted callback"]);
        }
    }

    public function updateRecordsVCS(Request $request)
    {

        /*
           $request->p2 = $data[0]; // reference number
    $request->p3 = $data[6];//payment description
    $request->p5 = $data[1];//customer name
    $request->p6 = $data[3];//amount
    $request->p8 = $data[2];//plan name
    $request->p12 = $data[5];//status code
    $request->created_at = date('Y-m-d H:i:s' ,strtotime(str_replace('/', '-', $data[7])));
    $request->authorization_Date = date('Y-m-d' ,strtotime(str_replace('/', '-', $data[7])));
    $request->settlement_Date = date('Y-m-d' ,strtotime(str_replace('/', '-', $data[8])));

        */
        $referenceNumber = $request->p2;
        $statusCode = urldecode($request->p12);
        $status = $statusCode == "00" ? "Success" : "Failed";
        $originalReferenceNumber = urldecode($request->p2);
        if (strpos($referenceNumber, '-') !== false) {
            $referenceNumberArr = explode("-", $originalReferenceNumber);
            $referenceNumber = $referenceNumberArr[0];
            $terminal_id = '3385';
            $trans_type = "Recurring";
            $ledgerTransType = strtoupper("Recurring");
            $policyNumber = $this->getReferenceNumber($referenceNumber);
        } else {
            $referenceNumber = $originalReferenceNumber;
            $terminal_id = '3345';
            $trans_type = "First";
            $ledgerTransType = "NEWBUSINESS";
            $policyNumber = $this->getReferenceNumber($referenceNumber);
        }

        $plan_name = urldecode($request->p8);
        $amount = urldecode($request->p6);
        $statusRef = urldecode($request->p3);
        $paymentDate = $request->authorization_Date;
        $settlementDate = $request->settlement_Date;
        $policyNumber = $this->getReferenceNumber($referenceNumber);
        $policy = Policy::where('policyNumber', $policyNumber)->first();
        if ($request->p1 == '3345') {

            $vcs = VcsTransaction::firstOrCreate(
                array(
                    'policyNumber' => $policyNumber,
                    'paymentDescription' => $request->p3,
                ),
                array(
                    'amount' => $request->p6,
                    'status' => strtoupper($status),
                    'updated_at' => $request->created_at
                )
            );

            Transaction::firstOrCreate(
                array(
                    'referenceNumber' => $referenceNumber,
                    'paymentDescription' => $request->p3
                ),
                array(
                    'vcsTransaction_id' => $vcs->id,
                    'transactionType' => 'VCS',
                    'customer_id' => $policy->customer_id,
                    'policyNumber' => $policyNumber,
                    'amount' => $request->p6,
                    'status' => strtoupper($status),
                    'updated_at' => $request->created_at
                )
            );
        }

        VcsNewTransaction::firstOrCreate([
            'originalReferenceNumber' => $originalReferenceNumber
        ], [
            'reference' => $referenceNumber,
            'name' => urldecode($request->p5),
            'amount' => $amount,
            'goods' => $plan_name,
            'transType' => $trans_type,
            'terminal_id' => $terminal_id,
            'status' => $status, // in lowercase
            'statusRef' => $statusRef,
            'authorision_Date' => $paymentDate, //get from csv and its mandatory to update it
            'settlement_Date' => $settlementDate, //get from csv and its mandatory to update it
            'policyNumber' => $policyNumber
        ]);


        PaymentTransaction::firstOrCreate([
            'referenceNumber' => $originalReferenceNumber
        ], [
            "policyNumber" => $policyNumber,
            "amount" => $amount,
            "status" => $status,
            "paymentDate" => $paymentDate,
            "is_ledger" => 0,
            "paymentMethod" => "VCS", //Realpay or VCS
            "numberOfInstalmentsPaid" => '1',
            "note" => $statusRef,
        ]);

        $policyController = new policyController();
        if ($request->p1 == '3345' && $status == 'Success' && $policy->status == '0') {
            $policyController->action($policy->id, 1); //set the Policy status to active
        }

        #Helper::ledgerStore($policy->customer_id, 'POLICY', $policy->id, $policy->product_id, $ledgerTransType);

        echo '<CallBackResponse>Accepted</CallBackResponse>';
    }

    public function acceptedCallbackPay(Request $request)
    {
        return redirect()->route('thankyou');
    }


    public function declinedCallback(Request $request)
    {
        // \Log::info($request->all());
        $status = 'FAILED';
        try {

            $referenceNumber = $request->p2;
            $this->saveTransaction($request, $status);

            //$this->getLeadSourceRedirect($request, $status);
            $request->p2 = $this->getReferenceNumber($referenceNumber);
            $policy = Policy::where('policyNumber', $request->p2)->first();
            $user = Customer::where('id', $policy->customer_id)->first();
            $encoded_id = base64_encode($policy->customer_id);
            $data['customer_name'] = $user->firstName . ' ' . $user->lastName;
            $data['trans_failed_description'] = $request->p3;
            $data['policyNumber'] = $request->p2;
            $data['referenceNumber'] = $referenceNumber;
            $policyController = new PolicyController(); //call the action function to update the status of the Policy to active
            $policyController->action($policy['id'], 0); //set the Policy status to deactivated

            $source = $policy['leadSource'];
            switch ($source) {

                case 'Graphite':
                    return Redirect::route('admin.policy.edit', $policy->id)->with('error', $request->p3);

                    break;

                case 'Alpha Fe':
                    return response()->json('HEY');
                    break;

                case 'USSD':
                    return response()->json(['declined' => 'tempGraphiteDeclined'], 200);
                    break;

                case 'G-ACT':
                    echo 'anderson paak 1';
                    return redirect()->route('thankyou');
                    break;

                case 'start.alphadirect.co.bw':
                    return redirect()->away(env('START_URL') . 'paymentpending/' . $encoded_id)->with(['data' => $data]);
                    break;

                case 'LiveQuote':
                    return redirect()->away('https://www.alphadirect.co.bw/payment_pending.php'); //this needs to be changed
                    break;

                case 'Chatbot':

                    $client = new \GuzzleHttp\Client();
                    $urlValue = \Config::get('values.graphite_url');
                    $url = $urlValue . 'chatbot/vcsDeclined';

                    $requestContent = [

                        'form_params' => [
                            'code' => '400',
                            'policyNumber' => $request->p2,
                            'errorMessage' => $request->p3,
                        ],
                    ];

                    try {

                        $apiRequest = $client->request('POST', $url, $requestContent);

                        $response = $apiRequest->getBody()->getContents();

                        return response()->json('Please close this window to return to the conversation thread.', 200);
                    } catch (RequestException $re) {
                        // For handling exception.
                        return response()->json([
                            "code" => "401",
                            "message" => "An error has occured",
                        ]);
                    }
                    break;

                case 'MobileApp':

                    return response()->json([
                        "code" => "401",
                        "message" => "Unsuccessful transaction",
                    ], 400);

                    break;

                default:
                    return '';
            }
        } catch (Exception $re) {

            return response()->json(["message" => "Error with VCS declined callback"]);
        }
    }

    public function saveTransaction(Request $request, $status)
    {

        $referenceNumber = $request->p2;
        $policyNumber = $this->getReferenceNumber($referenceNumber);
        $policy = Policy::where('policyNumber', $policyNumber)->first();

        $vcs = new VcsTransaction();
        $vcs->policyNumber = $policyNumber;
        $vcs->amount = $request->p6;
        $vcs->status = $status;
        $vcs->paymentDescription = $request->p3;
        $vcs->save();

        $transaction = Transaction::where('referenceNumber', $referenceNumber)->first();
        $transaction->vcsTransaction_id = $vcs->id;
        $transaction->transactionType = 'VCS'; //request->transactionType;
        $transaction->customer_id = $policy->customer_id; //request->transactionType;
        $transaction->policyNumber = $policyNumber; ////request->policyNumber;
        $transaction->amount = $request->p6; ////request->policyNumber;
        $transaction->status = $status;
        $transaction->paymentDescription = $request->p3;
        $transaction->save();
        if ($status == 'FAILED') {
            $this->declinedCallbackMonthlyVcs($request, "First");
        } else {
            $this->acceptedCallbackMonthlyVcs($request, "First");
        }
    }

    public function checkPaymentStatus(Request $request)
    {
        $policyTransaction = VcsTransaction::where('policyNumber', $request->policyNumber)->orderBy('id', 'desc')->first();
        if ($policyTransaction != NULL && $policyTransaction['status'] == 'SUCCESS') {
            return response()->json(['title' => 'Payment successful', 'description' => 'The payment authorisation ran succesfully'], 200);
        } else {
            return response()->json(['title' => 'Payment failure', 'description' => 'This payment has failed '], 401);
        }
    }

    public function getVCSfirstTras($referenceNumber)
    {

        /*
        $client = new SoapClient(
            "https://www.vcs.co.za/vOnlineWs/VCSQuery/VCSQuery.svc?wsdl",
            array('trace' => true, 'exceptions' => true, "cache_wsdl" => 0)
        );
        try {
            $status = $client->GetTransactionByTIDAndReference(array(
                "UserID" => "kamleshk3",
                "Password" => "M6iRVcTAMwD5b5d",
                "TerminalID" => "3385",
                "ReferenceNumber" => 'iCZWLmvAseXErjL-200130'
            ))->GetTransactionByTIDAndReferenceResult;
        } catch (SOAPFault $fault) {
            echo "<pre>" . $fault . "</pre>";
            echo "<pre>Last Request:</h3>" . $client->__getLastRequest() . "</pre>";
            echo "<pre>Last Request Headers:</h3>" . $client->__getLastRequestHeaders() . "</pre>";
            echo "<pre>Last Response:</h3><br />" . $client->__getLastResponse() . "</pre>";
            echo "<pre>Last Response Headers:</h3><br />" . $client->__getLastResponseHeaders() . "</pre>";
        }
        return $status; */
        $terminal_id_second = env('terminal_id_second');
        $username_second = env('username_second');
        $password_second = env('password_second');
        $firstArr = array();
        $client = new \GuzzleHttp\Client();
        $url = "https://www.vcs.co.za/wscs/svc_virtualrecur.asmx";
        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
          <soap12:Body>
            <GetCCTransactionDetail xmlns="https://www.vcs.co.za/wscs/svc_virtualrecur.asmx">
              <GetCCDetailRequest>
                <UserName>' . $username_second . '</UserName>
                <Password>' . $password_second . '</Password>
                <UserID>' . $terminal_id_second . '</UserID>
                <ReferenceNumber>' . $referenceNumber . '</ReferenceNumber>
              </GetCCDetailRequest>
            </GetCCTransactionDetail>
          </soap12:Body>
        </soap12:Envelope>';

        $options = [
            'headers' => [
                'Content-Type' => 'application/soap+xml',
            ],
            'body' => $xml,
        ];

        try {

            $apiRequest = $client->request('POST', $url, $options);
            $response = $apiRequest->getBody()->getContents();

            $clean_xml = str_ireplace(['SOAP-ENV:', 'SOAP:'], '', $response);

            $xml = simplexml_load_string($clean_xml) or die("Error: Cannot create object");
            $resultCode = $xml->Body->GetCCTransactionDetailResponse->GetCCTransactionDetailResult->ResultCode;
            $resultMessage = $xml->Body->GetCCTransactionDetailResponse->GetCCTransactionDetailResult->ResultMessage;
            $firstArr['code'] = $resultCode;
            if ($resultCode == '0') {
                $firstArr['message'] = $xml->Body->GetCCTransactionDetailResponse->GetCCTransactionDetailResult->CardNumber;
            } else {
                $firstArr['message'] = $resultMessage;
            }

            return $firstArr;
        } catch (RequestException $re) {
        }
    }

    public function getVCScardDetails(Request $request)
    {
        $policyTransaction = Transaction::where('policyNumber', $request->policyNumber)->where('status', 'SUCCESS')->orderBy('id', 'desc')->first();
        $recurringTrans = array();
        $firstTrans = $this->getVCSfirstTras($policyTransaction['referenceNumber']);
        return response()->json($firstTrans, 200);
        if ($firstTrans['code'] == '0') {

            return response()->json($firstTrans, 200);
        } else {

            return response()->json(['title' => 'Card data failure', 'description' => 'This policy have no card registered '], 400);
        }
    }

    public function GetVCSTransLog(Request $request)
    {

        #$policyTransaction = Transaction::where('policyNumber', $request->policyNumber)->orderBy('id', 'desc')->first();
        $policyTransaction = VcsNewTransaction::where('policyNumber', $request->policyNumber)->orderBy('id', 'desc')->first();
        $recurringTrans = array();
        $firstTrans = $this->getVCSfirstTras($policyTransaction['reference']);
        dd($firstTrans);
        //Guzzle request to VCS
        $client = new \GuzzleHttp\Client();
        $terminal_id_second = env('terminal_id_second');
        $username_second = env('username_second');
        $password_second = env('password_second');

        $url = "https://www.vcs.co.za/wscs/svc_virtualrecur.asmx";

        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
        <soap12:Body>
            <GetCCTransactionDetail xmlns="https://www.vcs.co.za/wscs/svc_virtualrecur.asmx">
              <GetCCDetailRequest>
                <UserName>' . $username_second . '</UserName>
                <Password>' . $password_second . '</Password>
                <UserID>' . $terminal_id_second . '</UserID>
                <ReferenceNumber>' . $policyTransaction['originalReferenceNumber'] . '</ReferenceNumber>
              </GetCCDetailRequest>
            </GetCCTransactionDetail>
          </soap12:Body>
        </soap12:Envelope>';

        $options = [
            'headers' => [
                'Content-Type' => 'application/soap+xml',
            ],
            'body' => $xml,
        ];

        try {

            $apiRequest = $client->request('POST', $url, $options);
            $response = $apiRequest->getBody()->getContents();

            $clean_xml = str_ireplace(['SOAP-ENV:', 'SOAP:'], '', $response);

            $xml = simplexml_load_string($clean_xml) or die("Error: Cannot create object");

            $resultCode = $xml->Body->GetCCTransactionDetailResponse->GetCCTransactionDetailResult->ResultCode;
            $resultMessage = $xml->Body->GetCCTransactionDetailResponse->GetCCTransactionDetailResult->ResultMessage;
            $recurringTrans['code'] = $resultCode;
            $recurringTrans['message'] = $resultMessage;
            if ($resultCode == 0) {
                return response()->json(['title' => 'Success', 'Description' => 'Payment Successful', 'inceptionTrans' => $firstTrans, 'recurringTrans' => $recurringTrans], 200);
            } else {
                return response()->json(['title' => 'Failed', 'Description' => 'Payment Unsuccessful'], 401);
            }
            return $resultCode;
        } catch (RequestException $re) {
        }

        //  return response()->json($policyTransaction);
    }

    public function suspendVcsTransaction(Request $request)
    {

        $policyTransaction = Transaction::where('policyNumber', $request->policyNumber)->orderBy('id', 'desc')->first();
        $username = $password = "";
        $client = new \GuzzleHttp\Client();
        $terminal_id = $request->transactionType == 'first' ? "3345" : "3385";
        if ($terminal_id == '3345') {
            $username = env('3345_un');
            $password = env('3345_ps');
        } else {
            $username = env('3385_un');
            $password = env('3385_ps');
        }
        $url = "https://www.vcs.co.za/wscs/svc_virtualrecur.asmx/DeleteCCTransaction";

        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <DeleteCCTransaction xmlns="https://www.vcs.co.za/wscs/svc_virtualrecur.asmx">
              <DeleteCCRequest>
                <UserName>' . $username . '</UserName>
                <Password>' . $password . '</Password>
                <UserID>' . $terminal_id . '</UserID>
                <ReferenceNumber>' . $policyTransaction['referenceNumber'] . '</ReferenceNumber>
              </DeleteCCRequest>
            </DeleteCCTransaction>
          </soap:Body>';

        $options = [
            'headers' => [

                'Content-Type' => 'application/soap+xml',
            ],
            'body' => $xml,
        ];

        try {

            $apiRequest = $client->request('POST', $url, $options);
            $response = $apiRequest->getBody()->getContents();

            $clean_xml = str_ireplace(['SOAP-ENV:', 'SOAP:'], '', $response);

            $xml = simplexml_load_string($clean_xml) or die("Error: Cannot create object");
            # dd($options);
            $resultCode = $xml->Body->DeleteCCTransactionResponse->DeleteCCTransactionResult->ResultCode;
            $resultMessage = $xml->Body->DeleteCCTransactionResponse->DeleteCCTransactionResult->ResultMessage;

            if ($resultCode == 0) {
                return response()->json(['title' => 'Success', 'Description' => 'Policy cancelled Successful'], 200);
            } else {
                return response()->json(['title' => 'Failed', 'Description' => 'Payment cancellation failed'], 401);
            }
            return $resultCode;
        } catch (RequestException $re) {
        }

        //  return response()->json($policyTransaction);
    }

    public function redoPayment(Request $request)
    {
        $policy = Policy::where('policyNumber', $request->policyNumber)->first();
        if (isset($policy) && $policy->status == 0) {
            $policyTransaction = Transaction::where('policyNumber', $request->policyNumber)->orderBy('id', 'desc')->first();

            $policy = Policy::where('policyNumber', $request->policyNumber)->first();


            if (isset($policy) && isset($policy->customer_id) && in_array($policy->product_id,array(1,4))) {
                $policies = Policy::join('customer', 'customer.id', 'policies.customer_id')
                    ->where('policies.status', 1)
                    ->count();
                 if (isset($policies) && $policies >= 1) {
                    return response()->json(['title' => 'Customer already registered for this policy', 'error' => 'Customer is already owner of policy. Please check again.'], 400);
                }
            }
            if ($policyTransaction == null || $request->BillingStart == "Later") {

                $policy = Policy::where('policyNumber', $request->policyNumber)->first();
                if ($policy != null) {
                    $vcs = new PaymentController;
                    return $vcs->handlePaymentForStart($request->policyNumber, 'MobileApp', $flag = 'REFERENCE_NUMBER_EXIST', $request->billing_date);
                }

                return response()->json(['title' => 'Policy number does not exists', 'description' => 'Entered policy number does not exist in system'], 401);
            }
            $policy = Policy::where('policyNumber', $request->policyNumber)->first();
            if ($policy->is_sys_act_generated == 1 || $request->BillingStart == "Immediate") {
                $vcs = new PaymentController;
                return $vcs->handlePaymentForStartActivationCodeGenerated($request->policyNumber, 'MobileApp', null, $flag = 'REFERENCE_NUMBER_EXIST', $request->BillingStart, $request->billing_date);
            }

            if ($policyTransaction['status'] != 'SUCCESS') {

                $vcs = new PaymentController;
                return $vcs->handlePayment($request->policyNumber, 'MobileApp', $flag = 'REFERENCE_NUMBER_EXIST');
            } else {

                return response()->json(['title' => 'Reference number exists', 'description' => 'A payment has been made with this reference number'], 401);
            }
        } else {
            return response()->json(['title' => 'Policy not allowed for payment', 'description' => 'Policy should be deactivated to process payment'], 401);
        }
    }

    public function redoPaymentForLiveQuote(Request $request)
    {
        if ($request->referenceNumber != null) {
            $policyTransaction = Transaction::where('referenceNumber', $request->referenceNumber)->where('status', 'SUCCESS')->orderBy('id', 'desc')->first();
        } else {
            $policyTransaction = null;
        }
        if ($policyTransaction == null) {

            $policy = Policy::where('policyNumber', $request->policyNumber)->first();
            $vcs = new PaymentController;

            if ($policy->product_id == 3) {
                $leadSource = isset($request->leadSource) && $request->leadSource != "" ? $request->leadSource : "LiveQuote";

                return $vcs->handlePaymentForQuoteVariable($policy->policyNumber, $leadSource, $flag = 'REFERENCE_NUMBER_EXIST');
            } else {
                if (isset($request->BillingStart) && $request->BillingStart == "Immediate") {
                    return $vcs->handlePaymentForStartActivationCodeGenerated($request->policyNumber, 'LiveQuote', null, $flag = 'REFERENCE_NUMBER_EXIST', $request->BillingStart, $request->billing_date);
                }
                return $this->handlePaymentForStart($policy->policyNumber, 'LiveQuote', 'REFERENCE_NUMBER_EXIST', $request->billing_date);
            }
        } else {
            $policy = Policy::where('policyNumber', $policyTransaction->policyNumber)->first();
        }
        $flag = "";

        if ($policy->is_sys_act_generated == 1) {
            $flag = 'SYSTEM_GENERATED_CODE';
        }

        if ($policyTransaction == null && $policy == null) {

            return response()->json(['title' => 'Policy number does not exists', 'description' => 'Entered policy number does not exist in system'], 401);
        }

        if ($policyTransaction == null) {

            $vcs = new PaymentController;

            if ($policy->product_id == 3) {
                return $vcs->handlePaymentForQuoteVariable($policy->policyNumber, 'LiveQuote', $flag = 'REFERENCE_NUMBER_EXIST');
            } else {
                if ($flag == 'SYSTEM_GENERATED_CODE') {
                    return $vcs->handlePaymentForStartActivationCodeGenerated($request->policyNumber, 'LiveQuote', null, $flag = 'REFERENCE_NUMBER_EXIST', $request->BillingStart, $request->billing_date);
                }
                return $this->handlePaymentForStart($policy->policyNumber, 'LiveQuote', 'REFERENCE_NUMBER_EXIST', $request->billing_date);
            }
        } else {

            return response()->json(['title' => 'Reference number exists', 'description' => 'A payment has been already made with this reference number'], 401);
        }
    }

    public function redoPaymentForMobileAppComprehensive(Request $request)
    {
        if ($request->referenceNumber != null) {
            $policyTransaction = Transaction::where('referenceNumber', $request->referenceNumber)->orderBy('id', 'desc')->first();
        } else {
            $policyTransaction = null;
        }

        if ($policyTransaction == null) {

            $policy = Policy::where('policyNumber', $request->policyNumber)->first();

            $vcs = new PaymentController;

            if ($policy->product_id == 3) {
                $leadSource = isset($request->leadSource) && $request->leadSource != "" ? $request->leadSource : "LiveQuote";

                return $vcs->handlePaymentForQuoteVariable($policy->policyNumber, $leadSource);
            }
        } else {
            $policy = Policy::where('policyNumber', $policyTransaction->policyNumber)->first();
        }
        $flag = "";

        if ($policy->is_sys_act_generated == 1) {
            $flag = 'SYSTEM_GENERATED_CODE';
        }

        if ($policyTransaction == null && $policy == null) {

            return response()->json(['title' => 'Policy number does not exists', 'description' => 'Entered policy number does not exist in system'], 401);
        }

        if ($policyTransaction['status'] != 'SUCCESS') {

            $vcs = new PaymentController;

            if ($policy->product_id == 3) {
                return $vcs->handlePaymentForQuoteVariable($policy->policyNumber, 'LiveQuote', $flag = 'REFERENCE_NUMBER_EXIST');
            } else {
                if ($flag == 'SYSTEM_GENERATED_CODE') {
                    return $vcs->handlePaymentForStartActivationCodeGenerated($request->policyNumber, 'LiveQuote', '', $flag = 'REFERENCE_NUMBER_EXIST', $policy->BillingStart, $policy->billingStartDate);
                }
                return $this->handlePaymentForStart($policy->policyNumber, 'LiveQuote', 'REFERENCE_NUMBER_EXIST', $policy->billingStartDate);
            }
        } else {

            return response()->json(['title' => 'Reference number exists', 'description' => 'A payment has been already made with this reference number'], 401);
        }
    }

    public function checkPolicyStatus(Request $request)
    {
        $policy = Policy::where('policyNumber', $request->policyNumber)->first();
        if (!isset($policy)) {
            return response()->json(['title' => 'Policy number does not exists', 'description' => 'Entered policy number does not exist in system', 'status' => "399"], 200);
        }

        if (isset($policy) && $policy->status == 0) {
            $policyTransaction = Transaction::where('policyNumber', $request->policyNumber)->orderBy('id', 'desc')->first();

            if ($policyTransaction == null && $policy == null) {
                return response()->json(['title' => 'Policy number does not exists', 'description' => 'Entered policy number does not exist in system', 'status' => "399"], 200);
            }

            if ((isset($policy) && isset($policy->customer_id) && in_array($policy->product_id,array(1,2,4,5,6,9)))) {

                if($policy->product_id == 2){
                    $vehicles = Vehicle::where('policy_id', $policy->id)->orderBy('id', 'desc')->first();
                   # DB::connection()->enableQueryLog();
                    $policies = Vehicle::join('policies', 'vehicle.policy_id', 'policies.id')
                                     ->where('vehiclePlate', $vehicles->vehiclePlate)
                                     ->where('policies.status', 1)
                                    ->orderBy('id', 'desc')->count();
                                  #  $queries = DB::getQueryLog();
                                 #   dd($policies);

                }else{

                    $policies = Policy::join('customer_kyc', 'customer_kyc.customer_id', 'policies.customer_id')
                    ->where('policies.customer_id', $policy->customer_id)
                    ->where('policies.status', 1)
                    ->where("policies.product_id", $policy->product_id)
                    ->count();
                }
                if (isset($policies) && $policies >= 1) {
                      if($policy->product_id == 5){
                        $policycellphone = PolicyCellPhone::where('policy_id', $policy->id)->orderBy('id', 'desc')->first();
                        $cellphoneCount = PolicyCellPhone::join('policies', 'policy_cellphone.policy_id', 'policies.id')
                                     ->where('imei', $policycellphone->imei)
                                     ->where('policies.status', 1)
                                    ->orderBy('id', 'desc')->count();
                        if(isset($cellphoneCount) && $cellphoneCount >= 1){
                             return response()->json(['title' => 'Customer already registered for this policy', 'description' => 'Customer already registered for this policy', 'error' => 'Customer is already owner same policy. Please check again.', 'status' => "399"], 200);
                        }else{
                          return response()->json(['title' => 'Success', 'description' => 'Success', 'status' => "200"], 200);
                        }

                      }

                    return response()->json(['title' => 'Customer already registered for this policy', 'description' => 'Customer already registered for this policy', 'error' => 'Customer is already owner same policy. Please check again.', 'status' => "399"], 200);
                }else{
                    if(isset($request->payment_method)){
                        if($request->payment_method=="N-Genius"){
                            $policy->BillingStart ="Immediate";
                            $policy->isVirtualBox = null;
                            $policy->save();
                        }
                    }
                    return response()->json(['title' => 'Success', 'description' => 'Success', 'status' => "200"], 200);
                }

            }else{
                return response()->json(['title' => 'Policy not allowed for payment', 'description' => 'Only instant insurance policy is to process payment', 'status' => "399"], 200);
            }

        } else {
            return response()->json(['title' => 'Policy not allowed for payment', 'description' => 'Policy should be deactivated to process payment', 'status' => "399"], 200);
        }
    }

    public function checkPolicyStatusMotorComp(Request $request)
    {
        $policy = Policy::where('policyNumber', $request->policyNumber)->first();
        if (!isset($policy)) {
            return response()->json(['title' => 'Policy number does not exists', 'description' => 'Entered policy number does not exist in system', 'status' => "399"], 200);
        }

        if (isset($policy) && $policy->status == 0) {
            $policyTransaction = Transaction::where('policyNumber', $request->policyNumber)->orderBy('id', 'desc')->first();

            if ($policyTransaction == null && $policy == null) {
                return response()->json(['title' => 'Policy number does not exists', 'description' => 'Entered policy number does not exist in system', 'status' => "399"], 200);
            }

            if (isset($policy) && isset($policy->customer_id) && ($policy->product_id==3)){
                if(isset($request->frequency)){
                    if($request->frequency==1){
                        $policy->premium = $request->premiumMonthly;
                        $policy->first_premium = $request->first_month_premium;
                        $policy->first_premium_wvat = $request->first_month_premium;
                    }elseif($request->frequency==2){
                        $policy->premium = $request->premium3Inst;
                    }elseif($request->frequency==3){
                        $policy->premium = $request->premiumAnnually;
                    }
                    $policy->billing_day = $request->billing_day;
                    $policy->premium_freq = $request->frequency;
                    $policy->save();
                    $checkVehicle = Vehicle::where('policy_id', $policy->id)->first('vehiclePlate');

                    $policies = Policy::join('customer_kyc', 'customer_kyc.customer_id', 'policies.customer_id')
                            ->join('vehicle', 'vehicle.policy_id', 'policies.id')
                            ->where('policies.customer_id', $policy->customer_id)
                            ->where('policies.status', 1)
                            ->where("policies.product_id", $policy->product_id)
                            ->where("vehicle.vehiclePlate", $checkVehicle->vehiclePlate)
                            ->count();
                }else{
                    return response()->json(['title' => 'Frequency for this policy is not available.', 'description' => 'Frequency for this policy is not available.', 'error' => 'Frequency for this policy is not available.', 'status' => "399"], 200);
                }

                if (isset($policies) && $policies >= 1) {
                    return response()->json(['title' => 'Customer already registered for this policy', 'description' => 'Customer already registered for this policy', 'error' => 'Customer is already owner same policy. Please check again.', 'status' => "399"], 200);
                }else{
                    return response()->json(['title' => 'Success', 'description' => 'Success', 'status' => "200"], 200);
                }
            }else{
                return response()->json(['title' => 'Policy not allowed for payment', 'description' => 'Only instant insurance policy is to process payment', 'status' => "399"], 200);
            }
        } else {
            return response()->json(['title' => 'Policy not allowed for payment', 'description' => 'Policy should be deactivated to process payment', 'status' => "399"], 200);
        }
    }

    public function redoPaymentForStart(Request $request)
    {
        $policy = Policy::where('policyNumber', $request->policyNumber)->first();
        $policy->leadsource = 'start.alphadirect.co.bw';
        $policy->BillingStart = $request->BillingStart;
        if($request->BillingStart=="Later"){
            $policy->isVirtualBox = 1;
        }else{
            $policy->isVirtualBox = null;
        }
        $policy->save();
        if (isset($policy) && $policy->status == 0) {
            $policyTransaction = Transaction::where('policyNumber', $request->policyNumber)->orderBy('id', 'desc')->first();

            if ($policyTransaction == null && $policy == null) {

                return response()->json(['title' => 'Policy number does not exists', 'description' => 'Entered policy number does not exist in system'], 401);
            }

            if (isset($policy) && isset($policy->customer_id)) {
                if($policy->product_id == 2){
                    $vehicles = Vehicle::where('policy_id', $policy->id)->orderBy('id', 'desc')->first();
                   # DB::connection()->enableQueryLog();
                    $policies = Vehicle::join('policies', 'vehicle.policy_id', 'policies.id')
                                     ->where('vehiclePlate', $vehicles->vehiclePlate)
                                     ->where('policies.status', 1)
                                    ->orderBy('id', 'desc')->count();
                                  #  $queries = DB::getQueryLog();
                                 #   dd($policies);

                }else{

                    $policies = Policy::join('customer_kyc', 'customer_kyc.customer_id', 'policies.customer_id')
                    ->where('policies.customer_id', $policy->customer_id)
                    ->where('policies.status', 1)
                    ->where("policies.product_id", $policy->product_id)
                    ->count();
                }
                if (isset($policies) && $policies >= 1) {
                    return response()->json(['title' => 'Customer already registered for this policy', 'error' => 'Customer is already owner same policy. Please check again.'], 400);
                }
            } else {
                return response()->json(['title' => 'Policy number or customer does not exists', 'description' => 'Entered policy number or customer does not exist in system'], 401);
            }

            if ((isset($policyTransaction->status) && $policyTransaction->status != 'SUCCESS') || $policyTransaction == null) {

                $vcs = new PaymentController;

                if (isset($request->BillingStart) && $request->BillingStart == "Immediate") {

                    return $vcs->handlePaymentForStartActivationCodeGenerated($request->policyNumber, 'Start', null, 'REFERENCE_NUMBER_EXIST', $request->BillingStart, $request->billing_date);
                }
                return $vcs->handlePaymentForStart($request->policyNumber, 'Start', 'REFERENCE_NUMBER_EXIST', $request->billing_date);
            } else {

                return response()->json(['title' => 'Reference number exists', 'description' => 'A payment has been made with this reference number'], 401);
            }
        } else {
            return response()->json(['title' => 'Policy not allowed for payment', 'description' => 'Policy should be deactivated to process payment'], 401);
        }
    }

    public function redoPaymentMotorCompForStart(Request $request)
    {
        $policy = Policy::where('policyNumber', $request->policyNumber)->first();
        $policy->leadsource = 'start.alphadirect.co.bw';
        $policy->save();
        if (isset($policy) && $policy->status == 0) {
            $policyTransaction = Transaction::where('policyNumber', $request->policyNumber)->orderBy('id', 'desc')->first();

            if ($policyTransaction == null && $policy == null) {

                return response()->json(['title' => 'Policy number does not exists', 'description' => 'Entered policy number does not exist in system'], 401);
            }

            if (isset($policy) && isset($policy->customer_id)) {
                $policies = Policy::join('customer_kyc', 'customer_kyc.customer_id', 'policies.customer_id')
                                    ->where('policies.customer_id', $policy->customer_id)
                                    ->where('policies.status', 1)
                                    ->where("policies.product_id", $policy->product_id)
                                    ->count();
                if (isset($policies) && $policies >= 1) {
                    return response()->json(['title' => 'Customer already registered for this policy', 'error' => 'Customer is already owner same policy. Please check again.'], 400);
                }
            } else {
                return response()->json(['title' => 'Policy number or customer does not exists', 'description' => 'Entered policy number or customer does not exist in system'], 401);
            }

            if ((isset($policyTransaction->status) && $policyTransaction->status != 'SUCCESS') || $policyTransaction == null) {
                $billingDate = $this->setDate($request->billing_day);
                $vcs = new PaymentController;
                return $vcs->handlePaymentForStart($request->policyNumber, 'Start', 'REFERENCE_NUMBER_EXIST', $billingDate);
            } else {

                return response()->json(['title' => 'Reference number exists', 'description' => 'A payment has been made with this reference number'], 401);
            }
        } else {
            return response()->json(['title' => 'Policy not allowed for payment', 'description' => 'Policy should be deactivated to process payment'], 401);
        }
    }

    private function setDate($day)
    {
        $current_timestamp = Carbon::now()->timestamp;
        $newDate = date("d", $current_timestamp);
        $mnth = (int)date("m", $current_timestamp);
        $yr = (int)date("Y", $current_timestamp);

        $v = (int) date('t', mktime(0, 0, 0, $mnth, 1, $yr));

        $days = $newDate - $day;
        if ($days < 0) {
            $date = Carbon::now()->addDays(abs($days))->format('Y-m-d');
            return $date;
        } elseif ($days > 0) {
            $date = Carbon::now()->addDays($v - abs($days))->format('Y-m-d');  /*env('AVERAGE_DAYS')*/
            return $date;
        } else {
            $date = Carbon::now()->format('Y-m-d');
            return $date;
        }
    }

    public function editTransaction($referenceNumber, $premium)
    {
        /* $envState = $this->getPaymentEnv();
        $terminal_id = $envState->terminal_id;
        $username = $envState->username;
        $password = $envState->password;
        $terminal_id = $transactionType  == 'first' ? "3345" : "3385";
        $numberOfDays = 1;
        $frequency = 'M'; // Please change this to billing from activation/product this is for testing with Arun's card
        $nextDate = Carbon::now()->addDay(1)->toDateString();
        $data = $this->calculatePremium($policyNumber);
        $premium = $data->premium;
        $cellphone = $data->cellphone;
        $occuranceCount = 1;
        $PolicyBillingdate = $data->billingStartDate; */
        $client = new \GuzzleHttp\Client();
        $url = "https://www.vcs.co.za/wscs/svc_virtualrecur.asmx";

        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
          <soap12:Body>
            <UpdateCCTransaction xmlns="https://www.vcs.co.za/wscs/svc_virtualrecur.asmx">
              <UpdateCCRequest>
                 <UserName>kamleshk3</UserName>
                 <Password>M6iRVcTAMwD5b5d</Password>
                 <UserID>3385</UserID>
                 <ReferenceNumber>' . $referenceNumber . '</ReferenceNumber>
                 <Amount>' . $premium . '</Amount>
                 <OccurCount>99</OccurCount>
                 <Frequency>M</Frequency>
                 </UpdateCCRequest>
                 </UpdateCCTransaction>
               </soap12:Body>
             </soap12:Envelope>';

        $options = [
            'headers' => [
                'Content-Type' => 'application/soap+xml',
            ],
            'body' => $xml,
        ];


        try {

            $apiRequest = $client->request('POST', $url, $options);
            $response = $apiRequest->getBody()->getContents();

            $clean_xml = str_ireplace(['SOAP-ENV:', 'SOAP:'], '', $response);

            $xml = simplexml_load_string($clean_xml) or die("Error: Cannot create object");

            $resultCode = $xml->Body->UpdateCCTransactionResponse->UpdateCCTransactionResult->ResultCode;
            $resultMessage = $xml->Body->UpdateCCTransactionResponse->UpdateCCTransactionResult->ResultMessage;

            if ($resultCode == 0) {
                return response()->json(['title' => 'Success', 'Description' => 'Premium updated'], 200);
            } else {
                return response()->json(['title' => 'Failed', 'Description' => $resultMessage], 401);
            }
            return $resultCode;
        } catch (RequestException $re) {
            dd($re);
        }
    }

    public function updateCardVcsXml($username, $password, $terminal_id, $referenceNumber, $request)
    {
        $client = new \GuzzleHttp\Client();
        $url = "https://www.vcs.co.za/wscs/svc_virtualrecur.asmx";

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <soap12:Envelope xmlns:soap12="http://www.w3.org/2003/05/soap-envelope" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
           <soap12:Body>
              <UpdateCCNumber xmlns="https://www.vcs.co.za/wscs/svc_virtualrecur.asmx">
                 <UpdateCCNumberRequest>
                    <UserName>' . $username . '</UserName>
                    <Password>' . $password . '</Password>
                    <UserID>' . $terminal_id . '</UserID>
                    <ReferenceNumber>' . $referenceNumber . '</ReferenceNumber>
                    <CardNumber>' . $request->CardNumber . '</CardNumber>
                    <CardExpiryYY>' . $request->CardExpiryYY . '</CardExpiryYY>
                    <CardExpiryMM>' . $request->CardExpiryMM . '</CardExpiryMM>
                    <CVC>' . $request->CVV . '</CVC>
                    <CardHolderName>' . $request->CardHolderName . '</CardHolderName>
                 </UpdateCCNumberRequest>
              </UpdateCCNumber>
           </soap12:Body>
        </soap12:Envelope>';

        $options = [
            'headers' => ['Content-Type' => 'application/soap+xml'],
            'body' => $xml,
        ];
        try {

            $apiRequest = $client->request('POST', $url, $options);
            $response = $apiRequest->getBody()->getContents();
            $clean_xml = str_ireplace(['SOAP-ENV:', 'SOAP:'], '', $response);
            $xml = simplexml_load_string($clean_xml) or die("Error: Cannot create object");
            $resultCode = $xml->Body->UpdateCCNumberResponse->UpdateCCNumberResult->ResultCode;
            $resultMessage = $xml->Body->UpdateCCNumberResponse->UpdateCCNumberResult->ResultMessage;
            $recurringTrans['code'] = $resultCode;
            $recurringTrans['message'] = $resultMessage;
            return $recurringTrans;
        } catch (RequestException $re) {
        }
    }

    public function updateCardVcs(Request $request)
    {
        $policyTransaction = Transaction::where('policyNumber', $request->policyNumber)->where('status', 'SUCCESS')->orderBy('id', 'desc')->first();
        $referenceNumber = $policyTransaction['referenceNumber'];
        /* $terminal_id_first = env('terminal_id_first');
        $username_first = env('username_first');
        $password_first =  env('password_first'); */
        $terminal_id_second = env('terminal_id_second');
        $username_second = env('username_second');
        $password_second = env('password_second');
        $res = $this->updateCardVcsXml($username_second, $password_second, $terminal_id_second, $referenceNumber, $request);
        // $res = $this->updateCardVcsXml($username_first, $password_first, $terminal_id_first,  $referenceNumber, $request);
        if ($res['code'] == '0') {
            $store = new VcsCardDetails();
            $store->card_no = substr($request->CardNumber, -4);
            $store->expiry = $request->CardExpiryMM . '|' . $request->CardExpiryYY;
            $store->agent_id = $request->agent_id;
            $store->is_updated = 1;
            $store->save();

            return response()->json([
                "code" => "200",
                "message" => $res['message'],
            ]);
        } else {
            return response()->json([
                "code" => "400",
                "message" => $res['message'],
            ]);
        }
    }

    public function editTransactionTest(Request $request)
    {
        $envVariable = env('GRAPHITE_URL');
        $numberOfDays = $request->days;
        $currentDate = Carbon::now()->addDay($numberOfDays)->toDateString();
        $nextDate = Carbon::now()->addDay(1)->toDateString();
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
                <ReferenceNumber>TqvvgICR4QvHRtT</ReferenceNumber>
                <Amount>55.00</Amount>
                <StartDate>2019/10/30</StartDate>
                <OccurCount>U</OccurCount>
                <Frequency>M</Frequency>
              </UpdateCCRequest>
            </UpdateCCTransaction>
          </soap12:Body>
        </soap12:Envelope>';
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
        $client = new \GuzzleHttp\Client();
        $url = "https://www.vcs.co.za/wscs/svc_virtualrecur.asmx";
        $xml = '<?xml version="1.0" encoding="utf-8"?>
                <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
                <soap12:Body>
                    <GetCCTransactionList xmlns="https://www.vcs.co.za/wscs/svc_virtualrecur.asmx">
                         <GetCCListRequest UserName="kamleshk3" Password="M6iRVcTAMwD5b5d" UserID="3385" />
                    </GetCCTransactionList>
                </soap12:Body>
                </soap12:Envelope>';

        $options = [
            'headers' => [
                'Content-Type' => 'application/soap+xml',
            ],
            'body' => $xml,
        ];

        try {

            $apiRequest = $client->request('POST', $url, $options);

            $response = $apiRequest->getBody()->getContents();

            $clean_xml = str_ireplace(['SOAP-ENV:', 'SOAP:'], '', $response);

            $xml = simplexml_load_string($clean_xml) or die("Error: Cannot create object");

            $resultCode = $xml->Body->GetCCTransactionListResponse->GetCCTransactionListResult->ResultCode;
            $resultMessage = $xml->Body->GetCCTransactionListResponse->GetCCTransactionListResult->ResultMessage;

            $recurringTrans['code'] = $resultCode;
            $recurringTrans['message'] = $resultMessage;

            if ($resultCode == 0) {
                return response()->json(['title' => 'Success', 'Description' => 'Payment Successful', 'recurringTrans' => $recurringTrans], 200);
            } else {
                return response()->json(['title' => 'Failed', 'Description' => 'Payment Unsuccessful'], 401);
            }
            return $resultCode;
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }
    }

    public function getVcsTransactionListBydate(Request $request)
    {
        /*    $client = new SoapClient(
            "https://www.vcs.co.za/vOnlineWs/VCSQuery/VCSQuery.svc?wsdl",
            array('trace' => true, 'exceptions' => true, "cache_wsdl" => 0)
        );
        try {
            $status = $client->GetTransactionByTIDAndDateRange(array(
                "UserID" => "kamleshk",
                "Password" => "M6iRVcTAMwD5b5d",
                "TerminalID" => "3345",
                "StartDate" => "",
                "EndDate" => "",
            ))->GetTransactionByTIDAndDateRangeResult;
        } catch (SOAPFault $fault) {
            echo "<pre>" . $fault . "</pre>";
            echo "<pre>Last Request:</h3>" . $client->__getLastRequest() . "</pre>";
            echo "<pre>Last Request Headers:</h3>" . $client->__getLastRequestHeaders() . "</pre>";
            echo "<pre>Last Response:</h3><br />" . $client->__getLastResponse() . "</pre>";
            echo "<pre>Last Response Headers:</h3><br />" . $client->__getLastResponseHeaders() .
                "</pre>";
        }
        return $status;
*/
        $client = new SoapClient(
            "https://www.vcs.co.za/vOnlineWs/VCSQuery/VCSQuery.svc?wsdl",
            array('trace' => true, 'exceptions' => true, "cache_wsdl" => 0)
        );
        try {
            $status = $client->GetTransactionByTIDAndReference(array(
                "UserID" => "kamleshk3",
                "Password" => "M6iRVcTAMwD5b5d",
                "TerminalID" => "3385",
                "ReferenceNumber" => 'RyGQnit6M412ufK'
            ))->GetTransactionByTIDAndReferenceResult;
        } catch (SOAPFault $fault) {
            echo "<pre>" . $fault . "</pre>";
            echo "<pre>Last Request:</h3>" . $client->__getLastRequest() . "</pre>";
            echo "<pre>Last Request Headers:</h3>" . $client->__getLastRequestHeaders() . "</pre>";
            echo "<pre>Last Response:</h3><br />" . $client->__getLastResponse() . "</pre>";
            echo "<pre>Last Response Headers:</h3><br />" . $client->__getLastResponseHeaders() . "</pre>";
        }
        return $status;
    }

    public function deleteTransaction(Request $request)
    {

        $envState = $this->getPaymentEnv();
        $terminal_id = $envState->terminal_id;
        $username = $envState->username;
        $password = $envState->password;

        $policy_data = Policy::where('policyNumber', $request->policyNumber)->first();

        $client = new \GuzzleHttp\Client();
        $url = "https://www.vcs.co.za/wscs/svc_virtualrecur.asmx";

        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
          <soap12:Body>
            <DeleteCCTransaction xmlns="https://www.vcs.co.za/wscs/svc_virtualrecur.asmx">
              <DeleteCCRequest>
                <UserName>' . $username . '</UserName>
                <Password>' . $password . '</Password>
                <UserID>' . $terminal_id . '</UserID>
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
            $clean_xml = str_ireplace(['SOAP-ENV:', 'SOAP:'], '', $response);

            $xml = simplexml_load_string($clean_xml) or die("Error: Cannot create object");

            $resultCode = $xml->Body->DeleteCCTransactionResponse->DeleteCCTransactionResult->ResultCode;


            return $resultCode;
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }
    }

    public function suspendTransactionOnVCS($referenceNumber)
    {
        #$referenceNumber = $request->referenceNumber;
        $terminal_id_second = env('terminal_id_second');
        $username_second = env('username_second');
        $password_second = env('password_second');
        $client = new \GuzzleHttp\Client();
        $url = "https://www.vcs.co.za/wscs/svc_virtualrecur.asmx";

        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
          <soap12:Body>
            <SuspendCCTransaction xmlns="https://www.vcs.co.za/wscs/svc_virtualrecur.asmx">
              <SuspendCCRequest>
                <UserName>' . $username_second . '</UserName>
                <Password>' . $password_second . '</Password>
                <UserID>' . $terminal_id_second . '</UserID>
                <ReferenceNumber>' . $referenceNumber . '</ReferenceNumber>
              </SuspendCCRequest>
            </SuspendCCTransaction>
          </soap12:Body>
        </soap12:Envelope>';

        $options = [
            'headers' => [
                'Content-Type' => 'application/soap+xml',
            ],
            'body' => $xml,
        ];

        try {

            $apiRequest = $client->request('POST', $url, $options);

            $response = $apiRequest->getBody()->getContents();
            $clean_xml = str_ireplace(['SOAP-ENV:', 'SOAP:'], '', $response);

            $xml = simplexml_load_string($clean_xml);

            $resultCode = $xml->Body->SuspendCCTransactionResponse->SuspendCCTransactionResult->ResultCode;
            if ($resultCode == '0') {
                $vcsEventLog = new vcsEventLog();
                $vcsEventLog->referenceNumber = $referenceNumber;
                $vcsEventLog->action = 'suspendTransactionOnVCS';
                $vcsEventLog->result_code = $resultCode;
                $vcsEventLog->result_message = $xml->Body->SuspendCCTransactionResponse->SuspendCCTransactionResult->ResultMessage;
                $vcsEventLog->status = 'success';
                $vcsEventLog->save();

                return true;
            } else {
                $vcsEventLog = new vcsEventLog();
                $vcsEventLog->referenceNumber = $referenceNumber;
                $vcsEventLog->action = 'suspendTransactionOnVCS';
                $vcsEventLog->result_code = $resultCode;
                $vcsEventLog->result_message = $xml->Body->SuspendCCTransactionResponse->SuspendCCTransactionResult->ResultMessage;
                $vcsEventLog->status = 'failed';
                $vcsEventLog->save();
                return false;
            }
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }
    }

    public function unsuspendTransactionOnVCS($referenceNumber)
    {
        #$referenceNumber = $request->referenceNumber;
        $terminal_id_second = env('terminal_id_second');
        $username_second = env('username_second');
        $password_second = env('password_second');
        $client = new \GuzzleHttp\Client();
        $url = "https://www.vcs.co.za/wscs/svc_virtualrecur.asmx";

        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
          <soap12:Body>
            <UnSuspendCCTransaction xmlns="https://www.vcs.co.za/wscs/svc_virtualrecur.asmx">
              <UnSuspendCCRequest>
                <UserName>' . $username_second . '</UserName>
                <Password>' . $password_second . '</Password>
                <UserID>' . $terminal_id_second . '</UserID>
                <ReferenceNumber>' . $referenceNumber . '</ReferenceNumber>
              </UnSuspendCCRequest>
            </UnSuspendCCTransaction>
          </soap12:Body>
        </soap12:Envelope>';

        $options = [
            'headers' => [
                'Content-Type' => 'application/soap+xml',
            ],
            'body' => $xml,
        ];

        try {

            $apiRequest = $client->request('POST', $url, $options);

            $response = $apiRequest->getBody()->getContents();
            $clean_xml = str_ireplace(['SOAP-ENV:', 'SOAP:'], '', $response);

            $xml = simplexml_load_string($clean_xml) or die("Error: Cannot create object");
            $resultCode = $xml->Body->UnSuspendCCTransactionResponse->UnSuspendCCTransactionResult->ResultCode;
            if ($resultCode == '0') {
                $vcsEventLog = new vcsEventLog();
                $vcsEventLog->referenceNumber = $referenceNumber;
                $vcsEventLog->action = 'unsuspendTransactionOnVCS';
                $vcsEventLog->result_code = $resultCode;
                $vcsEventLog->result_message = $xml->Body->UnSuspendCCTransactionResponse->UnSuspendCCTransactionResult->ResultMessage;
                $vcsEventLog->status = 'success';
                $vcsEventLog->save();

                return true;
            } else {
                $vcsEventLog = new vcsEventLog();
                $vcsEventLog->referenceNumber = $referenceNumber;
                $vcsEventLog->action = 'unsuspendTransactionOnVCS';
                $vcsEventLog->result_code = $resultCode;
                $vcsEventLog->result_message = $xml->Body->UnSuspendCCTransactionResponse->UnSuspendCCTransactionResult->ResultMessage;
                $vcsEventLog->status = 'failed';
                $vcsEventLog->save();
                return false;
            }
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }
    }

    public function getTransactionDetails(Request $request)
    {

        /* $envState = $this->getPaymentEnv();
        $terminal_id = $envState->terminal_id;
        $username = $envState->username;
        $password = $envState->password; */
        $client = new \GuzzleHttp\Client();
        $url = "https://www.vcs.co.za/wscs/svc_virtualrecur.asmx";

        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
          <soap12:Body>
            <GetCCTransactionDetail xmlns="https://www.vcs.co.za/wscs/svc_virtualrecur.asmx">
              <GetCCDetailRequest>
                <UserName>kamleshk</UserName>
                <Password>M6iRVcTAMwD5b5d</Password>
                <UserID>3345</UserID>
                <ReferenceNumber>BtDLNHzF8TwYFl6</ReferenceNumber>
              </GetCCDetailRequest>
            </GetCCTransactionDetail>
          </soap12:Body>
        </soap12:Envelope>';

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

    public function addTransaction(Request $request)
    {

        $envState = $this->getPaymentEnv();
        $terminal_id = $envState->terminal_id;
        $username = $envState->username;
        $password = $envState->password;
        $client = new \GuzzleHttp\Client();
        $url = "https://www.vcs.co.za/wscs/svc_virtualrecur.asmx";

        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
          <soap12:Body>
            <AddCCTransaction xmlns="https://www.vcs.co.za/wscs/svc_virtualrecur.asmx">
              <AddCCRequest>
                <UserName>kamleshk3</UserName>
                <Password>' . $password . '</Password>
                <UserID>3345</UserID>
                <ReferenceNumber>' . $request->referenceNumber . '</ReferenceNumber>
                <CardNumber>string</CardNumber>
                <CardExpiryYY>string</CardExpiryYY>
                <CardExpiryMM>string</CardExpiryMM>
                <Amount>decimal</Amount>
                <CVC>string</CVC>
                <CardHolderName>string</CardHolderName>
                <CardHolderEmail>string</CardHolderEmail>
                <DescrOfGoods>string</DescrOfGoods>
                <StartDate>string</StartDate>
                <EndDate>string</EndDate>
                <OccurCount>string</OccurCount>
                <Frequency>string</Frequency>
                <MerchantVar1>string</MerchantVar1>
                <MerchantVar2>string</MerchantVar2>
                <MerchantVar3>string</MerchantVar3>
                <MerchantVar4>string</MerchantVar4>
                <MerchantVar5>string</MerchantVar5>
                <MerchantVar6>string</MerchantVar6>
                <MerchantVar7>string</MerchantVar7>
                <MerchantVar8>string</MerchantVar8>
                <MerchantVar9>string</MerchantVar9>
                <MerchantVar10>string</MerchantVar10>
              </AddCCRequest>
            </AddCCTransaction>
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

    public function activateTransaction(Request $request)
    {

        $envState = $this->getPaymentEnv();
        $terminal_id = $envState->terminal_id;
        $username = $envState->username;
        $password = $envState->password;

        $client = new \GuzzleHttp\Client();
        $url = "https://www.vcs.co.za/wscs/svc_virtualrecur.asmx";

        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
          <soap12:Body>
            <ActivateCCTransaction xmlns="https://www.vcs.co.za/wscs/svc_virtualrecur.asmx">
              <ActivateCCRequest>
                <UserName>kamleshk3</UserName>
                <Password>' . $password . '</Password>
                <UserID>3345</UserID>
                <ReferenceNumber>' . $request->referenceNumber . '</ReferenceNumber>
              </ActivateCCRequest>
            </ActivateCCTransaction>
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

    public function getTransactionByReference(Request $request)
    {

        $vcsTransactions = new VcsModel();

        $referenceNumber = $request->referenceNumber;

        $result = $vcsTransactions->GetTransactionByTIDAndReference($referenceNumber);

        $responseCode = $result->original->VCSResponseCode;

        // Check the status and the repsonse code if a transaction was found.

        if ($responseCode === "0000") {

            return response()->json($result->original, 200);
        } else {

            return response()->json($result->original->VCSResponseMessage, 401);
        }
    }

    public function getTransactionByCardNumber(Request $request)
    {

        $vcsTransactions = new VcsModel();

        $cardNumber = $request->cardNumber;

        $result = $vcsTransactions->GetTransactionByTIDAndCardNumber($cardNumber);

        return response()->json($result->original, 200);

        $responseCode = $result->original->VCSResponseCode;

        // Check the status and the repsonse code if a transaction was found.

        if ($responseCode === "0000") {

            return response()->json($result->original, 200);
        } else {

            return response()->json($result->original->VCSResponseMessage, 401);
        }
    }

    public function GetTransactionByDateRange(Request $request)
    {

        $vcsTransactions = new VcsModel();

        //Replace all forward slashes with a dash , VCS documentation was misleading.

        $startDate = str_replace('/', '-', $request->startDate);
        $endDate = str_replace('/', '-', $request->endDate);

        $result = $vcsTransactions->GetTransactionByDateRange($startDate, $endDate);

        $list = $result->original->TransactionViewResponse;

        $slicedList = array_slice($list, 1);

        foreach ($slicedList as $key => $values) {

            if (isset($values->ReferenceNumber)) {
                $vcsTransactions = new VcsModel();
                $vcsTransactions->TerminalID = $values->TerminalID;

                if (strpos($values->ReferenceNumber, '-') !== false) {
                    $vcsTransactions->actualReferenceNumber = substr($values->ReferenceNumber, 0, strpos($values->ReferenceNumber, "-"));
                } else {

                    $vcsTransactions->ReferenceNumber = $values->ReferenceNumber;
                }
                $vcsTransactions->ReferenceNumber = $values->ReferenceNumber;
                $vcsTransactions->CardholderName = $values->CardholderName;
                $vcsTransactions->CardholderEmail = $values->CardholderEmail;
                $vcsTransactions->DescriptionOfGoods = $values->DescriptionOfGoods;
                $vcsTransactions->Amount = $values->Amount;
                $vcsTransactions->ExpiryMonth = $values->ExpiryMonth;
                $vcsTransactions->ExpiryYear = $values->ExpiryYear;
                $vcsTransactions->BankResponse = $values->BankResponse;
                $vcsTransactions->TransactionPresented = $values->TransactionPresented;
                $vcsTransactions->TransactionAuthorised = $values->TransactionAuthorised;
                $vcsTransactions->VCSResponseCode = $values->VCSResponseCode;
                $vcsTransactions->VCSResponseMessage = $values->VCSResponseMessage;
                $vcsTransactions->MaskedCardNumber = $values->MaskedCardNumber;
                $vcsTransactions->TransactionSettled = $values->TransactionSettled;
                $vcsTransactions->MarkUpReference = $values->MarkUpReference;
                $saveStatus = $vcsTransactions->save();
                if ($saveStatus == true) {
                    $param = Transaction::where('referenceNumber', $vcsTransactions->actualReferenceNumber)->first(array('customer_id', 'policyNumber', 'status'));
                    $count = $param->count();
                    if ($count == 0) {
                        $policy = Policy::where('policyNumber', $param->policyNumber)->first(array('id', 'product_id'));
                        /*$claim = Claim::where('policy_id',$policy->id)->first('id');*/   /*For claim payment*/
                        $check = Ledger::where('policy_id', $policy->id)->exists();
                        if ($check == true)
                            Helper::ledgerStore($param->id, 'POLICY', $policy->id, $policy->product_id, 'RENEW');
                        else
                            Helper::ledgerStore($param->id, 'POLICY', $policy->id, $policy->product_id, 'NEWBUSINESS');
                    }
                }
            }
        }

        if ($saveStatus == true) {

            return response()->json(['status' => 'Successful', 'msg' => 'VCS data pulled'], 200);
        } else {

            return response()->json(['status' => 'Failed', 'msg' => 'VCS data pulled has failed'], 401);
        }
    }

    public function testJoin()
    {

        $join = DB::table('vcs_data_dump')
            ->join('transactions', 'vcs_data_dump.actualReferenceNumber', '=', 'transactions.referenceNumber')
            ->join('policies', 'transactions.policyNumber', '=', 'policies.policyNumber')
            ->join('customer', 'policies.customer_id', '=', 'customer.id')
            ->select(
                'vcs_data_dump.ReferenceNumber',
                'vcs_data_dump.BankResponse',
                'vcs_data_dump.TransactionSettled',
                'vcs_data_dump.Amount',
                'vcs_data_dump.DescriptionOfGoods',
                'policies.policyNumber',
                'customer.firstName',
                'customer.lastName',
                'customer.cellphone'
            )
            ->get();

        return response()->json($join);
    }


    public function createInvoiceOdoo($customer, $policy, $request, $referenceNumber, $invoice_number, $product_plan)
    {

        $client = new \GuzzleHttp\Client();
        $url = "http://13.244.123.13/CreateInvoice";
        $requestContent = [
            'form_params' => [
                'partner_id' => $customer->odoo_customer_id,
                'name' => $invoice_number,
                'invoice_date_due' => date("Y-m-d"),
                'ref' => $product_plan->name,
            ],
        ];
        try {
            $apiRequest = $client->request('POST', $url, $requestContent);
            $response = $apiRequest->getBody()->getContents();
            if (is_numeric($response)) {
                // main credit amount post
                $client = new \GuzzleHttp\Client();
                $url = "http://13.244.123.13/CreateInvoiceLineNoTax";
                $requestContent = [
                    'form_params' => [
                        'move_id' => $response,
                        'credit' => $policy->premium,
                        'price_total' => $policy->premium + $policy->vat
                    ],
                ];
                $client->request('POST', $url, $requestContent);

                // tax amount post
                $client = new \GuzzleHttp\Client();
                $url = "http://13.244.123.13/CreateInvoiceLineTax";
                $requestContent = [
                    'form_params' => [
                        'move_id' => $response,
                        'credit' => $policy->vat,
                        'premium' => $policy->premium,
                    ],
                ];
                $client->request('POST', $url, $requestContent);

                // Final entry to null the balance
                $client = new \GuzzleHttp\Client();
                $url = "http://13.244.123.13/CreateInvoiceLineFinal";
                $requestContent = [
                    'form_params' => [
                        'move_id' => $response,
                        'debit' => $policy->premium + $policy->vat,
                        'price_total' => $policy->premium + $policy->vat,
                        'is_company' => $customer->is_company,
                        'odoo_customer_id' => $customer->odoo_customer_id,
                        'price_unit' => gmp_neg($policy->premium + $policy->vat),
                        'price_subtotal' => gmp_neg($policy->premium + $policy->vat),
                        'price_total' => gmp_neg($policy->premium + $policy->vat),
                        'price_unit' => $policy->premium + $policy->vat,

                    ],
                ];
                $client->request('POST', $url, $requestContent);
            }
            return response()->json([
                "code" => "200",
                "message" => "User created succesfully"
            ]);
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }
    }

    public function acceptedCallbackMonthlyVcs(Request $request, $flag = "Recurring")
    {
        //VcsNewTransaction
        $originalReferenceNumber = urldecode($request->p2);
        $plan_name = urldecode($request->p8);
        $amount = urldecode($request->p6);
        $statusCode = urldecode($request->p12);
        // Precedence-bug fix: was `$statusCode == "00" || "0"`, which parses as
        // `($statusCode == "00") || ("0")` and is ALWAYS truthy (a non-empty
        // string literal) — so declined cards were stored as "Success". Preserve
        // the original success set ("00" or "0") with an explicit second compare.
        $status = ($statusCode == "00" || $statusCode == "0") ? "Success" : "Failed";
        $statusRef = urldecode($request->p3);
        $paymentDate = date("Y-m-d");
        $settlementDate = date("Y-m-d");
        $referenceNumber = '';
        if (strpos($originalReferenceNumber, '-') !== false) {
            $referenceNumberArr = explode("-", $originalReferenceNumber);
            $referenceNumber = $referenceNumberArr[0];
            $terminal_id = '3385';
            $trans_type = "Recurring";
        } else {
            $referenceNumber = $originalReferenceNumber;
            $terminal_id = '3345';
            $trans_type = "First";
        }
        if (isset($request->m1) && $request->m1 != null) {
            $trans_type = $request->m1;
        }
        $cardExpiry = urldecode($request->p11);
        $cardYear = substr($cardExpiry, 0, 2);
        $cardMonth = substr($cardExpiry, -2);
        #$this->saveTransaction($request, 'SUCCESS');
        $policyNumber = $this->getReferenceNumber($referenceNumber);
        /*
            $policy = Policy::where('policyNumber', $request->p2)->first();
            $policyController = new PolicyController(); //call the action function to update the status of the Policy to active
         */
        VcsNewTransaction::firstOrCreate([
            'originalReferenceNumber' => $originalReferenceNumber
        ], [
            'reference' => $referenceNumber,
            'name' => urldecode($request->p5),
            'amount' => $amount,
            'goods' => $plan_name,
            'transType' => $trans_type,
            'terminal_id' => $terminal_id,
            'status' => $status,
            'statusRef' => $statusRef,
            'authorision_Date' => $paymentDate,
            'settlement_Date' => $settlementDate,
            'policyNumber' => $policyNumber,
            'exYear' => $cardYear,
            'exMonth' => $cardMonth
        ]);
        /* $VcsNewTransaction = new VcsNewTransaction();
        $VcsNewTransaction->originalReferenceNumber = $originalReferenceNumber;
        $VcsNewTransaction->reference = $referenceNumber;
        $VcsNewTransaction->name = urldecode($request->p5);
        $VcsNewTransaction->amount = $amount;
        $VcsNewTransaction->goods = $plan_name;
        $VcsNewTransaction->transType = $trans_type;
        $VcsNewTransaction->terminal_id = $terminal_id;
        $VcsNewTransaction->status = $status;
        $VcsNewTransaction->statusRef = $statusRef;
        $VcsNewTransaction->authorision_Date = $paymentDate;
        $VcsNewTransaction->settlement_Date = $settlementDate;
        $VcsNewTransaction->policyNumber = $policyNumber;
        $VcsNewTransaction->save();
         */
        #$policyController->action($policy->id, 1); //set the Policy status to active

        /* $customer = Customer::where('id', $policy->customer_id)->first();
        $product_plan = Productplan::where('id', $policy->plan_id)->first(array('name', 'sum_assured', 'premium', 'slug'));

        $invoice_number = Helper::ledgerStore($policy->customer_id, 'POLICY', $policy->id, $policy->product_id, 'NEWBUSINESS');

        $customerController = new CustomerController();
         if ($customer->odoo_customer_id != "") {
            $invoiceData =   $this->createInvoiceOdoo($customer, $policy, $request, $referenceNumber, $invoice_number, $product_plan);
        } else {

            $customerName = $customer->firstName . " " . $customer->middleName . " " . $customer->lastName;
            $email = $customer->email;
            $phone = $customer->cellphone;
            $id = $customer->id;
            $customerController->createOdooPartnerGraphite($id, $customerName, $email, $phone); // getting new customer object with updated data
            $customer = Customer::where('id', $policy->customer_id)->first();
            $invoiceData = $this->createInvoiceOdoo($customer, $policy, $request, $referenceNumber, $invoice_number, $product_plan);
        } */

        $paymentData = array();
        $paymentData['policyNumber'] = $policyNumber;
        $paymentData['referenceNumber'] = $originalReferenceNumber;
        $paymentData['amount'] = $amount;
        $paymentData['status'] = $status;
        $paymentData['paymentDate'] = $paymentDate;
        // Stamp new_payment_date so the ledger sweep (DOM/COM filters on it) can
        // post this card payment to the Account Statement.
        $paymentData['new_payment_date'] = $paymentDate;
        $paymentData['paymentMethod'] = 'VCS';
        $paymentData['numberOfInstalmentsPaid'] = '1';
        $paymentData['note'] = $statusRef;
        $policyController = new policyController();
        $policyController->updatePaymentTransactions($paymentData);

        if ($flag != 'First') {
            return $this->returnXmlForvcs();
        }
    }

    public function declinedCallbackMonthlyVcs(Request $request, $flag = "Recurring")
    {
        //VcsNewTransaction
        $originalReferenceNumber = urldecode($request->p2);
        $plan_name = urldecode($request->p8);
        $amount = urldecode($request->p6);
        $statusCode = urldecode($request->p12);
        $status = $statusCode == "00" ? "Success" : "Failed";
        $statusRef = urldecode($request->p3);;
        $paymentDate = date("Y-m-d" );
        $settlementDate = date("Y-m-d");

        if (strpos($originalReferenceNumber, '-') !== false) {
            $referenceNumberArr = explode("-", $originalReferenceNumber);
            $referenceNumber = $referenceNumberArr[0];
            $terminal_id = '3385';
            $trans_type = "Recurring";
        } else {
            $referenceNumber = $originalReferenceNumber;
            $terminal_id = '3345';
            $trans_type = "First";
        }

        #$this->saveTransaction($request, 'SUCCESS');
        $policyNumber = $this->getReferenceNumber($referenceNumber);
        $cardExpiry = urldecode($request->p11);
        $cardYear = substr($cardExpiry, 0, 2);
        $cardMonth = substr($cardExpiry, -2);
        /*
    $policy = Policy::where('policyNumber', $request->p2)->first();
    $policyController = new PolicyController(); //call the action function to update the status of the Policy to active
 */
        /*$VcsNewTransaction = new VcsNewTransaction();
        $VcsNewTransaction->reference = $referenceNumber;
        $VcsNewTransaction->originalReferenceNumber = $originalReferenceNumber;
        $VcsNewTransaction->name = urldecode($request->p5);
        $VcsNewTransaction->amount = $amount;
        $VcsNewTransaction->goods = $plan_name;
        $VcsNewTransaction->transType = $trans_type;
        $VcsNewTransaction->terminal_id = $terminal_id;
        $VcsNewTransaction->status = $status;
        $VcsNewTransaction->statusRef = $statusRef;
        $VcsNewTransaction->authorision_Date = $paymentDate;
        $VcsNewTransaction->settlement_Date = $settlementDate;
        $VcsNewTransaction->policyNumber = $policyNumber;
        $VcsNewTransaction->save(); */

        VcsNewTransaction::firstOrCreate([
            'originalReferenceNumber' => $originalReferenceNumber
        ], [
            'reference' => $referenceNumber,
            'name' => urldecode($request->p5),
            'amount' => $amount,
            'goods' => $plan_name,
            'transType' => $trans_type,
            'terminal_id' => $terminal_id,
            'status' => $status,
            'statusRef' => $statusRef,
            'authorision_Date' => $paymentDate,
            'settlement_Date' => $settlementDate,
            'policyNumber' => $policyNumber,
            'exYear' => $cardYear,
            'exMonth' => $cardMonth
        ]);
        #$policyController->action($policy->id, 1); //set the Policy status to active

        /* $customer = Customer::where('id', $policy->customer_id)->first();
$product_plan = Productplan::where('id', $policy->plan_id)->first(array('name', 'sum_assured', 'premium', 'slug'));

$invoice_number = Helper::ledgerStore($policy->customer_id, 'POLICY', $policy->id, $policy->product_id, 'NEWBUSINESS');

$customerController = new CustomerController();
 if ($customer->odoo_customer_id != "") {
    $invoiceData =   $this->createInvoiceOdoo($customer, $policy, $request, $referenceNumber, $invoice_number, $product_plan);
} else {

    $customerName = $customer->firstName . " " . $customer->middleName . " " . $customer->lastName;
    $email = $customer->email;
    $phone = $customer->cellphone;
    $id = $customer->id;
    $customerController->createOdooPartnerGraphite($id, $customerName, $email, $phone); // getting new customer object with updated data
    $customer = Customer::where('id', $policy->customer_id)->first();
    $invoiceData = $this->createInvoiceOdoo($customer, $policy, $request, $referenceNumber, $invoice_number, $product_plan);
} */

        $paymentData = array();
        $paymentData['policyNumber'] = $policyNumber;
        $paymentData['referenceNumber'] = $originalReferenceNumber;
        $paymentData['amount'] = $amount;
        $paymentData['status'] = $status;
        $paymentData['paymentDate'] = date("Y-m-d");
        $paymentData['paymentMethod'] = 'VCS';
        $paymentData['numberOfInstalmentsPaid'] = '0';
        $paymentData['note'] = $statusRef;
        $policyController = new policyController();
        $policyController->updatePaymentTransactions($paymentData);
        $policy = Policy::where('policyNumber', $policyNumber)->first();
        $user = Customer::where('id', $policy->customer_id)->first();
        $sms = new SmsMessaging();

        $sms->sendTsosologoSMS(11, $user->cellphone, $policyNumber, $amount, null, null, null);

        if ($flag != 'First') {
            return $this->returnXmlForvcs();
        }
    }


    public function returnXmlForvcs()
    {
        $maps = [];
        $xml = new \SimpleXMLElement('<CallBackResponse>Accepted</CallBackResponse>');
        $this->to_xml($xml, $maps);

        header('Content-type: text/xml');
        echo $xml->asXML();
    }

    public function to_xml(\SimpleXMLElement $object, array $data, $level = 0)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $new_object = $object->addChild(($level == 0) ? 'marker' : $key);
                $this->to_xml($new_object, $value, $level + 1);
            } else {
                $object->addChild($key, $value);
            }
        }
    }

    public function get_payment_status_details(Request $request)
    {
        if ($request->id != null) {
            $policyPaymentStatus = PolicyPaymentStatusDump::find(base64_decode($request->id));
            $details = null;
            $data = [];
            if ($policyPaymentStatus) {
                $details = Policy::with('customer')->where('policyNumber', $policyPaymentStatus->policyNumber)->latest()->first();
                $data['firstName'] = $details->customer->firstName;
                $data['lastName'] = $details->customer->lastName;
                $data['cellphone'] = $details->customer->cellphone;
                $data['policyNumber'] = $policyPaymentStatus->policyNumber;
                $data['balance'] = str_replace('-', '', $policyPaymentStatus->balance);
            }
            return ['data' => $data, 'count' => count($data)];
        }
    }

    public function orangeMoneyOrderNoification(Request $request)
    {
        /*
            [
                {
                    "mandateId": "123abc456def",
                    "amount": "100.5",
                    "currency": "XOF",
                    "executionDate": "2020-05-25",
                    "transactionId": "MP141218.1723.C00152",
                    "transactionStatus": "failed",
                    "code": 10,
                    "reason": "Insufficient funds"
                }
            ]
            mandateId : policy number
        */

        if (count($request->all())) {
         if ($request[0]['mandateId'] != null) {
                if (Policy::where('policyNumber', '=', $request[0]['mandateId'])->exists()) {
                    $result = PaymentTransaction::updateOrCreate([
                        'referenceNumber' => $request[0]['transactionId']
                    ], [
                        "policyNumber" => $request[0]['mandateId'],
                        "amount" =>  $request[0]['amount'],
                        "status" => ucwords($request[0]['transactionStatus']),
                        "paymentDate" => $request[0]['executionDate'],
                        "is_ledger" => 0,
                        "paymentMethod" => "OrangeMoney", //Realpay or VCS
                        "numberOfInstalmentsPaid" => '1',
                        "note" => $request[0]['transactionStatus'] == "success" ?  $request[0]['transactionId'] : $request[0]['reason'],
                    ]);

                    if (!$result->wasRecentlyCreated || $result->wasChanged()) {
                        return response()->json([
                            "code" => "200",
                            "message" => "Records updated successfully",
                            "description" => "Records updated successfully"
                        ], 200);
                    }
                } else {
                    return response()->json([
                        "code" => "23",
                        "message" => "Invalid Mandate Id 1",
                        "description" => "Invalid Mandate Id"
                    ], 400);
                }
            } else {
                return response()->json([
                    "code" => "23",
                    "message" => "Missing Mandate Id 2",
                    "description" => "Missing Mandate Id, missing request body"
                ], 400);
            }
        } else {
            return response()->json([
                "code" => "23",
                "message" => "Missing or invalid body or body fields",
                "description" => "Missing or invalid body or body fields"
            ], 400);
        }
    }

    public function orangeMandates(Request $request)
    {
        /*
         {
            "mandateId": "123abc456def",
            "referenceId": "1234abcd-1234-2000-8abc-0123456789ab",
            "amount": "100.5",
            "country": "CI",
            "creditorAddress": "Siège Social, 14000 Abidjan",
            "IC": "Company001",
            "creditorName": "Company name",
            "currency": "XOF",
            "lang": "fr",
            "offerName": "Offre Découverte",
            "offerId": "abcdef1234",
            "serviceName": "creditorName/offerName",
            "paymentDate": "2020-05-25",
            "paymentFrequency": "month",
            "debitorId": "1234567890",
            "debitorIdOnCreditorSide": "abc4587za",
            "debitorBirthDate": "2020-05-25",
            "debitorBirthPlace": "Abidjan",
            "debitorFirstName": "Leo",
            "debitorLastName": "Yao",
            "creationDate": "2020-05-25",
            "approvalDate": "2020-05-25",
            "bankName": "orange"
            }

         */

        if (count($request->all())) {
            if ($request->mandateId != null) {
                if (Policy::where('policyNumber', '=', $request->mandateId)->exists()) {
                    $result = OrangeMandate::updateOrCreate([
                        'referenceId' => $request->referenceId,
                    ], [
                        "mandateId" =>  $request->mandateId,
                        "referenceId" =>  $request->referenceId,
                        "amount" =>  $request->amount,
                        "country" =>  $request->country,
                        "creditorAddress" =>  $request->creditorAddress,
                        "IC" =>  $request->IC,
                        "creditorName" =>  $request->creditorName,
                        "currency" =>  $request->currency,
                        "lang" =>  $request->lang,
                        "offerName" =>  $request->offerName,
                        "offerId" =>  $request->offerId,
                        "serviceName" =>  $request->serviceName,
                        "paymentDate" =>  $request->paymentDate,
                        "paymentFrequency" =>  $request->paymentFrequency,
                        "debitorId" =>  $request->debitorId,
                        "debitorIdOnCreditorSide" =>  $request->debitorIdOnCreditorSide,
                        "debitorBirthDate" =>  $request->debitorBirthDate,
                        "debitorBirthPlace" =>  $request->debitorBirthPlace,
                        "debitorFirstName" =>  $request->debitorFirstName,
                        "debitorLastName" =>  $request->debitorLastName,
                        "creationDate" =>  $request->creationDate,
                        "approvalDate" =>  $request->approvalDate,
                        "bankName" =>  $request->bankName,
                        "status" => "Registered"
                    ]);

                    if (!$result->wasRecentlyCreated || $result->wasChanged()) {
                        $policy = Policy::with('customer')->where('policyNumber', $request->mandateId)->first();
                        $policy->payment_method = 'orange';
                        $policy->premium = isset($request->amount) ? $request->amount : $policy->premium;
                        $policy->billingStartDate = isset($request->paymentDate) ? $request->paymentDate : $policy->billingStartDate;

                        TestOrangeScheduleTransactionEvent::dispatch($policy);
                        $policy = Policy::with('customer')->where('policyNumber', $request->mandateId)->first();
                        $policy->status = 1;
                        $policy->save();

                        $paymentData = new PaymentTransaction();
                        $paymentData->policyNumber = $request->mandateId;
                        $paymentData->referenceNumber = $request->referenceId;
                        $paymentData->amount = $request->amount;
                        $paymentData->status = "Success";
                        $paymentData->paymentDate =  $request->paymentDate;
                        $paymentData->paymentMethod = 'OrangeMoney';
                        $paymentData->numberOfInstalmentsPaid = '1';
                        $paymentData->note =  $request->referenceId;
                        $paymentData->save();

                        return response()->json([
                            "code" => "200",
                            "message" => "Records updated successfully",
                            "description" => "Records updated successfully"
                        ], 200);
                    }
                } else {
                    return response()->json([
                        "code" => "23",
                        "message" => "Invalid Mandate Id",
                        "description" => "Invalid Mandate Id"
                    ], 400);
                }
            } else {
                return response()->json([
                    "code" => "23",
                    "message" => "Missing Mandate Id",
                    "description" => "Missing Mandate Id"
                ], 400);
            }
        } else {
            return response()->json([
                "code" => "23",
                "message" => "Missing or invalid body or body fields",
                "description" => "Missing or invalid body or body fields"
            ], 400);
        }
    }
    public function orangeMandateUpdate(Request $request)
    {
        /*
         {
            "mandateId": "123abc456def",
            "mandateStatus": "revoked"
            }

         */
        if (count($request->all())) {
            if ($request->mandateId != null) {
                if (OrangeMandate::where('mandateId', '=', $request->mandateId)->exists()) {
                    $result = OrangeMandate::updateOrCreate([
                        'mandateId' => $request->mandateId,
                    ], [
                        "status" => $request->mandateStatus
                    ]);

                    if (!$result->wasRecentlyCreated || $result->wasChanged()) {
                        CancelOrangeScheduleTransactionEvent::dispatch($request->mandateId);
                        return response()->json([
                            "code" => "200",
                            "message" => "Records updated successfully",
                            "description" => "Records updated successfully"
                        ], 200);
                    }
                } else {
                    return response()->json([
                        "code" => "23",
                        "message" => "Invalid Mandate Id",
                        "description" => "Invalid Mandate Id"
                    ], 400);
                }
            } else {
                return response()->json([
                    "code" => "23",
                    "message" => "Missing Mandate Id",
                    "description" => "Missing Mandate Id"
                ], 400);
            }
        } else {
            return response()->json([
                "code" => "23",
                "message" => "Missing or invalid body or body fields",
                "description" => "Missing or invalid body or body fields"
            ], 400);
        }
    }

    public function orangeMandateDispute(Request $request)
    {
        /*
         {
            "mandateId": "123abc456def",
            "mandateStatus": "revoked"
            }

         */
        if (count($request->all())) {
            if ($request->mandateId != null) {
                if (Policy::where('policyNumber', '=', $request->mandateId)->exists()) {
                    $result = OrangeDispute::updateOrCreate([
                        'disputeId' => $request->disputeId,
                    ], [
                        "mandateId" =>  $request->mandateId,
                        "referenceId" =>  $request->referenceId,
                        "amount" =>  $request->amount,
                        "paymentDate" =>  $request->paymentDate,
                        "transactionId" =>  $request->transactionId,
                        "debitorId" =>  $request->debitorId,
                        "debitorIdOnCreditorSide" =>  $request->debitorIdOnCreditorSide,
                        "debitorFirstName" =>  $request->debitorFirstName,
                        "debitorLastName" =>  $request->debitorLastName,
                        "bankName" =>  $request->bankName,
                        "status" => "Dispute"
                    ]);

                    if (!$result->wasRecentlyCreated || $result->wasChanged()) {
                        return response()->json([
                            "code" => "200",
                            "message" => "Records updated successfully",
                            "description" => "Records updated successfully"
                        ], 200);
                    }
                } else {
                    return response()->json([
                        "code" => "23",
                        "message" => "Invalid Mandate Id",
                        "description" => "Invalid Mandate Id"
                    ], 400);
                }
            } else {
                return response()->json([
                    "code" => "23",
                    "message" => "Missing Mandate Id",
                    "description" => "Missing Mandate Id"
                ], 400);
            }
        } else {
            return response()->json([
                "code" => "23",
                "message" => "Missing or invalid body or body fields",
                "description" => "Missing or invalid body or body fields"
            ], 400);
        }
    }

    public function orangeAccess()
    {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => env('ORANGE_BASE_URL').'/oauth/v3/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic '.env('ORANGE_CLIENT_AUTH'),
                'Content-Type: application/x-www-form-urlencoded'
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($response);
        return $res->access_token;
    }

    public function orangeMandateExcute()
    {
        $token = $this->orangeAccess();
        $curl = curl_init();
// exectution date window is 7 days
// amount is non editable for MC skip premium
// notification url is not mandatory, notification will be done on VPN only
// 9 days before

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.orange.com/om_autodebit/pp/v1/bw/orders',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '[
                {
                    "mandateID":  "MIS2022004951",
                    "amount": "29",
                    "currency": "BWP",
                    "executionDate": "2022-07-18"
                }
            ]',
            CURLOPT_HTTPHEADER => array(
                'NotificationURL: https://graphite.alphadirect.co.bw/api/orangeMandateNotification',
                'creditorId: '.env('ORANGE_CREDITOR_ID'),
                'Authorization: Bearer '.$token,
                'Content-Type: application/json;charset=utf-8',
                'Accept: application/json;charset=utf-8',
                'X-OAPI-Application-Id: '.env("ORANGE_APPLICATION_ID"),
                'X-OAPI-Offer-Data: country=BW'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        dd($response);
    }


    public function orangeMandateRevoke()
    {
        $token = $this->orangeAccess();
        $curl = curl_init();
// exectution date window is 7 days
// amount is non editable for MC skip premium
// notification url is not mandatory, notification will be done on VPN only
// 9 days before

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.orange.com/om_autodebit/pp/v1/bw/mandates/external',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_POSTFIELDS => '[
                {
                    "mandateID":  "MIS2022019262"
                   }
            ]',
            CURLOPT_HTTPHEADER => array(
                'creditorId: '.env('ORANGE_CREDITOR_ID'),
                'Authorization: Bearer '.$token,
                'Content-Type: application/json;charset=utf-8',
                'Accept: application/json;charset=utf-8',

            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        dd($response);
    }

    public function getScheduledTransactionForOrange()
    {
        try {
            $transaction_data = [];
            $date = Carbon::today()->addDays(9);
            $scheduledTransaction = ScheduleTransaction::where('payment_method','Orange')->whereDate('billing_date', $date)->get();
            foreach ($scheduledTransaction as $key => $transaction) {
                $data = [
                    'mandateID'         => $transaction->policy_number,
                    'amount'            => $transaction->premium,
                    'currency'          => "BWP",
                    'executionDate'     => $transaction->billing_date
                ];

                array_push($transaction_data, $data);
            }

            return response()->json([
                'Status' => '200',
                'Description' => 'Scheduled Transactions fetched successfully.',
                'data'=> $transaction_data
            ], 200);

        } catch (\Exception $ex) {
            return response()->json([
                "code" => "401",
                "message" => $ex->getMessage() .' '. $ex->getLine(),
            ], 401);
        }
    }

    public function OrangePaymentNoworangeMandateExcute(Request $request)
    {
        $request->validate([
            'id' => 'required|integer'
        ]);


        $scheduleTras = ScheduleTransaction::where('id', $request->id)->first();
        if($scheduleTras == null)
        {
            return response()->json([ 'status' => false, 'message'=>'This transactions does not exists.'], 500);
        }

        $token = $this->orangeAccess();
        $curl = curl_init();
        // exectution date window is 7 days
        // amount is non editable for MC skip premium
        // notification url is not mandatory, notification will be done on VPN only
        // 9 days before

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.orange.com/om_autodebit/pp/v1/bw/orders',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '[
                {
                    "mandateID":  "'. $scheduleTras->policy_number .'",
                    "amount": "'. $scheduleTras->premium .'",
                    "currency": "BWP",
                    "executionDate": "'. $scheduleTras->billing_date .'"
                }
            ]',
            CURLOPT_HTTPHEADER => array(
                'NotificationURL: https://graphite.alphadirect.co.bw/api/orangeMandateNotification',
                'creditorId:'.env('ORANGE_CREDITOR_ID'),
                'Authorization: Bearer '.$token,
                'Content-Type: application/json;charset=utf-8',
                'Accept: application/json;charset=utf-8',
                'X-OAPI-Application-Id:'.env('ORANGE_APPLICATION_ID'),
                'X-OAPI-Offer-Data: country=BW'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        dd($response);
    }


    public function removeOrangePayment(Request $request)
    {
        $request->validate([
            'id' => 'required|integer'
        ]);


        $scheduleTras = ScheduleTransaction::where('id', $request->id)->first();
        if($scheduleTras == null)
        {
            return response()->json([ 'status' => false, 'message'=>'This transactions does not exists.'], 500);
        }

        $token = $this->orangeAccess();
        $curl = curl_init();
// exectution date window is 7 days
// amount is non editable for MC skip premium
// notification url is not mandatory, notification will be done on VPN only
// 9 days before

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.orange.com/om_autodebit/pp/v1/bw/mandates/external',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_POSTFIELDS => '[
                {
                    "mandateID":  "'. $scheduleTras->policy_number .'",
                }
            ]',
            CURLOPT_HTTPHEADER => array(
                'NotificationURL: https://graphite.alphadirect.co.bw/api/orangeMandateNotification',
                'creditorId:'.env('ORANGE_CREDITOR_ID'),
                'Authorization: Bearer '.$token,
                'Content-Type: application/json;charset=utf-8',
                'Accept: application/json;charset=utf-8',
                'X-OAPI-Application-Id:'.env('ORANGE_APPLICATION_ID'),
                'X-OAPI-Offer-Data: country=BW',
               # 'creditorId:75007883'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        dd($response);
    }

    public function getHibPremiumByProductPlan($dependents,$productPlan,$premiumMeta,$returnBreakUp = 0)
    {
        $totalPremium = 0;
        $totalVatAmount = 0;
        $dependentsTotalPremium = 0;
        $premiumBreakUp = [];
        //Log::debug("Dependents",$dependents);
        $regionVat = Region::where('id', $productPlan->product->region_id)->first(array('vat'))->vat;
        //calculate principal subscriber premium with vat
        $principalSubscriberPremium = (isset($productPlan->premium))? $productPlan->premium : 0;
        $principalSubscriberPremiumWithVat = round(($principalSubscriberPremium * ($regionVat / 100)) + $principalSubscriberPremium, 2);
        $principalSubscriberVatAmount = round(($principalSubscriberPremium * ($regionVat / 100)),2);
        $totalVatAmount += $principalSubscriberVatAmount;
        $principalSubscriberPremiumBreakUp = [
            'relation' => 0,//self
            'premium_without_vat' => $principalSubscriberPremium,
            'premium_with_vat' => $principalSubscriberPremiumWithVat,
            'vat_amount' => $principalSubscriberVatAmount,
            'vat_percent' => $regionVat
        ];
        $premiumBreakUp[] = $principalSubscriberPremiumBreakUp;
        //calculate dependents premium
        foreach ($dependents as $dependent)
        {
            $dependentPremiumBreakUp = [];
            $relationship = (isset($dependent['dependent_relationship'])) ? $dependent['dependent_relationship'] : null;
            $dob = (isset($dependent['dependent_dob'])) ? $dependent['dependent_dob'] : null;
            if(isset($relationship) && isset($dob))
            {
                $dobObj = \Carbon\Carbon::createFromFormat('d/m/Y',$dob);
                $today = Carbon::now();
                $dependentAge = $dobObj->diffInYears($today);
                if($dependentAge > 21)
                {
                    //filter the premium meta and reindex the result array
                    $premiumData = array_values(array_filter($premiumMeta,function($item){
                        return $item['relation'] == "Adult";
                    }));
                    $premiumAmount = $premiumData[0]['premium'];
                    $premiumWithVat = round(($premiumAmount * ($regionVat / 100)) + $premiumAmount, 2);
                    $dependentsTotalPremium += $premiumWithVat;
                    $dependentVatAmount = round(($premiumAmount * ($regionVat / 100)),2);
                    $totalVatAmount += $dependentVatAmount;
                    $dependentPremiumBreakUp = [
                        'relation' => $relationship,
                        'premium_without_vat' => $premiumAmount,
                        'premium_with_vat' => $premiumWithVat,
                        'vat_amount' => $dependentVatAmount,
                        'vat_percent' => $regionVat
                    ];
                }
                if($dependentAge <= 21)
                {
                    $premiumData = array_values(array_filter($premiumMeta,function($item){
                        return $item['relation'] == "Child";
                    }));
                    $premiumAmount = $premiumData[0]['premium'];
                    $premiumWithVat = round(($premiumAmount * ($regionVat / 100)) + $premiumAmount, 2);
                    $dependentsTotalPremium += $premiumWithVat;
                    $dependentVatAmount = round(($premiumAmount * ($regionVat / 100)),2);
                    $totalVatAmount += $dependentVatAmount;
                    $dependentPremiumBreakUp = [
                        'relation' => $relationship,
                        'premium_without_vat' => $premiumAmount,
                        'premium_with_vat' => $premiumWithVat,
                        'vat_amount' => $dependentVatAmount,
                        'vat_percent' => $regionVat
                    ];
                }
                $premiumBreakUp[] = $dependentPremiumBreakUp;
            }
        }//for dependents
        //Log::debug("Dependents total premium ",[$dependentsTotalPremium]);
        $totalPremium += $principalSubscriberPremiumWithVat + $dependentsTotalPremium;
        if($returnBreakUp)
        {
            return ['total_premium' => $totalPremium, 'total_vat_amount' => $totalVatAmount, 'premium_break_up' => $premiumBreakUp];
        }
        else
        {
            return $totalPremium;
        }
    }
}
