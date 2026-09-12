<?php

namespace AlphaDirect\Http\Controllers\FrontendOld;

use AlphaDirect\Customer;
use AlphaDirect\CustomerBanking;
use AlphaDirect\CustomerProfile;
use AlphaDirect\FactorMain;
use AlphaDirect\FactorSubType;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\KYC;
use AlphaDirect\Policy;
use AlphaDirect\PolicyFactor;
use AlphaDirect\PolicyMember;
use AlphaDirect\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redirect;

class FrontendController extends Controller
{
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
        if (is_array($request->get('old_member'))) {
            for ($i = 0; $i < count($request->get('old_member')); $i++) {
                $m = PolicyMember::where('id', $request->get('old_member')[$i])->first();
                $m->relation = $request->get('old_relation')[$i];
                $m->first_name = $request->get('old_memberFName')[$i];
                $m->last_name = $request->get('old_memberLName')[$i];
                $m->dob = Carbon::parse($request->get('old_memberDOB')[$i])->format('Y-m-d');
                $m->gender = $request->get('old_memberGender')[$i];
                $m->save();
            }
        }

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

        if ($saved) {
            // Redirect to the home page with success menu
            return Redirect::route('thankyou')->with('success', 'Policy Updated Successfully');
        } else {
            return Redirect::route('index')->with('error', 'Something Went Wrong');
        }
    }

    public function getpolicysummary(Request $request)
    {

        $policy = Policy::join('customer', 'policies.customer_id', 'customer.id')
            ->join('customer_banking', 'policies.id', 'customer_banking.policy_id')
            ->join('policy_members', 'policies.id', 'policy_members.policy_id')
            ->join('customer_profile', 'customer.id', 'customer_profile.customer_id')
            ->join('policy_factors', 'policies.id', 'policy_factors.policy_id')
            ->where('policies.id', $request->policy_id)
            ->first(array('policies.id as policy_id', 'policies.agent_id as policy_agent_id', 'policies.product_id as policy_product_id', 'customer.id as customer_id', 'customer.firstName', 'customer.lastName', 'customer_profile.dob', 'customer_profile.omang', 'customer_profile.gender', 'customer_profile.passport', 'customer.cellphone', 'customer_profile.maritalstatus', 'customer_banking.bankName', 'customer_banking.billing', 'customer_banking.accountNumber', 'customer_banking.branchCode', 'customer_banking.accountType', 'policy_members.first_name', 'policy_members.last_name', 'policy_members.dob as memberdob', 'policy_members.gender as membergender', 'policy_members.relation', 'policy_factors.value_name'));
        $factors = PolicyFactor::where('policy_id', $request->policy_id)->get(array('factor_main_id', 'factor_value_id', 'value_name'));
        $policy->policy_factors = $factors;
        return response()->json($policy);

    }

    public function savePolicyYes(Request $request)
    {
        $user = new Customer();
        $user->firstName = $request['fname'];
        $user->lastName = $request->get('lname');
        $user->email = $request->get('email');
        $user->cellphone = $request->get('cellphone');
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
        $profile->dob = Carbon::parse($request->get('dob'));
        $profile->save();

        //Latestid for Policy Number
        $latest = Policy::latest()->first(array('id'));

        $policy = new Policy();
        $policy->customer_id = $user->id;
        $policy->product_id = $request['"product'];
        $policy->premium = $request->get('premium');
        $policy->policyNumber = 'MIS' . Carbon::now()->year . str_pad(($latest->id + 1), 6, '0', STR_PAD_LEFT);
        $policy->status = 1;
        $policy->save();

        $mains = FactorMain::where('product_id', $request->get('"product'))->where('status', 1)->get(array('id', 'name'));

        foreach ($mains as $main) {

            if (is_array($request->get('factor_' . $main->id))) {
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
                $factor = new PolicyFactor();
                $factor->policy_id = $policy->id;
                $factor->factor_main_id = $main->id;
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

        $relation = $request->relation;
        $memberLName = $request->memberLName;
        $memberDOB = $request->memberDOB;
        $memberSuminsured = $request->memberSuminsured;

        foreach ($request->get('memberFName') as $key => $member) {
            if ($member != null) {
                $m = new PolicyMember();
                $m->policy_id = $policy->id;
                $m->first_name = $member[$key];
                $m->last_name = $memberLName[$key];
                $m->relation = $relation[$key];
                $m->dob = Carbon::parse($memberDOB[$key]);
                $m->gender = $memberSuminsured[$key];
                $m->save();
            }
        }
        $banking = new CustomerBanking();
        $banking->customer_id = $user->id;
        $banking->policy_id = $policy->id;
        $banking->billing = $request->get('billing');
        $banking->billingCell = $request->get('billingCell');
        $banking->bankName = $request->get('bankName');
        $banking->branchCode = $request->get('branchCode');
        $banking->accountType = $request->get('bankAccountType');
        $banking->accountNumber = $request->get('accountNumber');
        $saved = $banking->save();

        if ($saved) {
            // Redirect to the home page with success menu
            return response()->json(array('status' => 'success', 'message' => 'Policy Created Successfully', 'policy_details' => $policy));
        } else {
            return response()->json(array('status' => 'success', 'message' => 'Something Went Wrong', 'policy_details' => null));
        }
    }

    public function getProductFactors(Request $request)
    {

        $check = FactorMain::with('value')->where('product_id', $request->get('id'))->where('status', 1)->get(array('id', 'name', 'type'));

        // Check if we are not trying to delete ourselves
        if ($check->count() == null) {
            // Prepare the error message
            return response()->json(['status' => 'error']);
        } else {
            $product = Product::where('id', $request->get('id'))->first(array('has_vehicle', 'has_member'));
            return response()->json(['status' => 'success', 'factors' => $check, 'product' => $product]);
        }

    }

    public function getInsuranceType(Request $request)
    {
        $check = Product::where('id', $request->get('id'))->first();
        // Check if we are not trying to delete ourselves
        if ($check->count() == null) {
            // Prepare the error message
            return response()->json(['status' => 'error', 'data' => $check]);
        } else {
            return response()->json(['status' => 'success', 'factors' => $check]);
        }
    }

    public function calculatePremium(Request $request)
    {
        $check = Product::where('status', 1)->get(array('id', 'name'));
        // Check if we are not trying to delete ourselves
        if ($check->count() == null) {
            // Prepare the error message
            return response()->json(['status' => 'error']);
        } else {
            return response()->json(['status' => 'success', 'premium' => '1000 P']);
        }
    }

}
