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
use Session;


class CustomerManagement extends Controller
{

    //view customer details

    public function viewCustomerDetails($id)
    {
        $userDetails = User::find($id);
        $policies = Policy::where('user_id', $id)->first();
        $policyList = Policy::where('user_id', $id)->get();
        $customerKYC = KYC::where('user_id', $id)->first();
        $customerBanking = CustomerBanking::where('policy_id', $policies->id)->first();
        return view('Admin/Customers/customerDetails', compact('policies', 'policyList', 'customerBanking', 'userDetails', 'customerKYC'));
    }

    //view customer details

    public function editCustomerDetails($id)
    {
        $userDetails = User::find($id);
        $policies = Policy::where('user_id', $id)->first();

        $policyList = Policy::where('user_id', $id)->get();
        $customerKYC = KYC::where('user_id', $id)->first();
        $customerBanking = CustomerBanking::where('policy_id', $policies->id)->first();
        return view('CustomerStaffViews/editCustomerDetails', compact('policies', 'policyList', 'customerBanking', 'userDetails', 'customerKYC'));
    }

    // update customer information
    // param: customer id
    // customer list page
    public function saveCustomerEdits(Request $request)
    {



        DB::table('users')
            ->where('id', $request->user_id)
            ->update([
                'firstName' => $request->fname,
                'lastName' => $request->lname,
                'cellphone' => $request->cellphone,
                'address' => $request->address,
                'email' => $request->email,
                'omang' => $request->omang,
            ]);


        Session::flash('updatedCustomer', 'Update successfull');
        return redirect()->back();
    }




    //Get all customers with role.
    public function getAllCustomers()
    {


        $customers =  DB::table('users')
            ->leftJoin('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->where('role_id', 5)
            ->get();

        return response()->json($customers);
    }
}
