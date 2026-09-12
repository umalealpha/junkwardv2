<?php

namespace AlphaDirect\Http\Livewire\Policy;

use AlphaDirect\PolicyBeneficiary;
use Livewire\Component;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Models\User;
use Carbon\Carbon;

class AddMember extends Component
{
    public $policy;
    public PolicyBeneficiary $beneficiaryData;
    public $B_isUpdate = false;
    public $isPrevious = false;
    public $termId;
    public $actionId;
    public $editable = true;
    public $allRiskaddress;
    public $riskId;
    public $beneficiary_date_of_birth;

    protected $rules = [
        'beneficiaryData.relation' => 'required',
        'beneficiaryData.first_name' => 'required',
        'beneficiaryData.middle_name' => '',
        'beneficiaryData.last_name' => 'required',
        'beneficiaryData.gender' => '',
        'beneficiaryData.omang' => '',
        'beneficiary_date_of_birth' => 'required',
        'beneficiaryData.passport' =>'',
        'beneficiaryData.payment' => 'required',
        'beneficiaryData.risk_id' => 'required',
    ];

    protected $messages = [
        'beneficiary_date_of_birth' => 'beneficiary date of birth required',
    ];

    protected $listeners = [
        'updateTermId' => '$refresh',
        'refreshParent'  => '$refresh',
        // 'refreshmember' => 'refreshmember',
        'beneficiaryTriggerDelete'
    ];

    // public function refreshmember()
    // {
    //     $this->allRiskaddress = $this->getallRiskAddress();
    //     $this->beneficiaryData->risk_id = null;
    //     $this->mount($this->allRiskaddress);
    //     $this->render();
    // }

    public function mount($allRiskaddress = null){
        // dd($allRiskaddress);
        $this->beneficiaryData = new PolicyBeneficiary();
        // if (is_null($allRiskaddress)){
            $this->allRiskaddress = $this->getallRiskAddress();
        // }else{
            // $this->allRiskaddress= $allRiskaddress;
        // }
    }

    public function render()
    {
        return view('v2.livewire.policy.add-member');
    }

    public function getAllBeneficiariesProperty(){
        return PolicyBeneficiary::Policy($this->policy->id)->term($this->termId)->action($this->actionId)->get();
    }

    public function getallRiskAddress(){
		return RiskAddress::Policy($this->policy->id)->action($this->actionId)->get()->keyBy('id')->map(function($riskaddress){
			return [
				'id'=>$riskaddress->id,
				'name'=>$riskaddress->address_name
			];
		});
	}

    public function addBeneficiary(){
        $this->beneficiaryData->policy_id= $this->policy->id;
        if($this->beneficiaryData->id != "" && $this->beneficiaryData->id != NULL){
            $this->rules['beneficiaryData.omang']='required_if:beneficiaryData.passport,==,""|unique:policy_beneficiary,omang,'.$this->beneficiaryData->id.'|min:5|max:25';
            $this->rules['beneficiaryData.passport']='required_if:beneficiaryData.omang,==,""|unique:policy_beneficiary,passport,'.$this->beneficiaryData->id.'|min:5|max:25';
        }else{
            if($this->beneficiaryData->passport==""){
                $this->rules['beneficiaryData.omang']='required_if:beneficiaryData.omang,==,""|unique:policy_beneficiary,omang,NULL,id|min:5|max:25';
            }
            if($this->beneficiaryData->omang==""){
                $this->rules['beneficiaryData.passport']='required_if:beneficiaryData.passport,==,""|unique:policy_beneficiary,passport,NULL,id|min:5|max:25';
            }
        }

        $this->validate();
        $totPayment = $this->policy->policyBeneficiaries()->Term($this->termId);
        if($this->B_isUpdate){
            $totPayment =$totPayment->where('id','!=',$this->beneficiaryData->id);
        }
        $totPayment =$totPayment->sum('payment');
        $totPayment =$totPayment+intval($this->beneficiaryData->payment);
        if($totPayment>100){
            \Validator::make(
                ['beneficiaryData.payment' => $totPayment],
                ['beneficiaryData.payment' => 'required|digits_between:1,100'],
                ['required' => 'The payment percentage should not be greater than 100'],
            )->validate();
        }
        $this->beneficiaryData->term_id = $this->termId;
        $this->beneficiaryData->action_id = $this->actionId;
        $this->beneficiaryData->dob = Carbon::createFromFormat(config('constants.date.format'),$this->beneficiary_date_of_birth);

        $this->beneficiaryData->save();
        if($this->B_isUpdate){

            activity('Beneficiary')
            ->performedOn($this->policy)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Beneficiary Details Updated');

            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Beneficiary Details Updated Successfully!']);
        }else{

            activity('Beneficiary')
            ->performedOn($this->policy)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Beneficiary Details Added');

            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Beneficiary Details Added Successfully!']);
        }
        $this->beneficiaryData= new PolicyBeneficiary();
        $this->B_isUpdate=false;
        $this->render();
    }

    public function beneficiaryDetailsedit($id){
        $this->beneficiaryData = PolicyBeneficiary::find(\Crypt::decrypt($id));
        $this->beneficiary_date_of_birth = (new Carbon($this->beneficiaryData->dob))->format(config('constants.date.format'));
        $this->B_isUpdate=true;
    }

    public function beneficiaryTriggerDelete($id){
        if (PolicyBeneficiary::find($id)->delete()){
            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Beneficiary Details Deleted Successfully!']);
        }else{
            $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Something Went Wrong']);
        }

        activity('Beneficiary')
        ->performedOn($this->policy)
        ->causedBy(User::where('id', auth()->user()->id)->first())
        ->log('Beneficiary Details Deleted');

        $this->beneficiaryDetailseditCancel();
        $this->render();
    }

    public function beneficiaryDetailseditCancel(){
        $this->beneficiaryData= new PolicyBeneficiary();
        $this->B_isUpdate=false;
    }

    public function saveStep4(){
        // $this->addBeneficiary();
        $this->emitUp('updateHasStatus');
    }

    public function backToStep3(){
        $this->emitUp('backToStep3');
    }
}
