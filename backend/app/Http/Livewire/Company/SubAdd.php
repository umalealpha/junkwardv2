<?php

namespace AlphaDirect\Http\Livewire\Company;

use AlphaDirect\Models\Company;
use Livewire\Component;
use Illuminate\Support\Facades\Log;
use DB;
use AlphaDirect\Customer;

class SubAdd extends Component
{
    public Company $parentCompany;
    public $parentCompanyId;
    public $forUpdate = false;
    public Company $subCompany;
    public $editable = true;
    public $allCities;

    public $rules = [
        'subCompany.name' => 'required',
        'subCompany.VAT_registration_number' => 'required',
        'subCompany.company_registration_number' => 'required',
        'subCompany.status' => '',
        'subCompany.head_office_physical_address' => 'required',
        'subCompany.postal_address' => 'required',
        'subCompany.city' => 'required',
        'subCompany.state' => 'required',
        'subCompany.pincode' => 'required',
        'subCompany.contact_person' => 'required',
        'subCompany.contact_person_number' => 'required',
        'subCompany.primary_email' => 'required',
        'subCompany.secondary_email' => 'required',
        'subCompany.broker_email' => 'required'
    ];
    protected $listeners = ['refreshComponent' => '$refresh','delete'];


    public function mount(){
//        $this->parentCompany = Company::find($this->parentCompanyId);
        $this->subCompany = new Company();
        $this->subCompany->status = true;
        $this->forUpdate = false;

        $this->allCities= \AlphaDirect\City::where('state_id', $this->subCompany->state)
            ->get()->keyBy('id')->map(function($d){
            return [
                'id'=>$d->id,
                'name'=>$d->name
            ];
        });
    }

    public function render()
    {
        
        return view('v2.livewire.company.sub-add');
    }

    public function submit(){
        // dd(1);
        $this->validate();
        $this->subCompany->parent_id = $this->parentCompany->id;
        if ($this->subCompany->save()){
            DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Sub Company Added')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Sub Company Added - '.$this->subCompany->name);
            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Sub Company Created Successfully!']);
            $this->emit('refreshComponent');
            $this->mount();
            return ;
        }
        $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Something Went Wrong']);
    }

    public function delete(Company $subCompany){
        
        if ($subCompany->delete()){
            DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Sub Company Deleted')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Sub Company Deleted - '.$subCompany->name);
            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Sub Company Deleted Successfully!']);
            $this->emit('refreshComponent');
            return;
        }
        $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Something Went Wrong']);
    }

    public function edit(Company $subCompany){
        $this->subCompany = $subCompany;
        $this->forUpdate = true;
    }

    public function cancel(){
        $this->mount();
    }

    /*
		Get All state
	*/
	public function getStates(){
		return cache()->driver('file')
		->remember('State::28',now()->addMinutes(20), function (){
			return \AlphaDirect\State::where('country_id',28)->get()->keyBy('id')->map(function($d){
				return [
					'id'=>$d->id,
					'name'=>$d->name
				];
			});
		});
	}

     /*
	 Get All cities WIth Selectd state
	*/
	public function updatedSubCompany($value, $key)
    {
		if ($key=="state") {
			$data= \AlphaDirect\City::where('state_id', $value)
			 ->get()->keyBy('id')->map(function($d){
				return [
					'id'=>$d->id,
					'name'=>$d->name
				];
			});
			$this->allCities = $data;
			$this->dispatchBrowserEvent('dropdown-changed',[
				'key'=>'state_id',
				'data'=>$data
			]);
        }
    }
}
