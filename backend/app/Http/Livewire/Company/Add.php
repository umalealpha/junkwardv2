<?php

namespace AlphaDirect\Http\Livewire\Company;

use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Models\Company;
use Illuminate\Support\Facades\Redirect;
use Livewire\Component;
use Illuminate\Support\Facades\Log;
use DB;
class Add extends Component
{
    public Company $company;
    public $customerData;
    public $customerProfile;
    public $customerDataPresent;
    public $colSize = "col-md-3";
    public $modalId = false;
    public $allCities;
    public $editable = true;

    public $rules = [
        'company.name' => 'required',
        'company.parent_id' => '',
        'company.VAT_registration_number' => 'required',
        'company.company_registration_number' => 'required',
        'company.status' => '',
        'company.head_office_physical_address' => 'required',
        'company.postal_address' => 'required',
        'company.city' => 'required',
        'company.state' => 'required',
        'company.pincode' => 'required',
        // 'company.contact_person' => 'required',
        'customerData.firstName' => 'required',
        'customerData.lastName' => 'required',
        'company.contact_person_number' => 'required',
        'company.primary_email' => 'required',
        'company.secondary_email' => 'required',
        'company.broker_email' => 'required'
    ];

    public function mount(){
        $this->company = new Company();
        $this->company->status = true;
        $this->customerData = new \AlphaDirect\Customer();
        $this->customerProfile = new \AlphaDirect\CustomerProfile();
    }

    public function render()
    {
        return view('v2.livewire.company.add')->layout('layouts.app-v2');
    }

    public function submit(){
        $this->validate();
        if ($this->company->save()){
            if ($this->company->parent_id == null) {
                
                $this->customerData->company_id = $this->company->id;
                $this->customerData->firstName  = $this->customerData->firstName;
                $this->customerData->middleName = null;
                $this->customerData->lastName   = $this->customerData->lastName;
                $this->customerData->email      = $this->company->primary_email;
                $this->customerData->cellphone  = $this->company->contact_person_number;
                $this->customerData->save();

                $this->customerProfile->customer_id  = $this->customerData->id;
                $this->customerProfile->company_id   = $this->company->id;
                $this->customerProfile->post_address = $this->company->postal_address;
                $this->customerProfile->address      = $this->company->postal_address;
                $this->customerProfile->city         = $this->company->city;
                $this->customerProfile->state        = $this->company->state;
                $this->customerProfile->save();
            }
            
            // dd("sub");
            DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Company')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Company Added - '.$this->company->name);
            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Company Created Successfully!']);
            $this->mount();
            if ($this->modalId){
                $this->emitUp('refreshParent');
            }
            return ;
        }
        $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Something Went Wrong']);
    }

    public function getComapniesProperty(){
        return Company::select('id','name')->get()->pluck('name','id');
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
	public function updatedCompany($value, $key)
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
