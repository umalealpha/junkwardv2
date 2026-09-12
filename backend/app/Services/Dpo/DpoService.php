<?php

namespace AlphaDirect\Services\Dpo;

use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

/**
 * DpoService — single authoritative client for every DPO v6 XML call.
 *
 * All existing DPO integrations (CreateTokenListener, ChargeTokenRecurrentListener,
 * RefundController, verifyToken flows) should be refactored to go through this
 * class. Consistent ServiceRef, consistent logging, consistent error handling,
 * consistent retries.
 *
 * Docs: https://docs.dpopay.com/dpo-pay-by-network/reference/dpo-pay-api-v6
 *
 * ServiceRef strategy (the whole point of this refactor):
 *   Every request that identifies a customer/policy carries
 *     <CompanyRef>POL-{policy_id}-{customer_id}</CompanyRef>
 *   and the token responses are stored keyed by that exact string. Email is
 *   still passed (DPO requires it for receipts) but is NEVER used as the
 *   match key on our side.
 */
class DpoService
{
    private string $companyToken;
    private string $baseUrl;
    private string $currency;
    private string $country;
    private int    $serviceType;
    private int    $timeoutSeconds;

    public function __construct()
    {
        $this->companyToken    = (string) env('COMPANY_TOKEN', '');
        // Normalise DPO_URL to the bare host before appending the API path.
        // The env value is inconsistent across environments — some set it to
        // the host ("https://secure.3gdirectpay.com/"), others to the full
        // endpoint ("https://secure.3gdirectpay.com/API/v6/"). Stripping a
        // trailing /API/vN avoids a doubled "/API/v6/API/v6/" path.
        $host = preg_replace('#/API/v\d+/?$#i', '', rtrim((string) env('DPO_URL', 'https://secure.3gdirectpay.com/'), '/'));
        $this->baseUrl         = rtrim((string) $host, '/') . '/API/v6/';
        $this->currency        = (string) env('PAYMENT_CURRENCY', 'BWP');
        $this->country         = (string) env('CUSTOMER_COUNTRY', 'BW');
        $this->serviceType     = (int)    env('SERVICE_TYPE', 3854);
        $this->timeoutSeconds  = (int)    env('DPO_TIMEOUT', 40);
    }

    // ─── Public API ──────────────────────────────────────────────────────────

    /**
     * Build the canonical service reference for a policy + customer.
     * This is the ONLY identifier that should ever be used on our side to
     * match a DPO response back to a customer record.
     */
    public static function serviceRef(int|string $policyId, int|string $customerId): string
    {
        return 'POL-' . $policyId . '-' . $customerId;
    }

    /**
     * Parse a ServiceRef back into (policyId, customerId).
     * Returns null if the string is not in the expected format.
     *
     * @return array{policy_id:int, customer_id:int}|null
     */
    public static function parseServiceRef(?string $ref): ?array
    {
        if (!$ref) return null;
        if (!preg_match('/^POL-(\d+)-(\d+)$/', $ref, $m)) return null;
        return ['policy_id' => (int) $m[1], 'customer_id' => (int) $m[2]];
    }

    /**
     * Issue a refund against a previously-settled DPO transaction.
     *
     * @param  string       $transactionToken  TransToken returned by the original charge
     * @param  float        $amount            Refund amount (≤ original charge)
     * @param  string       $reason            Merchant-visible reason
     * @param  string|null  $reference         Our own reference (stored as refundDetails)
     * @return DpoResponse
     */
    public function refundToken(string $transactionToken, float $amount, string $reason, ?string $reference = null): DpoResponse
    {
        $refundDetails = $reference
            ? "ref={$reference}; reason={$reason}"
            : "reason={$reason}";

        $xml = $this->build('refundToken', [
            'TransactionToken' => $transactionToken,
            'refundAmount'     => number_format($amount, 2, '.', ''),
            'refundDetails'    => $this->sanitize($refundDetails, 400),
        ]);

        return $this->dispatch($xml, 'refundToken', [
            'transaction_token' => $transactionToken,
            'amount'            => $amount,
        ]);
    }

    /**
     * Verify that a transaction token is valid and settled.
     * Useful as a pre-check before refunding.
     */
    public function verifyToken(string $transactionToken): DpoResponse
    {
        $xml = $this->build('verifyToken', [
            'TransactionToken' => $transactionToken,
        ]);

        return $this->dispatch($xml, 'verifyToken', ['transaction_token' => $transactionToken]);
    }

    /**
     * Execute a recurring debit against a saved card token.
     * The resulting payment is linked to our side via ServiceRef, which we
     * record on the payment_transactions row rather than looking up by email.
     *
     * @param  string $transactionToken    TransToken from the original createToken
     * @param  string $subscriptionToken   Returned by DPO when the recurring plan was created
     */
    public function chargeTokenRecurrent(string $transactionToken, string $subscriptionToken): DpoResponse
    {
        $xml = $this->build('chargeTokenRecurrent', [
            'TransactionToken'  => $transactionToken,
            'subscriptionToken' => $subscriptionToken,
        ]);

        return $this->dispatch($xml, 'chargeTokenRecurrent', [
            'transaction_token'  => $transactionToken,
            'subscription_token' => $subscriptionToken,
        ]);
    }

    /**
     * Create a payment token. The customer is then redirected to the DPO
     * payment page to enter card details.
     *
     * Every createToken request now carries ServiceRef in CompanyRef — that
     * is what we match webhook callbacks against.
     *
     * @param  array $params   [
     *   'policy_id', 'customer_id', 'policy_number', 'amount', 'email',
     *   'first_name', 'last_name', 'phone', 'city',
     *   'redirect_url', 'back_url', 'declined_url',
     *   'service_date' (Y/m/d H:i, optional),
     *   'retry_count', 'installment' (optional, appended to CompanyRef)
     * ]
     */
    public function createToken(array $params): DpoResponse
    {
        $serviceRef = self::serviceRef(
            $params['policy_id'] ?? 0,
            $params['customer_id'] ?? 0
        );
        $installment = !empty($params['installment']) ? '/' . $params['installment'] : '';
        $retry       = !empty($params['retry_count']) ? '/' . $params['retry_count'] : '';

        // CompanyRef format remains compatible with existing webhook logic:
        //   SERVICEREF/installment/retry
        // Webhook matching MUST explode on '/' and use the first segment.
        $companyRef = $serviceRef . $installment . $retry;

        $companyAccRef = 'DPO-' . ($params['policy_number'] ?? $serviceRef) . '-' . ($params['customer_id'] ?? 0) . '-' . time();
        $serviceDate = $params['service_date'] ?? \Carbon\Carbon::now()->format('Y/m/d H:i');

        $xml = $this->build('createToken', [
            'Transaction' => [
                'PaymentAmount'          => number_format((float)($params['amount'] ?? 0), 2, '.', ''),
                'PaymentCurrency'        => $this->currency,
                'CompanyRef'             => $this->sanitize($companyRef, 80),
                'RedirectURL'            => $params['redirect_url'] ?? '',
                'BackURL'                => $params['back_url'] ?? '',
                'DeclinedURL'            => $params['declined_url'] ?? '',
                'CompanyRefUnique'       => 0,
                'CompanyAccRef'          => $this->sanitize($companyAccRef, 80),
                'PTL'                    => 2,
                'PTLtype'                => 'hours',
                'TransactionChargeType'  => 1,
                'customerFirstName'      => $this->sanitize($params['first_name'] ?? '', 60),
                'customerLastName'       => $this->sanitize($params['last_name']  ?? '', 60),
                'customerZip'            => '',
                'customerCity'           => $this->sanitize($params['city'] ?? '', 60),
                'customerCountry'        => $this->country,
                'customerPhone'          => $this->sanitize($params['phone'] ?? '', 20),
                'customerDialCode'       => $this->country,
                'customerEmail'          => $this->sanitize($params['email'] ?? '', 190),
            ],
            'Services' => [
                'Service' => [
                    'ServiceType'        => $this->serviceType,
                    'ServiceDescription' => 'Policy premium — ' . ($params['policy_number'] ?? $serviceRef),
                    'ServiceDate'        => $serviceDate,
                ],
            ],
        ], rootAsAssoc: true);

        $resp = $this->dispatch($xml, 'createToken', [
            'service_ref'   => $serviceRef,
            'policy_number' => $params['policy_number'] ?? null,
            'customer_id'   => $params['customer_id']   ?? null,
            'amount'        => $params['amount']        ?? null,
        ]);

        // Enrich response with our ServiceRef so callers don't have to recompute it
        $resp->serviceRef     = $serviceRef;
        $resp->companyRef     = $companyRef;
        $resp->companyAccRef  = $companyAccRef;
        return $resp;
    }

    // ─── Core transport ─────────────────────────────────────────────────────

    /**
     * Build an API3G XML envelope.
     * If $rootAsAssoc is true, $fields may contain nested associative arrays
     * which are recursively serialised (needed for <Transaction> / <Services>).
     */
    private function build(string $request, array $fields, bool $rootAsAssoc = false): string
    {
        $head = '<?xml version="1.0" encoding="utf-8"?>'
              . '<API3G>'
              . '<CompanyToken>' . $this->escape($this->companyToken) . '</CompanyToken>'
              . '<Request>' . $this->escape($request) . '</Request>';

        $body = $rootAsAssoc
            ? $this->renderAssoc($fields)
            : $this->renderFlat($fields);

        return $head . $body . '</API3G>';
    }

    private function renderFlat(array $fields): string
    {
        $out = '';
        foreach ($fields as $k => $v) {
            $out .= '<' . $k . '>' . $this->escape((string) $v) . '</' . $k . '>';
        }
        return $out;
    }

    private function renderAssoc(array $fields): string
    {
        $out = '';
        foreach ($fields as $k => $v) {
            if (is_array($v)) {
                $out .= '<' . $k . '>' . $this->renderAssoc($v) . '</' . $k . '>';
            } else {
                $out .= '<' . $k . '>' . $this->escape((string) $v) . '</' . $k . '>';
            }
        }
        return $out;
    }

    /**
     * POST the XML to DPO. Always returns a DpoResponse, even on network errors
     * (isNetworkError = true in that case).
     */
    private function dispatch(string $xml, string $apiName, array $context = []): DpoResponse
    {
        $start = microtime(true);
        $ch = curl_init($this->baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ['Content-Type: text/xml'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $raw  = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_errno($ch);
        curl_close($ch);

        $durationMs = (int) ((microtime(true) - $start) * 1000);

        if ($raw === false || $code !== 0) {
            Log::warning("DpoService::{$apiName} network error", [
                'curl_errno'   => $code,
                'curl_error'   => $err,
                'duration_ms'  => $durationMs,
                'context'      => $context,
            ]);
            return new DpoResponse([
                'request_xml'       => $xml,
                'response_xml'      => null,
                'result_code'       => null,
                'result_explanation'=> 'network_error: ' . $err,
                'is_network_error'  => true,
                'duration_ms'       => $durationMs,
                'api_name'          => $apiName,
            ]);
        }

        libxml_use_internal_errors(true);
        $xmlObj = simplexml_load_string($raw);
        if ($xmlObj === false) {
            Log::warning("DpoService::{$apiName} invalid XML response", [
                'raw_head' => substr((string)$raw, 0, 200),
            ]);
            return new DpoResponse([
                'request_xml'        => $xml,
                'response_xml'       => $raw,
                'result_code'        => null,
                'result_explanation' => 'invalid_xml_response',
                'duration_ms'        => $durationMs,
                'api_name'           => $apiName,
            ]);
        }

        $arr = $this->xmlToArray($xmlObj);

        Log::info("DpoService::{$apiName}", [
            'result_code'        => $arr['Result'] ?? null,
            'result_explanation' => $arr['ResultExplanation'] ?? null,
            'duration_ms'        => $durationMs,
            'context'            => $context,
        ]);

        return new DpoResponse([
            'request_xml'        => $xml,
            'response_xml'       => $raw,
            'result_code'        => $arr['Result']            ?? null,
            'result_explanation' => $arr['ResultExplanation'] ?? null,
            'transaction_token'  => $arr['TransToken']        ?? null,
            'subscription_token' => $arr['SubscriptionToken'] ?? null,
            'refund_reference'   => $arr['RefundReference']   ?? ($arr['refundReference'] ?? null),
            'raw_fields'         => $arr,
            'duration_ms'        => $durationMs,
            'api_name'           => $apiName,
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function xmlToArray(SimpleXMLElement $element): array
    {
        $out = [];
        foreach ($element->children() as $child) {
            $name = $child->getName();
            $value = (count($child->children()) > 0)
                ? $this->xmlToArray($child)
                : trim((string) $child);
            if (isset($out[$name])) {
                if (!is_array($out[$name]) || !isset($out[$name][0])) {
                    $out[$name] = [$out[$name]];
                }
                $out[$name][] = $value;
            } else {
                $out[$name] = $value;
            }
        }
        return $out;
    }

    private function escape(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function sanitize(string $s, int $maxLen): string
    {
        $s = preg_replace('/[\x00-\x1F\x7F]/u', '', $s) ?? '';
        if (strlen($s) > $maxLen) $s = substr($s, 0, $maxLen);
        return $s;
    }
}
