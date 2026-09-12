<?php

namespace AlphaDirect\Listeners;

use AlphaDirect\Http\Controllers\DpoPaymentController;
use AlphaDirect\PaymentActivityLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Log;
use Exception;

class ChargeTokenRecurrentListener
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
        try {
            $data = $event->data;

            if (empty($data['token'])) {
                return response()->json(['message' => 'Token is missing'], 400);
            }

            // Route through DpoService — single place for every DPO call, so
            // ServiceRef and logging stay consistent. Existing data shape
            // (['token','subscriptionToken','policy_number','customer_id'])
            // is preserved for backward compatibility with every caller.
            $dpo = app(\AlphaDirect\Services\Dpo\DpoService::class);
            $resp = $dpo->chargeTokenRecurrent(
                (string) $data['token'],
                (string) ($data['subscriptionToken'] ?? '')
            );

            // Pre-compute the ServiceRef we expect to see on this payment.
            // Future webhook/callback handlers match on this string.
            $expectedServiceRef = \AlphaDirect\Services\Dpo\DpoService::serviceRef(
                $data['policy_id']   ?? ($data['policy_number'] ?? ''),
                $data['customer_id'] ?? ''
            );

            $activity = new PaymentActivityLog();
            $activity->policy_number    = $data['policy_number'] ?? null;
            $activity->customer_id      = $data['customer_id']   ?? null;
            $activity->TransactionToken = $data['token'];
            $activity->CompanyRef       = !empty($data['CompanyRef']) ? strtoupper((string) $data['CompanyRef']) : $expectedServiceRef;
            $activity->reason           = $resp->resultExplanation;
            $activity->status           = $resp->isSuccess() ? 1 : 0;
            // payment_activity_logs.response_json has a CHECK constraint
            // requiring valid JSON. DPO returns raw XML — wrap via
            // CreateTokenListener::xmlToJson() which parses well-formed XML
            // to structured JSON and falls back to {"xml_raw":...} on
            // malformed input. Without this the INSERT fails (4025) and
            // every successful DPO charge goes unlogged.
            $activity->response_json    = \AlphaDirect\Listeners\CreateTokenListener::xmlToJson($resp->responseXml) ?: json_encode($resp->toArray());
            $activity->request_json     = json_encode($data + ['expected_service_ref' => $expectedServiceRef]);
            $activity->api_name         = 'chargeTokenRecurrent';
            $activity->save();

            return [
                'status' => $resp->isSuccess() ? 1 : 0,
                'reason' => $resp->resultExplanation,
                'service_ref' => $expectedServiceRef,
            ];
        } catch (Exception $ex) {
            Log::error('ChargeTokenRecurrentListener: ' . $ex->getMessage());
            return null;
        }
    }
}
