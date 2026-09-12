<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\PaymentVendor;
use AlphaDirect\User;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use DB;
use Illuminate\Support\Facades\Auth;
use Redirect;
use Yajra\DataTables\DataTables;


class PaymentVendorController extends Controller
{
    /**
     * Display a listing of the payment Vendors.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if(Auth::user()->hasPermissionTo('payment-vendor-list'))
        {
            return view('admin.paymentVendor.index');
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /*
   * Pass data through ajax call
   */
    /**
     * @return mixed
     */
    public function data()
    {
        $paymentVendor = PaymentVendor::get();
        return DataTables::of($paymentVendor)
            ->editColumn('created_at', function ($paymentVendor)
            {
                return $paymentVendor->created_at->diffForHumans();
            })
            ->editColumn('status', function ($paymentVendor)
            {
                if ($paymentVendor->status == 1)
                    $return = '  <label class="kt-font-bold kt-font-accent">
                    Activated
                    </label>';
                else
                    $return = '<label class="kt-font-bold kt-font-danger">
                    Deactivated
                    </label>';

                return $return;
            })
            ->addColumn('vendorName', function ($paymentVendor)
            {
                if ($paymentVendor->vendorName != NULL)
                    return $paymentVendor->vendorName;
            })
            ->addColumn('email', function ($paymentVendor)
            {
                if ($paymentVendor->email != NULL)
                    return $paymentVendor->email;
            })
            ->addColumn('actions', function ($paymentVendor)
            {
                $actions ='';
                if(Auth::user()->hasPermissionTo('payment-vendor-edit'))
                {
                    $actions .= '<a href="' . route('admin.paymentVendor.edit', $paymentVendor->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                    <i class="la la-edit"></i>
                                </a>';
                }
                else
                {
                    $actions .= '<a href="' . route('admin.paymentVendor.edit', $paymentVendor->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                    <i class="flaticon-eye"></i>
                                </a>';
                }

                return $actions;
            })
            ->rawColumns(['actions', 'status'])
            ->make(true);
    }

    /**
     * method to change Status of payment vender.
     *
     * @return View
     */
    public function changeStatus($id)
    {
        $vendorStatus = PaymentVendor::find($id);
        if ($vendorStatus->status == 1)
        {
            $vendorStatus->status = 0;
        }
        else
        {
            $vendorStatus->status = 1;
        }
        return response()->json([
            'data' => [
                'success' => $vendorStatus->save(),
            ]
        ]);
    }

    /**
     * Show the form for creating a payment vender.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.paymentVendor.create');
    }

    /**
     * Store a newly created payment vender.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try
        {
            $paymentVendor = new PaymentVendor();
            $paymentVendor->vendorName = $request->vendorName;
            $paymentVendor->vendorLabel = $request->vendorLabel;
            $paymentVendor->email = $request->vendorEmail;
            $paymentVendor->telephone = $request->vendorTelephone;
            $paymentVendor->status = $request->status;
            if(isset($request->offtimestart) && $request->offtimestart != null){
            $paymentVendor->offtimestart = \Carbon\Carbon::parse($request->offtimestart)->format('H:i');
            }else{
                $paymentVendor->offtimestart = null;  
            }
            if(isset($request->offtimeend) && $request->offtimeend != null){
            $paymentVendor->offtimeend = \Carbon\Carbon::parse($request->offtimeend)->format('H:i'); 
            }else{
                $paymentVendor->offtimeend = null;
            }
            $paymentVendor->save();
            activity('Payment Vendor')
                ->performedOn($paymentVendor)
                ->causedBy(User::where('id', Auth()->user()->id)->first())
                ->log('Payment Vendor Created');
            return Redirect::route('admin.paymentVendor.index')->with('success', 'Created Payment Vendor Successfully');
        }
        catch (\Exception $e)
        {
            DB::rollback();
            return Redirect::back()->with('error', 'Something Went Wrong');
        }
    }

    /**
     * Display the specified payment vender.
     *
     * @param  \AlphaDirect\PaymentVendor  $paymentVendor
     * @return \Illuminate\Http\Response
     */
    public function show(PaymentVendor $paymentVendor)
    {
        //
    }

    /**
     * Show the form for editing the specified payment vender.
     *
     * @param  \AlphaDirect\PaymentVendor  $paymentVendor
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
        try
        {
            //code...
            $paymentVendor = PaymentVendor::findorFail($id);
            if(Auth::user()->hasPermissionTo('payment-vendor-edit'))
                return view('admin.paymentVendor.edit', compact('paymentVendor'));
            elseif(Auth::user()->hasPermissionTo('payment-vendor-list'))
                return view('admin.paymentVendor.view', compact('paymentVendor'));
            else
                return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');

        }
        catch (\Exception $ex)
        {
            //throw $th;
            return response()->json($ex->getMessage());
        }
    }

    /**
     * Update the specified resource in payment vender.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \AlphaDirect\PaymentVendor  $paymentVendor
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try
        {
            $paymentVendor = PaymentVendor::findorFail($id);
            $paymentVendor->vendorName = $request->vendorName;
            $paymentVendor->vendorLabel = $request->vendorLabel;
            $paymentVendor->email = $request->vendorEmail;
            $paymentVendor->telephone = $request->vendorTelephone;
            $paymentVendor->status = $request->status;
            if(isset($request->offtimestart) && $request->offtimestart != null){
             $paymentVendor->offtimestart = \Carbon\Carbon::parse($request->offtimestart)->format('H:i');
            }else{
                $paymentVendor->offtimestart = null;  
            }
            if(isset($request->offtimeend) && $request->offtimeend != null){
             $paymentVendor->offtimeend = \Carbon\Carbon::parse($request->offtimeend)->format('H:i'); 
            }else{
                $paymentVendor->offtimeend = null;
            }
            
            

            $paymentVendor->save();
            return Redirect::route('admin.paymentVendor.index')->with('success', ' Updated Payment Vendor Successfully');
        }
        catch (\Exception $ex)
        {
            return response()->json($ex->getMessage());
        }
    }

    /**
     * Remove the specified resource from payment vender.
     *
     * @param  \AlphaDirect\PaymentVendor  $paymentVendor
     * @return \Illuminate\Http\Response
     */
    public function destroy(PaymentVendor $paymentVendor)
    {
        //
    }

    public function getAllVendors()
    {
        $timeNow = \Carbon\Carbon::now()->format('H:i:s');
        $vendors = PaymentVendor::where('status', '1')->get();
        $data = [];
       if(count($vendors) > 0){
          foreach($vendors as $vendor){
            if($vendor->offtimestart != null && $vendor->offtimeend != null){
                if(($timeNow >= \Carbon\Carbon::parse($vendor->offtimestart)->format('H:i:s')) && ($timeNow <= \Carbon\Carbon::parse($vendor->offtimeend)->format('H:i:s'))){

                 }else{
                    $data[] = $vendor;
                 }
            }else{
                $data[] = $vendor;
            }
           }
        }



        // $timeNow = \Carbon\Carbon::now()->format('H:i:s');
        // if(($timeNow >= \Carbon\Carbon::parse('14:00')->format('H:i')) && ($timeNow <= \Carbon\Carbon::parse('17:00')->format('H:i'))){
        //     $vendors = PaymentVendor::where('vendorName','!=','DPO')->where('status', '1')->orderBy('id','desc')->get();
        // }else{
        //     $vendors = PaymentVendor::where('status', '1')->get();
        // }
       
        if ($data == [])
        {
            return response()->json([
                'status' => '404',
                'message' => 'No vendors were found',
            ]);
        }
        else
        {
          
            return response()->json($data);
        }
    }
}
