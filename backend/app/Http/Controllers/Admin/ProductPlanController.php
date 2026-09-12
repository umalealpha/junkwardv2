<?php
namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Formula;
use AlphaDirect\Lookup;
use AlphaDirect\Product;
use AlphaDirect\User;
use AlphaDirect\ProductType;
use Illuminate\Http\Request;
use AlphaDirect\Productplan;
use AlphaDirect\Region;
use Illuminate\Support\Facades\Auth;
use Redirect;
use Yajra\DataTables\DataTables;
use DB;

class ProductPlanController extends Controller
{
    /**
     * Show a list of all product plans.
     *
     * @return View product type listing page
     */
    public function index()
    {
        if (Auth::user()->hasPermissionTo('product-plan-list'))
        {
            // Show the page
            $products = Product::get(array(
                'id',
                'name'
            ));
            return view('admin.ProductPlan.index', compact('products'));
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
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
        $productPlan = Productplan::get(['id', 'name', 'product_id','billing','premium_type', 'premium', 'product_type_id', 'status', 'created_at']);
        if ($request->product_filter != - 1)
        {
            $productPlan = Productplan::where('product_id', $request->product_filter)
                ->get(['id', 'name', 'product_id', 'premium_type','billing', 'premium', 'product_type_id', 'status', 'created_at']);
        }

        return DataTables::of($productPlan)
            ->addColumn('product_name', function ($productPlan)
            {
                $product = Product::where('id', $productPlan->product_id)
                    ->first(array(
                        'name'
                    ));

                return $product ? $product->name : '';
            })->editColumn('status', function ($productPlan)
            {
                if ($productPlan->status)
                {
                    return 'Active';
                }
                else
                {
                    return 'In-Active';
                }
            })->editColumn('product_type_id', function ($productPlan)
            {
                $productType = ProductType::where('id', $productPlan->product_type_id)
                    ->first();

                return $productType ? $productType->name : '-';
            })->editColumn('created_at', function ($productPlan)
            {
                return $productPlan
                    ->created_at
                    ->diffForHumans();
            })->addColumn('actions', function ($productPlan)
            {
                $actions = '';
                if (Auth::user()->hasPermissionTo('product-plan-edit'))
                {
                    $actions .= '<a href="' . route('admin.productPlan.edit', $productPlan->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                }
                else
                {
                    $actions .= '<a href="' . route('admin.productPlan.edit', $productPlan->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="flaticon-eye"></i>
                            </a>';
                }
                if (Auth::user()
                    ->hasPermissionTo('product-plan-delete'))
                {
                    $actions .= '<a href="" value="' . $productPlan->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
                }
                return $actions;
            })
            ->editColumn('billing', function ($productPlan)
            {
                if ($productPlan->billing != null)
                {
                    return $productPlan->billing;
                }
                else
                {
                    return 'N/A';
                }
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    /**
     * Show a page to create product plan.
     *
     * @return View product plan create page
     */
    public function create(Request $request)
    {
        if (Auth::user()->hasPermissionTo('product-plan-create'))
        {
            $productPlan = Productplan::get();
            $lookUps = Lookup::where('key', 'premium_type')->get(array(
                'id',
                'value'
            ));
            $products = Product::where('status', 1)->get();
            $producttypes = ProductType::where('status', 1)->get();
            $premiumTypes = Lookup::where('key', 'premium_type')->get(array(
                'id',
                'value'
            ));
            $regions = Region::where('status', 1)->get();
            $formulas = Formula::get();

            return view('admin/ProductPlan/create', compact('regions', 'formulas', 'producttypes', 'products', 'productPlan', 'lookUps', 'premiumTypes'));
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * method to store product plan data from create page.
     *
     * @return View product plan listing page
     */
    public function store(Request $request)
    {

        $productplan = new Productplan();

        $productplan->name = $request->name;
        $productplan->slug = $request->slug;
        $productplan->product_id = $request->product_id;
        $productplan->flutter_plan_id = $request->flutter_plan_id;
        $productplan->premium_type = $request->premium_type;
        $productplan->premium = $request->premium;
        $productplan->sum_assured = $request->sum;
        $productplan->region_id = $request->region;
        $productplan->product_type_id = $request->product_type_id;
        $productplan->formula_id = $request->formula;
        $productplan->billing = $request->billing;
        $productplan->status = $request->status;

        // Retrieve the product using product_id from the request
        $product = Product::find($request->product_id);

        if ($product->product_type_id == 4) {
            // Retrieve the input arrays from the request
            $coapplicant_ids = $request->input('coapplicant_id', []);
            $relations = $request->input('coapplicant_relation', []);
            $premiums  = $request->input('coapplicant_premium', []);

            // Build an array of coapplicant data as key-value pairs
            // Optionally, you can choose to store each entry as an object with 'relation' and 'premium'
            $coapplicants = [];
            foreach ($relations as $index => $relation) {
                if (isset($premiums[$index]) && isset($coapplicant_ids[$index])) {
                    $coapplicants[] = [
                        'id'       => $coapplicant_ids[$index],
                        'relation' => $relation,
                        'premium'  => $premiums[$index],
                    ];
                }
            }

            // Store the coapplicant data in JSON format in the 'coapplicants' column
            $productplan->premiumAndRelation = json_encode($coapplicants);

        }

        $productplan->save();
        activity('Product Plan')
            ->performedOn($productplan)->causedBy(User::where('id', Auth()
                ->user()
                ->id)
                ->first())
            ->log('Product Plan Created');

        return redirect(url('admin/productPlan'))
            ->with('message', 'Product Plan succesfully');
    }

    /**
     * Show a page to edit specific product plan .
     *param: product plan id($id)
     * @return View product plan edit page
     */
    public function edit($id)
    {

        $products = Product::get();
        $formulas = Formula::get();
        $premium_types = Lookup::where('key', 'premium_type')->get(array(
            'id',
            'value'
        ));
        $productPlan = Productplan::where('id', $id)->first();
        $productType = ProductType::get(array(
            'id',
            'name'
        ));

        $productTypeId = Product::where('id',$productPlan->product_id)->first('product_type_id');

        if (isset($productPlan->premiumAndRelation)) {
            $productPlan->premiumAndRelation = json_decode($productPlan->premiumAndRelation, true);
        }
        if (Auth::user()->hasPermissionTo('product-plan-edit'))
        {
            return view('admin.ProductPlan.edit', compact('products', 'productPlan', 'productType', 'premium_types', 'formulas','productTypeId'));
        }
        elseif (Auth::user()
            ->hasPermissionTo('product-plan-list'))
        {
            return view('admin.ProductPlan.view', compact('products', 'productPlan', 'productType', 'premium_types', 'formulas','productTypeId'));
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }


    /**
     * method to update product plan data from edit page.
     *product plan id ($id)
     * @return View product plan listing page
     */

    public function update($id, Request $request)
    {
        //try{
            $productPlan = Productplan::where('id', $id)->first();
            $productPlan->name = $request->get('name');
            $productPlan->slug = $request->get('slug');
            $productPlan->premium_type = $request->get('premium_type');
            $productPlan->premium = $request->get('premium');

            // Retrieve the product using product_id from the request
            $product = Product::find($request->product);

            if ($product->product_type_id == 4) {
                // Retrieve the input arrays from the request
                $coapplicant_ids = $request->input('coapplicant_id', []);
                $relations = $request->input('coapplicant_relation', []);
                $premiums  = $request->input('coapplicant_premium', []);

                // Build an array of coapplicant data as key-value pairs
                // Optionally, you can choose to store each entry as an object with 'relation' and 'premium'
                $coapplicants = [];
                foreach ($relations as $index => $relation) {
                    if (isset($premiums[$index]) && isset($coapplicant_ids[$index])) {
                        $coapplicants[] = [
                            'id'       => $coapplicant_ids[$index],
                            'relation' => $relation,
                            'premium'  => $premiums[$index],
                        ];
                    }
                }

                // Store the coapplicant data in JSON format in the 'coapplicants' column
                $productPlan->premiumAndRelation = json_encode($coapplicants);

            }

            $productPlan->flutter_plan_id = $request->flutter_plan_id;
            $productPlan->billing = $request->billing;
            $productPlan->sum_assured = $request->get('sum');
            if ($request->formula_id != - 1) $productPlan->formula_id = $request->formula;
            if ($request->product_type_id != - 1) $productPlan->product_type_id = $request->product_type_id;
            if ($request->product_id != - 1) $productPlan->product_id = $request->product;
            if ($request->status == NULL)
            {
                $productPlan->status = "0";
            }
            else
            {
                $productPlan->status = "1";
            }
            $productPlan->save();

            if ($productPlan->save())
            {
                activity('Product Plan')
                    ->performedOn($productPlan)->causedBy(User::where('id', Auth()
                        ->user()
                        ->id)
                        ->first())
                    ->log('Product Plan Updated');
                // Redirect to the home page with success menu
                return Redirect::route('admin.productPlan.index')
                    ->with('success', 'Product Plan Updated Successfully');
            }
            DB::commit();
        // }catch(\Exception $ex)
        // {
        //     DB::rollBack();
        //     //throw $th;
        //     return redirect(url('admin/productPlan'))->with('error', 'Something went wrong');
        // }
    }

    /**
     * method to return modal body for confirm-delete.
     *
     * @return json
     */
    public function getModalDelete(Request $request)
    {
        $check = productplan::where('id', $request->get('id'))
            ->count();
        $body = 'Are you sure you want to delete the Product Plan ?';
        return response()->json(['status' => 'success', 'id' => $request->get('id') , 'body' => $body]);
    }

    /**
     *deletes specific user accounts
     *param: user id ($id)
     * @return product plan listing page
     */
    public function destroy($id)
    {
        try
        {

            activity('Product Plan')->performedOn(Productplan::where('id', $id)->first())
                ->causedBy(User::where('id', Auth()
                    ->user()
                    ->id)
                    ->first())
                ->log('Product Plan Deleted');

            Productplan::where('id', $id)->delete();

            return Redirect::route('admin.ProductPlan.index')
                ->with('success', 'Product Plan Deleted Successfully');

        }
        catch(\Exception $e)
        {
            return Redirect::route('admin.productPlan.index')->with('error', 'Something Went Wrong');
        }

    }

    /**
     *checks sum assured from specific product
     *param: product id ($id)
     * @return JSON
     */
    public function checkSumAssured(Request $request)
    {
        $asssuredLimit = Product::where('id', $request->get('product_id'))
            ->first(array(
                'sum_insured'
            ));
        if ($asssuredLimit->sum_insured < $request->get('sum')) return response()
            ->json(['error' => 1, 'value' => $asssuredLimit->sum_insured]);
        else return response()
            ->json(['error' => 0]);
    }

    public function getPlans()
    {
        $product = Productplan::where('status', 1)
            ->get(/* array('id', 'name', 'slug', 'premium_type_id', 'image', 'has_activation_code', 'has_member', 'has_vehicle') */);
        if ($product != null) {
            return response()->json(['plans' => $product], 200);
        } else {
            return response()->json('No product available', 401);
        }
    }
}

