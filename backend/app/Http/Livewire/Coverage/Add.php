<?php

namespace AlphaDirect\Http\Livewire\Coverage;

use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Models\PolicyCoverage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Illuminate\Support\Facades\Log;
use DB;
use AlphaDirect\Customer;

class Add extends Component
{
    Public $coverage;
    Public $d_EffectiveDt;
    Public $d_ExpirationDt;

    protected $rules = [
        'coverage.s_CoverageName' => 'required|unique:tb_cvgpccoverages,s_CoverageName',
        'coverage.s_ScreenName' => 'required|unique:tb_cvgpccoverages,s_ScreenName',
        'd_EffectiveDt' => 'required',
        'd_ExpirationDt' => 'required',
        'coverage.s_CoverageDesc' => 'required',
        'coverage.s_RatingMethod' => 'required',
        'coverage.n_PrintSequence' => 'required',
        'coverage.n_DisplaySequence' => 'required',
        'coverage.n_RateSequence' => 'required',
        'coverage.s_DISPLAYTOUSER' => '',
        'coverage.has_vehicle' => '',
        'coverage.has_member' => '',
        'coverage.has_device' => '',
    ];

    protected $validationAttributes = [
        'coverage.s_CoverageName' => 'coverage',
    ];

    public function getCoverageProperty(){
        return CoverageMaster::select('id as id','specified_name as name')->get()->toArray();
    }

    public function mount(){
        $this->coverage = new CoverageMaster();
    }

    public function submit(){
        $this->validate();

        $this->coverage->d_EffectiveDt = Carbon::createFromFormat(config('constants.date.format'),$this->d_EffectiveDt);
        $this->coverage->d_ExpirationDt = Carbon::createFromFormat(config('constants.date.format'),$this->d_ExpirationDt);

        $this->coverage->s_DISPLAYTOUSER = ($this->coverage->s_DISPLAYTOUSER==true)?1:0;
        // $this->coverage->has_risk_address = ($this->coverage->has_risk_address==true)?1:0;
        $this->coverage->has_vehicle = ($this->coverage->has_vehicle==true)?1:0;
        $this->coverage->has_member = ($this->coverage->has_member==true)?1:0;
        $this->coverage->has_device = ($this->coverage->has_device==true)?1:0;
        // $this->coverage->has_company = ($this->coverage->has_company==true)?1:0;

        $Generate_code = strtoupper($this->coverage->s_ScreenName);
        $this->coverage->s_CoverageCode = str_replace(' ', '', $Generate_code);
        $this->coverage->s_GroupRowType = 'COVERAGE';
        $this->coverage->s_UsageType = 'PARENT';
        $this->coverage->s_CoveragePart = 'PROPERTY';
        $this->coverage->s_CoverageSection = 'MAIN';
        $this->coverage->s_CoverageGroupCode = 'MAIN';
        $this->coverage->s_CoverageGroupName = 'Main';
        $this->coverage->s_ParentCoverageCode = null;
        $this->coverage->s_ParentCoverageID = null;
        $this->coverage->n_ParentCoverageForRate = null;
        $this->coverage->s_DefaultCovgCategoryCode = 'ENDCOVG';
        $this->coverage->s_AutoRenew = 'Y';
        $this->coverage->s_PermitDuplication = 'N';
        $this->coverage->s_CvgOccurrence = 'SINGLE';

        if ($this->coverage->save()){
            DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Coverage')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Coverage Added - '.$this->coverage->id);
            session()->flash('success', "Coverage Created Successfully");
            return Redirect::route('coverage');
        }
        session()->flash('error', "Something Went Wrong");
        return Redirect::back();
    }

    public function render()
    {
            return view('v2.livewire.coverage.add')->layout('layouts.app-v2');
    }
}
