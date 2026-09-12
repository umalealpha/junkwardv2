<?php

namespace AlphaDirect\Services\Dpo;

/**
 * Normalised response object from a DPO v6 API call.
 * Result === '000' means success per DPO convention.
 */
class DpoResponse
{
    public ?string $resultCode        = null;
    public ?string $resultExplanation = null;
    public ?string $transactionToken  = null;
    public ?string $subscriptionToken = null;
    public ?string $refundReference   = null;
    public ?string $requestXml        = null;
    public ?string $responseXml       = null;
    public array   $rawFields         = [];
    public int     $durationMs        = 0;
    public string  $apiName           = '';
    public bool    $isNetworkError    = false;

    // Optional metadata set by higher-level helpers (e.g. DpoService::createToken)
    public ?string $serviceRef    = null;
    public ?string $companyRef    = null;
    public ?string $companyAccRef = null;

    public function __construct(array $data = [])
    {
        $this->requestXml        = $data['request_xml']         ?? null;
        $this->responseXml       = $data['response_xml']        ?? null;
        $this->resultCode        = isset($data['result_code'])         ? (string) $data['result_code']         : null;
        $this->resultExplanation = isset($data['result_explanation'])  ? (string) $data['result_explanation']  : null;
        $this->transactionToken  = isset($data['transaction_token'])   ? (string) $data['transaction_token']   : null;
        $this->subscriptionToken = isset($data['subscription_token'])  ? (string) $data['subscription_token']  : null;
        $this->refundReference   = isset($data['refund_reference'])    ? (string) $data['refund_reference']    : null;
        $this->rawFields         = $data['raw_fields']                 ?? [];
        $this->durationMs        = (int) ($data['duration_ms']         ?? 0);
        $this->apiName           = (string) ($data['api_name']         ?? '');
        $this->isNetworkError    = (bool) ($data['is_network_error']   ?? false);
    }

    public function isSuccess(): bool
    {
        return $this->resultCode === '000';
    }

    public function isFailure(): bool
    {
        return !$this->isSuccess();
    }

    public function toArray(): array
    {
        return [
            'result_code'         => $this->resultCode,
            'result_explanation'  => $this->resultExplanation,
            'transaction_token'   => $this->transactionToken,
            'subscription_token'  => $this->subscriptionToken,
            'refund_reference'    => $this->refundReference,
            'service_ref'         => $this->serviceRef,
            'company_ref'         => $this->companyRef,
            'duration_ms'         => $this->durationMs,
            'is_network_error'    => $this->isNetworkError,
        ];
    }
}
