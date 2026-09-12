<?php

namespace Modules\Inventory\Http\Livewire;

use Livewire\Component;

class WirehouseStock extends Component
{
	public $warehouse_id;
	public bool $showDetails = false;
	
	protected $rules = [
		'warehouse_id'=>'required'
	];
	public function mount(){
		$this->warehouse_id=request()->route()->id;
		if($this->warehouse_id!=""){
			$this->showDetails = true;
		}
	}
    public function render()
    {
        return view('inventory::livewire.wirehouse-stock');
    }
	
	public function getWarehouse(){
		return \Modules\Inventory\Entities\WareHouse::whereStatus(1)->get();
	}
	
	public function setWireHouse($value){
		$this->warehouse_id=$value;
	}
	
	public function addQuantity($id,$num){
		#add to Table
		$stock = new \Modules\Inventory\Entities\WirehouseStock();
		$stock->warehouses_inteventories_id=$id;
		$stock->stock=$num;
		$stock->user_id=auth()->user()->id;
		$stock->save();
		$this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Product Added Successfully.']);
	}
	
	public function getWarehouseInventory(){
		return \Modules\Inventory\Entities\WireHouseInventory::with(['plan','product'])->where('warehouse_id',$this->warehouse_id)->get();
	}
	
}
