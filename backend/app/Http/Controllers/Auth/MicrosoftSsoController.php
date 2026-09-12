<?php

namespace AlphaDirect\Http\Controllers\Auth;

use AlphaDirect\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Microsoft Entra ID (Azure AD) SSO Controller
 *
 * Supports multi-domain SSO across:
 *   - alphadirect.co.bw
 *   - unicoin.co.bw
 *   - theriskco.com
 *
 * Flow:
 *   1. User clicks "Sign in with Microsoft" → redirect()
 *   2. Microsoft authenticates → redirects back to callback()
 *   3. We exchange code for token, get user profile
 *   4. Match email to users table → log in
 *   5. Optionally redirect to React portal via SSO token
 *
 * Config: config/services.php → microsoft key
 * Env: MICROSOFT_CLIENT_ID, MICROSOFT_CLIENT_SECRET, MICROSOFT_TENANT_ID
 */
class MicrosoftSsoController extends Controller
{
    private string $clientId;
    private string $clientSecret;
    private string $tenantId;
    private string $redirectUri;

    public function __construct()
    {
        $this->clientId     = config('services.microsoft.client_id', '');
        $this->clientSecret = config('services.microsoft.client_secret', '');
        $this->tenantId     = config('services.microsoft.tenant_id', 'common');
        $this->redirectUri  = config('services.microsoft.redirect_uri', url('/auth/microsoft/callback'));
    }

    /**
     * Redirect to Microsoft login page.
     * Accepts ?target=react to redirect to React portal after auth.
     */
    public function redirect(Request $request)
    {
        $state = Str::random(40);
        // Default to 'react' — V2 is the canonical UI. Legacy /admin/dashboard
        // should only be the destination when explicitly requested via
        // ?target=backend (rare, mostly legacy admin tooling).
        $target = $request->query('target', 'react');

        // Store state + target in cache for CSRF protection
        Cache::put("ms_sso_state:{$state}", $target, 300); // 5 min

        $params = http_build_query([
            'client_id'     => $this->clientId,
            'response_type' => 'code',
            'redirect_uri'  => $this->redirectUri,
            'response_mode' => 'query',
            'scope'         => 'openid profile email User.Read',
            'state'         => $state,
            'prompt'        => 'select_account', // Allow choosing account
        ]);

        $authorizeUrl = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/authorize?{$params}";

        return redirect($authorizeUrl);
    }

    /**
     * Handle Microsoft callback — exchange code for token, log user in.
     */
    public function callback(Request $request)
    {
        // Validate state (CSRF protection)
        $state = $request->query('state');
        $target = Cache::pull("ms_sso_state:{$state}");

        if (!$target) {
            return redirect('/login')->with('error', 'Invalid SSO state. Please try again.');
        }

        // Check for errors from Microsoft
        if ($request->has('error')) {
            Log::warning('Microsoft SSO error', [
                'error' => $request->query('error'),
                'description' => $request->query('error_description'),
            ]);
            return redirect('/login')->with('error', 'Microsoft authentication failed: ' . $request->query('error_description'));
        }

        $code = $request->query('code');
        if (!$code) {
            return redirect('/login')->with('error', 'No authorization code received from Microsoft.');
        }

        try {
            // Exchange code for access token
            $tokenResponse = Http::asForm()->post(
                "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token",
                [
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'code'          => $code,
                    'redirect_uri'  => $this->redirectUri,
                    'grant_type'    => 'authorization_code',
                    'scope'         => 'openid profile email User.Read',
                ]
            );

            if ($tokenResponse->failed()) {
                Log::error('Microsoft token exchange failed', ['body' => $tokenResponse->body()]);
                return redirect('/login')->with('error', 'Failed to authenticate with Microsoft.');
            }

            $tokens = $tokenResponse->json();
            $accessToken = $tokens['access_token'] ?? null;

            if (!$accessToken) {
                return redirect('/login')->with('error', 'No access token received from Microsoft.');
            }

            // Get user profile from Microsoft Graph
            $profileResponse = Http::withToken($accessToken)
                ->get('https://graph.microsoft.com/v1.0/me');

            if ($profileResponse->failed()) {
                Log::error('Microsoft Graph profile failed', ['body' => $profileResponse->body()]);
                return redirect('/login')->with('error', 'Failed to retrieve user profile from Microsoft.');
            }

            $msUser = $profileResponse->json();
            $email = $msUser['mail'] ?? $msUser['userPrincipalName'] ?? null;

            if (!$email) {
                return redirect('/login')->with('error', 'No email found in Microsoft profile.');
            }

            // Match Microsoft email to local user
            $user = User::where('email', strtolower($email))->first();

            if (!$user) {
                // Try matching by domain — find user with same name
                $user = $this->fuzzyMatchUser($msUser);
            }

            if (!$user) {
                Log::warning('Microsoft SSO: no matching user', ['email' => $email, 'name' => $msUser['displayName'] ?? '']);
                return redirect('/login')->with('error', "No account found for {$email}. Contact your administrator.");
            }

            // Log the user in.
            // remember=false: V1's users table doesn't have a remember_token
            // column, so Auth::login($user, true) tries an UPDATE that fails
            // with "1054 Unknown column 'remember_token'", the exception is
            // swallowed by the catch block below, and the user lands on
            // /admin/dashboard via the persisted session. Sessions are
            // Redis-backed in V2 — "remember" is unnecessary anyway.
            Auth::login($user);

            // Mark the session as Entra-authenticated. The PasswordValidation
            // middleware reads this and skips the 90-day local-password expiry:
            // the credential verified here is Microsoft's, and /reset-password
            // asks for a Current Password an SSO user does not have. Set after
            // Auth::login() — SessionGuard migrates the session id but keeps
            // the attributes, so the order is safe.
            $request->session()->put('auth_via', 'microsoft');

            Log::info("Microsoft SSO login: {$user->email} (MS: {$email})");

            // Redirect based on target
            if ($target === 'react') {
                return $this->redirectToReact($user);
            }

            return redirect('/admin/dashboard');

        } catch (\Exception $e) {
            Log::error('Microsoft SSO exception: ' . $e->getMessage());
            return redirect('/login')->with('error', 'SSO failed. Please try again or use email/password login.');
        }
    }

    /**
     * API endpoint: Generate Microsoft SSO URL for React frontend.
     * React can redirect the user to this URL for Microsoft login.
     */
    public function apiRedirectUrl(Request $request)
    {
        $state = Str::random(40);
        Cache::put("ms_sso_state:{$state}", 'react', 300);

        $params = http_build_query([
            'client_id'     => $this->clientId,
            'response_type' => 'code',
            'redirect_uri'  => $this->redirectUri,
            'response_mode' => 'query',
            'scope'         => 'openid profile email User.Read',
            'state'         => $state,
            'prompt'        => 'select_account',
        ]);

        return response()->json([
            'url' => "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/authorize?{$params}",
        ]);
    }

    /**
     * Try to match Microsoft user by name if email doesn't match exactly.
     * Handles cases where Microsoft email domain differs from local DB.
     */
    private function fuzzyMatchUser(array $msUser): ?User
    {
        $displayName = $msUser['displayName'] ?? '';
        $givenName = $msUser['givenName'] ?? '';
        $surname = $msUser['surname'] ?? '';

        if ($givenName && $surname) {
            return User::where('firstName', 'like', "%{$givenName}%")
                ->where('lastName', 'like', "%{$surname}%")
                ->first();
        }

        return null;
    }

    /**
     * After Microsoft auth, redirect to React portal with SSO token.
     */
    private function redirectToReact(User $user): \Illuminate\Http\RedirectResponse
    {
        $token = Str::random(64);
        Cache::put("sso_token:{$token}", $user->id, 60);
        $reactUrl = config('services.react_portal.url', 'http://localhost:3000');
        return redirect("{$reactUrl}/sso?token={$token}");
    }
}
