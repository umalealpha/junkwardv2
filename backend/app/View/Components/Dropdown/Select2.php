<?php

namespace AlphaDirect\View\Components\Dropdown;

use Illuminate\View\Component;

class Select2 extends Component
{
    public $options;
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($options)
    {
        $this->options = $options;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.dropdown.select2');
    }
}
