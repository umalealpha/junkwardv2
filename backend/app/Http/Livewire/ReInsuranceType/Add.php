<?php

namespace AlphaDirect\Http\Livewire\ReInsuranceType;
use AlphaDirect\Models\ReinsuranceType;
use Illuminate\Support\Facades\Redirect;
use Livewire\Component;
use Illuminate\Support\Facades\Log;
use DB;
use AlphaDirect\Customer;

class Add extends Component
{

    public ReInsuranceType $reinsurancetype;
    public $editable = true;
    public $colSize = "col-md-3";

    public $rules = [
        'reinsurancetype.type_code' => 'required|unique:reinsurance_type,type_code',
        'reinsurancetype.type_name' => 'required|unique:reinsurance_type,type_name',
        'reinsurancetype.type_description' => 'required',
        'reinsurancetype.status' => ''
    ];

    public function mount(){
        $this->reinsurancetype = new ReInsuranceType();
        $this->reinsurancetype->status = true;
    }

    public function submit(){
        $this->validate();
        $this->reinsurancetype->status = ($this->reinsurancetype->status==true)?1:null;
        if ($this->reinsurancetype->save()){  
            DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Re-Insurance Type')
            ->performedOn($customer)
            ->causedBy(Customer::where('id', auth()->user()->id)->first())
            ->log('Re-Insurance Type Added - '.$this->reinsurancetype->type_code);
                
            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Re-Insurance type has been successfully created.']);
            $this->mount();
            return Redirect::route('reinsurance-type');
        }
        $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Something Went Wrong']);
    }

    public function render()
    {
        return view('v2.livewire.re-insurance-type.add')->layout('layouts.app-v2');
    }
}
