<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PaymentWebhookController — receives payment-provider callbacks.
 *
 * Routes:
 *   POST /webhooks/dpo/push   (DPO IPN, unauthenticated)
 *
 * Orange Money is deprecated as of April 2026 and intentionally
 * absent from V2. See PARITY_AUDIT.md.
 */
class PaymentWebhookController extends Controller
{
    /**
     * POST /webhooks/dpo/push
     * Accepts DPO IPN (XML), updates the matching payment_transactions
     * row by TransactionToken. Returns XML OK so DPO stops retrying.
     *
     * NB: the legacy `dpo_payments` logging table is intentionally not
     * written to — it was unused in production. The raw XML lands in
     * the Laravel log for debugging instead.
     */
    public function dpoPush(Request $request)
    {
        $raw = $request->getContent();
        $body = $request->all();
        if (empty($body) && $raw) {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($raw);
            if ($xml !== false) $body = json_decode(json_encode($xml), true) ?? [];
        }

        $token      = $body['TransactionToken']  ?? $body['transaction_token'] ?? null;
        $res        = $body['Result']            ?? $body['result']            ?? null;
        $msg        = $body['ResultExplanation'] ?? $body['message']           ?? null;
        $companyRef = $body['CompanyRef']        ?? $body['company_ref']       ?? null;

        // DPO's numeric transaction reference. This is the SAME identifier
        // that appears in DPO's settlement export under "ref id" / "trans
        // ref" — capturing it now closes the historical gap where settlement
        // reconciliation couldn't match payments back via primary key.
        // DPO's IPN field naming varies across versions, so accept any of
        // the common shapes.
        $transRef = $body['TransactionRef']        ?? $body['transaction_ref']
                 ?? $body['TransRef']               ?? $body['trans_ref']
                 ?? $body['TransID']                ?? $body['trans_id']
                 ?? null;
        $approval = $body['TransactionApproval']   ?? $body['transaction_approval']
                 ?? $body['CCDapproval']            ?? $body['ccd_approval']
                 ?? null;
        $pnrId    = $body['PnrID']                 ?? $body['pnr_id']            ?? null;

        Log::info('DPO IPN received', [
            'token'        => $token,
            'result'       => $res,
            'company_ref'  => $companyRef,
            'trans_ref'    => $transRef,
            'approval'     => $approval,
            'pnr_id'       => $pnrId,
            'raw'          => substr((string) $raw, 0, 2000),
        ]);

        try {
            if (!$token) {
                return $this->ok();
            }

            $update = [
                'status'     => $res === '000' ? 'SUCCESS' : 'FAILED',
                'note'       => is_string($msg) ? substr($msg, 0, 250) : null,
                'updated_at' => now(),
            ];

            // Persist DPO's reference identifiers so settlement reconciliation
            // can match this transaction back to the daily DPO settlement
            // export via primary key (rather than relying on amount + policy
            // + date fallback). Settlement reports use the R-prefixed form
            // ("R78552124") in the trans_ref column, so we normalise to that
            // shape regardless of whether DPO sent it with or without the R.
            if ($transRef !== null && $transRef !== '') {
                $tref = (string) $transRef;
                $update['TransID'] = (preg_match('/^\d+$/', $tref) ? 'R' . $tref : $tref);
            }
            if ($approval !== null && $approval !== '') {
                $update['CCDapproval'] = (string) $approval;
            }
            if ($pnrId !== null && $pnrId !== '') {
                $update['PnrID'] = (string) $pnrId;
            }

            // Webhook integrity: if CompanyRef is present, parse it as our
            // canonical ServiceRef (POL-{policy_id}-{customer_id}[/...]) and
            // confirm the payment row we are about to update is linked to
            // THAT policy/customer. Refuse on mismatch — that is the exact
            // pattern that caused wrong customers to be debited historically.
            $serviceRef = $companyRef ? strtok((string) $companyRef, '/') : null;
            $parsed     = $serviceRef ? \AlphaDirect\Services\Dpo\DpoService::parseServiceRef((string) $serviceRef) : null;

            $txQuery = DB::table('payment_transactions')->where('referenceNumber', $token);
            if ($parsed) {
                $tx = (clone $txQuery)->first();
                if ($tx && $tx->customer_id && $tx->customer_id != $parsed['customer_id']) {
                    // Hard refuse — someone is trying to attribute a payment
                    // to a customer that doesn't match the ServiceRef.
                    Log::error('DPO webhook: ServiceRef/customer mismatch, write refused', [
                        'token'            => $token,
                        'tx_customer_id'   => $tx->customer_id,
                        'ref_customer_id'  => $parsed['customer_id'],
                        'ref_policy_id'    => $parsed['policy_id'],
                    ]);

                    // Record the anomaly if the table exists
                    try {
                        DB::connection('mysql_system')->table('wa_anomaly_alerts')->insert([
                            'alert_key' => 'dpo_ref_mismatch_' . date('Ymd') . '_' . $token,
                            'severity'  => 'high',
                            'message'   => "⚠️ DPO webhook ServiceRef mismatch\ntoken={$token} tx_customer={$tx->customer_id} ref_customer={$parsed['customer_id']}",
                            'created_at'=> now(),
                            'updated_at'=> now(),
                        ]);
                    } catch (\Throwable $ignore) { /* alerts table may be missing */ }

                    return $this->ok();
                }

                // Populate service_ref on the row if not already set. This
                // migrates historical data forward as webhooks arrive.
                $update['service_ref'] = $serviceRef;
            }

            $affected = $txQuery->update($update);

            // If no row existed for this token yet (e.g. the IPN beat the
            // browser redirect, or the customer never returned), create it so
            // the payment is never lost. Mirrors the create-if-not-exists in
            // dpoReturn / legacy saveOnlinePayment.
            if ($affected === 0) {
                $this->recordDpoTransaction((string) ($companyRef ?? ''), (string) $token, $res === '000', [
                    'amount'      => $body['TransactionAmount'] ?? $body['amount'] ?? null,
                    'TransID'     => $transRef,
                    'CCDapproval' => $approval,
                    'PnrID'       => $pnrId,
                    'note'        => $msg,
                ]);
            }

            // Motor-quote dispatch: when the CompanyRef is an MQ-* quote
            // number AND the payment succeeded, kick off the materialisation
            // worker that turns motor_quotes into a real Customer + Policy
            // + Motor row. Idempotent — re-runs are no-ops once materialised.
            if ($res === '000' && $companyRef && str_starts_with((string) $companyRef, 'MQ-')) {
                try {
                    \AlphaDirect\Jobs\MaterialiseMotorQuoteJob::dispatch(
                        (string) $companyRef,
                        $token,
                    );
                    Log::info('Motor quote materialisation dispatched', ['quote' => $companyRef]);
                } catch (\Throwable $e) {
                    Log::error('Failed to dispatch motor materialisation', [
                        'quote' => $companyRef,
                        'msg'   => $e->getMessage(),
                    ]);
                }
            }

            // Bundle-quote dispatch: BQ-* CompanyRef → MaterialiseBundleQuoteJob.
            // Same idempotency contract as motor — multiple IPN retries
            // collapse into one materialisation.
            if ($res === '000' && $companyRef && str_starts_with((string) $companyRef, 'BQ-')) {
                try {
                    \AlphaDirect\Jobs\MaterialiseBundleQuoteJob::dispatch(
                        (string) $companyRef,
                        $token,
                    );
                    Log::info('Bundle quote materialisation dispatched', ['quote' => $companyRef]);
                } catch (\Throwable $e) {
                    Log::error('Failed to dispatch bundle materialisation', [
                        'quote' => $companyRef,
                        'msg'   => $e->getMessage(),
                    ]);
                }
            }

            // Direct-bundle activation: when the CompanyRef is a real policy
            // number whose row is is_bundled=1, the bundle was created up-front
            // as draft policies (PublicBundleCreateController). Activate every
            // policy that shares its payment_reference.
            if ($res === '000' && $companyRef) {
                $this->activatePaidPolicyAndSchedule((string) $companyRef);
            }
        } catch (\Throwable $e) {
            Log::error('DPO webhook: ' . $e->getMessage(), ['token' => $token]);
        }

        return $this->ok();
    }

    /**
     * GET /webhooks/dpo/return
     *
     * Browser redirect after DPO checkout (RedirectURL / BackURL / DeclinedURL).
     * The push IPN above is the authoritative materialiser, but it is
     * server-to-server and may be delayed or unreachable in some environments,
     * so this handler also verifies the transaction with DPO (authoritative,
     * anti-spoof — the query string alone is trivially forgeable) and dispatches
     * the same idempotent materialise job as a safety net before bouncing the
     * customer to the FE success/failure page.
     */
    public function dpoReturn(Request $request)
    {
        // Read via input() (not query()) so the POST-redirect variant works
        // too — DPO posts the result fields in the body in some configs.
        $companyRef = (string) ($request->input('CompanyRef') ?: $request->input('policy_number') ?: '');
        $token      = (string) ($request->input('TransactionToken') ?: $request->input('TransID') ?: '');

        // Customer-facing start journey. `fe` is the start host the browser
        // came from (set at initiate); Helper::startSpaBase validates it
        // against the CORS start allowlist and otherwise falls back to
        // START_URL (only if that is a start host) or the prod start site —
        // so a misconfigured START_URL can never strand the customer on the
        // admin SPA (graphite-v2-fe) as happened on staging 2026-09-08.
        $feBase = \AlphaDirect\Helper::startSpaBase($request->input('fe'));

        // Verify with DPO rather than trusting the `result` query param.
        $paid = false;
        $resp = null;
        $verifyAmount = null;
        if ($token !== '') {
            try {
                $resp = app(\AlphaDirect\Services\Dpo\DpoService::class)->verifyToken($token);
                $paid = $resp->isSuccess();
                $verifyAmount = $resp->rawFields['TransactionAmount'] ?? null;
                Log::info('DPO return verify', ['ref' => $companyRef, 'paid' => $paid, 'code' => $resp->resultCode]);
            } catch (\Throwable $e) {
                Log::error('DPO return verifyToken error', ['ref' => $companyRef, 'msg' => $e->getMessage()]);
            }
        }

        // Persist the payment_transactions row. The legacy saveOnlinePayment
        // (DpoPaymentController) created this row in the redirect handler via
        // a create-if-not-exists; the V2 dpoReturn only ever *updated* by
        // token, so successful payments with no pre-created row left nothing
        // in the table. Mirror the legacy behaviour: record on every return
        // that carries a token (status SUCCESS/FAILED from the verify).
        if ($token !== '') {
            $this->recordDpoTransaction($companyRef, $token, $paid, [
                'amount'      => $verifyAmount ?? $request->input('amount'),
                'TransID'     => $request->input('TransID'),
                'CCDapproval' => $request->input('CCDapproval'),
                'PnrID'       => $request->input('PnrID'),
                'note'        => $resp?->resultExplanation,
            ]);
        }

        if ($paid && $companyRef !== '') {
            try {
                if (str_starts_with($companyRef, 'BQ-')) {
                    \AlphaDirect\Jobs\MaterialiseBundleQuoteJob::dispatch($companyRef, $token);
                } elseif (str_starts_with($companyRef, 'MQ-')) {
                    \AlphaDirect\Jobs\MaterialiseMotorQuoteJob::dispatch($companyRef, $token);
                } else {
                    // Direct policy (instant products 1,2,4,5,9) — activate +
                    // build the recurring premium schedule.
                    $this->activatePaidPolicyAndSchedule($companyRef);
                }
            } catch (\Throwable $e) {
                Log::error('DPO return materialise dispatch failed', ['ref' => $companyRef, 'msg' => $e->getMessage()]);
            }
        }

        $dest = $paid
            ? $feBase . '/thank-you-payment-successful?ref=' . urlencode($companyRef)
            : $feBase . '/payment-failed?ref=' . urlencode($companyRef);

        return redirect()->away($dest);
    }

    /**
     * Record (create-if-not-exists, else refresh) a payment_transactions row
     * for a DPO transaction. Field mapping mirrors the legacy
     * DpoPaymentController::saveOnlinePayment so existing readers/reports keep
     * working. Keyed on referenceNumber = the DPO TransactionToken.
     *
     * NB: payment_transactions has no customer_id column — legacy doesn't set
     * one either, so we don't. For MQ-/BQ- quote refs there is no Policy row
     * yet; we still store the payment (policy_id null) so money is never lost,
     * and the push IPN / materialise job backfill the rest later.
     *
     * @param array $extra ['amount','TransID','CCDapproval','PnrID','note']
     */
    private function recordDpoTransaction(string $companyRef, string $token, bool $paid, array $extra = []): void
    {
        try {
            $ref        = strtoupper($token);
            $serviceRef = strtok($companyRef, '/') ?: $companyRef;

            $policy = DB::table('policies')->where('policyNumber', $serviceRef)
                ->first(['id', 'policyNumber', 'premium_freq']);

            $now = now();
            $fields = [
                'status'           => $paid ? 'SUCCESS' : 'FAILED',
                'paymentDate'      => $now,
                'paymentMethod'    => 'DPO',
                'TransID'          => !empty($extra['TransID'])     ? strtoupper((string) $extra['TransID'])     : $ref,
                'CCDapproval'      => !empty($extra['CCDapproval']) ? strtoupper((string) $extra['CCDapproval']) : null,
                'PnrID'            => !empty($extra['PnrID'])       ? strtoupper((string) $extra['PnrID'])       : null,
                'TransactionToken' => $ref,
                'CompanyRef'       => strtoupper($companyRef),
                'service_ref'      => $serviceRef,
                'updated_at'       => $now,
            ];
            if (isset($extra['amount']) && $extra['amount'] !== null && $extra['amount'] !== '') {
                $fields['amount'] = (float) $extra['amount'];
            }
            if (!empty($extra['note'])) {
                $fields['note'] = mb_substr((string) $extra['note'], 0, 250);
            }
            if ($policy) {
                $fields['policyNumber'] = $policy->policyNumber;
                $fields['policy_id']    = $policy->id;
            }

            // Capture previous status before write so we can detect real
            // transitions (DPO retries the IPN — without this guard the
            // audit log would get duplicate "DPO payment SUCCESS" entries).
            $previousStatus = null;
            $exists = DB::table('payment_transactions')->where('referenceNumber', $ref)->exists();
            if ($exists) {
                $previousStatus = DB::table('payment_transactions')
                    ->where('referenceNumber', $ref)
                    ->value('status');
                DB::table('payment_transactions')->where('referenceNumber', $ref)->update($fields);
            } else {
                DB::table('payment_transactions')->insert(array_merge($fields, [
                    'referenceNumber'         => $ref,
                    'amount'                  => $fields['amount'] ?? 0,
                    'numberOfInstalmentsPaid' => 0,
                    'paymentFrequency'        => $policy->premium_freq ?? 1,
                    'created_at'              => $now,
                ]));
            }
            Log::info('DPO transaction recorded', [
                'ref'         => $ref,
                'company_ref' => $companyRef,
                'status'      => $fields['status'],
                'existed'     => $exists,
            ]);

            // Audit trail — log new payments + real status transitions on
            // existing ones. Skip when there's no Policy yet (MQ-/BQ- quote
            // refs materialise later; the activity log can't attach to a
            // non-existent subject). Webhook is unauthenticated, so causedBy
            // is null (system action).
            $statusChanged = !$exists || ($previousStatus !== $fields['status']);
            if ($policy && $statusChanged) {
                try {
                    $policyModel = \AlphaDirect\Policy::find($policy->id);
                    if ($policyModel) {
                        $verb = $exists ? 'updated to' : 'recorded as';
                        activity('DPO payment ' . strtolower($fields['status']))
                            ->performedOn($policyModel)
                            ->log('DPO webhook: payment ' . $verb . ' ' . $fields['status']
                                . ' (Reference - ' . $ref
                                . ', CompanyRef - ' . $companyRef
                                . ', Amount - P ' . number_format((float) ($fields['amount'] ?? 0), 2, '.', '')
                                . ')');
                    }
                } catch (\Throwable $e) {
                    Log::warning('dpo webhook activity log failed: ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::error('DPO recordDpoTransaction failed', ['token' => $token, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * Activate a policy whose DPO payment just succeeded AND build its recurring
     * premium schedule. Handles both create shapes:
     *   - Directly-created bundle / instant policies (is_bundled=1): every policy
     *     sharing the payment_reference (create-bundle: products 5, 9).
     *   - Single non-bundled policies created via the dedicated endpoints
     *     (products 1, 2, 4).
     *
     * For each newly-paid policy it dispatches ScheduleTransactionEvent so the
     * scheduled_transactions rows are created. Previously the V2 path activated
     * (bundles only) + recorded the payment but NEVER built the schedule, so
     * instant products (1,2,4,5,9) ended up with no future premium collections.
     *
     * Idempotent: only policies still in draft (status=0) are touched, so the
     * IPN + browser-return both calling this — and dues/redo payments on already
     * active policies — are harmless. The schedule listener also self-guards
     * (skips policies that already have schedule rows).
     */
    private function activatePaidPolicyAndSchedule(string $policyNumber): void
    {
        try {
            $serviceRef = strtok($policyNumber, '/') ?: $policyNumber;
            $policy = DB::table('policies')->where('policyNumber', $serviceRef)
                ->first(['id', 'is_bundled', 'payment_reference']);
            if (!$policy) return;

            // Scope the set of policies this payment covers (the whole bundle
            // group when applicable, else just this policy).
            $scope = (int) $policy->is_bundled === 1 && !empty($policy->payment_reference)
                ? DB::table('policies')->where('payment_reference', $policy->payment_reference)
                : DB::table('policies')->where('id', $policy->id);

            $now = now();

            // 0) Banking — promote each in-scope policy's customer_banking row to
            //    the active DPO billing method. The create-time row
            //    (storeCustomerBankingData) is stamped with the create method
            //    ('DEFER') and active=0, and nothing in the V2 path ever set it
            //    to DPO/active. The policy-detail query joins customer_banking on
            //    `active=1`, and the FE gates the Schedule Transactions tab on
            //    billingType/banking.billing === 'DPO' — so without this the tab
            //    never appears for DPO-paid policies. Legacy did this via
            //    saveOnlinePayment + PolicyController::action(...,'DPO').
            try {
                foreach ((clone $scope)->get(['id', 'customer_id', 'premium_freq']) as $p) {
                    // One-time-premium policies (Goods-in-Transit) have no
                    // recurring billing: no DPO billing method to promote, no
                    // Schedule Transactions tab to unlock. Skip entirely.
                    if (($p->premium_freq ?? null) === 'once') {
                        continue;
                    }
                    $bank = DB::table('customer_banking')
                        ->where('customer_id', $p->customer_id)
                        ->where('policy_id', $p->id)
                        ->first(['id']);
                    if ($bank) {
                        DB::table('customer_banking')->where('id', $bank->id)
                            ->update(['billing' => 'DPO', 'active' => 1, 'updated_at' => $now]);
                    } else {
                        DB::table('customer_banking')->insert([
                            'customer_id' => $p->customer_id,
                            'policy_id'   => $p->id,
                            'billing'     => 'DPO',
                            'active'      => 1,
                            'created_at'  => $now,
                            'updated_at'  => $now,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                // Never let a banking write block activation/scheduling — the
                // money is already captured. Log and carry on.
                Log::error('DPO banking promote failed', ['ref' => $policyNumber, 'msg' => $e->getMessage()]);
            }

            // 1) Activation — only policies still awaiting it (status=0). Dues /
            //    redo payments on already-active policies are left untouched and
            //    repeat IPN+return calls become no-ops.
            $toActivate = (clone $scope)->where('status', 0)->pluck('id');
            if ($toActivate->isNotEmpty()) {
                DB::table('policies')->whereIn('id', $toActivate)->update([
                    'status'              => 1,
                    'is_draft'            => 0,
                    'policyActivatedDate' => $now,
                    'updated_at'          => $now,
                ]);

                // Goods-in-Transit (premium_freq 'once'): mirror the payment
                // onto the shipment risk record so the Admin > Alpha Transit
                // page and the policy's Product Details tab stop reading
                // 'unpaid' after a successful DPO capture. Webhook-channel
                // shipments are settled by the ATC recon events instead, so
                // only 'unpaid' rows are promoted here (never settled→paid).
                try {
                    $onceIds = (clone $scope)->whereIn('id', $toActivate)
                        ->where('premium_freq', 'once')->pluck('id');
                    if ($onceIds->isNotEmpty()) {
                        DB::connection('mysql_system')->table('atc_shipments')
                            ->whereIn('policy_id', $onceIds)
                            ->where('payment_status', 'unpaid')
                            ->update(['payment_status' => 'paid', 'updated_at' => $now]);
                    }
                } catch (\Throwable $e) {
                    // Ops-view mirror only — never block activation on it.
                    Log::error('DPO atc_shipments paid-flag update failed', ['ref' => $policyNumber, 'msg' => $e->getMessage()]);
                }
            }

            // 2) Schedule — build it for any in-scope policy that has NO schedule
            //    rows yet. This covers both the fresh activation above AND a
            //    redo_payment / dues payment on a policy that was activated
            //    earlier (before the schedule was wired into the V2 path) and so
            //    still has none. Policies that already have a schedule are
            //    skipped here (and the listener self-guards too), so collections
            //    are never duplicated.
            $scopeIds    = (clone $scope)->pluck('id');
            $alreadyHave = \AlphaDirect\ScheduleTransaction::whereIn('policy_id', $scopeIds)
                ->distinct()->pluck('policy_id')->all();
            // One-time-premium policies (Goods-in-Transit: premium_freq
            // 'once', 14-day term, paid in full at checkout) must NEVER get a
            // recurring schedule — the listener's generic branch would build
            // 100 monthly collection rows against a single premium.
            $toSchedule  = (clone $scope)->whereNotIn('id', $alreadyHave)
                ->where(function ($q) {
                    $q->whereNull('premium_freq')->orWhere('premium_freq', '!=', 'once');
                })
                ->pluck('policyNumber', 'id');
            if ($toSchedule->isEmpty()) return;

            // Anchor a billing start for any to-be-scheduled policy that lacks
            // one (create-bundle drafts don't set it). The card payment just made
            // covers the first month, so start the recurring schedule a month out
            // — otherwise the listener treats a null/empty start as "today" and
            // the collection cron could re-charge immediately.
            DB::table('policies')->whereIn('id', $toSchedule->keys())
                ->where(function ($q) { $q->whereNull('billingStartDate')->orWhere('billingStartDate', ''); })
                ->update(['billingStartDate' => $now->copy()->addMonthsNoOverflow(1)->format('Y-m-d'), 'updated_at' => $now]);

            foreach ($toSchedule as $num) {
                $model = \AlphaDirect\Policy::FindPolicyWithCustomer($num);
                if ($model) {
                    \AlphaDirect\Events\ScheduleTransactionEvent::dispatch($model);
                }
            }

            Log::info('DPO success: policies activated + scheduled', [
                'ref'       => $policyNumber,
                'activated' => $toActivate->count(),
                'scheduled' => $toSchedule->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Policy activation/schedule failed', ['policy' => $policyNumber, 'msg' => $e->getMessage()]);
        }
    }

    private function ok()
    {
        return response('<API3G><Response>OK</Response></API3G>', 200, [
            'Content-Type' => 'application/xml',
        ]);
    }
}
