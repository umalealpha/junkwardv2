<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Vendor;
use AlphaDirect\User;
use AlphaDirect\Vehicle;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Yajra\DataTables\DataTables;

class VendorsController extends Controller
{
    /*
    * returns listing of all the vendors
    */
    /**
     * @return vendor index page
     */
    public function index()
    {
        if(Auth::user()->hasPermissionTo('vendor-list')){
            return view('admin.vendor.index');
        }
        else{
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
        // Show the page

    }

    /*
    * Pass data through ajax call for all vendor data
    */
    /**
     * @return JSON
     */
    public function data()
    {
        $vendor = Vendor::get(['id','name','vat','telephone','email','city', 'created_at']);

        return DataTables::of($vendor)
                ->addColumn('created_at', function ($vendor) {
                    if ($vendor->created_at != null) {
                        return   \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $vendor->created_at)->format('Y-m-d H:i') ;
                    }
                })->editColumn('name',function($vendor){
                    return ucwords($vendor->name);
                })
            ->addColumn('actions',function($vendor) {
                $actions = '';
                if(auth::user()->hasPermissionTo('vendor-edit')) {
                    $actions .= '<a href="' . route('admin.vendor.edit', $vendor->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                }else{
                    $actions .= '<a href="' . route('admin.vendor.edit', $vendor->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="flaticon-eye"></i>
                            </a>';
                }

                if(auth::user()->hasPermissionTo('vendor-delete')) {
                    $actions .= '<a href="" value="'.$vendor->id.'" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
                }
                return $actions;

            })
            ->rawColumns(['actions'])
            ->make(true);
    }



    /**
     * Show a page to create vendor.
     *
     * @return View: vendor create page
     */
    public function create(){
        if(Auth::user()->hasPermissionTo('vendor-create')){
            return view('admin.vendor.create');
        }
        else{
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }


    }

    /**
     * Show a edit page for a specified vendor.
     *param: vendor id
     * @return View: vendor edit page
     */
    public function edit( $id){

            $vendor = Vendor::find($id);
            if(Auth::user()->hasPermissionTo('vendor-edit')){
                return view('admin.vendor.edit',compact('vendor'))->with('success', 'You are on read mode.You can update this section');
            }
            elseif (Auth::user()->hasPermissionTo('vendor-list')){
                return view('admin.vendor.view',compact('vendor'))->with('success', 'You are on read mode.You can update this section');

            }
            else{
                return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
            }
    }

    /**
     * method stores vendor information
     *called from vendor create page
     * @return View: vendor listings
     */
    public function store(Request $request){

        $vendor = new Vendor();
        $vendor->name = $request->get('name');
        $vendor->vat = $request->get('vat');
        $vendor->telephone = $request->get('telephone');
        $vendor->email = $request->get('email');
        $vendor->city = $request->get('city');

        if($vendor->save()) {
            activity('Create')
                ->performedOn($vendor)
                ->causedBy(User::where('id',auth()->user()->id)->first())
                ->log('Vendor has been created');
            // Redirect to the home page with success menu
            return Redirect::route('admin.vendor.index')->with('success', 'Vendor Created Successfully');
        }else{
            return Redirect::route('admin.vendor.index')->with('error', 'Something Went Wrong');
        }

    }

    /**
     * method updatess vendor information
     *called from vendor edit page
     * param: vendor id
     * @return View: vendor listings
     */
    public function update($id, Request $request){
        $vendor = Vendor::where('id', $id)->first();
        $vendor->name = $request->get('name');
        $vendor->vat = $request->get('vat');
        $vendor->telephone = $request->get('telephone');
        $vendor->email = $request->get('email');
        $vendor->city = $request->get('city');

        if($vendor->save()) {
            activity('Update')
                ->performedOn($vendor)
                ->causedBy(User::where('id',auth()->user()->id)->first())
                ->log('Vendor has been updated');
            // Redirect to the home page with success menu
            return Redirect::route('admin.vendor.index')->with('success', 'Vendor Updated Successfully');
        }else{
            return redirect()->back()->with('error', 'Something Went Wrong');
        }
    }

    /**
     * method to return modal body for confirm-delete of vendor.
     *param: vendor id
     * @return json data
     */
    public function getModalDelete(Request $request)
    {
        $check = Vendor::where('id', $request->get('id'))->count();
        // Check if we are not trying to delete ourselves

        $body = 'Are you sure you want to delete the Vendor ?';
        return response()->json(['status'=>'success', 'id'=>$request->get('id'), 'body'=>$body]);

    }


    /**
     * method deletes specific vendor.
     *param: vendor id
     * @return view: vendor index page(listing)
     */
    public function destroy($id){
        try{
            activity('Delete')
                ->performedOn(Vendor::where('id',$id)->first())
                ->causedBy(User::where('id',auth()->user()->id)->first())
                ->log('Vendor has been deleted');
            $vendor = Vendor::where('id',$id)->delete();

            return Redirect::route('admin.vendor.index')->with('success', 'Vendor Deleted Successfully');

        }catch(TeacherNotFoundException $e){
            return Redirect::route('admin.vendor.index')->with('error', 'Something Went Wrong');
        }

    }

}
