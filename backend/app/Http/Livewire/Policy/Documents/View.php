<?php

namespace AlphaDirect\Http\Livewire\Policy\Documents;

use AlphaDirect\Customer;

use AlphaDirect\Models\GetPolicyDocuments;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Policy;
use AlphaDirect\PolicyBundled;
use AlphaDirect\PolicyCoverCancelNote;
use AlphaDirect\PolicyTerm;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class View extends Component
{
    public Policy $policy;
    public Customer $customer;
    public $policy_term;
    public $emailDocs;
    public $EmailDocsPolicyBundled;
    public $cancelNote;
    public $coverNote;
    public $newPolicySchedule;
    public $actionId;
    public $previousActionId;
    public $termId;
    public $action;
    public $dataShowForActionId;
    public $PolicyDocument;

    public function mount(){
        $this->dataShowForActionId = $this->previousActionId ?? $this->actionId;
        $this->policy_term = PolicyTerm::where('policy_id',$this->policy->id)->orderBy('id','desc')->get();
        $this->PolicyDocument = GetPolicyDocuments::where('policy_id',$this->policy->id)
        ->where('term_id',$this->termId)
        ->where('action_id',$this->actionId)
        ->orderBy('id', 'desc')->first();
        $this->currentAction=PolicyAction::where('id',$this->actionId)->orderBy('id','desc')->first();
        $this->emailDocs = DB::select(DB::raw('SELECT * FROM documents where status = 1 and (product_id = '. $this->policy->product_id .' || product_id = -1)'));
        $this->EmailDocsPolicyBundled = PolicyBundled::where('policy_id',$this->policy->id)->get(array('policyDocument'));

        $this->cancelNote = PolicyCoverCancelNote::where('policy_id', $this->policy->id)
                                                ->where('doc_type', 'Cancel')
                                                ->orderBy('id', 'DESC')
                                                ->first(array('path'));

        $this->coverNote = PolicyCoverCancelNote::where('policy_id', $this->policy->id)
                                                ->where('doc_type', 'Cover')
                                                ->orderBy('id', 'DESC')
                                                ->first(array('path'));
        $this->policyCancellationNoteDoc = GetPolicyDocuments::where('policy_id',$this->policy->id)
            ->where('is_cancellation_note',1)
            ->orderBy('id', 'desc')->first();
    }

    public function render()
    {

        return view('v2.livewire.policy.documents.view')->layout('layouts.app-v2');
    }
}
