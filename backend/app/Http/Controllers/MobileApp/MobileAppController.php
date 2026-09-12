<?php

namespace AlphaDirect\Http\Controllers\MobileApp;

use AlphaDirect\AccidentDriver;
use AlphaDirect\KycCompliance;
use AlphaDirect\Activation;
use AlphaDirect\ADGroupedBeneficiary;
use AlphaDirect\Billing;
use AlphaDirect\City;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Claim;
use AlphaDirect\ClaimAccident;
use AlphaDirect\ClaimAccidentPassenger;
use AlphaDirect\ClaimLife;
use AlphaDirect\ClaimThirdParty;
use AlphaDirect\ClaimVehicle;
use AlphaDirect\Customer;
use AlphaDirect\CustomerBanking;
use AlphaDirect\CustomerGeneratedActivationCode;
use AlphaDirect\CustomerProfile;
use AlphaDirect\FactorMain;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\FrontendPay\CustomerController;
use AlphaDirect\PolicyCoveredPerson;
use AlphaDirect\State;
use AlphaDirect\Stores;
use AlphaDirect\Master;
use AlphaDirect\PolicyLead;
use AlphaDirect\FactorSubType;
use AlphaDirect\DeviceMakeModel;
use AlphaDirect\PolicyCellPhone;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController;
use AlphaDirect\Http\Controllers\Payment\VCS\VcsController;
use AlphaDirect\Http\Controllers\Payment\VCS\PaymentController;
use AlphaDirect\Http\Controllers\Payment\Flutterwave\FlutterwaveController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\KYC;
use AlphaDirect\Lookup;
use AlphaDirect\Country;
use AlphaDirect\OTP;
use AlphaDirect\Policy;
use AlphaDirect\PolicyBeneficiary;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\PolicyFactor;
use AlphaDirect\PolicyMember;
use AlphaDirect\Product;
use AlphaDirect\Models\ProductCoverage;
use AlphaDirect\Productplan;
use AlphaDirect\Transaction;
use AlphaDirect\VcsTransaction;
use AlphaDirect\VcsNewTransaction;
use AlphaDirect\Region;
use AlphaDirect\Vehicle;
use AlphaDirect\VehicleMake;
use AlphaDirect\PolicyTyreRim;
use AlphaDirect\Config;
use AlphaDirect\CustomerConsent;
use AlphaDirect\CustomerMati;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Helper;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Crypt;
use File;
use Hash;
use Http\Client\Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Redirect;
use DB;
use Log;
use AlphaDirect\Repositories\Customer\CustomerInterface;
use AlphaDirect\Repositories\CustomerBanking\CustomerBankingInterface;
use AlphaDirect\Repositories\CustomerKyc\CustomerKycInterface;
use AlphaDirect\Repositories\CustomerProfile\CustomerProfileInterface;
use AlphaDirect\Repositories\PolicyCellPhone\PolicyCellPhoneInterface;
use AlphaDirect\Events\CommissionPolicyEvent;
use AlphaDirect\Events\DecrementCounterIfPolicySellsEvent as DecrementCounter;
use AlphaDirect\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use AlphaDirect\Http\Controllers\Admin\DocumentController;
use AlphaDirect\Http\Controllers\DpoPaymentController;
use AlphaDirect\UserPassword;
use Illuminate\Support\Str;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\User;
use AlphaDirect\Events\ScheduleTransactionEvent;
use AlphaDirect\HospitalCashbackCoapplicants;
use AlphaDirect\MobileAppErrorLog;
use AlphaDirect\Http\Controllers\NgeniusPaymentController;
use AlphaDirect\PolicyTerm;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\Models\PaymentEmail;
use AlphaDirect\Http\Controllers\LlmApiCrontroller;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\QuoteSettings;
use AlphaDirect\Http\Controllers\PayM8Controller;
use AlphaDirect\Jobs\UpdateRealpayInstallmentJob;
use AlphaDirect\Pay;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractDetails;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\ScheduleTransaction;
use AlphaDirect\PolicyBundled;

class MobileAppController extends Controller
{
    protected $customer_interface;
    protected $customer_profile_interface;
    protected $customer_kyc_interface;
    protected $policy_cell_phone_interface;

    public function __construct(CustomerInterface $customer_interface, CustomerProfileInterface $customer_profile_interface, CustomerKycInterface $customer_kyc_interface, CustomerBankingInterface $customer_banking_interface, PolicyCellPhoneInterface $policy_cell_phone_interface)
    {
        $this->customer_interface          = $customer_interface;
        $this->customer_profile_interface  = $customer_profile_interface;
        $this->customer_kyc_interface      = $customer_kyc_interface;
        $this->customer_banking_interface  = $customer_banking_interface;
        $this->policy_cell_phone_interface = $policy_cell_phone_interface;
    }
    /* API to login via mobile agent app
    */
    public function mobileAppLogin(Request $request)
    {
        if (Auth::guard('customer')->attempt(['cellphone' => request('cellphone'), 'password' => request('password')])) {
            $user = Auth::guard('customer')->user();
            $token = Str::random(80);
            $user->api_token = hash('sha256', $token);
            $user->save();

            $customerProfile = CustomerProfile::where('customer_id', $user->id)->first();
            $kyc = KYC::where('customer_id', $user->id)->first();
            // return response()->json($kyc);

            $alphaResponse = response()->json(['user' => $user, 'api_token' => $user->api_token, 'profile' => $customerProfile, 'kyc' => $kyc], 200);

            return $alphaResponse;
        } else {
            return response()->json(['message' => 'Login Failed, please create account'], 401);
        }
    }

    public function getActivationCode(Request $request)
    {
        $request->validate([
            'product_id'      => 'required|numeric',
            'product_plan_id' => 'required|numeric',
        ]);
        try {
            $activationProduct = Activation::where('product_id', $request->product_id)
                ->where('product_plan_id', $request->product_plan_id)
                ->where('status', 0)->orderBy('id', "ASC")
                ->take(10)
                ->pluck('activation_code');
            if ($activationProduct != null) {
                return response()->json(['activationCodes' => $activationProduct], 200);
            } else {
                return response()->json(['message' => 'Activation code is not found'], 401);
            }
        } catch (Exception $ex) {
            return response()->json(['message' => 'Invalid data'], 401);
        }
    }


    /*
    * Api to pull data from activation code
    */
    public function getActivationCodeData(Request $request)
    {
        try {
            //code...
            $activationProduct = Activation::where('activation_code', $request->activation_code)->where('status', 0)->first();

            if ($activationProduct != null) {
                $plans = Productplan::where('id', $activationProduct->product_plan_id)->first(array('id', 'name', 'premium'));
                $product = Product::where('id', $activationProduct->product_id)->first(array('id', 'name', 'premium_type_id', 'has_vehicle', 'has_member'));
                $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
                if ($product->premium_type_id == 11) {
                    $product_plan = Productplan::where('id', $plans->id)->first(array('sum_assured', 'premium'));
                    $premium = round(($product_plan->premium * ($regionVat / 100)) + $product_plan->premium, 2);
                } else {
                    $premium = ($request->premium * ($regionVat / 100)) + $request->premium;
                }
                $TCstatement = Config::where('key', 'tc_agent_app')->first();
                $TCstatement = str_replace('[PREMIUM]', $premium, $TCstatement->value);
                $TCstatement = stripcslashes(strip_tags($TCstatement));
                return response()->json(['product' => $product, 'plans' => $plans, 'termsAndConditions' => $TCstatement, 'premium' => $premium, 'status' => $activationProduct->status], 200);
            } else {
                return response()->json(['status' => 'failed'], 409);
            }
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage(), 500);
        }
    }

    /*
    * Api to pull data from activation code
    */
    public function appUserLifePolicy(Request $request)
    {
        if ($request->get('passport') == NULL) {
            $customer = CustomerProfile::where('omang', $request->get('omang'))->first();
        } else {
            $customer = CustomerProfile::where('passport', $request->get('passport'))->first();
        }

        if ($customer != null) {
            $customer->dob = Carbon::parse(str_replace("/", "-", $customer->dob))->format('Y-m-d');
            $customer->license_valid_till = Carbon::parse(str_replace("/", "-", $customer->license_valid_till))->format('Y-m-d');
        }

        $mati_config = Config::where('key','enable_mati')->first(array('id','value'));
        $mati_enable = $mati_config->value;

        $activationProduct = Activation::where('activation_code', $request->activation_code)->orderBy('id', 'DESC')->where('status', 0)->first();

        if ($activationProduct == null) {
            return response()->json(['title' => 'Check activation code', 'description' => 'Activation code may be used or expired'], 401);
        }

        $plans                    = Productplan::where('id', $activationProduct->product_plan_id)->first(array('id', 'name', 'premium'));
        $plans->premium           = (float)$plans->premium;
        $product                  = Product::where('id', $activationProduct->product_id)->first(array('id', 'name', 'region_id', 'premium_type_id', 'has_vehicle', 'has_member', 'kyc_compliance'));
        $product->has_member      = (int)$product->has_member;
        $product->has_vehicle     = (int)$product->has_vehicle;
        $product->premium_type_id = (int)$product->premium_type_id;
        $product->region_id = (int)$product->region_id;
        $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
        $premium =  round(($plans->premium  * ($regionVat / 100)) + $plans->premium, 2);
        $TCstatement = Config::where('key', 'tc_agent_app')->first();
        $TCstatement = str_replace('[PREMIUM]', $premium, $TCstatement->value);
        $TCstatement = stripcslashes(strip_tags($TCstatement));

        $compliance = KycCompliance::where('id', $product->kyc_compliance)->first(array('flow_id'));
        if ($compliance != NULL && $compliance->flow_id != NULL)
            $flow_id = $compliance->flow_id;
        else {
            if (env('APP_STATUS') == 'Production' || Config::get('values.APP_STATUS') == 'Production')
                $flow_id = '616ac99406694f001be574c7'; // AdvanceKYC LIVE
            else
                $flow_id = '612c87b1ebca36001b310ea1'; // LiveQuote Test
        }

        if (!$customer) {

            if($mati_enable == 0) {
                $customer = array();
                $customer['mati_identity'] = 0;
            }

            return response()->json(['product' => $product, 'plans' => $plans, 'customer' => $customer, 'customerProfile' => $customer,  'termsAndConditions' => $TCstatement, 'premium' => $premium, 'status' => $activationProduct->status, 'mati_flow_id' => $flow_id], 200);
        } else {
            if ($product->has_member == 1) {
                $row = Policy::where('customer_id', $customer->customer_id)->where('product_id', $activationProduct->product_id)->where('status', "!=", 2)->count();

                if ($row > 0) {
                    return response()->json(['title' => 'Customer exists', 'description' => 'The customer already has purchased the specified product'], 406);
                }
            }
            //Legal Products
            if ($product->id == 4) {
                $row = Policy::where('customer_id', $customer->customer_id)->where('product_id', $activationProduct->product_id)->count();

                if ($row > 0) {
                    return response()->json(['title' => 'Customer exists', 'description' => 'The customer already has purchased the specified product'], 406);
                }
            }
            $customerDetails = Customer::where('id', $customer->customer_id)->first();
            //$customerDetails->mati_id = $customerDetails->mati_identity;

            if (isset($customerDetails->mati_identity) && $customerDetails->mati_identity != NULL && $customerDetails->mati_identity != null && $customerDetails->mati_identity != '') {
                //$customerDetails->mati_identity = $customerDetails->mati_identity;
                //$customerDetails->mati_id = $customerDetails->mati_identity;
            } else {
                if($mati_enable == 0){
                    $customerDetails->mati_identity  = 0;
                }else{
                    $customerDetails->mati_identity = NULL;
                }
            }

            // $customerkyc = KYC::where('customer_id', $customer->customer_id)->first(array('omang', 'passport'));
            // if ($customerkyc != NULL) {
            //     if ($customerkyc->omang == NULL && $customerkyc->passport == NULL) {
            //         $customerDetails->mati_identity = NULL;
            //         $customerDetails->mati_id = NULL;
            //     }
            // } else {
            //     $customerDetails->mati_identity = NULL;
            //     $customerDetails->mati_id = NULL;
            // }

            return response()->json(['title' => 'Customer Id exists', 'description' => 'The entered id already exists in system', 'product' => $product, 'customer_id' => $customer->customer_id, 'customer' => $customerDetails, 'customerProfile' => $customer, 'plans' => $plans, 'premium' => $premium, 'termsAndConditions' => $TCstatement, 'status' => $activationProduct->status, 'mati_flow_id' => $flow_id], 409);
        }
    }
    /*getPolicyDetails
    * API  to set user password
    */
    public function setPassword(Request $request)
    {
        $phoneNumber = $request->cellphone;
        $password = $request->password;
        try {
            //code...
            $customer = Customer::where('cellphone', $phoneNumber)->first();
            $customer->password = Hash::make($request->password);

            if ($customer->save()) {
                return response()->json(['message' => 'Password created succefully'], 200);
            } else {
                return response()->json('Unable to set password, please try again.', 401);
            }
        } catch (\Exception $ex) {
            return response()->json($ex->getMessage(), 500);
        }
    }

    public function retrieveActivationCodeAlphaFePay(Request $request)
    {
        $code = CustomerGeneratedActivationCode::where('cellphone', $request->get('cellphone'))
            ->where('id_number', $request->get('id_number'))
            ->Where('status', '0')
            ->Where('product_id', $request->get('product'))
            ->Where('plan_id', $request->get('plan'))
            ->count();
        if ($code > 0) {
            $activationCode = CustomerGeneratedActivationCode::where('cellphone', $request->get('cellphone'))
                ->where('id_number', '=', $request->get('id_number'))
                ->Where('status', '0')
                ->Where('product_id', $request->get('product'))
                ->Where('plan_id', $request->get('plan'))
                ->first(array('activation_code'))->activation_code;
            $cellphone = $request->cellphone;
            $sms_status = event(new \AlphaDirect\Events\SendSms('+267' . $request->cellphone, 'Alpha Direct, Your Activation Code is:' . ' ' . $activationCode));
            // $sms_status = InfobipSms::send('+267' . $request->cellphone, 'Alpha Direct, Your Activation Code is:' . ' ' . $activationCode);
            return response()->json(['status' => 'Activation code sent successfully to ' . $cellphone, 'code' => $activationCode], 200);
        } else {
            return response()->json(['status' => 'Activation code may be expired or used, Please generate new one.'], 401);
        }
    }

    /*Retrieves customer Omang and passport number through customer ID*/
    public function getCustomerID(Request $request)
    {
        try {
            if ($request->customerID != null) {
                $profile = CustomerProfile::where('customer_id', base64_decode($request->customerID))->first();
                if ($profile != null) {
                    return response()->json(['Omang' => $profile->omang, 'Passport' => $profile->passport], 200);
                } else {
                    return response()->json('Customer data not found', 401);
                }
            }
        } catch (Exception $e) {
            return response()->json(["Message" => "Customer data not found", 'Error' => $e], 401);
        }
    }
    public function realpayCustomerCheck(Request $request)
    {   
        if(isset($request->policyNumber) && $request->policyNumber != null){
            $policy = Policy::where('policyNumber',$request->policyNumber)->first();
            if($policy){
                $customerBanking = CustomerBanking::where('customer_id', $policy->customer_id)
                ->whereIn('billing', ['RealPay', 'PayM8'])
                ->first();

                if (!$customerBanking) {
                    return response()->json(['status' => false], 400);
                }

                return response()->json([
                    'status' => true,
                    'billing' => $customerBanking->billing,
                    'customer_id' => $policy->customer_id,
                    'cellphone' => $policy->customer->cellphone,
                    'email' => $policy->customer->email,
                ], 200);
            }else{
                return response()->json(['status' => false], 400);
            }
        }
        $customer = Customer::leftJoin('customer_profile', 'customer.id', '=', 'customer_profile.customer_id');

        $customer->where(function ($q) use ($request) {
            if (!empty($request->cellphone)) {
                $q->orWhere('customer.cellphone', $request->cellphone);
            }
            if (!empty($request->omang)) {
                $q->orWhere('customer_profile.omang', $request->omang);
            }
            if (!empty($request->passport)) {
                $q->orWhere('customer_profile.passport', $request->passport);
            }
            if (!empty($request->email)) {
                $q->orWhere('customer.email', $request->email);
            }
        });

        $customer = $customer->select('customer.*')->orderBy('customer.id', 'DESC')->first();
        //dd($customer);
        if (!$customer) {
            return response()->json(['status' => false], 401);
        }

        $customerBanking = CustomerBanking::where('customer_id', $customer->id)
            ->whereIn('billing', ['RealPay', 'PayM8'])
            ->first();

        if (!$customerBanking) {
            return response()->json(['status' => false], 400);
        }

        return response()->json([
            'status' => true,
            'billing' => $customerBanking->billing,
            'customer_id' => $customer->id,
            
        ], 200);
    }

    public function getCustomerBank(Request $request)
    {
        if(!isset($request->customer_id) || $request->customer_id == null){
            return response()->json(['status' => false ], 400);
        }
          $customerBanking = CustomerBanking::where('customer_id', $request->customer_id)->where('billing','RealPay')->orderBy('id','desc')->first();   
            if($customerBanking){
                if($customerBanking->billing == 'RealPay' ){
                    $bankName = \AlphaDirect\Banks::where('bank_number', $customerBanking->bankName)->first();
                    $branches = \AlphaDirect\BankBranches::where('branch_id', $customerBanking->branchCode)->first();
                    $data = [
                        'customer_id' => $request->customer_id,
                        'account_number' => $customerBanking->accountNumber,
                        'bank_name' => $bankName ? $bankName->bank_name : $customerBanking->bankName,
                        'bank_branch' => $branches ? $branches->name : $customerBanking->branchCode,
                        'billing' => $customerBanking->billing,
                        'account_type' => $customerBanking->accountType == 1 ? "Cheque" : "Savings",
                    ];
                    return response()->json(['status' => true, 'customer_banking' => $customerBanking,'data'=>$data], 200);
                }
            }
                
       
            return response()->json(['status' => false ], 400);
       
    }
 

    /**
     * Check if pay_email exists for any other policy that doesn't belong to the given customer_id
     */
    public function checkPayEmailExists(Request $request)
    {
        try {
            // Validate required parameters
            if (!$request->has('pay_email')) {
                return response()->json([
                    'status' => false,
                    'message' => 'pay_email are required'
                ], 400);
            }

          
            $pay_email = $request->pay_email;
            $existingPayEmail = PaymentEmail::where('pay_email', $pay_email)->orderBy('id','desc')->first();
             
            if (!$existingPayEmail) {
               
                return response()->json([
                    'status' => false,
                    'message' => 'Pay email is available'
                ], 400);
            }else{
                $policy = Policy::where('policyNumber', $existingPayEmail->policyNumber)->first();
                $customer = Customer::where('id','!=', $policy->customer_id)
                ->where('email', $existingPayEmail->pay_email)->first();
                if(isset($request->reduo) && $request->reduo == true){
                
                if ($customer) {
                   
                    return response()->json([
                        'status' => true,
                     ], 200);
                } else {
                   
                    return response()->json([
                        'status' => false,
                        
                    ], 400);
                }
                    
                }else{
                    if(isset($request->customer_id) && $request->customer_id != null){
                        if($policy->customer_id == $request->customer_id){

                             return response()->json([
                                'status' => true,
                                'dpocustomer'=>true
                                
                             ], 200);

                        }else{
                            return response()->json([
                                'status' => true,
                             ], 200);
                        }
                    }
                    return response()->json([
                        'status' => true,
                     ], 200);
                }
                
            }

            return response()->json([
                'status' => false,
                
            ], 400); 
           
          
             
            
           

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while checking pay email',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if customer banking account number exists for another customer when billing is RealPay
     */
    public function checkCustomerBankingAccountExists(Request $request)
    {
        try {
            // Validate required parameters
            if (!$request->has('account_number')) {
                return response()->json([
                    'status' => false,
                    'message' => 'account_number are required'
                ], 401);
            }

           
            $account_number = $request->account_number;
            // A customer may legitimately reuse their OWN bank account across
            // multiple debit-order policies — only an account already held by a
            // DIFFERENT customer should block. When the caller passes the
            // current (OTP-verified) customer's cellphone, exclude that
            // customer's own banking rows so reuse-by-same-customer is allowed.
            // No cellphone (legacy callers) → unchanged behaviour: any match blocks.
            $cellphone = trim((string) $request->input('cellphone', ''));
            $ownCustomerIds = $cellphone !== ''
                ? DB::table('customer')->where('cellphone', $cellphone)->pluck('id')->all()
                : [];

            // Check if account number exists in customer_banking with RealPay/PayM8
            // billing for a DIFFERENT customer (matched by customer_id OR billingCell).
            $existingBanking = CustomerBanking::where('accountNumber', $account_number)
                                            ->whereIn('billing', ['RealPay', 'PayM8'])
                                            ->when($cellphone !== '', function ($q) use ($cellphone, $ownCustomerIds) {
                                                if (!empty($ownCustomerIds)) {
                                                    $q->whereNotIn('customer_id', $ownCustomerIds);
                                                }
                                                $q->where(function ($w) use ($cellphone) {
                                                    $w->whereNull('billingCell')
                                                      ->orWhere('billingCell', '!=', $cellphone);
                                                });
                                            })
                                            ->first();

            if (!$existingBanking) {
                // Account number doesn't exist for other customers
                return response()->json([
                    'status' => false,
                   
                ], 400);
            }else{
                return response()->json([
                    'status' => true,
                    
                ], 200);
            }
       

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while checking account number',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    


    /**
     * Saving policy data from mobile app and redirecting to payment page
     */
    public function activatePolicyFirstTimeUser(Request $request)
    {
        if (isset($request->Payment_method) && $request->Payment_method == 'DPO') {
            if (!isset($request->data['email'])) {
                return response()->json(['title' => 'Email is mandatory', 'description' => 'PLease provide email if payment method is DPO.'], 412);
            }
        }
        try {
             // Enable Mati
             $mati_config = Config::where('key','enable_mati')->first(array('id','value'));
             $mati_enable = $mati_config->value;

             if($mati_enable == 0){
                 $data_mati_id  = 0;
             }else{
                 $data_mati_id  = $request->get('mati-identityId');
             }

            $customerKYCToken = htmlspecialchars(strip_tags($request->input('customerKYCToken', NULL)));
            if ($customerKYCToken == NULL && $mati_enable == 1 && $customerKYCToken == '' && $data_mati_id == NULL && $data_mati_id == 'null' && $data_mati_id == '' && !$request->hasFile('omang') && !$request->hasFile('passport')) {
                if($request->data['agent_id'] == null){
                return response()->json(['title' => 'KYC is Mandatory', 'description' => 'Please Upload Omang Or Passport Image for KYC'], 412);
            }
            }
            if ($request->get('beneficiaries') != null) {
                $payment = 0;
                foreach ($request->get('beneficiaries') as $key => $beneficiary) {
                    if ($beneficiary['beneficiaryRelation'] != null) {
                        $payment = $payment + $beneficiary['beneficiaryPayment'];
                    }
                }
                if ($payment > 100) {
                    return response()->json(['title' => 'Beneficiary share exceeded', 'description' => 'Please adjust the beneficiary share not more than 100.'], 412);
                }
            }
            $product = Product::where('id', $request->product_id)->first(array('has_vehicle', 'has_member', 'premium_type_id', 'limit', 'kyc_recipient', 'kyc_customer', 'preinspection', 'is_motor_items', 'region_id', 'has_activation_code', 'type'));

            if ($product->has_vehicle == 1) {
                $vehicle = $this->checkIfVehicleAlreadyRegistered($request->input('vehiclePlate'));

                if ($vehicle) {
                    return response()->json(['title' => 'Motor vehicle exists', 'error' => 'You may not register a new policy with an already existing vehicle plate.'], 400);
                }
            }

            if ($request->get('omang') == "null") {
                $request->merge([
                    'omang' => null,
                ]);
            }

            if ($request->get('passport') == "null") {
                $request->merge([
                    'passport' => null,
                ]);
            }

            if ($request->get('cellphone') == "null") {
                $request->merge([
                    'cellphone' => null,
                ]);
            }

            if ($request->get('email') == "null") {
                $request->merge([
                    'email' => null,
                ]);
            }

            if ($product->has_member == 1) {
                $omang         = htmlspecialchars(strip_tags($request->input('omang', '')));
                $passport      = htmlspecialchars(strip_tags($request->input('passport', '')));
                $idType        = $omang != null ? "Omang" : "Passport";
                $idValue       = $omang != null ? $omang : $passport;
                $customerCheck = $this->checkIfMemberAlreadyRegistered($idType, $idValue);

                if ($customerCheck == true) {
                    return response()->json(['title' => 'Customer already registered for this policy', 'error' => 'Customer is already owner of Accidental death insurace policy. Please check again.'], 400);
                }
            }

            if ($product->type == 'Cellphone') {

                if ($request->get('devices') != NULL) {
                    foreach ($request->get('devices') as $key => $device) {

                        if (!isset($device['imei']) || $device['imei'] == null) {
                            return response()->json(['title' => 'Device details not captured', 'error' => 'Device details not captured.'], 411);
                        }
                    }
                } else {
                    return response()->json(['title' => 'Please add devices first', 'error' => 'Please add devices first.'], 411);
                }
            }

            $customer_id = htmlspecialchars(strip_tags($request->input('customer_id', '')));
            $omang       = htmlspecialchars(strip_tags($request->input('omang', '')));
            $passport    = htmlspecialchars(strip_tags($request->input('passport', '')));
            $cellphone   = htmlspecialchars(strip_tags($request->input('cellphone', '')));
            $profile     = null;
            if ($cellphone != null) {
                $profile = Customer::where('cellphone', $cellphone)->orderBy('id', 'asc')->first(array('id', 'is_blocked'));
            }

            // is blocked
            if ($profile && $profile->is_blocked != null) {
                if ($profile->is_blocked == 1) {
                    return response()->json(['success' => false, 'message' => 'customer is blocked'], 200);
                }
            }

            if ($profile != null) {
                $customer_id = $profile->id;
            }

            //check for omang and passport
            if ($profile == null) {
                if ($request->get('omang') != null && $request->get('passport') != null) {
                    $profile = CustomerProfile::where('omang', $request->get('omang'))->orWhere('passport', $request->get('passport'))->orderBy('id', 'asc')->first(array('customer_id'));
                } elseif ($request->get('omang') == null && $request->get('passport') != null) {
                    $profile = CustomerProfile::where('passport', $request->get('passport'))->orderBy('id', 'asc')->first(array('customer_id'));
                } elseif ($request->get('omang') != null && $request->get('passport') == null) {
                    $profile = CustomerProfile::where('omang', $request->get('omang'))->orderBy('id', 'asc')->first(array('customer_id'));
                }
                if ($profile != NULL) {
                    $customer_id = $profile->customer_id;
                }
            }

            if ($request->input('middleName') == "null") {
                $request->middleName = null;
            }


            if ($request->customerExists == 1 || $customer_id != NULL) {
                $user = Customer::where('id', $customer_id)->first();
                $profile = CustomerProfile::where('customer_id', $user->id)->first();
                if (isset($data_mati_id) && $data_mati_id != NULL && $data_mati_id != '' && $data_mati_id != 'null') {
                    $user->mati_identity = $data_mati_id;
                    $user->save();
                }elseif($data_mati_id == 0){
                    $user->mati_identity = $data_mati_id;
                    $user->save();
                }
                else {
                    if($mati_enable == 1 && $user->mati_identity == NULL || $user->mati_identity == ''){
                     if($request->data['agent_id'] == null){
                        return response()->json(['title' => 'KYC is Mandatory', 'error' => 'Please Complete Mati Verification'], 411);
                    }
                    }
                }

                $fname = htmlspecialchars(strip_tags($request->input('firstName', '')));
                $mname = htmlspecialchars(strip_tags($request->input('middleName', '')));
                $lname = htmlspecialchars(strip_tags($request->input('lastName', '')));
                $email = htmlspecialchars(strip_tags($request->input('emailId', '')));
                $cellphone = htmlspecialchars(strip_tags($request->input('cellphone', '')));
                $data = [
                    'firstName'  => $fname,
                    'middleName' => $mname,
                    'lastName'   => $lname,
                    'email'      => $email,
                    'cellphone'  => $cellphone,
                ];
                $user_id = $this->customer_interface->update_customer_by_id($customer_id, $data);

                $gender               = htmlspecialchars(strip_tags($request->input('gender', '')));
                $address              = htmlspecialchars(strip_tags($request->input('address', '')));
                $city                 = htmlspecialchars(strip_tags($request->input('city', '')));
                $countryId            = htmlspecialchars(strip_tags($request->input('passportIssuingCountry', '')));
                $state                = htmlspecialchars(strip_tags($request->input('state', '')));
                $omang                = htmlspecialchars(strip_tags($request->input('omang', '')));
                $passport             = htmlspecialchars(strip_tags($request->input('passport', '')));
                $maritalStatus        = htmlspecialchars(strip_tags($request->input('maritalStatus', '')));
                $drivingLicenseNumber = htmlspecialchars(strip_tags($request->input('drivingLicenseNumber', '')));
                $licenseValidTill     = Carbon::parse(htmlspecialchars(strip_tags($request->input('licenseValidTill', ''))))->format('Y-m-d');
                $dob                  = Carbon::parse(htmlspecialchars(strip_tags($request->get('dob'))))->format('Y-m-d');
                $sourceOfIncome       = json_encode($request->get('sourceOfIncome'));
                $data = [
                    'customer_id'            => $customer_id,
                    'gender'                 => $gender,
                    'address'                => $address,
                    'city'                   => $city,
                    'countryId'              => $countryId,
                    'state'                  => $state,
                    'omang'                  => $omang,
                    'passport'               => $passport,
                    'maritalstatus'          => $maritalStatus,
                    'driving_license_number' => $drivingLicenseNumber,
                    'license_valid_till'     => $licenseValidTill,
                    'dob'                    => $dob,
                    'sourceOfIncome'         => $sourceOfIncome
                ];
                //Legal Insurance
                if ($request->product_id == 4) {
                    $e_name               = htmlspecialchars(strip_tags($request->input('e_name', '')));
                    $emp_phone            = htmlspecialchars(strip_tags($request->input('emp_phone', '')));
                    $emp_no               = htmlspecialchars(strip_tags($request->input('emp_no', '')));
                    $salary_pay_date      = htmlspecialchars(strip_tags($request->input('salary_pay_date', '')));
                    $data['e_name']                 = $e_name;
                    $data['emp_phone']              = $emp_phone;
                    $data['emp_no']                 = $emp_no;
                    $data['salary_pay_date']        = $salary_pay_date;
                }

                $this->customer_profile_interface->update_customer_profile($customer_id, $data);

                $customerKYC = KYC::where('customer_id', $customer_id)->first();
                if ($customerKYC == null) {
                    $customerKYC = new KYC();
                }

                $customerKYCToken = htmlspecialchars(strip_tags($request->input('customerKYCToken', NULL)));
                $customerKYCTokenData = KYC::where('id', $customerKYCToken)->first();
                if ($customerKYC == null && $customerKYCToken != null) {
                    $customerKYC = new KYC;
                    $customerKYC->customer_id = $customerKYCToken;
                    $customerKYC->save();
                }
                if ($customerKYCToken != NULL && $customerKYCTokenData != NULL) {
                    if ($customerKYCTokenData->omang != NULL && $customerKYCTokenData->omang != '')
                        $customerKYC->omang = $customerKYCTokenData->omang;
                    if ($customerKYCTokenData->omangBack != NULL && $customerKYCTokenData->omangBack != '')
                        $customerKYC->omangBack = $customerKYCTokenData->omangBack;
                    if ($customerKYCTokenData->passport != NULL && $customerKYCTokenData->passport != '')
                        $customerKYC->passport = $customerKYCTokenData->passport;
                }
                if ($customerKYC == NULL) {
                    $customerKYC = KYC::where('id', $customerKYCToken)->first();
                }

                $customerKYC->customer_id = $customer_id;
                $customerKYC->passportIssuingCountry = htmlspecialchars(strip_tags($request->passportIssuingCountry, NULL));
                if ($request->hasFile('driving_license')) {
                    $file = htmlspecialchars(strip_tags($request->file('driving_license')));
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $customerKYC->customer_id . '/' . 'Customer' . '/driving_license' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $customerKYC->driving_license = $filePath;
                }
                if ($request->hasFile('omang')) {
                    $file = htmlspecialchars(strip_tags($request->file('omang')));
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $customerKYC->customer_id . '/' . 'Customer' . '/omang' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $customerKYC->omang = $filePath;
                }
                if ($request->hasFile('omangBack')) {
                    $file = htmlspecialchars(strip_tags($request->file('omangBack')));
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $customerKYC->customer_id . '/' . 'Customer' . '/omangBack' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $customerKYC->omangBack = $filePath;
                }
                if ($request->hasFile('proof_residence')) {
                    $file = htmlspecialchars(strip_tags($request->file('proof_residence')));
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $customerKYC->customer_id . '/' . 'Customer' . '/proof_residence' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $customerKYC->proof_residence = $filePath;
                }
                if ($request->hasFile('proof_income')) {
                    $file = htmlspecialchars(strip_tags($request->file('proof_income')));
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $customerKYC->customer_id . '/' . 'Customer' . '/proof_income' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $customerKYC->proof_income = $filePath;
                }
                if ($request->hasFile('passport')) {
                    $file = htmlspecialchars(strip_tags($request->file('passport')));
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $customerKYC->customer_id . '/' . 'Customer' . '/passport' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $customerKYC->passport = $filePath;
                }
            } else {
                $fname = htmlspecialchars(strip_tags($request->input('firstName', '')));
                $mname = htmlspecialchars(strip_tags($request->input('middleName', '')));
                $lname = htmlspecialchars(strip_tags($request->input('lastName', '')));
                $email = htmlspecialchars(strip_tags($request->input('emailId', '')));
                $cellphone = htmlspecialchars(strip_tags($request->input('cellphone', '')));
                $data = [
                    'firstName'   => $fname,
                    'middleName'    => $mname,
                    'lastName'    => $lname,
                    'email'       => $email,
                    'cellphone'   => $cellphone,
                ];
                $user_id = $this->customer_interface->add_new_customer($data);
                $user = Customer::where('id', $user_id)->first();

                if (isset($data_mati_id) && $data_mati_id != NULL && $data_mati_id != '' && $data_mati_id != 'null') {
                    $user->mati_identity = $data_mati_id;
                    $user->save();
                }elseif($data_mati_id == 0){
                    $user->mati_identity = $data_mati_id;
                    $user->save();
                }
                // else {
                //     return response()->json(['title' => 'KYC is Mandatory', 'error' => 'Please Complete Mati Verification'], 411);
                // }

                $gender               = htmlspecialchars(strip_tags($request->input('gender', '')));
                $address              = htmlspecialchars(strip_tags($request->input('address', '')));
                $city                 = htmlspecialchars(strip_tags($request->input('city', '')));
                $countryId            = htmlspecialchars(strip_tags($request->input('passportIssuingCountry', '')));
                $state                = htmlspecialchars(strip_tags($request->input('state', '')));
                $omang                = htmlspecialchars(strip_tags($request->input('omang', '')));
                $passport             = htmlspecialchars(strip_tags($request->input('passport', '')));
                $maritalStatus        = htmlspecialchars(strip_tags($request->input('maritalStatus', '')));
                $drivingLicenseNumber = htmlspecialchars(strip_tags($request->input('drivingLicenseNumber', '')));
                $licenseValidTill     = Carbon::parse(htmlspecialchars(strip_tags($request->input('licenseValidTill', ''))))->format('Y-m-d');
                $dob                  = Carbon::parse(htmlspecialchars(strip_tags($request->get('dob'))))->format('Y-m-d');
                $sourceOfIncome       = json_encode($request->get('sourceOfIncome'));

                $data = [
                    'customer_id'            => $user_id,
                    'gender'                 => $gender,
                    'address'                => $address,
                    'city'                   => $city,
                    'countryId'              => $countryId,
                    'state'                  => $state,
                    'omang'                  => $omang,
                    'passport'               => $passport,
                    'maritalstatus'          => $maritalStatus,
                    'driving_license_number' => $drivingLicenseNumber,
                    'license_valid_till'     => $licenseValidTill,
                    'dob'                    => $dob,
                    'sourceOfIncome'         => $sourceOfIncome
                ];

                //Legal Insurance
                if ($request->product_id == 4) {
                    $e_name               = htmlspecialchars(strip_tags($request->input('e_name', '')));
                    $emp_phone            = htmlspecialchars(strip_tags($request->input('emp_phone', '')));
                    $emp_no               = htmlspecialchars(strip_tags($request->input('emp_no', '')));
                    $salary_pay_date      = htmlspecialchars(strip_tags($request->input('salary_pay_date', '')));
                    $data['e_name']                 = $e_name;
                    $data['emp_phone']              = $emp_phone;
                    $data['emp_no']                 = $emp_no;
                    $data['salary_pay_date']        = $salary_pay_date;
                }

                $this->customer_profile_interface->add_new_customer_profile($data);

                $customerKYCToken = htmlspecialchars(strip_tags($request->input('customerKYCToken', NULL)));
                $customerKYC = NULL;
                if ($customerKYCToken != NULL)
                    $customerKYC = KYC::where('id', $customerKYCToken)->first();
                else {
                    $customerKYC = new KYC();
                }
                $customerKYC->customer_id = $user_id;
                $customerKYC->passportIssuingCountry = htmlspecialchars(strip_tags($request->passportIssuingCountry, NULL));
                if ($request->hasFile('driving_license')) {
                    $file = htmlspecialchars(strip_tags($request->file('driving_license')));
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $customerKYC->customer_id . '/' . 'Customer' . '/driving_license' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $customerKYC->driving_license = $filePath;
                }
                if ($request->hasFile('omang')) {
                    $file = htmlspecialchars(strip_tags($request->file('omang')));
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $customerKYC->customer_id . '/' . 'Customer' . '/omang' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $customerKYC->omang = $filePath;
                }
                if ($request->hasFile('omangBack')) {
                    $file = htmlspecialchars(strip_tags($request->file('omangBack')));
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $customerKYC->customer_id . '/' . 'Customer' . '/omangBack' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $customerKYC->omangBack = $filePath;
                }
                if ($request->hasFile('proof_residence')) {
                    $file = htmlspecialchars(strip_tags($request->file('proof_residence')));
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $customerKYC->customer_id . '/' . 'Customer' . '/proof_residence' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $kyc_data['proof_residence'] = $filePath;
                }
                if ($request->hasFile('proof_income')) {
                    $file = htmlspecialchars(strip_tags($request->file('proof_income')));
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $customerKYC->customer_id . '/' . 'Customer' . '/proof_income' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $customerKYC->proof_income = $filePath;
                }
                if ($request->hasFile('passport')) {
                    $file = htmlspecialchars(strip_tags($request->file('passport')));
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $customerKYC->customer_id . '/' . 'Customer' . '/passport' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $customerKYC->passport = $filePath;
                }
            }
            // if ($product->has_vehicle == 1  && $customerKYC->driving_license && $customerKYC->proof_residence && ($customerKYC->omang || $customerKYC->passport))
            //     $customerKYC->compliance = 1;
            // else if ($product->has_member == 1 && $customerKYC->proof_residence && ($customerKYC->omang || $customerKYC->passport))
            //     $customerKYC->compliance = 1;
            // else
            //     $customerKYC->compliance = 0;
            //$this->customer_kyc_interface->add_new_customer_kyc($kyc_data);

            $customer = Customer::where('id',$user_id)->first();

            if (!isset($customer) && !isset($customer->mati_identity)) {
                $customerKYC->compliance = 0;
            }

            $customerKYC->save();
            //save the policy
            //Latestid for Policy Number
            $latest = Policy::orderBy('id', 'desc')->first();
            if ($latest == null) {
                $latest = collect();
                $latest->id = 1;
            }

            $policy = new Policy();
            $policy->customer_id = $user->id;
            $policy->storeID = isset($request->storerId) ? $request->storerId : $request->storeID; // remove this when app goes live
            $policy->agent_id = $request->leadAgentId;
            $policy->product_id = $request->product_id;
            $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
            if ($product->premium_type_id == 11) {
                $product_plan = Productplan::where('id', $request->planId)->first(array('sum_assured', 'premium', 'slug', 'flutter_plan_id'));
                $premium = round(($product_plan->premium * ($regionVat / 100)) + $product_plan->premium, 2);
                $policy->plan_id = $request->planId;
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
            $policy->policyNumber     = 'MIS' . Carbon::now()->year . str_pad(($latest->id + 1), 6, '0', STR_PAD_LEFT);
            $policy->status           = 0;
            $policy->leadSource       = 'MobileApp';
            $policy->has_vehicle      = $product->has_vehicle;
            $policy->has_member       = $product->has_member;
            $policy->preinspection    = $product->preinspection;
            $policy->is_motor_items   = $product->is_motor_items;
            $policy->limit            = $product->limit;
            $policy->kyc_customer     = $product->kyc_customer;
            $policy->kyc_recipient    = $product->kyc_recipient;
            $policy->billingStartDate = $request->billingStartDate;
            $policy->ori_billingStartDate = $request->billingStartDate;

            if ($request->activationCode) {
                $activation         = Activation::where('activation_code', $request->activationCode)->first();
                if (isset($Check) &&  $Check != null) {
                    $activation->status = '1';
                    $activation->branch = $request->storeID;
                    $activation->save();
                }

                $Check = CustomerGeneratedActivationCode::where('activation_code', $request->activationCode)->first();
                if (isset($Check) &&  $Check != null) {
                    $Check->status = "1";
                    $Check->save();
                }
                $policy->activation_code = $request->activationCode;
                $policy->serial_code     = $activation->serial_code;
                $policy->isVirtualBox    = $request->isVirtualBox;
                $policy->BillingStart    = $request->BillingStart;
            }
            $saved = $policy->save();

            event(new \AlphaDirect\Events\policyLifecycle($policy->id, "Create"));

            if ($policy->storeID != null && $policy->agentId != null) {
                $this->addAgentActivity($policy->storeID, $policy->agentId);
            }

            if ($product->has_activation_code != 0 && $policy->storeID != null) {
                $check = \Modules\Inventory\Entities\StoresInventory::where('store_id', '=', $policy->storeID)->exists();
                if ($check == true) {
                    $stock = \Modules\Inventory\Entities\StoresInventory::where('store_id', '=', $policy->storeID)->where('product_id', '=', $request->product_id)->where('plan_id', '=', $request->planId)->first(array('counter', 'id'));
                    if ($saved == true && $stock != NULL && $stock->counter != '0') {
                        $quantity = 1;
                        $user = User::where('id', $request->get('agentCode'))->firstOr(function () {
                            return User::where('id', 1)->first();
                        });
                        event(new \Modules\Inventory\Events\DeductStockStore($stock->id, $user, $quantity));
                    }
                }
            }

            if ($saved == true && $policy->agent_id != null) {
                $data = [
                    'id' => $policy->id,
                    'agent_id' => $policy->agent_id,
                    'status' => $policy->status,
                ];
                event(new CommissionPolicyEvent($data));
            }
            //calling event for decrement couneter for specific product, plan and store
            if ($saved == true) {
                $data = [
                    'store_id' => $policy->storeID,
                    'product_id' => $request->product_id,
                    'plan_id' => $request->planId,
                ];
                event(new DecrementCounter($data));
            }

            if ($product->has_vehicle == '1') {
                $vehicle = new Vehicle();
                $vehicle->customer_id = $user->id;
                $vehicle->policy_id = $policy->id;
                $vehicle->purpose = $request->vehicle_purpose;
                $vehicle->vehiclePlate = $request->vehiclePlate;
                $vehicle->is_private = $request->purpose;
                $vehicle->save();
            }

            $mains = FactorMain::where('product_id', $request->product_id)->where('status', 1)->get(array('id', 'name', 'type'));

            foreach ($mains as $main) {

                if (is_array($request->get('factor_' . $main->id))) {
                    foreach ($request->get('factor_' . $main->id) as $key => $value_id) {
                        $factor = new PolicyFactor();
                        $factor->policy_id = $policy->id;
                        $factor->factor_main_id = $main->id;
                        $factor->name = $main->name;
                        $factor->type = $main->type;
                        $value = FactorSubType::where('id', $value_id)->first(array('name', 'factor'));
                        $factor->factor_value_id = $value_id;
                        $factor->value_name = $value->name;
                        $factor->factor = $value->factor;
                        $saved = $factor->save();
                    }
                } else {
                    $factor = new PolicyFactor();
                    $factor->policy_id = $policy->id;
                    $factor->factor_main_id = $main->id;
                    $factor->name = $main->name;
                    $factor->type = $main->type;
                    $value = FactorSubType::where('id', $request->get('factor_' . $main->id))->first(array('name', 'factor'));

                    if ($main->type == 'Input Field') {
                        $factor->value_name = $request->get('factor_' . $main->id);
                    } else {
                        $factor->value_name = $value->name;
                        $factor->factor = $value->factor;
                        $factor->factor_value_id = $request->get('factor_' . $main->id);
                    }
                    $saved = $factor->save();
                }
            }
            if ($request->main != null) {
                for ($i = 0; $i < count($request->main); $i++) {
                    $policyCover                 = new PolicyCoverage();
                    $policyCover->policy_id      = $policy->id;
                    $policyCover->main           = $request->main[$i];
                    $policyCover->coverage_value = $request->cover_value[$i];
                    $policyCover->discount       = $request->type[$i];
                    $policyCover->type           = $request->disccount_type[$i];
                    $policyCover->value          = $request->type_value[$i];
                    $saved = $policyCover->save();
                }
            }

            if ($request->get('members') != null) {

                foreach ($request->get('members') as $key => $member) {
                    if ($member['relation'] != null) {
                        $m             = new PolicyMember();
                        $m->policy_id  = $policy->id;
                        $m->relation   = $member['relation'];
                        $m->first_name = $member['memberFName'];
                        $m->last_name  = $member['memberLName'];
                        $m->dob        = Carbon::parse($member['memberDOB'])->format('Y-m-d');
                        $m->gender     = $member['memberGender'];
                        $m->save();
                    }
                }
            }

            if ($request->get('beneficiaries') != null) {

                foreach ($request->get('beneficiaries') as $key => $beneficiary) {
                    if ($beneficiary['beneficiaryRelation'] != null) {
                        $b              = new PolicyBeneficiary();
                        $b->policy_id   = $policy->id;
                        $b->relation    = $beneficiary['beneficiaryRelation'];
                        $b->first_name  = $beneficiary['beneficiaryFName'];
                        $b->middle_name = isset($beneficiary['beneficiaryMName'])  && ($beneficiary['beneficiaryMName'] != "") ? $beneficiary['beneficiaryMName'] : '';
                        $b->last_name   = $beneficiary['beneficiaryLName'];
                        $b->dob         = isset($beneficiary['beneficiaryDOB']) && ($beneficiary['beneficiaryDOB'] != "") ? date('Y-m-d', strtotime(str_replace('/', '-', $beneficiary['beneficiaryDOB']))) : "";
                        $b->passport    = $beneficiary['beneficiaryPassport'];
                        $b->omang       = $beneficiary['beneficiaryOmang'];
                        $b->gender      = $beneficiary['beneficiaryGender'];
                        $b->payment     = $beneficiary['beneficiaryPayment'];
                        if(isset($beneficiary['under_18_is_allowed'])){
                        $b->under_18_is_allowed = $beneficiary['under_18_is_allowed'][0];
                        }
                        $b->save();
                    }
                }
            }
            if ($product->type == 'Legal') {
                if ($request->maritalStatus == '2') {
                    $b = new PolicyBeneficiary();
                    $b->policy_id = $policy->id;
                    $b->relation = 'Spouse';
                    $b->first_name = isset($request->legalFName) ? $request->legalFName : "";
                    $b->middle_name = isset($request->legalMName) ? $request->legalMName : "";
                    $b->last_name = isset($request->legalLName) ? $request->legalLName : "";
                    $b->cellphone = isset($request->legalPhone) ? $request->legalPhone : "";
                    $b->email = isset($request->legalEmail) ? $request->legalEmail : "";
                    $b->passport = isset($request->legalPassport) ? $request->legalPassport : "";
                    $b->omang = isset($request->legalOmang) ? $request->legalOmang : "";
                    $b->gender = isset($request->legalGender) ? $request->legalGender : "";
                    $b->dob = isset($request->legalDOB) ? $request->legalDOB : "";
                    $b->legalOmangExpiry = isset($request->legalOmangExpiry) ? $request->legalOmangExpiry : "";
                    $b->legalPassportExpiry = isset($request->legalPassportExpiry) ? $request->legalPassportExpiry : "";
                    $b->save();
                }
            }

            if ($product->type == 'Cellphone') {
                if ($request->get('devices') != null) {
                    $sumAssuredCellphone = 0;
                    foreach ($request->get('devices') as $key => $device) {
                        $data = array();
                        $policy_id = $policy->id;
                        $customer_id = $user->id;
                        $device_type = htmlspecialchars(strip_tags($device['device_type']));

                        if (htmlspecialchars(strip_tags($device['imei']))) {
                            $count = PolicyCellPhone::where('imei', htmlspecialchars(strip_tags($device['imei'])))->count();
                            if ($count > 0) {
                                $policyCount = 0;
                                $imeiNos    = PolicyCellPhone::where('imei', htmlspecialchars(strip_tags($device['imei'])))->get();
                                foreach ($imeiNos as $imei) {
                                    $checkStatus = Policy::where('id', $imei->policy_id)->first()->status;
                                    if ($checkStatus != null || $checkStatus != 2) {
                                        $policyCount = $policyCount + 1;
                                        return response()->json(['title' => 'Imei is already exists', 'error' => 'Imei is already exists'], 411);
                                    }
                                }
                            }
                        }
                        $imei = htmlspecialchars(strip_tags($device['imei']));
                        $phone_value = htmlspecialchars(strip_tags($device['phone_value']));

                        $cell_phone_make = htmlspecialchars(strip_tags($device['cell_phone_make']));
                        if ($cell_phone_make == 'other') {
                            $request['type']       = 'cellphone';
                            $request['category']   = 'make';
                            $request['deviceType'] = $device_type;
                            $request['make']       = htmlspecialchars(strip_tags($device['make_other']));
                            $request['callback']   = 'create_policy';
                            $cell_phone_make = htmlspecialchars(strip_tags($device['make_other']));
                            $make_id = $this->addOption($request);
                        } else {
                            $make_id = $cell_phone_make;
                            $make = DeviceMakeModel::where('id', $cell_phone_make)->first(array('name'));
                            if ($make != NULL)
                                $cell_phone_make = $make->name;
                        }

                        $cell_phone_model = htmlspecialchars(strip_tags($device['cell_phone_model']));
                        if ($cell_phone_model == 'other') {
                            $request['type']     = 'cellphone';
                            $request['category'] = 'model';
                            $request['deviceType'] = $device_type;
                            $request['make']   = $make_id; //Make id
                            $request['model']  = htmlspecialchars(strip_tags($device['model_other']));
                            $request['callback']   = 'create_policy';
                            $cell_phone_model = htmlspecialchars(strip_tags($device['model_other']));
                            $this->addOption($request);
                        } else {
                            $model = DeviceMakeModel::where('id', $cell_phone_model)->first(array('name'));
                            if ($model != NULL)
                                $cell_phone_model = $model->name;
                        }

                        $deviceToken = htmlspecialchars(strip_tags($device['deviceToken']));

                        $data = [
                            'policy_id'        => $policy_id,
                            'customer_id'      => $customer_id,
                            'device_type'      => $device_type,
                            'imei'             => $imei,
                            'phone_value'      => $phone_value,
                            'cell_phone_make'  => $cell_phone_make,
                            'cell_phone_model' => $cell_phone_model,
                        ];
                        //return response()->json([$data],401);
                        $policyCellPhone = $this->policy_cell_phone_interface->update_policy_cell_phone_by_id($deviceToken, $data);
                        $sumAssuredCellphone = $sumAssuredCellphone + $phone_value;
                    }
                    $p = Policy::where('id', $policy->id)->first();
                    $p->sum_assured = $sumAssuredCellphone;
                    $p->save();
                }
            }
            if ($request->input('billingOption') == "orangeMoney") {
                $request->billingOption = "Orange USSD";
            }
            $data = [
                'customer_id'   => $user->id,
                'policy_id'     => $policy->id,
                'accountNumber'      => htmlspecialchars(strip_tags($request->input('accountNumber', NULL))),
                'billing'            => htmlspecialchars(strip_tags($request->input('billingOption', NULL))),
                'billingCell'        => htmlspecialchars(strip_tags($request->input('billingCell', NULL))),
                'bankName'           => htmlspecialchars(strip_tags($request->input('bankName', NULL))),
                'branchCode'         => htmlspecialchars(strip_tags($request->input('branchCode', NULL))),
                'accountType'        => htmlspecialchars(strip_tags($request->input('bankAccountType', NULL))),
            ];
            $this->customer_banking_interface->add_new_customer_banking($data);
            // policy create email & sms
            // info verification mail
            $d = new DocumentController();
            $verificationDoc = $d->generateInformationDocument($policy->id);

            if ($verificationDoc != null) {
                $policy->verification_doc = $verificationDoc;
                $saved = $policy->save();
            }

            $smsMessaging = new SmsMessaging;
            $smsMessaging->sendTsosologoSMS(1, $user->cellphone, $policy->policyNumber, $product_plan->slug, '', '', '');

            if ($user->email != null && $policy->save()) {
                $data              = new \stdClass();
                $data->hook        = 'create_policy';
                $data->customer_id = $user->id;
                $data->policy_id   = $policy->id;
                $data->user_id     = null;
                $data->attachment  = null;
                $emailTemplate     = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                $markdown          = new MailTemplate($data);
                $html              = $markdown->render('Mail.mailTemplate', ['data' => $data]);
                event(new \AlphaDirect\Events\SendMail($user->email, $emailTemplate->subject, "", $html, null, ['policyNumber' => $policy->policyNumber, 'hook' => $data->hook]));
                //  Mail::to($user->email)->send(new MailTemplate($data));
            }

            switch ($request->billingOption) {
                case 'VCS':
                    $vcs = new PaymentController;

                    if (isset($request->BillingStart) && $request->BillingStart == "Immediate") {
                        return $vcs->handlePaymentForActivationCodeGenerated($policy->policyNumber, 'MobileApp', $premium, '');
                    }
                    return $vcs->handlePayment($policy->policyNumber, 'MobileApp');
                    break;
                case 'DPO':
                    $dpo = new DpoPaymentController;

                    if (isset($request->BillingStart)) {

                        $request->filter      = 'PolicyNumber';
                        $request->searchValue = $policy->policyNumber;
                        return $dpo->findPolicyForOnlinePayment($request);
                    }
                    break;
                case 'Flutterwave':
                    $rave = new FlutterwaveController;
                    return $rave->handlePayment($policy->policyNumber, $product_plan->slug, $product_plan->flutter_plan_id);
                    break;

                case 'Orange USSD':
                    return response()->json(['status' => 'success', 'url' => 'thankyou-orange', 'policyNumber' => $policy->policyNumber], 200);
                    break;

                case 'orangeMoney':
                    return response()->json(['status' => 'success', 'url' => 'thankyou-orange', 'policyNumber' => $policy->policyNumber], 200);
                    break;

                case 'Orange':
                    $orangeMoney = new OrangeMoneyController();
                    return $orangeMoney->webPayIntiliazer($policy->policyNumber, $premium);
                    break;
                case 'RealPay':
                    return response()->json(['policyNumber' => $policy->policyNumber], 200);
                    break;
                default:
                    return Redirect::back()->with('error', 'Please select a payment vendor')
                        ->withInput($request->all());
            }

            return response()->json(['message' => 'First Time User Policy Created Successfuly'], 200);
        } catch (Exception $ex) {
            return response()->json($ex->getMessage(), 500);
        }
    }

    protected function calculateHospitalCashbackPremium(Request $request)
    {
        $totalPremium = 0;
        $maxChildren = 6;
        $childCount = 0;

        // Assume the form submits multiple co-applicant details as an array
        // $coapplicants = $request->input('data.coapplicants', []);
        $coapplicants = $request->input('coapplicants', $request->input('data.coapplicants', []));
        $planId = $request->input('planId', $request->input('data.planId'));


        // Retrieve the product plan from the database using the provided product_id
        $productPlan = ProductPlan::where('id', $planId)->first('premiumAndRelation'); // Get the product plan based on the submitted product_id

        // // Decode the premiumAndRelation column from JSON to array
        // $premiumAndRelation = [];
        // if (!empty($productPlan->premiumAndRelation)) {
        //     $premiumAndRelation = json_decode($productPlan->premiumAndRelation, true); // Decode as array
        // }


        // Retrieve VAT-inclusive premiumAndRelation using the helper function
        $premiumPayment = new PaymentController();
        $premiumAndRelation = $premiumPayment->getHospitalCashbackPremiumByProductPlan($planId);

        $basePremium = $this->getPremiumByRelationId(1, $premiumAndRelation);
        $totalPremium = (float) $basePremium;

        // Loop through each co-applicant to calculate premium
        foreach ($coapplicants as $coapplicant) {
            // $relationId = (int)$coapplicant['coapplicant_relation'];
            $relationId = isset($coapplicant['coapplicant_relation'])
                ? (int)$coapplicant['coapplicant_relation']
                : ((int)($coapplicant['coapplicantRelation'] ?? 0));

            // Get the premium value for the given relation ID
            $premium = $this->getPremiumByRelationId($relationId, $premiumAndRelation);

            if ($premium !== null) {
                // Handle the "Children" case (relationId == 3)
                if ($relationId === 3) { // Check if the relation is 'child'
                    $childCount++;
                    if ($childCount > $maxChildren) {
                        // Limit the children count to maxChildren
                        $childCount = $maxChildren;
                    }
                }
                $totalPremium += (float) $premium; // Add the premium value to the total premium
            }
        }

        return $totalPremium;
    }


    // Function to get the premium value by relation ID from the premiumAndRelation data
    protected function getPremiumByRelationId($relationId, $premiumAndRelation)
    {
        // Loop through the premiumAndRelation array and match the relation ID
        foreach ($premiumAndRelation as $premiumRelation) {
            if ((int) $premiumRelation['id'] === $relationId) {
                return $premiumRelation['premium']; // Return the premium value for the matched relation ID
            }
        }

        return null; // Return null if the relation ID is not found
    }


    /**
     * Saving policy data from start and redirecting to payment page
     */
    public function activatePolicyFirstTimeUserAlphaFe(Request $request)
    {

        if ($request->data['product_id'] == 9) {
            // Block Hospital Cashback (product 9) for age 65 and above
            // Check request DOB
            if (!empty($request->data['dob'])) {
                try {
                    $dob = Carbon::createFromFormat('d/m/Y', $request->data['dob']);
                    if ($dob->age >= 65) {
                        return response()->json([
                            'error' => 'Age limit exceeded',
                            'Message' => 'Hospital Cashback is only available for customers under 65 years old.'
                        ], 412);
                    }
                } catch (\Exception $e) {
                    // If DOB is invalid, fail fast
                    return response()->json([
                        'error' => 'Invalid Date of Birth',
                        'Message' => 'Please provide a valid date of birth in DD/MM/YYYY format.'
                    ], 412);
                }
            } else {
                return response()->json([
                    'error' => 'Date of Birth required',
                    'Message' => 'Date of birth is required for Hospital Cashback.'
                ], 412);
            }

            // For existing customers, also check customer_profile DOB
            $customer_id = null;
            $customerProfile = null;
            
            // Try to find existing customer
            if (isset($request->data['customer_id']) && !empty($request->data['customer_id'])) {
                $customer_id = $request->data['customer_id'];
            } elseif (isset($request->data['phone']) && !empty($request->data['phone'])) {
                $cellphone = htmlspecialchars(strip_tags($request->data['phone']));
                $customer = Customer::where('cellphone', $cellphone)->orderBy('id', 'asc')->first(['id']);
                if ($customer) {
                    $customer_id = $customer->id;
                }
            }
            
            // If still no customer_id, try using omang or passport
            if ($customer_id === null) {
                $omang = isset($request->data['idType']) && $request->data['idType'] == 'Omang' ? ($request->data['idValue'] ?? null) : null;
                $passport = isset($request->data['idType']) && $request->data['idType'] == 'Passport' ? ($request->data['idValue'] ?? null) : null;
                
                if (!empty($omang)) {
                    $customerProfile = CustomerProfile::where('omang', $omang)->orderBy('id', 'asc')->first(['customer_id', 'dob']);
                } elseif (!empty($passport)) {
                    $customerProfile = CustomerProfile::where('passport', $passport)->orderBy('id', 'asc')->first(['customer_id', 'dob']);
                }
                
                if ($customerProfile && $customerProfile->customer_id) {
                    $customer_id = $customerProfile->customer_id;
                }
            }
            
            // If customer exists, check customer_profile DOB
            if ($customer_id !== null) {
                if ($customerProfile === null) {
                    $customerProfile = CustomerProfile::where('customer_id', $customer_id)->first(['dob']);
                }
                
                if ($customerProfile && !empty($customerProfile->dob)) {
                    try {
                        // Parse customer_profile DOB (stored as Y-m-d in database)
                        $profileDob = Carbon::parse($customerProfile->dob);
                        if ($profileDob->age >= 65) {
                            return response()->json([
                                'error' => 'Age limit exceeded',
                                'Message' => 'Hospital Cashback is only available for customers under 65 years old. The date of birth in your profile indicates age 65 or above.'
                            ], 412);
                        }
                    } catch (\Exception $e) {
                        // If customer_profile DOB is invalid, continue (request DOB already validated)
                    }
                }
            }

            // Retrieve the premium submitted from the frontend (hidden input)
            $submittedPremium = $request->data['premium'];

            // Recalculate premium on the server side based on the submitted form data.
            // This function should mimic the frontend logic.
            $calculatedPremium = $this->calculateHospitalCashbackPremium($request);

            // Compare the recalculated premium with the submitted premium
            if ((int) $submittedPremium !== (int) $calculatedPremium) {
                // Premium mismatch: take appropriate action
                return response()->json(['error' => 'Premium mismatch.', 'Message' => 'Premium mismatch. Please recalculate premium before submitting.'], 412);
            }

            // Validate co-applicants for product_id 9
            if (isset($request->data['coapplicants']) && $request->data['coapplicants'] != null) {
                foreach ($request->data['coapplicants'] as $key => $coapplicant) {
                    // Get relation ID
                    $relationId = isset($coapplicant['coapplicant_relation']) 
                        ? (int)$coapplicant['coapplicant_relation'] 
                        : (isset($coapplicant['coapplicantRelation']) ? (int)$coapplicant['coapplicantRelation'] : null);
                    
                    // Validate DOB if present
                    if (isset($coapplicant['coapplicantDOB']) && !empty($coapplicant['coapplicantDOB'])) {
                        try {
                            // Parse DOB
                            $pos = strpos($coapplicant['coapplicantDOB'], '/');
                            if ($pos !== false) {
                                $coapplicantDob = Carbon::createFromFormat('d/m/Y', $coapplicant['coapplicantDOB']);
                            } else {
                                $coapplicantDob = Carbon::parse($coapplicant['coapplicantDOB']);
                            }
                            
                            // Validate child co-applicants (relation ID 3 = Children) - age cannot be greater than 17
                            if ($relationId === 3) {
                                if ($coapplicantDob->age > 17) {
                                    return response()->json([
                                        'error' => 'Invalid child co-applicant age',
                                        'Message' => 'Child co-applicants cannot be older than 17 years. Please check the date of birth for co-applicant ' . ($key + 1) . '.'
                                    ], 412);
                                }
                            }
                            
                            // Validate adult co-applicants (relation ID 2 = Spouse/Immediate Dependent) - age cannot be 65 or above
                            if ($relationId === 2) {
                                if ($coapplicantDob->age >= 65) {
                                    return response()->json([
                                        'error' => 'Age limit exceeded',
                                        'Message' => 'Hospital Cashback is only available for co-applicants under 65 years old. Please check the date of birth for co-applicant ' . ($key + 1) . '.'
                                    ], 412);
                                }
                            }
                        } catch (\Exception $e) {
                            return response()->json([
                                'error' => 'Invalid Date of Birth',
                                'Message' => 'Please provide a valid date of birth in DD/MM/YYYY format for co-applicant ' . ($key + 1) . '.'
                            ], 412);
                        }
                    }
                }
            }
        }

        if ($request->data['Payment_method'] == 'DPO') {
            if (!isset($request->data['email'])) {
                return response()->json(['title' => 'Email is mandatory', 'description' => 'PLease provide email if payment method is DPO.'], 412);
            }
        }
        try {
            //dd($request->all());
            if (isset($request->data['state']) &&  $request->data['state'] == null) {
                return response()->json(['title' => 'State', 'error' => 'Please select State.'], 409);
            }
            if (isset($request->data['city']) && $request->data['city'] == null) {
                return response()->json(['title' => 'City', 'error' => 'Please select City.'], 409);
            }
            if($request->data['product_id'] == 4){
                if ($request->data['maritalstatus'] == 2) {
                    if ($request->data['legalFName'] == null || $request->data['legalFName'] == '') {
                        return response()->json(['title' => 'SpouseFirstName', 'error' => 'Please Enter Spouse First Name.'], 409);
                    }
                    if ($request->data['legalLName'] == null || $request->data['legalLName'] == '') {
                        return response()->json(['title' => 'SpouseLastName', 'error' => 'Please Enter Spouse Last Name.'], 409);
                    }
                    if ($request->data['legalPhone'] == null || $request->data['legalPhone'] == '') {
                        return response()->json(['title' => 'SpousePhone', 'error' => 'Please Enter Spouse Phone no.'], 409);
                    }
                }
            }
         if($request->data['product_id'] == 5){

              if(!isset($request->data['devices'])){
                         return response()->json(['title' => 'Please add devices first', 'error' => 'Please add devices first.'], 411);
                 }
         }


            if($request->data['product_id'] == 1 || $request->data['product_id'] == 3 || (isset($request->data['productType']) && ($request->data['productType'] == Product::PRODUCT_TYPE_HEALTH))){
                $dateOfBCheck = Carbon::createFromFormat('d/m/Y', $request->data['dob'])->format('Y-m-d');
                $totalYears = Carbon::parse($dateOfBCheck)->age;
                if ($totalYears < 18 ) {
                    return ['success' => false, 'Message' => 'Customer age is less than 18 or more than 65.'];
                }
            }
            if( $request->data['product_id'] == 4 ){
                $dateOfBCheck = Carbon::createFromFormat('d/m/Y', $request->data['dob'])->format('Y-m-d');
                $totalYears = Carbon::parse($dateOfBCheck)->age;
                if ($totalYears < 18 ) {
                    return ['success' => false, 'Message' => 'Customer age is less than 18 .'];
                }
            }

            if (isset($request->data['beneficiaries']) && $request->data['beneficiaries'] != null) {
                $payment = 0;
                foreach ($request->data['beneficiaries']  as $key => $beneficiary) {
                    if ($beneficiary['beneficiaryRelation'] != null) {

                        $payment = $payment + $beneficiary['beneficiaryPayment'];
                    }
                }
                if ($payment > 100) {
                    return response()->json(['title' => 'Beneficiary share exceeded', 'description' => 'Please adjust the beneficiary share not more than 100.'], 412);
                }
            }

            $product = Product::where('id', $request->data['product_id'])->first(array('has_vehicle', 'has_member', 'premium_type_id', 'limit', 'kyc_recipient', 'kyc_customer', 'preinspection', 'is_motor_items', 'region_id', 'has_activation_code', 'type'));
            // for tyre we are adding vehicleplate in reapeter
            if ($product->type != 'Tyre') {
                if ($product->has_vehicle == 1) {
                    if (!isset($request->data['vehiclePlate']) || $request->data['vehiclePlate'] == null) {
                        return response()->json(['title' => 'Motor vehicle plate required', 'error' => 'Please provide valid vehicle plate number'], 409);
                    }
                    $vehicle = $this->checkIfVehicleAlreadyRegistered($request->data['vehiclePlate']);

                    if ($vehicle) {
                        return response()->json(['title' => 'Motor vehicle exists', 'error' => 'You may not register a new policy with an already existing vehicle plate.'], 409);
                    }
                }
            }

            if ($product->has_member == 1) {
                $customerCheck = $this->checkIfMemberAlreadyRegistered($request->data['idType'], $request->data['idValue']);

                if ($customerCheck == true) {
                    return response()->json(['title' => 'Customer already registered for this policy', 'error' => 'Customer is already owner of Accidental death insurace policy. Please check again.'], 400);
                }

                $dateOfBirth = Carbon::createFromFormat('d/m/Y', $request->data['dob'])->format('Y-m-d');;
                $years = Carbon::parse($dateOfBirth)->age;
                if ($years < 18) {
                    return ['success' => false, 'Message' => 'Customer age is less than 18.'];
                }
            }

            //validate for Health in a Box product
            if($product->type == Product::PRODUCT_TYPE_HEALTH)
            {
                $dependents = $request->data['dependents'];
                $adultDependentsCount = collect($dependents)->whereIn('relationship',['spouse','parents','inlaws','extended_family'])->count();
                $childDependentsCount = collect($dependents)->where('relationship','children')->count();
                if($adultDependentsCount > 10)
                {
                    return response()->json(['title'=>"Policy cannot have more than 10 adults as dependents",'error'=>"Policy cannot have more than 10 adults as dependents"]);
                }
                if($childDependentsCount > 10)
                {
                    return response()->json(['title'=>"Policy cannot have more than 10 children as dependents",'error'=>"Policy cannot have more than 10 children as dependents"]);
                }
            }

            if ($product->type == 'Cellphone') {
                if (isset($request->data['devices']) && $request->data['devices'] != null) {
                    foreach ($request->data['devices'] as $key => $device) {

                        if (!isset($device['imei']) || $device['imei'] == null) {
                            return response()->json(['title' => 'Please add devices first', 'error' => 'Please add devices first.'], 411);
                        }
                    }
                }
            }
            $omang = $request->data['idType'] == 'Omang' ? $request->data['idValue'] : null;
            $passport = $request->data['idType'] == 'Passport' ? $request->data['idValue'] : null;
            $cellphone = htmlspecialchars(strip_tags($request->data['phone']));
            $profile = null;
            if ($cellphone != null) {
                $profile = Customer::where('cellphone', $cellphone)->orderBy('id', 'asc')->first(array('id', 'is_blocked'));
            }
            // is blocked
            if ($profile && $profile->is_blocked != null) {
                if ($profile->is_blocked == 1) {
                    return response()->json(['success' => false, 'message' => 'customer is blocked'], 200);
                }
            }

            if ($profile != null) {
                $customer_id = $profile->id;

                if ($request->data['product_id'] == 1) {
                    $row = Policy::where('customer_id',$customer_id)->where('product_id',$request->data['product_id'])->where('status', "!=", 2)->count();
                    if ($row > 0) {
                        return response()->json(['title' => 'Policy exists', 'description' => 'The customer already has purchased the specified product'], 406);
                    }
                }
                //Legal Products
                if ($request->data['product_id'] == 4) {
                    $row = Policy::where('customer_id',$customer_id)->where('product_id',$request->data['product_id'])->where('status', "!=", 2)->count();
                    if ($row > 0) {
                        return response()->json(['title' => 'Policy exists', 'description' => 'The customer already has purchased the specified product'], 406);
                    }
                }
                //Hospital Cashback - Product ID 9
                if ($request->data['product_id'] == 9) {
                    $row = Policy::where('customer_id',$customer_id)->where('product_id',$request->data['product_id'])->where('status', "!=", 2)->count();
                    if ($row > 0) {
                        return response()->json(['error' => 'Policy exists', 'Message' => 'The customer already has a Hospital Cashback policy. Only one policy per customer is allowed for this product.'], 406);
                    }
                }
            }

            $customer_id = null;
            //check for omang and passport
            if ($profile == null) {
                if ($omang != null && $passport != null) {
                    $profile = CustomerProfile::where('omang', $omang)->orWhere('passport', $passport)->orderBy('id', 'asc')->first(array('customer_id'));
                } elseif ($omang == null && $passport != null) {
                    $profile = CustomerProfile::where('passport', $passport)->orderBy('id', 'asc')->first(array('customer_id'));
                } elseif ($omang != null && $passport == null) {
                    $profile = CustomerProfile::where('omang', $omang)->orderBy('id', 'asc')->first(array('customer_id'));
                }else{
                    $profile = new CustomerProfile();
                    $cust = Customer::where('cellphone', $cellphone)->orderBy('id', 'asc')->first();
                    $profile->customer_id = $cust->id;
                    $profile->save();
                }
                if ($profile != null) {
                    $customer_id = $profile->customer_id;
                    
                    //Hospital Cashback - Product ID 9 - Check for existing policy
                    if ($request->data['product_id'] == 9) {
                        $row = Policy::where('customer_id',$customer_id)->where('product_id',$request->data['product_id'])->where('status', "!=", 2)->count();
                        if ($row > 0) {
                            return response()->json(['error' => 'Policy exists', 'Message' => 'The customer already has a Hospital Cashback policy. Only one policy per customer is allowed for this product.'], 406);
                        }
                    }
                    
                    if (isset($request->data['sourceOfIncome'])) {

                            $profile->sourceOfIncome = json_encode($request->data['sourceOfIncome']);


                    }
                }
            }

              // Enable Mati
              $mati_config = Config::where('key','enable_mati')->first(array('id','value'));
              $mati_enable = $mati_config->value;

            if ($request->data['customerExists'] == 1 || $profile != null) {
                $AdminCustomerController = new AdminCustomerController();
                $policyInvalid = $AdminCustomerController->PolicyBusinessValidations($customer_id, $request->data['product_id']);
                if($policyInvalid)
                {
                    return response()->json(['policy_exists' => 1, 'title' => 'Policy exists', 'message' => 'The customer already has purchased the specified product'], 403);
                }

                if($mati_enable == 0){
                    $data_mati_id  = 0;
                }else{
                    $data_mati_id  = $request->data['mati-identityId'];
                }
                if(isset($profile) && $profile->customer_id != null){
                    $user = Customer::where('id', $profile->customer_id)->orderBy('id', 'asc')->first();
                }else{
                    $user = Customer::where('cellphone', $cellphone)->orderBy('id', 'asc')->first();
                }

                if(isset($data_mati_id)) {
                    $user->mati_identity = $data_mati_id;
                }
                $user->save();
                $profile_update = CustomerProfile::where('customer_id', $user->id)->first();
                if(!$profile_update){
                    $profile_update = new CustomerProfile();
                    $profile_update->customer_id = $user->id;
                }

                if ($product->type == 'Legal' && isset($profile_update)) {
                    $profile_update->e_name          = $request->data['e_name'];
                    $profile_update->emp_no         = $request->data['emp_no'];
                    $profile_update->emp_phone        = $request->data['emp_phone'];
                    $profile_update->salary_pay_date = date('Y-m-d', strtotime(str_replace('/', '-', $request->data['salary_pay_date'])));
                   // $profile_update->save();
                }

                if(isset($request->data['product_id']) && ($request->data['product_id'] == 1 || $request->data['product_id'] == 4 ) && isset($profile_update)){
                    if(isset($request->data['dob'])){
                        $dateOfBCheck        = date('Y-m-d', strtotime(str_replace('/', '-', $request->data['dob'])));
                        $profile_update->dob = $dateOfBCheck;
                    }


                }

                if (isset($request->data['sourceOfIncome']) && $request->data['sourceOfIncome'] != null && isset($profile_update)) {
                    if($request->data['sourceOfIncome'] == 'unemployed'){
                        $profile_update->sourceOfIncome = '"unemployed"';
                    }else{
                        $profile_update->sourceOfIncome = json_encode($request->data['sourceOfIncome']);
                    }
                }
                if(isset($profile_update)){
                    $profile_update->save();
                }

                $customerKYC = KYC::where('customer_id', $user->id)->first();
            } else {

                 if($mati_enable == 0){
                     $data_mati_id  = 0;
                 }else{
                     $data_mati_id  = $request->data['mati-identityId'];
                 }

                $user             = new Customer();
                $user->firstName  = $request->data['firstname'];
                $user->middlename = $request->data['middlename'];
                $user->lastName   = $request->data['lastname'];
                $user->email      = $request->data['email'];
                $user->cellphone  = $request->data['phone'];

                if(isset($data_mati_id)) {
                    $user->mati_identity = $data_mati_id;
                } else {
                    if($request->data['agent_id'] == null){
                        if (!isset($request->data['omangDocumentKyc']) && !isset($request->data['passportDocumentKyc'])) {
                            return response()->json(['title' => 'KYC is Mandatory', 'error' => 'Please Complete Mati Verification'], 411);
                        }
                    }
                }
                $user->save();
                // user create mail sms
                // for Password Reset
                $token                  = Str::random(8);
                $user_password          = new UserPassword();
                $user_password->user_id = $user->id;
                $user_password->token   = $token;
                $url                    = env('LIVEQUOTE_URL') . 'reset_password_first_time.php?token=' . $token;
                $user_password->url     = $url;
                $user_password->save();

                $sms = new SmsMessaging();
                $sms->sendSmsUserCreate(29, $user->firstName, $user->lastName, $user->cellphone, $url);

                if ($user->email != null) {
                    $data = new \stdClass();
                    $data->user_id = null;
                    $data->customer_id = $user->id;
                    $data->new_user_password_url_id = $user_password->id;
                    $data->hook = 'user_create';
                    $data->attachment = null;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate', ['data' => $data]);
                    event(new \AlphaDirect\Events\SendMail($user->email, $emailTemplate->subject, "", $html, null, ['hook' => $data->hook]));
                    //   mail::to($user->email)->send(new MailTemplate($data));
                    // return response()->json(['success' => 1, 'mail' => $mail], 200);
                }

                $ifExists = CustomerProfile::where('customer_id',$user->id)->exists();
                if(!empty($ifExists))
                {
                    $profile = CustomerProfile::where('customer_id',$user->id)->first();
                }else{
                    /*Add Records to Customers Profile table*/
                    $profile = new CustomerProfile();
                }
                $profile->customer_id     = $user->id;
                if ($product->type == 'Legal') {
                    $profile->e_name         = isset($request->data['e_name']) ? $request->data['e_name'] : "";
                    $profile->emp_no         = isset($request->data['emp_no']) ? $request->data['emp_no'] : "";
                    $profile->emp_phone      = isset($request->data['emp_phone']) ? $request->data['emp_phone'] : "";
                    $salary_date             = date('Y-m-d', strtotime(str_replace('/', '-', $request->data['salary_pay_date'])));
                    $profile->salary_pay_date = isset($salary_date) ? $salary_date : "";
                }
                $profile->gender      = $request->data['gender'];
                $profile->address     = $request->data['address'];
                $profile->city        = isset($request->data['city']) &&  $request->data['city'] != '' ? $request->data['city'] : "";
                $profile->state       = $request->data['state'];
                $profile->omang       = $request->data['idType'] == 'Omang' ? $request->data['idValue'] : "";
                $profile->passport    = $request->data['idType'] == 'Passport' ? $request->data['idValue'] : "";
                $profile->countryId   = $request->data['passportIssuingCountry'];
                $profile->maritalstatus = isset($request->data['maritalstatus']) && $request->data['maritalstatus'] != '' ? $request->data['maritalstatus'] : ""; //key changed as per request of abhijeet marital => maritalstatus
                if (!Policy::where('customer_id', $user->id)
                                            ->where('product_id', 1)
                                            ->where('status', '!=', 2)
                                            ->exists()) {
                    $profile->dob         = date('Y-m-d', strtotime(str_replace('/', '-', $request->data['dob'])));
                }
                $profile->driving_license_number = isset($request->data['dLicense']) ? $request->data['dLicense'] : "";
                if (isset($request->data['license_valid_till'])) {
                    $profile->license_valid_till = date('Y-m-d', strtotime(str_replace('/', '-', $request->data['license_valid_till'])));
                }
                if (isset($request->data['sourceOfIncome'])) {
                    $profile->sourceOfIncome = json_encode($request->data['sourceOfIncome']);
                }
                $profile->save();
            }

            $customerKYC = KYC::where('customer_id', $user->id)->first();
            if ($customerKYC == null) {
                $customerKYC = new KYC();
            }
            $customerKYC->customer_id = $user->id;

            //save the policy
            //Latestid for Policy Number
            $latest = Policy::orderBy('id', 'desc')->first(array('id'));
            if ($latest == null) {
                $latest = collect();
                $latest->id = 1;
            }

            $omangFront  = isset($request->data['omangKyc']) ? htmlspecialchars(strip_tags($request->data['omangKyc'])) : NULL;
            $omangBack   = isset($request->data['omangbackKyc']) ? htmlspecialchars(strip_tags($request->data['omangbackKyc'])) : NULL;
            $passportKyc = isset($request->data['passportKyc']) ? htmlspecialchars(strip_tags($request->data['passportKyc'])) : NULL;

            if (isset($request->data['idType']) && $request->data['idType'] == 'Omang' && isset($request->data['omangDocumentKyc'])) {
                $customerKYC->omang = $request->data['omangDocumentKyc'];
            }

            if (isset($request->data['idType']) && $request->data['idType'] == 'Passport' && isset($request->data['passportDocumentKyc'])) {
                $customerKYC->passport = $request->data['passportDocumentKyc'];
            }

            if ($omangFront != NULL) {
                $fileName = explode('/', $omangFront);
                $name = array_pop($fileName);
                $filePath = 'MIS/' . $customerKYC->customer_id . '/' . 'Customer' . '/omang' . '/' . $name;
                Storage::disk('s3')->move($omangFront, $filePath);
                $customerKYC->omang = $filePath;
            }
            if ($omangBack != NULL) {
                $fileName = explode('/', $omangBack);
                $name = array_pop($fileName);
                $filePath = 'MIS/' . $customerKYC->customer_id . '/' . 'Customer' . '/omang_back' . '/' . $name;
                Storage::disk('s3')->move($omangBack, $filePath);
                $customerKYC->omangBack = $filePath;
            }

            if ($passportKyc != NULL) {
                $fileName = explode('/', $passportKyc);
                $name = array_pop($fileName);
                $filePath = 'MIS/' . $customerKYC->customer_id . '/' . 'Customer' . '/passport' . '/' . $name;
                Storage::disk('s3')->move($passportKyc, $filePath);
                $customerKYC->passport = $filePath;
            }
            $customerKYC->save();

            $policy = new Policy();
            $policy->customer_id = $user->id;
            $policy->agent_id = $request->data['agent_id'] != "" ? $request->data['agent_id'] : "0";
            if(isset($request->data['agent_id']) && $request->data['agent_id'] != "" ){
                $agent = \AlphaDirect\User::where('id',$request->data['agent_id'])->where('agency_id','!=',null)->first(['agency_id']);
                if($agent){
                     $policy->agency_id = $agent->agency_id;
                }
            }
            $policy->storeID = isset($request->data['store']) ? $request->data['store'] : '';

            $policy->product_id = $request->data['product_id'];
            $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
            $product_plan = Productplan::where('id', $request->data['planId'])->first(array('sum_assured', 'premium', 'slug', 'flutter_plan_id'));
            if ($product->premium_type_id == 11) {
                $premium = round(($product_plan->premium * ($regionVat / 100)) + $product_plan->premium, 2);
                $policy->plan_id = $request->data['planId'];
                $policy->sum_assured = $product_plan->sum_assured;
                $policy->premium = round($premium, 2);
                $policy->vat = round($product_plan->premium * ($regionVat / 100), 2);
                $policy->vat_percent = $regionVat;
            } else {
                $product_plan = Productplan::where('id', $request->data['planId'])->first(array('sum_assured', 'premium', 'slug', 'flutter_plan_id'));
                if ($request->data['product_id'] == 9) {
                    $premium = $calculatedPremium;
                    $policy->premium = $premium;
                    $policy->vat = $calculatedPremium * ($regionVat / 100);
                } else {
                    $premium = isset($request->data['premium'])? ($request->data['premium'] * ($regionVat / 100)) + $request->data['premium'] : 0;
                    $policy->premium = $premium;
                    $policy->vat = isset($request->data['premium'])? $request->data['premium'] * ($regionVat / 100) :0;
                }

                $policy->sum_assured = isset($request->data['sum_assured']) ? $request->data['sum_assured'] : null;
                $policy->plan_id = isset($request->data['planId']) ? $request->data['planId'] : null;

                //calculate premium for Health in a Box product
                if($product->type == Product::PRODUCT_TYPE_HEALTH)
                {
                    $dependents = $request->data['dependents'];
                    $productPlan = Productplan::where('id',$request->data['planId'])->first();
                    $premiumMeta = json_decode($productPlan->premiumAndRelation,true);
                    $premiumBreakUp = $this->calculateHibPremium($dependents,$productPlan,$premiumMeta,1);
                    //Log::debug("Calculating HIB Premium",$premiumBreakUp);
                    $premiumWithVat = $premiumBreakUp['total_premium'];
                    $policy->plan_id = $request->data['planId'];
                    $policy->sum_assured = $productPlan->sum_assured;
                    $policy->premium = $premiumWithVat;
                    $policy->vat = $premiumBreakUp['total_vat_amount'];
                    $policy->vat_percent = $regionVat;
                }
            }

            // $policyNumberPrefix = ($request->data['productType'] == Product::PRODUCT_TYPE_HEALTH)? 'MIH' : 'MIS';
            $policyNumberPrefix = (!empty($request->data['productType']) && $request->data['productType'] == Product::PRODUCT_TYPE_HEALTH) ? 'MIH' : 'MIS';
            $policy->policyNumber         = $policyNumberPrefix . Carbon::now()->year . str_pad(($latest->id + 1), 6, '0', STR_PAD_LEFT);
            $policy->status               = 0;
            $policy->leadSource           = "start.alphadirect.co.bw";
            $policy->has_vehicle          = $product->has_vehicle;
            $policy->has_member           = $product->has_member;
            $policy->preinspection        = $product->preinspection;
            $policy->is_motor_items       = $product->is_motor_items;
            $policy->limit                = $product->limit;
            $policy->kyc_customer         = $product->kyc_customer;
            $policy->kyc_recipient        = $product->kyc_recipient;
            $policy->billingStartDate     = date('Y-m-d', strtotime(str_replace('/', '-', $request->data['billing_date'])));
            $policy->ori_billingStartDate     = date('Y-m-d', strtotime(str_replace('/', '-', $request->data['billing_date'])));
            $policy->BillingStart = isset($request->data['BillingStart']) ? $request->data['BillingStart'] : null;
            $policy->is_sys_act_generated = $request->data['GENERATED_ACTIVATION_CODE'];
            $policy->billing_day = isset($request->data['billing_date']) ? \Carbon\Carbon::createFromFormat('d/m/Y', $request->data['billing_date'])->format('d') :null;

            if ($request->data['product_id'] != 3) {
                $policy->BillingStart = isset($request->data['BillingStart']) ? $request->data['BillingStart'] : '';
                $policy->isVirtualBox = isset($request->data['isVirtualBox']) ? $request->data['isVirtualBox'] : '';
            }
            if ($request->data['activationCode']) {
                $activation         = Activation::where('activation_code', $request->data['activationCode'])->first();
                $activation->status = '1';
                $activation->branch = '0';
                $activation->save();

                $Check = CustomerGeneratedActivationCode::where('activation_code', $request->data['activationCode'])->first();
                if (isset($Check) &&  $Check != null) {
                    $Check->status = "1";
                    $Check->save();
                }

                $policy->activation_code = $request->data['activationCode'];
                $policy->serial_code     = $activation->serial_code;
                $policy->isVirtualBox    = $request->data['isVirtualBox'];
                $policy->BillingStart    = isset($request->data['BillingStart']) ? $request->data['BillingStart'] : null;
            }

            if ($request->data['Payment_method'] == 'N-Genius') {
                $policy->BillingStart = "Immediate" ;
                $policy->isVirtualBox = null;
            }

            $saved = $policy->save();

            event(new \AlphaDirect\Events\policyLifecycle($policy->id, "Create"));
            if ($product->has_activation_code != 0 && $policy->storeID != null) {
                $check = \Modules\Inventory\Entities\StoresInventory::where('store_id', '=', $policy->storeID)->exists();
                if ($check == true) {
                    $stock = \Modules\Inventory\Entities\StoresInventory::where('store_id', '=', $policy->storeID)->where('product_id', '=', $request->data['product_id'])->where('plan_id', '=', $request->data['planId'])->first(array('counter', 'id'));
                    if ($saved == true) {
                        $quantity = 1;
                        $agent = User::where('id', $policy->agent_id)->firstOr(function () {
                            return User::where('id', 1)->first();
                        });
                       // event(new \Modules\Inventory\Events\DeductStockStore($stock->id, $user, $quantity));
                    }
                }
            }

            if ($policy->storeID != null && $policy->agentId != null) {
                $this->addAgentActivity($policy->storeID, $policy->agentId);
            }
            if ($saved == true && $policy->agent_id != null) {
                $data = [
                    'id' => $policy->id,
                    'agent_id' => $policy->agent_id,
                    'status' => $policy->status,
                ];
                event(new CommissionPolicyEvent($data));
            }

            if ($saved == true) {
                $data = [
                    'store_id' => $policy->storeID,
                    'product_id' => $policy->product_id,
                    'plan_id' => $policy->plan_id,
                ];
                event(new DecrementCounter($data));
            }

            if ($product->type != 'Tyre') {
                if ($product->has_vehicle == '1') {
                    $vehicle = new Vehicle();
                    $vehicle->customer_id = $user->id;
                    $vehicle->policy_id = $policy->id;
                    $vehicle->vehiclePlate = $request->data['vehiclePlate'];
                    $vehicle->is_private = $request->purpose;
                    $vehicle->save();
                }
            }

            //tyre & rim policy
            if ($product->type == 'Tyre') {
                if (isset($request->data['vehicles']) && $request->data['vehicles'] != null) {
                    foreach ($request->data['vehicles'] as $key => $vehicle) {
                        // save vehicle data to vehicle table
                        $v = new vehicle();
                        $v->customer_id = $user->id;
                        $v->policy_id = $policy->id;
                        if(($vehicle['is_imported'])=='Yes') {
                           $is_imported=1;
                        }elseif(($vehicle['is_imported'])=='No'){
                            $is_imported=0 ;
                        }else{
                            $is_imported=NULL ;
                        }
                        $v->is_imported = $is_imported ;
                        $v->make = $vehicle['make'];
                        $v->year = $vehicle['year'];
                        $v->model = $vehicle['model'];
                        $v->purpose = $vehicle['purpose'];
                        $v->vehiclePlate = $vehicle['tyreVehiclePlate'];
                        if ($vehicle['invoice_tyre'] != NULL) {
                            $fileName = explode('/', $vehicle['invoice_tyre']);
                            $name = array_pop($fileName);
                            $filePath = 'MIS/' . $user->id . '/' . 'Tyre' . '/tyre_invoice' . '/' . $name;
                            Storage::disk('s3')->move($vehicle['invoice_tyre'], $filePath);
                            $v->tyre_invoice = $filePath;
                        }

                        if ($vehicle['km_of_car'] != NULL) {
                            $fileName = explode('/', $vehicle['km_of_car']);
                            $name = array_pop($fileName);
                            $filePath = 'MIS/'.$user->id.'/'.'Tyre'.'/km_of_car'.'/'.$name;
                            Storage:: disk('s3')->move($vehicle['km_of_car'], $filePath);
                            $v->km_of_car = $filePath;
                        }
                        $v->save();
                        // save tyres and rims data to policy_tyre_rim table
                        if (isset($vehicle['number_of_insure'])) {
                            $noOfInsure = $vehicle['number_of_insure'];
                            for ($i = 0; $i<$noOfInsure; $i++) {
                                $tyreImg = new PolicyTyreRim();
                                $tyreImg->customer_id = $user->id;
                                $tyreImg->policy_id = $policy->id;
                                $tyreImg->vehicle_id = $v->id;
                                $tyreImg->position = $vehicle['tyre_position'][$i];
                                $tyreImg->tyre_insure = $vehicle['tyre_insure'][$i];
                                if ($vehicle['tyre_image'][$i] != NULL) {
                                    $fileName = explode('/', $vehicle['tyre_image'][$i]);
                                    $name = array_pop($fileName);
                                    $filePath = 'MIS/' . $user->id . '/' . 'Tyre' . '/tyre_image' . '/' . $name;
                                    Storage::disk('s3')->move($vehicle['tyre_image'][$i], $filePath);
                                    $tyreImg->image = $filePath;
                                }
                                $tyreImg->size = $vehicle['tyre_size'][$i];
                                $tyreImg->value = $vehicle['tyre_value'][$i];
                                $tyreImg->dot_number = $vehicle['tyre_dot'][$i];
                                $tyreImg->barcode = $vehicle['tyre_barcode'][$i];
                                $tyreImg->created_at = Carbon::now();
                                $tyreImg->save();
                            }
                        }

                    }
                }
            }

            if (isset($request->data['main']) && $request->data['main'] != null) {
                $mains = FactorMain::where('product_id', $request->product_id)->where('status', 1)->get(array('id', 'name', 'type'));

                foreach ($mains as $main) {
                    if (is_array($request->data['factor_' . $main->id])) {
                        foreach ($request->data['factor_' . $main->id] as $key => $value_id) {
                            $factor = new PolicyFactor();
                            $factor->policy_id = $policy->id;
                            $factor->factor_main_id = $main->id;
                            $factor->name = $main->name;
                            $factor->type = $main->type;
                            $value = FactorSubType::where('id', $value_id)->first(array('name', 'factor'));
                            $factor->factor_value_id = $value_id;
                            $factor->value_name = $value->name;
                            $factor->factor = $value->factor;
                            $saved = $factor->save();
                        }
                    } else {
                        $factor = new PolicyFactor();
                        $factor->policy_id = $policy->id;
                        $factor->factor_main_id = $main->id;
                        $factor->name = $main->name;
                        $factor->type = $main->type;
                        $value = FactorSubType::where('id', $request->data['factor_' . $main->id])->first(array('name', 'factor'));

                        if ($main->type == 'Input Field') {
                            $factor->value_name = $request->data['factor_' . $main->id];
                        } else {
                            $factor->value_name = $value->name;
                            $factor->factor = $value->factor;
                            $factor->factor_value_id = $request->data['factor_' . $main->id];
                        }
                        $saved = $factor->save();
                    }
                }
                if ($request->data['main'] != null) {
                    for ($i = 0; $i < count($request->data['main']); $i++) {
                        $policyCover = new PolicyCoverage();
                        $policyCover->policy_id = $policy->id;
                        $policyCover->main = $request->data['main'][$i];
                        $policyCover->coverage_value = $request->data['cover_value'][$i];
                        $policyCover->discount = $request->data['type'][$i];
                        $policyCover->type = $request->data['disccount_type'][$i];
                        $policyCover->value = $request->data['type_value'][$i];
                        $saved = $policyCover->save();
                    }
                }
            }

            if (isset($request->data['members']) && $request->data['members'] != null) {

                foreach ($request->data['members']  as $key => $member) {
                    if ($member['relation'] != null) {
                        $m = new PolicyMember();
                        $m->policy_id = $policy->id;
                        $m->relation = $member['relation'];
                        $m->first_name = $member['memberFName'];
                        $m->last_name = $member['memberLName'];
                        $m->dob = date('Y-m-d', strtotime(str_replace('/', '-', $member['memberDOB'])));
                        $m->gender = $member['memberGender'];
                        $m->save();
                    }
                }
            }

            if (isset($request->data['coapplicants']) && $request->data['coapplicants'] != null) {

                foreach ($request->data['coapplicants'] as $key => $coapplicant) {
                    if ($coapplicant['coapplicant_relation'] != null) {
                        $hospitalCashback = new HospitalCashbackCoapplicants();
                        $hospitalCashback->policy_id = $policy->id;
                        $hospitalCashback->relation = htmlspecialchars(strip_tags($coapplicant['coapplicant_relation']));
                        $hospitalCashback->first_name = htmlspecialchars(strip_tags($coapplicant['coapplicantFName']));
                        $hospitalCashback->last_name = htmlspecialchars(strip_tags($coapplicant['coapplicantLName']));
                        $hospitalCashback->passport = htmlspecialchars(strip_tags($coapplicant['coapplicantPassport']));
                        $hospitalCashback->omang = htmlspecialchars(strip_tags($coapplicant['coapplicantOmang']));
                        $hospitalCashback->gender = htmlspecialchars(strip_tags($coapplicant['coapplicantGender']));
                        // $hospitalCashback->payment = htmlspecialchars(strip_tags($beneficiary['beneficiaryPayment']));
                        // if(isset($coapplicant['under_18_is_allowed'])){
                        //     $hospitalCashback->under_18_is_allowed = $coapplicant['under_18_is_allowed'][0];
                        // }

                        //$hospitalCashback->dob = Carbon::parse($beneficiary['beneficiaryDOB'])->format('Y-m-d');
                        if($coapplicant['coapplicantDOB'] != null){
                            $pos = strpos($coapplicant['coapplicantDOB'], '/');
                            if ($pos !== false) {
                                $hospitalCashback->dob = Carbon::createFromFormat('d/m/Y',$coapplicant['coapplicantDOB'])->format('Y-m-d');
                            } else {
                                $hospitalCashback->dob = Carbon::parse($coapplicant['coapplicantDOB'])->format('Y-m-d');
                            }
                        }

                        $hospitalCashback->save();
                    }
                }
            }

            if (isset($request->data['beneficiaries']) && $request->data['beneficiaries'] != null) {

                foreach ($request->data['beneficiaries'] as $key => $beneficiary) {
                    if ($beneficiary['beneficiaryRelation'] != null) {
                        $b = new PolicyBeneficiary();
                        $b->policy_id = $policy->id;
                        $b->relation = htmlspecialchars(strip_tags($beneficiary['beneficiaryRelation']));
                        $b->first_name = htmlspecialchars(strip_tags($beneficiary['beneficiaryFName']));
                        $b->last_name = htmlspecialchars(strip_tags($beneficiary['beneficiaryLName']));
                        $b->passport = htmlspecialchars(strip_tags($beneficiary['beneficiaryPassport']));
                        $b->omang = htmlspecialchars(strip_tags($beneficiary['beneficiaryOmang']));
                        $b->gender = htmlspecialchars(strip_tags($beneficiary['beneficiaryGender']));
                        $b->payment = htmlspecialchars(strip_tags($beneficiary['beneficiaryPayment']));
                        if(isset($beneficiary['under_18_is_allowed'])){
                            $b->under_18_is_allowed = $beneficiary['under_18_is_allowed'][0];
                        }

                        //$b->dob = Carbon::parse($beneficiary['beneficiaryDOB'])->format('Y-m-d');
                        if($beneficiary['beneficiaryDOB'] != null){
                            $pos = strpos($beneficiary['beneficiaryDOB'], '/');
                            if ($pos !== false) {
                                $b->dob = Carbon::createFromFormat('d/m/Y',$beneficiary['beneficiaryDOB'])->format('Y-m-d');
                            } else {
                                $b->dob = Carbon::parse($beneficiary['beneficiaryDOB'])->format('Y-m-d');
                            }
                        }

                        $b->save();
                    }
                }
            }


            if (isset($request->data['ad_beneficiaries']) && $request->data['ad_beneficiaries'] != null) {

                foreach ($request->data['ad_beneficiaries'] as $key => $adBeneficiary) {
                    $b = new ADGroupedBeneficiary();
                    $b->policy_id = $policy->id;
                    // $b->relation = htmlspecialchars(strip_tags($beneficiary['beneficiaryRelation']));
                    $b->first_name = htmlspecialchars(strip_tags($adBeneficiary['adBeneficiaryFName']));
                    $b->last_name = htmlspecialchars(strip_tags($adBeneficiary['adBeneficiaryLName']));
                    $b->middle_name = htmlspecialchars(strip_tags($adBeneficiary['adBeneficiaryMName']));
                    $b->passport = htmlspecialchars(strip_tags($adBeneficiary['adBeneficiaryPassport']));
                    $b->omang = htmlspecialchars(strip_tags($adBeneficiary['adBeneficiaryOmang']));
                    $b->gender = htmlspecialchars(strip_tags($adBeneficiary['adBeneficiaryGender']));
                    // $b->payment = htmlspecialchars(strip_tags($beneficiary['beneficiaryPayment']));
                    // if(isset($beneficiary['under_18_is_allowed'])){
                    //     $b->under_18_is_allowed = $beneficiary['under_18_is_allowed'][0];
                    // }

                    //$b->dob = Carbon::parse($beneficiary['beneficiaryDOB'])->format('Y-m-d');
                    if($adBeneficiary['adBeneficiaryDOB'] != null){
                        $pos = strpos($adBeneficiary['adBeneficiaryDOB'], '/');
                        if ($pos !== false) {
                            $b->dob = Carbon::createFromFormat('d/m/Y',$adBeneficiary['adBeneficiaryDOB'])->format('Y-m-d');
                        } else {
                            $b->dob = Carbon::parse($adBeneficiary['adBeneficiaryDOB'])->format('Y-m-d');
                        }
                    }

                    $b->save();
                }
            }

            if (isset($request->data['dependents']) && $request->data['dependents'] != null) {
                //add policyholder data also to covered persons to track premium information
                $coveredPersons = $request->data['dependents'];
                $self = [
                    'dependent_relationship' => 0,//self (policyholder)
                    'dependent_FName' => $user->firstName,
                    'dependent_LName' => $user->lastName,
                    'dependent_passport' => $user->profile->passport,
                    'dependent_omang' => $user->profile->omang,
                    'dependent_gender' => $user->profile->gender,
                    'dependent_dob' => $user->profile->dob
                ];
                $coveredPersons[] = $self;
                //get premium breakup for covered persons to save
                $premiumPayment = new PaymentController();
                $productPlan = Productplan::where('id',$request->data['planId'])->first();
                $premiumMeta = json_decode($productPlan->premiumAndRelation,true);
                $premiumBreakUp = $premiumPayment->getHibPremiumByProductPlan($request->data['dependents'],$productPlan,$premiumMeta,1);
                foreach ($coveredPersons as $key => $coveredPerson) {
                    if (isset($coveredPerson['dependent_relationship'])) {
                        $isSelf = ($coveredPerson['dependent_relationship'] == 0)? 1 : 0;
                        $b = new PolicyCoveredPerson();
                        $b->policy_id = $policy->id;
                        $b->is_self = ($isSelf)? 1 : 0;
                        $b->is_dependent = (!$isSelf)? 1 : 0;
                        $b->relation = htmlspecialchars(strip_tags($coveredPerson['dependent_relationship']));
                        $b->first_name = htmlspecialchars(strip_tags($coveredPerson['dependent_FName']));
                        $b->last_name = htmlspecialchars(strip_tags($coveredPerson['dependent_LName']));
                        $b->passport = htmlspecialchars(strip_tags($coveredPerson['dependent_passport']));
                        $b->omang = htmlspecialchars(strip_tags($coveredPerson['dependent_omang']));
                        $b->gender = htmlspecialchars(strip_tags($coveredPerson['dependent_gender']));
                        if($coveredPerson['dependent_dob'] != null){
                            $pos = strpos($coveredPerson['dependent_dob'], '/');
                            if ($pos !== false) {
                                $b->dob = Carbon::createFromFormat('d/m/Y',$coveredPerson['dependent_dob'])->format('Y-m-d');
                            } else {
                                $b->dob = Carbon::parse($coveredPerson['dependent_dob'])->format('Y-m-d');
                            }
                        }
                        $premiumData = array_values(array_filter($premiumBreakUp['premium_break_up'],function($item) use($coveredPerson){
                            return $item['relation'] == $coveredPerson['dependent_relationship'];
                        }));
                        $b->premium_without_vat = $premiumData[0]['premium_without_vat'];
                        $b->vat_amount = $premiumData[0]['vat_amount'];
                        $b->vat_percent = $premiumData[0]['vat_percent'];
                        $b->save();
                    }
                }
            }

            if ($product->type == 'Legal') {

                if ($request->data['maritalstatus'] == '2') {
                    $b = new PolicyBeneficiary();
                    $b->policy_id = $policy->id;
                    $b->relation = 'Spouse';
                    $b->first_name = isset($request->data['legalFName']) ? $request->data['legalFName'] : "";
                    $b->middle_name = isset($request->data['legalMName']) ? $request->data['legalMName'] : "";
                    $b->last_name = isset($request->data['legalLName']) ? $request->data['legalLName'] : "";
                    $b->cellphone = isset($request->data['legalPhone']) ? $request->data['legalPhone'] : "";
                    $b->email = isset($request->data['legalEmail']) ? $request->data['legalEmail'] : "";
                    $b->passport = isset($request->data['legalPassport']) ? $request->data['legalPassport'] : "";
                    $b->omang = isset($request->data['legalOmang']) ? $request->data['legalOmang'] : "";
                    $b->gender = isset($request->data['legalGender']) ? $request->data['legalGender'] : "";
                    $b->legalOmangExpiry = isset($request->data['omangExpiry']) ? $request->data['omangExpiry'] : "";
                    $b->legalPassportExpiry = isset($request->data['passportExpiry']) ? $request->data['passportExpiry'] : "";
                    if (($request->data['legalDOB'] != null) || ($request->data['legalDOB'] != '')) {
                        $b->dob = Carbon::createFromFormat('d/m/Y', $request->data['legalDOB'])->format('Y-m-d');
                    }
                    $b->save();
                }

            }

            if ($product->type == 'Cellphone') {
                if (isset($request->data['devices']) && $request->data['devices'] != null) {
                    $sumAssuredCellphone = 0;
                    foreach ($request->data['devices'] as $key => $device) {
                        $data = array();
                        $policy_id = $policy->id;
                        $customer_id = $user->id;
                        $device_type = htmlspecialchars(strip_tags($device['device_type']));
                        $imei = htmlspecialchars(strip_tags($device['imei']));
                        $phone_value = htmlspecialchars(strip_tags($device['phone_value']));
                        $cell_phone_make = htmlspecialchars(strip_tags($device['cell_phone_make']));
                        if ($cell_phone_make == 'Other')
                            $cell_phone_make = htmlspecialchars(strip_tags($device['make_other']));
                        else {
                            $make = DeviceMakeModel::where('id', $cell_phone_make)->first(array('name'));
                            $cell_phone_make = $make->name;
                        }
                        $cell_phone_model = htmlspecialchars(strip_tags($device['cell_phone_model']));
                        if ($cell_phone_model == 'Other')
                            $cell_phone_model = htmlspecialchars(strip_tags($device['model_other']));
                        else {
                            $model = DeviceMakeModel::where('id', $cell_phone_model)->first(array('name'));
                            $cell_phone_model = $model->name;
                        }
                        $data = [
                            'policy_id'        => $policy_id,
                            'customer_id'      => $customer_id,
                            'device_type'      => $device_type,
                            'imei'             => $imei,
                            'phone_value'      => $phone_value,
                            'cell_phone_make'  => $cell_phone_make,
                            'cell_phone_model' => $cell_phone_model,
                        ];

                        if ($device['cell_phone_front'] != NULL) {
                            $fileName = explode('/', $device['cell_phone_front']);
                            $name = array_pop($fileName);
                            $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/front' . '/' . $name;
                            Storage::disk('s3')->move($device['cell_phone_front'], $filePath);
                            $data['cell_phone_front'] = $filePath;
                        }
                        if ($device['cell_phone_back'] != NULL) {
                            $fileName = explode('/', $device['cell_phone_back']);
                            $name = array_pop($fileName);
                            $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/back' . '/' . $name;
                            Storage::disk('s3')->move($device['cell_phone_back'], $filePath);
                            $data['cell_phone_back'] = $filePath;
                        }

                        if ($device['cell_phone_left'] != NULL) {
                            $fileName = explode('/', $device['cell_phone_left']);
                            $name = array_pop($fileName);
                            $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/left' . '/' . $name;
                            Storage::disk('s3')->move($device['cell_phone_left'], $filePath);
                            $data['cell_phone_left'] = $filePath;
                        }
                        if ($device['cell_phone_right'] != NULL) {
                            $fileName = explode('/', $device['cell_phone_right']);
                            $name = array_pop($fileName);
                            $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/right' . '/' . $name;
                            Storage::disk('s3')->move($device['cell_phone_right'], $filePath);
                            $data['cell_phone_right'] = $filePath;
                        }
                        if ($device['cell_phone_top'] != NULL) {
                            $fileName = explode('/', $device['cell_phone_top']);
                            $name = array_pop($fileName);
                            $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/top' . '/' . $name;
                            Storage::disk('s3')->move($device['cell_phone_top'], $filePath);
                            $data['cell_phone_top'] = $filePath;
                        }
                        if ($device['cell_phone_bottom'] != NULL) {
                            $fileName = explode('/', $device['cell_phone_bottom']);
                            $name = array_pop($fileName);
                            $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/bottom' . '/' . $name;
                            Storage::disk('s3')->move($device['cell_phone_bottom'], $filePath);
                            $data['cell_phone_bottom'] = $filePath;
                        }

                        $policyCellPhone = $this->policy_cell_phone_interface->add_new_policy_cell_phone($data);
                        $sumAssuredCellphone =  $sumAssuredCellphone + $phone_value;
                    }
                    $p = Policy::where('id', $policy->id)->first();
                    $p->sum_assured = $sumAssuredCellphone;
                    $p->save();
                }
            }

            $banking                = new CustomerBanking();
            $banking->customer_id   = $user->id;
            $banking->policy_id     = $policy->id;
            $banking->accountNumber = isset($request->data['accountNumber']) ? $request->data['accountNumber'] : null;
            $banking->billing       = $request->data['Payment_method'] == "orangeMoney" ? "Orange USSD" :  $request->data['Payment_method'];
            $banking->billingCell   = $request->data['phone'];
            $banking->bankName      = isset($request->data['bankName']) ? $request->data['bankName'] :null;
            $banking->branchCode    = isset($request->data['branchCode']) ? $request->data['branchCode'] :null;
            $banking->accountType   = isset($request->data['bankAccountType']) ?  $request->data['bankAccountType'] :null;
            $banking->billingStartDate = isset($request->data['billing_date']) ?  date('Y-m-d', strtotime(str_replace('/', '-', $request->data['billing_date']))) :null;
            $banking->billing_day = isset($request->data['billing_date']) ? \Carbon\Carbon::createFromFormat('d/m/Y', $request->data['billing_date'])->format('d') :null;
            $saved = $banking->save();


            $customerConsent = new CustomerConsent();
            $customerConsent->policy_id = $policy->id;
            $customerConsent->customer_id = $policy->customer_id;
            $customerConsent->ip_address = $request->ip();
            $customerConsent->browser_name = $request->header('User-Agent');
            $customerConsent->is_consent_yes = $request->data['is_consent_yes'];
            $customerConsent->is_consent_to_process_yes = $request->data['is_consent_to_process_yes'];
            $customerConsent->save();

            // info verification mail
            $d = new DocumentController();
            $verificationDoc = $d->generateInformationDocument($policy->id);
            if ($verificationDoc != null) {
                $policy->verification_doc = $verificationDoc;
                $saved = $policy->save();
            }

            // policy create email & sms
            if ($user->cellphone && $policy->save()) {
                $smsMessaging = new SmsMessaging;
                $smsMessaging->sendTsosologoSMS(1, $user->cellphone, $policy->policyNumber, $product_plan->slug, '', '', '');
            }

            if ($user->email != null && $policy->save()) {

                    if ($policy->product_id != 3) {
                        $isGenerated = $d->generatePolicyDocument($policy->id);
                        if ($isGenerated != null && $policy->status == 1) {
                            $sent = $d->sendPolicyDocument($policy->id, "Agent");
                        }
                    }


                $data = new \stdClass();
                $data->hook = 'create_policy';
                $data->customer_id =  $user->id;
                $data->policy_id = $policy->id;
                $data->user_id = null;
                $data->attachment = null;
                $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                $markdown = new MailTemplate($data);
                $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                event(new \AlphaDirect\Events\SendMail($user->email,$emailTemplate->subject,"",$html,null,['policyNumber' => $policy->policyNumber,'hook' => $data->hook]));
                //Mail::to($user->email)->send(new MailTemplate($data));
            }

            if ($policy->save() && $policy->product_id == 3) {

                if(isset($policy->policyActivatedDate)) {
                    $expiry_date = Carbon::parse($policy->policyActivatedDate)->addYear()->subDays(1)->format('Y-m-d');
                } else {
                    $expiry_date = Carbon::parse($policy->created_at)->addYear()->subDays(1)->format('Y-m-d');
                }

                $status = 'Deactive';
                // if (isset($expiry_date)) {
                //     if ($expiry_date < Carbon::now()) {
                //         $status = 'Deactive';
                //     } else {
                //         $status = 'Active';
                //     }
                // }

                $policy_data = [
                    'premium'      => $policy->premium,
                    'premium_freq' => $policy->premium_freq
                ];

                $policyCon = new PolicyController();
                $premium = $policyCon->getMotorComprehensivePolicyPremium($policy_data);

                $data = [
                    'policy_id' => $policy->id,
                    'term_start_date' => ($policy->policyActivatedDate) ? $policy->policyActivatedDate : $policy->created_at,
                    'term_end_date' => $expiry_date,
                    // 'premium' => $policy->premium,
                    'premium' => $policy->premium,
                    'annual_premium' => isset($premium['annual']) ? round($premium['annual'],2) : null,
                    'vat' => $policy->vat,
                    'vat_percent' => $policy->vat_percent,
                    'renewed_by' => $request->agent_id,
                    'renewals_date' => $expiry_date,
                    'frequency' => $policy->premium_freq,
                    'first_premium' => $policy->first_premium,
                    'billing_start_date' => $policy->billingStartDate,
                    'policy_documents' => NULL,
                    'policyActivatedDate' => $policy->policyActivatedDate,
                    'payment_method' => $request->data['Payment_method'],
                    'payment_reference' => $policy->id,
                    'trans_type' => 'NEW BUSINESS',
                    'status' => $status,
                    'created_at' => Carbon::now()->format('Y-m-d'),

                ];
                $newBusiness_term_id = PolicyTerm::addPolicyTerm($data);
            }
            if( env('APP_STATUS') === 'Production' ){

                $llmapi = new LlmApiCrontroller();
                $llmapi->RegisterCustomer($policy->customer_id,$policy->agent_id);
                $llmapi->SalePolicy($policy->policyNumber);
            }

            switch ($request->data['Payment_method']) {
                case 'VCS':
                    $vcs = new PaymentController;

                    if (isset($request->data['BillingStart']) && $request->data['BillingStart'] == "Immediate") {
                        return $vcs->handlePaymentForStartActivationCodeGenerated($policy->policyNumber, 'start.alphadirect.co.bw', $premium, $request->data['GENERATED_ACTIVATION_CODE'], $request->data['BillingStart'], $request->data['billing_date']);
                    }
                    return $vcs->handlePaymentForStart($policy->policyNumber, 'start.alphadirect.co.bw', null, $request->data['billing_date']);
                    break;
                case 'DPO':

                        if(isset($request->data['pay_email']) && $request->data['pay_email'] != null){
                            $payemail               = new PaymentEmail();
                            $payemail->policyNumber =  $policy->policyNumber;
                            $payemail->pay_email =  $request->data['pay_email'];
                            $payemail->save();
                        }
                        
                       
                        $dpo = new DpoPaymentController;
                    // if (isset($request->data['BillingStart'])) {
                        $request->filter      = 'PolicyNumber';
                        $request->searchValue = $policy->policyNumber;
                        $request->leadSource  = $policy->leadSource;
                        $request->email = $request->data['email'];
                        $pay = $dpo->findPolicyForOnlinePayment($request);
                        return $pay;
                    // }
                    // return response()->json(['success' => 1, 'policyNumber' => $policy->policyNumber, 'product_id' => $policy->product_id], 200);

                    break;
                case 'N-Genius':

                    $Ngenius = new NgeniusPaymentController;
                    $request->filter      = 'PolicyNumber';
                    $request->searchValue = $policy->policyNumber;
                    if (isset($request->data['leadSource'])) {
                        $request->leadSource = $request->data['leadSource'];
                    }

                    $NgeniusReturn = $Ngenius->NgeniusPayment($request);
                    return $NgeniusReturn;

                break;
                case 'Flutterwave':
                    $rave = new FlutterwaveController;
                    return $rave->handlePayment($policy->policyNumber, $product_plan->slug, $product_plan->flutter_plan_id);
                    break;

                case 'Orange':
                    $orangeMoney = new OrangeMoneyController();
                    return $orangeMoney->webPayIntiliazer($policy->policyNumber, $premium);
                    break;

                case 'Orange USSD':
                    return response()->json(['status' => 'success', 'url' => 'thankyou-orange', 'policyNumber' => $policy->policyNumber], 200);
                    break;

                case 'orangeMoney':
                    return response()->json(['status' => 'success', 'url' => 'thankyou-orange', 'policyNumber' => $policy->policyNumber], 200);
                    break;

                case 'RealPay':
                    if(isset($request->data['instant_activate_policy'])) {
                        if(isset($request->data['pay_email']) && $request->data['pay_email'] != null){
                            $payemail               = new PaymentEmail();
                            $payemail->policyNumber =  $policy->policyNumber;
                            $payemail->pay_email =  $request->data['pay_email'];
                            $payemail->save();
                        }
                        $dpo = new DpoPaymentController;
                        $request->filter      = 'PolicyNumber';
                        $request->searchValue = $policy->policyNumber;
                        $request->leadSource  = $policy->leadSource;
                        $request->email = $request->data['email'];
                        $request->requestType = 'instantActivatePolicy';
                        $pay = $dpo->findPolicyForOnlinePayment($request);
                        return $pay;
                        break;
                    } else {
                        $addEvent = $this->realpayPayment($policy);
                        return response()->json(['status' => 'success', 'message' => 'Policy created successfully', 'policyNumber' => $policy->policyNumber], 200);
                        break;
                    }
                    case 'PayM8':
                            $paym8 = new PayM8Controller();
                            $addEvent = $paym8->createAdHocPayment($policy->id);
                            return response()->json(['status' => 'success', 'message' => 'Policy created successfully', 'policyNumber' => $policy->policyNumber], 200);
                            break;


                default:
                    return Redirect::back()->with('error', 'Please select a payment vendor')
                        ->withInput($request->data);
            }


            return response()->json(['message' => 'First Time User Policy Created Successfuly'], 200);
        } catch (Exception $ex) {

            return response()->json($ex->getMessage(), 500);
        }
    }
    public function activatePolicyFirstTimeUserAlphaFe2(Request $request)
    {


        if ($request->data['Payment_method'] == 'DPO') {
            if (!isset($request->data['email'])) {
                return response()->json(['title' => 'Email is mandatory', 'description' => 'PLease provide email if payment method is DPO.'], 412);
            }
        }
        //try {
            //dd($request->all());
            if ($request->data['state'] == null) {
                return response()->json(['title' => 'State', 'error' => 'Please select State.'], 409);
            }
            if ($request->data['city'] == null) {
                return response()->json(['title' => 'City', 'error' => 'Please select City.'], 409);
            }
            if($request->data['product_id'] == 4){
                if ($request->data['maritalstatus'] == 2) {
                    if ($request->data['legalFName'] == null || $request->data['legalFName'] == '') {
                        return response()->json(['title' => 'SpouseFirstName', 'error' => 'Please Enter Spouse First Name.'], 409);
                    }
                    if ($request->data['legalLName'] == null || $request->data['legalLName'] == '') {
                        return response()->json(['title' => 'SpouseLastName', 'error' => 'Please Enter Spouse Last Name.'], 409);
                    }
                    if ($request->data['legalPhone'] == null || $request->data['legalPhone'] == '') {
                        return response()->json(['title' => 'SpousePhone', 'error' => 'Please Enter Spouse Phone no.'], 409);
                    }
                }
            }
         if($request->data['product_id'] == 5){

              if(!isset($request->data['devices'])){
                         return response()->json(['title' => 'Please add devices first', 'error' => 'Please add devices first.'], 411);
                 }
         }


            if($request->data['product_id'] == 1 || $request->data['product_id'] == 3){
                $dateOfBCheck = Carbon::createFromFormat('d/m/Y', $request->data['dob'])->format('Y-m-d');
                $totalYears = Carbon::parse($dateOfBCheck)->age;
                if ($totalYears < 18 ) {
                    return ['success' => false, 'Message' => 'Customer age is less than 18 or more than 65.'];
                }
            }
            if( $request->data['product_id'] == 4 ){
                $dateOfBCheck = Carbon::createFromFormat('d/m/Y', $request->data['dob'])->format('Y-m-d');
                $totalYears = Carbon::parse($dateOfBCheck)->age;
                if ($totalYears < 18 ) {
                    return ['success' => false, 'Message' => 'Customer age is less than 18 .'];
                }
            }

            if (isset($request->data['beneficiaries']) && $request->data['beneficiaries'] != null) {
                $payment = 0;
                foreach ($request->data['beneficiaries']  as $key => $beneficiary) {
                    if ($beneficiary['beneficiaryRelation'] != null) {

                        $payment = $payment + $beneficiary['beneficiaryPayment'];
                    }
                }
                if ($payment > 100) {
                    return response()->json(['title' => 'Beneficiary share exceeded', 'description' => 'Please adjust the beneficiary share not more than 100.'], 412);
                }
            }

            $product = Product::where('id', $request->data['product_id'])->first(array('has_vehicle', 'has_member', 'premium_type_id', 'limit', 'kyc_recipient', 'kyc_customer', 'preinspection', 'is_motor_items', 'region_id', 'has_activation_code', 'type'));
            // for tyre we are adding vehicleplate in reapeter
            if ($product->type != 'Tyre') {
                if ($product->has_vehicle == 1) {
                    if (!isset($request->data['vehiclePlate']) || $request->data['vehiclePlate'] == null) {
                        return response()->json(['title' => 'Motor vehicle plate required', 'error' => 'Please provide valid vehicle plate number'], 409);
                    }
                    $vehicle = $this->checkIfVehicleAlreadyRegistered($request->data['vehiclePlate']);

                    if ($vehicle) {
                        return response()->json(['title' => 'Motor vehicle exists', 'error' => 'You may not register a new policy with an already existing vehicle plate.'], 409);
                    }
                }
            }

            if ($product->has_member == 1) {
                $customerCheck = $this->checkIfMemberAlreadyRegistered($request->data['idType'], $request->data['idValue']);

                if ($customerCheck == true) {
                    return response()->json(['title' => 'Customer already registered for this policy', 'error' => 'Customer is already owner of Accidental death insurace policy. Please check again.'], 400);
                }

                $dateOfBirth = Carbon::createFromFormat('d/m/Y', $request->data['dob'])->format('Y-m-d');;
                $years = Carbon::parse($dateOfBirth)->age;
                if ($years < 18) {
                    return ['success' => false, 'Message' => 'Customer age is less than 18.'];
                }
            }

            if ($product->type == 'Cellphone') {
                if (isset($request->data['devices']) && $request->data['devices'] != null) {
                    foreach ($request->data['devices'] as $key => $device) {

                        if (!isset($device['imei']) || $device['imei'] == null) {
                            return response()->json(['title' => 'Please add devices first', 'error' => 'Please add devices first.'], 411);
                        }
                    }
                }
            }
            $omang = $request->data['idType'] == 'Omang' ? $request->data['idValue'] : null;
            $passport = $request->data['idType'] == 'Passport' ? $request->data['idValue'] : null;
            $cellphone = htmlspecialchars(strip_tags($request->data['phone']));
            $profile = null;
            if ($cellphone != null) {
                $profile = Customer::where('cellphone', $cellphone)->orderBy('id', 'asc')->first(array('id', 'is_blocked'));
            }
            // is blocked
            if ($profile && $profile->is_blocked != null) {
                if ($profile->is_blocked == 1) {
                    return response()->json(['success' => false, 'message' => 'customer is blocked'], 200);
                }
            }

            if ($profile != null) {
                $customer_id = $profile->id;

                if ($request->data['product_id'] == 1) {
                    $row = Policy::where('customer_id',$customer_id)->where('product_id',$request->data['product_id'])->where('status', "!=", 2)->count();
                    if ($row > 0) {
                        return response()->json(['title' => 'Policy exists', 'description' => 'The customer already has purchased the specified product'], 406);
                    }
                }
                //Legal Products
                if ($request->data['product_id'] == 4) {
                    $row = Policy::where('customer_id',$customer_id)->where('product_id',$request->data['product_id'])->where('status', "!=", 2)->count();
                    if ($row > 0) {
                        return response()->json(['title' => 'Policy exists', 'description' => 'The customer already has purchased the specified product'], 406);
                    }
                }
            }
            //check for omang and passport
            if ($profile == null) {
                if ($omang != null && $passport != null) {
                    $profile = CustomerProfile::where('omang', $omang)->orWhere('passport', $passport)->orderBy('id', 'asc')->first(array('customer_id'));
                } elseif ($omang == null && $passport != null) {
                    $profile = CustomerProfile::where('passport', $passport)->orderBy('id', 'asc')->first(array('customer_id'));
                } elseif ($omang != null && $passport == null) {
                    $profile = CustomerProfile::where('omang', $omang)->orderBy('id', 'asc')->first(array('customer_id'));
                }
                if ($profile != null) {
                    $customer_id = $profile->customer_id;
                    if (isset($request->data['sourceOfIncome'])) {

                            $profile->sourceOfIncome = json_encode($request->data['sourceOfIncome']);


                    }
                }
            }

              // Enable Mati
              $mati_config = Config::where('key','enable_mati')->first(array('id','value'));
              $mati_enable = $mati_config->value;

            if ($request->data['customerExists'] == 1 || $profile != null) {
                $AdminCustomerController = new AdminCustomerController();
                $policyInvalid = $AdminCustomerController->PolicyBusinessValidations($customer_id, $request->data['product_id']);
                if($policyInvalid)
                {
                    return response()->json(['policy_exists' => 1, 'title' => 'Policy exists', 'message' => 'The customer already has purchased the specified product'], 403);
                }

                if($mati_enable == 0){
                    $data_mati_id  = 0;
                }else{
                    $data_mati_id  = $request->data['mati-identityId'];
                }

                $user = Customer::where('id', $customer_id)->first();
                if(isset($data_mati_id)) {
                    $user->mati_identity = $data_mati_id;
                }
                $user->save();
                $profile_update = CustomerProfile::where('customer_id', $user->id)->first();

                if ($product->type == 'Legal' && isset($profile_update)) {
                    $profile_update->e_name          = $request->data['e_name'];
                    $profile_update->emp_no         = $request->data['emp_no'];
                    $profile_update->emp_phone        = $request->data['emp_phone'];
                    $profile_update->salary_pay_date = date('Y-m-d', strtotime(str_replace('/', '-', $request->data['salary_pay_date'])));
                   // $profile_update->save();
                }

                if(isset($request->data['product_id']) && ($request->data['product_id'] == 1 || $request->data['product_id'] == 4 )){
                    if(isset($request->data['dob'])){
                        $dateOfBCheck        = date('Y-m-d', strtotime(str_replace('/', '-', $request->data['dob'])));
                        $profile_update->dob = $dateOfBCheck;
                    }


                }

                if (isset($request->data['sourceOfIncome']) && $request->data['sourceOfIncome'] != null) {
                    if($request->data['sourceOfIncome'] == 'unemployed'){
                        $profile_update->sourceOfIncome = '"unemployed"';
                    }else{
                        $profile_update->sourceOfIncome = json_encode($request->data['sourceOfIncome']);
                    }
                }else{
                    $profile_update->sourceOfIncome = '"unemployed"';
                }

                $profile_update->save();
                $customerKYC = KYC::where('customer_id', $user->id)->first();
            } else {

                 if($mati_enable == 0){
                     $data_mati_id  = 0;
                 }else{
                     $data_mati_id  = $request->data['mati-identityId'];
                 }

                $user             = new Customer();
                $user->firstName  = $request->data['firstname'];
                $user->middlename = $request->data['middlename'];
                $user->lastName   = $request->data['lastname'];
                $user->email      = $request->data['email'];
                $user->cellphone  = $request->data['phone'];

                if(isset($data_mati_id)) {
                    $user->mati_identity = $data_mati_id;
                } else {
                    if($request->data['agent_id'] == null){
                        return response()->json(['title' => 'KYC is Mandatory', 'error' => 'Please Complete Mati Verification'], 411);
                    }
                }
                $user->save();
                // user create mail sms
                // for Password Reset
                $token                  = Str::random(8);
                $user_password          = new UserPassword();
                $user_password->user_id = $user->id;
                $user_password->token   = $token;
                $url                    = env('LIVEQUOTE_URL') . 'reset_password_first_time.php?token=' . $token;
                $user_password->url     = $url;
                $user_password->save();

                $sms = new SmsMessaging();
                $sms->sendSmsUserCreate(29, $user->firstName, $user->lastName, $user->cellphone, $url);

                if ($user->email != null) {
                    $data = new \stdClass();
                    $data->user_id = null;
                    $data->customer_id = $user->id;
                    $data->new_user_password_url_id = $user_password->id;
                    $data->hook = 'user_create';
                    $data->attachment = null;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate', ['data' => $data]);
                    event(new \AlphaDirect\Events\SendMail($user->email, $emailTemplate->subject, "", $html, null, ['hook' => $data->hook]));
                    //   mail::to($user->email)->send(new MailTemplate($data));
                    // return response()->json(['success' => 1, 'mail' => $mail], 200);
                }

                $ifExists = CustomerProfile::where('customer_id',$user->id)->exists();
                if(!empty($ifExists))
                {
                    $profile = CustomerProfile::where('customer_id',$user->id)->first();
                }else{
                    /*Add Records to Customers Profile table*/
                    $profile = new CustomerProfile();
                }
                $profile->customer_id     = $user->id;
                if ($product->type == 'Legal') {
                    $profile->e_name         = isset($request->data['e_name']) ? $request->data['e_name'] : "";
                    $profile->emp_no         = isset($request->data['emp_no']) ? $request->data['emp_no'] : "";
                    $profile->emp_phone      = isset($request->data['emp_phone']) ? $request->data['emp_phone'] : "";
                    $salary_date             = date('Y-m-d', strtotime(str_replace('/', '-', $request->data['salary_pay_date'])));
                    $profile->salary_pay_date = isset($salary_date) ? $salary_date : "";
                }
                $profile->gender      = $request->data['gender'];
                $profile->address     = $request->data['address'];
                $profile->city        = isset($request->data['city']) &&  $request->data['city'] != '' ? $request->data['city'] : "";
                $profile->state       = $request->data['state'];
                $profile->omang       = $request->data['idType'] == 'Omang' ? $request->data['idValue'] : "";
                $profile->passport    = $request->data['idType'] == 'Passport' ? $request->data['idValue'] : "";
                $profile->countryId   = $request->data['passportIssuingCountry'];
                $profile->maritalstatus = isset($request->data['maritalstatus']) && $request->data['maritalstatus'] != '' ? $request->data['maritalstatus'] : ""; //key changed as per request of abhijeet marital => maritalstatus
                if (!Policy::where('customer_id', $user->id)
                                            ->where('product_id', 1)
                                            ->where('status', '!=', 2)
                                            ->exists()) {
                    $profile->dob         = date('Y-m-d', strtotime(str_replace('/', '-', $request->data['dob'])));
                }
                $profile->driving_license_number = isset($request->data['dLicense']) ? $request->data['dLicense'] : "";
                if (isset($request->data['license_valid_till'])) {
                    $profile->license_valid_till = date('Y-m-d', strtotime(str_replace('/', '-', $request->data['license_valid_till'])));
                }
                if (isset($request->data['sourceOfIncome'])) {
                    $profile->sourceOfIncome = json_encode($request->data['sourceOfIncome']);
                }
                $profile->save();
            }

            $customerKYC = KYC::where('customer_id', $user->id)->first();
            if ($customerKYC == null) {
                $customerKYC = new KYC();
            }
            $customerKYC->customer_id = $user->id;

            //save the policy
            //Latestid for Policy Number
            $latest = Policy::orderBy('id', 'desc')->first(array('id'));
            if ($latest == null) {
                $latest = collect();
                $latest->id = 1;
            }

            $omangFront  = isset($request->data['omangKyc']) ? htmlspecialchars(strip_tags($request->data['omangKyc'])) : NULL;
            $omangBack   = isset($request->data['omangbackKyc']) ? htmlspecialchars(strip_tags($request->data['omangbackKyc'])) : NULL;
            $passportKyc = isset($request->data['passportKyc']) ? htmlspecialchars(strip_tags($request->data['passportKyc'])) : NULL;
            if ($omangFront != NULL) {
                $fileName = explode('/', $omangFront);
                $name = array_pop($fileName);
                $filePath = 'MIS/' . $customerKYC->customer_id . '/' . 'Customer' . '/omang' . '/' . $name;
                Storage::disk('s3')->move($omangFront, $filePath);
                $customerKYC->omang = $filePath;
            }
            if ($omangBack != NULL) {
                $fileName = explode('/', $omangBack);
                $name = array_pop($fileName);
                $filePath = 'MIS/' . $customerKYC->customer_id . '/' . 'Customer' . '/omang_back' . '/' . $name;
                Storage::disk('s3')->move($omangBack, $filePath);
                $customerKYC->omangBack = $filePath;
            }

            if ($passportKyc != NULL) {
                $fileName = explode('/', $passportKyc);
                $name = array_pop($fileName);
                $filePath = 'MIS/' . $customerKYC->customer_id . '/' . 'Customer' . '/passport' . '/' . $name;
                Storage::disk('s3')->move($passportKyc, $filePath);
                $customerKYC->passport = $filePath;
            }
            $customerKYC->save();

            $policy = new Policy();
            $policy->customer_id = $user->id;
            $policy->agent_id = $request->data['agent_id'] != "" ? $request->data['agent_id'] : "0";
            if(isset($request->data['agent_id']) && $request->data['agent_id'] != "" ){
                $agent = \AlphaDirect\User::where('id',$request->data['agent_id'])->where('agency_id','!=',null)->first(['agency_id']);
                if($agent){
                     $policy->agency_id = $agent->agency_id;
                }
            }
            $policy->storeID = isset($request->data['store']) ? $request->data['store'] : '';

            $policy->product_id = $request->data['product_id'];
            $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
            if ($product->premium_type_id == 11) {
                $product_plan = Productplan::where('id', $request->data['planId'])->first(array('sum_assured', 'premium', 'slug', 'flutter_plan_id'));
                $premium = round(($product_plan->premium * ($regionVat / 100)) + $product_plan->premium, 2);
                $policy->plan_id = $request->data['planId'];
                $policy->sum_assured = $product_plan->sum_assured;
                $policy->premium = round($premium, 2);
                $policy->vat = round($product_plan->premium * ($regionVat / 100), 2);
                $policy->vat_percent = $regionVat;
            } else {
                $premium = ($request->data['premium'] * ($regionVat / 100)) + $request->data['premium'];
                $policy->premium = $premium;
                $policy->vat = $request->data['premium'] * ($regionVat / 100);
                $policy->sum_assured = $request->data['sum_assured'];
            }

            $policy->policyNumber         = 'MIS' . Carbon::now()->year . str_pad(($latest->id + 1), 6, '0', STR_PAD_LEFT);
            $policy->status               = 0;
            $policy->leadSource           = "start.alphadirect.co.bw";
            $policy->has_vehicle          = $product->has_vehicle;
            $policy->has_member           = $product->has_member;
            $policy->preinspection        = $product->preinspection;
            $policy->is_motor_items       = $product->is_motor_items;
            $policy->limit                = $product->limit;
            $policy->kyc_customer         = $product->kyc_customer;
            $policy->kyc_recipient        = $product->kyc_recipient;
            $policy->billingStartDate     = date('Y-m-d', strtotime(str_replace('/', '-', $request->data['billing_date'])));
            $policy->ori_billingStartDate     = date('Y-m-d', strtotime(str_replace('/', '-', $request->data['billing_date'])));
            $policy->BillingStart = isset($request->data['BillingStart']) ? $request->data['BillingStart'] : null;
            $policy->is_sys_act_generated = $request->data['GENERATED_ACTIVATION_CODE'];
            $policy->billing_day = isset($request->data['billing_date']) ? \Carbon\Carbon::createFromFormat('d/m/Y', $request->data['billing_date'])->format('d') :null;

            if ($request->data['product_id'] != 3) {
                $policy->BillingStart = isset($request->data['BillingStart']) ? $request->data['BillingStart'] : '';
                $policy->isVirtualBox = isset($request->data['isVirtualBox']) ? $request->data['isVirtualBox'] : '';
            }
            if ($request->data['activationCode']) {
                $activation         = Activation::where('activation_code', $request->data['activationCode'])->first();
                $activation->status = '1';
                $activation->branch = '0';
                $activation->save();

                $Check = CustomerGeneratedActivationCode::where('activation_code', $request->data['activationCode'])->first();
                if (isset($Check) &&  $Check != null) {
                    $Check->status = "1";
                    $Check->save();
                }

                $policy->activation_code = $request->data['activationCode'];
                $policy->serial_code     = $activation->serial_code;
                $policy->isVirtualBox    = $request->data['isVirtualBox'];
                $policy->BillingStart    = isset($request->data['BillingStart']) ? $request->data['BillingStart'] : null;
            }

            if ($request->data['Payment_method'] == 'N-Genius') {
                $policy->BillingStart = "Immediate" ;
                $policy->isVirtualBox = null;
            }

            $saved = $policy->save();

            event(new \AlphaDirect\Events\policyLifecycle($policy->id, "Create"));
            if ($product->has_activation_code != 0 && $policy->storeID != null) {
                $check = \Modules\Inventory\Entities\StoresInventory::where('store_id', '=', $policy->storeID)->exists();
                if ($check == true) {
                    $stock = \Modules\Inventory\Entities\StoresInventory::where('store_id', '=', $policy->storeID)->where('product_id', '=', $request->data['product_id'])->where('plan_id', '=', $request->data['planId'])->first(array('counter', 'id'));
                    if ($saved == true) {
                        $quantity = 1;
                        $agent = User::where('id', $policy->agent_id)->firstOr(function () {
                            return User::where('id', 1)->first();
                        });
                       // event(new \Modules\Inventory\Events\DeductStockStore($stock->id, $user, $quantity));
                    }
                }
            }

            if ($policy->storeID != null && $policy->agentId != null) {
                $this->addAgentActivity($policy->storeID, $policy->agentId);
            }
            if ($saved == true && $policy->agent_id != null) {
                $data = [
                    'id' => $policy->id,
                    'agent_id' => $policy->agent_id,
                    'status' => $policy->status,
                ];
                event(new CommissionPolicyEvent($data));
            }

            if ($saved == true) {
                $data = [
                    'store_id' => $policy->storeID,
                    'product_id' => $policy->product_id,
                    'plan_id' => $policy->plan_id,
                ];
                event(new DecrementCounter($data));
            }

            if ($product->type != 'Tyre') {
                if ($product->has_vehicle == '1') {
                    $vehicle = new Vehicle();
                    $vehicle->customer_id = $user->id;
                    $vehicle->policy_id = $policy->id;
                    $vehicle->vehiclePlate = $request->data['vehiclePlate'];
                    $vehicle->is_private = $request->purpose;
                    $vehicle->save();
                }
            }

            //tyre & rim policy
            if ($product->type == 'Tyre') {
                if (isset($request->data['vehicles']) && $request->data['vehicles'] != null) {
                    foreach ($request->data['vehicles'] as $key => $vehicle) {
                        // save vehicle data to vehicle table
                        $v = new vehicle();
                        $v->customer_id = $user->id;
                        $v->policy_id = $policy->id;
                        if(($vehicle['is_imported'])=='Yes') {
                           $is_imported=1;
                        }elseif(($vehicle['is_imported'])=='No'){
                            $is_imported=0 ;
                        }else{
                            $is_imported=NULL ;
                        }
                        $v->is_imported = $is_imported ;
                        $v->make = $vehicle['make'];
                        $v->year = $vehicle['year'];
                        $v->model = $vehicle['model'];
                        $v->purpose = $vehicle['purpose'];
                        $v->vehiclePlate = $vehicle['tyreVehiclePlate'];
                        if ($vehicle['invoice_tyre'] != NULL) {
                            $fileName = explode('/', $vehicle['invoice_tyre']);
                            $name = array_pop($fileName);
                            $filePath = 'MIS/' . $user->id . '/' . 'Tyre' . '/tyre_invoice' . '/' . $name;
                            Storage::disk('s3')->move($vehicle['invoice_tyre'], $filePath);
                            $v->tyre_invoice = $filePath;
                        }

                        if ($vehicle['km_of_car'] != NULL) {
                            $fileName = explode('/', $vehicle['km_of_car']);
                            $name = array_pop($fileName);
                            $filePath = 'MIS/'.$user->id.'/'.'Tyre'.'/km_of_car'.'/'.$name;
                            Storage:: disk('s3')->move($vehicle['km_of_car'], $filePath);
                            $v->km_of_car = $filePath;
                        }
                        $v->save();
                        // save tyres and rims data to policy_tyre_rim table
                        if (isset($vehicle['number_of_insure'])) {
                            $noOfInsure = $vehicle['number_of_insure'];
                            for ($i = 0; $i<$noOfInsure; $i++) {
                                $tyreImg = new PolicyTyreRim();
                                $tyreImg->customer_id = $user->id;
                                $tyreImg->policy_id = $policy->id;
                                $tyreImg->vehicle_id = $v->id;
                                $tyreImg->position = $vehicle['tyre_position'][$i];
                                $tyreImg->tyre_insure = $vehicle['tyre_insure'][$i];
                                if ($vehicle['tyre_image'][$i] != NULL) {
                                    $fileName = explode('/', $vehicle['tyre_image'][$i]);
                                    $name = array_pop($fileName);
                                    $filePath = 'MIS/' . $user->id . '/' . 'Tyre' . '/tyre_image' . '/' . $name;
                                    Storage::disk('s3')->move($vehicle['tyre_image'][$i], $filePath);
                                    $tyreImg->image = $filePath;
                                }
                                $tyreImg->size = $vehicle['tyre_size'][$i];
                                $tyreImg->value = $vehicle['tyre_value'][$i];
                                $tyreImg->dot_number = $vehicle['tyre_dot'][$i];
                                $tyreImg->barcode = $vehicle['tyre_barcode'][$i];
                                $tyreImg->created_at = Carbon::now();
                                $tyreImg->save();
                            }
                        }

                    }
                }
            }

            if (isset($request->data['main']) && $request->data['main'] != null) {
                $mains = FactorMain::where('product_id', $request->product_id)->where('status', 1)->get(array('id', 'name', 'type'));

                foreach ($mains as $main) {
                    if (is_array($request->data['factor_' . $main->id])) {
                        foreach ($request->data['factor_' . $main->id] as $key => $value_id) {
                            $factor = new PolicyFactor();
                            $factor->policy_id = $policy->id;
                            $factor->factor_main_id = $main->id;
                            $factor->name = $main->name;
                            $factor->type = $main->type;
                            $value = FactorSubType::where('id', $value_id)->first(array('name', 'factor'));
                            $factor->factor_value_id = $value_id;
                            $factor->value_name = $value->name;
                            $factor->factor = $value->factor;
                            $saved = $factor->save();
                        }
                    } else {
                        $factor = new PolicyFactor();
                        $factor->policy_id = $policy->id;
                        $factor->factor_main_id = $main->id;
                        $factor->name = $main->name;
                        $factor->type = $main->type;
                        $value = FactorSubType::where('id', $request->data['factor_' . $main->id])->first(array('name', 'factor'));

                        if ($main->type == 'Input Field') {
                            $factor->value_name = $request->data['factor_' . $main->id];
                        } else {
                            $factor->value_name = $value->name;
                            $factor->factor = $value->factor;
                            $factor->factor_value_id = $request->data['factor_' . $main->id];
                        }
                        $saved = $factor->save();
                    }
                }
                if ($request->data['main'] != null) {
                    for ($i = 0; $i < count($request->data['main']); $i++) {
                        $policyCover = new PolicyCoverage();
                        $policyCover->policy_id = $policy->id;
                        $policyCover->main = $request->data['main'][$i];
                        $policyCover->coverage_value = $request->data['cover_value'][$i];
                        $policyCover->discount = $request->data['type'][$i];
                        $policyCover->type = $request->data['disccount_type'][$i];
                        $policyCover->value = $request->data['type_value'][$i];
                        $saved = $policyCover->save();
                    }
                }
            }

            if (isset($request->data['members']) && $request->data['members'] != null) {

                foreach ($request->data['members']  as $key => $member) {
                    if ($member['relation'] != null) {
                        $m = new PolicyMember();
                        $m->policy_id = $policy->id;
                        $m->relation = $member['relation'];
                        $m->first_name = $member['memberFName'];
                        $m->last_name = $member['memberLName'];
                        $m->dob = date('Y-m-d', strtotime(str_replace('/', '-', $member['memberDOB'])));
                        $m->gender = $member['memberGender'];
                        $m->save();
                    }
                }
            }

            if (isset($request->data['beneficiaries']) && $request->data['beneficiaries'] != null) {

                foreach ($request->data['beneficiaries'] as $key => $beneficiary) {
                    if ($beneficiary['beneficiaryRelation'] != null) {
                        $b = new PolicyBeneficiary();
                        $b->policy_id = $policy->id;
                        $b->relation = htmlspecialchars(strip_tags($beneficiary['beneficiaryRelation']));
                        $b->first_name = htmlspecialchars(strip_tags($beneficiary['beneficiaryFName']));
                        $b->last_name = htmlspecialchars(strip_tags($beneficiary['beneficiaryLName']));
                        $b->passport = htmlspecialchars(strip_tags($beneficiary['beneficiaryPassport']));
                        $b->omang = htmlspecialchars(strip_tags($beneficiary['beneficiaryOmang']));
                        $b->gender = htmlspecialchars(strip_tags($beneficiary['beneficiaryGender']));
                        $b->payment = htmlspecialchars(strip_tags($beneficiary['beneficiaryPayment']));
                        if(isset($beneficiary['under_18_is_allowed'])){
                            $b->under_18_is_allowed = $beneficiary['under_18_is_allowed'][0];
                        }

                        //$b->dob = Carbon::parse($beneficiary['beneficiaryDOB'])->format('Y-m-d');
                        if($beneficiary['beneficiaryDOB'] != null){
                            $pos = strpos($beneficiary['beneficiaryDOB'], '/');
                            if ($pos !== false) {
                                $b->dob = Carbon::createFromFormat('d/m/Y',$beneficiary['beneficiaryDOB'])->format('Y-m-d');
                            } else {
                                $b->dob = Carbon::parse($beneficiary['beneficiaryDOB'])->format('Y-m-d');
                            }
                        }

                        $b->save();
                    }
                }
            }

            if ($product->type == 'Legal') {

                if ($request->data['maritalstatus'] == '2') {
                    $b = new PolicyBeneficiary();
                    $b->policy_id = $policy->id;
                    $b->relation = 'Spouse';
                    $b->first_name = isset($request->data['legalFName']) ? $request->data['legalFName'] : "";
                    $b->middle_name = isset($request->data['legalMName']) ? $request->data['legalMName'] : "";
                    $b->last_name = isset($request->data['legalLName']) ? $request->data['legalLName'] : "";
                    $b->cellphone = isset($request->data['legalPhone']) ? $request->data['legalPhone'] : "";
                    $b->email = isset($request->data['legalEmail']) ? $request->data['legalEmail'] : "";
                    $b->passport = isset($request->data['legalPassport']) ? $request->data['legalPassport'] : "";
                    $b->omang = isset($request->data['legalOmang']) ? $request->data['legalOmang'] : "";
                    $b->gender = isset($request->data['legalGender']) ? $request->data['legalGender'] : "";
                    $b->legalOmangExpiry = isset($request->data['omangExpiry']) ? $request->data['omangExpiry'] : "";
                    $b->legalPassportExpiry = isset($request->data['passportExpiry']) ? $request->data['passportExpiry'] : "";
                    if (($request->data['legalDOB'] != null) || ($request->data['legalDOB'] != '')) {
                        $b->dob = Carbon::createFromFormat('d/m/Y', $request->data['legalDOB'])->format('Y-m-d');
                    }
                    $b->save();
                }

            }

            if ($product->type == 'Cellphone') {
                if (isset($request->data['devices']) && $request->data['devices'] != null) {
                    $sumAssuredCellphone = 0;
                    foreach ($request->data['devices'] as $key => $device) {
                        $data = array();
                        $policy_id = $policy->id;
                        $customer_id = $user->id;
                        $device_type = htmlspecialchars(strip_tags($device['device_type']));
                        $imei = htmlspecialchars(strip_tags($device['imei']));
                        $phone_value = htmlspecialchars(strip_tags($device['phone_value']));
                        $cell_phone_make = htmlspecialchars(strip_tags($device['cell_phone_make']));
                        if ($cell_phone_make == 'Other')
                            $cell_phone_make = htmlspecialchars(strip_tags($device['make_other']));
                        else {
                            $make = DeviceMakeModel::where('id', $cell_phone_make)->first(array('name'));
                            $cell_phone_make = $make->name;
                        }
                        $cell_phone_model = htmlspecialchars(strip_tags($device['cell_phone_model']));
                        if ($cell_phone_model == 'Other')
                            $cell_phone_model = htmlspecialchars(strip_tags($device['model_other']));
                        else {
                            $model = DeviceMakeModel::where('id', $cell_phone_model)->first(array('name'));
                            $cell_phone_model = $model->name;
                        }
                        $data = [
                            'policy_id'        => $policy_id,
                            'customer_id'      => $customer_id,
                            'device_type'      => $device_type,
                            'imei'             => $imei,
                            'phone_value'      => $phone_value,
                            'cell_phone_make'  => $cell_phone_make,
                            'cell_phone_model' => $cell_phone_model,
                        ];

                        if ($device['cell_phone_front'] != NULL) {
                            $fileName = explode('/', $device['cell_phone_front']);
                            $name = array_pop($fileName);
                            $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/front' . '/' . $name;
                            Storage::disk('s3')->move($device['cell_phone_front'], $filePath);
                            $data['cell_phone_front'] = $filePath;
                        }
                        if ($device['cell_phone_back'] != NULL) {
                            $fileName = explode('/', $device['cell_phone_back']);
                            $name = array_pop($fileName);
                            $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/back' . '/' . $name;
                            Storage::disk('s3')->move($device['cell_phone_back'], $filePath);
                            $data['cell_phone_back'] = $filePath;
                        }

                        if ($device['cell_phone_left'] != NULL) {
                            $fileName = explode('/', $device['cell_phone_left']);
                            $name = array_pop($fileName);
                            $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/left' . '/' . $name;
                            Storage::disk('s3')->move($device['cell_phone_left'], $filePath);
                            $data['cell_phone_left'] = $filePath;
                        }
                        if ($device['cell_phone_right'] != NULL) {
                            $fileName = explode('/', $device['cell_phone_right']);
                            $name = array_pop($fileName);
                            $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/right' . '/' . $name;
                            Storage::disk('s3')->move($device['cell_phone_right'], $filePath);
                            $data['cell_phone_right'] = $filePath;
                        }
                        if ($device['cell_phone_top'] != NULL) {
                            $fileName = explode('/', $device['cell_phone_top']);
                            $name = array_pop($fileName);
                            $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/top' . '/' . $name;
                            Storage::disk('s3')->move($device['cell_phone_top'], $filePath);
                            $data['cell_phone_top'] = $filePath;
                        }
                        if ($device['cell_phone_bottom'] != NULL) {
                            $fileName = explode('/', $device['cell_phone_bottom']);
                            $name = array_pop($fileName);
                            $filePath = 'device/' . $policy_id . '/' . 'Cellphone/' . $customer_id . '/bottom' . '/' . $name;
                            Storage::disk('s3')->move($device['cell_phone_bottom'], $filePath);
                            $data['cell_phone_bottom'] = $filePath;
                        }

                        $policyCellPhone = $this->policy_cell_phone_interface->add_new_policy_cell_phone($data);
                        $sumAssuredCellphone =  $sumAssuredCellphone + $phone_value;
                    }
                    $p = Policy::where('id', $policy->id)->first();
                    $p->sum_assured = $sumAssuredCellphone;
                    $p->save();
                }
            }

            $banking                = new CustomerBanking();
            $banking->customer_id   = $user->id;
            $banking->policy_id     = $policy->id;
            $banking->accountNumber = isset($request->data['accountNumber']) ? $request->data['accountNumber'] : null;
            $banking->billing       = $request->data['Payment_method'] == "orangeMoney" ? "Orange USSD" :  $request->data['Payment_method'];
            $banking->billingCell   = $request->data['phone'];
            $banking->bankName      = isset($request->data['bankName']) ? $request->data['bankName'] :null;
            $banking->branchCode    = isset($request->data['branchCode']) ? $request->data['branchCode'] :null;
            $banking->accountType   = isset($request->data['bankAccountType']) ?  $request->data['bankAccountType'] :null;
            $banking->billingStartDate = isset($request->data['billing_date']) ?  date('Y-m-d', strtotime(str_replace('/', '-', $request->data['billing_date']))) :null;
            $banking->billing_day = isset($request->data['billing_date']) ? \Carbon\Carbon::createFromFormat('d/m/Y', $request->data['billing_date'])->format('d') :null;
            $saved = $banking->save();


            $customerConsent = new CustomerConsent();
            $customerConsent->policy_id = $policy->id;
            $customerConsent->customer_id = $policy->customer_id;
            $customerConsent->ip_address = $request->ip();
            $customerConsent->browser_name = $request->header('User-Agent');
            $customerConsent->is_consent_yes = $request->data['is_consent_yes'];
            $customerConsent->is_consent_to_process_yes = $request->data['is_consent_to_process_yes'];
            $customerConsent->save();

            // info verification mail
            $d = new DocumentController();
            $verificationDoc = $d->generateInformationDocument($policy->id);
            if ($verificationDoc != null) {
                $policy->verification_doc = $verificationDoc;
                $saved = $policy->save();
            }

            // policy create email & sms
            if ($user->cellphone && $policy->save()) {
                $smsMessaging = new SmsMessaging;
                $smsMessaging->sendTsosologoSMS(1, $user->cellphone, $policy->policyNumber, $product_plan->slug, '', '', '');
            }

            if ($user->email != null && $policy->save()) {
                if ($policy->status == 1) {
                    if ($policy->product_id != 3) {
                        $isGenerated = $d->generatePolicyDocument($policy->id);
                        if ($isGenerated != null) {
                            $sent = $d->sendPolicyDocument($policy->id, "Agent");
                        }
                    }
                }

                // $data = new \stdClass();
                // $data->hook = 'create_policy';
                // $data->customer_id =  $user->id;
                // $data->policy_id = $policy->id;
                // $data->user_id = null;
                // $data->attachment = null;
                // $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                // $markdown = new MailTemplate($data);
                // $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                // event(new \AlphaDirect\Events\SendMail($user->email,$emailTemplate->subject,"",$html,null,['policyNumber' => $policy->policyNumber,'hook' => $data->hook]));
                //Mail::to($user->email)->send(new MailTemplate($data));
            }

            if ($policy->save() && $policy->product_id == 3) {

                if(isset($policy->policyActivatedDate)) {
                    $expiry_date = Carbon::parse($policy->policyActivatedDate)->addYear()->subDays(1)->format('Y-m-d');
                } else {
                    $expiry_date = Carbon::parse($policy->created_at)->addYear()->subDays(1)->format('Y-m-d');
                }

                $status = 'Deactive';
                // if (isset($expiry_date)) {
                //     if ($expiry_date < Carbon::now()) {
                //         $status = 'Deactive';
                //     } else {
                //         $status = 'Active';
                //     }
                // }

                $policy_data = [
                    'premium'      => $policy->premium,
                    'premium_freq' => $policy->premium_freq
                ];

                $policyCon = new PolicyController();
                $premium = $policyCon->getMotorComprehensivePolicyPremium($policy_data);

                $data = [
                    'policy_id' => $policy->id,
                    'term_start_date' => ($policy->policyActivatedDate) ? $policy->policyActivatedDate : $policy->created_at,
                    'term_end_date' => $expiry_date,
                    // 'premium' => $policy->premium,
                    'premium' => $policy->premium,
                    'annual_premium' => isset($premium['annual']) ? round($premium['annual'],2) : null,
                    'vat' => $policy->vat,
                    'vat_percent' => $policy->vat_percent,
                    'renewed_by' => $request->agent_id,
                    'renewals_date' => $expiry_date,
                    'frequency' => $policy->premium_freq,
                    'first_premium' => $policy->first_premium,
                    'billing_start_date' => $policy->billingStartDate,
                    'policy_documents' => NULL,
                    'policyActivatedDate' => $policy->policyActivatedDate,
                    'payment_method' => $request->data['Payment_method'],
                    'payment_reference' => $policy->id,
                    'trans_type' => 'NEW BUSINESS',
                    'status' => $status,
                    'created_at' => Carbon::now()->format('Y-m-d'),

                ];
                $newBusiness_term_id = PolicyTerm::addPolicyTerm($data);
            }
            if( env('APP_STATUS') === 'Production' ){

                $llmapi = new LlmApiCrontroller();
                $llmapi->RegisterCustomer($policy->customer_id,$policy->agent_id);
                $llmapi->SalePolicy($policy->policyNumber);
            }

            switch ($request->data['Payment_method']) {
                case 'VCS':
                    $vcs = new PaymentController;

                    if (isset($request->data['BillingStart']) && $request->data['BillingStart'] == "Immediate") {
                        return $vcs->handlePaymentForStartActivationCodeGenerated($policy->policyNumber, 'start.alphadirect.co.bw', $premium, $request->data['GENERATED_ACTIVATION_CODE'], $request->data['BillingStart'], $request->data['billing_date']);
                    }
                    return $vcs->handlePaymentForStart($policy->policyNumber, 'start.alphadirect.co.bw', null, $request->data['billing_date']);
                    break;
                case 'DPO':

                        if(isset($request->data['pay_email']) && $request->data['pay_email'] != null){
                            $payemail               = new PaymentEmail();
                            $payemail->policyNumber =  $policy->policyNumber;
                            $payemail->pay_email =  $request->data['pay_email'];
                            $payemail->save();
                        }
                        $dpo = new DpoPaymentController;
                    // if (isset($request->data['BillingStart'])) {
                        $request->filter      = 'PolicyNumber';
                        $request->searchValue = $policy->policyNumber;
                        $request->leadSource  = $policy->leadSource;
                        $request->email = $request->data['email'];
                        $pay = $dpo->findPolicyForOnlinePayment($request);
                        return $pay;
                    // }
                    // return response()->json(['success' => 1, 'policyNumber' => $policy->policyNumber, 'product_id' => $policy->product_id], 200);

                    break;
                case 'N-Genius':

                    $Ngenius = new NgeniusPaymentController;
                    $request->filter      = 'PolicyNumber';
                    $request->searchValue = $policy->policyNumber;
                    if (isset($request->data['leadSource'])) {
                        $request->leadSource = $request->data['leadSource'];
                    }

                    $NgeniusReturn = $Ngenius->NgeniusPayment($request);
                    return $NgeniusReturn;

                break;
                case 'Flutterwave':
                    $rave = new FlutterwaveController;
                    return $rave->handlePayment($policy->policyNumber, $product_plan->slug, $product_plan->flutter_plan_id);
                    break;

                case 'Orange':
                    $orangeMoney = new OrangeMoneyController();
                    return $orangeMoney->webPayIntiliazer($policy->policyNumber, $premium);
                    break;

                case 'Orange USSD':
                    return response()->json(['status' => 'success', 'url' => 'thankyou-orange', 'policyNumber' => $policy->policyNumber], 200);
                    break;

                case 'orangeMoney':
                    return response()->json(['status' => 'success', 'url' => 'thankyou-orange', 'policyNumber' => $policy->policyNumber], 200);
                    break;

                case 'RealPay':
                    if(isset($request->data['instant_activate_policy'])) {
                        if(isset($request->data['pay_email']) && $request->data['pay_email'] != null){
                            $payemail               = new PaymentEmail();
                            $payemail->policyNumber =  $policy->policyNumber;
                            $payemail->pay_email =  $request->data['pay_email'];
                            $payemail->save();
                        }
                        $dpo = new DpoPaymentController;
                        $request->filter      = 'PolicyNumber';
                        $request->searchValue = $policy->policyNumber;
                        $request->leadSource  = $policy->leadSource;
                        $request->email = $request->data['email'];
                        $request->requestType = 'instantActivatePolicy';
                        $pay = $dpo->findPolicyForOnlinePayment($request);
                        return $pay;
                        break;
                    } else {
                        $addEvent = $this->realpayPayment($policy);
                        return response()->json(['status' => 'success', 'message' => 'Policy created successfully', 'policyNumber' => $policy->policyNumber], 200);
                        break;
                    }

                default:
                    return Redirect::back()->with('error', 'Please select a payment vendor')
                        ->withInput($request->data);
            }


            return response()->json(['message' => 'First Time User Policy Created Successfuly'], 200);
        // } catch (Exception $ex) {

        //     return response()->json($ex->getMessage(), 500);
        // }
    }

    public function uploadImage(Request $request)
    {
        try {
            $type = htmlspecialchars(strip_tags($request->input('type', NULL)));
            $customerKYCToken = htmlspecialchars(strip_tags($request->input('customerKYCToken', NULL)));
            $deviceToken = htmlspecialchars(strip_tags($request->input('deviceToken', NULL)));
            if ($type == 'KYC') {
                $customer_id = NULL;
                $nrc = htmlspecialchars(strip_tags($request->input('nrc', NULL)));
                $passport = htmlspecialchars(strip_tags($request->input('passport', NULL)));
                $cellphone = htmlspecialchars(strip_tags($request->input('cellphone', NULL)));
                $profile = null;
                if ($cellphone != null) {
                    $profile = Customer::where('cellphone', $cellphone)->orderBy('id', 'asc')->first(array('id'));
                }
                if ($profile != null) {
                    $customer_id = $profile->id;
                }
                //check for nrc and passport
                if ($profile == null) {
                    if ($nrc != null && $passport != null) {
                        $profile = CustomerProfile::where('nrc', $nrc)->orWhere('passport', $passport)->orderBy('id', 'asc')->first(array('customer_id'));
                    } elseif ($nrc == null && $passport != null) {
                        $profile = CustomerProfile::where('passport', $passport)->orderBy('id', 'asc')->first(array('customer_id'));
                    } elseif ($nrc != null && $passport == null) {
                        $profile = CustomerProfile::where('nrc', $nrc)->orderBy('id', 'asc')->first(array('customer_id'));
                    }
                    if ($profile != NULL) {
                        $customer_id = $profile->customer_id;
                    }
                }
                $customerKYC = NULL;
                if ($customer_id != NULL)
                    $customerKYC = KYC::where('customer_id', $customer_id)->first(array('id', 'omang', 'omangBack', 'passport'));
                if ($customerKYCToken != NULL)
                    $customerKYC = KYC::where('id', $customerKYCToken)->first(array('id', 'omang', 'omangBack', 'passport'));

                if ($customerKYC == NULL) {
                    $customerKYC = new KYC();
                }

                if ($request->get('omangKyc') != NULL) {
                    $file = 'data:image/jpeg;base64,' . $request->get('omangKyc');

                    $name = md5(rand(10, 1000)) . time();

                    $imageInfo = explode(";base64,", $file);
                    $imgExt = str_replace('data:image/', '', $imageInfo[0]);
                    $image = str_replace(' ', '+', $imageInfo[1]);
                    $imageName = $name . "." . $imgExt;
                    file_put_contents($imageName, base64_decode($image));

                    $filePath = 'MIS/Customer/omang/' . $imageName;
                    Storage::disk('s3')->put($filePath, file_get_contents($imageName, base64_decode($image)), 'public');
                    $customerKYC->omang = $filePath;
                }
                if ($request->get('omangbackKyc') != NULL) {
                    $file = 'data:image/jpeg;base64,' . $request->get('omangbackKyc');

                    $name = md5(rand(10, 1000)) . time();

                    $imageInfo = explode(";base64,", $file);
                    $imgExt = str_replace('data:image/', '', $imageInfo[0]);
                    $image = str_replace(' ', '+', $imageInfo[1]);
                    $imageName = $name . "." . $imgExt;
                    file_put_contents($imageName, base64_decode($image));

                    $filePath = 'MIS/Customer/omang_back/' . $imageName;
                    Storage::disk('s3')->put($filePath, file_get_contents($imageName, base64_decode($image)), 'public');
                    $customerKYC->omangBack = $filePath;
                }
                if ($request->get('passportKyc') != NULL) {
                    $file = 'data:image/jpeg;base64,' . $request->get('passportKyc');

                    $name = md5(rand(10, 1000)) . time();

                    $imageInfo = explode(";base64,", $file);
                    $imgExt = str_replace('data:image/', '', $imageInfo[0]);
                    $image = str_replace(' ', '+', $imageInfo[1]);
                    $imageName = $name . "." . $imgExt;
                    file_put_contents($imageName, base64_decode($image));

                    $filePath = 'MIS/Customer/passport/' . $imageName;
                    Storage::disk('s3')->put($filePath, file_get_contents($imageName, base64_decode($image)), 'public');
                    $customerKYC->passport = $filePath;
                }
                $customerKYC->save();

                //$customerKYC->omang == NULL ? NULL : 'https://d20dgglp0tqnyi.cloudfront.net'.$customerKYC->omang;
                //$customerKYC->omangBack == NULL ? NULL : 'https://d20dgglp0tqnyi.cloudfront.net'.$customerKYC->omangBack;
                //$customerKYC->passport == NULL ? NULL : 'https://d20dgglp0tqnyi.cloudfront.net'.$customerKYC->passport;
                return response()->json([
                    'status' => 'success',
                    'customerKYCToken' => $customerKYC->id,
                    'omang'            => $customerKYC->omang == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($customerKYC->omang),
                    'omangBack'        => $customerKYC->omangBack == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($customerKYC->omangBack),
                    'passport'         => $customerKYC->passport == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($customerKYC->passport)
                ],  200);
            }
            if ($type == 'DEVICES') {
                if ($deviceToken != NULL)
                    $device = PolicyCellPhone::where('id', $deviceToken)->first();
                else
                    $device = new PolicyCellPhone();
                if ($request->get('cell_phone_front') != NULL) {
                    $file = 'data:image/jpeg;base64,' . $request->get('cell_phone_front');

                    $name = md5(rand(10, 1000)) . time();

                    $imageInfo = explode(";base64,", $file);
                    $imgExt = str_replace('data:image/', '', $imageInfo[0]);
                    $image = str_replace(' ', '+', $imageInfo[1]);
                    $imageName = $name . "." . $imgExt;
                    file_put_contents($imageName, base64_decode($image));

                    $filePath = 'device/Cellphone/front/' . $imageName;
                    Storage::disk('s3')->put($filePath, file_get_contents($imageName, base64_decode($image)), 'public');
                    $device->cell_phone_front = $filePath;
                }
                if ($request->get('cell_phone_back') != NULL) {
                    $file = 'data:image/jpeg;base64,' . $request->get('cell_phone_back');

                    $name = md5(rand(10, 1000)) . time();

                    $imageInfo = explode(";base64,", $file);
                    $imgExt = str_replace('data:image/', '', $imageInfo[0]);
                    $image = str_replace(' ', '+', $imageInfo[1]);
                    $imageName = $name . "." . $imgExt;
                    file_put_contents($imageName, base64_decode($image));

                    $filePath = 'device/Cellphone/back/' . $imageName;
                    Storage::disk('s3')->put($filePath, file_get_contents($imageName, base64_decode($image)), 'public');
                    $device->cell_phone_back = $filePath;
                }
                if ($request->get('cell_phone_left') != NULL) {
                    $file = 'data:image/jpeg;base64,' . $request->get('cell_phone_left');

                    $name = md5(rand(10, 1000)) . time();

                    $imageInfo = explode(";base64,", $file);
                    $imgExt = str_replace('data:image/', '', $imageInfo[0]);
                    $image = str_replace(' ', '+', $imageInfo[1]);
                    $imageName = $name . "." . $imgExt;
                    file_put_contents($imageName, base64_decode($image));

                    $filePath = 'device/Cellphone/left/' . $imageName;
                    Storage::disk('s3')->put($filePath, file_get_contents($imageName, base64_decode($image)), 'public');
                    $device->cell_phone_left = $filePath;
                }
                if ($request->get('cell_phone_right') != NULL) {
                    $file = 'data:image/jpeg;base64,' . $request->get('cell_phone_right');

                    $name = md5(rand(10, 1000)) . time();

                    $imageInfo = explode(";base64,", $file);
                    $imgExt = str_replace('data:image/', '', $imageInfo[0]);
                    $image = str_replace(' ', '+', $imageInfo[1]);
                    $imageName = $name . "." . $imgExt;
                    file_put_contents($imageName, base64_decode($image));

                    $filePath = 'device/Cellphone/right/' . $imageName;
                    Storage::disk('s3')->put($filePath, file_get_contents($imageName, base64_decode($image)), 'public');
                    $device->cell_phone_right = $filePath;
                }
                if ($request->get('cell_phone_top') != NULL) {
                    $file = 'data:image/jpeg;base64,' . $request->get('cell_phone_top');

                    $name = md5(rand(10, 1000)) . time();

                    $imageInfo = explode(";base64,", $file);
                    $imgExt = str_replace('data:image/', '', $imageInfo[0]);
                    $image = str_replace(' ', '+', $imageInfo[1]);
                    $imageName = $name . "." . $imgExt;
                    file_put_contents($imageName, base64_decode($image));

                    $filePath = 'device/Cellphone/top/' . $imageName;
                    Storage::disk('s3')->put($filePath, file_get_contents($imageName, base64_decode($image)), 'public');
                    $device->cell_phone_top = $filePath;
                }
                if ($request->get('cell_phone_bottom') != NULL) {
                    $file = 'data:image/jpeg;base64,' . $request->get('cell_phone_bottom');

                    $name = md5(rand(10, 1000)) . time();

                    $imageInfo = explode(";base64,", $file);
                    $imgExt = str_replace('data:image/', '', $imageInfo[0]);
                    $image = str_replace(' ', '+', $imageInfo[1]);
                    $imageName = $name . "." . $imgExt;
                    file_put_contents($imageName, base64_decode($image));

                    $filePath = 'device/Cellphone/bottom/' . $imageName;
                    Storage::disk('s3')->put($filePath, file_get_contents($imageName, base64_decode($image)), 'public');
                    $device->cell_phone_bottom = $filePath;
                }

                if ($device->save()) {
                    // $device->cell_phone_front == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($device->cell_phone_front);
                    // $device->cell_phone_back == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($device->cell_phone_back);
                    // $device->cell_phone_left == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($device->cell_phone_left);
                    // $device->cell_phone_right == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($device->cell_phone_right);
                    // $device->cell_phone_top == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($device->cell_phone_top);
                    // $device->cell_phone_bottom == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($device->cell_phone_bottom);
                    return response()->json([
                        'status' => 'success',
                        'deviceToken' => $device->id,
                        'cell_phone_front' => $device->cell_phone_front == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($device->cell_phone_front),
                        'cell_phone_back' => $device->cell_phone_back == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($device->cell_phone_back),
                        'cell_phone_left' => $device->cell_phone_left == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($device->cell_phone_left),
                        'cell_phone_right' => $device->cell_phone_right == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($device->cell_phone_right),
                        'cell_phone_top' => $device->cell_phone_top == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($device->cell_phone_top),
                        'cell_phone_bottom' => $device->cell_phone_bottom == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($device->cell_phone_bottom)
                    ], 200);
                } else {
                    return response()->json('Failed to update device Images ,please try again', 400);
                }
            }
        } catch (Exception $ex) {
            return response()->json($ex->getMessage(), 500);
        }
    }


    public function uploadDeviceImages(Request $request)
    {
        try {

            $device = PolicyCellPhone::where('id', $request->get('device_id'))->first();
            if ($request->get('cell_phone_front') != NULL) {
                $file = 'data:image/jpeg;base64,' . $request->get('cell_phone_front');

                $name = $device->id . 'CellphoneFront';

                $imageInfo = explode(";base64,", $file);
                $imgExt = str_replace('data:image/', '', $imageInfo[0]);
                $image = str_replace(' ', '+', $imageInfo[1]);
                $imageName = $name . "." . $imgExt;
                file_put_contents($imageName, base64_decode($image));

                $filePath = 'device/' . $device->customer_id . '/' . 'Cellphone' . '/front' . '/' . $imageName;
                Storage::disk('s3')->put($filePath, file_get_contents($imageName, base64_decode($image)), 'public');
                $device->cell_phone_front = $filePath;
            }
            if ($request->get('cell_phone_back') != NULL) {
                $file = 'data:image/jpeg;base64,' . $request->get('cell_phone_back');

                $name = $device->id . 'CellphoneBack';

                $imageInfo = explode(";base64,", $file);
                $imgExt = str_replace('data:image/', '', $imageInfo[0]);
                $image = str_replace(' ', '+', $imageInfo[1]);
                $imageName = $name . "." . $imgExt;
                file_put_contents($imageName, base64_decode($image));

                $filePath = 'device/' . $device->customer_id . '/' . 'Cellphone' . '/back' . '/' . $imageName;
                Storage::disk('s3')->put($filePath, file_get_contents($imageName, base64_decode($image)), 'public');
                $device->cell_phone_back = $filePath;
            }
            if ($request->get('cell_phone_left') != NULL) {
                $file = 'data:image/jpeg;base64,' . $request->get('cell_phone_left');

                $name = $device->id . 'Cellphoneleft';

                $imageInfo = explode(";base64,", $file);
                $imgExt = str_replace('data:image/', '', $imageInfo[0]);
                $image = str_replace(' ', '+', $imageInfo[1]);
                $imageName = $name . "." . $imgExt;
                file_put_contents($imageName, base64_decode($image));

                $filePath = 'device/' . $device->customer_id . '/' . 'Cellphone' . '/left' . '/' . $imageName;
                Storage::disk('s3')->put($filePath, file_get_contents($imageName, base64_decode($image)), 'public');
                $device->cell_phone_left = $filePath;
            }
            if ($request->get('cell_phone_right') != NULL) {
                $file = 'data:image/jpeg;base64,' . $request->get('cell_phone_right');

                $name = $device->id . 'CellphoneRight';

                $imageInfo = explode(";base64,", $file);
                $imgExt = str_replace('data:image/', '', $imageInfo[0]);
                $image = str_replace(' ', '+', $imageInfo[1]);
                $imageName = $name . "." . $imgExt;
                file_put_contents($imageName, base64_decode($image));

                $filePath = 'device/' . $device->customer_id . '/' . 'Cellphone' . '/right' . '/' . $imageName;
                Storage::disk('s3')->put($filePath, file_get_contents($imageName, base64_decode($image)), 'public');
                $device->cell_phone_right = $filePath;
            }
            if ($request->get('cell_phone_top') != NULL) {
                $file = 'data:image/jpeg;base64,' . $request->get('cell_phone_top');

                $name = $device->id . 'CellphoneTop';

                $imageInfo = explode(";base64,", $file);
                $imgExt = str_replace('data:image/', '', $imageInfo[0]);
                $image = str_replace(' ', '+', $imageInfo[1]);
                $imageName = $name . "." . $imgExt;
                file_put_contents($imageName, base64_decode($image));

                $filePath = 'device/' . $device->customer_id . '/' . 'Cellphone' . '/top' . '/' . $imageName;
                Storage::disk('s3')->put($filePath, file_get_contents($imageName, base64_decode($image)), 'public');
                $device->cell_phone_top = $filePath;
            }
            if ($request->get('cell_phone_bottom') != NULL) {
                $file = 'data:image/jpeg;base64,' . $request->get('cell_phone_bottom');

                $name = $device->id . 'CellphoneBottom';

                $imageInfo = explode(";base64,", $file);
                $imgExt = str_replace('data:image/', '', $imageInfo[0]);
                $image = str_replace(' ', '+', $imageInfo[1]);
                $imageName = $name . "." . $imgExt;
                file_put_contents($imageName, base64_decode($image));

                $filePath = 'device/' . $device->customer_id . '/' . 'Cellphone' . '/bottom' . '/' . $imageName;
                Storage::disk('s3')->put($filePath, file_get_contents($imageName, base64_decode($image)), 'public');
                $device->cell_phone_bottom = $filePath;
            }

            if ($device->save()) {
                return response()->json(['message' => 'Device Updated'], 200);
            } else {
                return response()->json('Failed to update dependents,please try again', 400);
            }
        } catch (Exception $ex) {
            return response()->json($ex->getMessage(), 500);
        }
    }

    public function createDevices(Request $request)
    {
        try {
            $data = array();
            $policy_id = htmlspecialchars(strip_tags($request->policy_id));
            $policy = Policy::where('id', $policy_id)->first(array('customer_id'));
            if ($policy == NULL)
                return response()->json(['message' => 'Invalid Policy Id'], 400);
            $device_type = htmlspecialchars(strip_tags($request->device_type));
            $imei = htmlspecialchars(strip_tags($request->imei));
            $phone_value = htmlspecialchars(strip_tags($request->phone_value));
            $cell_phone_make = htmlspecialchars(strip_tags($request->cell_phone_make));
            $make = DeviceMakeModel::where('id', $cell_phone_make)->first(array('name'));
            if ($make != NULL)
                $cell_phone_make = $make->name;

            $cell_phone_model = htmlspecialchars(strip_tags($request->cell_phone_model));
            $model = DeviceMakeModel::where('id', $cell_phone_model)->first(array('name'));
            if ($model != NULL)
                $cell_phone_model = $model->name;
            $data = [
                'policy_id'        => $policy_id,
                'customer_id'      => $policy->customer_id,
                'device_type'      => $device_type,
                'imei'             => $imei,
                'phone_value'      => $phone_value,
                'cell_phone_make'  => $cell_phone_make,
                'cell_phone_model' => $cell_phone_model,
            ];
            $policyCellPhone = $this->policy_cell_phone_interface->add_new_policy_cell_phone($data);

            if ($policyCellPhone) {
                return response()->json(['message' => 'Device added'], 200);
            } else {
                return response()->json(['message' => 'Failed to add device, please try again'], 400);
            }
        } catch (Exception $exception) {
            return response()->json($exception->getMessage(), 500);
        }
    }
    public function updateDevices(Request $request)
    {
        try {
            $data = array();
            $device_id = htmlspecialchars(strip_tags($request->device_id));
            $device_type = htmlspecialchars(strip_tags($request->device_type));
            $imei = htmlspecialchars(strip_tags($request->imei));
            $phone_value = htmlspecialchars(strip_tags($request->phone_value));
            $cell_phone_make = htmlspecialchars(strip_tags($request->cell_phone_make));
            $make = DeviceMakeModel::where('id', $cell_phone_make)->first(array('name'));
            if ($make != NULL)
                $cell_phone_make = $make->name;
            $cell_phone_model = htmlspecialchars(strip_tags($request->cell_phone_model));
            $model = DeviceMakeModel::where('id', $cell_phone_model)->first(array('name'));
            if ($model != NULL)
                $cell_phone_model = $model->name;
            $data = [
                'device_type'      => $device_type,
                'imei'             => $imei,
                'phone_value'      => $phone_value,
                'cell_phone_make'  => $cell_phone_make,
                'cell_phone_model' => $cell_phone_model,
            ];
            $policyCellPhone = $this->policy_cell_phone_interface->update_policy_cell_phone_by_id($device_id, $data);

            if ($policyCellPhone) {
                return response()->json(['message' => 'Device details updated'], 200);
            } else {
                return response()->json(['message' => 'Failed to update device, please try again'], 400);
            }
        } catch (Exception $exception) {
            return response()->json($exception->getMessage(), 500);
        }
    }

    public function removeDevices(Request $request)
    {
        $device_id = htmlspecialchars(strip_tags($request->device_id));

        $result = PolicyCellphone::where('id', $device_id)->delete();

        if ($result == true) {

            //response for successful deletetion - (Important - status code for Flutter app)
            return response()->json(['message' => 'Policy Devices has been deleted.'], 200);
        } else {

            //response for unsuccessful deletetion - (Important - status code for Flutter app)
            return response()->json(['message' => 'Something went wrong , please try again later'], 401);
        }
    }

    public function insertPolicyLost(Request $request)
    {


        try {
            $handle = fopen(public_path() . "/policyData/policyData.csv", "r");
            $i = 0;
            $policyNumArr = $list = array();

            // Use fgetcsv function along with while loop to get all of the rows in the file
            while (($data = fgetcsv($handle, 100000, ','))) {

                /***
                 *
                 * array:11 [▼
                    0 => "Reference"
                    1 => "Cell"
                    2 => "Name?"
                    3 => "VCS Collection"
                    4 => "In Balance Report?"
                    5 => "Card number in balance report"
                    6 => "Policy"
                    7 => "Payment details received"
                    8 => "Response sent"
                    9 => "Response"
                    10 => "In Graphite?"
                    ]
                 */

                if ($i == 0) {
                    $i++;
                    continue;
                }

                $r = '';
                $referenceNumber = $data[0];
                $cellNumber = str_replace("267", "", $data[1]);
                $name = explode(" ", $data[2]);
                $firstName = isset($name[0]) && $name[0] != '' ? $name[0] : "";
                $lastName = isset($name[1]) && $name[1] != '' ? $name[1] : "";
                $premiumForPolicy = $data[3];
                $planName =   $data[6];
                $PolicyActivationDate = $data[7];
                $settlementDate = $data[8];
                $paymentResponse = $data[9];
                $product_plan = Productplan::where('name', trim($planName))->first();
                $productId =  $product_plan->product_id;
                $product = Product::where('id', $productId)->first(array('has_vehicle', 'has_member', 'premium_type_id', 'limit', 'kyc_recipient', 'kyc_customer', 'preinspection', 'is_motor_items', 'region_id', 'has_activation_code'));
                $user =  Customer::updateOrCreate(array('cellphone' => $cellNumber));
                $user->firstName = $firstName;
                $user->lastName = $lastName;
                $user->cellphone = $cellNumber;
                $user->save();
                $profile = CustomerProfile::updateOrCreate(array('customer_id' => $user->id));
                $profile->customer_id = $user->id;
                $profile->save();

                $customerKYC = KYC::updateOrCreate(array('customer_id' => $user->id));
                $customerKYC->customer_id = $user->id;
                $customerKYC->save();

                //Latestid for Policy Number
                $latest = Policy::orderBy('id', 'desc')->first(array('id'));

                if ($latest == null) {
                    $latest = collect();
                    $latest->id = 1;
                }

                $policy =  new policy();
                $policy->customer_id = $user->id;
                $policy->product_id = $productId;
                $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
                $premium = round(($product_plan->premium * ($regionVat / 100)) + $product_plan->premium, 2);
                $policy->plan_id = $product_plan->id;
                $policy->sum_assured = $product_plan->sum_assured;
                $policy->premium = round($premium, 2);
                $policy->vat = round($product_plan->premium * ($regionVat / 100), 2);
                $policy->vat_percent = $regionVat;
                $policy->policyNumber =  'MIS' . Carbon::now()->year . str_pad((substr($latest->policyNumber, -6) + 1), 6, '0', STR_PAD_LEFT);
                $policy->status = 1;
                $policy->leadSource = 'MobileApp';
                $policy->has_vehicle = $product->has_vehicle;
                $policy->has_member = $product->has_member;
                $policy->preinspection = $product->preinspection;
                $policy->is_motor_items = $product->is_motor_items;
                $policy->limit = $product->limit;
                $policy->kyc_customer = $product->kyc_customer;
                $policy->kyc_recipient = $product->kyc_recipient;
                $policy->policyActivatedDate = date("Y-m-d H:i:s", strtotime(str_replace("/", "-", $settlementDate)));
                $policy->payment_reference = $referenceNumber;
                $policy->note = "restored policy data";

                $saved = $policy->save();
                if ($product->has_vehicle == '1') {
                    $vehicle = new Vehicle();
                    $vehicle->customer_id = $user->id;
                    $vehicle->policy_id = $policy->id;
                    $vehicle->save();
                }

                $banking = new CustomerBanking();
                $banking->customer_id = $user->id;
                $banking->policy_id = $policy->id;
                $saved = $banking->save();

                $policyNumber = $policy->policyNumber;

                $vcs = new VcsTransaction();
                $vcs->policyNumber = $policyNumber;
                $vcs->amount = '1.00';
                $vcs->status = 'SUCCESS';
                $vcs->paymentDescription = $paymentResponse;
                $vcs->save();

                $transaction = new Transaction();
                $transaction->vcsTransaction_id = $vcs->id;
                $transaction->transactionType = 'VCS'; //request->transactionType;
                $transaction->customer_id = $policy->customer_id; //request->transactionType;
                $transaction->policyNumber = $policyNumber; ////request->policyNumber;
                $transaction->amount = '1.00';; ////request->policyNumber;
                $transaction->status = 'SUCCESS';
                $transaction->paymentDescription =  $paymentResponse;
                $transaction->referenceNumber = $referenceNumber;
                $transaction->save();


                Helper::ledgerStore($policy->customer_id, 'POLICY', $policy->id, $policy->product_id, 'NEWBUSINESS');


                $VcsNewTransaction = new VcsNewTransaction();
                $VcsNewTransaction->reference = $referenceNumber;
                $VcsNewTransaction->originalReferenceNumber = $referenceNumber;
                $VcsNewTransaction->name = $firstName . ' ' . $lastName;
                $VcsNewTransaction->amount = $premiumForPolicy;
                $VcsNewTransaction->goods = $planName;
                $VcsNewTransaction->transType = 'First';
                $VcsNewTransaction->terminal_id = '3345';
                $VcsNewTransaction->status = 'Success';
                $VcsNewTransaction->statusRef = $paymentResponse;
                $VcsNewTransaction->authorision_Date = date('Y-m-d', strtotime(str_replace("/", "-", $PolicyActivationDate)));
                $VcsNewTransaction->settlement_Date = $settlementDate;
                $VcsNewTransaction->policyNumber = $policyNumber;
                $VcsNewTransaction->save();


                array_push($list, array(
                    $policyNumber, $referenceNumber
                ));
            }
            fclose($handle);
            $file = fopen(public_path() . "/policyData/policyDataOutput.csv", "w+");
            //calling event for decrement couneter for specific product, plan and store
            if ($saved == true) {
                $store_id = $policy->storeID ? $policy->storeID : NULL;
                $data = [
                    'store_id' => $store_id,
                    'product_id' => $policy->product_id,
                    'plan_id' => $policy->planId,
                ];
                event(new DecrementCounter($data));
            }
            //     if ($product->has_vehicle == '1') {
            //         $vehicle = new Vehicle();
            //         $vehicle->customer_id = $user->id;
            //         $vehicle->policy_id = $policy->id;
            //         $vehicle->save();
            //     }

            //     $banking = new CustomerBanking();
            //     $banking->customer_id = $user->id;
            //     $banking->policy_id = $policy->id;
            //     $saved = $banking->save();

            //     $policyNumber = $policy->policyNumber;

            //     $vcs = new VcsTransaction();
            //     $vcs->policyNumber = $policyNumber;
            //     $vcs->amount = '1.00';
            //     $vcs->status = 'SUCCESS';
            //     $vcs->paymentDescription = $paymentResponse;
            //     $vcs->save();

            //     $transaction = new Transaction();
            //     $transaction->vcsTransaction_id = $vcs->id;
            //     $transaction->transactionType = 'VCS'; //request->transactionType;
            //     $transaction->customer_id = $policy->customer_id; //request->transactionType;
            //     $transaction->policyNumber = $policyNumber; ////request->policyNumber;
            //     $transaction->amount = '1.00';; ////request->policyNumber;
            //     $transaction->status = 'SUCCESS';
            //     $transaction->paymentDescription =  $paymentResponse;
            //     $transaction->referenceNumber = $referenceNumber;
            //     $transaction->save();


            //     Helper::ledgerStore($policy->customer_id, 'POLICY', $policy->id, $policy->product_id, 'NEWBUSINESS');


            //     $VcsNewTransaction = new VcsNewTransaction();
            //     $VcsNewTransaction->reference = $referenceNumber;
            //     $VcsNewTransaction->originalReferenceNumber = $referenceNumber;
            //     $VcsNewTransaction->name = $firstName . ' ' . $lastName;
            //     $VcsNewTransaction->amount = $premiumForPolicy;
            //     $VcsNewTransaction->goods = $planName;
            //     $VcsNewTransaction->transType = 'First';
            //     $VcsNewTransaction->terminal_id = '3345';
            //     $VcsNewTransaction->status = 'Success';
            //     $VcsNewTransaction->statusRef = $paymentResponse;
            //     $VcsNewTransaction->authorision_Date = date('Y-m-d', strtotime(str_replace("/", "-", $PolicyActivationDate)));
            //     $VcsNewTransaction->settlement_Date = $settlementDate;
            //     $VcsNewTransaction->policyNumber = $policyNumber;
            //     $VcsNewTransaction->save();


            //     array_push($list, array(
            //         $policyNumber, $referenceNumber
            //     ));
            // }
            fclose($handle);
            $file = fopen(public_path() . "/policyData/policyDataOutput.csv", "w+");
            foreach ($list as $line) {
                fputcsv($file, $line);
            }
            fclose($file);
            die('Done');
        } catch (Exception $ex) {

            return response()->json($ex->getMessage(), 500);
        }
    }

    public function getCounries(Request $request)
    {
        return Country::all();
    }




    //create a new policy for an existing user
    public function newPolicyRequest(Request $request)
    {
        try {
            //save the policy userId, product_id, planId,activationCode,premium,main
            //Latestid for Policy Number
            $latest = Policy::orderBy('id', 'desc')->first(array('id'));
            if ($latest == null) {
                $latest = collect();
                $latest->id = 1;
            }
            $product = Product::where('id', $request->product_id)->first(array('has_vehicle', 'has_member', 'premium_type_id', 'limit', 'kyc_recipient', 'kyc_customer', 'preinspection', 'is_motor_items', 'region_id', 'has_activation_code'));

            $policy = new Policy();
            $policy->customer_id = $request->userId;
            $policy->product_id = $request->product_id;
            $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;

            if ($product->premium_type_id == '11') {
                $product_plan = Productplan::where('id', $request->planId)->first(array('sum_assured', 'premium'));
                $premium = round(($product_plan->premium * ($regionVat / 100)) + $product_plan->premium, 2);
                $policy->plan_id = $request->planId;
                $policy->sum_assured = $product_plan->sum_assured;
                $policy->premium = $premium;
            } else {
                $premium = ($request->premium * ($regionVat / 100)) + $request->premium;
                $policy->premium = $premium;
            }

            $policy->policyNumber = 'MIS' . Carbon::now()->year . str_pad(($latest->id + 1), 6, '0', STR_PAD_LEFT);
            $policy->status = 0;
            $policy->leadSource = 'Mobile App';
            $policy->has_vehicle = $product->has_vehicle;
            $policy->has_member = $product->has_member;
            $policy->preinspection = $product->preinspection;
            $policy->is_motor_items = $product->is_motor_items;
            $policy->limit = $product->limit;
            $policy->kyc_customer = $product->kyc_customer;
            $policy->kyc_recipient = $product->kyc_recipient;

            if ($product->has_activation_code == '1') {
                $activation = Activation::where('activation_code', $request->activationCode)->first();
                $activation->status = 1;
                $activation->save();

                $Check = CustomerGeneratedActivationCode::where('activation_code', $request->activationCode)->first();
                if (isset($Check) &&  $Check != null) {
                    $Check->status = "1";
                    $Check->save();
                }

                $policy->activation_code = $request->activationCode;
                $policy->serial_code = $activation->serial_code;
            }
            $saved = $policy->save();

            if ($product->has_vehicle == '1') {
                $vehicle = new Vehicle();
                $vehicle->customer_id = $request->userId;
                $vehicle->policy_id = $policy->id;
                $vehicle->vehiclePlate = $request->vehiclePlate;
                $vehicle->save();
            }

            $mains = FactorMain::where('product_id', $request->product_id)->where('status', 1)->get(array('id', 'name', 'type'));
            $requestFactors = $request->factors; // factor_43: 129,factor_44: [2, 3, 5],factor_45: 1,factor_46: 28,factor_47: 1

            foreach ((array) $requestFactors as $key => $value) { // $key = factor_43 , $value = 129

                if (in_array(str_replace("factors_", '', $key), array($mains))) { // 1st parameter = 43, second parameter is mains array (43,55,66)

                    $Request_factor = str_replace("factors_", '', $key); // 43

                    $Request_factor_value = $value; // 129

                    if (is_array($Request_factor_value)) { // is factor value array, 44: [2, 3, 5]
                        foreach ($Request_factor_value as $key => $val) { // get the values 2,3,5
                            foreach ($mains as $main) {
                                $factor = new PolicyFactor();
                                $factor->policy_id = $policy->id;
                                $factor->factor_main_id = $main->id;
                                $factor->name = $main->name;
                                $factor->type = $main->type;
                                $factors_value = FactorSubType::where('main_id', $val)->first(array('name', 'factor'));
                                $factor->factor_value_id = $val;
                                $factor->value_name = $factors_value->name;
                                $factor->factor = $factors_value->factor;
                                $saved = $factor->save();
                            }
                        }
                    } else {
                        foreach ($mains as $main) {
                            $factor = new PolicyFactor();
                            $factor->policy_id = $policy->id;
                            $factor->factor_main_id = $main->id;
                            $factor->name = $main->name;
                            $factor->type = $main->type;
                            $factors_value = FactorSubType::where('main_id', $Request_factor_value)->first(array('name', 'factor'));
                            if ($value == null) {
                                $factor->value_name = $Request_factor_value;
                            } else {
                                $factor->value_name = $factors_value->name;
                                $factor->factor = $factors_value->factor;
                                $factor->factor_value_id = $Request_factor_value;
                            }
                            $saved = $factor->save();
                        }
                    }
                }
            }
            if ($request->main != null) {
                for ($i = 0; $i < count($request->main); $i++) {
                    $policyCover = new PolicyCoverage();
                    $policyCover->policy_id = $policy->id;
                    $policyCover->main = $request->main[$i];
                    $policyCover->coverage_value = $request->cover_value[$i];
                    $policyCover->discount = $request->type[$i];
                    $policyCover->type = $request->disccount_type[$i];
                    $policyCover->value = $request->type_value[$i];
                    $saved = $policyCover->save();
                }
            }

            if ($request->get('members') != null) {

                foreach ($request->get('members') as $key => $member) {
                    if ($member['relation'] != null) {
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
            }

            if ($request->get('beneficiaries') != null) {

                foreach ($request->get('beneficiaries') as $key => $beneficiary) {
                    if ($beneficiary['beneficiaryRelation'] != null) {
                        $b = new PolicyBeneficiary();
                        $b->policy_id = $policy->id;
                        $b->relation = $beneficiary['beneficiaryRelation'];
                        $b->first_name = $beneficiary['beneficiaryFName'];
                        $b->last_name = $beneficiary['beneficiaryLName'];
                        $b->dob = Carbon::parse($beneficiary['beneficiaryDOB'])->format('Y-m-d');
                        $b->gender = $beneficiary['beneficiaryGender'];
                        $b->payment = $beneficiary['beneficiaryPayment'];
                        if(isset($beneficiary['under_18_is_allowed'])){
                            $b->under_18_is_allowed = $beneficiary['under_18_is_allowed'][0];
                        }
                        $b->save();
                    }
                }
            }

            $banking = new CustomerBanking();
            $banking->customer_id = $request->userId;
            $banking->policy_id = $policy->id;
            $banking->billing = $request->get('billingMethod');
            $banking->billingCell = $request->get('billingCell');
            if ($request->get('billingMethod') == 'RealPay') {
                $banking->accountNumber = $request->accountNumber;
                $banking->bankName = $request->bankName;
                $banking->branchCode = $request->branchCode;
                $banking->accountType = $request->bankAccountType;
            } else {
                $banking->accountNumber = '';
                $banking->bankName = '';
                $banking->branchCode = '';
                $banking->accountType = '';
            }

            $saved = $banking->save();

            Helper::ledgerStore($request->userId, 'POLICY', $policy->id, $policy->product_id, 'NEWBUSINESS');

            switch ($request->billingOption) {
                case 'VCS':
                    $vcs = new VcsController;
                    return $vcs->appPayment($policy->policyNumber, $premium, $request->cellphone);
                    break;

                case 'Orange':
                    $orangeMoney = new OrangeMoneyController();
                    return $orangeMoney->webPayIntiliazer($policy->policyNumber, $premium);
                    break;
                case 'RealPay':
                    return response()->json(['policyNumber' => $policy->policyNumber], 200);
                    break;
                default:
                    return Redirect::back()->with('error', 'Please select a payment vendor')
                        ->withInput($request->all());
            }

            return response()->json(['message' => 'Policy creation succesful', 'policyNumber' => $policy->policyNumber, 'premium' => $premium], 200);
        } catch (Exception $ex) {

            return response()->json($ex->getMessage(), 500);
        }
    }

    /* public function preInspectionPhotos(Exception $exception)
    {
        try {
            //front photo, both sides and back sides
        } catch (Exception $ex) {
            return response()->json($ex->getMessage(), 500);
        }
    } */


    /*
    *API to fetch plans for given product id
    */

    public function getProductPlans(Request $request)
    {
        $check = Productplan::where('product_id', $request->get('product_id'))
            ->where('status', 1)->whereNull('is_not_show')
            ->get(array('id', 'slug', 'name', 'premium', 'sum_assured', 'product_id'));
        // Check if we are not trying to delete ourselves

        if ($check->count() == null) {
            // Prepare the error message
            return response()->json('No product plans available', 401);
        } else {
            return response()->json(['productPlans' => $check], 200);
        }
    }


    public function getCustomerDocument(Request $request)
    {
        $policy = Policy::where('policyNumber', $request->policyNumber)->first(array('customer_id'));
        if ($policy != NULL) {
            $kyc = KYC::where('customer_id', $policy->customer_id)->first(array('omang', 'omangBack', 'passport', 'proof_income', 'proof_residence', 'driving_license'));
            $kyc->omang = $kyc->omang == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($kyc->omang);
            $kyc->omangBack = $kyc->omangBack == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($kyc->omangBack);
            $kyc->passport = $kyc->passport == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($kyc->passport);
            $kyc->proof_income = $kyc->proof_income == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_income);
            $kyc->proof_residence = $kyc->proof_residence == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_residence);
            $kyc->driving_license = $kyc->driving_license == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($kyc->driving_license);
            return response()->json(['success' => true, 'kyc' => $kyc], 200);
        } else {
            return response()->json(['success' => false, 'Message' => 'Policy Number not found'], 401);
        }
    }



    /*
     * generates activation code
     */
    public function generateActivationCodeAlphaFePay(Request $request)
    {
        $latest_code = Activation::orderBy('id', 'DESC')->first(array('serial_code', 'activation_code', 'group_id'));
        if ($latest_code == null) {
            $serial_code = 'AAAAAA';
            $activation_code = Helper::gen_ustring(10000000, 99999999);
            $group_id = 1;
        } else {
            $serial_code = $latest_code->serial_code;
            $group_id = $latest_code->group_id + 1;
            $activation_code = Helper::gen_ustring(10000000, 99999999);
        }

        // customer block
        $customerData = Customer::where('cellphone', $request->cellphone)->first('is_blocked');
        if (isset($customerData) && $customerData != null) {
            if ($customerData->is_blocked == 1) {
                return response()->json(['activationCode' => 0, 'is_blocked_check' => true], 200);
            }
        }

        for ($i = 1; $i <= $request->get('noofcodes'); $i++) {
            $activation = new Activation();
            if ($latest_code != null) {
                $var = base_convert($serial_code, 36, 10);
                $var++;
                //To check if number in serial code than increament
                while (preg_match('~[0-9]+~', strtoupper(base_convert($var, 10, 36)))) {
                    $var++;
                }
                $serial_code = strtoupper(base_convert($var, 10, 36));
            }
            $activation->group_id = $group_id;
            $activation->serial_code = $serial_code;
            $check = Activation::where('activation_code', $activation_code)->count();
            while ($check > 0) {
                $activation_code = Helper::gen_ustring(10000000, 99999999);
                $check = Activation::where('activation_code', $activation_code)->count();
            }
            $activation->activation_code = $activation_code;
            $activation->vendor = $request->get('vendor');
            $activation->branch = $request->get('branch');
            $activation->rack_no = $request->get('rack_no');
            $activation->trial_periods = $request->get('trial_periods');
            $activation->trial_coverage = $request->get('trial_coverage');
            $activation->country = $request->get('country');
            $activation->city = $request->get('city');
            $activation->state = $request->get('state');
            $activation->product_type_id = Product::where('id', $request->get('product'))->first(array('product_type_id'))->product_type_id; //product_type_id
            $activation->product_id = $request->get('product');
            $activation->premium_type_id = Product::where('id', $request->get('product'))->first(array('premium_type_id'))->premium_type_id;
            $activation->product_plan_id = $request->get('plan');
            $activation->status = 0;
            $saved = $activation->save();
            //To getin If condition
            $latest_code = 1;
        }
        // $check = CustomerGeneratedActivationCode::where('cellphone', $request->get('cellphone'))
        //     ->where('id_number', $request->get('id_number'))
        //     ->Where('status', '0')
        //     ->Where('product_id', $request->get('product'))
        //     ->Where('plan_id', $request->get('plan'))
        //     ->count();

        //if ($check == 0) {
            $activationSave = new CustomerGeneratedActivationCode();
            $activationSave->activation_code = $activation_code;
            $activationSave->id_type = $request->get('id_type');
            $activationSave->id_number = $request->get('id_number');
            $activationSave->cellphone = $request->get('cellphone');
            $activationSave->status = $request->get('status');
            $activationSave->product_id = $request->get('product');
            $activationSave->plan_id = $request->get('plan');
            $activationSaved = $activationSave->save();
            $smsMessaging = new SmsMessaging;
            $smsMessaging->sendActivationCode($request->get('cellphone'), $activation_code);

            if ($saved && $activationSaved) {
                return response()->json(['activationCode' => $activation_code, 'is_blocked_check' => false], 200);
            } else {
                return response()->json('Can not process the activation.Please contact administration. ', 401);
            }
        // } else {
        //     return response()->json('Code already generated.Please try to retrieve. ', 401);
        // }
    }

    /*
    *API to fetch all the products
    */
    public function getProducts()
    {
        $product = Product::where('status', 1)
            ->where('isForStart', 1)
            ->get(array('id', 'name', 'slug', 'premium_type_id', 'image', 'has_activation_code', 'has_member', 'has_vehicle'));
        if ($product != null) {
            return response()->json(['products' => $product], 200);
        } else {
            return response()->json('No product available', 401);
        }
    }

    /*
    *API to fetch all the products
    */
    public function getProductsForStart()
    {
        $product = Product::where(['status' => 1, 'isForStart' => 1])->get(array('id', 'name', 'slug', 'premium_type_id', 'image', 'has_activation_code', 'has_member', 'has_vehicle'));
        if ($product != null) {
            return response()->json(['products' => $product], 200);
        } else {
            return response()->json('No product available', 401);
        }
    }


    /*
    * Function to generate unique id
    */
    private function gen_uuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            Helper::gen_ustring(0, 0xffff),
            Helper::gen_ustring(0, 0xffff),
            // 16 bits for "time_mid"
            Helper::gen_ustring(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            Helper::gen_ustring(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            Helper::gen_ustring(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            Helper::gen_ustring(0, 0xffff),
            Helper::gen_ustring(0, 0xffff),
            Helper::gen_ustring(0, 0xffff)
        );
    }

    //check the bluebook for the license plate of the vehicle
    public function checkBluebook(Request $request)
    {

        try {
            //code...
            $vehicle = Vehicle::where('vehiclePlate', $request->vehiclePlate)->first();

            if ($vehicle->vehicleRegistration == null) {

                return response()->json(['message' => 'Vehicle Bluebook has not been uploaded'], 401);
            } else {
                return response()->json(['message' => 'Bluebook Available', 'vehicleId' => $vehicle->id], 200);
            }
        } catch (Exception $ex) {

            return response()->json($ex->getMesssage(), 500);
        }
    }
    //check for the client KYC
    public function checkKYCCompliance(Request $request)
    {

        try {
            //code...
            $customer = KYC::where('customer_id', $request->userId)->first();

            if ($customer->compliance == 0) {

                return response()->json(['message' => 'Compliance Not Updated', 'status' => 401]);
            } else {

                return response()->json(['message' => 'KYC Compliant', 'status' => 200]);
            }
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage(), 500);
        }
    }
    // function to get claim request number id
    public function requestGlassClaim(Request $request)
    {
        //first step is to check if the vehiclePlate has a bluebook saved
        $checkBlueBook = $this->checkBluebook($request);

        //Latestid for Claim Number
        $latest = Claim::orderBy('id', 'DESC')->first(array('id'));
        if ($latest == null) {
            $latest = collect();
            $latest->id = 1;
        }

        $status_code = $checkBlueBook->status();
        if ($status_code == http_response_code(200)) {
            try {
                //code...
                $vehicle_id = $checkBlueBook->getData()->vehicleId; //get the vehicle id
                $claim = new Claim(); // new claim
                $claim->policy_id = $request->policy_id;
                $claim->customer_id = $request->userId;
                $claim->claim_type = $request->claim_type;
                $claim->claim_number = 'G' . Carbon::now()->year . str_pad(($latest->id + 1), 6, '0', STR_PAD_LEFT);

                if ($claim->save()) {
                    $vehicleClaim = new ClaimVehicle(); // new vehicle claim
                    $vehicleClaim->claim_id = $claim->id;
                    $vehicleClaim->date_of_damage = Carbon::parse($request->date_of_damage)->format('Y-m-d');
                    $vehicleClaim->damage_extent = $request->extent;
                    $vehicleClaim->damage_cause = $request->cause;
                    $vehicleClaim->vehicle_id = $vehicle_id;

                    if ($request->incidentFront) {
                        $pdf_base64 = $request->incidentFront; //get the encoded pdf file string
                        $pdf_decoded = base64_decode($pdf_base64); //decode the file string
                        $path = storage_path() . '/pdf/' . $request->userId . 'incidentFront.pdf'; // storage path
                        $pdf = fopen($path, 'w'); // create the pdf path file using customer id
                        $pdfWrite = fwrite($pdf, $pdf_decoded); // write the pdf file with data
                        fclose($pdf); //close opertaion

                        /**** Start S3 operation */
                        $filelocation = $path; // get the newly created PDF file from public folder
                        $file = File::get($filelocation);

                        $name = $this->gen_uuid() . $request->userId . 'incidentFront.pdf';
                        //$contents = file_get_contents($file);
                        $filePath = 'MIS/' . $request->userId . '/' . 'Claims' . '/' . $vehicleClaim->id . '/' . $name; // name the file
                        Storage::disk('s3')->put($filePath, $file, 'public'); // store to S3
                        $vehicleClaim->front_image = $filePath; // save S3 file path to database
                        $vehicleClaim->front_image_description = $request->front_image_description;
                        File::delete($filelocation); // delete the file from disk

                    }
                    if ($request->incidentBack) {
                        $pdf_base64 = $request->incidentBack; //get the encoded pdf file string
                        $pdf_decoded = base64_decode($pdf_base64); //decode the file string
                        $path = storage_path() . '/pdf/' . $request->userId . 'incidentBack.pdf'; // storage path
                        $pdf = fopen($path, 'w'); // create the pdf path file using customer id
                        $pdfWrite = fwrite($pdf, $pdf_decoded); // write the pdf file with data
                        fclose($pdf); //close opertaion

                        /**** Start S3 operation */
                        $filelocation = $path; // get the newly created PDF file from public folder
                        $file = File::get($filelocation);

                        $name = $this->gen_uuid() . $request->userId . 'incidentBack.pdf';
                        //$contents = file_get_contents($file);
                        $filePath = 'MIS/' . $request->userId . '/' . 'Claims' . '/' . $vehicleClaim->id . '/' . $name; // name the file
                        Storage::disk('s3')->put($filePath, $file, 'public'); // store to S3
                        $vehicleClaim->back_image = $filePath; // save S3 file path to database
                        $vehicleClaim->back_image_description = $request->back_image_description;
                        File::delete($filelocation); // delete the file from disk

                    }
                    if ($request->incidentRight) {
                        $pdf_base64 = $request->incidentRight; //get the encoded pdf file string
                        $pdf_decoded = base64_decode($pdf_base64); //decode the file string
                        $path = storage_path() . '/pdf/' . $request->userId . 'incidentRight.pdf'; // storage path
                        $pdf = fopen($path, 'w'); // create the pdf path file using customer id
                        $pdfWrite = fwrite($pdf, $pdf_decoded); // write the pdf file with data
                        fclose($pdf); //close opertaion

                        /**** Start S3 operation */
                        $filelocation = $path; // get the newly created PDF file from public folder
                        $file = File::get($filelocation);

                        $name = $this->gen_uuid() . $request->userId . 'incidentRight.pdf';
                        $filePath = 'MIS/' . $request->userId . '/' . 'Claims' . '/' . $vehicleClaim->id . '/' . $name; // name the file
                        Storage::disk('s3')->put($filePath, $file, 'public'); // store to S3
                        $vehicleClaim->right_image = $filePath; // save S3 file path to database
                        $vehicleClaim->right_image_description = $request->right_image_description;
                        File::delete($filelocation); // delete the file from disk

                    }

                    if ($request->incidentLeft) {
                        $pdf_base64 = $request->incidentLeft; //get the encoded pdf file string
                        $pdf_decoded = base64_decode($pdf_base64); //decode the file string
                        $path = storage_path() . '/pdf/' . $request->userId . 'incidentLeft.pdf'; // storage path
                        $pdf = fopen($path, 'w'); // create the pdf path file using customer id
                        $pdfWrite = fwrite($pdf, $pdf_decoded); // write the pdf file with data
                        fclose($pdf); //close opertaion

                        /**** Start S3 operation */
                        $filelocation = $path; // get the newly created PDF file from public folder
                        $file = File::get($filelocation);

                        $name = $this->gen_uuid() . $request->userId . 'incidentLeft.pdf';
                        //$contents = file_get_contents($file);
                        $filePath = 'MIS/' . $request->userId . '/' . 'Claims' . '/' . $vehicleClaim->id . '/' . $name; // name the file
                        Storage::disk('s3')->put($filePath, $file, 'public'); // store to S3
                        $vehicleClaim->left_image = $filePath; // save S3 file path to database
                        $vehicleClaim->left_image_description = $request->left_image_description;
                        File::delete($filelocation); // delete the file from disk

                    }
                    $saved = $vehicleClaim->save();

                    return response()->json(['message' => 'Vehicle claim submission successful, Claim Request number' . $claim->claim_number], 200);
                } else {
                    return response()->json(['message' => 'Something went wrong, Please try again'], 400);
                }
            } catch (\Exception $ex) {
                //throw $th;
                return response()->json($ex->getMessage(), 500);
            }
        } else {

            return response()->json($checkBlueBook->getData()->message, $status_code);
        }
    }

    public function requestThirdPartyAccidentClaim(Request $request)
    {

        $checkBlueBook = $this->checkBluebook($request);

        //Latestid for Claim Number
        $latest = Claim::orderBy('id', 'DESC')->first(array('id'));
        if ($latest == null) {
            $latest = collect();
            $latest->id = 1;
        }

        $status_code = $checkBlueBook->status();
        if ($status_code == http_response_code(200)) {
            try {
                //code...
                $vehicle_id = $checkBlueBook->getData()->vehicleId; //get the vehicle id
                $claim = new Claim(); // new claim
                $claim->policy_id = $request->policy_id;
                $claim->customer_id = $request->customer_id;
                $claim->claim_type = $request->claim_type;
                $claim->claim_number = 'G' . Carbon::now()->year . str_pad(($latest->id + 1), 6, '0', STR_PAD_LEFT);

                if ($claim->save()) {
                    $claimAccident = new ClaimAccident(); // accident claim
                    $claimAccident->claim_id = $claim->id;
                    $claimAccident->loss_description = $request->description;
                    $claimAccident->claim_type = $request->claim_type;
                    $claimAccident->cause = $request->cause;
                    $claimAccident->incident_date = Carbon::parse($request->incidentDate)->format('Y-m-d');

                    if ($request->policeReport) {
                        $pdf_base64 = $request->policeReport; //get the encoded pdf file string
                        $pdf_decoded = base64_decode($pdf_base64); //decode the file string
                        $path = storage_path() . '/pdf/' . $request->userId . 'policeReport.pdf'; // storage path
                        $pdf = fopen($path, 'w'); // create the pdf path file using customer id
                        $pdfWrite = fwrite($pdf, $pdf_decoded); // write the pdf file with data
                        fclose($pdf); //close opertaion

                        /**** Start S3 operation */
                        $filelocation = $path; // get the newly created PDF file from public folder
                        $file = File::get($filelocation);

                        $name = $this->gen_uuid() . $request->userId . 'policeReport.pdf';
                        //$contents = file_get_contents($file);
                        $filePath = 'MIS/' . $request->userId . '/' . 'Claims' . '/' . $claimAccident->id . '/' . $name; // name the file
                        Storage::disk('s3')->put($filePath, $file, 'public'); // store to S3
                        $claimAccident->document = $filePath; // save S3 file path to database

                        File::delete($filelocation); // delete the file from disk

                    }
                    $claimAccident->date_of_accident = Carbon::parse($request->date_of_accident)->format('Y-m-d');

                    if ($request->third_party == null) { // check for third
                        $claimAccident->third_party = "0";
                    } else {
                        $claimAccident->third_party = "1";
                        /* storing third party details  */
                        $extra_details = $request->get('extra_details');
                        foreach ($extra_details as $key => $details) { // array of third party details

                            $thirdparty = new ClaimThirdParty();
                            $thirdparty->claim_id = $claim->id;
                            $thirdparty->name = $details['name'];
                            $thirdparty->address = $details['address'];
                            $thirdparty->cellphone = $details['cellphone'];
                            $thirdparty->registration_no = $details['plate'];
                            $thirdparty->make = $details['make'];
                            $thirdparty->model = $details['model'];
                            $thirdparty->damage_details = $details['damage_details'];
                            $thirdparty->injured_name = $details['injured_name'];
                            $thirdparty->relationship = $details['relationship'];
                            $thirdparty->hospital_name = $details['hospital_name'];
                            $thirdparty->injured_details = $details['injured_details'];
                            $thirdparty->save();
                        }
                    }

                    $claimAccident->save();

                    /* storing driver details* person who caused the accident */
                    if ($request->accidentDriver) {

                        $accidentDriver = new AccidentDriver();
                        $accidentDriver->claim_id = $claim->id;
                        $accidentDriver->name = $request->driver_name;
                        $accidentDriver->address = $request->driver_address;
                        $accidentDriver->dob = $request->driver_dob;
                        $accidentDriver->cellphone = $request->driver_num;
                        $accidentDriver->purpose = $request->driver_purpose;
                        $accidentDriver->license = $request->driver_license;
                        $accidentDriver->save();
                    }

                    /* storing passenger injured details*/

                    if ($request->get('passengers')) {
                        $passengers = $request->get('passengers');

                        foreach ($passengers as $key => $passenger) {
                            $claimAccidentPasenger = new ClaimAccidentPassenger();
                            $claimAccidentPasenger->claim_id = $claim->id;
                            $claimAccidentPasenger->name = $passenger['passenger_name'];
                            $claimAccidentPasenger->address = $passenger['passenger_address'];
                            $claimAccidentPasenger->injury = $passenger['passenger_injury'];
                            $claimAccidentPasenger->save();
                        }
                    }
                    // send sms here OTP code
                    return response()->json(['message' => 'Third Party ' . $request->claim_type . ' claim submited successfully, Your Claim Number is: ' . $claim->claim_number], 200);
                } else {
                    return response()->json(['message' => 'Something went wrong, Please try again'], 400);
                }
            } catch (\Exception $ex) {
                return response()->json(['message' => $ex->getMessage()], 500);
            }
        } else {
            return response()->json($checkBlueBook->getData()->message, $status_code);
        }
    }

    public function requestLifeClaim(Request $request)
    {
        //check for complaince
        $policy = Policy::where('id', $request->policy_id)->first(array('has_vehicle', 'customer_id', 'agent_id', 'kyc_recipient'));
        $compliance = $this->checkKYCCompliance($request);
        $latest = Claim::orderBy('id', 'DESC')->first(array('id'));
        if ($latest == null) {
            $latest = collect();
            $latest->id = 1;
        }
        $status_code = $compliance->status();
        if ($status_code == http_response_code(200)) {
            try {
                //code...
                $claim = new Claim();
                $claim->policy_id = $request->policy_id;
                $claim->customer_id = $request->userId;
                $claim->claim_type = $request->claim_type;
                $claim->claim_number = 'G' . Carbon::now()->year . str_pad(($latest->id + 1), 6, '0', STR_PAD_LEFT);

                if ($claim->save()) {
                    $lifeclaim = new ClaimLife();
                    $lifeclaim->claim_id = $claim->id;
                    $lifeclaim->date_of_death = Carbon::parse($request->date_of_death)->format('Y-m-d');
                    $lifeclaim->cause_of_death = $request->cause;
                    $lifeclaim->description = $request->description;

                    if ($request->death_certificate) {
                        $pdf_base64 = $request->death_certificate; //get the encoded pdf file string
                        $pdf_decoded = base64_decode($pdf_base64); //decode the file string
                        $path = storage_path() . '/pdf/' . $request->userId . '_deathCertificate.pdf'; // storage path
                        $pdf = fopen($path, 'w'); // create the pdf path file using customer id
                        $pdfWrite = fwrite($pdf, $pdf_decoded); // write the pdf file with data
                        fclose($pdf); //close opertaion

                        /**** Start S3 operation */
                        $filelocation = $path; // get the newly created PDF file from public folder
                        $file = File::get($filelocation);

                        $name = $this->gen_uuid() . $request->userId . '_deathCertificate.pdf';
                        //$contents = file_get_contents($file);
                        $filePath = 'MIS/' . $request->userId . '/' . 'Claims' . '/' . $lifeclaim->id . '/' . $name; // name the file
                        Storage::disk('s3')->put($filePath, $file, 'public'); // store to S3
                        $lifeclaim->certificate = $filePath; // save S3 file path to database
                        File::delete($filelocation); // delete the file from disk

                    }

                    $saved = $lifeclaim->save();
                    if ($saved) {
                        //delete file here in local storage
                        //send sms here OTP code
                        return response()->json(['message' => 'Submission for Life Claim Successful'], 200);
                    }
                } else {
                    return response()->json(['message' => 'Something went wrong, Please try again'], 400);
                }
            } catch (\Exception $ex) {
                return response()->json($ex->getMessage(), 500);
            }
        } else {
            return response()->json($compliance->content(), $status_code);
        }
    }
    public function createPDF($encodedPdf)
    {

        try {

            $pdf_base64 = $encodedPdf;
            //Get File content from txt file
            //$pdf_base64_handler = fopen($pdf_base64,'r');
            //$pdf_content = fread ($pdf_base64_handler,filesize($pdf_base64));
            // fclose ($pdf_base64_handler);
            //Decode pdf content
            $pdf_decoded = base64_decode($pdf_base64);
            //Write data back to pdf file
            $path = public_path();
            $pdf = fopen($path . '/pdf/test.pdf', 'w');
            fwrite($pdf, $pdf_decoded);
            //close output file
            fclose($pdf);

            return response()->json(['message' => 'PDF created']);
        } catch (Exception $exception) {
            return response()->json($exception->getMessage(), $exception->getLine());
        }
    }
    public function getPublicFolder()
    {
        $path = storage_path();
        echo $path . '/pdf/ ';
    }

    //check users phone before submiting policy
    public function checkPhoneNumber(Request $request)
    {

        //check if customer cellphone exists
        if (Customer::where('cellphone', '=', $request->cellphone)->exists()) {

            return response()->json('An account with this number already exists, please login.', 409);
        } else {

            return response()->json('Account with phone number does not exist', 200);
        }
    }

    //OUTSIDE the Ap
    public function updatePasswordWithOTP(Request $request)
    {

        $phoneNumber = $request->cellphone;
        $newPassword = $request->password;
        $code = $request->otpCode;

        try {
            //code...
            $userOtp = OTP::where('otp', $code)->first();
            $customer = Customer::where('cellphone', $phoneNumber)->first();

            if ($customer->cellphone == $phoneNumber && $userOtp->otp == $code) {

                $deleteOTP = OTP::where('id', $userOtp->id);
                if ($deleteOTP->delete()) {
                    $customer->password = Hash::make($newPassword);
                    $customer->save();
                    return response()->json(['message' => 'Password Reset Successful'], 200);
                }
            } else {
                return response()->json('OTP verification failed, please try again.', 401);
            }
        } catch (\Exception $ex) {
            return response()->json(['error' => $ex->getMessage()]);
        }
    }
    //inside the APP
    public function updateMobileAppPassword(Request $request)
    {

        $oldPassword = $request->currentPassword;
        $newPassword = $request->newPassword;

        try {
            //code...

            $customer = Customer::findorFail($request->userId);
            //$decrypt = bcrypt($customer->password);
            if (Hash::check($oldPassword, $customer->password) == true) {

                $customer->password = Hash::make($newPassword);

                if ($customer->save()) {
                    return response()->json(['message' => 'Password Set'], 200);
                }
                return response()->json(['message' => 'Failed to set password,try again'], 400);
            } else {
                return response()->json('Password mismatch', 401);
            }
        } catch (\Exception $ex) {
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }

    public function checkPassword(Request $request, $newPassword, $cellphone)
    {
        try {
            //code...
            $customer = Customer::where('cellphone', '=', $request->cellphone)->first();
            $decrypt = Crypt::decrypt($customer->password);
            if ($newPassword == $decrypt) {
                return response()->json(['new password cannot be old password'], 200);
            }
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }

    protected function getProductFactors(Request $request)
    {
        $factors = FactorMain::with('value')->where('product_id', $request->get('id'))->where('status', 1)->get(array('id', 'name', 'type'));
        // Check if we are not trying to delete ourselves

        $product = Product::where('id', $request->get('id'))->first(array('id', 'has_vehicle', 'has_member', 'premium_type_id', 'has_activation_code', 'is_motor_items', 'preinspection', 'kyc_customer', 'sum_insured'));
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

    protected function manageAccount(Request $request)
    {

        try {
            //code...
            $customer = Customer::findorFail($request->userId);
            $customer->firstName = $request->firstName;
            $customer->lastName = $request->lastName;
            $customer->cellphone = $request->cellphone;
            $customer->email = $request->email;

            if ($customer->save()) {
                $customerProfile = CustomerProfile::where('customer_id', $request->userId)->first();
                $customerProfile->dob = Carbon::parse($request->dob)->format('Y-m-d');
                $customerProfile->gender = $request->gender;
                $customerProfile->maritalstatus = $request->maritalstatus;
                if ($request->has('omang')) {
                    $customerProfile->omang = $request->omang;
                }
                if ($request->has('passport')) {
                    $customerProfile->omang = $request->passport;
                }
                $customerProfile->address = $request->address;
                $customerProfile->save();

                return response()->json(['user' => $customer, 'profile' => $customerProfile], 200);
            } else {
                return response()->json('Failed to update user, please try again.', 400);
            }
        } catch (\Exception $ex) {

            return response()->json($ex->getMessage(), 500);
        }
    }

    public function addPolicyFamilyMembers(Request $request)
    {
        //check the product type first

        $policy = Policy::findorFail($request->policy_id);

        $product = Product::findorFail($policy->product_id);
        if ($product->has_member == '1') {
            try {
                //code... get the policy Id

                if ($request['members'] != null) {

                    $members = $request['members'];
                    foreach ($members as $key => $member) {

                        $familyMember = new PolicyMember();
                        $familyMember->policy_id = $request->policy_id;
                        $familyMember->relation = $member['relation'];
                        $familyMember->first_name = $member['memberFName'];
                        $familyMember->last_name = $member['memberLName'];
                        $familyMember->dob = Carbon::parse($member['memberDOB'])->format('Y-m-d');
                        $familyMember->gender = $member['gender'];
                        $familyMember->save();
                    }
                    $familyMembers = PolicyMember::where('policy_id', $policy->id)->get();
                    return response()->json(['policy_members' => $familyMembers], 200);
                }
            } catch (\Exception $ex) {
                //throw $th;
                return response()->json($ex->getMessage(), 404);
            }
        } else {
            return response()->json(['message' => 'Insurance type does not include dependents.'], 400);
        }
    }

    public function addPolicyBeneficiary(Request $request)
    {
        //check the product type first
        /*  $policy = Policy::where('id', $request->policy_id)->first();

        $product = Product::where('id', $policy->product_id)->first(array('id', 'name', 'premium_type_id', 'has_member'));

        if ($product->has_member == '1') { */
        try {
            if ($request['beneficiaries'] != null) {
                $beneficiaries = $request['beneficiaries'];

                foreach ($beneficiaries as $key => $beneficiary) {

                    $policyBeneficiary = new PolicyBeneficiary();
                    $policyBeneficiary->policy_id = $request->policy_id;
                    $policyBeneficiary->relation = isset($beneficiary['beneficiaryRelation']) && $beneficiary['beneficiaryRelation'] != '' ? $beneficiary['beneficiaryRelation'] : '';
                    $policyBeneficiary->first_name = isset($beneficiary['beneficiaryFName']) && $beneficiary['beneficiaryFName'] != '' ? $beneficiary['beneficiaryFName'] : '';
                    $policyBeneficiary->last_name = isset($beneficiary['beneficiaryLName']) && $beneficiary['beneficiaryLName'] != "" ?  $beneficiary['beneficiaryLName'] : "";
                    $policyBeneficiary->dob = isset($beneficiary['beneficiaryDOB']) && $beneficiary['beneficiaryDOB'] != "" ? $beneficiary['beneficiaryDOB'] : "";
                    $policyBeneficiary->gender = isset($beneficiary['beneficiaryGender']) && $beneficiary['beneficiaryGender'] != "" ? $beneficiary['beneficiaryGender'] : "";
                    $policyBeneficiary->payment = isset($beneficiary['beneficiaryPayment']) && $beneficiary['beneficiaryPayment'] != "" ? $beneficiary['beneficiaryPayment'] : "";
                    $policyBeneficiary->omang = isset($beneficiary['beneficiaryOmang']) && $beneficiary['beneficiaryOmang'] != "" ? $beneficiary['beneficiaryOmang'] : "";
                    $policyBeneficiary->passport = isset($beneficiary['beneficiaryPassport']) &&  $beneficiary['beneficiaryPassport'] != "" ? $beneficiary['beneficiaryPassport'] : "";
                    $res =  $policyBeneficiary->save();
                }
                if ($res) {
                    $allBeneficiaries = PolicyBeneficiary::where('policy_id', $policyBeneficiary->policy_id)->get();
                    return response()->json(['message' => 'Beneficiary cover list updated', 'policyBeneficiaries' => $allBeneficiaries], 200);
                } else {
                    return response()->json('Failed to add dependents,please try again', 400);
                }
            }
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage(), 500);
        }
        /*  } else {
            return response()->json(['message' => 'Insurance type does not include dependents.'], 400);
        } */
    }

    public function deleteBeneficiary(Request $request)
    {
        try {

            $beneficiary = PolicyBeneficiary::findorFail($request->beneficiary_id);

            if ($beneficiary->delete()) {
                return response()->json(['message' => 'Person has been removed from your Beneficiary list'], 200);
            } else {
                return response()->json(['message' => 'Failed to delete Beneficairy, please try again'], 404);
            }
        } catch (Exception $ex) {
            return response()->json(['message' => $ex->getMessage()], 500);
        }
    }

    public function removeBenef(Request $request)
    {
        try {
            $beneficiary = PolicyBeneficiary::where('id', $request->beneficiary_id)->first();
            if ($beneficiary) {
                $payment = $beneficiary->payment;
                $policyID = $beneficiary->policy_id;
                if ($beneficiary->delete()) {
                    $beneficiaries = PolicyBeneficiary::where('policy_id', $policyID)->get();
                    if (count($beneficiaries) > 0) {
                        $add = $payment / count($beneficiaries);
                        foreach ($beneficiaries as $b) {
                            $b['payment'] = $b['payment'] + $add;
                            $b->save();
                        }
                    }
                    return response()->json(['message' => 'Person has been removed from your Beneficiary list'], 200);
                } else {
                    return response()->json(['message' => 'Failed to delete Beneficairy, please try again'], 404);
                }
            } else {
                return response()->json(['message' => 'Beneficiary not found, please try again'], 404);
            }
        } catch (Exception $ex) {
            return response()->json(['message' => $ex->getMessage()], 500);
        }
    }

    public function updateFamilyMember(Request $request)
    {
        try {
            $familyMember = PolicyMember::findorFail($request->familyMember_id);
            $familyMember->relation = $request->relation;
            $familyMember->first_name = $request->memberFName;
            $familyMember->last_name = $request->memberLName;
            $familyMember->dob = Carbon::parse($request->memberDOB)->format('Y-m-d');
            $familyMember->gender = $request->memberGender;

            if ($familyMember->save()) {
                return response()->json(['message' => 'Family Memebr details updated', 'familyMember' => $familyMember], 200);
            } else {
                return response()->json('Failed to update dependents,please try again', 400);
            }
        } catch (Exception $exception) {
            return response()->json($exception->getMessage(), 500);
        }
    }

    public function deleteFamilyMember(Request $request)
    {
        try {
            $familyMember = PolicyMember::findorFail($request->familyMember_id);
            $familyMember->delete();
            return response()->json(['message' => 'Family Member removed from cover list'], 200);
        } catch (Exception $exception) {
            return response()->json($exception->getMessage(), 500);
        }
    }

    public function calculatePremium(Request $request, $productId)
    {
        $product = Product::findorFail($productId);
        if ($product == null) {
            // Prepare the error message
            return response()->json(['message' => 'product has no formula'], 400);
        } else {
            $selectedFactorsValues = $request->get('factors');
            //Get all factor main Ids from formula in output
            preg_match_all('~_(.*?)]]~', $product->formula, $output);
            $parameter = array();
            $replace_parameter = array();
            $factors = FactorMain::whereIn('id', $output[1])->get(array('id', 'name', 'type'));
            foreach ($factors as $factor) {
                $factor->name = str_replace(' ', '', $factor->name);
                $factor->name = str_replace('?', '', $factor->name);
                $msg_feild = '[[' . $factor->name . '_' . $factor->id . ']]';
                array_push($parameter, $msg_feild);
                if ($factor->type == 'Input Field') {
                    $inputs = $request->get('input');
                    foreach ($inputs as $input) {
                        $value = explode('_', $input);
                        if ($value[1] == $factor->id) {
                            $msg_replace_feilds = $value[2];
                        }
                    }
                } else {
                    $msg_replace_feilds = FactorSubType::where('main_id', $factor->id)->whereIn('id', explode(',', $selectedFactorsValues))->sum('factor');
                }
                array_push($replace_parameter, (int) $msg_replace_feilds);
            }
            $math = str_replace($parameter, $replace_parameter, $product->formula);
            // H1 (pentest) — the formula string is executed via eval(), so a
            // tampered product.formula could inject PHP (RCE). Restrict the
            // fully-substituted expression to a STRICT arithmetic whitelist
            // (digits, + - * / ( ) . and spaces) before eval().
            $mathTrimmed = trim($math);
            if ($mathTrimmed === '' || !preg_match('/^[0-9+\-*\/(). ]+$/', $mathTrimmed)) {
                \Log::warning('Premium formula rejected — non-arithmetic content');
                return response()->json(['response' => '0', 'message' => 'Invalid premium formula.'], 422);
            }
            $result = 0;
            eval('$result = (' . $mathTrimmed . ');');
            return response()->json(['premium' => $result], 200);
        }
    }

    public function getUserApplications(Request $request)
    {

        $policyDetails = Policy::where('customer_id', $request->customerId)->with('kyc', 'policyMembers', 'policyBeneficiaries', 'product', 'vehicle')->get(array('id', 'customer_id', 'product_id', 'plan_id', 'policyNumber', 'trial_period', 'trial_coverage', 'policyActivatedDate', 'has_vehicle', 'has_member', 'balance', 'limit', 'premium', 'sum_assured', 'status'));
        $customer_kyc = KYC::where('customer_id', $request->customerId)->first();

        return response()->json($policyDetails);
    }


    public function verifyActivationCode($activationCode)
    {
        try {
            //code...
            if (Activation::where('activation_code', '=', $activationCode)->exists()) {

                return response()->json('Activation code valid', 200);
            } else {

                return response()->json('Activation code has expired.', 400);
            }
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage(), 500);
        }
    }

    public function checkIdentity(Request $request)
    {
        try {
            if ($request->get('omang') != null && $request->get('passport') != null) {
                $profile = CustomerProfile::where('omang', $request->get('omang'))->orwhere('passport', $request->get('passport'))->first();
            } elseif ($request->get('omang') != null && $request->get('passport') == null) {
                $profile = CustomerProfile::where('omang', $request->get('omang'))->first();
            } elseif ($request->get('omang') == null && $request->get('passport') != null) {
                $profile = CustomerProfile::where('passport', $request->get('passport'))->first();
            }

            if ($profile != null) {
                $customerData = Customer::where('id', $profile->customer_id)->first(array('firstName', 'lastName', 'email', 'cellphone'));
                return response()->json(['message' => 'Account exists', 'count' => $profile, 'customerData' => $customerData], 409);
            } else {
                return response()->json(['message' => 'Account does not exist'], 200);
            }
        } catch (Exception $ex) {
            return response()->json($ex->getMessage(), 500);
        }
    }

    public function checkCustomer(Request $request)
    {
        try {

            $AdminCustomerController = new AdminCustomerController();
            $customerExists = $AdminCustomerController->checkExsitingCustomer($request);

            if (isset($customerExists)) {
                if ($customerExists['customerExists'] == 1) {

                    $policyInvalid = $AdminCustomerController->PolicyBusinessValidations($customerExists['count']->customer_id, $request->product_id);
                    if($policyInvalid)
                    {
                        return response()->json(['policy_exists' => 1, 'title' => 'Policy exists', 'message' => 'The customer already has purchased the specified product'], 403);
                    }

                    // $customerData = Customer::where('id', $profile->customer_id)->first(array('firstName', 'middleName', 'lastName', 'email', 'cellphone'));
                    // $userKyc = KYC::where('customer_id', $profile->customer_id)->first('compliance');
                    return response()->json(['message' => 'Account exists',  'policy_product' => $customerExists['policy_product'], 'count' => $customerExists['count'], 'customerData' => $customerExists['customerData'], 'userkyc' => $customerExists['userkyc'], 'kyc' => $customerExists['kyc'], 'customerExists' => $customerExists['customerExists'], 'flow_id' => $customerExists['flow_id'],'policyNumber'=> null], 200);
                } else if ($customerExists['customerExists'] == 2) {
                    return response()->json(['message' => 'Account exists',  'policy_product' => $customerExists['policy_product'], 'count' => $customerExists['count'], 'customerData' => $customerExists['customerData'], 'userkyc' => $customerExists['userkyc'], 'kyc' => $customerExists['kyc'], 'customerExists' => $customerExists['customerExists'], 'flow_id' => $customerExists['flow_id'],'policyNumber'=> $customerExists['policyNumber']], 200);
                }
                else {
                    return response()->json(['message' => 'Account does not exist', 'kyc' => $customerExists['kyc'], 'customerExists' => $customerExists['customerExists'],'flow_id' => $customerExists['flow_id']], 200);
                }
            }
            else {
                return response()->json(['message' => 'Something went wrong'], 401);
            }
        } catch (Exception $ex) {
            return response()->json($ex->getMessage(), 500);
        }
    }


    public function uploadBluebook(Request $request)
    {
        try {

            $vehicle = Vehicle::where('vehiclePlate', $request->vehicePlate)->first();
        } catch (Exception $ex) {
            return response()->json($ex->getMessage(), 500);
        }
    }

    public function getCustomerInfo(Request $request)
    {

        $policy = Policy::with('profile')->where('policyNumber', $request->policyNumber)->first();

        return response()->json(['omang' => $policy->profile->omang]);
    }

    public function getAgentPolicies(Request $request)
    {
        $policy =  Policy::where('agent_id', $request->agentId)->with('customer', 'transactions', 'plan')->orderBy('id', 'DESC')->take(10)->get();

        foreach ($policy as $pol) {

            $paymentStatus = 'Payment not initiated';
            $paymentMethod = 'N/A';

            $transaction = PaymentTransaction::where('policyNumber', $pol->policyNumber)->orderBy('id', 'DESC')->first(array('status', 'paymentMethod'));
            if ($transaction != NULL) {
                $paymentStatus = $transaction->status;
                $paymentMethod = $transaction->paymentMethod;
            } else {
                $transaction = Transaction::where('policyNumber', $pol->policyNumber)->orderBy('id', 'DESC')->first();
                if ($transaction != NULL) {
                    if ($transaction->orangeTransaction_id != null) {
                        $paymentMethod = 'Orange Money';
                        $paymentStatus = ucfirst($transaction->status);
                    } elseif ($transaction->vcsTransaction_id != null) {
                        $paymentMethod = 'VCS';
                        $paymentStatus = ucfirst($transaction->status);
                    } elseif ($transaction->flutterwave_id != null) {
                        $paymentMethod = 'Flutter Wave';
                        $paymentStatus = ucfirst($transaction->status);
                    } elseif ($transaction->realPayTransaction_id != null) {
                        $paymentMethod = 'RealPay';
                        switch ($transaction->status) {
                            case 'a':
                                $paymentStatus = 'Active';
                                break;
                            case 'I':
                                $paymentStatus = 'Cancelled';
                                break;
                            case 's':
                                $paymentStatus = 'Success';
                                break;
                            case 'A':
                                $paymentStatus = 'Active';
                                break;
                            default:
                                $paymentStatus = ucfirst($transaction->status);
                                break;
                        }
                    }
                }
            }

            $pol['paymentStatus'] = $paymentStatus;
            $pol['paymentMethod'] = $paymentMethod;
        }

        return response()->json($policy);
    }

    public function getPolicyTransactions(Request $request)
    {
        if (isset($request->user_id) &&  $request->user_id != null) {
            $policies = Policy::where('customer_id', $request->user_id)->get(array('policyNumber'));

            $policy =  PaymentTransaction::whereIn('policyNumber', $policies->pluck('policyNumber'))->orderBy('id', 'DESC')->get(array('policyNumber', 'amount', 'paymentDate', 'status', 'note', 'referenceNumber', 'paymentMethod'));
        } else {
            $policy =  PaymentTransaction::where('policyNumber', $request->policyNumber)->orderBy('id', 'DESC')->get(array('policyNumber', 'amount', 'paymentDate', 'status', 'note', 'referenceNumber', 'paymentMethod'));
        }
        return response()->json($policy);
    }


    public function search(Request $request)
    {
        //Search filter from search bar
        $category = $request['filter'];
        $source = isset($request['source']) ? $request['source'] : NULL;
        $controller = new PolicyController();
        switch ($category) {

            case "CustomerName":

                $customerName = $request->searchValue;
                $searchQuery = Customer::orderBy('id', 'DESC');
                if ($source == 'start') //Do not find Motor Comprehensive Policies for Start
                    $searchQuery->whereHas('policy', function ($query) {
                        $query->where('product_id', '!=', '3');
                    });
                $searchResult = $searchQuery->customerPolicies($customerName, 'customerName');
                $resultCount = count($searchResult);

                break;

            case "Customer Name":

                $customerName = $request->searchValue;
                $searchQuery = Customer::orderBy('id', 'DESC');
                if ($source == 'start') //Do not find Motor Comprehensive Policies for Start
                    $searchQuery->whereHas('policy', function ($query) {
                        $query->where('product_id', '!=', '3');
                    });
                $searchResult = $searchQuery->customerPolicies($customerName, 'customerName');
                $resultCount = count($searchResult);

                break;

                //This case had to use the get_object_vars() funtion due to the empty JSON object to now make the variable  $searchResult in this case , countable.
            case "PolicyNumber":

                $policyNumber = $request->searchValue;
                $searchQuery = Policy::orderBy('id', 'DESC');
                if ($source == 'start') //Do not find Motor Comprehensive Policies for Start
                    $searchQuery->where('product_id', '!=', '3');
                $searchResult = $searchQuery->FindPolicy($policyNumber);
                $objKeys = get_object_vars($searchResult);
                $resultCount = count($objKeys);
                if ($resultCount > 0) {
                    $plan = Productplan::where('id', $searchResult->plan_id)->first();
                    $searchResult['plan_id'] = $plan;
                    $data = $controller->getPolicyPaymentDetails($policyNumber);
                    $searchResult['paymentMethod'] = $data['status'] == true ? $data['paymentMethod'] : 'N/A';
                    $searchResult['paymentStatus'] = $data['status'] == true ? $data['paymentStatus'] : 'N/A';
                    if($searchResult->is_bundled == 1){
                            $bundledPolicies = PolicyBundled::where('policy_id',$searchResult->id)->with('product','productPlan')->get();
                            $searchResult['bundled_policies'] = $bundledPolicies;

                    }else{
                        $searchResult['bundled_policies'] = [];
                    }

                }
                break;

                //This case had to use the get_object_vars() funtion due to the empty JSON object to now make the variable  $searchResult in this case , countable.
            case "Policy Number":

                $policyNumber = $request->searchValue;
                $searchQuery = Policy::orderBy('id', 'DESC');
                if ($source == 'start') //Do not find Motor Comprehensive Policies for Start
                    $searchQuery->where('product_id', '!=', '3');
                $searchResult = $searchQuery->FindPolicy($policyNumber);
                $objKeys = get_object_vars($searchResult);
                $resultCount = count($objKeys);
                if ($resultCount > 0) {
                    $plan = Productplan::where('id', $searchResult->plan_id)->first();
                    $searchResult['plan_id'] = $plan;
                    $data = $controller->getPolicyPaymentDetails($policyNumber);
                    $searchResult['paymentMethod'] = $data['status'] == true ? $data['paymentMethod'] : 'N/A';
                    $searchResult['paymentStatus'] = $data['status'] == true ? $data['paymentStatus'] : 'N/A';
                    if($searchResult->is_bundled == 1){
                        $bundledPolicies = PolicyBundled::where('policy_id',$searchResult->id)->with('product','productPlan')->get();
                        $searchResult['bundled_policies'] = $bundledPolicies;

                    }else{
                        $searchResult['bundled_policies'] = [];
                    }
                }
                break;

            case "Cellphone":
                $customerCellphone = $request->searchValue;
                $searchQuery = Customer::orderBy('id', 'DESC');
                if ($source == 'start') //Do not find Motor Comprehensive Policies for Start
                    $searchQuery->whereHas('policy', function ($query) {
                        $query->where('product_id', '!=', '3');
                    });
                $searchResult = $searchQuery->customerPolicies($customerCellphone, 'cellPhone');
                $resultCount = count($searchResult);

                break;
             case "CustomerEmail":
                $customerEmail = $request->searchValue;
                $searchQuery = Customer::orderBy('id', 'DESC');
                if ($source == 'start') //Do not find Motor Comprehensive Policies for Start
                    $searchQuery->whereHas('policy', function ($query) {
                        $query->where('product_id', '!=', '3');
                    });
                $searchResult = $searchQuery->customerPolicies($customerEmail, 'email');
                $resultCount = count($searchResult);

                break;

            case "Vehicle Plate":

                $vehiclePlate = $request->searchValue;
                $searchQuery = Vehicle::join('policies', 'vehicle.policy_id', 'policies.id')
                    ->where('vehicle.vehiclePlate', $vehiclePlate)
                    ->where('policies.status', '!=', '2');
                if ($source == 'start') //Do not find Motor Comprehensive Policies for Start
                    $searchQuery->where('policies.product_id', '!=', '3');
                $searchResult = $searchQuery->first();
                if (isset($searchResult->policyNumber) && $searchResult->policyNumber != null) {
                    $searchResult = Policy::orderBy('id', 'DESC')->FindPolicy($searchResult->policyNumber);
                    $objKeys = get_object_vars($searchResult);
                    $resultCount = count($objKeys);
                    if ($resultCount > 0) {
                        $plan = Productplan::where('id', $searchResult->plan_id)->first();
                        $searchResult['plan_id'] = $plan;
                        $data = $controller->getPolicyPaymentDetails($searchResult->policyNumber);
                        $searchResult['paymentMethod'] = $data['status'] == true ? $data['paymentMethod'] : 'N/A';
                        $searchResult['paymentStatus'] = $data['status'] == true ? $data['paymentStatus'] : 'N/A';
                    }
                } else {
                    $resultCount  = 0;
                }
                break;

            default:

                return response()->json(['No case found'], 404);

                break;
        }

        if ($resultCount == 0) {

            return response()->json('No Results found', 201);
        }
        /* if($resultCount == 1){
            $plan = Productplan::where('id', $searchResult->plan_id)->first();
            $searchResult['plan_id'] = $plan;
            return response()->json($searchResult);
        }*/
        if ($resultCount > 0) {
            foreach ($searchResult as $key => $result) {
                if (isset($result['policy']) && !empty($result['policy'])) {
                    foreach ($result['policy'] as $key => $pol) {
                        $product = Product::where('id', $pol['product_id'])->first();
                        $plan = Productplan::where('id', $pol['plan_id'])->first();
                        $pol['product_id'] = $product;
                        $pol['plan_id'] = $plan;
                        $data = $controller->getPolicyPaymentDetails($pol['policyNumber']);
                        $pol['paymentMethod'] = $data['status'] == true ? $data['paymentMethod'] : 'N/A';
                        $pol['paymentStatus'] = $data['status'] == true ? $data['paymentStatus'] : 'N/A';
                    }
                }
            }
            return response()->json($searchResult);
        }
    }

    public function getHospitalCashbackPremium(Request $request) {
        $premiumPayment = new PaymentController();
        $premiumAndRelation = $premiumPayment->getHospitalCashbackPremiumByProductPlan($request->plan);

        return response()->json($premiumAndRelation);
    }

    public function searchTwo(Request $request)
    {
        //Search filter from search bar
        $category = $request['filter'];
        $source = isset($request['source']) ? $request['source'] : NULL;
        $controller = new PolicyController();
        switch ($category) {

            case "CustomerName":

                $customerName = $request->searchValue;
                $searchQuery = Customer::orderBy('id', 'DESC');
                // if ($source == 'start') //Do not find Motor Comprehensive Policies for Start
                //     $searchQuery->whereHas('policy', function ($query) {
                //         $query->where('product_id', '!=', '3');
                //     });
                $searchResult = $searchQuery->customerPolicies($customerName, 'customerName');
                $resultCount = count($searchResult);

                break;

            case "Customer Name":

                $customerName = $request->searchValue;
                $searchQuery = Customer::orderBy('id', 'DESC');
                // if ($source == 'start') //Do not find Motor Comprehensive Policies for Start
                //     $searchQuery->whereHas('policy', function ($query) {
                //         $query->where('product_id', '!=', '3');
                //     });
                $searchResult = $searchQuery->customerPolicies($customerName, 'customerName');
                $resultCount = count($searchResult);

                break;

                //This case had to use the get_object_vars() funtion due to the empty JSON object to now make the variable  $searchResult in this case , countable.
            case "PolicyNumber":

                $policyNumber = $request->searchValue;
                $searchQuery = Policy::orderBy('id', 'DESC');
                // if ($source == 'start') //Do not find Motor Comprehensive Policies for Start
                //     $searchQuery->where('product_id', '!=', '3');
                $searchResult = $searchQuery->FindPolicy($policyNumber);
                $objKeys = get_object_vars($searchResult);
                $resultCount = count($objKeys);
                if ($resultCount > 0) {
                    $plan = Productplan::where('id', $searchResult->plan_id)->first();
                    $searchResult['plan_id'] = $plan;
                    $data = $controller->getPolicyPaymentDetails($policyNumber);
                    $searchResult['paymentMethod'] = $data['status'] == true ? $data['paymentMethod'] : 'N/A';
                    $searchResult['paymentStatus'] = $data['status'] == true ? $data['paymentStatus'] : 'N/A';
                }
                break;

                //This case had to use the get_object_vars() funtion due to the empty JSON object to now make the variable  $searchResult in this case , countable.
            case "Policy Number":

                $policyNumber = $request->searchValue;
                $searchQuery = Policy::orderBy('id', 'DESC');
                // if ($source == 'start') //Do not find Motor Comprehensive Policies for Start
                //     $searchQuery->where('product_id', '!=', '3');
                $searchResult = $searchQuery->FindPolicy($policyNumber);
                $objKeys = get_object_vars($searchResult);
                $resultCount = count($objKeys);
                if ($resultCount > 0) {
                    $plan = Productplan::where('id', $searchResult->plan_id)->first();
                    $searchResult['plan_id'] = $plan;
                    $data = $controller->getPolicyPaymentDetails($policyNumber);
                    $searchResult['paymentMethod'] = $data['status'] == true ? $data['paymentMethod'] : 'N/A';
                    $searchResult['paymentStatus'] = $data['status'] == true ? $data['paymentStatus'] : 'N/A';
                }
                break;

            case "Cellphone":
                $customerCellphone = $request->searchValue;
                $searchQuery = Customer::orderBy('id', 'DESC');
                // if ($source == 'start') //Do not find Motor Comprehensive Policies for Start
                //     $searchQuery->whereHas('policy', function ($query) {
                //         $query->where('product_id', '!=', '3');
                //     });
                $searchResult = $searchQuery->customerPolicies($customerCellphone, 'cellPhone');
                $resultCount = count($searchResult);

                break;
             case "CustomerEmail":
                $customerEmail = $request->searchValue;
                $searchQuery = Customer::orderBy('id', 'DESC');
                // if ($source == 'start') //Do not find Motor Comprehensive Policies for Start
                //     $searchQuery->whereHas('policy', function ($query) {
                //         $query->where('product_id', '!=', '3');
                //     });
                $searchResult = $searchQuery->customerPolicies($customerEmail, 'email');
                $resultCount = count($searchResult);

                break;

            case "Vehicle Plate":

                $vehiclePlate = $request->searchValue;
                $searchQuery = Vehicle::join('policies', 'vehicle.policy_id', 'policies.id')
                    ->where('vehicle.vehiclePlate', $vehiclePlate)
                    ->where('policies.status', '!=', '2');
                // if ($source == 'start') //Do not find Motor Comprehensive Policies for Start
                //     $searchQuery->where('policies.product_id', '!=', '3');
                $searchResult = $searchQuery->first();
                if (isset($searchResult->policyNumber) && $searchResult->policyNumber != null) {
                    $searchResult = Policy::orderBy('id', 'DESC')->FindPolicy($searchResult->policyNumber);
                    $objKeys = get_object_vars($searchResult);
                    $resultCount = count($objKeys);
                    if ($resultCount > 0) {
                        $plan = Productplan::where('id', $searchResult->plan_id)->first();
                        $searchResult['plan_id'] = $plan;
                        $data = $controller->getPolicyPaymentDetails($searchResult->policyNumber);
                        $searchResult['paymentMethod'] = $data['status'] == true ? $data['paymentMethod'] : 'N/A';
                        $searchResult['paymentStatus'] = $data['status'] == true ? $data['paymentStatus'] : 'N/A';
                    }
                } else {
                    $resultCount  = 0;
                }
                break;

            default:

                return response()->json(['No case found'], 404);

                break;
        }

        if ($resultCount == 0) {

            return response()->json('No Results found', 201);
        }
        /* if($resultCount == 1){
            $plan = Productplan::where('id', $searchResult->plan_id)->first();
            $searchResult['plan_id'] = $plan;
            return response()->json($searchResult);
        }*/
        if ($resultCount > 0) {
            foreach ($searchResult as $key => $result) {
                if (isset($result['policy']) && !empty($result['policy'])) {
                    foreach ($result['policy'] as $key => $pol) {
                        $product = Product::where('id', $pol['product_id'])->first();
                        $plan = Productplan::where('id', $pol['plan_id'])->first();
                        $pol['product_id'] = $product;
                        $pol['plan_id'] = $plan;
                        $data = $controller->getPolicyPaymentDetails($pol['policyNumber']);
                        $pol['paymentMethod'] = $data['status'] == true ? $data['paymentMethod'] : 'N/A';
                        $pol['paymentStatus'] = $data['status'] == true ? $data['paymentStatus'] : 'N/A';
                    }
                }
            }
            return response()->json($searchResult);
        }
    }
    public function findPolicyAlphaFePay(Request $request)
    {

        //Search filter from search bar
        $category = $request['filter'];

        switch ($category) {

            case "Customer Name":

                $customerName = $request->searchValue;
                $searchResult = Customer::customerPolicies($customerName, 'customerName');
                $resultCount = count($searchResult);

                break;

                //This case had to use the get_object_vars() funtion due to the empty JSON object to now make the variable  $searchResult in this case , countable.
            case "Policy Number":

                $policyNumber = $request->searchValue;
                $searchResult = Policy::FindPolicy($policyNumber);
                $objKeys = get_object_vars($searchResult);
                $resultCount = count($objKeys);

                break;

            case "Cellphone":
                $customerCellphone = $request->searchValue;
                $searchResult = Customer::customerPolicies($customerCellphone, 'cellPhone');
                $resultCount = count($searchResult);

                break;

            default:

                return response()->json(['No case found'], 404);

                break;
        }


        if ($resultCount == 0) {

            return response()->json('No Results found', 201);
        } else {
            return response()->json($searchResult);
        }
    }


    public function getPolicyDetails(Request $request)
    {
        try {
            $policyNumber = $request->policyNumber;
            $policyDetails = Policy::PolicyDetail($policyNumber);

            if ($policyDetails == NULL) {
                return response()->json(['message' => 'Policy details not found.'], 201);
            } else {
                if (isset($request->leadSource) && $request->leadSource == "MobileApp") {
                    if ($policyDetails->product_id == 5 && count($policyDetails->devices) > 0) {
                        foreach ($policyDetails->devices as $device) {
                            $device->cell_phone_front = $device->cell_phone_front != null ? \AlphaDirect\Helper::getCloudFrontURL($device->cell_phone_front) : null;
                            $device->cell_phone_back  = $device->cell_phone_back != null ? \AlphaDirect\Helper::getCloudFrontURL($device->cell_phone_back) : null;
                            $device->cell_phone_left  = $device->cell_phone_left != null ? \AlphaDirect\Helper::getCloudFrontURL($device->cell_phone_left) : null;
                            $device->cell_phone_right = $device->cell_phone_right != null ? \AlphaDirect\Helper::getCloudFrontURL($device->cell_phone_right) : null;
                            $device->cell_phone_top   = $device->cell_phone_top != null ? \AlphaDirect\Helper::getCloudFrontURL($device->cell_phone_top) : null;
                            $device->cell_phone_bottom = $device->cell_phone_bottom != null ? \AlphaDirect\Helper::getCloudFrontURL($device->cell_phone_bottom) : null;
                        }
                    }
                }
                if (isset($policyDetails->profile->dob)) {
                    $policyDetails->profile->dob = Carbon::parse(str_replace("/", "-", $policyDetails->profile->dob))->format('d-m-Y');
                }
                $state = State::where('id', $policyDetails->profile->state)->first('name');
                if (is_numeric($policyDetails->profile->city)) {
                    $city = City::where('id', $policyDetails->profile->city)->first();
                } else {
                    $city = City::where('name', $policyDetails->profile->city)->first();
                }

                $customerKYC = KYC::where('customer_id', $policyDetails->customer_id)->first(array('passportIssuingCountry'));

                if ($state != NULL) {
                    $policyDetails->profile->stateName = $state->name;
                } else {
                    $policyDetails->profile->stateName = NULL;
                }
                if ($policyDetails->profile->maritalstatus) {
                    $policyDetails->profile->maritalstatus = strval($policyDetails->profile->maritalstatus);
                } else {
                    $policyDetails->profile->maritalstatus = NULL;
                }
                if ($policyDetails->policyActivatedDate) {
                    $policyDetails->policyActivatedDateFormatted = Carbon::createFromFormat('Y-m-d H:i:s', $policyDetails->policyActivatedDate)->format('d-m-Y');
                }
                if ($policyDetails->profile->license_valid_till) {
                    $policyDetails->profile->license_valid_till = Carbon::parse($policyDetails->profile->license_valid_till)->format('d-m-Y');
                }
                if ($city != NULL) {
                    $policyDetails->profile->cityName = $city->name;
                    $policyDetails->profile->city =  strval($city->id);
                } else {
                    $policyDetails->profile->cityName = NULL;
                }
                if (isset($customerKYC->passportIssuingCountry) && $customerKYC->passportIssuingCountry != NULL) {
                    $policyDetails->profile->passportIssuingCountry = $customerKYC->passportIssuingCountry;
                } else {
                    $policyDetails->profile->passportIssuingCountry = '-';
                }

                $controller = new PolicyController();
                $data = $controller->getPolicyPaymentDetails($policyNumber);
                $policyDetails['paymentMethod'] = ($data['status'] == true) ? $data['paymentMethod'] : 'N/A';
                $policyDetails['paymentStatus'] = ($data['status'] == true) ? $data['paymentStatus'] : 'N/A';
                return response()->json($policyDetails, 200);
            }
        } catch (Exception $ex) {
            return response()->json(['message' => $ex->getMessage()], 500);
        }
    }

    public function getOutletStores()
    {
        // $branches = Branch::GetBranches();
        $branches = Stores::where('status', 1)->orderBy('appearance_order', 'ASC')->get();
        return response()->json(['branches' => $branches]);
    }


    public function removeBeneficiary(Request $request)
    {
        $beneficiaryID = $request->beneficiary_id;

        $benefeciary = new PolicyBeneficiary();

        //Call to Model delete function , returns boolean of deletetion success
        $result =  $benefeciary->deleteBeneficiary($beneficiaryID);

        if ($result == true) {

            //response for successful deletetion - (Important - status code for Flutter app)
            return response()->json(['message' => 'Policy beneficiary has been deleted.'], 200);
        } else {

            //response for unsuccessful deletetion - (Important - status code for Flutter app)
            return response()->json(['message' => 'Something went wrong , please try again later'], 401);
        }
    }
    public function createBeneficiary(Request $request)
    {

        $benefeciary = new PolicyBeneficiary();

        //Call to Model addbeneficiary function , returns boolean of creation success
        $result =  $benefeciary->addBeneficiary($request);

        if ($result == true) {

            //response for successful creation - (Important - status code for Flutter app)
            return response()->json(['message' => 'Policy beneficiary has been added successfully.'], 200);
        } else {

            //response for unsuccessful creation - (Important - status code for Flutter app)
            return response()->json(['message' => 'Something went wrong , please try again later'], 401);
        }
    }

    public function updateBeneficiary(Request $request)
    {
        $benefeciary = new PolicyBeneficiary();
        //Call to Model addbeneficiary function , returns boolean of update success
        $result =  $benefeciary->updateBeneficiary($request);

        if ($result == true) {
            //response for successful update - (Important - status code for Flutter app)
            return response()->json(['message' => 'Policy beneficiary has been updated successfully.'], 200);
        } else {
            //response for unsuccessful update - (Important - status code for Flutter app)
            return response()->json(['message' => 'Something went wrong , please try again later'], 401);
        }
    }

    public function updatePolicyInformation(Request $request)
    {

        $policy          = new Policy();
        $policy          = $policy->getPolicyDataById($request->policy_id);
        $customerProfile = new CustomerProfile();
        $customer        = new Customer();
        $vehicle         = new Vehicle();
        //Legal Insurance
        if ($policy->product_id == 4) {
            $benefeciary = new PolicyBeneficiary();
            if (isset($request->legalrelation)) {
                $request->relation    = $request->legalrelation;
            }
            if (isset($request->legalFName)) {
                $request->first_name  = $request->legalFName;
            }
            if (isset($request->legalMName)) {
                $request->middle_name = $request->legalMName;
            }
            if (isset($request->legalLName)) {
                $request->last_name   = $request->legalLName;
            }
            if (isset($request->legalDOB)) {
                $request->dob         = $request->legalDOB;
            }
            if (isset($request->legalGender)) {
                $request->gender      = $request->legalGender;
            }
            if (isset($request->legalEmail)) {
                $request->email       = $request->legalEmail;
            }
            if (isset($request->legalOmang)) {
                $request->omang       = $request->legalOmang;
            }
            if (isset($request->legalPassport)) {
                $request->passport    = $request->legalPassport;
            }
            if (isset($request->legalPassportExpiry)) {
                $request->legalPassportExpiry   = $request->legalPassportExpiry;
            }
            if (isset($request->legalOmangExpiry)) {
                $request->legalOmangExpiry   = $request->legalOmangExpiry;
            }
            if (isset($request->legalPhone)) {
                $request->cellphone   = $request->legalPhone;
            }
            $request->payment     = null;
            if (isset($request->beneficiary_id) && $request->beneficiary_id != null) {
                // if(isset($request->legalFName) && $request->legalFName != null){
                //     $result =  $benefeciary->updateBeneficiary($request);
                // }
            } else {
                $result =  $benefeciary->addBeneficiary($request);
            }
        }

        $customerProfile = $customerProfile->updateInfo($request);
        $customer        = $customer->updateInfo($request);

        if ($request->vehicle_id != null) {
            $vehicle =  $vehicle->updateInfo($request);
            if (!$vehicle) {
                return response()->json("Vehicle not found.", 404);
            }
        }
        if ($request->passportIssuingCountry != NULL) {
            $kyc = KYC::where('customer_id', $request->customer_id)->first();
            if ($kyc != NULL) {
                if (isset($request->passportIssuingCountry)) {
                    $kyc->passportIssuingCountry = $request->passportIssuingCountry;
                }

                $kyc->save();
            }
        }

        if ($policy->product_id == 5) {
            $sum = 0;

            if (isset($request->phone_value) && $request->phone_value != null) {

                foreach ($request->phone_value  as $key => $value) {
                    $sum += $value;
                }
            }

            if (isset($request->devices) && $request->devices != null) {

                foreach ($request->devices  as $key => $device) {
                    if ($device['device_type'] != null) {
                        $sum += $device['phone_value'];
                    }
                }
            }
            $policy->sum_assured  = $sum;
            $policy->save();

            // $imeimsg = null;
            if (is_array($request->get('devices')) && count($request->get('devices')) > 0) {
                foreach ($request->devices as $key => $device) {
                    $deviceToken = isset($device['deviceToken']) ? $device['deviceToken'] : null;
                    if ($deviceToken != null) { //For Mobile App
                        $policy_cell_phone = PolicyCellphone::where('id', $deviceToken)->first();
                        if ($policy_cell_phone == null) {
                            return response()->json(['status' => false, 'message' => 'Device token is invalid'], 400);
                        }
                        if ($device['cell_phone_make'] == 'other') {
                            $request['type']       = 'cellphone';
                            $request['category']   = 'make';
                            if (isset($device['device_type'])) {
                                $request['deviceType'] = $device['device_type'];
                            }
                            if (isset($device['optionMake'])) {
                                $request['make']       = $device['optionMake'];
                            }


                            $request['callback']   = 'create_policy';
                        }
                        $cell_phone_make   = $device['cell_phone_make'] == 'other' ? $this->addOption($request) : $device['cell_phone_make'];
                        if ($device['cell_phone_model'] == 'other') {
                            $request['type']       = 'cellphone';
                            $request['category']   = 'model';
                            if (isset($device['device_type'])) {
                                $request['deviceType'] = $device['device_type'];
                            }
                            if (isset($device['optionModel'])) {
                                $request['model']      = $device['optionModel'];
                            }

                            $request['make']       = $cell_phone_make;

                            $request['callback']   = 'create_policy';
                        }
                        $cell_phone_model  = $device['cell_phone_model'] == 'other' ? $this->addOption($request) : $device['cell_phone_model'];

                        $policy_cell_phone->policy_id        = $policy->id;
                        $policy_cell_phone->customer_id      = $policy->customer_id;
                        if (isset($device['device_type'])) {
                            $policy_cell_phone->device_type      = $device['device_type'];
                        }
                        if (isset($device['imei'])) {
                            $policy_cell_phone->imei             = $device['imei'];
                        }
                        if (isset($device['phone_value'])) {
                            $policy_cell_phone->phone_value      = $device['phone_value'];
                        }



                        $policy_cell_phone->cell_phone_make  = $cell_phone_make;
                        $policy_cell_phone->cell_phone_model = $cell_phone_model;


                        /* foreach($data as $key => $value) {
                                if($value != NULL || $value != '') {
                                    $policy_cell_phone->$key = $value;
                                }
                            } */
                        $policy_cell_phone->save();
                    } else {
                        $policyCellPhone                   = new PolicyCellPhone();
                        $policyCellPhone->policy_id        = $policy->id;
                        $policyCellPhone->customer_id      = $policy->customer_id;
                        if (isset($device['device_type'])) {
                            $policyCellPhone->device_type      = $device['device_type'];
                        }
                        if (isset($device['imei'])) {
                            $policyCellPhone->imei             = $device['imei'];
                        }
                        if (isset($device['phone_value'])) {
                            $policyCellPhone->phone_value      = $device['phone_value'];
                        }




                        if ($device['cell_phone_make'] == 'other') {
                            $request['type']       = 'cellphone';
                            $request['category']   = 'make';
                            $request['deviceType'] = $policyCellPhone->device_type;
                            if (isset($device['optionMake'])) {
                                $request['make']       = $device['optionMake'];
                            }

                            $request['callback']   = 'create_policy';
                        }
                        $policyCellPhone->cell_phone_make  = $device['cell_phone_make'] == 'other' ? $this->addOption($request) : $device['cell_phone_make'];
                        if ($device['cell_phone_model'] == 'other') {
                            $request['type']       = 'cellphone';
                            $request['category']   = 'model';
                            $request['deviceType'] = $policyCellPhone->device_type;
                            $request['make']       = $policyCellPhone->cell_phone_make;
                            if (isset($device['optionModel'])) {
                                $request['model']      = $device['optionModel'];
                            }

                            $request['callback']   = 'create_policy';
                        }
                        $policyCellPhone->cell_phone_model = $device['cell_phone_model'] == 'other' ? $this->addOption($request) : $device['cell_phone_model'];

                        if (isset($device['cell_phone_front']) && $device['cell_phone_front'] != NULL) {
                            $policyCellPhone->cell_phone_front = $device['cell_phone_front'];
                        }

                        if (isset($device['cell_phone_left']) && $device['cell_phone_back'] != NULL) {
                            $policyCellPhone->cell_phone_back = $device['cell_phone_back'];
                        }

                        if (isset($device['cell_phone_left']) && $device['cell_phone_left'] != NULL) {
                            $policyCellPhone->cell_phone_left = $device['cell_phone_left'];
                        }

                        if (isset($device['cell_phone_right']) && $device['cell_phone_right'] != NULL) {
                            $policyCellPhone->cell_phone_right = $device['cell_phone_right'];
                        }
                        if (isset($device['cell_phone_top']) && $device['cell_phone_top'] != NULL) {
                            $policyCellPhone->cell_phone_top = $device['cell_phone_top'];
                        }
                        if (isset($device['cell_phone_bottom']) && $device['cell_phone_bottom'] != NULL) {
                            $policyCellPhone->cell_phone_bottom = $device['cell_phone_bottom'];
                        }
                        $policyCellPhone->save();
                    }
                }
            }
        }

        if ($customerProfile == true && $customer == true || $vehicle == true) {

            //response for successful update - (Important - status code for Flutter app)
            return response()->json([
                'message'         => 'Policy details have been updated successfully',
            ], 200);
        } else {
            //response for unsuccessful update - (Important - status code for Flutter app)
            return response()->json(['message' => 'Something went wrong , please try again later'], 401);
        }
    }

    public function updatePolicyInformationPay(Request $request)
    {
        $policy = new Policy();
        $policyData = $policy->getPolicyDataById($request->policy_id);
        $customerProfile = new CustomerProfile();

        $customer = new Customer();
        $customer->cellphone = $request->cellphone;
        $customer->email = $request->email;
        $customer->save();

        $vehicle = new Vehicle();
        $customerProfile =  $customerProfile->updateInfoPay($policyData->customer_id, $request);

        if ($customerProfile == false) {
            return response()->json("We encountered the problem in saving customer profile data.", 500);
        }

        $customer =  $customer->updateInfoPay($policyData->customer_id, $request);

        if ($customer == false) {
            return response()->json("We encountered the problem in saving customer data.", 500);
        }

        $vehicle = new vehicle();
        if ($policyData->has_vehicle == 1) {

            if ($request->vehiclePlate != null) {

                $vehicle =  $vehicle->updateInfoStart($request->policy_id, $request->vehiclePlate);

                if ($vehicle == false) {
                    return response()->json("We encountered the problem in saving vehicle data.", 500);
                }
            }
        }

        return response()->json([
            'message' => 'Policy details have been updated successfully'

        ], 200);
    }

    public function leadStore(Request $request)
    {

        $lead = new PolicyLead();

        $result =  $lead->saveLead($request);

        if ($result == true) {

            //response for successful creation - (Important - status code for Flutter app)
            return response()->json(['message' => 'Lead has been saved successfully.'], 200);
        } else {

            //response for unsuccessful creation - (Important - status code for Flutter app)
            return response()->json(['message' => 'Something went wrong , please try again later'], 401);
        }
    }


    public function getCustomerCellphoneByPolicyNumber(Request $request)
    {

        try {
            //code...

            if ($request->policy_number) {
                $customerDetail = Policy::where('policyNumber', $request->policy_number)->first(['customer_id']);
                if ($customerDetail) {
                    $customer = Customer::where('id', $customerDetail->customer_id)->first(['cellphone']);
                    if ($customer) {
                        $data = $this->getKycDocumentTypes()->getData();
                        $proofResidence = $data->proofResidence;
                        $proofIncome = $data->proofIncome;
                        return response()->json(
                            [
                                'cellphone' => $customer->cellphone,
                                'proofResidence' => $proofResidence,
                                'proofIncome' => $proofIncome,
                            ],
                            200
                        );
                    } else {
                        return response()->json(['message' => 'Customer cellphone not found.'], 401);
                    }
                } else {
                    return response()->json(['message' => 'Customer not found.'], 401);
                }
            } else {
                return response()->json(['message' => 'Policy Number not found.'], 401);
            }
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage(), 500);
        }
    }

    public function getCustomerCellphoneByVehicleNumber(Request $request)
    {

        try {
            //code...

            if ($request->vehiclePlate) {
                $customerDetail = Vehicle::where('vehiclePlate', $request->vehiclePlate)->first(['customer_id']);
                if ($customerDetail) {
                    $customer = DB::select('call getCustomerCelllphone(?)', [$customerDetail->customer_id]);
                    $cellPhone = $customer[0]->cellphone;
                    if ($cellPhone) {
                        return response()->json(
                            [
                                'cellphone' => $cellPhone,
                            ],
                            200
                        );
                    } else {
                        return response()->json(['message' => 'Customer cellphone not found.'], 401);
                    }
                } else {
                    return response()->json(['message' => 'Customer not found.'], 401);
                }
            } else {
                return response()->json(['message' => 'Policy Number not found.'], 401);
            }
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage(), 500);
        }
    }
    //$products = $this->getProducts()->getData()->products;
    public function getKycDocumentTypes()
    {
        try {
            $proofResidence = Master::where('key', 'proof_of_residence')->get(['value']);
            $proofIncome = Master::where('key', 'proof_of_income')->get(['value']);
            return response()->json(
                [
                    'proofResidence' => $proofResidence,
                    'proofIncome' => $proofIncome
                ],
                200
            );
        } catch (\Exception $ex) {
            return response()->json($ex->getMessage(), 500);
        }
    }

    public function checkVehicle(Request $request)
    {
        if ($request->has_vehicle) {
            $count = Vehicle::join('policies', 'policies.id', 'vehicle.policy_id')->where('vehicle.vehiclePlate', $request->get('vehiclePlate'))
                        ->where('policies.status', '!=', 2)->count();
            if ($count > 0)
                return response()->json(['count' => $count], 401);
            else
                return response()->json(['count' => 0], 200);
        } else {
            return response()->json(['message' => 'Vehicle registration number is required'], 401);
        }
    }

    public function checkIfVehicleAlreadyRegistered($vehiclePlate)
    {
        $count = Vehicle::join('policies', 'policies.id', 'vehicle.policy_id')->where('vehicle.vehiclePlate', $vehiclePlate)
                    ->where('policies.status', '!=', 2)->count();
        if ($count > 0)
            return true;
        else
            return false;
    }

    public function checkIfMemberAlreadyRegistered($idType, $idValue)
    {
        $omang = $idType == 'Omang' ? $idValue : null;
        $passport = $idType == 'Passport' ? $idValue : null;
        if ($omang != null || $passport != null) {
            $count = customerprofile::where(strtolower($idType), $idValue)->first();
            if ($count != null) {
                $policyCount = 0;
                $customers = customerprofile::where(strtolower($idType), $idValue)->get();
                foreach ($customers as $customer) {

                    $checkPolicy = Policy::where('customer_id', $customer->customer_id)->where("product_id", "1")->first();
                    if ($checkPolicy == null) {
                        return false;
                    } else {
                        $checkStatus = $checkPolicy->status;
                        if ($checkStatus != null && ($checkStatus != 2)) {
                            $policyCount = $policyCount + 1;
                        }
                    }
                }
                if ($policyCount > 0)
                    return true;
                else
                    return false;
            } else {
                return false;
            }
        }
    }

    public function getBillingDaysForProducts(Request $request)
    {
        if ($request->get('product_id')) {
            $billing = Billing::where('product_id', $request->get('product_id'))->first();
            if ($billing) {
                if ($billing->billing_days) {
                    return response()->json(['days' => $billing->billing_days], 200);
                } else {
                    return response()->json(['message' => 'Billling days found empty'], 401); //3632505
                }
            } else {
                return response()->json(['message' => 'No data found'], 401);
            }
        } else {
            return response()->json(['message' => 'Product found empty'], 401);
        }
    }

    public function getDocTye(Request $request)
    {
        $kycDoc = $this->getKycDocumentTypes()->getData();
        if ($kycDoc) {
            $proofResidence = $kycDoc->proofResidence;
            $proofIncome = $kycDoc->proofIncome;
            return response()->json(
                [
                    'proofResidence' => $proofResidence,
                    'proofIncome' => $proofIncome,
                ],
                200
            );
        } else {
            return response()->json(['message' => 'Doc type not found'], 401);
        }
    }

    public function getDevicesByPolicyNo(Request $request)
    {
        try {
            if ($request->get('policyNumber')) {
                $policy = Policy::where('policyNumber', $request->get('policyNumber'))->first(array('id'));
                if ($policy != NULL) {
                    $devices = PolicyCellPhone::where('policy_id', $policy->id)->get();
                    return response()->json(
                        [
                            'devices' => $devices
                        ],
                        200
                    );
                } else {
                    return response()->json(['message' => 'Policy not found'], 401);
                }
            } else {
                return response()->json(['message' => 'Please provide valid Policy number.'], 401);
            }
        } catch (\Exception $ex) {
            return response()->json($ex->getMessage(), 500);
        }
    }

    public function getCustomerCellphoneById(Request $request)
    {
        try {
            if ($request->get('idType') && $request->get('idNumber')) {
                $customer = Customer::leftJoin('customer_profile', 'customer_profile.customer_id', '=', 'customer.id');
                if ($request->get('idType') == 'Omang')
                    $data = $customer->where('customer_profile.omang', $request->get('idNumber'))->first(['customer.id', 'customer.firstName', 'customer.lastName', 'customer.cellphone']);

                if ($request->get('idType') == 'Passport')
                    $data = $customer->where('customer_profile.passport', $request->get('idNumber'))->first(['customer.id', 'customer.firstName', 'customer.lastName', 'customer.cellphone']);

                if ($data) {
                    $kycDoc = $this->getKycDocumentTypes()->getData();
                    $proofResidence = $kycDoc->proofResidence;
                    $proofIncome = $kycDoc->proofIncome;
                    return response()->json(
                        [
                            'customer' => $data,
                            'proofResidence' => $proofResidence,
                            'proofIncome' => $proofIncome,
                        ],
                        200
                    );
                } else {
                    return response()->json(['message' => 'Customer not found'], 401);
                }
            } else {
                return response()->json(['message' => 'Please provide valid Id and Id number.'], 401);
            }
        } catch (\Exception $ex) {
            return response()->json($ex->getMessage(), 500);
        }
    }

    public function getLookupData(Request $request)
    {
        $data = Lookup::where('key', $request->get('key'))->where('status', 1)->get(array('id', 'key', 'value'));
        return response()->json(['success' => true, 'data' => $data], 200);
    }

    public function isLive(Request $request)
    {
        return response()->json(['success' => true], 200);
    }

    public function addOption(Request $request)
    {
        if (isset($request->type) && $request->type == 'vehicle') {
            $request->validate([
                'type'        => 'required|in:vehicle',
                'category'    => 'required|in:make,model',
                //'is_imported' => 'sometimes|required_if:type,vehicle|in:0,1',
                //'make'        => 'required|regex:/^[A-Za-z0-9-_ ]+$/|not_in:other,OTHER|'. !isset($request->model) && $request->model == null ?  'unique:tb_prmotormakemodels,s_Make' : '|',
                //'model'       => 'sometimes|required||regex:/^[A-Za-z0-9-_ ]+$/|unique:tb_prmotormakemodels,s_Variant|not_in:other,OTHER|',
            ]);
        } elseif (isset($request->type) && $request->type == 'cellphone') {
            $request->validate([
                'type'        => 'required|in:cellphone',
                'category'    => 'required|in:make,model',
                'deviceType'  => 'sometimes|required_if:type,cellphone|in:Cellphone,Tablet,Laptop',
                'make'        => 'required|regex:/^[A-Za-z0-9-_ ]+$/|not_in:other,OTHER|'/* unique:device_make_and_model,name' */,
                'model'       => 'sometimes|required|regex:/^[A-Za-z0-9-_ ]+$/|not_in:other,OTHER|'/* unique:device_make_and_model,name' */,
            ]);
        } elseif (isset($request->type) && $request->type == 'color') {
            $request->validate([
                'type'        => 'required|in:color',
                'category'    => 'required|in:color',
                'color_name'  => 'required|alpha|unique:lookup_data,value'
            ]);
        } else {
            return response()->json(['status' => false, 'message' => 'Type is required'], 422);
        }

        DB::beginTransaction();
        try {
            if ($request->type == 'vehicle') {
                if ($request->category == 'make') {
                    if (!isset($request->callback)) {
                        return response()->json(['status' => true, 'message' => 'valid'], 200);
                    }

                    $make              = new VehicleMake();
                    $make->s_Make      = $request->make;
                    $make->is_imported = $request->is_imported;
                    $make->save();
                    DB::commit();
                    return $make->s_Make;
                }

                if ($request->category == 'model') {
                    if (!isset($request->callback)) {
                        return response()->json(['status' => true, 'message' => 'valid'], 200);
                    }

                    $make = VehicleMake::where('s_Make', $request->make)->whereNull('s_Variant')->first();
                    if ($make == null) {
                        $make            = new VehicleMake();
                    }

                    $make->s_Make      = $request->make;
                    $make->s_Variant   = $request->model;
                    $make->is_imported = $request->is_imported;
                    $make->save();
                    DB::commit();
                    return $make->s_Variant;

                }
                /*if($make->save())
                 return response()->json([ 'status' => true, 'option' => $make ,'type'=> $request->type, 'category' => $request->category], 200);
                else
                return response()->json([ 'status' => false, 'message' => 'Something went wrong. Please try again.'], 500); */
            } elseif ($request->type == 'cellphone') {
                if (!isset($request->callback)) {
                    return response()->json(['status' => true, 'message' => 'valid'], 200);
                }

                $make              = new DeviceMakeModel();
                $make->device_type = $request->deviceType;

                if (isset($request->category) && $request->category == 'model') {
                    $make->name        = $request->model;
                    $make->make_id     = $request->make;
                    $make->save();
                    DB::commit();
                    return $make->id;
                } else {
                    $make->name        = $request->make;
                    $make->save();
                    DB::commit();
                    return $make->id;
                }

                /* if($make->save())
                return response()->json([ 'status' => true, 'option' => $make ,'type'=> $request->type, 'category' => $request->category], 200);
                else
                return response()->json([ 'status' => false, 'message' => 'Something went wrong. Please try again.'], 500); */
            } elseif ($request->type == 'color') {
                if (!isset($request->callback)) {
                    return response()->json(['status' => true, 'message' => 'valid'], 200);
                }

                $color = new Lookup();
                $color->key    = $request->category;
                $color->value  = $request->color_name;
                $color->status = 1;
                $color->save();

                DB::commit();
                return $color->id;
            } else {
                return response()->json(['status' => false, 'message' => 'Something went wrong. Please try again.'], 500);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['status' => false, 'message' => 'Something went wrong. Please try again. chathc'], 500);
        }
    }


    public function deleteCellphoneDevicesApi(Request $request)
    {
        try {
            $type = htmlspecialchars(strip_tags($request->input('type', NULL)));
            $removeDocument = htmlspecialchars(strip_tags($request->input('removeDocument', NULL)));
            $deviceToken = htmlspecialchars(strip_tags($request->input('tokenDevice', NULL)));

            if ($type == 'DEVICES') {
                if ($deviceToken != NULL) {
                    $device = PolicyCellPhone::where('id', $deviceToken)->first();
                    if (isset($device)) {
                        if ($removeDocument == 'front') {
                            $device->cell_phone_front = NULL;
                        }

                        if ($removeDocument == 'back') {
                            $device->cell_phone_back = NULL;
                        }

                        if ($removeDocument == 'left') {
                            $device->cell_phone_left = NULL;
                        }

                        if ($removeDocument == 'right') {
                            $device->cell_phone_right = NULL;
                        }

                        if ($removeDocument == 'top') {
                            $device->cell_phone_top = NULL;
                        }

                        if ($removeDocument == 'bottom') {
                            $device->cell_phone_bottom = NULL;
                        }

                        if ($device->save()) {
                            $device = PolicyCellPhone::where('id', $deviceToken)->first();

                            return response()->json([
                                'status' => 'success',
                                'deviceToken' => $device->id,
                                'cell_phone_front' => $device->cell_phone_front == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($device->cell_phone_front),
                                'cell_phone_back' => $device->cell_phone_back == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($device->cell_phone_back),
                                'cell_phone_left' => $device->cell_phone_left == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($device->cell_phone_left),
                                'cell_phone_right' => $device->cell_phone_right == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($device->cell_phone_right),
                                'cell_phone_top' => $device->cell_phone_top == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($device->cell_phone_top),
                                'cell_phone_bottom' => $device->cell_phone_bottom == NULL ? NULL : \AlphaDirect\Helper::getCloudFrontURL($device->cell_phone_bottom)
                            ], 200);
                        } else {
                            return response()->json('Failed to delete device Images ,please try again', 400);
                        }

                    } else {
                        return response()->json('Failed to delete device Images ,please try again', 400);
                    }
                } else {
                    return response()->json('Failed to delete device Images , device token not found', 400);
                }
            }
        } catch (Exception $ex) {
            return response()->json($ex->getMessage(), 500);
        }
    }


    public function insertErrorLogAPI(Request $request)
    {
        try {
            $data = [
                'title' => $request->title,
                'text'  => $request->text,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s')
            ];
            $errorLog = MobileAppErrorLog::addLog($data);
            return response()->json([
                'status' => 'success',
                'message' => 'Data inserted successfully.'
            ], 200);
        } catch (\Exception $ex) {
            return response()->json($ex->getMessage(), 500);
        }
    }

    public function realpayPayment($policy)
    {
        // dd($policy);
        $log = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
        $addLog = $log->logEvent($policy->id, 1);
        $responseArr = array('ClientCreated' => 0, 'ContractCreated' => 0);
        $stringArr = \Opis\Closure\serialize($responseArr);

        $transaction = new Transaction();
        $transaction->policyNumber = $policy->policyNumber;
        $transaction->amount = $policy->premium;
        $transaction->customer_id = $policy->customer_id;
        $transaction->realPayTransaction_id = $policy->id;
        $transaction->referenceNumber = $policy->policyNumber;
        $transaction->status = "PENDING";
        $transaction->save();

        if ($addLog == true) {
            $payRequest = new RealpayPaymentRequest();
            $payRequest->policy_id = $policy->id;
            // $payRequest->first_premium = $policy->leftout_premium;
            $payRequest->first_premium = $policy->first_premium;
            $payRequest->premium = $policy->premium;
            $payRequest->billing_day = $policy->billing_day;
            $payRequest->billing_date = $policy->billingStartDate;
            $payRequest->first_premium_contract = null;
            $payRequest->contract = null;
            $payRequest->status = 0;
            $payRequest->response = $stringArr;
            $payRequest->frequency = $policy->premium_freq;
            $payRequest->clientCreated = 0;
            $payRequest->contractCreated = 0;
            $payRequest->save();

            return response()->json(['status' => '200', 'message' => 'Payment successful', 'PolicyNumber' => $policy->policyNumber], 200);
        } else {
            return response()->json(['status' => '401', 'message' => 'Payment log unsuccessful'], 401);
        }
    }


    public function getQuoteData(Request $request)
    {
        if(isset($request->quote_number) && $request->quote_number != null)
        {
            $is_processed          = Policy::where('quoteNumber', $request->quote_number)->count();

            if($is_processed == 0 )
            {
                $quote                 = MotorComprehensiveQuotes::where('quoteNumber', $request->quote_number)->first();
                $setting = QuoteSettings::first();
                $days = (int)(($setting->DaysToExpireQuote ?? 0) ?: 30);
                $start = new Carbon($quote->created_at);
                $now   = new Carbon();
                $expiryDate = $start->addDays($days);
                $status = $now->greaterThanOrEqualTo($expiryDate);

                if($quote->status == 1 && $status == false){
                    $quote->customer       = Customer::with('profile')->where('id', $quote->customer_id )->first();
                    $quote->customer->profile->country_name  = Country::where('id', $quote->customer->profile->countryId)->value('name');
                    // $quote->colors         = Lookup::where('key', 'color')->where('status', 1)->orderBy('value','asc')->get(array('id', 'value'));
                    // $quote->type_of_bodies = Lookup::where('key', 'type_of_body')->where('status', 1)->orderBy('value','asc')->get(array('id', 'value'));
                    $quote->agent          = User::where('id', $quote->agentID)->first(array('id', 'firstName', 'lastName'));
                    $quote->store          = Stores::where('id', $quote->storeID)->first(['id','name']);
                    $quote->countries      = Country::groupBy('name')->get(['id','name']);
                    $quote->states         = State::where('country_id',245)->get(['id','name']);
                    $quote->vehicle_types  = Product::where([ ['premium_type_id', '=', '11'],  ['billing_cycle', '=', 'Quaterly'], ['type', '=', 'vehicle_type'],
                                            ['has_vehicle', '=', '1'],
                                            ['status', '=', '1'],
                                        ])->get(['id', 'slug']);

                    if($quote !=null && $quote->customer !=null && $quote->customer->profile !=null )
                    {
                        return response()->json(['status' => true, 'quote' => $quote, 'type' => 'success'], 200);
                    } else {
                        return response()->json(['status' => false, 'message' => 'Data not found', 'type' => 'error'], 500);
                    }

                } else {
                    return response()->json(['status' => false, 'message' => 'Quote number might be expired or rejected. Please try another.', 'type' => 'error'], 500);
                }

            }else{
                return response()->json(['status' => false, 'message' => 'Quote number is used. Please try another.', 'type' => 'error'], 500);
            }
        }else{
            return response()->json(['status' => false, 'message' => 'Please provide valid input', 'type' => 'error'], 500);
        }


    }

    public function getHibPremium(Request $request)
    {
        $productId = $request->product_id;
        $planId = $request->plan_id;
        $dependents = $request->dependents;
        //Log::debug("Dependents",$dependents);
        $productPlan = Productplan::where('id',$planId)->first();
        if(!isset($productPlan))
        {
            return response()->json(['status' => false, 'message' => 'Plan not configured for premium calculation', 'type' => 'error']);
        }
        $premiumMeta = json_decode($productPlan->premiumAndRelation,true);
        //Log::debug("Premium meta",$premiumMeta);
        $totalPremium = $this->calculateHibPremium($dependents,$productPlan,$premiumMeta);
        return response()->json(['status'=>true,'type'=>'success','data'=>['premium' => number_format($totalPremium,2)]]);
    }

    private function calculateHibPremium($dependents,$productPlan,$premiumMeta,$returnBreakUp=0)
    {
        $premiumPayment = new PaymentController();
        return $premiumPayment->getHibPremiumByProductPlan($dependents,$productPlan,$premiumMeta,$returnBreakUp);
    }

    public function deleteCoapplicant(Request $request)
    {
        try {

            $coapplicant = HospitalCashbackCoapplicants::findorFail($request->coapplicant_id);

            if ($coapplicant->delete()) {
                return response()->json(['message' => 'Person has been removed from your Coapplicant list'], 200);
            } else {
                return response()->json(['message' => 'Failed to delete Coapplicant, please try again'], 404);
            }
        } catch (Exception $ex) {
            return response()->json(['message' => $ex->getMessage()], 500);
        }
    }

    public function updateCoapplicant(Request $request)
    {
        $coapplicants = new HospitalCashbackCoapplicants();

        $result =  $coapplicants->updateCoapplicants($request);

        if ($result == true) {
            //response for successful update - (Important - status code for Flutter app)
            return response()->json(['message' => 'Policy coapplicant has been updated successfully.'], 200);
        } else {
            //response for unsuccessful update - (Important - status code for Flutter app)
            return response()->json(['message' => 'Something went wrong , please try again later'], 401);
        }
    }

    public function addPolicyCoapplicant(Request $request)
    {
        try {
            if ($request['coapplicants'] != null) {
                $coapplicants = $request['coapplicants'];

                foreach ($coapplicants as $key => $coapplicant) {
                    $policyCoapplicant = new HospitalCashbackCoapplicants();
                    $policyCoapplicant->policy_id = $request->policy_id;
                    $policyCoapplicant->relation = isset($coapplicant['coapplicantRelation']) && $coapplicant['coapplicantRelation'] != '' ? $coapplicant['coapplicantRelation'] : '';
                    $policyCoapplicant->first_name = isset($coapplicant['coapplicantFName']) && $coapplicant['coapplicantFName'] != '' ? $coapplicant['coapplicantFName'] : '';
                    $policyCoapplicant->last_name = isset($coapplicant['coapplicantLName']) && $coapplicant['coapplicantLName'] != '' ? $coapplicant['coapplicantLName'] : '';
                    $policyCoapplicant->dob = isset($coapplicant['coapplicantDOB']) && $coapplicant['coapplicantDOB'] != '' ? Carbon::createFromFormat('d/m/Y', $coapplicant['coapplicantDOB'])->format('Y-m-d') : '';
                    $policyCoapplicant->gender = isset($coapplicant['coapplicantGender']) && $coapplicant['coapplicantGender'] != '' ? $coapplicant['coapplicantGender'] : '';
                    $policyCoapplicant->omang = isset($coapplicant['coapplicantOmang']) && $coapplicant['coapplicantOmang'] != '' ? $coapplicant['coapplicantOmang'] : '';
                    $policyCoapplicant->passport = isset($coapplicant['coapplicantPassport']) && $coapplicant['coapplicantPassport'] != '' ? $coapplicant['coapplicantPassport'] : '';
                    $res = $policyCoapplicant->save();
                }

                if ($res) {
                    $allCoapplicants = HospitalCashbackCoapplicants::where('policy_id', $policyCoapplicant->policy_id)->get();
                    return response()->json(['message' => 'Coapplicant list updated', 'policyCoapplicants' => $allCoapplicants], 200);
                } else {
                    return response()->json('Failed to add coapplicants, please try again', 400);
                }
            }
        } catch (\Exception $ex) {
            return response()->json($ex->getMessage(), 500);
        }
    }

    public function savePolicyCoapplicants(Request $request)
    {

        $submittedPremium = $request->premium;

        $calculatedPremium = $this->calculateHospitalCashbackPremium($request);

        if ((int) $submittedPremium !== (int) $calculatedPremium) {

            return response()->json(['title' => 'Premium mismatch.', 'description' => 'Premium mismatch. Please recalculate premium before submitting.'], 412);
        }

        $policyId = $request->input('policy_id');

        $policy = Policy::where('id',$policyId)->first();

        if (isset($policy)) {
            if($policy->is_bundled == 1){

               
            
            }else{
                if ((int) $policy->premium !== (int) $submittedPremium) {

                    $updatePayment = $this->updatePolicyPaymentContract($policyId, $policy->policyNumber, $submittedPremium);
    
                    if (!isset($updatePayment['status']) || $updatePayment['status'] !== 'success') {
                        return response()->json([
                            'message' => 'Failed to update payment contract',
                            'details' => $updatePayment['message'] ?? 'Unknown error'
                        ], 500);
                    }
    
                    $policy->premium = $submittedPremium;
                    $policy->save();
    
    
                    if (!$policy->save()) {
                        return response()->json(['message' => 'Failed ! Updating policy premium failed.'], 500);
                    }
                }
            }

          

            $coapplicants = $request->coapplicants;

            if (!$policyId || !is_array($coapplicants)) {
                return response()->json(['message' => 'Invalid input'], 400);
            }

            foreach ($coapplicants as $c) {
                if (!empty($c['coapplicantRelation'])) {
                    $coapplicant = isset($c['id'])
                        ? HospitalCashbackCoapplicants::find($c['id'])
                        : new HospitalCashbackCoapplicants();

                    if (!$coapplicant) continue;

                    $coapplicant->policy_id = $policyId;
                    $coapplicant->relation = $c['coapplicantRelation'];
                    $coapplicant->first_name = $c['coapplicantFName'];
                    $coapplicant->last_name = $c['coapplicantLName'];
                    $coapplicant->gender = $c['coapplicantGender'] ?? null;
                    // $coapplicant->dob = $c['coapplicantDOB'] ?? null;
                    $coapplicant->dob = Carbon::createFromFormat('d/m/Y', $c['coapplicantDOB'])->format('Y-m-d');
                    $coapplicant->omang = $c['coapplicantOmang'] ?? null;
                    $coapplicant->passport = $c['coapplicantPassport'] ?? null;
                    $coapplicant->save();
                }
            }

            return response()->json(['message' => 'Success ! Coapplicants updated successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed ! Updating coapplicants failed. Policy Not Found'], 500);
        }
    }

    public function updatePolicyPaymentContract($policyId, $policyNumber, $newPremium)
    {
        $customer_billing = CustomerBanking::where('policy_id', $policyId)
            ->orderByDesc('id')
            ->value('billing');

        if (isset($customer_billing)) {
            $billingMethod = strtolower($customer_billing);

            switch ($billingMethod) {
                case 'dpo':
                    return $this->handleDpo($policyId, $policyNumber, $newPremium);
                    break;

                case 'realpay':
                    return $this->handleRealpay($policyId, $policyNumber, $newPremium);
                    break;

                default:
                    $result = ['status' => 'error', 'message' => 'Unsupported billing type or not found'];
                    break;
            }

            return response()->json($result);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Policy billing method not found.'], 404);
        }
    }

    public function handleDpo($policyId, $policyNumber, $newPremium)
    {
        $updatedByPolicyNumber = ScheduleTransaction::where('policy_number', $policyNumber)
            ->where('status', 0)
            ->update(['premium' => $newPremium]);

        if ($updatedByPolicyNumber > 0) {
            return ['status' => 'success', 'message' => 'Premium updated using policy number.'];
        }

        $updatedByPolicyId = ScheduleTransaction::where('policy_id', $policyId)
            ->where('status', 0)
            ->update(['premium' => $newPremium]);

        if ($updatedByPolicyId > 0) {
            return ['status' => 'success', 'message' => 'Premium updated using policy ID.'];
        }

        return ['status' => 'error', 'message' => 'No scheduled transactions found to update.'];
    }

    public function handleRealpay($policyId, $policyNumber, $newPremium)
    {
        try {
            // Step 1: Try to get contract from realpay_client_contracts
            $contract = RealpayClientContracts::where('policy_id', $policyId)
                ->orderByDesc('id')
                ->first(['client_number', 'contract_number']);

            $clientNumber = $contract->client_number ?? null;
            $contractNumber = $contract->contract_number ?? null;

            // Step 2: Fallback to realpay_contracts if no contract found
            if (!$contract) {
                $contract = RealpayContractDetails::where('ClientNumber', $policyNumber)
                    ->orderByDesc('id')
                    ->first(['ClientNumber as client_number', 'ContractNumber as contract_number']);

                $clientNumber = $contract->client_number ?? null;
                $contractNumber = $contract->contract_number ?? null;
            }

            if (!$clientNumber || !$contractNumber) {
                return ['status' => 'error', 'message' => 'RealPay contract not found'];
            }

            // Step 3: Try to get active installments from DB
            $installments = RealpayContractInstallments::where('clientNumber', $clientNumber)
                ->where('contractNumber', $contractNumber)
                ->where('InstalmentStatus', 'A')
                ->get();

            // Step 4: If no installments found, fetch from RealPay API
            if ($installments->isEmpty()) {
                $response = $this->getRealpayInstallmentsForInstantProduct([
                    'policy_id' => $policyId,
                    'clientNumber' => $clientNumber,
                    'contractNumber' => $contractNumber
                ]);

                if (!isset($response['InstalmentGetResponse'])) {
                    return ['status' => 'error', 'message' => 'No active installments found'];
                }

                $installments = collect($response['InstalmentGetResponse']);
            }

            // // Step 5: Process installments in chunks to avoid timeout
            // $installments->chunk(5)->each(function ($chunk) use ($policyId, $clientNumber, $contractNumber, $newPremium) {
            //     UpdateRealpayInstallmentJob::dispatch([
            //         'policy_id' => $policyId,
            //         'realpay_client_number' => $clientNumber,
            //         'realpay_contract_number' => $contractNumber,
            //         'realpay_installment_premium' => $newPremium,
            //         'installments' => $chunk->toArray(), // send all 5 installments at once
            //     ])->delay(now()->addSeconds(1));
            // });

            // $installments->chunk(5)->each(function ($chunk) use ($policyId, $clientNumber, $contractNumber, $newPremium) {
            //     $this->updateRealpayInstallments([
            //         'policy_id' => $policyId,
            //         'realpay_client_number' => $clientNumber,
            //         'realpay_contract_number' => $contractNumber,
            //         'realpay_installment_premium' => $newPremium,
            //         'installments' => $chunk->toArray(), // Pass entire chunk
            //     ]);

            //     sleep(2); // Optional pause to avoid timeout
            // });

            // dd($policyId, $clientNumber, $contractNumber,$newPremium,$installments->toArray());

            $this->updateRealpayInstallments([
                'policy_id' => $policyId,
                'realpay_client_number' => $clientNumber,
                'realpay_contract_number' => $contractNumber,
                'realpay_installment_premium' => $newPremium,
                'installments' => $installments->toArray(), // Pass all installments directly
            ]);

            return ['status' => 'success', 'message' => 'RealPay installments updated'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function updateRealpayInstallments($data) {
        try {

            Log::info('Starting RealPay Installment update for contract: '.$data['realpay_contract_number']);

            $fetchToken = $this->clientAuthForInstantProduct();

            if (empty($fetchToken)) {
                Log::warning('Token not retrieved.');
                return;
            }

            $policy = Policy::find($data['policy_id']);

            $customerBanking = CustomerBanking::where('policy_id', $policy->id)->latest()->first()
                ?? CustomerBanking::where('customer_id', $policy->customer_id)->latest()->first();

            $url = '';
            if ($customerBanking && $customerBanking->bankName == 12) {
                $url = config('realpay.start.base_url') . "/maintain/instalments/" . config('realpay.fnb_product')
                    . "?BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version');
            } else {
                $url = config('realpay.start.base_url') . "/maintain/instalments/" . config('realpay.start.product')
                    . "?BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version');
            }

            $countActive = 0;
            foreach ($data['installments'] as $installment) {
                // dd($installment);
                // // $contractSeq = $installment['ContractSequence'];
                // $clientNum = $installment['ClientNumber'];
                // $contractNumber = $installment['ContractNumber'];
                // // $insSeq = $installment['InstalmentSequence'];
                // // $tracking = $installment['TrackingCode'];
                // $insStatus = $installment['InstalmentStatus'];

                // $amnt = $data['realpay_installment_premium'] ?? $installment['InstalmentAmount'];
                // // $instDate = isset($installment['InstalmentActionDate'])
                // //     ? Carbon::parse($installment['InstalmentActionDate'])->addMonthsNoOverflow($countActive)->format('Y-m-d')
                // //     : null;

                $clientNum = $installment['ClientNumber'] ?? $installment['clientNumber'] ?? null;
                $contractNumber = $installment['ContractNumber'] ?? $installment['contractNumber'] ?? null;
                $contractSeq = $installment['ContractSequence'] ?? $installment['contractSequence'] ?? null;
                $insSeq = $installment['InstalmentSequence'] ?? $installment['instalmentSequence'] ?? null;
                $tracking = $installment['TrackingCode'] ?? $installment['trackingCode'] ?? null;
                $insStatus = $installment['InstalmentStatus'] ?? $installment['instalmentStatus'] ?? null;

                $amnt = $data['realpay_installment_premium'] ?? $installment['InstalmentAmount'] ?? $installment['instalmentAmount'] ?? 0;

                $instDate = $installment['InstalmentActionDate'] ?? $installment['instalmentActionDate'] ?? null;
                // $instDate = $originalDate
                //     ? Carbon\Carbon::parse($originalDate)->addMonthsNoOverflow($countActive)->format('Y-m-d')
                //     : now()->addMonths($countActive)->format('Y-m-d');

                if ($insStatus == 'A') {
                    $payload = [
                        "InstalmentPutRequest" => [[
                            "ClientNumber" => $clientNum,
                            "ContractSequence" => $contractSeq,
                            "ContractNumber" => $contractNumber,
                            "InstalmentSequence" => $insSeq,
                            "InstalmentActionDate" => $instDate,
                            "TrackingCode" => $tracking,
                            "InstalmentAmount" => $amnt,
                            "InstalmentStatus" => $insStatus,
                            "DebitSequenceType" => "OOFF"
                        ]]
                    ];

                    $curl = curl_init();
                    curl_setopt_array($curl, [
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_CUSTOMREQUEST => "PUT",
                        CURLOPT_POSTFIELDS => json_encode($payload),
                        CURLOPT_HTTPHEADER => [
                            "Content-Type: application/json",
                            "Accept: application/json",
                            "Authorization: " . $fetchToken
                        ],
                    ]);

                    $response = curl_exec($curl);
                    curl_close($curl);

                    $decoded = json_decode($response, true);

                    // Update DB if needed
                    $insdata = RealpayContractInstallments::where('clientNumber', $clientNum)
                        ->where('contractNumber', $contractNumber)
                        ->where('InstalmentSequence', $insSeq)
                        ->first();

                    if ($insdata) {
                        $insdata->InstalmentAmount = $amnt;
                        $insdata->save();

                        activity('Realpay Installments')
                            ->performedOn($insdata)
                            ->log('Updated Realpay Installment Date');
                    }

                    $countActive++;
                }
                sleep(1);
            }

            Log::info('Finished RealPay Installment update for contract: '.$data['realpay_contract_number']);
        } catch (\Exception $e) {
            Log::error('RealPay installment update failed: ' . $e->getMessage());
        }
    }


    public function clientAuthForInstantProduct(){
        try{
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.start.base_url').'/oauth/token?grant_type=client_credentials',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_HTTPHEADER => array(
                  'Authorization: Basic '.config('realpay.start.client_auth')
                ),
              ));

            $response = curl_exec($curl);
            curl_close($curl);

            $fetchToken = json_decode($response,true);
            //   dd($response);
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                return $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

        }catch(\Exception $e){
            return null;
        }
    }
   
}
