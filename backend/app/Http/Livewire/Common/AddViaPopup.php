<?php

namespace AlphaDirect\Http\Livewire\Common;

use Livewire\Component;

class AddViaPopup extends Component
{
    public $title;
    public $componentName;
    public $modalId;

    protected $listeners = ['refreshParent'  => 'refreshParent'];

    public function refreshParent(){
        $this->dispatchBrowserEvent('closeModal');
        $this->emitUp('refreshParent');
    }

    public function render()
    {
        return view('v2.livewire.common.add-via-popup');
    }
}
