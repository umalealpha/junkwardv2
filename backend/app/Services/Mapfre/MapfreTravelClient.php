<?php

namespace AlphaDirect\Services\Mapfre;

use AlphaDirect\Services\IntegrationSettings;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MapfreTravelClient — single authoritative client for MAPFRE / MAWDY
 * "maip-travel" v1.0 travel-insurance API calls.
 *
 * Endpoints (base = config('services.mapfre.base_url'), e.g.
 *   https://api20-pre.mia-assistance.com/maip_api_travel):
 *     GET  /api/v1/dealer/tripTypes
 *     GET  /api/v1/dealer/destinations?tripType={n}
 *     GET  /api/v1/catalog/fiscalIdTypes
 *     POST /api/v1/product/{id}/price
 *     POST /api/v1/product/{id}/quotes     -> quoteId, productCode, token, ...
 *     POST /api/v1/product/{id}/contract   -> contractNumber, productName
 *
 * Every /api/v1/* call carries (per the "API Travel" Postman collection):
 *   - Authorization: Bearer <eMiA token>  (collection-level bearer = access_token)
 *   - countryId:        config('services.mapfre.country')   (ISO 3166-1 alpha-2)
 *   - Accept-Language:  config('services.mapfre.language')
 *   - Content-Type / Accept: application/json
 *
 * Conventions (matching Services\Swiftly\SwiftlyService):
 *   - Config via config('services.mapfre.*') — never runtime env().
 *   - Feature-flag short-circuit via IntegrationSettings::isEnabled('mapfre').
 *   - Fail loud on missing config (assertConfigured).
 *   - Secrets (tokens / credentials) are NEVER written to logs or the audit row.
 *   - contract() writes a best-effort audit row to mapfre_quote_submissions on
 *     the mysql_system connection — an audit failure NEVER breaks the call.
 *   - The eMiA token is short-lived; on a 401 we drop the cached tokens once
 *     and retry the request a single time.
 */
class MapfreTravelClient
{
    /**
     * Config keys every travel request needs before it is worth firing.
     *
     * This is NOT the same set as MapfreAuthClient::REQUIRED_CONFIG_KEYS:
     * `base_url` is this client's alone (the auth chain never calls it), and
     * the two Cognito client credentials are the auth chain's alone. A caller
     * that wants "is MAPFRE fully configured?" has to check the UNION of both
     * — see IntegrationSettingsController::requiredConfigKeys().
     */
    public const REQUIRED_CONFIG_KEYS = [
        'base_url',
        'auth_url',
        'cognito_token_url',
        'username',
        'password',
    ];

    private string $baseUrl;
    private string $country;
    private string $countryId;
    private string $language;
    private int $timeout;
    private int $connectTimeout;

    public function __construct(
        private ?Client $http = null,
        private ?MapfreAuthClient $auth = null,
    ) {
        $this->baseUrl        = rtrim((string) config('services.mapfre.base_url'), '/');
        $this->country        = (string) config('services.mapfre.country', 'BW');
        // NOT the same as `country`: see config/services.php. The eMiA login
        // country and the API's countryId header are provisioned separately.
        $this->countryId      = (string) config('services.mapfre.country_id', $this->country);
        $this->language       = (string) config('services.mapfre.language', 'en');
        $this->timeout        = (int) config('services.mapfre.timeout', 30);
        $this->connectTimeout = (int) config('services.mapfre.connect_timeout', 10);

        $this->auth ??= new MapfreAuthClient($this->http);
    }

    /** GET /api/v1/dealer/tripTypes — trip types for the dealer's floating policy. */
    public function tripTypes(): MapfreResponse
    {
        if (!IntegrationSettings::isEnabled('mapfre')) {
            return MapfreResponse::failure(null, 'MAPFRE integration is disabled');
        }

        return $this->request('GET', '/api/v1/dealer/tripTypes');
    }

    /** GET /api/v1/dealer/destinations?tripType={n} — destinations for a trip type. */
    public function destinations(string $tripType): MapfreResponse
    {
        if (!IntegrationSettings::isEnabled('mapfre')) {
            return MapfreResponse::failure(null, 'MAPFRE integration is disabled');
        }

        $path = '/api/v1/dealer/destinations?tripType=' . urlencode($tripType);

        return $this->request('GET', $path);
    }

    /** GET /api/v1/catalog/fiscalIdTypes — fiscal-id types for the dealer. */
    public function fiscalIdTypes(): MapfreResponse
    {
        if (!IntegrationSettings::isEnabled('mapfre')) {
            return MapfreResponse::failure(null, 'MAPFRE integration is disabled');
        }

        return $this->request('GET', '/api/v1/catalog/fiscalIdTypes');
    }

    /**
     * POST /api/v1/product/ALL/price — price EVERY product this dealer sells,
     * in one round trip.
     *
     * `ALL` is a literal path segment, not a product id. Verified live against
     * PRE on 2026-08-20: it returns a top-level ARRAY, one entry per product,
     * each carrying `id`, `productCode`, `productName`, `priceData`
     * {grossPrice, netPrice, taxes, currency} and `infoCover[]`
     * {coverCode, coverName}. That makes the cover-tier catalogue discoverable
     * at runtime — the tiers no longer need MAPFRE product ids configured per
     * environment, because MAPFRE names them here alongside their price.
     */
    public function priceAll(array $req): MapfreResponse
    {
        if (!IntegrationSettings::isEnabled('mapfre')) {
            return MapfreResponse::failure(null, 'MAPFRE integration is disabled');
        }

        return $this->request('POST', '/api/v1/product/ALL/price', $req);
    }

    /** POST /api/v1/product/{id}/price — price one or more products. */
    public function price(string $productId, array $req): MapfreResponse
    {
        if (!IntegrationSettings::isEnabled('mapfre')) {
            return MapfreResponse::failure(null, 'MAPFRE integration is disabled');
        }

        $path = '/api/v1/product/' . urlencode($productId) . '/price';

        return $this->request('POST', $path, $req);
    }

    /** POST /api/v1/product/{id}/quotes — validate + create a quote (quoteId, token, ...). */
    public function quote(string $productId, array $req): MapfreResponse
    {
        if (!IntegrationSettings::isEnabled('mapfre')) {
            return MapfreResponse::failure(null, 'MAPFRE integration is disabled');
        }

        $path = '/api/v1/product/' . urlencode($productId) . '/quotes';

        return $this->request('POST', $path, $req);
    }

    /**
     * POST /api/v1/product/{id}/contract — issue (bind) a travel contract.
     *
     * Writes a best-effort audit row to mapfre_quote_submissions keyed on a
     * caller-supplied `reference` (else derived from productId + quoteId), so
     * the same reference is never double-bound. The audit write is wrapped so
     * it can NEVER break the actual contract call.
     */
    public function contract(string $productId, array $req): MapfreResponse
    {
        if (!IntegrationSettings::isEnabled('mapfre')) {
            return MapfreResponse::failure(null, 'MAPFRE integration is disabled');
        }

        $quoteId   = (string) ($req['quoteId'] ?? '');
        $reference = (string) ($req['reference'] ?? '');
        if ($reference === '') {
            // Deterministic, caller-controllable fallback — no random/uniqid so
            // tests can predict it: productId + quoteId (or productId alone).
            $reference = $quoteId !== '' ? $productId . ':' . $quoteId : $productId;
        }

        $path = '/api/v1/product/' . urlencode($productId) . '/contract';

        $this->recordSubmission($reference, $productId, $quoteId, $req, 'pending', null, null, null);

        $response = $this->request('POST', $path, $req);

        if ($response->isSuccess()) {
            $this->recordSubmission(
                $reference,
                $productId,
                $quoteId,
                $req,
                'submitted',
                $response->httpStatus,
                $response->get('contractNumber'),
                null,
            );
        } else {
            $this->recordSubmission(
                $reference,
                $productId,
                $quoteId,
                $req,
                'failed',
                $response->httpStatus,
                null,
                $response->error,
            );
        }

        return $response;
    }

    // ─── internals ─────────────────────────────────────────────────────────

    /**
     * Perform an authenticated request and normalise the result into a
     * MapfreResponse. On a 401 the cached tokens are dropped once and the
     * request is retried a single time (the eMiA token is short-lived).
     */
    private function request(string $method, string $path, ?array $json = null, bool $retried = false): MapfreResponse
    {
        $this->assertConfigured(self::REQUIRED_CONFIG_KEYS);

        $url = $this->baseUrl . $path;

        try {
            $options = ['headers' => $this->authHeaders()];
            if ($json !== null) {
                $options['json'] = $json;
            }

            $response = $this->client()->request($method, $url, $options);

            $status = $response->getStatusCode();
            $body   = (string) $response->getBody();
            $data   = json_decode($body, true) ?? [];

            return MapfreResponse::success($status, $data, $this->excerpt($body));
        } catch (RequestException $e) {
            $status = $e->getResponse()?->getStatusCode();

            // Short-lived token rotation: on a single 401, drop cached tokens
            // and retry once with a freshly minted eMiA token.
            if ($status === 401 && !$retried) {
                $this->auth->forgetTokens();
                Log::info('MAPFRE 401 — refreshing tokens and retrying once', ['path' => $path]);
                return $this->request($method, $path, $json, true);
            }

            $body = $e->getResponse() ? (string) $e->getResponse()->getBody() : '';
            Log::error('MAPFRE request failed', [
                'method' => $method,
                'path'   => $path,
                'http'   => $status,
                'msg'    => $e->getMessage(),
            ]);
            return MapfreResponse::failure($status, $e->getMessage(), $this->excerpt($body));
        } catch (\Throwable $e) {
            Log::error('MAPFRE request error', [
                'method' => $method,
                'path'   => $path,
                'msg'    => $e->getMessage(),
            ]);
            return MapfreResponse::failure(null, $e->getMessage());
        }
    }

    private function authHeaders(): array
    {
        return [
            'Authorization'   => 'Bearer ' . $this->auth->token(),
            'countryId'       => $this->countryId,
            'Accept-Language' => $this->language,
            'Content-Type'    => 'application/json',
            'Accept'          => 'application/json',
        ];
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
     * doomed unauthenticated request.
     */
    private function assertConfigured(array $keys): void
    {
        foreach ($keys as $key) {
            if (empty(config("services.mapfre.$key"))) {
                throw new MapfreNotConfiguredException($key);
            }
        }
    }

    private function submissions()
    {
        return DB::connection('mysql_system')->table('mapfre_quote_submissions');
    }

    /**
     * Best-effort audit row for a contract (bind). Never logs secrets; never
     * throws — an audit failure must not break the contract call.
     */
    private function recordSubmission(
        string $reference,
        string $productId,
        string $quoteId,
        array $payload,
        string $status,
        ?int $httpStatus,
        ?string $contractNumber,
        ?string $error,
    ): void {
        try {
            $now = now();
            $row = [
                'product_id'             => $productId !== '' ? $productId : null,
                'mapfre_quote_id'        => $quoteId !== '' ? $quoteId : null,
                'mapfre_contract_number' => $contractNumber,
                'status'                 => $status,
                'http_status'            => $httpStatus,
                'error'                  => $error ? mb_substr($error, 0, 500) : null,
                'payload'                => json_encode($payload),
                'submitted_at'           => in_array($status, ['submitted', 'failed'], true) ? $now : null,
                'updated_at'             => $now,
            ];

            $exists = $this->submissions()->where('reference', $reference)->exists();
            if ($exists) {
                $this->submissions()->where('reference', $reference)->update($row);
            } else {
                $this->submissions()->insert(array_merge($row, [
                    'reference'  => $reference,
                    'created_at' => $now,
                ]));
            }
        } catch (\Throwable $e) {
            // Audit write must never block the actual contract flow.
            Log::warning('MAPFRE contract audit write failed', ['reference' => $reference, 'msg' => $e->getMessage()]);
        }
    }

    private function excerpt(?string $body): ?string
    {
        if ($body === null || $body === '') {
            return null;
        }
        return mb_substr($body, 0, 1000);
    }
}
