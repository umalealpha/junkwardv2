<?php

namespace AlphaDirect\Http\Controllers\Admin;

use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\CommissionPolicy;
use AlphaDirect\CommissionProduct;
use Yajra\DataTables\DataTables;
use AlphaDirect\KYC;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use Redirect;

class CommissionController extends Controller
{
    public function create(Request $request){

        // if(auth::user()->hasPermissionTo('account-create')){
            $products = Product::with('type')->where('status', 1)->get(array('id', 'product_type_id', 'name', 'is_motor_items', 'kyc_customer', 'has_vehicle', 'has_subApplicant'));
            // dd($products);
            return view('admin.commission.create', compact('products'));
        // }
        // else{
        //     return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        // }

    }

    public function store(Request $request){

        $commission = new CommissionProduct();

        $commission->product_id = $request->get('product_id');

        if($request->get('convert_fixed_sold') == 'amt'){
            $commission->fixed_sold_amt = $request->get('fixed_sold');
            $commission->fixed_sold_per = 0;

        }else{
            $commission->fixed_sold_per = $request->get('fixed_sold');
            $commission->fixed_sold_amt = 0;
        }

        if($request->get('convert_fixed_activated') == 'amt'){
            $commission->fixed_activated_amt = $request->get('fixed_activated');
            $commission->fixed_activated_per = 0;

        }else{
            $commission->fixed_activated_per = $request->get('fixed_activated');
            $commission->fixed_activated_amt = 0;

        }

        if($request->get('convert_preinsp_kyc') == 'amt'){
            $commission->fixed_activated_preinsp_kyc_amt = $request->get('fixed_activated_preinsp_kyc');
            $commission->fixed_activated_preinsp_kyc_per = 0;

        }else{
            $commission->fixed_activated_preinsp_kyc_per = $request->get('fixed_activated_preinsp_kyc');
            $commission->fixed_activated_preinsp_kyc_amt = 0;

        }

        // dd($request->get('status'));
        if ($request->get('status')) {
            $commission->status = $request->get('status');
        }else{
            $commission->status = 0;
        }

        $commission->save();

    activity('Create')
        ->performedOn($commission)
        ->log('Commission has been created');

    return Redirect::route('admin.commission.index')->with('success', 'Commission Created Successfully');
}


    public function index(Request $request)
    {
        // if(Auth::user()->hasPermissionTo('account-list')){

            return view('admin.commission.index');
        // }
        // else{
        //     return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        // }

    }

    public function data()
    {
        $returnComplainceStatus = $this->checkcomplianceStatus();
        $returnpolicyCellphoneStatus = $this->checkPolicyCellphoneStatus();


        $commission = CommissionProduct::get(array('id','product_id', 'fixed_sold_amt','fixed_sold_per', 'fixed_activated_amt', 'fixed_activated_per', 'fixed_activated_preinsp_kyc_amt', 'fixed_activated_preinsp_kyc_per', 'status'));

        return DataTables::of($commission)

        ->editColumn('product_id',function($commission) {
            $product_id = Product::where('id', $commission->product_id)->first(array('name'));
            $product =  str_replace('{"name":"','',$product_id);
            $product =  str_replace('"}','',$product);
            return $product;
        })
        ->rawColumns(['product_id'])

            ->editColumn('status',function($commission) {
                $status = '';
                if ($commission->status == 1) {
                    $status = 'Active';
                }else{
                    $status = 'Inactive';
                }
                return $status;
            })
            ->rawColumns(['status'])

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


    public function edit($id){
        // if(Auth::user()->hasPermissionTo('account-edit')){
            $products = Product::with('type')->where('status', 1)->get(array('id', 'product_type_id', 'name', 'is_motor_items', 'kyc_customer', 'has_vehicle', 'has_subApplicant'));
            // return view('admin.commission.edit',compact('products'));

            $commission = CommissionProduct::where('id',$id)->first();
            return view('admin.commission.edit',compact('commission','products'));
        // }
        // elseif(Auth::user()->hasPermissionTo('account-list')){
            $commission = CommissionProduct::where('id',$id)->first();
            return view('admin.commission.view',compact('commission','products'));
        // }else{
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        // }

    }


    public function update(Request $request,$id  )
    {
        $commission = CommissionProduct::where('id', $id)->first();
        $commission->product_id = $request->get('product_id');

        if($request->get('convert_fixed_sold') == 'amt'){
            $commission->fixed_sold_amt = $request->get('fixed_sold');
            $commission->fixed_sold_per = 0;
        }else{
            $commission->fixed_sold_per = $request->get('fixed_sold');
            $commission->fixed_sold_amt = 0;
        }

        if($request->get('convert_fixed_activated') == 'amt'){
            $commission->fixed_activated_amt = $request->get('fixed_activated');
            $commission->fixed_activated_per = 0;

        }else{
            $commission->fixed_activated_per = $request->get('fixed_activated');
            $commission->fixed_activated_amt = 0;

        }

        if($request->get('convert_preinsp_kyc') == 'amt'){
            $commission->fixed_activated_preinsp_kyc_amt = $request->get('fixed_activated_preinsp_kyc');
            $commission->fixed_activated_preinsp_kyc_per = 0;

        }else{
            $commission->fixed_activated_preinsp_kyc_per = $request->get('fixed_activated_preinsp_kyc');
            $commission->fixed_activated_preinsp_kyc_amt = 0;

        }

        // $commission->status = $request->get('status');
        $request->get('status') ? $commission->status = $request->get('status') : $commission->status = 0;
        $commission->getChanges();
        if($commission->save()) {
            // Redirect to the home page with success menu
            activity('Commission')
                ->performedOn($commission)
                // ->causedBy(User::where('id',Auth()->user()->id)->first())
                ->log('Commission Updated');
            return Redirect::route('admin.commission.index')->with('success', 'Commission Updated Successfully');
        }else{
            return redirect()->back()->with('error', 'Something Went Wrong');
        }
    }


    /**
     * method to return modal body for confirm-delete.
     *
     * @return View
     */
    public function getModalDelete(Request $request)
    {

            $body = 'Are you sure you want to delete the Commission ?';
            return response()->json(['status'=>'success', 'id'=>$request->get('id'), 'body'=>$body]);

    }

     /**
     * method to delete Account.
     *
     * @return View
     */
    public function destroy($id){
        try{
            $commission = CommissionProduct::where('id',$id)->delete();

            return Redirect::route('admin.commission.index')->with('success', 'Commission Deleted Successfully');

        }catch(Exception $e){
            return Redirect::route('admin.commission.index')->with('error', 'Something Went Wrong');
        }

    }



    public function checkComplianceStatus()
    {
        $now = date("Y-m-d H:i:s");
        $date = date("Y-m-d H:i:s", strtotime('-24 hours', time()));//(new \DateTime())->modify('-1 day');

        $policies = Policy::
        whereBetween('created_at', [$date, $now])
           ->whereNotNull('agent_id')
           ->where(function ($query) {
               $query->where('status', '=', '0')
                     ->orWhere('status', '=', '1');
           })
           ->get(array('id','status', 'customer_id','product_id','agent_id'));

        // print_r(count($policies));
        // print_r($policies);

        foreach ($policies as $policy) {
            try {
                if ($policy->status == 0) {
                    $this->saveUpdateData('Fixed Sold', $policy->id, $policy->agent_id);
                }else{
                  $customerkyc = KYC::where('id', $policy->customer_id)->first();
                    if ($customerkyc) {
                        if ($customerkyc->compliance == 1) {
                            $customerkyc = 1;
                            // $this->saveUpdateData('Fixed Activated', $policy->id, $policy->agent_id);
                        }

                        $products = Product::where('id', $policy->product_id)->first();
                            if ($products) {
                                if ($products->preinspection == 1) {
                                    $vehicle = Vehicle::where('policy_id', $policy->id)->first();
                                    if($vehicle->compliance == 1){
                                        if($customerKYC && $products->preinspection){
                                            $this->saveUpdateData('Fixed Activated KYC PREINSPECTION', $policy->id, $policy->agent_id);
                                        }else{
                                            $this->saveUpdateData('Fixed Activated', $policy->id, $policy->agent_id);
                                        }
                                    }else{
                                        if($customerKYC){
                                            $this->saveUpdateData('Fixed Activated KYC PREINSPECTION', $policy->id, $policy->agent_id);
                                        }else{
                                            $this->saveUpdateData('Fixed Activated', $policy->id, $policy->agent_id);
                                        }
                                    }
                                }else{
                                    $this->saveUpdateData('Fixed Activated', $policy->id, $policy->agent_id);
                                }
                            }
                    }else{
                        $this->saveUpdateData('Fixed Activated', $policy->id, $policy->agent_id);
                    }

                }
            }catch(Exception $e){
                return null;
            }
        }
    }


    public function checkPolicyCellphoneStatus()
    {
        $now = date("Y-m-d H:i:s");
        $date = date("Y-m-d H:i:s", strtotime('-24 hours', time()));//(new \DateTime())->modify('-1 day');

        $policies = Policy::
        whereBetween('created_at', ['2021-02-19 18:12:15', $now])
           ->whereNotNull('agent_id')
           ->where(function ($query) {
               $query->where('status', '=', '0')
                     ->orWhere('status', '=', '1');
           })
           ->get(array('id','status', 'customer_id','product_id','agent_id'));

        // print_r(count($policies));
        // print_r($policies);

        foreach ($policies as $policy) {
            try {
                if ($policy->status == 0) {
                    $this->saveUpdateData('Fixed Sold', $policy->id, $policy->agent_id);
                }else{
                  $customerkyc = KYC::where('id', $policy->customer_id)->first();
                    if ($customerkyc) {
                        if ($customerkyc->compliance == 1) {
                            $customerkyc = 1;
                            // $this->saveUpdateData('Fixed Activated', $policy->id, $policy->agent_id);
                        }

                        $products = Product::where('id', $policy->product_id)->first();
                            if ($products) {
                                if ($products->preinspection == 1) {
                                    $policyCellphone = PolicyCellPhone::where('policy_id', $policy->id)->first();
                                    if($policyCellphone->cell_phone_front != null AND $policyCellphone->cell_phone_back != null AND $policyCellphone->cell_phone_left != null AND $policyCellphone->cell_phone_right != null AND $policyCellphone->cell_phone_top != null AND $policyCellphone->cell_phone_bottom != null ){
                                        if($customerKYC && $products->preinspection){
                                            $this->saveUpdateData('Fixed Activated KYC PREINSPECTION', $policy->id, $policy->agent_id);
                                        }else{
                                            $this->saveUpdateData('Fixed Activated', $policy->id, $policy->agent_id);
                                        }
                                    }else{
                                        if($customerKYC){
                                            $this->saveUpdateData('Fixed Activated KYC PREINSPECTION', $policy->id, $policy->agent_id);
                                        }else{
                                            $this->saveUpdateData('Fixed Activated', $policy->id, $policy->agent_id);
                                        }
                                    }
                                }else{
                                    $this->saveUpdateData('Fixed Activated', $policy->id, $policy->agent_id);
                                }
                            }
                    }else{
                        $this->saveUpdateData('Fixed Activated', $policy->id, $policy->agent_id);
                    }

                }
            }catch(Exception $e){
                return null;
            }
        }
    }



    public function saveUpdateData($commission_type, $policy_id, $agent_id){
        $commissionpolicy = CommissionPolicy::where('policy_id',$policy_id)->first();
        try{
            if ($commissionpolicy) {
                $commissionpolicy->commission_type = $commission_type;//'Fixed Activated';
                $commissionpolicy->policy_id = $policy_id;//$policy->id;
                $commissionpolicy->agent_id = $agent_id;//$policy->agent_id;
                $commissionpolicy->getChanges();
                $commissionpolicy->save();
            }else{
                $commissionpolicy = new CommissionPolicy();
                $commissionpolicy->commission_type = $commission_type;//'Fixed Activated';
                $commissionpolicy->policy_id = $policy_id;//$policy->id;
                $commissionpolicy->agent_id = $agent_id;//$policy->agent_id;
                $commissionpolicy->save();
            }
        }catch(Exception $e){
            return null;
        }
    }


}
