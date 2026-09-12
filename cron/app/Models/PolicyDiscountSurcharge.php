<?php

namespace AlphaDirect\Models;

use AlphaDirect\Policy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DB;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;


class PolicyDiscountSurcharge extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'policy_discount_surcharge';
    protected $fillable = [];
    protected $dates = [];
    public function transformAudit(array $data): array
    {

        if (Arr::has($data, 'new_values')) {

        if(isset($this->policy_id)){
           $policy = Policy::where('id', $this->policy_id)->first();
           $data['policy_id'] = $this->policy_id;
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
            'discount surcharge'.' '.$this->auditEvent,
        ];
    }



    public static function addLog($data){
        try{
            $log                 = new PolicyDiscountSurcharge();
            $log->policy_id      = $data['policy_id'];
            $log->discount       = number_format((float)$data['discount'],2,'.','');
            $log->surcharge      = number_format((float)$data['surcharge'],2,'.','');
            $log->old_value      = number_format((float)$data['old_value'],2,'.','');
            $log->new_value      = number_format((float)$data['new_value'],2,'.','');
            $log->total_dis_surc = number_format((float)$data['total_dis_surc'],2,'.','');
            $log->old_sumInsured = isset($data['old_sumInsured']) ? number_format((float)$data['old_sumInsured'],2,'.','') : null;
            $log->custom_rate_per = isset($data['custom_rate_per']) ? number_format((float)$data['custom_rate_per'],2,'.','') : null;
            $log->custom_rate_flat  = isset($data['custom_rate_flat']) ? number_format((float)$data['custom_rate_flat'],2,'.','') : null;
            $log->custom_reason    = isset($data['custom_reason']) ? $data['custom_reason'] : null;
            $log->ip_address     = $data['ip_address'];
            $log->user_id        = $data['user_id'];
            $log->save();

            return true;
            // $setPremium = self::setPremium($data);
            // if($setPremium == true){
            //     return true;
            // }else{
            //     return false;
            // }
        }catch(\Exception $ex){
            return false;
        }
    }

    public static function setPremium($data){
        try{

            $policy = Policy::where('id',$data['policy_id'])->first(array('id','premium_freq','premium'));
            if($policy){
                switch($policy->premium_freq){
                    case 1:
                        $premium = ($data['new_value'] / 12) * 1.08;
                        break;
                    case 2:
                        $premium = ($data['new_value']) / 3;
                        break;
                    case 3:
                        $premium = ($data['new_value']);
                        break;
                }

                DB::select(DB::raw("update policies set premium = ".$premium." where id =" .$data['policy_id']));
                return true;
            }else{
                return false;
            }
        }catch(\Exception $ex){
            //DB::rollback();
            return false;
        }
    }
}
