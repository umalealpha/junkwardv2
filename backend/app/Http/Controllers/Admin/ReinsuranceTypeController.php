<?php
namespace AlphaDirect\Http\Controllers\Admin;
use AlphaDirect\Models\ReinsuranceFormula;
use AlphaDirect\Models\ReinsuranceType;
use AlphaDirect\User;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Redirect;
use Yajra\DataTables\DataTables;

class ReinsuranceTypeController extends Controller
{
    /**
     * Show a list of all reinsurance types.
     *
     * @return View reinsurance types listing page
     */
    public function index()
    {
        if (Auth::user()->hasPermissionTo('reinsurance-type-list'))
        {
            return view('admin.reinsuranceType.index');
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
        // Show the page

    }

    /**
     * Show a page to create new reinsurance type.
     *
     * @return View reinsurance type form
     */
    public function create()
    {

            return view('admin.reinsuranceType.create');


    }

    /**
     * method to store reinsurance type data from create page.
     *
     * @return View listing page
     */
    public function store(Request $request)
    {
        $reinsuranceType = new ReinsuranceType();
        $reinsuranceType->type_code = $request->get('type_code');
        $reinsuranceType->type_name = $request->get('type_name');
        $reinsuranceType->type_description = $request->get('type_desc');
        if ($request->status == NULL)
        {
            $reinsuranceType->status = "0";
        }
        else
        {
            $reinsuranceType->status = "1";
        }
        if ($reinsuranceType->save())
        {

            return Redirect::route('admin.reinsuranceType.index')
                ->with('success', 'Reinsurance Type Created Successfully');
        }
        else
        {
            return Redirect::route('admin.reinsuranceType.index')
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
        $reinsuranceType = ReinsuranceType::get(array(
            'id',
            'type_code',
            'type_name',
            'status',
            'created_at'
        ));
        return DataTables::of($reinsuranceType)->editColumn('status', function ($reinsuranceType)
        {
            if ($reinsuranceType->status)
            {
                return 'Active';
            }
            else
            {
                return 'In-Active';
            }
        })->editColumn('type_name' , function($reinsuranceType){
               if($reinsuranceType->type_name)
               {
                   return ucwords($reinsuranceType->type_name);
               }else{
                   return "--";
               }
        })
        ->editColumn('created_at', function ($reinsuranceType)
        {
            return $reinsuranceType
                ->created_at
                ->diffForHumans();
        })->addColumn('actions', function ($reinsuranceType)
        {
            $actions = '';
            if (Auth::user()->hasPermissionTo('reinsurance-type-edit'))
            {
                $actions .= '<a href="' . route('admin.reinsuranceType.edit', $reinsuranceType->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
            }
            else
            {
                $actions .= '<a href="' . route('admin.reinsuranceType.edit', $reinsuranceType->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="flaticon-eye"></i>
                            </a>';
            }
            if (Auth::user()
                ->hasPermissionTo('reinsurance-type-delete'))
            {
                $actions .= '<a href="" value="' . $reinsuranceType->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
            }
            return $actions;
        })->rawColumns(['actions'])
            ->make(true);
    }

    /**
     * Show a page to edit specific Reinsurance Type.
     *param: $reinsuranceType
     * @return View
     */
    public function edit(ReinsuranceType $reinsuranceType)
    {
        if (Auth::user()->hasPermissionTo('reinsurance-type-edit'))
        {
            return view('admin.reinsuranceType.edit', compact('reinsuranceType'));
        }
        elseif (Auth::user()
            ->hasPermissionTo('reinsurance-type-list'))
        {
            return view('admin.reinsuranceType.view', compact('reinsuranceType'));
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }

    }

    /**
     * Show a page to update data for specific Reinsurance Type.
     *param: $reinsurance type id
     * @return View reinsurance type listing
     */
    public function update($id, Request $request)
    {
        $reinsuranceType = ReinsuranceType::where('id', $id)->first();
        $reinsuranceType->type_code = $request->get('type_code');
        $reinsuranceType->type_name = $request->get('type_name');
        $reinsuranceType->type_description = $request->get('type_desc');
        if ($request->status == NULL)
        {
            $reinsuranceType->status = "0";
        }
        else
        {
            $reinsuranceType->status = "1";
        }
        if ($reinsuranceType->save())
        {
            // Redirect to the home page with success menu
            activity('Reinsurance Type')
                ->performedOn($reinsuranceType)->causedBy(User::where('id', Auth()
                    ->user()
                    ->id)
                    ->first())
                ->log('Reinsurance Type Updated');
            return Redirect::route('admin.reinsuranceType.index')
                ->with('success', 'Reinsurance Type Updated Successfully');
        }
        else
        {
            return redirect()
                ->back()
                ->with('error', 'Something Went Wrong');
        }
    }

    /**
     * method to return modal body for confirm-delete.
     *
     * @return View
     */
    public function getModalDelete(Request $request)
    {
        $check = ReinsuranceFormula::where('reinsurance_type_id', $request->get('id'))
            ->count();
        if ($check)
        {
            $body = 'Reinsurance Type assigned to Reinsurance Formula. Cannot delete this Reinsurance Type';
            return response()->json(['status' => 'error', 'body' => $body]);
        }
        $body = 'Are you sure you want to delete the Reinsurance Type?';
        return response()->json(['status' => 'success', 'id' => $request->get('id') , 'body' => $body]);

    }

    /**
     *deletes specific Reinsurance type
     *param: Reinsurance type id ($id)
     * @return Reinsurance type listing page
     */
    public function destroy($id)
    {
        try
        {
            activity('Reinsurance Type')->performedOn(ReinsuranceType::where('id', $id)->first())
                ->causedBy(User::where('id', Auth()
                    ->user()
                    ->id)
                    ->first())
                ->log('Reinsurance Type Deleted');
            $delete = ReinsuranceType::where('id', $id)->delete();

            return Redirect::route('admin.reinsuranceType.index')
                ->with('success', 'Reinsurance Type Deleted Successfully');

        }
        catch(Exception $e)
        {
            return Redirect::route('admin.reinsuranceType.index')->with('error', 'Something Went Wrong');
        }

    }

}

