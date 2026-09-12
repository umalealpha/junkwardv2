<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\City;
use AlphaDirect\Partners;
use AlphaDirect\RepairCenter;
use AlphaDirect\State;
use AlphaDirect\Stores;
use Http\Client\Exception;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Hash;
use DB;
use Yajra\DataTables\DataTables;

class RepairController extends Controller
{
    public function index()
    {
        try {
            return view('admin.repairCenters.index');
        } catch (Exception $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }
    }


    public function data()
    {
        $data = \AlphaDirect\RepairCenter::orderBy('id', 'DESC')->get();
        return DataTables::of($data)
            ->editColumn('name', function ($data) {
                if ($data->name != null) {
                    return  ucwords($data->name) ;
                }
            })        
        ->editColumn('created_at', function ($data) {
                    if ($data->created_at != null) {
                        return   \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $data->created_at)->format('Y-m-d H:i') ;
                    }
                })

            ->editColumn('state_id', function ($data) {

                if ($data->state_id) {
                    $state= State::where('id', $data->state_id)->first(array('name'));
                    return ucwords($state->name);
                } else {
                    return 'N/A';
                }
            })
            ->editColumn('city_id', function ($data) {
                if ($data->city_id != NULL) {
                    $city= City::where('id', $data->city_id)->first(array('name'));
                    if ($city){
                        return ucwords($city->name);
                    }
                    else{
                        return 'N/A';
                    }
                } else {
                    return 'N/A';
                }
            })

            ->addColumn('actions', function ($data) {
                $actions = '';
                if (Auth::user()->hasPermissionTo('repair_centers-edit'))
                {
                    $actions .= '<a href="' . route('admin.repairCenters.edit', $data->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                        <i class="la la-edit"></i>
                    </a>';
                }
                return $actions;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function create()
    {
        try {
            $states = State::where('country_id', 28)->get(['id', 'name']);
            return view('admin.repairCenters.create', compact( 'states'));
        } catch (Exception $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }
    }

    public function store(Request $request)
    {
        try {
            $center = new RepairCenter();
            $center->name = $request->name;
            $center->email = $request->email;
            $center->username = $request->username;
            $center->password = Hash::make($request->password);
            $center->mobile = $request->mobile;
            $center->vat = $request->vat;
            $center->city_id = $request->city;
            $center->state_id = $request->state;
            $center->save();
            return Redirect::route('admin.repairCenters.index')->with('success', 'Repair Center Created Successfully');
        } catch (Exception $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }
    }

    public function edit($id)
    {
        try {
            $center = RepairCenter::where('id', $id)->orderBy('id', 'DESC')->first();
            $states = State::where('country_id', 28)->get(['id', 'name']);
            $cities = City::where('state_id', $center->state_id)->get(['id', 'name']);
            return view('admin.repairCenters.edit', compact('center',  'states','cities'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage() . ' - ' . $e->getCode());
        }
    }
    public function updateStore(Request $request, $id)
    {
        try {
            $center = RepairCenter::where('id', $id)->first();
            $center->name = $request->name;
            $center->email = $request->email;
            if ($request->password != null) {
                $center->password = Hash::make($request->password);
            }
            $center->username = $request->username;
            $center->mobile = $request->mobile;
            $center->vat = $request->vat;
            $center->city_id = $request->city;
            $center->state_id = $request->state;
            $center->save();
            return Redirect::route('admin.repairCenters.index')->with('success', 'Repair Center Updated Successfully');
        } catch (Exception $e) {
            return redirect()::back()->with('error', $e->getMessage() . ' - ' . $e->getCode());
        }
    }

}
