<?php

namespace AlphaDirect\Http\Controllers\Admin;
use AlphaDirect\FactorSubType;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Master;
use AlphaDirect\Product;
use AlphaDirect\User;
use Http\Client\Exception;
use Illuminate\Http\Request;
use AlphaDirect\FactorMain;

use Yajra\DataTables\DataTables;
use Validator;
use Session;
use Auth;
use Redirect;

class FactorMainController extends Controller
{
    /**
     * Show a list of all product Factor Main
     *
     * @return View product Factor Main index page
     */
    public function index()
    {
        if(auth::user()->hasPermissionTo('product-factor-main-list'))
        {
            $products = Product::get(array('id','name'));
            return view("admin.productFactorMain.index",compact('products'));
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
    public function data(Request $request)
    {
        $factormain = FactorMain::get(array('id','product_id','name', 'status', 'created_at'));
        if($request->product_filter != -1)
        {
            $factormain = FactorMain::where('product_id',$request->product_filter)->get(array('id','product_id','name', 'status', 'created_at'));
        }
        return DataTables::of($factormain)
            ->editColumn('product_id',function($factormain)
            {
                $productName = Product::where('id',$factormain->product_id)->first(array('name'));
                return $productName?$productName->name:'';
            })
            ->editColumn('status',function($factormain)
            {
                if($factormain->status)
                {
                    return 'Active';
                }
                else
                {
                    return 'In-Active';
                }
            })
            ->editColumn('created_at',function($factormain)
            {
                return $factormain->created_at->diffForHumans();
            })
            ->addColumn('actions',function($factormain)
            {
                $actions = '';
                $actions .= '<a href="'. route('admin.factorMain.edit', $factormain->id) .'" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                if(auth::user()->hasPermissionTo('product-factor-main-delete'))
                {
                    $actions .= '<a href="" value="' . $factormain->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
                }
                return $actions;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    /**
     * Show a page to product Factor Main create
     *
     * @return View product Factor Main create page
     */
    public function create()
    {
        if(auth::user()->hasPermissionTo('product-factor-main-create'))
        {
            $product = Product::where('status', 1)->get(array('id','name'));
            $lookup = Master::where('key', 'factor_main_type')->get(array('id','value'));
            return view("admin.productFactorMain.create",compact('product','lookup'));
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * method to store accounts data from factor Main page.
     *
     * @return View factor Main index page
     */
    public function store(Request $request)
    {
        $factormain = new FactorMain();
        $factormain->product_id = $request->product_name;
        $factormain->name = $request->factor_name;
        $factormain->type = $request->type;
        if($request->status == NULL)
        {
            $factormain->status="0";
        }
        else
        {
            $factormain->status="1";
        }
        if($factormain->save())
        {
            foreach($request->factorProductValues as $key=>$value)
            {
                if($value['factor_type_values'] != NULL)
                {
                    $storeValues = new FactorSubType();
                    $storeValues->main_id = $factormain->id;
                    $storeValues->name = $value['factor_type_values'];
                    $storeValues->factor = $value['factor_type_factor'];
                    $storeValues->status = $request->status;
                    $storeValues->save();
                    activity('Product Factor Main')
                        ->performedOn($factormain)
                        ->causedBy(User::where('id',auth()->user()->id)->first())
                        ->log('Product Factor Main Created');
                }

            }
            // Redirect to the home page with success menu
            return Redirect::route('admin.factorMain.index')->with('success', 'Product factor Main Created Successfully');
        }
        else
        {
            return Redirect::route('admin.factorMain.index')->with('error', 'Something Went Wrong');
        }

    }

    /**
     * Show a page to edit specific product Factor Main
     * param: customer id ($id)
     * @return View product Factor Main edit page
     */
    public function edit($id)
    {
        if(auth::user()->hasPermissionTo('product-factor-main-edit'))
        {
            $factormain= FactorMain::where('id',$id)->first();
            $product = Product:: pluck('name','id')->all();
            $lookup = Master::where('key', 'factor_main_type')->get(array('id','value'));
            $factorValues = FactorSubType::where('main_id',$id)->get();
            return view('admin.productFactorMain.edit',compact('factormain','product','lookup','factorValues'));
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * method to update accounts data from edit page.
     *param: factor Main id($id)
     * @return factor Main index page
     */
    public function update($id,Request $request)
    {
        $factor_type_values_names = $request->factor_type_values_names;
        $factor_type_values_factors = $request->factor_type_factor_names;
        $factorProductValues = $request->factorProductValues;
        $factormain = FactorMain::where('id',$id)->first();
        $factormain->product_id = $request->product_id;
        $factormain->name = $request->product_name;
        $factormain->type = $request->type;
        if($factor_type_values_names !=null)
        {
            foreach($factor_type_values_names as $key => $values)
            {
                if(!empty($values))
                {
                    $checkValues = FactorSubType::where('id',$key)->first();
                    $checkValues->name = $values;
                    $checkValues->save();
                }
            }
        }
        if($factor_type_values_factors !=null)
        {
            foreach($factor_type_values_factors as $key => $values)
            {
                if(!empty($values))
                {
                    $checkValues = FactorSubType::where('id',$key)->first();
                    $checkValues->factor = $values;
                    $checkValues->save();
                }

            }
        }
        if($factorProductValues !=null)
        {
            foreach($factorProductValues as $key =>$value)
            {
                if($value['factor_type_values'] !=null && $value['factor_type_factor'])
                {
                    $storeValues = new FactorSubType();
                    $storeValues->main_id = $id;
                    $storeValues->name = $value['factor_type_values'];
                    $storeValues->factor = $value['factor_type_factor'];
                    $storeValues->status = $request->status;
                    $storeValues->save();
                }
            }
        }

        if($request->status == NULL)
        {
            $factormain->status="0";
        }
        else
        {
            $factormain->status="1";
        }
        if($factormain->save())
        {
            // Redirect to the home page with success menu
            activity('Product Factor Main')
                ->performedOn($factormain)
                ->causedBy(User::where('id',auth()->user()->id)->first())
                ->log('Product Factor Main Updated');
            return Redirect::route('admin.factorMain.index')->with('success', 'Product Factor Main Updated Successfully');
        }
        else
        {
            return redirect()->back()->with('error', 'Something Went Wrong');
        }
    }

    /**
     *deletes specific factor Value
     *param: factor Value id ($id)
     * @return  view page
     */
    public function factorValueDelete($id)
    {
        $factorValues = FactorSubType::find($id)->delete();
        return redirect()->back()->with('success', 'Factor Main Values Deleted Successfully');
    }


    /**
     * method to return modal body for confirm-delete.
     *
     * @return View json
     */
    public function getModalDelete(Request $request)
    {
        $check = FactorSubType::where('main_id',$request->get('id'))->count();
        if ($check)
        {
            // Prepare the error message
            $body = 'Product Factor Main Assigned to Factor Value. Cannot delete this Product Factor Main';
            return response()->json(['status' => 'error', 'body' => $body]);
        }
        else
        {
            $body="Are you sure you want to delete the factor main ? ";
            return response()->json(['status'=>'success', 'id'=>$request->get('id'), 'body'=>$body]);
        }
    }


    /**
     *deletes specific factor Main accounts
     *param: factor Main id ($id)
     * @return factor Main index page
     */
    public function destroy($id)
    {
        try
        {
            activity('Product Factor Main')
                ->performedOn(FactorMain::where('id',$id)->first())
                ->causedBy(User::where('id',auth()->user()->id)->first())
                ->log('Product Factor Main Deleted');
            FactorMain::where('id',$id)->delete();
            return Redirect::route('admin.factorMain.index')->with('success', 'Product factor Main Deleted Successfully');

        }
        catch(Exception $e)
        {
            return Redirect::route('admin.factorMain.index')->with('error', 'Something Went Wrong');
        }

    }

}

