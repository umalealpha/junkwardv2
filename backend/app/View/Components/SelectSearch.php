<?php

namespace AlphaDirect\View\Components;

use Illuminate\View\Component;

class SelectSearch extends Component
{

    public $options = [];

    public $selected = '';

    public $trackBy;

    public $label;

	public $emptyOptionsMessage;

	public $disabled = false;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(
		$options,
		$selected = '',
		$trackBy = 'id',
		$label = 'name',
		$emptyOptionsMessage='No results match your search.',
        $disabled = false
	)
    {
        $this->options = $options;
		$this->selected = $selected;
        $this->trackBy = $trackBy;
        $this->label = $label;
        $this->disabled = $disabled;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.select-search');
    }
}
