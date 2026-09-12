<?php

namespace AlphaDirect\Http\Livewire\Profile;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Validator;

class ChangeProfilePicture extends Component
{
	use WithFileUploads;
	
	/**
     * The new avatar for the user.
     *
     * @var mixed
     */
    public $photo;
	 
	
	public function updateProfilePhoto(){
		request()->validate([
			'photo' => ['required', 'mimes:jpg,jpeg,png', 'max:1024']
		])->validateWithBag('updateProfilePhoto');
		tap($this->user->profile->profile_photo, function ($previous){
            $this->user->profile->forceFill([
                'profile_photo' => $this->photo->storePublicly(
                    'public/users/'.$this->user->id.'/profile/profile_picture', ['disk' => config('jetstream.profile_photo_disk', 'public')]
                ),
            ])->save();

            if ($previous) {
                \Storage::disk(config('jetstream.profile_photo_disk', 'public'))->delete($previous);
            }
		});
		$this->emit('saved');
		$this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Profile Photo Updated Successfully!']);
	}
	
    public function render()
    {
        return view('livewire.profile.change-profile-picture');
    }
	
	/**
     * Get the current user of the application.
     *
     * @return mixed
     */
    public function getUserProperty()
    {
		return \Auth::user();
    }
	
	 /**
     * Delete user's profile photo.
     *
     * @return void
     */
    public function deleteProfilePhoto()
    {
		\Storage::disk(config('jetstream.profile_photo_disk', 'public'))->delete($this->user->profile->profile_photo);

        $this->user->profile->forceFill([
            'profile_photo' => null,
        ])->save(); 
		
		$this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Profile Photo Removed Successfully!']);
		$this->emit('refresh-navigation-menu');
    }
	
}
