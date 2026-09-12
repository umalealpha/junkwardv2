<?php

namespace AlphaDirect\Http\Middleware;

use AlphaDirect\Services\AuthGate;
use AlphaDirect\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class PasswordValidation
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
		if(request()->method()=='GET'){
			if(auth()->user() && request()->path()!='reset-password'){
                $user = auth()->user();

                // Signed in through Microsoft Entra this session: the credential
                // that was actually verified is the Microsoft one, not the local
                // password, so the 90-day local rotation does not apply. Checked
                // before any DB/cache work — it is a plain session read.
                // Set by MicrosoftSsoController::callback().
                if (session('auth_via') === 'microsoft') {
                    return $next($request);
                }

				$password_changed_at = new \Carbon\Carbon($user->password_changed_at ?? $user->created_at);

                // Cache the 3 DB queries per user for 30 minutes (busted on password change)
                $resetPass = \Cache::remember('pw_reset_exempt_' . $user->id, 1800, function () use ($user) {
                    // SSO-only user (no admin-equivalent role): AuthGate refuses
                    // their password login outright, so they have no usable local
                    // password to rotate and /reset-password — which demands a
                    // Current Password — is unreachable for them. Exempt.
                    if (AuthGate::isSsoOnly($user)) {
                        return 1;
                    }

                    $role = UserRole::where('user_id', $user->id)->first();
                    $permission = Permission::where('name', 'like', '%not_reset_password%')->first();

                    if (!isset($role) || !isset($permission)) {
                        return 0;
                    }
                    $rolePermissions = DB::table('role_has_permissions')
                        ->where('role_id', $role->role_id)
                        ->where('permission_id', $permission->id)
                        ->first();
                    return isset($rolePermissions) ? 1 : 0;
                });

                if($resetPass == 0){
                    if (\Carbon\Carbon::now()->diffInDays($password_changed_at) >= 90) {
                        if (! $request->expectsJson()) {
                            session()->put('resetPath',request()->route()->getPrefix());
                            return redirect("/reset-password");
                        }
                    }
                }
			}
		}
        return $next($request);
    }

}
