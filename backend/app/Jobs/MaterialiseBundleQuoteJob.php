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

/**
 * Materialise a paid bundle quote into per-line Policy rows.
 *
 * Triggered by PaymentWebhookController::dpoPush when the CompanyRef
 * starts with BQ- (bundle prefix). Mirrors MaterialiseMotorQuoteJob in
 * structure: idempotent via materialised_policy_ids, transaction-wrapped,
 * marks quote 'paid' even on partial materialisation so DPO doesn't
 * re-fire. Failures land in the admin queue for manual resolution.
 *
 * For each line: resolve-or-create one shared customer, create one
 * Policy row scoped to the line's product_id + plan_id. Per-product
 * downstream rows (vehicle, device, etc.) stay out of scope here —
 * legacy product flows handle those after the customer logs in to
 * complete preinspection. The bundle's job is to issue the policies
 * and link them to one customer.
 */
class MaterialiseBundleQuoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public string $quoteNumber,
        public ?string $dpoTransToken = null,
    ) {}

    public function handle(): void
    {
        $quote = DB::table('bundle_quotes')->where('quote_number', $this->quoteNumber)->first();
        if (!$quote) {
            Log::warning('MaterialiseBundleQuoteJob: quote_not_found', ['quote' => $this->quoteNumber]);
            return;
        }
        if ($quote->materialised_customer_id && !empty(json_decode((string) $quote->materialised_policy_ids, true))) {
            Log::info('MaterialiseBundleQuoteJob: already materialised', [
                'quote'    => $this->quoteNumber,
                'customer' => $quote->materialised_customer_id,
            ]);
            return;
        }

        try {
            $customerData = json_decode((string) $quote->customer_payload, true) ?: [];
            $lines        = json_decode((string) $quote->lines_payload,    true) ?: [];
            $devices      = property_exists($quote, 'devices_payload')
                ? (json_decode((string) $quote->devices_payload, true) ?: [])
                : [];
            $cellphone    = (string) $quote->cellphone;

            $policyIds = DB::transaction(function () use ($quote, $customerData, $lines, $devices, $cellphone) {
                $customerId = $this->resolveOrCreateCustomer($customerData, $cellphone);
                $this->upsertKyc($customerId, $customerData);
                $policyIds  = [];

                foreach ($lines as $idx => $line) {
                    $policyNumber = $this->mintPolicyNumber((int) $line['product_id']);
                    $policyId = $this->createPolicy($customerId, $policyNumber, $quote, $line);
                    $policyIds[] = ['line' => $idx, 'product_id' => (int) $line['product_id'], 'policy_id' => $policyId, 'policy_number' => $policyNumber];

                    // Product 5 (Mobile/Electronic): seed policy_cellphone +
                    // pending_device_preinspection from the captured devices
                    // so the customer's quote-time IMEI/make/model survives
                    // payment and lands in the preinspection task queue.
                    // Devices belong to the product-5 line(s) in the cart;
                    // if there are multiple product-5 lines we attach all
                    // devices to the first one (current FE produces ≤1).
                    if ((int) $line['product_id'] === 5 && !empty($devices)) {
                        $this->seedDevices($customerId, $policyId, $devices);
                    }
                }

                DB::table('bundle_quotes')->where('id', $quote->id)->update([
                    'status'                    => 'paid',
                    'paid_at'                   => Carbon::now(),
                    'dpo_trans_token'           => $this->dpoTransToken,
                    'materialised_customer_id'  => $customerId,
                    'materialised_policy_ids'   => json_encode($policyIds),
                    'updated_at'                => Carbon::now(),
                ]);

                return $policyIds;
            });

            Log::info('MaterialiseBundleQuoteJob: materialised', [
                'quote'    => $this->quoteNumber,
                'lines'    => count($policyIds),
                'policies' => array_column($policyIds, 'policy_number'),
            ]);
        } catch (\Throwable $e) {
            Log::error('MaterialiseBundleQuoteJob failed', [
                'quote'   => $this->quoteNumber,
                'message' => $e->getMessage(),
                'trace'   => substr($e->getTraceAsString(), 0, 1500),
            ]);
            DB::table('bundle_quotes')->where('id', $quote->id)->update([
                'status'   => 'paid', // money is captured — do not retry on DPO side
                'paid_at'  => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            throw $e;
        }
    }

    /**
     * Resolve-or-create the customer. Mirrors MaterialiseMotorQuoteJob and the
     * real Graphite_live schema: identity (omang/passport) lives on
     * `customer_kyc`, NOT on `customer`. Dedup via customer_kyc → cellphone,
     * then insert a clean `customer` row (only the columns that table has).
     */
    private function resolveOrCreateCustomer(array $cd, string $cellphone): int
    {
        $omang    = $cd['omang']    ?? null;
        $passport = $cd['passport'] ?? null;

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
            'email'      => $cd['email']      ?? null,
            'cellphone'  => $cellphone,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        if (\Schema::hasTable('customer_profile')) {
            $profileCols = \Schema::getColumnListing('customer_profile');
            $profileRow  = array_intersect_key([
                'customer_id' => $customerId,
                // customer_profile.gender is int(11): 1 = Male, 0 = Female. The
                // bundle_quotes payload snapshot carries the raw FE string, so
                // code it here — matching PublicBundleCreateController::upsertProfile
                // (sync path), which already uses genderToInt().
                'gender'      => $this->genderCode($cd['gender'] ?? null),
                'dob'         => !empty($cd['dob']) ? $cd['dob'] : null,
                'omang'       => $omang,
                'passport'    => $passport,
                'address'     => $cd['residentialAddress'] ?? null,
                // KYC personal details — mirrors PublicBundleCreateController::upsertProfile.
                // employerName reuses the e_name column.
                'nationality'      => $cd['nationality'] ?? null,
                'occupation'       => $cd['occupation'] ?? null,
                'occupation_level' => $cd['occupationLevel'] ?? null,
                'country'          => $cd['country'] ?? null,
                'plot_number'      => $cd['plotNumber'] ?? null,
                'e_name'           => $cd['employerName'] ?? null,
                'is_pep'           => filter_var($cd['isPep'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                'pep_type'         => filter_var($cd['isPep'] ?? false, FILTER_VALIDATE_BOOLEAN) ? ($cd['pepType'] ?? null) : null,
                'is_pep_related'   => filter_var($cd['isPepRelated'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                'pep_relationship' => filter_var($cd['isPepRelated'] ?? false, FILTER_VALIDATE_BOOLEAN) ? ($cd['pepRelationship'] ?? null) : null,
                'pep_relationship_specify' => filter_var($cd['isPepRelated'] ?? false, FILTER_VALIDATE_BOOLEAN) ? ($cd['pepRelationshipSpecify'] ?? null) : null,
                // Source of Income/Funds (KYC/AML) — staged in customer_payload
                // by PublicBundleCreateController.
                'source_of_income'         => $cd['sourceOfIncome'] ?? null,
                'source_of_income_details' => !empty($cd['sourceOfIncomeDetails']) ? json_encode($cd['sourceOfIncomeDetails']) : null,
                'created_at'  => Carbon::now(),
                'updated_at'  => Carbon::now(),
            ], array_flip($profileCols));
            DB::table('customer_profile')->insert($profileRow);
        }

        return $customerId;
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

    /**
     * Persist customer identity (omang/passport) to customer_kyc — the table
     * the platform reads for KYC + dedup. Schema-guarded.
     */
    private function upsertKyc(int $customerId, array $cd): void
    {
        if (!\Schema::hasTable('customer_kyc')) return;
        if (empty($cd['omang']) && empty($cd['passport'])) return;

        $existing = DB::table('customer_kyc')->where('customer_id', $customerId)->first(['id']);
        $row = [
            'customer_id'    => $customerId,
            'omangNumber'    => $cd['omang']    ?? null,
            'passportNumber' => $cd['passport'] ?? null,
            'updated_at'     => Carbon::now(),
        ];
        if ($existing) {
            DB::table('customer_kyc')->where('id', $existing->id)->update($row);
        } else {
            $row['created_at'] = Carbon::now();
            DB::table('customer_kyc')->insert($row);
        }
    }

    /**
     * Create one Policy row for one bundle line. We only set the columns
     * that exist on the policies table — schema-guarded so different
     * deploys (V1 vs V2) don't crash on a missing column.
     */
    private function createPolicy(int $customerId, string $policyNumber, $quote, array $line): int
    {
        $row = [
            'customer_id'   => $customerId,
            'policyNumber'  => $policyNumber,
            'product_id'    => (int) $line['product_id'],
            'created_at'    => Carbon::now(),
            'updated_at'    => Carbon::now(),
        ];
        foreach (['plan_id' => (int) $line['plan_id'], 'premium' => (float) $line['premium'], 'status' => 1] as $col => $val) {
            if (\Schema::hasColumn('policies', $col)) $row[$col] = $val;
        }
        if (\Schema::hasColumn('policies', 'bundle_quote_id')) $row['bundle_quote_id'] = $quote->id;
        if (\Schema::hasColumn('policies', 'source'))          $row['source'] = 'bundle_start_fe';

        return (int) DB::table('policies')->insertGetId($row);
    }

    /**
     * Mint a product-native policy number. Mirrors the prefix rules used
     * by PolicyCreateController and the per-product V2 controllers:
     *   7, 16, 17, 20, 22 → COMG{YYYY}{6-digit}
     *   8, 18             → DOMG{YYYY}{6-digit}
     *   everything else   → MIS{YYYY}{6-digit}   (legacy retail: 1, 2, 4, 5, 9)
     *
     * The previous BUN-{date}-{rand}-{line} format collided with the
     * project convention where BUN- denotes multi-product bundles and
     * legacy retail products mint MIS. Customers and downstream tooling
     * expect a product-native prefix on every policy row.
     *
     * Concurrency note: best-effort estimator via max(id). Same caveat
     * as AccidentalDeathController and HospitalCashbackController — two
     * simultaneous materialisations of legacy products in the same year
     * could collide; surface a duplicate-key error at insert time which
     * MaterialiseBundleQuoteJob's outer catch routes to the admin queue.
     */
    private function mintPolicyNumber(int $productId): string
    {
        $year   = Carbon::now()->year;
        $latest = (int) (DB::table('policies')->max('id') ?? 0);
        $padded = str_pad((string) ($latest + 1), 6, '0', STR_PAD_LEFT);

        if (in_array($productId, [7, 16, 17, 20, 22], true)) return "COMG{$year}{$padded}";
        if (in_array($productId, [8, 18], true))             return "DOMG{$year}{$padded}";
        return "MIS{$year}{$padded}";
    }

    /**
     * Seed Mobile/Electronic device rows. Mirrors the legacy
     * BundleProductController::processDevices column mapping so claims and
     * preinspection workflows see the same shape regardless of which
     * creation path was used. Each device gets a policy_cellphone row +
     * a pending_device_preinspection task linking back to it.
     */
    private function seedDevices(int $customerId, int $policyId, array $devices): void
    {
        foreach ($devices as $d) {
            $deviceId = (int) DB::table('policy_cellphone')->insertGetId([
                'policy_id'        => $policyId,
                'customer_id'      => $customerId,
                'device_type'      => (string) ($d['deviceType'] ?? ''),
                'imei'             => (string) ($d['imei'] ?? ''),
                'phone_value'      => (string) ($d['value'] ?? '0'),
                'cell_phone_make'  => (string) ($d['make'] ?? ''),
                'cell_phone_model' => (string) ($d['model'] ?? ''),
                'status'           => 'pending_preinspection',
                'created_at'       => Carbon::now(),
                'updated_at'       => Carbon::now(),
            ]);

            DB::table('pending_device_preinspection')->insert([
                'customer_id' => $customerId,
                'device_id'   => $deviceId,
                'created_at'  => Carbon::now(),
                // Note: column is misspelled "updtaed_at" in the live schema.
                // Mirror the existing typo so the insert succeeds.
                'updtaed_at'  => Carbon::now(),
            ]);
        }
    }
}
