<?php

namespace AlphaDirect\Http\Livewire\Company;

use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Models\Company;
use Illuminate\Support\Facades\Redirect;
use Livewire\Component;
use AlphaDirect\Models\User;
use Illuminate\Support\Facades\Log;
use DB;
class EditCompany extends Component
{
    // public Company $parentCompany;
    public $parentCompany;
    public $company;
    public $customerData;
    public $customerProfile;
    public $allCities;
    public $editable = true;
    public $policy;

    public $rules = [
        'company.name' => 'required',
        // 'company.parent_id' => '',
        'company.VAT_registration_number' => 'required',
        'company.company_registration_number' => 'required',
        'company.status' => '',
        'company.head_office_physical_address' => 'required',
        'company.postal_address' => 'required',
        'company.city' => 'required',
        'company.state' => 'required',
        'company.pincode' => 'required',
        'customerData.firstName' => 'required',
        'customerData.lastName' => 'required',
        'company.contact_person_number' => 'required',
        'company.primary_email' => 'required',
        'company.secondary_email' => 'required',
        'company.broker_email' => 'required'
    ];

    public function mount(){
        // $this->company = $company;
        $this->company = Company::find($this->parentCompany->id);
       // dd($this->company);
        $this->customerData = Customer::where('company_id',$this->company->id)->first();
        $this->customerProfile = CustomerProfile::where('company_id',$this->company->id)->first();

        $this->allCities= \AlphaDirect\City::where('state_id', $this->company->state)
            ->get()->keyBy('id')->map(function($d){
            return [
                'id'=>$d->id,
                'name'=>$d->name
            ];
        });
    }

    public function render()
    {
        return view('v2.livewire.company.edit-company')->layout('layouts.app-v2');
    }

    public function submit(){
       
        $this->validate();

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
        if ($this->company->save()){
            DB::commit();
            $customer = Customer::where('id', auth()->user()->id)->first();
            activity('Sub Company Updated')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Sub Company Updated - '.$this->company->name);
    
            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Company Updated Successfully!']);
           
           return $this->mount($this->company);
        }
        $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Something Went Wrong']);
    }

    public function getComapniesProperty(){
        return Company::select('id','name')->whereNotIn('id',[$this->company->id])->get()->pluck('name','id');
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
