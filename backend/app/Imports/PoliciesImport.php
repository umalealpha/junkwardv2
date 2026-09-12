<?php

namespace AlphaDirect\Imports;

use AlphaDirect\Policy;
use Maatwebsite\Excel\Concerns\ToModel;
use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\KYC;
use AlphaDirect\UserPassword;
use Illuminate\Support\Str;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Region;
use AlphaDirect\Productplan;
use Carbon\Carbon;
use AlphaDirect\Product;
use AlphaDirect\Events\CommissionPolicyEvent;
use AlphaDirect\Events\DecrementCounterIfPolicySellsEvent as DecrementCounter;
use AlphaDirect\Vehicle;
use AlphaDirect\CustomerBanking;
use AlphaDirect\PolicyBeneficiary;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use AlphaDirect\Models\CompanyName;
use AlphaDirect\Models\CompanyPolicy;
use Log;
use AlphaDirect\Helper;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class PoliciesImport implements ToModel,  WithHeadingRow, WithChunkReading
{
    protected $upload_id;

    public function __construct($upload_id)
    {
        $this->upload_id = $upload_id;
    }
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
       
       
       //try{
            $customer =Customer::where('cellphone',$row['phone'])->first();
            if($customer){
            $policy = Policy::where('customer_id',$customer->id)->where('product_id',4)
            ->where('status','!=',2)->first();
            if($policy){
               
                return null; 
            }
            }
            $dateOfBCheck = Carbon::parse($row['dob'])->format('Y-m-d');
                $totalYears = Carbon::parse($dateOfBCheck)->age;
            if ($totalYears < 18 ) {
               
                return null;
            }
           
            
            if ($row['maritalstatus'] == 2) {
                if ($row['legalfname'] == null || $row['legalfname'] == '') {
                   
                    return null;
                }
                if ($row['legallname'] == null || $row['legallname'] == '') {
                  
                    return null;
                }
                if ($row['legalphone'] == null || $row['legalphone'] == '') {
                   
                    return null;
                }
            }
           
           
            $customer =  $this->customerCreate($row);
           
            $policy =  $this->policyCreate($row,$customer);
           
            $company =  $this->companyCreate($row,$policy);
            return $policy;
        // } catch (\Exception $e) {
        //     \Log::error('Error processing row: ' . $e->getMessage(), ['row' => $row]);
        //     return null; 
        // }
    }
    public function customerCreate($row)
    {
        
        $customer =Customer::where('cellphone',$row['phone'])->first();
        if(!$customer){
           $customer             = new Customer();
        }
        $customer->firstName  = $row['firstname'];
        $customer->middlename = $row['middlename'];
        $customer->lastName   = $row['lastname'];
        $customer->email      = $row['email'];
        $customer->cellphone  = $row['phone'];
        $customer->save();
       

        $token                  = Str::random(8);
        $user_password          = new UserPassword();
        $user_password->user_id = $customer->id;
        $user_password->token   = $token;
        $url                    = env('LIVEQUOTE_URL') . 'reset_password_first_time.php?token=' . $token;
        $user_password->url     = $url;
        $user_password->save();

        $ifExists = CustomerProfile::where('customer_id',$customer->id)->exists();
        if(!empty($ifExists)){
            $profile = CustomerProfile::where('customer_id',$customer->id)->first();
        }else{
            $profile = new CustomerProfile();
        }
        $profile->customer_id     = $customer->id;
        $profile->gender      = $row['gender'] == 'male' ? 1:0 ;
        $profile->address     = $row['address'];
        $profile->city        = isset($row['city']) &&  $row['city'] != '' ? $row['city'] : "";
        $profile->state       = $row['state'];
        $profile->omang       = $row['idtype'] == 'Omang' ? $row['idvalue'] : "";
        $profile->passport    = $row['idtype'] == 'Passport' ? $row['idvalue'] : "";
        $profile->countryId   = $row['passportissuingcountry'];
        $profile->maritalstatus = isset($row['maritalstatus']) && $row['maritalstatus'] != '' ? $row['maritalstatus'] : "";
            
        $profile->e_name         = isset($row['e_name']) ? $row['e_name'] : "";
        $profile->emp_no         = isset($row['emp_no']) ? $row['emp_no'] : "";
        $profile->emp_phone      = isset($row['emp_phone']) ? $row['emp_phone'] : "";
        $salary_date             = isset($row['salary_pay_date']) ? date('Y-m-d', strtotime(str_replace('/', '-', $row['salary_pay_date']))) : null;
        $profile->salary_pay_date = isset($salary_date) ? $salary_date : "";
        $profile->dob         =  Carbon::parse($row['dob'])->format('Y-m-d');//date('Y-m-d', strtotime(str_replace('/', '-', $row['dob'])));
        $profile->driving_license_number = isset($row['dLicense']) ? $row['dLicense'] : "";
        if (isset($row['license_valid_till'])) {
            $profile->license_valid_till = date('Y-m-d', strtotime(str_replace('/', '-', $row['license_valid_till'])));
        }
        if (isset($row['sourceofincome'])) {
            $profile->sourceOfIncome = json_encode($row['sourceofincome']);
        }
        $profile->save();

        $customerKYC = KYC::where('customer_id', $customer->id)->first();
        if ($customerKYC == null) {
            $customerKYC = new KYC();
        }
        $customerKYC->customer_id = $customer->id;
        $customerKYC->save();
        \Log::info(json_encode($customer));
        return $customer; 
    }
    public function policyCreate($row,$customer)
    {
        $product = Product::where('id', 4)->first(array('has_vehicle', 'has_member', 'premium_type_id', 'limit', 'kyc_recipient', 'kyc_customer', 'preinspection', 'is_motor_items', 'region_id', 'has_activation_code', 'type'));
        $latest = Policy::orderBy('id', 'desc')->first(array('id'));
            if ($latest == null) {
                $latest = collect();
                $latest->id = 1;
            }
        $policy = new Policy();
        $policy->customer_id = $customer->id;
        $policy->agent_id = $row['agent_id'] != "" ? $row['agent_id'] : "0";
        if(isset($row['agent_id']) && $row['agent_id'] != "" ){
            $agent = \AlphaDirect\User::where('id',$row['agent_id'])->where('agency_id','!=',null)->first(['agency_id']);
            if($agent){
                 $policy->agency_id = $agent->agency_id;
            }
        }
        $policy->storeID = isset($row['store']) ? $row['store'] : '';

        $policy->product_id = 4;
        $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
        if ($product->premium_type_id == 11) {
            $product_plan = Productplan::where('id', 18)->first(array('sum_assured', 'premium', 'slug', 'flutter_plan_id'));
            $premium = round(($product_plan->premium * ($regionVat / 100)) + $product_plan->premium, 2);
            $policy->plan_id = 18;
            $policy->sum_assured = $product_plan->sum_assured;
            $policy->premium = round($premium, 2);
            $policy->vat = round($product_plan->premium * ($regionVat / 100), 2);
            $policy->vat_percent = $regionVat;
        } else {
            $premium = ($row['premium'] * ($regionVat / 100)) + $row['premium'];
            $policy->premium = $premium;
            $policy->vat = $row['premium'] * ($regionVat / 100);
            $policy->sum_assured = $row['sum_assured'];
        }

        $policy->policyNumber         = 'SCHD' . Carbon::now()->year . str_pad(($latest->id + 1), 6, '0', STR_PAD_LEFT);
        $policy->status               = 1;
        $policy->leadSource           = "excel";
        $policy->has_vehicle          = $product->has_vehicle;
        $policy->has_member           = $product->has_member;
        $policy->preinspection        = $product->preinspection;
        $policy->is_motor_items       = $product->is_motor_items;
        $policy->limit                = $product->limit;
        $policy->kyc_customer         = $product->kyc_customer;
        $policy->kyc_recipient        = $product->kyc_recipient;
        $policy->billingStartDate     = Carbon::now()->format('Y-m-d');
        $policy->ori_billingStartDate     = Carbon::now()->format('Y-m-d');
        $policy->BillingStart = "Immediate";
        $policy->is_sys_act_generated = null;
        $policy->billing_day = Carbon::now()->format('d');
        $policy->policyActivatedDate = Carbon::now()->format('Y-m-d');
        
        $saved = $policy->save();
        event(new \AlphaDirect\Events\policyLifecycle($policy->id, "Create"));
        
        if ($saved == true && $policy->agent_id != null) {
            $data = [
                'id' => $policy->id,
                'agent_id' => $policy->agent_id,
                'status' => $policy->status,
            ];
            event(new CommissionPolicyEvent($data));
        }

        if ($saved == true) {
            $data = [
                'store_id' => $policy->storeID,
                'product_id' => $policy->product_id,
                'plan_id' => $policy->plan_id,
            ];
            event(new DecrementCounter($data));
        }
      
        if ($product->type == 'Legal') {

            if ($row['maritalstatus'] == '2') {
             try{
                $b = new PolicyBeneficiary();
                $b->policy_id = $policy->id;
                $b->relation = 'Spouse';
                $b->first_name = isset($row['legalfname']) ? $row['legalfname'] : "";
                $b->middle_name = isset($row['legalmname']) ? $row['legalmname'] : "";
                $b->last_name = isset($row['legallname']) ? $row['legallname'] : "";
                $b->cellphone = isset($row['legalphone']) ? $row['legalphone'] : "";
                $b->email = isset($row['legalemail']) ? $row['legalemail'] : "";
                $b->passport = isset($row['legalpassport']) ? $row['legalpassport'] : "";
                $b->omang = isset($row['legalomang']) ? $row['legalomang'] : "";
                $b->gender = isset($row['legalgender']) ? $row['legalgender'] : "";
                $b->legalOmangExpiry = isset($row['omangexpiry']) ? $row['omangexpiry'] : "";
                $b->legalPassportExpiry = isset($row['passportexpiry']) ? $row['passportexpiry'] : "";
                if (($row['legaldob'] != null) || ($row['legaldob'] != '')) {
                    $b->dob = Carbon::parse($row['legaldob'])->format('Y-m-d');
                }
                $b->save();
                } catch (\Exception $e) {
                
                }
            }

        }
        $banking                = new CustomerBanking();
        $banking->customer_id   = $customer->id;
        $banking->policy_id     = $policy->id;
        $banking->accountNumber =  null;
        $banking->billing       = "CASH";
        $banking->save();
        Helper::addInvoice($policy->id,$policy->premium,Carbon::now()->format('Y-m-d'));
        return $policy;  
    }
    public function companyCreate($row,$policy)
    {
        $comName =  CompanyName::where('name',$row['companyname'])->first();
        if(!$comName){
                $comName =new CompanyName();
                $comName->name = $row['companyname'];
                $comName->save();
        }
        $compPolicy = new CompanyPolicy();
        $compPolicy->company_id = $comName->id;
        $compPolicy->policyNumber = $policy->policyNumber;
        $compPolicy->upload_id = $this->upload_id;
        $compPolicy->save();
        return $compPolicy;
    }
    public function chunkSize(): int
    {
        return 100;  // Process 500 rows at a time
    }
  
}
