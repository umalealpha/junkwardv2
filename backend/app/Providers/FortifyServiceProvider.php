<?php

namespace AlphaDirect\Providers;

use AlphaDirect\Actions\Fortify\CreateNewUser;
use AlphaDirect\Actions\Fortify\ResetUserPassword;
use AlphaDirect\Actions\Fortify\UpdateUserPassword;
use AlphaDirect\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use AlphaDirect\User;
use Hash;
use Redirect;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        RateLimiter::for('login', function (Request $request) {
            $user = User::where('email', $request->email)->first();
            if(isset($user)){
                if($user->active==1){
                    if ($user && Hash::check($request->password, $user->password)) {
                        return $user;
                        // return Limit::perMinute(5)->by($request->email.$request->ip());
                    }
                }else{
                    return Redirect::back()->with('error', 'Your User is not active ! Please contact Admisintrator.');
                    // dd('Your User is not active ! Please contact Admisintrator.');
                }
            }else{
                return Redirect::back()->with('error', 'User not found.');
            }
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
