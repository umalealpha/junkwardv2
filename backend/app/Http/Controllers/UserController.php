<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\Claim;
use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\KYC;
use AlphaDirect\Notifications;
use AlphaDirect\OTP;
use AlphaDirect\Role;
use Auth;
use DB;
use Hash;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Redirect;
use Session;
use Validator;
use AlphaDirect\UserProfile;


class UserController extends Controller
{

    use AuthenticatesUsers;

    public function showUser(Request $request)
    {
        return $request->user();
    }

    public function login(Request $request)
    {

        if (Auth::guard('customer')->attempt(['cellphone' => request('cellphone'), 'password' => request('password')])) {
            $user = Auth::guard('customer')->user();
            $customerKYC = KYC::where('customer_id', $user->id)->first();
            $customerProfile = CustomerProfile::where('customer_id', $user->id)->first();
            $user->setAttribute('kyc-compliance', $customerKYC->compliance);

            $alphaResponse = response()->json(['user' => $user, 'profile' => $customerProfile], 200);
            return $alphaResponse;
        }
        //check if customer cellphone exists
        if (Customer::where('cellphone', '=', $request->cellphone)->exists()) {

            return response()->json(['code' => '409', 'Description' => 'Account Exists , incorrect credentials'], 409);
        } else {

            $user = Auth::user();

            if ($user == null) {

                return response()->json(['code' => '401', 'Description' => 'Account does not exist'], 401);
            }
        }
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firstName' => 'required',
            'lastName' => 'required',
            'email' => 'required|email',
            'cellphone' => 'required',
            'password' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
        $userAccount = User::where('email', $request->email)->exists();
        if ($userAccount) {
            Session::flash('accountCreation', 'Staff email has already been used');
            redirect()->back();
            return response()->json('User Account exists');
        } else {

            // Roles for account assigning
            $userRole = Role::where('name', 'Super Admin')->first();
            $agentRole = Role::where('name', 'Agents')->first();
            $hodRole = Role::where('name', 'HOD')->first();
            $customerRole = Role::where('name', 'Customer')->first();

            switch ($request->accountType) {

                case 'HOD':
                    $role_hod = new User();
                    $role_hod->firstName = $request->firstName;
                    $role_hod->lastName = $request->lastName;
                    $role_hod->email = $request->email;
                    $role_hod->cellphone = $request->cellphone;
                    $role_hod->password = bcrypt($request->pasword);
                    $role_hod->save();
                    $role_hod->roles()->attach($hodRole);

                    return response()->json('HOD account successfully created', 200);

                    break;

                case 'Agents':
                    $role_agent = new User();
                    $role_agent->firstName = $request->firstName;
                    $role_agent->lastName = $request->lastName;
                    $role_agent->email = $request->email;
                    $role_agent->cellphone = $request->cellphone;
                    $role_agent->password = bcrypt($request->pasword);
                    $role_agent->save();
                    $role_agent->roles()->attach($agentRole);

                    Session::flash('employeeAdded', 'Employee has been added');
                    response()->json('Agents account successfully created', 200);
                    return Redirect::back();

                    break;

                default:
                    $role_customer = new User();
                    $role_customer->firstName = $request->firstName;
                    $role_customer->lastName = $request->lastName;
                    $role_customer->email = $request->email;
                    $role_customer->cellphone = $request->cellphone;
                    $role_customer->password = bcrypt($request->pasword);
                    $role_customer->save();
                    $role_customer->roles()->attach($agentRole);

                    return response()->json('Customer account successfully created', 200);

                    break;
            }
        }
    }

    public function saveKYC(Request $request)
    {

        //associate KYC with policy activation
        $kyc = new KYC;

        $kyc->driversLicense = $request->driversLicense;
        $kyc->omang = $request->omang;
        $kyc->bluebook = $request->bluebook;
        $kyc->save();
    }
    public function notifications(Request $request)
    {

        $notifications = Notifications::where('user_id', $request->user_id)
            ->where('read_at', null)
            ->take(5)
            ->get();

        if (!$notifications) {

            return response()->json(['message' => 'No notifications found for this user']);
        }

        foreach ($notifications as $nots) {

            $nots->read_at = date("Y/m/d");
            $nots->save();
        }

        return response()->json($notifications);
    }

    public function getAllUserApplications($id)
    {
        try {
            //code...
            $userPolicies = DB::table('policies')
                ->leftJoin('customer_kyc', 'customer_kyc.customer_id', '=', 'policies.customer_id')
                ->leftJoin('customer', 'customer.id', '=', 'policies.customer_id')
                ->leftJoin('customer_banking', 'customer_banking.policy_id', '=', 'policies.id')
                ->leftJoin('vehicle', 'vehicle.policy_id', '=', 'policies.id')
                ->leftJoin('products', 'products.id', '=', 'policies.product_id')
                ->leftJoin('policy_members', 'policy_members.id', '=', 'policies.product_id')
                ->where('policies.customer_id', $id)
                ->select(
                    'customer_kyc.omang AS kycOmang',
                    'customer_kyc.driving_license AS kycLicense',
                    'customer_kyc.proof_residence AS kycProofResidence',
                    'customer_kyc.proof_income AS kycProofIncome',
                    'customer_kyc.passport AS kycPassport',
                    'policies.id',
                    'customer.firstName',
                    'customer.lastName',
                    'customer.cellphone',
                    'vehicle.vehiclePlate',
                    'vehicle.front',
                    'vehicle.back',
                    'vehicle.right',
                    'vehicle.left',
                    'customer_banking.prefered as billing',
                    'customer_banking.billingCell',
                    'customer_banking.billingStartDate',
                    'customer_banking.bankName',
                    'customer_banking.branchCode',
                    'customer_banking.accountNumber',
                    'policies.policyNumber',
                    'policies.status',
                    'policies.has_member',
                    'policies.has_vehicle',
                    'policies.premium',
                    'policies.trial_period',
                    'policies.has_vehicle',
                    'products.name AS productName'

                )
                ->get();
            if ($userPolicies != null) {
                return response()->json($userPolicies, 200);
            } else {
                return response()->json(['Unable to get Data'], 401);
            }
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage(), 500);
        }
    }

    public function passwordReset(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'cellphone' => 'required',
            'via' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 401);
        }
        //Get info from request
        $input = $request->all();

        //find user with specific cellphone
        $user = User::where('cellphone', $input['cellphone'])->first();

        //no email found,
        if (!$user) {

            return response()->json(['description' => 'User Account doesnt exist', 'code' => 403], 403);
        } else {

            //OTP sent by via email or sms.

            if ($request->via == 'sms') {

                $userObj = User::where('cellphone', $input['cellphone'])->first();
                $userOtpdelete = OTP::where('user_id', $userObj->id)->delete();

                $userCreateOtp = User::where('cellphone', $input['cellphone'])->first()->saveOTP($userObj);

                $userOtp = OTP::where('user_id', $userObj->id)->first();
                //return response()->json($userOtp);
                //$sendOtp = InfobipSms::send('+267' . $userObj->cellphone, 'Dumelang ' . $userObj->firstName . ', to reset your password , please enter the following OTP : ' . $userOtp->OTP);
                $sendOtp = event(new \AlphaDirect\Events\SendSms('+267' . $userObj->cellphone, 'Dumelang ' . $userObj->firstName . ', to reset your password , please enter the following OTP : ' . $userOtp->OTP));
                return response()->json(['description' => 'OTP - via text Successfully sent', 'id' => $userOtp, 'code' => 200], 200);
            } else {

                $userObj = User::where('email', $input['email'])->first();
                $userOtpdelete = Otp::where('user_id', $userObj->id)->delete();
                $user = User::where('email', $input['email'])->first()->sendOTP($request->via, $userObj->id);
                $userOtp = Otp::where('user_id', $userObj->id)->pluck('id')->last();

                return response()->json(['description' => 'OTP - via email Successfully sent', 'id' => $userOtp, 'code' => $this->successStatus], $this->successStatus);
            }
        }
    }

    public function setPassword(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'password' => 'required',
            'OtpID' => 'required',
            'OTP' => 'required',

        ]);

        if (empty($request->password)) {

            return response()->json(['error' => 'password is empty'], 400);
        }

        if (empty($request->OtpID)) {

            return response()->json(['error' => 'OtpID is empty'], 400);
        }

        if (empty($request->OTP)) {

            return response()->json(['error' => 'OTP is empty'], 400);
        }

        //Get the OTP given to a specific user
        $Otp_id = OTP::where('id', $request->OtpID)->first();

        if (!$Otp_id) {

            return response()->json(['OTP verification failed' => 'The OTP has expired'], 401);
        }

        //User has successfully entered the correct OTP and has reset their password
        if ($request->OtpID == $Otp_id->id && $request->OTP == $Otp_id->OTP) {
            $data = $request->all();
            $user = User::find($Otp_id->user_id);

            if (!Hash::check($data['oldPassword'], $user->password)) {

                return response()->json('error', 'The specified password does not match the database password');
            } else {
                // write code to update password
                $user = User::where('id', $Otp_id->user_id)->update(['password' => bcrypt($request->password)]);
                $userObj = User::where('id', $Otp_id->user_id)->first();
                $Otp_id = Otp::where('id', $request->OtpID)->first()->delete();

                return response()->json($userObj, 200);
            }
        }
        //handle when user enters the wrong OTP
        if ($request->OtpID == $Otp_id->id && $request->OTP != $Otp_id->OTP) {

            return response()->json(['OTP Verification failed' => 'You have not entered the correct OTP'], 401);
        } else if ($request->OtpID == $Otp_id->id) {

            return response()->json(['OTP Verification failed' => 'The OTP has expired'], 401);
        }
    }

    public function updatePassword(Request $request)
    {

        $data = $request->all();
        $loggedInUser = $data["userId"];
        $user = User::find($loggedInUser);

        if (!Hash::check($data['oldPassword'], $user->password)) {

            return response()->json('The specified password does not match your current password', 400);
        } else {
            // write code to update password
            $user = User::where('id', $loggedInUser)->update(['password' => bcrypt($request->newPassword)]);
            $userObj = User::where('id', $loggedInUser)->first();

            return response()->json($userObj, 200);
        }
    }

    public function getUserClaims(Request $request)
    {

        //$userClaims =  GlassClaim::where('user_id',$id)->orderBy('created_at','desc')->get();

        $userClaims = Claim::all();




        return response()->json($userClaims);
    }
    public function resetPasswordPage()
    {
        return view('auth.passwords.reset');
    }


    public function resetPassword(Request $request)
    {
        try {
            $user = new User();
            $user->password = Hash::make($request->password);

            Auth::logout();

            $user->save();
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json(['error' => $ex->getMessage(), 'line' => $ex->getLine()]);
        }
    }
}
