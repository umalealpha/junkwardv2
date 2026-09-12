<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\vcsEventLog;
use AlphaDirect\VcsNewTransaction;
use AlphaDirect\VcsTransaction;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Yajra\DataTables\DataTables;

class VcsEventLogController extends Controller
{
    public function index(){
        if (Auth::user()->hasPermissionTo('vcs-event-log-view'))
        {
            return view("admin.vcsEventLog.index");
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    public function data(){
        $data = vcsEventLog::get();
        return DataTables::of($data)
            ->editColumn('created_at', function ($data) {
                if ($data->created_at != null) {
                    return   \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $data->created_at)->format('Y-m-d H:i') ;
                }
            })
            ->editColumn('policyNumber', function ($data)
            {
                $vcsTrans = \AlphaDirect\VcsNewTransaction::where('reference',$data->referenceNumber)->first();
                if($vcsTrans != null)
                    return $vcsTrans->policyNumber;
                else
                    return 'N/A';
            })
            ->rawColumns(['policyNumber'])
            ->make(true);
    }
}
