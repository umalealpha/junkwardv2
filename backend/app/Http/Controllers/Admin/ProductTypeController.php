<?php
namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Middleware\RedirectIfAuthenticated;
use AlphaDirect\Product;
use AlphaDirect\ProductType;
use AlphaDirect\Regions;
use AlphaDirect\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Yajra\DataTables\DataTables;
class ProductTypeController extends Controller
{
    /**
     * Show a list of all product types.
     *
     * @return View product listing page
     */
    public function index()
    {
        if (Auth::user()->hasPermissionTo('product-type-list'))
        {
            // Show the page
            return view('admin.ProductType.index');
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * Show a page to create new product type.
     *
     * @return View product type create page
     */
    public function create(Request $request)
    {
        if (Auth::user()->hasPermissionTo('product-type-create'))
        {
            $producttype = ProductType::get();
            return view('admin.ProductType.create', compact('producttype'));
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * method to store product type data from create page.
     *
     * @return View product type list
     */
    public function store(Request $request)
    {

        $producttype = new ProductType();

        $producttype->name = $request->pname;
        $producttype->sum_assured_limit = $request->sumassured;
        if ($request->status)
        {
            $producttype->status = "1";
        }
        else
        {
            $producttype->status = "0";
        }
        $producttype->save();
        activity('Product Type')
            ->performedOn($producttype)->causedBy(User::where('id', Auth()
                ->user()
                ->id)
                ->first())
            ->log('Product Type Created');

        return view('admin.ProductType.index');
    }

    /**
     * Show a page to edit specific product type.
     * product type id ($id)
     * @return View product type edit page
     */
    public function edit($id)
    {
        $producttype = ProductType::where('id', $id)->first();
        if (Auth::user()
            ->hasPermissionTo('product-type-edit'))
        {
            return view('admin.ProductType.edit', compact('producttype'));
        }
        elseif (Auth::user()
            ->hasPermissionTo('product-type-list'))
        {
            return view('admin.ProductType.view', compact('producttype'));
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     *deletes specific product type
     *param: product type id ($id)
     * @return product type listing page
     */
    public function destroy($id)
    {
        try
        {

            activity('Product Type')->performedOn(ProductType::where('id', $id)->first())
                ->causedBy(User::where('id', Auth()
                    ->user()
                    ->id)
                    ->first())
                ->log('Product Type Deleted');

            ProductType::where('id', $id)->delete();
            return Redirect::back()
                ->with('success', 'Product Type Deleted Successfully');

        }
        catch(\Exception $e)
        {
            return Redirect::route('admin.ProductType.index')->with('error', 'Something Went Wrong');
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
        $productType = ProductType::get(['id', 'name', 'sum_assured_limit', 'status', 'created_at']);
        return DataTables::of($productType)->editColumn('status', function ($productType)
        {
            if ($productType->status)
            {
                return 'Active';
            }
            else
            {
                return 'In-Active';
            }

        })->editColumn('product_type_id', function ($productType)
        {
            $productType = ProductType::where('id', $productType->product_type_id)
                ->first();

            return $productType ? $productType->name : '-';
        })->editColumn('created_at', function ($productType)
        {
            return $productType
                ->created_at
                ->diffForHumans();
        })->addColumn('actions', function ($productType)
        {
            $actions = '';
            if (Auth::user()->hasPermissionTo('product-type-edit'))
            {
                $actions .= '<a href="' . route('admin.productType.edit', $productType->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
            }
            else
            {
                $actions .= '<a href="' . route('admin.productType.edit', $productType->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                                <i class="flaticon-eye"></i>
                            </a>';
            }
            if (Auth::user()
                ->hasPermissionTo('product-type-delete'))
            {
                $actions .= '<a href="" value="' . $productType->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
            }

            return $actions;
        })->rawColumns(['actions'])
            ->make(true);
    }

    /**
     * method to update product type data from create page.
     *param: product type id
     * @return View product type list
     */
    public function update($id, Request $request)
    {
        /* dd($request->name);*/
        $producttype = ProductType::where('id', $id)->first();

        $producttype->name = $request->name;
        $producttype->sum_assured_limit = $request->sumassured;
        if ($request->status == NULL)
        {
            $producttype->status = "0";
        }
        else
        {
            $producttype->status = "1";
        }

        $producttype->save();
        activity('Product Type')
            ->performedOn($producttype)->causedBy(User::where('id', Auth()
                ->user()
                ->id)
                ->first())
            ->log('Product Type Updated');
       // return Redirect::route('admin.ProductType.index') ->with('success', 'Product Type Updated Successfully');
      // return view('admin.productType.index')->with('success', 'Product Type Updated Successfully');
      return view('admin.ProductType.index');
    }

    /**
     * method to return modal body for confirm-delete.
     *
     * @return View
     */
    public function getModalDelete(Request $request)
    {
        $check = Product::where('product_type_id', $request->get('id'))
            ->count();
        // Check if we are not trying to delete ourselves
        if ($check)
        {
            // Prepare the error message
            $body = 'Product Type Assigned to Products. Cannot delete this Product Type';
            return response()->json(['status' => 'error', 'body' => $body]);
        }
        else
        {
            $body = 'Are you sure you want to delete the Product Type ?';
            return response()->json(['status' => 'success', 'id' => $request->get('id') , 'body' => $body]);
        }

    }

}

