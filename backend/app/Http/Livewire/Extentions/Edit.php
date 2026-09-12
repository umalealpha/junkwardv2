<?php

namespace AlphaDirect\Http\Livewire\Extentions;

use Livewire\Component;

use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Models\Extention;
use AlphaDirect\Models\TbValidOptions;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redirect;

use Illuminate\Support\Facades\Log;
use DB;
use AlphaDirect\Customer;
class Edit extends Component
{
    public $coverage,$subcoverage,$s_CoverageName,$rate,$s_ScreenName,$coverageId,$n_DisplaySequence,$extention_type,$s_ExtensionsGroupName,$type;
	public $d_EffectiveDt,$s_CoverageDesc,$s_RatingMethod,$s_DISPLAYTOUSER,$d_ExpirationDt;
   
    public $inputs = [];
    public $i = 0;
	public $edit=false;
    protected $rules = [
		'coverage' => 'required',
        'subcoverage' => '',
        'extention_type' => 'required',
        's_ExtensionsGroupName' => 'required',
        'type' => 'required',
		's_CoverageName.0'=>'required',
    	's_ScreenName.0'=>'required',
        'rate.0'=>'required|numeric',
    	'd_EffectiveDt.0'=>'required',
    	'd_ExpirationDt.0'=>'required',
    	's_CoverageDesc.0'=>'required',
    	's_RatingMethod.0'=>'required',
    	's_DISPLAYTOUSER.0'=>'',
        'n_DisplaySequence.0'=>'required',
		's_CoverageName.*'=>'required',
    	's_ScreenName.*'=>'required',
        'rate.*'=>'required|numeric',
    	'd_EffectiveDt.*'=>'required',
    	'd_ExpirationDt.*'=>'required',
    	's_CoverageDesc.*'=>'required',
    	's_RatingMethod.*'=>'required',
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
    	's_DISPLAYTOUSER.0'		=>'Display To User',
        'n_DisplaySequence.0'	=>'Display To Sequence',
		's_CoverageName.*'		=>'Coverage Name',
    	's_ScreenName.*'		=> 'Screen name',
        'rate.*'		=> 'Rate',
    	'd_EffectiveDt.*'		=>'Effective Date',
    	'd_ExpirationDt.*'		=>'Expire Date',
    	's_CoverageDesc.*'		=>'Description',
    	's_RatingMethod.*'		=>'Rating Method',
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

    public function getSubCoveragesProperty(){
        return CoverageMaster::where('s_CoverageGroupCode','=','SUB')
                ->where('s_UsageType','=','CHILD')
                // ->where('s_SubCoverageMainName','=','Heading')
                ->orderBy('s_CoverageName','asc')
                ->get()->keyBy('id')
                ->map(function($d){
                    return [
                        'id'=>$d->id,
                        'name'=>$d->s_ParentCoverageCode. ' - ' .$d->s_CoverageName. ' (' . $d->n_DisplaySequence . ')'
                    ];
                });
	}

    public function mount(){
        $this->s_DISPLAYTOUSER[0] = false;
		if(request()->route('id')){

			$data = Extention::where('s_CoverageCode','=',\Crypt::decrypt(request()->route('id')))
									->first();

			$this->coverage = $data->s_ParentCoverageID ?? "";
            $this->subcoverage = $data->s_SubCoverageID ?? "";
            if(!isset($data->extention_type)){
                $this->extention_type = 'NOEDIT';
                $this->type = 'Extention';
            }else{
                $this->extention_type = $data->extention_type ?? "";
                $this->type = $data->type ?? "";
            }

            if($data->s_ExtensionsGroupName == 'Main'){
                $this->s_ExtensionsGroupName = 'Main';
            }else{
                $this->s_ExtensionsGroupName = 'Heading';
            }

			$this->edit=true;
            if ($data && $data->s_CoverageCode != null) {
                $mdata = Extention::where('s_CoverageCode','=',$data->s_CoverageCode)
                // ->where('s_UsageType','=','CHILD')
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
	}

    public function submit(){
		$this->validate();
		$d = CoverageMaster::find($this->coverage);
		foreach ($this->s_CoverageName as $key => $value){
			if((isset($this->coverageId[$key])) && ((isset($this->s_CoverageName[$key])) )){
                if(!empty($this->coverageId[$key])){
					$extention = Extention::find($this->coverageId[$key]);
				}
			}else{
				$extention = new Extention();
			}
            $extention->extention_type = $this->extention_type;
            $extention->type = $this->type;
            $extention->s_ExtensionsGroupName = $this->s_ExtensionsGroupName;
			$extention->s_CoverageCode = strtoupper($this->s_ScreenName[$key]);
            $extention->n_DisplaySequence = $this->n_DisplaySequence[$key];
            $extention->s_ParentCoverageCode = str_replace(' ', '', $d->s_CoverageCode);
            $extention->s_ParentCoverageID = $this->coverage;
            $extention->s_SubCoverageID = $this->subcoverage;
            $extention->s_CoverageName = $this->s_CoverageName[$key];
            $extention->s_ScreenName = $this->s_ScreenName[$key];
            $extention->rate = $this->rate[$key];
            $extention->d_EffectiveDt = Carbon::createFromFormat(config('constants.date.format'),$this->d_EffectiveDt[$key]);
            $extention->d_ExpirationDt = Carbon::createFromFormat(config('constants.date.format'),$this->d_ExpirationDt[$key]);
            $extention->s_CoverageDesc = $this->s_CoverageDesc[$key];
            $extention->s_RatingMethod = $this->s_RatingMethod[$key];
            $extention->s_DISPLAYTOUSER =($this->s_DISPLAYTOUSER[$key]==true)?1:0;
			$extention->save();

            // if(isset($extention->id)){
            //     $valid_option = TbValidOptions::where('extention_id',$extention->id)->get();
            // }
		}
		$this->inputs = [];
        DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Extention')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Extention Updated - '.$this->s_CoverageName[0]);
		session()->flash('success','Extention Updated Successfully.');
		return redirect()->route('extentions');
    }

    public function render()
    {
        return view('v2.livewire.extentions.edit')->layout('layouts.app-v2');
    }
}
