<?php

namespace AlphaDirect\Http\Livewire;

use Livewire\Component;

class ExcessRepeaterComponent extends Component
{
    public $fields = [];
    public $coverage;
    public $policyCoverage;
    public $policyExtentionDetail = [];
    public function addField()
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
      
        return view('v2.livewire.excess-repeater-component', [
            'coverage' => $this->coverage,
            'policyCoverage' => $this->policyCoverage,
            'policyExtentionDetail' => $this->policyExtentionDetail,
        ]);

    }
}
