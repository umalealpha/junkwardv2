<?php

namespace AlphaDirect;
use AlphaDirect\Models\Company;
use Carbon\Carbon;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use AlphaDirect\Policy;
use OwenIt\Auditing\Contracts\Auditable;

class CustomerProfile extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'customer_profile';
    protected $guarded = ['id'];

    protected $dates = [];
     public function transformAudit(array $data): array
    {

        if (Arr::has($data, 'new_values')) {
        if($this->auditEvent != 'created'){
        if(isset($this->customer_id)){
            $policy = Policy::where('customer_id', $this->customer_id)->first();
            if($policy != null){
            $data['policy_id'] = $policy->id;
            $data['policy_number'] = $policy->policyNumber;
     }}}
        }
        //to store customer as a causer in case of APP/API call
        // if (Arr::has($data, 'new_values.lead_source')) {
        //     $data['customer_id'] = $data['new_values']['customer_id'];
        // }

        return $data;

    }
    public function generateTags(): array
    {
        return [
            'Customer Profile'.' '.$this->auditEvent,
        ];
    }

    // public function customer()
    // {
    //     return $this->belongsTo('AlphaDirect\Customer');
    // }

    public function states()
    {
        return $this->hasOne(State::class,'id','state');
    }

    public function cities()
    {
        return $this->hasOne(City::class,'id','city');
    }

    public function company()
    {
        return $this->hasOne(Company::class,'id','company_id');
    }


    public function scopeCustomerPolicies($query, $cellphone)
    {
        return $query->with(['customer'])->where('cellphone', $cellphone)->first();
    }

    /* public function setSourceOfIncomeAttribute($sourceOfIncome)
    {
        $arr = json_encode($sourceOfIncome);
        return $arr;
    } */

    public function getSourceOfIncomeAttribute($sourceOfIncome)
    {
        $arr = json_decode($sourceOfIncome);
        return $arr;
    }

    /*
     * The physical column is `Insure` (capital I, see the
     * 2023_08_04_114843_add_customer_profile_columns migration) but the whole
     * app — forms (wire:model="customer_profile.insure"), the
     * `customer_profile.insure` => 'required' validation rule, and reads like
     * AddWizard — use the lowercase `insure`. Eloquent attribute access is
     * case-sensitive on the array key, so $model->insure read NULL on any
     * record loaded from the DB (which returns the key as `Insure`). That made
     * the required rule fail for every Person profile and silently abort
     * saveStep1() before it reached the premium_freq / policy save. These
     * mutators keep a single stored attribute (`Insure`) while letting either
     * casing read and write it. Str::studly routes both `insure` and `Insure`
     * here, so there is no recursion (we touch $this->attributes directly).
     */
    public function getInsureAttribute()
    {
        return $this->attributes['Insure'] ?? null;
    }

    public function setInsureAttribute($value)
    {
        $this->attributes['Insure'] = $value;
    }

    //Function to update the customer profile for Pay.alphadirect.co.bw
    public function updateInfoPay($customer_id,$customerInfo)
    {
        $query = CustomerProfile::where('customer_id', $customer_id)->first();
        $query->dob = Carbon::parse(str_replace('/','-',$customerInfo->dob))->format('Y-m-d'); //before hotfix
        //$query->dob =  $customerInfo->dob; //after hotfix
        $query->omang = $customerInfo->omang;
        $query->gender = $customerInfo->gender;
        #$query->passport = $customerInfo->passport;
        $query->maritalstatus = $customerInfo->maritalstatus;
        $query->city = $customerInfo->city;
        $query->state = $customerInfo->state;
        $query->address = $customerInfo->address;
        $query->countryId = $customerInfo->countryId;
        $query->driving_license_number = $customerInfo->license_number;
        $query->license_valid_till = $customerInfo->license_valid_till_date;
         $query->e_name = $customerInfo->e_name;
         $query->emp_phone = $customerInfo->emp_phone;
         $query->emp_no = $customerInfo->emp_no;
         $query->salary_pay_date = $customerInfo->salary_pay_date;
        if(isset($customerInfo->sourceOfIncome))
        {

            $query->sourceOfIncome = $customerInfo->sourceOfIncome;
        }
        return $query->save();
    }

    public function formatDate($date){
        $date = Carbon::createFromFormat("d/m/Y", $date)->timestamp;
        $newDate = date("Y-m-d", $date);
        return $newDate;
    }


    //Function to update the customer profile for mobile app
    public function updateInfo($customerInfo)
    {
        $query                         = CustomerProfile::where('customer_id', $customerInfo->customer_id)->first();
        if(isset($customerInfo->dob)){$query->dob                    = $customerInfo->dob;}
        if(isset($customerInfo->omang)){$query->omang                  = $customerInfo->omang;}
        if(isset($customerInfo->passport)){$query->passport               = $customerInfo->passport;}
        if(isset($customerInfo->passportIssuingCountry)){ $query->countryId              = $customerInfo->passportIssuingCountry;}
        if(isset($customerInfo->gender)){$query->gender                 = $customerInfo->gender;}
        if(isset($customerInfo->address)){$query->address                = $customerInfo->address;}
        if(isset($customerInfo->state)){$query->state                  = $customerInfo->state;}
        if(isset($customerInfo->city)){$query->city                   = $customerInfo->city;}
        if(isset($customerInfo->maritalstatus)){$query->maritalstatus          = $customerInfo->maritalstatus;}
        if(isset($customerInfo->driving_license_number)){$query->driving_license_number = $customerInfo->driving_license_number;}
        if(isset($customerInfo->license_valid_till)){$query->license_valid_till     = $customerInfo->license_valid_till;}
        if(isset($customerInfo->e_name)){$query->e_name                 = $customerInfo->e_name;}
        if(isset($customerInfo->emp_no)){$query->emp_no                 = $customerInfo->emp_no;}
        if(isset($customerInfo->emp_phone)){$query->emp_phone              = $customerInfo->emp_phone;}
        if(isset($customerInfo->salary_pay_date)){$query->salary_pay_date        = $customerInfo->salary_pay_date;}

        if(isset($customerInfo->sourceOfIncome))
        {

            $query->sourceOfIncome = $customerInfo->sourceOfIncome;
        }
        return $query->save();

    }

    public function checkCustomerIfExist($omang,$passport){
    //check for omang and passport

            if ($omang != null && $passport != null) {
                $profile = CustomerProfile::where('omang', $omang)->orWhere('passport', $passport)->orderBy('id', 'desc')->first(array('customer_id'));
            } elseif ($omang == null && $passport != null) {
                $profile = CustomerProfile::where('passport', $passport)->orderBy('id', 'desc')->first(array('customer_id'));
            } elseif ($omang != null && $passport == null) {
                $profile = CustomerProfile::where('omang', $omang)->orderBy('id', 'desc')->first(array('customer_id'));
            }
            if($profile == null){

            }else{
                return $profile;
            }

    }

    public function scopeOnlyOrganisation($query) {
        $query->where('entity_type','Organisation');
    }
    //  public function setDobAttribute($value)
    // {
    //     return Carbon::parse($value)->format('Y-m-d');   
    // }
    // public function getDobAttribute($value)
    // {
    //     return Carbon::parse($value)->format('d-m-Y');   
    // }

  }
