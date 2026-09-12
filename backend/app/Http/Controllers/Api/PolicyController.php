<?php

namespace AlphaDirect\Http\Controllers\Api;

use AlphaDirect\AccidentDriver;
use AlphaDirect\Accounts;
use AlphaDirect\Claim;
use AlphaDirect\ClaimVehicle;
use AlphaDirect\Http\Controllers\Admin\UserController;
use AlphaDirect\Ledger;
use AlphaDirect\Policy;
use AlphaDirect\User;
use AlphaDirect\Vehicle;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class PolicyController extends Controller
{
    // method to retrieve policy information
    // param: auth_key
    // param: policy number
    // return JSON
    public function getDetails(Request $request){
        if($request->header('Authorization')) {
            $user = User::where('auth_key', $request->auth_key)->first();
            if ($user) {
                $policyDetails = Policy::where('policyNumber', $request->policyNumber)->first(['id', 'sum_assured']);
                $cliamDetails = Claim::where('policy_id', $policyDetails->id)->get(['id as claim_id', 'policy_id']);
                $data = $cliamDetails->map(function ($item) {
                    $accident_driver = AccidentDriver::where('claim_id', $item->claim_id)->first(['dob']);
                    $vehicle = Vehicle::where('policy_id', $item->policy_id)->get(array('make', 'year', 'is_imported'));
                    $claim_vehicle = ClaimVehicle::where('claim_id', $item->claim_id)->get();
                    $item['accident_driver'] = $accident_driver;
                    $item['vehicle'] = $vehicle;
                    $item['claim_vehicle'] = $claim_vehicle;
                    return collect($item)->only(['claim_id', 'accident_driver', 'vehicle', 'claim_vehicle', 'claim_count']);
                });
                $data['policy_details'] = $policyDetails;
                $data['claim_count'] = Claim::where('policy_id', $policyDetails->id)->count();
                return response()->json(['policies' => $data]);
            }
            else{
                    return response()->json(['error' => 'Auth key not found.'], 401);
                }
            } else {
                return response()->json(['error' => 'Please provide authentication parameter in header'], 401);
            }
        }

    public function subLedgerEntries(Request $request){
        if($request->header('Authorization')){
            $user = User::where('auth_key',$request->header('Authorization'))->first();
            if($user){
                $entries = Ledger::join('accounts', 'accounts.id', '=', 'policy_ledger.account_id');

                if($request->ledger_id != '') {
                    $entries->where('policy_ledger.id' ,'>',$request->ledger_id);;
                }
                if($request->account_id != '') {
                    $entries->where('policy_ledger.account_id', $request->account_id);
                }
                if($request->odoo_status != '') {
                    $entries->where('policy_ledger.odoo_status', 'like', '%' . $request->odoo_status . '%' );
                }
                if ($request->filterDateFrom != '' && $request->filterDateto != '') {

                    $entries->whereBetween(DB::raw('date(policy_ledger.created_at)'), [Carbon::parse($request->filterDateFrom)->format('Y-m-d'), Carbon::parse($request->filterDateto)->format('Y-m-d')]);
                }

                $data = $entries->select('policy_ledger.id','policy_ledger.odoo_id','policy_ledger.customer_id','policy_ledger.trans_ref as policyNumber','policy_ledger.accounting_date','accounts.account_name','accounts.account_num','policy_ledger.credit','policy_ledger.debit','policy_ledger.odoo_status')->paginate(10);
                    return response()->json(['subLedger' => $data]);


            }else{
                return response()->json(['error'=>'User not found.'], 401);
            }
        }else{
            return response()->json(['error'=>'Please provide auth_key in header parameter'], 401);
        }

    }

    // method to retrieve sub ledger enttries forspecific policy
    // param: auth_key
    // param: policy number
    // return JSON
    public function subLedger(Request $request)
    {
        if($request->header('Authorization')){
            $user = User::where('auth_key',$request->header('Authorization'))->first();
            if($user){
                $policyDetails = Policy::where('policyNumber',$request->policyNumber)->first(['id']);
                if($policyDetails != null){
                    $subLedger = Ledger::join('accounts','accounts.id', '=', 'policy_ledger.account_id')
                        ->where('policy_id',$policyDetails->id)
                        ->get(['policy_ledger.trans_ref as policyNumber','policy_ledger.accounting_date','policy_ledger.account_name','accounts.account_num','policy_ledger.credit','policy_ledger.debit']);
                    return response()->json(['subLedger' => $subLedger]);
                }else{
                    return response()->json(['error'=>'No Policy found with this policy number.'], 401);
                }
            }else{
                return response()->json(['error'=>'Authentication key not found.'], 401);
            }
        }else{
            return response()->json(['error'=>'Please provide auth_key in header parameter'], 401);
        }
    }

    public function updateOdooId(Request $request)
    {
        if($request->header('Authorization')){
            $user = User::where('auth_key',$request->header('Authorization'))->first();
            if($user){
                $ledger = Ledger::where('id',$request->ledger_id)->first();
                if($ledger != null){
                        $ledger->odoo_Id = $request->odoo_id;
                        $ledger->odoo_status = "success";
                        $ledger->save();
                    return response()->json(['success'=>'Odoo ID And Status has been updated successfully.'], 200);
                }else{
                    return response()->json(['error'=>'No ledger entry found with this ID.'], 401);
                }
            }else{
                return response()->json(['error'=>'Authentication key not found.'], 401);
            }
        }else{
            return response()->json(['error'=>'Please provide auth_key in header parameter'], 401);
        }
    }

}
