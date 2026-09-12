<?php

namespace Modules\Inventory\Http\Livewire;

use Livewire\Component;

class WirehouseStockReduce extends Component
{
	public $warehouselog,$warehouses_id,
	$product_id,$plan_id,$wid,$damaged_stock,$transfer_warehouses_id;
	public $availabe_stock=0;
	public $warehouses_inteventories_id; #inventory To deduct stock
	public $reason="";
	
	protected $rules = [
		'warehouses_id'=>'required',
		'product_id'=>'required',
		'plan_id'=>'required',
		'reason'=>'required',
		'damaged_stock'=>'required|numeric|not_in:0',
		'availabe_stock'=>'required',
		'transfer_warehouses_id'=>'required_if:reason,==,transfer',
	];
	protected $messages = [
		'damaged_stock.not_in'=>'damaged stock should not be zero',
	];
    public function render()
    {
        return view('inventory::livewire.wirehouse-stock-reduce');
    }
	
	public function save(){
		$this->validate();
		$warehouse_log = new \Modules\Inventory\Entities\WirehouseStockReduceLog();
		$warehouse_log->warehouses_id=$this->warehouses_id;
		$warehouse_log->product_id=$this->product_id;
		$warehouse_log->plan_id=$this->plan_id;
		$warehouse_log->reason=$this->reason ?? ""; 
		$warehouse_log->damaged_stock=$this->damaged_stock ?? 0; 
		$warehouse_log->availabe_stock=$this->availabe_stock;
		$warehouse_log->transfer_warehouses_id=$this->transfer_warehouses_id ?? "";
		$warehouse_log->user_id=auth()->user()->id;
		$warehouse_log->save();
		#remove Stock from Current WireHose
		$stock = new \Modules\Inventory\Entities\WirehouseStock();
		$stock->warehouses_inteventories_id=$this->warehouses_inteventories_id;
		$stock->stock=$this->damaged_stock;
		$stock->stock_add_remove=1;
		$stock->user_id=auth()->user()->id;
		$stock->save();
		#update or create on transfer Case
		if($warehouse_log->reason=="transfer"){
			$rr= \Modules\Inventory\Entities\WireHouseInventory::where('warehouse_id',$this->transfer_warehouses_id)
			->where('product_id','=',$this->product_id)
			->where('plan_id','=',$this->plan_id)->firstOr(function () {
				return \Modules\Inventory\Entities\WireHouseInventory::create([
					'warehouse_id' => $this->transfer_warehouses_id,
					'product_id' => $this->product_id,
					'plan_id' => $this->plan_id
				]);
			});
			$stock = new \Modules\Inventory\Entities\WirehouseStock();
			$stock->warehouses_inteventories_id=$rr->id;
			$stock->stock=$warehouse_log->damaged_stock;
			$stock->user_id=auth()->user()->id;
			$stock->save();
		}
		
		$this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Reduce / Transfer WareHouse Stock Successfully.']);
	}
	
	public function getWarehouse(){
		return \Modules\Inventory\Entities\WareHouse::whereStatus(1)->get();
	}
	
	public function getOtherWarehouse(){
		return \Modules\Inventory\Entities\WareHouse::whereStatus(1)->where('id','!=',$this->warehouses_id)->get();
	}
	
	public function setWareHouse($value){
		$this->warehouses_id=$value;
	}
	
	public function getproduct(){
		return \Modules\Inventory\Entities\WireHouseInventory::with(['product'])
		->where('warehouse_id',$this->warehouses_id)->groupBy('product_id')->get();
	}
	
	public function getProductPlan()
	{
		return \Modules\Inventory\Entities\WireHouseInventory::with(['plan'])->where('warehouse_id',$this->warehouses_id)->where('product_id','=',$this->product_id)->groupBy('plan_id')->get();
	}
	
	
	public function stock(){
		$r = \Modules\Inventory\Entities\WireHouseInventory::where('warehouse_id',$this->warehouses_id)->where('product_id','=',$this->product_id)->where('plan_id','=',$this->plan_id)->first();
		$this->availabe_stock = ($r) ? $r->counter:0;
		$this->warehouses_inteventories_id = ($r) ? $r->id:0;
	}
}
