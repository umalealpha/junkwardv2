<?php
namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Lookup;
use AlphaDirect\Product;
use AlphaDirect\Models\ProductCoverage;
use AlphaDirect\Productplan;
use AlphaDirect\Models\ReinsuranceGroup;
use AlphaDirect\Models\ReinsuranceGroupCoverage;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\ReinsuranceTreaty;
use AlphaDirect\Models\ReinsuranceType;
use AlphaDirect\Models\ReinsuranceFormula;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Redirect;

class ReinsuranceFormulaController extends Controller
{
    /**
     * Show a list of all Reinsurance Formula.
     *
     * @return View Reinsurance Formula list
     */
    public function index()
    {
        if (Auth::user()->hasPermissionTo('reinsurance-formula-list'))
        {
            return view('admin.reinsuranceFormula.index');
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }

    }

    /**
     * Show a page to create Reinsurance Formula.
     *
     * @return View Reinsurance Formula create page
     */
    public function create()
    {
        if (Auth::user()
            ->hasPermissionTo('reinsurance-formula-create'))
        {
            $products = Product::where('status', 1)->get();
            $reinsuranceTypes = ReinsuranceType::get();
            $types = Lookup::where('key', 'reinsurance_formula_key')->get(array(
                'value',
                'id'
            ));

            return view('admin.reinsuranceFormula.create', compact('products', 'reinsuranceTypes', 'types'));
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
        $reinsuranceformula = ReinsuranceFormula::get(array(
            'id',
            'formula_name',
            'formula_code',
            'product_id',
            'reinsurance_type_id',
            'type_id',
            'status',
            'created_at'
        ));

        return DataTables::of($reinsuranceformula)->editColumn('product_id', function ($reinsuranceformula)
        {
            $product = Product::where('id', $reinsuranceformula->product_id)
                ->first(array(
                    'name'
                ));
            return $product ? $product->name : '';
        })
            ->editColumn('reinsurance_type_id', function ($reinsuranceformula)
            {
                $reinsuranceType = ReinsuranceType::where('id', $reinsuranceformula->reinsurance_type_id)
                    ->first(array(
                        'type_name'
                    ));
                return $reinsuranceType ? $reinsuranceType->type_name : '';
            })
            ->editColumn('type_id', function ($reinsuranceformula)
            {
                $type = Lookup::where('id', $reinsuranceformula->type_id)
                    ->first(array(
                        'value'
                    ));
                return $type ? $type->value : '';
            })->editColumn('status', function ($reinsuranceformula)
            {
                if ($reinsuranceformula->status)
                {
                    return 'Active';
                }
                else
                {
                    return 'In-Active';
                }
            })->editColumn('created_at', function ($reinsuranceformula)
            {
                return $reinsuranceformula
                    ->created_at
                    ->diffForHumans();
            })->addColumn('actions', function ($reinsuranceformula)
            {
                $actions = '';
                if (Auth::user()->hasPermissionTo('reinsurance-formula-edit'))
                {
                    $actions .= '<a href="' . route('admin.reinsuranceFormula.edit', $reinsuranceformula->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                }
                else
                {
                    $actions .= '<a href="' . route('admin.reinsuranceFormula.edit', $reinsuranceformula->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="flaticon-eye"></i>
                            </a>';
                }
                if (Auth::user()
                    ->hasPermissionTo('reinsurance-formula-delete'))
                {
                    $actions .= '<a href="" value="' . $reinsuranceformula->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
                }
                return $actions;
            })->rawColumns(['actions'])
            ->make(true);
    }

    public function getGroup(Request $request)
    {
        $groups = ReinsuranceGroup::where('product_id', $request->get('id'))
            ->get(array(
                'id',
                'group_name'
            ));
        return response()
            ->json(['status' => 'success', 'groups' => $groups]);
    }

    /**
     * method to store Reinsurance Formula data from create page.
     *
     * @return View Reinsurance Formula list
     */
    public function store(Request $request)
    {
        $reinsuranceformula = new ReinsuranceFormula();
        $reinsuranceformula->formula_name = $request->get('formulaname');
        $reinsuranceformula->formula_code = $request->get('formulacode');
        $reinsuranceformula->reinsurance_type_id = $request->get('reinsurancetype');
        $reinsuranceformula->type_id = $request->get('type');
        $reinsuranceformula->product_id = $request->get('product');
        if ($request->status == NULL)
        {
            $reinsuranceformula->status = "0";
        }
        else
        {
            $reinsuranceformula->status = "1";
        }
        $saved = $reinsuranceformula->save();
        if ($request->group_name != Null)
        {
            $reinsuranceformula = ReinsuranceFormula::where('id', $reinsuranceformula->id)->first();
            $reinsuranceformula->group_id = $request->group_name;
            $reinsuranceformula->operator = $request->operator;
            if ($request->get('type') == 35)
            {
                $reinsuranceformula->vehicle_type = $request->vehicle_type;
            }
            else
            {
                $reinsuranceformula->vehicle_type = NULL;
            }
            $reinsuranceformula->si_allocation = isset($request->si_allocation) ? $request->si_allocation: NULL;
            $reinsuranceformula->percentage = isset($request->percentage) ? $request->percentage : NULL;
            $reinsuranceformula->date_from = $request->datefrom;
            $reinsuranceformula->date_to = $request->dateto;
            $saved = $reinsuranceformula->save();
        }

        if ($saved)
        {
            return Redirect::route('admin.reinsuranceFormula.index')->with('success', 'Reinsurance Type Created Successfully');
        }
        else
        {
            return Redirect::route('admin.reinsuranceFormula.index')->with('error', 'Something Went Wrong');
        }
    }

    /**
     * method to update Reinsurance Formula data from edit page.
     *
     * @return View Reinsurance Formula list
     */
    public function update($id, Request $request)
    {
        $reinsuranceformula = ReinsuranceFormula::where('id', $id)->first();
        $reinsuranceformula->formula_name = $request->get('formulaname');
        $reinsuranceformula->formula_code = $request->get('formulacode');
        $reinsuranceformula->reinsurance_type_id = $request->get('reinsurancetype');
        $reinsuranceformula->type_id = $request->get('type');
        if ($request->status == NULL)
        {
            $reinsuranceformula->status = "0";
        }
        else
        {
            $reinsuranceformula->status = "1";
        }
        $saved = $reinsuranceformula->save();
        if ($request->group_name != Null)
        {
            $reinsuranceformula = ReinsuranceFormula::where('id', $reinsuranceformula->id)->first();
            $reinsuranceformula->group_id = $request->group_name;
            $reinsuranceformula->operator = $request->operator;
            if ($request->get('type') == 35)
            {
                $reinsuranceformula->vehicle_type = $request->vehicle_type;
            }
            else
            {
                $reinsuranceformula->vehicle_type = NULL;
            }
            $reinsuranceformula->si_allocation = isset($request->si_allocation) ? $request->si_allocation: NULL;
            $reinsuranceformula->percentage = isset($request->percentage) ? $request->percentage : NULL;
            $reinsuranceformula->date_from = $request->datefrom;
            $reinsuranceformula->date_to = $request->dateto;
            $saved = $reinsuranceformula->save();
        }
        if ($saved)
        {
            return Redirect::route('admin.reinsuranceFormula.index')->with('success', 'Reinsurance Type Updated Successfully');
        }
        else
        {
            return redirect()->back()->with('error', 'Something Went Wrong');
        }
    }

    /**
     * Show a page to edit specific reinsurance formula.
     *
     * @return View reinsurance formula edit page
     */
    public function edit($id)
    {
        $groups = ReinsuranceGroup::get(array(
            'id',
            'group_name'
        ));
        $products = Product::where('status', 1)->get();
        $reinsuranceformula = ReinsuranceFormula::where('id', $id)->first();
        $reinsuranceTypes = ReinsuranceType::get();
        $types = Lookup::where('key', 'reinsurance_formula_key')->get(array(
            'id',
            'value'
        ));
        if (Auth::user()
            ->hasPermissionTo('reinsurance-formula-edit'))
        {
            return view('admin.reinsuranceFormula.edit', compact('reinsuranceformula', 'products', 'types', 'reinsuranceTypes', 'groups'));
        }
        elseif (Auth::user()
            ->hasPermissionTo('reinsurance-formula-list'))
        {
            return view('admin.reinsuranceFormula.view', compact('reinsuranceformula', 'products', 'types', 'reinsuranceTypes', 'groups'));
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');

        }

    }

    /**
     * method to return modal body for confirm-delete.
     *
     * @return JSON
     */
    public function getModalDelete(Request $request)
    {
        $body = 'Are you sure you want to delete the Reinsurance Formula?';
        return response()->json(['status' => 'success', 'id' => $request->get('id') , 'body' => $body]);
    }

    /**
     *deletes specific reinsurance formula
     *param: reinsurance formula id ($id)
     * @return reinsurance formula listing page
     */
    public function destroy($id)
    {
        try
        {
            $delete = ReinsuranceFormula::where('id', $id)->delete();

            return Redirect::route('admin.reinsuranceFormula.index')
                ->with('success', 'Reinsurance Formula Deleted Successfully');

        }
        catch(Exception $e)
        {
            return Redirect::route('admin.reinsuranceFormula.index')->with('error', 'Something Went Wrong');
        }

    }
}

