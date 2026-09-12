<?php

namespace AlphaDirect\Http\Controllers\Whatsapp;

use AlphaDirect\AccidentDriver;
use AlphaDirect\AccidentInjury;
use AlphaDirect\City;
use AlphaDirect\Claim;
use AlphaDirect\ClaimAccident;
use AlphaDirect\ClaimAccidentPassenger;
use AlphaDirect\ClaimKeyLoss;
use AlphaDirect\ClaimLife;
use AlphaDirect\ClaimThirdParty;
use AlphaDirect\ClaimVehicle;
use AlphaDirect\Country;
use AlphaDirect\CustomerFeedback;
use AlphaDirect\Helper;
use AlphaDirect\Http\Controllers\Payment\Flutterwave\FlutterwaveController;
use AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController;
use AlphaDirect\Http\Controllers\Payment\VCS\PaymentController;
use AlphaDirect\Http\Controllers\Payment\RealPay\RealPayController;
use AlphaDirect\OTP;
use AlphaDirect\PolicyBeneficiary;
use AlphaDirect\PolicyMember;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\RecipientKyc;
use AlphaDirect\State;
use AlphaDirect\Supplier;
use AlphaDirect\Transaction;
use AlphaDirect\Vehicle;
use AlphaDirect\Activation;
use AlphaDirect\VehicleMake;
use AlphaDirect\CustomerBanking;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Customer;
use AlphaDirect\Region;
use AlphaDirect\KYC;
use AlphaDirect\Policy;
use AlphaDirect\Lookup;
use Crypt;
use File;
use DB;
use Http\Client\Exception;
use Illuminate\Http\Request;
use Hash;
use Carbon\Carbon;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\Http\Controllers\SmsMessaging;
use Pnlinh\InfobipSms\Facades\InfobipSms;

class WhatsappAPIController extends Controller
{
    public function vehicleMake(Request $request)
    {
        if($request->get_all == 0){
            $vehcileMake = array(
                'Toyota','Ford','BMW','Mercedes-Benz','Honda','Nissan','Volkswagen (VW)', 'Mazda','Isuzu',
                'Hyundai','Land Rover','Kia','Jeep','Volvo','Audi ','Mahindra','Renault','Suzuki','Haval',
                'Tata','Chevrolet','Land Rover'
            );
            $vehcileMake = VehicleMake::select('id', 's_Make')->whereIn('s_Make', $vehcileMake)->groupBy('s_Make')->get();
        }else{
            $vehcileMake = VehicleMake::groupBy('s_Make')->get(['id', 's_Make']);
        }

        if ($vehcileMake)
            return response()->json(['success' => true, 'makes' => $vehcileMake], 200);
        else
            return response()->json(['success' => false, 'message' => 'No vehicle makes found.'], 401);

    }

    public function getvehicleMake(Request $request)
    {
        $vehcileMake = VehicleMake::select('id', 's_Make')->groupBy('s_Make')->paginate(20);
        if ($vehcileMake)
            return response()->json(['success' => true, 'makes' => $vehcileMake], 200);
        else
            return response()->json(['success' => false, 'message' => 'No vehicle makes found.'], 401);

    }

    public function vehicleModel(Request $request)
    {
        $vehcileModels = VehicleMake::where('s_Make', $request->get('vehicle_make'))->get(['s_Variant', 's_IntroDate', 's_DiscDate']);
        if ($vehcileModels)
            return response()->json(['success' => true, 'makes' => $vehcileModels], 200);
        else
            return response()->json(['success' => false, 'message' => 'No vehicle models found.'], 401);

    }

    public function isImageValid($image)
    {
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
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function login(Request $request)
    {
        if (filter_var($request->login_email, FILTER_VALIDATE_EMAIL) && $request->login_email != null) {
            $customer = Customer::leftJoin('customer_profile', 'customer_profile.customer_id', '=', 'customer.id')
                ->where('customer.email', $request->login_email)
                ->first();
            if ($customer) {
                if ($customer->email == $request->login_email) {
                    if (Hash::check($request->login_password, $customer->password)) {
                        $foundCustomer = Customer::leftJoin('customer_profile', 'customer_profile.customer_id', '=', 'customer.id')
                            ->leftJoin('customer_kyc', 'customer_kyc.customer_id', '=', 'customer.id')
                            ->where('customer.email', $customer->email)
                            ->first(['customer.id', 'customer.firstName', 'customer.lastName', 'customer.email', 'customer_profile.omang', 'customer_profile.passport', 'customer_kyc.compliance as KYC_compliance']);
                        return response()->json([
                            'success' => true, 'message' => "User Found", 'customer' => $foundCustomer
                        ], 200);
                    } else {
                        return response()->json([
                            'success' => false, 'message' => "Invalid password."
                        ], 401);
                    }
                } else {
                    return response()->json([
                        'success' => false, 'message' => "Invalid Email."
                    ], 401);
                }
            } else {
                return response()->json([
                    'success' => false, 'message' => "Invalid email/password."
                ], 401);
            }
        } elseif (is_numeric($request->login_email) && $request->login_email != null) {
            $customer = Customer::leftJoin('customer_profile', 'customer_profile.customer_id', '=', 'customer.id')
                ->where('customer.cellphone', $request->login_email)
                ->first();
            if ($customer) {
                if ($customer->cellphone == $request->login_email) {
                    if (Hash::check($request->login_password, $customer->password)) {
                        $foundCustomer = Customer::leftJoin('customer_profile', 'customer_profile.customer_id', '=', 'customer.id')
                            ->leftJoin('customer_kyc', 'customer_kyc.customer_id', '=', 'customer.id')
                            ->where('customer.cellphone', $customer->cellphone)
                            ->first(['customer.id', 'customer.firstName', 'customer.lastName', 'customer.email', 'customer_profile.omang', 'customer_profile.passport', 'customer_kyc.compliance as KYC_compliance']);
                        return response()->json([
                            'success' => true, 'message' => "User Found", 'customer' => $foundCustomer
                        ], 200);
                    } else {
                        return response()->json([
                            'success' => false, 'message' => "Invalid password."
                        ], 401);
                    }
                } else {
                    return response()->json([
                        'success' => false, 'message' => "Invalid Contact no.."
                    ], 401);
                }
            } else {
                return response()->json([
                    'success' => false, 'message' => "Invalid  Contact no./password."
                ], 401);
            }
        }
        {
            return response()->json(['success' => false, 'message' => 'Customer not found'], 401);
        }
    }

    public function createPolicyInstantInsurance(Request $request)
    {
        try {
            $activationCodeData = $this->getActivationCodeData($request->activationcode);
            if ($activationCodeData['status'] == 0) {
                $product = Product::where('id', $activationCodeData['product']->id)
                    ->first(array('has_vehicle', 'has_member', 'premium_type_id', 'limit', 'kyc_recipient', 'kyc_customer', 'preinspection', 'is_motor_items', 'region_id', 'has_activation_code'));
                if ($product != null) {
                    $storeCustomerInfo = $this->storeCustomerInfo($request);

                    $storePolicyInfo = $this->storePolicyInfo($request, $storeCustomerInfo->id, $activationCodeData);

                    if ($product->has_vehicle == 1)
                        $storeVehicleInfo = $this->storeVehicleInfo($request, $storePolicyInfo->id, $storeCustomerInfo->id);

                    if ($product->has_member == 1)
                        $storebeneficiaryInfo = $this->storePolicyBeneficiaryInfo($request, $storePolicyInfo->id);

                    $customerBankingInfo = $this->storeCustomerBankingInfo($request, $storePolicyInfo->id, $storeCustomerInfo->id);

                     return $this->processPolicyPayment($request,$storePolicyInfo);

                    return response()->json(['success' => true, 'policy_id' => $storePolicyInfo->id], 200);
                } else {
                    return response()->json(['success' => false, 'message' => 'Product not found with product ID'], 401);
                }

            } else {
                return response()->json(['success' => false, 'message' => 'Product Id is null'], 401);
            }
        } catch (Exception $ex) {
            return response()->json(['success' => false, 'message' => $ex->getMessage()], $ex->getCode());
        }
    }

    public function getCountries()
    {
        $countries = Country::groupBy('name')->get(['id', 'name']);
        if ($countries)
            return response()->json(['success' => true, 'countries' => $countries], 200);
        else
            return response()->json(['success' => false, 'message' => 'No countries found.'], 401);
    }

    private function storePolicyInfo(Request $request, $customerID, $activationCodeData)
    {
        try {
            //Latestid for Policy Number
            $latest = Policy::latest()->first(array('id'));
            if ($latest == null) {
                $latest = collect();
                $latest->id = 0;
            }

            $latest = Policy::latest()->first(array('id'));
            if ($latest == null) {
                $latest = collect();
                $latest->id = 0;
            }

            $product = Product::where('id', $activationCodeData['product']->id)->first(array('has_vehicle', 'sum_insured', 'has_member', 'premium_type_id', 'limit', 'kyc_recipient', 'kyc_customer', 'preinspection', 'is_motor_items', 'region_id', 'has_activation_code'));
            $policy = new Policy();
            $policy->customer_id = $customerID;
            $policy->note = $request->get('note');
            $policy->leadSource = 'LiveQuote';
            $policy->billingStartDate = $request->billing_date;//$this->setDate($request->billing_day);
            $policy->billing_day = $request->billing_day;
            $policy->product_id = $activationCodeData['product']->id;
            $policy->plan_id = $activationCodeData['plans']->id;
            $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;

            $premium = ($activationCodeData['plans']->premium * ($regionVat / 100)) + $activationCodeData['plans']->premium;
            $policy->premium = $premium;
            $policy->premium_freq = $request->frequency;
            $policy->vat = $request->get('premium') * ($regionVat / 100);
            $policy->vat_percent = $regionVat;
            $policy->sum_assured = $product->sum_insured;

            $policy->policyNumber = 'MIS' . Carbon::now()->year . str_pad(($latest->id + 1), 6, '0', STR_PAD_LEFT);
            $policy->status = 0;
            $policy->has_vehicle = $product->has_vehicle;
            $policy->has_member = $product->has_member;
            $policy->preinspection = $product->preinspection;
            $policy->is_motor_items = $product->is_motor_items;
            $policy->limit = $product->limit;
            $policy->kyc_customer = $product->kyc_customer;
            $policy->kyc_recipient = $product->kyc_recipient;
            $addDays = 0;

            //$activation = Activation::where('activation_code', $request->get('activation_code'))->first();
            //$activation->product_id = $request->get('product');
            //$activation->product_plan_id = $request->get('plan');
            //$activation->status = 1;
            //$activation->save();
            $policy->activation_code = $activationCodeData['activation_code'];
            $policy->serial_code = $activationCodeData['serial_code'];
            $addDays = $activationCodeData['trial_periods'];

            $policy->trial_period = Carbon::now()->addDays($addDays)->format('Y-m-d');
            $saved = $policy->save();
            if ($saved)
                return $policy;
            else
                return null;
        } catch (Exception $ex) {
            return null;
        }
    }

    public function getActivationCodeData($code)
    {
        try {
            //code...
            $activationProduct = Activation::where('activation_code', $code)->where('status', 0)->first();

            if ($activationProduct != null) {
                $plans = Productplan::where('id', $activationProduct->product_plan_id)
                    ->first(array('id', 'name', 'premium'));
                $product = Product::where('id', $activationProduct->product_id)
                    ->first(array('id', 'name', 'premium_type_id', 'has_vehicle', 'has_member'));

                $return = array();
                $return['product'] = $product;
                $return['activation_code'] = $activationProduct->activation_code;
                $return['serial_code'] = $activationProduct->serial_code;
                $return['trial_periods'] = $activationProduct->trial_periods;
                $return['plans'] = $plans;
                $return['status'] = $activationProduct->status;

                return $return;
            } else {
                return ['status' => 'failed'];
            }
        } catch (\Exception $ex) {
            //throw $th;
            return ['status' => 'failed'];
        }
    }

    public function processPolicyPayment($request, $policy)
    {
         try {
            switch ($request->Payment_method) {
                case 'VCS':
                   /*  $vcs = new PaymentController;
                    //For Motor Comprehensive
                    if ($request->get('product') == 3) {
                        return $vcs->handlePaymentForQuoteVariable($policy->policyNumber, 'LiveQuote');
                        break;
                    } else {
                        return $vcs->handlePaymentForStart($policy->policyNumber, 'LiveQuote');
                        break;
                    } */
                    $sms = new SmsMessaging();
                    $sms->sendpaymentUrl($request->phone,$policy->id,$policy->policyNumber,'WhatsappApi',$premium = null);
                    break;

                case 'Flutterwave':
                    $rave = new FlutterwaveController;
                    //return $rave->handlePayment($policy->policyNumber, $product_plan->slug, $product_plan->flutter_plan_id);
                    break;

                case 'Orange':
                    $orangeMoney = new OrangeMoneyController();
                    return $orangeMoney->webPayIntiliazer($policy->policyNumber, $premium = $policy->premium + $policy->vat);
                    break;
                case 'RealPay':
                    $RealPayController = new RealPayController();
                    if ($request->billing_day != null) {
                        $date = $request->billing_day;
                    } else {
                        $current_timestamp = Carbon::now()->timestamp;
                        $date = date("d", $current_timestamp);
                    }
                    if ($date != null)
                        $success = $RealPayController->leftOutPremiumPayment($policy, $request->leftout_premium, $date);

                    if ($success == true)
                        return $RealPayController->addClientRealPayAlphaFePay($policy, $premium = $policy->premium + $policy->vat, $customerExist = null, $existingPolicy = 0, $request->leftout_premium);

                    break;
                default:
                    return Redirect::back()->with('error', 'Please select a payment vendor')
                        ->withInput($request->all());
            }
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
    }

    public function storeCustomerBankingInfo($request, $policyID, $customerID)
    {
        try {
            $banking = new CustomerBanking();
            $banking->customer_id = $customerID;
            $banking->policy_id = $policyID;
            $banking->billing = $request->get('Payment_method');
            $banking->billingCell = $request->get('phone');
            if ($request->get('Payment_method') == 'RealPay') {
                $banking->bankName = $request->get('bankName');
                $banking->branchCode = $request->get('branchCode');
                $banking->accountNumber = $request->get('accountNumber');
                $banking->accountType = $request->get('bankAccountType');
            }
            $banking->billingStartDate = $this->setDate($request->billing_day);
            $banking->billing_day = $request->billing_day;
            $saved = $banking->save();
        } catch (Exception $ex) {
            return null;
        }
    }

    public function storePolicyBeneficiaryInfo($request, $policyID)
    {
        try {
            if ($request->get('beneficiaries') != null) {
                foreach ($request->get('beneficiaries') as $key => $beneficiary) {
                    if ($beneficiary['beneficiaryRelation'] != null) {
                        $b = new PolicyBeneficiary();
                        $b->policy_id = $policyID;
                        $b->relation = $beneficiary['beneficiaryRelation'];
                        $b->omang = $beneficiary['beneficiaryOmang'];
                        $b->passport = $beneficiary['beneficiaryPassport'];
                        $b->first_name = $beneficiary['beneficiaryFName'];
                        $b->last_name = $beneficiary['beneficiaryLName'];
                        $b->dob = Carbon::createFromFormat('d/m/Y', $beneficiary['beneficiaryDOB']);
                        $b->gender = $beneficiary['beneficiaryGender'];
                        $b->payment = $beneficiary['beneficiaryPayment'];
                        $b->save();
                    }
                }
            }
            return 1;
        } catch (Exception $ex) {
            return null;
        }
    }

    public function storeVehicleInfo($request, $policyID, $customerID)
    {
        try {
            $vehicle = new Vehicle();
            $vehicle->customer_id = $customerID;
            $vehicle->policy_id = $policyID;
            $vehicle->vehiclePlate = $request->vehiclePlate;
            $vehicle->make = $request->make;
            $vehicle->model = $request->model;
            $vehicle->year = $request->year;
            $vehicle->vinnumber = $request->vinnumber;
            $vehicle->engineNo = $request->enginenumber;
            $vehicle->financial_interest = $request->financial_interest;
            $vehicle->claim_count = $request->claim_count;
            $vehicle->is_imported = $request->is_imported;
            /*Store image URL for vehicle*/
            $vehicle->front = $request->get('front_image');
            $vehicle->back = $request->get('back_image');
            $vehicle->right = $request->get('right_image');
            $vehicle->left = $request->get('left_image');
            $vehicle->vehicleRegistration = $request->get('vehicleRegistration_image');

            $saved = $vehicle->save();
            if ($saved)
                return $vehicle->id;
            else
                return null;
        } catch (Exception $ex) {
            return null;
        }
    }

    public function storeCustomerInfo(Request $request)
    {
        try {
            $profile = null;
            if ($request->get('phone') != null) {
                $profile = Customer::where('cellphone', $request->get('phone'))->orderBy('id', 'desc')->first();
            }
            if ($profile != null) {
                $profile->customer_id = $profile->id;
            }
            //check for omang and passport
            if ($request->get('omang') != null && $request->get('passport') != null) {
                $profile = CustomerProfile::where('omang', $request->get('omang'))->orWhere('passport', $request->get('passport'))->orderBy('id', 'desc')->first(array('customer_id'));
            } elseif ($request->get('omang') == null && $request->get('passport') != null) {
                $profile = CustomerProfile::where('passport', $request->get('passport'))->orderBy('id', 'desc')->first(array('customer_id'));
            } elseif ($request->get('omang') != null && $request->get('passport') == null) {
                $profile = CustomerProfile::where('omang', $request->get('omang'))->orderBy('id', 'desc')->first(array('customer_id'));
            }

            if ($profile == null) {
                $user = new Customer();
                $user->firstName = $request->get('firstname');
                $user->lastName = $request->get('lastname');
                $user->email = $request->get('email');
                $user->cellphone = $request->get('phone');
                if ($request->get('password') != NULL)
                    $user->password = Hash::make($request->get('password'));
                $user->save();

                $ifExists = CustomerProfile::where('customer_id',$user->id)->exists();
                if(!empty($ifExists))
                {
                    $profile = CustomerProfile::where('customer_id',$user->id)->first();
                }else{
                    /*Add Records to Customers Profile table*/
                    $profile = new CustomerProfile();
                }
                $profile->customer_id = $user->id;
                $profile->gender = $request->get('gender');
                $profile->address = $request->get('address');
                $profile->omang = $request->get('omang');
                $profile->passport = $request->get('passport');
                $profile->city = $request->get('city');
                $profile->maritalstatus = $request->get('maritalstatus');
                $profile->dob = Carbon::createFromFormat('d/m/Y', $request->get('dob'));
                $profile->save();
                $kyc = new KYC();
                $kyc->customer_id = $user->id;
                $kyc->omangExpiry = $request->get('omangexpiry');
                $kyc->passportExpiry = $request->get('passportexpiry');
                $kyc->save();
            } else {
                $user = Customer::where('id', $profile->customer_id)->first();
                $user->firstName = $request->get('firstname');
                $user->lastName = $request->get('lastname');
                $user->email = $request->get('email');
                $user->cellphone = $request->get('phone');
                $userSaved = $user->save();

                $profile = CustomerProfile::where('customer_id', $profile->customer_id)->first();
                $profile->customer_id = $user->id;
                $profile->gender = $request->get('gender');
                $profile->address = $request->get('address');
                $profile->omang = $request->get('omang');
                $profile->passport = $request->get('passport');
                $profile->maritalstatus = $request->get('maritalstatus');
                $profile->city = $request->get('city');
                $profile->dob = Carbon::createFromFormat('d/m/Y', $request->get('dob'));
                $profile->save();
            }
            if ($profile->save() && $profile->save())
                return $user;
            else
                return null;
        } catch (Exception $ex) {
            return false;
        }
    }

    private function setDate($day)
    {
        $current_timestamp = Carbon::now()->timestamp;
        $newDate = date("d", $current_timestamp);
        $days = $newDate - $day;
        if ($days < 0) {
            $date = Carbon::now()->addDays(abs($days))->format('Y-m-d');
            return $date;
        } elseif ($days > 0) {
            $date = Carbon::now()->addDays(30.4375 - abs($days))->format('Y-m-d');  /*env('AVERAGE_DAYS')*/
            return $date;
        } else {
            $date = Carbon::now()->addDays($days)->format('Y-m-d');
            return $date;
        }
    }

    public function storeCustomerKycImages(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'policy_id' => 'required',
            'type' => 'required',
            'image' => 'required',

        ]);
        if ($validator->fails()) {
            $responseArr['error'] = 1;
            $responseArr['message'] = $validator->errors();;
            return response()->json($responseArr, 500);
        } else {
            $policy = Policy::where('id', $request->policy_id)->first();
            if ($policy) {
                $customer = KYC::where('customer_id', $policy->customer_id)->first();
                if ($customer) {
                    $customerId = $policy->customer_id;
                    $type = $request->type;
                    $filePath = null;
                    $image = $request->image;  // your base64 encoded
                    $image = str_replace('data:image/png;base64,', '', $image);
                    $image = str_replace(' ', '+', $image);
                    $imageName = str_random(10) . '.png';
                    $filePath = $this->imageType($type, $image, $imageName, $customerId);
                    if ($filePath) {
                        KYC::where('customer_id', $policy->customer_id)->update([
                            $type => $filePath
                        ]);
                        $responseArr['success'] = 0;
                        $responseArr['message'] = 'Document updated successfully';
                        return response()->json($responseArr, 200);
                    } else {
                        $responseArr['success'] = 1;
                        $responseArr['message'] = 'Document not uploaded';
                        return response()->json($responseArr, 200);
                    }
                }
            } else {
                $responseArr['success'] = 1;
                $responseArr['message'] = 'Policy not found';
                return response()->json($responseArr, 500);
            }
        }
    }

    private function imageType($type, $image, $imageName, $customerId)
    {
        $filePath = null;
        if ($type == 'driving_license') {
            $filePath = 'MIS/' . $customerId . '/' . 'Customer' . '/driving_license' . '/' . $imageName;
            Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
        }
        if ($type == 'omang') {
            $filePath = 'MIS/' . $customerId . '/' . 'Customer' . '/omang' . '/' . $imageName;
            Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
        }
        if ($type == 'proof_residence') {

            $filePath = 'MIS/' . $customerId . '/' . 'Customer' . '/proof_residence' . '/' . $imageName;
            Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
        }
        if ($type == 'proof_income') {

            $filePath = 'MIS/' . $customerId . '/' . 'Customer' . '/proof_income' . '/' . $imageName;
            Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
        }
        if ($type == 'passport') {
            $filePath = 'MIS/' . $customerId . '/' . 'Customer' . '/passport' . '/' . $imageName;
            Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
        }

        return $filePath;
    }

    public function getpoliciesbycellphone(Request $request)
    {
        if($request->get('cellphone') != Null && $request->get('policynumber') == null){
            $profile = Customer::where('cellphone', $request->cellphone)->orderBy('id', 'desc')->first();
            $customer_id = $profile->id;
        }
        else{
            $policy = Policy::where('policyNumber',$request->get('policynumber'))->orderBy('id', 'desc')->first();
            $customer_id = $policy->customer_id;
        }
        $policies = Policy::leftJoin('products', 'products.id', '=', 'policies.product_id')
            ->where('policies.customer_id',$customer_id)
            ->orderBy('policies.id', 'DESC')
            ->get([
                'policies.id',
                'policies.policyNumber',
                'products.name',
                'products.has_vehicle',
                'policies.status'
            ]);
        $count = count($policies);
        if($count > 0){
            return response()->json([
                'success'=>true,'message'=>"Policies found for the user",'policies'=>$policies
            ],200);
        }else{
            return response()->json([
                'success'=>false,'message'=>"No policy found for the user"
            ],401);
        }
    }

    public function getclaimsbycellphone(Request $request)
    {
        if($request->get('cellphone') != Null && $request->get('policynumber') == null){
            $profile = Customer::where('cellphone', $request->cellphone)->orderBy('id', 'desc')->first();
            $customer_id = $profile->id;
        }
        else{
            $policy = Policy::where('policyNumber',$request->get('policynumber'))->orderBy('id', 'desc')->first();
            $customer_id = $policy->customer_id;
        }
        $claims = Claim::leftJoin('policies', 'policies.id', '=', 'claims.policy_id')
            ->where('claims.customer_id',$customer_id)
            ->orderBy('claims.id', 'DESC')
            ->get(['claims.id','claims.claim_number','claims.claim_type','claims.status','policies.policyNumber']);
        $count = count($claims);
        if($count > 0){
            return response()->json([
                'success'=>true,'message'=>"Claims found for the user",'claims'=>$claims
            ],200);
        }else{
            return response()->json([
                'success'=>false,'message'=>"No claims found for the user"
            ],401);
        }
    }

    //to get policy details as per policyNumber (MISxxxxxxxx)
    public function getpolicybypolicynumber(Request $request){

        $policy = Policy::where('policyNumber', $request->get('policynumber'))->first();

        if($policy == NULL)
            return response()->json(['success' => false, 'message' => 'Policy not found.'], 401);

        $user = Customer::with(['profile'])->where('id', $policy->customer_id)->first(array('id', 'firstName', 'lastName', 'email', 'cellphone'));
        $user->profile->dob = Carbon::parse($user->profile->dob)->format('Y-m-d');
        $product = Product::where('id', $policy->product_id)->first(array('name', 'product_type_id'));
        $plan = Productplan::where('product_id', $policy->product_id)->first(array('name'));
        $beneficiaries = PolicyBeneficiary::where('policy_id', $policy->id)->get();
        $banking = CustomerBanking::where('policy_id', $policy->id)->first();
        $kyc = KYC::where('customer_id', $policy->customer_id)->first();
        if ($policy->has_vehicle)
            $vehicle = Vehicle::where('policy_id', $policy->id)->first();
        else
            $vehicle = NULL;
        return response()->json(
            array(
                'success' => 1,
                'policy' => $policy,
                'plan' => $plan,
                'user' => $user,
                'product' => $product,
                'beneficiary' => $beneficiaries,
                'banking' => $banking,
                'kyc' => $kyc,
                'vehicle' => $vehicle
            ),
            200
        );
    }

    public function getcutomerdetails(Request $request)
    {
        if($request->cellphone != null){
            $customerDetail= Customer::where('cellphone',$request->cellphone)->first(array('id','firstName','middleName','lastName','email','cellphone'));
            $profile = CustomerProfile::where('customer_id',$customerDetail->id)->first();
            return response()->json(['success'=>true,'profile'=>$profile,'Cdetail'=>$customerDetail], 200);
        } else {
            return response()->json(['success' => false, 'message' => 'Customer not found.'], 401);
        }
    }

    public function authenticateOTPCodeUsingOtpCodeAndCellphone($query, $otp_code, $cellphone)
    {
        return $query->where('otp', $otp_code)->where('cellphone', $cellphone)->exists();
    }

    /** functon for creating 6 digit OTP code */
    public function generateOTP()
    {
        $string = str_random(6);

        // generate a otp based on 6 digits +
        $otp = Helper::gen_ustring(100000, 999999);

        // shuffle the result
        $string = str_shuffle($otp);
    }

    /**Api function for sending an OTP code,   */
    public function requestOTP(Request $request)
    {
        //code to generate OTP and send it to user
        $cellphone = $request->cellphone;
        $customer = new Customer();
        $otp = new OTP();

        try {

            if ($cellphone != null) {
                $otp_response = $otp->OTPStore($cellphone); //save otp
                $data = $otp_response->getData();
                $sms_status = event(new \AlphaDirect\Events\SendSms('+267' . $request->cellphone, 'Alpha Direct, Your Verification Code is:' . ' ' . $data->otp_code));
               // $sms_status = InfobipSms::send('+267' . $request->cellphone, 'Alpha Direct, Your Verification Code is:' . ' ' . $data->otp_code);
                return response()->json(['status' => 'success','message' =>'OTP code sent successfully to '  . $cellphone], 200);
            } else {
                return response()->json(['status'=>'failed','message'=>'User does not exists'],401);
            }
        } catch (Exception $ex) {
            return response()->json(['status'=>'failed','message'=>$ex->getMessage()], 401);
        }
    }

    public function authenticateOTP(Request $request)
    {
        //code to autheticate the Customers OTP and Number
        $cellphone = $request->phoneNumber;
        $otp_code = $request->otpCode;

        try {

            if ($cellphone != null && $otp_code != null) {

                $otp = new OTP(); //instance of OTP model

                $otpValid = $otp->authenticateOTPCodeUsingOtpCodeAndCellphone($otp_code, $cellphone);

                if ($otpValid == true) { //if otp has corresponding cellphone

                    $otpData = $otp->getOTPDataUsingOTPCode($otp_code);
//                    $user_id = Customer::where('cellphone', $cellphone)->first()->id;
                    //$sms = InfobipSms::send('+267' . $otpData->cellphone, 'Alpha Direct, Your OTP Has been Verified');
                    $deleteOTP = $otp->deleteOTP($otpData->id);
                    return response()->json(['status' => 'success', 'message' => 'OTP successfully verified'], 200);
                } else {
                    return response()->json(['status' => 'failed', 'message' => 'OTP verification failed'], 401);
                }
            } else {
                return response()->json('Phone number & OTP code is empty', 401);
            }
        } catch (Exception $th) {

            return response()->json(['error' => $th->getMessage()]);
        }
    }

    public function getclaimbypolicynumber(Request $request){

        $policy = Policy::where('policyNumber',$request->get('policynumber'))->orderBy('id', 'desc')->first();
        $customerid = $policy->customer_id;

        $claim = Claim::where('customer_id',$customerid)->first();
        if($claim)
            $claimType = $claim->claim_type;
        else
            return response()->json(['success'=>false,'message'=>'Claim not found.'], 401);

        if($claimType == "Accident"){
            $passengers = ClaimAccidentPassenger::where('claim_id',$claim->id)->get();
            foreach ($passengers as $passenger){
                $passenger_country = Country::where('id',$passenger->country)->first(['name']);
                $passenger->country_name = $passenger_country->name;
                $passenger_state = State::where('id',$passenger->state)->first(['name']);
                $passenger->state_name = $passenger_state->name;
            }
            $driverDetail = AccidentDriver::where('claim_id',$claim->id)->first();
            $driverCountry = Country::where('id',$driverDetail->country)->first();
            $driverState = State::where('id',$driverDetail->state)->first();
            $driverCity = City::where('name',$driverDetail->city)->first();
            $accidentDetails = ClaimAccident::where('claim_id',$claim->id)
                ->first([
                    'id',
                    'claim_id',
                    'third_party as third_party_involved',
                    'date_of_accident',
                    'detail_of_accident',
                    'purpose_of_trip',
                    'police_report',
                    'vehicle_document',
                    'party_at_fault',
                    'place_of_accident',
                    'time_of_accident',
                ]);
            $thirdParty = ClaimThirdParty::where('claim_id',$claim->id)->get();
            foreach($thirdParty as $third)
            {
                $injury = AccidentInjury::where('otherparty_id', $third->id)->get()->toArray();
                $third->injury = $injury;
            }
            $recipient = RecipientKyc::where('claim_id',$claim->id)->first();
            $claimDetails = Claim::where('id',$request->get('claim_id'))->first(['id','policy_id','claim_number','claim_type','status']);
            $policyNumber = $request->get('policynumber');
            $customer_id = Policy::where('policyNumber',$request->get('policynumber'))->first(['customer_id']);
            return response()->json(
                [
                    'success'=>true,
                    'message'=>'Claim found.',
                    'claimDetails'=>$claimDetails,
                    'passengers'=>$passengers,
                    'passenger'=>$passenger,
                    'driverDetails'=>$driverDetail,
                    'country'=>$driverCountry,
                    'state'=>$driverState,
                    'city'=>$driverCity,
                    'accidentDetails'=>$accidentDetails,
                    'thirdParty'=>$thirdParty,
                    'recipient'=>$recipient,
                    'policyNumber'=>$policyNumber,
                    'customer'=>$customer_id,
                ],
                200);

        }elseif($claimType == "Life"){
            $claimDetails = Claim::join('claim_life','claim_life.claim_id', '=' , 'claims.id')
                ->join('claim_recipient','claim_recipient.claim_id', '=' , 'claims.id')
                ->where('claims.id',$request->get('claim_id'))
                ->first([
                    'claims.id',
                    'claims.claim_number',
                    'claims.claim_type',
                    'claims.status',
                    'claim_life.date_of_death',
                    'claim_life.cause_of_death',
                    'claim_life.certificate',
                    'claim_life.description',
                    'claim_recipient.driving_license',
                    'claim_recipient.omang',
                    'claim_recipient.proof_residence',
                    'claim_recipient.proof_income',
                    'claim_recipient.passport',
                ]);
            $policyNumber = $request->get('policynumber');
            $beneficiary = PolicyBeneficiary::where('policy_id',$claim->policy_id)->get();
            $customer_id = Policy::where('policyNumber',$request->get('policynumber'))->first(['customer_id']);
            return response()->json(
                [
                    'success'=>true,
                    'message'=>'Claim found.',
                    'claimDetails'=>$claimDetails,
                    'beneficiary'=>$beneficiary,
                    "policyNumber"=>$policyNumber,
                    'customer'=>$customer_id,
                ],
                200);
        }elseif($claimType == "Glass"){
            $claimData = Claim::join('claim_vehicle','claim_vehicle.claim_id', '=' , 'claims.id')
                ->join('claim_recipient','claim_recipient.claim_id', '=' , 'claims.id')
                ->where('claims.id',$request->get('claim_id'))
                ->first();
            return response()->json(
                [
                    'success'=>true,
                    'message'=>'Claim found.',
                    'claimDetails'=>$claimData,
                ],
                200);
        }else{
            return response()->json(['success'=>false,'message'=>'Claim type not found.'], 401);
        }
    }

    public function createPolicy(Request $request)
    {
        try {
            if ($request->activationcode != null){
                $activationProduct = Activation::where('activation_code', $request->activationcode)->where('status', 0)->first(array('product_id', 'product_plan_id', 'activation_code', 'serial_code', 'trial_periods'));
                if ($activationProduct != null) {
                    $plan = Productplan::where('id', $activationProduct->product_plan_id)
                        ->first(array('sum_assured', 'premium'));
                    $product = Product::where('id', $activationProduct->product_id)
                        ->first(array('has_vehicle', 'has_member', 'premium_type_id', 'limit', 'kyc_recipient', 'kyc_customer', 'preinspection', 'is_motor_items', 'region_id', 'has_activation_code'));
                }
            }
            else{
                $activationProduct = new Activation();
                $activationProduct->product_id = 3;
                $activationProduct->product_plan_id = 2;
                $plan = Productplan::where('id', $activationProduct->product_plan_id)
                    ->first(array('sum_assured', 'premium'));
                $product = Product::where('id', $activationProduct->product_id)
                    ->first(array('has_vehicle', 'has_member', 'premium_type_id', 'limit', 'kyc_recipient', 'kyc_customer', 'preinspection', 'is_motor_items', 'region_id', 'has_activation_code'));
                }
            //check for email and cellphone
            $profile = null;
            if ($request->get('phone') != null) {
                $profile = Customer::where('cellphone', $request->get('phone'))->orderBy('id', 'desc')->first();
            }
            if ($profile != null) {
                $profile->customer_id = $profile->id;
            }
            //check for omang and passport
            if ($request->get('omang') != null && $request->get('passport') != null) {
                $profile = CustomerProfile::where('omang', $request->get('omang'))->orWhere('passport', $request->get('passport'))->orderBy('id', 'desc')->first(array('customer_id'));
            } elseif ($request->get('omang') == null && $request->get('passport') != null) {
                $profile = CustomerProfile::where('passport', $request->get('passport'))->orderBy('id', 'desc')->first(array('customer_id'));
            } elseif ($request->get('omang') != null && $request->get('passport') == null) {
                $profile = CustomerProfile::where('omang', $request->get('omang'))->orderBy('id', 'desc')->first(array('customer_id'));
            }

            if ($profile == null) {
                $user = new Customer();
                $user->firstName = $request->get('firstname');
                $user->lastName = $request->get('lastname');
                $user->email = $request->get('email');
                $user->cellphone = $request->get('phone');
                if ($request->get('password') != NULL)
                    $user->password = Hash::make($request->get('password'));
                $user->save();
                $ifExists = CustomerProfile::where('customer_id',$user->id)->exists();
                if(!empty($ifExists))
                {
                    $profile = CustomerProfile::where('customer_id',$user->id)->first();
                }else{
                    /*Add Records to Customers Profile table*/
                    $profile = new CustomerProfile();
                }
                $profile->customer_id = $user->id;
                $profile->gender = $request->get('gender');
                $profile->address = $request->get('address');
                $profile->omang = $request->get('omang');
                $profile->state = $request->get('state');
                $profile->passport = $request->get('passport');
                $profile->countryId = $request->get('passportIssuingCountry');
                $profile->city = $request->get('city');
                $profile->maritalstatus = $request->get('maritalstatus');
                $profile->dob = $request->get('dob');
                $profile->save();
                $kyc = new KYC();
                $kyc->customer_id = $user->id;
                $kyc->omangExpiry = $request->get('omangexpiry');
                $kyc->passportExpiry = $request->get('passportexpiry');
                $kyc->save();
            } else {
                $user = Customer::where('id', $profile->customer_id)->first();
                $user->id = $profile->customer_id;
                $user->firstName = $request->get('firstname');
                $user->lastName = $request->get('lastname');
                $user->email = $request->get('email');
                $user->cellphone = $request->get('phone');
                $userSaved = $user->save();

                $profile = CustomerProfile::where('customer_id', $profile->customer_id)->first();
                $profile->customer_id = $user->id;
                $profile->gender = $request->get('gender');
                $profile->address = $request->get('address');
                $profile->omang = $request->get('omang');
                $profile->passport = $request->get('passport');
                $profile->maritalstatus = $request->get('maritalstatus');
                $profile->city = $request->get('city');
                $profile->dob = $request->get('dob');
                $profile->save();
            }

            //Latestid for Policy Number
            $latest = Policy::latest()->first(array('id'));

            $policy = new Policy();
            $policy->customer_id = $user->id;
            $policy->agent_id = $request->get('agent_id');
            $policy->activation_code = $request->activationcode;
            $policy->note = $request->get('note');
            $policy->leadSource = 'Whatsapp';
            if ($request->billing_day != null) {
                $policy->billing_day = $request->billing_day;
                $policy->billingStartDate = $this->setDate($request->billing_day);
            } else {
                $current_timestamp = Carbon::now()->timestamp;
                $policy->billing_day = date("d", $current_timestamp);
                $policy->billingStartDate = $this->setDate($policy->billing_day);
            }
            $policy->product_id = $activationProduct->product_id;

            $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
            if (($product->premium_type_id == 11) && ($activationProduct->product_id != 3)) {

                $premium = ($plan->premium * ($regionVat / 100)) + $plan->premium;
                $policy->plan_id = $request->get('plan');
                $policy->sum_assured = $plan->sum_assured;
                $policy->premium = $plan->premium;
                $policy->premium_freq = $request->frequency;
                $policy->vat = $plan->premium * ($regionVat / 100);
                $policy->vat_percent = $regionVat;
            } else {
                $premium = ($request->get('premium') * ($regionVat / 100)) + $request->get('premium');
                $policy->premium = $request->get('premium');
                $policy->premium_freq = $request->frequency;
                $policy->vat = $request->get('premium') * ($regionVat / 100);
                $policy->vat_percent = $regionVat;
                $policy->sum_assured = $request->get('sum_assured');
            }
            $policy->policyNumber = 'MIS' . Carbon::now()->year . str_pad((substr($latest->id, -6) + 1), 6, '0', STR_PAD_LEFT);
            $policy->status = 0;
            $policy->has_vehicle = $product->has_vehicle;
            $policy->has_member = $product->has_member;
            $policy->preinspection = $product->preinspection;
            $policy->is_motor_items = $product->is_motor_items;
            $policy->limit = $product->limit;
            $policy->kyc_customer = $product->kyc_customer;
            $policy->kyc_recipient = $product->kyc_recipient;

            if ($request->frequency != '1') {
                $policy->first_premium = $request->premium;
                $policy->first_premium_wvat = $request->premium_label_vat;
                $policy->billingStartDate = date("Y-m-d", strtotime("+30 days"));
            } else {
                $policy->first_premium = $request->leftout_premium;
                $policy->first_premium_wvat = $request->leftout_premium_wvat;
                $policy->billingStartDate = $this->setDate($request->billing_day);
            }
            $addDays = 0;
            $policy->trial_period = Carbon::now()->addDays($addDays)->format('Y-m-d');
            $policy->sum_assured = $request->get('sum_insured');
            $saved = $policy->save();
            if ($product->has_member) {
                if ($request->get('beneficiaries') != null) {
                    foreach ($request->get('beneficiaries') as $key => $beneficiary) {
                        if ($beneficiary['beneficiaryRelation'] != null) {
                            $b = new PolicyBeneficiary();
                            $b->policy_id = $policy->id;
                            $b->relation = $beneficiary['beneficiaryRelation'];
                            $b->omang = $beneficiary['beneficiaryOmang'];
                            $b->passport = $beneficiary['beneficiaryPassport'];
                            $b->first_name = $beneficiary['beneficiaryFName'];
                            $b->last_name = $beneficiary['beneficiaryLName'];
                            $b->dob = $beneficiary['beneficiaryDOB'];
                            $b->gender = $beneficiary['beneficiaryGender'];
                            $b->payment = $beneficiary['beneficiaryPayment'];
                            $b->save();
                        }
                    }
                }
            }
            $existingPolicy = '';
            $customerExist = 0;
            $checkCustomerBanking = CustomerBanking::where([
                ['accountNumber', '=', $request->get('accountNumber')],
                ['client_number', '!=', null],
                ['contract_number', '!=', null],
            ])
                ->orderBy('id', 'DESC')
                ->first();

            if ($checkCustomerBanking) {
                if ($checkCustomerBanking->billing_day && $request->billing_day && $checkCustomerBanking->policy_id) {
                    if ($checkCustomerBanking->billing_day == $request->billing_day) {

                        if ($checkCustomerBanking->merge_ref != null)
                            $existingPolicy = Policy::where('policyNumber', $checkCustomerBanking->client_number)->first();
                        else
                            $existingPolicy = Policy::where('id', $checkCustomerBanking->policy_id)->first();

                        $customerExist = 1;
                    }
                }
            }

            $banking = new CustomerBanking();
            $banking->customer_id = $user->id;
            $banking->policy_id = $policy->id;
            $banking->billing = $request->get('Payment_method');
            $banking->billingCell = $request->get('phone');
            if ($request->get('Payment_method') == 'RealPay') {
                $banking->bankName = $request->get('bankName');
                $banking->branchCode = $request->get('branchCode');
                $banking->accountNumber = $request->get('accountNumber');
                $banking->accountType = $request->get('bankAccountType');
            }

            if ($request->frequency == 1)
                $banking->billingStartDate = $this->setDate($request->billing_day);
            elseif ($request->frequency == 2)
                $banking->billingStartDate = Carbon::now()->addMonths(1)->format('Y-m-d');
            else
                $banking->billingStartDate = Carbon::now()->addYear()->format('Y-m-d');

            $banking->billing_day = $request->billing_day;
            $saved = $banking->save();

            if ($product->has_vehicle) {
                $vehicle = new Vehicle();
                $vehicle->customer_id = $user->id;
                $vehicle->policy_id = $policy->id;
                $vehicle->vehiclePlate = $request->vehiclePlate;
                $vehicle->make = $request->make;
                $vehicle->model = $request->model;
                $vehicle->year = $request->year;
                $vehicle->vinnumber = $request->vinnumber;
                $vehicle->engineNo = $request->enginenumber;
                $vehicle->financial_interest = $request->financial_interest;
                $vehicle->financial_interest_other = $request->other_finance;
                $vehicle->claim_count = $request->claim_count;
                $vehicle->is_imported = $request->is_imported;
                $vehicle->mileage = $request->mileage;
                $vehicle->estimated_value = $request->estimated_value;
                $filePath = null;
                if ($request->front) {

                    $image = $request->front;  // your base64 encoded
                    $image = str_replace('data:image/png;base64,', '', $image);
                    $image = str_replace(' ', '+', $image);
                    $imageName = str_random(10) . '.png';
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/front' . '/' . $imageName;
                    Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
                    $vehicle->front = $filePath;
                }
                if ($request->back) {
                    $image = $request->back;  // your base64 encoded
                    $image = str_replace('data:image/png;base64,', '', $image);
                    $image = str_replace(' ', '+', $image);
                    $imageName = str_random(10) . '.png';
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/back' . '/' . $imageName;
                    Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
                    $vehicle->back = $filePath;
                }
                if ($request->right) {
                    $image = $request->right;  // your base64 encoded
                    $image = str_replace('data:image/png;base64,', '', $image);
                    $image = str_replace(' ', '+', $image);
                    $imageName = str_random(10) . '.png';
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/right' . '/' . $imageName;
                    Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
                    $vehicle->right = $filePath;
                }
                if ($request->left) {
                    $image = $request->left;  // your base64 encoded
                    $image = str_replace('data:image/png;base64,', '', $image);
                    $image = str_replace(' ', '+', $image);
                    $imageName = str_random(10) . '.png';
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/left' . '/' . $imageName;
                    Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
                    $vehicle->left = $filePath;
                }
                if ($request->vehicleRegistration) {
                    $image = $request->vehicleRegistration;  // your base64 encoded
                    $image = str_replace('data:image/png;base64,', '', $image);
                    $image = str_replace(' ', '+', $image);
                    $imageName = str_random(10) . '.png';
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/vehicleRegistration' . '/' . $imageName;
                    Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
                    $vehicle->vehicleRegistration = $filePath;
                }
                $saved = $vehicle->save();
            }
            $method = new \AlphaDirect\Http\Controllers\Admin\QuoteController();
            $message = $method->getWhatsappMessage();

            switch ($request->Payment_method) {
                case 'VCS':
                    return response()->json([
                        'success' => 1,
                        'message' => $message,
                        'payment_url' => 'https://start.alphadirect.co.bw/redopayment',
                        'policy_details' => array('policy_id' => $policy->id, 'policy_no' => $policy->policyNumber)
                    ], 200);
                    break;
                case 'RealPay':
                    return $this->realpayPayment($policy, $message);
                    break;
                default:
                    return response()->json([
                        'success' => 0,
                        'message' => 'Please select a Payment Vendor',
                        'policy_details' => array('policy_id' => $policy->id, 'policy_no' => $policy->policyNumber)
                    ], 200);
            }


        } catch (Exception $e) {
            $Emessage = $e->getMessage();
            return response()->json([
                'success' => 0,
                'message' => $Emessage
            ], 401);
        }
    }

    public function realpayPayment($policy, $message)
    {
        $log = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
        $addLog = $log->logEvent($policy->id,1);
        $responseArr = array('ClientCreated'=>0,'ContractCreated'=>0);
        $stringArr = \Opis\Closure\serialize($responseArr);

        $transaction = new Transaction();
        $transaction->policyNumber = $policy->policyNumber;
        $transaction->amount = $policy->premium;
        $transaction->customer_id = $policy->customer_id;
        $transaction->realPayTransaction_id = $policy->id;
        $transaction->referenceNumber = $policy->policyNumber;
        $transaction->status = "PENDING";
        $transaction->save();

        if($addLog == true){
            $payRequest = new RealpayPaymentRequest();
            $payRequest->policy_id = $policy->id;
            $payRequest->first_premium = $policy->first_premium;
            $payRequest->premium = $policy->premium;
            $payRequest->billing_day = $policy->billing_day;
            $payRequest->billing_date = $policy->billingStartDate;
            $payRequest->first_premium_contract = null;
            $payRequest->contract = null;
            $payRequest->status = 0;
            $payRequest->response = $stringArr;
            $payRequest->frequency = $policy->premium_freq;
            $payRequest->save();

            return response()->json([
                'success' => 1,
                'message' => $message,
                'policy_details' => array('policy_id' => $policy->id, 'policy_no' => $policy->policyNumber)
            ], 200);
        }else{
            return response()->json([
                'success' => 0,
                'message' => 'Payment Unsuccessful',
                'policy_details' => array('policy_id' => $policy->id, 'policy_no' => $policy->policyNumber)
            ], 401);
        }

    }

    public function saveClaim(Request $request){
        if($request->claim_type != null){

            $policy = Policy::where('id',$request->get('policy_id'))->first(array('has_vehicle', 'customer_id', 'agent_id', 'kyc_recipient', 'product_id'));
            //Latestid for Claim Number
            $latest = Claim::orderBy('id', 'DESC')->first(array('id'));
            if ($latest == null) {
                $latest = collect();
                $latest->id = 0;
            }

            $customer_email= Customer::where('id', $policy->customer_id)->first();
            $claim = new Claim();
            $claim->claim_number = 'G' . Carbon::now()->year . str_pad(($latest->id + 1), 6, '0', STR_PAD_LEFT);
            $claim->customer_id = $policy->customer_id;
            $claim->agent_id = $policy->agent_id;
            $claim->policy_id = $request->policy_id;
            $claim->claim_type = $request->claim_type;

            $claim->customer_selected = 1;
            $saveClaim = $claim->save();

            if($saveClaim){
                if($request->claim_type == "Life"){
                    $lifeClaim = new ClaimLife();
                    $lifeClaim->claim_id = $claim->id;
                    $lifeClaim->date_of_death = $request->dateOfDeath;
                    $lifeClaim->cause_of_death = $request->causeOfDeath;
                    $lifeClaim->description = $request->descriptionofDeath;
                    if ($request->certificate) {
                        $image = $request->certificate;  // your base64 encoded
                        $image = str_replace('data:image/png;base64,', '', $image);
                        $image = str_replace(' ', '+', $image);
                        $imageName = str_random(10) . '.png';
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $imageName;
                        Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
                        $lifeClaim->certificate = $filePath;
                    }
                    $saveLifeClaim = $lifeClaim->save();
                    if($saveLifeClaim)
                        if($saveClaim && $saveLifeClaim){
                            $recipientKyc = new RecipientKyc();
                            $recipientKyc->policy_id = $claim->policy_id;
                            $recipientKyc->claim_id = $claim->id;
                            if ($request->driving_license) {
                                $image = $request->driving_license;  // your base64 encoded
                                $image = str_replace('data:image/png;base64,', '', $image);
                                $image = str_replace(' ', '+', $image);
                                $imageName = str_random(10) . '.png';
                                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $imageName;
                                Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
                                $recipientKyc->driving_license = $filePath;
                            }
                            if ($request->omang) {
                                $image = $request->omang;  // your base64 encoded
                                $image = str_replace('data:image/png;base64,', '', $image);
                                $image = str_replace(' ', '+', $image);
                                $imageName = str_random(10) . '.png';
                                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $imageName;
                                Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
                                $recipientKyc->omang = $filePath;
                            }
                            if ($request->proof_residence) {
                                $image = $request->proof_residence;  // your base64 encoded
                                $image = str_replace('data:image/png;base64,', '', $image);
                                $image = str_replace(' ', '+', $image);
                                $imageName = str_random(10) . '.png';
                                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $imageName;
                                Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
                                $recipientKyc->proof_residence = $filePath;
                            }
                            if ($request->proof_income) {
                                $image = $request->proof_income;  // your base64 encoded
                                $image = str_replace('data:image/png;base64,', '', $image);
                                $image = str_replace(' ', '+', $image);
                                $imageName = str_random(10) . '.png';
                                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $imageName;
                                Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
                                $recipientKyc->proof_income = $filePath;
                            }
                            if ($request->passport) {
                                $image = $request->passport;  // your base64 encoded
                                $image = str_replace('data:image/png;base64,', '', $image);
                                $image = str_replace(' ', '+', $image);
                                $imageName = str_random(10) . '.png';
                                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $imageName;
                                Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
                                $recipientKyc->passport = $filePath;
                            }
                            $recipientKyc->save();
                        }
                    if($recipientKyc){
                        return response()->json(['success'=>true,'message'=>'Claim Saved successfully..'], 200);
                    }else{
                        return response()->json(['success'=>false,'message'=>'Claim process failed..'], 401);
                    }
                }
                elseif($request->claim_type == "Accident"){
                    $accidentClaim = new ClaimAccident();
                    $accidentClaim->claim_id = $claim->id;
                    $accidentClaim->claim_type = $request->claim_type;
                    $accidentClaim->date_of_accident = $request->dateOfAccident;
                    $accidentClaim->place_of_accident = $request->placeOfAccident;
                    $accidentClaim->purpose_of_trip = $request->purposeoftrip;
                    $accidentClaim->detail_of_accident = $request->placeOfAccident;
                    $accidentClaim->time_of_accident = $request->timeOfAccident;
                    $accidentClaim->third_party = $request->third_party;
                    $accidentClaim->party_at_fault = $request->partyatfault;
                    if ($request->police_report) {
                        $image = $request->police_report;  // your base64 encoded
                        $image = str_replace('data:image/png;base64,', '', $image);
                        $image = str_replace(' ', '+', $image);
                        $imageName = str_random(10) . '.png';
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Claims' . '/' . $claim->id . '/' . $imageName;
                        Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
                        $accidentClaim->police_report = $filePath;
                    }
                    if ($request->vehicle_document) {
                        $image = $request->vehicle_document;  // your base64 encoded
                        $image = str_replace('data:image/png;base64,', '', $image);
                        $image = str_replace(' ', '+', $image);
                        $imageName = str_random(10) . '.png';
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Claims' . '/' . $claim->id . '/' . $imageName;
                        Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
                        $accidentClaim->vehicle_document = $filePath;
                    }
                    $saveAccidentClaim = $accidentClaim->save();

                    $accidentDriver = new AccidentDriver();
                    $accidentDriver->claim_id = $claim->id;
                    $accidentDriver->name = $request->driverfName.$request->driverlName;
                    $accidentDriver->fname = $request->driverfName;
                    $accidentDriver->mname = $request->drivermName;
                    $accidentDriver->lname = $request->driverlName;
                    $accidentDriver->address = $request->driverAddress;
                    $accidentDriver->country = $request->driverCountry;
                    $accidentDriver->city = $request->driverCity;
                    $accidentDriver->state = $request->driverState;
                    $accidentDriver->country_code = $request->driverCity;
                    $accidentDriver->dob = $request->driverDOB;
                    $accidentDriver->country_code = $request->countryCode;
                    $accidentDriver->cellphone = $request->driverContactNumber;
                    $accidentDriver->license = $request->driverLicense;
                    $accidentDriver->purpose = $request->driverPurpose;
                    $accidentDriver->license_expiry = $request->licenceexpiry;
                    $accidentDriver->save();

                    $accidentPassenger = new ClaimAccidentPassenger();
                    $accidentPassenger->claim_id = $claim->id;
                    $accidentPassenger->name = $request->injuredPassengerName.$request->injuredPassengerlName;
                    $accidentPassenger->fname = $request->injuredPassengerfName;
                    $accidentPassenger->mname = $request->injuredPassengermName;
                    $accidentPassenger->lname = $request->injuredPassengerlName;
                    $accidentPassenger->city = $request->injuredPassengerCity;
                    $accidentPassenger->country = $request->injuredPassengerCountry;
                    $accidentPassenger->state = $request->injuredPassengerState;
                    $accidentPassenger->contact = $request->injuredPassengerlCellphone;
                    $accidentPassenger->address = $request->injuredPassengerAddress;
                    $accidentPassenger->injury = $request->injury;
                    $accidentPassenger->save();

                    if($request->third_party == 1){
                        if (count($request->get('third-party')) >0 ) {
                            foreach ($request->get('third-party') as $key => $party) {
                                if ($party['otherPartyFirstName'] && $party['otherPartyLastName'] != null) {
                                    $p = new ClaimThirdParty();
                                    $p->claim_id = $claim->id;
                                    $p->first_name = $party['otherPartyFirstName'];
                                    $p->last_name = $party['otherPartyLastName'];
                                    $p->address = $party['otherPartyAddress'];
                                    $p->cellphone = $party['otherPartyMobileNumber'];
                                    $p->damage_details = $party['vehicleDamageDetail'];
                                    $p->registration_no = $party['vehicleRegistration'];
                                    if ($party['vehicleMake'] == "Other") {
                                        $p->othermake = $party['othermakemodel'];
                                    }
                                    $p->make = $party['vehicleMake'];
                                    $p->model = $party['vehicleModel'];
                                    $p->save();
                                }
                                if (count($request->get('accident-injured')) >0 ) {
                                    foreach ($request->get('accident-injured') as $key => $injured) {
                                        if ($injured['Injuredname'] && $injured['Injuredrelation'] != null) {
                                            $i = new AccidentInjury();
                                            $i->otherparty_id = $p->id;
                                            $i->claim_id = $claim->id;
                                            $i->injured_name = $injured['Injuredname'];
                                            $i->relationship = $injured['Injuredrelation'];
                                            $i->details = $injured['InjuredDetail'];
                                            $i->hospital_name = $injured['InjuredhospitalName'];
                                            $i->save();
                                        }
                                    }
                                }
                            }
                        }
                    }

//                    if($saveClaim && $saveAccidentClaim){
//                        $check = RecipientKyc::where('claim_id',$claim->id)->first();
//                        if($check != null)
//                            $recipientKYC = RecipientKyc::where('claim_id',$claim->id)->first();
//                        else
//                            $recipientKYC = new RecipientKyc();
//
//                        $recipientKYC->claim_id = $claim->id;
//                        $recipientKYC->policy_id = $request->policy_id;
//
//                        if ($request->driving_license) {
//                            $image = $request->driving_license;  // your base64 encoded
//                            $image = str_replace('data:image/png;base64,', '', $image);
//                            $image = str_replace(' ', '+', $image);
//                            $imageName = str_random(10) . '.png';
//                            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $imageName;
//                            Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
//                            $recipientKYC->driving_license = $filePath;
//                        }
//                        if ($request->omang) {
//                            $image = $request->omang;  // your base64 encoded
//                            $image = str_replace('data:image/png;base64,', '', $image);
//                            $image = str_replace(' ', '+', $image);
//                            $imageName = str_random(10) . '.png';
//                            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $imageName;
//                            Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
//                            $recipientKYC->omang = $filePath;
//                        }
//                        if ($request->proof_residence) {
//                            $image = $request->proof_residence;  // your base64 encoded
//                            $image = str_replace('data:image/png;base64,', '', $image);
//                            $image = str_replace(' ', '+', $image);
//                            $imageName = str_random(10) . '.png';
//                            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $imageName;
//                            Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
//                            $recipientKYC->proof_residence = $filePath;
//                        }
//                        if ($request->proof_income) {
//                            $image = $request->proof_income;  // your base64 encoded
//                            $image = str_replace('data:image/png;base64,', '', $image);
//                            $image = str_replace(' ', '+', $image);
//                            $imageName = str_random(10) . '.png';
//                            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $imageName;
//                            Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
//                            $recipientKYC->proof_income = $filePath;
//                        }
//                        if ($request->passport) {
//                            $image = $request->passport;  // your base64 encoded
//                            $image = str_replace('data:image/png;base64,', '', $image);
//                            $image = str_replace(' ', '+', $image);
//                            $imageName = str_random(10) . '.png';
//                            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $imageName;
//                            Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
//                            $recipientKYC->passport = $filePath;
//                        }
//                        $recipientKYC->save();
//                    }
                    return response()->json(['success'=>true,'message'=>'Claim Saved successfully.'], 200);
                }
                elseif($request->claim_type == "Glass"){ //store Glass claim
                    $glass = new ClaimVehicle();
                    $glass->claim_id = $claim->id;

                    if($request->policy_id)
                        $vehicle = Vehicle::where('policy_id',$request->policy_id)->first(['id']);
                    else
                        return response()->json(['success'=>false,'message'=>'Policy Id not found.'], 401);
                    $glass->vehicle_id = $vehicle->id;
                    $glass->date_of_damage = $request->date_of_damage;
                    $glass->damage_extent = $request->damage_extent;
                    $glass->damage_cause = $request->damage_cause;
                    $glass->front_image_description = $request->front_image_description;
                    $glass->back_image_description = $request->back_image_description;
                    $glass->right_image_description = $request->right_image_description;
                    $glass->left_image_description = $request->left_image_description;
                    $glass->glass_location = $request->location_glass;
                    $glass->other_location = $request->other_location;
                    if ($request->back_image) {
                        $image = $request->back_image;  // your base64 encoded
                        $image = str_replace('data:image/png;base64,', '', $image);
                        $image = str_replace(' ', '+', $image);
                        $imageName = str_random(10) . '.png';
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Claims' . '/' . $claim->id . '/' . $imageName;
                        Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
                        $glass->back_image = $filePath;
                    }
                    if ($request->right_image) {
                        $image = $request->right_image;  // your base64 encoded
                        $image = str_replace('data:image/png;base64,', '', $image);
                        $image = str_replace(' ', '+', $image);
                        $imageName = str_random(10) . '.png';
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Claims' . '/' . $claim->id . '/' . $imageName;
                        Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
                        $glass->right_image = $filePath;
                    }
                    if ($request->left_image) {
                        $image = $request->left_image;  // your base64 encoded
                        $image = str_replace('data:image/png;base64,', '', $image);
                        $image = str_replace(' ', '+', $image);
                        $imageName = str_random(10) . '.png';
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Claims' . '/' . $claim->id . '/' . $imageName;
                        Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
                        $glass->left_image = $filePath;
                    }
                    if ($request->front_image) {
                        $image = $request->front_image;  // your base64 encoded
                        $image = str_replace('data:image/png;base64,', '', $image);
                        $image = str_replace(' ', '+', $image);
                        $imageName = str_random(10) . '.png';
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'Claims' . '/' . $claim->id . '/' . $imageName;
                        Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
                        $glass->front_image = $filePath;
                    }
                    $glass->save();
                    return response()->json(['success'=>true,'message'=>'Claim Saved successfully.'], 200);
                }
                elseif($request->claim_type == "Key Loss"){
                    $keyLoss = new ClaimKeyLoss();
                    $keyLoss->claim_id = $claim->id;
                    $keyLoss->financial_interest = $request->financial_interest;
                    $keyLoss->chassis_num = $request->chassis_num;
                    $keyLoss->purpose = $request->purpose;
                    $keyLoss->reason = $request->reason;
                    $keyLoss->replacement_estimate = $request->estimate;
                    $keyLoss->date_of_loss =  $request->lossDate;
                    $keyLoss->description = $request->descriptionofLoss;
                    $keyLoss->company_1 = $request->company_1;
                    $keyLoss->amount_quote_1 = $request->amount_quote_1;
                    if ($request->police_affidavit) {
                        $image = $request->police_affidavit;  // your base64 encoded
                        $image = str_replace('data:image/png;base64,', '', $image);
                        $image = str_replace(' ', '+', $image);
                        $imageName = str_random(10) . '.png';
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'police_affidavit' . '/' . $claim->id . '/' . $imageName;
                        Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
                        $keyLoss->police_affidavit = $filePath;
                    }
                    if ($request->quote_1) {
                        $image = $request->quote_1;  // your base64 encoded
                        $image = str_replace('data:image/png;base64,', '', $image);
                        $image = str_replace(' ', '+', $image);
                        $imageName = str_random(10) . '.png';
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'quote_1' . '/' . $claim->id . '/' . $imageName;
                        Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
                        $keyLoss->quote_1 = $filePath;
                    }

                    $keyLoss->save();
                    return response()->json(['success'=>true,'message'=>'Claim Saved successfully.'], 200);
                }
                else{
                    return response()->json(['success'=>false,'message'=>'Claim Type not found'], 401);
                }
            }else{
                return response()->json(['success'=>false,'message'=>'Claim Type not found'], 401);
            }
        }else{
            return response()->json(['success'=>false,'message'=>'Claim Type not found'], 401);
        }
    }

    public function saveKyc(Request $request)
    {
        if($request->get('user_id')){
            $check = KYC::where('customer_id',$request->get('user_id'))->first();
            if($check){
                $kyc = KYC::where('customer_id',$request->get('user_id'))->first();
            }
            else{
                $kyc = new KYC();
                $kyc->customer_id = $request->get('user_id');
            }
            if ($request->driving_licenseKyc != NULL) {
                $image = $request->driving_licenseKyc;  // your base64 encoded
                $image = str_replace('data:image/png;base64,', '', $image);
                $image = str_replace(' ', '+', $image);
                $imageName = str_random(10) . '.png';
                $filePath = 'MIS/' . $check->customer_id . '/' . 'Customer' . '/driving_license' . '/' . $imageName;
                Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
                $kyc->driving_license = $filePath;
            }
            if ($request->omangKyc != NULL) {
                $image = $request->omangKyc;  // your base64 encoded
                $image = str_replace('data:image/png;base64,', '', $image);
                $image = str_replace(' ', '+', $image);
                $imageName = str_random(10) . '.png';
                $filePath = 'MIS/' . $check->customer_id . '/' . 'Customer' . '/omang' . '/' . $imageName;
                Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
                $kyc->omang = $filePath;
            }
            if ($request->omangBack != NULL) {
                $image = $request->omangBack;  // your base64 encoded
                $image = str_replace('data:image/png;base64,', '', $image);
                $image = str_replace(' ', '+', $image);
                $imageName = str_random(10) . '.png';
                $filePath = 'MIS/' . $check->customer_id . '/' . 'Customer' . '/omang_back' . '/' . $imageName;
                Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
                $kyc->omangBack = $filePath;
            }
            if ($request->proof_residenceKyc != NULL) {
                $image = $request->proof_residenceKyc;  // your base64 encoded
                $image = str_replace('data:image/png;base64,', '', $image);
                $image = str_replace(' ', '+', $image);
                $imageName = str_random(10) . '.png';
                $filePath = 'MIS/' . $check->customer_id . '/' . 'Customer' . '/residence' . '/' . $imageName;
                Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
                $kyc->proof_residence = $filePath;
            }
            if ($request->passportKyc != NULL) {
                $image = $request->passportKyc;  // your base64 encoded
                $image = str_replace('data:image/png;base64,', '', $image);
                $image = str_replace(' ', '+', $image);
                $imageName = str_random(10) . '.png';
                $filePath = 'MIS/' . $check->customer_id . '/' . 'Customer' . '/passport' . '/' . $imageName;
                Storage::disk('s3')->put($filePath, base64_decode($image), 'public');
                $kyc->passport = $filePath;
            }
            if($request->get('omangExpiry') != NULL)
                $kyc->omangExpiry = $request->get('omangExpiry');

            if($request->get('passportExpiry') != NULL)
                $kyc->passportExpiry = $request->get('passportExpiry');

            if($request->get('licenseExpiry') != NULL)
                $kyc->licenseExpiry = $request->get('licenseExpiry');

            if($request->get('incomeExpiry') != NULL)
                $kyc->incomeExpiry = $request->get('incomeExpiry');

            if($request->get('residenceExpiry') != NULL)
                $kyc->residenceExpiry = $request->get('residenceExpiry');

                $kyc->compliance = 0;

            $saved=$kyc->save();
            if($saved){
                return response()->json(['success'=>true,'message'=>'KYC Details Saved successfully..'], 200);
            }
        }
        else{
            return response()->json(['success'=>false,'Message'=>'User not found'], 401);
        }
    }

    public function profileUpdate(Request $request){
        try{
            if($request->profile_customer_id){
                $customer = Customer::where('id',$request->profile_customer_id)->first();
                $customer->firstName = $request->profile_fname;
                $customer->lastName = $request->profile_lname;
                $customer->cellphone = $request->profilemp_phone;
                $customer->email = $request->profile_email;
                $customer->save();
                if($customer->save()){
                    $profileCheck = CustomerProfile::where('customer_id',$request->profile_customer_id)->first();
                    if($profileCheck){
                        $profile = CustomerProfile::where('customer_id',$request->profile_customer_id)->first();
                        $profile->dob = $request->profile_dob;
                        $profile->gender = $request->profile_gender;
                        $profile->maritalstatus = $request->profile_marital_status;
                        $profile->address = $request->profile_address;
                        $profile->city = $request->profile_city;
                        $profile->save();
                    }else{
                        $profile = new CustomerProfile();
                        $profile->customer_id = $request->profile_customer_id;
                        $profile->dob = Carbon::parse($request->profile_dob1)->format('Y-m-d');
                        $profile->gender = $request->profile_gender;
                        $profile->omang = $request->profile_omang;
                        $profile->passport = $request->profile_passport;
                        $profile->maritalstatus = $request->profile_marital_status;
                        $profile->address = $request->profile_address;
                        $profile->city = $request->profile_city;
                        $profile->save();
                    }

                }else{
                    return response()->json(['success'=>false,'message'=>'Customer update failed.'], 401);
                }
                return response()->json(['success'=>true,'message'=>'Customer updated successfully.'], 200);
            }else{
                return response()->json(['success'=>false,'message'=>'Customer not found.'], 401);
            }
        }catch(\Exception $e){
            return response()->json(['success'=>false,'message'=>$e->getMessage()], 401);
        }
    }

    public function updatePolicy(Request $request)
    {
        try{
            $policy = Policy::where('id', $request->policyId)->first();
            $policy->sum_assured = $request->get('sum_insured');
            $policy->save();
            $user = Customer::where('id', $policy->customer_id)->first();
            $user->email = $request->get('email');
            $user->save();
            
            $profile = CustomerProfile::where('customer_id', $policy->customer_id)->first();
            $profile->customer_id = $user->id;
            $profile->address = $request->get('address');
            $profile->maritalstatus = $request->get('maritalstatus');
            $profile->city = $request->get('city');
            $profile->save();
            if ($policy->save()) {
                if ($user != null && $user->cellphone != null) {
                    $smsMessaging = new SmsMessaging;
                    $smsMessaging->sendPolicyUpdateSMS(6, $user->cellphone, $policy->policyNumber);
                }
            }
            return response()->json(['success' => 1,'message'=> 'Policy updated Successfully'], 200);
        }catch(\Exception $e){
            return response()->json(['success' => 0,'message'=>$e],401);
        }
    }

    public function cancelPolicy(Request $request)
    {
        $policyToCancel = Policy::where('id', $request->policyId)->first();
        $policyToCancel->status = 2;
        $cancelled = $policyToCancel->save();

        if ($cancelled) {
            // Cancel the RealPay debit order with the policy. This journey
            // flipped the status and stopped there, so a customer who cancelled
            // over WhatsApp kept being debited until back-office cancelled the
            // contract by hand. Runs before the feedback branch below because it
            // must happen whether or not a customer_id came through.
            // Best-effort and non-throwing: the policy is already cancelled, and
            // the service logs + queues anything it could not cancel for
            // realpay:retry-policy-cancellations.
            try {
                app(\AlphaDirect\Services\RealPayPolicyCancellationService::class)
                    ->cancelForPolicy($policyToCancel, 'whatsapp-cancel');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error(
                    'Whatsapp cancelPolicy realpay cancel: ' . $e->getMessage()
                );
            }

            if ($request->customer_id != null) {
                $feedback = new CustomerFeedback();
                $feedback->policy_id = $request->policyId;
                $feedback->customer_id = $request->customer_id;
                $feedback->product_id = $request->product_id;

                if ($request->reason != null) {
                    $feedback->reason = $request->reason;
                }
                if ($request->circumstances != null) {
                    $feedback->circumstances = $request->circumstances;
                }
                if ($request->other_company != null) {
                    $feedback->other_company = $request->other_company;
                }
                $feedback->save();

                return response()->json(['success' => 1], 200);
            } else {
                return response()->json(['success' => 0], 401);
            }
        } else {
            return response()->json(['success' => 0], 401);
        }
    }

    Public function updateCardVcs(Request $request)
    {
        $policy = Policy::where('policyNumber',$request->policyNumber)->first(array('id'));
        $banking = CustomerBanking::where('id',$policy->id)->first(array('billing'));
        if($banking->billing == 'VCS'){
            $controller = app()->make('AlphaDirect\Http\Controllers\Payment\VCS\PaymentController');
            return $controller->callAction('updateCardVcs',[$request]);
        }
        else{
            return response()->json(['status' => '401', 'message' => 'Payment Method not support'],401);
        }
    }

    public function redoPayment(Request $request)
    {
        if($request->policyNumber != null) {

            $policy = Policy::where('policyNumber', $request->policyNumber)->first();

            $method = new \AlphaDirect\Http\Controllers\Admin\QuoteController();
            $message = $method->getWhatsappMessage();

            switch ($request->Payment_method) {
                case 'VCS':
                    return response()->json([
                        'success' => 1,
                        'message' => $message,
                        'payment_url' => 'https://start.alphadirect.co.bw/redopayment',
                        'policy_details' => array('policy_id' => $policy->id, 'policy_no' => $policy->policyNumber)
                    ], 200);
                    break;
                case 'RealPay':
                    return $this->realpayPayment($policy, $message);
                    break;
                default:
                    return response()->json([
                        'success' => 0,
                        'message' => 'Please select a Payment Vendor',
                        'policy_details' => array('policy_id' => $policy->id, 'policy_no' => $policy->policyNumber)
                    ], 200);
            }

            $controller = app()->make('AlphaDirect\Http\Controllers\Payment\VCS\PaymentController');
            return $controller->callAction('redoPaymentForLiveQuote', [$request]);
        }
        else{
            return response()->json(['status' => '401', 'message' => 'Please provide policy number'],401);
        }
    }

    public function beneficiaryUpdate(Request $request)
    {
        if($request->policy_id != null){
            if($request->beneficiary_id != null){
                $b = PolicyBeneficiary::where('id',$request->beneficiary_id)->first();
                $b->relation = $request->beneficiary_relation;
                $b->first_name = $request->beneficiary_fname;
                $b->middle_name = $request->beneficiary_mname;
                $b->last_name = $request->beneficiary_lname;
                $b->dob = $request->beneficiary_dob;
                $b->gender = $request->beneficiary_gender;
                $b->payment = $request->beneficiary_payment;
                $b->omang = $request->beneficiary_omang;
                $b->passport = $request->beneficiary_passport;
                $b->save();
                if($b->save()){
                    return response()->json(['success' => 1,'message'=> 'Beneficiary updated Successfully'], 200);
                }
                else{
                    return response()->json(['success' => 0,'message'=> 'No beneficiary to update'], 401);
                }
            }
        }
        return response()->json(['success' => 0,'message'=>'Something went wrong'],401);
    }

    public function updateRealPayBankDetails(Request $request){
        try{
            $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
            return $realpay->updateRealPayBankigDetails($request);
        }catch(\Exception $e){
            return response()->json(['success' => 0,'message'=>$e->getMessage()],401);
        }
    }

    //store beneficiary
    public function storeBeneficiary(Request $request)
    {
        if ($request->policyNumber != null) {
            try {
                \Illuminate\Support\Facades\DB::beginTransaction();
                $policy = Policy::where('policyNumber',$request->policyNumber)->select('id')->first();
                if($policy){
                    $request->request->add(['policy_id'=>$policy->id]);
                }else{
                    return response()->json(['success' => 0, 'message' => 'Policy not found'], 401);
                }
                $benefeciary = new PolicyBeneficiary();
                $result =  $benefeciary->addBeneficiary($request);
                \Illuminate\Support\Facades\DB::commit();
                return response()->json(['success' => 1, 'message' => 'Beneficiary added Successfully'], 200);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\DB::rollBack();
                return response()->json(['success' => 0,'message'=>$e->getMessage()],401);
            }

        } else {
            return response()->json(['success' => 0, 'message' => 'Something went wrong'], 401);
        }

    }

    //get lookup table data
    public function lookupdata(Request $request)
    {
        if ($request->key != null) {
            $lookup = Lookup::where('key', $request->key)->get(array('id','value'));
            if($lookup != null){
                return response()->json(['success'=>true,'lookup'=>$lookup], 200);
            } else {
                return response()->json(['success' => false, 'message' => 'No data available.'], 401);
            }
        }
        else {
            return response()->json(['success' => false, 'message' => 'Something went wrong.'], 401);
        }
    }
}
