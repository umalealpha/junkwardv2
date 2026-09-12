<?php

namespace AlphaDirect\Http\Livewire\Policy\EarnedPremium;

use AlphaDirect\Claim;
use AlphaDirect\EarnPremium;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Policy;
use Livewire\Component;
use AlphaDirect\Models\TbEarnedpremiumDaypremiummaster;
use AlphaDirect\Models\PolicyCoverage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redirect;

use Illuminate\Support\Facades\Log;
use DB;
use AlphaDirect\Customer;

class View extends Component
{
    public Policy $policy;
    public $earned_Premium;
    public $policy_details;
    public $actionId;
    public $action;
    public $claim_count;
    public $termId;

    public function mount(){
        $this->action = PolicyAction::find($this->actionId);
        $this->earned_Premium = EarnPremium::where('policy_id',$this->policy->id)->first();
        $this->policy_details = Policy::where('id',$this->policy->id)->first();
        $this->claim_count = Claim::where('policy_id',$this->policy->id)->count();
    }

    public function policyEarnedPremiumCalculations()
    {
       // dd($this->policy->policyNumber,$this->policy->id,$this->actionId,$this->termId);...............
       $this->action = PolicyAction::find($this->actionId);
       $policyEarnedPremiumCalculations = PolicyCoverage::CalculateEarnedPremium($this->policy->policyNumber,$this->policy->id,$this->actionId,$this->action->term_id,$this->action->transaction_type);
       
       DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Policy Earned Premium Calculations')
                ->performedOn($customer)
                ->causedBy(EarnPremium::where('policy_id', $this->policy->id)->first())
                ->tap(function ($activity){
                    $activity->subject_id = $this->policy->id; 
                })  
                ->log('Policy Earned Premium Calculations Created');

       $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Policy Earned Premium Calculations are Created Successfully']);
       return Redirect::route('policy.edit', [\Crypt::encrypt($this->policy->id)]);
    }

    public function render()
    {
        return view('v2.livewire.policy.earned-premium.view')->layout('layouts.app-v2');
    }
}
