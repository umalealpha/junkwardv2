<?php
namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use  AlphaDirect\Country;
use Illuminate\Support\Arr;
use OwenIt\Auditing\Contracts\Auditable;
class Policy extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'policies';
    protected $dates = ['policyActivatedDate'];

    //adds in agent id to the data while saving audits
    public function transformAudit(array $data): array
    {

        if (Arr::has($data, 'new_values.agent_id')) {
            $data['agent_id'] = $data['new_values']['agent_id'];
        }
        if($this->auditEvent != 'created'){
       if (Arr::has($data, 'new_values')) {
            $data['policy_id'] = $this->id;
            $data['policy_number'] = $this->policyNumber;
        }
    }

        //to store customer as a causer in case of APP/API call
        // if (Arr::has($data, 'new_values.lead_source')) {
        //     $data['customer_id'] = $data['new_values']['customer_id'];
        // }
        return $data;
    }
    //adds in activity tag to the data while saving audits
    public function generateTags(): array
    {

        if($this->status == '2'){
            return [
                'Policy'.' '.'Cancelled',
            ];
        }else{
            return [
                'Policy'.' '.$this->auditEvent,
            ];
        }
    }

    public function scopePolicy($query,$policy_id){
        return $query->where('id', $policy_id);
    }

    public function policyrenewal()
    {
        return $this->belongsTo('AlphaDirect\PolicyRenewal', 'policyNumber', 'policyNumber');
    }

    public function transaction(){
        return $this->belongsTo('AlphaDirect\PaymentTransaction','policyNumber','policyNumber');
    }

    public function customer()
    {
        return $this->belongsTo('AlphaDirect\Customer', 'customer_id', 'id')->select('id', 'firstName', 'lastName', 'middleName','cellphone','email');
    }
    public function profile()
    {
        return $this->belongsTo('AlphaDirect\CustomerProfile', 'customer_id', 'customer_id')->select('id', 'customer_id', 'omang', 'passport', 'gender','maritalstatus','countryId','dob','state','city','address','driving_license_number','license_valid_till', 'e_name', 'emp_phone', 'emp_no','salary_pay_date');
    }

    public function activation()
    {
        return $this->hasOne('AlphaDirect\Activation', 'activation_code', 'activation_code')->select();
    }

    public function kyc()
    {
        return $this->hasOne('AlphaDirect\KYC', 'customer_id', 'customer_id')->select('customer_id', 'compliance', 'driving_license', 'omang', 'proof_residence', 'proof_income', 'passport');
    }

    public function product()
    {
        return $this->hasOne('AlphaDirect\Product', 'id', 'product_id')->select('id', 'name');
    }
    public function plan()
    {
        return $this->hasOne('AlphaDirect\Productplan', 'id', 'plan_id')->select();
    }
    public function vehicle()
    {
        return $this->hasOne('AlphaDirect\Vehicle')->select();
    }

    public function policyMembers()
    {
        return $this->hasMany('AlphaDirect\PolicyMember')->select();
    }

    public function policyBeneficiaries()
    {
        return $this->hasMany('AlphaDirect\PolicyBeneficiary')->select();
    }

    public function devices()
    {
        return $this->hasMany('AlphaDirect\PolicyCellPhone')->select();
    }

    public function claim()
    {
        return $this->hasMany('AlphaDirect\Claim')->select();
    }

    public function transactions()
    {
        return $this->hasMany('AlphaDirect\PaymentTransaction', 'policyNumber', 'policyNumber')->select()->orderBy('id', 'DESC');
    }

    public function user()
    {
        return $this->belongsTo('AlphaDirect\User', 'agent_id', 'id')->select('id', 'firstName', 'lastName');
    }

    public function scheduleTransactions()
    {
        return $this->hasMany('AlphaDirect\Policy', 'policy_id', 'id');
    }

    public function scopeFindPolicy($query, $policyNumber)
    {
        return $query->with(['profile', 'customer', 'product', 'policyBeneficiaries', 'vehicle', 'transactions','devices'])
            ->where('policyNumber', $policyNumber)
            ->first();
    }

    public function scopeFindClaim($query, $policyNumber)
    {
        return $query->with(['claim'])
        ->where('policyNumber', $policyNumber)
        ->where($this->claim->claim_type, 'Cellphone')
        ->get();
    }

    public function scopePolicyDetail($query, $policyNumber)
    {
        return $query->with(['profile', 'customer', 'product', 'policyBeneficiaries', 'vehicle', 'transactions', 'devices'])
            ->where('policyNumber', $policyNumber)
            ->first();
    }

    public function getPolicyDataByPolicyNumber($policyNumber)
    {
        return $this->where('policyNumber', $policyNumber)->first();
    }

    public function getPolicyDataById($id)
    {
        return $this->where('id', $id)->orWhere('policyNumber', $id)->first();
    }

    public function scopeFindPolicyWithCustomer($query, $policyNumber)
    {
        return $query->with(['profile', 'customer', ])
            ->where('policyNumber', $policyNumber)
            ->first();
    }

    public function scopeGetPolicyDataByPolicyNumberWithCustomerData($query, $policyNumber)
    {
        return $query->with(['customer'])
            ->where('policyNumber', $policyNumber)
            ->first();
    }

    public function quote()
    {
        return $this->hasOne('AlphaDirect\Quote','quoteCode', 'quoteNumber')->select();
    }

    public static function updateInfo($data){
//        Policy::disableAuditing();
        $policyData = Policy::where('id',$data['id'])->first();

        foreach($data as $key=>$d){
            $policyData->$key = $d;
        }
        $policyData->save();
    }
}
