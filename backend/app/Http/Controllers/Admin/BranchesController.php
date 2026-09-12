<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Branch;
use AlphaDirect\User;
use AlphaDirect\Vendor;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Yajra\DataTables\Contracts\DataTable;
use Yajra\DataTables\Facades\DataTables;

class BranchesController extends Controller
{
    /**
     * Show a list of all .
     *
     * @return View
     */
    public function index()
    {
        if(Auth::user()->hasPermissionTo('branch-list'))
        {
            return view('admin.branch.index');
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
        $branch = Branch::get(['id','name','created_at']);
        return DataTables::of($branch)
                ->addColumn('created_at', function ($branch) {
                    if ($branch->created_at != null) {
                        return   \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $branch->created_at)->format('Y-m-d H:i') ;
                    }
                })->editColumn('name', function($branch){
                    if($branch->name !=null)
                    {
                        return ucwords($branch->name);
                    }
                })
            ->addColumn('actions',function($branch)
            {
                $actions = '';
                if(Auth::user()->can('branch-edit'))
                {
                    $actions .= '<a href="'. route('admin.branch.edit', $branch->id) .'" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                }
                else
                {
                    $actions .= '<a href="'. route('admin.branch.edit', $branch->id) .'" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="flaticon-eye"></i>
                            </a>';
                }
                if(Auth::user()->hasPermissionTo('branch-delete'))
                {
                    $actions .='<a href="" value="'.$branch->id.'" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                                </a>';
                }
                return $actions;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }


    /**
     * Show a page to create branch.
     *
     * @return View
     */
    public function create()
    {
        if(Auth::user()->hasPermissionTo('branch-create'))
        {
            return view('admin.branch.create');
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }


    /**
     * Method to store data from create page.
     *
     * @return View
     */
    public function store(Request $request)
    {
        $branch = new Branch();
        $branch->name = $request->get('name');
        if($branch->save())
        {
            activity('Create')
                ->performedOn($branch)
                ->causedBy(User::where('id',Auth()->user()->id)->first())
                ->log('Branch has been created');
            // Redirect to the home page with success menu
            return Redirect::route('admin.branch.index')->with('success', 'Branch Created Successfully');
        }
        else
        {
            return Redirect::route('admin.branch.index')->with('error', 'Something Went Wrong');
        }
    }

    /**
     * Show a page to edit branch.
     *
     * @return View
     */
    public function edit( $id)
    {
        if(Auth::user()->hasPermissionTo('branch-edit'))
        {
            $branch = Branch::find($id);
            return view('admin.branch.edit',compact('branch'));
        }
        elseif(Auth::user()->hasPermissionTo('branch-list'))
        {
            $branch = Branch::find($id);
            return view('admin.branch.view',compact('branch'));
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * method to update branch data from edit page.
     *
     * @return View
     */
    public function update($id, Request $request)
    {
        $branch = Branch::where('id', $id)->first();
        $branch->name = $request->get('name');
        if($branch->save())
        {
            activity('Update')
                ->performedOn($branch)
                ->causedBy(User::where('id',Auth()->user()->id)->first())
                ->log('Branch has been updated');
            // Redirect to the home page with success menu
            return Redirect::route('admin.branch.index')->with('success', 'Branch Updated Successfully');
        }
        else
        {
            return Redirect()->back()->with('error', 'Something Went Wrong');
        }
    }


    /**
     * method to return modal body for confirm-delete of branch.
     *
     * @return View
     */
    public function getModalDelete(Request $request)
    {
        $check = Branch::where('id', $request->get('id'))->count();
        // Check if we are not trying to delete ourselves
        $body = 'Are you sure you want to delete the Branch ?';
        return response()->json(['status'=>'success', 'id'=>$request->get('id'), 'body'=>$body]);
        /*return view('admin.productPlan.index');*/
    }

    /**
     * method to delete specific branch.
     *
     * @return View
     */
    public function destroy($id)
    {
        try
        {
            activity('Delete')
                ->performedOn(Branch::where('id',$id)->first())
                ->causedBy(User::where('id',Auth()->user()->id)->first())
                ->log('Branch has been deleted');
            $branch = Branch::where('id',$id)->delete();
            return Redirect::route('admin.branch.index')->with('success', 'Branch Deleted Successfully');
        }
        catch(TeacherNotFoundException $e)
        {
            return Redirect::route('admin.branch.index')->with('error', 'Something Went Wrong');
        }
    }
}
