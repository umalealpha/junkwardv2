<?php

namespace AlphaDirect\Http\Livewire\Policy\FacutatlvePlacement;

use AlphaDirect\Models\FacutatlveParticipants;
use AlphaDirect\Policy;
use Illuminate\Support\Facades\Redirect;
use Livewire\Component;
use AlphaDirect\Models\TbPorifacparties;
use AlphaDirect\Models\TbPorifacmaster;

class Participants extends Component
{
    public Policy $policy;
    public $R_isUpdate = false;
    public $isPrevious = false;
    public $editable=true;
    public TbPorifacparties $participants;
    public $actionId;

    public $rules = [
        'participants.n_PersonInfoId_FK' => 'required',
        'participants.n_SharePercent' => 'required',
        'participants.n_PremiumShare' => 'required',
        'participants.description' => '',
        'participants.commission_rate' => 'required',
        'participants.commission_amount' => 'required',
        'participants.tax_rate' => 'required',
        'participants.tax_amount' => 'required',
        'participants.n_NetPremium' => 'required'
    ];

    public function mount(){
        $this->participants = new TbPorifacparties();
    }

    public function render()
    {
        return view('v2.livewire.policy.facutatlve-placement.participants');
    }

    public function getallParticipants(){
        return TbPorifacparties::get();
    }
    

    public function addParticipants(){
        $valid=[];
        $valid['participants.n_PersonInfoId_FK']='required';
        $valid['participants.n_SharePercent']='required';
        $valid['participants.n_PremiumShare']='required';
        $valid['participants.description']='';
        $valid['participants.commission_rate']='required';
        $valid['participants.commission_amount']='required';
        $valid['participants.tax_rate']='required';
        $valid['participants.tax_amount']='required';
        $valid['participants.n_NetPremium']='required';
        $this->validate($valid);
        $TbPorifacmasterExist = TbPorifacmaster::where('policy_id',$this->policy->id)->where('action_id',$this->actionId)->first();
        if(!empty($TbPorifacmasterExist)){
            $this->participants->porifacmasters_id = $TbPorifacmasterExist->id;
            $this->participants->n_PersonInfoId_FK = $this->participants->n_PersonInfoId_FK;
            $this->participants->n_SharePercent = $this->participants->n_SharePercent;
            $this->participants->n_PremiumShare = $this->participants->n_PremiumShare;
            $this->participants->commission_rate = $this->participants->commission_rate;
            $this->participants->commission_amount = $this->participants->commission_amount;
            $this->participants->tax_rate = $this->participants->tax_rate;
            $this->participants->tax_amount = $this->participants->tax_amount;
            $this->participants->description = $this->participants->description;
            $this->participants->n_NetPremium = $this->participants->n_NetPremium;
            $this->participants->save();
            if($this->R_isUpdate){
                $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Participants Details Updated Successfully!']);
            }else{
                $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Participants Details Added Successfully!']);
            }
            $this->participants = new TbPorifacparties();
            $this->R_isUpdate = false;
            $this->render();
        }else{
            $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'First fill out the Placement Summary Form!']);
        }
    }

    public function participantsDetailsedit($id){
        $this->participants= TbPorifacparties::find(\Crypt::decrypt($id));
        $this->R_isUpdate=true;
    }

    public function triggerparticipantsDelete($id){
        TbPorifacparties::find(\Crypt::decrypt($id))->delete();
        $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'participants Details Deleted Successfully!']);
        $this->participantsDetailsEditCancel();
        $this->render();
    }

    public function participantsDetailsEditCancel(){
        $this->participants = new TbPorifacparties();
        $this->R_isUpdate = false;
    }

    public function calculateSharePercent()
    {
        $premium = 100;
        if(!empty($this->participants->n_SharePercent)){
            $PremiumShareAmount = ($this->participants->n_SharePercent/100) * $premium;
            $this->participants->n_PremiumShare = $PremiumShareAmount;
        }else{
            $this->participants->n_PremiumShare = 0;
        }

        if(!empty($this->participants->commission_rate)){
            $commissionAmount = ($this->participants->commission_rate/100) * $this->participants->n_PremiumShare;
            $this->participants->commission_amount = $commissionAmount;
        }else{
            $this->participants->commission_amount = 0;
        }

        if(!empty($this->participants->tax_rate)){
            $taxAmount = ($this->participants->tax_rate/100) * $this->participants->n_PremiumShare;
            $this->participants->tax_amount = $taxAmount;
        }else{
            $this->participants->tax_amount = 0;
        }

        if(($this->participants->n_PremiumShare??"") && ($this->participants->commission_amount??"") && ($this->participants->tax_amount??"")){
            $netPremium = ($this->participants->n_PremiumShare)+($this->participants->commission_amount)+($this->participants->tax_amount);
            $this->participants->n_NetPremium = $netPremium;
        }else{
            $this->participants->n_NetPremium = 0;
        }
    }

    public function calculateCommissionRate()
    {
        if(!empty($this->participants->commission_rate)){
            $commissionAmount = ($this->participants->commission_rate/100) * $this->participants->n_PremiumShare;
            $this->participants->commission_amount = $commissionAmount;
        }else{
            $this->participants->commission_amount = 0;
        }

        if(!empty($this->participants->tax_rate)){
            $taxAmount = ($this->participants->tax_rate/100) * $this->participants->n_PremiumShare;
            $this->participants->tax_amount = $taxAmount;
        }else{
            $this->participants->tax_amount = 0;
        }

        if(($this->participants->n_PremiumShare??"") && ($this->participants->commission_amount??"") && ($this->participants->tax_amount??"")){
            $netPremium = ($this->participants->n_PremiumShare)+($this->participants->commission_amount)+($this->participants->tax_amount);
            $this->participants->n_NetPremium = $netPremium;
        }else{
            $this->participants->n_NetPremium = 0;
        }
    }

    public function calculateTaxRate()
    {
        if(!empty($this->participants->tax_rate)){
            $taxAmount = ($this->participants->tax_rate/100) * $this->participants->n_PremiumShare;
            $this->participants->tax_amount = $taxAmount;
        }else{
            $this->participants->tax_amount = 0;
        }

        if(!empty($this->participants->commission_rate)){
            $commissionAmount = ($this->participants->commission_rate/100) * $this->participants->n_PremiumShare;
            $this->participants->commission_amount = $commissionAmount;
        }else{
            $this->participants->commission_amount = 0;
        }

        if(($this->participants->n_PremiumShare??"") && ($this->participants->commission_amount??"") && ($this->participants->tax_amount??"")){
            $netPremium = ($this->participants->n_PremiumShare)+($this->participants->commission_amount)+($this->participants->tax_amount);
            $this->participants->n_NetPremium = $netPremium;
        }else{
            $this->participants->n_NetPremium = 0;
        }
    }
    
    
}
