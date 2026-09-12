<?php

namespace AlphaDirect\Http\Livewire;

use Livewire\Component;

class ExtensionsRepeaterComponent extends Component
{
    public $subExtention;
    public $fields = [];
   
    public $policyCoverage;
    public $policyExtentionDetail = [];
    public function addField2()
    {
       
        $this->fields[] = count($this->fields) + 1;
    }

    public function removeField($index)
    {
        unset($this->fields[$index]);
        $this->fields = array_values($this->fields);
    }
    public function render()
    {
        return view('v2.livewire.extensions-repeater-component',[
            'subExtention' => $this->subExtention,
            'policyCoverage' => $this->policyCoverage,
            'policyExtentionDetail' => $this->policyExtentionDetail,
        ]);
    }
}
