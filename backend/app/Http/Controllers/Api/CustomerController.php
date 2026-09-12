<?php

namespace AlphaDirect\Http\Controllers\Api;

use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\User;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;

// method to retrieve customer information 
// param: auth_key
// param: customer ID
// return JSON
class CustomerController extends Controller
{
    public function customerDetails(Request $request)
    {
        if ($request->header('Authorization')) {
            $user = User::where('auth_key', $request->header('Authorization'))->first();
            if ($user) {
                $check = Customer::where('id', $request->id)->first(['id']);
                if ($check != null) {
                    $customerDetails = Customer::join('customer_profile', 'customer_profile.customer_id', '=', 'customer.id')
                        ->where('customer.id', $check->id)
                        ->first(['customer.id', 'customer.firstName', 'customer.lastName', 'customer.cellphone', 'customer.email', 'customer_profile.dob', 'customer_profile.gender', 'customer_profile.address', 'customer_profile.city']);
                    if ($customerDetails['gender'] == 1) {
                        $customerDetails['gender'] = "Male";
                    } else {
                        $customerDetails['gender'] = "Female";
                    }
                    return response()->json(['success' => true, 'customerDetails' => $customerDetails]);
                } else {
                    return response()->json(['error' => 'No Customer found with this ID.'], 401);
                }
            } else {
                return response()->json(['error' => 'Authentication key not found.'], 401);
            }
        } else {
            return response()->json(['error' => 'Please provide auth_key in header parameter'], 401);
        }
    }

    public function allCustomers(Request $request)
    {
        if ($request->header('Authorization')) {
            $user = User::where('auth_key', $request->header('Authorization'))->first();
            if ($user) {
                $check = Customer::join('customer_profile', 'customer_profile.customer_id', '=', 'customer.id')->get(['customer.id', 'customer.firstName', 'customer.lastName as lname', 'customer.cellphone', 'customer.email', 'customer_profile.dob', 'customer_profile.gender', 'customer_profile.address', 'customer_profile.city']);
                if ($check) {
                    foreach ($check as $c) {

                        if ($c['gender'] == 1) {
                            $c['gender'] = "Male";
                        } elseif ($c['gender'] == 0) {
                            $c['gender'] = "Female";
                        } else {
                            $c['gender'] = "NA";
                        }

                        if ($c['email'] == null) {
                            $c['email'] = "NA";
                        }
                        if ($c['cellphone'] == null) {
                            $c['cellphone'] = "false";
                        }
                    }
                    return response()->json(['success' => true, 'result' => $check], 200);
                } else {
                    return response()->json(['error' => 'No customer found.'], 401);
                }
            } else {
                return response()->json(['error' => 'Authentication key not found.'], 401);
            }
        } else {
            return response()->json(['error' => 'Please provide auth_key in header parameter'], 401);
        }
    }
}
