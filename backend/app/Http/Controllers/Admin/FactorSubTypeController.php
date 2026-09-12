<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Http\Controllers\Controller;
use Http\Client\Exception;
use Illuminate\Http\Request;
use AlphaDirect\FactorMain;
use AlphaDirect\User;
use AlphaDirect\FactorSubType;
use Redirect;
use Illuminate\Support\Facades\DB;
use \Illuminate\Foundation\Http\Middleware\TrimStrings;
use Yajra\DataTables\DataTables;
use Validator;
use Session;
use Auth;

class FactorSubTypeController extends Controller
{
    /**
     * Show a list of all product Factor Value
     *
     * @return View product Factor Value index page
     */
    public function index()
    {
        $factorMain = FactorMain::get(array('id','name'));
        return view("admin.productFactorValue.index",compact('factorMain'));
    }

    /*
    * Pass data through ajax call
    */
    /**
     * @return mixed
     */
    public function data(Request $request)
    {
        $factorSubType = FactorSubType::get(array('id','main_id','name', 'status','factor', 'created_at'));
        if($request->main_filter != -1)
        {
            $factorSubType = FactorSubType::where('main_id',$request->main_filter)->get(array('id','main_id','name', 'status','factor', 'created_at'));
        }
        return DataTables::of($factorSubType)
            ->editColumn('main_id',function($factorSubType)
            {
                $mainName = FactorMain::where('id',$factorSubType->main_id)->first(array('name'));
                return $mainName?$mainName->name:'';

            })
            ->editColumn('status',function($factorSubType) {
                if($factorSubType->status)
                {
                    return 'Active';
                }
                else
                {
                    return 'In-Active';
                }

            })
            ->editColumn('created_at',function($factorSubType)
            {
                return $factorSubType->created_at->diffForHumans();
            })
            ->addColumn('actions',function($factorSubType)
            {
                $actions = '<a href="'. route('admin.factorValue.edit', $factorSubType->id) .'" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>
                           <a href="" value="'.$factorSubType->id.'" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
                return $actions;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    /**
     * Show a page to product Factor Value create
     *
     * @return View product Factor Value create page
     */
    public function create()
    {
        $factorMain = FactorMain::where('status', 1)->get(array('id','name','type'));
        return view("admin.productFactorValue.create",compact('factorMain'));
    }

    /**
     * method to store accounts data from product Factor Value page.
     *
     * @return View product Factor Value index page
     */
    public function store(Request $request)
    {
        $factorSubType = new FactorSubType();
        $factorSubType->main_id = $request->main_type;
        $factorSubType->name = $request->sub_type;
        $factorSubType->factor = $request-> factor_type_factor;
        if($request->status == NULL)
        {
            $factorSubType->status="0";
        }
        else
        {
            $factorSubType->status="1";
        }
        if($factorSubType->save())
        {
            // Redirect to the home page with success menu
            activity('Product Factor value')
                ->performedOn($factorSubType)
                ->causedBy(User::where('id', auth()->user()->id)->first())
                ->log('Product Factor value Created');
            return Redirect::route('admin.factorValue.index')->with('success', 'Product Factor Value Created Successfully');
        }
        else
        {
            return Redirect::route('admin.factorValue.index')->with('error', 'Something Went Wrong');
        }

    }

    /**
     * Show a page to edit specific email Broad Casting
     * param: product Factor Value id ($id)
     * @return View product Factor Value view page
     */
    public function edit($id)
    {
        $factorSubType = FactorSubType::where('id',$id)->first();
        $factormain = FactorMain::get(array('name','id'));
        return view('admin.productFactorValue.edit',compact('factorSubType','factormain'));
    }

    /**
     * method to update accounts data from edit page.
     *param: product Factor Value id($id)
     * @return product Factor Value index page
     */
    public function update($id, Request $request)
    {
        $factorSubType = FactorSubType::where('id', $id)->first();
        $factorSubType->main_id = $request->main_id;
        $factorSubType->name = $request->sub_type;
        if($request->status == NULL)
        {
            $factorSubType->status="0";
        }
        else
        {
            $factorSubType->status="1";
        }
        $factorSubType->factor = $request->factor_type_factor;
        if($factorSubType->save())
        {
            // Redirect to the home page with success menu
            activity('Product Factor value')
                ->performedOn($factorSubType)
                ->causedBy(User::where('id',auth()->user()->id)->first())
                ->log('Product Factor value Updated');
            return Redirect::route('admin.factorValue.index')->with('success', 'Product Factor Value Updated Successfully');
        }
        else
        {
            return redirect()->back()->with('error', 'Something Went Wrong');
        }
    }

    /**
     * method to return modal body for confirm-delete.
     *
     * @return View json
     */
    public function getModalDelete(Request $request)
    {
        $body="Are you sure you want to delete the factor sub type ? ";
        return response()->json(['status'=>'success', 'id'=>$request->get('id'), 'body'=>$body]);
    }

    /**
     *deletes specific Product Factor value accounts
     *param: Product Factor value id ($id)
     * @return Product Factor value index page
     */
    public function destroy($id)
    {
        try
        {
            activity('Product Factor value')
                ->performedOn(FactorSubType::where('id',$id)->first())
                ->causedBy(User::where('id',auth()->user()->id)->first())
                ->log('Product Factor value Deleted');
            FactorSubType::where('id',$id)->delete();
            return Redirect::route('admin.factorValue.index')->with('success', 'Product factor value Deleted Successfully');
        }
        catch(Exception $e)
        {
            return Redirect::route('admin.factorValue.index')->with('error', 'Something Went Wrong');
        }

    }
}
