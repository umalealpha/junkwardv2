<?php

namespace AlphaDirect\Models;

use AlphaDirect\Policy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
class ExpiredPoliciesImportJobs extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;

    protected $table = 'expired_policies_import';
    protected $guarded = ['id'];


    public function transformAudit(array $data): array
    {

        if (Arr::has($data, 'new_values')) {
            if($this->auditEvent != 'created'){
                if(isset($this->policyNumber)){
                    $expired_policies_import = ExpiredPoliciesImportJobs::where('policyNumber',$this->policyNumber)->first();
                    if($expired_policies_import != null){
                        $data['policy_id'] = $expired_policies_import->policy_id;
                        $data['policy_number'] = $expired_policies_import->policyNumber;
                    }
                }
            }
        }

        return $data;

    }
    public function generateTags(): array
    {
        return [
            'Expired Policies Import'.' '.$this->auditEvent,
        ];
    }


    public function policy()
    {
        return $this->hasOne('AlphaDirect\Policy', 'policyNumber', 'policyNumber');
    }
}
