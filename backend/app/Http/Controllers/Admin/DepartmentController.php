<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Department;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Product;
use AlphaDirect\Region;
use AlphaDirect\UserProfile;
use Illuminate\Http\Request;

use AlphaDirect\User;
use AlphaDirect\Role;
use AlphaDirect\Notifications;
use AlphaDirect\KYC;
use AlphaDirect\Policy;
use AlphaDirect\GlassClaim;
use AlphaDirect\OTP;
use Hash;
use Illuminate\Support\Facades\Auth;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

use Validator;
use Session;
use DB;
use Redirect;
use Yajra\DataTables\DataTables;

class DepartmentController extends Controller
{

    /**
     * Show a list of all department
     *
     * @return View department index page
     */
    public function index()
    {
        if(Auth::user()->hasPermissionTo('department-list'))
        {
            return view('admin.department.index');
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
        $department = Department::get(array('id','name','status','created_at'));
        return DataTables::of($department)
            ->editColumn('status',function($region)
            {
                if($region->status)
                {
                    return 'Active';
                }
                else
                {
                    return 'In-Active';
                }
            })

            ->editColumn('created_at',function($department)
            {
                return $department->created_at->diffForHumans();
            })
            ->addColumn('actions',function($department)
            {
                $actions = '';
                if(Auth::user()->haspermissionTo('department-edit'))
                {
                    $actions .= '<a href="' . route('admin.department.edit', $department->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                }
                else
                {
                    $actions .= '<a href="' . route('admin.department.edit', $department->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="flaticon-eye"></i>
                            </a>';
                }
                if(Auth::user()->haspermissionTo('department-delete'))
                {
                    $actions .='<a href="" value="'.$department->id.'" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
                }
                return $actions;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }


    /**
     * Show a page to department create
     *
     * @return View department create page
     */
    public function create()
    {
        if(Auth::user()->hasPermissionTo('department-create'))
        {
            return view('admin.department.create');
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * Show a page to edit specific department
     *
     * @return View department view page
     */
    public function edit(Department $department)
    {
        if(Auth::user()->hasPermissionTo('department-edit'))
            return view('admin.department.edit',compact('department'));
        elseif(Auth::user()->hasPermissionTo('department-list'))
            return view('admin.department.view',compact('department'));
        else
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
    }

    /**
     * method to store accounts data from department page.
     *
     * @return View department index page
     */
    public function store(Request $request)
    {
        $department = new Department();
        $department->name = $request->get('name');
        if ($request->status == NULL) {
            $department->status = "0";
        } else {
            $department->status = "1";
        }
        if ($department->save())
        {
            activity('Department')
                ->performedOn($department)
                ->causedBy(User::where('id', Auth()->user()->id)->first())
                ->log('Department Created');
            // Redirect to the home page with success menu
            return Redirect::route('admin.department.index')->with('success', 'New Department Created Successfully');
        }
        else
        {
            return Redirect::route('admin.department.index')->with('error', 'Something Went Wrong');
        }
    }

    /**
     * method to update accounts data from edit page.
     *param: department id
     * @return department index page
     */
    public function update($id, Request $request)
    {
        $department = Department::where('id', $id)->first();
        $department->name = $request->get('name');
        if($request->status == NULL)
        {
            $department->status="0";
        }
        else
        {
            $department->status="1";
        }
        if($department->save())
        {
            activity('Department')
                ->performedOn($department)
                ->causedBy(User::where('id',Auth()->user()->id)->first())
                ->log('Department Updated');
            // Redirect to the home page with success menu
            return Redirect::route('admin.department.index')->with('success', 'Department Updated Successfully');
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
        $count = UserProfile::where('department_id', $request->get('id'))->count();
        if($count > 0)
        {
            $body = '<span style="font-weight:bold">' .$count. ' </span>user(s) under this department.Can not delete this Department'.'<br>';
            return response()->json(['status'=>'error', 'body'=>$body]);
        }
        else
        {
            $body = 'Are you sure you want to delete the  Department ?';
            return response()->json(['status'=>'success', 'id'=>$request->get('id'), 'body'=>$body]);
        }
    }

    /**
     *deletes specific department accounts
     *param: department id ($id)
     * @return department index page
     */
    public function destroy($id)
    {
        try
        {
            activity('Department')
                ->performedOn(Department::where('id',$id)->first())
                ->causedBy(User::where('id',Auth()->user()->id)->first())
                ->log('Department Deleted');
            $department = Department::where('id',$id)->delete();
            return Redirect::route('admin.department.index')->with('success', 'Department Deleted Successfully');
        }
        catch(TeacherNotFoundException $e)
        {
            return Redirect::route('admin.department.index')->with('error', 'Something Went Wrong');
        }

    }
}
