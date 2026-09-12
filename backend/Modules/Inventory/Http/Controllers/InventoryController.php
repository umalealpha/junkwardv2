<?php

namespace Modules\Inventory\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InventoryController extends Controller
{

	/**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        try {
            return view('inventory::index');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function warehouseEdit($id){
		abort_if(!auth()->user(), 403);
		request()->merge(['id'=>\Crypt::decrypt($id)]);
		request()->validate([
			'id'=>"required"
		]);
		$warehouse = \Modules\Inventory\Entities\WareHouse::findOrFail(\Crypt::decrypt($id));
		return view('inventory::form-edit',[
			'tablename'=>"warehouse-form",
			'breadcum'=>"header.warehouse",
			'data'=>$warehouse
		]);
	}

	public function storesEdit($id){
		abort_if(!auth()->user(), 403);
		request()->merge(['id'=>\Crypt::decrypt($id)]);
		request()->validate([
			'id'=>"required"
		]);
		$stores = \Modules\Inventory\Entities\Stores::findOrFail(\Crypt::decrypt($id));
		return view('inventory::form-edit',[
			'tablename'=>"stores-form",
			'breadcum'=>"header.stores",
			'data'=>$stores
		]);
	}

    public function storesRemoveStock($id){
		abort_if(!auth()->user(), 403);
		request()->merge(['id'=>\Crypt::decrypt($id)]);
		request()->validate([
			'id'=>"required"
		]);
		$stores = \Modules\Inventory\Entities\Stores::findOrFail(\Crypt::decrypt($id));
		return view('inventory::removestorestock',[
			'tablename'=>"removestore-stock",
			'breadcum'=>"header.stores",
			'data'=>$stores
		]);
	}

}
