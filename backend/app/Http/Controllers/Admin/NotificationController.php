<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Claim;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\Role;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use auth;
use AlphaDirect\User;
use AlphaDirect\Activity;

class NotificationController extends Controller
{
    public function GetNotification()
    {
        if(auth::user()->hasRole('Super Admin'))
        {
            $notifications = Activity::where('causer_id', '=', 1)->orderBy('id','DESC')->get();
            $count = Activity::where('causer_id', '=', 1)
                ->where('read_at',0)
                ->orderBy('id','DESC')->get()->count();
        }
        return response()->json(['notifications' => $notifications , 'count'=> $count]);
    }

    public function data(Request $request)
    {
        if(auth::user()->hasRole('Super Admin'))
        {
            $activity = Activity::where('id', $request->get('id'))->first();
            $activity->read_at = 1;
            $activity->save();
            $changes = $activity->changes;
            $date = $activity->created_at->format('Y-m-d');
            $time = $activity->created_at->format('H:i:s');
            $updateTime = $activity->updated_at->format('Y-m-d');
            $string = $activity->subject_type;
            $model = substr($string, strrpos($string, '\\') + 1);
            $user = User::where('id',$activity->causer_id)->first();
            $performedBy = $user->firstName.' '.$user->lastName;
            switch ($model)
            {
                case 'User':
                    $user = User::where('id',$activity->subject_id)->first();
                    $performedOn = $user->firstName.' '.$user->lastName;
                    break;

                case 'Role':
                    $role = Role::where('id',$activity->subject_id)->first();
                    $performedOn = 'Role: '.$role->name;
                    break;

                case 'Claim':
                    $claim = Claim::where('id',$activity->subject_id)->first();
                    $performedOn = $claim->claim_number;
                    break;

                case 'Policy':
                    $policy = Policy::where('id',$activity->subject_id)->first();
                    $performedOn = $policy->policyNumber;
                    break;

                case 'Product':
                    $product = Product::where('id',$activity->subject_id)->first();
                    $performedOn = $product->name;
                    break;

                case 'Productplan':
                    $productplan = Productplan::where('id',$activity->subject_id)->first();
                    $performedOn = $productplan->name;
                    break;

                default  :
                    $performedOn = $model;
            }
        }
        return response()->json(['activity' => $activity,'performedBy'=>$performedBy,'performedOn'=>$performedOn,'date'=>$date,'time'=>$time,'update'=>$updateTime]);
    }
}
