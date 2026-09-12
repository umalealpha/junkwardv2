<?php

namespace AlphaDirect\Http\Livewire\Policy;

use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Models\PolicyCoverageDetail;
use AlphaDirect\Models\PolicyCoverageEntity;
use AlphaDirect\Models\PolicyCoverageNote;
use AlphaDirect\Models\PolicyExtentionDetails;
use AlphaDirect\Models\PolicySpecifiedItem;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Policy;
use AlphaDirect\PolicyBeneficiary;
use AlphaDirect\PolicyCellPhone;
use AlphaDirect\Vehicle;
use Livewire\Component;
use AlphaDirect\Models\PolicyAction;


class CoveragesDetails extends Component

{

    const RISKACCORDIONCOLLAPSE = 'show'; //show
    const COVERSGEACCORDIONCOLLAPSE = 'show'; //show

    public Policy $policy;
    public $termId;
    public $actionId;
    public $previousActionId;
    public $dataShowForActionId;
    public PolicyCoverage $policyCoverage;
    public $editmode;

    public $policyCoverageDetail = [];
    public $policyExtentionDetail = [];
    public $policyCoverageEntity = [];
    public $policyCoverageNote;
    public $policyCoverageMemorandaWarranty;
    public $policyCoverageCashWarranty;
    public $policyCoverageBurglarWarranty;
    public $policyCoverageEndorsements;
    public $specifiedRow = [];
    public $specified_items;
    public $specifiedItems = [];

    protected $rules = [
        'policyCoverage.coverage_id' => 'required',
        'policyCoverage.risk_address_id' => 'required',
        'policyCoverage.term_id' => 'required',
        'policyCoverage.action_id' => 'required',
        'policyCoverage.policy_id' => 'required',

        'policyCoverageDetail.*.*.coverage_value' => '',
        'policyCoverageDetail.*.*.discount_surcharge' => '',
        'policyCoverageDetail.*.*.discount_surcharge_type' => '',
        'policyCoverageDetail.*.*.discount_surcharge_value' => 'nullable',
        'policyCoverageDetail.*.*.rate' => '',
        'policyCoverageDetail.*.*.calculated_value' => '',

        'policyCoverageEntity.*.*' => '',
        'policyCoverageNote.*' => '',
        'policyCoverageMemorandaWarranty.*' => '',
        'policyCoverageCashWarranty.*' => '',
        'policyCoverageBurglarWarranty.*' => '',
        'policyCoverageEndorsements.*' => '',

        'policyExtentionDetail.*.*.extention_text_value' => '',
        'policyExtentionDetail.*.*.extention_coverage_value' => '',
        'policyExtentionDetail.*.*.extention_discount_surcharge' => '',
        'policyExtentionDetail.*.*.extention_discount_surcharge_type' => '',
        'policyExtentionDetail.*.*.extention_discount_surcharge_value' => 'nullable',
        'policyExtentionDetail.*.*.extention_calculated_value' => '',
        'policyExtentionDetail.*.*.extention_limit_id' => '',
    ];

    protected $validationAttributes = [
        'policyCoverageDetail.*.*.calculated_value' => 'premium',
    ];

    public function render()
    {
        foreach ($this->policyCoverages as $index => $policyCoverage){
            // dd($policyCoverage);
            foreach ($policyCoverage->coverage['subCoverage'] as $subCoverage){
                $this->policyCoverageDetail[$policyCoverage->id][$subCoverage->id]['rate'] = $subCoverage['rate'] ?? 0;
            }

            foreach ($policyCoverage->coverageDetail as $coverageDetail){
                $this->policyCoverageDetail[$policyCoverage->id][$coverageDetail->coverage_id]['limit_id'] = $coverageDetail['limit_id'] ?? 0;
                $this->policyCoverageDetail[$policyCoverage->id][$coverageDetail->coverage_id]['ratefactor_type'] = $coverageDetail['ratefactor_type'] ?? null;
                $this->policyCoverageDetail[$policyCoverage->id][$coverageDetail->coverage_id]['ratefactor_value'] = $coverageDetail['ratefactor_value'] ?? null;
                $this->policyCoverageDetail[$policyCoverage->id][$coverageDetail->coverage_id]['coverage_value'] = $coverageDetail['coverage_value'] ?? 0;
                $this->policyCoverageDetail[$policyCoverage->id][$coverageDetail->coverage_id]['discount_surcharge'] = $coverageDetail['discount_surcharge'] ?? 0;
                $this->policyCoverageDetail[$policyCoverage->id][$coverageDetail->coverage_id]['discount_surcharge_type'] = $coverageDetail['discount_surcharge_type'] ?? 0;
                $this->policyCoverageDetail[$policyCoverage->id][$coverageDetail->coverage_id]['discount_surcharge_value'] = $coverageDetail['discount_surcharge_value'] ?? 0;
                $this->policyCoverageDetail[$policyCoverage->id][$coverageDetail->coverage_id]['calculated_value'] = $coverageDetail['calculated_value'] ?? 0;
            }
            // dd($policyCoverage->extentionDetail);
            foreach ($policyCoverage->extentionDetail as $extentionDetail){
                $this->policyExtentionDetail[$policyCoverage->id][$extentionDetail->extentions_id]['extention_limit_id'] = $extentionDetail['extention_limit_id'] ?? 0;
                $this->policyExtentionDetail[$policyCoverage->id][$extentionDetail->extentions_id]['extention_text_value'] = $extentionDetail['extention_text_value'] ?? null;
                $this->policyExtentionDetail[$policyCoverage->id][$extentionDetail->extentions_id]['extention_coverage_value'] = $extentionDetail['extention_coverage_value'] ?? 0;
                $this->policyExtentionDetail[$policyCoverage->id][$extentionDetail->extentions_id]['extention_discount_surcharge'] = $extentionDetail['extention_discount_surcharge'] ?? 0;
                $this->policyExtentionDetail[$policyCoverage->id][$extentionDetail->extentions_id]['extention_discount_surcharge_type'] = $extentionDetail['extention_discount_surcharge_type'] ?? 0;
                $this->policyExtentionDetail[$policyCoverage->id][$extentionDetail->extentions_id]['extention_discount_surcharge_value'] = $extentionDetail['extention_discount_surcharge_value'] ?? 0;
                $this->policyExtentionDetail[$policyCoverage->id][$extentionDetail->extentions_id]['extention_calculated_value'] = $extentionDetail['extention_calculated_value'] ?? 0;
            }

            foreach ($policyCoverage->entities as $entity){
                $this->policyCoverageEntity[$policyCoverage->id][$entity->entity_type] = $entity->entity_id;
            }

            $this->policyCoverageNote[$policyCoverage->id] = $policyCoverage->note->note ?? '' ;
            $this->policyCoverageMemorandaWarranty[$policyCoverage->id] = $policyCoverage->note->memoranda_warranty ?? '' ;
            $this->policyCoverageCashWarranty[$policyCoverage->id] = $policyCoverage->note->cash_warranty ?? '' ;
            $this->policyCoverageBurglarWarranty[$policyCoverage->id] = $policyCoverage->note->burglar_warranty ?? '' ;
            $this->policyCoverageEndorsements[$policyCoverage->id] = $policyCoverage->note->endorsements ?? '' ;
        }
        $this->policyAction = PolicyAction::where('id',$this->actionId)->first();

        return view('v2.livewire.policy.coverages-details');
    }

    public function mount(){
        $this->dataShowForActionId = $this->previousActionId ?? $this->actionId;
        $this->policyCoverage = new PolicyCoverage();
        $this->policyCoverage->term_id = $this->termId;
        $this->policyCoverage->action_id = $this->actionId;
        $this->policyCoverage->policy_id = $this->policy->id;
        $this->AllMainCoverages;

        foreach ($this->policyCoverages as $index => $policyCoverage){

            foreach ($policyCoverage->specifedItems as $index => $specifedItemData){
                $this->specified_items[$policyCoverage->id][$index] = [
                    'selected_item' => $specifedItemData['specified_coverage_id'],
                    'sum_insured' => $specifedItemData['sum_insured'],
                    'id' => $specifedItemData['id']
                ];
                if (!isset($this->specifiedRow[$policyCoverage->id][$index])){
                    $this->specifiedRow[$policyCoverage->id][$index] = [];
                }
            }
        }
    }

    public function submit(){
        $this->rules = [
            'policyCoverageDetail.*.*.coverage_value' => '',
            'policyCoverageDetail.*.*.discount_surcharge' => '',
            'policyCoverageDetail.*.*.discount_surcharge_type' => '',
            'policyCoverageDetail.*.*.discount_surcharge_value' => 'nullable',
            'policyCoverageDetail.*.*.calculated_value' => 'gt:0',
            'policyCoverageDetail.*.*.rate' => '',
            'policyExtentionDetail.*.*.extention_text_value' => '',
            'policyExtentionDetail.*.*.extention_coverage_value' => '',
            'policyExtentionDetail.*.*.extention_limit_id' => '',
            'policyExtentionDetail.*.*.extention_discount_surcharge' => '',
            'policyExtentionDetail.*.*.extention_discount_surcharge_type' => '',
            'policyExtentionDetail.*.*.extention_discount_surcharge_value' => 'nullable',
            'policyExtentionDetail.*.*.extention_calculated_value' => 'numeric|min:0',
        ];
        foreach ($this->policyCoverageDetail as $policyCoverageId => $policyCoverageData){
            foreach ($policyCoverageData as $coverage_id => $coverageData) {
                if (isset($coverageData['coverage_value']))
                {
                    $coverageData['coverage_value'] = (float)(str_replace(',', '', ($coverageData['coverage_value']??''))) ?? 0;
                    $coverageData['discount_surcharge_value'] = (float)(str_replace(',', '', ($coverageData['discount_surcharge_value']??''))) ?? 0;
                    $calculated_value = $coverageData['coverage_value'] ?? 0;
                    if (isset($coverageData['rate'])){
                        $calculated_value = ((float)($calculated_value)*(float)($coverageData['rate']))/100;
                    }
                    if (isset($coverageData['discount_surcharge']) and isset($coverageData['discount_surcharge_type']) and isset($coverageData['discount_surcharge_value'])){
                        if ($coverageData['discount_surcharge']=="Discount"){
                            if ($coverageData['discount_surcharge_type'] == "Flat"){
                                $calculated_value = (float)($calculated_value) - (float)($coverageData['discount_surcharge_value']);
                            }
                            if ($coverageData['discount_surcharge_type'] == "Percentage"){
                                $calculated_value = (float)($calculated_value) - (((float)($calculated_value) * (float)($coverageData['discount_surcharge_value']))/100);
                            }
                        }
                        if ($coverageData['discount_surcharge']=="Surcharge"){
                            if ($coverageData['discount_surcharge_type'] == "Flat"){
                                $calculated_value = (float)($calculated_value) + (float)($coverageData['discount_surcharge_value']);
                            }
                            if ($coverageData['discount_surcharge_type'] == "Percentage"){
                                $calculated_value =  (float)($calculated_value) + (((float)($calculated_value) * (float)($coverageData['discount_surcharge_value']))/100);
                            }
                        }
                    }
                    $coverageData['calculated_value'] = $calculated_value;
                    $this->policyCoverageDetail[$policyCoverageId][$coverage_id] = $coverageData;
                }
            }
        }

        // For extention
        foreach ($this->policyExtentionDetail as $policyCoverageId => $policyCoverageData){
            foreach ($policyCoverageData as $extentions_id => $extentionData) {
              //  dd($extentionData);
                if (isset($extentionData['extention_coverage_value']) || isset($extentionData['extention_text_value']) || isset($extentionData['extention_limit_id']))
                {
                    $extentionData['extention_limit_id'] = $extentionData['extention_limit_id'] ?? null;
                    $extentionData['extention_text_value'] = $extentionData['extention_text_value'] ?? null;
                    $extentionData['extention_coverage_value'] = (float)(str_replace(',', '', ($extentionData['extention_coverage_value']??''))) ?? 0;
                    $extentionData['extention_discount_surcharge_value'] = (float)(str_replace(',', '', ($extentionData['extention_discount_surcharge_value']??''))) ?? 0;
                    $calculated_value = $extentionData['extention_coverage_value'] ?? 0;
                    if (isset($extentionData['rate'])){
                        $calculated_value = ((float)($calculated_value)*(float)($extentionData['rate']))/100;
                    }
                    if (isset($extentionData['extention_discount_surcharge']) and isset($extentionData['extention_discount_surcharge_type']) and isset($coverageData['extention_discount_surcharge_value'])){
                        if ($extentionData['extention_discount_surcharge']=="Discount"){
                            if ($extentionData['extention_discount_surcharge_type'] == "Flat"){
                                $calculated_value = (float)($calculated_value) - (float)($extentionData['extention_discount_surcharge_value']);
                            }
                            if ($extentionData['extention_discount_surcharge_type'] == "Percentage"){
                                $calculated_value = (float)($calculated_value) - (((float)($calculated_value) * (float)($extentionData['extention_discount_surcharge_value']))/100);
                            }
                        }
                        if ($extentionData['extention_discount_surcharge']=="Surcharge"){
                            if ($extentionData['extention_discount_surcharge_type'] == "Flat"){
                                $calculated_value = (float)($calculated_value) + (float)($extentionData['extention_discount_surcharge_value']);
                            }
                            if ($extentionData['extention_discount_surcharge_type'] == "Percentage"){
                                $calculated_value =  (float)($calculated_value) + (((float)($calculated_value) * (float)($extentionData['extention_discount_surcharge_value']))/100);
                            }
                        }
                    }
                    $extentionData['extention_calculated_value'] = $calculated_value;
                    $this->policyExtentionDetail[$policyCoverageId][$extentions_id] = $extentionData;
                }

            }
        }

        $this->validate();

        // save coverage detail
        foreach ($this->policyCoverageDetail as $policyCoverageId => $policyCoverageData){
            PolicyCoverageDetail::PolicyCoverage($policyCoverageId)->delete();
            foreach ($policyCoverageData as $coverage_id => $coverageData) {
                if (isset($coverageData['coverage_value']))
                {
                    PolicyCoverageDetail::withTrashed()->updateOrCreate(
                        [
                            'policy_coverage_id' =>  $policyCoverageId,
                            'coverage_id' => $coverage_id
                        ],
                        [
                            'coverage_value' => $coverageData['coverage_value'],
                            'discount_surcharge' => $coverageData['discount_surcharge'] ?? '',
                            'discount_surcharge_type' => $coverageData['discount_surcharge_type'] ?? '',
                            'discount_surcharge_value' => $coverageData['discount_surcharge_value'] ?? '',
                            'rate' => $coverageData['rate'] ?? '',
                            'calculated_value' => $coverageData['calculated_value'] ?? '',
                            'deleted_at' => null,
                        ]
                    );
                }
            }
        }

        // save Extention detail
        foreach ($this->policyExtentionDetail as $policyCoverageId => $policyExtentionData){
            //  dd($policyCoverageId);
            foreach ($policyExtentionData as $extentions_id => $extentionData) {
              //  dd($extentionData['extention_coverage_value']);
                if (isset($extentionData['extention_coverage_value']) || isset($extentionData['extention_text_value']) || isset($extentionData['extention_limit_id']))
                {
                    // Fetch main coverage record to get parent coverage ID
                    $policyCoverage = DB::table('policy_coverages')
                        ->where('id', $policyCoverageId)
                        ->whereNull('deleted_at')
                        ->first(['id', 'coverage_id', 'policy_id']);

                    if (!$policyCoverage) {
                        continue;
                    }

                    // Load Extention master data directly from database
                    $extention = DB::table('extentions')
                        ->where('id', $extentions_id)
                        ->first();

                    if (!$extention) {
                        continue;
                    }

                    DB::table('policy_extention_detail')->updateOrInsert(
                        [
                            'policy_coverage_id' =>  $policyCoverageId,
                            'extentions_id' => $extentions_id
                        ],
                        [
                            // Main Coverage ID - from policy_coverages table
                            's_ParentCoverageID' => $extention->s_ParentCoverageID ?? $policyCoverage->coverage_id ?? '',
                            's_SubCoverageID' => $extention->s_SubCoverageID ?? '',
                            's_ParentCoverageCode' => $extention->s_ParentCoverageCode ?? '',
                            'type' => $extention->type ?? '',
                            'extention_type' => $extention->extention_type ?? '',
                            's_CoverageName' => $extention->s_CoverageName ?? '',
                            's_CoverageCode' => $extention->s_CoverageCode ?? '',
                            's_ScreenName' => $extention->s_ScreenName ?? '',
                            's_CoverageDesc' => $extention->s_CoverageDesc ?? '',
                            's_ExtensionsGroupName' => $extention->s_ExtensionsGroupName ?? '',
                            'n_DisplaySequence' => $extention->n_DisplaySequence ?? '',
                            'rate' => $extention->rate ?? '',
                            'd_EffectiveDt' => $extention->d_EffectiveDt ?? null,
                            'd_ExpirationDt' => $extention->d_ExpirationDt ?? null,
                            's_RatingMethod' => $extention->s_RatingMethod ?? '',
                            's_DISPLAYTOUSER' => $extention->s_DISPLAYTOUSER ?? '',

                            // Form Input Fields
                            'extention_text_value' => $extentionData['extention_text_value'] ?? null,
                            'extention_coverage_value' => $extentionData['extention_coverage_value'] ?? '',
                            'extention_limit_id' => $extentionData['extention_limit_id'] ?? null,
                            'extention_discount_surcharge' => $extentionData['extention_discount_surcharge'] ?? '',
                            'extention_discount_surcharge_type' => $extentionData['extention_discount_surcharge_type'] ?? '',
                            'extention_discount_surcharge_value' => $extentionData['extention_discount_surcharge_value'] ?? '',
                            'extention_calculated_value' => $extentionData['calculated_value'] ?? '',
                            'deleted_at' => null,
                            'updated_at' => now()
                        ]
                    );
                }
            }
        }

        // save entity detail
        foreach ($this->policyCoverageEntity as $policyCoverageId => $entityData){
            PolicyCoverageEntity::PolicyCoverage($policyCoverageId)->delete();
            foreach ($entityData as $entityType => $entityId){
                PolicyCoverageEntity::withTrashed()->updateOrCreate([
                    'policy_coverage_id' =>  $policyCoverageId,
                    'entity_type' => $entityType
                ],[
                    'entity_id' => $entityId,
                    'deleted_at' => null
                ]);
            }
        }

        // save notes
        foreach ($this->policyCoverageNote as $policyCoverageId => $note){
            PolicyCoverageNote::PolicyCoverage($policyCoverageId)->delete();
            PolicyCoverageNote::withTrashed()->updateOrCreate([
                'policy_coverage_id' =>  $policyCoverageId,
            ],[
                'note' => $note
            ]);
        }

        foreach ($this->policyCoverageMemorandaWarranty as $policyCoverageId => $memoranda_warranty){
            PolicyCoverageNote::withTrashed()->updateOrCreate([
                'policy_coverage_id' =>  $policyCoverageId,
            ],[
                'memoranda_warranty' => $memoranda_warranty
            ]);
        }
        foreach ($this->policyCoverageCashWarranty as $policyCoverageId => $cash_warranty){
            PolicyCoverageNote::withTrashed()->updateOrCreate([
                'policy_coverage_id' =>  $policyCoverageId,
            ],[
                'cash_warranty' => $cash_warranty
            ]);
        }
        foreach ($this->policyCoverageEndorsements as $policyCoverageId => $endorsements){
            PolicyCoverageNote::withTrashed()->updateOrCreate([
                'policy_coverage_id' =>  $policyCoverageId,
            ],[
                'endorsements' => $endorsements
            ]);
        }
        foreach ($this->policyCoverageBurglarWarranty as $policyCoverageId => $burglar_warranty){
            PolicyCoverageNote::withTrashed()->updateOrCreate([
                'policy_coverage_id' =>  $policyCoverageId,
            ],[
                'burglar_warranty' => $burglar_warranty
            ]);
        }
        
        // save specified coverage
        if ($this->specified_items){
            foreach ($this->specified_items as $policyCoverageId => $specifiedItemDatas){
                PolicySpecifiedItem::PolicyCoverage($policyCoverageId)->forceDelete();
                foreach ($specifiedItemDatas as $specifiedItemData){
                    //@todo need to set rate and calculate with rate
                    PolicySpecifiedItem::create([
                        'policy_coverage_id' =>  $policyCoverageId,
                        'specified_coverage_id' => $specifiedItemData['selected_item'],
                        'sum_insured' => $specifiedItemData['sum_insured']
                    ]);
                }
            }
        }
    }

    public function addMainCoverage(){
        $this->validate();
        $this->policyCoverage->save();
        $this->policyCoverage = $this->policyCoverage->replicate();
    }

    public function getPolicyCoveragesProperty(){

        return PolicyCoverage::with(['coverage','coverageDetail','extentionDetail'])
            ->where(function($query){
                return $query->has('coverageDetail')->orHas('entities')->orHas('note')->orHas('specifedItems');
            })
            ->select('id','risk_address_id','coverage_id')
            ->Policy($this->policy->id)->Action($this->dataShowForActionId)
            ->orderBy('risk_address_id')
            ->get();
    }

    public function getAllRiskAddressProperty(){
        return RiskAddress::Policy($this->policy->id)->get()->keyBy('id')->map(function($riskaddress){
            return [
                'id'=>$riskaddress->id,
                'name'=>$riskaddress->address_name
            ];
        });
    }

    public function getAllMainCoveragesProperty(){
        $data = ($this->policy?->product?->coverageMaster()
                ->select('tb_cvgpccoverages.id','s_CoverageCode')?->with([
                    'specifiedCoverages' => function($query){
                        $query->EffectiveItemOnly();
                    }])->get()
            ) ?? [];
        foreach ($data as $coverageData){
            $this->specifiedItems[$coverageData->id] = $coverageData->specifiedCoverages->pluck('specified_name','id');
        }
        return $data->keyBy('id')->map(function($coverage){
            return [
                'id'=>$coverage->id,
                'name'=>$coverage->s_CoverageCode
            ];
        });
    }

    public function getAllVehiclesProperty(){
        return Vehicle::select('vehiclePlate','id')->PolicyId($this->policy->id)->ActionId($this->dataShowForActionId)
            ->get()->pluck('vehiclePlate','id')->toArray();
    }

    public function getallDevicesProperty(){
        return PolicyCellPhone::Policy($this->policy->id)->action($this->dataShowForActionId)->get()->pluck('device_type','id')->toArray();
    }

    public function getallBeneficiariesProperty(){
        return PolicyBeneficiary::selectRaw("id,concat(first_name,' ',middle_name,' ',last_name) as name")->Policy($this->policy->id)->action($this->dataShowForActionId)->get()->pluck('name','id')->toArray();
    }
    public function deleteCoverage($policyCoverageId){
        // @todo delete all the data related to te policyCOverage
        PolicyCoverage::find($policyCoverageId)->delete();
    }

    // specified iteams code

    public function addSpecifiedRow($policyCoverageId)
    {
        $this->specifiedRow[$policyCoverageId][] = [];
    }
    public function removeRow($policyCoverageId,$key,$specifiedId=0)
    {
        unset($this->specifiedRow[$policyCoverageId][$key]);
        unset($this->specified_items[$policyCoverageId][$key]);
    }

    public function backToStep2(){
        $this->emitUp('backToStep2');
    }
}
