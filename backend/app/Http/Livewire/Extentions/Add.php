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
class Add extends Component
{
    public $coverage,$subcoverage,$s_CoverageName,$rate,$s_ScreenName,$coverageId,$n_DisplaySequence,$extention_type,$s_ExtensionsGroupName,$type;
	public $d_EffectiveDt,$s_CoverageDesc,$s_RatingMethod,$s_DISPLAYTOUSER,$d_ExpirationDt;
    public $modalId = false;
    public $inputs = [];
    public $i = 0;
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
    	's_CoverageDesc.0'		=>'Describtion',
    	's_RatingMethod.0'		=>'Rating Method',
    	's_DISPLAYTOUSER.0'		=>'Display To User',
        'n_DisplaySequence.0'	=>'Display To Sequence',
		's_CoverageName.*'		=>'Coverage Name',
    	's_ScreenName.*'		=> 'Screen name',
        'rate.*'		        => 'Rate',
    	'd_EffectiveDt.*'		=>'Effective Date',
    	'd_ExpirationDt.*'		=>'Expire Date',
    	's_CoverageDesc.*'		=>'Describtion',
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
        $this->extention_type = 'NOEDIT';
        $this->s_ExtensionsGroupName = 'Main';
        $this->type = 'Extention';
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
            $extention = new Extention();
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
            $extention->s_DISPLAYTOUSER = ($this->s_DISPLAYTOUSER[$key]==true)?1:0;
			$extention->save();

            if($extention->extention_type == 'RADIO'){
                $valid_option = new TbValidOptions();
                $valid_option->s_OptionType = 'EXT_LIMIT';
                $valid_option->n_SourceOneFK = $extention->id;
                $valid_option->s_SourceOneType = $extention->type;
                $valid_option->n_SourceTwoFK = 2;
                $valid_option->s_SourceOneDesc = 'Extention Primary Key';
                $valid_option->s_SourceTwoDesc = 'Extention Primary Key';
                $valid_option->d_CreatedDate = Carbon::now();
                $valid_option->save();

                $valid_option = new TbValidOptions();
                $valid_option->s_OptionType = 'EXT_LIMIT';
                $valid_option->n_SourceOneFK = $extention->id;
                $valid_option->s_SourceOneType = $extention->type;
                $valid_option->n_SourceTwoFK = 23;
                $valid_option->s_SourceOneDesc = 'Extention Primary Key';
                $valid_option->s_SourceTwoDesc = 'Extention Limit Primary Key';
                $valid_option->d_CreatedDate = Carbon::now();
                $valid_option->save();

            }elseif($extention->extention_type == 'DROPDOWN'){
                $valid_option = new TbValidOptions();
                $valid_option->s_OptionType = 'EXT_LIMIT';
                $valid_option->n_SourceOneFK = $extention->id;
                $valid_option->s_SourceOneType = $extention->type;
                $valid_option->n_SourceTwoFK = 7;
                $valid_option->s_SourceOneDesc = 'Extention Primary Key';
                $valid_option->s_SourceTwoDesc = 'Extention Limit Primary Key';
                $valid_option->d_CreatedDate = Carbon::now();
                $valid_option->save();

                $valid_option = new TbValidOptions();
                $valid_option->s_OptionType = 'EXT_LIMIT';
                $valid_option->n_SourceOneFK = $extention->id;
                $valid_option->s_SourceOneType = $extention->type;
                $valid_option->n_SourceTwoFK = 8;
                $valid_option->s_SourceOneDesc = 'Extention Primary Key';
                $valid_option->s_SourceTwoDesc = 'Extention Limit Primary Key';
                $valid_option->d_CreatedDate = Carbon::now();
                $valid_option->save();

                $valid_option = new TbValidOptions();
                $valid_option->s_OptionType = 'EXT_LIMIT';
                $valid_option->n_SourceOneFK = $extention->id;
                $valid_option->s_SourceOneType = $extention->type;
                $valid_option->n_SourceTwoFK = 9;
                $valid_option->s_SourceOneDesc = 'Extention Primary Key';
                $valid_option->s_SourceTwoDesc = 'Extention Limit Primary Key';
                $valid_option->d_CreatedDate = Carbon::now();
                $valid_option->save();

                $valid_option = new TbValidOptions();
                $valid_option->s_OptionType = 'EXT_LIMIT';
                $valid_option->n_SourceOneFK = $extention->id;
                $valid_option->s_SourceOneType = $extention->type;
                $valid_option->n_SourceTwoFK = 10;
                $valid_option->s_SourceOneDesc = 'Extention Primary Key';
                $valid_option->s_SourceTwoDesc = 'Extention Limit Primary Key';
                $valid_option->d_CreatedDate = Carbon::now();
                $valid_option->save();

                $valid_option = new TbValidOptions();
                $valid_option->s_OptionType = 'EXT_LIMIT';
                $valid_option->n_SourceOneFK = $extention->id;
                $valid_option->s_SourceOneType = $extention->type;
                $valid_option->n_SourceTwoFK = 11;
                $valid_option->s_SourceOneDesc = 'Extention Primary Key';
                $valid_option->s_SourceTwoDesc = 'Extention Limit Primary Key';
                $valid_option->d_CreatedDate = Carbon::now();
                $valid_option->save();

            }elseif($extention->extention_type == 'NUMBER'){
                $valid_option = new TbValidOptions();
                $valid_option->s_OptionType = 'EXT_LIMIT';
                $valid_option->n_SourceOneFK = $extention->id;
                $valid_option->s_SourceOneType = $extention->type;
                $valid_option->n_SourceTwoFK = 1;
                $valid_option->s_SourceOneDesc = 'Extention Primary Key';
                $valid_option->s_SourceTwoDesc = 'Extention Limit Primary Key';
                $valid_option->d_CreatedDate = Carbon::now();
                $valid_option->save();
            }elseif($extention->extention_type == 'NOEDIT'){
                $valid_option = new TbValidOptions();
                $valid_option->s_OptionType = 'EXT_LIMIT';
                $valid_option->n_SourceOneFK = $extention->id;
                $valid_option->s_SourceOneType = $extention->type;
                $valid_option->n_SourceTwoFK = 6;
                $valid_option->s_SourceOneDesc = 'Extention Primary Key';
                $valid_option->s_SourceTwoDesc = 'Extention Limit Primary Key';
                $valid_option->d_CreatedDate = Carbon::now();
                $valid_option->save();
            }
		}
		$this->inputs = [];
        DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Extention')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Extention Added - '.$this->s_CoverageName[0]);
        $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Extention Added Successfully!']);
        $this->mount();
        if ($this->modalId){
            $this->emitUp('refreshParent');
        }
        return ;

		// session()->flash('success','Extention Added Successfully.');
		// return redirect()->route('extentions');
    }

    public function render()
    {
        return view('v2.livewire.extentions.add')->layout('layouts.app-v2');
    }
}
