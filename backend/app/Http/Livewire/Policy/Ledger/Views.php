<?php

namespace AlphaDirect\Http\Livewire\Policy\Ledger;

use AlphaDirect\Ledger;
use AlphaDirect\Models\LedgerArchive;
use AlphaDirect\Policy;
use Livewire\Component;
use Carbon\Carbon;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\PolicyTerm;
use AlphaDirect\Region;
use AlphaDirect\Models\ProductCoverage;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Models\PolicyCoveragesData;
use AlphaDirect\Models\PolicyExcessesData;
use AlphaDirect\Models\PolicyBusiExcessesData;
use DB;
use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Models\Extention;
use AlphaDirect\Models\PolicyExtentionDetails;
use AlphaDirect\Models\TbCvgpcLimits;
use AlphaDirect\Models\TbValidOptions;
use AlphaDirect\Models\PolicyCoverageDetail;
class Views extends Component
{
    public Policy $policy;
    public $totalDue;
    public $legaltab=1;
    public $actionId;
    public $termId;
    public $previousActionId;
    public $dataShowForActionId;

    public $policyNumber;
    public $premium_freq;
    public $array;

    public $ledger;
    public function mount(){
        $policyId = $this->policy->id;

        $policy = Policy::find($this->policy->id);
  $ledger = Ledger::leftJoin('policy_actions as pa', 'pa.id', '=', 'policy_ledger.action_id')
    ->where('policy_ledger.policy_id', $policyId)
    ->whereIn('policy_ledger.trans_type', ['Invoice', 'Credit Note'])
    ->whereNull('policy_ledger.deleted_at')
    ->where(function ($q) {
        // include rows with NO action OR include rows where action exists and is NOT soft-deleted
        $q->whereNull('pa.id')
          ->orWhereNull('pa.deleted_at');
    })
    ->select(
        'policy_ledger.*',
        'pa.transaction_type',
        'pa.status',
        'pa.effective_from',
        'pa.effective_to',
        'pa.deleted_at as pa_deleted_at' // helpful for debugging
    )
    ->orderBy('policy_ledger.invoice_no', 'asc')
    ->get();

        $this->ledger = $ledger;
        $this->premium_freq = $policy->premium_freq;
        $this->policyNumber = $this->policy->policyNumber;
        $this->policyId = $this->policy->id;
        $this->render();
    }

    public function render()
    {
        return view('v2.livewire.policy.ledger.views')->layout('layouts.app-v2');
    }

    private function getExtensionsByType($coverageId, $extensionType)
    {
        $query = "CAST(n_DisplaySequence AS UNSIGNED ) asc";
        return PolicyExtentionDetails::where('policy_coverage_id', $coverageId)
            ->where('type', $extensionType)
            ->orderByRaw( $query)
            ->get();
    }
}
