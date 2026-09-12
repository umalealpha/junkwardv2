<?php

namespace AlphaDirect\Services\Swiftly;

use AlphaDirect\Services\IntegrationSettings;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SwiftlyService — single authoritative client for Swiftly Finance API calls.
 *
 * V2-only supply-chain / early-payment finance integration. Outbound side:
 *   - submitInvoice()  POST /api/v1/invoices
 *   - listSuppliers()  GET  /api/v1/offtaker/suppliers  (resolves supplier_id)
 *
 * Inbound early-payment webhooks are handled separately by
 * Api\V1\SwiftlyWebhookController behind the swiftly.signature middleware.
 *
 * Conventions followed (matching Services\Dpo\DpoService):
 *   - Config via config('services.swiftly.*') — NOT runtime env() — so values
 *     survive `php artisan config:cache` (the exact bug class flagged in the
 *     2026-06-12 review).
 *   - Every outbound invoice is logged to swiftly_invoice_submissions on the
 *     V2 ops DB (mysql_system) for audit + idempotency (invoice_id is unique;
 *     a resubmission of the same invoice_id is a no-op rather than a double
 *     submit).
 *   - Secrets (api_key) are NEVER written to logs or the audit row.
 *
 * Auth: API Key + IP whitelisting (per Swiftly, 2026-06-17). Header shape:
 *   Authorization: ApiKey <key>
 */
class SwiftlyService
{
    private string $baseUrl;
    private ?string $apiKey;
    private ?string $programId;
    private ?string $supplierId;
    private string $currency;
    private int $timeout;
    private int $connectTimeout;

    public function __construct(private ?Client $http = null)
    {
        $this->baseUrl        = rtrim((string) config('services.swiftly.base_url'), '/');
        $this->apiKey         = config('services.swiftly.api_key');
        // program_id / supplier_id are non-secret identifiers that can be set
        // from the admin UI (stored in integration_settings) — prefer that,
        // falling back to env config. api_key stays env/SSM-only (secret).
        $this->programId      = IntegrationSettings::getSetting('swiftly', 'program_id') ?: config('services.swiftly.program_id');
        $this->supplierId     = IntegrationSettings::getSetting('swiftly', 'supplier_id') ?: config('services.swiftly.supplier_id');
        $this->currency       = (string) config('services.swiftly.currency', 'BWP');
        $this->timeout        = (int) config('services.swiftly.timeout', 30);
        $this->connectTimeout = (int) config('services.swiftly.connect_timeout', 10);
    }

    /**
     * Submit an invoice to Swiftly for early-payment financing.
     *
     * Amounts are in the smallest currency unit (thebe for BWP), no decimals,
     * matching Swiftly's contract (e.g. 5_000_000 == BWP 50,000.00). Callers
     * MUST pass an integer minor-unit amount; this method does not convert.
     *
     * Idempotent on invoice_id: if we already hold a SUBMITTED row for this
     * invoice_id we return the prior result rather than re-POSTing.
     *
     * @param array{invoice_id:string, amount_minor:int, due_at:string,
     *              supplier_id?:string, currency?:string} $invoice
     */
    public function submitInvoice(array $invoice): SwiftlyResponse
    {
        if (!\AlphaDirect\Services\IntegrationSettings::isEnabled('swiftly')) {
            Log::info('Swiftly submitInvoice skipped — integration disabled');
            return SwiftlyResponse::failure(null, 'Swiftly integration is disabled');
        }

        $this->assertConfigured(['api_key', 'program_id']);

        $invoiceId  = (string) ($invoice['invoice_id'] ?? '');
        $amountMinor = (int) ($invoice['amount_minor'] ?? 0);
        $dueAt      = (string) ($invoice['due_at'] ?? '');
        $supplierId = (string) ($invoice['supplier_id'] ?? $this->supplierId ?? '');
        $currency   = (string) ($invoice['currency'] ?? $this->currency);

        if ($invoiceId === '' || $amountMinor <= 0 || $dueAt === '' || $supplierId === '') {
            return SwiftlyResponse::failure(null, 'invoice_id, amount_minor (>0), due_at and supplier_id are required');
        }

        // Idempotency guard — never double-submit the same invoice. If the
        // audit store is unreachable we log and proceed (the unique index on
        // invoice_id is the real backstop); a storage blip must not silently
        // block a submission. The test console can set skip_idempotency to
        // bypass this and hit Swiftly's own duplicate handling (422).
        if (empty($invoice['skip_idempotency'])) {
            try {
                $existing = $this->submissions()->where('invoice_id', $invoiceId)->first();
                if ($existing && $existing->status === 'submitted') {
                    Log::info('Swiftly invoice already submitted — skipping resubmit', ['invoice_id' => $invoiceId]);
                    return SwiftlyResponse::success((int) ($existing->http_status ?? 200), [
                        'invoice_id'        => $invoiceId,
                        'swiftly_reference' => $existing->swiftly_reference,
                        'idempotent'        => true,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Swiftly idempotency check skipped (storage unavailable)', [
                    'invoice_id' => $invoiceId,
                    'msg'        => $e->getMessage(),
                ]);
            }
        }

        $payload = [
            'invoice_id'     => $invoiceId,
            'program_id'     => $this->programId,
            'supplier_id'    => $supplierId,
            'invoice_amount' => $amountMinor,   // smallest unit, no decimals
            'due_at'         => $dueAt,         // YYYY-MM-DD
            'currency'       => $currency,
        ];

        // Custom early-payment tier (percentage_requested) when provided —
        // otherwise Swiftly applies the program default.
        if (isset($invoice['percentage_requested']) && $invoice['percentage_requested'] !== null && $invoice['percentage_requested'] !== '') {
            $payload['percentage_requested'] = $invoice['percentage_requested'];
        }

        // Swiftly's create request accepts an auto_request_early_payment flag
        // (added 2026-06-30): when true, Swiftly also creates an approved
        // early-payment request in the same call and fires the webhook back to
        // us — letting us exercise the full loop from one outbound call.
        if (!empty($invoice['auto_request_early_payment'])) {
            $payload['auto_request_early_payment'] = true;
        }

        $this->recordSubmission($invoiceId, $payload, 'pending', null, null, null);

        try {
            $response = $this->client()->post($this->baseUrl . '/api/v1/invoices', [
                'headers' => $this->authHeaders(),
                'json'    => $payload,
            ]);

            $status = $response->getStatusCode();
            $body   = (string) $response->getBody();
            $data   = json_decode($body, true) ?? [];
            // Swiftly nests its reference under `invoice.reference_code`
            // (per V3 guide); keep the older shapes as fallbacks.
            $ref    = $data['invoice']['reference_code']
                ?? $data['reference_code']
                ?? $data['reference']
                ?? $data['id']
                ?? null;

            $this->recordSubmission($invoiceId, $payload, 'submitted', $status, $ref, null);
            Log::info('Swiftly invoice submitted', ['invoice_id' => $invoiceId, 'http' => $status, 'ref' => $ref]);

            return SwiftlyResponse::success($status, $data, $this->excerpt($body));
        } catch (RequestException $e) {
            $status = $e->getResponse()?->getStatusCode();
            $body   = $e->getResponse() ? (string) $e->getResponse()->getBody() : '';
            $this->recordSubmission($invoiceId, $payload, 'failed', $status, null, $e->getMessage());
            Log::error('Swiftly invoice submission failed', [
                'invoice_id' => $invoiceId,
                'http'       => $status,
                'msg'        => $e->getMessage(),
            ]);
            return SwiftlyResponse::failure($status, $e->getMessage(), $this->excerpt($body));
        } catch (\Throwable $e) {
            $this->recordSubmission($invoiceId, $payload, 'failed', null, null, $e->getMessage());
            Log::error('Swiftly invoice submission error', ['invoice_id' => $invoiceId, 'msg' => $e->getMessage()]);
            return SwiftlyResponse::failure(null, $e->getMessage());
        }
    }

    /**
     * GET /api/v1/offtaker/suppliers — returns this off-taker's program_id and
     * supplier_id values once the API key is active. Used during onboarding to
     * resolve the supplier_id we then store in config.
     */
    public function listSuppliers(): SwiftlyResponse
    {
        $this->assertConfigured(['api_key']);

        try {
            $response = $this->client()->get($this->baseUrl . '/api/v1/offtaker/suppliers', [
                'headers' => $this->authHeaders(),
            ]);
            $status = $response->getStatusCode();
            $body   = (string) $response->getBody();
            $data   = json_decode($body, true) ?? [];
            return SwiftlyResponse::success($status, $data, $this->excerpt($body));
        } catch (RequestException $e) {
            $status = $e->getResponse()?->getStatusCode();
            Log::error('Swiftly listSuppliers failed', ['http' => $status, 'msg' => $e->getMessage()]);
            return SwiftlyResponse::failure($status, $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Swiftly listSuppliers error', ['msg' => $e->getMessage()]);
            return SwiftlyResponse::failure(null, $e->getMessage());
        }
    }

    // ─── internals ─────────────────────────────────────────────────────────

    private function authHeaders(): array
    {
        return [
            'Authorization' => 'ApiKey ' . $this->apiKey,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
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
     * doomed unauthenticated request. Surfaces misconfiguration in logs/UI
     * instead of as an opaque 401 from Swiftly.
     */
    private function assertConfigured(array $keys): void
    {
        // Check the RESOLVED values (DB-or-env), not raw config — program_id
        // may be set from the admin UI rather than env.
        $resolved = [
            'api_key'     => $this->apiKey,
            'program_id'  => $this->programId,
            'supplier_id' => $this->supplierId,
        ];
        foreach ($keys as $key) {
            $value = $resolved[$key] ?? config("services.swiftly.$key");
            if (empty($value)) {
                throw new \RuntimeException("Swiftly integration not configured: missing {$key}");
            }
        }
    }

    private function submissions()
    {
        return DB::connection('mysql_system')->table('swiftly_invoice_submissions');
    }

    private function recordSubmission(
        string $invoiceId,
        array $payload,
        string $status,
        ?int $httpStatus,
        ?string $reference,
        ?string $error,
    ): void {
        try {
            $now = now();
            $row = [
                'program_id'        => $payload['program_id'] ?? null,
                'supplier_id'       => $payload['supplier_id'] ?? null,
                'amount_minor'      => $payload['invoice_amount'] ?? 0,
                'currency'          => $payload['currency'] ?? $this->currency,
                'due_at'            => $payload['due_at'] ?? null,
                'status'            => $status,
                'http_status'       => $httpStatus,
                'swiftly_reference' => $reference,
                'error'             => $error ? mb_substr($error, 0, 500) : null,
                'submitted_at'      => in_array($status, ['submitted', 'failed'], true) ? $now : null,
                'updated_at'        => $now,
            ];

            $exists = $this->submissions()->where('invoice_id', $invoiceId)->exists();
            if ($exists) {
                $this->submissions()->where('invoice_id', $invoiceId)->update($row);
            } else {
                $this->submissions()->insert(array_merge($row, [
                    'invoice_id' => $invoiceId,
                    'created_at' => $now,
                ]));
            }
        } catch (\Throwable $e) {
            // Audit write must never block the actual submission flow.
            Log::warning('Swiftly submission audit write failed', ['invoice_id' => $invoiceId, 'msg' => $e->getMessage()]);
        }
    }

    private function excerpt(?string $body): ?string
    {
        if ($body === null || $body === '') return null;
        return mb_substr($body, 0, 1000);
    }
}
