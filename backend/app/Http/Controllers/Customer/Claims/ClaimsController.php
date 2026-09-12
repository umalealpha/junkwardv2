<?php

namespace AlphaDirect\Http\Controllers\Customer\Claims;

use Illuminate\Http\Request;
use AlphaDirect\Vehicle;
use AlphaDirect\User;
use AlphaDirect\Notifications;
use AlphaDirect\GlassClaim;
use AlphaDirect\IncidentPhoto;
use DB;
use AlphaDirect\Http\Controllers\Controller;


class ClaimsController extends Controller
{

// method to create claim
// return JSON
 public function createClaim (Request $request){

    $customerVehicle = Vehicle::where('vehiclePlate',$request->vehiclePlate)->first();

    $staffMembers =  DB::table('users')
                                        ->leftJoin('user_roles','users.id', '=', 'user_roles.user_id')
                                        ->where('role_id',4)
                                        ->inRandomOrder()
                                        ->first();
                                        
    $glassClaim = new GlassClaim;
        $glassClaim->user_id = $customerVehicle->user_id;
        $glassClaim->agent_id = $staffMembers->id;
        $glassClaim->policy_id = $customerVehicle->policy_id;
        $glassClaim->damageExtent = $request->damageExtent;
        $glassClaim->glassType = $request->glassType;
        $glassClaim->brokenSize = $request->brokenSize;
        $glassClaim->incidentDate = $request->incidentDate;
        $glassClaim->thirdPartyName = $request->thirdPartyName;
        $glassClaim->thirdPartyaddress = $request->thirdPartyaddress;
        $glassClaim->claimStep = 1;
        $glassClaim->save();

    $incidentPhotos = new IncidentPhoto;
        $incidentPhotos->claim_id = $glassClaim->id;
        $incidentPhotos->front = null;
        $incidentPhotos->back = null;
        $incidentPhotos->right = null;
        $incidentPhotos->left = null;
        $incidentPhotos->save();

    $agentNotifications = new Notifications;
        $agentNotifications->user_id = $staffMembers->id;
        $agentNotifications->type = 'Customer Glass Claim';
        $agentNotifications->data = 'You have been assigned a claim.';
        $agentNotifications->action = 'viewClaim/';
        $agentNotifications->save();

    return response()->json(['message'=>'Claim successfully submitted']);
  }
}