<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Activation;
use AlphaDirect\Branch;
use AlphaDirect\Customer;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\KYC;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\Transaction;
use AlphaDirect\User;
use AlphaDirect\VcsTransaction;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class PolicyReportController extends Controller
{

    public function index()
    {
        if (Auth::user()->hasPermissionTo('policy-report-list'))
        {
            $products = Product::with('type')->where('status', 1)
                ->get(array(
                    'id',
                    'product_type_id',
                    'name',
                    'is_motor_items',
                    'kyc_customer',
                    'has_vehicle',
                    'has_subApplicant'
                ));
            $productPlans = Productplan::where('status', 1)->get(array(
                'id',
                'name'
            ));
            $customerNumbers = Customer::where('id', '!=', null)->get();
            $policyAgents = Policy::with('user')->where('agent_id', '!=', 'null')
                ->groupBy('agent_id')
                ->get();

            //  return response($policyLeadAgent);
            return view('admin.reports.policyReport', compact('policy', 'products', 'productPlans', 'customerNumbers', 'policyAgents'));
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }

    }

    public function data(Request $request)
    {
        $query = Policy::orderBy('created_at', 'DESC');
        if ($request->product_plan != - 1)
        {
            $query->where('plan_id', $request->product_plan);
        }
        if ($request->product_filter != - 1)
        {
            $query->where('product_id', $request->product_filter);
        }
        if ($request->policyStatus_filter != - 1)
        {
            $query->where('status', $request->policyStatus_filter);
        }
        if ($request->policy_number != - 1)
        {
            $query->where('policyNumber', $request->policy_number);
        }
        if ($request->contact_number != - 1)
        {
            $customerId = Customer::where('cellphone', $request->contact_number)
                ->first('id');
            $query->where('customer_id', $customerId->id);
        }
        if ($request->premium_value != - 1)
        {
            $query->where('premium', $request->premium_value);
        }
        if ($request->customer_name != - 1)
        {
            $customer = Customer::where('firstName', $request->customer_name)
                ->orWhere('lastName', $request->customer_name)
                ->first(array(
                    'id',
                    'firstName',
                    'lastName'
                ));
            $query->where('customer_id', $customer->id);
        }
        if ($request->payment_status != - 1)
        {
            $paymentStatus = Transaction::where('status', 'like', '%' . $request->payment_status . '%')
                ->first();
            $query->where('customer_id', $paymentStatus->customer_id);
        }
        if ($request->filterDateFrom != '-1' && $request->filterDateto != '-1')
        {
            $query->whereBetween(DB::raw('date(created_at)') , [Carbon::parse($request->filterDateFrom)
                ->format('Y-m-d') , Carbon::parse($request->filterDateto)
                ->format('Y-m-d') ]);
        }

        $policy = $query->get(['id', 'customer_id', 'plan_id', 'product_id', 'has_vehicle', 'has_member', 'policyNumber', 'status', 'created_at']);

        /* if ($request->policyStatus_filter != -1 && $request->product_filter != -1 && $request->policy_number != -1) {
        $policy = Policy::where('status', $request->policyStatus_filter)->where('product_id', $request->product_filter)->where('plan_id', $request->product_plan)->get(['id', 'customer_id', 'product_id', 'has_vehicle', 'has_member', 'policyNumber', 'status', 'created_at']);
        }*/

        return DataTables::of($policy)->editColumn('created_at', function ($policy)
        {
            return $policy
                ->created_at
                ->diffForHumans();
        })->editColumn('status', function ($policy)
        {
            if ($policy->status == 1)
            {
                $return = '<span class="kt-font-bold kt-font-brand">Activated</span>';
            }
            elseif ($policy->status == 2)
            {
                $return = '<span class="kt-font-bold kt-font-danger">Cancel</span>';
            }
            else
            {
                $return = '<span class="kt-font-bold kt-font-focus">Deactivated</span>';
            }

            if ($policy->kyc && $policy
                    ->kyc->compliance == 1)
            {
                $return .= '<br><span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">KYC Compliant</span>';
            }
            else
            {
                $return .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">KYC Non-Compliant</span>';
            }

            return $return;
        })->addColumn('name', function ($policy)
        {
            if ($policy->customer != null)
            {
                if($policy->customer->firstName != null && $policy->customer->lastName != null){
                    return $policy
                            ->customer->firstName . ' ' . $policy->customer->lastName;
                }elseif($policy->customer->firstName == null && $policy->customer->lastName != null){
                    return 'N/A (first name)' . ' ' . $policy->customer->lastName;
                }elseif($policy->customer->firstName != null && $policy->customer->lastName == null){
                    return $policy->customer->firstName . ' ' . 'N/A';
                }else{
                    return 'N/A';
                }
            }

        })->addColumn('product_name', function ($policy)
        {
            if ($policy->product != null)
            {
                if($policy->product->name != null){
                    return $policy->product->name;
                }else{
                    return 'N/A';
                }
            }

        })

            ->rawColumns(['status'])
            ->make(true);
    }

    public function alltransactionData(Request $request)
    {
        $query = Policy::orderBy('created_at', 'DESC');
        if ($request->product_plan != - 1)
        {
            $query->where('plan_id', $request->product_plan);
        }
        if ($request->product_filter != - 1)
        {
            $query->where('product_id', $request->product_filter);
        }
        if ($request->policyStatus_filter != - 1)
        {
            $query->where('status', $request->policyStatus_filter);
        }
        if ($request->policy_number != - 1)
        {
            $query->where('policyNumber', $request->policy_number);
        }
        if ($request->policy_number != - 1)
        {
            $query->where('policyNumber', $request->policy_number);
        }
        if ($request->contact_number != - 1)
        {

            $customerId = Customer::where('cellphone', 'like', '%' . $request->contact_number . '%')
                ->first('id');

            $query->where('customer_id', $customerId->id);

        }
        if ($request->premium_value != - 1)
        {
            $query->where('premium', $request->premium_value);
        }
        if ($request->customer_name != - 1)
        {
            $customer = Customer::where('firstName', 'like', '%' . $request->customer_name . '%')
                ->orWhere('lastName', 'like', '%' . $request->customer_name . '%')
                ->pluck('id');
            $query->whereIn('customer_id', $customer);
        }
        if ($request->filterDateFrom != '-1' && $request->filterDateto != '-1')
        {
            $query->whereBetween(DB::raw('date(created_at)') , [Carbon::parse($request->filterDateFrom)
                ->format('Y-m-d') , Carbon::parse($request->filterDateto)
                ->format('Y-m-d') ]);
        }
        if ($request->agent_id != '-1')
        {
            $query->where('agent_id', $request->agent_id);
        }
        $policy = $query->get(['id', 'customer_id', 'plan_id', 'premium', 'product_id', 'activation_code', 'has_vehicle', 'has_member', 'policyNumber', 'agent_id', 'status', 'created_at']);

        if ($request->payment_status != - 1)
        {
            foreach ($policy as $key => $p) {
                $paymentStatus = VcsTransaction::where('policyNumber', $p->policyNumber)->orderBy('id', 'DESC')->first(array('status'));
                if($paymentStatus == NULL)
                {
                    $policy->forget($key);
                } elseif($paymentStatus->status != $request->payment_status)
                {
                    $policy->forget($key);
                }

            }
        }

        return DataTables::of($policy)->editColumn('created_at', function ($policy)
        {
            return $policy->created_at;
        })->editColumn('status', function ($policy)
        {
            if ($policy->status == 1)
            {
                $return = 'Activated';
            }
            elseif ($policy->status == 2)
            {
                $return = 'Cancel';
            }
            else
            {
                $return = 'Deactivated';
            }

            return $return;
        })->editColumn('agent_id', function ($policy)
        {
            if ($policy->agent_id != null)
            {
                $user = User::where('id', $policy->agent_id)
                    ->first(array(
                        'firstName',
                        'lastName'
                    ));
                if ($user != null)
                {
                    $return = $user->firstName . ' ' . $user->lastName;
                }
                else
                {
                    $return = 'N/A';
                }
            }
            else
            {
                $return = 'N/A';
            }

            return $return;
        })->addColumn('name', function ($policy)
        {
            if ($policy->customer != null)
            {
                if($policy->customer->firstName != null && $policy->customer->lastName != null){
                    return $policy
                            ->customer->firstName . ' ' . $policy->customer->lastName;
                }elseif($policy->customer->firstName == null && $policy->customer->lastName != null){
                    return 'N/A (first name)' . ' ' . $policy->customer->lastName;
                }elseif($policy->customer->firstName != null && $policy->customer->lastName == null){
                    return $policy->customer->firstName . ' ' . 'N/A';
                }else{
                    return 'N/A';
                }
            }
            else
            {
                return '-';
            }
        })->addColumn('cellphone', function ($policy)
        {
            if ($policy->customer_id != null)
            {
                $customerNumber = Customer::where('id', $policy->customer_id)
                    ->first('cellphone');
                if($customerNumber && $customerNumber->cellphone != null)
                    return $customerNumber->cellphone;
                else
                    return 'N/A';
            }
            else
            {
                return '-';
            }

        })->addColumn('product_name', function ($policy)
        {
            if ($policy->product_id != null)
            {
                return $policy
                    ->product->name;
            }
            else
            {
                return '-';
            }

        })
            ->addColumn('plan_id', function ($policy)
            {
                /* dd($policy->plan_id );*/
                if ($policy->plan_id != null)
                {
                    $productPlan = Productplan::where('id', $policy->plan_id)
                        ->first('name');
                    if($productPlan->name)
                        return $productPlan->name;
                    else
                        return 'N/A';
                }
                else
                {
                    return '-';
                }

            })->addColumn('branch', function ($policy)
            {
                //return 'na';
                $activationCode_branch = Activation::where('activation_code', $policy->activation_code)
                    ->first();
                if($activationCode_branch){
                    $branch = Branch::where('id', $activationCode_branch->branch)
                        ->first();
                    $source = Policy::where('activation_code', $policy->activation_code)
                        ->first('leadSource');
                    if($branch){
                        return $branch->name;
                    }else{
                        return 'N/A';
                    }
                }else{
                    return 'N/A';
                }


            })->editColumn('premium', function ($policy)
            {
                if ($policy->premium != null)
                {
                    $premium = 'P' . $policy->premium;
                    return $premium;
                }
                else
                {
                    return '-';
                }

            })->addColumn('paymentStatus', function ($policy)
            {
                $paymentStatus = VcsTransaction::where('policyNumber', $policy->policyNumber)
                    ->orderBy('id', 'DESC')
                    ->first('status');
                if($paymentStatus != null) {
                    $pstatus = $paymentStatus->status;
                    return $pstatus;
                }else{
                    $pstatus = "FAILED";
                }
                return $pstatus;

            })->addColumn('kycStatus', function ($policy)
            {
                $kycStatus = KYC::where('customer_id', $policy->customer_id)
                    ->first('compliance');
                if($kycStatus){
                    if ($kycStatus->compliance == 1)
                    {
                        $kstatus = 'Completed';
                        return $kstatus;
                    }
                    else
                    {
                        $kstatus = 'Incomplete';
                    }

                    return $kstatus;
                }else{
                    return 'N/A';
                }

            })->addColumn('referenceNo', function ($policy)
            {
                /*if ($policy->customer != NULL)
                    $trans = Transaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'DESC')->first();
                if ($trans != NULL && $trans['referenceNumber'] != null)
                {
                    return $trans['referenceNumber'];
                }
                else
                {
                    return "Payment reference not generated";
                }*/
                return "N/A";
            })
            ->rawColumns(['premium', 'branch', 'status', 'paymentStatus', 'kycStatus', 'referenceNo'])
            ->make(true);
    }

}
