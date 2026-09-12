<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
use AlphaDirect\Policy;

class Vehicle extends Model /* implements Auditable */
{
    //use \OwenIt\Auditing\Auditable;
   // use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'vehicle';

    protected $fillable = [
        'user_id', 'policy_id', 'vehiclePlate', 'make', 'model', 'year', 'front', 'back', 'right', 'left',
    ];
   /* public function transformAudit(array $data): array
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
    } */

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
}
