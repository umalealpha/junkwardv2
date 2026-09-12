<?php

namespace AlphaDirect\Http\Controllers\Management;


use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;

use AlphaDirect\Policy;
use AlphaDirect\User;
use AlphaDirect\Treaty;
use AlphaDirect\PolicyPlan;
use AlphaDirect\KYC;
use AlphaDirect\Staff;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Vehicle;

use DB;
use Auth;
use Session;


class PolicyManagement extends Controller
{

    // updates policy details from edit page
    // param: policy ID
    // policy list page
    public function editPolicyDetails(Request $request){
    
        $policy = Policy::where('policyNumber',$request->policyNumber)->first();

        $vehicle = Vehicle::where('policy_id',$policy->id)->first();

        

        $policyPlan = PolicyPlan::where('premium',$request->policyPlan)->first();

        //return response()->json($policyPlan);

        DB::table('users')
        ->where('id',$policy->user_id)
        ->update(['firstName' =>$request->fname,
                  'lastName' =>$request->lname,
                  'cellphone' =>$request->cellphone,
                  'address' =>$request->address,
                  'email' =>$request->email,
                  'omang' =>$request->omang,
                  'passport' =>$request->passport,
                 ]);
    
        DB::table('policies')
                ->where('policyNumber',$request->policyNumber)
                ->update(['plan_id' =>$policyPlan->id,
                           ]);


        DB::table('vehicle')
        ->where('policy_id',$vehicle->policy_id)
        ->update(['make' =>$request->make,
                  'model' =>$request->model,
                  'year' =>$request->year,
                  'odometer' =>$request->odometer,
                  'condition' =>$request->condition,
                 ]);



        $loggedInUser = Auth::user()->id;


        activity()
                  ->performedOn($policy)
                  ->causedBy($loggedInUser)
               
                  ->log('Updated Policy details');

        Session::flash('policyUpdated','Policy successfully updated');

        return redirect()->back();
        
    }
    
}

