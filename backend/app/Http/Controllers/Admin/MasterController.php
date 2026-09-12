<?php

namespace AlphaDirect\Http\Controllers\Admin;
use Http\Client\Exception;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Product;
use AlphaDirect\Region;
use AlphaDirect\User;
use AlphaDirect\Role;
use AlphaDirect\Notifications;
use AlphaDirect\KYC;

use AlphaDirect\Master;
use AlphaDirect\GlassClaim;
use AlphaDirect\OTP;
use Hash;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

use Validator;
use Session;
use Auth;
use DB;
use Redirect;
use Yajra\DataTables\DataTables;

class MasterController extends Controller
{
    /**
     * Show a list of all master Data
     *
     * @return View master Data index page
     */
    public function index()
    {
        if(auth::user()->hasPermissionTo('master-list'))
        {
            return view('admin.masterData.index');
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
        $master = Master::get(array('id','key', 'value', 'created_at'));
        //dd($master);
        return DataTables::of($master)
            ->editColumn('status', function (Master $t)
            {
                if ($t->is_active == 1)
                {
                    return 'Active';
                } else
                {
                    return 'In-Active';
                }
            })
            ->editColumn('created_at', function ($master)
            {
                return $master->created_at->diffForHumans();
            })
            ->addColumn('actions', function ($master)
            {
                $actions ='';
                if(auth::user()->hasPermissionTo('master-edit'))
                {
                    $actions .= '<a href="'. route('admin.masterData.edit', $master->id) .'" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>';
                }
                else
                {
                    $actions .= '<a href="'. route('admin.masterData.edit', $master->id) .'" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="flaticon-eye"></i>';
                }
                return $actions;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    /**
     * Show a page to master Data create
     *
     * @return View master Data create page
     */
    public function create()
    {
        if(auth::user()->hasPermissionTo('master-create'))
        {
            return view('admin.masterData.create');
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * method to store accounts data from master Data page.
     *
     * @return View master Data index page
     */
    public function store(Request $request)
    {
        $master = new Master();
        $master->key = $request->get('key_name');
        $master->value = $request->get('value');
        if ($master->save())
        {
            activity('Master Data')
                ->performedOn($master)
                ->causedBy(User::where('id',auth()->user()->id)->first())
                ->log('Master Data Created');
            return Redirect::route('admin.masterData.index')->with('success', 'Master Data Created Successfully');
        }
        else
        {
            return Redirect::route('admin.masterData.index')->with('error', 'Something Went Wrong');
        }
    }

    /**
     * Show a page to edit specific master Data
     * param: master Data id ($id)
     * @return View master Data view page
     */
    public function edit($id)
    {
        $master= Master::where('id',$id)->first();
        if(auth::user()->hasPermissionTo('master-edit'))
        {
           return view('admin.masterData.edit',compact('master'));
        }
        elseif(auth::user()->hasPermissionTo('master-list'))
        {
            return view('admin.masterData.view',compact('master'));
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * method to update accounts data from edit page.
     *param: master Data id($id)
     * @return master Data index page
     */
    public function update($id, Request $request)
    {
        $master = Master::where('id', $id)->first();
        $master->key = $request->get('key_name');
        $master->value = $request->get('value');
        if($master->save())
        {
            // Redirect to the home page with success menu
            activity('Master Data')
                ->performedOn($master)
                ->causedBy(User::where('id',auth()->user()->id)->first())
                ->log('Master Data Updated');
            return Redirect::route('admin.masterData.index')->with('success', 'Master Data Updated Successfully');
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
        $body = 'Are you sure you want to delete the Master data ?';
        return response()->json(['status'=>'success', 'id'=>$request->get('id'), 'body'=>$body]);
    }


    /**
     *deletes specific Master Data accounts
     *param: Master Data id ($id)
     * @return Master Data index page
     */
    public function destroy($id)
    {
        try
        {
            activity('Master Data')
            ->performedOn(Master::where('id',$id)->first())
            ->causedBy(User::where('id',auth()->user()->id)->first())
            ->log('Master Data Deleted');
           Master::where('id',$id)->delete();
           return Redirect::route('admin.masterData.index')->with('success', 'Master Data Deleted Successfully');
        }
        catch(Exception $e)
        {
            return Redirect::route('admin.masterData.index')->with('error', 'Something Went Wrong');
        }
    }

}
