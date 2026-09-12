<?php

namespace AlphaDirect\Http\Livewire\ReInsuranceType;
use AlphaDirect\Models\ReinsuranceType;
use Livewire\Component;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use DB;
use AlphaDirect\Customer;
class Edit extends Component
{
    public ReInsuranceType $reinsurancetype;
    public $editable = true;
    public $colSize = "col-md-3";

    protected $rules = [
        'reinsurancetype.type_code' => 'required|unique:reinsurance_type,type_code,',
        'reinsurancetype.type_name' => 'required',
        'reinsurancetype.type_description' => 'required',
        'reinsurancetype.status' => ''
    ];

    public function mount($id){
        $this->reinsurancetype = ReInsuranceType::find($id);
        $this->reinsurancetype->status = ($this->reinsurancetype->status==1)?true:false;
    }

    public function submit(){
        $this->rules['reinsurancetype.type_code']= ['required', Rule::unique('reinsurance_type','type_code')->ignore($this->reinsurancetype->id)];

        $this->validate();
        $this->reinsurancetype->status = ($this->reinsurancetype->status==true)?1:null;
        if ($this->reinsurancetype->save()){
                DB::commit();
                $customer = Customer::where('id', auth()->user()->id)->first();
                activity('Re-Insurance Type')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Re-Insurance Type Updated - '.$this->reinsurancetype->type_code);
            session()->flash('success', "Re-Insurance type has been successfully updated.");
            return Redirect::route('reinsurance-type');
        }
        session()->flash('error', "Something Went Wrong");
        return Redirect::back();
    }

    public function render()
    {
        return view('v2.livewire.re-insurance-type.edit')->layout('layouts.app-v2');
    }
}
