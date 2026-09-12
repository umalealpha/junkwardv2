<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use OwenIt\Auditing\Contracts\Auditable;

class PolicyCoveredPerson extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;
    protected $table = "policy_covered_persons";

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
        return $data;
    }
}
