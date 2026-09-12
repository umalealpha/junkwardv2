<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
use AlphaDirect\CustomerProfile;
use Illuminate\Support\Facades\DB;
use AlphaDirect\Policy;
class KYC extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use \Illuminate\Database\Eloquent\Factories\HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'customer_kyc';

    protected $fillable = [
        'customer_id', 'driversLicense', 'omang','omangBack', 'vehicleRegistration','compliance','passportIssuingCountry','omangNumber','passport',
        'omang_front', 'omang_back', 'passport_front_image', 'passport_back','passportBack','bank_statement_file_path',
        'drivers_license_front_image', 'driving_license_back', 'proof_of_address_image','status','driversLicenseStatus','omangFrontStatus','omangBackStatus',
        'passportStatus','passportBackStatus','proof_of_address_status','vehicleRegistrationStatus',
        'bank_statement_file_path', 'debit_authorization_form', 'bankStatementFileStatus', 'bankStatementFileRemark'
    ];
    public function transformAudit(array $data): array
    {
      
        if (Arr::has($data, 'new_values')) {
        if($this->auditEvent != 'created'){
        if(isset($this->customer_id)){

            $policy = Policy::where('customer_id', $this->customer_id)->first();
            if($policy != null){
            $data['policy_id'] = $policy->id;
            $data['policy_number'] = $policy->policyNumber;
     }}
    }
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
            'Customer KYC'.' '.$this->auditEvent,
        ];
    }

    public function User()
    {
        return $this->belongsTo('AlphaDirect\User');
    }

    public function Customer()
    {
        return $this->belongsTo('AlphaDirect\Customer', 'customer_id');
    }

}
