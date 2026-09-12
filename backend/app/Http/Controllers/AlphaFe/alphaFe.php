<?php

namespace AlphaDirect\Http\Controllers\AlphaFe;

use AlphaDirect\Activation;
use AlphaDirect\Banks;
use AlphaDirect\Customer;
use AlphaDirect\CustomerBanking;
use AlphaDirect\CustomerProfile;
use AlphaDirect\FactorMain;
use AlphaDirect\FactorSubType;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController;
use AlphaDirect\Http\Controllers\Payment\RealPay\RealPayController;
use AlphaDirect\Http\Controllers\Payment\VCS\VcsController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\KYC;
use AlphaDirect\PaymentVendor;
use AlphaDirect\Policy;
use AlphaDirect\PolicyBeneficiary;
use AlphaDirect\PolicyFactor;
use AlphaDirect\PolicyMember;
use AlphaDirect\Product;
use AlphaDirect\Models\ProductCoverage;
use AlphaDirect\Productplan;
use AlphaDirect\Repositories\ClaimCellphone\ClaimCellphoneInterface;
use AlphaDirect\Repositories\Customer\CustomerInterface;
use AlphaDirect\Repositories\CustomerBanking\CustomerBankingInterface;
use AlphaDirect\Repositories\CustomerKyc\CustomerKycInterface;
use AlphaDirect\Repositories\CustomerProfile\CustomerProfileInterface;
use AlphaDirect\Repositories\PolicyCellPhone\PolicyCellPhoneInterface;
use AlphaDirect\Region;
use AlphaDirect\Transaction;
use AlphaDirect\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Input;
use Response;
use AlphaDirect\Lookup;
use AlphaDirect\Http\Controllers\Payment\VCS\PaymentController;

class alphaFe extends Controller
{
    /*
    * method to check alphaFE activation already exists
    *return JSON
    */
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

    public function checkIfAlphaFEActivationCodeExists(Request $request)
    {
        try {
            //code...
            if (Activation::where('activation_code', '=', $request->activation_code)->exists()) {

                return response()->json(['message' => 'Activation Code Exists', 'status' => 'success'], 200);
            } else {

                return response()->json(['message' => 'Activation Code does not exists,please buy a new product', 'status' => 'failed'], 400);
            }
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage(), 500);
        }
    }

    /*
    * method to retrieve all the activation code data
    *return JSON
    */
    public function getAlphaFEActivationCodeData(Request $request)
    {
        try {
            //code...
            $activationProduct = Activation::where('activation_code', $request->activation_code)->where('status', 0)->first();

            if ($activationProduct != null) {
                $plans = Productplan::where('id', $activationProduct->product_plan_id)->first(array('id', 'name', 'premium'));
                $product = Product::where('id', $activationProduct->product_id)->first(array('id', 'name', 'premium_type_id', 'has_vehicle', 'has_member'));
                return response()->json(['product' => $product, 'plans' => $plans, 'status' => $activationProduct->status], 200);
            } else {
                return response()->json(['status' => 'failed'], 409);
            }
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage(), 500);
        }
    }

    /*
    * method to check activation code status
    *return JSON
    */
    public function checkAlphaFEActivationCodeStatus(Request $request)
    {
        try {
            $activationCode = Activation::where('activation_code', $request->activation_code)->first();

            if ($activationCode->status == 1) {
                return response()->json(['status' => 'deactived']);
            } else {
                return response()->json(['status' => 'active']);
            }
        } catch (Exception $ex) {
            return response()->json($ex->getMessage(), 500);
        }
    }

    /*
    * method to retrieve all the activation code plans data
    *return JSON
    */
    public function getAlphaFeActivationCodePlans(Request $request)
    {
        try {
            $activationPlans = Activation::where('activation_code', $request->activation_code)->where('status', 0)->first();
            $plans = Productplan::where('id', $activationPlans->product_plan_id)->first();
            return response()->json(['plans' => $plans]);
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage(), 500);
        }
    }


    /*
    * method to retrieve payment page form data
    *return JSON
    */
    public function getPagePaymentFormData(Request $request)
    {
        try {
            //code...

            $policy = Policy::findorFail($request->policy_id)->first(array('id', 'customer_id', 'policyNumber', 'product_id', 'premium'));
            if ($policy != null) {
                $customer = CustomerProfile::where('customer_id', $policy->customer_id)->first(array('omang', 'passport'));
                $transaction = Transaction::where('policyNumber', $policy->policyNumber)->first('status');
                $product = Product::where('id', $policy->product_id)->first(array('name', 'premium_type_id'));
                $vendors = PaymentVendor::where('status', '1')->get(array('id', 'vendorName', 'vendorLabel'));

                return response()->json(['policy' => $policy, 'transaction' => $transaction, 'customer' => $customer, 'product' => $product, 'vendors' => $vendors], 200);
            } else {
                return response()->json(['message' => 'policy is null'], 400);
            }
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage(), 500);
        }
    }

    /*
    * method to retrieve all the product factor plans
    * return JSON
    */
    public function getAlphaFeProductFactorsPlans(Request $request)
    {
        $factors = FactorMain::with('value')->where('product_id', $request->product_id)->where('status', 1)->get(array('id', 'name', 'type'));
        // Check if we are not trying to delete ourselves

        $product = Product::where('id', $request->product_id)->first(array('id', 'has_vehicle', 'has_member', 'has_subApplicant', 'premium_type_id', 'has_activation_code', 'is_motor_items', 'preinspection', 'kyc_customer', 'sum_insured'));
        if ($product->premium_type_id == 11) {
            $plans = Productplan::where('product_id', $request->product_id)->where('status', 1)->get(array('id', 'name', 'premium', 'sum_assured'))->toArray();
        } else {
            $plans = null;
        }

        $coverage = ProductCoverage::where('product_id', $request->product_id)->get(array('coverage_id', 'name'));
        if ($factors->count() == null) {
            // Prepare the error message
            return response()->json(['has_plans' => '1', 'product' => $product, 'plans' => $plans], 200);
        } else {
            return response()->json(['has_plans' => '0', 'product' => $product, 'factors' => $factors], 200);
        }
    }


    /*
    * method to retrieve aaall the payment vendors
    *return JSON
    */
    public function getAlphaFeVendors()
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

    /*
    * method to retrieve all the banks
    * return JSON
    */
    public function getAlphaFeBanks()
    {
        try {
            //code...
            $banks = Banks::all();
            return response()->json($banks);
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage(), 500);
        }
    }

    /*
    * method to policy data
    * return JSON
    */
    public function saveStartPolicy(Request $request)
    {
        try {
            //code...

            $fname = htmlspecialchars(strip_tags($request->input('fname', '')));
            $lname = htmlspecialchars(strip_tags($request->input('lname', '')));
            $email = htmlspecialchars(strip_tags($request->input('email', '')));
            $cellphone = htmlspecialchars(strip_tags($request->input('cellphone', '')));
            $data = [
                'firstName'          => $fname,
                'lastName'           => $lname,
                'email'              => $email,
                'cellphone'          => $cellphone,
            ];
            $user_id = $this->customer_interface->add_new_customer($data);

            $customer_id = $user_id;
            $gender = htmlspecialchars(strip_tags($request->input('gender', '')));
            $address = htmlspecialchars(strip_tags($request->input('address', '')));
            $omang = htmlspecialchars(strip_tags($request->input('omang', '')));
            $passport = htmlspecialchars(strip_tags($request->input('passport', '')));
            $maritalstatus = htmlspecialchars(strip_tags($request->input('maritalstatus', '')));
            $dob = Carbon::parse($request->get('dob'))->format('Y-m-d');
            $data = [
                'gender'        => $gender,
                'customer_id'   => $customer_id,
                'address'       => $address,
                'omang'         => $omang,
                'passport'      => $passport,
                'maritalstatus' => $maritalstatus,
                'dob'           => $dob,
            ];
            $customer_id = $this->customer_profile_interface->add_new_customer_profile($data);

            $customerKYC = new KYC();
            $customerKYC->customer_id = $user_id;
            $customerKYC->compliance = 0;
            $customerKYC->save();

            $latest = Policy::latest()->first(array('id'));
            if ($latest == null) {
                $latest = collect();
                $latest->id = 1;
            }
            $product = Product::where('id', htmlspecialchars(strip_tags($request->get('product'))))->first(array('has_vehicle', 'has_member', 'premium_type_id', 'limit', 'kyc_recipient', 'kyc_customer', 'preinspection', 'is_motor_items', 'region_id', 'has_activation_code'));

            $policy = new Policy();
            $policy->customer_id = $user_id;
            $policy->product_id = htmlspecialchars(strip_tags($request->get('product')));
            $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;

            if ($product->premium_type_id == 11) {
                $regionVat = Region::where('id', htmlspecialchars(strip_tags($request->get('region_id'))))->first(array('vat'))->vat;
                $product_plan = Productplan::where('id', $request->plan)->first(array('sum_assured', 'premium'));
                $premium = round(($product_plan->premium * ($regionVat / 100)) + $product_plan->premium, 2);
                $policy->plan_id = $request->plan;
                $policy->sum_assured = $product_plan->sum_assured;
                $policy->premium = round($premium, 2);
                $policy->vat = round($product_plan->premium * ($regionVat / 100), 2);
                $policy->vat_percent = $regionVat;
            } else {
                $premium = (htmlspecialchars(strip_tags($request->get('premium'))) * ($regionVat / 100)) + htmlspecialchars(strip_tags($request->get('premium')));
                $policy->premium = $premium;
                $policy->vat = htmlspecialchars(strip_tags($request->get('premium'))) * ($regionVat / 100);
                $policy->sum_assured = htmlspecialchars(strip_tags($request->get('sum_insured')));
            }

            $policy->policyNumber = 'MIS' . Carbon::now()->year . str_pad(($latest->id + 1), 6, '0', STR_PAD_LEFT);
            $policy->status = 0;
            $policy->leadSource = 'AlphaFe';
            $policy->has_vehicle = $product->has_vehicle;
            $policy->has_member = $product->has_member;
            $policy->preinspection = $product->preinspection;
            $policy->is_motor_items = $product->is_motor_items;
            $policy->limit = $product->limit;
            $policy->kyc_customer = $product->kyc_customer;
            $policy->kyc_recipient = $product->kyc_recipient;

            if ($request->activation_code) {
                $activation = Activation::where('activation_code', htmlspecialchars(strip_tags($request->get('activation_code'))))->first();
                $activation->status = '0'; // should be 1 after testing
                $activation->save();

                $policy->activation_code = htmlspecialchars(strip_tags($request->get('activation_code')));
                $policy->serial_code = $activation->serial_code;
            }

            $saved = $policy->save();

            if ($product->has_vehicle == '1') {
                $vehicle = new Vehicle();
                $vehicle->customer_id = $user_id;
                $vehicle->policy_id = $policy->id;
                $vehicle->vehiclePlate = htmlspecialchars(strip_tags($request->get('vehiclePlate')));
                $vehicle->save();
            }

            if ($product->premium_type_id == '15') {
                $mains = FactorMain::where('product_id', htmlspecialchars(strip_tags($request->get('product'))))->where('status', 1)->get(array('id', 'name', 'type'));

                $requestFactors = htmlspecialchars(strip_tags($request->get('factors'))); // factor_43: 129,factor_44: [2, 3, 5],factor_45: 1,factor_46: 28,factor_47: 1

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
            }

            $customer_id = $user_id;
            $policy_id = $policy->id;
            $accountNumber = htmlspecialchars(strip_tags($request->input('accountNumber', '')));
            $billingMethod = htmlspecialchars(strip_tags($request->input('billingMethod', '')));
            $billingCell = htmlspecialchars(strip_tags($request->input('billingCell', '')));
            $bankName = htmlspecialchars(strip_tags($request->input('bankName', '')));
            $branchCode = htmlspecialchars(strip_tags($request->input('branchCode', '')));
            $bankAccountType = htmlspecialchars(strip_tags($request->input('bankAccountType', '')));
            $data = [
                'customer_id'       => $customer_id,
                'policy_id'         => $policy_id,
                'accountNumber'     => $accountNumber,
                'billingMethod'     => $billingMethod,
                'billingCell'       => $billingCell,
                'bankName'          => $bankName,
                'branchCode'        => $branchCode,
                'bankAccountType'   => $bankAccountType,
            ];

            $addCustBank = $this->customer_banking_interface->add_new_customer_banking($data);

            //replace with members array
            if ($product->has_member && $request->relation != null) {

                $newMember = new PolicyMember();
                $newMember->policy_id = $policy->id;
                $newMember->relation = htmlspecialchars(strip_tags($request->input('relation', '')));
                $newMember->first_name = htmlspecialchars(strip_tags($request->input('memberFName', '')));
                $newMember->last_name = htmlspecialchars(strip_tags($request->input('memberLName', '')));
                $newMember->dob = Carbon::parse($request->memberDOB)->format('Y-m-d');
                $newMember->gender = htmlspecialchars(strip_tags($request->input('memberGender', '')));
                $newMember->save();
            }


            if ($request->get('beneficiaries') != null) {

                foreach ($request->get('beneficiaries') as $key => $beneficiary) {

                    if ($beneficiary['beneficiaryRelation'] != null) {
                        $b = new PolicyBeneficiary();
                        $b->policy_id = $policy->id;
                        $b->relation = $beneficiary['beneficiaryRelation'];
                        $b->first_name = $beneficiary['beneficiaryFName'];
                        $b->last_name =  $beneficiary['beneficiaryLName'];
                        $b->omang = $beneficiary['beneficiaryOmang'];
                        $b->passport = $beneficiary['beneficiaryPassport'];
                        $b->dob = Carbon::parse($beneficiary['beneficiaryDOB'])->format('Y-m-d');
                        $b->gender = $beneficiary['beneficiaryGender'];
                        $b->payment = $beneficiary['beneficiaryPayment'];
                        $b->save();
                    }
                }
            }

            $id = base64_encode($policy->id);

            return response()->json(array('status' => 'success', 'message' => 'Policy Created Successfully', 'id' => $id, 'policy_details' => $policy));
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json(['message' => $ex->getMessage(), 'line' => $ex->getLine()], 500);
        }
    }

    /*
    * method for policy confirmation
    * return JSON
    */
    public function confirmStartPolicy($id, Request $request)
    {

        try {
            //code...

            $policy = Policy::findorFail($id);

            $user = Customer::findorFail($policy->customer_id);
            $user->firstName = htmlspecialchars(strip_tags($request->input('fname', '')));
            $user->lastName = htmlspecialchars(strip_tags($request->input('lname', '')));
            $user->email = htmlspecialchars(strip_tags($request->input('email', '')));
            $user->cellphone = htmlspecialchars(strip_tags($request->input('cellphone', '')));
            $user->save();

            $profile = CustomerProfile::where('customer_id', $policy->customer_id)->first();
            $profile->customer_id = $user->id;
            $profile->gender = htmlspecialchars(strip_tags($request->input('gender', '')));
            $profile->address = htmlspecialchars(strip_tags($request->input('address', '')));
            $profile->omang = htmlspecialchars(strip_tags($request->input('omang', '')));
            $profile->passport = htmlspecialchars(strip_tags($request->input('passport', '')));
            $profile->dob = Carbon::parse($request->get('dob'))->format('Y-m-d');
            $profile->save();
            $product = Product::findorFail($policy->product_id);

            if ($policy->activation_code != null) {
                $activation = Activation::where('activation_code', $policy->activation_code)->first();
                $activation->status = '1'; // should be 1 after testing
                $activation->save();

                $policy->activation_code = htmlspecialchars(strip_tags($request->input('activation_code', '')));
                $policy->serial_code = $activation->serial_code;
            }
            $policy->save();


            if ($product->has_vehicle == '1') {
                $vehicle = new Vehicle();
                $vehicle->customer_id = $user->id;
                $vehicle->policy_id = $policy->id;
                $vehicle->vehiclePlate = htmlspecialchars(strip_tags($request->input('vehiclePlate', '')));
                $vehicle->save();
            }

            if ($product->premium_type_id == '15') { //check for product factors

                $mains = FactorMain::where('product_id', htmlspecialchars(strip_tags($request->input('product', ''))))->where('status', 1)->get(array('id', 'name', 'type'));

                $requestFactors = htmlspecialchars(strip_tags($request->input('factors', ''))); // factor_43: 129,factor_44: [2, 3, 5],factor_45: 1,factor_46: 28,factor_47: 1

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
            }

            $banking = new CustomerBanking();
            $banking->customer_id = $user->id;
            $banking->policy_id = $policy->id;
            $banking->accountNumber = htmlspecialchars(strip_tags($request->input('accountNumber', '')));
            $banking->billing = htmlspecialchars(strip_tags($request->input('billingMethod', '')));
            $banking->billingCell = htmlspecialchars(strip_tags($request->input('billingCell', '')));
            $banking->bankName = htmlspecialchars(strip_tags($request->input('bankName', '')));
            $banking->branchCode = htmlspecialchars(strip_tags($request->input('branchCode', '')));
            $banking->accountType = htmlspecialchars(strip_tags($request->input('bankAccountType', '')));
            $saved = $banking->save();

            // $members = $request->get('members');

            //replace with members array
            if ($product->has_subApplicant == '1') {
                $newMember = new PolicyMember();
                $newMember->policy_id = $policy->id;
                $newMember->relation = htmlspecialchars(strip_tags($request->input('relation', '')));
                $newMember->first_name = htmlspecialchars(strip_tags($request->input('memberFName', '')));
                $newMember->last_name = htmlspecialchars(strip_tags($request->input('memberLName', '')));
                $newMember->dob = Carbon::parse($request->memberDOB)->format('Y-m-d');
                $newMember->gender = htmlspecialchars(strip_tags($request->input('memberGender', '')));
                $newMember->save();
            }

            //For Already added beneficiaries  
            if ($product->has_member == '1') {

                if (is_array($request->get('old_beneficiary')) && htmlspecialchars(strip_tags($request->input('old_beneficiary', ''))) != NULL) {
                    for ($i = 0; $i < count($request->get('old_beneficiary')); $i++) {
                        $b = PolicyBeneficiary::where('id', htmlspecialchars(strip_tags($request->input('old_beneficiary', '')))[$i])->first();
                        $b->relation = htmlspecialchars(strip_tags($request->input('old_beneficiaryRelation', '')))[$i];
                        $b->first_name = htmlspecialchars(strip_tags($request->input('old_beneficiaryFName', '')))[$i];
                        $b->last_name = htmlspecialchars(strip_tags($request->input('old_beneficiaryLName', '')))[$i];
                        $b->omang = htmlspecialchars(strip_tags($request->input('old_beneficiaryOmang', '')))[$i];
                        $b->passport = htmlspecialchars(strip_tags($request->input('old_beneficiaryPassport', '')))[$i];
                        $b->dob = Carbon::parse($request->get('old_beneficiaryDOB')[$i])->format('Y-m-d');
                        $b->gender = htmlspecialchars(strip_tags($request->input('old_beneficiaryGender', '')))[$i];
                        $b->payment = htmlspecialchars(strip_tags($request->input('old_beneficiaryPayment', '')))[$i];
                        $b->save();
                    }
                }
                if (is_array($request->get('beneficiaries')) && $request->get('beneficiaries') != NULL) {
                    foreach ($request->get('beneficiaries') as $key =>  $beneficiary) {
                        if ($beneficiary['beneficiaryRelation'] != NULL) {
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
                }
            }

            $payment  = new PaymentController();

            $sms = new SmsMessaging();

            return response()->json($payment->handlePayment($policy->policyNumber, 'AlphaFe'));
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json(['message' => $ex->getMessage(), 'line' => $ex->getLine(), 'status' => 'failed'], 500);
        }
    }

    /*
    * method to retrieve all the infomation for specificpolicy
    * paaram: policy Id
    * return JSON
    */
    public function getStartPolicySummary(Request $request)
    {
        try {
            $policy = Policy::where('id', $request->get('policy_id'))->first();
            $productPlan = Productplan::where('id', $policy->plan_id)->first(array('name', 'sum_assured'));
            $user = Customer::with(['profile', 'banking'])->where('id', $policy->customer_id)->first(array('id', 'firstName', 'lastName', 'email', 'cellphone'));
            $policy->customer = $user;
            $productFactors = FactorMain::with('value')->where('product_id', $policy->product_id)->get(array('id', 'name', 'type'));
            foreach ($productFactors as $productFactor) {
                $policyFactors = PolicyFactor::where('policy_id', $policy->id)->where('factor_main_id', $productFactor->id)->get(array('factor_value_id', 'value_name', 'name'));
                if ($productFactor->type == 'Input Field') {
                    $productFactor->policyFactors = $policyFactors->pluck('value_name')->toArray();
                } else {
                    $productFactor->policyFactors = $policyFactors->pluck('factor_value_id')->toArray();
                }

                $productFactor->policyFactorsValueName = $policyFactors->pluck('value_name')->toArray();
            }
            $policy->factors = $productFactors;

            $message = 'success';

            if ($policy->has_vehicle) {
                $vehicle = Vehicle::where('customer_id', $policy->customer_id)->first();
                $vehicle_purpose = Lookup::where('key', 'vehicle_purpose')->get(array('id', 'value'));
                $policy->vehicle = $vehicle;
                $policy->purpose = $vehicle_purpose;
            }
            if ($policy->has_member) {
                $members = PolicyMember::where('policy_id', $policy->id)->get();
                $policy->members = $members;
            }
            $policyBeneficiaries = PolicyBeneficiary::where('policy_id', $policy->id)->get();
            $policy->policyBeneficiaries = $policyBeneficiaries;

            return response()->json(['data' => $policy, 'message' => 'success'], 200);
        } catch (Exception $ex) {
            return response()->json($ex->getMessage, 500);
        }
    }

    /*
    * method to retrieve all the products
    * return JSON
    */
    public function getStartProducts()
    {
        $product = Product::where('status', 1)->get(array('id', 'name', 'premium_type_id', 'has_activation_code', 'has_member', 'has_vehicle'));
        // Check if we are not trying to delete ourselves
        if ($product->count() != null) {
            return response()->json($product, 200);
        } else {
            return response()->json('No product available', 401);
        }
    }

    /*
    * method to generate activationcode
    * return JSON
    */
    public function generateStartActivationCode()
    {
        $latest_code = Activation::orderBy('id', 'DESC')->first(array('serial_code', 'activation_code', 'group_id'));
        if ($latest_code == null) {
            $serial_code = 'AAAAAA';
            $activation_code = random_int(10000000, 99999999);
            $group_id = 1;
        } else {
            $serial_code = $latest_code->serial_code;
            $group_id = $latest_code->group_id + 1;
            $activation_code = random_int(10000000, 99999999);
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
            $activation_code = random_int(10000000, 99999999);
            $check = Activation::where('activation_code', $activation_code)->count();
        }

        $activation->activation_code = $activation_code;
        $activation->status = 0;
        $saved = $activation->save();

        return response()->json(['status' => 'success', 'code' => $activation->activation_code]);
    }



    /*
    * method to show Thank you page after successfull process
    * return view: Thank You page
    */
    public function thankYouPage()
    {
        return view('alphaFe/thankyou2');
    }
}
