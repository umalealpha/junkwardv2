<?php

namespace AlphaDirect\Http\Livewire\Policy\Attachment;

use AlphaDirect\Lookup;
use AlphaDirect\Policy;
use Livewire\Component;

class View extends Component
{
    public Policy $policy;
    public $fileNames;

    public function mount(){
        $this->fileNames = Lookup::where('key', 'file_type')->get(array('key', 'value'));
    }

    public function render()
    {
        return view('v2.livewire.policy.attachment.view');
    }
}
