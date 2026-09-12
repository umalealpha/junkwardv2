<?php

namespace Modules\Inventory\Http\Livewire;

use Livewire\Component;

class StoresStockReduce extends Component
{
	public $warehouses_id,$store_id,$damaged_stock,$store_inteventories_id,
	$transfer_warehouses_id,$transfer_store_id,$product_id,$plan_id;
	public $availabe_stock=0;
	public $reason="";
	
	protected $rules = [
		'warehouses_id'=>'required',
		'store_id'=>'required',
		'reason'=>'required',
		'damaged_stock'=>'required|numeric|not_in:0',
		'availabe_stock'=>'required',
		'transfer_warehouses_id'=>'required_if:reason,==,transfer',
		'transfer_store_id'=>'required_if:reason,==,transfer',
	];
	protected $messages = [
		'damaged_stock.not_in'=>'damaged stock should not be zero',
	];
    public function render()
    {
        return view('inventory::livewire.stores-stock-reduce');
    }
	public function getWarehouse(){
		return \Modules\Inventory\Entities\WareHouse::whereStatus(1)->get();
	}
	public function getOtherWarehouse(){
		return \Modules\Inventory\Entities\WareHouse::whereStatus(1)->where('id','!=',$this->warehouses_id)->get();
	}
	public function getStore(){
		return \Modules\Inventory\Entities\Stores::whereStatus(1)->where('warehouses_id','=',$this->warehouses_id)->get();
	}
	public function getOtherStore(){
		return \Modules\Inventory\Entities\Stores::whereStatus(1)->where('id','!=',$this->store_id)->where('warehouses_id','!=',$this->warehouses_id)->get();
	}
	public function setStores($value){
		$this->store_id=$value;
	}

	public function setWareHouse($value){
		$this->warehouses_id=$value;
	}
	public function stock(){
		
		$r = \Modules\Inventory\Entities\StoresInventory::with(['plan','product'])->where('store_id',$this->store_id)->first();
		
		$this->availabe_stock = ($r) ? $r->counter:0;
		$this->store_inteventories_id = ($r) ? $r->id:0;
		$this->product_id = ($r) ? $r->product_id:"";
		$this->plan_id = ($r) ? $r->plan_id:"";
	}
	
	public function save(){
		$this->validate();
		$stores_log = new \Modules\Inventory\Entities\StoresStockReduceLog();
		$stores_log->warehouses_id=$this->warehouses_id;
		$stores_log->store_id=$this->store_id;
		$stores_log->reason=$this->reason ?? ""; 
		$stores_log->damaged_stock=$this->damaged_stock ?? 0; 
		$stores_log->availabe_stock=$this->availabe_stock;
		$stores_log->transfer_warehouses_id=$this->transfer_warehouses_id ?? "";
		$stores_log->transfer_store_id=$this->transfer_store_id ?? "";
		$stores_log->user_id=auth()->user()->id;
		$stores_log->save();
		#remove Stock from Current WireHose
		$stock = new \Modules\Inventory\Entities\StoreStock();
		$stock->store_inteventories_id=$this->store_inteventories_id;
		$stock->stock=$this->damaged_stock;
		$stock->stock_add_remove=1;
		$stock->user_id=auth()->user()->id;
		$stock->save();
		#update or create on transfer Case
		if($stores_log->reason=="transfer"){
			$rr= \Modules\Inventory\Entities\StoresInventory::where('store_id',$this->transfer_store_id)
			->where('product_id','=',$this->product_id)
			->where('plan_id','=',$this->plan_id)->firstOr(function () {
				return \Modules\Inventory\Entities\StoresInventory::create([
					'store_id' => $this->transfer_store_id ?? "",
					'product_id' => $this->product_id,
					'plan_id' => $this->plan_id
				]);
			});
			$stock = new \Modules\Inventory\Entities\StoreStock();
			$stock->store_inteventories_id=$rr->id;
			$stock->stock=$stores_log->damaged_stock;
			$stock->user_id=auth()->user()->id;
			$stock->save();
		}
		$this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Reduce / Transfer Stores Stock Successfully.']);
		
	}
}
