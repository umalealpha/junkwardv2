<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
use AlphaDirect\Policy;

class HospitalCashbackCoapplicants extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table ='hospital_Cashback_coapplicants';

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

    // public function policy()
    // {
    //     return $this->belongsTo(Policy::class, 'policy_id', 'id');
    // }

    // Method to update a single coapplicant
    public function updateCoapplicant($coapplicantInfo)
    {
        $query = HospitalCashbackCoapplicants::where('id', $coapplicantInfo->coapplicant_id)->first();

        if (!$query) {
            return false;
        }

        if (isset($coapplicantInfo->coapplicantRelation) && !empty($coapplicantInfo->coapplicantRelation)) {
            $query->relation = $coapplicantInfo->coapplicantRelation;
        }

        $query->first_name = $coapplicantInfo->coapplicantFName;
        $query->last_name = $coapplicantInfo->coapplicantLName;

        if (isset($coapplicantInfo->coapplicantDOB) && !empty($coapplicantInfo->coapplicantDOB)) {
            $query->dob = Carbon::parse($coapplicantInfo->coapplicantDOB)->format('Y-m-d');
        }

        if (isset($coapplicantInfo->coapplicantGender) && !empty($coapplicantInfo->coapplicantGender)) {
            $query->gender = $coapplicantInfo->coapplicantGender;
        }

        if (isset($coapplicantInfo->coapplicantOmang) && !empty($coapplicantInfo->coapplicantOmang)) {
            $query->omang = $coapplicantInfo->coapplicantOmang;
        }

        if (isset($coapplicantInfo->coapplicantPassport) && !empty($coapplicantInfo->coapplicantPassport)) {
            $query->passport = $coapplicantInfo->coapplicantPassport;
        }

        return $query->save();
    }

    public function updateCoapplicants($coapplicants)
    {
        foreach ($coapplicants->coapplicants as $coapplicant) {
            $coapplicantData = is_array($coapplicant) ? (object) $coapplicant : $coapplicant;

            $coapplicantInfo = (object) [
                'coapplicant_id' => $coapplicantData->id ?? null,
                'coapplicantRelation' => $coapplicantData->coapplicantRelation ?? null,
                'coapplicantFName' => $coapplicantData->coapplicantFName ?? null,
                'coapplicantLName' => $coapplicantData->coapplicantLName ?? null,
                'coapplicantGender' => $coapplicantData->coapplicantGender ?? null,
                'coapplicantDOB' => $coapplicantData->coapplicantDOB ?? null,
                'coapplicantOmang' => $coapplicantData->coapplicantOmang ?? null,
                'coapplicantPassport' => $coapplicantData->coapplicantPassport ?? null,
            ];

            $this->updateCoapplicant($coapplicantInfo);
        }
    }

}
