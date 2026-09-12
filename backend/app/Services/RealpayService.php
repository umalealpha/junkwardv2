<?php

namespace AlphaDirect\Services;

use Illuminate\Support\Facades\Log;

/**
 * Shared RealPay client — line-by-line port of graphiteBWV8's
 * AddRealpayContract helpers using raw cURL (no Laravel Http facade /
 * Guzzle). Restructured into a service so V2's controllers can call
 * the same code paths from anywhere.
 *
 * Env variables consumed (V8 names verbatim — copy a V8 .env over and
 * it works unchanged):
 *   REALPAY_BASE_URL
 *   REALPAY_PRODUCT          (default product, used for non-FNB)
 *   REALPAY_FNB_PRODUCT      (FNB-specific product, bank_id == 12)
 *   REALPAY_MERCHANT
 *   REALPAY_VERSION
 *   CLIENT_AUTH              ← NOT REALPAY_CLIENT_AUTH (V8 quirk)
 *
 * Bank routing (V8 parity):
 *   bank_id == 12 (FNB) → product = REALPAY_FNB_PRODUCT, tracking 'B3'
 *   any other bank      → product = REALPAY_PRODUCT,    tracking '44'
 *
 * Frequency mapping (V8 parity):
 *   monthly   → MNTH (12 installments)
 *   quarterly → QURT (4 installments)
 *   annual    → YEAR (1 installment)
 */
class RealpayService
{
    /** FNB Botswana bank_id in V2's banks table (matches V8). */
    public const FNB_BANK_ID = 12;

    /**
     * Which RealPay credential platform this instance targets:
     *   - 'legacy' : REALPAY_* (merchant 16244) — motor comprehensive (product 3)
     *                and DOMG/COMG, matching prior behaviour.
     *   - 'start'  : REALPAY_START_* (merchant 19413) — MIS "instant" products.
     *
     * graphiteBWV8 parity: the instant flow swaps the ENTIRE credential set
     * (base_url, client_auth, merchant, product, version) to START while keeping
     * identical payloads and the shared FNB product code. Defaults to legacy so
     * existing callers are unchanged until they opt in via usePlatform().
     */
    private string $platform = 'legacy';

    /** Select the credential platform ('legacy' | 'start') for this instance. */
    public function usePlatform(string $platform): self
    {
        $this->platform = $platform === 'start' ? 'start' : 'legacy';
        return $this;
    }

    /**
     * MIS "instant" products whose RealPay contracts live on the START platform
     * (merchant 19413), per graphiteBWV8. Everything else — motor comprehensive
     * (product 3), DOMG/COMG — stays on the legacy merchant.
     *
     * This is the single source of truth for the split. It lives here rather
     * than on a controller because the credential set has to be chosen
     * identically by EVERY caller that touches a contract: whichever platform
     * created a contract is the only one that can post an instalment against
     * it. A caller that guesses legacy for a START contract does not fall back
     * — RealPay simply does not know the contract.
     */
    public const INSTANT_PRODUCT_IDS = [1, 2, 4, 5, 6, 9, 10];

    /** Which credential platform owns a policy's contracts, from its product. */
    public static function platformForProduct($productId): string
    {
        return in_array((int) $productId, self::INSTANT_PRODUCT_IDS, true) ? 'start' : 'legacy';
    }

    /**
     * Platform-aware config read for callers that build their own RealPay
     * requests (PayNowService's one-off InstalmentPost) rather than going
     * through this service's methods. Same resolution cfg() uses internally.
     */
    public function platformConfig(string $key)
    {
        return $this->cfg($key);
    }

    /**
     * Platform-aware RealPay config read. Returns the START value when this
     * instance targets the START platform, else the legacy value. The FNB
     * product code is shared across both platforms (V8 parity), so it always
     * resolves to realpay.fnb_product regardless of platform.
     */
    private function cfg(string $key)
    {
        if ($key === 'fnb_product') {
            return config('realpay.fnb_product');
        }
        return $this->platform === 'start'
            ? config("realpay.start.$key")
            : config("realpay.$key");
    }

    /** Map a Graphite frequency to RealPay's frequency code + count. */
    public function frequencyMap(string $freq): array
    {
        return match (strtolower($freq)) {
            'monthly', 'mnth' => ['code' => 'MNTH', 'count' => 12],
            'quarterly', 'qurt' => ['code' => 'QURT', 'count' => 4],
            'annual', 'yearly', 'year' => ['code' => 'YEAR', 'count' => 1],
            default => ['code' => 'MNTH', 'count' => 12],
        };
    }

    /** Returns ['product' => ..., 'tracking_code' => ...] for the bank. */
    public function productForBank(int $bankId): array
    {
        return $bankId === self::FNB_BANK_ID
            ? ['product' => config('realpay.fnb_product'), 'tracking_code' => 'B3']
            : ['product' => $this->cfg('product'),     'tracking_code' => '44'];
    }

    // ──────────────────────────────────────────────────────────────
    // V8 getRealpayAuthToken() — verbatim
    // ──────────────────────────────────────────────────────────────

    private function getRealpayAuthToken()
    {
        try {
            $curl = curl_init();
            
            curl_setopt_array($curl, array(
                CURLOPT_URL => $this->cfg('base_url') . '/oauth/token?grant_type=client_credentials',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Basic ' . $this->cfg('client_auth'),
                ),
            ));
            
            $response = curl_exec($curl);
            $data = json_decode($response, true);
            curl_close($curl);
            
            if (isset($data['token_type']) && isset($data['access_token'])) {
                return $data['token_type'] . ' ' . $data['access_token'];
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Realpay Auth Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @return array{token: ?string, error: ?string, http_status: ?int, http_body: ?string, url: ?string}
     */
    public function getAccessTokenWithDebug(): array
    {
        $base       = $this->cfg('base_url');
        $clientAuth = $this->cfg('client_auth');
        if (!$base || !$clientAuth) {
            Log::warning('realpay.config_missing', ['base' => (bool) $base, 'client_auth' => (bool) $clientAuth]);
            return ['token' => null, 'error' => 'realpay_config_missing', 'http_status' => null, 'http_body' => null, 'url' => null];
        }

        $url = $base . '/oauth/token?grant_type=client_credentials';

        // Loud server-side trace — tail storage/logs/laravel.log to follow
        // every RealPay call in real time:
        //   tail -f storage/logs/laravel.log | grep '\[REALPAY\]'
        Log::info('[REALPAY] → POST oauth/token', ['url' => $url, 'client_auth_len' => strlen($clientAuth)]);
        $tStart = microtime(true);
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                // 10s per call — total workflow has ~4-5 calls + must fit
                // inside Cloudflare/ALB ~60s gateway window. V8 uses 30s
                // here but V8 isn't behind a strict gateway timeout.
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Basic ' . $clientAuth,
                ],
            ]);
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($curl);
            curl_close($curl);
            $elapsedMs = (int) round((microtime(true) - $tStart) * 1000);
            Log::info('[REALPAY] ← oauth/token', ['http' => $httpCode, 'elapsed_ms' => $elapsedMs, 'body_len' => strlen((string) $response), 'curl_err' => $curlErr ?: null]);

            if ($curlErr) {
                Log::error('[REALPAY] oauth curl_error', ['msg' => $curlErr]);
                return ['token' => null, 'error' => 'realpay_oauth_curl_error: ' . $curlErr, 'http_status' => null, 'http_body' => null, 'url' => $url];
            }

            $data = json_decode((string) $response, true);

            if (isset($data['token_type']) && isset($data['access_token'])) {
                // V8 returns "$type $token" (e.g. "Bearer eyJ...")
                return [
                    'token'       => $data['token_type'] . ' ' . $data['access_token'],
                    'error'       => null,
                    'http_status' => $httpCode,
                    'http_body'   => null,
                    'url'         => $url,
                ];
            }

            Log::warning('realpay.oauth_failed', ['status' => $httpCode, 'body' => substr((string) $response, 0, 500)]);
            return [
                'token'       => null,
                'error'       => 'realpay_oauth_http_' . $httpCode,
                'http_status' => $httpCode,
                'http_body'   => substr((string) $response, 0, 1000),
                'url'         => $url,
            ];
        } catch (\Throwable $e) {
            Log::error('realpay.oauth_exception', ['msg' => $e->getMessage()]);
            return ['token' => null, 'error' => 'realpay_oauth_exception: ' . $e->getMessage(), 'http_status' => null, 'http_body' => $e->getMessage(), 'url' => $url];
        }
    }

    /** Back-compat shim. */
    public function getAccessToken(): ?string
    {
        return $this->getAccessTokenWithDebug()['token'];
    }

    // ──────────────────────────────────────────────────────────────
    // V8 checkClientExists() — verbatim
    // ──────────────────────────────────────────────────────────────

    /**
     * @return array{exists: ?bool, http_status: ?int, raw: ?string}
     *   exists=true   → client found
     *   exists=false  → client not found
     *   exists=null   → HTTP / network error
     */
    public function checkClientExists(string $token, string $clientNumber, int $bankId): array
    {
        $pt  = $this->productForBank($bankId);
        $url = $this->cfg('base_url') . '/maintain/clients/' . $pt['product']
             . '?ClientNumber=' . $clientNumber
             . '&BeneficiaryUser=' . $this->cfg('merchant')
             . '&Version=' . $this->cfg('version');
        Log::info('[REALPAY] → GET clients', ['url' => $url, 'client' => $clientNumber]);
        $tStart = microtime(true);

        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                // 10s per call — total workflow has ~4-5 calls + must fit
                // inside Cloudflare/ALB ~60s gateway window. V8 uses 30s
                // here but V8 isn't behind a strict gateway timeout.
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'GET',
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: ' . $token,
                ],
            ]);
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($curl);
            curl_close($curl);
            $elapsedMs = (int) round((microtime(true) - $tStart) * 1000);
            Log::info('[REALPAY] ← GET clients', ['http' => $httpCode, 'elapsed_ms' => $elapsedMs, 'body_len' => strlen((string) $response), 'curl_err' => $curlErr ?: null]);

            if ($httpCode >= 200 && $httpCode < 300) {
                $data = json_decode((string) $response, true);
                return [
                    'exists'      => !empty($data['ClientGetResponse']),
                    'http_status' => $httpCode,
                    'raw'         => substr((string) $response, 0, 1000),
                ];
            }
            if ($httpCode === 404) {
                // RealPay returns 404 when the ClientNumber is not yet
                // registered for this product. That's "not found", not a
                // hard error — let the controller route into createClient.
                return ['exists' => false, 'http_status' => 404, 'raw' => substr((string) $response, 0, 1000)];
            }
            Log::warning('[REALPAY] checkClient.http_error', ['status' => $httpCode, 'body' => substr((string) $response, 0, 500)]);
            return ['exists' => null, 'http_status' => $httpCode, 'raw' => substr((string) $response, 0, 1000)];
        } catch (\Throwable $e) {
            Log::error('[REALPAY] checkClient.exception', ['msg' => $e->getMessage()]);
            return ['exists' => null, 'http_status' => null, 'raw' => $e->getMessage()];
        }
    }

    // ──────────────────────────────────────────────────────────────
    // V8 createRealpayClient() — verbatim
    // ──────────────────────────────────────────────────────────────

    /**
     * @return array{ok: bool, error: ?string, http_status: ?int, raw: ?string}
     */
    public function createClient(string $token, array $clientData, int $bankId): array
    {
        $pt  = $this->productForBank($bankId);
        $url = $this->cfg('base_url') . '/maintain/clients/' . $pt['product']
             . '?BeneficiaryUser=' . $this->cfg('merchant')
             . '&Version=' . $this->cfg('version');
        $body = json_encode(['ClientPostRequest' => [$clientData]]);
        return $this->callClientWrite('POST', $url, $token, $body, 'ClientPostResponse');
    }

    // ──────────────────────────────────────────────────────────────
    // V8 updateRealpayClient() — verbatim
    // ──────────────────────────────────────────────────────────────

    /**
     * @return array{ok: bool, error: ?string, http_status: ?int, raw: ?string}
     */
    public function updateClient(string $token, array $clientData, int $bankId): array
    {
        $pt  = $this->productForBank($bankId);
        $url = $this->cfg('base_url') . '/maintain/clients/' . $pt['product']
             . '?BeneficiaryUser=' . $this->cfg('merchant')
             . '&Version=' . $this->cfg('version');
        $body = json_encode(['ClientPutRequest' => [$clientData]]);
        return $this->callClientWrite('PUT', $url, $token, $body, 'ClientPutResponse');
    }

    /** Shared POST/PUT body for client create / update. */
    private function callClientWrite(string $method, string $url, string $token, string $body, string $responseKey): array
    {
        // Mask AccountNumber in the logged payload (admins see the unmasked
        // value elsewhere; logs don't need to leak it on every line).
        $logPayload = json_decode($body, true);
        if (isset($logPayload[array_key_first($logPayload)][0]['AccountNumber'])) {
            $acct = (string) $logPayload[array_key_first($logPayload)][0]['AccountNumber'];
            $logPayload[array_key_first($logPayload)][0]['AccountNumber'] = preg_replace('/.(?=.{4})/', '*', $acct);
        }
        Log::info('[REALPAY] → ' . $method . ' clients', ['url' => $url, 'payload' => $logPayload]);
        $tStart = microtime(true);

        
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                // 10s per call — total workflow has ~4-5 calls + must fit
                // inside Cloudflare/ALB ~60s gateway window. V8 uses 30s
                // here but V8 isn't behind a strict gateway timeout.
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: ' . $token,
                ],
            ]);
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($curl);
            curl_close($curl);
            $elapsedMs = (int) round((microtime(true) - $tStart) * 1000);
            Log::info('[REALPAY] ← ' . $method . ' clients', ['http' => $httpCode, 'elapsed_ms' => $elapsedMs, 'body_len' => strlen((string) $response), 'curl_err' => $curlErr ?: null, 'body' => substr((string) $response, 0, 1500)]);

            if ($httpCode >= 200 && $httpCode < 300) {
                $data = json_decode((string) $response, true);
                if (!empty($data[$responseKey][0]['Successful'])) {
                    return ['ok' => true, 'error' => null, 'http_status' => $httpCode, 'raw' => substr((string) $response, 0, 2000)];
                }
                Log::warning('[REALPAY] client.write_failed', ['response_key' => $responseKey, 'body' => substr((string) $response, 0, 1000)]);
                return [
                    'ok'          => false,
                    'error'       => $this->extractApiFailures($data, $responseKey),
                    'http_status' => $httpCode,
                    'raw'         => substr((string) $response, 0, 2000),
                ];
            }
            Log::warning('[REALPAY] client.http_error', ['method' => $method, 'status' => $httpCode, 'body' => substr((string) $response, 0, 500)]);
            return ['ok' => false, 'error' => 'API error (HTTP ' . $httpCode . ')', 'http_status' => $httpCode, 'raw' => substr((string) $response, 0, 2000)];
        } catch (\Throwable $e) {
            Log::error('[REALPAY] client.exception', ['msg' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Error: ' . $e->getMessage(), 'http_status' => null, 'raw' => $e->getMessage()];
        }
    }

    // ──────────────────────────────────────────────────────────────
    // V8 cancelExistingContracts() — verbatim
    //
    // For each product (default + FNB) GET active contracts for the
    // client, then DELETE each found contract.
    // ──────────────────────────────────────────────────────────────

    /**
     * @return array{ok: bool, cancelled: int, failed: int, error: ?string, details: array}
     */
    public function cancelExistingContracts(string $token, string $clientNumber, ?int $policyId = null): array
    {
        // The caller passes an already-validated OAuth token. Re-fetching it
        // here (V8 did, because V8 called this without a token) discarded the
        // valid token and — on a re-auth hiccup — hit `return false`, which
        // violates this method's `: array` return type and surfaced as a raw
        // HTTP 500 "Server Error" before the client/banking step could run.
        if (!$token) {
            return ['ok' => false, 'cancelled' => 0, 'failed' => 0, 'error' => 'No RealPay token available.', 'details' => []];
        }

        $products  = array_filter([$this->cfg('product'), config('realpay.fnb_product')]);
        $cancelled = 0;
        $failed    = 0;
        $details   = [];

        foreach ($products as $product) {
            // GET contracts under this product
            $getUrl = $this->cfg('base_url') . '/maintain/contracts/' . $product
                    . '?ClientNumber=' . $clientNumber
                    . '&BeneficiaryUser=' . $this->cfg('merchant')
                    . '&Version=' . $this->cfg('version');
            try {
                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL            => $getUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING       => '',
                    CURLOPT_MAXREDIRS      => 10,
                    CURLOPT_TIMEOUT        => 8,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST  => 'GET',
                    CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/json',
                        'Accept: application/json',
                        'Authorization: ' . $token,
                    ],
                ]);
                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);

                if ($httpCode < 200 || $httpCode >= 300) {
                    Log::warning('realpay.cancelExisting.get_failed', ['product' => $product, 'status' => $httpCode]);
                    continue;
                }
                $data = json_decode((string) $response, true);
                $apiContracts = $data['ContractGetResponse'] ?? [];
                if (empty($apiContracts)) continue;

                foreach ($apiContracts as $apiContract) {
                    $contractNumber = $apiContract['ContractNumber'] ?? null;
                    $cn             = $apiContract['ClientNumber'] ?? $clientNumber;
                    if (!$contractNumber) continue;

                    $deleteUrl = $this->cfg('base_url') . '/maintain/contracts/' . $product
                               . '?ClientNumber=' . $cn
                               . '&ContractNumber=' . $contractNumber
                               . '&BeneficiaryUser=' . $this->cfg('merchant')
                               . '&Version=' . $this->cfg('version');
                    $curl = curl_init();
                    curl_setopt_array($curl, [
                        CURLOPT_URL            => $deleteUrl,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING       => '',
                        CURLOPT_MAXREDIRS      => 10,
                        CURLOPT_TIMEOUT        => 8,
                        CURLOPT_CONNECTTIMEOUT => 5,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST  => 'DELETE',
                        CURLOPT_POSTFIELDS     => '{}',
                        CURLOPT_HTTPHEADER     => [
                            'Content-Type: application/json',
                            'Accept: application/json',
                            'Authorization: ' . $token,
                        ],
                    ]);
                    $deleteResponse = curl_exec($curl);
                    $deleteHttpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                    curl_close($curl);

                    if ($deleteHttpCode >= 200 && $deleteHttpCode < 300) {
                        $deleteData = json_decode((string) $deleteResponse, true);
                        if (!empty($deleteData['ContractDeleteResponse'][0]['Successful'])) {
                            $cancelled++;
                            $details[] = ['product' => $product, 'contract' => $contractNumber, 'result' => 'cancelled'];
                            // V8 parity — mark local rows inactive too.
                            if ($policyId) {
                                \DB::table('realpay_client_contracts')
                                    ->where('contract_number', $contractNumber)
                                    ->where('policy_id', $policyId)
                                    ->update(['status' => 0]);
                                \DB::table('realpay_contract_installments')
                                    ->where('contractNumber', $contractNumber)
                                    ->where('InstalmentStatus', 'A')
                                    ->update(['InstalmentStatus' => 'I']);
                            }
                        } else {
                            $failed++;
                            $details[] = ['product' => $product, 'contract' => $contractNumber, 'result' => 'failed', 'status' => $deleteHttpCode];
                            Log::warning('realpay.cancelExisting.delete_failed', ['contract' => $contractNumber, 'status' => $deleteHttpCode]);
                        }
                    } else {
                        $failed++;
                        $details[] = ['product' => $product, 'contract' => $contractNumber, 'result' => 'failed', 'status' => $deleteHttpCode];
                        Log::warning('realpay.cancelExisting.delete_http_error', ['contract' => $contractNumber, 'status' => $deleteHttpCode]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('realpay.cancelExisting.exception', ['msg' => $e->getMessage(), 'product' => $product]);
            }
        }

        return [
            'ok'        => true,
            'cancelled' => $cancelled,
            'failed'    => $failed,
            'error'     => null,
            'details'   => $details,
        ];
    }

    /**
     * Cancel a single contract on RealPay by its contract number (DELETE),
     * trying each product until one accepts it. On success, mark the local
     * realpay_client_contracts row inactive and flip any active installments
     * to 'I' (cancelled) — same local bookkeeping as cancelExistingContracts.
     *
     * @return array{ok: bool, cancelled: bool, error: ?string, status: ?int}
     */
    public function cancelContractByNumber(string $token, string $clientNumber, string $contractNumber, ?int $policyId = null): array
    {
        $products  = array_filter([$this->cfg('product'), config('realpay.fnb_product')]);
        $lastError = null;
        $lastCode  = null;

        foreach ($products as $product) {
            $deleteUrl = $this->cfg('base_url') . '/maintain/contracts/' . $product
                       . '?ClientNumber=' . $clientNumber
                       . '&ContractNumber=' . $contractNumber
                       . '&BeneficiaryUser=' . $this->cfg('merchant')
                       . '&Version=' . $this->cfg('version');
            try {
                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL            => $deleteUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING       => '',
                    CURLOPT_MAXREDIRS      => 10,
                    CURLOPT_TIMEOUT        => 8,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST  => 'DELETE',
                    CURLOPT_POSTFIELDS     => '{}',
                    CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/json',
                        'Accept: application/json',
                        'Authorization: ' . $token,
                    ],
                ]);
                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);
                $lastCode = $httpCode;

                if ($httpCode >= 200 && $httpCode < 300) {
                    $data = json_decode((string) $response, true);
                    if (!empty($data['ContractDeleteResponse'][0]['Successful'])) {
                        if ($policyId) {
                            \DB::table('realpay_client_contracts')
                                ->where('contract_number', $contractNumber)
                                ->where('policy_id', $policyId)
                                ->update(['status' => 0]);
                            \DB::table('realpay_contract_installments')
                                ->where('contractNumber', $contractNumber)
                                ->where('InstalmentStatus', 'A')
                                ->update(['InstalmentStatus' => 'I']);

                            // The mandate is the authority this contract carried.
                            // RealPay has confirmed the cancellation, so leaving
                            // the mandate `active` states an authority that no
                            // longer exists — and guardContractCreation() then
                            // refuses the policy a replacement contract, because
                            // block_on_active defaults to true. That is the
                            // "false already-active permanently blocks
                            // reprocessing" failure, arrived at from the one
                            // setting that ships enabled.
                            //
                            // Isolated: the cancellation is already confirmed
                            // upstream and must not be reported as failed
                            // because bookkeeping threw.
                            try {
                                app(RealPayMandateService::class)->markCancelled(
                                    (int) $policyId,
                                    $contractNumber,
                                    'RealPay contract ' . $contractNumber . ' cancelled'
                                );
                            } catch (\Throwable $e) {
                                Log::error('realpay.cancelContractByNumber.mandate_not_cancelled', [
                                    'policy_id' => $policyId,
                                    'contract'  => $contractNumber,
                                    'error'     => $e->getMessage(),
                                ]);
                            }
                        }
                        return ['ok' => true, 'cancelled' => true, 'error' => null, 'status' => $httpCode];
                    }
                    $lastError = $this->extractApiFailures($data, 'ContractDeleteResponse')
                               ?: 'RealPay did not confirm the cancellation.';
                } else {
                    $lastError = 'RealPay returned HTTP ' . $httpCode . ' for the cancellation.';
                    Log::warning('realpay.cancelContractByNumber.http_error', ['contract' => $contractNumber, 'status' => $httpCode]);
                }
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::error('realpay.cancelContractByNumber.exception', ['msg' => $e->getMessage(), 'contract' => $contractNumber, 'product' => $product]);
            }
        }

        return ['ok' => false, 'cancelled' => false, 'error' => $lastError ?? 'No RealPay product accepted the cancellation.', 'status' => $lastCode];
    }

    // ──────────────────────────────────────────────────────────────
    // V8 createRealpayContract() — verbatim
    // ──────────────────────────────────────────────────────────────

    /**
     * @return array{
     *     ok: bool,
     *     successful: array|null,
     *     error: ?string,
     *     http_status: ?int,
     *     raw: ?string,
     *     debug: array
     * }
     */
    public function createContract(string $token, array $p): array
    {
        $bankId       = (int) ($p['bank_id'] ?? 0);
        $pt           = $this->productForBank($bankId);
        $product      = $pt['product'];
        $trackingCode = $pt['tracking_code'];

        $url = $this->cfg('base_url') . '/maintain/contracts/' . $product
             . '?BeneficiaryUser=' . $this->cfg('merchant')
             . '&Version=' . $this->cfg('version');

        $body = json_encode([
            'ContractPostRequest' => [[
                'ClientNumber'           => $p['client_number'],
                'ContractNumber'         => $p['contract_number'],
                'FrequencyCode'          => $p['frequency_code'],
                'CollectionDay'          => $p['collection_day'],
                'TrackingCode'           => $trackingCode,
                'FirstCollectionDate'    => $p['first_collection_date'],
                'FirstCollectionAmount'  => (string) $p['first_collection_amount'],
                'InstalmentStartDate'    => $p['instalment_start_date'],
                'InstalmentAmount'       => $p['instalment_amount'],
                'NumberOfInstalments'    => (string) $p['number_of_instalments'],
                'CTCPercentage'          => 1,
            ]],
        ]);

        Log::info('[REALPAY] → POST contracts', [
            'url'       => $url,
            'contract'  => $p['contract_number'],
            'product'   => $product,
            'tracking'  => $trackingCode,
            'payload'   => json_decode($body, true),
        ]);
        $tStart = microtime(true);

        $debug = [
            'url'           => $url,
            'product'       => $product,
            'tracking_code' => $trackingCode,
            'bank_id'       => $bankId,
        ];

        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                // 10s per call — total workflow has ~4-5 calls + must fit
                // inside Cloudflare/ALB ~60s gateway window. V8 uses 30s
                // here but V8 isn't behind a strict gateway timeout.
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: ' . $token,
                ],
            ]);
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($curl);
            curl_close($curl);
            $elapsedMs = (int) round((microtime(true) - $tStart) * 1000);
            Log::info('[REALPAY] ← POST contracts', [
                'http'       => $httpCode,
                'elapsed_ms' => $elapsedMs,
                'body_len'   => strlen((string) $response),
                'curl_err'   => $curlErr ?: null,
                'body'       => substr((string) $response, 0, 2000),
            ]);

            $debug['http_status'] = $httpCode;
            $debug['http_body']   = substr((string) $response, 0, 2000);
            $debug['elapsed_ms']  = $elapsedMs;

            if ($curlErr) {
                Log::error('[REALPAY] createContract.curl_error', ['msg' => $curlErr]);
                $debug['curl_error'] = $curlErr;
                return ['ok' => false, 'successful' => null, 'error' => 'cURL error: ' . $curlErr, 'http_status' => null, 'raw' => null, 'debug' => $debug];
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                return ['ok' => false, 'successful' => null, 'error' => 'API error (HTTP ' . $httpCode . ')', 'http_status' => $httpCode, 'raw' => substr((string) $response, 0, 2000), 'debug' => $debug];
            }

            $data = json_decode((string) $response, true);
            $successful = $data['ContractPostResponse'][0]['Successful'] ?? [];
            if (!empty($successful)) {
                return ['ok' => true, 'successful' => $successful[0], 'error' => null, 'http_status' => $httpCode, 'raw' => substr((string) $response, 0, 2000), 'debug' => $debug];
            }

            return [
                'ok'          => false,
                'successful'  => null,
                'error'       => $this->extractApiFailures($data, 'ContractPostResponse'),
                'http_status' => $httpCode,
                'raw'         => substr((string) $response, 0, 2000),
                'debug'       => $debug,
            ];
        } catch (\Throwable $e) {
            Log::error('realpay.createContract.exception', ['msg' => $e->getMessage()]);
            $debug['exception'] = $e->getMessage();
            return ['ok' => false, 'successful' => null, 'error' => 'Error: ' . $e->getMessage(), 'http_status' => null, 'raw' => null, 'debug' => $debug];
        }
    }

    // ──────────────────────────────────────────────────────────────
    // V8 extractApiFailures() — verbatim
    // ──────────────────────────────────────────────────────────────
    public function extractApiFailures(?array $data, string $responseKey): string
    {
        $failures = [];
        if (isset($data[$responseKey][0]['Failed'])) {
            foreach ($data[$responseKey][0]['Failed'] as $failed) {
                if (isset($failed['Failures'])) {
                    foreach ($failed['Failures'] as $failure) {
                        if (isset($failure['FailureDescription'])) {
                            $failures[] = trim($failure['FailureDescription'], ' -');
                        } elseif (isset($failure['Message'])) {
                            $failures[] = trim($failure['Message']);
                        } elseif (isset($failure['Description'])) {
                            $failures[] = trim($failure['Description']);
                        }
                    }
                } elseif (isset($failed['Message'])) {
                    $failures[] = trim($failed['Message']);
                } elseif (isset($failed['Description'])) {
                    $failures[] = trim($failed['Description']);
                }
            }
        }
        return !empty($failures) ? implode(', ', $failures) : 'Unknown error. Please check the logs.';
    }
}
