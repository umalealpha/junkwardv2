<?php

namespace AlphaDirect\Http\Controllers\Customer\Profile;

use AlphaDirect\Notifications;
use AlphaDirect\Policy;
use AlphaDirect\PolicyPlan;
use AlphaDirect\Role;
use AlphaDirect\User;
use AlphaDirect\KYC;
use AlphaDirect\Vehicle;
use AlphaDirect\CustomerBanking;

use DB;

use Illuminate\Http\Request;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Session;
use Redirect;
use Carbon;

use AlphaDirect\Http\Controllers\Controller;

class  CustomerController extends Controller
{

    // method to view dashboard
    // return view: Dashboard
    public function viewMyDashboard(){

        return view('CustomerPortal/Customer/myDashboard');
    }

    // method to view customer specific policies
    // return view: policies list
    public function viewMyPolicies(){

        return view('CustomerPortal/Policy/myPolicies');
    }

    // method to view customer account
    // return view: customer specific account page
    public function viewMyAccount(){

        return view('CustomerPortal/ManageAccount/myAccount');
    }

    // method to view customer claims
    // return view: customer specific claim list/s
    public function viewMyClaims(){

        return view('CustomerPortal/Claims/myClaims');
    }



}