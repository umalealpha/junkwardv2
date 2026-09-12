<?php
namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Product;
use AlphaDirect\Models\ReinsuranceGroup;
use AlphaDirect\Models\ReinsuranceGroupCoverage;
use AlphaDirect\Models\ProductCoverage;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Yajra\DataTables\DataTables;

class ReinsuranceGroupCovController extends Controller
{
    /**
     * Show a list of all Reinsurance Group Coverage.
     *
     * @return View Reinsurance Group Coverage listing
     */
    public function index()
    {
        if (Auth::user()->hasPermissionTo('reinsurance-coverage-group-list'))
        {
            return view('admin.reinsuranceGroupCoverage.index');
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
        // Show the page

    }

    /**
     * Show a page to create Reinsurance Group Coverage.
     *
     * @return View Reinsurance Group Coverage create
     */
    public function create()
    {
        if (Auth::user()
            ->hasPermissionTo('reinsurance-coverage-group-create'))
        {
            $products = Product::get();
            return view('admin.reinsuranceGroupCoverage.create', compact('products'));
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }

    }

    /**
     * method to store Reinsurance Group Coverage data from create page.
     *
     * @return View Reinsurance Group Coverage listing
     */
    public function store(Request $request)
    {
        $reinsuranceGroup = new ReinsuranceGroup();
        $reinsuranceGroup->group_name = $request->get('group_name');
        $reinsuranceGroup->group_code = $request->get('group_code');
        $reinsuranceGroup->product_id = $request->get('product_id');
        if ($request->status == NULL)
        {
            $reinsuranceGroup->status = "0";
        }
        else
        {
            $reinsuranceGroup->status = "1";
        }
        $saved = $reinsuranceGroup->save();

        if ($request->coverage_name != Null)
        {

            for ($i = 0;$i < count($request->coverage_name);$i++)
            {
                $groupCoverage = new ReinsuranceGroupCoverage();
                $groupCoverage->group_id = $reinsuranceGroup->id;
                $groupCoverage->coverage_id = $request->coverage_id[$i];
                $groupCoverage->coverage_name = $request->coverage_name[$i];
                $groupCoverage->si_premium = $request->si_premium[$i];
                $groupCoverage->ri_limit = $request->ri_limit[$i];
                if ($request->ri_limit[$i] == 3)
                {

                    $groupCoverage->limit_value = $request->limit[$i];
                }
                else
                {
                    $groupCoverage->limit_value = NULL;
                }
                $saved = $groupCoverage->save();
            }
        }
        if ($saved)
        {

            return Redirect::route('admin.reinsuranceGroupCoverage.index')->with('success', 'Reinsurance Group Coverage Created Successfully');
        }
        else
        {
            return Redirect::route('admin.reinsuranceGroupCoverage.index')
                ->with('error', 'Something Went Wrong');
        }

    }

    /*
     * Pass data through ajax call for datatable
     */
    /**
     * @return mixed
     */
    public function data()
    {
        $reinsuranceGroup = ReinsuranceGroup::get(array(
            'id',
            'group_name',
            'product_id',
            'group_code',
            'status',
            'created_at'
        ));
        return DataTables::of($reinsuranceGroup)->editColumn('product_id', function ($reinsuranceGroup)
        {
            $product = Product::where('id', $reinsuranceGroup->product_id)
                ->first(array(
                    'name'
                ));
            return $product ? $product->name : '';
        })->editColumn('status', function ($reinsuranceGroup)
        {
            if ($reinsuranceGroup->status)
            {
                return 'Active';
            }
            else
            {
                return 'In-Active';
            }
        })
            ->addColumn('actions', function ($reinsuranceGroup)
            {
                $actions = '';
                if (Auth::user()->hasPermissionTo('reinsurance-coverage-group-edit'))
                {
                    $actions .= '<a href="' . route('admin.reinsuranceGroupCoverage.edit', $reinsuranceGroup->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                }
                else
                {
                    $actions .= '<a href="' . route('admin.reinsuranceGroupCoverage.edit', $reinsuranceGroup->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="flaticon-eye"></i>
                            </a>';
                }
                if (Auth::user()
                    ->hasPermissionTo('reinsurance-coverage-group-delete'))
                {
                    $actions .= '<a href="" value="' . $reinsuranceGroup->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
                }
                return $actions;
            })->rawColumns(['actions'])
            ->make(true);
    }

    /**
     * Show a page to edit specific Reinsurance Group Coverage.
     *Reinsurance Group Coverage ID ($id)
     * @return View Reinsurance Group Coverage edit page
     */
    public function edit($id)
    {
        $reinsuranceGroup = ReinsuranceGroup::where('id', $id)->first();
        $products = Product::get(array(
            'name',
            'id'
        ));
        $groupCoverages = ReinsuranceGroupCoverage::where('group_id', $reinsuranceGroup->id)
            ->get(array(
                'id',
                'coverage_id',
                'group_id',
                'coverage_name',
                'si_premium',
                'ri_limit',
                'limit_value'
            ));
        if (Auth::user()
            ->hasPermissionTo('reinsurance-coverage-group-edit'))
        {
            return view('admin.reinsuranceGroupCoverage.edit', compact('reinsuranceGroup', 'products', 'groupCoverages'));
        }
        elseif (Auth::user()
            ->hasPermissionTo('reinsurance-coverage-group-list'))
        {
            return view('admin.reinsuranceGroupCoverage.view', compact('reinsuranceGroup', 'products', 'groupCoverages'));
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');

        }

    }

    /**
     * returns product coverages
     * @return JSON
     */
    public function getProductCoverages(Request $request)
    {
        $coverage = ProductCoverage::where('product_id', $request->get('id'))
            ->get(array(
                'coverage_id',
                'name'
            ));
        return response()
            ->json(['status' => 'success', 'coverage' => $coverage]);
    }

    public function show()
    {

    }

    /**
     * method to update specific Reinsurance Group Coverage data from edit page.
     *Reinsurance Group Coverage ID
     * @return View Reinsurance Group Coverage listing
     */
    public function update($id, Request $request)
    {
        $reinsurance = ReinsuranceGroup::where('id', $id)->first();
        $reinsurance->group_name = $request->get('group_name');
        $reinsurance->group_code = $request->get('group_code');
        if ($request->status == NULL)
        {
            $reinsurance->status = "0";
        }
        else
        {
            $reinsurance->status = "1";
        }
        $saved = $reinsurance->save();

        if ($request->groupCoverage_id != Null)
        {
            for ($i = 0;$i < count($request->groupCoverage_id);$i++)
            {
                $groupCoverage = ReinsuranceGroupCoverage::where('id', $request->groupCoverage_id[$i])->first();
                $groupCoverage->si_premium = $request->si_premium[$i];
                $groupCoverage->ri_limit = $request->ri_limit[$i];
                if ($request->ri_limit[$i] == 3)
                {

                    $groupCoverage->limit_value = $request->limit[$i];
                }
                else
                {
                    $groupCoverage->limit_value = NULL;
                }
                $saved = $groupCoverage->save();
            }
        }
        if ($saved)
        {

            return Redirect::route('admin.reinsuranceGroupCoverage.index')->with('success', 'Reinsurance Group Coverage Updated Successfully');
        }
        else
        {
            return Redirect::route('admin.reinsuranceGroupCoverage.index')
                ->with('error', 'Something Went Wrong');
        }

    }

    /**
     * method to return modal body for confirm-delete.
     *
     * @return JSON
     */
    public function getModalDelete(Request $request)
    {
        $body = 'Are you sure you want to delete the Reinsurance Type?';
        return response()->json(['status' => 'success', 'id' => $request->get('id') , 'body' => $body]);

    }

    /**
     *deletes specific Reinsurance Group Coverage
     *param: Reinsurance Group Coverage id ($id)
     * @return Reinsurance Group Coverages listing page
     */
    public function destroy($id)
    {
        try
        {
            ReinsuranceGroupCoverage::where('group_id', $id)->delete();
            $reinsuranceGroup = ReinsuranceGroup::where('id', $id)->delete();
            return Redirect::route('admin.reinsuranceGroupCoverage.index')
                ->with('success', 'Reinsurance Group Coverage Deleted Successfully');

        }
        catch(Exception $e)
        {
            return Redirect::route('admin.reinsuranceGroupCoverage.index')->with('error', 'Something Went Wrong');
        }

    }

}

