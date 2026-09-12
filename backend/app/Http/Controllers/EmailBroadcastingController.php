<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;

use AlphaDirect\User;
use DB;


class EmailBroadcastingController extends Controller
{


    public function email(Request $request){


        switch($request->group){


            case 'allClients':


            $customers =  DB::table('user_roles')
                 ->leftJoin('users','users.id', '=', 'user_roles.user_id')
                 ->where('role_id',5)  
                 ->select(
                 'users.email'
                )
            ->get();


            return response()->json($customers);

            break;


            case 'allStaff':

            $alphas =  DB::table('user_roles')
            ->leftJoin('users','users.id', '=', 'user_roles.user_id')
            ->where('role_id', '=', 1)  
            ->orWhere('role_id', '=', 2)  
            ->orWhere('role_id', '=', 3)  
            ->orWhere('role_id', '=', 4)  
            ->select(
            'users.email'
             )
             ->get();


            return response()->json($alphas);

            break;

            case 'HOD':

            $hod =  DB::table('user_roles')
            ->leftJoin('users','users.id', '=', 'user_roles.user_id')
            ->where('role_id', '=', 2)  
            ->select(
            'users.email'
             )
             ->get();

             return response()->json($hod);

            break;


            case 'agents':

            $hod =  DB::table('user_roles')
            ->leftJoin('users','users.id', '=', 'user_roles.user_id')
            ->where('role_id', '=', 3)  
            ->select(
            'users.email'
             )
             ->get();

             return response()->json($hod);

            break;

            case 'ClaimsAgent':

            $hod =  DB::table('user_roles')
            ->leftJoin('users','users.id', '=', 'user_roles.user_id')
            ->where('role_id', '=', 4)  
            ->select(
            'users.email'
             )
             ->get();

             return response()->json($hod);

            break;

        }
    }
}
