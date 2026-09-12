<?php

namespace Modules\Inventory\Http\Livewire;
use \Modules\Inventory\Entities\Stores;
use \Modules\Inventory\Entities\StoresInventory;
use Livewire\Component;
use Illuminate\Database\Eloquent\Builder;
class StoreQuantityForm extends Component
{
	public $Storequantity,$storesInventory;
	protected $rules = [
		'Storequantity.store_id' => '',
		'Storequantity.product_id' => ''
	];
	public function mount($data=""){
		if(!$data){
			$this->storesInventory= new StoresInventory();
		}else{
			$this->storesInventory= $data;
		}
	}
	
    public function render()
    {
        return view('inventory::livewire.store-quantity-form');
    }
	
	public function getStore(){
		return Stores::whereNull('deleted_at')->get();
	}
	
	public function getProduct()
    {
		//dd($this->storesInventory);
		return  Stores::with('inventory')
		//->where('store_id','=',)
		->get();
	}
}
