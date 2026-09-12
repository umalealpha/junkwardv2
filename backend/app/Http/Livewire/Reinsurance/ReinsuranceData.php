<?php

namespace AlphaDirect\Http\Livewire\Reinsurance;

use Livewire\Component;
use AlphaDirect\Models\PolicyReinsurer;
use AlphaDirect\Models\Policy;
use AlphaDirect\Models\Customer;
use AlphaDirect\Models\Company;
use AlphaDirect\Models\Product;
use AlphaDirect\Models\ReinsuranceGroup;
use AlphaDirect\Models\ReinsuranceType;
use AlphaDirect\Models\ReinsuranceFormula;
use AlphaDirect\Models\ReinsuranceFormulaDetails;
use AlphaDirect\Models\ReinsuranceTreaty;
use AlphaDirect\Models\ReinsuranceTreatyDetails;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Models\PolicyCoverageDetail;
use AlphaDirect\PolicySlip;
use AlphaDirect\Models\CoverageMaster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Log;

class ReinsuranceData extends Component
{
    public $reinsurers;
    public $selectedReinsurer = '';
    public $reinsurersCompany = '';
    public $reinsurersType = '';
    public $reinsurersBroker = '';
    public $reinsurersCover = '';
    public $ReinsuranceCommission = '';
    public $policyId = '';
    public $actionId = '';
    public $groupId = '';
    public function mount()
    {
        $this->loadReinsurers();
    }

    public function loadReinsurers()
    {
        
        $this->reinsurers = PolicyReinsurer::all()->mapWithKeys(function ($item) {
            return [$item->id => $item->reinsurers_company . ' - ' . $item->reinsurers_type];
        })->toArray();
    }

    public function updatedSelectedReinsurer($value)
    {
        if ($value) {
            $reinsurer = PolicyReinsurer::find($value);
            if ($reinsurer) {
                $this->reinsurersCompany = $reinsurer->reinsurers_company;
                $this->reinsurersType = $reinsurer->reinsurers_type;
            }
        } else {
            $this->reinsurersCompany = '';
            $this->reinsurersType = '';
        }
    }

    public function submit()
    {
        
        $this->validate([
            'selectedReinsurer' => 'required',
            'reinsurersBroker' => 'required|string',
            'reinsurersCover' => 'required|string',
            'ReinsuranceCommission' => 'required|string',
        ]);

        $policyId = $this->policyId; 
        DB::transaction(function () use ($policyId, &$policySlip) {

            $year = date('Y');
        
            $lastSeq = PolicySlip::where('slip_year', $year)
                ->lockForUpdate()
                ->max('sequence_no');
        
            $nextSeq = $lastSeq ? $lastSeq + 1 : 1;
            $nextSeq = (int) $lastSeq + 1;
            $formattedSeq = str_pad($nextSeq, 2, '0', STR_PAD_LEFT);

        
            $policySlip = PolicySlip::create([
                'policy_id'       => $policyId,
                'slip_year'       => $year,
                'sequence_no'     => $formattedSeq,
                'policy_slip_no'  => $year . '/' . $formattedSeq,
            ]);
        });
        activity()
        ->performedOn($policySlip) // MUST be model
        ->withProperties([
            'policy_slip_no' => $policySlip->policy_slip_no,
        ])
        ->log('Policy Slip Generated');

        $finalReinsurerSlipData = [];
        $reinsurerSlipData = DB::table('policies')
        ->join('customer', 'policies.customer_id', '=', 'customer.id')
        ->join('companies', 'customer.company_id', '=', 'companies.id')
        ->where('policies.id', $this->policyId) 
        ->select('policies.*', 'customer.*', 'companies.name as company_name')
        ->first();
        // Get all s_ScreenName values as a comma-separated string
        $screenNames = DB::table('policy_coverages')
            ->join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverages.coverage_id')
            ->where('policy_coverages.policy_id', $this->policyId)
            ->where('tb_cvgpccoverages.id', $this->groupId)
            ->where('policy_coverages.action_id', $this->actionId)
            ->whereNull('policy_coverages.deleted_at')
            ->pluck('tb_cvgpccoverages.s_ScreenName')
            ->implode(', ');
            $addresses = RiskAddress::query()
            ->select('address_name', 'physical_address')
            ->where('policy_id', $this->policyId)
            ->where('action_id', $this->actionId)
            ->first();

            $term = DB::table('policy_term')
            ->select('term_start_date', 'term_end_date')
            ->where('policy_id', $this->policyId)
            ->first();   
            
            $reinsuranceGroupCoverId = DB::table('reinsurance_group')
            ->select('coverages_id')
            ->where('id', $this->groupId)
            ->first(); 
            
            $totalPremiumData = DB::table('policy_reinsurance')
            ->select('totalPremium')
            ->where('group_id', $this->groupId)
            ->where('policy_id', $this->policyId)
            ->first(); 
            
            $totalPremium = $totalPremiumData->totalPremium;
            $allCoverIDData = $reinsuranceGroupCoverId->coverages_id;
            $allCoverID = explode(',', $allCoverIDData);
            
        // Get coverage details using models
         $coverageDetails = PolicyCoverage::query()
            ->join(
                'policy_coverage_detail',
                'policy_coverage_detail.policy_coverage_id',
                '=',
                'policy_coverages.id'
            )
            ->join(
                'tb_cvgpccoverages',
                'tb_cvgpccoverages.id',
                '=',
                'policy_coverage_detail.coverage_id'
            )
            ->where('policy_coverages.policy_id', $this->policyId)
            ->whereNull('policy_coverages.deleted_at')
            ->whereIn('policy_coverages.coverage_id', $allCoverID)
            ->select(
                'tb_cvgpccoverages.s_ScreenName',
                'policy_coverage_detail.coverage_value',
                'policy_coverage_detail.calculated_value',
                'policy_coverage_detail.coverage_value_string'
            )
            ->get();
        $policySlipNumber = $policySlip->policy_slip_no;
        $finalReinsurerSlipData['Insured'] = $reinsurerSlipData->company_name;
        $finalReinsurerSlipData['TypeandExtentofCoverGranted'] = $screenNames;
        $finalReinsurerSlipData['policyNo'] = $reinsurerSlipData->policyNumber;
        $finalReinsurerSlipData['PhysicalLocation'] = $addresses ? ($addresses->address_name . ' - ' . $addresses->physical_address) : '';
        $finalReinsurerSlipData['PolicyStartDate'] = $term ? $term->term_start_date : '';
        $finalReinsurerSlipData['PolicyEndDate'] = $term ? $term->term_end_date : '';
        $finalReinsurerSlipData['reinsurersBroker'] = $this->reinsurersBroker;
        $finalReinsurerSlipData['reinsurersCover'] = $this->reinsurersCover;
        $finalReinsurerSlipData['ReinsuranceCommission'] = $this->ReinsuranceCommission;
        $finalReinsurerSlipData['reinsurersCompany'] = $this->reinsurersCompany;
        $finalReinsurerSlipData['reinsurersType'] = $this->reinsurersType;
        $finalReinsurerSlipData['totalPremium'] = $totalPremium;
        $finalReinsurerSlipData['policySlipNumber'] = $policySlipNumber;        
        
        // Store data in session and redirect
        session()->put('reinsuranceDocumentData', $finalReinsurerSlipData);
        session()->put('coverageDetails', $coverageDetails);
        
        return $this->redirect(route('admin.policy.reinsuranceDocument', [
            'policyId' => $this->policyId,
            'actionId' => $this->actionId,
            'groupId' => $this->groupId
        ]));
    }

    public function render()
    {
        // Get policyId and actionId from route parameters
        $this->policyId = request()->route('policyId') ?? $this->policyId;
        $this->actionId = request()->route('actionId') ?? $this->actionId;
        $this->groupId = request()->route('groupId') ?? $this->groupId;
                   
        return view('v2.livewire.reinsurance.reinsurance-data', [
            'policyId' => $this->policyId,
            'actionId' => $this->actionId,
            'groupId' => $this->groupId
        ]);
    }
   
}