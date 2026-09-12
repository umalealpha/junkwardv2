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
    protected $fillable = [
        'id' ,
        'customer_id',
        'agent_id' ,
        'policy_id'  ,
        'policy_action_id',
        'supplier_id',
        'claim_type',
        'claim_number' ,
        'note' ,
        'invoice' ,
        'customer_selected',
        'category' ,
        'po' ,
        'status' ,
        'ip' ,
        'machine_data' ,
        'created_by',
        'created_at',
        'updated_at' ,
        'document_1' , 
        'document_2' ,
        'document_3' ,
        'closed_note' ,
        'registered_claim',
        'reopen_claim_sub_status' ,
        // Working sub-status shown when a claim is in the 'Open' main status
        // (set via ClaimsV2 updateStatus). Distinct from claim_accident.claim_sub_status.
        'claim_sub_status' ,

        // Extended fields (added 2026-04-16 via add_claim_detail_fields migration)
        'incident_date', 'incident_time', 'incident_location', 'incident_description',
        'type_of_loss', 'event_name', 'is_motor_claim', 'vehicle_plate',
        'catastrophe_loss', 'attorney_involved', 'co_attorney_involved', 'dfs_complaint',
        'recovery_involved', 'third_party_insured_elsewhere', 'driver_as_insured',
        'weather_condition', 'fault_party', 'reason',
        'reported_by', 'reported_date', 'form_template', 'claim_sub_type',
    ];



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

    public function policy()
    {
        return $this->belongsTo('AlphaDirect\Policy', 'policy_id');
    }

    public function customer()
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


    public static function queryForClaimCountBetweenDates($startDate, $endDate){
        $startDate = $startDate->format('Y-m-d');
        $endDate = $endDate->format('Y-m-d');
        return Claim::whereBetween( 'created_at', [$startDate." 00:00:00",$endDate." 23:59:59"])
                        ->orderBy('id','desc')
                        ->count();
    }

    public static function queryForHighRiskClaimCountBetweenDates($startDate, $endDate){
        $startDate = $startDate->format('Y-m-d');
        $endDate = $endDate->format('Y-m-d');
        return Claim::leftJoin('customer', function($join) {
                        $join->on('claims.customer_id', '=', 'customer.id');
                    })
                    ->where('customer.customer_category',2)
                    ->whereBetween( 'claims.created_at', [$startDate." 00:00:00",$endDate." 23:59:59"])
                    ->orderBy('claims.id','desc')
                    ->count();
    }


    public static function queryForHighRiskClaimCountWithStatusBetweenDates($startDate,$endDate,$status=null){
        $startDate = $startDate->format('Y-m-d');
        $endDate = $endDate->format('Y-m-d');
        return Claim::select('claims.id','claims.customer_id','claims.policy_id','claims.created_at','customer.customer_category')
                        ->leftJoin('customer', function($join) {
                            $join->on('claims.customer_id', '=', 'customer.id');
                        })
                        ->where('customer.customer_category',2)
                        ->where('claims.status',$status)
                        ->whereBetween( 'claims.created_at', [$startDate." 00:00:00",$endDate." 23:59:59"])
                        ->orderBy('claims.id','desc')
                        ->count();
    }

    public function scopePolicyId($query,$policy_id){
        $query->where('policy_id',$policy_id);
    }

}
