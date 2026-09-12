<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use AlphaDirect\Policy;
use OwenIt\Auditing\Contracts\Auditable;

class PolicyBundled extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $table ='policy_bundled';

    // public function Product() {
    //     return $this->belongsTo('AlphaDirect\Product', 'product_id')
    //                     ->select('id', 'name');
    // }

    public function transformAudit(array $data): array
    {

        if (Arr::has($data, 'new_values')) {
        if($this->auditEvent != 'created'){
            
       if(isset($this->policy_id)){
            $policy = Policy::where('id', $this->policy_id)->first();
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
            'Bundled product'.' '.$this->auditEvent,
        ];
    }
    
  
}
