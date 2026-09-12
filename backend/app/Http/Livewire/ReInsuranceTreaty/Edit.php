<?php

namespace AlphaDirect\Http\Livewire\ReInsuranceTreaty;

use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Models\TreatyDetails;
use AlphaDirect\Models\ReinsuranceFormula;
use AlphaDirect\Models\ReinsuranceTreaty;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redirect;
use Livewire\Component;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use DB;
use AlphaDirect\Customer;
class Edit extends Component
{
    Public $treaty;
    Public $treaty_details;
    public $reinsuranceformula;
    Public $saveTreatyCoverage;
    Public $coverage_name;
    public $treaty_Coverages;
    public $formula_attach=[];
    protected $listeners = [
        'formulaInput', 'formulaRemove'
    ];
    public $effective_from;
    public $effective_to;

    protected $rules = [
        // treaty
        'treaty.treaty_name' => 'required|unique:reinsurance_treaty,treaty_name',
        'treaty.treaty_number' => 'required',
        'effective_from' => 'required',
        'effective_to' => 'required',
        'treaty.provisional_commission' => 'required',
        'treaty.proportional_share' => 'required',
        'treaty.cash_loss_advise' => 'required',
        'treaty.event_limit' => 'required',
        'treaty.exclusions' => 'required',
        'treaty.status'=>'required',
    ];

    public function mount($id){
        // treaty
        $this->treaty = ReinsuranceTreaty::find($id);
        $this->formula_attach = TreatyDetails::select('formula_attached')->where('treaty_id',$id)->get()->pluck('formula_attached')->toArray();
        $this->effective_from = (new Carbon($this->treaty->effective_from))->format(config('constants.date.format'));
        $this->effective_to = (new Carbon($this->treaty->effective_to))->format(config('constants.date.format'));
	}

    public function render()
    {
        return view('v2.livewire.re-insurance-treaty.edit')->layout('layouts.app-v2');
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
        if (($key = array_search($value, $this->formula_attach)) !== false) {
            unset($this->formula_attach[$key]);
        }
    }

    public function submit(){
        $this->rules['treaty.treaty_name']= ['required', Rule::unique('reinsurance_treaty','treaty_name')->ignore($this->treaty->id)];
        $this->validate();
        $this->treaty->effective_from =  Carbon::createFromFormat(config('constants.date.format'),$this->effective_from);
        $this->treaty->effective_to =  Carbon::createFromFormat(config('constants.date.format'),$this->effective_to);
        if ($this->treaty->save()){
            $treaty_details = TreatyDetails::where('treaty_id',$this->treaty->id)->delete();
            foreach ($this->formula_attach as $key => $value) {
                $treaty_details = new TreatyDetails();
                $treaty_details->treaty_id = $this->treaty->id;
                $treaty_details->formula_attached = $value;
                $treaty_details->save();
            }
             DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Re-Insurance Treaty')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Re-Insurance Treaty Updated - '.$this->treaty->id);
            session()->flash('success', "Treaty Updated Successfully");
            return Redirect::route('reinsuranceTreaty');
        }
        session()->flash('error', "Something Went Wrong");
        return Redirect::back();
    }
}
