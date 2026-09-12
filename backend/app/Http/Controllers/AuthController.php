<?php

namespace AlphaDirect\Http\Controllers;
use AlphaDirect\Services\AuthGate;
use AlphaDirect\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class AuthController extends Controller
{
    use HasFactory, Notifiable, HasApiTokens;
    public function register(Request $request)
    {
        return User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password'))
        ]);
    }

    public function login(Request $request)
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response([
                'message' => 'Invalid credentials!'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = Auth::user();

        // SSO-only policy (UAT 2026-05-26). Only admin-equivalent roles may
        // authenticate by password unless the emergency fallback is enabled.
        // Mirrors the gate on /api/v1/auth/login so the legacy /api/createToken
        // path is closed too.
        if (!AuthGate::shouldAllowPasswordLogin($user)) {
            Auth::logout();
            Log::info('[AuthController:legacy] password login blocked — SSO required', [
                'email' => $user->email ?? null,
            ]);
            return response()->json(AuthGate::ssoRequiredResponse(), Response::HTTP_FORBIDDEN);
        }

        $token = $user->createToken('token')->plainTextToken;

        $cookie = cookie('jwt', $token, 1); // 1 mint

        return response([
            'message' => $token
        ])->withCookie($cookie);
    }

    public function user()
    {
       
        return Auth::user();
    }

    public function logout(Request $request)
    {
      
          $request->user()->currentAccessToken()->delete();
          $cookie = Cookie::forget('jwt');
        
        return response([
            'message' => 'Success'
        ])->withCookie($cookie);
    }
}
