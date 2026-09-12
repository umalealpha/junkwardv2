<?php

namespace AlphaDirect\Http\Livewire\ReInsuranceFormula;

use AlphaDirect\Lookup;
use AlphaDirect\Models\ReinsuranceFormula;
use AlphaDirect\Models\ReinsuranceFormulaDetails;
use AlphaDirect\Models\ReinsuranceGroup;
use AlphaDirect\Models\ReinsuranceType;
use Illuminate\Support\Facades\Redirect;
use Livewire\Component;
use AlphaDirect\Product;
use AlphaDirect\Models\MotorType;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use DB;
use AlphaDirect\Customer;
class Edit extends Component
{
    public ReinsuranceFormula $reinsurance_formula;
    public ReinsuranceFormulaDetails $reinsurance_formula_details;
    public $editable = true;
    public $allGroups=[];
    public $allMotorType;
    public $date_from;
    public $date_to;

    protected $rules = [
        'reinsurance_formula.formula_name' => 'required|unique:reinsurance_formula,formula_name',
        'reinsurance_formula.formula_code' => 'required',
        'reinsurance_formula.product_id' => 'required',
        'reinsurance_formula.reinsurance_type_id' => 'required',
        'reinsurance_formula.type_id' => 'required',
        'reinsurance_formula.status' => '',
        'reinsurance_formula.s_FormulaType' => 'required',
        'reinsurance_formula_details.group_id' => '',
        'reinsurance_formula_details.vehicle_type' => '',
        'reinsurance_formula_details.operator' => 'nullable',
        'reinsurance_formula_details.si_allocation' => 'nullable',
        'reinsurance_formula_details.percentage' => 'nullable',
        'date_from' => 'required',
        'date_to' => 'required',
    ];

    public function mount($id){
        $this->reinsurance_formula = ReinsuranceFormula::find($id);
        $this->reinsurance_formula->status = ($this->reinsurance_formula->status==1)?true:false;
        if(!empty($this->reinsurance_formula->id)){
            $this->reinsurance_formula_details = ReinsuranceFormulaDetails::find($this->reinsurance_formula->id);
        }else{
            $this->reinsurance_formula_details = new ReinsuranceFormulaDetails();
        }

        $this->allGroups = ReinsuranceGroup::where('product_id', $this->reinsurance_formula->product_id)->where('status',1)->get()->keyBy('id')->map(function($d){
            return [
                'id'=>$d->id,
                'name'=>$d->group_name
            ];
        });

        $this->allMotorType = MotorType::where('product_id', $this->reinsurance_formula->product_id)->get()->keyBy('id')->map(function($d){
            return [
                'id'=>$d->id,
                'name'=>$d->motor_name
            ];
        });

        $this->date_from = (new Carbon($this->reinsurance_formula_details->date_from))->format(config('constants.date.format'));
        $this->date_to = (new Carbon($this->reinsurance_formula_details->date_to))->format(config('constants.date.format'));
	}

    public function render()
    {
        return view('v2.livewire.re-insurance-formula.add')->layout('layouts.app-v2');
    }

    public function getProducts(){
		return \AlphaDirect\Product::get()->keyBy('id')->map(function($d){
			return [
				'id'=>$d->id,
				'name'=>$d->name
			];
		});
	}

    public function updatedReinsuranceFormulaProductId($value,$key)
    {
        if ($key=="product_id") {
            $groupdata= ReinsuranceGroup::where('status',1)->where('product_id',$value)->get()->keyBy('id')->map(function($d){
                    return [
                        'id'=>$d->id,
                        'name'=>$d->group_name
                    ];
                });
            $this->dispatchBrowserEvent('dropdown-changed',[
                'key'=>'group_id',
                'data'=>$groupdata,
                'selected_id' => $this->reinsurance_formula_details->group_id
            ]);
        }

        if ($key=="product_id") {
            $motordata= MotorType::where('product_id',$value)->get()->keyBy('id')->map(function($d){
                    return [
                        'id'=>$d->id,
                        'name'=>$d->motor_name
                    ];
                });
            $this->dispatchBrowserEvent('dropdown-changed',[
                'key'=>'vehicle_type',
                'data'=>$motordata,
                'selected_id' => $this->reinsurance_formula_details->vehicle_type
            ]);
        }
    }

    public function getReinsuranceType(){
		return ReinsuranceType::where('status',1)->get()->keyBy('id')
        ->map(function($d){
            return [
                'id'=>$d->id,
                'name'=>$d->type_name
            ];
        });
	}

    public function getTypes(){
		return Lookup::where('key','reinsurance_formula_key')->get()->keyBy('id')
        ->map(function($d){
            return [
                'id'=>$d->id,
                'name'=>$d->value
            ];
        });
	}



    public function submit(){
        $this->rules['reinsurance_formula.formula_name']= ['required', Rule::unique('reinsurance_formula','formula_name')->ignore($this->reinsurance_formula->id)];
        $this->validate();

        $this->reinsurance_formula->status = ($this->reinsurance_formula->status==true)?1:null;
        if ($this->reinsurance_formula->save()){
            $this->reinsurance_formula_details->date_from =  Carbon::createFromFormat(config('constants.date.format'),$this->date_from);
            $this->reinsurance_formula_details->date_to =  Carbon::createFromFormat(config('constants.date.format'),$this->date_to);
            $this->reinsurance_formula_details->formula_id = $this->reinsurance_formula->id;
            $this->reinsurance_formula_details->save();
            DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Re-Insurance Formula Updated')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Re-Insurance Formula Updated - '.$this->reinsurance_formula->id);
            session()->flash('success', "Formula Updated Successfully");
            return Redirect::route('reinsuranceFormula');
        }
        session()->flash('error', "Something Went Wrong");
        return Redirect::back();
    }
}
