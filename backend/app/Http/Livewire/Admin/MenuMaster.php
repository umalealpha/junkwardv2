<?php

namespace AlphaDirect\Http\Livewire\Admin;

use Livewire\Component;

class MenuMaster extends Component
{
	public $name,$menu_url,$remarks,$menu_id;
	public $availbale_for_roles=[];
	public $availbale_for_permission=[];
	
	protected $rules = [
        'name'=>"required|max:110",
		'menu_url'=>"required|max:210"
    ];
	
	public function mount(){
		
	}
	public function saveMenu(){
		$this->validate();
		\AlphaDirect\MenuMaster::updateOrCreate(['id'=>$this->menu_id],[
			'name'=>$this->name,
			'menu_url'=>$this->menu_url,
			'remarks'=>$this->remarks,
			'availbale_for_roles'=>json_encode($this->availbale_for_roles),
			'availbale_for_permission'=>json_encode($this->availbale_for_permission),
		]);
		if($this->menu_id==""){
			$this->emit('saved');
			$this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Menu Master Added Successfully!']);
		}else{
			$this->emit('updated');
			$this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Menu Master Updated Successfully!']);
		}
		$this->resetForm();
	}
	
	public function resetForm(){
		$this->name="";
		$this->menu_url="";
		$this->remarks="";
		$this->menu_id="";
		$this->availbale_for_roles=[];
		$this->availbale_for_permission=[];
	}
	
    public function render()
    {
        return view('livewire.admin.menu-master');
    }
	
	public function getRolesProperty()
    {
		return \Spatie\Permission\Models\Role::all();
	}
	
	
	public function getPermissionProperty()
    {
		return \Spatie\Permission\Models\Permission::all();
	}
}
