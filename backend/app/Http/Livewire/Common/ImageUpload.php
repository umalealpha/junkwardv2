<?php

namespace AlphaDirect\Http\Livewire\Common;

use Livewire\Component;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Redirect;

class ImageUpload extends Component
{
    public $imageTitle;
    public $imageField;
    public $imageExist;
    public $customer;

    public function mount(){
        // dd($this->imageTitle,$this->imageField,$this->imageExist);
    }

    public function render()
    {
        return view('v2.livewire.common.image-upload');
    }
}
