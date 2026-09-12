<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\Warehouse;
use AlphaDirect\Warehouse_inventory;
use AlphaDirect\City;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use Illuminate\Http\Request;
use DataTables;
use DB;

class WarehouseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        try {
            // $warehouses = Warehouse::latest()->get();
            return view('admin.warehouses.index',);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function checkName(Request $request)
    {
        if ($request->form == 'create') {
            $nums = Warehouse::where('name', $request->name)->count();
            return response()->json(['status' => $nums > 0 ? true : false]);
        } elseif ($request->form == 'edit') {
            $nums = Warehouse::where(['name', '=', $request->name,], ['id', '<>', $request->id])->count();
            return response()->json(['status' => $nums > 0 ? true : false]);
        }
    }

    public function data(Request $request)
    {
        $warehouses = Warehouse::latest()->get();

        return DataTables::of($warehouses)
                    ->editColumn('last_activity', function ($warehouses) {
                        if ($warehouses->created_at != null) {
                            return   \Carbon\Carbon::parse( $warehouses->created_at)->format('Y-m-d H:i') ;
                        }
                    })

                    ->editColumn('name', function ($warehouses) {
                        if ($warehouses->name != null) {
                            return ucwords($warehouses->name);
                        }
                    })
            ->editColumn('status', function ($warehouse) {

                // $status = '';
                // $status .= '<span class="kt-font-bold kt-font-' . $warehouse->status == 1 ? 'primary' : 'danger';
                // $status .= '">';
                // $status .= $warehouse->status == 1 ? 'Active' : 'Deactive' . '</span>';

                return   $warehouse->status == 1 ? 'Active' : 'Deactive';
            })
            ->rawColumns(['status'])
            ->addColumn('actions', function ($warehouse) {
                $actions = '';
                // if (auth::user()->can('warehouses-edit')) {
                $actions .= '<a href="' . route('warehouses.edit', $warehouse->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                $actions .= '<button data-id="' . $warehouse->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </button>';
                $actions .= '<button data-id="' . $warehouse->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md show" title="show">
                                <i class="la la-eye"></i>
                            </button>';
                // }
                return $actions;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        try {
            $products = DB::select(DB::raw("select `products`.`id` as `product_id`, `products`.`name`  as 'product_name', `product_plans`.`id` as 'plan_id', `product_plans`.`name` as 'plan_name' from `products` inner join `product_plans` on `products`.`id` = `product_plans`.`product_id` where `products`.`has_activation_code`=1 AND `products`.`status` = 1 AND `product_plans`.`status` = 1"));
            // dd($products);
            return view('admin.warehouses.create', compact('products'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // dd($request->data);
        try {
            $warehouse = Warehouse::create([
                'name'              => $request->name,
                'address'           => $request->address,
                'status'            => isset($request->status) ? 1 : 0,
            ]);

            $inventory = new Warehouse_inventory();
            if ($warehouse) {
                foreach ($request->data as $row) {
                    $inventory->create([
                        'warehouse_id' => $warehouse->id,
                        'plan_name' => $row['plan_name'],
                        'plan_id' => $row['plan_id'],
                        'product_name' => $row['product_name'],
                        'product_id' => $row['product_id'],
                        'max_inventory' => $row['max_inventory'],
                        'min_inventory' => $row['min_inventory'],
                        'counter'  => $row['counter']
                    ]);
                }
            }

            return redirect()->route('warehouses.index')->with('success', 'Warehouse Created Successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \AlphaDirect\Warehouse  $warehouse
     * @return \Illuminate\Http\Response
     */
    public function show(Warehouse $warehouse)
    {
        $model_body = '';

        $inventories = Warehouse_inventory::where('warehouse_id', $warehouse->id)->get();

        if (count($inventories) > 0) {
            $model_body .= '<div class="modal-header">';
            $model_body .= '<h5 class="modal-title">' . $warehouse->name . ' </h5>';
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
                                    <th scope="col">Minimum warehouse inventory</th>
                                    <th scope="col">Maximum warehouse inventory</th>
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
            $model_body .= '<a href="' .  route('warehouses.edit', $warehouse->id) . '" class="btn btn-primary">Edit</a>';
            $model_body .= '</div>';
            // dd($model_body);
            return response()->json(['model_body' => $model_body, 'status' => 'success'], 200);
        } else {
            $model_body .= '<h4 class="text-warning">Data not found</h4>';
            return response()->json(['model_body' => $model_body, 'status' => 'false'], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \AlphaDirect\Warehouse  $warehouse
     * @return \Illuminate\Http\Response
     */
    public function edit(Warehouse $warehouse)
    {
        try {
            return view('admin.warehouses.edit', compact('warehouse',));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage() . ' - ' . $e->getCode());
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \AlphaDirect\Warehouse  $warehouse
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Warehouse $warehouse)
    {
        // dd($request->data);
        try {
            foreach ($request->data as $row) {
                Warehouse_inventory::where('id', $row['inventory_id'])
                    ->where('warehouse_id', $warehouse->id)
                    ->update([
                        'max_inventory' => $row['max_inventory'],
                        'min_inventory' => $row['min_inventory'],
                        'counter'       => $row['counter'],
                    ]);
            }

            $warehouse->update([
                'name'          => $request->name,
                'status'        => $request->warehouse_status,
                'address'       => $request->address,
            ]);
            return redirect()->route('warehouses.index')->with('success', 'Warehouse Updated Successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage() . ' - ' . $e->getCode());
        }
    }

    public function bundleEdit(Request $request)
    {
        $products = DB::select(DB::raw("select `products`.`id` as `product_id`,
                                               `products`.`name`  as 'product_name', 
                                               `product_plans`.`id` as 'plan_id', 
                                               `product_plans`.`name` as 'plan_name' 
                                               from `products` inner join `product_plans` on 
                                               `products`.`id` = `product_plans`.`product_id` 
                                               where `products`.`has_activation_code`=1 AND 
                                               `products`.`status` = 1 AND 
                                               `product_plans`.`status` = 1"));

        return view('admin.warehouses.warehousesBundleEdit')->with('warehouses', Warehouse::where('status', 1)->get(['id', 'name']))
            ->with('products', $products);
    }

    public function bundleUpdate(Request $request)
    {
        try {
            $warehouses = $request->warehouses;
            $products = $request->data;
            // dd($products);
            foreach ($warehouses as $warehouse) {
                foreach ($products as $product) {
                    Warehouse_inventory::where([
                        ['warehouse_id',    '=', $warehouse],
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
            return redirect()->route('warehouses.index')->with('success', 'Warehouses Updated Successfully');
        } catch (\Exception $e) {
            return redirect()->route('warehouses.index')->with('error', $e->getMessage());
        }
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  \AlphaDirect\Warehouse  $warehouse
     * @return \Illuminate\Http\Response
     */
    public function destroy(Warehouse $warehouse)
    {
        try {
            $warehouse->warehouse_inventories()->delete();
            $warehouse->delete();
            return response()->json(['status' => 'success',], 200);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage() . ' - ' . $e->getCode());
        }
    }
}
