<?php
namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Models\SpecifiedCoveragesItems;
use AlphaDirect\SubCoverages;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Redirect;
use Carbon;

class SpecifiedCoveragesItemsController extends Controller
{
    /**
     * Show a list of all Specified Coverages Items.
     *
     * @return View Specified Coverages Items listing page
     */
    public function index()
    {
        if (Auth::user()->hasPermissionTo('specified-coverages-items-list'))
        {
           
            $subCoverages = SubCoverages::get(array('id','name'));
            return view('admin.specifiedCoveragesItems.index', compact('subCoverages'));
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

        $query = SpecifiedCoveragesItems::orderBy('created_at', 'DESC');

        if ($request->sub_coverages_filter != - 1)
        {
            $query->where('sub_coverage_id', $request->sub_coverages_filter);
        }

        $specifiedCoveragesItems = $query->limit(20)->get(array(
            'id',
            'specified_code',
            'specified_name',
            'rate',
            'sub_coverage_id',
            'effective_from',
            'effective_to',
            'created_at'
        ));

        return DataTables::of($specifiedCoveragesItems)
            ->addIndexColumn()
           ->editColumn('sub_coverage_id', function ($specifiedCoveragesItems)
            {
                $subCoverage = $specifiedCoveragesItems->SubCoverages->name ;
                if ($subCoverage)
                {
                   return ucwords($subCoverage);
                }
                else
                {
                    return '--';
                }
            })

            ->editColumn('effective_from', function ($specifiedCoveragesItems) {
                return Carbon::parse($specifiedCoveragesItems->effective_from)->format('d-m-Y');

            })

            ->editColumn('effective_to', function ($specifiedCoveragesItems) {
                return Carbon::parse($specifiedCoveragesItems->effective_to)->format('d-m-Y');

            })

            ->editColumn('created_at', function ($specifiedCoveragesItems)
            {
                return $specifiedCoveragesItems
                    ->created_at
                    ->diffForHumans();
            })

            ->addColumn('actions', function ($specifiedCoveragesItems)
            {
                $actions = '';
                if (Auth::user()->hasPermissionTo('specified-coverages-items-edit'))
                {
                    $actions .= '<a href="' . route('admin.specifiedCoveragesItems.edit', $specifiedCoveragesItems->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                }
                else
                {
                    $actions .= '<a href="' . route('admin.specifiedCoveragesItems.edit', $specifiedCoveragesItems->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="flaticon-eye"></i>
                            </a>';
                }
                if (Auth::user()
                    ->hasPermissionTo('specified-coverages-items-delete'))
                {
                    $actions .= '<a href="" value="' . $specifiedCoveragesItems->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-cov-delete" title="Delete">
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
        $coverages = SubCoverages::get(array('id','name'));
        return view('admin.specifiedCoveragesItems.create', compact('coverages'));
    }

    public function store(Request $request)
    {
        $coverage = new SpecifiedCoveragesItems();
        $coverage->specified_name = $request->name;
        $coverage->specified_code = $request->code;
        $coverage->rate = $request->rate;
        $coverage->sub_coverage_id = $request->subcoverage;
        $coverage->effective_from = $request->effectivefrom;
        $coverage->effective_to = $request->effectiveto;
       
        if($coverage->save()) {         
            return Redirect::route('admin.specifiedCoveragesItems.index')->with('success', 'Specified Coverages Items Created Successfully');
        }else{
            return Redirect::route('admin.specifiedCoveragesItems.index')->with('error', 'Something Went Wrong');
        }
    }

    public function edit($id)
    {
        $specified = SpecifiedCoveragesItems::where('id', $id)->first();
        $coverages = SubCoverages::get(array('id','name'));
        return view('admin.specifiedCoveragesItems.edit', compact('specified','coverages'));
    }

    public function update($id, Request $request)
    {
        $coverage = SpecifiedCoveragesItems::where('id', $id)->first();
        $coverage->specified_name = $request->get('name');
        $coverage->specified_code = $request->get('code');
        $coverage->rate = $request->get('rate');
        $coverage->sub_coverage_id = $request->get('subcoverage');
        $coverage->effective_from = $request->get('effectivefrom');
        $coverage->effective_to = $request->get('effectiveto');
       
        if ($coverage->save())
        {

            return Redirect::route('admin.specifiedCoveragesItems.index')->with('success', 'Specified Coverages Items Updated Successfully');
        }
        else
        {
            return redirect()->back()->with('error', 'Something Went Wrong');
        }
    }

    public function getModalDelete(Request $request)
    {
        $body = 'Are you sure you want to delete the Specified Coverages Items?';
        return response()->json(['status' => 'success', 'id' => $request->get('id') , 'body' => $body]);

    }

   
    public function destroy($id)
    {
        try
        {
            $delete = SpecifiedCoveragesItems::where('id', $id)->delete();

            return Redirect::route('admin.specifiedCoveragesItems.index')
                ->with('success', 'Specified Coverages Items Deleted Successfully');

        }
        catch(Exception $e)
        {
            return Redirect::route('admin.specifiedCoveragesItems.index')->with('error', 'Something Went Wrong');
        }

    }
}

