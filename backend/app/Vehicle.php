<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
use AlphaDirect\Policy;

class Vehicle extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'vehicle';

    protected $fillable = [
        'user_id', 'policy_id', 'vehiclePlate', 'make', 'model', 'risk_id','year', 'front', 'back', 'right', 'left',
         'approval_requested_by',
        'approval_request_comment',
        'approval_status',
        'term_id',
        'action_id',
        'policy_id',
        'customer_id',
        'approved_at',
        'estimated_value',
        'vinnumber',
        'engineNo',
        'chassisNo',
        'financial_interest',
        'financial_interest_other',
        'is_private',
        'is_imported',
        'claim_count',
        'mileage',
        'condition',
        'seats',
    ];
    public function transformAudit(array $data): array
    {

        if (Arr::has($data, 'new_values')) {
          if($this->auditEvent != 'created'){
             $policy = Policy::where('id', $this->policy_id)->first();
             if($policy != null){
             $data['policy_id'] = $policy->id;
             $data['policy_number'] = $policy->policyNumber;
             }
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
            'Vehicle'.' '.$this->auditEvent,
        ];
    }

    /**
     * Find a live vehicle with the same plate already on the same policy + action
     * (regardless of risk address). A vehicle may sit on ONLY ONE risk address, so
     * callers use this to block re-adding it under another risk address before it is
     * removed from the first. Pass $ignoreVehicleId when editing an existing row.
     *
     * @return \AlphaDirect\Vehicle|null
     */
    public static function duplicateOnPolicyAction($vehiclePlate, $policyId, $actionId, $ignoreVehicleId = null)
    {
        return static::where('vehiclePlate', $vehiclePlate)
            ->where('policy_id', $policyId)
            ->where('action_id', $actionId)
            ->whereNull('deleted_at')
            ->when($ignoreVehicleId, function ($q) use ($ignoreVehicleId) {
                $q->where('id', '!=', $ignoreVehicleId);
            })
            ->first();
    }

    public function User()
    {

        return $this->belongsTo('AlphaDirect\User', 'user_id', 'id');

    }

    public function Policy()
    {

        return $this->belongsTo('AlphaDirect\Policy', 'policy_id', 'id');

    }

    public function inspectionPhotos()
    {
        return $this->hasOne('AlphaDirect\AgentPreInsepection');
    }

    public function updateInfo($vehicleInfo)
    {

        $query = Vehicle::where('id', $vehicleInfo->vehicle_id)->first();

        //$query->vehiclePlate = $vehicleInfo->vehiclePlate;

        return $query->save();

    }

    public function updateInfoStart($policy_id,$vehiclePlate)
    {

        $query = Vehicle::where('policy_id', $policy_id)->first();

        $query->vehiclePlate = $vehiclePlate;

        return $query->save();

    }

    public function scopePolicyId($query,$policy_id){
        $query->where('policy_id',$policy_id);
    }

    public function scopeTermId($query,$term_id){
        $query->where('term_id',$term_id);
    }

    public function scopeActionId($query,$action_id){
        $query->where('action_id',$action_id);
    }

    public function scopeEngineNo($query,$engineNo){
        $query->where('engineNo',$engineNo);
    }

    public function risk()
    {
        return $this->belongsTo('AlphaDirect\Models\RiskAddress', 'risk_id', 'id');
    }

}
