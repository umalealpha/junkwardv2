<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Exports\DpoReconsilationExport;
use AlphaDirect\Exports\RealpayReconsilationExport;
use AlphaDirect\Exports\VcsReconsilationExport;
use AlphaDirect\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;

class ReconsilationController extends Controller
{
    public function dpoExport()
    {
        return Excel::download(new DpoReconsilationExport, 'dpoReconsilationExport.csv');
    }

    public function realpayExport()
    {
        return Excel::download(new RealpayReconsilationExport, 'realpayReconsilationExport.csv');
    }

    public function vcsExport()
    {
        return Excel::download(new VcsReconsilationExport, 'vcsReconsilationExport.csv');
    }

}
