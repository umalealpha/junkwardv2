<?php

namespace AlphaDirect\Http\Controllers\CustomerPortal;

use AlphaDirect\Customer;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\KYC;
use AlphaDirect\OTP;
use AlphaDirect\Policy;
use AlphaDirect\PolicyPlan;
use AlphaDirect\User;
use AlphaDirect\Vehicle;
use Auth;
use DB;
use Hash;
use Illuminate\Http\Request;

class CustomerPortalController extends Controller
{
    // method to return customer login page
    // return view: customer login page
    public function login()
    {

        return view('CustomerLogin');
    }

    // method to return customer dashboard
    // return view: customer dashboard page
    public function index()
    {

        return view('CustomerPortal/Customer/myDashboard');
    }

    // method to return set new login passowrd page
    // return view: customer set new password page
    public function setPasswordView()
    {

        return view('CustomerPortal/Customer/SetNewPassword');
    }

    // method to return view claim page
    // return view: claim page
    public function ViewCliams()
    {

        return view('CustomerPortal/Claims/myClaims');
    }

    // method to return view policy page
    // return view: policy page
    public function ViewPolicies()
    {

        return view('CustomerPortal/Policy/myPolicies');
    }

    // method to return view installment page
    // return view: customer installment page
    public function ViewInstallemts()
    {

        return view('CustomerPortal/Banking/customerInstallemts');
    }

    // method to return view customer transactions
    // return view: transaction page
    public function ViewTransactions()
    {

        return view('CustomerPortal/Banking/customerTransactions');
    }

    // method to return view profile page
    // return view: profile page
    public function myProfileView()
    {

        $loggedInUser = Auth::user()->id;
        $userDetails = User::find($loggedInUser);
        $userKYC = KYC::where('user_id', $loggedInUser)->first();
        $userBanking = CustomerBanking::where('user_id', $loggedInUser)->first();

        $userPoliciesCount = Policy::where('user_id', $loggedInUser)->count();
        $userCarCount = Vehicle::where('user_id', $loggedInUser)->count();

        return view('CustomerPortal/MyProfile/Myprofile', compact('userDetails', 'userKYC', 'userBanking', 'userPoliciesCount', 'userCarCount'));
    }

    // method to return view policy detail page
    // return view: policy detail view page
    // param: policy ID
    public function viewPolicyDetail($id)
    {

        $policies = Policy::find($id);
        $policyPlan = PolicyPlan::where('status', 1)->get();
        $selectedPolicyPlan = PolicyPlan::find($policies->plan_id)->first();
        $vehicle = Vehicle::find($policies->id);

        $carMake = DB::table('tb_prmotormakemodels')
            ->selectRaw('DISTINCT s_Make')
            ->get();

        $userDetails = User::find($policies->user_id);
        $agentDetails = User::find($policies->agent_id);
        $customerKYC = KYC::where('user_id', $userDetails->id)->first();
        $customerBanking = CustomerBanking::where('policy_id', $policies->id)->first();

        return view('CustomerPortal/Policy/PolicyDetails', compact('policies', 'userDetails', 'carMake', 'customerKYC', 'vehicle', 'agentDetails', 'policyPlan', 'selectedPolicyPlan', 'customerBanking', 'policyActivityLog'));

    }

    public function showOtp()
    {

        return view('CustomerPortal/Otp/otpCustomer');
    }

    // method to store user new resetted passord
    // return view: policy customer login page
    public function userSetNewPassword(Request $request)
    {

        $user = User::where('id', $request->userId)->first();
        $user->password = $request->password;
        $user->isVerified = 1;
        $user->save();

        return redirect()->route('customerLogin');
    }

    // method to return all user policies
    // return view: policy list page
    // JSON
    public function getUserPolicies()
    {

        $userPolicies = DB::table('policies')
            ->leftJoin('customer_kyc', 'customer_kyc.user_id', '=', 'policies.user_id')
            ->leftJoin('users', 'users.id', '=', 'policies.user_id')
            ->leftJoin('customer_banking', 'customer_banking.policy_id', '=', 'policies.id')
            ->leftJoin('vehicle', 'vehicle.policy_id', '=', 'policies.id')
            ->where('policies.user_id', Auth::user()->id)
            ->select('customer_kyc.omang AS kycOmang',
                'vehicle.vehicleRegistration AS kycBluebook',
                'customer_kyc.driversLicense AS kycLicense',
                'policies.id',
                'users.firstName',
                'users.lastName',
                'users.cellphone',
                'users.dob',
                'users.omang',
                'vehicle.vehiclePlate as licensePlate',
                'customer_banking.prefered as billing',
                'customer_banking.billingCell',
                'customer_banking.billingStartDate',
                'customer_banking.bankName',
                'customer_banking.branchCode',
                'customer_banking.accountNumber',
                'policies.policyNumber',
                'policies.plan_id',
                'policies.isActive'
            )->orderBy('policies.created_at', 'desc')
            ->get();

        return response()->json($userPolicies);
    }

    // method to return policy 
    // param: policy number
    // return view: policy list page
    // JSON
    public function findPolicyNumber(Request $request)
    {
        try {
            //code...send to graphite
            if ($request->policyNumber != null) {
                $policy = Policy::where('policyNumber', $request->policyNumber)->first();

                if ($policy->count() != null) {
                    $customer = Customer::where('id', $policy->customer_id)->first();
                    $request->request->add(['cellphone' => $customer->cellphone]);
                    return response()->json(['customerDetails' => $customer], 200);

                } else {

                    return response()->json('no values to display', 204);
                }
            } else {
                return response()->json('Policy number does not exists', 401);
            }

        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage(), 500);
        }

    }


    // method to verify customer OTP on customer portal
    // param:  policy number
    // return: JSON
    public function verifyCustomerPortalOTP(Request $request)
    {
        $code = $request->otp;
        try {
            $policy = Policy::where('policyNumber', $request->policyNumber)->first(); //get policy
            $customer = Customer::where('id', $policy->customer_id)->first(); //get customer number from customer policy
            $userOtp = OTP::where('OTP', $code)->first(); //get the recent otp

            if ($customer->cellphone == $userOtp->cellphone && $userOtp->otp == $code) {
                $deleteOTP = OTP::where('id', $userOtp->id);
                $deleteOTP->delete();
                return response()->json(['message' => 'OTP Verified', 'customer' => $customer], 200);
            } else {
                return response()->json('OTP verification failed, please try again.', 401);
            }

        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage(), 500);
        }

    }

    // method to store customer password from portal
    // param: customer cellphone number
    // return JSON
    public function setCustomerPortalPassword(Request $request)
    {
        try {
            //code...
            $phoneNumber = $request->cellphone;
            $password = $request->password;

            //code...
            $customer = Customer::where('cellphone', $phoneNumber)->first();

            if ($customer != null) {

                $customer->password = Hash::make($request->password);

                if ($customer->save()) {
                    return response()->json(['message' => 'Password created succefully'], 200);
                } else {
                    return response()->json('Unable to set password, please try again.', 400);
                }
            } else {
                return response()->json('Unable to set password, please try again.', 401);
            }

        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage(), 500);
        }
    }

}
