<?php

namespace AlphaDirect\Http\Controllers\CommonApis;

use AlphaDirect\Activation;
use AlphaDirect\CustomerBanking;
use AlphaDirect\CustomerFeedback;
use AlphaDirect\CustomerGeneratedActivationCode;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Helper;
use AlphaDirect\Http\Controllers\Admin\DocumentController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\KYC;
use AlphaDirect\Lead;
use AlphaDirect\Mail\ForgotPassword;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\OTP;
use AlphaDirect\Policy;
use AlphaDirect\PolicyBeneficiary;
use AlphaDirect\PolicyLead;
use AlphaDirect\PolicyMember;
use AlphaDirect\PolicyStatusLogs;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\RealpayCancelRequests;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\Region;
use AlphaDirect\User;
use AlphaDirect\Vehicle;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Customer;
use AlphaDirect\DeviceMakeModel;
use Hash;
use Carbon\Carbon;
use Http\Client\Exception;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Redirect;
use AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController;
use AlphaDirect\Http\Controllers\Payment\VCS\PaymentController;
use AlphaDirect\Http\Controllers\Payment\Flutterwave\FlutterwaveController;
use AlphaDirect\Repositories\ClaimCellphone\ClaimCellphoneInterface;
use AlphaDirect\Repositories\Customer\CustomerInterface;
use AlphaDirect\Repositories\CustomerBanking\CustomerBankingInterface;
use AlphaDirect\Repositories\CustomerKyc\CustomerKycInterface;
use AlphaDirect\Repositories\CustomerProfile\CustomerProfileInterface;
use AlphaDirect\Repositories\PolicyCellPhone\PolicyCellPhoneInterface;
use Illuminate\Support\Facades\Validator;

class PolicyController extends Controller
{
    protected $claim_cellphone_interface;
    protected $policy_cell_phone_interface;
    protected $customer_interface;
    protected $customer_profile_interface;
    protected $customer_kyc_interface;
    protected $customer_banking_interface;
    public function __construct(ClaimCellphoneInterface $claim_cellphone_interface,PolicyCellPhoneInterface $policy_cell_phone_interface,CustomerInterface $customer_interface,CustomerProfileInterface $customer_profile_interface, CustomerKycInterface $customer_kyc_interface,CustomerBankingInterface $customer_banking_interface) {
        $this->claim_cellphone_interface = $claim_cellphone_interface;
        $this->policy_cell_phone_interface = $policy_cell_phone_interface;
        $this->customer_interface = $customer_interface;
        $this->customer_profile_interface = $customer_profile_interface;
        $this->customer_kyc_interface = $customer_kyc_interface;
        $this->customer_banking_interface = $customer_banking_interface;
    }

    public function isImageValid($image)
    {
        $data = Image::make($image)->exif();
        if ($data != null) {
            $exif = exif_read_data($image, 0, true);
            if ($exif) {
                if (array_key_exists('EXIF', $exif)) {
                    if (array_key_exists('DateTimeOriginal', $exif['EXIF'])) {
                        $DateTime = \Carbon::parse($exif['EXIF']['DateTimeOriginal'])->timestamp;
                    } elseif (array_key_exists('aTime' || 'DateTime', $exif['EXIF'])) {
                        $DateTime = \Carbon::parse($exif['EXIF']['aTime' || 'DateTime'])->timestamp;
                    } else {
                        return false;
                    }
                    $createdDateTime = Carbon::createFromTimestamp($DateTime);
                    $uploadDateTime = Carbon::createFromTimestamp(Carbon::now()->timestamp);
                    $diff_in_hours = $createdDateTime->diffInHours($uploadDateTime);
                    if ($diff_in_hours > 24)
                        return false;
                    elseif ($diff_in_hours < 24)
                        return true;
                    else
                        return false;
                } elseif (array_key_exists('FILE', $exif)) {
                    $DateTime = $exif['FILE']['FileDateTime'];
                    $createdDateTime = Carbon::createFromTimestamp($DateTime);
                    $uploadDateTime = Carbon::createFromTimestamp(Carbon::now()->timestamp);
                    $diff_in_hours = $createdDateTime->diffInHours($uploadDateTime);
                    if ($diff_in_hours > 24)
                        return false;
                    elseif ($diff_in_hours < 24)
                        return true;
                    else
                        return false;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function createPolicy(Request $request)
    {
        // BizSure traffic still POSTs to the legacy /api/createPolicy path. It is
        // a SEPARATE flow (dedicated BizSurePolicyController + BizSureOrchestrator);
        // detect it by leadSource and delegate so the partner client needs no URL
        // change. Everything below this guard is the LiveQuote flow, untouched.
        if (strtolower((string) $request->input('leadSource', '')) === 'bizsure') {
            // /createPolicy is unauthenticated for LiveQuote, but BizSure must
            // still present the shared partner api-key (the dedicated
            // /bizsure/createPolicy route enforces it via VerifyApiKey, so we
            // enforce the same here before delegating).
            $partnerKey = (string) config('services.partner.api_key');
            if ($partnerKey === '' || ! hash_equals($partnerKey, (string) $request->header('api-key'))) {
                return response()->json(['status' => false, 'message' => 'Invalid API KEY'], 401);
            }
            return app(\AlphaDirect\Http\Controllers\CommonApis\BizSurePolicyController::class)
                ->createPolicy($request);
        }

        try {
            if($request->get('product') != null)
                $product = Product::where('id', $request->get('product'))->first(array('id', 'has_vehicle', 'has_member', 'premium_type_id', 'limit', 'kyc_recipient', 'kyc_customer', 'preinspection', 'is_motor_items', 'region_id', 'has_activation_code', 'type'));
            else
                return response()->json(['success' => false, 'message' => "Product ID is empty"], 401);
                 
            if($product->has_vehicle == 1) {
                $exist = $this->checkVehicleExistWIthStatusActive($request->vehiclePlate);
                if ($exist == true) {
                    return response()->json([
                        'success' => false, 'message' => "Vehicle already associated with existing policy"
                    ], 401);
                }
                if($request->vehiclePlate == null){
                    return response()->json([
                        'success' => false, 'message' => "Vehicle plate is empty"
                    ], 401);
                }
                if ($request->purpose != '27' || $request->purpose != 27) {
                    return response()->json([
                        'success' => false, 'message' => "Sorry, Currently we can only provide vehicles with personal use"
                    ], 401);
                }

                if ($request->hasFile('front')) {
                    $file = $request->file('front');
                    $result = $this->isImageValid($file);
                    if ($result == false) {
                        return response()->json(['success' => false, 'message' => 'The photo of the Front of your vehicle is older than 24 hours, and can not be accepted. Please take a new image of the vehicle and re-upload.'], 401);
                    }
                }
                if ($request->hasFile('back')) {
                    $file = $request->file('back');
                    $result = $this->isImageValid($file);
                    if ($result == false) {
                        return response()->json(['success' => false, 'message' => 'The photo of the rear (back) of your vehicle is older than 24 hours, and can not be accepted. Please take a new image of the vehicle and re-upload.'], 401);
                    }
                }
                if ($request->hasFile('right')) {
                    $file = $request->file('right');
                    $result = $this->isImageValid($file);
                    if ($result == false) {
                        return response()->json(['success' => false, 'message' => 'The photo of the right of your vehicle is older than 24 hours, and can not be accepted. Please take a new image of the vehicle and re-upload.'], 401);
                    }
                }
                if ($request->hasFile('left')) {
                    $file = $request->file('left');
                    $result = $this->isImageValid($file);
                    if ($result == false) {
                        return response()->json(['success' => false, 'message' => 'The photo of the left of your vehicle is older than 24 hours, and can not be accepted. Please take a new image of the vehicle and re-upload.'], 401);
                    }
                }
                if ($request->hasFile('vehicleRegistration')) {
                    $file = $request->file('vehicleRegistration');
                    $result = $this->isImageValid($file);
                    if ($result == false) {
                        return response()->json(['success' => false, 'message' => 'The photo of the Vehicle registration book is older than 24 hours, and can not be accepted. Please take a new image of the Vehicle registration book and re-upload.'], 401);
                    }
                }
            } 
           
            if ($request->billing_day == '' && $request->frequency == 1) {
                return response()->json([
                    'success' => false, 'message' => "Sorry, you need to select billing start day"
                ], 401);
            }
            if ($request->frequency == null && $product->id == 3) {
                return response()->json([
                    'success' => false, 'message' => "Sorry, Please choose premium frequency"
                ], 401);
            }
            if ($request->Payment_method == null) {
                return response()->json([
                    'success' => false, 'message' => "Sorry, Please choose payment method"
                ], 401);
            }
            if($request->Payment_method == "RealPay"){
                if($request->get('bankName') == null || $request->get('branchCode') == null || $request->get('bankAccountType') == null || $request->get('accountNumber') == null){
                    return response()->json([
                        'error' => false, 'message' => "Sorry, Please provide complete account details to process policy with RealPay"
                    ], 401);
                }
            }

            $validator = Validator::make($request->all(), [
                'premium' => 'required',
            ]);
             
            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => $validator->messages()->first()], 401);
            }

            //check for email and cellphone
            $profile = null;
            //check for omang and passport
            if ($request->get('phone') != null) {
                $profile = Customer::where('cellphone', $request->get('phone'))->orderBy('id', 'asc')->first();
            }
            if ($profile != null) {
                $profile->customer_id = $profile->id;
            } 
            if ($profile == null) {
                if ($request->get('omang') != null && $request->get('passport') != null) {
                    $profile = CustomerProfile::where('omang', $request->get('omang'))->orWhere('passport', $request->get('passport'))->orderBy('id', 'asc')->first(array('customer_id'));
                } elseif ($request->get('omang') == null && $request->get('passport') != null) {
                    $profile = CustomerProfile::where('passport', $request->get('passport'))->orderBy('id', 'asc')->first(array('customer_id'));
                } elseif ($request->get('omang') != null && $request->get('passport') == null) {
                    $profile = CustomerProfile::where('omang', $request->get('omang'))->orderBy('id', 'asc')->first(array('customer_id'));
                }
            } 
        
            if ($profile == null) {

                $fname = htmlspecialchars(strip_tags($request->input('firstname', '')));
                $lname = htmlspecialchars(strip_tags($request->input('lastname', '')));
                $email = htmlspecialchars(strip_tags($request->input('email', '')));
                $cellphone = htmlspecialchars(strip_tags($request->input('phone', '')));
                $data = [
                    'firstName'          => $fname,
                    'lastName'           => $lname,
                    'email'              => $email,
                    'cellphone'          => $cellphone,
                ];
                if ($request->get('password') != NULL){
                    $data['password']  = Hash::make($request->get('password'));
                }
                $user_id = $this->customer_interface->add_new_customer($data);

                $gender = htmlspecialchars(strip_tags($request->input('gender', '')));
                $address = htmlspecialchars(strip_tags($request->input('address', '')));
                $omang = htmlspecialchars(strip_tags($request->input('omang', '')));
                $state = htmlspecialchars(strip_tags($request->input('state', '')));
                $passport = htmlspecialchars(strip_tags($request->input('passport', '')));
                $countryId = htmlspecialchars(strip_tags($request->input('passportIssuingCountry', '')));
                $city = htmlspecialchars(strip_tags($request->input('city', '')));
                $maritalstatus = htmlspecialchars(strip_tags($request->input('maritalstatus', '')));
                $dob = Carbon::createFromFormat('d/m/Y', $request->get('dob'))->format('Y-m-d');
                $data = [
                    'gender'          => $gender,
                    'customer_id'     => $user_id,
                    'address'         => $address,
                    'omang'           => $omang,
                    'state'           => $state,
                    'passport'        => $passport,
                    'countryId'       => $countryId,
                    'city'            => $city,
                    'maritalstatus'   => $maritalstatus,
                    'dob'             => $dob,
                ];
                $this->customer_profile_interface->add_new_customer_profile($data);
                
                $omangExpiry = htmlspecialchars(strip_tags($request->input('omangexpiry', '')));
                $passportExpiry = htmlspecialchars(strip_tags($request->input('passportexpiry', '')));
                $data = [
                    'customer_id'     => $user_id,
                    'omangExpiry'     => $omangExpiry,
                    'passportExpiry'  => $passportExpiry,
                ];
                $this->customer_kyc_interface->add_new_customer_kyc($data);

            } else {
                $fname = htmlspecialchars(strip_tags($request->input('firstname', '')));
                $lname = htmlspecialchars(strip_tags($request->input('lastname', '')));
                $email = htmlspecialchars(strip_tags($request->input('email', '')));
                $cellphone = htmlspecialchars(strip_tags($request->input('phone', '')));
                $data = [
                    'firstName'          => $fname,
                    'lastName'           => $lname,
                    'email'              => $email,
                    'cellphone'          => $cellphone,
                ];
                if ($request->get('password') != NULL){
                    $data['password'] = Hash::make($request->get('password'));
                }
                $user_id = $profile->customer_id;
                $this->customer_interface->update_customer_by_id($user_id, $data);
                $gender = htmlspecialchars(strip_tags($request->input('gender', '')));
                $address = htmlspecialchars(strip_tags($request->input('address', '')));
                $omang = htmlspecialchars(strip_tags($request->input('omang', '')));
                $passport = htmlspecialchars(strip_tags($request->input('passport', '')));
                $maritalstatus = htmlspecialchars(strip_tags($request->input('maritalstatus', '')));
                $city = htmlspecialchars(strip_tags($request->input('city', '')));
                $state = htmlspecialchars(strip_tags($request->input('state', '')));
                $dob = Carbon::createFromFormat('d/m/Y', $request->get('dob'))->format('Y-m-d');
                 $data = [
                    'customer_id'       => $user_id,
                    'gender'            => $gender,
                    'address'           => $address,
                    'omang'             => $omang,
                    'passport'          => $passport,
                    'maritalstatus'     => $maritalstatus,
                    'city'              => $city,
                    'state'             => $state,
                    'dob'               => $dob,
                ];
                $profile = $this->customer_profile_interface->update_customer_profile_by_id($user_id, $data);
                $omangExpiry = htmlspecialchars(strip_tags($request->input('omangexpiry', '')));
                $passportExpiry = htmlspecialchars(strip_tags($request->input('passportexpiry', '')));
                $data = [
                    'omangExpiry'     => $omangExpiry,
                    'passportExpiry'  => $passportExpiry,
                ];
                $updateKyc = $this->customer_kyc_interface->update_customer_kyc_by_id($user_id, $data);
            }
            //Latestid for Policy Number
            $latest = Policy::latest('policyNumber')->orderBy('id', 'DESC')->first(array('policyNumber'));
            if ($latest == null) {
                $latest = collect();
                $latest->policyNumber = 0;
            }

            $policy = new Policy();
            $policy->customer_id = $user_id;
            $policy->note = $request->get('note');
            $policy->leadSource = 'LiveQuote';
            if ($request->frequency != 1) {
                $request->billing_day = $policy->billing_day = date('d');
            }

            if ($request->billing_day != null) {
                $policy->billing_day = htmlspecialchars(strip_tags($request->billing_day));
                $policy->billingStartDate = $this->setDate(htmlspecialchars(strip_tags($request->billing_day)));
            } else {
                $current_timestamp = Carbon::now()->timestamp;
                $policy->billing_day = date("d", $current_timestamp);
                $policy->billingStartDate = $this->setDate($policy->billing_day);
            }
            $policy->product_id = $request->get('product');
            $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
            if (($product->premium_type_id == 11) && ($request->product != 3)) {

                $product_plan = Productplan::where('id',$request->get('plan_id'))->first(array('sum_assured', 'premium'));
                $premium = round(($product_plan->premium * ($regionVat / 100)) + $product_plan->premium, 2);
                $policy->plan_id = $request->get('plan_id');
                $policy->sum_assured = $product_plan->sum_assured;
                $policy->premium = $product_plan->premium;
                $policy->premium_freq = 1; //$request->frequency;
                $policy->vat = $product_plan->premium * ($regionVat / 100);
                $policy->vat_percent = $regionVat;
                $policy->agent_id = htmlspecialchars(strip_tags($request->agentCode));
                $policy->storeID = htmlspecialchars(strip_tags($request->store_id));
            } else {
                $f = htmlspecialchars(strip_tags($request->frequency));
//                $premium = ($request->get('premium') * ($regionVat / 100)) + $request->get('premium');
//                $policy->premium = $request->get('premium');
//                $policy->vat = $request->get('premium') * ($regionVat / 100);
                $policy->premium_freq = $f;
                $policy->vat_percent = $regionVat;
                $policy->sum_assured = htmlspecialchars(strip_tags($request->get('sum_insured')));

                $plan = Productplan::where('product_id', htmlspecialchars(strip_tags($request->get('product'))))->first(array('id'));
                $policy->plan_id = $plan->id;
            }
            $policy->policyNumber = 'MIS' . Carbon::now()->year . str_pad((substr($latest->policyNumber, -6) + 1), 6, '0', STR_PAD_LEFT);
            $policy->status = 0;
            $policy->has_vehicle = $product->has_vehicle;
            $policy->has_member = $product->has_member;
            $policy->preinspection = $product->preinspection;
            $policy->is_motor_items = $product->is_motor_items;
            $policy->limit = $product->limit;
            $policy->kyc_customer = $product->kyc_customer;
            $policy->kyc_recipient = $product->kyc_recipient;

            if ($product->id == 3) {
                if ($request->frequency != '1') {
                    $policy->first_premium = htmlspecialchars(strip_tags($request->premium));
                    $policy->first_premium_wvat = htmlspecialchars(strip_tags($request->premium_label_vat));
                } else {
                    $policy->first_premium = htmlspecialchars(strip_tags($request->leftout_premium));
                    $policy->first_premium_wvat = htmlspecialchars(strip_tags($request->leftout_premium_wvat));
                }
            }
            $policy->billingStartDate = $this->setDate(htmlspecialchars(strip_tags($request->billing_day)));
            $addDays = 0;
            if ($product->has_activation_code) {
                $activationCode = htmlspecialchars(strip_tags($request->activationCode));
                $activation = Activation::where('activation_code', $request->data['activationCode'])->first();
                if ($activation != NULL) {
                    $activation->status = '1';
                    $activation->branch = '0';
                    $activation->save();
    
                    $policy->activation_code = $request->data['activationCode'];
                    $policy->serial_code = $activation->serial_code;
                    $addDays = $activation->trial_periods;
                } else {
                    $activationData = new \Illuminate\Http\Request();
                    $activationData->setMethod('POST');
                    $activationData->vendor = 5;
                    $activationData->branch = 8;
                    $activationData->rack_no = 0;
                    $activationData->trial_periods=30;
                    $activationData->trial_coverage=100000;
                    $activationData->city='Gaborone';
                    $activationData->state='Gaborone';
                    $activationData->country='Botswana';
                    $activationData->product= $request->product;
                    $activationData->plan =$request->get('plan_id') ;
                    $activationData->cellphone=$cellphone;
                    $activationData->id_type = $request->get('omang') != "" ? "omang": "passport";
                    $activationData->id_number = $activationData->id_type == "omang" ?  $request->get('omang') :  $request->get('passport');
                    $activationData->product_type_id = 11;
                    $activationData->status = 0;
                    $activationCode =  $this->generateActivationCode($activationData);
                    
                    $activation = Activation::where('activation_code',$activationCode->getData()->activationCode)->first();
                    $activation->status = 1;
                    $activation->save();
    
                    $policy->activation_code = htmlspecialchars(strip_tags($activationCode->getData()->activationCode));
                    $policy->serial_code = $activation->serial_code;
                    $addDays = $activation->trial_periods;
                }
            }

            $policy->trial_period = Carbon::now()->addDays($addDays)->format('Y-m-d');

            $policySaved = $policy->save();

            $importStatus = '';

            if ($policySaved && $request->generatedQuoteCode != null && htmlspecialchars(strip_tags($request->get('product'))) == 3) {
                $updateQuote = $this->updateQuoteStatus($request->generatedQuoteCode);
                if ($updateQuote == true) {
                    $data = MotorComprehensiveQuotes::where('quoteNumber', htmlspecialchars(strip_tags($request->generatedQuoteCode)))->first();
                    $policy->premium_freq = $request->frequency;
                    switch ($request->frequency) {
                        case '1':
                            $policy->premium = $data->premiumMonthly;
                            break;

                        case '2':
                            $policy->premium = $data->premium3Inst;
                            break;

                        case '3':
                            $policy->premium = $data->premiumAnnually;

                        default:
                            $policy->premium_freq = 1;
                            $policy->premium = $data->premiumMonthly;
                            break;
                    }

                    $policy->vat = $policy->premium * ($regionVat / 100);
                    $policy->quoteNumber = $request->generatedQuoteCode;
                    $policy->sum_assured = $data->estimatedValue;
                    if($request->agent_id != null) {
                        $policy->storeID = $request->stores;
                        $policy->agent_id = $request->agent_id;
                        $policy->save();
                    }

                    if($data->is_imported == "Yes")
                        $importStatus = 1;
                    else
                        $importStatus = 0;

                } 
            }

            if ($product->has_member) {
                if ($request->get('beneficiaries') != null) {
                    foreach ($request->get('beneficiaries') as $key => $beneficiary) {
                        if ($beneficiary['beneficiaryRelation'] != null) {
                            $policy_id = $policy->id;
                            $relation = $beneficiary['beneficiaryRelation'];
                            $omang = $beneficiary['beneficiaryOmang'];
                            $passport = $beneficiary['beneficiaryPassport'];
                            $first_name = $beneficiary['beneficiaryFName'];
                            $last_name = $beneficiary['beneficiaryLName'];
                            $dob = Carbon::createFromFormat('d/m/Y', $beneficiary['beneficiaryDOB']);
                            $gender = $beneficiary['beneficiaryGender'];
                            $payment = $beneficiary['beneficiaryPayment'];

                            $data = [
                                'policy_id' => $policy_id,
                                'relation'  => $relation,
                                'omang'     => $omang,
                                'passport'  => $passport,
                                'first_name'=> $first_name,
                                'last_name' => $last_name,
                                'dob'       => $dob,
                                'gender'    => $gender,
                                'payment'   => $payment,
                            ];
                            $policyBenAdd = $this->policy_beneficiary->add_new_policy_beneficiary($data);
                        }
                    }
                }
            }

            $banking = new CustomerBanking();
            $banking->customer_id = $user_id;
            $banking->policy_id = $policy->id;
            $banking->billing = htmlspecialchars(strip_tags($request->get('Payment_method')));
            $banking->billingCell = htmlspecialchars(strip_tags($request->get('phone')));
            if ($request->get('Payment_method') == 'RealPay') {
                $banking->bankName = htmlspecialchars(strip_tags($request->get('bankName')));
                $banking->branchCode = htmlspecialchars(strip_tags($request->get('branchCode')));
                $banking->accountNumber = htmlspecialchars(strip_tags($request->get('accountNumber')));
                $banking->accountType = htmlspecialchars(strip_tags($request->get('bankAccountType')));
            }

            if ($request->frequency == 1)
                $banking->billingStartDate = $this->setDate(htmlspecialchars(strip_tags($request->billing_day)));
            elseif ($request->frequency == 2)
                $banking->billingStartDate = Carbon::now()->addMonths(1)->format('Y-m-d');
            else
                $banking->billingStartDate = Carbon::now()->addYear()->format('Y-m-d');

            $banking->billing_day = htmlspecialchars(strip_tags($request->billing_day));
            $saved = $banking->save();

            if ($product->has_vehicle) {
                $vehicle = new Vehicle();
                $vehicle->customer_id = $user_id;
                $vehicle->policy_id = $policy->id;
                $vehicle->vehiclePlate = $request->vehiclePlate;
                if(htmlspecialchars(strip_tags($request->get('product'))) == 3){
                    $vehicleData = MotorComprehensiveQuotes::where('quoteNumber', $request->generatedQuoteCode)->first(array('make','model','manufacturingYear'));
                    if($vehicleData != null){
                    $vehicle->make = $vehicleData->make;
                    $vehicle->model = $vehicleData->model;
                    $vehicle->year = $vehicleData->manufacturingYear;
                    }
                 }else{
                    $vehicle->make = $request->make;
                    $vehicle->model = $request->model;
                    $vehicle->year = $request->year;
                }    
                $vehicle->is_private = $request->purpose;
                $vehicle->vinnumber = $request->vinnumber;
                $vehicle->engineNo = $request->enginenumber;
                $vehicle->financial_interest = $request->financial_interest;
                $vehicle->financial_interest_other = $request->other_finance;
                $vehicle->claim_count = $request->claim_count;
                $vehicle->is_imported = $importStatus;
                $vehicle->mileage = 'LO';
                $vehicle->condition = 'EX';
                $vehicle->estimated_value = $request->estimated_value;

                if ($request->hasFile('front')) {
                    $file = $request->file('front');
                    $result = $this->isImageValid($file);
                    if ($result) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/front' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $vehicle->front = $filePath;
                    } else {
                        return response()->json(['success' => false, 'message' => 'Front image is invalid'], 401);
                    }
                }
                if ($request->hasFile('back')) {
                    $file = $request->file('back');
                    $result = $this->isImageValid($file);
                    if ($result) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/back' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $vehicle->back = $filePath;
                    } else {
                        return response()->json(['success' => false, 'message' => 'Back image is invalid'], 401);
                    }
                }
                if ($request->hasFile('right')) {
                    $file = $request->file('right');
                    $result = $this->isImageValid($file);
                    if ($result) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/right' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $vehicle->right = $filePath;
                    } else {
                        return response()->json(['success' => false, 'message' => 'Right image is invalid'], 401);
                    }
                }
                if ($request->hasFile('left')) {
                    $file = $request->file('left');

                    $result = $this->isImageValid($file);
                    if ($result) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/left' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $vehicle->left = $filePath;
                    } else {
                        return response()->json(['success' => false, 'message' => 'Left image is invalid'], 401);
                    }
                }
                if ($request->hasFile('vehicleRegistration')) {
                    $file = $request->file('vehicleRegistration');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'vehicleRegistration' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $vehicle->vehicleRegistration = $filePath;
                }
                $saved = $vehicle->save();
            }
            if($product->id == 3){
                $d = new DocumentController();
                $verificationDoc = $d->generateInformationDocument($policy->id);

                if($verificationDoc != null){
                    $policy->verification_doc = $verificationDoc;
                    $policy->save();
                }
            }

            if($product->type == 'Cellphone') {
                if ($request->get('devices') != null) {
                    foreach ($request->get('devices') as $key => $device) {
                        $data = array();
                        $policy_id = $policy->id;
                        $customer_id = $user_id;
                        $device_type = htmlspecialchars(strip_tags($device['device_type']));
                        $imei = htmlspecialchars(strip_tags($device['imei']));
                        $phone_value = htmlspecialchars(strip_tags($device['phone_value']));
                        $cell_phone_make = htmlspecialchars(strip_tags($device['cell_phone_make']));
                        if($cell_phone_make == 'Other')
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
                            'cell_phone_model'      => $cell_phone_model,
                        ];

                        if ($request->hasFile($request->get('cell_phone_front'))['devices'][$key]['cell_phone_front']) {
                            $file = $request->file($request->get('cell_phone_front'))['devices'][$key]['cell_phone_front'];
                            $name = $file->getClientOriginalName();
                            $filePath = 'device/' . $policy->customer_id . '/' . 'Cellphone' . '/front' . '/' . $name;
                            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                            $data['cell_phone_front'] = $filePath;
                        }
                        if ($request->hasFile($request->get('cell_phone_back'))['devices'][$key]['cell_phone_back']) {
                            $file = $request->file($request->get('cell_phone_back'))['devices'][$key]['cell_phone_back'];
                            $name = $file->getClientOriginalName();
                            $filePath = 'device/' . $policy->customer_id . '/' . 'Cellphone' . '/back' . '/' . $name;
                            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                            $data['cell_phone_back'] = $filePath;
                        }
                        
                        $policyCellPhone = $this->policy_cell_phone_interface->add_new_policy_cell_phone($data);
                    }
                }
            }


            switch ($request->Payment_method) {
                case 'VCS':
                    $vcs = new PaymentController;
                    //For Motor Comprehensive
                    if ($request->get('product') == 3) {
                        return $vcs->handlePaymentForQuoteVariable($policy->policyNumber, 'LiveQuote');
                        break;
                    } else {
                        return $vcs->handlePaymentForStart($policy->policyNumber, 'LiveQuote');
                        break;
                    }
                case 'Flutterwave':
                    $rave = new FlutterwaveController;
                    return $rave->handlePayment($policy->policyNumber, $product_plan->slug, $product_plan->flutter_plan_id);
                    break;

                case 'Orange':
                    $orangeMoney = new OrangeMoneyController();
                    return $orangeMoney->webPayIntiliazer($policy->policyNumber, $premium);
                    break;
                case 'RealPay':
                    return $this->realpayPayment($policy);
                    break;
                default:
                    return Redirect::back()->with('error', 'Please select a payment vendor')
                        ->withInput($request->all());
            }

            return response()->json(['success' => 1,'policyNumber'=>$policy->policyNumber], 200);
        } catch (Exception $ex) {
            $Emessage = $ex->getMessage();
            return response()->json(['success' => 0, 'message' => '$Emessage'], 401);
        }
    }


    public function lastId()
    {
        $latest = Policy::orderBy('id', 'desc')->first(['id']);
        return response()->json(['last_id' => $latest ? $latest->id : 0], 200);
    }

    public function generateActivationCode(Request $request)
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
        $activation->product_type_id = Product::where('id', $request->get('product'))->first(array('product_type_id')); //product_type_id
        $activation->product_id = $request->get('product');
        $activation->premium_type_id = Product::where('id', $request->get('product'))->first(array('premium_type_id'));
        $activation->product_plan_id = $request->get('plan');
        $activation->status = 0;
        $saved = $activation->save();

        $check = CustomerGeneratedActivationCode::where('cellphone', $request->get('cellphone'))
            ->where('id_number', $request->get('id_number'))
            ->Where('status', '0')
            ->Where('product_id', $request->get('product'))
            ->Where('plan_id', $request->get('plan'))
            ->first(array('activation_code'));
        if ($check == NULL) {
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
                return response()->json(['activationCode' => $activation_code], 200);
            } else {
                return response()->json('Can not process the activation.Please contact administration. ', 401);
            }
        } else {
            return response()->json(['activationCode' => $check->activation_code], 200);
        }
    }


}
