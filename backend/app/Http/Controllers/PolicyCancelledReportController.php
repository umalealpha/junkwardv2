<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\PolicyActivateCancelledDate;
use Illuminate\Http\Request;
use DataTables;
use DB;

class PolicyCancelledReportController extends Controller
{
    public function policyCanceledReport()
    {
        return view('admin.reports.policy_canceled_report');
    }

    public function policyCanceledReportData()
    {

        // $policies = PolicyActivateCancelledDate::latest()->get();
        $policies = DB::table('policyactivatecancelleddates')
            ->select(DB::raw('policyNumber, activated_date, cancelled_date, created_at'))
            // ->where('activated_date', '<>', NULL)
            // ->where('cancelled_date', '<>', NULL)
            ->where('policyNumber', '<>', NULL)
            ->groupBy('policyNumber')
            ->orderBy('created_at', 'desc')
            ->get();

        return DataTables::of($policies)->make(true);
    }
}
