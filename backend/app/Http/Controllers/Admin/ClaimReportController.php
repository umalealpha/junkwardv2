<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Claim;
use AlphaDirect\Customer;
use AlphaDirect\Policy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
class ClaimReportController extends Controller
{
    /**
     * Show a list of all claims.
     *
     * @return View
     */
    public function index()
    {
        if (Auth::user()->hasPermissionTo('claim-report-list')) {
            $products = Product::get(array('id', 'name'));
            $claimStatus = Claim::groupBy('status')->get(array('status'));
            $claimTypes = Claim::groupBy('claim_type')->get(array('claim_type'));
            return view('admin.reports.claim', compact('claimStatus', 'claimTypes', 'products'));
        } else {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /*
     * Pass data through ajax call for export function
     */
    /**
     * @return mixed
     */
    public function allDataJson(Request $request)
    {
        $claims = Claim::get(array('id', 'policy_id', 'claim_number', 'customer_id', 'claim_type', 'status', 'created_at'));
        if ($request->claimProduct_filter != -1)
        {
            $claims =  DB::table('claims')
                ->leftJoin('policies', 'policies.id', '=' ,'claims.policy_id')
                ->where('policies.product_id',$request->claimProduct_filter)->get(array('claims.id as id', 'policies.id as policy_id', 'claims.claim_number as claim_number', 'policies.customer_id as customer_id', 'claims.claim_type as claim_type', 'claims.status as status', 'claims.created_at as created_at'));
        }
        if ($request->claimStatus_filter != -1 && $request->claimType_filter == -1 )
        {
            $claims = Claim::where('status', $request->claimStatus_filter)->get(array('id', 'policy_id', 'claim_number', 'customer_id', 'claim_type', 'status', 'created_at'));
        }
        if ($request->claimType_filter != -1 && $request->claimStatus_filter == -1)
        {
            $claims = Claim::where('claim_type', $request->claimType_filter)->get(array('id', 'policy_id', 'claim_number', 'claim_type', 'customer_id', 'status', 'created_at'));
        }
        if ($request->claimStatus_filter != -1 && $request->claimType_filter != -1)
        {
            $claims = Claim::where('status', $request->claimStatus_filter)->where('claim_type', $request->claimType_filter)->get(array('id', 'policy_id', 'claim_number', 'customer_id', 'claim_type', 'status', 'created_at'));
        }
        return DataTables::of($claims)
            ->editColumn('name', function ($claims)
            {
                $customerName = Customer::where('id', $claims->customer_id)->first(array('firstName', 'lastName'));
                if (!empty($customerName))
                {
                    return $customerName->firstName . ' ' . $customerName->lastName;
                }

            })
            ->editColumn('policy_id', function ($claims)
            {
                $policyNumber = Policy::where('id', $claims->policy_id)->first(array('policyNumber'));
                if($policyNumber != null){
                    return $policyNumber->policyNumber;
                }
                else{
                    return '-';
                }

            })
            ->editColumn('claim_type', function ($claims)
            {
                if ($claims->claim_type == 'Accident')
                    return 'Motor Accident';
                else
                    return $claims->claim_type;
            })
            ->editColumn('created_at', function ($claims)
            {
                return $claims->created_at->diffForHumans();
            })
            ->editColumn('status', function ($claims)
            {
                if ($claims->status == 'Approved')
                    $return = 'Approved';
                elseif ($claims->status == 'Pending')
                    $return = 'Pending';
                else
                    $return = 'Rejected';
                return $return;
            })
            ->addColumn('cellphone', function ($claims)
            {
                $cellphone = Customer::where('id',$claims->customer_id)->first();
                if($cellphone != null){
                    return $cellphone->cellphone;
                }
                else
                {
                    return '-';
                }
            })
            ->rawColumns(['actions', 'status','cellphone','created_at'])
            ->make(false);
    }


    /*
     * Pass data through ajax call for datatable
     */
    /**
     * @return mixed
     */

        public function data(Request $request)
    {
        $claims = Claim::get(array('id', 'policy_id', 'claim_number', 'customer_id', 'claim_type', 'status', 'created_at'));
        if ($request->claimProduct_filter != -1)
        {
            $claims =  DB::table('claims')
            ->leftJoin('policies', 'policies.id', '=' ,'claims.policy_id')
            ->where('policies.product_id',$request->claimProduct_filter)
            ->get(array('claims.id as id', 'policies.id as policy_id', 'claims.claim_number as claim_number', 'policies.customer_id', 'claims.claim_type as claim_type', 'claims.status as status', 'claims.created_at as created_at'));

        }
        if ($request->claimStatus_filter != -1 && $request->claimType_filter == -1 )
        {
            $claims = Claim::where('status', $request->claimStatus_filter)->get(array('id', 'policy_id', 'claim_number', 'customer_id', 'claim_type', 'status', 'created_at'));
        }
        if ($request->claimType_filter != -1 && $request->claimStatus_filter == -1)
        {
            $claims = Claim::where('claim_type', $request->claimType_filter)->get(array('id', 'policy_id', 'claim_number', 'claim_type', 'customer_id', 'status', 'created_at'));
        }
        if ($request->claimStatus_filter != -1 && $request->claimType_filter != -1)
        {
            $claims = Claim::where('status', $request->claimStatus_filter)->where('claim_type', $request->claimType_filter)->get(array('id', 'policy_id', 'claim_number', 'customer_id', 'claim_type', 'status', 'created_at'));
        }
        return DataTables::of($claims)
            ->editColumn('name', function ($claims)
            {
                $customerName = Customer::where('id', $claims->customer_id)->first(array('firstName', 'lastName'));
                if ($customerName != null)
                {
                    if($customerName->firstName != null)
                        $firstName = $customerName->firstName;
                    else
                        $firstName = '(No first name) -';

                    if($customerName->lastName != null)
                        $lastName = $customerName->lastName;
                    else
                        $lastName = '- (No last name)';

                    return $firstName.' '.$lastName;
                }
                else{
                    return '-';
                }

            })
            ->editColumn('policy_id', function ($claims)
            {
                $policyNumber = Policy::where('id', $claims->policy_id)->first(array('policyNumber'));
                if($policyNumber != null){
                    if($policyNumber->policyNumber != null){
                        return $policyNumber->policyNumber;
                    }
                    else{
                        return '-';
                    }
                }
                else{
                    return '-';
                }
            })
            ->editColumn('claim_type', function ($claims)
            {
                if ($claims->claim_type == 'Accident')
                    return 'Motor Accident';
                else
                    return $claims->claim_type;
            })
            ->editColumn('created_at', function ($claims)
            {
                return Carbon::parse($claims->created_at)->diffForHumans();
            })
            ->editColumn('status', function ($claims)
            {
                if ($claims->status == 'Approved')
                    $return = '<span class="kt-font-bold kt-font-accent">Approved</span>';
                elseif ($claims->status == 'Pending')
                    $return = '<span class="kt-font-bold kt-font-primary">Pending</span>';
                else
                    $return = '<span class="kt-font-bold kt-font-danger">Rejected</span>';
                return $return;
            })
            ->addColumn('cellphone', function ($claims)
            {
                $cellphone = Customer::where('id',$claims->customer_id)->first();
                if($cellphone != null){
                    return $cellphone->cellphone;
                }
                else
                {
                    return '-';
                }
            })
            ->rawColumns(['actions', 'status','cellphone','created_at'])
            ->make(true);
    }
}
