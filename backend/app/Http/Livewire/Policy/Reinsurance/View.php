<?php

namespace AlphaDirect\Http\Livewire\Policy\Reinsurance;

use Livewire\Component;
use AlphaDirect\Policy;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Models\Alpharicvggroup;
use Illuminate\Support\Facades\Redirect;
use AlphaDirect\Models\RiskInsurance;

class View extends Component
{

    public Policy $policy;
    public $actionId;
    public $termId;
    public $previousActionId;
    public $dataShowForActionId;

    public function mount(){}

    public function policyReinsuranceCalculations()
    {
    
      
       $this->dataShowForActionId = $this->previousActionId ?? $this->actionId;
       $PolicyReinsuranceCalculations = PolicyCoverage::getReinsuranceCoverageCalculations($this->policy->id,$this->termId,$this->actionId);
        
       // $PolicyReinsuranceCalculations = PolicyCoverage::getReinsuranceCoverageCalculations($this->policy->id,$this->dataShowForActionId);
       $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Policy Reinsurance Calculations are Created Successfully']);
      
       return Redirect::route('policy.edit', [\Crypt::encrypt($this->policy->id)]);
    }

    public function render()
    {
        $this->policyId = $this->policy->id;
        $this->policyNumber = $this->policy->policyNumber;
        $this->riskAddress = RiskAddress::where('policy_id',$this->policy->id)->get();   
        $this->coveragesMaster = Alpharicvggroup::where('s_Status','ACTIVE')->get();         
        $this->Reinsurance = RiskInsurance::where('policy_id',$this->policy->id)->get();

        return view('v2.livewire.policy.reinsurance.view');
    }
    public function getPremiumFreqPropert(){
		return ["1"=>"MONTHLY","2"=>"3 INSTALLMENTS","3"=>"ANNUAL","4"=>"SEMIANNUAL","5"=>"QUARTERLY"];
	}
}
