<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;

use AlphaDirect\User;

use Auth;
use Redirect;
use Session;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;


class CustomerLoginController extends Controller
{
    
  use HasFactory, Notifiable, HasApiTokens;

    protected function credentials(Request $request)
        {
          if(is_numeric($request->get('email'))){

            $credentialsPhone = array(
                'cellphone' => $request->email,
                'password' => $request->password
            );

            if (Auth::attempt($credentialsPhone)) {
                Session::flash('userNotification', 'Login successfully');
                return redirect('/customers/dashboard');
                
            }
            Session::flash('failedAuth','Wrong credentials entered');
             return Redirect::back(); // redirect back to the login page, using ->withErrors($errors) you send the error created above
          }
          elseif (filter_var($request->get('email'), FILTER_VALIDATE_EMAIL)) {

            $credentialsPhone = array(
                'email' => $request->email,
                'password' => $request->password
            );


            if (Auth::attempt($credentialsPhone)) {


                    Session::flash('userNotification', 'Login successfully');
                    return redirect('/customers/dashboard');
             }
          }
        }
}
