<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Coverage;
use AlphaDirect\FactorMain;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\KycFields;
use AlphaDirect\Lookup;
use AlphaDirect\Product;
use AlphaDirect\Models\ProductCoverage;
use AlphaDirect\ProductLicense;
use AlphaDirect\Productplan;
use AlphaDirect\ProductType;
use AlphaDirect\Region;
use AlphaDirect\RegionLicense;
use AlphaDirect\Models\ReinsuranceFormula;
use AlphaDirect\User;
use AlphaDirect\Policy;
use Http\Client\Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Redirect;
use Yajra\DataTables\DataTables;
use AlphaDirect\KycCompliance;
use AlphaDirect\Models\CoverageMaster;
use DB;
class ProductController extends Controller
{
    /**
     * Show a list of all products.
     *
     * @return View product listing page
     */
    public function index()
    {
        if (Auth::user()->hasPermissionTo('product-list')) {
            $productType = ProductType::get(array(
                'id',
                'name'
            ));
            return view("admin.product.index", compact('productType'));
        } else {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }


    /**
     * Show a page to create product.
     *
     * @return View product create page
     */
    public function create()
    {
        if (Auth::user()
            ->hasPermissionTo('product-create')
        ) {
            $regions = Region::where('status', 1)->get(array(
                'id',
                'name'
            ));
            $productType = ProductType::where('status', 1)->get(array(
                'id',
                'name'
            ));
            $billingCycles = Lookup::where('key', 'billing_cycle')->get(array(
                'id',
                'value'
            ));
            $premiumType = Lookup::where('key', 'premium_type')->get(array(
                'id',
                'value'
            ));
            $coverages = CoverageMaster::where('s_CoverageGroupCode','MAIN')
                                        ->where('s_UsageType','PARENT')
                                        ->orderBy('id','asc')->get(array(
                                            'id',
                                            's_ScreenName'
                                        ));
            $kycCompliance = KycCompliance::get(array(
                'id',
                'name'
            ));

            return view("admin.product.create", compact('productType', 'regions', 'premiumType', 'coverages', 'billingCycles', 'kycCompliance'));
        } else {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * method to store product data from create page.
     *
     * @return View
     */
    public function store(Request $request)
    {
        $product = new Product();
        $product->name = $request->product_name;
        $product->policy_initials = $request->policy_initials;
        $product->premium_type_id = $request->premium_type;
        $product->slug = $request->slug;
        $product->region_id = $request->product_region;
        $product->kyc_compliance = isset($request->flow_kyc_compliance) ? $request->flow_kyc_compliance : null;
        $product->sum_insured = $request->sum;
        $product->billing_cycle = $request->billing_cycle;
        $product->product_type_id = $request->product_type_id;

        if ($request->hasFile('product_image')) {
            $file = $request->file('product_image');
            $name = $file->getClientOriginalName();
            $filePath = 'Product/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $product->image = $filePath;
        }

        if ($request->has_wordings == null) {
            $product->has_wordings = "0";
        } else {
            $product->has_wordings = "1";
        }

        if ($request->has_schedule == null) {
            $product->has_schedule = "0";
        } else {
            $product->has_schedule = "1";
        }

        if ($request->customer == null) {
            $product->kyc_customer = "0";
        } else {
            $product->kyc_customer = "1";
        }

        if ($request->recipient == null) {
            $product->kyc_recipient = "0";
        } else {
            $product->kyc_recipient = "1";
        }

        if ($request->is_vehicle == null) {
            $product->has_vehicle = "0";
        } else {
            $product->has_vehicle = "1";
        }

        if ($request->is_member == null) {
            $product->has_member = "0";
        } else {
            $product->has_member = "1";
            if ($request->is_applicant == null) {
                $product->has_subApplicant = "0";
            } else {
                $product->has_subApplicant = "1";
            }
        }

        if ($request->is_applicant == null) {
            $product->has_subApplicant = "0";
        } else {
            $product->has_subApplicant = "1";
        }

        if (!$request->has_activation) {
            $product->has_activation_code = "0";
        } else {
            $product->has_activation_code = "1";
        }

        if (!$request->is_motor_items) {
            $product->is_motor_items = "0";
        } else {
            $product->is_motor_items = "1";
        }

        if ($request->inspection == null) {
            $product->preinspection = "0";
        } else {
            $product->preinspection = "1";
            $product->limit = $request->limit;
        }

        if (!$request->isForStart) {
            $product->isForStart = "0";
        } else {
            $product->isForStart = "1";
        }

        if ($request->status == null) {
            $product->status = "0";
        } else {
            $product->status = "1";
        }

        if ($product->save()) {
            if ($request->license != null) {
                foreach ($request->license as $key => $value) {
                    $p_license = new ProductLicense();
                    $p_license->product_id = $product->id;
                    $p_license->region_id = $product->region_id;
                    $p_license->region_license_id = $value;
                    $p_license->save();
                }
            }

            if($request->coverage != null){
                foreach ($request->coverage as $cover) {
                    $policyCoverageName = DB::table('tb_cvgpccoverages')->where('id',$cover)->first('s_ScreenName');

                    $coverage = new ProductCoverage();
                    $coverage->product_id = $product->id;
                    $coverage->coverage_id = $cover;
                    $coverage->name = isset($policyCoverageName) ? $policyCoverageName->s_ScreenName : null;
                    $coverage->save();
                }
            }

            activity('Product')
                ->performedOn($product)->causedBy(User::where('id', Auth()
                    ->user()
                    ->id)
                    ->first())
                ->log('Product Created');
            // Redirect to the home page with success menu
            return Redirect::route('admin.product.index')
                ->with('success', 'Product Created Successfully');
        } else {
            return Redirect::route('admin.product.index')
                ->with('error', 'Something Went Wrong');
        }
    }

    /**
     * method to retrieve all the region license associated.
     * param: region id
     * @return json
     */
    public function getRegionLicense(Request $request)
    {
        $region = Region::where('id', $request->get('id'))
            ->first('id', 'name');
        if ($region == null) {
            // Prepare the error message
            return response()->json(['status' => 'error']);
        } else {
            $regionId = $region->id;
            $regionLicenses = RegionLicense::where('region_id', $regionId)->get(array(
                'id',
                'license_name',
                'license_number'
            ));
            return response()
                ->json(['status' => 'success', 'regionLicenses' => $regionLicenses]);
        }
    }


    /*
     * Pass data through ajax call
     * @return mixed
     */
    public function data(Request $request)
    {
        $product = Product::get(array(
            'id',
            'name',
            'premium_type_id',
            'status',
            'product_type_id',
            'created_at'
        ));
        if ($request->productType_filter != -1) {
            $product = Product::where('product_type_id', $request->productType_filter)
                ->get(array(
                    'id',
                    'premium_type_id',
                    'name',
                    'status',
                    'product_type_id',
                    'created_at'
                ));
        }

        return DataTables::of($product)
            ->editColumn('premium_type_id', function ($product) {
                $premiumType = Lookup::where('id', $product->premium_type_id)
                    ->first(array(
                        'value'
                    ));
                return $premiumType ? $premiumType->value : '';
            })
            ->editColumn('status', function ($policy) {
                if ($policy->status == 1) {
                    $return = '<span class="kt-font-bold kt-font-brand">Active</span>';
                } else {
                    $return = '<span class="kt-font-bold kt-font-danger">In-Active</span>';
                }

                return $return;
            })
            ->editColumn('product_type_id', function ($product) {
                $productType = ProductType::where('id', $product->product_type_id)
                    ->first(array(
                        'name'
                    ));
                return $productType ? $productType->name : '';
            })
            ->editColumn('created_at', function ($product) {
                return $product
                    ->created_at
                    ->diffForHumans();
            })
            ->addColumn('actions', function ($product) {
                $actions = '';
                if (Auth::user()->hasPermissionTo('product-edit')) {
                    $actions .= '<a href="' . route('admin.product.edit', $product->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                } else {
                    $actions .= '<a href="' . route('admin.product.edit', $product->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="flaticon-eye"></i>
                            </a>';
                }
                if (Auth::user()
                    ->hasPermissionTo('product-delete')
                ) {
                    $actions .= '<a href="" value="' . $product->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                           </a>';
                }

                // $actions .= '<a href="' . route('admin.product.kyc', $product->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                //                 <i class="la la-edit"></i>
                //             </a>';

                if ($product->premium_type_id == 15) {
                    $actions .= '<a href="' . route('admin.product.formula', $product->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Formula">
                                    <i class="la la-flask"></i>
                               </a>';
                }

                return $actions;
            })
            ->rawColumns(['actions', 'status'])
            ->make(true);
    }

    /**
     * method to show product edit page.
     *product id ($id)
     * @return View product edit page
     */
    public function edit($id)
    {
        $regions = Region::where('status', 1)->get(array(
            'name',
            'id'
        ));
        $kycCompliance = KycCompliance::get(array(
            'id',
            'name'
        ));
        $products = Product::where('id', $id)->first();
        $productRegion = $products->region_id;
        $checkedLicense = ProductLicense::where('region_id', $productRegion)->pluck('region_license_id')
            ->toArray();

        $premiumType = Lookup::where('key', 'premium_type')->get(array(
            'id',
            'value'
        ));
        $productType = ProductType::get(array(
            'name',
            'id'
        ));

        $coverages = CoverageMaster::where('s_CoverageGroupCode','MAIN')
                        ->where('s_UsageType','PARENT')
                        ->orderBy('id','asc')->get(array(
                            'id',
                            's_ScreenName'
                        ));

        $billingCycles = Lookup::where('key', 'billing_cycle')->get(array(
            'id',
            'value'
        ));
        $prodCov = ProductCoverage::where('product_id', $id)->pluck('coverage_id')
            ->toArray();

        if (Auth::user()
            ->hasPermissionTo('product-edit')
        ) {
            return view('admin.product.edit', compact('billingCycles', 'products', 'regions', 'checkedLicense', 'productType', 'premiumType', 'coverages', 'prodCov', 'checkedLicense', 'kycCompliance'));
        } elseif (Auth::user()
            ->hasPermissionTo('product-list')
        ) {
            return view('admin.product.view', compact('billingCycles', 'products', 'regions', 'checkedLicense', 'productType', 'premiumType', 'coverages', 'prodCov', 'checkedLicense', 'kycCompliance'));
        } else {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * retrieves formula related to product
     * @param product id ($id)
     * @return view product formula page
     **/
    public function formula($id)
    {
        $product = Product::where('id', $id)->first(array(
            'id',
            'formula'
        ));
        $factors = FactorMain::where('product_id', $id)->get(array(
            'id',
            'name'
        ));
        return view('admin.product.formula', compact('factors', 'product'));
    }

    /**store formula related to product
     * @param product $id
     * @param Request $request
     * @return view product listing page
     */
    public function formulaStore($id, Request $request)
    {
        $products = Product::where('id', $id)->first();
        $products->formula = $request->formula;
        if ($products->save()) {
            activity('Product Formula')
                ->performedOn($products)->causedBy(User::where('id', Auth()
                    ->user()
                    ->id)
                    ->first())
                ->log('Product Formula Stored');
            // Redirect to the home page with success menu
            return Redirect::route('admin.product.index')
                ->with('success', 'Product created Successfully');
        } else {
            return redirect()
                ->back()
                ->with('error', 'Something Went Wrong');
        }
    }

    public function kyc($id)
    {
        $kyc_fields = KycFields::get('name');
      //  dd($kyc_fields);
        return view('admin.product.kyc', compact('kyc_fields'));
    }


    /**
     * method to update product data from edit page.
     *product id ($id)
     * @return View product listing page
     */
    public function update($id, Request $request)
    {
        try {
            // dd($request->product_type_id);
            $product = Product::where('id', $id)->first();
            $product->name = $request->product_name;
            $product->policy_initials = $request->policy_initials;
            $product->premium_type_id = $request->premium_type;
            $product->slug = $request->slug;
            $product->region_id = $request->region_id;
            $product->kyc_compliance = isset($request->flow_kyc_compliance) ? $request->flow_kyc_compliance : null;
            $product->sum_insured = $request->sum;
            $product->product_type_id = $request->product_type_id;
            $product->billing_cycle = $request->billing_cycle;

            if ($request->hasFile('product_image')) {
                $file = $request->file('product_image');
                $name = $file->getClientOriginalName();
                $filePath = 'Product/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $product->image = $filePath;
            }
            if ($request->is_vehicle == null) {
                $product->has_vehicle = "0";
                // ProductCoverage::where('product_id', $id)->delete();
            } else {
                $product->has_vehicle = "1";
            }
                $prodCov = ProductCoverage::where('product_id', $id)->pluck('coverage_id')
                    ->toArray();
                if ($request->coverage != null) {
                    foreach ($request->coverage as $cover) {
                        $check = ProductCoverage::where('product_id', $product->id)
                            ->where('coverage_id', $cover)->first();
                        $policyCoverageName = DB::table('tb_cvgpccoverages')->where('id',$cover)->first('s_ScreenName');

                        if ($check == null) {
                            $coverage = new ProductCoverage();
                            $coverage->product_id = $product->id;
                            $coverage->coverage_id = $cover;
                            $coverage->name = isset($policyCoverageName) ? $policyCoverageName->s_ScreenName : null;
                            $coverage->save();
                        }
                    }
                    if (array_diff($prodCov, $request->coverage)) {
                        ProductCoverage::where('product_id', $product->id)
                            ->whereIn('coverage_id', array_diff($prodCov, $request->coverage))
                            ->delete();
                    }
                } else {
                    ProductCoverage::where('product_id', $product->id)
                        ->delete();
                }

            if (!$request->has_schedule) {
                $product->has_schedule = "0";
            } else {
                $product->has_schedule = "1";
            }

            if (!$request->has_wordings) {
                $product->has_wordings = "0";
            } else {
                $product->has_wordings = "1";
            }

            if (!$request->has_activation_code) {
                $product->has_activation_code = "0";
            } else {
                $product->has_activation_code = "1";
            }

            if (!$request->isForStart) {
                $product->isForStart = "0";
            } else {
                $product->isForStart = "1";
            }

            if (!$request->is_motor_items) {
                $product->is_motor_items = "0";
            } else {
                $product->is_motor_items = "1";
            }
            if (!$request->inspection) {
                $product->preinspection = "0";
                $product->limit = null;
            } else {
                $product->preinspection = "1";
                $product->limit = $request->limit;
            }

            if ($request->is_member == null) {
                $product->has_member = "0";
                $product->has_subApplicant = "0";
            } else {
                $product->has_member = "1";
                if ($request->is_applicant == null) {
                    $product->has_subApplicant = "0";
                } else {
                    $product->has_subApplicant = "1";
                }
            }

            if ($request->recipient == null) {
                $product->kyc_recipient = "0";
            } else {
                $product->kyc_recipient = "1";
            }

            if ($request->status == null) {
                $product->status = "0";
            } else {
                $product->status = "1";
            }
            if ($request->customer == null) {
                $product->kyc_customer = "0";
            } else {
                $product->kyc_customer = "1";
            }

            if ($product->save()) {

                if ($request->license != null) {
                    foreach ($request->license as $key => $value) {

                        $p_license = ProductLicense::where('product_id', $product->id);
                        $p_license->product_id = $product->id;
                        $p_license->region_id = $product->region_id;
                        $p_license->region_license_id = $value;
                        $p_license->save();
                    }
                }
                activity('Product')
                    ->performedOn($product)->causedBy(User::where('id', Auth()
                        ->user()
                        ->id)
                        ->first())
                    ->log('Product Updated');
                // Redirect to the home page with success menu
                return Redirect::route('admin.product.index')
                    ->with('success', 'Product Updated Successfully');
            }
        } catch (\Exception $ex) {
            return Redirect::route('admin.product.index')->with('error', $ex->getMessage());
        }
    }

    /**
     * method to check product association before deleting it.
     *param: product id
     * @return View
     */
    public function getModalDelete(Request $request)
    {
        $check = Policy::where('product_id', $request->get('id'))
            ->count();
        $check1 = ReinsuranceFormula::where('product_id', $request->get('id'))
            ->count();
        $check2 = Productplan::where('product_id', $request->get('id'))
            ->count();
        $check3 = FactorMain::where('product_id', $request->get('id'))
            ->count();
        // Check if we are not trying to delete ourselves
        if ($check) {
            // Prepare the error message
            $body = 'Product Assigned to Policy. Cannot delete this product';
            return response()->json(['status' => 'error', 'body' => $body]);
        } elseif ($check1) {
            $body = 'Product assigned to Reinsurance Formula. Cannot delete this product';
            return response()->json(['status' => 'error', 'body' => $body]);
        } elseif ($check2) {
            $body = 'Product assigned to Product Plan. Cannot delete this product';
            return response()->json(['status' => 'error', 'body' => $body]);
        } elseif ($check3) {
            $body = 'Product assigned to Product Factor Main. Cannot delete this product';
            return response()->json(['status' => 'error', 'body' => $body]);
        } else {
            $body = 'Are you sure you want to delete the Product ?';
            return response()->json(['status' => 'success', 'id' => $request->get('id'), 'body' => $body]);
        }
    }

    /**
     *deletes specific product
     *param: product id ($id)
     * @return product listing page
     */
    public function destroy($id)
    {
        try {

            activity('Product')->performedOn(Product::where('id', $id)->first())
                ->causedBy(User::where('id', Auth()
                    ->user()
                    ->id)
                    ->first())
                ->log('Product Deleted');
            Product::where('id', $id)->delete();

            return Redirect::route('admin.product.index')
                ->with('success', 'Product Deleted Successfully');
        } catch (Exception $e) {
            return Redirect::route('admin.product.index')->with('error', 'Something Went Wrong');
        }
    }

    /**
     * method to retrieve sum assured for a specific product.
     *param: product_id (request)
     * @return json
     */
    public function checkSumAssured(Request $request)
    {
        $asssuredLimit = ProductType::where('id', $request->get('product_id'))
            ->first(array(
                'sum_assured_limit'
            ));
        if ($asssuredLimit->sum_assured_limit < $request->get('sum')) {
            return response()
                ->json(['error' => 1, 'value' => $asssuredLimit->sum_assured_limit]);
        } else {
            return response()
                ->json(['error' => 0]);
        }
    }

    /**
     * method to retrieve all the product plans related with the product.
     *param: product_id (request)
     * @return json
     */
    public function productPlans(Request $request)
    {
        try {
            //code...
            $productplans = Productplan::where('product_id', $request->product_id)
                ->where('status', 1)
                ->get();

            return response()
                ->json($productplans);
        } catch (Exception $ex) {
            //throw $th;
            return response()->json($ex->getMessage());
        }
    }



    //api
    public function getProducts()
    {
        $product = Product::where('status', 1)
            ->get(/* array('id', 'name', 'slug', 'premium_type_id', 'image', 'has_activation_code', 'has_member', 'has_vehicle') */);
        if ($product != null) {
            return response()->json(['products' => $product], 200);
        } else {
            return response()->json('No product available', 401);
        }
    }
}
