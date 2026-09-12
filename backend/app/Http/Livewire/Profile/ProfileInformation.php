<?php

namespace AlphaDirect\Http\Livewire\Profile;

use Livewire\Component;

class ProfileInformation extends Component
{
	public $user;
	public $selectedrole;
	public $role;
	/**
     * Prepare the component.
     *
     * @return void
     */
	 
    public function mount()
    {
        $this->user = \Auth::user()->toArray();
		$this->commission = $this->user['commission'];
		$this->selectedrole=  auth()->user()->roles->toArray();
		if(count($this->selectedrole)>0){
			$this->role=$this->selectedrole[0]['name'];
		}
	}
	
	
    public function render()
    {
        return view('livewire.profile.profile-information')
		->withDepartment(\AlphaDirect\Department::orderBy('name')->get())
    	->withAgency(\AlphaDirect\Agency::orderBy('name')->get());
    }
	
	public function updateProfileInformation(){
		request()->validate([
			'firstName'=>'required'
		]);
		/* $this->user->profile->forceFill([
            'profile_photo' => null,
        ])->save();  */
		$this->emit('saved');
		$this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Profile Updated Successfully!']);
	}
	
	
	public function getRolesProperty()
    {
		if(auth()->user()->hasRole('Super Admin')){
			return \Spatie\Permission\Models\Role::all();
		}else{
			return (object) auth()->user()->roles;
		}
    }
}
