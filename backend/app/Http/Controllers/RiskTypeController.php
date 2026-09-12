<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\Region;
use AlphaDirect\RiskType;
use Auth;
use Exception;
use Illuminate\Http\Request;
use Redirect;
use Response;
use Yajra\DataTables\DataTables;

class RiskTypeController extends Controller
{
    /** index page for Risk Types */
    public function index()
    {
        $riskTypes = RiskType::all();
        return view('/admin/risk/index', compact('riskTypes'));
    }

    /**edit page for risk type */
    public function edit($id)
    {
        $riskTypes = RiskType::find($id);
        $regions = Region::all();
//        if (auth::user()->hasPermissionTo('risk-edit')) {
            return view('/admin/risk/edit', compact('riskTypes', 'regions'));
////        } else {
//            return view('/admin/risk/view', compact('riskTypes', 'regions'));
//        }

    }

    /**creae page for Risk type */

    public function create()
    {
        $regions = Region::all();
        return view('/admin/risk/create', compact('regions'));
    }
    /*
     * Pass data through ajax call
     */
    /**
     * @return mixed
     */
    public function data()
    {
        $riskTypes = RiskType::get(array('id', 'name', 'active', 'region', 'created_at'));
        return DataTables::of($riskTypes)

            ->editColumn('active', function ($riskTypes) {
                if ($riskTypes->active) {
                    return 'Active';
                } else {
                    return 'In-Active';
                }
            })

            ->editColumn('region', function ($riskTypes) {
                $regionName = Region::where('id', $riskTypes->region)->first(array('name'));

                return $regionName ? $regionName->name : '';
            })

            ->addColumn('actions', function ($riskTypes) {
                $actions = '';
//                if (auth::user()->hasPermissionTo('risk-edit')) {
                    $actions .= '<a href="' . route('risk.edit', $riskTypes->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
//                } else {
                    $actions .= '<a href="' . route('risk.edit', $riskTypes->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="flaticon-eye"></i>
                            </a>';

//                }
//                if (auth::user()->hasPermissionTo('risk-edit')) {
                    $actions .= '<a href="" value="' . $riskTypes->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
//                }

                return $actions;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function getModalDelete(Request $request)
    {
        $check = RiskType::where('id', $request->get('id'))->count();
        // Check if we are not trying to delete ourselves

        $body = 'Are you sure you want to delete the  Risk ?';
        return response()->json(['status' => 'success', 'id' => $request->get('id'), 'body' => $body]);
    }

    /** store function for risk type */
    public function store(Request $request)
    {
        /**validations for input */
        $request->validate([
            'name' => 'required',
            'limit' => 'required',
            'active' => 'required',
            'region' => 'required',
        ]);
        try {
            $riskType = new RiskType();
            $riskType->name = $request->name;
            //check whether active or not
            if ($request->active == null) {
                $riskType->active = "0";
            } else {
                $riskType->active = "1";
            }
            $riskType->region = $request->region;
            $riskType->limit = $request->limit;
            $riskType->created_by = Auth::id();
            $riskType->save();

            $riskType->save();
            return Redirect::route('risk.display')->with('success', 'Risk Created Successfully');
        } catch (Exception $exception) {
            $errormsg = 'Database error! ' . $exception->getCode();
            return Response::json(['errormsg' => $exception]);
        }
    }

    /**update function */
    public function update(Request $request, $id)
    {
        // dd($request);
        try {
            $riskType = RiskType::where('id', $id)->first();
            $riskType->name = $request->name;
            //check status before submitting
            //check whether active or not
            if ($request->active == null) {
                $riskType->active = "0";
            } else {
                $riskType->active = "1";
            }
            $riskType->region = $request->region;
            $riskType->limit = $request->limit;
            $riskType->save();
            return Redirect::route('risk.display')->with('success', 'Risk Updated Successfully');
        } catch (Exception $errormsg) {
            $errormsg = 'Database error! ' . $errormsg->getCode();
            return Response::json(['errormsg' => $errormsg]);
        }
    }

    public function destroy($id)
    {
        $riskType = RiskType::findOrFail($id);
        $riskType->delete();
        return Redirect::route('risk.display')->with('success', 'Risk Deleted Successfully');
    }
}
