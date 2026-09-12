<?php

namespace AlphaDirect\Http\Livewire;

use Livewire\Component;

class DescriptionofcoverRepeaterComponent extends Component
{
    public $fields = [];
    public $coverage;
    public $policyCoverage;
    public $policyCoverageDetail = [];

    public function addField1()
    {
        $this->fields[] = count($this->fields) + 1;
       
    }

    public function removeField($k1)
    {
        unset($this->fields[$k1]);
        $this->fields = array_values($this->fields);
    }
    
    public function render()
    {
       return view('v2.livewire.descriptionofcover-repeater-component', [
            'coverage' => $this->coverage,
            'policyCoverage' => $this->policyCoverage,
            'policyCoverageDetail' => $this->policyCoverageDetail,
        ]);

    }
    
}
