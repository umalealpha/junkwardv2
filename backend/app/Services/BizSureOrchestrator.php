<?php

namespace AlphaDirect\Services;

use AlphaDirect\City;
use AlphaDirect\Customer;
use AlphaDirect\CustomerBanking;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Helper;
use AlphaDirect\KYC;
use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Models\PolicyCoverageDetail;
use AlphaDirect\Models\PolicyDirector;
use AlphaDirect\Models\PolicyExtentionDetails;
use AlphaDirect\Models\PolicyKycDocument;
use AlphaDirect\Models\PolicySpecifiedItem;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Models\SpecifiedCoveragesItems;
use AlphaDirect\Policy;
use AlphaDirect\PolicyTerm;
use AlphaDirect\State;
use AlphaDirect\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates BizSure /createPolicy traffic — coordinates V2 models
 * to fulfill BizSure's create-policy contract WITHOUT modifying V2's
 * existing PolicyCreateController.
 *
 * Build progress (Phase 2c, 6 commits):
 *   [2c-1] resolveOrCreateCustomer · upsertCustomerProfile · persistCommercialKyc
 *   [2c-2] createPolicyShell (COMD prefix + 12 business fields) · createPolicyTermAction · activity-log actor helper

 *   [2c-3] createRiskAddress with state/city FK resolution (Botswana Gaborone quirk handling)
 *   [2c-4] buildCoverageTree (parents + sub + extensions + portable items)
 *   [2c-5] createDirectors · persistDirectorKycDocuments
 *   [2c-6] issueAndInvoice + RealPay activation hook   ← THIS COMMIT (Phase 2c complete)
 *
 * BizSure customers are ALWAYS persons — the business owner is the
 * Customer record; business metadata (business_name, business_structure,
 * etc.) lives on the policies table (lands in 2c-2). Pty Ltd directors
 * persist into the policy_directors table (lands in 2c-5).
 */
class BizSureOrchestrator
{
    /**
     * Main entry point. Called by BizSurePolicyController after the
     * controller's payload-shape validation. Wraps the full BizSure
     * create-policy flow in a single DB transaction.
     */
    public function createPolicy(array $payload): array
    {
        // Idempotency: BizSure's Rails client retries createPolicy on timeout
        // / 5xx. Without a stable key per quote, each retry created a whole new
        // policy + invoice + RealPay intent. If this quote already became a
        // policy, return that one instead of creating a duplicate.
        $ref = $this->resolveIdempotencyKey($payload);
        if ($ref !== null) {
            $existing = Policy::where('leadSource', 'bizsure')
                ->where('bizsure_ref', $ref)
                ->first();
            if ($existing !== null) {
                Log::info('[BizSureOrchestrator] idempotent hit — returning existing policy', [
                    'bizsure_ref' => $ref,
                    'policy_id'   => $existing->id,
                ]);
                return $this->existingPolicyResponse($existing, $ref);
            }
        } else {
            Log::warning('[BizSureOrchestrator] no idempotency key on payload — a retry of this request will create a duplicate policy');
        }

        try {
            return DB::transaction(function () use ($payload, $ref) {
                $actorLabel = $this->resolveActorLabel($payload);

                $customer = $this->resolveOrCreateCustomer($payload);
                $profile  = $this->upsertCustomerProfile($customer, $payload);
                $kyc      = $this->persistCommercialKyc($customer, $payload);

                $policy = $this->createPolicyShell($customer, $payload, $actorLabel, $ref);
                [$termId, $actionId] = $this->createPolicyTermAction($policy, $payload, $actorLabel);
                $riskAddress = $this->createRiskAddress($customer, $policy, $termId, $actionId, $payload, $actorLabel);
                $coverages   = $this->buildCoverageTree($policy, $riskAddress, $termId, $actionId, $payload, $actorLabel);
                $directors   = $this->createDirectors($policy, $termId, $actionId, $payload, $actorLabel);
                $vehicles    = $this->createVehicles($policy, $riskAddress, $termId, $actionId, $payload, $actorLabel);
                $issued      = $this->issueAndInvoice($policy, $termId, $actionId, $coverages, $payload, $actorLabel);

                // CustomerBanking row — RealPay debit-order setup reads it (V1:1497-1517).
                $this->createCustomerBanking($customer, $policy, $payload, $actorLabel);

                // PDF dispatch — non-fatal; policy is already ISSUED if this throws
                $pdfStatus = $this->dispatchPolicyDocument($policy);

                // RealPay inline — fire immediately when Payment_method=RealPay (matches V1 behaviour)
                $realpayStatus = null;
                if ($issued['realpay_required']) {
                    $realpayStatus = $this->triggerRealPayInline($policy, $payload, $actorLabel);
                }

                return [
                    'phase'            => 'full-parity',
                    'customer_id'      => $customer->id,
                    'customer_created' => $customer->wasRecentlyCreated,
                    'profile_id'       => $profile->id ?? null,
                    'kyc_id'           => $kyc?->id,
                    'policy_id'        => $policy->id,
                    'policy_number'    => $policy->policyNumber,
                    'bizsure_ref'      => $ref,
                    'term_id'          => $termId,
                    'action_id'        => $actionId,
                    'risk_address_id'  => $riskAddress->id,
                    'coverages'        => $coverages,
                    'directors_count'  => $directors['count'],
                    'director_kyc_docs_count' => $directors['kyc_docs'],
                    'vehicles_count'   => $vehicles,
                    'policy_status'    => $issued['policy_status'],
                    'total_premium'    => $issued['total_premium'],
                    'vat'              => $issued['vat'],
                    'invoice_status'   => $issued['invoice_status'],
                    'payment_method'   => $issued['payment_method'],
                    'realpay_required' => $issued['realpay_required'],
                    'realpay_status'   => $realpayStatus,
                    'pdf_status'       => $pdfStatus,
                    'actor'            => $actorLabel,
                ];
            });
        } catch (QueryException $e) {
            // Concurrency backstop: a simultaneous first-time retry won the
            // race and committed the same bizsure_ref (UNIQUE index). Return
            // that policy instead of surfacing a duplicate-key 500.
            if ($ref !== null && $this->isDuplicateRefViolation($e)) {
                $existing = Policy::where('leadSource', 'bizsure')
                    ->where('bizsure_ref', $ref)
                    ->first();
                if ($existing !== null) {
                    return $this->existingPolicyResponse($existing, $ref);
                }
            }
            throw $e;
        }
    }

    /**
     * Find an existing Customer by email+cellphone, then by cellphone,
     * then by omang/passport on CustomerProfile. Create new if no match.
     * Refresh name/email/cellphone on matched customers so the latest
     * BizSure-supplied values win without nuking other fields.
     */
    private function resolveOrCreateCustomer(array $p): Customer
    {
        $email = $this->stringField($p, 'email');
        $phone = $this->stringField($p, 'phone');

        $existing = null;

        if ($email !== null && $phone !== null) {
            $existing = Customer::where('email', $email)
                ->where('cellphone', $phone)
                ->first();
        }

        if ($existing === null && $phone !== null) {
            $existing = Customer::where('cellphone', $phone)->orderBy('id')->first();
        }

        if ($existing === null) {
            $omang    = $this->stringField($p, 'omang');
            $passport = $this->stringField($p, 'passport');
            if ($omang !== null || $passport !== null) {
                $profile = CustomerProfile::where(function ($q) use ($omang, $passport) {
                    if ($omang !== null) {
                        $q->orWhere('omang', $omang);
                    }
                    if ($passport !== null) {
                        $q->orWhere('passport', $passport);
                    }
                })->orderBy('id')->first();
                if ($profile !== null) {
                    $existing = Customer::find($profile->customer_id);
                }
            }
        }

        if ($existing !== null) {
            $existing->update(array_filter([
                'firstName'  => $this->stringField($p, 'firstname'),
                'middleName' => $this->stringField($p, 'middlename'),
                'lastName'   => $this->stringField($p, 'lastname'),
                'email'      => $email,
                'cellphone'  => $phone,
            ], fn ($v) => $v !== null));
            return $existing->fresh();
        }

        return Customer::create([
            'firstName'  => $this->stringField($p, 'firstname'),
            'middleName' => $this->stringField($p, 'middlename'),
            'lastName'   => $this->stringField($p, 'lastname'),
            'email'      => $email,
            'cellphone'  => $phone,
        ]);
    }

    /**
     * Upsert CustomerProfile with BizSure metadata. entity_type is
     * always 'Person' — BizSure business is policy metadata, not a
     * separate Organisation customer.
     */
    private function upsertCustomerProfile(Customer $customer, array $p): CustomerProfile
    {
        $data = [
            'entity_type'   => 'Person',
            'omang'         => $this->stringField($p, 'omang'),
            'passport'      => $this->stringField($p, 'passport'),
            'gender'        => $this->stringField($p, 'gender'),
            'maritalstatus' => $this->stringField($p, 'maritalstatus'),
            'state'         => $this->stringField($p, 'state'),
            'city'          => $this->stringField($p, 'city'),
            'address'       => $this->stringField($p, 'address'),
            'post_address'  => $this->stringField($p, 'postal_address') ?? $this->stringField($p, 'address'),
        ];

        $dob = $this->stringField($p, 'dob');
        if ($dob !== null) {
            try {
                $data['dob'] = Carbon::parse($dob);
            } catch (\Throwable $e) {
                Log::warning('[BizSureOrchestrator] dob parse failed', [
                    'dob'   => $dob,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return CustomerProfile::updateOrCreate(
            ['customer_id' => $customer->id],
            array_filter($data, fn ($v) => $v !== null)
        );
    }

    /**
     * Persist BizSure commercial KYC certs (company_reg, company_extract,
     * bors, tin) onto customer_kyc. These S3 paths were uploaded earlier
     * via the separate /uploadKycImages endpoint and arrive here as
     * already-stored URIs in the createPolicy payload.
     *
     * Skipped if no commercial certs are present — sole-proprietor
     * customers may not have a CIPA company cert yet. Uses direct
     * property assignment rather than mass-assign so V2's KYC model
     * fillable array stays untouched.
     */
    private function persistCommercialKyc(Customer $customer, array $p): ?KYC
    {
        $commercialFields = [
            'company_reg'     => $this->stringField($p, 'company_reg'),
            'company_extract' => $this->stringField($p, 'company_extract'),
            'bors'            => $this->stringField($p, 'bors'),
            'tin'             => $this->stringField($p, 'tin'),
        ];

        $present = array_filter($commercialFields, fn ($v) => $v !== null);
        if (empty($present)) {
            return null;
        }

        $kyc = KYC::firstOrCreate(['customer_id' => $customer->id]);
        foreach ($present as $col => $value) {
            $kyc->{$col} = $value;
        }
        $kyc->save();

        return $kyc;
    }

    /**
     * Create the Policy shell row with COMD prefix and all 12 BizSure
     * business fields populated. status=0 (Quote), is_draft=1 — BizSure
     * policies enter as quotes and get issued via the issuePolicy step
     * in 2c-6 after coverages land.
     *
     * Policy number: COMD = "Commercial entered Digitally" (via external
     * BizSure client). Distinct from COMG = "Commercial entered via
     * Graphite admin UI" so audit / MIS reports can tell channels apart.
     */
    private function createPolicyShell(Customer $customer, array $p, string $actorLabel, ?string $ref = null): Policy
    {
        $productId = (int) $this->stringField($p, 'product');

        $termStart = $this->parseDateOrNull($p, 'term_start_date')
            ?? $this->parseDateOrNull($p, 'binder_date')
            ?? Carbon::now()->startOfDay();

        $expiry = $this->parseDateOrNull($p, 'expiry_date')
            ?? $termStart->copy()->addYear()->subDay();

        $policyNo = $this->generateCOMDPolicyNumber();

        $policy = Policy::create(array_filter([
            // standard policy shell
            'customer_id'        => $customer->id,
            'product_id'         => $productId ?: null,
            'plan_id'            => $this->stringField($p, 'plan_id'),
            'agency_id'          => $this->stringField($p, 'agency_id'),
            // V1 writes policies.agent_id from the BizSure `agentCode` field
            // (legacy CommonApis/PolicyController:825). Keep `agent_id` as a
            // fallback for any caller that sends the resolved id directly.
            'agent_id'           => $this->stringField($p, 'agentCode')
                                     ?? $this->stringField($p, 'agent_id'),
            'premium_freq'       => $this->stringField($p, 'frequency')
                                     ?? $this->stringField($p, 'premium_freq'),
            'policyNumber'       => $policyNo,
            'term_start_date'    => $termStart,
            'expiry_date'        => $expiry,
            'status'             => 0,
            'is_draft'           => 1,
            'leadSource'         => 'bizsure',
            'bizsure_ref'        => $ref,
            'billingStartDate'   => $this->parseDateOrNull($p, 'binder_date'),
            'note'               => $this->stringField($p, 'note'),
            'gfs_policy_no'      => $this->stringField($p, 'gfs_policy_no'),

            // 12 BizSure business fields (added by migration 2026_05_25_210003)
            'business_name'      => $this->stringField($p, 'business_name'),
            'occupation_type'    => $this->stringField($p, 'occupation_type'),
            'annual_turnover'    => $this->numericFieldOrNull($p, 'annual_turnover'),
            'years_in_business'  => $this->intFieldOrNull($p, 'years_in_business'),
            'number_of_employees'=> $this->intFieldOrNull($p, 'number_of_employees'),
            'floor_area_sqm'     => $this->intFieldOrNull($p, 'floor_area_sqm'),
            'property_ownership' => $this->stringField($p, 'property_ownership'),
            'business_structure' => $this->stringField($p, 'business_structure'),
            'company_reg_number' => $this->stringField($p, 'company_reg_number'),
            'tin_number'         => $this->stringField($p, 'tin_number'),
            'vat_number'         => $this->stringField($p, 'vat_number'),
            'postal_address'     => $this->stringField($p, 'postal_address'),
        ], fn ($v) => $v !== null));

        // Re-stamp the policy number to the ACTUAL row id (V1-proven; COO
        // escalation 2026-04-22 — the counter-based suffix drifts from
        // policies.id, e.g. COMD2026000018 on policies.id=128175). The
        // counter value set at insert above is only the placeholder that
        // satisfies the policyNumber UNIQUE index; bind the final number to
        // the real id so admin/MIS reports stay consistent. (V1:999-1005)
        $policy->policyNumber = 'COMD' . Carbon::now()->year
            . str_pad((string) $policy->id, 6, '0', STR_PAD_LEFT);
        $policy->save();

        $this->logActivity('Policy', $policy, "Policy Created via {$actorLabel}", $actorLabel);

        return $policy;
    }

    /**
     * Create the PolicyTerm + PolicyAction pair that V2's admin UI
     * relies on for visibility, earned-premium tracking, and the term/
     * action hierarchy on policy_coverage_detail rows.
     *
     * Mirrors V2 PolicyCreateController::store() lines 388–424.
     */
    private function createPolicyTermAction(Policy $policy, array $p, string $actorLabel): array
    {
        $termId = PolicyTerm::addPolicyTerm([
            'policy_id'          => $policy->id,
            'term_start_date'    => $policy->term_start_date,
            'term_end_date'      => $policy->expiry_date,
            'premium'            => $policy->premium,
            'annual_premium'     => null,
            'vat'                => $policy->vat,
            'vat_percent'        => $policy->vat_percent,
            'renewed_by'         => $policy->agent_id,
            'renewals_date'      => Carbon::now()->addYear()->format('Y-m-d'),
            'frequency'          => $policy->premium_freq,
            'first_premium'      => $policy->first_premium,
            'billing_start_date' => null,
            'policy_documents'   => null,
            'policyActivatedDate'=> null,
            'payment_method'     => null,
            'payment_reference'  => $policy->id,
            'trans_type'         => 'NEW BUSINESS',
            'status'             => 'Active',
            'created_at'         => Carbon::now()->format('Y-m-d'),
        ]);

        $this->logActivity(
            'Policy Term Created',
            $policy,
            'Term Date : ' . $policy->term_start_date . ' - ' . $policy->expiry_date,
            $actorLabel
        );

        $action = PolicyAction::create([
            'policy_id'      => $policy->id,
            'term_id'        => $termId,
            'policy_quote_no'=> $policy->policyNumber . '/01',
            'effective_from' => $policy->term_start_date,
            'effective_to'   => $policy->expiry_date,
            'note'           => 'New Business',
        ]);

        $this->logActivity(
            'Policy Action Created',
            $policy,
            "New Business action {$policy->policyNumber}/01",
            $actorLabel
        );

        return [$termId, $action->id];
    }

    /**
     * Create the risk_address row that holds the business premises
     * metadata. BizSure sends state + city as STRINGS ("South-East" /
     * "Gaborone"); V2 stores them as FK ids in risk_state + risk_city.
     * Resolution falls back gracefully so a typo or unseen value
     * doesn't crash the create — the BizSure raw value is preserved
     * in physical_address (or in a separate field if columns exist)
     * for UW manual repair.
     *
     * Field mapping (BizSure → V2 risk_address):
     *   address          → physical_address
     *   business_name    → address_name (the site name)
     *   state            → risk_state (FK id)
     *   city             → risk_city  (FK id)
     *   occupation_type  → occupation
     *   construction_type→ const_type
     *   area_sqm / area  → area
     *   occupancy_type   → occupancy_type
     *   town_class       → town_class
     */
    private function createRiskAddress(
        Customer $customer,
        Policy $policy,
        int $termId,
        int $actionId,
        array $p,
        string $actorLabel
    ): RiskAddress {
        [$stateId, $cityId] = $this->resolveStateCityIds(
            $this->stringField($p, 'state'),
            $this->stringField($p, 'city')
        );

        $riskAddress = RiskAddress::create(array_filter([
            'customer_id'      => $customer->id,
            'policy_id'        => $policy->id,
            'term_id'          => $termId,
            'action_id'        => $actionId,
            'address_name'     => $this->stringField($p, 'business_name')
                                   ?? $this->stringField($p, 'address_name'),
            'physical_address' => $this->stringField($p, 'address'),
            'risk_state'       => $stateId,
            'risk_city'        => $cityId,
            'occupation'       => $this->stringField($p, 'occupation_type'),
            'town_class'       => $this->stringField($p, 'town_class'),
            'area'             => $this->stringField($p, 'area_sqm')
                                   ?? $this->stringField($p, 'area'),
            'const_type'       => $this->stringField($p, 'construction_type')
                                   ?? $this->stringField($p, 'const_type'),
            'occupancy_type'   => $this->stringField($p, 'occupancy_type'),
        ], fn ($v) => $v !== null));

        $this->logActivity(
            'Risk Address Created',
            $policy,
            'Risk address ' . ($riskAddress->address_name ?? '#' . $riskAddress->id)
                . ' attached to policy ' . $policy->policyNumber,
            $actorLabel
        );

        return $riskAddress;
    }

    /**
     * Finalise the BizSure policy: compute premium + VAT, flip the
     * Policy + PolicyAction to ISSUED state, generate invoice rows via
     * V2's Helper::generateInvoiceDomComIssued, and record RealPay
     * intent if BizSure chose that payment method.
     *
     * Premium policy: BizSure pre-computes per-cover premiums and the
     * policy total. If payload carries `premium`, that wins (BizSure is
     * the source of truth for its quotes). Otherwise we sum
     * calculated_value across the coverage tree as a fallback so we
     * never issue a P0 policy.
     *
     * Invoice generation is wrapped in try/catch — if Helper fails for
     * any reason (region.vat misconfig, ledger contention, etc.) the
     * policy stays in ISSUED state but invoice_status surfaces 'failed'
     * so ops can re-run via the admin UI.
     *
     * RealPay activation is intentionally a recorded-intent rather than
     * a live API call. The dedicated /api/policies/{id}/realpay endpoint
     * (Aradhana's Phase) is the right place to trigger the contract;
     * BizSure calls that separately after createPolicy returns.
     */
    private function issueAndInvoice(
        Policy $policy,
        int $termId,
        int $actionId,
        array $coverages,
        array $p,
        string $actorLabel
    ): array {
        $declaredPremium = $this->numericFieldOrNull($p, 'premium');
        $coverageSum     = $this->sumCoveragePremiums($coverages);
        // BizSure rates locally (its own rating-engine, NOT Graphite) and sends
        // the authoritative premium, so the declared premium wins; fall back to
        // the coverage-row sum, then 0. V1 also stored the BizSure premium.
        $premium         = $declaredPremium ?? $coverageSum ?? 0.0;
        $vatPct          = $this->numericFieldOrNull($p, 'vat_percent') ?? 14.0;
        $vatAmount       = round($premium * ($vatPct / 100), 2);
        // BizSure sends the payment method as `Payment_method` (capital P) —
        // V1 reads it that way (CommonApis/PolicyController:1500). Read that
        // first, snake_case as fallback, then default to RealPay.
        $paymentMethod   = $this->stringField($p, 'Payment_method')
                            ?? $this->stringField($p, 'payment_method')
                            ?? 'RealPay';

        // Price the policy but DO NOT activate it. V1 leaves a BizSure policy at
        // status=0 (Quote) after createPolicy (CommonApis/PolicyController:393)
        // and only activates it after server-verified DPO payment via
        // action(...,1,'DPO'). The BizSure client triggers that by calling
        // /saveDpoOnesOffTransaction once the customer has paid. Setting status=1
        // here would make every BizSure policy go live BEFORE payment.
        $policy->update([
            'premium'        => $premium,
            'annual_premium' => $premium,
            'vat'            => $vatAmount,
            'vat_percent'    => $vatPct,
            'is_draft'       => 0,
            // status stays 0 (Quote); policyActivatedDate stays null until paid.
        ]);

        PolicyAction::where('id', $actionId)->update([
            'premium'          => $premium,
            'status'           => 'QUOTE', // V1 creates the action as QUOTE (PolicyController:933); action() flips it to ISSUED on payment
            'transaction_date' => Carbon::now()->toDateString(),
        ]);

        $invoiceStatus = $this->generateInvoice($policy, $termId, $actionId);

        $this->logActivity(
            'Policy Quoted',
            $policy,
            "Policy {$policy->policyNumber} quoted via BizSure — premium {$premium}, VAT {$vatAmount} (awaiting payment)",
            $actorLabel
        );

        return [
            'policy_status'    => 'QUOTE',
            'total_premium'    => $premium,
            'vat'              => $vatAmount,
            'invoice_status'   => $invoiceStatus,
            'payment_method'   => $paymentMethod,
            'realpay_required' => strtolower($paymentMethod) === 'realpay',
        ];
    }

    /**
     * Create the CustomerBanking row RealPay debit-order setup reads.
     * Mirrors V1 (CommonApis/PolicyController:1497-1517): billing method,
     * billing cell, bank details for RealPay, and a billingStartDate derived
     * from the frequency (monthly → next billing-day occurrence; else +1 month
     * / +1 year). Without this row a RealPay BizSure policy has no bank details
     * to debit. Non-fatal — a failure here should not roll back the whole
     * policy; ops can repair banking from the admin UI.
     */
    private function createCustomerBanking(Customer $customer, Policy $policy, array $p, string $actorLabel): void
    {
        try {
            $paymentMethod = $this->stringField($p, 'Payment_method')
                ?? $this->stringField($p, 'payment_method')
                ?? 'RealPay';
            $frequency  = $this->intFieldOrNull($p, 'frequency');
            $billingDay = $this->intFieldOrNull($p, 'billing_day');

            $banking = new CustomerBanking();
            $banking->customer_id = $customer->id;
            $banking->user_id     = $customer->id;
            $banking->policy_id   = $policy->id;
            $banking->billing     = $paymentMethod;
            $banking->billingCell = $this->stringField($p, 'phone');

            if (strtolower($paymentMethod) === 'realpay') {
                $banking->bankName      = $this->stringField($p, 'bankName');
                $banking->branchCode    = $this->stringField($p, 'branchCode');
                $banking->accountNumber = $this->stringField($p, 'accountNumber');
                $banking->accountType   = $this->stringField($p, 'bankAccountType');
            }

            // billingStartDate by frequency (V1:1509-1514).
            if ($frequency === 1) {
                $banking->billingStartDate = $billingDay !== null
                    ? $this->billingStartFromDay($billingDay)
                    : Carbon::now()->format('Y-m-d');
            } elseif ($frequency === 2) {
                $banking->billingStartDate = Carbon::now()->addMonths(1)->format('Y-m-d');
            } else {
                $banking->billingStartDate = Carbon::now()->addYear()->format('Y-m-d');
            }
            $banking->billing_day = $billingDay;
            $banking->save();

            $this->logActivity(
                'Customer Banking Created',
                $policy,
                "Banking ({$paymentMethod}) recorded for {$policy->policyNumber}",
                $actorLabel
            );
        } catch (\Throwable $e) {
            Log::error('[BizSureOrchestrator] CustomerBanking create failed', [
                'policy_id' => $policy->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Next occurrence of a billing day-of-month — mirror of V1 setDate()
     * (CommonApis/PolicyController:1855): this month if today is before the
     * day, otherwise next month (and on the day itself → next month).
     */
    private function billingStartFromDay(int $day): string
    {
        $now      = Carbon::now();
        $todayDay = (int) $now->format('d');
        $target   = Carbon::create($now->year, $now->month, $day);
        return $todayDay < $day
            ? $target->format('Y-m-d')
            : $target->addMonth()->format('Y-m-d');
    }

    /**
     * Sum calculated_value across the coverage tree summary. Falls back
     * to 0 when no coverages landed (or when sums are not present —
     * e.g. all skipped due to unknown cover_codes).
     */
    private function sumCoveragePremiums(array $coverages): ?float
    {
        $sum = 0.0;
        $found = false;
        foreach ($coverages as $cov) {
            $covId = $cov['policy_coverage_id'] ?? null;
            if ($covId === null) {
                continue;
            }
            // Read via the PolicyCoverage MODEL, not a raw DB::table() query.
            // The model is connection-pinned (legacy 'mysql' connection); the
            // parent coverage row was written through it, so its `calculated_value`
            // lives on that connection. A raw DB::table('policy_coverages') hits
            // the DEFAULT connection, where this legacy table/column isn't present
            // → SQLSTATE[42S22] "Unknown column 'calculated_value'" and a rolled-back
            // create. Using the model keeps read + write on the same connection.
            $row = PolicyCoverage::find($covId);
            if ($row !== null && $row->calculated_value !== null) {
                $sum += (float) $row->calculated_value;
                $found = true;
            }
        }
        return $found ? round($sum, 2) : null;
    }

    /**
     * Invoice the BizSure quote. V1 uses Helper::addInvoiceToLedger
     * (CommonApis/PolicyController:952) — it has NO product/status filter, so
     * it writes the ledger while the policy is still a Quote (status 0).
     *
     * V2's generateInvoiceDomComIssued was used previously but it requires
     * status IN (1,2) AND product_id IN a fixed allowlist — for an unpaid
     * BizSure quote it silently writes NO ledger yet returns normally, so the
     * policy would carry zero invoice. Use addInvoiceToLedger to match V1.
     *
     * Wrapped in try/catch so a ledger failure doesn't abort the create —
     * ops can re-run invoice generation from the admin UI.
     */
    private function generateInvoice(Policy $policy, int $termId, int $actionId): string
    {
        try {
            $action = PolicyAction::find($actionId);
            $invoiceDate = $action?->effective_from ?? Carbon::now()->toDateString();
            Helper::addInvoiceToLedger($policy->id, $termId, $actionId, $invoiceDate);
            return 'generated';
        } catch (\Throwable $e) {
            Log::error('[BizSureOrchestrator] invoice generation failed', [
                'policy_id'  => $policy->id,
                'term_id'    => $termId,
                'action_id'  => $actionId,
                'error'      => $e->getMessage(),
            ]);
            return 'failed';
        }
    }

    /**
     * Persist Pty Ltd directors + their KYC images.
     *
     * BizSure payload shape:
     *   directors: [
     *     {
     *       first_name: "...", last_name: "...",
     *       id_type: "Omang"|"Passport", id_number: "...",
     *       nationality: "Motswana", address: "...",
     *       id_image: "s3://...../director_0_id.jpg"   // optional
     *     }, ...
     *   ]
     *
     * Sole-proprietor policies send an empty directors[] array (or omit
     * the key entirely) — produces zero rows, no warning needed.
     *
     * Each director gets a stable director_index (0-based) so the KYC
     * image row in policy_kyc_documents links back via doc_type
     * 'director_id' + doc_index matching the director_index.
     */
    private function createDirectors(
        Policy $policy,
        int $termId,
        int $actionId,
        array $p,
        string $actorLabel
    ): array {
        $directors = (array) ($p['directors'] ?? []);
        $created   = 0;
        $kycDocs   = 0;

        foreach ($directors as $i => $d) {
            // BizSure sends director keys in camelCase (V1 reads $d['firstName'],
            // $d['idNumber'], $d['idType'] — legacy CommonApis/PolicyController:
            // 1336-1350). Read camelCase first, snake_case as a fallback. Without
            // this the guard below skips EVERY director (company policies got
            // zero directors persisted).
            $idType   = $this->stringField($d, 'idType')    ?? $this->stringField($d, 'id_type');
            $idNumber = $this->stringField($d, 'idNumber')  ?? $this->stringField($d, 'id_number');
            $firstName= $this->stringField($d, 'firstName') ?? $this->stringField($d, 'first_name');

            // Minimum viable director — first name + id number are required to
            // be useful. Skip + log incomplete rows.
            if ($firstName === null || $idNumber === null) {
                Log::warning('[BizSureOrchestrator] director missing required fields — skipped', [
                    'index'      => $i,
                    'has_first'  => $firstName !== null,
                    'has_id_num' => $idNumber !== null,
                ]);
                continue;
            }

            // id_type must match the enum on policy_directors. Coerce
            // common variants ('omang' / 'OMANG' / 'PASSPORT') to the
            // canonical 'Omang' / 'Passport' values.
            $idTypeNorm = match (strtolower((string) $idType)) {
                'omang'    => 'Omang',
                'passport' => 'Passport',
                default    => 'Omang',
            };

            PolicyDirector::create([
                'policy_id'      => $policy->id,
                'term_id'        => $termId,
                'action_id'      => $actionId,
                'director_index' => $i,
                'first_name'     => $firstName,
                'last_name'      => $this->stringField($d, 'lastName')
                                     ?? $this->stringField($d, 'last_name'),
                'address'        => $this->stringField($d, 'address'),
                'nationality'    => $this->stringField($d, 'nationality'),
                'id_type'        => $idTypeNorm,
                'id_number'      => $idNumber,
            ]);
            $created++;

            // Director KYC image — optional. BizSure uploads the image
            // earlier via /uploadKycImages and sends the resulting S3
            // path here. Linked by director_index so the V2 admin UI can
            // join policy_directors.director_index ↔ policy_kyc_documents.doc_index
            // when rendering the director KYC view.
            $idImage = $this->stringField($d, 'id_image')
                ?? $this->stringField($d, 'kyc_image')
                ?? $this->stringField($d, 'file_path');
            if ($idImage !== null) {
                PolicyKycDocument::create([
                    'policy_id'   => $policy->id,
                    'customer_id' => $policy->customer_id,
                    'doc_type'    => 'director_id',
                    'doc_index'   => $i,
                    'file_path'   => $idImage,
                ]);
                $kycDocs++;
            }
        }

        if ($created > 0) {
            $this->logActivity(
                'Directors Created',
                $policy,
                "{$created} director(s) persisted ({$kycDocs} with KYC image)",
                $actorLabel
            );
        }

        return ['count' => $created, 'kyc_docs' => $kycDocs];
    }

    /**
     * Build the policy coverage tree from BizSure's covers[] array.
     * Each cover becomes one PolicyCoverage parent row, with sub_covers
     * as PolicyCoverageDetail rows, extensions as PolicyExtentionDetails,
     * and (for BUSINESSALLRISKS) portable_items as PolicySpecifiedItem.
     *
     * Unknown cover_codes are skipped + logged rather than aborting the
     * whole policy create — better to land partial coverage and let UW
     * repair than fail an entire BizSure quote.
     *
     * Returns a summary array suitable for the createPolicy response so
     * BizSure can confirm what landed vs what was dropped.
     */
    private function buildCoverageTree(
        Policy $policy,
        RiskAddress $riskAddress,
        int $termId,
        int $actionId,
        array $p,
        string $actorLabel
    ): array {
        $covers = (array) ($p['covers'] ?? []);
        $summary = [];

        foreach ($covers as $i => $cov) {
            $code = $this->stringField($cov, 'cover_code');
            if ($code === null) {
                Log::warning('[BizSureOrchestrator] cover missing cover_code', ['index' => $i]);
                continue;
            }

            $master = CoverageMaster::where('s_CoverageCode', $code)->first();
            if ($master === null) {
                Log::warning('[BizSureOrchestrator] unknown cover_code — skipped', [
                    'cover_code' => $code,
                    'index'      => $i,
                ]);
                $summary[] = ['cover_code' => $code, 'status' => 'unknown_code'];
                continue;
            }

            $parent = $this->createCoverageParent(
                $policy, $riskAddress, $master, $cov, $termId, $actionId
            );

            $subCount      = $this->createCoverageDetails($parent, $master, (array) ($cov['sub_covers'] ?? []), $termId, $actionId, $riskAddress->id);
            $extCount      = $this->createCoverageExtensions($parent, $master, (array) ($cov['extensions'] ?? []));
            $portableCount = strtoupper($master->s_CoverageCode) === 'BUSINESSALLRISKS'
                ? $this->createPortableItems($parent, $master, (array) ($cov['portable_items'] ?? []))
                : 0;

            $summary[] = [
                'cover_code'       => $code,
                'coverage_id'      => $master->id,
                'policy_coverage_id'=> $parent->id,
                'sub_covers'       => $subCount,
                'extensions'       => $extCount,
                'portable_items'   => $portableCount,
            ];
        }

        if (count($summary) > 0) {
            $coverList = implode(', ', array_map(fn ($s) => $s['cover_code'], $summary));
            $this->logActivity(
                'Coverages Created',
                $policy,
                'Coverages attached: ' . $coverList,
                $actorLabel
            );
        }

        return $summary;
    }

    private function createCoverageParent(
        Policy $policy,
        RiskAddress $riskAddress,
        CoverageMaster $master,
        array $cov,
        int $termId,
        int $actionId
    ): PolicyCoverage {
        return PolicyCoverage::create(array_filter([
            'policy_id'        => $policy->id,
            'customer_id'      => $policy->customer_id,
            'risk_address_id'  => $riskAddress->id,
            'coverage_id'      => $master->id,
            'action_id'        => $actionId,
            'term_id'          => $termId,
            'coverage_value'   => $this->numericFieldOrNull($cov, 'sum_insured'),
            'rate'             => $this->numericFieldOrNull($cov, 'rate'),
            'calculated_value' => $this->numericFieldOrNull($cov, 'premium')
                                   ?? $this->numericFieldOrNull($cov, 'calculated_value'),
            'pro_rate_premium' => $this->numericFieldOrNull($cov, 'premium')
                                   ?? $this->numericFieldOrNull($cov, 'calculated_value'),
        ], fn ($v) => $v !== null));
    }

    /**
     * Sub-coverages come from BizSure as free-text labels with
     * sum_insured / rate / premium. V2's policy_coverage_detail uses
     * coverage_id as the FK to the master sub-coverage row — BizSure
     * doesn't send that. We use the parent's coverage_id and surface
     * the sub-cover label via coverage_value_string (per V2 pattern
     * line 1846).
     */
    private function createCoverageDetails(
        PolicyCoverage $parent,
        CoverageMaster $parentMaster,
        array $subCovers,
        int $termId,
        int $actionId,
        int $riskAddressId
    ): int {
        $count = 0;
        foreach ($subCovers as $sub) {
            $sumInsured = $this->numericFieldOrNull($sub, 'sum_insured')
                ?? $this->numericFieldOrNull($sub, 'coverage_value');
            $rate       = $this->numericFieldOrNull($sub, 'rate');
            $premium    = $this->numericFieldOrNull($sub, 'premium')
                ?? $this->numericFieldOrNull($sub, 'calculated_value');

            // Skip silent-zero rows so we don't pollute policy_coverage_detail
            // with phantoms (V2 pattern line 1822–1830 — only persist if any
            // meaningful value present).
            if (($sumInsured ?? 0) <= 0 && ($premium ?? 0) <= 0) {
                continue;
            }

            PolicyCoverageDetail::create(array_filter([
                'policy_coverage_id'    => $parent->id,
                'coverage_id'           => $parentMaster->id,
                'term_id'               => $termId,
                'action_id'             => $actionId,
                'risk_address_id'       => $riskAddressId,
                'coverage_value'        => $sumInsured,
                'rate'                  => $rate,
                'calculated_value'      => $premium,
                'pro_rate_premium'      => $premium,
                'coverage_value_string' => null,
            ], fn ($v) => $v !== null));
            $count++;
        }
        return $count;
    }

    /**
     * Extensions: BizSure may send extension by screen-name string OR by
     * extentions_id directly. Try id first, fall back to name lookup
     * scoped to the parent coverage code. Unknown extensions are skipped
     * + logged rather than blocking the policy.
     */
    private function createCoverageExtensions(
        PolicyCoverage $parent,
        CoverageMaster $parentMaster,
        array $extensions
    ): int {
        $count = 0;
        foreach ($extensions as $ext) {
            $extId = $this->intFieldOrNull($ext, 'extentions_id');

            if ($extId === null) {
                $screenName = $this->stringField($ext, 's_ScreenName')
                    ?? $this->stringField($ext, 'name');
                if ($screenName !== null) {
                    $row = DB::table('extentions')
                        ->where('s_ParentCoverageCode', $parentMaster->s_CoverageCode)
                        ->where('s_ScreenName', $screenName)
                        ->where('s_DISPLAYTOUSER', '1')
                        ->orderBy('id')
                        ->first();
                    if ($row !== null) {
                        $extId = (int) $row->id;
                    }
                }
            }

            if ($extId === null) {
                Log::warning('[BizSureOrchestrator] extension lookup miss — skipped', [
                    'cover_code' => $parentMaster->s_CoverageCode,
                    'extension'  => $ext,
                ]);
                continue;
            }

            PolicyExtentionDetails::create(array_filter([
                'policy_coverage_id'      => $parent->id,
                'extentions_id'           => $extId,
                'extention_coverage_value'=> $this->numericFieldOrNull($ext, 'coverage_value')
                                              ?? $this->numericFieldOrNull($ext, 'extention_coverage_value'),
                'extention_calculated_value' => $this->numericFieldOrNull($ext, 'premium')
                                              ?? $this->numericFieldOrNull($ext, 'extention_calculated_value'),
                'extention_sum_insured'   => $this->numericFieldOrNull($ext, 'sum_insured')
                                              ?? $this->numericFieldOrNull($ext, 'extention_sum_insured'),
                'type'                    => $this->stringField($ext, 'type') ?? 'Extention',
            ], fn ($v) => $v !== null));
            $count++;
        }
        return $count;
    }

    /**
     * BAR portable items: BizSure sends portable_items[] with a code that
     * matches a row seeded by BizSureBarSpecifiedItemsSeeder
     * (CELL_PHONE, LAPTOP, CAMERA, POWER_TOOL, PORTABLE_ELECTRONICS, OTHER).
     * Each becomes a policy_specified_items row under the BAR coverage.
     *
     * Per-item descriptor (make / model / serial / IMEI / year) lands in
     * the description column we added via 2026_05_25_210007.
     */
    private function createPortableItems(
        PolicyCoverage $parent,
        CoverageMaster $parentMaster,
        array $portableItems
    ): int {
        $count = 0;
        foreach ($portableItems as $item) {
            $code = $this->stringField($item, 'code')
                ?? $this->stringField($item, 'specified_code');
            if ($code === null) {
                Log::warning('[BizSureOrchestrator] portable item missing code — skipped', ['item' => $item]);
                continue;
            }

            $catalog = SpecifiedCoveragesItems::where('coverage_id', $parentMaster->id)
                ->where('specified_code', $code)
                ->orderBy('id')
                ->first();
            if ($catalog === null) {
                Log::warning('[BizSureOrchestrator] portable item code not in catalog — skipped', [
                    'cover_code'     => $parentMaster->s_CoverageCode,
                    'specified_code' => $code,
                ]);
                continue;
            }

            $sumInsured = $this->numericFieldOrNull($item, 'sum_insured');
            $rate       = $this->numericFieldOrNull($item, 'rate') ?? (float) $catalog->rate;
            $premium    = $sumInsured !== null ? round($sumInsured * $rate / 100, 2) : null;

            PolicySpecifiedItem::create(array_filter([
                'policy_coverage_id' => $parent->id,
                'specified_coverage_id' => $catalog->id,
                'name'               => $catalog->specified_name,
                'sum_insured'        => $sumInsured,
                'rate'               => $rate,
                'calculated_value'   => $premium,
                'description'        => $this->stringField($item, 'description'),
            ], fn ($v) => $v !== null));
            $count++;
        }
        return $count;
    }

    /**
     * Persist vehicles[] from BizSure payload. BizSure BAR / commercial
     * products may include company-owned vehicles. Each becomes a Vehicle
     * row linked to policy + term + action + risk_address.
     *
     * Returns count of vehicles persisted.
     */
    private function createVehicles(
        Policy $policy,
        RiskAddress $riskAddress,
        int $termId,
        int $actionId,
        array $p,
        string $actorLabel
    ): int {
        $vehicles = (array) ($p['vehicles'] ?? []);
        $count    = 0;

        foreach ($vehicles as $v) {
            $plate = $this->stringField($v, 'vehiclePlate')
                ?? $this->stringField($v, 'vehicle_plate')
                ?? $this->stringField($v, 'registration_number');
            if ($plate === null) {
                Log::warning('[BizSureOrchestrator] vehicle missing plate — skipped', ['vehicle' => $v]);
                continue;
            }

            Vehicle::create(array_filter([
                'policy_id'       => $policy->id,
                'customer_id'     => $policy->customer_id,
                'user_id'         => $policy->customer_id,
                'term_id'         => $termId,
                'action_id'       => $actionId,
                'risk_id'         => $riskAddress->id,
                'vehiclePlate'    => $plate,
                'make'            => $this->stringField($v, 'make'),
                'model'           => $this->stringField($v, 'model'),
                'year'            => $this->stringField($v, 'year'),
                'estimated_value' => $this->numericFieldOrNull($v, 'value')
                                     ?? $this->numericFieldOrNull($v, 'estimated_value'),
                'chassisNo'       => $this->stringField($v, 'chassis_number')
                                     ?? $this->stringField($v, 'chassisNo'),
                'engineNo'        => $this->stringField($v, 'engine_number')
                                     ?? $this->stringField($v, 'engineNo'),
                'seats'           => $this->intFieldOrNull($v, 'seats'),
                'is_imported'     => isset($v['imported']) ? (int) $v['imported'] : null,
            ], fn ($val) => $val !== null));

            $count++;
        }

        if ($count > 0) {
            $this->logActivity('Vehicles Created', $policy, "{$count} vehicle(s) attached to policy", $actorLabel);
        }

        return $count;
    }

    /**
     * Dispatch the Schedule PDF. Called after issueAndInvoice so the PDF
     * reflects the final premium. Non-fatal — ops can regenerate via the
     * admin UI if this fails.
     */
    private function dispatchPolicyDocument(Policy $policy): string
    {
        try {
            $docController = new \AlphaDirect\Http\Controllers\Admin\DocumentController();
            $docController->generatePolicyDocument($policy->id);
            return 'generated';
        } catch (\Throwable $e) {
            Log::warning('[BizSureOrchestrator] PDF dispatch failed', [
                'policy_id' => $policy->id,
                'error'     => $e->getMessage(),
            ]);
            return 'failed';
        }
    }

    /**
     * Fire RealPay payment request inline, mirroring V1's Phase 13
     * (realpayPayment() called inside createPolicy when Payment_method=RealPay).
     *
     * Delegates to RealPayController::realpayPayment() which owns the
     * contract-creation + RealpayPaymentRequest row logic. Non-fatal —
     * ops can re-trigger via the admin UI if this fails.
     */
    private function triggerRealPayInline(Policy $policy, array $p, string $actorLabel): string
    {
        try {
            $realpayCon = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
            $realpayCon->realpayPayment($policy);

            $this->logActivity(
                'RealPay Intent Recorded',
                $policy,
                "RealPay payment intent queued for {$policy->policyNumber}",
                $actorLabel
            );

            return 'queued';
        } catch (\Throwable $e) {
            Log::error('[BizSureOrchestrator] RealPay inline trigger failed', [
                'policy_id' => $policy->id,
                'error'     => $e->getMessage(),
            ]);
            return 'failed';
        }
    }

    /**
     * Resolve BizSure's free-text state + city names into Botswana
     * lookup FK ids. country_id=28 = Botswana (per V2 LookupController
     * line 82).
     *
     * State lookup: exact-name match within Botswana. Miss → null +
     * warning log; risk_state stays empty so UW knows to repair.
     *
     * City lookup: try within the resolved state first (handles cases
     * like "Gaborone" appearing under multiple states); fall back to
     * any matching city if not found in state; null on full miss.
     *
     * Returns [stateId, cityId] — both nullable.
     */
    private function resolveStateCityIds(?string $stateName, ?string $cityName): array
    {
        $stateId = null;
        $cityId  = null;

        if ($stateName !== null) {
            $state = State::where('country_id', 28)
                ->where('name', $stateName)
                ->orderBy('id')
                ->first();

            if ($state !== null) {
                $stateId = $state->id;
            } else {
                Log::warning('[BizSureOrchestrator] state lookup miss', [
                    'state_name' => $stateName,
                    'country_id' => 28,
                ]);
            }
        }

        if ($cityName !== null) {
            $city = null;

            if ($stateId !== null) {
                $city = City::where('state_id', $stateId)
                    ->where('name', $cityName)
                    ->orderBy('id')
                    ->first();
            }

            // Fallback: city name may exist in a different state (BizSure
            // payload typo, or our state lookup missed). Pick the lowest id.
            if ($city === null) {
                $city = City::where('name', $cityName)->orderBy('id')->first();
                if ($city !== null && $stateId !== null) {
                    Log::info('[BizSureOrchestrator] city resolved outside requested state', [
                        'city_name'         => $cityName,
                        'requested_state_id'=> $stateId,
                        'resolved_state_id' => $city->state_id ?? null,
                    ]);
                }
            }

            if ($city !== null) {
                $cityId = $city->id;
            } else {
                Log::warning('[BizSureOrchestrator] city lookup miss', [
                    'city_name'         => $cityName,
                    'requested_state_id'=> $stateId,
                ]);
            }
        }

        return [$stateId, $cityId];
    }

    /**
     * COMD = Commercial entered Digitally (external BizSure client).
     * Format: COMD + 4-digit year + 6-digit zero-padded next policy id.
     * Same nextId pattern as V2's existing COMG/DOMG/MIS generation
     * in PolicyCreateController line 348.
     */
    private function generateCOMDPolicyNumber(): string
    {
        // Serialize number allocation against concurrent BizSure creates.
        // lockForUpdate makes a second concurrent transaction block until this
        // one commits, so two requests can't read the same max(id) and collide
        // on the policyNumber UNIQUE index (idx_policies_policyNumber_unique).
        // Always called from inside createPolicy's DB::transaction.
        $nextId = ((int) Policy::lockForUpdate()->max('id')) + 1;
        return 'COMD' . Carbon::now()->year . str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Resolve the actor label for activity logging. BizSure traffic is
     * un-authed (no Laravel user), so we tag it with a string actor
     * carried in the activity log's `properties` field.
     *
     *   - Client-direct submission       → "Bizsure/Client"
     *   - Agent-assisted via agentCode   → "Bizsure/Agent(<CODE>)"
     *
     * Agent name lookup (V1 pattern "Bizsure/Agent(CODE - NAME)") can
     * be added in a later commit once the agent-by-code lookup pattern
     * is decided. The code alone is sufficient for audit traceability.
     */
    private function resolveActorLabel(array $p): string
    {
        $agentCode = $this->stringField($p, 'agentCode');
        return $agentCode !== null
            ? "Bizsure/Agent({$agentCode})"
            : 'Bizsure/Client';
    }

    /**
     * Wrap Spatie's activity() helper so the BizSure actor label lands
     * in the log's properties even when auth()->user() is null. V2's
     * existing controllers call activity()->causedBy(auth()->user())
     * directly; for un-authed BizSure traffic that's null, which is
     * fine — we surface the actor via properties instead.
     */
    private function logActivity(string $logName, $subject, string $description, string $actorLabel): void
    {
        try {
            activity($logName)
                ->performedOn($subject)
                ->causedBy(auth()->user())
                ->withProperties(['actor' => $actorLabel])
                ->log($description);
        } catch (\Throwable $e) {
            Log::warning('[BizSureOrchestrator] activity log failed', [
                'log_name'    => $logName,
                'description' => $description,
                'actor'       => $actorLabel,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * Parse a payload date field via Carbon. Returns null on missing
     * or unparseable input rather than throwing — letting upstream
     * code fall back to a sensible default.
     */
    private function parseDateOrNull(array $p, string $key): ?Carbon
    {
        $raw = $this->stringField($p, $key);
        if ($raw === null) {
            return null;
        }
        try {
            return Carbon::parse($raw);
        } catch (\Throwable $e) {
            Log::warning('[BizSureOrchestrator] date parse failed', [
                'key'   => $key,
                'raw'   => $raw,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function numericFieldOrNull(array $p, string $key): ?float
    {
        $raw = $this->stringField($p, $key);
        return $raw === null || !is_numeric($raw) ? null : (float) $raw;
    }

    private function intFieldOrNull(array $p, string $key): ?int
    {
        $raw = $this->stringField($p, $key);
        return $raw === null || !is_numeric($raw) ? null : (int) $raw;
    }

    /**
     * BizSure-supplied idempotency key. Accepts the common field aliases the
     * Rails client may send. Null when none present (older clients) — in that
     * case retries cannot be de-duplicated (a warning is logged in createPolicy).
     */
    private function resolveIdempotencyKey(array $p): ?string
    {
        return $this->stringField($p, 'bizsure_ref')
            ?? $this->stringField($p, 'quote_ref')
            ?? $this->stringField($p, 'external_ref')
            ?? $this->stringField($p, 'reference');
    }

    /**
     * Idempotent response for a createPolicy retry that matched an
     * already-created policy. Mirrors the key fields of a fresh create so the
     * BizSure client can treat the duplicate and the original identically.
     */
    private function existingPolicyResponse(Policy $policy, string $ref): array
    {
        return [
            'phase'         => 'idempotent-existing',
            'idempotent'    => true,
            'bizsure_ref'   => $ref,
            'policy_id'     => $policy->id,
            'policy_number' => $policy->policyNumber,
            'customer_id'   => $policy->customer_id,
            'policy_status' => ((int) $policy->status === 1) ? 'ISSUED' : 'QUOTE',
            'total_premium' => $policy->premium,
            'vat'           => $policy->vat,
        ];
    }

    /**
     * True when the QueryException is a duplicate-key violation on the
     * bizsure_ref unique index (MySQL/MariaDB error 1062).
     */
    private function isDuplicateRefViolation(QueryException $e): bool
    {
        return ((int) ($e->errorInfo[1] ?? 0)) === 1062
            && str_contains((string) $e->getMessage(), 'bizsure_ref');
    }

    /**
     * Trim, return null for missing or blank-after-trim. Avoids
     * writing empty strings into Eloquent attributes.
     */
    private function stringField(array $p, string $key): ?string
    {
        $value = $p[$key] ?? null;
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }
}
