<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\AdGroupKycSubmission;
use AlphaDirect\Models\EmployerGroup;
use AlphaDirect\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Carbon\Carbon;
use DB;

class EmployerGroupKycController extends Controller
{
    /**
     * Shows listing of all employer group KYC data
     * @return View KYC upload listing page
     */
    public function index()
    {
        if (Auth::user()->hasPermissionTo('customer-kyc-list'))
        {
            return view('admin.employerGroupKyc.index');
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * Pass data through ajax call for DataTable
     * @return mixed
     */
    public function kycData(\Illuminate\Http\Request $request)
    {
        try{
            ## Read value
            $draw = $request->get('draw');
            $start = $request->get("start");
            $rowperpage = $request->get("length"); // Rows display per page

            $columnIndex_arr = $request->get('order');
            $columnName_arr = $request->get('columns');
            $order_arr = $request->get('order');
            $search_arr = $request->get('search');

            $columnIndex = $columnIndex_arr[0]['column']; // Column index
            $columnName = $columnName_arr[$columnIndex]['data']; // Column name
            $columnSortOrder = $order_arr[0]['dir']; // asc or desc
            $searchValue = trim($search_arr['value']); // Search value

            // Fetch ALL employer groups from employer_groups table only
            $records = EmployerGroup::orderBy('id', 'DESC');

            $totalRecords = $records->count();
            $totalRecordswithFilter = $totalRecords;

            $records = $records->skip($start)
                        ->take($rowperpage)
                        ->get();

            $data_arr = array();
            $sno = $start+1;
            foreach($records as $record){

                $id = $record->id ?? 'N/A';
                $employerGroupName = $record->name ?? 'N/A';

                // Employer Group Name with link
                if($id != 'N/A' && $record->name){
                    $employerGroupName = '<a href="' . route('admin.employer-groups.show', $id) . '" target="_blank">' . $record->name . '</a>';
                }

                // Status - Default to Pending with badge
                $status = '<span class="status-badge pending"><i class="la la-clock"></i> Pending</span>';

                // Compliance - Default to Verification Pending with badge
                $compliance = '<span class="compliance-badge pending"><i class="la la-hourglass-half"></i> Verification Pending</span>';

                // Updated At
                $updated_at = 'N/A';
                if($record->updated_at != null){
                    $updated_at = Carbon::parse($record->updated_at)->format('Y-m-d H:i');
                }

                // Actions - Always show view and edit icons with improved styling
                $actions = '<div class="action-buttons">';
                $actions .= '<a href="' . route('admin.employerGroupKyc.view', $id) . '" class="btn btn-sm btn-primary" title="View Details" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                                <i class="la la-eye"></i> View
                            </a>';

                $actions .= '<a href="' . route('admin.employerGroupKyc.verify', $id) . '" class="btn btn-sm btn-success" title="Edit/Verify" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border: none;">
                                <i class="la la-edit"></i> Edit
                            </a>';
                $actions .= '</div>';

                $data_arr[] = array(
                    "id" => $id,
                    "employer_group_name" => $employerGroupName,
                    "compliance" => $compliance,
                    "status" => $status,
                    "updated_at" => $updated_at,
                    "action" => $actions,
                );

            }

            $response = array(
                "draw" => intval($draw),
                "iTotalRecords" => $totalRecords,
                "iTotalDisplayRecords" => $totalRecordswithFilter,
                "aaData" => $data_arr
            );

            echo json_encode($response);
            exit;

        }catch(\Exception $exception){
            \Log::error('Employer Group KYC Data Error: ' . $exception->getMessage());
            return response()->json(['error' => 'Failed to load data'], 500);
        }
    }

    /**
     * View employer group KYC details (similar to customer KYC view)
     */
    public function view($id)
    {
        if (Auth::user()->hasPermissionTo('customer-kyc-view'))
        {
            $employerGroup = EmployerGroup::where('id', $id)->first();

            if(!$employerGroup){
                return Redirect::back()->with('error', 'Employer group not found');
            }

            // Get latest KYC submission if exists
            $submission = null;
            if($employerGroup->employer_group_id){
                $submission = AdGroupKycSubmission::with(['directors', 'shareholders', 'documents'])
                    ->where('employer_group_id', $employerGroup->employer_group_id)
                    ->orderBy('id', 'DESC')
                    ->first();
            }

            return view('admin.employerGroupKyc.view', compact('employerGroup', 'submission'));
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * Verify/Edit employer group KYC (similar to customer KYC verify)
     */
    public function verify($id)
    {
        if (Auth::user()->hasPermissionTo('customer-kyc-edit'))
        {
            $employerGroup = EmployerGroup::where('id', $id)->first();

            if(!$employerGroup){
                return Redirect::back()->with('error', 'Employer group not found');
            }

            // Get latest KYC submission if exists
            $submission = null;
            if($employerGroup->employer_group_id){
                $submission = AdGroupKycSubmission::with(['directors', 'shareholders', 'documents'])
                    ->where('employer_group_id', $employerGroup->employer_group_id)
                    ->orderBy('id', 'DESC')
                    ->first();
            }

            return view('admin.employerGroupKyc.verify', compact('employerGroup', 'submission'));
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
}

