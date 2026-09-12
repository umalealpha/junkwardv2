<?php

namespace AlphaDirect\Http\Livewire\Coverage;

use AlphaDirect\Models\CoverageMaster;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Redirect;
use Livewire\Component;
use Illuminate\Support\Facades\Log;
use DB;
use AlphaDirect\Customer;

class Edit extends Component
{
    Public $coverage;
    Public $d_EffectiveDt;
    Public $d_ExpirationDt;

    protected $rules = [
        'coverage.s_CoverageName' => 'required',
        'coverage.s_ScreenName' => 'required|unique:tb_cvgpccoverages,s_ScreenName',
        'd_EffectiveDt' => 'required',
        'd_ExpirationDt' => 'required',
        'coverage.s_CoverageDesc' => 'required',
        'coverage.s_RatingMethod' => 'required',
        'coverage.n_PrintSequence' => 'required',
        'coverage.n_DisplaySequence' => 'required',
        'coverage.n_RateSequence' => 'required',
        'coverage.s_DISPLAYTOUSER' => '',
        // 'coverage.has_risk_address' => '',
        'coverage.has_vehicle' => '',
        'coverage.has_member' => '',
        'coverage.has_device' => '',
        // 'coverage.has_company' => ''
    ];

    protected $validationAttributes = [
        'coverage.s_CoverageName' => 'coverage',
    ];

    public function getCoverageProperty(){
        return CoverageMaster::select('id as id','specified_name as name')->get()->toArray();
    }

    public function mount($id){
        $this->coverage = CoverageMaster::find($id);

        $this->d_EffectiveDt = (new Carbon($this->coverage->d_EffectiveDt))->format(config('constants.date.format'));
        $this->d_ExpirationDt = (new Carbon($this->coverage->d_ExpirationDt))->format(config('constants.date.format'));
        $this->coverage->s_DISPLAYTOUSER = ($this->coverage->s_DISPLAYTOUSER==1)?true:false;
        // $this->coverage->has_risk_address = ($this->coverage->has_risk_address==1)?true:false;
        $this->coverage->has_vehicle = ($this->coverage->has_vehicle==1)?true:false;
        $this->coverage->has_member = ($this->coverage->has_member==1)?true:false;
        $this->coverage->has_device = ($this->coverage->has_device==1)?true:false;
        // $this->coverage->has_company = ($this->coverage->has_company==1)?true:false;
    }

    public function submit(){
        // 'coverage.s_ScreenName' => 'required|unique:tb_cvgpccoverages,s_ScreenName'. $this->coverage->id,
        // $this->rules['coverage.s_CoverageName']= ['required', Rule::unique('tb_cvgpccoverages','s_CoverageName')->ignore($this->coverage->id)];
        $this->rules['coverage.s_ScreenName']= ['required', Rule::unique('tb_cvgpccoverages','s_ScreenName')->ignore($this->coverage->id)];
        $this->validate();
        $this->coverage->d_EffectiveDt = Carbon::createFromFormat(config('constants.date.format'),$this->d_EffectiveDt);
        $this->coverage->d_ExpirationDt = Carbon::createFromFormat(config('constants.date.format'),$this->d_ExpirationDt);

        $this->coverage->s_DISPLAYTOUSER = ($this->coverage->s_DISPLAYTOUSER==true)?1:0;
        // $this->coverage->has_risk_address = ($this->coverage->has_risk_address==true)?1:0;
        $this->coverage->has_vehicle = ($this->coverage->has_vehicle==true)?1:0;
        $this->coverage->has_member = ($this->coverage->has_member==true)?1:0;
        $this->coverage->has_device = ($this->coverage->has_device==true)?1:0;
        // $this->coverage->has_company = ($this->coverage->has_company==true)?1:0;

        if ($this->coverage->save()){
             DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Coverage')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Coverage Updated - '.$this->coverage->id);
            session()->flash('success', "Coverage Updated Successfully");
            return Redirect::route('coverage');
        }
        session()->flash('error', "Something Went Wrong");
        return Redirect::back();
    }

    public function render()
    {
        return view('v2.livewire.coverage.edit')->layout('layouts.app-v2');
    }
}
