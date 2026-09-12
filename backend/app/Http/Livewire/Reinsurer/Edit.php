<?php

namespace AlphaDirect\Http\Livewire\Reinsurer;

use Livewire\Component;
use AlphaDirect\Models\Reinsurer;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use DB;
use AlphaDirect\Customer;

class Edit extends Component
{
    public Reinsurer $reinsurer;
    public $editable = true;
    public $colSize = "col-md-3";

    public $rules = [
        'reinsurer.company_name' => 'required',
        'reinsurer.email' => 'required',
        'reinsurer.cellphone' => 'required'
    ];

    public function mount($id){
        $this->reinsurer = Reinsurer::find($id);
    }

    public function submit(){

        $this->validate();
        if ($this->reinsurer->save()){
            session()->flash('success', "Re-Insurer type has been successfully updated.");
            DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Re-Insurer')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Re-Insurer Updated - '.$this->reinsurer->id);
            return Redirect::route('reinsurer');
        }
        session()->flash('error', "Something Went Wrong");
        return Redirect::back();
    }

    public function render()
    {
        return view('v2.livewire.reinsurer.edit')->layout('layouts.app-v2');
    }
}
