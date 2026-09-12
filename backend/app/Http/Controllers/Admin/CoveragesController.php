<?php
namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Coverage;
use AlphaDirect\Product;
use AlphaDirect\Models\ProductCoverage;
use AlphaDirect\Models\ReinsuranceFormula;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Redirect;
use Carbon;

class CoveragesController extends Controller
{
    /**
     * Show a list of all Coverages.
     *
     * @return View Coverages listing page
     */
    public function index()
    {
        if (Auth::user()->hasPermissionTo('coverages-list'))
        {
            
            return view('admin.coverages.index');
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
    public function data()
    {
        $coverage = Coverage::get(array(
            'id',
            'code',
            'name',
            'effective_from',
            'effective_to',
            'status',
            'created_at'
        ));
        
        return DataTables::of($coverage)
            ->editColumn('name', function ($coverage)
            {
                if ($coverage->name)
                {
                   return ucwords($coverage->name);
                }
                else
                {
                    return '--';
                }
            })
            
            ->editColumn('effective_from', function ($coverage) {               
                return Carbon::parse($coverage->effective_from)->format('d-m-Y');
            })

            ->editColumn('effective_to', function ($coverage) {
                return Carbon::parse($coverage->effective_to)->format('d-m-Y');

            })

            ->editColumn('status', function ($coverage)
            {
                if ($coverage->status)
                {
                    return 'Active';
                }
                else
                {
                    return 'In-Active';
                }
            })
            
            ->editColumn('created_at', function ($coverage)
            {
                return $coverage
                    ->created_at
                    ->diffForHumans();
            })
            
            ->addColumn('actions', function ($coverage)
            {
                $actions = '';
                if (Auth::user()->hasPermissionTo('coverages-edit'))
                {
                    $actions .= '<a href="' . route('admin.coverages.edit', $coverage->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                }
                else
                {
                    $actions .= '<a href="' . route('admin.coverages.edit', $coverage->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="flaticon-eye"></i>
                            </a>';
                }
                if (Auth::user()
                    ->hasPermissionTo('coverages-delete'))
                {
                    $actions .= '<a href="" value="' . $coverage->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
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
        return view('admin.coverages.create');
    }

    public function store(Request $request)
    {
        $coverage = new Coverage();
        $coverage->name = $request->coveragename;
        $coverage->code = $request->code;
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
            return Redirect::route('admin.coverages.index')->with('success', 'Coverage Created Successfully');
        }else{
            return Redirect::route('admin.coverages.index')->with('error', 'Something Went Wrong');
        }
    }

    public function edit($id)
    {
        $coverage = Coverage::where('id', $id)->first();
        return view('admin.coverages.edit', compact('coverage'));
    }
  
    public function update($id, Request $request)
    {
        $coverage = Coverage::where('id', $id)->first();
        $coverage->name = $request->get('coveragename');
        $coverage->code = $request->get('code');
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

            return Redirect::route('admin.coverages.index')->with('success', 'Coverage Updated Successfully');
        }
        else
        {
            return redirect()->back()->with('error', 'Something Went Wrong');
        }
    }


    public function getModalDelete(Request $request)
    {
        $body = 'Are you sure you want to delete the Coverage?';
        return response()->json(['status' => 'success', 'id' => $request->get('id') , 'body' => $body]);

    }

   
    public function destroy($id)
    {
        try
        {
            $delete = Coverage::where('id', $id)->delete();

            return Redirect::route('admin.coverages.index')
                ->with('success', 'Coverage Deleted Successfully');

        }
        catch(Exception $e)
        {
            return Redirect::route('admin.coverages.index')->with('error', 'Something Went Wrong');
        }

    }


}

