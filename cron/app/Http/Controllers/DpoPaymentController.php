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

class DpoPaymentController extends Controller
{
    public $policyController;

    public function __construct()
    {
        $this->policyController = new PolicyController();
    }

    public function findPolicyForOnlinePayment(Request $request)
    {
        // dd($request->all());
        if(!isset($request->searchValue) || $request->searchValue == null){
            return response()->json(['status' => false, 'message'=>'Please provide policy number.' ], 403);
        }

        if(!Policy::where('policyNumber', $request->searchValue)->exists())
        {
            return response()->json(['status' => false, 'message'=>'Policy number not found.' ], 400);
        }

        $activity     = new PaymentActivityLog();
        $policyNumber = $request->searchValue;
        $policy       = Policy::FindPolicyWithCustomer($policyNumber);

        try {
            if(!isset($request->requestType) && $request->requestType == 'reinstate')
            {
                if($policy->status == 2)
                {
                    return response()->json(['status' => false, 'message'=>'Policy number is canceled.' ], 400);
                }
            }

                if(isset($request->billing_date))
                {
                    $policy->billingStartDate = Carbon::createFromFormat('d/m/Y', $request->billing_date)->format('Y-m-d') ;
                }

                if(isset($request->BillingStart))
                {
                    $policy->BillingStart = $request->BillingStart;
                }

                if(isset($request->email))
                {
                    $policy->customer->email = $request->email;
                    if(ScheduleTransaction::where('policy_number', $policy->policyNumber)->exists())
                    {
                        $scheduleTransaction = ScheduleTransaction::where('policy_number', $policy->policyNumber)->update(['email' => $request->email]);
                    }
                }

                $policy->customer->save();
                $policy->save();

                $customer_data   = $policy->customer->id;

                if(isset($request->amount))
                {
                    $premium         = (float)str_replace(',' ,'' , number_format($request->amount ,2 ,'.', ','));

                }
                elseif(isset($request->requestType) && $request->requestType == 'rerate')
                {
                    switch($request->frequency){
                        case 1:
                            $premium = $request->monthly;
                            break;
                        case 2:
                            $premium = $request->threeInstalment;
                            break;
                        case 3:
                            $premium = $request->annual;
                            break;
                    }
                }
                elseif($policy->product_id == 3)
                {
                    $frequency  = $policy->premium_freq;
                    if($frequency == 1 && (float)$policy->first_premium_wvat > 0){
                        $premium = (float)str_replace(',' ,'' , number_format($policy->first_premium_wvat ,2 ,'.', ','));
                    } else {
                        $policyController = new PolicyController;
                        $premiumArr = $policyController->getMotorComprehensivePremium($policy->policyNumber);
                        switch($frequency){
                            case 1:
                                $premium = (float)str_replace(',' ,'' , number_format($premiumArr['monthly'] ,2 ,'.', ','));
                                break;

                            case 2:
                               $premium = (float)str_replace(',' ,'' , number_format($premiumArr['3_inst'] ,2 ,'.', ','));
                                break;

                            case 3:
                               $premium = (float)str_replace(',' ,'' , number_format($premiumArr['annual'] ,2 ,'.', ','));
                                break;

                                default:
                             break;
                        }
                    }

                }else{
                    if($policy->BillingStart == 'Immediate' )
                    {
                        $premium         = isset($policy->premium) ? (float)str_replace(',' ,'' ,number_format($policy->premium ,2 ,'.', ',')) : 1.00;
                    }else{
                        $premium         = 1.00;
                    }
                }

                if ($policy->is_bundled == 1) {
                    $PolicyBundled = PolicyBundled::where('policy_id',$policy->id)->where('product_id',3)->first();
                    if($PolicyBundled != null && $PolicyBundled->frequency_mc == 1){
                        $premium =  $policy->first_premium_wvat;
                    }
                }

                $first_name      = isset($policy->customer->firstName) ? $policy->customer->firstName : null;
                $last_name       = isset($policy->customer->lastName)  ? $policy->customer->lastName  : null;
                $cellphone       = isset($policy->customer->cellphone) ? $policy->customer->cellphone : null;
                $email           = isset($policy->customer->email) ? $policy->customer->email : null;
                $city_name       = isset($policy->profile->city) ? $policy->profile->city : null;
                $address         = isset($policy->profile->address) ? $policy->profile->address : null;
                $leadsource      = isset($request->leadSource) ? '&amp;leadSource='.$request->leadSource : null;
                //get parameters passing througth redirect url and encode them
                $policy_number = base64_encode($policy->policyNumber);
                $amount        = base64_encode((float)str_replace(',' ,'' ,number_format($premium ,2 ,'.', ',')));

                $requestType = isset($request->requestType) ? $request->requestType: null;

                switch ($requestType)
                {
                    case  'renew':
                    $paraArray   = [
                        'policy_id'      => $policy->id,
                        'payment_method' => 'DPO',
                        'frequency'      => $request->frequency,
                        'amount'         => (string)sprintf("%.2f", $request->amount),
                        'premium'        => (string)sprintf("%.2f", $request->premium),
                        'first_premium'  => (string)sprintf("%.2f", $request->first_premium),
                        'term_premium'   => (string)sprintf("%.2f", $request->term_premium),
                        'policy_number'  => $policy_number,
                    ];

                    $paraGet     = str_replace('&', '&amp;', $request->fullUrlWithQuery(array_merge($paraArray)));
                    $get         = stristr($paraGet,"?");
                    $redirectUrl = env('GRAPHITE_URL').'api/policyRenewPay'. $get;
                    $backUrl     = env('GRAPHITE_URL').'api/policyRenewalPaymentFailed'. $get ;
                    $declinedURL = env('GRAPHITE_URL').'api/policyRenewalPaymentDeclined'. $get;
                    break;

                    case 'reinstate':
                    $paraArray   = [
                        'policy_id'      => $policy->id,
                        'payment_method' => 'DPO',
                        'frequency'      => isset($request->frequency) ?$request->frequency : null,
                        'amount'         => isset($request->amount) ?(string)sprintf("%.2f", $request->amount) : null,
                        'premium'        => isset($request->premium) ? (string)sprintf("%.2f", $request->premium) : null,
                        'first_premium'  => isset($request->first_premium) ? (string)sprintf("%.2f", $request->first_premium) : null,
                        // 'term_premium'   => isset($request->term_premium) ? (string)sprintf("%.2f", $request->term_premium) : null,
                        'term_permium'   => isset($request->term_permium) ? (string)sprintf("%.2f", $request->term_permium) : null,
                        'new_premium'    => isset($request->new_premium) ? (string)sprintf("%.2f", $request->new_premium) : null,
                        'reinstate_type' => isset($request->reinstate_type) ? $request->reinstate_type : null,
                        'policy_number'  => isset($policy_number) ? $policy_number : null,
                        'calculated_premium'  => isset($calculated_premium) ? $calculated_premium : null,
                        'reinstated_by' => isset($request->reinstated_by) ? $request->reinstated_by : null,
                        'balance_due' => isset($request->balance_due) ? $request->balance_due : null,
                    ];

                    $paraGet     = str_replace('&', '&amp;', $request->fullUrlWithQuery(array_merge($paraArray)));
                    $get         = stristr($paraGet,"?");
                    $redirectUrl = env('GRAPHITE_URL').'api/PolicyReinstate'. $get;
                    $backUrl     = env('GRAPHITE_URL').'api/PolicyReinstateFailed'. $get ;
                    $declinedURL = env('GRAPHITE_URL').'api/PolicyReinstateDeclined'. $get;
                    break;

                    case 'rerate':
                    $paraArray =[
                        'monthly'         => $request->monthly,
                        'threeInstalment' => $request->threeInstalment,
                        'annual'          => $request->annual,
                        'frequency'       => $request->frequency,
                        'policyNumber'    => $policy->policyNumber,
                        'payment_method'  => 'DPO',
                        'linkToken'       => $request->linkToken,
                        'sum_assured'     => $request->sum_assured,
                    ];

                    $paraGet     = str_replace('&', '&amp;', $request->fullUrlWithQuery(array_merge($paraArray)));
                    $get         = stristr($paraGet,"?");
                    $redirectUrl = env('GRAPHITE_URL').'api/reratePolicyUpdate'.$get;
                    $backUrl     = env('GRAPHITE_URL').'api/rerateFailedWithDpo/'.$policy_number.$get;
                    $declinedURL = env('GRAPHITE_URL').'api/rerateWithDpo/'.$policy_number.$get;
                    break;

                    case 'updateCard':
                    $paraArray   = [
                        'policy_id'      => $policy->id,
                        'payment_method' => 'DPO',
                        // 'frequency'      => $request->frequency,
                        'billing_date'   => $request->billing_date,
                        'amount'         => (string)sprintf("%.2f", $request->amount),
                        'policy_number'  => $policy_number,
                    ];


                    $paraGet     = str_replace('&', '&amp;', $request->fullUrlWithQuery(array_merge($paraArray)));
                    $get         = stristr($paraGet,"?");
                    $redirectUrl = env('GRAPHITE_URL').'api/updateContract'. $get;
                    $backUrl     = env('GRAPHITE_URL').'api/updateContractFailed'. $get ;
                    $declinedURL = env('GRAPHITE_URL').'api/updateContractFailed'. $get;
                    break;

                    default:
                    $redirectUrl = env('GRAPHITE_URL') .'api/saveonlinepayment?policy_number='.$policy_number.'&amp;amount='.$amount. '&amp;status=success'. $leadsource ;
                    $backUrl     = env('GRAPHITE_URL') .'api/saveonlinepayment?policy_number='.$policy_number.'&amp;amount='.$amount .'&amp;status=failed'. $leadsource;
                    $declinedURL = env('GRAPHITE_URL') .'api/saveonlinepayment?policy_number='.$policy_number.'&amp;amount='.$amount .'&amp;status=cancel'. $leadsource;
                }

                 $xml = '<?xml version="1.0" encoding="utf-8"?>
						<API3G>
							<CompanyToken>'. env('COMPANY_TOKEN') .'</CompanyToken>
                            <Request>createToken</Request>
							<Transaction>
								<PaymentAmount>'.$premium .'</PaymentAmount>
								<PaymentCurrency>'. env('PAYMENT_CURRENCY') .'</PaymentCurrency>
								<CompanyRef>'. /* env('COMPANY_REF') */ $policy->policyNumber .'</CompanyRef>
								<RedirectURL>'. $redirectUrl .'</RedirectURL>
								<BackURL>'. $backUrl .'</BackURL>
                                <DeclinedURL>'. $declinedURL .'</DeclinedURL>
								<CompanyRefUnique></CompanyRefUnique>
								<PTL>2</PTL>
								<PTLtype>hours</PTLtype>
                                <TransactionChargeType>1</TransactionChargeType>
                                <customerFirstName>' . $first_name . '</customerFirstName>
								<customerLastName>' . $last_name . '</customerLastName>
								<customerZip></customerZip>
								<customerCity>' . $city_name . '</customerCity>
								<customerAddress>' . $address . '</customerAddress>
								<customerCountry>'. env('CUSTOMER_COUNTRY')  .'</customerCountry>
								<customerPhone>' . $cellphone . '</customerPhone>
                                <customerDialCode>'. env('CUSTOMER_COUNTRY') .'</customerDialCode>
								<customerEmail>' . $email . '</customerEmail>
                                <AllowRecurrent>1</AllowRecurrent>
							</Transaction>
							<Services>
								<Service>
									<ServiceType>'. env('SERVICE_TYPE') .'</ServiceType>
									<ServiceDescription>Package delivery</ServiceDescription>
									<ServiceDate>'.\Carbon\Carbon::now()->format("Y/m/d").'</ServiceDate>
								</Service>
							</Services>
						</API3G>
					';
                    // dd( $xml);
					$url = env('DPO_URL').'API/v6/';
					$curl = curl_init($url);
					curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: text/xml"));
					curl_setopt($curl, CURLOPT_POST, true);
					curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);
					curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
					$result = curl_exec($curl);
                    // dd($result);
					if(curl_errno($curl)){
                        $activity->policy_number   = $policy->policyNumber;
                        $activity->customer_id     = $policy->customer->id;
                        $activity->amount          = $premium;
                        $activity->curl_error_code = curl_errno($curl);
                        $activity->reason          = curl_strerror(curl_errno($curl));
                        $activity->api_name       = 'createToken';
                        $activity->save();

                        activity('Policy')
                        ->performedOn($policy)
                        ->log('Online payment failed of amount K '.$policy->premium. ' curl error code:'.curl_errno($curl) );
                        return response()->json(['status' => false,'product_id' => $policy->product_id, 'type' => 'error' , 'message' =>  'Payment can\'t be proceed due to some reason', 'hint' => 'curl error' ], 500);
        		    }
					curl_close($curl);
					$final_result = simplexml_load_string($result);
					$dpoarray     = $this->xml2array($final_result);
                    // dd($dpoarray);

                    //request
                    $request_data = $this->xmlToJson($xml);
                    // dd($request_data);
                    if($dpoarray['Result'] == "000")
                    {
                        $activity->policy_number  = $policy->policyNumber;
                        $activity->customer_id    = $policy->customer->id;
                        $activity->amount         = $premium;
                        $activity->dpo_error_code = $dpoarray['Result'];
                        $activity->reason         = $dpoarray['ResultExplanation'];
                        $activity->api_name       = 'createToken';
                        $activity->TransToken     = $dpoarray['TransToken'];
                        $activity->response_json  = json_encode($dpoarray);
                        $activity->request_json   = $request_data;
                        $activity->save();

                        activity('Policy')
                        ->performedOn($policy)
                        ->log('Online payment started of amount K '.$policy->premium);
                        return response()->json(['status' => true, 'product_id' => $policy->product_id, 'type' => 'success' , 'url'=> env('DPO_PAY_URL') ."?ID=".$dpoarray['TransToken'], 'token'=>$dpoarray['TransToken'],'policyNumber'=>$policy_number ], 200);
                    }else{
                        $activity->policy_number  = $policy->policyNumber;
                        $activity->customer_id    = $policy->customer->id;
                        $activity->amount         = $premium;
                        $activity->dpo_error_code = $dpoarray['Result'];
                        $activity->reason         = $dpoarray['ResultExplanation'];
                        $activity->response_json  = json_encode($dpoarray);
                        $activity->request_json   =  $request_data;
                        $activity->api_name       = 'createToken';
                        $activity->save();

                        activity('Policy')
                        ->performedOn($policy)
                        ->log('Online payment failed of amount K '.$policy->premium. ' payment error code:'.$dpoarray['Result']  );
                        return response()->json(['status' => false, 'product_id' => $policy->product_id, 'type' => 'error' , 'message' => $dpoarray['ResultExplanation'], 'result' => $dpoarray['Result'], 'hint' => 'DPO error','policyNumber'=>$policy_number ], 500);
                    }

                }catch(\Exception $e){
                    $activity->policy_number  = $policy->policyNumber;
                    $activity->customer_id    = $policy->customer->id;
                    $activity->reason         = $e->getMessage();
                    $activity->save();

                    activity('Policy')
                    ->performedOn($policy)
                    ->log('Online payment failed of amount K '.$policy->premium. ' payment error message:'.$e->getMessage()  );
                    return response()->json(['status' => false, 'product_id' => $policy->product_id, 'type' => 'error', 'message'=>$e->getMessage() ], 500);
                }
    }

    public function saveOnlinePayment(Request $request)
    {
        $policy_number = base64_decode($request->policy_number);
        $amount        = $request->amount;

        DB::beginTransaction();
        try{
            $policy = Policy::with('customer')->where('policyNumber', $policy_number)->first();

            if($policy == null)
            {
                 return response()->json(['status' => false , 'message' => 'Policy not found'], 401);
            }

            if($policy->status == 2)
            {
                return response()->json(['status' => false , 'message' => 'Canceled policy is not available for this feature.'], 401);
            }

            $data = [
                    "token"            => $request->TransactionToken,
                    "policy_number"    => $policy->policyNumber,
                    "TransID"          => isset($request->TransactionToken)      ? strtoupper($request->TransactionToken)     : null,
                    "CCDapproval"      => isset($request->CCDapproval)  ? strtoupper($request->CCDapproval) : null,
                    "PnrID"            => isset($request->PnrID)        ? strtoupper($request->PnrID)       : null,
                    "TransactionToken" => isset($request->TransactionToken) ? strtoupper($request->TransactionToken) : null,
                    "CompanyRef"       => isset($request->CompanyRef)   ? strtoupper($request->CompanyRef)  : null,
                    "customer_id"      => $policy->customer_id,
                    'leadSource'       => $request->leadSource
                ];


            //fire event send sms and email when policy is created
            $event                                       = VerifyTokenEvent::dispatch($data);
            $event                                       = $event[0];
            $paymentTransaction                          = new PaymentTransaction();
            $paymentTransaction->policyNumber            = $policy->policyNumber;
            $paymentTransaction->referenceNumber         = isset($request->TransactionToken)        ? strtoupper($request->TransactionToken)     : null;
            $paymentTransaction->amount                  = $event['amount'] ;
            $paymentTransaction->status                  = $event['status'] == 1 ? 'SUCCESS' : 'FAILED';
            $paymentTransaction->paymentDate             = $event['status'] == 1 ? Carbon::now() :  Carbon::now();
            $paymentTransaction->paymentMethod           = 'DPO';
            $paymentTransaction->numberOfInstalmentsPaid = 0;
            $paymentTransaction->paymentFrequency        = isset($request->payment_frequency) ?  $request->payment_frequency : $policy->premium_freq;
            $paymentTransaction->TransID                 = isset($request->TransactionToken)  ? strtoupper($request->TransactionToken)     : null;
            $paymentTransaction->CCDapproval             = isset($request->CCDapproval)    ? strtoupper($request->CCDapproval) : null;
            $paymentTransaction->PnrID                   = isset($request->PnrID)          ? strtoupper($request->PnrID)       : null;
            $paymentTransaction->TransactionToken        = isset($request->TransactionToken) ? strtoupper($request->TransactionToken) : null;
            $paymentTransaction->CompanyRef              = isset($request->CompanyRef)     ? strtoupper($request->CompanyRef)  : null;
            $paymentTransaction->save();
            $policyController = new PolicyController(); //call the action function to update the status of the Policy to active
            if( $event['status'] == 1 )
            {
                $action = $policyController->action($policy->id, 1, 'DPO'); //set the Policy status to active
                if($policy->customer->email != null)
                {
                    ScheduleTransactionEvent::dispatch($policy);
                }else{
                    return response()->json(['status' => false , 'message' => 'Please provide email.'], 400);
                }
            }

            $user             = Customer::where('id', $policy->customer_id)->first();
            // $product_plan     = Productplan::where('id', $policy->plan_id)->first(array('sum_assured', 'premium', 'slug'));
            Helper::ledgerStore($policy->customer_id, 'POLICY', $policy->id, $policy->product_id, 'NEWBUSINESS');
            $sms              = new SmsMessaging();
            if ($event['status'] == 1 ) {
                $sms->sendPaySuccessSMS(4, $user->cellphone, $policy->premium);
            }else{
                $sms->sendPaymentFailedSMS(21, $user->firstName, $policy->policyNumber, $user->cellphone, $policy->premium);
            }

            DB::commit();
            if ($event['status'] == 1 ) {
                if(isset($request->leadSource) && $request->leadSource == 'graphite')
                {
                    return redirect()->route('admin.policy.payWithDpo')->with('success', 'Payment done successfully.');
                }
                elseif(isset($request->leadSource) && $request->leadSource == 'MobileApp')
                {
                    return response()->json(['status' => true, 'message' => 'Payment Done succesfully.', 'type' => 'success'], 200);
                }
                elseif(isset($request->leadSource) && $request->leadSource == 'start.alphadirect.co.bw')
                {
                    return redirect('https://start.alphadirect.co.bw/thank-you-payment-successful?policyNumber='.$policy_number.'&amount='. $amount );
                }
                else{
                    $notification = array(
                        'message'      => 'Payment Done successfully',
                        'alert-type'   => 'success',
                        'policyNumber' => $policy_number,
                        'amount'       => $amount
                    );
                    return redirect(env('LIVEQUOTE_URL').'thank_you_payment_successfull.php?policyNumber='.$policy_number )->with('notification', $notification);
                }
            } else {
                if(isset($request->leadSource) && $request->leadSource == 'graphite')
                {
                    return redirect()->route('admin.policy.payWithDpo')->withError('Payment failed. '. $event['reason']);
                }
                elseif(isset($request->leadSource) && $request->leadSource == 'MobileApp')
                {
                    return response()->json(['status' => false, 'message' => 'Payment failed, please try again later', 'type' => 'error'], 401);
                }
                elseif($request->has('leadSource')  && $request->leadSource == 'start.alphadirect.co.bw')
                {
                    return redirect('https://start.alphadirect.co.bw/payment-failed?policyNumber='.$policy_number.'&amount='. $amount );
                }
                else{
                    $notification = array(
                        'message'      => 'Payment failed, please try again later',
                        'alert-type'   => 'failed',
                        'policyNumber' => $policy_number,
                        'amount'       => $amount
                    );

                $sms = new PaymentTransaction();
                $sent = $sms->sendSMSOnFailedPayments($policy_number,$amount);



                    return redirect(env('START_URL').'payment_failed.php')->with('notification', $notification);
                }
                //response for unsuccessful deletetion - (Important - status code for Flutter app)
            }
        }catch (\Exception $ex) {
            //throw $th;
            DB::rollback();
            return response()->json(['status' => false, 'message' => $ex->getMessage(), 'type' => 'error'], 500);
        }
    }


    public function updateContract(Request $request)
    {
        $policy_number = base64_decode($request->policy_number);
        $amount        = base64_decode($request->amount);

        DB::beginTransaction();
        try{
            $policy = Policy::with('customer')->where('policyNumber', $policy_number)->first();

            if($policy == null)
            {
                 return response()->json(['status' => false , 'message' => 'Policy not found'], 401);
            }

            if($policy->status == 2)
            {
                return response()->json(['status' => false , 'message' => 'Canceled policy is not available for this feature.'], 401);
            }

            $data = [
                    "token"            => $request->TransactionToken,
                    "policy_number"    => $policy->policyNumber,
                    "TransID"          => isset($request->TransactionToken)      ? strtoupper($request->TransactionToken)     : null,
                    "CCDapproval"      => isset($request->CCDapproval)  ? strtoupper($request->CCDapproval) : null,
                    "PnrID"            => isset($request->PnrID)        ? strtoupper($request->PnrID)       : null,
                    "TransactionToken" => isset($request->TransactionToken) ? strtoupper($request->TransactionToken) : null,
                    "CompanyRef"       => isset($request->CompanyRef)   ? strtoupper($request->CompanyRef)  : null,
                    "customer_id"      => $policy->customer_id,
                    'leadSource'       => $request->leadSource
                ];


            //fire event send sms and email when policy is created
            $event                                       = VerifyTokenEvent::dispatch($data);
            $event                                       = $event[0];
            $paymentTransaction                          = new PaymentTransaction();
            $paymentTransaction->policyNumber            = $policy->policyNumber;
            $paymentTransaction->referenceNumber         = isset($request->TransactionToken)        ? strtoupper($request->TransactionToken)     : null;
            $paymentTransaction->amount                  = $event['amount'] ;
            $paymentTransaction->status                  = $event['status'] == 1 ? 'SUCCESS' : 'FAILED';
            $paymentTransaction->paymentDate             = $event['status'] == 1 ? Carbon::now() :  Carbon::now();
            $paymentTransaction->paymentMethod           = 'DPO';
            $paymentTransaction->numberOfInstalmentsPaid = 0;
            $paymentTransaction->paymentFrequency        = isset($request->payment_frequency) ?  $request->payment_frequency : 1;
            $paymentTransaction->TransID                 = isset($request->TransactionToken)  ? strtoupper($request->TransactionToken)     : null;
            $paymentTransaction->CCDapproval             = isset($request->CCDapproval)    ? strtoupper($request->CCDapproval) : null;
            $paymentTransaction->PnrID                   = isset($request->PnrID)          ? strtoupper($request->PnrID)       : null;
            $paymentTransaction->TransactionToken        = isset($request->TransactionToken) ? strtoupper($request->TransactionToken) : null;
            $paymentTransaction->CompanyRef              = isset($request->CompanyRef)     ? strtoupper($request->CompanyRef)  : null;
            $paymentTransaction->save();

            ScheduleTransaction::where('policy_number', $policy->policyNumber)
                                ->whereIn('status', [0, 1, 3])
                                ->update([
                                    'status'     => 4
                                ]);


            $policyController = new PolicyController(); //call the action function to update the status of the Policy to active
            if( $event['status'] == 1 )
            {
                $action = $policyController->action($policy->id, 1, 'DPO'); //set the Policy status to active
                if($policy->customer->email != null)
                {
                    ScheduleTransactionEvent::dispatch($policy);

                }else{
                    return response()->json(['status' => false , 'message' => 'Please provide email.'], 400);
                }
            }

            $user             = Customer::where('id', $policy->customer_id)->first();
            // $product_plan     = Productplan::where('id', $policy->plan_id)->first(array('sum_assured', 'premium', 'slug'));
            Helper::ledgerStore($policy->customer_id, 'POLICY', $policy->id, $policy->product_id, 'NEWBUSINESS');
            $sms              = new SmsMessaging();
            if ($event['status'] == 1 ) {
                $sms->sendPaySuccessSMS(4, $user->cellphone, $policy->premium);
            }else{
                $sms->sendPaymentFailedSMS(21, $user->firstName, $policy->policyNumber, $user->cellphone, $policy->premium);
            }

            DB::commit();
            if ($event['status'] == 1 ) {
                if(isset($request->leadSource) && $request->leadSource == 'graphite')
                {
                    return redirect()->route('admin.policy.payWithDpo')->with('success', 'Payment done successfully.');
                }
                elseif(isset($request->leadSource) && $request->leadSource == 'MobileApp')
                {
                    return response()->json(['status' => true, 'message' => 'Payment Done succesfully.', 'type' => 'success'], 200);
                }
                elseif(isset($request->leadSource) && $request->leadSource == 'start.alphadirect.co.bw')
                {
                    return redirect('https://start.alphadirect.co.bw/thank-you-payment-successful?policyNumber='.$policy_number.'&amount='. $amount );
                }
                else{
                    $notification = array(
                        'message'      => 'Payment Done successfully',
                        'alert-type'   => 'success',
                        'policyNumber' => $policy_number,
                        'amount'       => $amount
                    );
                    return redirect(env('LIVEQUOTE_URL').'thank_you_payment_successfull.php?policyNumber='.$policy_number )->with('notification', $notification);
                }
            } else {
                if(isset($request->leadSource) && $request->leadSource == 'graphite')
                {
                    return redirect()->route('admin.policy.payWithDpo')->withError('Payment failed. '. $event['reason']);
                }
                elseif(isset($request->leadSource) && $request->leadSource == 'MobileApp')
                {
                    return response()->json(['status' => false, 'message' => 'Payment failed, please try again later', 'type' => 'error'], 401);
                }
                elseif($request->has('leadSource')  && $request->leadSource == 'start.alphadirect.co.bw')
                {
                    return redirect('https://start.alphadirect.co.bw/payment-failed?policyNumber='.$policy_number.'&amount='. $amount );
                }
                else{
                    $notification = array(
                        'message'      => 'Payment failed, please try again later',
                        'alert-type'   => 'failed',
                        'policyNumber' => $policy_number,
                        'amount'       => $amount
                    );

                $sms = new PaymentTransaction();
                $sent = $sms->sendSMSOnFailedPayments($policy_number,$amount);
                    return redirect(env('START_URL').'payment_failed.php')->with('notification', $notification);
                }
                //response for unsuccessful deletetion - (Important - status code for Flutter app)
            }
        }catch (\Exception $ex) {
            //throw $th;
            DB::rollback();
            return response()->json(['status' => false, 'message' => $ex->getMessage(), 'type' => 'error'], 500);
        }
    }

    public function updateContractFailed(Request $request)
    {
        $policy_number = base64_decode($request->policy_number);
        $amount        = base64_decode($request->amount);

        return redirect('https://start.alphadirect.co.bw/payment-failed?policyNumber='.$policy_number.'&amount='. $amount );
    }


    public function xml2array ( $xmlObject, $out = array () )
	{
		foreach ( (array) $xmlObject as $index => $node )
		$out[$index] = ( is_object ( $node ) ) ? $this->xml2array ( $node ) : $node;
		return $out;
	}

    public function xmlToJson($xml){
        $xml          = stripslashes($xml);
        $xml = simplexml_load_string($xml);
        $xml = $this->xml2array($xml);
        return json_encode($xml, true);
    }

    public function dpoPushNotification(Request $request)
    {
        $xml          = $request->getContent();
        $response_xml = '<?xml version="1.0" encoding="utf-8"?><API3G><Response>OK</Response></API3G>';
        $dpo          = new DpoPayment();
        try{
                $final_result                         = simplexml_load_string($xml);
                $dpoarray                             = $this->xml2array($final_result);
                $dpoarray                             = (object)($final_result);
                $dpo->result                          = $dpoarray->Result ;
                $dpo->result_explanation              = $dpoarray->ResultExplanation ;
                $dpo->transaction_token               = $dpoarray->TransactionToken ;
                $dpo->transaction_ref                 = $dpoarray->TransactionRef ;
                $dpo->customer_name                   = $dpoarray->CustomerName ;
                $dpo->customer_credit                 = $dpoarray->CustomerCredit ;
                $dpo->transaction_approval            = $dpoarray->TransactionApproval ;
                $dpo->transaction_currency            = $dpoarray->TransactionCurrency ;
                $dpo->transaction_amount              = $dpoarray->TransactionAmount ;
                $dpo->fraud_alert                     = $dpoarray->FraudAlert ;
                $dpo->fraud_explanation               = $dpoarray->FraudExplnation ;
                $dpo->transaction_net_amount          = $dpoarray->TransactionNetAmount ;
                $dpo->transaction_settlement_date     = $dpoarray->TransactionSettlementDate ;
                $dpo->transaction_rolling_reserveDate = $dpoarray->TransactionRollingReserveAmount ;
                $dpo->customer_phone                  = $dpoarray->CustomerPhone ;
                $dpo->customer_country                = $dpoarray->CustomerCountry ;
                $dpo->customer_address                = $dpoarray->CustomerAddress ;
                $dpo->customer_city                   = $dpoarray->CustomerCity ;
                $dpo->customer_zip                    = $dpoarray->CustomerZip ;
                $dpo->mobile_payment_request          = $dpoarray->MobilePaymentRequest ;
                $dpo->acc_ref                         = $dpoarray->AccRef ;
                $dpo->response_data                   = $this->xmlToJson($xml);
                $dpo->save();
                if($dpo->save())
                {
                    $dpo->request_json                = $this->xmlToJson($response_xml);
                    $dpo->save();
                    return response($response_xml,200)->header("Content-type","text/xml");
                }else{
                    return response()->json(['status' => 'false', 'message' => 'failed'], 400);
                }
        }catch(\Exception $ex){
            $dpo->response_data                   = $this->xmlToJson($xml);
            $response_xml      = '<?xml version="1.0" encoding="utf-8"?><API3G><Response>Failed</Response></API3G>';
            $dpo->request_json = $this->xmlToJson($response_xml);
            $dpo->save();
            return response($response_xml,500)->header("Content-type","text/xml");
        }
    }

    public function verifyPayment(Request $request)
    {
        $request->validate([
            'token'         => 'required',
            'policy_number' => 'required'
        ]);

        if(!Policy::where('policyNumber', $request->policy_number)->exists())
        {
            return response()->json(['status' => false, 'message' => 'Policy does not exists'], 401);
        }

        $data = [
            'token' => $request->token,
            'policy_number' => $request->policy_number
        ];

        $verifyToken = VerifyTokenEvent::dispatch($data);
        $verifyToken = $verifyToken[0];
        if($verifyToken['status'] == 1)
        {
            return response()->json(['status' => true, 'message' => 'Payment verified', 'amount' => $verifyToken['amount'] ], 200);
        }else{
            return response()->json(['status' => false, 'message' => 'Payment is not verified'], 401);
        }
    }

    public function nextBillingDate($retry_count, $billing_date)
    {
        if($retry_count == 1)
        {
            return Carbon::parse($billing_date)->addDay(1)->format('Y-m-d H:i:s'); // add 1 days to original billing date
        }elseif($retry_count == 2)
        {
            return Carbon::parse($billing_date)->addDay(2)->format('Y-m-d H:i:s'); // add 3 days to original billing date
        }elseif($retry_count == 3)
        {
            return Carbon::parse($billing_date)->addDay(10)->format('Y-m-d H:i:s'); // add 15 days to original billing date
        }elseif($retry_count == 4)
        {
            return Carbon::parse($billing_date)->addDay(10)->format('Y-m-d H:i:s'); // add 25 days to original billing date
        }else{
            return $billing_date;
        }
    }

    public function schedule(Request $request)
    {
        $inserdata = [];
        $policy = Policy::where('policyNumber', $request->policyNumber)->first();
        $policy->billingStartDate = Carbon::parse($policy->billingStartDate);
        /* if(!ScheduleTransaction::where('policy_id', $policy->id)->exists())
        { */
            if($policy->product_id == 3)
            {
                if(isset($policy->premium_freq) && $policy->premium_freq == 3)
                {
                    for ($i=0; $i < 100; $i++) {
                        $scheduleData[] = [
                            'policy_id'     => $policy->id,
                            'policy_number' => $policy->policyNumber,
                            'installment'   => $i + 1,
                            'retry_count'   => 0,
                            'premium'       => $policy->premium,
                            'customer_id'   => $policy->customer_id,
                            'email'         => $policy->customer->email,
                            'billing_date'  => $i == 0 ? $policy->billingStartDate : Carbon::parse($policy->billingStartDate)->addYearsWithOverflow($i),
                            'status'        => 0,    //payment not initiated
                            'created_at'    => Carbon::now(),  //payment not initiated
                            'updated_at'    => Carbon::now(),  //payment not initiated
                        ];
                    }
                }
                elseif(isset($policy->premium_freq) && $policy->premium_freq == 2)
                {

                    $installment = 0;
                    for ($i=0; $i < 33; $i++) {
                        if($i > 0)
                        {
                            $policy->billingStartDate = $policy->billingStartDate->addMonthsNoOverflow(9);
                        }

                        for ($j=0; $j < 3; $j++) {
                            $scheduleData[] = [
                                'policy_id'     => $policy->id,
                                'policy_number' => $policy->policyNumber,
                                'retry_count'   => 0,
                                'premium'       => $policy->premium,
                                'customer_id'   => $policy->customer_id,
                                'email'         => $policy->customer->email,
                                'billing_date'  => $installment == 0 ? Carbon::parse($policy->billingStartDate) : Carbon::parse($policy->billingStartDate)->addMonthsNoOverflow($installment),
                                'installment'   => $installment++,
                                'status'        => 0,    //payment not initiated
                                'created_at'    => Carbon::now(),
                                'updated_at'    => Carbon::now(),
                            ];
                        }
                    }
                }else{
                    for ($i=0; $i < 100; $i++) {
                        $scheduleData[] = [
                            'policy_id'     => $policy->id,
                            'policy_number' => $policy->policyNumber,
                            'installment'   => $i + 1,
                            'retry_count'   => 0,
                            'premium'       => $policy->premium,
                            'customer_id'   => $policy->customer_id,
                            'email'         => $policy->customer->email,
                            'billing_date'  => $i == 0 ? $policy->billingStartDate : Carbon::parse($policy->billingStartDate)->addMonthsNoOverflow($i),
                            'status'        => 0,    //payment not initiated
                            'created_at'    => Carbon::now(),  //payment not initiated
                            'updated_at'    => Carbon::now(),  //payment not initiated
                        ];
                    }
                }

            }else{
                for ($i=0; $i < 100; $i++) {
                    $scheduleData[] = [
                        'policy_id'     => $policy->id,
                        'policy_number' => $policy->policyNumber,
                        'installment'   => $i + 1,
                        'retry_count'   => 0,
                        'premium'       => $policy->premium,
                        'customer_id'   => $policy->customer_id,
                        'email'         => $policy->customer->email,
                        'billing_date'  => $i == 0 ? $policy->billingStartDate : Carbon::parse($policy->billingStartDate)->addMonthsNoOverflow($i)->format('Y-m-d'),
                        'status'        => 0,    //payment not initiated
                        'created_at'    => Carbon::now(),  //payment not initiated
                        'updated_at'    => Carbon::now(),  //payment not initiated
                    ];
                }

            }

            foreach ($scheduleData as $data) {
                echo "<pre>";
                print_r($data);
                echo "</pre>";
                // ScheduleTransaction::insert($data);
            }
        /* } */
    }

    public function makePaymentNow(Request $request){
        if (!auth::user()->hasPermissionTo('policy-make_payment_dpo'))
        {
            return response()->json([ 'status' => 500, 'message'=> 'Sorry! You do not have permission to access this page!']);
        }

        $request->validate([
            'id' => 'required|integer'
        ]);

        // dd($request->all());

        // DB::beginTransaction();
        // try
        // {
            $scheduleTras = ScheduleTransaction::where('id', $request->id)->first();
            $dpo          = new DpoPaymentController();
            if($scheduleTras == null)
            {
                return response()->json([ 'status' => false, 'message'=>'This transactions does not exists.'], 500);
            }

            $createToken = CreateTokenEvent::dispatch($scheduleTras);
            $createToken = $createToken[0];

            if($createToken['status'] == 1)
            {
                    $data = [
                        'policy_number'    => $scheduleTras->policy_number,
                        "customer_id"      => $createToken['customer_id'],
                        "amount"           => $createToken['amount'],
                        "dpo_error_code"   => $createToken['dpo_error_code'],
                        "reason"           => $createToken['reason'],
                        "token"            => $createToken['token'],
                        "TransactionToken" => $createToken['TransactionToken'],
                        "TransID"          => $createToken['TransID'],
                        "response_json"    => $createToken['response_json'],
                        "request_json"     => $createToken['request_json'],
                        "CompanyRef"       => $scheduleTras->policy_number.'/'.$scheduleTras->installment.'/'.$scheduleTras->retry_count,
                        "email"            => $createToken['email'] ,
                    ];

                    $subscriptionTokenEvent = SubscriptionTokenEvent::dispatch($data);

                    $subscriptionTokenEvent = $subscriptionTokenEvent[0];

                    if($subscriptionTokenEvent['status'] == 1)
                    {
                        $data['subscriptionToken']        = $subscriptionTokenEvent['subscriptionToken'];
                        $data['customerToken']            = $subscriptionTokenEvent['customerToken'];

                        $scheduleTras->subscription_token = $subscriptionTokenEvent['subscriptionToken'];
                        $scheduleTras->customer_token     = $subscriptionTokenEvent['customerToken'];
                        $scheduleTras->token              = $data['TransactionToken'];
                        $scheduleTras->status             = 1;                                             //in progress
                        $scheduleTras->reason             = isset($subscriptionTokenEvent['reason']) ? $subscriptionTokenEvent['reason'] : null;                                             //in progress
                        $scheduleTras->save();

                        $chargeTokenRecurrentEvent = ChargeTokenRecurrentEvent::dispatch($data);
                        $chargeTokenRecurrentEvent = $chargeTokenRecurrentEvent[0];

                        if($chargeTokenRecurrentEvent['status'] == 1)
                        {
                            $verifyTokenEvent     = VerifyTokenEvent::dispatch($data);
                            $verifyTokenEvent     = $verifyTokenEvent['0'];
                            // dd($verifyTokenEvent);

                            if($verifyTokenEvent['status'] == 1)
                            {
                                $scheduleTras->billing_date = Carbon::today()->format('Y-m-d H:i:s');
                                $scheduleTras->status       = 2; //payment successful
                                $scheduleTras->reason       = $verifyTokenEvent['reason'];
                                $scheduleTras->save();

                                $transaction                          = new PaymentTransaction();
                                $transaction->policyNumber            = $scheduleTras->policy_number;
                                $transaction->referenceNumber         = $scheduleTras->token;
                                $transaction->TransactionToken        = $scheduleTras->token;
                                $transaction->amount                  = $scheduleTras->premium;
                                $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                $transaction->paymentMethod           = 'DPO';
                                $transaction->numberOfInstalmentsPaid = $scheduleTras->installment;
                                $transaction->paymentFrequency        =  1;
                                $transaction->status                  = 'SUCCESS' ;
                                $transaction->save();

                                $account = PullAccountEvent::dispatch($data);

                                return response()->json(['status' => true, 'message'=> 'Payment successful.'], 200);

                            }else
                            {
                                //update next billing date
                                $scheduleTras->retry_count  = $scheduleTras->retry_count + 1; //0 to 4
                                $scheduleTras->billing_date = $dpo->nextBillingDate($scheduleTras->retry_count, $scheduleTras->billing_date); // 2 days, 5 days, 15 days, 30 days retry payment
                                $scheduleTras->status       = 3; //failed
                                $scheduleTras->reason       = $verifyTokenEvent['reason'];
                                $scheduleTras->save();

                                $transaction                          = new PaymentTransaction();
                                $transaction->policyNumber            = $scheduleTras->policy_number;
                                $transaction->referenceNumber         = $scheduleTras->token;
                                $transaction->TransactionToken        = $scheduleTras->token;
                                $transaction->amount                  = $scheduleTras->premium;
                                $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                $transaction->paymentMethod           = 'DPO';
                                $transaction->note                    = $verifyTokenEvent['reason'];
                                $transaction->numberOfInstalmentsPaid = $scheduleTras->installment;
                                $transaction->status                  = 'FAILED';
                                $transaction->save();
                                PullAccountEvent::dispatch($data);

                                return response()->json(['status' => false, 'message'=> $scheduleTras->reason], 500);
                            }
                        }else
                        {

                            //update next billing date
                            $scheduleTras->retry_count  = $scheduleTras->retry_count + 1; //0 to 4
                            $scheduleTras->billing_date = $dpo->nextBillingDate($scheduleTras->retry_count, $scheduleTras->billing_date); // 2 days, 5 days, 15 days, 30 days retry payment
                            $scheduleTras->status       = 3; //failed
                            $scheduleTras->reason       = isset($chargeTokenRecurrentEvent['reason']) ? $chargeTokenRecurrentEvent['reason'] : null;
                            $scheduleTras->save();

                            $transaction                          = new PaymentTransaction();
                            $transaction->policyNumber            = $scheduleTras->policy_number;
                            $transaction->referenceNumber         = $scheduleTras->token;
                            $transaction->TransactionToken        = $scheduleTras->token;
                            $transaction->amount                  = $scheduleTras->premium;
                            $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                            $transaction->paymentMethod           = 'DPO';
                            $transaction->note                    = isset($chargeTokenRecurrentEvent['reason']) ? $chargeTokenRecurrentEvent['reason'] : null;
                            $transaction->numberOfInstalmentsPaid = $scheduleTras->installment;
                            $transaction->status                  = 'FAILED' ;
                            $transaction->save();

                            PullAccountEvent::dispatch($data);

                            return response()->json(['status' => false, 'message'=> $scheduleTras->reason ], 500);
                        }
                    }else
                    {
                        $scheduleTras->retry_count  = $scheduleTras->retry_count + 1; //0 to 4
                        $scheduleTras->billing_date = $dpo->nextBillingDate($scheduleTras->retry_count, $scheduleTras->billing_date); // 2 days, 5 days, 15 days, 30 days retry payment
                        $scheduleTras->status       = 3; //failed
                        $scheduleTras->reason       = $subscriptionTokenEvent['reason'];
                        $scheduleTras->save();

                        return response()->json(['status' => false, 'message'=>$subscriptionTokenEvent['reason']], 500);
                    }
            }else{
                $scheduleTras->retry_count  = $scheduleTras->retry_count + 1; //0 to 4
                $scheduleTras->billing_date = $dpo->nextBillingDate($scheduleTras->retry_count, $scheduleTras->billing_date); // 2 days, 5 days, 15 days, 30 days retry payment
                $scheduleTras->status       = 3; //failed
                $scheduleTras->reason       = $createToken['reason'];
                $scheduleTras->save();

                return response()->json([ 'status' => false, 'message'=>$createToken['reason']], 500);
            }
            // DB::commit();

        // }catch(\Exception $ex)
        // {
        //     return response()->json([ 'status' => false, 'message'=> $ex->getMessage()], 500);

        // }
    }

    public function suspendPaymentDpo(Request $request)
    {
        // dd($request->all());
        if (!Auth::user()->hasPermissionTo('policy-suspend_payment_dpo'))
        {
            return response()->json([ 'status' => 500, 'message'=> 'Sorry! You do not have permission to access this page!']);
        }

        $request->validate([
            'id' => 'required|integer'
        ]);


        DB::beginTransaction();
        try{

            $id = ScheduleTransaction::where('id', $request->id)
                                ->update([
                                    'status'     => 4,
                                    'added_by'   => auth()->user()->id
                                ]);
            // dd($id);
            DB::commit();
            return response()->json(['status' => true, 'message' => 'Schedule transaction is canceled sucessfully.'], 200);

        }catch(\Exception $exm)
        {
            DB::rollBack();
            return response()->json([ 'status' => false, 'message'=> $ex->getMessage()], 500);
        }
    }

    public function suspendPaymentDpoAll(Request $request)
    {
        // dd($request->all());
        if (!Auth::user()->hasPermissionTo('policy-suspend_payment_all_dpo'))
        {
            return response()->json([ 'status' => 500, 'message'=> 'Sorry! You do not have permission to access this page!']);
        }

        $request->validate([
            'policyNumber' => 'required'
        ]);


        DB::beginTransaction();
        try{

            ScheduleTransaction::where('policy_number', $request->policyNumber)
                                ->whereIn('status', [0, 1, 3])
                                ->update([
                                    'status'     => 4
                                ]);

            DB::commit();
            return response()->json(['status' => true, 'message' => 'Schedule transactions are canceled sucessfully.'], 200);

        }catch(\Exception $ex)
        {
            DB::rollBack();
            return response()->json([ 'status' => false, 'message'=> $ex->getMessage()], 500);
        }

    }

    public function payWithDpo(Request $request)
    {
        if (!Auth::user()->hasPermissionTo('policy-pay_with_dpo'))
        {
            return response()->json([ 'status' => 500, 'message'=> 'Sorry! You do not have permission to access this page!']);
        }

        return view('admin.dpo.makePayment');
    }

    public function fetchDpoTransactions(Request $request)
    {
        if (!Auth::user()->hasPermissionTo('policy-pay_with_dpo'))
        {
            return response()->json([ 'status' => 500, 'message'=> 'Sorry! You do not have permission to access this page!']);
        }

        return view('admin.dpo.fetchDpoTransactions');
    }

    public function getDpoTransactions(Request $request)
    {
        try {
            $request->validate([
                'policyNumber' => 'required',
            ]);

            if(Policy::where('policyNumber', $request->policyNumber)->exists()) {

                $xml = '<?xml version="1.0" encoding="utf-8"?>
                    <API3G>
                        <CompanyToken>'.env('COMPANY_TOKEN').'</CompanyToken>
                        <Request>getTransactionByRef</Request>
                        <CompanyRef>'. $request->policyNumber .'</CompanyRef>
                        <allTrans>1</allTrans>
                        <descOrder>1</descOrder>
                    </API3G>';

                // dd($xml);
                $url = env('DPO_URL').'API/v7/';
                $curl = curl_init($url);
                curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: text/xml"));
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                $result = curl_exec($curl);

                curl_close($curl);
                $final_result = simplexml_load_string($result);
                $dpoarray     = $this->xml2array($final_result);
                // dd($dpoarray);

                if($dpoarray['Code'] == "000")
                    {
                        if (isset($dpoarray['Transactions']['Transaction']) && $dpoarray['Transactions']['Transaction'] > 0) {
                            $policy = Policy::with('customer')->where('policyNumber', $request->policyNumber)->first();
                            $request_data = $this->xmlToJson($xml);
                            foreach ($dpoarray['Transactions']['Transaction'] as $key => $transaction) {
                                $transaction = json_decode(json_encode($transaction));

                                $activity     = new PaymentActivityLog();
                                if (isset($policy)) {
                                    $activity->policy_number  = $policy->policyNumber;
                                    $activity->customer_id    = $policy->customer_id;
                                } else {
                                    $activity->policy_number  = $request->policyNumber;
                                    $activity->customer_id    = null;
                                }
                                $activity->amount         = $transaction->TransactionAmount;
                                $activity->dpo_error_code = $dpoarray['Code'];
                                $activity->reason         = $dpoarray['Explanation'];
                                $activity->api_name       = 'getTransactionByRef';
                                $activity->TransactionToken     = $transaction->TransactionToken;
                                $activity->response_json  = json_encode($dpoarray);
                                $activity->request_json   = $request_data;
                                $activity->CompanyRef     = $transaction->TransactionBookRef;
                                $activity->save();

                                $paymentTransaction = PaymentTransaction::where('policyNumber',$request->policyNumber)
                                    ->where('referenceNumber',$transaction->TransactionToken)->first();
                                    if (!isset($paymentTransaction)) {
                                        $paymentTransaction                          = new PaymentTransaction();
                                        $paymentTransaction->policyNumber            = $request->policyNumber;
                                        $paymentTransaction->referenceNumber         = $transaction->TransactionToken;
                                        $paymentTransaction->TransactionToken        = $transaction->TransactionToken;
                                        $paymentTransaction->amount                  = $transaction->TransactionAmount;
                                        $paymentTransaction->paymentDate             = $transaction->TransactionCreatedDate;
                                        $paymentTransaction->paymentMethod           = 'DPO';
                                        // $paymentTransaction->numberOfInstalmentsPaid = $policy->installment;
                                        $paymentTransaction->paymentFrequency        =  1;
                                        $paymentTransaction->status                  = $transaction->TransactionStatus == 'Paid' ? 'SUCCESS' : $transaction->TransactionStatus;
                                        $paymentTransaction->CompanyRef              = $transaction->TransactionBookRef;
                                        $paymentTransaction->save();

                                        if( $transaction->TransactionStatus == 'Paid' )
                                        {
                                            $policyController = new PolicyController(); //call the action function to update the status of the Policy to active

                                            $action = $policyController->action($policy->id, 1, 'DPO'); //set the Policy status to active
                                            if($transaction->TransactionCustomerEmail != null)
                                            {
                                                // $policy->customer->email = $transaction->TransactionCustomerEmail;
                                                ScheduleTransactionEvent::dispatch($policy);
                                                $transactionCreatedDate = Carbon::parse($transaction->TransactionCreatedDate)->format('Y-m-d');
                                                $schedule = ScheduleTransaction::where('policy_number',$request->policyNumber)->whereDate('billing_date',$transactionCreatedDate)->first();
                                                if(isset($schedule)){
                                                    $schedule->status = 2;
                                                    $schedule->save();
                                                }
                                            }
                                        }
                                    }
                            }
                            return redirect()->back()->withSuccess('Successfully fetched transactions.');
                        } else {
                            return redirect()->back()->withError('No transaction found.');
                        }

                    }else{
                        return redirect()->back()->withError($dpoarray['Code'] . ' ' . $dpoarray['Explanation']);
                    }

            } else {
                return redirect()->back()->withError('Policy number does not exists');
            }

        } catch (\Exception $ex) {
            return redirect()->back()->withError($ex->getMessage() . ' ' . $ex->getLine());
        }
    }

    public function dpoTransactionImport(Request $request)
    {
        if (!Auth::user()->hasPermissionTo('policy-pay_with_dpo'))
        {
            return response()->json([ 'status' => 500, 'message'=> 'Sorry! You do not have permission to access this page!']);
        }

        return view('admin.dpo.dpoTransactionImport');
    }

    public function getDpoTransactionImport(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required',
            ]);

            $data =  Excel::import(new DpoExcelImport, $request->file('file')->store('files'));

            return redirect()->back()->withSuccess('Data imported successfully.');

        } catch (\Exception $ex) {
            return redirect()->back()->withError($ex->getMessage() . ' ' . $ex->getLine());
        }
    }


    public function payWithDpoStore(Request $request)
    {
        if (!Auth::user()->hasPermissionTo('policy-pay_with_dpo'))
        {
            return response()->json([ 'status' => 500, 'message'=> 'Sorry! You do not have permission to access this page!']);
        }
        $request->validate([
            'policyNumber' => 'required',
            'amount' => 'required'
        ]);

        if(Policy::where('policyNumber', $request->policyNumber)->exists())
        {
            $data = [
                'policy_number' => $request->policyNumber,
                'amount' => $request->amount
            ];

            $scheduleTras = new ScheduleTransaction();

                    if(!isset($policy))
                    {
                        return redirect()->back()->withError('Please provide email of the customer.');
                    }

                    $createToken = CreateTokenEvent::dispatch($policy);
                    $createToken = $createToken[0];

                    if($createToken['status'] == 1)
                    {
                            $data = [
                                'policy_number'    => $policy->policyNumber,
                                "customer_id"      => $createToken['customer_id'],
                                "amount"           => $createToken['amount'],
                                "dpo_error_code"   => $createToken['dpo_error_code'],
                                "reason"           => $createToken['reason'],
                                "token"            => $createToken['token'],
                                "TransactionToken" => $createToken['TransactionToken'],
                                "TransID"          => $createToken['TransID'],
                                "api_name"         => 'subscriptionToken',
                                "response_json"    => $createToken['response_json'],
                                "request_json"     => $createToken['request_json'],
                                "CompanyRef"       => $policy->policyNumber,
                                "email"            => $createToken['email'] ,
                            ];

                            $subscriptionTokenEvent = SubscriptionTokenEvent::dispatch($data);
                            $subscriptionTokenEvent = $subscriptionTokenEvent[0];

                             if($subscriptionTokenEvent['status'] == 1)
                            {
                                $data['subscriptionToken']        = $subscriptionTokenEvent['subscriptionToken'];
                                $data['customerToken']            = $subscriptionTokenEvent['customerToken'];

                                $scheduleTras->subscription_token = $subscriptionTokenEvent['subscriptionToken'];
                                $scheduleTras->customer_token     = $subscriptionTokenEvent['customerToken'];
                                $scheduleTras->token              = $data['TransactionToken'];
                                $scheduleTras->status             = 1;                                             //in progress
                                $scheduleTras->reason             = isset($subscriptionTokenEvent['reason']) ? $subscriptionTokenEvent['reason'] : null;                                             //in progress
                                $scheduleTras->save();

                                $chargeTokenRecurrentEvent = ChargeTokenRecurrentEvent::dispatch($data);
                                $chargeTokenRecurrentEvent = $chargeTokenRecurrentEvent[0];

                                if($chargeTokenRecurrentEvent['status'] == 1)
                                {
                                    $verifyTokenEvent     = VerifyTokenEvent::dispatch($data);
                                    $verifyTokenEvent     = $verifyTokenEvent['0'];
                                    // dd($verifyTokenEvent);

                                    if($verifyTokenEvent['status'] == 1)
                                    {
                                        $scheduleTras->billing_date = Carbon::today()->format('Y-m-d H:i:s');
                                        $scheduleTras->status       = 2;                                       //payment successful
                                        $scheduleTras->reason       = $verifyTokenEvent['reason'];
                                        $scheduleTras->processed_by = auth()->user()->id;
                                        $scheduleTras->save();

                                        $transaction                          = new PaymentTransaction();
                                        $transaction->policyNumber            = $scheduleTras->policy_number;
                                        $transaction->referenceNumber         = $scheduleTras->token;
                                        $transaction->TransactionToken        = $scheduleTras->token;
                                        $transaction->amount                  = $scheduleTras->premium;
                                        $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                        $transaction->paymentMethod           = 'DPO';
                                        $transaction->numberOfInstalmentsPaid = $scheduleTras->installment;
                                        $transaction->paymentFrequency        =  1;
                                        $transaction->status                  = 'SUCCESS' ;
                                        $transaction->save();

                                        $account = PullAccountEvent::dispatch($data);

                                        return redirect()->back()->withSuccess('Payment successful.');

                                    }else
                                    {
                                        //update next billing date
                                        // $scheduleTras->retry_count  = $scheduleTras->retry_count + 1; //0 to 4
                                        $scheduleTras->billing_date = Carbon::now(); // 2 days, 5 days, 15 days, 30 days retry payment
                                        $scheduleTras->status       = 3; //failed
                                        $scheduleTras->reason       = $verifyTokenEvent['reason'];
                                        $scheduleTras->save();

                                        $transaction                          = new PaymentTransaction();
                                        $transaction->policyNumber            = $scheduleTras->policy_number;
                                        $transaction->referenceNumber         = $scheduleTras->token;
                                        $transaction->TransactionToken        = $scheduleTras->token;
                                        $transaction->amount                  = $scheduleTras->premium;
                                        $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                        $transaction->paymentMethod           = 'DPO';
                                        $transaction->note                    = $verifyTokenEvent['reason'];
                                        $transaction->numberOfInstalmentsPaid = $scheduleTras->installment;
                                        $transaction->status                  = 'FAILED';
                                        $transaction->save();
                                        PullAccountEvent::dispatch($data);

                                        return redirect()->back()->withError($scheduleTras->reason);
                                    }
                                }else
                                {

                                    //update next billing date
                                    $scheduleTras->retry_count  = $scheduleTras->retry_count + 1; //0 to 4
                                    $scheduleTras->billing_date = Carbon::now(); // 2 days, 5 days, 15 days, 30 days retry payment
                                    $scheduleTras->status       = 3; //failed
                                    $scheduleTras->reason       = isset($chargeTokenRecurrentEvent['reason']) ? $chargeTokenRecurrentEvent['reason'] : null;
                                    $scheduleTras->save();

                                    $transaction                          = new PaymentTransaction();
                                    $transaction->policyNumber            = $scheduleTras->policy_number;
                                    $transaction->referenceNumber         = $scheduleTras->token;
                                    $transaction->TransactionToken        = $scheduleTras->token;
                                    $transaction->amount                  = $scheduleTras->premium;
                                    $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
                                    $transaction->paymentMethod           = 'DPO';
                                    $transaction->note                    = isset($chargeTokenRecurrentEvent['reason']) ? $chargeTokenRecurrentEvent['reason'] : null;
                                    $transaction->numberOfInstalmentsPaid = $scheduleTras->installment;
                                    $transaction->status                  = 'FAILED' ;
                                    $transaction->save();
                                    PullAccountEvent::dispatch($data);

                                    return redirect()->back()->withError( $scheduleTras->reason );
                                }
                            }else
                            {
                                $scheduleTras->retry_count  = $scheduleTras->retry_count + 1; //0 to 4
                                $scheduleTras->billing_date = $dpo->nextBillingDate($scheduleTras->retry_count, $scheduleTras->billing_date); // 2 days, 5 days, 15 days, 30 days retry payment
                                $scheduleTras->status       = 3; //failed
                                $scheduleTras->reason       = $subscriptionTokenEvent['reason'];
                                $scheduleTras->save();

                                return redirect()->back()->withError($subscriptionTokenEvent['reason']);
                            }

                    }else{
                        $scheduleTras->retry_count  = $scheduleTras->retry_count + 1; //0 to 4
                        $scheduleTras->billing_date = Carbon::now(); // 2 days, 5 days, 15 days, 30 days retry payment
                        $scheduleTras->status       = 3; //failed
                        $scheduleTras->reason       = $createToken['reason'];
                        $scheduleTras->save();

                        return redirect()->back()->withError($createToken['reason']);

                    }
        }else{
            return redirect()->back()->withError('Policy number does not exists');
        }
    }

    public function addScheduleTransaction(Request $request)
    {
        if (!Auth::user()->hasPermissionTo('policy-add_schedule_transaction'))
        {
            return redirect()->back()->withError('Sorry! You do not have permission to access this page!');
        }

        $request->validate([
            'policyNumber'          => 'required',
            'schedule_amount'       => 'required',
            'schedule_billing_date' => 'required',
        ]);

        if (!Policy::where('policyNumber', $request->policyNumber)->exists())
        {
            return redirect()->back()->withError('Policy number does not exists.');
        }

        try{
            DB::beginTransaction();
            $policy = Policy::with('customer')->where('policyNumber', $request->policyNumber)->first();
            $schedule = new ScheduleTransaction();
            $schedule->policy_id     = $policy->id;
            $schedule->policy_number = $policy->policyNumber;
            $schedule->customer_id   = $policy->customer_id;
            $schedule->installment   = ScheduleTransaction::where('policy_number', $request->policyNumber)->max('installment') + 1;
            $schedule->retry_count   = 0;
            $schedule->premium       = $request->schedule_amount;
            $schedule->email         = $policy->customer->email;
            $schedule->billing_date  = $request->schedule_billing_date;
            $schedule->status        = 0;
            $schedule->retry_count   = 0;
            $schedule->added_by   = auth()->user()->id;
            $schedule->created_at    = Carbon::now();
            $schedule->save();
            // dd($schedule);
            DB::commit();

            return redirect()->back()->withSuccess('Schedule transaction added successfully.');
        }catch(\Exception $e)
        {
            DB::rollBack();
            return redirect()->back()->withError('Something went wrong. Please try again.');
        }
    }


    public function reratePolicyUpdate(Request $request)
    {
        $policy_number = $request->policyNumber;

        DB::beginTransaction();
        try{
            $policy = Policy::with('customer')->where('policyNumber', $policy_number)->first();
            $policyFrequency = $policy->premium_freq;
            // dd($policy);
            if($policy == null)
            {
                 return response()->json(['status' => false , 'message' => 'Policy not found'], 401);
            }

            $policyController = new PolicyController(); //call the action function to update the status of the Policy to active

            $data = [
                    "token"            => $request->TransactionToken,
                    "linkToken"        => $request->linkToken,
                    "policy_number"    => $policy->policyNumber,
                    "TransID"          => isset($request->TransactionToken)      ? strtoupper($request->TransactionToken)     : null,
                    "CCDapproval"      => isset($request->CCDapproval)  ? strtoupper($request->CCDapproval) : null,
                    "PnrID"            => isset($request->PnrID)        ? strtoupper($request->PnrID)       : null,
                    "TransactionToken" => isset($request->TransactionToken) ? strtoupper($request->TransactionToken) : null,
                    "CompanyRef"       => isset($request->CompanyRef)   ? strtoupper($request->CompanyRef)  : null,
                    "customer_id"      => $policy->customer_id,
                    'leadSource'       => $request->leadSource
                ];

                switch($request->frequency){
                    case 1:
                        $amount = $request->monthly;
                        break;
                    case 2:
                        $amount = $request->threeInstalment;
                        break;
                    case 3:
                        $amount = $request->annual;
                        break;
                }

            //fire event send sms and email when policy is created
            $event                                       = VerifyTokenEvent::dispatch($data);
            $event                                       = $event[0];
            $paymentTransaction                          = new PaymentTransaction();
            $paymentTransaction->policyNumber            = $policy->policyNumber;
            $paymentTransaction->referenceNumber         = isset($request->TransactionToken)        ? strtoupper($request->TransactionToken)     : null;
            $paymentTransaction->amount                  =  $event['amount'] ;
            $paymentTransaction->status                  = $event['status'] == 1 ? 'SUCCESS' : 'FAILED';
            $paymentTransaction->paymentDate             = $event['status'] == 1 ? Carbon::now() :  Carbon::now();
            $paymentTransaction->paymentMethod           = 'DPO';
            $paymentTransaction->numberOfInstalmentsPaid = 0;
            $paymentTransaction->paymentFrequency        = isset($request->frequency) ?  $request->frequency : 1;
            $paymentTransaction->TransID                 = isset($request->TransactionToken)  ? strtoupper($request->TransactionToken)     : null;
            $paymentTransaction->CCDapproval             = isset($request->CCDapproval)    ? strtoupper($request->CCDapproval) : null;
            $paymentTransaction->PnrID                   = isset($request->PnrID)          ? strtoupper($request->PnrID)       : null;
            $paymentTransaction->TransactionToken        = isset($request->TransactionToken) ? strtoupper($request->TransactionToken) : null;
            $paymentTransaction->CompanyRef              = isset($request->CompanyRef)     ? strtoupper($request->CompanyRef)  : null;
            $paymentTransaction->note                    = 'policy rerated';
            $paymentTransaction->save();

            $policy->sum_assured = $request->sum_assured;
            $policy->premium_freq = $request->frequency;
            $policy->premium      = $amount;
            $policy->first_premium      = $event['amount'];
            $policy->save();

            $onePayment = PaymentUrls::where('url', 'https://pay.alphadirect.co.bw/rerate-premium-pay/'.$request->linkToken)->first();
            $onePayment->status     = 1;
            $onePayment->save();

            $customerBanking =  CustomerBanking::updateOrCreate([
                'policy_id'        => $policy->id,
            ], [
                'billing'          => 'DPO',
                'billingCell'      => $policy->customer->cellphone,
                'billingStartDate' => $policy->billingStartDate,
                'billing_day'      => $policy->billing_day
            ]);

            $frequency = $onePayment->frequency;
            switch ($frequency) {
                case 1:
                    $schedules =13;
                  break;
                case 2:
                    $schedules =4;
                  break;
                default:
                $schedules =13;
            }

            if($frequency != $policyFrequency)
            {
                ScheduleTransaction::where('policy_number', $policy->policyNumber)->where('status', 0)->delete();
            }
            // dd($customerBanking);
            $latestSchedule = ScheduleTransaction::where('policy_id', $policy->id)->orderBy('installment', 'desc')->value('installment');
            if( $event['status'] == 1 )
            {
                if($policy->customer->email != null)
                {
                    $scheduleData = [];

                    //first instalment
                    $scheduleData[0] = [
                        'policy_id'     => $policy->id,
                        'policy_number' => $policy->policyNumber,
                        'installment'   => $latestSchedule + 1,
                        'retry_count'   => 0,
                        'billing_date'  => Carbon::parse( $onePayment->first_collection_date)->format('Y-m-d'),
                        'premium'       => $onePayment->first_premium,
                        'customer_id'   => $policy->customer_id,
                        'email'         => $policy->customer->email,
                        'status'        => 2,
                        'created_at'    => Carbon::now(),
                        'updated_at'    => Carbon::now(),
                    ];

                    for ($i=1; $i < $schedules; $i++) {
                        $scheduleData[] = [
                            'policy_id'     => $policy->id,
                            'policy_number' => $policy->policyNumber,
                            'installment'   => $i,
                            'retry_count'   => 0,
                            'premium'       => isset($onePayment->rerate_premium) ? $onePayment->rerate_premium : $policy->premium,
                            'customer_id'   => $policy->customer_id,
                            'email'         => $policy->customer->email,
                            'billing_date'  => $i == 1 ? $onePayment->billingDay : Carbon::parse($onePayment->billingDay)->addMonthsNoOverflow($i)->format('Y-m-d'),
                            'status'        => 0,    //payment not initiated
                            'created_at'    => Carbon::now(),  //payment not initiated
                            'updated_at'    => Carbon::now(),  //payment not initiated
                        ];
                    }

                    foreach ($scheduleData as $data) {
                        ScheduleTransaction::insert($data);
                    }
                }else{
                    return response()->json(['status' => true , 'message' => 'Please provide email.'], 200);
                }

            }

            $data['id'] = $policy->id;
            $this->policyController->updatenewRatePremium($data);

            $user             = Customer::where('id', $policy->customer_id)->first();
            // $product_plan     = Productplan::where('id', $policy->plan_id)->first(array('sum_assured', 'premium', 'slug'));
            Helper::ledgerStore($policy->customer_id, 'POLICY', $policy->id, $policy->product_id, 'NEWBUSINESS');
            $sms              = new SmsMessaging();
            if ($event['status'] == 1 ) {
                $sms->sendPaySuccessSMS(4, $user->cellphone, $policy->premium);
            }else{
                $sms->sendPaymentFailedSMS(21, $user->firstName, $policy->policyNumber, $user->cellphone, $policy->premium);
            }

            DB::commit();
            if ($event['status'] == 1 ) {
                if(isset($request->leadSource) && $request->leadSource == 'graphite')
                {
                    return redirect()->route('admin.policy.payWithDpo')->with('success', 'Payment done successfully.');
                }
                elseif(isset($request->leadSource) && $request->leadSource == 'MobileApp')
                {
                    return response()->json(['status' => true, 'message' => 'Payment Done succesfully.', 'type' => 'success'], 200);
                }
                elseif(isset($request->leadSource) && $request->leadSource == 'start.alphadirect.co.bw')
                {
                    return redirect('https://start.alphadirect.co.bw/thank-you-payment-successful?policyNumber='.$policy_number.'&amount='. $amount );
                }
                else{
                    $notification = array(
                        'message'      => 'Payment Done successfully',
                        'alert-type'   => 'success',
                        'policyNumber' => $policy_number,
                        'amount'       => $amount
                    );
                    return redirect(env('LIVEQUOTE_URL').'thank_you_payment_successfull.php?policyNumber='.$policy_number )->with('notification', $notification);
                }
            } else {
                if(isset($request->leadSource) && $request->leadSource == 'graphite')
                {
                    return redirect()->route('admin.policy.payWithDpo')->withError('Payment failed. '. $event['reason']);
                }
                elseif(isset($request->leadSource) && $request->leadSource == 'MobileApp')
                {
                    return response()->json(['status' => false, 'message' => 'Payment failed, please try again later', 'type' => 'error'], 401);
                }
                elseif($request->has('leadSource')  && $request->leadSource == 'start.alphadirect.co.bw')
                {
                    return redirect('https://start.alphadirect.co.bw/payment-failed?policyNumber='.$policy_number.'&amount='. $amount );
                }
                else{
                    $notification = array(
                        'message'      => 'Payment failed, please try again later',
                        'alert-type'   => 'failed',
                        'policyNumber' => $policy_number,
                        'amount'       => $amount
                    );

                $sms = new PaymentTransaction();
                $sent = $sms->sendSMSOnFailedPayments($policy_number,$amount);
                    return redirect(env('START_URL').'payment_failed.php')->with('notification', $notification);
                }
                //response for unsuccessful deletetion - (Important - status code for Flutter app)
            }
        }catch (\Exception $ex) {
            //throw $th;
            DB::rollback();
            return response()->json(['status' => false, 'message' => $ex->getMessage(), 'type' => 'error'], 500);
        }
    }

    public function rerateFailedWithDpo(Request $request)
    {
        /* $request->validate([
            'policyNumber' => 'required',
            'payment_method' => 'required'
        ]); */
        $policy_number = base64_decode($request->policyNumber);

        switch($request->frequency){
            case 1:
                $amount = $request->monthly;
                break;
            case 2:
                $amount = $request->threeInstalment;
                break;
            case 3:
                $amount = $request->annual;
                break;
        }

        DB::beginTransaction();
        try{
            $policy = Policy::where('policyNumber', $policy_number)->first();
            if($policy == null)
            {
                 return response()->json(['status' => false , 'message' => 'Policy not found'], 401);
            }

            if($policy->status == 2)
            {
                return response()->json(['status' => false , 'message' => 'Canceled policy is not available for this feature.'], 401);
            }

            $data = [
                    "token"            => $request->TransactionToken,
                    "policy_number"    => $policy->policyNumber,
                    "TransID"          => isset($request->TransactionToken)      ? strtoupper($request->TransactionToken)     : null,
                    "CCDapproval"      => isset($request->CCDapproval)  ? strtoupper($request->CCDapproval) : null,
                    "PnrID"            => isset($request->PnrID)        ? strtoupper($request->PnrID)       : null,
                    "TransactionToken" => isset($request->TransactionToken) ? strtoupper($request->TransactionToken) : null,
                    "CompanyRef"       => isset($request->CompanyRef)   ? strtoupper($request->CompanyRef)  : null,
                    "customer_id"      => $policy->customer_id,
                ];


            //fire event send sms and email when policy is created
            $event                                       = VerifyTokenEvent::dispatch($data);
            $event                                       = $event[0];
            $paymentTransaction                          = new PaymentTransaction();
            $paymentTransaction->policyNumber            = $policy->policyNumber;
            $paymentTransaction->referenceNumber         = isset($request->TransactionToken)        ? strtoupper($request->TransactionToken)     : null;
            $paymentTransaction->amount                  = (float)str_replace(',','',$amount) ;
            $paymentTransaction->status                  = $event['status'] == 1 ? 'SUCCESS' : 'FAILED';
            $paymentTransaction->paymentDate             = $event['status'] == 1 ? Carbon::now() :  Carbon::now();
            $paymentTransaction->paymentMethod           = 'DPO';
            $paymentTransaction->numberOfInstalmentsPaid = 0;
            $paymentTransaction->paymentFrequency        = isset($request->frequency) ?  $request->frequency : 1;
            $paymentTransaction->TransID                 = isset($request->TransactionToken)        ? strtoupper($request->TransactionToken)     : null;
            $paymentTransaction->CCDapproval             = isset($request->CCDapproval)    ? strtoupper($request->CCDapproval) : null;
            $paymentTransaction->PnrID                   = isset($request->PnrID)          ? strtoupper($request->PnrID)       : null;
            $paymentTransaction->TransactionToken        = isset($request->TransactionToken) ? strtoupper($request->TransactionToken) : null;
            $paymentTransaction->CompanyRef              = isset($request->CompanyRef)     ? strtoupper($request->CompanyRef)  : null;
            $paymentTransaction->note                    = 'policy rerated';
            $paymentTransaction->save();

            DB::commit();

                $notification = array(
                    'message'      => 'Payment failed, please try again later',
                    'amount'       => $amount,
                    'alert-type'   => 'failed',
                    'policyNumber' => $policy_number,
                    'amount'       => $amount
                );

                $sms = new PaymentTransaction();
                $sent = $sms->sendSMSOnFailedPayments($policy_number,$amount);
                return redirect(env('START_URL').'payment_failed.php')->with('notification', $notification);


        }catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['status' => false, 'message' => $ex->getMessage(), 'type' => 'error'], 500);
        }
    }

    public function PolicyReinstateDeclined(Request $request)
    {
        /* $request->validate([
            'policyNumber' => 'required',
            'payment_method' => 'required'
        ]); */
        $policy_number = base64_decode($request->policyNumber);

        switch($request->frequency){
            case 1:
                $amount = $request->monthly;
                break;
            case 2:
                $amount = $request->threeInstalment;
                break;
            case 3:
                $amount = $request->annual;
                break;
        }

        DB::beginTransaction();
        try{
            $policy = Policy::where('policyNumber', $policy_number)->first();

            if($policy == null)
            {
                 return response()->json(['status' => false , 'message' => 'Policy not found'], 401);
            }

            if($policy->status == 2)
            {
                return response()->json(['status' => false , 'message' => 'Canceled policy is not available for this feature.'], 401);
            }

            $data = [
                    "token"            => $request->TransactionToken,
                    "policy_number"    => $policy->policyNumber,
                    "TransID"          => isset($request->TransactionToken)  ? strtoupper($request->TransactionToken)     : null,
                    "CCDapproval"      => isset($request->CCDapproval)  ? strtoupper($request->CCDapproval) : null,
                    "PnrID"            => isset($request->PnrID)        ? strtoupper($request->PnrID)       : null,
                    "TransactionToken" => isset($request->TransactionToken) ? strtoupper($request->TransactionToken) : null,
                    "CompanyRef"       => isset($request->CompanyRef)   ? strtoupper($request->CompanyRef)  : null,
                    "customer_id"      => $policy->customer_id,
                ];


            DB::commit();

                return response()->json([
                    'message'      => 'Payment is canceled.',
                    'alert-type'   => 'failed',
                    'amount'       => $amount,
                    'policyNumber' => $policy_number,
                    'amount'       => $amount
                ]);

        }catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['status' => false, 'message' => $ex->getMessage(), 'type' => 'error'], 500);
        }
    }


    public function CancelContractForDpoPolicy($policy)
    {
        try {
            $data = [
                "token"            => ScheduleTransaction::where('policy_number', $policy->policyNumber)->where('status', 1)->value('token'),
                "policy_number"    => $policy->policyNumber,
                "CompanyRef"       => env('COMPANY_REF'),
                "customer_id"      => $policy->customer_id,
            ];

            if($data['token'] != null )
            {
                CancelTokenEvent::dispatch($data);
            }

            if(ScheduleTransaction::where('policy_number', $data['policy_number'])->exists())
            {
                CancelScheduleTransactionEvent::dispatch($data);
            }

            return response()->json(['status' => true, 'message' => 'Contract cancelled successfully', 'type' => 'success'], 200);
        } catch (\Exception $ex) {
            return response()->json(['status' => false, 'message' => $ex->getMessage(), 'type' => 'error'], 500);
        }
    }


}
