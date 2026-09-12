<?php

namespace AlphaDirect\Http\Controllers\Frontend;

use AlphaDirect\Customer;
use AlphaDirect\CustomerBanking;
use AlphaDirect\CustomerProfile;
use AlphaDirect\FactorMain;
use AlphaDirect\FactorSubType;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\KYC;
use AlphaDirect\AgentKyc;
use AlphaDirect\Documents;
use AlphaDirect\Http\Controllers\Admin\DocumentController;
use AlphaDirect\Lookup;
use AlphaDirect\Policy;
use AlphaDirect\PolicyBeneficiary;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\PolicyCoverCancelNote;
use AlphaDirect\PolicyFactor;
use AlphaDirect\PolicyLeads;
use AlphaDirect\PolicyLeadsFactor;
use AlphaDirect\PolicyMember;
use AlphaDirect\Product;
use AlphaDirect\Models\ProductCoverage;
use AlphaDirect\Productplan;
use AlphaDirect\Region;
use AlphaDirect\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\Helper;

class FrontendController extends Controller
{
    // method to update policy
    // paraam: policy id
    //return --
    public function updatepolicy($id, Request $request)
    {
        $policy = Policy::where('id', $id)->first();
        $user = Customer::where('id', $policy->customer_id)->first();
        $user->firstName = $request->get('fname');
        $user->lastName = $request->get('lname');
        $user->email = $request->get('email');
        $user->cellphone = $request->get('cellphone');
        $user->save();

        $profile = CustomerProfile::where('customer_id', $policy->customer_id)->first();
        $profile->customer_id = $user->id;
        $profile->gender = $request->get('gender');
        $profile->address = $request->get('address');
        $profile->omang = $request->get('omang');
        $profile->passport = $request->get('passport');
        $profile->dob = Carbon::parse($request->get('dob'))->format('Y-m-d');
        $profile->save();
        $mains = FactorMain::where('product_id', $policy->product_id)->where('status', 1)->get(array('id', 'name'));
        foreach ($mains as $main) {
            if (is_array($request->get('factor_' . $main->id))) {
                PolicyFactor::where('policy_id', $policy->id)->where('factor_main_id', $main->id)->delete();
                //For Check box multiple values
                foreach ($request->get('factor_' . $main->id) as $key => $value_id) {
                    $factor = new PolicyFactor();
                    $factor->policy_id = $policy->id;
                    $factor->factor_main_id = $main->id;
                    $factor->name = $main->name;

                    $value = FactorSubType::where('id', $value_id)->first(array('name', 'factor'));
                    $factor->factor_value_id = $value_id;
                    $factor->value_name = $value->name;
                    $factor->factor = $value->factor;
                    $saved = $factor->save();
                }
            } else {
                $factor = PolicyFactor::where('policy_id', $policy->id)->where('factor_main_id', $main->id)->first();
                $factor->name = $main->name;

                $value = FactorSubType::where('id', $request->get('factor_' . $main->id))->first(array('name', 'factor'));
                if ($value == null) {
                    $factor->value_name = $request->get('factor_' . $main->id);
                } else {
                    $factor->value_name = $value->name;
                    $factor->factor = $value->factor;
                    $factor->factor_value_id = $request->get('factor_' . $main->id);
                }
                $saved = $factor->save();
            }
        }

        //For Already added members
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
        //KYC Update
        $omangKYC = KYC::where('customer_id', $policy->customer_id)->first();
        if ($request->hasFile('driving_license')) {
            $file = $request->file('driving_license');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $policy->customer_id . '/' . 'KYC' . '/' . $user->firstName . '/driving_license' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $omangKYC->driving_license = $filePath;
        }
        if ($request->hasFile('omang_kyc')) {
            $file = $request->file('omang_kyc');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $policy->customer_id . '/' . 'KYC' . '/' . $user->firstName . '/Omang' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $omangKYC->omang = $filePath;
        }
        if ($request->hasFile('proof_residence')) {
            $file = $request->file('proof_residence');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $policy->customer_id . '/' . 'KYC' . '/' . $user->firstName . '/proof_residence' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $omangKYC->proof_residence = $filePath;
        }
        if ($request->hasFile('proof_income')) {
            $file = $request->file('proof_income');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $policy->customer_id . '/' . 'KYC' . '/' . $user->firstName . '/proof_income' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $omangKYC->proof_income = $filePath;
        }
        if ($request->hasFile('passport_kyc')) {
            $file = $request->file('passport_kyc');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $policy->customer_id . '/' . 'KYC' . '/' . $user->firstName . '/passport' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $omangKYC->passport = $filePath;
        }
        $omangKYC->save();

        $banking = CustomerBanking::where('customer_id', $policy->customer_id)->where('policy_id', $policy->id)->first();
        $banking->accountNumber = $request->get('accountNumber');
        $banking->billing = $request->get('billingMethod');
        $banking->billingCell = $request->get('billingCell');
        $banking->bankName = $request->get('bankName');
        $banking->branchCode = $request->get('branchCode');
        $banking->accountType = $request->get('bankAccountType');
        $saved = $banking->save();
    }

    // method to get policy summary
    // paraam: policy id
    // return --
    public function getpolicysummary(Request $request)
    {
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

        return response()->json($policy);
    }

    // method to store policy
    // return JSON
    public function savePolicyYes(Request $request)
    {
        $user = new Customer();
        $user->firstName = $request->get('fname');
        $user->lastName = $request->get('lname');
        $user->email = $request->get('email');
        $user->cellphone = $request->get('cellphone');
        $user->save();

        $kyc = new KYC();
        $kyc->customer_id = $user->id;
        $kyc->save();

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
        $profile->dob = Carbon::parse($request->get('dob'))->format('Y-m-d');
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

        if ($request->hasFile('proof_residence')) {
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

        if ($request->hasFile('passport')) {
            $file = $request->file('passport');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $kyc->customer_id . '/' . 'Customer' . '/passport' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $kyc->passport = $filePath;
        }

        $kyc->compliance = 0;
        $kyc->save();

        //Latestid for Policy Number
        $latest = Policy::latest()->first(array('id'));
        $product = Product::where('id', $request->get('product'))->first(array('has_vehicle', 'has_member', 'premium_type_id', 'limit', 'kyc_recipient', 'kyc_customer', 'preinspection', 'is_motor_items'));

        $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
        $policy = new Policy();
        $policy->customer_id = $user->id;
        $policy->product_id = $request->get('product');
        if ($product->premium_type_id == 11) {
            $product_plan = Productplan::where('id', $request->get('plan'))->first(array('sum_assured', 'premium'));
            $policy->plan_id = $request->get('plan');
            $policy->sum_assured = $product_plan->sum_assured;
            $policy->premium = ($request->get('premium') * ($regionVat / 100)) + $request->get('premium');
        } else {
            $policy->premium = ($request->get('premium') * ($regionVat / 100)) + $request->get('premium');
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
        $policy->activation_code = $request->get('activation_code');
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

        $saved = $vehicle->save();
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

        if ($saved) {
            // Redirect to the home page with success menu
            return response()->json(array('status' => 'success', 'message' => 'Policy Created Successfully', 'policy_details' => $policy));
        } else {
            return response()->json(array('status' => 'success', 'message' => 'Something Went Wrong', 'policy_details' => null));
        }
    }

    // retrieve all product factor related to products
    // param: product id
    // return: JSON
    public function getProductFactors(Request $request)
    {
        $check = FactorMain::with('value')->where('product_id', $request->get('id'))->where('status', 1)->get(array('id', 'name', 'type'));
        // Check if we are not trying to delete ourselves

        $product = Product::where('id', $request->get('id'))->first(array('id', 'has_vehicle', 'has_member', 'premium_type_id', 'has_activation_code', 'is_motor_items', 'preinspection'));
        if ($product->premium_type_id == 11) {
            $plans = Productplan::where('product_id', $request->get('id'))->where('status', 1)->get(array('id', 'name', 'premium', 'sum_assured'))->toArray();
        } else {
            $plans = null;
        }

        $coverage = ProductCoverage::where('product_id', $request->get('id'))->get(array('coverage_id', 'name'));
        if ($check->count() == null) {
            // Prepare the error message
            return response()->json(['status' => 'error', 'factors' => $check, 'product' => $product, 'coverage' => $coverage, 'plans' => $plans]);
        } else {
            return response()->json(['status' => 'success', 'factors' => $check, 'product' => $product, 'coverage' => $coverage, 'plans' => $plans]);
        }
    }

    // retrieve all products
    // param: product id
    // return: JSON
    public function getProducts()
    {
        $check = Product::where('status', 1)->get(array('id', 'name'));
        // Check if we are not trying to delete ourselves
        if ($check->count() == null) {
            // Prepare the error message
            return response()->json(['status' => 'error']);
        } else {
            return response()->json(['status' => 'success', 'products' => $check]);
        }
    }
// Policy Document Api
    // public function getPolicyDocument(Request $request)
    // {
    //     $policy = Policy::where('customer_id', $request->customer_id)->first(array('id','product_id','customer_id'));

    //     if ($policy != NULL) {
    //         $customer = Customer::where('id',$policy->customer_id)->first(array('id','email'));

    //         $emailDocs = Documents::where('product_id', $policy->product_id)
    //             ->orWhere('product_id', -1)
    //             ->where('status', 1)
    //             ->get(array('name', 'link'));


    //         $cancelNote = PolicyCoverCancelNote::where('policy_id', $policy->id)
    //         ->where('doc_type', 'Cancel')
    //         ->orderBy('id', 'DESC')
    //         ->first(array('path'));

    //         $coverNote = PolicyCoverCancelNote::where('policy_id', $policy->id)
    //         ->where('doc_type', 'Cover')
    //         ->orderBy('id', 'DESC')
    //         ->first(array('path'));

    //         $policyDocument = Policy::where('customer_id', $request->customer_id)->get(array('policyDocument','policyNumber'));

    //         return response()->json(['success'=> true, 'policyDocument'=>$policyDocument,'customer'=>$customer,'emailDocs'=>$emailDocs,'coverNote'=> $coverNote,'cancelNote'=>$cancelNote], 200);
    //     } else {
    //         return response()->json(['success' => false, 'Message' => 'Policy Number not found'], 401);
    //     }
    // }

    /////

    public function getPolicyDocument(Request $request)
    {
            $policies = Policy::leftJoin('policy_cover_cancel_notes','policy_cover_cancel_notes.policy_id','=','policies.id')
                            ->where('policies.customer_id', $request->customer_id)
                            ->orderBy('policies.id', 'DESC')
                            ->get([
                                'policies.id',
                                'policies.product_id',
                                'policies.customer_id',
                                'policies.policyNumber',
                                'policies.policyDocument as policyschedule',
                                'policies.status',
                                'policy_cover_cancel_notes.path as cover_cancel_file',
                                'policy_cover_cancel_notes.doc_type',
                            ]);

            foreach($policies as $policy)
            {
                $documents = Documents::where('product_id', $policy->product_id)->where('status','!=',null)->get(array('name', 'link'));
                $policy->documents = $documents;

                $docs_type = $policy->doc_type;
                $policyId = $policy->id;
                if($policy->cover_cancel_file == null || $policy->cover_cancel_file == ""){
                    $CoverCancelDocument = new DocumentController();
                    $getCoverCancelNote =  $CoverCancelDocument->generateCoverCancelNote($policyId,$docs_type);
                }
            }

        $customer = Customer::where('id',$request->customer_id)->first(array('email'));
        $count = count($policies);
        if ($count > 0) {
            return response()->json([
                'success' => true, 'message' => "Document found for the user",'policies' => $policies,'customer'=>$customer
            ], 200);
        } else {
            return response()->json([
                'success' => false, 'message' => "No Document found for the user"
            ], 401);
        }
    }
    public function getAllWording(Request $request)
    {
        // Get all products with a valid id
        $products = Product::whereNotNull('id')->whereNotIn('id', [7,8])
        ->where('status', 1)->get(['id', 'name']);
        $result = [];
        foreach ($products as $product) {
            // Get all documents for this product where status is active and product_id matches
            $productDocuments = Documents::where('product_id', $product->id)
                ->where('status', 1)
                ->whereNull('plan_id')
                ->get(['id', 'name', 'link', 'plan_id']);

            // Convert links to S3 URLs
            $productDocumentsArr = [];
            foreach ($productDocuments as $doc) {
                $productDocumentsArr[] = [
                    'id' => $doc->id,
                    'name' => $doc->name,
                    'link' => Helper::getCloudFrontURL($doc->link),
                ];
            }

            // Get all plan-specific documents for this product
            $planDocuments = Documents::where('product_id', $product->id)
                ->where('status', 1)
                ->whereNotNull('plan_id')
                ->get(['id', 'name', 'link', 'plan_id']);

            // Group plan documents by plan_id
            $plans = [];
            foreach ($planDocuments as $doc) {
                $plans[$doc->plan_id][] = [
                    'id' => $doc->id,
                    'name' => $doc->name,
                    'link' => Helper::getCloudFrontURL($doc->link),
                ];
            }

            $result[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'documents' => $productDocumentsArr,
                'plans' => $plans,
            ];
        }
        return response()->json(['status' => 'success', 'data' => $result]);
    }
    

    // return calculated premium
    // param: product id
    // return: JSON
    public function calculatePremium(Request $request)
    {
        $product = Product::where('id', $request->get('product_id'))->first(array('formula', 'region_id'));
        $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
        if ($product->formula == null) {
            // Prepare the error message
            return response()->json(['status' => 'error']);
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
                return response()->json(['status' => 'error', 'message' => 'Invalid premium formula.'], 422);
            }
            $result = 0;
            eval('$result = (' . ($mathTrimmed + $mathTrimmed * ($regionVat / 100)) . ');');

            return response()->json(['status' => 'success', 'premium' => $result]);
        }
    }
    public function savePolicyNo(Request $request)
    {
        $policyLeadsFactorsArray = array_merge($request->factor_1, $request->factor_2, $request->factor_1, $request->factor_input);

        $policy_leads = new PolicyLeads();
        $policy_leads->fname = $request->fname;
        $policy_leads->lname = $request->lname;
        $policy_leads->email = $request->email;
        $policy_leads->gender = $request->gender;
        $policy_leads->maritalstatus = $request->maritalstatus;
        $policy_leads->dob = Carbon::parse($request->dob);
        $policy_leads->cellphone = $request->cellphone;
        $policy_leads->omang = $request->omang;
        $policy_leads->passport = $request->passport;
        $policy_leads->reason_policy = $request->reason_policy;
        $policy_leads->save();

        foreach ($policyLeadsFactorsArray as $item) {
            $policyLeadsFactor = new PolicyLeadsFactor();
            $policyLeadsFactor->policy_leads_id = $policy_leads->id;
            $policyLeadsFactor->factor_main_id = $item;
            $policyLeadsFactor->save();
        }
        return response()->json(array('status' => 'success'));
    }

    public function getThumbnailPath($path)
    {
        $strArr = explode("/", $path);
        $reversedArray =  array_reverse($strArr);
        $new = $reversedArray[0];
        $newName = "thumbnail/" . $new;
        array_pop($strArr);
        array_push($strArr, $newName);
        $newPath = implode("/", $strArr);
        return $newPath;
    }

    public function getFilesArr($field, $columnName)
    {
        return array(
            "name" => basename($field),
            "url" => $field,
            "extension" => $this->getFileExtension($field),
            "deleteUrl" => $field,
            "docType" => $this->getDocType($columnName),
            "thumbnailUrl" => $this->getThumbnailPath($field),
            "deleteType" => "DELETE"
        );
    }

    public function getKycFiles(Request $request)
    {
        $customer_id = $request->customer_id;
        $customer_id = base64_decode($customer_id);
        $KYCrow = AgentKyc::where('customer_id', $customer_id)->first();
        $files = array();
        if (!empty($KYCrow)) {
            $fieldArray = array('omang', 'omangBack', 'passport', 'passportBack', 'driversLicense', 'driving_license_back', 'proofIncome', 'proofResidence');
            foreach ($fieldArray as $key => $field) {
                if ($KYCrow->$field && $KYCrow->$field != '') {
                    array_push($files, $this->getFilesArr($KYCrow->$field, $field));
                }
            }
            return response()->json(array('status' => 'success', 'files' => $files));
        }
    }

    public function startgetKycFiles(Request $request)
    {
        $customer_id = $request->customer_id;
        // $customer_id = base64_decode($customer_id);
        $KYCrow = KYC::where('customer_id',$customer_id)->first();
        $files = array();
        if (!empty($KYCrow)) {
            $fieldArray = array('omang', 'omangBack', 'passport', 'proof_income', 'proof_residence');
            foreach ($fieldArray as $key => $field) {
                if ($KYCrow->$field && $KYCrow->$field != '') {
                    array_push($files, $this->getFilesArr($KYCrow->$field, $field));
                }
            }
            return response()->json(array('status' => 'success', 'files' => $files, 'KYCrow'=>$KYCrow));
        }
    }


    public function getFileExtension($field)
    {
        return pathinfo($field, PATHINFO_EXTENSION);
    }

    public function getDocType($columnName)
    {
        switch ($columnName) {
            case 'omang':
                return "Omang Front";
                break;

            case 'omangBack':
                return "Omang Back";
                break;

            case 'passport':
                return "Passport Front";
                break;

            case 'passportBack':
                return "Passport Back";
                break;

            case 'driversLicense':
                return "Driving License Front";
                break;

            case 'driving_license_back':
                return "Driving License Back";
                break;

            case 'proofIncome':
                return "Proof of Income";
                break;

            case 'proofResidence':
                return "Proof of Residence";
                break;

            default:
                return "";
                break;
        }
    }

    public function deleteKycFiles(Request $request)
    {
        $customer_id = $request->customer_id;
        $customer_id = base64_decode($customer_id);
        $KYCrow = AgentKyc::where('customer_id', $customer_id)->first();
        return response()->json(array('status' => 'success', 'data' => $KYCrow));
    }
}
