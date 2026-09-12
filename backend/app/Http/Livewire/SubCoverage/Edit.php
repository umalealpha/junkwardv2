<?php

namespace AlphaDirect\Http\Livewire\SubCoverage;

use Livewire\Component;
use AlphaDirect\Models\CoverageMaster;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use DB;
use AlphaDirect\Customer;

class Edit extends Component
{
    public $coverage,$s_CoverageName,$rate,$s_ScreenName,$coverageId,$n_DisplaySequence;
	public $d_EffectiveDt,$s_CoverageDesc,$s_RatingMethod,$s_CoverageGroupName,$s_SubCoverageMainName,$s_DISPLAYTOUSER,$d_ExpirationDt;
    public $subcoverage = [];
    public $inputs = [];
    public $i = 0;
	public $edit=false;
    // protected $listeners = ['refreshComponent' => '$refresh','deleteRecord'];
    // protected $listeners = ['refreshParent'  => 'refreshParent'];
    // protected $listeners = ['refreshParent'];
    // public function refreshParent()
    // {
    //     $this->s_CoverageName;
    // }


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
    	's_CoverageDesc.0'		=>'Description',
    	's_RatingMethod.0'		=>'Rating Method',
    	's_CoverageGroupName.0'	=>'Coverage Group Name',
        's_SubCoverageMainName.0'	=>'Group Name',
    	's_DISPLAYTOUSER.0'		=>'Display To User',
        'n_DisplaySequence.0'	=>'Display To Sequence',
		's_CoverageName.*'		=>'Coverage Name',
    	's_ScreenName.*'		=> 'Screen name',
        'rate.*'		=> 'Rate',
    	'd_EffectiveDt.*'		=>'Effective Date',
    	'd_ExpirationDt.*'		=>'Expire Date',
    	's_CoverageDesc.*'		=>'Description',
    	's_RatingMethod.*'		=>'Rating Method',
    	's_CoverageGroupName.*'	=>'Coverage Group Name',
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
		if(request()->route('id')){

			$data = CoverageMaster::where('s_CoverageGroupCode','=','MAIN')
                                    ->orderBy('id','asc')
									->where('s_UsageType','=','PARENT')
									->where('s_CoverageCode','=',\Crypt::decrypt(request()->route('id')))
									->first();
			$this->coverage=$data->id ?? "";
           //  $this->coverage=227;
			$this->edit=true;
            if ($data && $data->s_CoverageCode != null) {
                $mdata= CoverageMaster::where('s_ParentCoverageCode','=',$data->s_CoverageCode)
                ->orderBy('id','asc')
                ->where('s_UsageType','=','CHILD')
                ->get();
                $i=0;
                foreach($mdata as $k=>$v){
                $this->coverageId[$i] = $v->id;
                $this->s_CoverageName[$i] = $v->s_CoverageName;
                $this->s_ScreenName[$i] = $v->s_ScreenName;
                $this->n_DisplaySequence[$i] = $v->n_DisplaySequence;
                $this->rate[$i] = $v->rate;

                $this->d_EffectiveDt[$i] = (new Carbon($v->d_EffectiveDt))->format(config('constants.date.format'));
                $this->d_ExpirationDt[$i]  = (new Carbon($v->d_ExpirationDt))->format(config('constants.date.format'));

                $this->s_CoverageDesc[$i] = $v->s_CoverageDesc;
                $this->s_RatingMethod[$i] = $v->s_RatingMethod;
                $this->s_CoverageGroupName[$i] = $v->s_CoverageGroupName;
                $this->s_SubCoverageMainName[$i] = $v->s_SubCoverageMainName;
                $this->s_DISPLAYTOUSER[$i] = ($v->s_DISPLAYTOUSER==1)?true:false;
                array_push($this->inputs ,$i);
                ++$i;
                }
                $this->i=--$i;
            }
		}
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
		$this->coverageId[$i] = 0;
		unset($this->inputs[$i]);
        $this->deleteRecord();
	}

    public function deleteRecord(){
        $d = CoverageMaster::find($this->coverage);
		$idsArray = CoverageMaster::where('s_ParentCoverageCode','=',$d->s_CoverageCode)
			                        ->where('s_UsageType','=','CHILD')
								    ->pluck('id')->toArray();

        if($idsArray != null){
            $result= array_diff($idsArray,$this->coverageId);
            DB::table('tb_cvgpccoverages')->whereIn('id',array_values($result))->delete();
        }
    }

    public function submit(){
        $this->validate();
        //dd($this);
        $d = CoverageMaster::find($this->coverage);

	    foreach ($this->inputs as $key => $value){
            //dd($key);
            if(!empty($this->coverageId[$key])){
				$coverage_master = CoverageMaster::find($this->coverageId[$key]);
			}else{
                //dd(2);
				$coverage_master = new CoverageMaster();
			}
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
            $coverage_master->d_EffectiveDt = Carbon::createFromFormat(config('constants.date.format'),$this->d_EffectiveDt[$key]);
            $coverage_master->d_ExpirationDt = Carbon::createFromFormat(config('constants.date.format'),$this->d_ExpirationDt[$key]);
            $coverage_master->s_CoverageDesc = $this->s_CoverageDesc[$key];
            $coverage_master->s_RatingMethod = $this->s_RatingMethod[$key];
            $coverage_master->s_CoverageGroupName = $this->s_CoverageGroupName[$key];
            $coverage_master->s_SubCoverageMainName = $this->s_SubCoverageMainName[$key];
            $coverage_master->s_DISPLAYTOUSER = ($this->s_DISPLAYTOUSER[$key]==true)?1:0;
			$coverage_master->save();
		}

        if ($coverage_master->save()){
            DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Sub Coverage')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Sub Coverage Updated - '.$this->coverage);
            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Sub Coverage Updated Successfully!']);
            return;
        }
        $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Something Went Wrong']);

		// session()->flash('success','Sub Coverage Updated Successfully.');
		// return redirect()->route('subcoverage');
    }

    public function render()
    {
        return view('v2.livewire.sub-coverage.edit')->layout('layouts.app-v2');
    }
}
