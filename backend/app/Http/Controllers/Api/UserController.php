<?php

namespace AlphaDirect\Http\Controllers\Api;

use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Services\AuthGate;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use AlphaDirect\User;
use Pnlinh\InfobipSms\Facades\InfobipSms;
class UserController extends Controller
{
    // method to verify user credentials for login
    // param: email
    // param: password
    // return JSON
    public function login(Request $request){
        if(Auth::attempt(['email' => request('email'), 'password' => request('password')]))
        {
            $user = Auth::user();

            // SSO-only policy (UAT 2026-05-26). Only admin-equivalent roles may
            // authenticate by password unless the emergency fallback is enabled.
            // Mirrors the gate on /api/v1/auth/login so the legacy /api/signIn
            // path used by the V1 mobile app is closed too.
            if (!AuthGate::shouldAllowPasswordLogin($user)) {
                Auth::logout();
                Log::info('[Api/UserController:legacy] password login blocked — SSO required', [
                    'email' => $user->email ?? null,
                ]);
                return response()->json(array_merge(['success' => false], AuthGate::ssoRequiredResponse()), 403);
            }

            if($user->active == 1){
                $updatetoken = User::where('id',$user->id)->first();
                //check for str declaration
                $updatetoken->auth_key =  Str::random(30);
                $updatetoken->save();

                $Role = User::with('roles')->where('id', $user->id)->first(); // get the current clicked user for edit purpose
                $userRole = '';

                if(Auth::user()->can('policy-Full List') == true){
                    $user = User::where('id',1)->first();
                    $access_all = 1;
                }else{
                    $access_all = 0;
                }


                if($Role->roles->count() > 0)
                    $userRole = $Role->roles[0]->name;

                $user_details = ['id'=>$updatetoken->id,'Name'=>$updatetoken->firstName.' '.$updatetoken->lastName,'auth_key'=>$updatetoken->auth_key,'role_name'=>$userRole,'graphite_login'=>$updatetoken->is_graphite_login,'report_login'=>$updatetoken->is_report_login,'agency_id'=>$updatetoken->agency_id,'access_all'=>$access_all];

                return response()->json([
                    'success'=>true,'userDetails'=>$user_details
                ],200);
            }else{
                return response()->json(['error'=>'Your User is not active ! Please contact Admisintrator.'], 401);
            }
        }
        else
        {
            return response()->json(['error'=>'User not found'], 401);
        }
    }

    // method to verify header parameter auth_key
    // param: Request $request ,auth_key
    // return JSON
    public function validateUser(Request $request)
    {
        $user = User::where('auth_key',$request->auth_key)->first();
        if(!empty($user)){
            return response()->json(['success'=>true],200);
        }else{
            return response()->json(['success'=>false],401);
        }

    }

    public function checkSMS(){
        $text = 'This is a test message';
        //$response = InfobipSms::send('+26781499306',$text);
        $response = event(new \AlphaDirect\Events\SendSms('+26781499306',$text));
        //$sms  = SmsMessaging::testSMS();
        return response()->json(['success'=>True,'response'=>$response],200);
    }

}
