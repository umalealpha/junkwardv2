<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use DB;
use Illuminate\Support\Arr;
use AlphaDirect\Policy;

class PolicyPremiumLogs extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table ='policy_premium_logs';
    protected $fillable = [];
    protected $guarded = ['id'];

    public function transformAudit(array $data): array
    {
     // dd($this);
       // if (Arr::has($data, 'new_values')) {
       // if(isset($this->customer_id)){
       //     $policy = Policy::where('customer_id', $this->customer_id)->first();
       //     $data['policy_id'] = $policy->id;
        //    $data['policy_number'] = $policy->policyNumber;
       //    }
      //  }
        //to store customer as a causer in case of APP/API call
        // if (Arr::has($data, 'new_values.lead_source')) {
        //     $data['customer_id'] = $data['new_values']['customer_id'];
        // }

        return $data;

    }

    public function generateTags(): array
    {
        return [
            'policy term'.' '.$this->auditEvent,
        ];
    }

}
