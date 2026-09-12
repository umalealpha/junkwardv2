<?php
namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\FactorSubType;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Product;
use AlphaDirect\Region;
use AlphaDirect\RegionLicense;
use Http\Client\Exception;
use Illuminate\Http\Request;

use AlphaDirect\User;
use AlphaDirect\Role;
use AlphaDirect\Notifications;
use AlphaDirect\KYC;
use AlphaDirect\Policy;
use AlphaDirect\GlassClaim;
use AlphaDirect\OTP;
use Hash;
use Illuminate\Support\Facades\Auth;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

use Validator;
use Session;
use DB;
use Redirect;
use Yajra\DataTables\DataTables;

class RegionController extends Controller
{
    /**
     * Show a list of all the Regions.
     *
     * @return View
     */
    public function index()
    {
        if (Auth::user()->hasPermissionTo('product-region-list'))
        {
            // Show the page
            return view('admin.region.index');
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
        $region = Region::get(array(
            'id',
            'name',
            'slug',
            'status',
            'created_at'
        ));
        return DataTables::of($region)->editColumn('status', function ($region)
        {
            if ($region->status)
            {
                return 'Active';
            }
            else
            {
                return 'In-Active';
            }
        })->editColumn('created_at', function ($region)
        {
            return $region
                ->created_at
                ->diffForHumans();
        })->addColumn('actions', function ($region)
        {
            $actions = '';
            if (Auth::user()->hasPermissionTo('product-region-edit'))
            {
                $actions .= '<a href="' . route('admin.region.edit', $region->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
            }
            else
            {
                $actions .= '<a href="' . route('admin.region.edit', $region->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="flaticon-eye"></i>
                            </a>';
            }

            if (Auth::user()
                ->hasPermissionTo('product-region-delete'))
            {
                $actions .= '<a href="" value="' . $region->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
            }
            return $actions;
        })->rawColumns(['actions'])
            ->make(true);
    }

    /**
     * Show a page to create region.
     *
     * @return View region create page
     */
    public function create()
    {
        if (Auth::user()
            ->hasPermissionTo('product-region-create'))
        {
            // Show the page
            return view('admin.region.create');
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * Show a page to edit region.
     **param: region id ($id)
     * @return View region edit page
     */
    public function edit(Region $region)
    {
        $regionLicenses = RegionLicense::where('region_id', $region->id)
            ->get();
        if (Auth::user()
            ->hasPermissionTo('product-region-edit'))
        {
            return view('admin.region.edit', compact('region', 'regionLicenses'));
        }
        elseif (Auth::user()
            ->hasPermissionTo('product-region-list'))
        {
            return view('admin.region.view', compact('region', 'regionLicenses'));
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');

        }
    }


    /**
     * method to store regions data from create page.
     *
     * @return View region list page
     */
    public function store(Request $request)
    {

        $region = new Region();
        $region->name = $request->get('name');
        $region->slug = $request->get('name');
        $region->currency = $request->get('currency');
        $region->tax = $request->get('tax');
        $region->vat = $request->get('vat');
        $region->registration = $request->get('registration');
        if ($request->status == NULL)
        {
            $region->status = "0";
        }
        else
        {
            $region->status = "1";
        }
        if ($region->save())
        {
            foreach ($request->licenseValues as $key => $value)
            {
                if ($value['lic_name'] != NULL)
                {
                    $storeValues = new RegionLicense();
                    $storeValues->region_id = $region->id;
                    $storeValues->license_name = $value['lic_name'];
                    $storeValues->license_number = $value['lic_num'];
                    $storeValues->save();
                    activity('Region')
                        ->performedOn($region)->causedBy(User::where('id', Auth()
                            ->user()
                            ->id)
                            ->first())
                        ->log('Region Created');
                }
            }

            return Redirect::route('admin.region.index')
                ->with('success', 'Region Created Successfully');
        }
        else
        {
            return Redirect::route('admin.region.index')
                ->with('error', 'Something Went Wrong');
        }

    }

    /**
     * method to update regions data from edit page.
     **param: region id ($id)
     * @return View region list page
     */
    public function update($id, Request $request)
    {
        $region = Region::where('id', $id)->first();
        $region->name = $request->get('name');
        $region->slug = $request->get('name');
        $region->currency = $request->get('currency');
        $region->tax = $request->get('tax');
        $region->vat = $request->get('vat');
        $region->registration = $request->get('registration');
        //New
        $regionLicense = $request->regionLicense;
        //Existing
        $regionLicenseName = $request->lic_name;
        $regionLicenseNumber = $request->lic_num;
        if ($regionLicenseName != null)
        {
            foreach ($regionLicenseName as $key => $values)
            {
                if (!empty($values))
                {
                    $license = RegionLicense::where('id', $key)->first();
                    $license->license_name = $values;
                    $license->license_number = $regionLicenseNumber[$key];
                    $license->save();
                }
            }
        }
        if ($regionLicense != null)
        {
            foreach ($regionLicense as $key => $value)
            {
                if ($value['lic_name'] != null && $value['lic_num'])
                {
                    $storeLicense = new RegionLicense();
                    $storeLicense->region_id = $id;
                    $storeLicense->license_name = $value['lic_name'];
                    $storeLicense->license_number = $value['lic_num'];
                    $storeLicense->save();
                }
            }
        }
        if ($request->status == NULL)
        {
            $region->status = "0";
        }
        else
        {
            $region->status = "1";
        }
        if ($region->save())
        {
            // Redirect to the home page with success menu
            activity('Region')
                ->performedOn($region)->causedBy(User::where('id', Auth()
                    ->user()
                    ->id)
                    ->first())
                ->log('Region Updated');
            return Redirect::route('admin.region.index')
                ->with('success', 'Region Updated Successfully');
        }
        else
        {
            return redirect()
                ->back()
                ->with('error', 'Something Went Wrong');
        }
    }

    /**
     * method to delete region license.
     *param: region license id ($id)
     * @return View region edit page
     */
    public function regionLicenseDelete($id)
    {
        $regionLicense = RegionLicense::find($id)->delete();
        return redirect()
            ->back()
            ->with('success', 'Region License Deleted Successfully');
    }

    /**
     * method to return modal body for confirm-delete.
     *
     * @return JSON
     */
    public function getModalDelete(Request $request)
    {
        $check = Product::where('region_id', $request->get('id'))
            ->count();
        // Check if we are not trying to delete ourselves
        if ($check)
        {
            // Prepare the error message
            $body = 'Region Assigned to Products. Cannot delete this Region';
            return response()->json(['status' => 'error', 'body' => $body]);
        }
        else
        {
            $body = 'Are you sure you want to delete the Region ?';
            return response()->json(['status' => 'success', 'id' => $request->get('id') , 'body' => $body]);
        }
    }

    /**
     *deletes specific region
     *param: region id ($id)
     * @return region listing page
     */
    public function destroy($id)
    {
        try
        {

            activity('Region')->performedOn(Region::where('id', $id)->first())
                ->causedBy(User::where('id', Auth()
                    ->user()
                    ->id)
                    ->first())
                ->log('Region Deleted');
            $region = Region::where('id', $id)->delete();

            return Redirect::route('admin.region.index')
                ->with('success', 'Region Deleted Successfully');

        }
        catch(Exception $e)
        {
            return Redirect::route('admin.region.index')->with('error', 'Something Went Wrong');
        }

    }
}

