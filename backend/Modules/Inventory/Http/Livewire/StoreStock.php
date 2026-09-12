<?php

namespace Modules\Inventory\Http\Livewire;
use Modules\Inventory\Events\DeductStockStore;
use Modules\Inventory\Entities\StoresInventory;
use Illuminate\Mail\Markdown;
use Livewire\Component;
use Log;
use Pnlinh\InfobipSms\Facades\InfobipSms;

class StoreStock extends Component
{
	public $store_id;
	public bool $showDetails = false;
	protected $rules = [
		'store_id'=>'required'
	];

	public function mount(){
		$this->store_id=request()->route()->id;
		if($this->store_id!=""){
			$this->showDetails = true;
		}
	}
	public function setStores($value){
		$this->store_id=$value;
	}
	public function getStore(){
		return \Modules\Inventory\Entities\Stores::whereStatus(1)->get();
	}

	public function getStoreInventory(){
		return \Modules\Inventory\Entities\StoresInventory::with(['plan','product'])->where('store_id',$this->store_id)->get();
	}

	public function addQuantity($id,$num,$wire_inventory){
		\DB::transaction(function () use($id,$num,$wire_inventory){
			$stock = new \Modules\Inventory\Entities\StoreStock();
		    $stock->store_inteventories_id=$id;
			$stock->stock=$num;
			$stock->user_id=auth()->user()->id;
			$stock->save();
			if($wire_inventory!=""){
				$stock = new \Modules\Inventory\Entities\WirehouseStock();
				$stock->warehouses_inteventories_id=$wire_inventory;
				$stock->stock=$num;
				$stock->stock_add_remove=1;
				$stock->user_id=auth()->user()->id;
				$stock->save();
			}
		}, 5);
        $winventory = \Modules\Inventory\Entities\WireHouseInventory::with(['product','plan'])->where('id',$wire_inventory)->first();
        $warehouse = \Modules\Inventory\Entities\WareHouse::where('id',$winventory->warehouse_id)->first();
        if($winventory->counter < $winventory->min_inventory){
            $warehouse_inventory = \Modules\Inventory\Entities\WireHouseInventory::with(['product','plan'])->where('id',$wire_inventory)->get();
            InfobipSms::send('+267' . $warehouse->mobile, 'Stock is running low please add stock for'.' '.$winventory->product->name .''.'at your warehouse'.''.$warehouse->name);
            $markdown = new Markdown(view(), config('mail.markdown'));
            $html = $markdown->render('Mail.StockAlertForWarehouse',['email'=>$warehouse->email_id,'warehouseInventory'=>$warehouse_inventory,'inventory'=>$winventory,'warehouseContact'=>$warehouse->contact_person]);
            event(new \AlphaDirect\Events\SendMail($warehouse->email_id,"AlphaDirect | Stock Alert For Your Warehouse","Email Content in Text",$html));
        }
		$this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Product Added Successfully.']);
	}

    public function render()
    {
        return view('inventory::livewire.store-stock');
    }
}
