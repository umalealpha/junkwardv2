<?php

namespace AlphaDirect\Http\Livewire\Policy\AddOfflinePayment;

use Livewire\Component;

class View extends Component
{

    public function mount(){

    }

    public function render()
    {
        return view('v2.livewire.policy.add-offline-payment.view')->layout('layouts.app-v2');
    }
}
