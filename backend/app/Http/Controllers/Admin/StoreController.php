<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\City;
use AlphaDirect\Partners;
use AlphaDirect\Product;
use AlphaDirect\State;
use AlphaDirect\Stores;
use Http\Client\Exception;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Store_inventory;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;
use Redirect;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{

    public function index()
    {
        try {
            $partners = Partners::get(array('id', 'name'));
            return view('admin.store.index', compact('partners'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function checkName(Request $request)
    {
        if ($request->form == 'create') {
            $nums = Stores::where('name', $request->name)->count();
            return response()->json(['status' => $nums > 0 ? true : false]);
        } elseif ($request->form == 'edit') {
            $nums = Stores::where(['name', '=', $request->name,], ['id', '<>', $request->id])->count();
            return response()->json(['status' => $nums > 0 ? true : false]);
        }
    }

    public function data(Request $request)
    {

        $data1 = Stores::orderBy('id', 'DESC');

        if ($request->partner_filter != -1)
            $data1->where('partner_id', $request->partner_filter);

        $data = $data1->get();

        return DataTables::of($data)
            ->editColumn('created_at', function ($data) {
                if ($data->created_at != null) {
                    return   \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $data->created_at)->format('Y-m-d H:i') ;
                }
            })
            ->editColumn('status', function ($data) {

                if ($data->status == 1)
                    return 'Active';
                else
                    return 'In-active';
            })
            ->editColumn('partner_id', function ($data) {

                if ($data->partner_id) {
                    $par = Partners::where('id', $data->partner_id)->first(array('name'));
                    return $par->name;
                } else {
                    return 'N/A';
                }
            })
            ->addColumn('actions', function ($data) {
                $actions = '';
                if (Auth::user()->can('store-edit')) {
                    $actions .= '<a href="' . route('admin.store.edit', $data->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                }
                $actions .= '<button data-id="' . $data->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </button>';
                $actions .= '<button data-id="' . $data->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md show" title="show">
                                <i class="la la-eye"></i>
                            </button>';
                return $actions;
            })
            ->rawColumns(['status', 'actions'])
            ->make(true);
    }


    public function create()
    {
        try {
            $partners = Partners::where('status', 1)->get();
            $states = State::where('country_id', 28)->get(['id', 'name']);
            $products = DB::select(DB::raw("select `products`.`id` as `product_id`, `products`.`name`  as 'product_name', `product_plans`.`id` as 'plan_id', `product_plans`.`name` as 'plan_name' from `products` inner join `product_plans` on `products`.`id` = `product_plans`.`product_id` where `products`.`has_activation_code`=1 AND `products`.`status` = 1 AND `product_plans`.`status` = 1"));

            return view('admin.store.create', compact('partners', 'states', 'products'));
        } catch (Exception $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }
    }

    public function store(Request $request)
    {
        try {
            $store = Stores::create([
                'name'              => $request->name,
                'partner_id'        => $request->partner,
                'city'              => $request->city,
                'state_id'          => $request->state,
                'address'           => $request->address,
                'status'            => isset($request->status) ? 1 : 0,
                'appearance_order'  => isset($request->order) ? $request->order : 0,
            ]);

            $inventory = new Store_inventory();
            if ($store) {
                foreach ($request->data as $row) {
                    $inventory->create([
                        'store_id'      => $store->id,
                        'plan_name'     => $row['plan_name'],
                        'plan_id'       => $row['plan_id'],
                        'product_name'  => $row['product_name'],
                        'product_id'    => $row['product_id'],
                        'max_inventory' => $row['max_inventory'],
                        'min_inventory' => $row['min_inventory'],
                        'counter'       => $row['counter']
                    ]);
                }
            }
            return redirect()->route('admin.store')->with('success', 'Store Created Successfully');
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }


    public function bundleEdit(Request $request)
    {
        $products = DB::select(DB::raw("select `products`.`id` as `product_id`, `products`.`name`  as 'product_name', `product_plans`.`id` as 'plan_id', `product_plans`.`name` as 'plan_name' from `products` inner join `product_plans` on `products`.`id` = `product_plans`.`product_id` where `products`.`has_activation_code`=1 AND `products`.`status` = 1 AND `product_plans`.`status` = 1"));

        return view('admin.store.storesBundleEdit')->with('stores', Stores::where('status', 1)->get(['id', 'name']))
            ->with('products', $products);
    }

    public function bundleUpdate(Request $request)
    {
        try {
            $stores = $request->stores;
            $products = $request->data;
            // dd($products);
            foreach ($stores as $store) {
                foreach ($products as $product) {
                    Store_inventory::where([
                        ['store_id',    '=', $store],
                        ['product_id',  '=', $product['product_id']],
                        ['plan_id',     '=', $product['plan_id']]
                    ])
                        ->update([
                            'max_inventory' => $product['max_inventory'],
                            'min_inventory' => $product['min_inventory'],
                            'counter'       => $product['counter'],
                        ]);
                }
            }
            return redirect()->route('admin.store')->with('success', 'Stores Updated Successfully');
        } catch (Exception $e) {
            return redirect()->route('admin.store')->with('error', $e->getMessage());
        }
    }
    public function addstoresPartner()
    {
        return view('admin.store.addPartner');
    }

    public function storePartner(Request $request)
    {
        try {
            $partner = new Partners();
            $partner->name = $request->name;
            $partner->status = isset($request->status) ? 1 : 0;
            $partner->save();
            return redirect()->route('admin.store.create')->with('success', 'Partner Created Successfully');
        } catch (Exception $e) {
            return redirect()->route('admin.store')->with('error', $e->getMessage());
        }
    }

    public function getStores()
    {
        try {
            $stores = Stores::where('status', 1)->orderBy('name')->orderBy('appearance_order','ASC')->get();

            return response()->json(['Status' => 'Success', 'data' => $stores], 200);
        } catch (Exception $e) {
            return response()->json(['Status' => 'Failed', 'Description' => $e->getMessage()], $e->getCode());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \AlphaDirect\Stores  $store
     * @return \Illuminate\Http\Response
     */
    public function show(Stores $store)
    {
        $model_body = '';

        $inventories = Store_inventory::where('store_id', $store->id)->get();

        if (count($inventories) > 0) {
            $model_body .= '<div class="modal-header">';
            $model_body .= '<h5 class="modal-title">' . $store->name . ' </h5>';
            $model_body .= '<button type="button" class="close" data-dismiss="modal" aria-label="Close">';
            $model_body .= '<span aria-hidden="true">&times;</span>';
            $model_body .= '</button>';
            $model_body .= '</div>';
            $model_body .= '<div class="modal-body">';
            $model_body .= '<table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Product</th>
                                    <th scope="col">Plan</th>
                                    <th scope="col">Minimum store inventory</th>
                                    <th scope="col">Maximum store inventory</th>
                                    <th scope="col">Actual Count</th>
                                </tr>
                            </thead>
                            <tbody>';
            foreach ($inventories as $i => $inventory) {
                $model_body .= '<tr>';
                $model_body .= '<td scope="row">' . ($i + 1) . '</td>';
                $model_body .= '<td>' . $inventory->product_name . '</td>';
                $model_body .= '<td>' . $inventory->plan_name . '</td>';
                $model_body .= '<td>' . $inventory->min_inventory . '</td>';
                $model_body .= '<td>' . $inventory->max_inventory . '</td>';
                $model_body .= '<td>' . $inventory->counter . '</td>';
                $model_body .= '</tr>';
            }

            $model_body .= '</tbody>';
            $model_body .= '</table>';
            $model_body .= '</div>';
            $model_body .= '<div class="modal-footer">';
            $model_body .= '<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>';
            $model_body .= '<a href="' .  route('admin.store.edit', $store->id) . '" class="btn btn-primary">Edit</a>';
            $model_body .= '</div>';
            // dd($model_body);
            return response()->json(['model_body' => $model_body, 'status' => 'success'], 200);
        } else {
            $model_body .= '<h4 class="text-warning">Data not found</h4>';
            return response()->json(['model_body' => $model_body, 'status' => 'false'], 500);
        }
    }

    public function edit($id)
    {
        try {

            $store = Stores::where('id', $id)->orderBy('id', 'DESC')->first();
            $products = Store_inventory::where('store_id', $store->id)->get();
            $partners = Partners::where('status', 1)->get(array('id', 'name'));
            $states = State::where('country_id', 28)->get(['id', 'name']);
            $cities = City::where('state_id', $store->state_id)->get(['id', 'name']);

            return view('admin.store.edit', compact('store', 'partners', 'states', 'cities', 'products'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage() . ' - ' . $e->getCode());
        }
    }

    public function updateStore(Request $request, $id)
    {
        $store = Stores::findOrFail($id)->first();
        try {
           /*  foreach ($request->data as $row) {
                Store_inventory::where('id', $row['inventory_id'])
                    ->where('store_id', $store->id)
                    ->update([
                        'max_inventory' => $row['max_inventory'],
                        'min_inventory' => $row['min_inventory'],
                        'counter'       => $row['counter'],
                    ]);
            } */

            $store->whereId($id)->update([
                'name'          => $request->name,
                'partner_id'     => $request->partner,
                'status'        => $request->store_status,
                'address'       => $request->address,
                'city'          => $request->city,
                'state_id'      => $request->state_id,
            ]);
            return redirect()->route('admin.store')->with('success', 'Store Updated Successfully');
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage() . ' - ' . $e->getCode());
        }
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  \AlphaDirect\Stores  $store
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $store = Stores::findOrFail($id)->first();

        try {
            Store_inventory::where('store_id', $store->id)->delete();
            $store->delete();
            return response()->json(['status' => 'success',], 200);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage() . ' - ' . $e->getCode());
        }
    }
}
