<?php

namespace AlphaDirect\View\Components;

use Illuminate\View\Component;

class AppV2Layout extends Component
{
    /**
     * Get the view / contents that represents the component.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('layouts.app-v2');
    }
}
