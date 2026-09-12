<?php

namespace AlphaDirect\View\Components;

use Illuminate\View\Component;


class FormInputError extends Component
{
	public string $name;
    public string $bag;
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(string $name="", string $bag = 'default')
    {
		$this->name = $name;

        $this->bag = $bag;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.form-input-error');
    }
}
