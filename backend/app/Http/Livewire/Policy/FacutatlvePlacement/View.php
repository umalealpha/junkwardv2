<?php

namespace AlphaDirect\Http\Livewire\Policy\FacutatlvePlacement;

use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Models\FacutatlveParticipants;
use AlphaDirect\Policy;
use Livewire\Component;
use AlphaDirect\Models\TbPorifacmaster;
use AlphaDirect\Models\TbPorifacdetail;
use AlphaDirect\Models\TbPorifacparties;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Auth;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Models\PolicyCoverageDetail;
use DB;

class View extends Component
{
    public Policy $policy;
    public $actionId;
    public $tbporifacmaster;
    public $allParticipants;
    public $coveragesValues = [];
    public $totalSumInsured = 0;
    public $totalPremium = 0;
    public $editForm = true;
    public $tbporifacdetail;
    public $coverages;
    public $action;

    public $rules = [
        'tbporifacmaster.s_FacPlacmentNo' => 'required',
        'tbporifacmaster.s_FacOfferReference' => 'required',
        'tbporifacmaster.d_PlacementDate' => 'required',
        'tbporifacmaster.d_PlacementEffectiveFrom' => 'required',
        'tbporifacmaster.s_PremiumCalcType' => 'required',
        'tbporifacmaster.s_RateBasis' => 'required',
        'tbporifacmaster.n_FacShare' => 'required',
        'tbporifacmaster.s_Remarks' => 'required'
    ];

    public function mount(){
        $TbPorifacmasterExist = TbPorifacmaster::where('policy_id',$this->policy->id)->where('action_id',$this->actionId)->first();
        if(!empty($TbPorifacmasterExist)){
            $this->tbporifacmaster = $TbPorifacmasterExist;
            $this->editForm = false;
        }else{
            $this->tbporifacmaster = new TbPorifacmaster;
            $this->editForm = true;
        }
        $this->tbporifacdetail = new TbPorifacdetail;
        $this->coverages = PolicyCoverage::Policy($this->policy->id)->Action($this->actionId)->orderby('id','desc')->get();
        $this->coveragesValues = PolicyCoverage::Policy($this->policy->id)->Action($this->actionId)->orderby('id','desc')->get();
        $this->totalSumInsured = $this->coveragesValues->sum('coverage_value');
        $this->totalPremium = $this->coveragesValues->sum('calculated_value');
        $this->action = PolicyAction::find($this->actionId);
    }

    public function getPolicySubCoverage($code){
        $subCoverages = PolicyCoverageDetail::where('policy_coverage_id',$code)->get();
        $subCoveragesValue = $subCoverages->sum('coverage_value');
        $subCoveragesCalculated = $subCoverages->sum('calculated_value');
        $subCoveragesArray = array(
            'SumInsured'=>$subCoveragesValue,
            'Premium'=>$subCoveragesCalculated
        );
        return $subCoveragesArray;
    }

    public function getCoverageId($code){
        return CoverageMaster::where('s_CoverageCode',$code)->where('s_UsageType','PARENT')->first()?->id;
    }

    public function submit($formData){
        request()->merge($formData);
        $this->validate();
        $this->tbporifacmaster->policy_id = $this->policy->id;
        $this->tbporifacmaster->action_id  = $this->actionId ;
        $this->tbporifacmaster->s_FacPlacmentNo = $this->tbporifacmaster->s_FacPlacmentNo;
        $this->tbporifacmaster->s_FacOfferReference = $this->tbporifacmaster->s_FacOfferReference;
        $this->tbporifacmaster->d_PlacementDate = $this->tbporifacmaster->d_PlacementDate;
        $this->tbporifacmaster->d_PlacementEffectiveFrom = $this->tbporifacmaster->d_PlacementEffectiveFrom;
        $this->tbporifacmaster->s_PremiumCalcType = $this->tbporifacmaster->s_PremiumCalcType;
        $this->tbporifacmaster->s_RateBasis = $this->tbporifacmaster->s_RateBasis;
        $this->tbporifacmaster->n_FacShare = $this->tbporifacmaster->n_FacShare;
        $this->tbporifacmaster->s_Remarks = $this->tbporifacmaster->s_Remarks;
        $this->tbporifacmaster->added_by = Auth::user()->id;
        if ($this->tbporifacmaster->save()){
            if (count($this->coverages)>0){
                foreach($this->coverages as $c){
                    if(request()->get($c->id."_FIELD1")!=""){
                        $data[$c->id]['porifacmasters_id']= $this->tbporifacmaster->id;
                        // $data[$c->main]['n_PORiskMaster_FK']=;
                        // $data[$c->main]['group_id']=;
                        // $data[$c->main]['s_RIGroupCode']=;
                        $data[$c->id]['porifacmasters_id']=$this->tbporifacmaster->id;
                        $data[$c->id]['n_PoCoverageSubMaster_FK']= request()->get($c->id."_FIELD1")??"";
                        $data[$c->id]['n_PolicySumInsured']=request()->get($c->id."_FIELD2");
                        $data[$c->id]['n_PolicyPremium']=request()->get($c->id."_FIELD3");
                    }
                }
                if(!$this->editForm){
                    DB::table('tb_porifacdetails')->where('porifacmasters_id',$this->tbporifacmaster->id)->delete();
                }
                dd($data);
                TbPorifacdetail::insert($data);
            }
            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Placement data has been saved successfully.']);
            $this->mount();
            return Redirect::route('policy.edit', [\Crypt::encrypt($this->policy->id)]);
        }
        $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Something Went Wrong']);
    }

    public function render()
    {
        return view('v2.livewire.policy.facutatlve-placement.view')->layout('layouts.app-v2');
    }
}
