<?php

namespace AlphaDirect\Http\Livewire\ReInsuranceTreaty;

use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Models\TreatyDetails;
use AlphaDirect\Models\ReinsuranceFormula;
use AlphaDirect\Models\ReinsuranceTreaty;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redirect;
use Livewire\Component;
use Illuminate\Support\Facades\Log;
use DB;
use AlphaDirect\Customer;

class Add extends Component
{
    // public $coverages;
    Public $treaty;
    Public $treaty_details;
    public $reinsuranceformula;
    Public $saveTreatyCoverage;
    Public $coverage_name;
    public $treaty_Coverages;
    public $effective_from;
    public $effective_to;

    public $formula_attach=[];
    protected $listeners = [
        'formulaInput', 'formulaRemove'
    ];

    public $rules = [
        // treaty
        'treaty.treaty_name' => 'required|unique:reinsurance_treaty,treaty_name',
        'treaty.treaty_number' => 'required|unique:reinsurance_treaty,treaty_number',
        'effective_from' => 'required',
        'effective_to' => 'required',
        'treaty.provisional_commission' => 'required',
        'treaty.proportional_share' => 'required',
        'treaty.cash_loss_advise' => 'required',
        'treaty.event_limit' => 'required',
        'treaty.exclusions' => 'required',
        'treaty.status'=>'required',
    ];

    public function mount(){
        $this->treaty = new ReinsuranceTreaty();
	}

    public function render()
    {
        return view('v2.livewire.re-insurance-treaty.add')->layout('layouts.app-v2');
    }

    public function getCoveragesMasterProperty(){
		return CoverageMaster::where('s_CoverageGroupCode','=','MAIN')
		->where('s_UsageType','=','PARENT')
		->get()->keyBy('id')
        ->map(function($d){
            return [
                'id'=>$d->id,
                'name'=>$d->s_CoverageName
            ];
        });
	}

    public function getReinsuranceFormula(){
		return ReinsuranceFormula::get()->keyBy('id')
        ->map(function($d){
            return [
                'id'=>$d->id,
                'name'=>$d->formula_name
            ];
        });
	}

    public function formulaInput($value)
    {
        if(!is_null($value))
            $this->formula_attach[] = $value;
    }

    public function formulaRemove($value)
    {
        // $index = array_search($value, $this->formula_attach);
        // unset($this->formula_attach[$index]);
        if (($key = array_search($value, $this->formula_attach)) !== false) {
            unset($this->formula_attach[$key]);
        }
        // dd($this->formula_attach);
    }

    public function submit(){
        $this->validate();
        $this->treaty->effective_from =  Carbon::createFromFormat(config('constants.date.format'),$this->effective_from);
        $this->treaty->effective_to =  Carbon::createFromFormat(config('constants.date.format'),$this->effective_to);

        if ($this->treaty->save()){
            foreach ($this->formula_attach as $key => $value) {
                $this->treaty_details = new TreatyDetails();
                $this->treaty_details->treaty_id = $this->treaty->id;
                $this->treaty_details->formula_attached = $value;
                $this->treaty_details->save();
            }
            DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Re-Insurance Treaty')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Re-Insurance Treaty Added - '.$this->treaty->id);
            session()->flash('success', "Treaty Created Successfully");
            return Redirect::route('reinsuranceTreaty');
        }
        session()->flash('error', "Something Went Wrong");
        return Redirect::back();
    }
}


