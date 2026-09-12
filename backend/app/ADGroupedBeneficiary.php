<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
use AlphaDirect\Policy;

class ADGroupedBeneficiary extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table ='ad_grouped_policy_beneficiary';
    // protected $casts = [
    //     'dob' => 'datetime:Y-m-d',
    // ];

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

}
