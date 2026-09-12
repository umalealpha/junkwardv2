<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Arr;
class RealpayContractInstallments extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table ='realpay_contract_installments';
    protected $id ='id';
    protected $fillable = [];


    public function transformAudit(array $data): array
    {

        if (Arr::has($data, 'new_values')) {
            if($this->auditEvent != 'created'){
                if(isset($this->clientNumber)){
                    $ins = RealpayContractInstallments::where('InstalmentReferenceNumber',$this->InstalmentReferenceNumber)->first();
                    $policy = Policy::where('policyNumber', $this->clientNumber)->first();
                    if($policy != null){
                        $data['policy_id'] = $policy->id;
                        $data['policy_number'] = $policy->policyNumber;
                    }
                }
            }
        }

        return $data;

    }
    public function generateTags(): array
    {
        return [
            'Realpay Installments'.' '.$this->auditEvent,
        ];
    }
}
