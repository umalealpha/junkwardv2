<?php

namespace AlphaDirect\Http\Livewire\Policy;

use AlphaDirect\Beneficiary;
use AlphaDirect\Customer;
use AlphaDirect\Http\Traits\Policy\AddCoverageTrait;
use AlphaDirect\Models\Company;
use AlphaDirect\Models\newPolicyCoverages;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Policy;
use AlphaDirect\PolicyBeneficiary;
use AlphaDirect\Vehicle;
use AlphaDirect\VehicleMake;
use AlphaDirect\PolicyCellPhone;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\DeviceMakeModel;
use AlphaDirect\PolicyTerm;
use AlphaDirect\State;
use AlphaDirect\City;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Http\Controllers\Admin\DocumentController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\UserPassword;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Illuminate\Validation\Validator;
use Livewire\WithFileUploads;
use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\User;
class AddWizard extends Component
{
	use WithFileUploads;
	use AddCoverageTrait;

	public $photopath;
	public $policy;
	public $mainSteps;
	public $agency; # Agency Details
	public $policies; # model Policy
	public $renualPlan; #Renual Plan
	public $pinfo;
	public $customer;
	public $customer_profile;
	public $customer_profile_update;
	public $riskSelectedState = NULL;
    public $Application_Update = false;
	public $step=1;
	public $seletedCoverages=[];
	public $plans;
	public $agents;
	public $selectedAgency = NULL;
	public $isPrevious = true;
	public $policyTermId;
	#for exting customer
	public $extingCostomer;
	public $employment=[];
	public $sourceOfIncome;
	public $selectedTermId;
    public $actionId;
    public $allPlan;
    public $allCities;
    public $editable = true;
    public $term_start_date;
    public $binder_date;
    public $policies_expiry_date;
    public $customer_profile_date;
	public $customer_profile_dob;
	public $restricted_editable = 0;
    protected $listeners = [
        'updateHasStatus' => 'updateHasStatus',
        'backToStep1' => 'backToStep1',
        'backToStep2' => 'backToStep2',
        'backToStep3' => 'backToStep3',
        'backToStep4' => 'backToStep4',
        'backToStep5' => 'backToStep5',
        'refreshParent' => '$refresh'
    ];
	public function rules(){
		return[
			'selectedAgency'=>'', #step1
			'policies.premium_freq'=>'', #step1
			// 'policies.term_start_date'=>'', #step1
            'term_start_date'=>'', #step1
			'policies_expiry_date'=>'', #step1
			// 'customer_profile.binder_date'=>'', #step1
            'binder_date'=>'', #step1
			'customer_profile.select_product'=>'', #step1
			'policies.policyNumber'=>'', #step1
			// 'customer_profile.estm'=>'', #step1
			'policies.agency_id'=>'', #step1
			'policies.agent_id'=>'', #step1
			'policies.status'=>'', #step1
            'policies.uw_app_status'=>'', #step1
			'policies.product_id'=>'', #step1
			'policies.plan_id'=>'', #step1
            'policies.gfs_policy_no'=>'', #step1
			'customer_profile.entity_type'=>'', #step1
			'customer_profile.company_id'=>'', #step1
			'customer.firstName'=>'', #step1
			'customer.middleName'=>'', #step1
			'customer.lastName'=>'', #step1
			'customer_profile.gender'=>'', #step1
			'customer_profile.maritalstatus'=>'', #step1
			'customer_profile_dob'=>'', #step1
			'customer_profile.omang'=>'', #step1
			'customer_profile.passport'=>'', #step1
			// 'customer_profile.know_name'=>'', #step1
			'customer_profile.post_address'=>'', #step1
			'customer_profile.state'=>'', #step1
			'customer_profile.city'=>'', #step1
			'customer.email'=>'', #step1
			'customer.cellphone'=>'', #step1
			'customer_profile_date'=>'', #step1
			'customer_profile.business_note'=>'', #step1
			'customer_profile.decline_proposal'=>'', #step1
			'customer_profile.refused_policy'=>'', #step1
            'customer_profile.cancel_policy'=>'', #step1
			'customer_profile.insure'=>'', #step1
			'customer_profile.firm_member'=>'', #step1
			'customer_profile.books'=>'', #step1
			'customer_profile.about_alpha'=>'', #step1
			'seletedCoverages.*'=> '',
			'sourceOfIncome'=>'',
			'employment.*'=>''
		];
	}

    protected $messages = [
        'beneficiaryData.dob' => 'beneficiary date of birth required',
		'beneficiaryData.omang.required_if' => 'beneficiary omang id field is required',
		'beneficiaryData.omang.min' => 'beneficiary omang id must be at least 5 characters.',
		'beneficiaryData.omang.unique' => 'The beneficiary omang id has already been taken.',
		'beneficiaryData.passport.required_if' =>'beneficiary passport is required',
		'beneficiaryData.passport.min' => 'beneficiary passport must be at least 5 characters.',
		'vehicleData.estimated_value.required_if' => 'estimated value of vehicle is required',
		'vehicleData.claim_count.required_if' => 'no of accidents field is required',
    ];

	protected $validationAttributes = [
		'polices.agent_id' => 'Agent'
	];

	public function messages(){
		return[
			'customer.cellphone.unique' => 'The cellphone has already been taken.<a class="btn btn-info" wire:click="fetchCustomer(\'customer.cellphone\')">Fetch Previous detail\'s</a>',#
		];
	}

	public function mount(){
		#set default value
		// $this->pinfo['get']=false;
		$this->customer_profile['decline_proposal']=false;
		$this->customer_profile['refused_policy']=false;
		$this->customer_profile['cancel_policy']=false;
		$this->customer_profile['firm_member']=false;
		$this->customer_profile['books']=false;
		$this->customer_profile['entity_type']="";
		$this->policies= new \AlphaDirect\Policy();
		$this->agency= new \AlphaDirect\Agency();
        $this->customer= new \AlphaDirect\Customer();
		$this->customer_profile= new \AlphaDirect\CustomerProfile();
	}

	/*
		Get All Active Agency step 1
	*/
	public function getAgency(){
		return \AlphaDirect\Agency::whereStatus(1)->get()->keyBy('id')->map(function($d){
			return [
				'id'=>$d->id,
				'name'=>$d->name
			];
		});;
	}

	/* step 1*/
	public function getPremiumFreqPropert(){
		//return ["1"=>"MONTHLY","2"=>"3 INSTALLMENTS","3"=>"ANNUAL","4"=>"SEMIANNUAL","5"=>"QUARTERLY"];
		// Company-only products (20 Commercial Liabilities / 23 Guarantee /
		// 24 Miscellaneous) are ANNUAL term only. Restrict here rather than in
		// the applicant-information partial: on THIS wizard $Application_Update
		// is false, so the partial's gated frequency select never renders and
		// the live control is the one in add-wizard.blade.php, which reads this
		// method directly.
		$companyOnly = \AlphaDirect\Support\CompanyOnlyProducts::freqOptions($this->policies->product_id ?? null);
		if ($companyOnly !== null) {
			return $companyOnly;
		}
		return ["1"=>"MONTHLY","3"=>"ANNUAL","5"=>"QUARTERLY","6"=>"MANUAL INPUT"];
	}
	/* step 1*/
	public function getSourceOfIncome(){
		return array('unemployed'=>'Unemployed','employment'=>'Employment','pensioner_retired'=>'Pensioner/Retired','bussiness'=>'Self-Employment/Business','inheritance'=>'Inheritance','gifts'=>'Gifts','investments'=>'Investments');
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

	public function getCurrentlyInsure(){
		return \AlphaDirect\Lookup::where('key','are_you_currently_insured')->get()->keyBy('id')->map(function($d){
			return [
				'id'=>$d->id,
				'name'=>$d->value
			];
		});
	}

	public function getHearAboutAlphadirect(){
		return \AlphaDirect\Lookup::where('key','hear_about_alphadirect')->get()->keyBy('id')->map(function($d){
			return [
				'id'=>$d->id,
				'name'=>$d->value
			];
		});
	}

	/*
	 Get All cities WIth Selectd state
	*/
    public function updatedCustomerProfileDate($value){
        $this->updatedCustomerProfile($value, "customer_profile_date");
    }
	public function updatedCustomerProfile($value, $key)
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

		if ($key=="customer_profile_date") {
            $d = Carbon::createFromFormat(config('constants.date.format'),$value ?? "");
			$years = \Carbon\Carbon::parse($d)->age;
			$this->customer_profile->business_note= $years.' years old';
		}
    }
//
//	public function updatedRiskinfo($value, $key)
//    {
//		if ($key=="risk_state") {
//			$data= \AlphaDirect\City::where('state_id', $value)
//			 ->get()->keyBy('id')->map(function($d){
//				return [
//					'id'=>$d->id,
//					'name'=>$d->name
//				];
//			});
//			$this->dispatchBrowserEvent('dropdown-changed',[
//				'key'=>'risk_state',
//				'data'=>$data
//			]);
//        }
//
//    }


	/*
		Get All products
	*/
	public function getProducts(){
		return \AlphaDirect\Product::get()->whereIn('id',[7,8,16,17,18,20,22,23,24])->keyBy('id')->map(function($d){
			return [
				'id'=>$d->id,
				'name'=>$d->name
			];
		});
	}

	/*
	 Get All plan WIth Selectd product
	*/
    public function updatedTermStartDate($value){
		
        $this->updatedPolicies($value, "term_start_date");
    }

	public function updatedPolicies($value, $key)
    {
        // dd($value, $key);
        if ($key=="product_id") {
			$data= \AlphaDirect\Productplan::where('product_id', $value)
			 ->get()->keyBy('id')->map(function($d){
				return [
					'id'=>$d->id,
					'name'=>$d->name
				];
			});
            $this->allPlan = $data;
			$this->dispatchBrowserEvent('dropdown-changed',[
				'key'=>'product_id',
				'data'=>$data
			]);
			// Switching INTO a company-only product (20 / 23 / 24) forces the
			// Annual term. Without this, a frequency picked BEFORE the product
			// (say Quarterly) leaves policies_expiry_date on a 3-month term
			// while saveStep1 coerces premium_freq to Annual — an "Annual"
			// policy with a quarterly period. Re-enter this handler on the
			// premium_freq key so the existing expiry switch below recomputes
			// the date rather than duplicating that logic here.
			if (\AlphaDirect\Support\CompanyOnlyProducts::includes($value)
				&& $this->policies->premium_freq !== \AlphaDirect\Support\CompanyOnlyProducts::ANNUAL_FREQ) {
				$this->policies->premium_freq = \AlphaDirect\Support\CompanyOnlyProducts::ANNUAL_FREQ;
				$this->updatedPolicies($this->policies->premium_freq, 'premium_freq');
			}
        }

		if ($key=="premium_freq" || $key=="term_start_date") {
			if($this->term_start_date!="" && $this->policies->premium_freq!="" && $this->policies->premium_freq != "6"){
				switch($this->policies->premium_freq){
					case "1":
						// $this->policies->expiry_date=\Carbon\Carbon::parse($this->term_start_date)->addMonth()->subDay(1)->format('d/m/Y');
                        $this->policies_expiry_date = Carbon::createFromFormat(config('constants.date.format'),$this->term_start_date)->addMonth()->subDay(1)->format(config('constants.date.format'));
					break;
					case "2":
						// $this->policies->expiry_date=\Carbon\Carbon::parse($this->term_start_date)->addMonths(3)->subDay(1)->format('d/m/Y');
                        $this->policies_expiry_date = Carbon::createFromFormat(config('constants.date.format'),$this->term_start_date)->addMonths(3)->subDay(1)->format(config('constants.date.format'));
					break;
					case "3":
						// $this->policies->expiry_date=\Carbon\Carbon::parse($this->term_start_date)->addYear(1)->subDay(1)->format('d/m/Y');
                        $this->policies_expiry_date = Carbon::createFromFormat(config('constants.date.format'),$this->term_start_date)->addYear(1)->subDay(1)->format(config('constants.date.format'));
					break;
					case "4":
						// $this->policies->expiry_date=\Carbon\Carbon::parse($this->term_start_date)->addMonths(6)->subDay(1)->format('d/m/Y');
                        $this->policies_expiry_date = Carbon::createFromFormat(config('constants.date.format'),$this->term_start_date)->addMonths(6)->subDay(1)->format(config('constants.date.format'));
					break;
					case "5":
					default:
					    // $this->policies->expiry_date=\Carbon\Carbon::parse($this->term_start_date)->addMonths(3)->subDay(1)->format('d/m/Y');
                        $this->policies_expiry_date = Carbon::createFromFormat(config('constants.date.format'),$this->term_start_date)->addMonths(3)->subDay(1)->format(config('constants.date.format'));
					break;
				}
			}elseif($this->policies->premium_freq == "6"){
				// Manual Input selected - don't auto-calculate, let user enter manually
				// Don't change policies_expiry_date, keep it as is or empty
			}else{
				$this->policies_expiry_date="";
			}
		}
		if ($key=="agency_id") {
			$data= \AlphaDirect\User::where('agency_id', $value)
			 ->get()->keyBy('id')->map(function($d){
				return [
					'id'=>$d->id,
					'name'=>$d->full_name
				];
			});
			$this->agents = $data;
			
			$this->dispatchBrowserEvent('dropdown-changed',[
				'key'=>'selectedAgency',
				'data'=>$data
			]);
		}

    }


    public function render()
    {
		return view('v2.livewire.policy.add-wizard')->layout('layouts.app-v2');
	}

	/* step 2*/
	public function getallRiskAddress(){
		return RiskAddress::where('policy_id',"=",$this->policies->id)->get(); //19602
	}

	/* step 4*/
	public function getallBeneficiaries(){
		return PolicyBeneficiary::where('policy_id',"=",$this->policies->id)->get();
	}

	/* step 5*/
	public function getallVehicles(){
		return Vehicle::where('policy_id',"=",$this->policies->id)->get();
	}

	/* step 6*/
	public function getallDevices(){
		return PolicyCellPhone::where('policy_id',"=",$this->policies->id)->get();
	}

	public function updated($propertyName)
	{
       if($this->customer_profile['omang']!=""
		&& $this->customer_profile['passport']!=""
		&& $this->customer['email']	!=""
		&& $this->customer['cellphone']	!=""
		){
			#check For Exiting Customer
			$d = \AlphaDirect\Customer::where('email','=',$this->customer['email'])
			->where('cellphone','=',$this->customer['cellphone'])
			->whereHas('profile', function($q){
				$q->where('omang', '=', $this->customer_profile['omang'])
				->where('passport', '=', $this->customer_profile['passport']);
			})->first();
			if($d){
				$this->extingCostomer=$d;
				$this->dispatchBrowserEvent('openModalCostomerDetails');
			}else{
				$this->extingCostomer='';
			}
		}
    }

	public function seletedExitingCostomer(){
		$this->customer=$this->extingCostomer;
	}

	public function fetchCustomer($f){
		$d=explode('.',$f);
		$this->customer= \AlphaDirect\Customer::where(end($d),'=',$this->customer[end($d)])->first();
	}

	/* Save Step 1*/
	public function saveStep1(){
		$sourceOfIncome=[];
		if(is_array($this->employment)&& count($this->employment)>0){
			$sourceOfIncome[$this->sourceOfIncome]=$this->employment;
		}else{
			$sourceOfIncome=$this->sourceOfIncome;
		}
		$this->policies->has_member = 1;
        $this->policies->status = 0;
		// Company-only products (20 Commercial Liabilities / 23 Guarantee /
		// 24 Miscellaneous): Annual term on all three, Organisation holder on
		// 23 / 24 only — per UW (2026-08-26) Commercial Liabilities takes an
		// Individual or an Organisation, so its entity_type is left alone.
		// COERCE rather than add a validation rule — a failing rule in
		// saveStep1 aborts the whole save with no error surfaced on this
		// screen, which reads to the operator as "Update does nothing". The
		// step-1 partial already offers only these values, so this is the
		// belt-and-braces for a stale wire:model or a frequency carried over
		// from another product.
		if (\AlphaDirect\Support\CompanyOnlyProducts::includes($this->policies->product_id)) {
			if ($this->policies->premium_freq !== \AlphaDirect\Support\CompanyOnlyProducts::ANNUAL_FREQ) {
				$this->policies->premium_freq = \AlphaDirect\Support\CompanyOnlyProducts::ANNUAL_FREQ;
				// Re-derive the term too, otherwise the policy saves as Annual
				// with whatever period the previous frequency had computed.
				$this->updatedPolicies($this->policies->premium_freq, 'premium_freq');
			}
		}
		if (\AlphaDirect\Support\CompanyOnlyProducts::entityLocked($this->policies->product_id)) {
			$this->customer_profile->entity_type = \AlphaDirect\Support\CompanyOnlyProducts::ENTITY_TYPE;
		}
		$valid=[];
		$valid['policies.product_id']='required';
		$valid['policies.plan_id']='required';
		$valid['customer_profile.entity_type']='required';
		$valid['policies.premium_freq']='required';
		// $valid['policies.term_start_date']='required';
        $valid['term_start_date']='required';
		$valid['policies_expiry_date']='required';
        $valid['policies.gfs_policy_no']='';
		$valid['customer_profile.insure']='required';
		$valid['customer_profile.about_alpha']='required';
		if(($this->policies->product_id)==7){
			$valid['customer_profile_date']='required';
		}
		if(($this->customer_profile->entity_type)=='Organisation'){
            $this->customer = Customer::where('company_id',$this->customer_profile->company->id)->first(); // @todo need to refactor the code it's only temporary solution
			$valid['customer_profile.company_id']='required';
		}else{
            $valid['customer.email']='required';
            $valid['customer.cellphone']='required|regex:/^([0-9\s\-\+\(\)]*)$/|min:8|max:8';
            $valid['customer_profile.state']='required';
		    $valid['customer_profile.city']='required';
			$valid['customer.firstName']='required';
			$valid['customer.lastName']='required';
			$valid['customer_profile.gender']='required';
			$valid['customer_profile.maritalstatus']='required';
			$valid['customer_profile_dob']='required';
			$valid['sourceOfIncome']='required';
			if(($this->sourceOfIncome)=='employment'){
				$valid['employment.where_are_you_employed_?']='required';
				$valid['employment.what_is_your_monthly_salary_?']='required';
			}elseif(($this->sourceOfIncome)=='pensioner_retired'){
				$valid['employment.how_much_is_your_monthly_pension_']='required';
			}elseif(($this->sourceOfIncome)=='bussiness'){
				$valid['employment.name_of_your_bussiness']='required';
				$valid['employment.bussiness_address']='required';
				$valid['employment.what_is_your_monthly_income_?']='required';
			}elseif(($this->sourceOfIncome)=='inheritance'){
				$valid['employment.who_did_you_inherit_these_funds_from_?']='required';
			}elseif(($this->sourceOfIncome)=='gifts'){
				$valid['employment.who_gifted_you_these_funds_?']='required';
				$valid['employment.how_much_were_you_gifted_?']='required';
				$valid['employment.gift_frequency']='required';
			}elseif(($this->sourceOfIncome)=='investments'){
				$valid['employment.what_is_the_amount_of_funds_invested_?']='required';
				$valid['employment.how_much_do_you_earn_from_these_investments_per_month_?']='required';
			}
			if (empty($this->customer_profile->omang) && empty($this->customer_profile->passport)){
				$valid['customer_profile.omang']='required|regex:/^[a-zA-Z0-9]+$/|min:5|max:25';
			    $valid['customer_profile.passport']='required|regex:/^[0-9]{4}[1-2][0-9]{4}$/|max:9';
			}
			if (empty($this->customer_profile->omang)){
			    $valid['customer_profile.passport']='required|regex:/^[a-zA-Z0-9]+$/|min:5|max:25';
			}
			if (empty($this->customer_profile->passport)){
				$valid['customer_profile.omang']='required|regex:/^[0-9]{4}[1-2][0-9]{4}$/|max:9';
			}
            $this->customer->save();
		}

		$this->customer_profile->sourceOfIncome=json_encode($sourceOfIncome);
		$this->validate($valid,$this->messages);

		$this->customer_profile->decline_proposal = ($this->customer_profile->decline_proposal==true)?1:0;
		$this->customer_profile->refused_policy = ($this->customer_profile->refused_policy==true)?1:0;
		$this->customer_profile->cancel_policy = ($this->customer_profile->cancel_policy==true)?1:0;
		if(($this->policies->product_id)==7){
			if(!empty($this->customer_profile_date)){
				$this->customer_profile->date = Carbon::createFromFormat(config('constants.date.format'),$this->customer_profile_date);
			}
			$this->customer_profile->firm_member = ($this->customer_profile->firm_member==true)?1:0;
			$this->customer_profile->books = ($this->customer_profile->books==true)?1:0;
		}
		if(!empty($this->customer_profile_dob)){
			$this->customer_profile->dob = Carbon::createFromFormat(config('constants.date.format'),$this->customer_profile_dob);
		}
		$this->customer_profile->customer_id= $this->customer->id;

		$customerExist = \AlphaDirect\CustomerProfile::where('customer_id',$this->customer->id)->first();
        if($customerExist){
			// Update Customer Profile data
			$this->customer_profile_update = $customerExist;
			$this->customer_profile_update->customer_id= $this->customer->id;
			$this->customer_profile_update->entity_type = $this->customer_profile->entity_type??"";
			if(($this->customer_profile->entity_type)=='Organisation'){
				$this->customer_profile_update->company_id = $this->customer_profile->company_id??"";
			}else{
				$this->customer_profile_update->gender = $this->customer_profile->gender??"";
				if(!empty($this->customer_profile_dob)){
				    $this->customer_profile_update->dob = Carbon::createFromFormat(config('constants.date.format'),$this->customer_profile_dob);
				}
				$this->customer_profile_update->maritalstatus = $this->customer_profile->maritalstatus??"";
				$this->customer_profile_update->sourceOfIncome = json_encode($sourceOfIncome);
				$this->customer_profile_update->omang = $this->customer_profile->omang??"";
				$this->customer_profile_update->passport = $this->customer_profile->passport??"";
				$this->customer_profile_update->city = $this->customer_profile->city??"";
				$this->customer_profile_update->state = $this->customer_profile->state??"";
				$this->customer_profile_update->address = $this->customer_profile->post_address??"";
				$this->customer_profile_update->post_address = $this->customer_profile->post_address??"";
			}
			if(($this->policies->product_id)==7){
                if(!empty($this->customer_profile_date)){
				    $this->customer_profile_update->date = Carbon::createFromFormat(config('constants.date.format'),$this->customer_profile_date);
				}
				$this->customer_profile_update->firm_member = ($this->customer_profile->firm_member==true)?1:0;
				$this->customer_profile_update->books = ($this->customer_profile->books==true)?1:0;
			}

			$this->customer_profile_update->insure = $this->customer_profile->insure??"" ;
			$this->customer_profile_update->decline_proposal = ($this->customer_profile->decline_proposal==true)?1:0;
			$this->customer_profile_update->refused_policy = ($this->customer_profile->refused_policy==true)?1:0;
			$this->customer_profile_update->about_alpha = $this->customer_profile->about_alpha??"" ;
			// $this->customer_profile_update->binder_date = $this->customer_profile->binder_date??"";
            $this->customer_profile_update->binder_date = $this->binder_date??"";
			$this->customer_profile_update->estm = $this->customer_profile->estm??"";
			$this->customer_profile_update->cancel_policy = ($this->customer_profile->cancel_policy==true)?1:0;
			$this->customer_profile_update->business_note = $this->customer_profile->business_note??"" ;
			$this->customer_profile_update->save();
		}else{

			$this->customer_profile->save(); // Add Customer Profile data
		}

        $this->policies->customer_id= $this->customer->id;

        $latest = Policy::orderBy('id', 'desc')->first(array('id'));

        if ($latest == null) {
            $latest = collect();
            $latest->id = 1;
        }
        if($this->policies->product_id == 7 || $this->policies->product_id == 16 || $this->policies->product_id == 17 ){
           $policy_no = 'COMG' . Carbon::now()->year . str_pad(($latest->id + 1), 6, '0', STR_PAD_LEFT);
        }elseif($this->policies->product_id == 8 || $this->policies->product_id == 18 || $this->policies->product_id == 19){
           $policy_no = 'DOMG' . Carbon::now()->year . str_pad(($latest->id + 1), 6, '0', STR_PAD_LEFT);
        }else{
            $policy_no = 'MIS' . Carbon::now()->year . str_pad(($latest->id + 1), 6, '0', STR_PAD_LEFT);
        }
        $this->policies->policyNumber = $policy_no;
		$this->policies->policyActivatedDate = null; //Carbon::now();
		$this->policies->billingStartDate = null;
        $this->policies->term_start_date = Carbon::createFromFormat(config('constants.date.format'),$this->term_start_date);
        $this->policies->expiry_date = Carbon::createFromFormat(config('constants.date.format'),$this->policies_expiry_date);
		$this->policies->save();

        activity('Policy')
        ->performedOn($this->policies)
        ->causedBy(User::where('id', auth()->user()->id)->first())
        ->log('Policy Created');

		$data = [
			'policy_id' => $this->policies->id,
			// 'term_start_date' => $this->policies->term_start_date,
            'term_start_date' => $this->policies->term_start_date,
			'term_end_date' => $this->policies->expiry_date, //Carbon::now()->addYear()->format('Y-m-d'),
			'premium' => $this->policies->premium,
			'annual_premium' => NULL,
			'vat' => $this->policies->vat,
			'vat_percent' => $this->policies->vat_percent,
			'renewed_by' => $this->policies->agent_id,
			'renewals_date' =>  Carbon::now()->addYear()->format('Y-m-d'),
			'frequency' => $this->policies->premium_freq,
			'first_premium' => $this->policies->first_premium,
			'billing_start_date' => $this->policies->billingStartDate,
			'policy_documents' => NULL,
			'policyActivatedDate' => NULL,
			'payment_method' => NULL,
			'payment_reference' => $this->policies->id,
			'trans_type' => 'NEW BUSINESS',
			'status' => 'Active',
			'created_at' => Carbon::now()->format('Y-m-d'),
		];

        $term_start_date = $this->policies->term_start_date;
        $expiry_date     = $this->policies->expiry_date;
        //  dd($expiry_date);
        $this->selectedTermId = PolicyTerm::addPolicyTerm($data);

        activity('Policy Term Created')
        ->performedOn($this->policies)
        ->causedBy(auth()->user())
        ->log('Term Date : '.$term_start_date.' - '.$expiry_date);

        $this->actionId = PolicyAction::create([
            'policy_id'=>$this->policies->id,
            'term_id'=>$this->selectedTermId,
            'note'=>'New Business',
            'policy_quote_no' => $policy_no.'/01',
            'effective_from' => $this->policies->term_start_date,
            'effective_to' => $this->policies->expiry_date,
        ])->id;

        activity('Policy Action Created')
        ->performedOn($this->policies)
        ->causedBy(auth()->user())
        ->log('Effective Date : '.$term_start_date.' - '.$expiry_date.' , '.'Transaction Type : NEW BUSINESS'.' , '.'Status : QUOTE');

        //     $token                  = Str::random(8);
        //     $user_password          = new UserPassword();
        //     $user_password->user_id = $this->customer->id;
        //     $user_password->token   = $token;
        //     $url                    = env('LIVEQUOTE_URL').'reset_password_first_time.php?token='.$token;
        //     $user_password->url     = $url;
        //     $user_password->save();

        //   //sms
        //   $sms = new SmsMessaging();
        //   $sms->sendSmsUserCreate(29, $this->customer->firstName, $this->customer->lastName, $this->customer->cellphone, $url);

          //mail
          if(config('app.env') != 'local'){
            if ($this->customer->email != null) {
                $data = new \stdClass();
                $data->user_id = null;
                $data->customer_id = $this->customer->id;
                $data->new_user_password_url_id = null;
                $data->hook = 'user_create';
                $data->attachment = null;

                $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                $markdown = new MailTemplate($data);
                $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
               // event(new \AlphaDirect\Events\SendMail($this->customer->email,$emailTemplate->subject,"",$html,null,['hook' => $data->hook]));
                // return response()->json(['success' => 1, 'mail' => $mail], 200);
              }
          //sms
          //   if ($this->policies->product_id != 3) {
          //     $sms = new SmsMessaging();
          //     $sms->sendSmsPolicyCreate(28, $this->policies->policyNumber,$this->customer->firstName, $this->customer->lastName, $this->customer->cellphone);
          //   }
          }

		$this->step=2;
	}

	public function addCoverages(){
		#assign coverages add on edit mode if already saved

	}

	public function coverageSelectChange(){
		#if need to che selecet coverges are valid
	}

//	public function getCoverageDesign($code){
//		return \AlphaDirect\Models\CoverageMaster::where('s_ParentCoverageCode', '=', $code)->orderBy('n_DisplaySequence',"ASC")->get();
//	}

	public function getCoveragesMasterProperty(){
		return \AlphaDirect\Models\CoverageMaster::where('s_CoverageGroupCode','=','MAIN')
		->where('s_UsageType','=','PARENT')
		->get();
	}

	public function saveStep6(){
		
		$this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Policy details are saved successfully!']);
//		dd('success');
	}
	/* End Device*/

    public function getCompaniesProperty(){
        return Company::select('id','name')->MainCompanyOnly()->Activated()->orderBy('id','desc')->get()->pluck('name','id');
    }
}
