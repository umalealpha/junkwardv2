<?php

namespace AlphaDirect\Http\Controllers\Customer\Microinsurance;

use AlphaDirect\Notifications;
use AlphaDirect\Policy;
use AlphaDirect\PolicyPlan;
use AlphaDirect\Role;
use AlphaDirect\User;
use AlphaDirect\KYC;
use AlphaDirect\Vehicle;
use AlphaDirect\CustomerBanking;

use Illuminate\Support\Facades\DB;

use Illuminate\Http\Request;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Illuminate\Support\Facades\Storage;

use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Carbon;;
use Illuminate\Support\Facades\Auth;

use AlphaDirect\Http\Controllers\Controller;

class ScratchController extends Controller
{

    // method to change policy status to ACTIVATE
    // return JSON
    public function activatePolicy(Request $request)
    {
        $staffMembers =  DB::table('users')
            ->leftJoin('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->where('role_id', 3)
            ->inRandomOrder()
            ->first();

        $policyPlan = PolicyPlan::where('premium', $request->policyPlan)->first();

        if (Vehicle::where('vehiclePlate', $request->license)->exists()) {

            Session::flash('userAccountExists', 'Vehicle already has an active policy');

            return response()->json('Vehicle with this registration number has an active policy.', 401);
        }

        $year = Carbon::now();

        //save user policy
        $policy = new Policy;
        $policy->agent_id = $staffMembers->id;
        $policy->user_id = $request->userId;
        $policy->scratchCode = $request->activationCode;
        $policy->plan_id = $policyPlan->id;
        $policy->leadSource = 'Mobile App';
        $policy->policyType = 'MIS';
        $policy->policyNumber = 'MIS' . $year->format('Y') . str_pad((string) $policy->getNextId() + 100, 6, "0", STR_PAD_LEFT);
        $policy->save();

        //customer banking associate policy to bank details
        $customerBanking = new CustomerBanking;
        $customerBanking->user_id = $request->userId;
        $customerBanking->prefered = $request->billing;
        $customerBanking->myzaka = $request->myzaka;
        $customerBanking->billingCell = $request->billingCell;
        $customerBanking->orangeMoney = $request->orangeMoney;
        $customerBanking->billingStartDate = Carbon::now()->addDay(90)->toDateString();
        $customerBanking->bankName = $request->bankName;
        $customerBanking->accountNumber = $request->accountNumber;
        $customerBanking->branchCode = $request->branchCode;
        $customerBanking->Policy()->associate($policy);
        $customerBanking->save();


        $vehicleDetails = new Vehicle;
        $vehicleDetails->user_id = $request->userId;
        $vehicleDetails->vehiclePlate = trim(strtoupper($request->license));
        $vehicleDetails->make = $request->make;
        $vehicleDetails->model = $request->model;
        $vehicleDetails->front = $request->front;
        $vehicleDetails->back = $request->back;
        $vehicleDetails->left = $request->left;
        $vehicleDetails->right = $request->right;
        $vehicleDetails->Policy()->associate($policy);
        $vehicleDetails->save();

        $userDetails = User::where('id', $request->userId)->first();

        $agentNotifications = new Notifications;
        $agentNotifications->user_id = 3;
        $agentNotifications->type = 'Scratch Code Activated';
        $agentNotifications->data = 'You have been assigned a new policy. Policy Number: ' . 'MIS-2019-' . $policy->policyNumber;
        $agentNotifications->action = 'viewPolicy/' . $policy->id;
        $agentNotifications->save();

        //$response = InfobipSms::send('+267' . $userDetails->cellphone, 'Dumelang ' . $userDetails->firstName . ', Alpha Direct has received your activation. An Agent will contact you shortly. Your policy number ' . 'MIS-2019-' . $policy->policyNumber . ' KYC:incomplete');
        $response = event(new \AlphaDirect\Events\SendSms('+267' . $userDetails->cellphone, 'Dumelang ' . $userDetails->firstName . ', Alpha Direct has received your activation. An Agent will contact you shortly. Your policy number ' . 'MIS-2019-' . $policy->policyNumber . ' KYC:incomplete'));
        Session::flash('policySaved', 'Policy saved and assigned');
        return response()->json(['message' => 'policy activated', 'policyNumber' => $policy->policyNumber], 200);
    }


    public function agentActivatePolicy(Request $request)
    {
        $policyPlan = PolicyPlan::where('premium', $request->policyPlan)->first();
        if (User::where('cellphone', $request->cellphone)->exists()) {
            Session::flash('userAccountExists', 'User account already exists ');
            return redirect()->back()->withInput($request->input());
        }

        //attach customer role to user
        $customerRole = Role::where('name', 'Customer')->first();


        //save user
        $user = new User;
        $user->firstName = $request->fname;
        $user->lastName = $request->lname;
        $user->cellphone = $request->cellphone;
        $user->address = $request->address;
        $user->omang = $request->omang;
        $user->passport = $request->passport;
        $user->idType = $request->idType;
        $user->email = $request->email;
        $user->dob = $request->dob;
        $user->password = bcrypt($request->password);
        $user->save();
        $user->roles()->attach($customerRole);

        //associate KYC with policy activation
        $kyc = new KYC;
        $kyc->driversLicense = null;
        $kyc->omang = null;
        $kyc->proofResidence = null;
        $kyc->proofIncome = null;
        $kyc->User()->associate($user);
        $kyc->save();

        //save user policy
        $policy = new Policy;
        $policy->agent_id = Auth::user()->id;
        $policy->plan_id = $policyPlan->id;
        $policy->policyNumber = rand(500000, 600000);
        $policy->leadSource = 'Web';
        $policy->User()->associate($user);
        $policy->save();

        //Billing start date
        $startDate =  Carbon::now()->addDay(90)->toDateString();

        //customer banking associate policy to bank details
        $customerBanking = new CustomerBanking;
        $customerBanking->prefered = $request->billing;
        $customerBanking->myzaka = $request->myzaka;
        $customerBanking->billingCell = $request->billingCell;
        $customerBanking->orangeMoney = $request->orangeMoney;
        $customerBanking->billingStartDate = $startDate->format('d/m/Y');
        $customerBanking->bankName = $request->bankName;
        $customerBanking->accountNumber = $request->accountNumber;
        $customerBanking->accountType = $request->bankAccountType;
        $customerBanking->branchCode = $request->branchCode;
        $customerBanking->User()->associate($user);
        $customerBanking->Policy()->associate($policy);
        $customerBanking->save();


        $vehicleDetails = new Vehicle;
        $vehicleDetails->vehiclePlate = trim(strtoupper($request->license));
        $vehicleDetails->make = $request->make;
        $vehicleDetails->model = $request->model;
        $vehicleDetails->year = $request->year;
        $vehicleDetails->front = base64_encode(file_get_contents($request->file('front')));
        $vehicleDetails->back = base64_encode(file_get_contents($request->file('back')));
        $vehicleDetails->left = base64_encode(file_get_contents($request->file('left')));
        $vehicleDetails->right = base64_encode(file_get_contents($request->file('right')));
        $vehicleDetails->User()->associate($user);
        $vehicleDetails->Policy()->associate($policy);
        $vehicleDetails->save();

        $agentNotifications = new Notifications;
        $agentNotifications->user_id = Auth::user()->id;
        $agentNotifications->type = 'Scratch Code Activated';
        $agentNotifications->data = 'You have created a new policy. Policy Number: ' . 'MIS-2019-' . $policy->policyNumber;
        $agentNotifications->action = 'viewPolicy/' . $policy->id;
        $agentNotifications->save();

       // $response = InfobipSms::send('+267' . $user->cellphone, 'Dumelang ' . $user->firstName . ', Alpha Direct has received your activation. An Agent will contact you shortly. Your policy number ' . 'MIS-2019-' . $policy->policyNumber . ' KYC:incomplete');
        $response = event(new \AlphaDirect\Events\SendSms('+267' . $user->cellphone, 'Dumelang ' . $user->firstName . ', Alpha Direct has received your activation. An Agent will contact you shortly. Your policy number ' . 'MIS-2019-' . $policy->policyNumber . ' KYC:incomplete'));
        //Dumelang Tumelano , Alpha Direct has received your activation, an Agent will contact you shortly. Your policy number : MIS2019520321. KYC: incomplete
        Session::flash('policySaved', 'Policy saved and assigned');
        return redirect()->route('agent-addPolicy');
    }


    public function adminActivatePolicy(Request $request)
    {
        $staffMembers =  DB::table('users')
            ->leftJoin('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->where('role_id', 3)
            ->inRandomOrder()
            ->first();

        $policyPlan = PolicyPlan::where('premium', $request->policyPlan)->first();

        if (User::where('cellphone', $request->cellphone)->exists()) {

            Session::flash('userAccountExists', 'User account already exists ');

            return redirect()->back()->withInput($request->input());
        }

        if (Vehicle::where('vehiclePlate', $request->license)->exists()) {

            Session::flash('userAccountExists', 'Vehicle already has an active policy');

            return redirect()->back()->withInput($request->input());
        }

        //attach customer role to user
        $customerRole = Role::where('name', 'Customer')->first();

        //GET YEAR

        $year = Carbon::now();

        //random string for password

        $temp_password = Helper::gen_ustring(0,8); //str_random(8)

        //save user
        $user = new User;
        $user->firstName = $request->fname;
        $user->lastName = $request->lname;
        $user->cellphone = $request->cellphone;
        $user->address = $request->address;
        $user->omang = $request->omang;
        $user->passport = $request->passport;
        $user->idType = $request->idType;
        $user->email = $request->email;
        $user->dob = $request->dob;
        $user->password = bcrypt($temp_password);
        $user->save();
        $user->roles()->attach($customerRole);

        //associate KYC with policy activation
        $kyc = new KYC;
        $kyc->driversLicense = null;
        $kyc->omang = null;
        $kyc->proofResidence = null;
        $kyc->proofIncome = null;
        $kyc->User()->associate($user);
        $kyc->save();


        //save user policy
        $policy = new Policy;
        $policy->agent_id = $staffMembers->id;
        $policy->plan_id = $policyPlan->id;
        $policy->policyType = 'MIS';
        $policy->policyNumber = 'MIS' . $year->format('Y') . str_pad((string) $policy->getNextId() + 100, 6, "0", STR_PAD_LEFT);
        $policy->leadSource = 'Web';
        $policy->User()->associate($user);
        $policy->save();



        $startDate =  Carbon::now()->addDay(90)->toDateString();

        //customer banking associate policy to bank details
        $customerBanking = new CustomerBanking;
        $customerBanking->prefered = $request->billing;
        $customerBanking->myzaka = $request->myzaka;
        $customerBanking->billingCell = $request->billingCell;
        $customerBanking->orangeMoney = $request->orangeMoney;
        $customerBanking->billingStartDate = $startDate;
        $customerBanking->bankName = $request->bankName;
        $customerBanking->accountNumber = $request->accountNumber;
        $customerBanking->accountType = $request->bankAccountType;
        $customerBanking->branchCode = $request->branchCode;
        $customerBanking->User()->associate($user);
        $customerBanking->Policy()->associate($policy);
        $customerBanking->save();


        //Optional image uploading
        $frontImage = $request->file('front');
        $backImage = $request->file('back');
        $rightImage = $request->file('right');
        $leftImage = $request->file('left');


        $vehicleDetails = new Vehicle;
        $vehicleDetails->vehiclePlate = trim(strtoupper($request->license));
        $vehicleDetails->make = $request->make;
        $vehicleDetails->model = $request->model;
        $vehicleDetails->year = $request->year;
        $vehicleDetails->condition = $request->condition;
        $vehicleDetails->odometer = $request->odometer;

        if ($frontImage != null) {
            if ($request->hasFile('front')) {
                $file = $request->file('front');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $user->id . '/' . 'Vehicle' . '/' . trim(strtoupper($request->license)) . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $vehicleDetails->front = $filePath;
            }
        } else {
            $vehicleDetails->front = null;
        }

        if ($backImage != null) {
            if ($request->hasFile('back')) {
                $file = $request->file('back');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $user->id . '/' . 'Vehicle' . '/' . trim(strtoupper($request->license)) . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $vehicleDetails->back = $filePath;
            }
        } else {

            $vehicleDetails->back = null;
        }

        if ($leftImage != null) {
            if ($request->hasFile('left')) {
                $file = $request->file('left');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $user->id . '/' . 'Vehicle' . '/' . trim(strtoupper($request->license)) . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $vehicleDetails->left = $filePath;
            }
        } else {
            $vehicleDetails->left = null;
        }

        if ($rightImage != null) {
            if ($request->hasFile('right')) {
                $file = $request->file('right');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $user->id . '/' . 'Vehicle' . '/' . trim(strtoupper($request->license)) . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $vehicleDetails->right = $filePath;
            }
        } else {
            $vehicleDetails->right = null;
        }
        $vehicleDetails->User()->associate($user);
        $vehicleDetails->Policy()->associate($policy);
        $vehicleDetails->save();

        $agentNotifications = new Notifications;
        $agentNotifications->user_id = Auth::user()->id;
        $agentNotifications->type = 'Scratch Code Activated';
        $agentNotifications->data = 'You have been assigned a new policy. Policy Number: ' . 'MIS-2019-' . $policy->policyNumber;
        $agentNotifications->action = 'viewPolicy/' . $policy->id;
        $agentNotifications->save();

        //$response = InfobipSms::send('+267' . $user->cellphone, 'Dumelang ' . $user->firstName . 'Your temporary password is: ' . $temp_password);
        $response = event(new \AlphaDirect\Events\SendSms('+267' . $user->cellphone, 'Dumelang ' . $user->firstName . 'Your temporary password is: ' . $temp_password));
        //$sendPolicy = InfobipSms::send('+267' . $user->cellphone, 'Dumelang ' . $user->firstName . ', Alpha Direct has received your activation. An Agent will contact you shortly. Your policy number ' . 'MIS-2019-' . $policy->policyNumber . ' KYC:incomplete');
        $sendPolicy = event(new \AlphaDirect\Events\SendSms('+267' . $user->cellphone, 'Dumelang ' . $user->firstName . ', Alpha Direct has received your activation. An Agent will contact you shortly. Your policy number ' . 'MIS-2019-' . $policy->policyNumber . ' KYC:incomplete'));
        $loggedInUser = Auth::user()->id;

        activity()
            ->performedOn($policy)
            ->causedBy($loggedInUser)

            ->log('Created Policy and new Account for vehicle registration: ' . strtoupper($request->license));

        //Dumelang Tumelano , Alpha Direct has received your activation, an Agent will contact you shortly. Your policy number : MIS2019520321. KYC: incomplete
        Session::flash('policySaved', 'Policy saved and assigned');
        return redirect('admin/viewPolicy' . '/' . $policy->id);
    }

    public function registerUser(Request $request)
    {
        $year = Carbon::now();

        //attach a agent role
        $staffMembers =  DB::table('users')
            ->leftJoin('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->where('role_id', 3)
            ->inRandomOrder()
            ->first();
        $policyPlan = PolicyPlan::where('premium', $request->policyPlan)->first();

        if (User::where('cellphone', $request->cellphone)->exists()) {
            return response()->json('User account already exists.', 401);
        }
        //attach customer role to user
        $customerRole = Role::where('name', 'Customer')->first();

        //save user
        $user = new User;
        $user->firstName = $request->fname;
        $user->lastName = $request->lname;
        $user->cellphone = $request->cellphone;
        $user->email = $request->email;
        $user->omang = $request->id;
        $user->idType = $request->idType;
        $user->email = $request->email;
        $user->dob = $request->dob;
        $user->password = bcrypt($request->password);
        $user->save();
        $user->roles()->attach($customerRole);

        //associate KYC with policy activation
        $kyc = new KYC;
        $kyc->driversLicense = null;
        $kyc->omang = null;
        $kyc->proofResidence = null;
        $kyc->proofIncome = null;
        $kyc->User()->associate($user);
        $kyc->save();


        //save user policy
        $policy = new Policy;
        $policy->agent_id = $staffMembers->id;
        $policy->scratchCode = $request->activationCode;
        $policy->plan_id = $policyPlan->id;
        $policy->policyType = 'MIS';
        $policy->policyNumber = 'MIS' . $year->format('Y') . str_pad((string) $policy->getNextId() + 100, 6, "0", STR_PAD_LEFT);
        $policy->leadSource = 'Mobile App';
        $policy->User()->associate($user);
        $policy->save();

        $replacedSeconds = Carbon::now()->addDay(90)->toDateString();
        $startDate = str_replace(' 00:00:00', ' 00:00', $replacedSeconds);


        //customer banking associate policy to bank details
        $customerBanking = new CustomerBanking;
        $customerBanking->prefered = $request->billing;
        $customerBanking->billingCell = $request->billingCell;
        $customerBanking->billingStartDate = $startDate;
        $customerBanking->bankName = $request->bankName;
        $customerBanking->accountNumber = $request->accountNumber;
        $customerBanking->branchCode = $request->branchCode;
        $customerBanking->User()->associate($user);
        $customerBanking->Policy()->associate($policy);
        $customerBanking->save();

        $vehicleDetails = new Vehicle;
        $vehicleDetails->vehiclePlate = trim(strtoupper($request->license));
        $vehicleDetails->make = $request->make;
        $vehicleDetails->model = $request->model;
        $vehicleDetails->front = $request->front;
        $vehicleDetails->back = $request->back;
        $vehicleDetails->left = $request->left;
        $vehicleDetails->right = $request->right;
        $vehicleDetails->vehicleRegistration = null;
        $vehicleDetails->User()->associate($user);
        $vehicleDetails->Policy()->associate($policy);
        $vehicleDetails->save();

        $agentNotifications = new Notifications;
        $agentNotifications->user_id = 3;
        $agentNotifications->type = 'Scratch Code Activated';
        $agentNotifications->data = 'You have been assigned a new policy. Policy Number: ' . 'MIS-2019-' . $policy->policyNumber;
        $agentNotifications->action = 'viewPolicy/' . $policy->id;
        $agentNotifications->save();
        $response = event(new \AlphaDirect\Events\SendSms('+267' . $user->cellphone, 'Dumelang ' . $user->firstName . ', Alpha Direct has received your activation. An Agent will contact you shortly. Your policy number : ' . $policy->policyNumber . ' KYC:incomplete'));
        //$response = InfobipSms::send('+267' . $user->cellphone, 'Dumelang ' . $user->firstName . ', Alpha Direct has received your activation. An Agent will contact you shortly. Your policy number : ' . $policy->policyNumber . ' KYC:incomplete');
        //Dumelang Tumelano , Alpha Direct has received your activation, an Agent will contact you shortly. Your policy number : MIS2019520321. KYC: incomplete

        Session::flash('policySaved', 'Policy saved and assigned');
        return response()->json(['message' => 'policy activated', 'policyNumber' => $policy->policyNumber], 200);
        //return Redirect::back();
    }

    public function createAccount(Request $request)
    {
        $year = Carbon::now();

        //attach a agent role
        $staffMembers =  DB::table('users')
            ->leftJoin('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->where('role_id', 3)
            ->inRandomOrder()
            ->first();
        $policyPlan = PolicyPlan::where('premium', $request->policyPlan)->first();

        if (User::where('cellphone', $request->cellphone)->exists()) {
            return response()->json('User account already exists.', 401);
        }
        //attach customer role to user
        $customerRole = Role::where('name', 'Customer')->first();

        //random string for password

        $temp_password = str_random(8);
        $urlValue = \Config::get('values.graphite_url');

        //save user
        $user = new User;
        $user->firstName = $request->fname;
        $user->lastName = $request->lname;
        $user->cellphone = $request->cellphone;
        $user->email = $request->email;
        $user->omang = $request->omang;
        $user->passport = $request->passport;
        $user->idType = $request->idType;
        $user->email = $request->email;
        $user->dob = $request->dob;
        $user->password = bcrypt($temp_password);
        $user->save();
        $user->roles()->attach($customerRole);

        //associate KYC with policy activation
        $kyc = new KYC;
        $kyc->driversLicense = null;
        $kyc->omang = null;
        $kyc->proofResidence = null;
        $kyc->proofIncome = null;
        $kyc->User()->associate($user);
        $kyc->save();


        //save user policy
        $policy = new Policy;
        $policy->agent_id = $staffMembers->id;
        $policy->scratchCode = $request->activationCode;
        $policy->plan_id = $policyPlan->id;
        $policy->policyType = 'MIS';
        $policy->policyNumber = 'MIS' . $year->format('Y') . str_pad((string) $policy->getNextId() + 100, 6, "0", STR_PAD_LEFT);
        $policy->leadSource = 'Web';
        $policy->User()->associate($user);
        $policy->save();

        $replacedSeconds = Carbon::now()->addDay(90)->toDateString();
        $startDate = str_replace(' 00:00:00', ' 00:00', $replacedSeconds);


        //customer banking associate policy to bank details
        $customerBanking = new CustomerBanking;
        $customerBanking->prefered = $request->billing;
        $customerBanking->billingCell = $request->billingCell;
        $customerBanking->billingStartDate = $startDate;
        $customerBanking->bankName = $request->bankName;
        $customerBanking->accountNumber = $request->accountNumber;
        $customerBanking->branchCode = $request->branchCode;
        $customerBanking->User()->associate($user);
        $customerBanking->Policy()->associate($policy);
        $customerBanking->save();

        $vehicleDetails = new Vehicle;
        $vehicleDetails->vehiclePlate = trim(strtoupper($request->license));
        $vehicleDetails->make = $request->make;
        $vehicleDetails->model = $request->model;
        $vehicleDetails->front = $request->front;
        $vehicleDetails->back = $request->back;
        $vehicleDetails->left = $request->left;
        $vehicleDetails->right = $request->right;
        $vehicleDetails->vehicleRegistration = null;
        $vehicleDetails->User()->associate($user);
        $vehicleDetails->Policy()->associate($policy);
        $vehicleDetails->save();

        $agentNotifications = new Notifications;
        $agentNotifications->user_id = 3;
        $agentNotifications->type = 'Scratch Code Activated';
        $agentNotifications->data = 'You have been assigned a new policy. Policy Number: ' . 'MIS-2019-' . $policy->policyNumber;
        $agentNotifications->action = 'viewPolicy/' . $policy->id;
        $agentNotifications->save();
        //Dumelang Tumelano , Alpha Direct has received your activation, an Agent will contact you shortly. Your policy number : MIS2019520321. KYC: incomplete

        //$response = InfobipSms::send('+267' . $user->cellphone, 'Dumelang ' . $user->firstName . 'Your temporary password is: ' . $temp_password . 'To login go to our website : ' . $surlValue . 'customers/Login');
        $response = event(new \AlphaDirect\Events\SendSms('+267' . $user->cellphone, 'Dumelang ' . $user->firstName . 'Your temporary password is: ' . $temp_password . 'To login go to our website : ' . $surlValue . 'customers/Login'));
        // $sendPolicy = InfobipSms::send('+267' . $user->cellphone, 'Dumelang ' . $user->firstName . ', Alpha Direct has received your activation. An Agent will contact you shortly. Your policy number ' . $policy->policyNumber . ' KYC:incomplete');
        $sendPolicy = event(new \AlphaDirect\Events\SendSms('+267' . $user->cellphone, 'Dumelang ' . $user->firstName . ', Alpha Direct has received your activation. An Agent will contact you shortly. Your policy number ' . $policy->policyNumber . ' KYC:incomplete'));
        Session::flash('policySaved', 'Policy saved and assigned');

        return response()->json(['message' => 'policy activated', 'policyNumber' => $policy->policyNumber], 200);
        //return Redirect::back();
    }

    public function updatePolicy(Request $request)
    {

        $policy = Policy::find($request->id);

        $policy->firstName = $request->firstName;
        $policy->lastName = $request->lastName;
        $policy->cellphone = $request->cellphone;
        $policy->dob = $request->dob;
        $policy->licensePlate = $request->licensePlate;
        $policy->omang = $request->omang;
        //$policy->scratchCode = Input::get('scratchCode');
        $policy->billing = $request->billing;
        $policy->billingCell = $request->billingCell;
        $policy->bankName = $request->bankName;
        $policy->accountNumber = $request->accountNumber;
        //$policy->policyNumber = rand(500000, 600000);

        $policy->save();

        // redirect
        Session::flash('message', 'Successfully updated policy details.', 5000);
        Redirect::back();
        return response()->json(['message' => 'Successfully updated 1']);
    }

    public function sendCustomerSms($cellphone)
    {
        $response = event(new \AlphaDirect\Events\SendSms('+267' . $cellphone, 'This messages serves as a notice that we have recieved your request. We will contact you shortly'));
        //$response = InfobipSms::send('+267' . $cellphone, 'This messages serves as a notice that we have recieved your request. We will contact you shortly');
    }




    public function updatePolicyInfo(Request $request)
    {

        $userVehicle = Vehicle::where('vehiclePlate', $request->license)->first();

        return response()->json($userVehicle);
    }
}
