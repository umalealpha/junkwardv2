<?php

namespace AlphaDirect\Http\Livewire\Reinsurer;

use Livewire\Component;
use AlphaDirect\Models\Reinsurer;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use DB;
use AlphaDirect\Customer;

class Add extends Component
{
    public $editable = true;
    public $colSize = "col-md-3";

    public $rules = [
        'reinsurer.company_name' => 'required',
        'reinsurer.email' => 'required',
        'reinsurer.cellphone' => 'required'
    ];

    public function mount(){
        $this->reinsurer = new Reinsurer();
    }

    public function submit(){
        $this->validate();
        if ($this->reinsurer->save()){
            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Re-Insurer has been successfully created.']);
            $this->mount();
            
            DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Re-Insurer')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Re-Insurer Added - '.$this->reinsurer->id);
            return Redirect::route('reinsurer');
        }
        $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Something Went Wrong']);
    }

    public function render()
    {
        return view('v2.livewire.reinsurer.add')->layout('layouts.app-v2');
    }
}
