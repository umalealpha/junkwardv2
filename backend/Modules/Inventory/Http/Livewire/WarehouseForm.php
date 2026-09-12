<?php

namespace Modules\Inventory\Http\Livewire;

use Livewire\Component;
use \Modules\Inventory\Entities\WareHouse;
use \Modules\Inventory\Entities\WireHouseInventory;
use Illuminate\Database\Eloquent\Builder;

class WarehouseForm extends Component
{

	public $warehouse;
	public $mininventory;
	public $maxinventory;
	public $counter;
	protected $rules = [
		'warehouse.name' => 'required|string|min:6|max:210',
		'warehouse.status' => 'required',
		'warehouse.contact_person' => 'required',
		'warehouse.email_id' => 'required|email',
		'warehouse.mobile' => 'required|min:8|max:8|regex:/[0-9]{8}/',
		'warehouse.address' => 'required',
		'mininventory.*' => '',
		'maxinventory.*' => '',
	];

    protected $messages = [
        'warehouse.name.required' => 'Please Enter Warehouse Name',
		'warehouse.status.required' => 'Please Select Warehouse Status',
		'warehouse.contact_person.required' => 'Please Enter Contact Person',
		'warehouse.email_id.required' => 'Please Enter Email address',
		'warehouse.mobile.required' => 'Please Enter Mobile',
        'warehouse.mobile.min' => 'Mobile number should be atleast 8 digits',
		'warehouse.address.required' => 'Please Enter Address',
    ];

	protected $validationAttributes = [
		'warehouse.name' => 'Ware House Name',
	];


	public function mount($data=""){
		if(!$data){
			$this->warehouse= new WareHouse();
			foreach($this->getProduct() as $plan){
				$this->maxinventory[$plan->id] = '';
				$this->mininventory[$plan->id] ='';
			}
		}else{
			$this->warehouse= $data;
			foreach($this->warehouse->inventory as $plan){
				$this->maxinventory[$plan->plan_id]=$plan->max_inventory;
				$this->mininventory[$plan->plan_id]=$plan->min_inventory;
				$this->counter[$plan->plan_id]=$plan->counter;
			}
		}
	}

    public function render()
    {
        return view('inventory::livewire.warehouse-form');
    }


	public function save(){
		$this->validate();
		\DB::transaction(function () {
			$this->warehouse->save();
			foreach($this->getProduct() as $plan){
				$this->warehouse->inventory()->updateOrCreate([
					'plan_id'=>$plan->id,
					'product_id'=>$plan->product->id,
				],[
					'max_inventory'=>$this->maxinventory[$plan->id] ?? 0,
					'min_inventory'=>$this->mininventory[$plan->id] ?? 0
				]);
			}
		}, 5);
		$this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Ware House Saved Successfully!']);
		return redirect()->route("inventory.warehouse");
	}

	public function getProduct()
    {
		return cache()->remember(config('inventory.countryCode').'::Productplan',120, function () {
			return  \AlphaDirect\Productplan::whereHas('product', function (Builder $query) {
				$query->whereStatus(1);
			})
			->whereStatus(1)
			->get();
		});
	}
}
