<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Activation;
use AlphaDirect\Branch;
use AlphaDirect\Customer;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Lookup;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\ProductType;
use AlphaDirect\User;
use AlphaDirect\Vendor;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Redirect;
use Yajra\DataTables\DataTables;
use AlphaDirect\Helper;
use AlphaDirect\Exports\ActivationCodeStore;
use AlphaDirect\Events\ActivationCode;

class ActivationController extends Controller
{

    /**
     * Show a list of all Activation codes.
     *
     * @return View
     */

    public function index()
    {
        // Show the page
        return view('admin.activation.index');
    }


    /*
    * Pass data through ajax call on mail policy listing table
    */
    /**
     * @return mixed
     */
    public function data()
    {
      /*  $activation = Activation::get(array('id', 'group_id', 'product_id', 'product_type_id', 'product_plan_id', 'vendor', 'printed', 'recycle', 'recycle_date', 'branch', 'rack_no', 'city', 'trial_periods'))->unique('group_id');
        return DataTables::of($activation)
            ->editColumn('serial_min', function ($activation) {
                $single_agent = Activation::where('group_id', $activation->group_id)->select(\DB::raw("MIN(serial_code) AS serial_min, MAX(serial_code) AS serial_max, MIN(activation_code) AS act_min, MAX(activation_code) AS act_max"))->first();
                return $single_agent->serial_min . '-' . $single_agent->serial_max;
            })
            ->editColumn('activation_code', function ($activation) {
                $single_agent = Activation::where('group_id', $activation->group_id)->select(\DB::raw("MIN(serial_code) AS serial_min, MAX(serial_code) AS serial_max, MIN(activation_code) AS act_min, MAX(activation_code) AS act_max"))->first();
                return $single_agent->act_min . '-' . $single_agent->act_max;
            })
            ->editColumn('product_type_id', function ($activation) {
                $productType = ProductType::where('id', $activation->product_type_id)->first(array('name'));
                return $productType ? $productType->name : '';
            })
            ->editColumn('product_id', function ($activation) {
                $product = Product::where('id', $activation->product_id)->first(array('name'));
                return $product ? $product->name : '';
            })
            ->editColumn('vendor', function ($activation) {
                $vendor = Vendor::where('id', $activation->vendor)->first(array('name'));
                return $vendor ? $vendor->name : '';
            })
            ->editColumn('branch', function ($activation) {
                $branches = Branch::where('id', $activation->branch)->first(array('name'));
                return $branches ? $branches->name : '';
            })
            ->editColumn('product_plan_id', function ($activation) {
                $productPlan = Productplan::where('id', $activation->product_plan_id)->first(array('name'));
                return $productPlan ? $productPlan->name : '';
            })
            ->editColumn('status', function ($activation) {
                if ($activation->status == 0) {
                    return '<span class="kt-font-bold kt-font-danger">Deactivated</span>';
                } else {
                    return '<span class="kt-font-bold kt-font-brand">Activated</span>';
                }
            })
            ->addColumn('actions', function ($activation) {
                $new = Activation::where('group_id', $activation->group_id)->orderBy('id', 'desc')->first(array('id', 'download'));
                $actions = '<a href="' . route('admin.activation.show', $activation->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                                <i class="flaticon-eye"></i></a>

                             <a href="' . route('admin.activation.list', $activation->group_id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Code List">
                                <i class="flaticon-list"></i></a>';
                if ($new->download != NULL) {
                    $actions .=   '<a href="' . \AlphaDirect\Helper::getCloudFrontURL($new->download) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="download"><i class="flaticon-download"></i></a>';
                }

                return $actions;
            })
            ->rawColumns(['actions', 'status'])
            ->make(true);*/

            $activation = Activation::orderby('id', 'DESC')->groupBy('group_id')->select(array('id', 'group_id', 'product_id', 'product_type_id', 'product_plan_id', 'vendor', 'printed', 'recycle', 'recycle_date', 'branch', 'rack_no', 'city', 'trial_periods'));

            return DataTables::of($activation)
                ->editColumn('serial_min', function ($activation) {
                    $single_agent = Activation::where('group_id', $activation->group_id)->select(\DB::raw("MIN(serial_code) AS serial_min, MAX(serial_code) AS serial_max, MIN(activation_code) AS act_min, MAX(activation_code) AS act_max"))->first();
                    return $single_agent->serial_min . '-' . $single_agent->serial_max;
                })
                ->editColumn('activation_code', function ($activation) {
                    $single_agent = Activation::where('group_id', $activation->group_id)->select(\DB::raw("MIN(serial_code) AS serial_min, MAX(serial_code) AS serial_max, MIN(activation_code) AS act_min, MAX(activation_code) AS act_max"))->first();
                    return $single_agent->act_min . '-' . $single_agent->act_max;
                })
                ->editColumn('product_type_id', function ($activation) {
                    $productType = ProductType::where('id', $activation->product_type_id)->first(array('name'));
                    return $productType ? $productType->name : '';
                })
                ->editColumn('product_id', function ($activation) {
                    $product = Product::where('id', $activation->product_id)->first(array('name'));
                    return $product ? $product->name : '';
                })
                ->editColumn('vendor', function ($activation) {
                    $vendor = Vendor::where('id', $activation->vendor)->first(array('name'));
                    return $vendor ? $vendor->name : '';
                })
                ->editColumn('branch', function ($activation) {
                    $branches = Branch::where('id', $activation->branch)->first(array('name'));
                    return $branches ? $branches->name : '';
                })
                ->editColumn('product_plan_id', function ($activation) {
                    $productPlan = Productplan::where('id', $activation->product_plan_id)->first(array('name'));
                    return $productPlan ? $productPlan->name : '';
                })
                ->editColumn('status', function ($activation) {
                    if ($activation->status == 0) {
                        return '<span class="kt-font-bold kt-font-danger">Deactivated</span>';
                    } else {
                        return '<span class="kt-font-bold kt-font-brand">Activated</span>';
                    }
                })
                ->addColumn('actions', function ($activation) {
                    $new = Activation::where('group_id', $activation->group_id)->orderBy('id', 'desc')->first(array('id', 'download'));
                    $actions = '<a href="' . route('admin.activation.show', $activation->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                                    <i class="flaticon-eye"></i></a>

                                 <a href="' . route('admin.activation.list', $activation->group_id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Code List">
                                    <i class="flaticon-list"></i></a>';
                    if ($new->download != NULL) {
                        $actions .= '<a href="' . \AlphaDirect\Helper::getCloudFrontURL($new->download) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="download"><i class="flaticon-download"></i></a>';
                    }

                    return $actions;
                })
                ->rawColumns(['actions', 'status'])
                ->make(true);

    }

    /**
     * Show a page to create activation code.
     *
     * @return View
     */
    public function create(Request $request)
    {
        $productTypes = ProductType::where('status', 1)->get(array('id', 'name'));
        $products = Product::where('status', 1)->get(array('id', 'name'));
        $productPlans = Productplan::where('status', 1)->get(array('id', 'name'));
        $vendors = Vendor::get(array('id', 'name'));
        $branches = Branch::get(array('id', 'name'));
        $billingCycles = Lookup::where('key', 'billing_cycle')->get(array('id', 'value'));
        return view('admin.activation.create', compact('productTypes', 'products', 'productPlans', 'vendors', 'branches', 'billingCycles'));
    }

    /**
     * method to store activation codes from create page.
     *
     * @return View
     */
    public function store(Request $request)
    {      $data = $request->all();
           $response =   event(new ActivationCode($data));
           return Redirect::route('admin.activation.index')->with('success', 'Activation Code Send Via Email And Sms  Shortly');
      /*  $latest_code = Activation::orderBy('id', 'DESC')->first(array('serial_code', 'activation_code', 'group_id'));
        if ($latest_code == null) {
            $serial_code = 'AAAAAA';
            $activation_code = Helper::gen_ustring(10000000, 99999999);
            $group_id = 1;
        } else {
            $serial_code = $latest_code->serial_code;
            $group_id = $latest_code->group_id + 1;
            $activation_code = Helper::gen_ustring(10000000, 99999999);
        }
        for ($i = 1; $i <= $request->get('noofcodes'); $i++) {
            $activation = new Activation();
            if ($latest_code != null) {
                $var = base_convert($serial_code, 36, 10);
                $var++;
                //To check if number in serial code than increament
                while (preg_match('~[0-9]+~', strtoupper(base_convert($var, 10, 36)))) {
                    $var++;
                }
                $serial_code = strtoupper(base_convert($var, 10, 36));
            }
            $activation->group_id = $group_id;
            $activation->serial_code = $serial_code;
            $check = Activation::where('activation_code', $activation_code)->count();
            while ($check > 0) {
                $activation_code = Helper::gen_ustring(10000000, 99999999);
                $check = Activation::where('activation_code', $activation_code)->count();
            }
            $activation->activation_code = $activation_code;
            $activation->vendor = $request->get('vendor');
            $activation->branch = $request->get('branch');
            $activation->rack_no = $request->get('rack_no');
            $activation->trial_periods = $request->get('trial_periods');
            $activation->trial_coverage = $request->get('trial_coverage');
            $activation->country = $request->get('country');
            $activation->city = $request->get('city');
            $activation->state = $request->get('state');
            $activation->product_type_id = $request->get('product_type');
            $activation->product_id = $request->get('product');
            $activation->premium_type_id = Product::where('id', $request->get('product'))->first(array('premium_type_id'))->premium_type_id;
            $activation->product_plan_id = $request->get('plan');
            $activation->status = 0;
            $saved = $activation->save();
            //To getin If condition
            $latest_code = 1;
        }
        if ($saved) {
            // $codes = Activation::orderBy('id', 'desc')->take($request->get('noofcodes'))->get();
            // $codes = $codes->sortBy('id');
            // $exportData = Excel::create('activation_codes', function ($excel) use ($codes) {
            //     $excel->sheet('activation_codes', function ($sheet) use ($codes) {
            //         $sheet->row(1, array('serial_code', 'activation_code', 'Product Type', 'Product', 'Product Plan', 'Vendors', 'Branch', 'Rack Number', 'Trial Period', 'Trial Coverage', 'City', 'State', 'Country', 'Status'));
            //         $records = $codes;
            //         $cnt = 2;
            //         foreach ($records as $item) {
            //             $product = Product::where('id', $item->product_id)->first(array('name'));
            //             if ($product != null) {
            //                 $product_name = $product->name;
            //             } else {
            //                 $product_name = null;
            //             }
            //             $plan = Productplan::where('id', $item->product_plan_id)->first(array('name'));
            //             if ($plan != null) {
            //                 $plan_name = $plan->name;
            //             } else {
            //                 $plan_name = null;
            //             }
            //             $type = ProductType::where('id', $item->product_type_id)->first(array('name'));
            //             if ($type != null) {
            //                 $type_name = $type->name;
            //             } else {
            //                 $type_name = null;
            //             }
            //             $vendor = Vendor::where('id', $item->vendor)->first(array('name'));
            //             if ($vendor != null) {
            //                 $vendor_name = $vendor->name;
            //             } else {
            //                 $vendor_name = null;
            //             }
            //             $branch = Branch::where('id', $item->branch)->first(array('name'));
            //             if ($type != null) {
            //                 $branch_name = $branch->name;
            //             } else {
            //                 $branch_name = null;
            //             }
            //             if ($item->status == 0) {
            //                 $status = 'Deactivated';
            //             } else {
            //                 $status = 'Activated';
            //             }
            //             $sheet->appendRow($cnt, array($item->serial_code, $item->activation_code, $type_name, $product_name, $plan_name, $vendor_name, $branch_name, $item->rack_no, $item->trial_periods, $item->trial_coverage, $item->city, $item->state, $item->country, $status));
            //             $cnt++;
            //         }
            //     });
            // })->store('xlsx', false, true);

            $exportData = Excel::store(new ActivationCodeStore($request->get('noofcodes')), 'activation_codes.xlsx');

            $filePath = Storage::disk('s3')->url('activation_codes.xlsx');

            $new = Activation::where('id', $activation->id)->first(array('id', 'serial_code', 'activation_code', 'group_id'));
            $new->download = $filePath;

            $new->save();
            activity('Activation')
                ->performedOn($activation)
                ->causedBy(User::where('id', Auth()->user()->id)->first())
                ->log('Activation Created');

            Session::flash('download_file', str_replace(env('AWS_URL'), env('AWS_CLOUDFRONT'), $filePath));

            return Redirect::route('admin.activation.create')->with('success', 'Activation Code Created Successfully');
        } else {
            return Redirect::route('admin.activation.create')->with('error', 'Something Went Wrong');
        } */
    }

    /**
     * method to store activation codes from API.
     *
     * @return View
     */
    public function generateActCodeApi(Request $request)
    {
        usleep(10000);
        $latest_code = Activation::orderBy('id', 'DESC')->first(array('serial_code', 'activation_code', 'group_id'));
        if ($latest_code == null) {
            $serial_code = 'AAAAAA';
            $activation_code = Helper::gen_ustring(10000000, 99999999);
            $group_id = 1;
        } else {
            $serial_code = $latest_code->serial_code;
            $group_id = $latest_code->group_id + 1;
            $activation_code = Helper::gen_ustring(10000000, 99999999);
        }
        for ($i = 1; $i <= $request->get('noofcodes'); $i++) {
            $activation = new Activation();
            if ($latest_code != null) {
                $var = base_convert($serial_code, 36, 10);
                $var++;
                //To check if number in serial code than increament
                while (preg_match('~[0-9]+~', strtoupper(base_convert($var, 10, 36)))) {
                    $var++;
                }
                $serial_code = strtoupper(base_convert($var, 10, 36));
            }
            $activation->group_id = $group_id;
            $activation->serial_code = $serial_code;
            $check = Activation::where('activation_code', $activation_code)->count();
            while ($check > 0) {
                $activation_code = Helper::gen_ustring(10000000, 99999999);
                $check = Activation::where('activation_code', $activation_code)->count();
            }
            $activation->activation_code = $activation_code;
            $activation->vendor = $request->get('vendor');
            $activation->branch = $request->get('branch');
            $activation->rack_no = $request->get('rack_no');
            $activation->trial_periods = $request->get('trial_periods');
            $activation->trial_coverage = $request->get('trial_coverage');
            $activation->country = $request->get('country');
            $activation->city = $request->get('city');
            $activation->state = $request->get('state');
            $activation->product_type_id = $request->get('product_type');
            $activation->product_id = $request->get('product');
            $activation->premium_type_id = Product::where('id', $request->get('product'))->first(array('premium_type_id'))->premium_type_id;
            $activation->product_plan_id = $request->get('plan');
            $activation->status = 0;
            $saved = $activation->save();
            //To getin If condition
            $latest_code = 1;
        }
        if ($saved) {
            $codes = Activation::orderBy('id', 'desc')->take($request->get('noofcodes'))->get();
            $codes = $codes->sortBy('id');
            $exportData = Excel::create('activation_codes', function ($excel) use ($codes) {
                $excel->sheet('activation_codes', function ($sheet) use ($codes) {
                    $sheet->row(1, array('serial_code', 'activation_code', 'Product Type', 'Product', 'Product Plan', 'Vendors', 'Branch', 'Rack Number', 'Trial Period', 'Trial Coverage', 'City', 'State', 'Country', 'Status'));
                    $records = $codes;
                    $cnt = 2;
                    foreach ($records as $item) {
                        $product = Product::where('id', $item->product_id)->first(array('name'));
                        if ($product != null) {
                            $product_name = $product->name;
                        } else {
                            $product_name = null;
                        }
                        $plan = Productplan::where('id', $item->product_plan_id)->first(array('name'));
                        if ($plan != null) {
                            $plan_name = $plan->name;
                        } else {
                            $plan_name = null;
                        }
                        $type = ProductType::where('id', $item->product_type_id)->first(array('name'));
                        if ($type != null) {
                            $type_name = $type->name;
                        } else {
                            $type_name = null;
                        }
                        $vendor = Vendor::where('id', $item->vendor)->first(array('name'));
                        if ($vendor != null) {
                            $vendor_name = $vendor->name;
                        } else {
                            $vendor_name = null;
                        }
                        $branch = Branch::where('id', $item->branch)->first(array('name'));
                        if ($type != null) {
                            $branch_name = $branch->name;
                        } else {
                            $branch_name = null;
                        }
                        if ($item->status == 0) {
                            $status = 'Deactivated';
                        } else {
                            $status = 'Activated';
                        }
                        $sheet->appendRow($cnt, array($item->serial_code, $item->activation_code, $type_name, $product_name, $plan_name, $vendor_name, $branch_name, $item->rack_no, $item->trial_periods, $item->trial_coverage, $item->city, $item->state, $item->country, $status));
                        $cnt++;
                    }
                });
            })->store('xlsx', false, true);

            $filePath = 'Activation_codes/' . date('Y-m-d') . "_" . $request->get('product') . "_" . $request->get('plan') . "_" . $request->get('noofcodes') . "_" . $exportData['file'];
            Storage::disk('s3')->put($filePath, \file_get_contents($exportData['full']));
            $new = Activation::where('id', $activation->id)->first(array('id', 'serial_code', 'activation_code', 'group_id'));
            $new->download = $filePath;
            $new->save();

            return response()->json("Activation code generated and placed at " . Storage::disk('s3')->url($filePath));
        } else {
            return response()->json("Error in generating  activation code");
        }
    }

    /**
     * method to view activation codes from index page.
     *
     * @return View
     */
    public function show($id)
    {
        try {
            $activation = Activation::where('id', $id)->first(array('id', 'vendor', 'serial_code', 'activation_code', 'status','branch', 'rack_no', 'country', 'city', 'state', 'product_plan_id', 'product_type_id', 'trial_periods', 'trial_coverage','email','cellphone'));
            $productPlan = Productplan::where('id', $activation->product_plan_id)->first(array('name'));
            $productType = ProductType::where('id', $activation->product_type_id)->first(array('name'));
            $branch = Branch::where('id', $activation->branch)->first(array('name'));
            $vendor = Vendor::where('id', $activation->vendor)->first(array('name'));

            return view('admin.activation.show', compact('productPlan', 'productType', 'activation', 'branch', 'vendor'));
        } catch (\Exception $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }
    }


    /**
     * Show a list of all Activation codes list.
     *
     * @return View
     */
    public function codeList($group_id)
    {
        return view('admin.activation.codeList', compact('group_id'));
    }

    /*
    * Pass data through ajax call to main activation code table
    */
    /**
     * @return mixed
     */
    public function codeListData($group_id)
    {
        $activation = Activation::where('group_id', $group_id)->get(array('id', 'group_id', 'serial_code', 'product_id', 'activation_code', 'product_type_id', 'product_plan_id', 'vendor', 'branch', 'city', 'status'));
        return DataTables::of($activation)

            ->editColumn('status', function ($activation) {
                if ($activation->status == 0) {
                    return '<span class="kt-font-bold kt-font-danger">Deactivated</span>';
                } else {
                    return '<span class="kt-font-bold kt-font-brand">Activated</span>';
                }
            })
            ->editColumn('product_type_id', function ($activation) {
                $productType = ProductType::where('id', $activation->product_type_id)->first(array('name'));
                return $productType ? $productType->name : '';
            })
            ->editColumn('product_plan_id', function ($activation) {
                $productPlan = Productplan::where('id', $activation->product_plan_id)->first(array('name'));
                return $productPlan ? $productPlan->name : '';
            })
            ->editColumn('vendor', function ($activation) {
                $vendor = Vendor::where('id', $activation->vendor)->first(array('name'));
                return $vendor ? $vendor->name : '';
            })
            ->editColumn('branch', function ($activation) {
                $branches = Branch::where('id', $activation->branch)->first(array('name'));
                return $branches ? $branches->name : '';
            })
            ->editColumn('product_id', function ($activation) {
                $product = Product::where('id', $activation->product_id)->first(array('name'));
                return $product ? $product->name : '';
            })

            ->rawColumns(['status'])
            ->make(true);
    }

    /* Methods for fetching activation code with status 1*/

    public function activatedCodeList()
    {
        return view('admin.activation.activatedCodeList');
    }

    /* Methods for fetching activation code with status 1*/
    public function CheckCode()
    {
        $activation = Activation::where('status', 1)->get(array('id', 'group_id', 'serial_code', 'status', 'product_id', 'activation_code', 'product_type_id', 'product_plan_id', 'vendor', 'branch', 'city'));
        return DataTables::of($activation)

            ->editColumn('status', function ($activation) {
                if ($activation->status == 0) {
                    return '<span class="kt-font-bold kt-font-danger">Deactivated</span>';
                } else {
                    return '<span class="kt-font-bold kt-font-brand">Activated</span>';
                }
            })
            ->editColumn('product_type_id', function ($activation) {
                $productType = ProductType::where('id', $activation->product_type_id)->first(array('name'));
                return $productType ? $productType->name : '';
            })
            ->editColumn('product_plan_id', function ($activation) {
                $productPlan = Productplan::where('id', $activation->product_plan_id)->first(array('name'));
                return $productPlan ? $productPlan->name : '';
            })
            ->editColumn('vendor', function ($activation) {
                $vendor = Vendor::where('id', $activation->vendor)->first(array('name'));
                return $vendor ? $vendor->name : '';
            })
            ->editColumn('branch', function ($activation) {
                $branches = Branch::where('id', $activation->branch)->first(array('name'));
                return $branches ? $branches->name : '';
            })
            ->addColumn('policy_number', function ($activation) {
                $policy = Policy::where('activation_code', $activation->activation_code)->first(array('policyNumber', 'customer_id'));
                return $policy ? $policy->policyNumber : '';
            })
            ->editColumn('product_id', function ($activation) {
                $product = Product::where('id', $activation->product_id)->first(array('name'));
                return $product ? $product->name : '';
            })

            ->rawColumns(['status'])
            ->make(true);
    }
    /** view page for checking the activation code */
    public function checkActivationCode()
    {
        return view('admin.activation.activationCodeData');
    }
    /** function to get the data, called by the activation code view page */
    public function checkActivationCodeData(Request $request)
    {
        try {
            //code... ss

            if ($request->activation_code != null || $request->serial_code != null) {

                $query = Activation::with(['vendor', 'product', 'branch', 'productplans', 'product_type'])->orderBy('created_at', 'DESC'); // activation data, with its relationships
                $query->where('activation_code', $request->activation_code)->orWhere('serial_code', $request->serial_code); //Laravel query to get where activation.activation_code == activation_code or activation.serial_code == serial_code
                $activation = $query->get(array('id', 'group_id', 'rack_no', 'trial_periods', 'trial_coverage', 'serial_code', 'status', 'product_id', 'activation_code', 'product_type_id', 'product_plan_id', 'vendor', 'branch', 'city', 'state', 'country'));
                //if the actiovation is not null return the JSON object
                if ($activation != null) {
                    return response()->json($activation);
                } else {
                    return response()->json('failed');
                }
            }
        } catch (\Excpetion $ex) {
            return response($ex->getMessage(), $ex->getLine());
        }
    }
}
