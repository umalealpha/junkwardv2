<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Support\Arr;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use AlphaDirect\Policy;

class PaymentEmail extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
  
    protected $auditTimestamps = true;
    protected $table = 'payment_email';
    protected $guarded = [];
     public function transformAudit(array $data): array
    {
       //dd( $this);
        if (Arr::has($data, 'new_values')) {
             $policy = Policy::where('policyNumber', $this->policyNumber)->first();
             if($policy){
                $data['policy_id'] = $policy->id;
                $data['policy_number'] = $policy->policyNumber;
             }else{
                $data['policy_id'] = null;
                $data['policy_number'] = null;
             }
             
       }
        return $data;
    }
    
    public function generateTags(): array
    {
        
            return [

               'payment_email'.' '.$this->auditEvent,
            ];
    
    } 
}
