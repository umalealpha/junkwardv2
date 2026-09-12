<?php

namespace AlphaDirect\Http\Controllers\ChatBot;

use AlphaDirect\Activation;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Product;
use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\PolicyBeneficiary;
use AlphaDirect\KYC;
use AlphaDirect\Policy;
use AlphaDirect\Region;
use AlphaDirect\Vehicle;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\AccountingRules;
use AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController;
use AlphaDirect\Http\Controllers\Payment\VCS\VcsController;
use AlphaDirect\Productplan;
use Illuminate\Http\Request;
use AlphaDirect\FactorMain;
use AlphaDirect\Models\ProductCoverage;
use AlphaDirect\Lookup;
use AlphaDirect\PolicyMember;
use AlphaDirect\PolicyFactor;
use AlphaDirect\FactorSubType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Auth;
use phpDocumentor\Reflection\Types\Null_;
use Redirect;


class ChatBotController extends Controller
{
    // to retrieve activation product
    // return JSON
    public function getActivation(Request $request)
    {
        try {
            //code...
            $activationProduct = Activation::where('activation_code', $request->activationCode)->first();

            if ($activationProduct != null) {
                $plans = Productplan::where('id', $activationProduct->product_plan_id)->get(array('id','name', 'premium'));
                $product = Product::where('id', $activationProduct->product_id)->get(array('id','name', 'premium_type_id','has_member'));
                return response()->json(['product' => $product, 'plans' => $plans]);

            } else {
                return response()->json(['status' => 'failed'], 400);
            }
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage(), 500);
        }

    }

    // retrieves all product plans related with productsSSS
    // JSON
    public function getProductPlans($product_plan_id)
    {
        try {
            //code...
            $plans = Productplan::where('product_id', $product_id)->get(array('name', 'premium'));

            if ($plans != null) {

                return response()->json(['status' => 'success', 'plans' => $plans], 200);
            } else {
                return response()->json(['status' => 'failed'], 400);
            }

        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage(), 500);
        }

    }

    // method to check user omang & passport
    // JSON
    public function checkUserOmang(Request $request)
    {
        if($request->get('omang') != NULL && $request->get('passport') != NULL)
            $profile = CustomerProfile::where('omang', $request->get('omang'))->orwhere('passport', $request->get('passport'))->first();
        elseif($request->get('omang') != NULL && $request->get('passport') == NULL)
            $profile = CustomerProfile::where('omang', $request->get('omang'))->first();
        elseif($request->get('omang') == NULL && $request->get('passport') != NULL)
            $profile = CustomerProfile::where('passport', $request->get('passport'))->first();

        if($profile != NULL ){
            $customerData = Customer::where('id',$profile->customer_id)->first(array('firstName','lastName','email','cellphone'));
            return response()->json(['count'=> $profile,'customerData'=>$customerData]);
        }
        else{
            return response()->json(['count'=> $profile]);
        }
    }


    // method to check user omang
    // JSON
    public function checkOmang(Request $request){

        if($request->get('passport') != NULL && $request->get('omang_id') == NULL){
            $customer = CustomerProfile::where('passport',$request->get('passport'))->count();
        }else{
            $customer = CustomerProfile::where('omang',$request->get('omang_id'))->count();
        }

        if($customer > 0){
            return response()->json('This omang\'/passport already exists');
        }
        else{
            return response()->json('true');
        }
    }

    // retrieves all product factors related with product
    // param: product ID
    // JSON
    public function getProductFactors(Request $request)
    {

        $check = FactorMain::with('value')->where('product_id', $request->get('id'))->where('status', 1)->get(array('id', 'name', 'type'));
        
        // Check if we are not trying to delete ourselves
        $product = Product::where('id', $request->get('id'))->get(array('has_vehicle', 'has_member','premium_type_id','has_activation_code','is_motor_items', 'preinspection', 'kyc_customer', 'sum_insured'));

        
        if($product['premium_type_id'] == 11)
            $plans = Productplan::where('product_id', $request->get('id'))->where('status', 1)->get(array('id', 'name', 'premium', 'sum_assured'))->toArray();
        else
            $plans = NULL;
        $coverage = ProductCoverage::where('product_id', $request->get('id'))->get(array('coverage_id', 'name'));
        if ($check->count() == NULL) {
            // Prepare the error message
            return response()->json(['status'=>'error', 'factors'=>$check, 'product'=>$product, 'coverage'=>$coverage, 'plans'=>$plans]);
        } else {
            return response()->json(['status'=>'success', 'factors'=>$check, 'product'=>$product, 'coverage'=>$coverage, 'plans'=>$plans]);
        }
    }


    // method to check activation code
    // activation code
    // JSON
    public function checkActivation(Request $request)
    {
        $code = Activation::where('activation_code', $request->get('code'))->first(array('product_id', 'product_plan_id', 'status'));
        return response()->json(['count'=> $code]);
    }


    // method to check user vehicle
    // param: vehicle plate
    // JSON
    public function checkUserVehicle(Request $request)
    {

        $vehicle = Vehicle::where('vehiclePlate',$request->get('vehiclePlate'))->count();

        return response()->json(['count' => $vehicle]);
       
    }

    // method to check user vehicle
    // param: vehicle plate
    // JSON
    public function checkVehiclePlate(Request $request)
    {

        $vehicle = Vehicle::where('vehiclePlate',$request->get('vehiclePlate'))->count();

        if($vehicle > 0){
            return response()->json('Vehicle already has a policy');

        }
        else{
            return response()->json('true');
        }
    }

    // method to check user life policy already exists
    // param: omang/passport
    // JSON
    public function checkUserLifePolicy(Request $request){


        if($request->get('passport') == NULL){
            $customer = CustomerProfile::where('omang',$request->get('omang'))->first();

        }else{
            $customer = CustomerProfile::where('passport',$request->get('passport'))->first();
        } 



        if(!$customer){

            return response()->json('true');

        }
        else{
            $policy = Policy::where('customer_id', $customer['customer_id'])->get();


                if($policy->has($request->get('product_id'))){
                    return response()->json('true');
    
                } else{
                    return response()->json('This user already has a product of the selected product');
                }
            
        }
    }

    // method to store policy data from policy create page
    // policy listing page
    public function store(Request $request)
    {
        if($request->get('omang_id') != NULL && $request->get('passport') != NULL)
            $customerProfile = CustomerProfile::where('omang', $request->get('omang_id'))->orwhere('passport', $request->get('passport'))->first();
        elseif($request->get('omang_id') != NULL && $request->get('passport') == NULL)
            $customerProfile = CustomerProfile::where('omang', $request->get('omang_id'))->first();
        elseif($request->get('omang_id') == NULL && $request->get('passport') != NULL)
            $customerProfile = CustomerProfile::where('passport', $request->get('passport'))->first();

            if($customerProfile == NULL){
                $user = new Customer();
                $user->firstName = $request->get('fname');
                $user->lastName = $request->get('lname');
                $user->email = $request->get('email');
                $user->cellphone = $request->get('cellphone');
                //$user->password = Hash::make('111111');
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
                $profile->omang = $request->get('omang_id');
                $profile->passport = $request->get('passport');
                $origDate = $request->dob;
                $dob = str_replace('/', '-', $origDate);
                $profile->dob = Carbon::parse(date("Y-m-d", strtotime($dob)));
                $profile->save();
                $kyc = new KYC();
                $kyc->customer_id = $user->id;
                if ($request->hasFile('driving_license')) {
                    $file = $request->file('driving_license');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $kyc->customer_id . '/' . 'Customer' . '/driving_license' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $kyc->driving_license = $filePath;
            }
            if ($request->hasFile('omang')) {
                    $file = $request->file('omang');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $kyc->customer_id . '/' . 'Customer' . '/omang' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $kyc->omang = $filePath;
            }
            if ($request->hasFile('proof_residence')){
                    $file = $request->file('proof_residence');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $kyc->customer_id . '/' . 'Customer' . '/proof_residence' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $kyc->proof_residence = $filePath;
            }
            if ($request->hasFile('proof_income')) {
                    $file = $request->file('proof_income');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $kyc->customer_id . '/' . 'Customer' . '/proof_income' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $kyc->proof_income = $filePath;
            }
            if ($request->hasFile('passportPicture')) {
                    $file = $request->file('passportPicture');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $kyc->customer_id . '/' . 'Customer' . '/passport' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $kyc->passport = $filePath;
            }
                    $kyc->compliance = 0;
                    $kyc->save();
        } else {
            $user = Customer::where('id', $request->get('customer_id'))->first();
            $user->firstName = $request->get('fname');
            $user->lastName = $request->get('lname');
            $user->email = $request->get('email');
            $user->cellphone = $request->get('cellphone');
            $user->save();

            $profile = CustomerProfile::where('customer_id', $request->get('customer_id'))->first();
            $profile->customer_id = $user->id;
            $profile->gender = $request->get('gender');
            $profile->address = $request->get('address');
            $profile->omang = $request->get('omang_id');
            $profile->passport = $request->get('passport');
            $profile->dob = Carbon::parse($request->get('dob'))->format('Y-m-d');
            $profile->save();
        }
        //Latestid for Policy Number
        $latest = Policy::latest()->first(array('id'));
        if ($latest == NULL) {
            $latest = collect();
            $latest->id = 1;
        }
        $product = Product::where('id', $request->get('product'))->first(array('has_vehicle', 'has_member', 'premium_type_id', 'limit', 'kyc_recipient', 'kyc_customer', 'preinspection', 'is_motor_items', 'region_id', 'has_activation_code'));
        $policy = new Policy();
        $policy->customer_id = $user->id;
        $policy->agent_id = null;
        $policy->note = $request->get('note');
        $policy->leadSource = 'Chatbot';
        $policy->product_id = $request->get('product');
        $regionVat = Region::where('id',$product->region_id)->first(array('vat'))->vat;
        if ($product->premium_type_id == 11) {
            $product_plan = Productplan::where('id', $request->plan)->first(array('sum_assured', 'premium'));
            $premium = round(($product_plan->premium*($regionVat/100)) + $product_plan->premium,2);
            $policy->plan_id = $request->get('plan');
            $policy->sum_assured = $product_plan->sum_assured;
            $policy->premium = $premium;
        } else {
            $premium = ($request->get('premium')*($regionVat/100)) + $request->get('premium');
            $policy->premium = $premium;
            $policy->sum_assured = $request->get('sum_assured');
        }
        $policy->policyNumber = 'MIS' . Carbon::now()->year . str_pad(($latest->id + 1), 6, '0', STR_PAD_LEFT);
        $policy->status = 0;
        $policy->has_vehicle = $product->has_vehicle;
        $policy->has_member = $product->has_member;
        $policy->preinspection = $product->preinspection;
        $policy->is_motor_items = $product->is_motor_items;
        $policy->limit = $product->limit;
        $policy->kyc_customer = $product->kyc_customer;
        $policy->kyc_recipient = $product->kyc_recipient;

        if($product->has_activation_code)
        {
            $activation = Activation::where('activation_code', $request->get('activation_code'))->first();
            $activation->product_id = $request->get('product');
            $activation->product_plan_id = $request->get('plan');
            $activation->status = 1;
            $activation->save();

            $policy->activation_code = $request->get('activation_code');
            $policy->trial_coverage = $activation->trial_coverage;
            $policy->serial_code = $activation->serial_code;
        }
        $saved = $policy->save();

        $mains = FactorMain::where('product_id', $request->get('product'))->where('status', 1)->get(array('id', 'name', 'type'));
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

        if($request->get('members') != NULL){

            foreach (json_decode($request->get('members'), true)as $key => $member) {
                if ($member['relation'] != NULL) {
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
    
        if($request->get('beneficiaries') != NULL)
        {
            foreach (json_decode($request->get('beneficiaries'), true) as $key => $beneficiaries)
            {
                if($beneficiaries['beneficiaryRelation'] != NULL)
                {
                    $b = new PolicyBeneficiary();
                        $b->policy_id = $policy->id;
                        $b->relation = $beneficiaries['beneficiaryRelation'];
                        $b->first_name = $beneficiaries['beneficiaryFName'];
                        $b->last_name = $beneficiaries['beneficiaryLName'];
                        $b->passport = $beneficiaries['beneficiaryPassport'];
                        $b->omang = $beneficiaries['beneficiaryOmang'];
                        $b->dob = Carbon::parse($beneficiaries['beneficiaryDOB'])->format('Y-m-d');
                        $b->gender = $beneficiaries['beneficiaryGender'];
                        $b->payment =$beneficiaries['beneficiaryPayment'];
                        $b->save();
                }
            }
        }
        $banking = new CustomerBanking();
        $banking->customer_id = $user->id;
        $banking->policy_id = $policy->id;
        $banking->accountNumber = $request->get('accountNumber');
        $banking->billing = $request->get('billingMethod');
        $banking->billingCell = $request->get('billingCell');
        $banking->bankName = $request->get('bankName');
        $banking->branchCode = $request->get('branchCode');
        $banking->accountType = $request->get('bankAccountType');
        $saved = $banking->save();
        $vehicle = new Vehicle();
        $vehicle->customer_id = $user->id;
        $vehicle->policy_id = $policy->id;
        $vehicle->vehiclePlate = $request->vehiclePlate;
        $vehicle->chassisNo = $request->chassisNo;
        $vehicle->odometer = $request->odometer;
        $vehicle->condition = $request->condition;
        $vehicle->purpose = $request->purpose;
        $vehicle->make = $request->make;
        $vehicle->model = $request->model;
        $vehicle->year = $request->date;
        $vehicle->condition = $request->condition;
        $vehicle->cylinders = $request->cylinders;
        $vehicle->cubic_capacity = $request->cubic_capacity;
        $vehicle->seats = $request->seats;
        $vehicle->engineNo = $request->engineNo;
        $vehicle->is_private = $request->is_private;
        $vehicle->is_modified = $request->is_modified;
        $vehicle->is_tracking = $request->is_tracking;
        $vehicle->is_imported = $request->is_imported;
        if ($request->hasFile('front')) {
            $file = $request->file('front');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/front' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $vehicle->front = $filePath;
        }
        if ($request->hasFile('back')) {
            $file = $request->file('back');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/back' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $vehicle->back = $filePath;
        }
        if ($request->hasFile('right')) {
            $file = $request->file('right');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/right' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $vehicle->right = $filePath;
        }
        if ($request->hasFile('left')) {
            $file = $request->file('left');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/left' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $vehicle->left = $filePath;
        }
        if ($request->hasFile('vehicleRegistration')) {
            $file = $request->file('vehicleRegistration');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $policy->customer_id . '/' . 'vehicleRegistration' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $vehicle->vehicleRegistration = $filePath;
        }
        $saved = $vehicle->save();
        if ($request->main != Null) {
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
        $user_id =  $user->id;
        $policy_id = $policy->id;
        $product_id = $policy->product_id;
        $policyProduct = Policy::where('product_id',$product_id)->first();
        $policyProductId = $policyProduct->product_id;
        $ruleProductCount = AccountingRules::where('product_id',$policyProductId)->where('action','like', '%' . 'Process Policy' . '%')->count();
        if($ruleProductCount > 0){
            $new = $this->ledgerStore($user_id,$policy_id,$product_id);
            switch($request->billingOption){
                case 'VCS':
                    $vcs = new VcsController;
                    return $vcs->chatbotVcsPayment($policy->policyNumber,$premium , $activation->trial_coverage);
                    break;
                case 'Orange' :
                    $orangeMoney = new OrangeMoneyController();
                    return $orangeMoney->chatbotPayIntiliazer($policy->policyNumber,$premium);
                    break;
                case 'RealPay':
                    $realPay = new RealPayController();
                    return $realPay->addClientRealPay($policy, $premium);
                    break;
                default: return Redirect::back()->with('error', 'Please select a payment vendor')
                    ->withInput($request->all());
            }
            return Redirect::route('admin.policy.index')->with('success', 'Policy Created Successfully');
        }
        else
            switch($request->billingOption){
                case 'VCS':
                    $vcs = new VcsController;
                    return $vcs->chatbotVcsPayment($policy->policyNumber,$premium);
                    break;
                case 'Orange':
                    $orangeMoney = new OrangeMoneyController();
                    return $orangeMoney->chatbotPayIntiliazer($policy->policyNumber,$premium);
                    break;
                case 'RealPay':
                    $realPay = new RealPayController();
                    return $realPay->addClientRealPay($policy, $premium);
                    break;
                default: return Redirect::back()->with('error', 'Please select a payment vendor')
                    ->withInput($request->all());
            }
    }

    // method to get purpose
    // JSON
    public function getPurposes(){

        $vehicle_purpose = Lookup::where('key', 'vehicle_purpose')->get(array('id','value'));
        return response()->json($vehicle_purpose);
    }


}
