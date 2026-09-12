<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Accounts;
use AlphaDirect\Billing;
use AlphaDirect\Lookup;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use Redirect;
use Yajra\DataTables\DataTables;
class BillingController extends Controller
{
    public function index()
    {

        return view('admin.billing.index');
    }

    public function data()
    {
        $data = \AlphaDirect\Billing::get(array('id','product_id','plan_id','billing_days','created_at'));
        return DataTables::of($data)
            ->addColumn('actions',function($data) {
                $actions = '';
                    $actions .= '<a href="'. route('admin.billing.edit', $data->id) .'" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                return $actions;
            })
            ->editColumn('product_id', function ($data) {
                $product = \AlphaDirect\Product::where('id', $data->product_id)
                    ->first(array(
                        'name'
                    ));
                return $product ? $product->name : '';
            })
            ->rawColumns(['actions','product_id'])
            ->make(true);
    }

    public function create(){
        $products = \AlphaDirect\Product::where('status', 1)->get(['id','name','slug']);
        return view('admin.billing.create', compact('products'));
    }

    public function store(Request $request){
        $check = \AlphaDirect\Billing::where('product_id',$request->product)->count();
        if($check > 0)
            return Redirect::route('admin.billing')->with('error', 'Billing days already exist for selected product');

        $billing = new \AlphaDirect\Billing();
        $billing->product_id = $request->product;
        $billing->billing_days = $request->billingDays;
        $billing->save();
        return Redirect::route('admin.billing')->with('success', 'Billing day added for product Successfully');
    }

    public function edit($id){
        $products = \AlphaDirect\Product::where('status', 1)->get(['id','name','slug']);
        $billing = \AlphaDirect\Billing::where('id',$id)->first();
        return view('admin.billing.edit', compact('products','billing'));
    }

    public function update(Request $request){
        $billing = \AlphaDirect\Billing::where('id',$request->id)->first();
        $billing->billing_days = $request->billingDays;
        $billing->save();
        return Redirect::route('admin.billing')->with('success', 'Billing day updated for product Successfully');
    }

/*getProductPlans*/
    public function getProductPlans(Request $request){
        $check = \AlphaDirect\Productplan::where('product_id', $request->get('product_id'))->where('status', 1)->get(array('id', 'name', 'premium', 'sum_assured', 'product_id'));
        // Check if we are not trying to delete ourselves

        if ($check->count() == null) {
            // Prepare the error message
            return response()->json('No product plans available', 401);
        } else {
            return response()->json(['productPlans' => $check], 200);
        }
    }
}
