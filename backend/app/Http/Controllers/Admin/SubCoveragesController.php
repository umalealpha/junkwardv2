<?php
namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\SubCoverages;
use AlphaDirect\Coverage;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Redirect;
use Carbon;

class SubCoveragesController extends Controller
{
    /**
     * Show a list of all Sub Coverages.
     *
     * @return View Sub Coverages listing page
     */
    public function index()                       
    {
        if (Auth::user()->hasPermissionTo('sub-coverages-list'))
        {
            $coverages = Coverage::get(array('id','name'));

            return view('admin.subCoverages.index', compact('coverages'));
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }


    /*
    * Pass data through ajax call
    */
    /**
     * @return mixed
     */
    public function data(Request $request)
    {   
        $query = SubCoverages::orderBy('created_at', 'DESC');
        if ($request->coverages_filter != - 1)
        {
            $query->where('coverage_id', $request->coverages_filter);
        }

        $subCoverage = $query->get(array(
            'id',
            'code',
            'name',
            'coverage_id',
            'effective_from',
            'effective_to',
            'status',
            'created_at'
        ));

     
        return DataTables::of($subCoverage)
            ->editColumn('name', function ($subCoverage)
            {
                if ($subCoverage->name)
                {
                   return ucwords($subCoverage->name);
                }
                else
                {
                    return '--';
                }
            })

            ->editColumn('coverage_id', function ($subCoverage)
            {
                // $coverage = $subCoverage['Coverages']['name'] ;
                $coverage = $subCoverage->Coverages->name ;
                if ($coverage)
                {
                   return ucwords($coverage);
                }
                else
                {
                    return '--';
                }
            })
            
            ->editColumn('status', function ($subCoverage)
            {
                if ($subCoverage->status)
                {
                    return 'Active';
                }
                else
                {
                    return 'In-Active';
                }
            })

            ->editColumn('effective_from', function ($subCoverage) {
                return Carbon::parse($subCoverage->effective_from)->format('d-m-Y');

            })

            ->editColumn('effective_to', function ($subCoverage) {
                return Carbon::parse($subCoverage->effective_to)->format('d-m-Y');

            })
            
            ->editColumn('created_at', function ($subCoverage)
            {
                return $subCoverage
                    ->created_at
                    ->diffForHumans();
            })
            
            ->addColumn('actions', function ($subCoverage)
            {
                $actions = '';
                if (Auth::user()->hasPermissionTo('sub-coverages-edit'))
                {
                    $actions .= '<a href="' . route('admin.subCoverages.edit', $subCoverage->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                }
                else
                {
                    $actions .= '<a href="' . route('admin.subCoverages.edit', $subCoverage->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="flaticon-eye"></i>
                            </a>';
                }
                if (Auth::user()
                    ->hasPermissionTo('sub-coverages-delete'))
                {
                    $actions .= '<a href="" value="' . $subCoverage->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
                }

                return $actions;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function create()
    {
        $coverages = Coverage::get(array('id','name'));
        return view('admin.subCoverages.create', compact('coverages'));
    }

    public function store(Request $request)
    {
        $coverage = new SubCoverages();
        $coverage->name = $request->name;
        $coverage->code = $request->code;
        $coverage->coverage_id = $request->coverage;
        $coverage->effective_from = $request->effectivefrom;
        $coverage->effective_to = $request->effectiveto;
        if ($request->status == NULL)
        {
            $coverage->status = "0";
        }
        else
        {
            $coverage->status = "1";
        }
        if($coverage->save()) {         
            return Redirect::route('admin.subCoverages.index')->with('success', 'Sub Coverage Created Successfully');
        }else{
            return Redirect::route('admin.subCoverages.index')->with('error', 'Something Went Wrong');
        }
    }

    public function edit($id)
    {
        $subcoverages = SubCoverages::where('id', $id)->first();
        $coverages = Coverage::get(array('id','name'));
        return view('admin.subCoverages.edit', compact('subcoverages','coverages'));
    }

    public function update($id, Request $request)
    {
        $coverage = SubCoverages::where('id', $id)->first();
        $coverage->name = $request->get('name');
        $coverage->code = $request->get('code');
        $coverage->coverage_id = $request->get('coverage');
        $coverage->effective_from = $request->get('effectivefrom');
        $coverage->effective_to = $request->get('effectiveto');
       
        if ($request->status == NULL)
        {
            $coverage->status = "0";
        }
        else
        {
            $coverage->status = "1";
        }
        if ($coverage->save())
        {

            return Redirect::route('admin.subCoverages.index')->with('success', 'Sub Coverage Updated Successfully');
        }
        else
        {
            return redirect()->back()->with('error', 'Something Went Wrong');
        }
    }
  
    public function getModalDelete(Request $request)
    {
        $body = 'Are you sure you want to delete the Sub Coverage?';
        return response()->json(['status' => 'success', 'id' => $request->get('id') , 'body' => $body]);

    }

   
    public function destroy($id)
    {
        try
        {
            $delete = SubCoverages::where('id', $id)->delete();

            return Redirect::route('admin.subCoverages.index')
                ->with('success', 'Sub Coverage Deleted Successfully');

        }
        catch(Exception $e)
        {
            return Redirect::route('admin.subCoverages.index')->with('error', 'Something Went Wrong');
        }

    }

}

