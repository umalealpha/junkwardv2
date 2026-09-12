<?php

namespace Modules\Incentive\Http\Livewire;

use Livewire\Component;
use \Modules\Incentive\Entities\Incentive;
class IncentiveForm extends Component
{
	public $plan_id,$product_id,$payment_type,$incentive_type,$incentive_value,$incentive,$status,
	$unlimited=0,$subsquent_amount=0;
	protected $rules = [
		'incentive.incentive_type' => 'required|not_in:0',
		'incentive.product_id' => 'required|not_in:0',
		'incentive.plan_id' => 'required|not_in:0',
        'incentive.status' => 'required|not_in:0',
		'incentive.payment_type' => 'required|not_in:0',
		'incentive.incentive_value' => 'required|numeric',
		'incentive.subsquent_amount' => 'required_if:incentive_type,==,7',
		'incentive.unlimited' => '',
	];

    protected $messages = [
        'incentive.product_id.required' => 'Please Select Product',
        'incentive.plan_id.required' => 'Please Select Plan',
        'incentive.status.required' => 'Please Select Status for incentive',
        'incentive.plan_id.unique' => 'Incentive has been already added for this plan',
        'incentive.payment_type.required' => 'Please Select Payment Type',
        'incentive.incentive_type.required' => 'Please Select Incentive Type',
        'incentive.incentive_value.required' => 'Please Select Incentive Value',
		'incentive.subsquent_amount.required_if' => 'Please Select how many months you want to give commisions',
    ];
	public function mount($data=""){
		if(!$data){
			$this->incentive= new Incentive();
		}else{
			$this->incentive= $data;
		}
	}
	public function setIncentive($value)
	{
		$this->product_id=$value;
	}

	public function getProduct()
	{
		return \AlphaDirect\Product::whereStatus(1)->get();
	}

	public function getProductPlan()
	{
		return \AlphaDirect\Productplan::whereStatus(1)->where('product_id','=',$this->incentive->product_id)->get();
	}

	public function saveIncentive()
	{
        $this->validate([
			'incentive.incentive_type' => 'required|not_in:0|unique:incentive,incentive_type,'.$this->incentive->id.',id,product_id,'.
            $this->incentive->product_id.',plan_id,'.$this->incentive->plan_id.',deleted_at,NULL',
            'incentive.product_id' => 'required|not_in:0',
            'incentive.plan_id' => 'required|not_in:0',
            'incentive.status' => 'required',
            'incentive.payment_type' => 'required|not_in:0',
            'incentive.incentive_value' => 'required|numeric',
			'incentive.subsquent_amount' => 'required_if:incentive_type,==,7',
			'incentive.unlimited'=>''
        ]);
		
		\DB::transaction(function () {
			#when incentive_type=Commision on subsquent collection and unlimited is not checked
			if($this->incentive->incentive_type==7 && $this->incentive->unlimited==0){
				$this->incentive->subsquent_amount=($this->incentive->subsquent_amount) ? $this->incentive->subsquent_amount:0;
			}
			#when incentive_type=Commision on subsquent collection and unlimited is checked
			if($this->incentive->incentive_type==7 && $this->incentive->unlimited==1){
				$this->incentive->subsquent_amount=0;
			}
			
			$this->incentive->save();
			$this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Incentive Added Successfully!']);
		}, 5);
		return redirect()->route("incentive.settings");
	}

    public function render()
    {
        return view('incentive::livewire.incentive-form');
    }
}
