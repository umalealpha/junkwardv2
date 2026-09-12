<?php

namespace AlphaDirect\Http\Controllers\Admin;

use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\CommissionPolicy;
// use AlphaDirect\Commission;
use Yajra\DataTables\DataTables;
use AlphaDirect\Policy;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\User;
use AlphaDirect\KYC;
use AlphaDirect\Vehicle;

class CommissionReportController extends Controller
{
    //
    public function index(Request $request)
    {
        // if(Auth::user()->hasPermissionTo('account-list')){

                return view('admin.commissionReports.index');
                // return view('admin.TermsConditions.index');

        // }
        // else{
        //     return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        // }

    }

    public function commissionReport(Request $request)
    {
        // if(Auth::user()->hasPermissionTo('account-list')){

                return view('admin.commissionReports.commissionReport');
                // return view('admin.TermsConditions.index');

        // }
        // else{
        //     return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        // }

    }


    public function data()
    {

        $summary = Policy::join('commission_config', 'commission_config.product_id', '=', 'policies.product_id')
            // ->join('orders', 'users.id', '=', 'orders.user_id')
            // ->select('users.*', 'contacts.phone', 'orders.price')
            ->whereNotNull('agent_id')
            ->get();
        // print_r(count($summary));
            // $agents = Policy::join('users', 'users.id', 'policies.agent_id')
            //     ->where('users.active', 1)
            //     ->where('policies.agent_id', '!=', 'null')
            //     ->groupBy('policies.agent_id')
            //     ->get();

        return DataTables::of($summary)
        ->editColumn('created_at', function ($summary) {
            if ($summary->created_at != null) {
                return   \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $summary->created_at)->format('Y-m-d H:i') ;
            }
        })
        ->editColumn('total',function($summary) {
            $total = $summary->fixed_sold_per + $summary->fixed_activated_per + $summary->fixed_activated_preinsp_kyc_per;
            return $total;
        })
        ->rawColumns(['total'])

            // ->editColumn('status',function($commission) {
            //     $status = '';
            //     if ($commission->status == 1) {
            //         $status = 'Active';
            //     }else{
            //         $status = 'Inactive';
            //     }
            //     return $status;
            // })
            // ->rawColumns(['status'])

            ->addColumn('actions',function($commission) {
                $actions = '';
                // if(Auth::user()->can('account-edit')){
                    $actions .= '<a href="'. route('admin.commission.edit', $commission->id) .'" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';

                    $actions .= '<a href="" value="' . $commission->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
                // }else{
                    // $actions .= '<a href="'. route('admin.commission.edit', $commission->id) .'" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                    //             <i class="flaticon-eye"></i>
                    //         </a>';
                // }
                // if(Auth::user()->can('account-delete')) {

                // }
                return $actions;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }


    public function reportData()
    {

        $report = Policy::join('products', 'products.id', '=', 'policies.product_id')
            ->join('users', 'users.id', '=', 'policies.agent_id')
            ->join('payment_transactions', 'payment_transactions.policyNumber', '=', 'policies.policyNumber')
            ->select('policies.id','policies.policyNumber', 'policies.premium', 'policies.preinspection','policies.payment_reference', 'products.name', 'users.id', 'users.firstName', 'users.lastName', 'payment_transactions.status')
            ->get();
        // print_r(count($report));


        return DataTables::of($report)

        ->editColumn('firstName',function($report) {
            $agentName = $report->firstName . ' ' . $report->lastName;
            return $agentName;
            // print_r($agentName);
        })
        ->rawColumns(['firstName'])

        ->editColumn('kyc',function($report) {
            $customerkyc = KYC::where('customer_id', $report->customer_id)->first(array('compliance'));
            $kycstatus = "";
            if ($customerkyc->compliance == 1) {
                $kycstatus = "Done";
            }else{
                $kycstatus = "Not Done";
            }
            return $kycstatus;
            // print_r($agentName);
        })
        ->rawColumns(['kyc'])


        ->editColumn('preinspection',function($report) {
            $vehiclePreinsp = Vehicle::where('customer_id', $report->customer_id)->first(array('compliance'));
            $preinpstatus = '';
            if ($vehiclePreinsp != null) {
                if ($vehiclePreinsp->compliance == 1) {
                    $preinpstatus = "Done";
                }else{
                    $preinpstatus = "Not Done";
                }
            }else{
                $preinpstatus = "Not Done";
            }
            return $preinpstatus;
        })
        ->rawColumns(['preinspection'])

        // ->editColumn('commission_type',function($report) {
        //     $commission = CommissionPolicy::where('policy_id', $report->id)->first(array('commission_type'));
        //     // $kycstatus = "";
        //     // if ($customerkyc->compliance == 1) {
        //     //     $kycstatus = "Done";
        //     // }else{
        //     //     $kycstatus = "Not Done";
        //     // }
        //     return $commission;
        //     // print_r($agentName);
        // })
        // ->rawColumns(['commission_type'])

            ->addColumn('actions',function($commission) {
                $actions = '';
                // if(Auth::user()->can('account-edit')){
                    $actions .= '<a href="'. route('admin.commission.edit', $commission->id) .'" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';

                    $actions .= '<a href="" value="' . $commission->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
                // }else{
                    // $actions .= '<a href="'. route('admin.commission.edit', $commission->id) .'" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                    //             <i class="flaticon-eye"></i>
                    //         </a>';
                // }
                // if(Auth::user()->can('account-delete')) {

                // }
                return $actions;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }
}
