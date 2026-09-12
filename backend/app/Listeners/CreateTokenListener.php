<?php

namespace AlphaDirect\Listeners;

use AlphaDirect\City;
use AlphaDirect\Http\Controllers\DpoPaymentController;
use AlphaDirect\PaymentActivityLog;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateTokenListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        $data         = $event->data;
        $activity     = new PaymentActivityLog();
        $policyNumber = $data['policy_number'];

        $policy = Policy::FindPolicyWithCustomer($policyNumber);
        if (isset($policy->profile) && $policy->profile->city != null) {
            $policy->profile->city_data = City::where('id', $policy->profile->city)->first();
        } else {
            $policy->profile->city_data = null;
        }

        $customerId = $data['customer_id'];
        $premium    = isset($data['premium']) ? $data['premium'] : $policy->premium;

        // Base64 in URL path is legacy — keep for back-compat with the
        // redirect-handler routes.
        $policyNumberB64 = base64_encode($policy->policyNumber);
        $amountB64       = base64_encode((string) $premium);

        $billingDate = isset($data['billing_date'])
            ? \Carbon\Carbon::parse($data['billing_date'])->format('Y/m/d H:i')
            : \Carbon\Carbon::now()->format('Y/m/d H:i');

        // Hand off to DpoService — it applies the canonical ServiceRef
        // (= POL-{policy_id}-{customer_id}) as CompanyRef, so webhook/callback
        // handlers can match the response back to our records without touching
        // customerEmail. email is still sent (DPO wants it for receipts) but
        // never used for matching on our side.
        $dpo = app(\AlphaDirect\Services\Dpo\DpoService::class);
        $resp = $dpo->createToken([
            'policy_id'     => $policy->id,
            'customer_id'   => $customerId,
            'policy_number' => $policy->policyNumber,
            'amount'        => $premium,
            'email'         => $data['email'] ?? $policy->customer->email ?? null,
            'first_name'    => $policy->customer->firstName ?? null,
            'last_name'     => $policy->customer->lastName  ?? null,
            'phone'         => $policy->customer->cellphone ?? null,
            'city'          => $policy->profile->city_data->name ?? null,
            'redirect_url'  => env('START_URL') . 'subscription/' . $policyNumberB64 . '/' . $amountB64,
            'back_url'      => env('START_URL') . 'payment-failed/' . $policyNumberB64 . '/' . $amountB64,
            'declined_url'  => env('START_URL') . 'payment-failed/' . $policyNumberB64 . '/' . $amountB64,
            'service_date'  => $billingDate,
            'retry_count'   => $data['retry_count'] ?? null,
            'installment'   => $data['installment'] ?? null,
        ]);

        $activity->policy_number  = $policyNumber;
        $activity->customer_id    = $resp->isSuccess() ? $customerId : null;
        $activity->amount         = $premium;
        $activity->dpo_error_code = $resp->resultCode;
        $activity->reason         = $resp->resultExplanation;
        $activity->TransToken     = $resp->transactionToken;
        // payment_activity_logs.response_json / request_json have a CHECK
        // constraint requiring valid JSON. DPO returns raw XML, so we wrap
        // it in a JSON envelope. xmlToJson() converts well-formed XML to
        // structured JSON (queryable via JSON_EXTRACT), falling back to a
        // safe `{"xml_raw":"..."}` wrapper on parse failure.
        $activity->response_json  = self::xmlToJson($resp->responseXml) ?: json_encode($resp->toArray());
        $activity->request_json   = self::xmlToJson($resp->requestXml) ?: '{}';
        $activity->api_name       = $resp->isSuccess() ? 'chargeToken-renewal' : 'createToken-renewal';
        $activity->CompanyAccRef  = $resp->companyAccRef;
        if ($resp->isNetworkError) {
            $activity->curl_error_code = 1;
        }
        $activity->save();

        $email = $data['email'] ?? ($policy->customer->email ?? null);

        return [
            'policy_number'    => $policyNumber,
            'customer_id'      => $customerId,
            'amount'           => $premium,
            'dpo_error_code'   => $resp->resultCode,
            'reason'           => $resp->resultExplanation,
            'token'            => $resp->transactionToken,
            'TransactionToken' => $resp->transactionToken,
            'TransID'          => $resp->rawFields['TransRef'] ?? null,
            'api_name'         => 'chargeToken-renewal',
            'response_json'    => self::xmlToJson($resp->responseXml) ?: json_encode($resp->toArray()),
            'request_json'     => self::xmlToJson($resp->requestXml) ?: '{}',
            'CompanyRef'       => $resp->companyRef,    // = POL-{policy_id}-{customer_id}[/installment/retry]
            'service_ref'      => $resp->serviceRef,    // canonical stable identifier
            'email'            => $email,
            'status'           => $resp->isSuccess() ? 1 : 0,
        ];
    }

    /**
     * Convert a DPO XML payload to a JSON string suitable for the
     * payment_activity_logs.{response,request}_json columns (which have a
     * CHECK constraint requiring valid JSON).
     *
     * - Empty/null input returns null so the caller can default.
     * - Well-formed XML is parsed to a structured object then JSON-encoded
     *   so JSON_EXTRACT('$.TransToken') works downstream.
     * - Malformed XML is wrapped raw: {"xml_raw":"<...>","parse_error":"..."}
     *   — preserves the original bytes for forensic debugging without ever
     *   failing the INSERT.
     */
    public static function xmlToJson(?string $xml): ?string
    {
        if (!is_string($xml) || trim($xml) === '') {
            return null;
        }
        $prev = libxml_use_internal_errors(true);
        try {
            $parsed = simplexml_load_string($xml);
            if ($parsed !== false) {
                $encoded = json_encode($parsed);
                if ($encoded !== false) {
                    return $encoded;
                }
            }
            $errors = array_map(fn($e) => trim($e->message), libxml_get_errors());
            return json_encode([
                'xml_raw'    => $xml,
                'parse_error'=> $errors[0] ?? 'xml parse failed',
            ]);
        } catch (\Throwable $e) {
            return json_encode(['xml_raw' => $xml, 'parse_error' => $e->getMessage()]);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
    }
}
