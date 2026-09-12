<?php

namespace AlphaDirect\Http\Controllers\USSD;

use AlphaDirect\Activation;
use AlphaDirect\Customer;
use AlphaDirect\CustomerBanking;
use AlphaDirect\CustomerGeneratedActivationCode;
use AlphaDirect\CustomerProfile;
use AlphaDirect\FactorMain;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController;
use AlphaDirect\Http\Controllers\Payment\RealPay\RealPayController;
use AlphaDirect\Http\Controllers\Payment\VCS\PaymentController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\KYC;
use AlphaDirect\PaymentUrls;
use AlphaDirect\PaymentVendor;
use AlphaDirect\Policy;
use AlphaDirect\PolicyBeneficiary;
use AlphaDirect\PolicyMember;
use AlphaDirect\Product;
use AlphaDirect\Models\ProductCoverage;
use AlphaDirect\Productplan;
use AlphaDirect\Region;
use AlphaDirect\Transaction;
use AlphaDirect\Vehicle;
use Carbon\Carbon;
use DB;
use Http\Client\Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Log;
use Redirect;
use Response;
use Validator;

class ussd extends Controller
{
    /*
      * to retrieve all generated activation codes
      * return JSON
      */
    public function getActivationCodeData(Request $request)
    {
        try {
            //code...
            $activationProduct = Activation::where('activation_code', $request->activation_code)->where('status', 0)->first();

            if ($activationProduct != null) {
                $activationCode = $activationProduct->activation_code;
                $plans = Productplan::where('id', $activationProduct->product_plan_id)
                    ->first(array('id', 'name', 'premium'));
                $product = Product::where('id', $activationProduct->product_id)
                    ->first(array('id', 'name', 'premium_type_id', 'has_vehicle', 'has_member'));
                return response()->json(['product' => $product, 'activation_code' => $activationCode, 'plans' => $plans, 'status' => $activationProduct->status], 200);
            } else {
                return response()->json(['status' => 'failed'], 409);
            }
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage(), 500);
        }
    }

    /*
      * to retrieve activation code information for Pay.alphadirect.co.bw
      * return JSON
      */
    public function getActivationCodeDataPay(Request $request)
    {

        try {
            //code...
            $activationProduct = Activation::where('activation_code', $request->activation_code)->where('status', 0)->first();

            if ($activationProduct != null) {
                $activationCode = $activationProduct->activation_code;
                $Check = CustomerGeneratedActivationCode::where('activation_code', $activationCode)->first();
                if ($Check)
                    $codeGenerated = 1;
                else
                    $codeGenerated = 0;

                $profile = null;
                //check for omang and passport
                if ($profile == null) {
                    if ($request->get('omang') != null && $request->get('passport') != null) {
                        $profile = CustomerProfile::where('omang', $request->get('omang'))->orWhere('passport', $request->get('passport'))->orderBy('id', 'asc')->first(array('customer_id'));
                    } elseif ($request->get('omang') == null && $request->get('passport') != null) {
                        $profile = CustomerProfile::where('passport', $request->get('passport'))->orderBy('id', 'asc')->first(array('customer_id'));
                    } elseif ($request->get('omang') != null && $request->get('passport') == null) {
                        $profile = CustomerProfile::where('omang', $request->get('omang'))->orderBy('id', 'asc')->first(array('customer_id'));
                    }
                }
                $kyc = 0;

                if ($profile != NULL) {
                    $customerkyc = KYC::where('customer_id', $profile->customer_id)->first(array('omang', 'passport'));
                    if ($customerkyc != NULL) {
                        if ($customerkyc->omang != NULL && $request->get('omang') != NULL)
                            $kyc = 1;
                        if ($customerkyc->passport != NULL && $request->get('passport') != NULL)
                            $kyc = 1;
                    }

                    // Block customer
                    $customerData = Customer::where('id', $profile->customer_id)->first('is_blocked');
                    if ($customerData != null) {
                        if ($customerData->is_blocked == 1) {
                            return response()->json(['product' => 0, 'activation_code' => $activationCode, 'plans' => 0, 'status' => $activationProduct->status, 'customer_generated' => $codeGenerated, 'kyc' => $kyc, 'is_blocked_check' => true], 200);
                        }
                    }
                    // End Block customer
                }
                $plans = Productplan::where('id', $activationProduct->product_plan_id)
                    ->first(array('id', 'name', 'premium', 'billing', 'sum_assured'));
                $product = Product::where('id', $activationProduct->product_id)
                    ->first(array('id', 'name', 'type', 'premium_type_id', 'has_vehicle', 'has_member', 'region_id'));
                $premiumPayment = new PaymentController();
                if($product->type == Product::PRODUCT_TYPE_HEALTH)
                {
                    $productPlan = Productplan::where('id',$activationProduct->product_plan_id)->first();
                    $premiumMeta = json_decode($productPlan->premiumAndRelation,true);
                    $premium = $premiumPayment->getHibPremiumByProductPlan([],$productPlan,$premiumMeta);
                } elseif ($activationProduct->product_id == 9) {
                    $premiumPayment = new PaymentController();
                    $premium = $premiumPayment->getHospitalCashbackPremiumByProductPlan($activationProduct->product_plan_id);
                } elseif ($activationProduct->product_id == 12) {
                    $premiumPayment = new PaymentController();
                    $premium = $premiumPayment->getADGroupedPremiumByProductPlan($activationProduct->product_plan_id);
                }
                else
                {
                    $premium = $premiumPayment->getPremiumByProductPlan($activationProduct->product_plan_id);
                }

                $plans->premium = $premium;
                return response()->json(['product' => $product, 'activation_code' => $activationCode, 'plans' => $plans, 'status' => $activationProduct->status, 'customer_generated' => $codeGenerated, 'kyc' => $kyc, 'is_blocked_check' => false], 200);
            } else {
                return response()->json(['status' => 'Activation code is either wrong or was used already'], 409);
            }
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage(), 500);
        }
    }

    /*
     * retrieve all generated activation code ussd
     * param: request
     * return JSON
     */
     public function getActivationCodeDataPay2(Request $request)
    {

        try {
               $plans = Productplan::where('id', $request->plan)->where('status', 1)
                    ->first(array('id', 'name', 'premium', 'billing','sum_assured'));
                $product = Product::where('id', $request->product)
                    ->first(array('id', 'name', 'type', 'premium_type_id', 'has_vehicle', 'has_member', 'region_id','product_type_id'));

                // $premiumPayment = new PaymentController();
                // $premium = $premiumPayment->getPremiumByProductPlan($request->plan);

                // $plans->premium = $premium;

                // Initialize premiumAndRelation as null
                $premiumAndRelation = null;

                if ($product) {
                    if ($product->product_type_id == 4) {
                        // Fetch premiumAndRelation for product_type_id == 4
                        // $premiumAndRelation = Productplan::where('id', $request->plan)
                        //     ->value('premiumAndRelation');

                        $premiumPayment = new PaymentController();
                        $premiumAndRelation = $premiumPayment->getHospitalCashbackPremiumByProductPlan($request->plan);
                        // $plans->premium = $premium;
                    } else {
                        // Calculate premium for other product types
                        $premiumPayment = new PaymentController();
                        $premium = $premiumPayment->getPremiumByProductPlan($request->plan);
                        $plans->premium = $premium;
                    }
                }
                return response()->json(['product' => $product,  'plans' => $plans,  'premiumAndRelation' => $premiumAndRelation], 200);

        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage(), 500);
        }
    }
    public function getActivationCodeDataUssd(Request $request)
    {
        try {
            //code...
            $activationProduct = Activation::where('activation_code', $request->activation_code)->where('status', '0')->first();
            if ($activationProduct != null) {
                $product = Product::where('id', $activationProduct->product_id)
                    ->first(array('has_vehicle', 'has_member'));
                return response()->json(['status' => 'sucess', 'has_vehicle' => $product->has_vehicle, 'has_member' => $product->has_member], 200);
            } else {
                return response()->json(['status' => 'failed'], 200);
            }
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage(), 500);
        }
    }

    protected function getUSSDProductFactors(Request $request)
    {
        $factors = FactorMain::with('value')->where('product_id', $request->get('id'))->where('status', 1)->get(array('id', 'name', 'type'));
        // Check if we are not trying to delete ourselves

        $product = Product::where('id', $request->get('id'))->first(array('has_vehicle', 'has_member', 'premium_type_id', 'has_activation_code', 'is_motor_items', 'preinspection', 'kyc_customer', 'sum_insured'));
        if ($product->premium_type_id == 11) {
            $plans = Productplan::where('product_id', $request->get('id'))->where('status', 1)->get(array('id', 'name', 'premium', 'sum_assured'))->toArray();
        } else {
            $plans = null;
        }

        $coverage = ProductCoverage::where('product_id', $request->get('id'))->get(array('coverage_id', 'name'));
        if ($factors->count() == null) {
            // Prepare the error message
            return response()->json(['has_plans' => '1', 'product' => $product, 'plans' => $plans], 200);
        } else {
            return response()->json(['has_plans' => '0', 'product' => $product, 'factors' => $factors], 200);
        }
    }

    /*
     * provides payment form
     * param: policy id
     * return: JSON
     */
    public function loadPaymentForm(Request $request, $id)
    {
        try {
            $str = base64_decode($id);
            $paymentUrl = PaymentUrls::findorFail($str);
            $paymentURL_ID = $str;
            $policy = Policy::findorFail($paymentUrl->policy_id);
            $customer = Customer::where('id', $policy->customer_id)->first();
            $product_plan = Productplan::where('id', $policy->plan_id)->first(array('sum_assured', 'premium', 'slug'));

            $sms = new SmsMessaging();
            //TODO : review this, keep sending texts


            if ($policy != null) {

                $transaction = Transaction::where('policyNumber', $policy->policyNumber)->first('status');

                $customer = Customer::where('id', $policy->customer_id)->first();
                $product = Product::where('id', $policy->product_id)->first();
                $vendors = PaymentVendor::where('status', '1')->get();
                $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;

                if ($product->premium_type_id == 11) {
                    $product_plan = Productplan::where('id', $policy->plan_id)->first(array('sum_assured', 'premium'));
                    $premium = round(($product_plan->premium * ($regionVat / 100)) + $product_plan->premium, 2);
                    $policy->sum_assured = $product_plan->sum_assured;
                    $policy->premium = round($premium, 2);
                    $policy->vat = round($product_plan->premium * ($regionVat / 100), 2);
                    $policy->vat_percent = $regionVat;
                } else {
                    $premium = ($request->premium * ($regionVat / 100)) + $request->premium;
                    $policy->premium = $premium;
                    $policy->vat = $request->get('premium') * ($regionVat / 100);
                    $policy->sum_assured = $request->sum_assured;
                }
                return view('ussd/process', compact('policy', 'customer', 'product', 'vendors', 'transaction', 'premium', 'paymentURL_ID'));
            } else {
                return response('Policy Does not exist', 404); //response page
            }
        } catch (Exception $ex) {

            return response()->json($ex->getMessage(), 500);
        }
    }

    // initiates payment process
    // param: policy id
    // return JSON
    public function processPayment(Request $request, $id)
    {
        try {
            //code..
            $str = base64_decode($id); //decode payment url id
            return response()->json($str);
            $paymentUrl = PaymentUrls::findorFail($str);
            $paymentUrl->status = '1'; //change status to 1

            $paymentUrl->save(); //save the change

            $policy = Policy::findorFail($paymentUrl->policy_id); //get the policy of the payment URL

            if ($policy != null) {
                $product = Product::findorFail($policy->product_id);

                $product_plan = Productplan::findorFail($policy->plan_id)->first(array('sum_assured', 'premium', 'slug'));

                if ($policy->has_subApplicant == '1') {

                    foreach ($request->get('members') as $key => $member) {

                        $m = new PolicyMember();
                        $m->policy_id = $policy->id;
                        $m->relation = $member['relation'];
                        $m->first_name = $member['memberFName'];
                        $m->last_name = $member['memberLName'];
                        $m->dob = Carbon::parse($member['memberDOB'])->format('Y-m-d');
                        $m->gender = $member['memberGender'];
                        $m->save();
                    }
                }
                //check for policy beneficiaries

                if ($product->premium_type_id == 11) {
                    $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
                    $product_plan = Productplan::where('id', $policy->plan_id)->first(array('sum_assured', 'premium'));
                    $premium = round(($product_plan->premium * ($regionVat / 100)) + $product_plan->premium, 2);
                    $policy->sum_assured = $product_plan->sum_assured;
                    $policy->premium = round($premium, 2);
                    $policy->vat = round($product_plan->premium * ($regionVat / 100), 2);
                    $policy->vat_percent = $regionVat;
                } else {
                    $premium = ($request->premium * ($regionVat / 100)) + $request->premium;
                    $policy->premium = $premium;
                    $policy->vat = $request->get('premium') * ($regionVat / 100);
                    $policy->sum_assured = $request->sum_assured;
                }

                if ($policy->has_member == '1') {

                    $beneficiaries = $request->get('beneficiaries');

                    //specify your custom message here
                    $messages = [
                        'required' => 'This field is required',
                        'string' => 'This field must be text format',
                        'file' => 'The :attribute must be a file',
                        'required_if' => 'Please provide Omang or Passport',
                        'mimes' => 'Supported file format for :attribute are :mimes',
                        'max' => 'You have surpassed our maximum length of :max',
                        'min' => 'Length too short',
                        'regex' => 'Please input either valid Botswana Omang or Passport',
                        'date_format' => 'Input the Beneficiary Date of Birth',
                    ];

                    $validator = Validator::make($request->all(), [

                        'beneficiaries.*.beneficiaryRelation' => 'required|string|min:5|max:30',
                        'beneficiaries.*.beneficiaryFName' => 'required|string|min:2|max:30',
                        'beneficiaries.*.beneficiaryLName' => 'required|string|min:2|max:30',
                        'beneficiaries.*.beneficiaryGender' => 'required',
                        'beneficiaries.*.beneficiaryDOB' => 'date_format:Y-m-d|before:today',
                        'beneficiaries.*.beneficiaryOmang' => 'required_if:beneficiaries.*.beneficiaryPassport,null|regex:/[0-9]{4}[1-2]{1}[0-9]{4}/',
                        'beneficiaries.*.beneficiaryPassport' => 'required_if:beneficiaries.*.beneficiaryOmang,null|regex:/^[Bb][Nn][0-9]{7}$/',
                        'beneficiaries.*.beneficiaryPayment' => 'required|numeric|min:0|max:100',

                    ], $messages);

                    if ($validator->fails()) {
                        return Redirect::back()
                            ->withErrors($validator)
                            ->withInput();
                    } else {
                        foreach ($beneficiaries as $beneficiary) {

                            if ($beneficiary['beneficiaryRelation'] != null) {

                                $b = new PolicyBeneficiary();
                                $b->policy_id = $policy->id;
                                $b->relation = $beneficiary['beneficiaryRelation'];
                                $b->first_name = $beneficiary['beneficiaryFName'];
                                $b->last_name = $beneficiary['beneficiaryLName'];
                                $b->omang = $beneficiary['beneficiaryOmang'];
                                $b->passport = $beneficiary['beneficiaryPassport'];
                                $b->dob = Carbon::parse($beneficiary['beneficiaryDOB'])->format('Y-m-d');
                                $b->gender = $beneficiary['beneficiaryGender'];
                                $b->payment = $beneficiary['beneficiaryPayment'];
                                $b->save();
                            }
                        }

                        switch ($request->billingMethod) {

                            case 'VCS':
                                $vcs = new PaymentController;
                                $response = $vcs->handlePayment($policy->policyNumber, 'loadPaymentForm');

                                return $response;
                                break;
                            case 'Orange':
                                $orangeMoney = new OrangeMoneyController();
                                return $orangeMoney->webPayIntiliazer($policy->policyNumber, $policy->premium);
                                break;
                            case 'RealPay':
                                $realPay = new RealPayController();
                                return $realPay->addClientRealPay($policy, $policy->premium);
                                break;
                            default:

                                return Redirect::back()->with('error', 'Please select a payment vendor')
                                    ->withInput($request->all());
                        }
                    }
                }

                if ($policy->has_vehicle == '1') {

                    switch ($request->billingMethod) {
                        case 'VCS':
                            $vcs = new PaymentController;
                            $response = $vcs->handlePayment($policy->policyNumber, 'loadPaymentForm');

                            return $response;
                        case 'Orange':
                            $orangeMoney = new OrangeMoneyController();
                            return $orangeMoney->webPayIntiliazer($policy->policyNumber, $policy->premium);
                            break;
                        case 'RealPay':
                            $realPay = new RealPayController();
                            return $realPay->addClientRealPay($policy, $policy->premium);
                            break;
                        default:
                            return Redirect::back()->with('error', 'Please select a payment vendor')
                                ->withInput($request->all());
                    }
                }
            }
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json(['error' => $ex->getMessage(), 'line' => $ex->getLine(), 'code' => $ex->getCode()]);
        }
    }

    /*
     * get all payment vendors data
     * return JSON
     */
    public function getAllVendorsList()
    {

        $vendors = PaymentVendor::where('status', '1')->get();

        if ($vendors == null) {

            return response()->json([
                'status' => '404',
                'message' => 'No vendors were found',
            ]);
        } else {

            return response()->json($vendors);
        }
    }

    public function ussdPolicy(Request $request)
    {

        try {
            //code...
            $urlValue = \Config::get('values.graphite_url');
            $user = new Customer();
            $user->firstName = $request->fname;
            $user->lastName = $request->lname;
            $user->cellphone = $request->cellphone;
            if ($user->save()) {
                $ifExists = CustomerProfile::where('customer_id',$user->id)->exists();
                if(!empty($ifExists))
                {
                    $profile = CustomerProfile::where('customer_id',$user->id)->first();
                }else{
                    /*Add Records to Customers Profile table*/
                    $profile = new CustomerProfile();
                }
                $profile->customer_id = $user->id;
                $profile->gender = $request->gender;
                $profile->omang = $request->omang;
                $profile->passport = $request->passport;
                $origDate = $request->dob;
                $dob = str_replace('/', '-', $origDate);
                $profile->dob = Carbon::parse(date("Y-m-d", strtotime($dob)));

                if ($profile->save()) {
                    //save the policy
                    $latest = Policy::latest()->first(array('id'));
                    $activationData = Activation::where('activation_code', $request->activation_code)->first();
                    \Log::info('activation data is  ' . $activationData);
                    if ($activationData->product_plan_id != null) {
                        \Log::info('inside the activation if');
                        $product_plan = Productplan::where('id', $activationData->product_plan_id)->first();
                    }
                    \Log::info('outside the activation if ' . $product_plan);
                    $product = Product::where('id', $activationData->product_id)->first();
                    \Log::info($product);
                    $policy = new Policy();
                    \Log::info($policy);
                    \Log::info('id ' . $user->id);
                    $policy->customer_id = $user->id;
                    \Log::info('activation id ' . $activationData->product_id);
                    $policy->product_id = $activationData->product_id;
                    \Log::info('before the region ' . $product_plan->premium);
                    $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;

                    \Log::info('before the block ' . $product_plan->premium);
                    if ($product->premium_type_id == '11') {
                        $premium = ($product_plan->premium * ($regionVat / 100)) + $product_plan->premium;
                        \Log::info('premium plan is ' . $premium);
                        $policy->premium = $premium;
                        $policy->plan_id = $activationData->product_plan_id;
                    } else {
                        $premium = ($request->premium * ($regionVat / 100)) + $request->premium;
                        $policy->premium = $premium;
                    }
                    $policy->policyNumber = 'MIS' . Carbon::now()->year . str_pad(($latest->id + 1), 6, '0', STR_PAD_LEFT);
                    \Log::info('before the block');
                    $policy->status = 0;

                    if ($product->has_activation_code == '1') {
                        $policy->trial_coverage = $activationData->trial_coverage;
                        $policy->trial_period = Carbon::now()->addDays($activationData->trial_periods)->format('Y-m-d');
                        $activationData->status = 1;
                        $activationData->save();
                        $policy->activation_code = $activationData->activation_code;
                        $policy->serial_code = $activationData->serial_code;
                        \Log::info('inside the block');
                    }
                    \Log::info('after the block');
                    $policy->agent_id = '177'; // need to change it when bitrix is done
                    $policy->has_member = $product->has_member;
                    $policy->has_vehicle = $product->has_vehicle;
                    $policy->preinspection = $product->preinspection;
                    $policy->kyc_customer = $product->kyc_customer;
                    $policy->kyc_recipient = $product->kyc_recipient;
                    $policy->is_motor_items = $product->is_motor_items;
                    $policy->policyActivatedDate = null;
                    $policy->monthly_income = null;
                    $policy->policyType = null;
                    $policy->balance = 0;
                    $policy->note = null;
                    $policy->limit = $product->limit;
                    $policy->sum_assured = $product->sum_insured;
                    $policy->leadSource = $request->leadSource;
                    \Log::info('policy is' . $policy);
                    $policy->save();
                    $customerKYC = new KYC();
                    $customerKYC->customer_id = $user->id;
                    $customerKYC->save();

                    if ($request->vehiclePlate) {

                        $vehicle = new Vehicle();
                        $vehicle->customer_id = $user->id;
                        $vehicle->policy_id = $policy->id;
                        $vehicle->vehiclePlate = $request->vehiclePlate;
                        $vehicle->save();
                    }

                    $banking = new CustomerBanking();
                    $banking->customer_id = $user->id;
                    $banking->policy_id = $policy->id;
                    $banking->billing = null;
                    $banking->billingCell = null;
                    $banking->bankName = null;
                    $banking->branchCode = null;
                    $banking->accountType = null;
                    $banking->accountNumber = null;
                    $banking->save();
                }
                $sms = new SmsMessaging();
                $leadSource = 'ussd';
                $msgResponse = $sms->sendPaymentUrl($user->cellphone, $policy->id, $policy->policyNumber, $leadSource);

                if ($msgResponse->getData()->status == 'success') {

                    return response()->json(['status' => 'success', 'policy_id' => $policy->policyNumber], 200);
                } else {
                    die('payment url message not sent');
                }
            } else {
                return response()->json(['status' => 'Failed'], 400);
            }
        } catch (\Exception $e) {
            //throw $th;
            echo $e->getMessage();
        }
    }

    /*
     * provides latest APKbuild for app
     * return downloadable apk file
     */
    public function downloadLatestApkBuild()
    {
        $file = DB::table('apkFileUploads')->latest('created_at')->first();

        if (Storage::disk('s3')->exists($file->filepath)) {
            return Storage::disk('s3')->download($file->filepath);
        }
    }
}
