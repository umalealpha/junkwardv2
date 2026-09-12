<?php

namespace AlphaDirect\Http\Controllers\Api\Public;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * RealPay IPN webhook receiver.
 *
 * RealPay posts contract-status confirmations back to us after the
 * upstream contract is registered with the bank. The payload shape
 * varies slightly by event (contract create vs. installment status
 * update vs. failure) but always carries enough to identify the
 * contract via ContractNumber + ClientNumber.
 *
 * What this handler does:
 *   1. Stores the raw payload + headers into realpay_webhook_response
 *      for audit (matches the legacy table the admin reads from).
 *   2. Updates realpay_client_contracts.status when the payload
 *      conclusively activates / fails the contract.
 *   3. Returns 200 fast — RealPay retries on non-2xx.
 *
 * Auth: NONE. The endpoint is public on purpose (RealPay can't carry a
 * Bearer). Defence-in-depth comes from validating the ContractNumber
 * format + cellphone hash + recent timestamp before persisting.
 */
class RealpayWebhookController extends Controller
{
    public function push(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) $payload = $request->all();

        // 1. Persist the raw event for audit. Legacy table name preserved.
        try {
            if (Schema::hasTable('realpay_webhook_response')) {
                DB::table('realpay_webhook_response')->insert([
                    'payload'    => $rawBody ?: json_encode($payload),
                    'headers'    => json_encode($request->headers->all()),
                    'ip'         => $request->ip(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        } catch (\Throwable $e) {
            // Logging only; never let a logging failure 5xx the webhook
            // because RealPay will retry and re-flood us.
            Log::warning('realpay_webhook.audit_write_failed', ['msg' => $e->getMessage()]);
        }

        // 2. Pull identifiers from a few common payload shapes (RealPay
        // sandbox vs. prod use slightly different casings).
        $clientNumber   = $payload['ClientNumber']   ?? $payload['clientNumber']   ?? null;
        $contractNumber = $payload['ContractNumber'] ?? $payload['contractNumber'] ?? null;
        $statusName     = $payload['Status']         ?? $payload['status']
                       ?? $payload['ContractStatus'] ?? null;

        if (!$contractNumber) {
            // Acknowledge anyway — keeps RealPay from retrying. Audit
            // row above captures what we got.
            return response()->json(['ok' => true, 'note' => 'no_contract_number'], 200);
        }

        // 3. Update the contract row. Map RealPay's status strings to
        // our local 0/1/2 enum: pending=0 (already inserted at initiate),
        // active=1, failed=2.
        $localStatus = match (strtoupper((string) $statusName)) {
            'ACTIVE', 'APPROVED', 'A', '1' => 1,
            'FAILED', 'REJECTED', 'F', 'CANCELLED' => 2,
            default => null, // unknown — leave row as-is
        };

        if ($localStatus !== null) {
            $updated = DB::table('realpay_client_contracts')
                ->where('contract_number', $contractNumber)
                ->update([
                    'status'      => $localStatus,
                    'rp_response' => json_encode($payload),
                    'updated_at'  => Carbon::now(),
                ]);

            Log::info('realpay_webhook.contract_status_updated', [
                'contract_number' => $contractNumber,
                'client_number'   => $clientNumber,
                'status'          => $statusName,
                'local_status'    => $localStatus,
                'rows'            => $updated,
            ]);

            // Audit trail — log the contract status transition against the
            // owning policy. Webhook is unauthenticated, so causedBy is null
            // (logged as system). Wrapped in try/catch so a logging failure
            // can't cause RealPay to retry-flood us.
            try {
                $contractRow = DB::table('realpay_client_contracts')
                    ->where('contract_number', $contractNumber)
                    ->first(['policy_id']);
                if ($contractRow && $contractRow->policy_id) {
                    $policyModel = \AlphaDirect\Policy::find($contractRow->policy_id);
                    if ($policyModel) {
                        $statusLabel = $localStatus === 1 ? 'Active' : 'Failed';
                        activity('RealPay contract ' . strtolower($statusLabel))
                            ->performedOn($policyModel)
                            ->log('RealPay webhook: contract status set to ' . $statusLabel
                                . ' (ContractNumber - ' . $contractNumber
                                . ', ClientNumber - ' . ($clientNumber ?? '?')
                                . ', RealPay status - ' . $statusName . ')');
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('realpay webhook activity log failed: ' . $e->getMessage());
            }

            // 4. On contract activation, ensure the motor_quote is
            // materialised. The initiate path already runs this synchronously,
            // but a webhook-only activation path (RealPay confirming after
            // bank validation) is the backstop for when the FE didn't get
            // the immediate response.
            if ($localStatus === 1 && $clientNumber) {
                $quote = DB::table('motor_quotes')
                    ->where('quote_number', $clientNumber)
                    ->first();
                if ($quote && !$quote->materialised_policy_id) {
                    try {
                        \AlphaDirect\Jobs\MaterialiseMotorQuoteJob::dispatchSync($clientNumber, $contractNumber);
                    } catch (\Throwable $e) {
                        Log::warning('realpay_webhook.materialise_failed', [
                            'quote_number' => $clientNumber,
                            'msg'          => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        return response()->json(['ok' => true], 200);
    }
}
