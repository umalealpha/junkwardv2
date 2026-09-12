<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
use AlphaDirect\Policy;


class CustomerBanking extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table ='customer_banking';

    protected $dates = ['created_at', 'updated_at', 'billingStartDate'];

    protected $fillable = [
        'customer_id','user_id','policy_id','bankName','branchCode','accountNumber','bank_statement_file_path','debit_authorization_form','bankStatementFileStatus','bankStatementFileRemark','debitAuthFileStatus','debitAuthFileRemark','bankingMethod','myzaka','orangeMoney','prefered', 'billing'
    ];
    // protected $gaurded = [];

    public function transformAudit(array $data): array
    {
      //dd($this->policy_id);
        if (Arr::has($data, 'new_values')) {

        if(isset($this->policy_id)){
           $policy = Policy::where('id', $this->policy_id)->first();
           if($policy != null){
            $data['policy_id'] = $policy->id;
           $data['policy_number'] = $policy->policyNumber;
         }}
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
            'Customer Banking'.' '.$this->auditEvent,
        ];
    }

    public function User(){

        return $this->belongsTo('AlphaDirect\User');

    }
    public function Policy(){

        return $this->belongsTo('AlphaDirect\Policy');

    }

}
