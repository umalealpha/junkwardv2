<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
use AlphaDirect\Policy;

class PolicyBeneficiary extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table ='policy_beneficiary';
    // protected $casts = [
    //     'dob' => 'datetime:Y-m-d',
    // ];

    public function risk()
    {
        return $this->belongsTo('AlphaDirect\Models\RiskAddress', 'risk_id', 'id');
    }

    public function transformAudit(array $data): array
    {
        if (Arr::has($data, 'new_values')) {
        if($this->auditEvent != 'created'){
        if(isset($this->policy_id)){
            $policy = Policy::where('id', $this->policy_id)->first();
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

    public function addBeneficiary($beneficiaryInfo,$beneficiaryData_dob){

        $query = new PolicyBeneficiary();
        if(isset($beneficiaryInfo->policy_id)){$query->policy_id = $beneficiaryInfo->policy_id;}
        if(isset($beneficiaryInfo->relation)){ $query->relation = $beneficiaryInfo->relation;}
        if(isset($beneficiaryInfo->first_name)){$query->first_name = $beneficiaryInfo->first_name;}
        $query->middle_name = isset($beneficiaryInfo->middle_name) && !empty($beneficiaryInfo->middle_name) ? $beneficiaryInfo->middle_name : '';

        if(isset($beneficiaryInfo->last_name)){ $query->last_name = $beneficiaryInfo->last_name;}
        if(isset($beneficiaryInfo->dob)){ $query->dob = Carbon::parse($beneficiaryInfo->dob)->format('Y-m-d');}
        if(isset($beneficiaryInfo->gender)){$query->gender = $beneficiaryInfo->gender;}
        if(isset($beneficiaryInfo->payment)){$query->payment = $beneficiaryInfo->payment;}
        if(isset($beneficiaryInfo->omang)){$query->omang = $beneficiaryInfo->omang;}

        if(isset($beneficiaryInfo->passport)){$query->passport = $beneficiaryInfo->passport;}
        if(isset($beneficiaryInfo->cellphone) && !empty($beneficiaryInfo->cellphone))
            $query->cellphone = $beneficiaryInfo->cellphone;
        if(isset($beneficiaryInfo->email) && !empty($beneficiaryInfo->email))
            $query->email = $beneficiaryInfo->email;
        if(isset($beneficiaryInfo->legalPassportExpiry)){$query->legalPassportExpiry = $beneficiaryInfo->legalPassportExpiry;}
        if(isset($beneficiaryInfo->legalOmangExpiry)){ $query->legalOmangExpiry = $beneficiaryInfo->legalOmangExpiry;}
        return $query->save();
    }

    public function updateBeneficiary($beneficiaryInfo){
        $query = PolicyBeneficiary::where('id', $beneficiaryInfo->beneficiary_id)->first();

        if(isset($beneficiaryInfo->relation) && !empty($beneficiaryInfo->relation))
            $query->relation = $beneficiaryInfo->relation;
        $query->first_name = $beneficiaryInfo->first_name;
        $query->middle_name = isset($beneficiaryInfo->middle_name) && !empty($beneficiaryInfo->middle_name) ? $beneficiaryInfo->middle_name : '';
        $query->last_name = $beneficiaryInfo->last_name;
        #$query->dob = Carbon::createFromFormat($beneficiaryInfo->dob)->toDateString();
        $query->dob = Carbon::parse($beneficiaryInfo->dob)->format('Y-m-d');
        $query->gender = $beneficiaryInfo->gender;
        $query->payment = $beneficiaryInfo->payment;
        $query->omang = $beneficiaryInfo->omang;
        $query->passport = $beneficiaryInfo->passport;
        if(isset($beneficiaryInfo->cellphone) && !empty($beneficiaryInfo->cellphone))
            $query->cellphone = $beneficiaryInfo->cellphone;
        if(isset($beneficiaryInfo->email) && !empty($beneficiaryInfo->email))
            $query->email = $beneficiaryInfo->email;
        // $query->legalPassportExpiry = $beneficiaryInfo->legalPassportExpiry;
        // $query->legalOmangExpiry = $beneficiaryInfo->legalOmangExpiry;
        return $query->save();
    }

    public function deleteBeneficiary($beneficiaryID){


        //Find beneficiary accoriding to the id
        $query = PolicyBeneficiary::where('id',$beneficiaryID)->first();

        //Handle beneficiaries that do not exist
        if($query == null){

            return $status = false;
        }

        //Execute the delete operation on the specific id
        $status = $query->delete();

        //Boolean of delete opperation
        return $status ;
    }

    public function scopePolicy($query,$policy_id){
        $query->where('policy_id',$policy_id);
    }

    public function scopeTerm($query,$term_id){
        $query->where('term_id',$term_id);
    }

    public function scopeAction($query,$action_id){
        $query->where('action_id',$action_id);
    }

    public function scopeFirstName($query,$first_name){
        $query->where('first_name',$first_name);
    }

    public function scopeMiddleName($query,$middle_name){
        $query->where('middle_name',$middle_name);
    }

    public function scopeLastName($query,$last_name){
        $query->where('last_name',$last_name);
    }

    public function scopeCellphone($query,$cellphone){
        $query->where('cellphone',$cellphone);
    }
}
