<?php

namespace Modules\Inventory\Http\Livewire;

use Livewire\Component;
use \Modules\Inventory\Entities\Stores;
use \Modules\Inventory\Entities\WareHouse;
use Illuminate\Database\Eloquent\Builder;

class StoresForm extends Component
{
	public $stores,$store_inventory,$partner,$name,$status;
	public $inventory,$inventorymin,$inventorymax;

	protected $validationAttributes = [
		'stores.name' => 'Store name',
        'stores.partner_id' => 'Store partner',
		'stores.state_id' => 'State',
		'stores.city' => 'City',
		'stores.address' => 'Address',
		'stores.appearance_order' => 'Apprearance order',
		'stores.status' => 'Status',
		'partner.name' => 'Partner Name',
		'partner.status' => 'Status',
	];

	public function rules() {
		return [
			'stores.name' => 'required|string|min:6|max:210|unique:stores,name,' . $this->stores->id,
			'stores.partner_id' => 'required',
			'stores.warehouses_id' => 'required',
			'stores.state_id' => 'required|not_in:0',
			'stores.city' => 'required|not_in:0',
			'stores.status' => 'required',
			'stores.contact_person' => 'required',
			'stores.mobile' => 'required|min:8|max:8|regex:/[0-9]{8}/',
			'stores.email_id' => 'email',
			'inventorymin.*' => 'required',
			'inventorymax.*' => 'required',
			'partner.name' => '',
			'partner.status' => '',
		];
	}
	public function mount($data=""){
		if(!$data){
			$this->stores= new Stores();
			$this->partner=new \Modules\Inventory\Entities\Partners();

		}else{
			$this->stores= $data;
			foreach($this->stores->inventory as $plan){
				$this->inventorymax[$plan->plan_id]=$plan->max_inventory;
				$this->inventorymin[$plan->plan_id]=$plan->min_inventory;
			}
		}

	}
	public function save(){
		$this->validate();
		\DB::transaction(function () {
			$this->stores->save();
			foreach($this->getProduct() as $plan){
				$this->stores->inventory()->updateOrCreate([
					'plan_id'=>$plan->id,
					'product_id'=>$plan->product->id,
				],[
					'max_inventory'=>$this->inventorymax[$plan->id] ?? 0,
					'min_inventory'=>$this->inventorymin[$plan->id] ?? 0
				]);
			}
		}, 5);
		$this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Stores Saved Successfully!']);
		return redirect()->route("inventory.stores");
	}
    public function render()
    {
        return view('inventory::livewire.stores-form');
    }

	public function getPartner(){
		return \Modules\Inventory\Entities\Partners::whereStatus(1)->get();
	}

	public function getState()
    {
		return \AlphaDirect\State::where('country_id', config('inventory.countryCode'))
		->get();
    }

	public function getCity()
    {
		return \AlphaDirect\City::where('state_id','=',$this->stores->state_id)->get();
    }


	public function getWarehouse()
    {
		return WareHouse::whereStatus(1)->get();
    }

	public function getProduct()
    {
		return cache()->remember(config('inventory.countryCode').'::Productplan',120, function () {
			return  \AlphaDirect\Productplan::whereHas('product', function (Builder $query) {
				$query->whereStatus(1)
				->where('has_activation_code','=','1');
			})
			->whereStatus(1)
			->get();
		});
	}
	#save partner
	public function savePartner(){
		$this->validate(
		[
			'partner.name' => 'required|unique:store_partners,name',
			'partner.status' => 'required'
		]);
		\DB::transaction(function () {
			$this->partner->save();
			$this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Partner Add Successfully!']);
		}, 5);
		$this->dispatchBrowserEvent('partnerAdded');
	}

}
