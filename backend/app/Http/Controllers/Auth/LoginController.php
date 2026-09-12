<?php

namespace AlphaDirect\Http\Controllers\Auth;

use AlphaDirect\CustomerProfile;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Requests\PasswordExpiredRequest;
use AlphaDirect\KYC;
use AlphaDirect\Role;
use AlphaDirect\User;
use Auth;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth as FacadesAuth;
use Illuminate\Support\Facades\Hash;
use Redirect;
use Session;
use AlphaDirect\OTPTemp;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = '/home';

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        // $this->middleware('guest:customer')->except('logout');
    }

    //Mobile app customer login
    /* public function customerLogin(Request $request){
        $this->validate($request, [
            'cellphone'   => 'required|digits:8',
            'password' => 'required|min:6'
        ]);

        if (FacadesAuth::guard('customer')->attempt(['cellphone' => $request->cellphone, 'password' => $request->password], $request->get('remember'))) {

            $user = FacadesAuth::guard('customer')->user();
            $customerProfile = CustomerProfile::where('customer_id', $user->id)->first();
            $kyc = KYC::where('customer_id', $user->id)->first();
            return response()->json($kyc);

            $alphaResponse = response()->json(['user' => $user, 'profile' => $customerProfile, 'kyc_compliance' => $kyc->compliance], 200);

            return $alphaResponse;
        }
    } */
    /**
     * signin function for Active users, checks the email and password, then checks if the user acc is active
     */
    public function signin(Request $request)
        {    
            
        $credentials = array(
            'email' => $request->email,
            'password' => $request->password,
        );

        $authAttempt = Auth::attempt($credentials);
        if ($authAttempt) {
            if(Auth::User()->is_graphite_login == 1) {

                $userStatus = Auth::User()->active; // get the Users active status

                switch ($userStatus) {
                    case '1':

                        $data = Role::get(array('name'));
                        $roles = array();
                        foreach ($data as $d) {
                            if ($d->name != null || $d->name != '') {
                                array_push($roles, $d->name);
                            }
                        }

                        if (Auth::user()->hasAnyRole($roles)) {
                            Session::flash('userNotification', 'Welcome ' . Auth::user()->firstName . ' ' . Auth::user()->lastName);
                            return redirect('/admin/dashboard');
                        } else {
                            //return response()->json('User has no role');
                            auth()->logout(); //log them out and redirect to login page
                            $request->session()->flush(); //remove any session information
                            return redirect()->route('login')->with('failedAuth', 'User has no role, please contact Administrator');
                        }

                        break;

                    case '2':
                        # code...
                        //suspended account
                        auth()->logout(); //log them out and redirect to login page
                        $request->session()->flush(); //remove any session information
                        return redirect()->route('login')->with('failedAuth', 'Account suspended please contact Administrator');

                        break;

                    case null:
                        # code...
                        //
                        auth()->logout(); //log them out and redirect to login page
                        $request->session()->flush(); //remove any session information
                        return redirect()->route('login')->with('failedAuth', 'Account not activated');

                        break;
                    default:
                        # code...
                        auth()->logout(); //log them out and redirect to login page
                        $request->session()->flush(); //remove any session information
                        return redirect()->route('login')->with('failedAuth', 'Wrong credentials entered');
                        break;
                }
            }else{
            auth()->logout(); //log them out and redirect to login page
            $request->session()->flush(); //remove any session information
            return redirect()->route('login')->with('failedAuth', 'Unable to process login.Please contact Administrator');

            }
        } else {

            auth()->logout(); //log them out and redirect to login page
            $request->session()->flush(); //remove any session information
            return redirect()->route('login')->with('failedAuth', 'Wrong credentials entered');
        }
    }

    public function authenticateViaEmail(Request $request)
    {
       
        try {
            $email = $request->email;
            //ToDo: use Laravel Validator to check the email
            /*  $this->validate($request, [
            'email' => 'required|email|max:255|exists:users',
            ]);*/

            $user = User::where('email', '=', $email)->first(); // get the user where user.email == email

            if (empty($user)) { // if the user is empty return reidrect back with message
                //Session::flash('failedAuth', 'Wrong credentials entered');

                return Redirect::back()->with('failedAuth', 'Account with that email address does not exist.');
            }

            if ($user->password == null) { //If the user password in DB is null, proceed to firstTime Login & active status is null

                if ($user->active == null) { //check the status of the user, if user active is null/active/deactivated, allow only if the active status has not been changed
                    if (Auth::loginUsingId($user->id)) { //login the User using Laravel Auth::loginUsingId();

                        $all_roles_in_database = Role::all()->pluck('name'); //get all roles from the DB
                        //$user = User::role('Agent')->get(); check for users with specified role

                        if (auth()->user()->hasRole('Super Admin')) {
                            Session::flash('userNotification', 'Welcome ' . Auth::user()->firstName . ' ' . Auth::user()->lastName);
                            return redirect('/admin/dashboard');
                        } else if (auth()->user()->hasRole('Agent')) {
                            Session::flash('userNotification', 'Welcome ' . Auth::user()->firstName . ' ' . Auth::user()->lastName);
                            return redirect('/admin/dashboard');
                        } else if (auth()->user()->hasRole('Manager')) {
                            Session::flash('userNotification', 'Login successfully');
                            return redirect('/admin/dashboard');
                        } else if (auth()->user()->hasRole('Customer')) {
                            Session::flash('userNotification', 'Login successfully');
                            return redirect('/admin/dashboard');
                        } else if (auth()->user()->hasRole('Salvage Yard')) {
                            Session::flash('userNotification', 'Login successfully');
                            return redirect('/admin/dashboard');
                        } else if (auth()->user()->hasRole('Accessor')) {
                            Session::flash('userNotification', 'Login successfully');
                            return redirect('/admin/dashboard');
                        } else if (auth()->user()->hasRole('Attorney')) {
                            Session::flash('userNotification', 'Login successfully');
                            return redirect('/admin/dashboard');
                        } else if (auth()->user()->hasAnyRole($all_roles_in_database) == false) { //check if the user has any of the roles from DB, if the user does not have any role assign default Agent role.
                            Session::flash('userNotification', 'Login successfully');
                            $user->assignRole('Agent');
                            return redirect('/admin/dashboard');
                        }
                    } else {

                        $request->session()->flush(); //remove any session data
                        return Redirect::back()->with('failedAuth', 'Something went wrong, please try again');
                    }
                } else {
                    //if a User active status has not been updated, redirect to Login page

                    $request->session()->flush(); //remove any session data
                    return redirect()->route('login')->with('failedAuth', 'Please try login');
                }
            } else {

                //if a User has a password, redirect to login Page
                $request->session()->flush(); //remove any session data
                return redirect()->route('login')->with('failedAuth', 'Please logIn');
            }
        } catch (\Exception $ex) {

            return response()->json(['error' => $ex->getMessage(), 'line' => $ex->getLine()]);
        }
    }



    public function firstTimeLogin()
    {
        return view('auth.first_time_login');
    }

    // expired passport after 90 days
    public function expired()
    {
        return view('auth.passwords.expired');
    }

    public function postExpired(PasswordExpiredRequest $request)
    {
        // Checking current password
        if (!Hash::check($request->current_password, $request->user()->password)) {
            return redirect()->back()->withErrors(['current_password' => 'Current password is not correct']);
        }

        $request->user()->update([
            'password' => bcrypt($request->password),
            'password_changed_at' => Carbon::now()->toDateTimeString()
        ]);
        return redirect()->back()->with(['status' => 'Password changed successfully']);
    }


}
