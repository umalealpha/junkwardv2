<?php

namespace AlphaDirect\Http\Livewire\SubCoverage;

use AlphaDirect\Models\CoverageMaster;
use Carbon\Carbon;
use AlphaDirect\Models\TbValidOptions;
use Illuminate\Support\Facades\Redirect;
use Livewire\Component;
use DB;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Customer;
class Add extends Component
{
    public $coverage,$s_CoverageName,$rate,$s_ScreenName,$coverageId,$n_DisplaySequence;
	public $d_EffectiveDt,$s_CoverageDesc,$s_RatingMethod,$s_CoverageGroupName,$s_SubCoverageMainName,$s_DISPLAYTOUSER,$d_ExpirationDt;
    public $subcoverage = [];
    public $modalId = false;
    public $inputs = [];
    public $i = 0;
    public $policy;
    protected $rules = [
		'coverage' => 'required',
		's_CoverageName.0'=>'required',
    	's_ScreenName.0'=>'required',
        'rate.0'=>'required|numeric',
    	'd_EffectiveDt.0'=>'required',
    	'd_ExpirationDt.0'=>'required',
    	's_CoverageDesc.0'=>'required',
    	's_RatingMethod.0'=>'required',
    	's_CoverageGroupName.0'=>'required',
        's_SubCoverageMainName.0'=>'required',
    	's_DISPLAYTOUSER.0'=>'',
        'n_DisplaySequence.0'=>'required',
		's_CoverageName.*'=>'required',
    	's_ScreenName.*'=>'required',
        'rate.*'=>'required|numeric',
    	'd_EffectiveDt.*'=>'required',
    	'd_ExpirationDt.*'=>'required',
    	's_CoverageDesc.*'=>'required',
    	's_RatingMethod.*'=>'required',
    	's_CoverageGroupName.*'=>'required',
        's_SubCoverageMainName.*'=>'required',
    	's_DISPLAYTOUSER.*'=>'',
        'n_DisplaySequence.*'=>'required',
    ];

	protected $validationAttributes = [
		's_CoverageName.0' 	=>'Coverage Name',
    	's_ScreenName.0' 		=>'Screen name',
        'rate.0' 		=>'Rate',
    	'd_EffectiveDt.0'		=>'Effective Date',
    	'd_ExpirationDt.0'		=>'Expire Date',
    	's_CoverageDesc.0'		=>'Describtion',
    	's_RatingMethod.0'		=>'Rating Method',
    	's_CoverageGroupName.0' =>'Coverage Group Name',
        's_SubCoverageMainName.0' =>'Group Name',
    	's_DISPLAYTOUSER.0'		=>'Display To User',
        'n_DisplaySequence.0'	=>'Display To Sequence',
		's_CoverageName.*'		=>'Coverage Name',
    	's_ScreenName.*'		=> 'Screen name',
        'rate.*'		        => 'Rate',
    	'd_EffectiveDt.*'		=>'Effective Date',
    	'd_ExpirationDt.*'		=>'Expire Date',
    	's_CoverageDesc.*'		=>'Describtion',
    	's_RatingMethod.*'		=>'Rating Method',
    	's_CoverageGroupName.*'		=>'Coverage Group Name',
        's_SubCoverageMainName.*'	=>'Group Name',
    	's_DISPLAYTOUSER.*'		=>'Display To User',
        'n_DisplaySequence.*'	=>'Display To Sequence'
	];

    public function getCoveragesMasterProperty(){
        return CoverageMaster::where('s_CoverageGroupCode','=','MAIN')
                ->where('s_UsageType','=','PARENT')
                ->orderBy('s_CoverageName','asc')
                ->get()->keyBy('id')
                ->map(function($d){
                    return [
                        'id'=>$d->id,
                        'name'=>$d->s_CoverageName
                    ];
                });
	}

    public function mount(){
        $this->s_DISPLAYTOUSER[0] = false;
	}

    public function addRow($i)
    {
        $i = $i + 1;
        $this->i = $i;
		$this->s_DISPLAYTOUSER[$i] = false;
        array_push($this->inputs ,$i);
    }

    public function remove($i)
    {
		unset($this->inputs[$i]);
        // unset($this->s_CoverageName[$i]);
       // dd($this->inputs);
	}

    public function submit(){
       
		$this->validate();
		$d = CoverageMaster::find($this->coverage);
		foreach ($this->s_CoverageName as $key => $value){
            $coverage_master = new CoverageMaster();
            $coverage_master->policy_id= null;
			$coverage_master->s_CoverageCode = strtoupper($this->s_ScreenName[$key]);
            $coverage_master->n_DisplaySequence = $this->n_DisplaySequence[$key];
            $coverage_master->s_GroupRowType = 'COVERAGE';
            $coverage_master->s_UsageType = 'CHILD';
            $coverage_master->s_CoveragePart = 'PROPERTY';
            $coverage_master->s_CoverageSection = 'SUB';
            $coverage_master->s_CoverageGroupCode = 'SUB';
            $coverage_master->s_ParentCoverageCode = str_replace(' ', '', $d->s_CoverageCode);
            $coverage_master->n_ParentCoverageForRate = '2';
            $coverage_master->s_DefaultCovgCategoryCode = 'ENDCOVG';
            $coverage_master->s_AutoRenew = 'Y';
            $coverage_master->s_PermitDuplication = 'N';
            $coverage_master->s_CvgOccurrence = 'SINGLE';

            $coverage_master->s_ParentCoverageID = $this->coverage;
            $coverage_master->s_CoverageName = $this->s_CoverageName[$key];
            $coverage_master->s_ScreenName = $this->s_ScreenName[$key];
            $coverage_master->rate = $this->rate[$key];
            // $coverage_master->d_EffectiveDt = $this->d_EffectiveDt[$key];
            // $coverage_master->d_ExpirationDt = $this->d_ExpirationDt[$key];

            $coverage_master->d_EffectiveDt = Carbon::createFromFormat(config('constants.date.format'),$this->d_EffectiveDt[$key]);
            $coverage_master->d_ExpirationDt = Carbon::createFromFormat(config('constants.date.format'),$this->d_ExpirationDt[$key]);

            $coverage_master->s_CoverageDesc = $this->s_CoverageDesc[$key];
            $coverage_master->s_RatingMethod = $this->s_RatingMethod[$key];
            $coverage_master->s_CoverageGroupName = $this->s_CoverageGroupName[$key];
            $coverage_master->s_SubCoverageMainName = $this->s_SubCoverageMainName[$key];
            $coverage_master->s_DISPLAYTOUSER = ($this->s_DISPLAYTOUSER[$key]==true)?1:0;
			$coverage_master->save();

            $valid_option = new TbValidOptions();
            $valid_option->s_OptionType = 'CVG_LIMIT';
            $valid_option->n_SourceOneFK = $coverage_master->id;
            $valid_option->s_SourceOneType = null;
            $valid_option->n_SourceTwoFK = 1;
            $valid_option->s_SourceOneDesc = 'Coverage Primary Key';
            $valid_option->s_SourceTwoDesc = 'Limit Primary Key';
            $valid_option->d_CreatedDate = Carbon::now();
            $valid_option->save();
		}
		$this->inputs = [];
            DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Sub Coverage')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Sub Coverage Added - '.$this->coverage);
        $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Sub Coverage Add Successfully!']);
        $this->mount();
        if ($this->modalId){
            $this->emitUp('refreshParent');
        }
        return ;

		// session()->flash('success','Sub Coverage Add Successfully.');
		// return redirect()->route('subcoverage');
    }

    public function render()
    {
        return view('v2.livewire.sub-coverage.add')->layout('layouts.app-v2');
    }
}
