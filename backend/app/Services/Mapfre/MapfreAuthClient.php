<?php

namespace AlphaDirect\Services\Mapfre;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * MapfreAuthClient — produces a valid eMiA API bearer token for the
 * MAPFRE / MAWDY "maip-travel" v1.0 travel-insurance integration.
 *
 * Two-stage authentication (mirrors the PRE Postman collections exactly):
 *
 *   1) AWS Cognito client-credentials grant. POST to
 *      config('services.mapfre.cognito_token_url') with
 *      grant_type=client_credentials and HTTP Basic auth
 *      (cognito_client_id : cognito_client_secret). Returns the standard
 *      OAuth2 token response { access_token, token_type, expires_in }.
 *      (Postman: "API eMiA Authenticate" → oauth2 auth block, accessTokenUrl =
 *      {{baseURL_tokenAWSCognito}}, grant_type = client_credentials.)
 *
 *   2) eMiA user authentication. POST {auth_url}/oauth2/emia/token with
 *      Authorization: Bearer <cognito access_token> and JSON body
 *      { username, password, country }. Returns { access_token, ... } — this
 *      is the bearer token used on every /api/v1/* call.
 *      (Postman: "API eMiA Authenticate" → "Auth codCuenta - desCuenta":
 *      method POST, url {{baseUrl}}/oauth2/emia/token, raw JSON body
 *      { "username", "password", "country" }; test script stores
 *      jsonData.access_token.)
 *
 * Conventions (matching Services\Swiftly\SwiftlyService):
 *   - Secrets read ONLY via config('services.mapfre.*') — never runtime env() —
 *     so they survive `php artisan config:cache`.
 *   - Tokens, password and client_secret are NEVER written to logs.
 *   - Fail loud (RuntimeException naming the missing config key) on
 *     misconfiguration rather than firing a doomed unauthenticated request.
 */
class MapfreAuthClient
{
    /** Cache keys for the two tokens in the auth chain. */
    private const CACHE_COGNITO = 'mapfre:cognito_token';
    private const CACHE_EMIA    = 'mapfre:emia_token';

    /**
     * Config keys that must be set for the auth chain to run at all.
     *
     * Public because the admin Integrations screen needs the SAME list to
     * decide whether MAPFRE counts as "configured". MAPFRE authenticates
     * through Cognito + eMiA and has no `api_key`, so the generic
     * `services.<slug>.api_key` check that screen used reported it as
     * permanently "Not configured" no matter what was set.
     */
    public const REQUIRED_CONFIG_KEYS = [
        'cognito_token_url',
        'cognito_client_id',
        'cognito_client_secret',
        'auth_url',
        'username',
        'password',
    ];

    /**
     * Which required credentials are absent, in declaration order.
     *
     * assertConfigured() throws on the FIRST missing key, which is right for a
     * request path but poor diagnostics: `cognito_token_url` is first of six,
     * so an environment missing several reported them one at a time — one
     * deploy per credential to discover the next. The admin connection test
     * uses this to list every gap in a single answer.
     *
     * @return list<string> config keys under `services.mapfre.` that are empty
     */
    public static function missingConfigKeys(): array
    {
        return array_values(array_filter(
            self::REQUIRED_CONFIG_KEYS,
            static fn (string $key): bool => empty(config("services.mapfre.{$key}"))
        ));
    }

    private int $timeout;
    private int $connectTimeout;

    public function __construct(private ?Client $http = null)
    {
        $this->timeout        = (int) config('services.mapfre.timeout', 30);
        $this->connectTimeout = (int) config('services.mapfre.connect_timeout', 10);
    }

    /**
     * Return a cached, valid eMiA bearer token, fetching the full two-stage
     * chain (Cognito → eMiA) on a cache miss.
     */
    public function token(): string
    {
        $this->assertConfigured(self::REQUIRED_CONFIG_KEYS);

        $cached = Cache::get(self::CACHE_EMIA);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $cognitoToken = $this->cognitoToken();

        return $this->emiaToken($cognitoToken);
    }

    /**
     * Clear both cached tokens. Used on a 401 retry by MapfreTravelClient and
     * by tests to force a fresh auth chain.
     */
    public function forgetTokens(): void
    {
        Cache::forget(self::CACHE_EMIA);
        Cache::forget(self::CACHE_COGNITO);
    }

    // ─── internals ─────────────────────────────────────────────────────────

    /**
     * Stage 1 — AWS Cognito client-credentials grant. Cached under
     * 'mapfre:cognito_token' for (expires_in - 60)s.
     */
    private function cognitoToken(): string
    {
        $cached = Cache::get(self::CACHE_COGNITO);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $tokenUrl     = (string) config('services.mapfre.cognito_token_url');
        $clientId     = (string) config('services.mapfre.cognito_client_id');
        $clientSecret = (string) config('services.mapfre.cognito_client_secret');

        try {
            $response = $this->client()->post($tokenUrl, [
                // Cognito client-credentials: HTTP Basic client_id:client_secret.
                'auth'    => [$clientId, $clientSecret],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept'       => 'application/json',
                ],
                'form_params' => [
                    'grant_type' => 'client_credentials',
                ],
            ]);

            $data        = json_decode((string) $response->getBody(), true) ?? [];
            $accessToken = $data['access_token'] ?? null;
            $expiresIn   = (int) ($data['expires_in'] ?? 0);

            if (!is_string($accessToken) || $accessToken === '') {
                throw new \RuntimeException('MAPFRE Cognito token response missing access_token');
            }

            $ttl = max(60, $expiresIn - 60);
            Cache::put(self::CACHE_COGNITO, $accessToken, $ttl);

            return $accessToken;
        } catch (RequestException $e) {
            $status = $e->getResponse()?->getStatusCode();
            Log::error('MAPFRE Cognito auth failed', ['http' => $status, 'msg' => $e->getMessage()]);
            throw new \RuntimeException('MAPFRE Cognito authentication failed (HTTP ' . ($status ?? 'n/a') . ')');
        } catch (\Throwable $e) {
            Log::error('MAPFRE Cognito auth error', ['msg' => $e->getMessage()]);
            throw new \RuntimeException('MAPFRE Cognito authentication error');
        }
    }

    /**
     * Stage 2 — eMiA user authentication, authorized by the Cognito token.
     * Cached under 'mapfre:emia_token'.
     */
    private function emiaToken(string $cognitoToken): string
    {
        $authUrl  = rtrim((string) config('services.mapfre.auth_url'), '/');
        $username = (string) config('services.mapfre.username');
        $password = (string) config('services.mapfre.password');
        $country  = (string) config('services.mapfre.country', 'BW');

        try {
            $response = $this->client()->post($authUrl . '/oauth2/emia/token', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $cognitoToken,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'json' => [
                    'username' => $username,
                    'password' => $password,
                    'country'  => $country,
                ],
            ]);

            $data        = json_decode((string) $response->getBody(), true) ?? [];
            $accessToken = $data['access_token'] ?? null;
            $expiresIn   = (int) ($data['expires_in'] ?? 0);

            if (!is_string($accessToken) || $accessToken === '') {
                throw new \RuntimeException('MAPFRE eMiA token response missing access_token');
            }

            // Honour the eMiA token lifetime when given, else a conservative
            // default so a stale token is re-fetched rather than reused forever.
            $ttl = $expiresIn > 0 ? max(60, $expiresIn - 60) : 600;
            Cache::put(self::CACHE_EMIA, $accessToken, $ttl);

            return $accessToken;
        } catch (RequestException $e) {
            $status = $e->getResponse()?->getStatusCode();
            Log::error('MAPFRE eMiA auth failed', ['http' => $status, 'msg' => $e->getMessage()]);
            throw new \RuntimeException('MAPFRE eMiA authentication failed (HTTP ' . ($status ?? 'n/a') . ')');
        } catch (\Throwable $e) {
            Log::error('MAPFRE eMiA auth error', ['msg' => $e->getMessage()]);
            throw new \RuntimeException('MAPFRE eMiA authentication error');
        }
    }

    private function client(): Client
    {
        return $this->http ??= new Client([
            'timeout'         => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'http_errors'     => true,
        ]);
    }

    /**
     * Fail loudly when a required credential is missing rather than firing a
     * doomed unauthenticated request — surfaces misconfiguration in logs/UI
     * instead of as an opaque 401 from MAPFRE.
     */
    private function assertConfigured(array $keys): void
    {
        foreach ($keys as $key) {
            if (empty(config("services.mapfre.$key"))) {
                throw new MapfreNotConfiguredException($key);
            }
        }
    }
}
