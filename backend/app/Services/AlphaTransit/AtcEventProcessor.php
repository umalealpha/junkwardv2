<?php

namespace AlphaDirect\Services\AlphaTransit;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * AtcEventProcessor — ingests the six Alpha Transit Cover event types into
 * the legacy Graphite tables, per the Integration Brief (30 June 2026) and
 * the Reply & Sample Payloads document.
 *
 *   policy.created                → customer (+profile), policies,
 *                                   policy_terms, policy_actions, atc_shipments
 *   policy.payment_status_changed → atc_shipments.payment_status
 *   payment.received              → atc_payments + one payment_transactions
 *                                   row per settled policy
 *   recon.monthly_settled         → duplicate-safe re-confirmation of the
 *                                   same settlement (subset payload)
 *   claim.created                 → claims + atc_claims
 *   claim.updated                 → claims.status (+ atc_claims detail)
 *
 * Deliberate divergences from the brief, forced by the legacy schema:
 *   - `customers`/`payments` are really `customer`/`payment_transactions`.
 *   - `mis_premium_data` has no schema and no writer anywhere in the repo —
 *     premium data is kept on atc_shipments until Finance defines the table.
 *   - policies has no payment_status column; settlement state lives on
 *     atc_shipments (courier remits monthly — cover is active regardless).
 *   - claims has no amount/settled_amount columns; money lives on atc_claims.
 *
 * Graphite NEVER mints ATC numbers — policy/claim numbers arrive allocated
 * (base 9,000,000; ~42x above the legacy max, no collision risk) and the
 * unique index on policies.policyNumber is the last line of defence.
 *
 * Every handler is idempotent on its natural key (policyNumber, atc_payment_id,
 * atc_claim_id) in addition to the envelope-level X-Idempotency-Key dedupe in
 * the controller, so replays after partial failures self-heal.
 */
class AtcEventProcessor
{
    public const EVENT_TYPES = [
        'policy.created',
        'policy.payment_status_changed',
        'payment.received',
        'recon.monthly_settled',
        'claim.created',
        'claim.updated',
    ];

    // Risk-gate 2 (Reply §01.5) — re-validate what the platform validated on
    // issuance, as a second wall. Values mirror routes/policies.js RATES.
    private const GOODS_CATEGORIES = ['STD', 'ELE'];
    private const VALUE_MIN = 200;
    private const VALUE_MAX = 75000;

    private const PRODUCT_NAME = 'Alpha Transit Cover';
    private const PRODUCT_SLUG = 'alpha-transit-cover';

    // ATC claim statuses (claims.js VALID_STATUSES) → legacy claims.status.
    private const CLAIM_STATUS_MAP = [
        'open'          => 'Open',
        'under_review'  => 'Open',
        'info_required' => 'Open',
        'approved'      => 'Approved',
        'rejected'      => 'Rejected',
        'paid'          => 'Paid',
        'closed'        => 'Closed',
    ];

    private ?int $productId = null;

    /**
     * @return array{graphite_id:int, duplicate:bool, entity_type:string, warnings:string[]}
     */
    public function handle(string $eventType, array $payload): array
    {
        return match ($eventType) {
            'policy.created'                => $this->policyCreated($payload),
            'policy.payment_status_changed' => $this->paymentStatusChanged($payload),
            'payment.received'              => $this->paymentReceived($payload),
            'recon.monthly_settled'         => $this->reconMonthlySettled($payload),
            'claim.created'                 => $this->claimCreated($payload),
            'claim.updated'                 => $this->claimUpdated($payload),
            default => throw new AtcRejection(422, 'malformed_payload', "Unknown event_type '$eventType'"),
        };
    }

    // ─── policy.created ─────────────────────────────────────────────────────

    private function policyCreated(array $p): array
    {
        $policyNumber = trim((string) ($p['policy_number'] ?? ''));
        $companyCode  = trim((string) ($p['company_code'] ?? ''));
        $sender       = (array) ($p['sender'] ?? []);
        $goods        = (array) ($p['goods'] ?? []);
        $financials   = (array) ($p['financials'] ?? []);
        $cover        = (array) ($p['cover'] ?? []);

        if ($policyNumber === '' || $companyCode === '' || trim((string) ($sender['name'] ?? '')) === ''
            || !isset($goods['declared_value'], $financials['premium'], $financials['sum_insured'], $cover['start'], $cover['end'])) {
            throw new AtcRejection(422, 'malformed_payload', 'policy.created requires policy_number, company_code, sender.name, goods.declared_value, financials.premium, financials.sum_insured, cover.start, cover.end');
        }
        // atc_policy_id is the shipment upsert key for webhook-channel rows —
        // without it every keyless payload would collide on the same row
        // (…?? 0) and silently overwrite the previous shipment. Refuse it as
        // malformed instead.
        if (!is_numeric($p['atc_policy_id'] ?? null) || (int) $p['atc_policy_id'] <= 0) {
            throw new AtcRejection(422, 'malformed_payload', 'policy.created requires a positive numeric atc_policy_id');
        }

        $coverStart = $this->parseDate($cover['start'], 'cover.start');
        $coverEnd   = $this->parseDate($cover['end'], 'cover.end');

        // Risk gate 2 — declared-value band and approved goods categories.
        $category = strtoupper(trim((string) ($goods['category'] ?? '')));
        if (!in_array($category, self::GOODS_CATEGORIES, true)) {
            throw new AtcRejection(400, 'excluded_category', "Goods category '$category' is not in the approved list (" . implode(', ', self::GOODS_CATEGORIES) . ')');
        }
        $declared = (float) $goods['declared_value'];
        if ($declared < self::VALUE_MIN || $declared > self::VALUE_MAX) {
            throw new AtcRejection(400, 'value_out_of_band', "declared_value $declared outside " . self::VALUE_MIN . '–' . self::VALUE_MAX . ' (above band belongs to fac referral)');
        }

        // Natural-key idempotency — the unique index on policies.policyNumber
        // is authoritative. On replay, self-heal a missing shipment row.
        $existing = DB::table('policies')->where('policyNumber', $policyNumber)->first(['id']);
        if ($existing) {
            $this->upsertShipment($p, (int) $existing->id, $coverStart, $coverEnd);
            return $this->result((int) $existing->id, 'policy', true);
        }

        $agencyId  = $this->courierAgencyId($companyCode);
        $productId = $this->productId();
        $issuedAt  = isset($p['issued_at']) ? $this->parseDate($p['issued_at'], 'issued_at') : Carbon::now();

        DB::beginTransaction();
        try {
            $customerId = $this->matchOrCreateCustomer($sender);

            // Cover is active from issuance regardless of remittance — the
            // courier collects the premium and remits monthly (net-7).
            $policyId = $this->insertInto('policies', [
                'customer_id'     => $customerId,
                'agency_id'       => $agencyId,
                'product_id'      => $productId,
                'policyNumber'    => $policyNumber,
                'status'          => 1,
                'premium'         => (float) $financials['premium'],
                'annual_premium'  => (float) $financials['premium'], // one-time premium, 14-day term
                'premium_freq'    => 'once',                          // must never enter the debit-order cycle
                'sum_assured'     => (float) $financials['sum_insured'],
                'has_member'      => 0,
                'has_vehicle'     => 0,
                'leadSource'      => 'AlphaTransit',
                'term_start_date' => $coverStart->format('Y-m-d'),
                'term_end_date'   => $coverEnd->format('Y-m-d'),
                'expiry_date'     => $coverEnd->format('Y-m-d'),
                'kyc_customer'    => '0',
                'kyc_recipient'   => '0',
                'created_at'      => $issuedAt,
                'updated_at'      => Carbon::now(),
            ]);

            // Term + NEWBUSINESS action, per the ingestion contract (Brief §05).
            $termId = null;
            if (Schema::hasTable('policy_terms')) {
                $termId = $this->insertInto('policy_terms', [
                    'policy_id'       => $policyId,
                    'term_start_date' => $coverStart->format('Y-m-d'),
                    'term_end_date'   => $coverEnd->format('Y-m-d'),
                    'premium'         => (float) $financials['premium'],
                    'annual_premium'  => (float) $financials['premium'],
                    'frequency'       => 'once',
                    'trans_type'      => 'NEW BUSINESS',
                    'status'          => 'Active',
                    'created_at'      => $issuedAt,
                    'updated_at'      => Carbon::now(),
                ]);
            }
            if (Schema::hasTable('policy_actions')) {
                $issuedBy = (array) ($p['issued_by'] ?? []);
                $this->insertInto('policy_actions', [
                    'policy_id'        => $policyId,
                    'term_id'          => $termId,
                    'policy_quote_no'  => $policyNumber . '/01',
                    'effective_from'   => $coverStart->format('Y-m-d'),
                    'effective_to'     => $coverEnd->format('Y-m-d'),
                    'transaction_type' => 'NEWBUSINESS',
                    'transaction_date' => $issuedAt->format('Y-m-d'),
                    'status'           => 'ISSUED',
                    'premium'          => (float) $financials['premium'],
                    'note'             => 'Alpha Transit Cover — issued on ATC platform'
                        . (isset($issuedBy['email']) ? ' by ' . $issuedBy['email'] : ''),
                    'created_at'       => $issuedAt,
                    'updated_at'       => Carbon::now(),
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        // Shipment detail (route/goods/parties) — V2 side. Outside the legacy
        // transaction: if this insert fails the event is marked failed, ATC
        // retries, and the duplicate path above heals the missing row.
        $this->upsertShipment($p, $policyId, $coverStart, $coverEnd);

        // mis_premium_data intentionally NOT written: the table has no schema
        // and no writer anywhere in the repo. Premium/sum-insured for MIS
        // reporting live on atc_shipments until Finance defines the shape.

        return $this->result($policyId, 'policy', false);
    }

    private function upsertShipment(array $p, int $policyId, Carbon $coverStart, Carbon $coverEnd): void
    {
        $sender     = (array) ($p['sender'] ?? []);
        $receiver   = (array) ($p['receiver'] ?? []);
        $route      = (array) ($p['route'] ?? []);
        $goods      = (array) ($p['goods'] ?? []);
        $financials = (array) ($p['financials'] ?? []);
        $issuedBy   = (array) ($p['issued_by'] ?? []);

        $this->sys()->table('atc_shipments')->updateOrInsert(
            ['atc_policy_id' => (int) ($p['atc_policy_id'] ?? 0)],
            [
                'policy_id'         => $policyId,
                'policy_number'     => (string) $p['policy_number'],
                'company_code'      => (string) $p['company_code'],
                'issued_by_email'   => $issuedBy['email'] ?? null,
                'issued_by_name'    => $issuedBy['name'] ?? null,
                'sender_name'       => (string) ($sender['name'] ?? ''),
                'sender_phone'      => $sender['phone'] ?? null,
                'sender_email'      => $sender['email'] ?? null,
                'receiver_name'     => $receiver['name'] ?? null,
                'receiver_phone'    => $receiver['phone'] ?? null,
                'receiver_email'    => $receiver['email'] ?? null,
                'from_zone'         => (string) ($route['from_zone'] ?? ''),
                'from_town'         => $route['from_town'] ?? null,
                'to_zone'           => (string) ($route['to_zone'] ?? ''),
                'to_town'           => $route['to_town'] ?? null,
                'goods_category'    => strtoupper((string) ($goods['category'] ?? '')),
                'goods_description' => isset($goods['description']) ? mb_substr((string) $goods['description'], 0, 500) : null,
                'declared_value'    => (float) ($goods['declared_value'] ?? 0),
                'weight_kg'         => isset($goods['weight_kg']) ? (float) $goods['weight_kg'] : null,
                'sum_insured'       => (float) ($financials['sum_insured'] ?? 0),
                'premium'           => (float) ($financials['premium'] ?? 0),
                'excess'            => isset($financials['excess']) ? (float) $financials['excess'] : null,
                'rate'              => isset($financials['rate']) ? (float) $financials['rate'] : null,
                'currency'          => (string) ($financials['currency'] ?? 'BWP'),
                'cover_start'       => $coverStart->format('Y-m-d'),
                'cover_end'         => $coverEnd->format('Y-m-d'),
                'courier_waybill'   => $p['courier_waybill'] ?? null,
                'service_type'      => $p['service_type'] ?? null,
                'courier_fee'       => isset($p['courier_fee']) ? (float) $p['courier_fee'] : null,
                'issued_at'         => isset($p['issued_at']) ? $this->parseDate($p['issued_at'], 'issued_at') : Carbon::now(),
                'created_at'        => Carbon::now(),
                'updated_at'        => Carbon::now(),
            ]
        );
    }

    // ─── policy.payment_status_changed ──────────────────────────────────────

    private function paymentStatusChanged(array $p): array
    {
        $policyNumber = trim((string) ($p['policy_number'] ?? ''));
        $to           = $p['changes']['payment_status']['to'] ?? null;
        if ($policyNumber === '' || !is_string($to) || $to === '') {
            throw new AtcRejection(422, 'malformed_payload', 'policy.payment_status_changed requires policy_number and changes.payment_status.to');
        }

        $policy = DB::table('policies')->where('policyNumber', $policyNumber)->first(['id']);
        if (!$policy) {
            // 409, not 400: ATC treats any 4xx except 409 as terminal. An
            // unknown policy here is usually just out-of-order delivery
            // (policy.created still in retry backoff), so let the queue
            // redeliver instead of parking the event for manual review.
            throw new AtcRejection(409, 'policy_not_found', "Unknown policy_number '$policyNumber' — policy.created not ingested yet, retry");
        }

        // policies has no payment_status column — settlement state lives on
        // the shipment record (cover stays active; courier remits monthly).
        $this->sys()->table('atc_shipments')
            ->where('policy_number', $policyNumber)
            ->update(['payment_status' => mb_substr($to, 0, 20), 'updated_at' => Carbon::now()]);

        return $this->result((int) $policy->id, 'policy', false);
    }

    // ─── payment.received ────────────────────────────────────────────────────

    private function paymentReceived(array $p): array
    {
        $atcPaymentId = $p['atc_payment_id'] ?? null;
        $settled      = $p['policies_settled'] ?? null;
        if (!is_numeric($atcPaymentId) || !isset($p['amount']) || !is_array($settled)) {
            throw new AtcRejection(422, 'malformed_payload', 'payment.received requires atc_payment_id, amount and policies_settled[]');
        }
        $atcPaymentId = (int) $atcPaymentId;

        $existing = $this->sys()->table('atc_payments')->where('atc_payment_id', $atcPaymentId)->first();
        if ($existing && $existing->status === 'recorded') {
            return $this->result((int) $existing->id, 'payment', true);
        }

        $paymentDate = isset($p['payment_date']) ? $this->parseDate($p['payment_date'], 'payment_date')->format('Y-m-d') : null;
        // The row stays 'partial' until every money leg is written — 'recorded'
        // is the completion marker the duplicate short-circuit above trusts, so
        // promoting it early would make a mid-loop failure unrepairable (the
        // ATC retry would be answered "duplicate" with legs still missing).
        $row = [
            'company_code'      => (string) ($p['company_code'] ?? ''),
            'payment_type'      => $p['payment_type'] ?? 'monthly_recon',
            'reference_month'   => $p['reference_month'] ?? null,
            'bank_reference'    => $p['bank_reference'] ?? null,
            'amount'            => (float) $p['amount'],
            'currency'          => (string) ($p['currency'] ?? 'BWP'),
            'payment_date'      => $paymentDate,
            'policies_settled'  => json_encode(array_values($settled)),
            'policies_count'    => count($settled),
            'status'            => 'partial',
            'recorded_by_email' => $p['recorded_by']['email'] ?? null,
            'notes'             => isset($p['notes']) ? mb_substr((string) $p['notes'], 0, 500) : null,
            'recorded_at'       => isset($p['recorded_at']) ? $this->parseDate($p['recorded_at'], 'recorded_at') : Carbon::now(),
            'updated_at'        => Carbon::now(),
        ];

        if ($existing) {
            // recon.monthly_settled arrived first and parked a partial row —
            // complete it now that the full payload is here.
            $this->sys()->table('atc_payments')->where('id', $existing->id)->update($row);
            $paymentRowId = (int) $existing->id;
        } else {
            $paymentRowId = (int) $this->sys()->table('atc_payments')->insertGetId(
                $row + ['atc_payment_id' => $atcPaymentId, 'created_at' => Carbon::now()]
            );
        }

        // One money leg per settled policy, each for that policy's own premium
        // (the remittance is the sum of premiums — Finance outputs must balance).
        // Leg dedupe keys on a reference derived from the ATC payment id, never
        // the bank narrative: bank references repeat across months, and a
        // repeated narrative must not suppress a legitimate month-2 leg. The
        // bank narrative is still kept on the atc_payments row above.
        $warnings  = [];
        $reference = 'ATC-RECON-' . $atcPaymentId;
        $sumLegs   = 0.0;
        $unknown   = [];
        $settled   = array_values(array_unique(array_map('strval', $settled)));

        foreach ($settled as $number) {
            $policy = DB::table('policies')->where('policyNumber', $number)->first(['id', 'premium']);
            if (!$policy) {
                $unknown[] = $number;
                continue;
            }
            $legExists = DB::table('payment_transactions')
                ->where('policyNumber', $number)
                ->where('referenceNumber', $reference)
                ->exists();
            if (!$legExists) {
                $this->insertInto('payment_transactions', [
                    'policy_id'        => $policy->id,
                    'policyNumber'     => $number,
                    'referenceNumber'  => $reference,
                    'amount'           => (float) $policy->premium,
                    'status'           => 'SUCCESS',
                    'paymentDate'      => $paymentDate,
                    'new_payment_date' => $paymentDate,
                    'paymentMethod'    => 'EFT',
                    'note'             => 'ATC monthly recon ' . ($p['reference_month'] ?? '') . ' — courier remittance'
                        . ($p['bank_reference'] ? ' (bank ref: ' . mb_substr((string) $p['bank_reference'], 0, 60) . ')' : ''),
                    'created_at'       => Carbon::now(),
                    'updated_at'       => Carbon::now(),
                ]);
            }
            $sumLegs += (float) $policy->premium;
        }

        // A settled policy we have not ingested yet (policy.created still in
        // retry backoff, or ingestion was dark) is a RETRYABLE condition, not
        // a warning to swallow: the row stays 'partial' and the thrown error
        // becomes a 500, so ATC's queue redelivers until the policy exists.
        // Legs already written are deduped on the replay, so nothing doubles.
        if ($unknown !== []) {
            Log::warning('ATC recon: settled policies not yet ingested — event left retryable', [
                'atc_payment_id' => $atcPaymentId, 'unknown' => $unknown,
            ]);
            throw new \RuntimeException(
                'payment.received: ' . count($unknown) . ' settled policy number(s) not ingested yet ('
                . implode(', ', array_slice($unknown, 0, 10)) . ') — retry after policy.created lands'
            );
        }

        $this->sys()->table('atc_shipments')
            ->whereIn('policy_number', $settled)
            ->update(['payment_status' => 'settled', 'updated_at' => Carbon::now()]);

        // All legs written and shipments settled — promote to 'recorded' so
        // replays are answered as duplicates from here on.
        $this->sys()->table('atc_payments')->where('id', $paymentRowId)
            ->update(['status' => 'recorded', 'updated_at' => Carbon::now()]);

        if (abs($sumLegs - (float) $p['amount']) > 0.01) {
            $warnings[] = sprintf(
                'remittance amount %.2f does not equal the sum of settled policy premiums %.2f — flag to Finance',
                (float) $p['amount'],
                $sumLegs
            );
            Log::warning('ATC recon amount drift', [
                'atc_payment_id' => $atcPaymentId,
                'amount'         => (float) $p['amount'],
                'sum_premiums'   => $sumLegs,
            ]);
        }

        return $this->result($paymentRowId, 'payment', false, $warnings);
    }

    // ─── recon.monthly_settled ───────────────────────────────────────────────

    private function reconMonthlySettled(array $p): array
    {
        $atcPaymentId = $p['atc_payment_id'] ?? null;
        if (!is_numeric($atcPaymentId)) {
            throw new AtcRejection(422, 'malformed_payload', 'recon.monthly_settled requires atc_payment_id');
        }

        // Duplicate-safe re-confirmation of the same settlement (Reply §04.4).
        $existing = $this->sys()->table('atc_payments')->where('atc_payment_id', (int) $atcPaymentId)->first(['id']);
        if ($existing) {
            return $this->result((int) $existing->id, 'payment', true);
        }

        // Recon arrived before payment.received — park a partial row; the full
        // payload completes it (paymentReceived's $existing branch).
        $id = (int) $this->sys()->table('atc_payments')->insertGetId([
            'atc_payment_id'    => (int) $atcPaymentId,
            'company_code'      => (string) ($p['company_code'] ?? ''),
            'payment_type'      => 'monthly_recon',
            'reference_month'   => $p['reference_month'] ?? null,
            'amount'            => (float) ($p['amount'] ?? 0),
            'policies_count'    => (int) ($p['policies_settled_count'] ?? 0),
            'status'            => 'partial',
            'recorded_by_email' => $p['actor']['email'] ?? null,
            'recorded_at'       => isset($p['settled_at']) ? $this->parseDate($p['settled_at'], 'settled_at') : Carbon::now(),
            'created_at'        => Carbon::now(),
            'updated_at'        => Carbon::now(),
        ]);

        return $this->result($id, 'payment', false);
    }

    // ─── claim.created ───────────────────────────────────────────────────────

    private function claimCreated(array $p): array
    {
        $atcClaimId   = $p['atc_claim_id'] ?? null;
        $claimNumber  = trim((string) ($p['claim_number'] ?? ''));
        $policyNumber = trim((string) ($p['policy_number'] ?? ''));
        if (!is_numeric($atcClaimId) || $claimNumber === '' || $policyNumber === '') {
            throw new AtcRejection(422, 'malformed_payload', 'claim.created requires atc_claim_id, claim_number and policy_number');
        }

        $policy = DB::table('policies')->where('policyNumber', $policyNumber)->first(['id', 'customer_id']);
        if (!$policy) {
            // 409 = retryable to ATC (out-of-order claim before its policy).
            throw new AtcRejection(409, 'policy_not_found', "Unknown policy_number '$policyNumber' — policy.created not ingested yet, retry");
        }

        $existing = $this->sys()->table('atc_claims')->where('atc_claim_id', (int) $atcClaimId)->first(['claim_id']);
        if ($existing && $existing->claim_id) {
            return $this->result((int) $existing->claim_id, 'claim', true);
        }

        $claimant = (array) ($p['claimant'] ?? []);
        $filedAt  = isset($p['filed_at']) ? $this->parseDate($p['filed_at'], 'filed_at') : Carbon::now();

        // Replay safety across the two connections: the legacy claims insert
        // below and the atc_claims anchor upsert cannot share a transaction
        // (default connection vs mysql_system). If a prior attempt crashed
        // between the two writes, the atc_claims lookup above finds nothing
        // but the legacy claim already exists — claims has no unique index on
        // claim_number, so a blind re-insert would open a SECOND claim for
        // the same incident. Dedupe on the natural key first and just
        // backfill the anchor.
        $legacyClaim = DB::table('claims')->where('claim_number', $claimNumber)->first(['id']);
        if ($legacyClaim) {
            $claimId = (int) $legacyClaim->id;
        } else {
            $claimId = $this->insertInto('claims', [
            'customer_id'          => $policy->customer_id,
            'policy_id'            => $policy->id,
            'claim_number'         => $claimNumber, // ATC-allocated — kept verbatim so claim.updated can find it
            'status'               => 'New',
            'incident_date'        => isset($p['incident_date']) ? $this->parseDate($p['incident_date'], 'incident_date')->format('Y-m-d') : null,
            'incident_description' => isset($p['description']) ? (string) $p['description'] : null,
            'type_of_loss'         => $p['incident_type'] ?? null,
            'note'                 => 'Alpha Transit Cover claim'
                . (isset($p['filed_by']['email']) ? ' — filed by ' . $p['filed_by']['email'] : ''),
            'reported_by'          => $p['filed_by']['email'] ?? null,
            'reported_date'        => $filedAt->format('Y-m-d'),
            'created_at'           => $filedAt,
            'updated_at'           => Carbon::now(),
            ]);
        }

        $this->sys()->table('atc_claims')->updateOrInsert(
            ['atc_claim_id' => (int) $atcClaimId],
            [
                'claim_id'       => $claimId,
                'claim_number'   => $claimNumber,
                'policy_number'  => $policyNumber,
                'company_code'   => $p['company_code'] ?? null,
                'incident_type'  => $p['incident_type'] ?? null,
                'status'         => 'open',
                'claim_amount'   => isset($p['claim_amount']) ? (float) $p['claim_amount'] : null,
                'claimant_name'  => $claimant['name'] ?? null,
                'claimant_phone' => $claimant['phone'] ?? null,
                'claimant_email' => $claimant['email'] ?? null,
                'filed_by_email' => $p['filed_by']['email'] ?? null,
                'filed_at'       => $filedAt,
                'created_at'     => Carbon::now(),
                'updated_at'     => Carbon::now(),
            ]
        );

        return $this->result($claimId, 'claim', false);
    }

    // ─── claim.updated ───────────────────────────────────────────────────────

    private function claimUpdated(array $p): array
    {
        // claim.updated carries only atc_claim_id + claim_number — no policy
        // or company reference (services/events.js::claimUpdated).
        $atcClaimId  = $p['atc_claim_id'] ?? null;
        $claimNumber = trim((string) ($p['claim_number'] ?? ''));
        if (!is_numeric($atcClaimId) && $claimNumber === '') {
            throw new AtcRejection(422, 'malformed_payload', 'claim.updated requires atc_claim_id or claim_number');
        }

        $detail = null;
        if (is_numeric($atcClaimId)) {
            $detail = $this->sys()->table('atc_claims')->where('atc_claim_id', (int) $atcClaimId)->first();
        }
        if (!$detail && $claimNumber !== '') {
            $detail = $this->sys()->table('atc_claims')->where('claim_number', $claimNumber)->first();
        }
        if (!$detail || !$detail->claim_id) {
            // 409 = retryable to ATC (claim.updated racing its claim.created).
            throw new AtcRejection(409, 'policy_not_found', "Unknown claim '" . ($claimNumber !== '' ? $claimNumber : ('atc:' . $atcClaimId)) . "' — claim.created not ingested yet, retry");
        }

        $changes      = (array) ($p['changes'] ?? []);
        $claimUpdate  = ['updated_at' => Carbon::now()];
        $detailUpdate = ['updated_at' => Carbon::now()];

        if (isset($changes['status']['to']) && is_string($changes['status']['to'])) {
            $to = $changes['status']['to'];
            $detailUpdate['status'] = mb_substr($to, 0, 32);
            $claimUpdate['status']  = self::CLAIM_STATUS_MAP[$to] ?? ucfirst($to);
        }
        if (array_key_exists('settled_amount', $changes) && isset($changes['settled_amount']['to'])) {
            // No settled_amount column on legacy claims — recorded on atc_claims.
            $detailUpdate['settled_amount'] = (float) $changes['settled_amount']['to'];
        }
        if (isset($p['note']) && trim((string) $p['note']) !== '') {
            $at = isset($p['at']) ? (string) $p['at'] : Carbon::now()->toIso8601String();
            $existingNote = (string) (DB::table('claims')->where('id', $detail->claim_id)->value('note') ?? '');
            $claimUpdate['note'] = trim($existingNote . "\n[ATC $at] " . trim((string) $p['note']));
        }

        DB::table('claims')->where('id', $detail->claim_id)->update($claimUpdate);
        $this->sys()->table('atc_claims')->where('id', $detail->id)->update($detailUpdate);

        return $this->result((int) $detail->claim_id, 'claim', false);
    }

    // ─── Customer dedup (Brief §06) ──────────────────────────────────────────

    /**
     * Sender becomes a customer on first issuance. Dedup key, in order:
     *  1. normalised phone + email both match an existing customer → reuse;
     *  2. normalised phone matches AND name fuzzy-match ≥ 90% → reuse;
     *  3. else create.
     * Receiver is captured on atc_shipments only (not a customer in Phase 1).
     */
    private function matchOrCreateCustomer(array $sender): int
    {
        $name  = trim((string) $sender['name']);
        $phone = trim((string) ($sender['phone'] ?? ''));
        $email = strtolower(trim((string) ($sender['email'] ?? '')));

        if ($phone !== '') {
            $digits = preg_replace('/\D+/', '', $phone);
            $last8  = substr($digits, -8);
            $variants = array_values(array_unique(array_filter([
                $phone, $digits, $last8, '267' . $last8, '+267' . $last8,
                '+267 ' . substr($last8, 0, 4) . ' ' . substr($last8, 4),
            ])));

            $candidates = DB::table('customer')
                ->whereIn('cellphone', $variants)
                ->orderByDesc('id')
                ->limit(50)
                ->get(['id', 'firstName', 'lastName', 'email']);

            if ($email !== '') {
                foreach ($candidates as $c) {
                    if (strtolower(trim((string) $c->email)) === $email) {
                        return (int) $c->id;
                    }
                }
            }
            foreach ($candidates as $c) {
                $candidateName = trim(trim((string) $c->firstName) . ' ' . trim((string) $c->lastName));
                similar_text(mb_strtolower($name), mb_strtolower($candidateName), $pct);
                if ($pct >= 90) {
                    return (int) $c->id;
                }
            }
        }

        // Split "Boitumelo Pharma" → firstName="Boitumelo", lastName="Pharma";
        // a single-word name lands wholly in firstName.
        $parts    = preg_split('/\s+/', $name);
        $lastName = count($parts) > 1 ? array_pop($parts) : '';

        $customerId = $this->insertInto('customer', [
            'firstName'  => implode(' ', $parts),
            'lastName'   => $lastName,
            'email'      => $email !== '' ? $email : null,
            'cellphone'  => $phone !== '' ? $phone : null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Minimal profile shell so joins that expect customer_profile hold.
        if (Schema::hasTable('customer_profile')) {
            $this->insertInto('customer_profile', [
                'customer_id' => $customerId,
                'created_at'  => Carbon::now(),
                'updated_at'  => Carbon::now(),
            ]);
        }

        return $customerId;
    }

    // ─── Courier / product resolution ────────────────────────────────────────

    private function courierAgencyId(string $companyCode): ?int
    {
        $courier = $this->sys()->table('atc_couriers')->where('company_code', $companyCode)->first();

        if (!$courier) {
            // Unknown courier code — register it so ingestion never blocks a
            // live policy; ops rename it from the registry afterwards.
            Log::warning('ATC: unknown company_code — auto-registering courier', ['company_code' => $companyCode]);
            $this->sys()->table('atc_couriers')->insert([
                'company_code' => $companyCode,
                'name'         => $companyCode,
                'status'       => 1,
                'created_at'   => Carbon::now(),
                'updated_at'   => Carbon::now(),
            ]);
            $courier = $this->sys()->table('atc_couriers')->where('company_code', $companyCode)->first();
        }

        if ($courier->agency_id) {
            return (int) $courier->agency_id;
        }

        if (!Schema::hasTable('agencies')) {
            return null;
        }
        $agencyId = DB::table('agencies')->where('name', $courier->name)->value('id');
        if (!$agencyId) {
            $agencyId = DB::table('agencies')->insertGetId([
                'name'       => $courier->name,
                'status'     => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
        $this->sys()->table('atc_couriers')->where('id', $courier->id)
            ->update(['agency_id' => $agencyId, 'updated_at' => Carbon::now()]);

        return (int) $agencyId;
    }

    private function productId(): int
    {
        if ($this->productId !== null) {
            return $this->productId;
        }

        $q = DB::table('products');
        $id = Schema::hasColumn('products', 'slug')
            ? $q->where('slug', self::PRODUCT_SLUG)->orWhere('name', self::PRODUCT_NAME)->value('id')
            : $q->where('name', self::PRODUCT_NAME)->value('id');

        if (!$id) {
            // Retryable (5xx path): the seed migration hasn't run in this env.
            throw new \RuntimeException('Alpha Transit Cover product row missing — run migration 2026_09_03_120001');
        }

        return $this->productId = (int) $id;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function parseDate($value, string $field): Carbon
    {
        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable $e) {
            throw new AtcRejection(422, 'malformed_payload', "Unparseable date in '$field': " . json_encode($value));
        }
    }

    /**
     * Defensive insert — filter the row through the live column listing so an
     * environment missing an optional column can't fail the insert (same idiom
     * as ThirdPartyCarController and its siblings).
     */
    private function insertInto(string $table, array $row): int
    {
        $cols = DB::getSchemaBuilder()->getColumnListing($table);
        return (int) DB::table($table)->insertGetId(array_intersect_key($row, array_flip($cols)));
    }

    private function sys()
    {
        return DB::connection('mysql_system');
    }

    /** @return array{graphite_id:int, duplicate:bool, entity_type:string, warnings:string[]} */
    private function result(int $graphiteId, string $entityType, bool $duplicate, array $warnings = []): array
    {
        return [
            'graphite_id' => $graphiteId,
            'duplicate'   => $duplicate,
            'entity_type' => $entityType,
            'warnings'    => $warnings,
        ];
    }
}
