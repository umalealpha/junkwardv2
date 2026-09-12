<?php

namespace Modules\Cashback\Http\Livewire;

use Livewire\Component;
use \Modules\Cashback\Entities\Cashback;
class CustomercashbackForm extends Component
{

    public $plan_id,$product_id,$payment_type,$cashback_type,$cashback_value,$cashback,$unlimited=0,$subsquent_amount=0;
	protected $rules = [
		'cashback.product_id' => 'required|not_in:0',
		'cashback.plan_id' => 'required|not_in:0',
		'cashback.payment_type' => 'required|not_in:0',
		'cashback.cashback_type' => 'required|not_in:0',
		'cashback.cashback_value' => 'required|numeric',
        'cashback.subsquent_amount' => 'required_if:cashback_type,==,7',
		'cashback.unlimited' => '',
	];

    protected $messages = [
        'cashback.product_id.required' => 'Please Select Product',
        'cashback.plan_id.required' => 'Please Select Plan',
        'cashback.payment_type.required' => 'Please Select Payment Type',
        'cashback.cashback_type.required' => 'Please Select cashback Type',
        'cashback.cashback_value.required' => 'Please Select cashback Value',
        'cashback.subsquent_amount.required_if' => 'Please Select how many months you want to give commisions',
    ];
	public function mount($data=""){
		if(!$data){
			$this->cashback= new Cashback();
		}else{
			$this->cashback= $data;
		}
	}
	public function setProduct($value)
	{
		$this->product_id=$value;
	}

	public function getProduct()
	{
		return \AlphaDirect\Product::whereStatus(1)->get();
	}

	public function getProductPlan()
	{
		return \AlphaDirect\Productplan::whereStatus(1)->where('product_id','=',$this->cashback->product_id)->get();
	}

	public function saveCashback()
	{
        $this->validate([
            'cashback.product_id' => 'required|not_in:0',
            'cashback.plan_id' => 'required|not_in:0',
            'cashback.payment_type' => 'required|not_in:0',
            'cashback.cashback_type' => 'required|not_in:0|unique:cashback,cashback_type,'.$this->cashback->id.',id,product_id,'.
            $this->cashback->product_id.',plan_id,'.$this->cashback->plan_id.',deleted_at,NULL',
            'cashback.cashback_value' => 'required|numeric',
            'cashback.subsquent_amount' => 'required_if:cashback_type,==,7',
			'cashback.unlimited'=>''
        ]);
		\DB::transaction(function () {
             #when cashback_type=Commision on subsquent collection and unlimited is not checked
			if($this->cashback->cashback_type==7 && $this->cashback->unlimited==0){
				$this->cashback->subsquent_amount=($this->cashback->subsquent_amount) ? $this->cashback->subsquent_amount:0;
			}
			#when cashback_type=Commision on subsquent collection and unlimited is checked
			if($this->cashback->cashback_type==7 && $this->cashback->unlimited==1){
				$this->cashback->subsquent_amount=0;
			}
			$this->cashback->save();
			$this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Cashback Added Successfully!']);
		}, 5);
		return redirect()->route("cashback.settings");
	}

    public function render()
    {
        return view('cashback::livewire.customercashback-form');
    }
}
