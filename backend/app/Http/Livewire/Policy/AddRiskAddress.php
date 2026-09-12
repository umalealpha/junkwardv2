<?php

namespace AlphaDirect\Http\Livewire\Policy;

use AlphaDirect\Models\Company;
use AlphaDirect\Policy;
use AlphaDirect\Models\RiskAddress as AlphaDirectRiskAddress;
use AlphaDirect\Models\User;
use Livewire\Component;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Models\PolicyAction;

class AddRiskAddress extends Component
{
    public Policy $policy;
    public $riskinfo;
    public $R_isUpdate = false;
    public $isPrevious = false;
    public $customer;
    public $termId;
    public $inSide;
    public $actionId;
    public $editable=true;
    public $risk_cites = [];


    protected $rules = [
        'riskinfo.address_name' => '', #step2
        'riskinfo.lat' => '', #step2
        'riskinfo.lng' => '', #step2
        'riskinfo.physical_address' => '', #step2
        'riskinfo.risk_state' => '', #step2
        'riskinfo.risk_city' => '', #step2
        'riskinfo.extension' => '', #step2
        'riskinfo.occupation' => '', #step2
        'riskinfo.town_class' => '', #step2
        'riskinfo.risk_class' => '', #step2
        'riskinfo.iso_rcv' => '', #step2
        'riskinfo.year_built' => '', #step2
        'riskinfo.area' => '', #step2
        'riskinfo.structure_type' => '', #step2
        'riskinfo.const_type' => '', #step2
        'riskinfo.distance_to_water' => '', #step2
        'riskinfo.distance_to_fire' => '', #step2
        'riskinfo.distance_to_hydrant' => '', #step2
        'riskinfo.usage' => '', #step2
        'riskinfo.occupancy_type' => '', #step2
        'riskinfo.central_fire' => '', #step2
        'riskinfo.central_burglar' => '', #step2
        'riskinfo.gated_community' => '', #step2
        'riskinfo.automatic' => '', #step2
        'riskinfo.company_id' => '', #step2
    ];
    protected $listeners = [
        'getallRiskAddress'  => '$refresh',
        'setLatitude' => 'setLatitude',
        'triggerRiskAddressDelete'
    ];

    public function setLatitude($lat,$lng,$physical_address)
    {
        $this->riskinfo->lat = $lat;
        $this->riskinfo->lng = $lng;
        $this->riskinfo->physical_address = $physical_address;
    }
    public function mount(){
        $this->customer = $this->policy->customer;
        $this->riskinfo = new AlphaDirectRiskAddress();
    }
    public function render()
    {
        return view('v2.livewire.policy.add-risk-address');
    }

    public function getallRiskAddress(){
        return AlphaDirectRiskAddress::policy($this->policy->id)->term($this->termId)->action($this->actionId)->get();
    }

    public function getSubCompanies(){
        if(!isset($this->policy->profile->company_id)){
            return [];
        }
        return Company::with('subCompanies')->find($this->policy->profile->company_id)?->subCompanies()?->activated()->get()->keyBy('id')->map(function($subcompany){
            return [
                'id'=>$subcompany->id,
                'name'=>$subcompany->name
            ];
        });
	}

    public function addRiskAddress(){
        $valid=[];
        $valid['riskinfo.address_name']='required';
        $valid['riskinfo.lat']='required';
        $valid['riskinfo.lng']='required';
        $valid['riskinfo.physical_address']='required';
        $valid['riskinfo.risk_state']='required';
        $valid['riskinfo.risk_city']='required';
        // $valid['riskinfo.extension']='required';
        // $valid['riskinfo.occupation']='required';
        $valid['riskinfo.town_class']='required';
        $valid['riskinfo.risk_class']='required';
        $valid['riskinfo.iso_rcv']='required';
        $valid['riskinfo.year_built']='required';
        $valid['riskinfo.area']='required';
        $valid['riskinfo.structure_type']='required';
        $valid['riskinfo.const_type']='required';
        // $valid['riskinfo.distance_to_water']='required';
        // $valid['riskinfo.distance_to_fire']='required';
        if($this->policy->profile->entity_type=='Organisation'){
            $valid['riskinfo.company_id']='required';
        }

        $this->validate($valid);
        $this->riskinfo->policy_id = $this->policy->id; //19602
        $this->riskinfo->customer_id = $this->customer->id;
        $this->riskinfo->central_fire = ($this->riskinfo->central_fire==true)?1:0;
        $this->riskinfo->central_burglar = ($this->riskinfo->central_burglar==true)?1:0;
        $this->riskinfo->gated_community = ($this->riskinfo->gated_community==true)?1:0;
        $this->riskinfo->term_id = $this->termId;
        $this->riskinfo->action_id = $this->actionId;
        $this->riskinfo->save();

        if($this->R_isUpdate){

            activity('Risk Address')
            ->performedOn($this->policy)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Risk Address Updated');

            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Risk Address Details Updated Successfully!']);
        }else{

            activity('Risk Address')
            ->performedOn($this->policy)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Risk Address Added');

            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Risk Address Details Added Successfully!']);
        }
        // $this->emitTo('policy.add-member','refreshmember');
        $this->riskinfo = new AlphaDirectRiskAddress();
        $this->R_isUpdate = false;
        $this->render();
    }

    public function riskAddressDetailsedit($id){
        $this->riskinfo= AlphaDirectRiskAddress::find(\Crypt::decrypt($id));
        $this->riskinfo->central_fire = ($this->riskinfo->central_fire==1)?true:false;
        $this->riskinfo->central_burglar = ($this->riskinfo->central_burglar==1)?true:false;
        $this->riskinfo->gated_community = ($this->riskinfo->gated_community==1)?true:false;
        $this->R_isUpdate=true;
        $this->updatedRiskinfo($this->riskinfo->risk_state,'risk_state');
        
        // Dispatch event to scroll to form and show success message
        $this->dispatchBrowserEvent('scroll-to-edit-form', [
            'message' => 'Risk Address loaded for editing. All fields are now populated with the existing data.'
        ]);
        $this->dispatchBrowserEvent('alert', ['type' => 'info', 'message' => 'Risk Address loaded for editing']);
    }

    public function triggerRiskAddressDelete($id){
        if (AlphaDirectRiskAddress::find($id)->delete()){
            PolicyCoverage::where('risk_address_id',$id)->delete();
            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Risk Address Details Deleted Successfully!']);
        }else{
            $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Something Went Wrong']);
        }

        activity('Risk Address')
        ->performedOn($this->policy)
        ->causedBy(User::where('id', auth()->user()->id)->first())
        ->log('Risk Address Deleted');

        $this->riskAddressDetailsEditCancel();
        $this->render();
    }

    public function riskAddressDetailsEditCancel(){
        $this->riskinfo = new AlphaDirectRiskAddress();
        $this->R_isUpdate = false;
    }

    public function getStates(){
        return cache()->driver('file')
            ->remember('State::28',now()->addMinutes(20), function (){
                return \AlphaDirect\State::where('country_id',28)->get()->keyBy('id')->map(function($d){
                    return [
                        'id'=>$d->id,
                        'name'=>$d->name
                    ];
                });
            });
    }

    // Occupation
    public function getLookupOccupation(){
		return \AlphaDirect\Lookup::get()->where('key','risk_occupation')->keyBy('value')->map(function($d){
			return [
				'id'=>$d->value,
				'name'=>$d->value
			];
		});
	}

    public function getLookupExtension(){
		return \AlphaDirect\Lookup::get()->where('key','extensions')->keyBy('value')->map(function($d){
			return [
				'id'=>$d->value,
				'name'=>$d->value
			];
		});
	}

    // risk_structure_type
    public function getLookupStructureType(){
		return \AlphaDirect\Lookup::get()->where('key','risk_structure_type')->keyBy('value')->map(function($d){
			return [
				'id'=>$d->value,
				'name'=>$d->value
			];
		});
	}

    // risk_construction_type
    public function getLookupConstructionType(){
		return \AlphaDirect\Lookup::get()->where('key','risk_construction_type')->keyBy('value')->map(function($d){
			return [
				'id'=>$d->value,
				'name'=>$d->value
			];
		});
	}

    // risk_occupancy_type
    public function getLookupOccupancyType(){
		return \AlphaDirect\Lookup::get()->where('key','risk_occupancy_type')->keyBy('value')->map(function($d){
			return [
				'id'=>$d->value,
				'name'=>$d->value
			];
		});
	}

    public function updatedRiskinfo($value, $key)
    {
        if ($key=="risk_state") {
            $data= \AlphaDirect\City::where('state_id', $value)
                ->get()->keyBy('id')->map(function($d){
                    return [
                        'id'=>$d->id,
                        'name'=>$d->name
                    ];
                });
            $this->risk_cites = $data;
            $this->dispatchBrowserEvent('dropdown-changed',[
                'key'=>'risk_state',
                'data'=>$data
            ]);
        }
    }

    public function backToStep1(){
        $this->emitUp('backToStep1');
    }
    public function saveStep2(){
        $this->addRiskAddress();
        $this->emitUp('updateHasStatus');
    }

    // public function backToStep2(){
    //     $this->emitUp('backToStep2');
    // }

    /**
     * Get the current policy action transaction type for this component's actionId.
     */
    public function getActionTransactionTypeProperty()
    {
        if (!$this->actionId) {
            return null;
        }

        return PolicyAction::find($this->actionId)?->transaction_type;
    }
}
