<?php

namespace AlphaDirect\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Materialise a paid motor_quote into a real Customer + Policy + Motor.
 *
 * Triggered from PaymentWebhookController::dpoPush when the IPN's
 * CompanyRef matches an MQ-* quote and the result is success.
 *
 * Idempotent: dispatching twice for the same quote_number is a no-op
 * after the first run sets motor_quotes.materialised_policy_id.
 *
 * Steps (in order, all in one transaction):
 *   1. Resolve customer — match on Omang first (most stable), fall
 *      back to cellphone. Create new if no hit.
 *   2. Upsert CustomerKyc with Omang/passport + license details.
 *   3. Upsert CustomerProfile with the address + DOB/gender.
 *   4. Create Policy with policyNumber auto-generated, status=1
 *      (active), product_id=3 (Motor Comprehensive), premium pinned
 *      from the quote.
 *   5. Create the legacy `motor` row with the vehicle data — pinned
 *      to a placeholder policy_coverage_id of 0 since the full
 *      coverage tree (own_damage / windscreen / liability lines)
 *      requires more product-knowledge than belongs in a webhook
 *      worker. Ops finishes the coverage tree in admin.
 *   6. Re-stamp public_uploaded_files rows for this customer with
 *      the materialised policy_id so the docs link correctly.
 *   7. Update motor_quotes — status='paid', materialised_policy_id,
 *      paid_at, dpo_trans_token.
 *
 * Failure isolation: any step throw rolls back the transaction and
 * marks motor_quotes.status='paid' with materialised_policy_id NULL
 * + a sticky error column so the admin queue can flag for manual
 * resolution. Never lets an exception bubble back to the DPO webhook.
 */
class MaterialiseMotorQuoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public string $quoteNumber,
        public ?string $dpoTransToken = null,
    ) {}

    public function handle(): void
    {
        $quote = DB::table('motor_quotes')->where('quote_number', $this->quoteNumber)->first();
        if (!$quote) {
            Log::warning('MaterialiseMotorQuoteJob: quote not found', ['quote' => $this->quoteNumber]);
            return;
        }

        // Idempotency — already materialised, nothing to do.
        if ($quote->materialised_policy_id) {
            Log::info('MaterialiseMotorQuoteJob: already materialised', [
                'quote'  => $this->quoteNumber,
                'policy' => $quote->materialised_policy_id,
            ]);
            return;
        }

        $customerData = json_decode($quote->customer_payload, true) ?: [];
        $vehicleData  = json_decode($quote->vehicle_payload,  true) ?: [];
        $cellphone    = $this->normalize((string) $quote->cellphone);

        $policyId = null;
        try {
            DB::transaction(function () use ($quote, $customerData, $vehicleData, $cellphone, &$policyId) {
                $customerId = $this->resolveOrCreateCustomer($customerData, $cellphone);
                $this->upsertKyc($customerId, $customerData);
                $this->upsertProfile($customerId, $customerData);

                $policyNumber = 'MOT-' . Carbon::now()->format('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
                $policyId = $this->createPolicy($customerId, $policyNumber, $quote);
                $this->createMotorRow($policyId, $vehicleData);
                $this->linkUploads($customerId, $policyId, $cellphone);
                $this->backLinkConsent($cellphone, $policyId);
                $this->backLinkRealpayContracts($quote, $policyId);

                DB::table('motor_quotes')->where('id', $quote->id)->update([
                    'status'                => 'paid',
                    'materialised_policy_id'=> $policyId,
                    'paid_at'               => Carbon::now(),
                    'dpo_trans_token'       => $this->dpoTransToken,
                    'updated_at'            => Carbon::now(),
                ]);

                Log::info('MaterialiseMotorQuoteJob: materialised', [
                    'quote'    => $this->quoteNumber,
                    'policy'   => $policyNumber,
                    'customer' => $customerId,
                ]);
            });

            // Same auto-send as the legacy DPO new-business flow (see
            // DpoPaymentController::saveOnlinePayment) — generate + email the
            // policy schedule right at issuance. Isolated in its own try/catch
            // so a PDF/email failure never turns into a job retry (the policy
            // is already committed at this point; retrying would just no-op
            // against the materialised_policy_id idempotency check above).
            if ($policyId) {
                try {
                    $documentController = new \AlphaDirect\Http\Controllers\Admin\DocumentController();
                    $documentController->generatePolicyDocument($policyId);
                    $documentController->sendPolicyDocument($policyId, 'System');
                } catch (\Throwable $docEx) {
                    Log::error('MaterialiseMotorQuoteJob: auto-send policy document failed', [
                        'policy'  => $policyId,
                        'message' => $docEx->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('MaterialiseMotorQuoteJob failed', [
                'quote'   => $this->quoteNumber,
                'message' => $e->getMessage(),
                'trace'   => substr($e->getTraceAsString(), 0, 1500),
            ]);
            // Mark the quote paid (the customer's money is genuinely captured)
            // but leave materialised_policy_id null so the admin queue can
            // pick it up for manual resolution.
            DB::table('motor_quotes')->where('id', $quote->id)->update([
                'status'          => 'paid',
                'paid_at'         => Carbon::now(),
                'dpo_trans_token' => $this->dpoTransToken,
                'updated_at'      => Carbon::now(),
            ]);
            throw $e; // re-throw so retries kick in
        }
    }

    private function resolveOrCreateCustomer(array $cd, string $cellphone): int
    {
        // Prefer Omang match (most stable), fall back to passport, then cellphone.
        $omang    = $cd['omang']    ?? null;
        $passport = $cd['passport'] ?? null;
        $email    = $cd['email']    ?? null;

        if ($omang) {
            $kyc = DB::table('customer_kyc')->where('omangNumber', $omang)->first(['customer_id']);
            if ($kyc?->customer_id) return (int) $kyc->customer_id;
        }
        if ($passport) {
            $kyc = DB::table('customer_kyc')->where('passportNumber', $passport)->first(['customer_id']);
            if ($kyc?->customer_id) return (int) $kyc->customer_id;
        }
        $byPhone = DB::table('customer')->where('cellphone', $cellphone)->orderBy('id', 'desc')->first(['id']);
        if ($byPhone) return (int) $byPhone->id;

        return (int) DB::table('customer')->insertGetId([
            'firstName'  => $cd['firstName']  ?? '',
            'middleName' => $cd['middleName'] ?? '',
            'lastName'   => $cd['lastName']   ?? '',
            'email'      => $email,
            'cellphone'  => $cellphone,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private function upsertKyc(int $customerId, array $cd): void
    {
        $existing = DB::table('customer_kyc')->where('customer_id', $customerId)->first(['id']);
        $row = [
            'customer_id'    => $customerId,
            'omangNumber'    => $cd['omang']    ?? null,
            'passportNumber' => $cd['passport'] ?? null,
            'updated_at'     => Carbon::now(),
        ];
        // Document expiry columns drive the re-KYC reminder cron.
        // The legacy customer_kyc has omangExpiry, passportExpiry and
        // license_valid_till (license expiry). Apply each defensively
        // so a deployment with a slimmer schema doesn't break.
        $expiryCols = [
            'omangExpiry'        => $cd['omangExpiry']     ?? null,
            'passportExpiry'     => $cd['passportExpiry']  ?? null,
            'license_number'     => $cd['licenseNumber']   ?? null,
            'license_class'      => $cd['licenseClass']    ?? null,
            'license_valid_from' => $cd['licenseValidFrom']?? null,
            'license_valid_till' => $cd['licenseValidTo']  ?? null,
        ];
        foreach ($expiryCols as $col => $val) {
            if ($val !== null && \Schema::hasColumn('customer_kyc', $col)) {
                $row[$col] = $val;
            }
        }
        if ($existing) {
            DB::table('customer_kyc')->where('id', $existing->id)->update($row);
        } else {
            $row['created_at'] = Carbon::now();
            DB::table('customer_kyc')->insert($row);
        }
    }

    private function upsertProfile(int $customerId, array $cd): void
    {
        if (!\Schema::hasTable('customer_profiles')) return;
        $existing = DB::table('customer_profiles')->where('customer_id', $customerId)->first(['id']);
        $row = ['customer_id' => $customerId, 'updated_at' => Carbon::now()];
        $optional = [
            'dob'                => $cd['dob']                ?? null,
            // customer_profiles.gender is int(11): 1 = Male, 0 = Female. The
            // motor_quotes.customer_payload snapshot carries the raw FE string
            // ('Male'/'Female'), so code it here on the final write.
            'gender'             => $this->genderCode($cd['gender'] ?? null),
            'maritalstatus'      => $cd['maritalStatus']      ?? null,
            'address'            => $cd['residentialAddress'] ?? null,
            // KYC personal details. employerName reuses the e_name column.
            'nationality'        => $cd['nationality']        ?? null,
            'occupation'         => $cd['occupation']         ?? null,
            'occupation_level'   => $cd['occupationLevel']    ?? null,
            'country'            => $cd['country']            ?? null,
            'plot_number'        => $cd['plotNumber']         ?? null,
            'e_name'             => $cd['employerName']       ?? null,
            'is_pep'             => filter_var($cd['isPep'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            'pep_type'           => filter_var($cd['isPep'] ?? false, FILTER_VALIDATE_BOOLEAN) ? ($cd['pepType'] ?? null) : null,
            'is_pep_related'     => filter_var($cd['isPepRelated'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            'pep_relationship'   => filter_var($cd['isPepRelated'] ?? false, FILTER_VALIDATE_BOOLEAN) ? ($cd['pepRelationship'] ?? null) : null,
            'pep_relationship_specify' => filter_var($cd['isPepRelated'] ?? false, FILTER_VALIDATE_BOOLEAN) ? ($cd['pepRelationshipSpecify'] ?? null) : null,
        ];
        foreach ($optional as $col => $val) {
            if ($val !== null && \Schema::hasColumn('customer_profiles', $col)) {
                $row[$col] = $val;
            }
        }
        if ($existing) {
            DB::table('customer_profiles')->where('id', $existing->id)->update($row);
        } else {
            $row['created_at'] = Carbon::now();
            DB::table('customer_profiles')->insert($row);
        }
    }

    /**
     * Encode gender for the int(11) gender column (1 = Male, 0 = Female).
     * Tolerant (accepts 'Male'/'Female', 'M'/'F', already-coded 1/0) and
     * null-safe so a missing gender stays NULL rather than coercing to 0.
     */
    private function genderCode($value): ?int
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) return (int) $value === 1 ? 1 : 0;
        return match (strtoupper(trim((string) $value))) {
            'M', 'MALE'   => 1,
            'F', 'FEMALE' => 0,
            default       => null,
        };
    }

    private function createPolicy(int $customerId, string $policyNumber, $quote): int
    {
        $payload = [
            'customer_id'   => $customerId,
            'product_id'    => 3, // Motor Comprehensive
            'policyNumber'  => $policyNumber,
            'premium'       => $quote->amount_to_pay,
            'annual_premium'=> $quote->premium_annual,
            'first_premium' => $quote->amount_to_pay,
            'premium_freq'  => $this->mapFrequency((string) $quote->premium_frequency),
            'has_vehicle'   => 1,
            'status'        => 1, // active
            'leadSource'    => 'start_fe',
            'policyActivatedDate' => Carbon::now(),
            'billingStartDate'    => Carbon::now()->toDateString(),
            'created_at'    => Carbon::now(),
            'updated_at'    => Carbon::now(),
        ];
        return (int) DB::table('policies')->insertGetId($payload);
    }

    private function createMotorRow(int $policyId, array $vd): void
    {
        if (!\Schema::hasTable('motor')) return;
        // Motor row hangs off a policy_coverage. The coverage tree port
        // is significant work — for now we insert with policy_coverage_id
        // = 0 as a placeholder so ops can finish the coverage in admin.
        $row = [
            'policy_coverage_id' => 0,
            'created_at'         => Carbon::now(),
            'updated_at'         => Carbon::now(),
        ];
        $optional = [
            'make'             => $vd['vehicleMake']    ?? null,
            'model'            => $vd['vehicleModel']   ?? null,
            'registration_no'  => $vd['vehicleReg']     ?? null,
            'engine_number'    => $vd['vehicleEngine']  ?? null,
            'chassis_number'   => $vd['vehicleChassis'] ?? null,
            'estimated_value'  => $vd['sumInsured']     ?? null,
            'coverage_value'   => $vd['sumInsured']     ?? null,
            'coverage_value_main' => $vd['sumInsured']  ?? null,
            'use'              => 'private',
            'use_main'         => 'private',
            'note'             => 'Materialised from motor_quotes; coverage tree pending admin completion. Policy id=' . $policyId,
        ];
        foreach ($optional as $col => $val) {
            if ($val !== null && \Schema::hasColumn('motor', $col)) {
                $row[$col] = $val;
            }
        }
        DB::table('motor')->insert($row);
    }

    /**
     * Stamp the most-recent retail consent for this cellphone with the
     * newly-created policy_id so the audit dashboard can join consent →
     * policy. Best-effort — schema-guarded so deploys with the older
     * customer_privacy_consents shape still pass.
     */
    /**
     * Attach the RealPay debit-order contract created before the policy existed.
     *
     * Api\Public\RealpayController::initiate() has to create the contract while
     * the customer is still a quote, so it writes realpay_client_contracts with
     * client_number = the MQ- quote number and policy_id = NULL. Nothing used to
     * fill that in afterwards, so every instalment webhook for the contract
     * arrived carrying a ClientNumber that matched no policy — and the webhook
     * had no way to attach the payment. Setting policy_id here is what lets
     * RealpayPaymentRecorder::resolvePolicy() find the policy from the contract,
     * which is the one link that survives however the ClientNumber is shaped.
     */
    private function backLinkRealpayContracts(object $quote, int $policyId): void
    {
        try {
            if (!\Schema::hasTable('realpay_client_contracts')) return;

            $linked = DB::table('realpay_client_contracts')
                ->whereNull('policy_id')
                ->where(function ($q) use ($quote) {
                    $q->where('client_number', $quote->quote_number)
                      ->orWhere('contract_number', 'like', $quote->quote_number . '/%');
                })
                ->update(['policy_id' => $policyId, 'updated_at' => Carbon::now()]);

            if ($linked > 0) {
                Log::info('MaterialiseMotorQuoteJob: RealPay contract back-linked', [
                    'quote'  => $quote->quote_number,
                    'policy' => $policyId,
                    'rows'   => $linked,
                ]);
            }
        } catch (\Throwable $e) {
            // Never fail materialisation over bookkeeping — but say so loudly,
            // because an unlinked contract is a future unattachable payment.
            Log::error('MaterialiseMotorQuoteJob: RealPay contract back-link failed', [
                'quote'   => $quote->quote_number,
                'policy'  => $policyId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function backLinkConsent(string $cellphone, int $policyId): void
    {
        if (!\Schema::hasTable('customer_privacy_consents')) return;
        if (!\Schema::hasColumn('customer_privacy_consents', 'policy_id')) return;

        // The consent row stores cellphone in E.164 (+267…). The
        // materialisation job carries the 8-digit local form.
        $e164 = strlen($cellphone) === 8 ? '+267' . $cellphone : '+' . ltrim($cellphone, '+');

        $consent = DB::table('customer_privacy_consents')
            ->where('cellphone', $e164)
            ->whereNull('revoked_at')
            ->where('product_scope', 'retail')
            ->orderByDesc('accepted_at')
            ->first(['id', 'policy_id']);
        if (!$consent || $consent->policy_id) return;

        DB::table('customer_privacy_consents')
            ->where('id', $consent->id)
            ->update(['policy_id' => $policyId, 'updated_at' => Carbon::now()]);
    }

    private function linkUploads(int $customerId, int $policyId, string $cellphone): void
    {
        // Re-stamp uploaded files so the admin can find them under the
        // newly-created policy. Only touch rows that haven't been linked
        // yet — if ops manually moved a doc, we don't override.
        if (!\Schema::hasTable('public_uploaded_files')) return;
        if (\Schema::hasColumn('public_uploaded_files', 'materialised_policy_id')) {
            DB::table('public_uploaded_files')
                ->where('cellphone', $cellphone)
                ->whereNull('materialised_policy_id')
                ->update([
                    'materialised_policy_id' => $policyId,
                    'updated_at' => Carbon::now(),
                ]);
        }
    }

    private function mapFrequency(string $freq): int
    {
        return match ($freq) {
            'monthly'   => 1,
            'quarterly' => 5,
            'annual'    => 3,
            default     => 1,
        };
    }

    private function normalize(string $cellphone): string
    {
        $digits = preg_replace('/\D/', '', $cellphone);
        if (str_starts_with($digits, '267') && strlen($digits) === 11) return substr($digits, 3);
        return $digits;
    }
}
