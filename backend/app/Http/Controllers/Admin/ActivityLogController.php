<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Activity;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Supplier;
use AlphaDirect\User;
use Hash;
use Illuminate\Support\Facades\Auth;
use Validator;
use Session;
use DB;
use Redirect;
use Yajra\DataTables\DataTables;

class ActivityLogController extends Controller
{
    /**
     * Show a list of all Activities.
     *
     * @return View
     */
    public function index()
    {
        if(Auth::user()->hasPermissionTo('activity-list'))
        {
            return view('admin.activityLog.index');
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
        return DataTables::of(Activity::query())

            ->editColumn('subject_type',function ($activity)
            {
                $type = explode("\\",$activity->subject_type);
                $modal_name = $activity->subject_type;

                if($type[count($type)-1] == 'Supplier')
                {
                    $column = 'supplierName';
                }
                elseif($type[count($type)-1] == 'Policy')
                {
                    $column = 'policyNumber';
                }
                elseif($type[count($type)-1] == 'Master')
                {
                    $column = 'value';
                }
                elseif($type[count($type)-1] == 'User')
                {
                    $column = 'firstName';
                }
                elseif($type[count($type)-1] == 'Customer')
                {
                    $column = 'firstName';
                }
                elseif($type[count($type)-1] == 'Activation')
                {
                    $column = 'activation_code';
                }
                elseif($type[count($type)-1] == 'Product')
                {
                    $column = 'name';
                }
                elseif($type[count($type)-1] == 'EmailBroadCasting')
                {
                    $column = 'name';
                }
                elseif($type[count($type)-1] == 'AccountingRules')
                {
                    $column = 'action';
                }
                elseif($type[count($type)-1] == 'Claim')
                {
                    $column = 'claim_number';
                }
                elseif($type[count($type)-1] == 'ClaimAccident')
                {
                    $column = 'claim_id';
                }
                elseif($type[count($type)-1] == 'ClaimQuote')
                {
                    $column = 'claim_id';
                }
                elseif($type[count($type)-1] == 'ClaimAccidentPassenger')
                {
                    $column = 'claim_id';
                }
                elseif($type[count($type)-1] == 'ClaimThirdParty')
                {
                    $column = 'claim_id';
                }
                elseif($type[count($type)-1] == 'ReinsuranceType')
                {
                    $column = 'type_name';
                }
                elseif($type[count($type)-1] == 'RealpayContractInstallments')
                {
                    $column = 'clientNumber';
                }
                elseif($type[count($type)-1] == 'ExpiredPoliciesImportJobs')
                {
                    $column = 'policyNumber';
                }
                else
                {
                    $column = NULL;
                }


                if($column == NULL)
                {
                    return '-';
                }
                else
                {
                    $query = $modal_name::where('id', $activity->subject_id)->pluck($column);
                    return $type[count($type)-1]. ' '.$query;
                }
            })

            ->editColumn('causer_id',function ($activity)
            {
                $user = User::where('id',$activity->causer_id)->first();
                if($user && $user->firstName != null )
                    $firstName = $user->firstName;
                else
                    $firstName = '-';

                if($user && $user->lastName != null )
                    $lastName = $user->lastName;
                else
                    $lastName = '-';
                return $activity?$firstName.' '.$lastName:'-';
            })
            ->editColumn('created_at', function ($activity) {
                if ($activity->created_at != null) {
                    return   \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $activity->created_at)->format('Y-m-d H:i') ;
                }
            })
            ->editColumn('properties',function ()
            {

                return '';
            })
            ->make(true);
    }

}
