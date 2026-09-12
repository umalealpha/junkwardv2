<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    public function webhook (Request $request){


        $verifyToken  = env('VERIFY_TOKEN');
        $mode = $request->get('hub_mode');
        $token = $request->input('hub_verify_token');
        $challenge = $request->input('hub_challenge');
  
        if($mode != '' && $token != ''){

         if($mode == 'subscribe' && $token == $verifyToken){

            echo $challenge;
         }

         else{

            return response()->json('Forbidden',403);
         }
        }else{

       return response()->json('FAIL');
     }
   }
}
