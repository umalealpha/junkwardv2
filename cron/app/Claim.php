<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use DB;
use Illuminate\Support\Arr;
use AlphaDirect\Policy;

class Claim extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'claims';
    public function transformAudit(array $data): array
    {
     
        if (Arr::has($data, 'new_values')) {
        if(isset($this->policy_id)){
            $policy = Policy::where('id', $this->policy_id)->first();
            $data['policy_id'] = $policy->id;
            $data['policy_number'] = $policy->policyNumber;
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
            'Claims'.' '.$this->auditEvent,
        ];
    }

    protected function Policy()
    {
        return $this->belongsTo('AlphaDirect\Policy', 'policy_id');
    }

    protected function customer()
    {
        return $this->belongsTo('AlphaDirect\Customer', 'customer_id');
    }

    protected function transactions()
    {

        return $this->hasMany('AlphaDirect\Transaction');
    }

    public static function getTotalClaimAmnt($policyId){
        $amount = DB::select(DB::raw('select sum(crc.reserve_amt) as amount from claim_reserves_coverages crc
inner join claims c on crc.claim_id= c.id
inner join policies p on p.id= c.policy_id
where c.policy_id ='.$policyId));

        return $amount;
    }

}
