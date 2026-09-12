<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Customer;
use AlphaDirect\Exports\RenewPolicyExport;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\PolicyRenewal;
use AlphaDirect\Policy;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;

class RenewpolicyController extends Controller
{
    public function index()
    {
        try {
            return view('admin.renewPolicy.list');
        } catch (Exception $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }
    }

    public function data()
    {
        $data = PolicyRenewal::with('policy')->orderBy('id', 'DESC')->get();

        foreach ($data as $key => $renew_policy) {
            if(isset($renew_policy->policy->customer_id)){
                $renew_policy->policy->customer = Customer::where('id',$renew_policy->policy->customer_id)->first(array('firstName','lastName','cellphone','email'));
            }
        }

        return DataTables::of($data)
            ->editColumn('policy_number', function ($data) {
                $policy = Policy::where('policyNumber',$data->policyNumber)->first();
                $policy_number = '<a href="' . route('admin.policy.policyView', $policy->id) . '" target="_blank">' . $data->policyNumber . '</a>';
                return $policy_number;
            })
            ->editColumn('customer_name', function ($data) {
                $customer_fname_lname = null;
                if ($data->policy->customer_id != null && $data->policy->customer!= null) {
                    $customer_fname_lname = ucwords($data->policy->customer->firstName).'  '.ucwords($data->policy->customer->lastName);
                } else {
                    $customer_fname_lname = '-';
                }
                $customer_name = $customer_fname_lname;
                return $customer_name;
            })

            ->editColumn('policy.customer.cellphone', function ($data) {
                //return Carbon::createFromFormat('Y-m-d', $data->expiry_date)->format('d-m-Y');
                $cellphone=null;
                if($data->policy->customer_id != null && $data->policy->customer!= null && $data->policy->customer->cellphone != null)
                {
                    return $cellphone= $data->policy->customer->cellphone;
                }else{
                    return $cellphone='--';
                }
            })

            ->editColumn('policy.customer.email', function ($data) {

                $email=null;
                if($data->policy->customer_id != null && $data->policy->customer!= null && $data->policy->customer->email != null)
                {
                    return $email= $data->policy->customer->email;
                }else{
                    return $email='--';
                }
            })

            ->editColumn('expiry_date', function ($data) {
                return Carbon::createFromFormat('Y-m-d', $data->expiry_date)->format('d-m-Y');

            })
            ->editColumn('request_data', function ($data) {
                if($data->request_data){
                    $array = unserialize($data->request_data, ['allowed_classes' => false]);
                }else{
                    $array = '-';
                }

                return $array;
            })
            ->editColumn('response_data', function ($data) {
                if($data->response_data){
                    $array = unserialize($data->response_data, ['allowed_classes' => false]);
                    $arrayNew = '';
                    if($array){
                        foreach($array as $key=>$ar){
                            $arrayNew .= $key.' : '.$ar.'<br>';
                        }
                    }else{
                        $arrayNew = '-';
                    }

                    return $arrayNew;

                }else{
                    $array = '-';
                }

                return $array;
            })

            ->editColumn('rerated', function ($data) {
                $rerated=null;
                if($data->is_rated == 0)
                {
                    $rerated='No';
                }else{
                    $rerated="Yes";
                }
                return $rerated;
            })
            ->editColumn('is_renewed', function ($data) {
                $rerated=null;
                if($data->is_renewed == 0)
                {
                    $is_renewed='No';
                }else{
                    $is_renewed="Yes";
                }
                return $is_renewed;
            })

            ->editColumn('old_premium', function ($data) {
                $primium=null;
                if($data->old_premium != null )
                {
                    $primium=$data->old_premium;
                }else{
                    $primium="--";
                }
                return $primium;
            })
            ->editColumn('new_premium', function ($data) {
                $primium=null;
                if($data->new_premium != null )
                {
                    $primium=$data->new_premium;
                }else{
                    $primium="--";
                }
                return $primium;
            })
            ->editColumn('paymentFrequency', function ($data) {
                if($data->paymentFrequency != null )
                {
                    if($data->paymentFrequency == 1){
                        $primium = 'Monthly';
                    }elseif($data->paymentFrequency == 2){
                        $primium = 'Three Instalments';
                    }elseif($data->paymentFrequency == 3){
                        $primium = 'Annual';
                    }else{
                        $primium = '--';
                    }
                }else{
                    $primium="--";
                }
                return $primium;
            })
            ->editColumn('paymentMethod', function ($data) {
                if($data->paymentMethod != null )
                {
                    $primium=$data->paymentMethod;
                }else{
                    $primium="--";
                }
                return $primium;
            })
            ->addColumn('premium_changed_in_per', function ($data) {
                $premium_changed_in_per = 'N/A';
                if(isset($data->old_premium) && isset($data->new_premium))
                {
                    $premium_value = $data->new_premium - $data->old_premium;
                    if($data->old_premium == 0 || $data->old_premium == NULL || $data->old_premium == '')
                        $data->old_premium = 1;
                    $premium_value = ($premium_value / $data->old_premium) * 100;
                    $premium_changed_in_per = round($premium_value);
                }
                return $premium_changed_in_per;
            })
            ->rawColumns(['customer_name','policy_number','response_data','request_data','paymentFrequency','paymentMethod','is_renewed','premium_changed_in_per'])
            ->make(true);
    }

    public function export()
    {

        return Excel::download(new RenewPolicyExport, 'RenewPolicyExport.csv');
    }

}
