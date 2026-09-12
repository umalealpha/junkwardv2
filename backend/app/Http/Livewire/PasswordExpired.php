<?php

namespace AlphaDirect\Http\Livewire;

use Livewire\Component;

class PasswordExpired extends Component
{

	public $current_password;
	public $password;
	public $password_confirmation;

	protected function rules()

    {
		return [

            'password' => 'required|confirmed|string|min:8',
			'current_password' => ['required', function ($attr, $password, $validation) {
				if (isset($password) && isset(auth()->user()->password)) {
                    if (!\Hash::check($password, auth()->user()->password)) {
                        return $validation(__('The current password is incorrect.'));
                    }
                }
			}],
			'password_confirmation' => 'required',

        ];

    }

    public function render()
    {
        return view('v2.livewire.password-expired');
    }

	public function submit(){
		$this->validate();
		auth()->user()->password_changed_at=\Carbon\Carbon::now();
		auth()->user()->password= \Hash::make($this->password);
		auth()->user()->save();
		return redirect()->to(session()->get('resetPath').'/dashboard')->with('success',"Password Updated Succesufully");
	}
}
