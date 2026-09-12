<?php

namespace AlphaDirect\Http\Livewire\Policy;

use Livewire\Component;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Models\TbPrTranTypes;
use AlphaDirect\Models\TbPrTranSubTypes;
use AlphaDirect\Policy;
use AlphaDirect\PolicyTerm;
use Carbon\Carbon;
use AlphaDirect\Vehicle;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Models\User;

class EditTransaction extends Component
{
    public PolicyAction $policyAction;
    public PolicyTerm $policy_term;
    public bool $showModal = false;
    public $policy;
    public $termId;
    public $actionId;
    public $subTransactionTypes;
    public $effective_from;
    public $effective_to;
    public $transaction_date;
    public $lastPolicyActionId;
    public $dataShowForActionId;
    public $previousActionId;
    public $transaction_reason;

    public array $rules = [
        'policyAction.transaction_type' => 'required',
       // 'policyAction.transaction_reason' => 'required',
        'effective_from' => 'required',
        'policy_term.term_end_date' => 'date',
        'effective_to' => 'required|date_format:d/m/Y|date_equals:policy_term.term_end_date',
        'transaction_date' => 'required',
        'policyAction.note' => '',
    ];

    public function mount(){
        $this->dataShowForActionId = $this->previousActionId ?? $this->actionId;
        $this->policyAction = new PolicyAction();
        $this->policyAction = PolicyAction::where('id',$this->actionId)->first();
        $this->note=$this->policyAction->note;
        $this->transaction_type=$this->policyAction->transaction_type;
        $this->transaction_reason='';
        if(isset($this->transaction_type)){
        $reason=TbPrTranSubTypes::where('TranSubTypeCode',$this->policyAction->transaction_reason)->first();
            $this->transaction_reason = $reason->TranSubTypeCode ?? '';
        }
        $this->effective_from = Carbon::create($this->policyAction->effective_from)->format(config('constants.date.format'));
        $this->effective_to = Carbon::create($this->policyAction->effective_to)->format(config('constants.date.format'));
        $this->transaction_date = now()->format(config('constants.date.format'));
        
    }
    public function render()
    {
        return view('v2.livewire.policy.edit-transaction');
    }

    public function submit(){
        \DB::transaction(function () {
            $this->showModal = true;
            $this->validate();
            
            $this->policyAction->effective_from    = Carbon::createFromFormat(config('constants.date.format'),$this->effective_from);
            $this->policyAction->effective_to      = Carbon::createFromFormat(config('constants.date.format'),$this->effective_to);
            $this->policyAction->transaction_date  = Carbon::createFromFormat(config('constants.date.format'),$this->transaction_date);
            $this->policyAction->transaction_reason  = $this->transaction_reason;
            $this->policyAction->save();

            activity('Alter term/Date of Policy')
            ->performedOn($this->policy)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Edit term/Date of Policy current Transaction ID: '.$this->policyAction->id);

            $this->emitUp('refreshParent',$this->policyAction->id);
            $this->policyAction = new PolicyAction();
            $this->dispatchBrowserEvent('closeTransactionModal');
            $this->dispatchBrowserEvent('refresh-page');
        //}
        });
    }
    public function getTransactionTypes(){
        $this->newActionDates=PolicyAction::where('id',$this->actionId)->first();
        // if( $this->newActionDates->transaction_type=='CANCEL'){
        //     $idss = [5,6];
		return TbPrTranTypes::get()->keyBy('TranTypeCode')->map(function($d){
			return [
				'id'=>$d->TranTypeCode,
				'name'=>$d->TranTypeScreenName
			];
          
		});
        // }else{
        //     return TbPrTranTypes::get()->keyBy('TranTypeCode')->map(function($d){
        //         return [
        //             'id'=>$d->TranTypeCode,
        //             'name'=>$d->TranTypeScreenName
        //         ];
              
        //     });

        // }
	}

    public function getselectSubTransactionTYpe(){
        $this->newActionDates=PolicyAction::where('id',$this->actionId)->first();
        if($this->newActionDates->transaction_type!=$this->policyAction->transaction_type){
            return TbPrTranSubTypes::where('TranTypeCode',$this->policyAction->transaction_type)->get()
            ->keyBy('TranSubTypeCode')->map(function($trantypedata){
                return [
                    'id'=>$trantypedata->TranSubTypeCode,
                    'name'=>$trantypedata->TranSubtypeScreenName
                ];
            });
        }else{
            return TbPrTranSubTypes::where('TranTypeCode',$this->newActionDates->transaction_type)->get()
            ->keyBy('TranSubTypeCode')->map(function($trantypedata){
                return [
                    'id'=>$trantypedata->TranSubTypeCode,
                    'name'=>$trantypedata->TranSubtypeScreenName
                ];
            });
        }
      
    }

    public function updatedPolicyActionTransactionType($value,$key)
    {
        if ($key=="transaction_type") {
            $trantypedata = TbPrTranSubTypes::where('id',$value)->get()->keyBy('TranSubTypeCode')->map(function($trantypedata){
                return [
                    'id'=>$trantypedata->TranSubTypeCode,
                    'name'=>$trantypedata->TranSubtypeScreenName
                ];
            });
            $this->subTransactionTypes = $trantypedata;
            $this->dispatchBrowserEvent('dropdown-changed',[
                'key'=>'transaction_reason',
                'data'=>$trantypedata
            ]);
        }
    }


    public function getTransactionRequestBysProperty(){
        return [
            'INSURED' => 'Insured','SYSTEM' => 'System','UNDERWRITER' => 'Underwriter'
        ];
    }
}
