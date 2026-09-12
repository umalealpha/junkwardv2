<?php

namespace AlphaDirect\View\Components;

use Illuminate\View\Component;

class Select extends Component
{

    public $options = [];

    public $selected = '';

    public $trackBy;

    public $label;

	public $emptyOptionsMessage;

    public $disabled;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(
		$options,
		$selected = '',
		$label = 'name',
		$emptyOptionsMessage='No results match your search.',
        $disabled = false
	)
    {
        $this->options = $options;
		$this->selected = $selected;
        $this->label = $label;
		$this->trackBy = "id_".rand(1111,99999);
		$this->disabled = $disabled;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.select');
    }
}
