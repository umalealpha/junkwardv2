<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Traits\ResolvesMatiIdentity;
use AlphaDirect\Http\Traits\ResolvesBillingStartDate;
use AlphaDirect\Services\PublicOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Bundle policy creation — parallel of PublicPolicyCreateController.
 *
 * Single endpoint:
 *   POST /api/v1/public/policies/create-bundle
 *
 * Stages a bundle_quotes row + DPO CompanyRef. After DPO IPN flips
 * status to 'paid', MaterialiseBundleQuoteJob promotes each line into
 * a real Policy + product-specific row, sharing one customer record.
 *
 * Auth: Bearer session (purpose=payment_authorize) so a leaked POST URL
 * can't seed bundles for someone else. Cellphone match enforced.
 *
 * Pricing: trusted from server-side recompute (we do NOT trust the
 * subtotal/total the client sends; recompute from line premiums + the
 * bundle_settings discount rate matching the cart length).
 */
class PublicBundleCreateController extends Controller
{
    use ResolvesMatiIdentity;
    use ResolvesBillingStartDate;

    /**
     * Products that have a dedicated single-product V2 endpoint and whose
     * required downstream rows (beneficiaries, covered lives, etc.) the
     * generic MaterialiseBundleQuoteJob cannot reconstruct from
     * lines_payload alone. Routing them through create-bundle would
     * silently stage a quote that materialises into a malformed policy.
     */
    private const DEDICATED_ENDPOINT_PRODUCTS = [
        1  => '/api/v1/public/policies/create-accidental-death',
        2  => '/api/v1/public/policies/create-third-party-car',
        4  => '/api/v1/public/policies/create-legal-insurance',
        25 => '/api/v1/public/policies/create-goods-in-transit',
    ];

    public function __construct(private PublicOtpService $otp) {}

    public function createBundle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Customer (single set — bundles share a customer record)
            'firstName'         => ['required', 'string', 'max:30', 'regex:/^[A-Za-z. ]+$/'],
            'lastName'          => ['required', 'string', 'max:30', 'regex:/^[A-Za-z. ]+$/'],
            'middleName'        => ['nullable', 'string', 'max:30'],
            'omang'             => ['nullable', 'string', 'regex:/^[0-9]{4}[12][0-9]{4}$/'],
            'passport'          => ['nullable', 'string', 'min:5', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/'],
            'idType'            => ['nullable', 'string', 'in:Omang,Passport'],
            'dob'               => 'nullable|date_format:Y-m-d',
            'gender'            => 'nullable|string|in:Male,Female',
            'maritalStatus'     => 'nullable|string',
            'phone'             => ['required', 'string', 'regex:/^[0-9]{8}$/'],
            'email'             => 'nullable|email|max:160',
            'residentialAddress'=> 'nullable|string|max:100',
            'nationality'       => ['nullable', 'string', 'max:100', new \AlphaDirect\Rules\NotSanctionedCountry()],
            'occupation'        => 'nullable|string|max:100',
            'occupationLevel'   => 'nullable|string|in:Senior,Middle,Junior,Unemployed',
            'employerName'      => 'nullable|string|max:50',
            'country'           => ['nullable', 'string', 'max:100', new \AlphaDirect\Rules\NotSanctionedCountry()],
            'plotNumber'        => 'nullable|string|max:120',
            'isPep'             => 'nullable|boolean',
            'pepType'           => 'nullable|string|max:255',
            'isPepRelated'      => 'nullable|boolean',
            'pepRelationship'   => 'nullable|string|max:50',
            'pepRelationshipSpecify' => 'nullable|string|max:255',
            // Source of Income/Funds (KYC/AML) — staged in customer_payload and
            // written to customer_profile by MaterialiseBundleQuoteJob. Nullable
            // so existing callers/tests are unaffected; the FE marks it required.
            'sourceOfIncome'        => ['nullable', 'string', 'in:unemployed,employment,pensioner_retired,bussiness,inheritance,gifts,investments,dividends,rental,other'],
            'sourceOfIncomeDetails' => ['nullable', 'array'],
            'sourceOfIncomeDetails.*' => ['nullable', 'string', 'max:255'],

            // Cart lines
            'lines'                       => 'required|array|min:1|max:6',
            'lines.*.product_id'          => 'required|integer|exists:products,id',
            'lines.*.plan_id'             => 'required|integer|exists:product_plans,id',
            'lines.*.premium'             => 'required|numeric|min:0',
            'lines.*.product_name'        => 'nullable|string|max:120',
            'lines.*.plan_name'           => 'nullable|string|max:120',

            // Mobile/Electronic device entries — persisted alongside lines so
            // MaterialiseBundleQuoteJob can seed policy_cellphone +
            // pending_device_preinspection on payment. Without this, IMEI /
            // make / model captured at quote time was silently dropped and
            // the customer had to re-enter everything during preinspection.
            'devices'                     => 'nullable|array|max:10',
            'devices.*.deviceType'        => 'required_with:devices|string|in:Cellphone,Tablet,Laptop',
            'devices.*.imei'              => 'required_with:devices|string|max:32',
            'devices.*.make'              => 'required_with:devices|string|max:64',
            'devices.*.model'             => 'required_with:devices|string|max:64',
            'devices.*.value'             => 'required_with:devices|numeric|min:0|max:15000',

            'premiumFrequency'  => 'required|string|in:monthly,quarterly,annual',
        ]);

        if (!$request->filled('omang') && !$request->filled('passport')) {
            return response()->json(['ok' => false, 'error' => 'omang_or_passport_required'], 422);
        }

        // ── Bearer session ────────────────────────────────────────────
        $bearer = $this->extractBearer($request);
        if (!$bearer) return response()->json(['ok' => false, 'error' => 'session_required'], 401);
        $session = $this->otp->validateToken($bearer);
        if (!$session) return response()->json(['ok' => false, 'error' => 'session_invalid_or_expired'], 401);

        $sessionPhone = $this->normalize((string) ($session['cellphone'] ?? ''));
        $formPhone    = $this->normalize($request->input('phone'));
        if ($sessionPhone !== $formPhone) {
            return response()->json(['ok' => false, 'error' => 'session_phone_mismatch'], 403);
        }

        // ── Server-side recompute of subtotal/discount/total ──────────
        // The client sends premium per line; we never trust the total.
        $lines    = $validated['lines'];
        $subtotal = array_sum(array_map(fn ($l) => (float) $l['premium'], $lines));
        $count    = count($lines);
        $discountRatePct = $this->discountRateForCount($count);
        $discountAmount  = round($subtotal * $discountRatePct / 100, 2);
        $total           = round($subtotal - $discountAmount, 2);

        if ($total <= 0) {
            return response()->json(['ok' => false, 'error' => 'invalid_bundle_total'], 422);
        }

        // No-duplicate-product enforcement (defence-in-depth — FE blocks
        // it but a manual POST could try).
        $productIds = array_map(fn ($l) => (int) $l['product_id'], $lines);
        if (count($productIds) !== count(array_unique($productIds))) {
            return response()->json(['ok' => false, 'error' => 'duplicate_products_in_bundle'], 422);
        }

        // Mobile / Electronic Device (product 5) must carry at least one device.
        // The FE already enforces this, but a manual POST could omit it — and
        // without a device the policy_cellphone row (IMEI / make / value) never
        // gets created, leaving the cover with no insured item.
        $devices = $validated['devices'] ?? [];
        if (in_array(5, $productIds, true) && empty($devices)) {
            return response()->json([
                'ok'      => false,
                'error'   => 'device_required',
                'message' => 'At least one device is required for Mobile / Electronic Device cover.',
            ], 422);
        }

        // Dedicated-endpoint gate. Restored after commit 8e1b45ea0
        // ("merge issue short") dropped the foreach during a botched
        // conflict resolution, leaving self::DEDICATED_ENDPOINT_PRODUCTS
        // orphaned and letting products 1/2/4 (ACD / TP Car / Legal)
        // through into the legacy storage path. The generic materialiser
        // cannot reconstruct their downstream rows (beneficiaries,
        // vehicle, legal spouse) from lines_payload, so without this
        // gate /create-bundle silently mints malformed policies on
        // payment confirm.
        foreach ($productIds as $pid) {
            if (isset(self::DEDICATED_ENDPOINT_PRODUCTS[$pid])) {
                Log::warning('public_bundle_create.misrouted_product', [
                    'product_id'   => $pid,
                    'use_endpoint' => self::DEDICATED_ENDPOINT_PRODUCTS[$pid],
                    'cellphone'    => substr(hash('sha256', $sessionPhone), 0, 8),
                ]);
                return response()->json([
                    'ok'           => false,
                    'error'        => 'product_has_dedicated_endpoint',
                    'product_id'   => $pid,
                    'use_endpoint' => self::DEDICATED_ENDPOINT_PRODUCTS[$pid],
                    'message'      => 'This product cannot be staged via /create-bundle — the generic materialiser does not capture its required downstream rows. Use the dedicated endpoint.',
                ], 422);
            }
        }

        // ── Create the bundle using the LEGACY storage model ─────────────
        // Mirrors AlphaDirect\Http\Controllers\Api\BundleProductController:
        //   • ONE main Policy (MIS{year}{id}, is_bundled=1, premium = the
        //     discounted total, status=0/draft) for the first product.
        //   • One policy_bundled child row per additional product.
        //   • Identity on customer_profile; per-product detail rows
        //     (beneficiary / vehicle / legal spouse / device / HCB
        //     co-applicants) hang off the main policy.
        // Status starts deactivated (0) and is flipped active by the DPO
        // webhook on payment confirm — same as the legacy flow.
        $cd = $request->only([
            'firstName','middleName','lastName','omang','passport','idType','dob','gender',
            'maritalStatus','email','residentialAddress','state','city','sourceOfIncome',
            'nationality','occupation','occupationLevel','employerName','country','plotNumber',
            'isPep','pepType','isPepRelated','pepRelationship','pepRelationshipSpecify',
        ]);
        $premiumFreq = $validated['premiumFrequency'];
        // MATI verification id (legacy field `mati-identityId`) → customer.mati_identity.
        $matiIdentity = $this->resolveMatiIdentity($request);

        // ─── Duplicate-submit guard (5.1) ────────────────────────────────
        // The bundle stores the discounted total on the MAIN policy (first
        // line's product). A double-submit resolves to the same customer
        // (resolveOrCreateCustomer dedupes by identity) so we match on the
        // main product_id + total.
        $dup = \AlphaDirect\Policy::recentInstantDuplicateByIdentity(
            $cd['omang'] ?? null,
            $cd['passport'] ?? null,
            $sessionPhone,
            (int) $lines[0]['product_id'],
            $total
        );
        if ($dup) {
            return response()->json([
                'ok'            => false,
                'error'         => 'duplicate_submission',
                'message'       => 'A matching bundle was just created. Please wait a moment before submitting again.',
                'policy_number' => $dup->policyNumber,
            ], 409);
        }

        try {
            $result = DB::transaction(function () use ($request, $cd, $sessionPhone, $lines, $devices, $discountRatePct, $discountAmount, $total, $subtotal, $premiumFreq, $matiIdentity) {
                $customerId = $this->resolveOrCreateCustomer($cd, $sessionPhone, $matiIdentity);
                $this->upsertProfile($customerId, $cd);
                $this->upsertKyc($customerId, $cd);

                // Main MIS policy (first line is the "main" product).
                $main     = $lines[0];
                $mainPlan = DB::table('product_plans')->where('id', (int) $main['plan_id'])->first(['sum_assured']);
                $latest   = DB::table('policies')->orderByDesc('id')->first(['id']);
                $policyNumber = 'MIS' . Carbon::now()->year . str_pad((string) ((int) ($latest->id ?? 0) + 1), 6, '0', STR_PAD_LEFT);

                // One-year ADI term, mirroring the standalone product controllers:
                // start = today, end/expiry = +1yr. The bundle flow collects no
                // start date, so default to now (status flips active on payment).
                $termStart = Carbon::now();
                $termEnd   = $termStart->copy()->addYear();

                $mainRow = [
                    'customer_id'  => $customerId,
                    'product_id'   => (int) $main['product_id'],
                    'plan_id'      => (int) $main['plan_id'],
                    'policyNumber' => $policyNumber,
                    'premium'      => $total,   // discounted bundle total sits on the main policy
                    'status'       => 0,        // Deactivated until paid
                    'leadSource'   => 'start.alphadirect.co.bw',
                    'created_at'   => Carbon::now(),
                    'updated_at'   => Carbon::now(),
                ];
                // Customer-selected billing start date (future, ≤45 days).
                // Additive: the bundle staging historically set no
                // billingStartDate, so only write it when explicitly chosen.
                $billingStartDate = $this->resolveBillingStartDateOrNull($request);

                $optionalCols = [
                    'is_bundled' => 1, 'is_draft' => 1, 'sum_assured' => $mainPlan->sum_assured ?? null,
                    'payment_reference' => $policyNumber, 'premium_freq' => $premiumFreq,
                    'bundled_discount_rate' => $discountRatePct, 'bundled_discount_amount' => $discountAmount,
                    'bundled_discount_type' => 'bundle',
                    'term_start_date' => $termStart->format('Y-m-d'),
                    'term_end_date'   => $termEnd->format('Y-m-d'),
                    'expiry_date'     => $termEnd->format('Y-m-d'),
                ];
                if ($billingStartDate) {
                    $optionalCols['billingStartDate'] = $billingStartDate;
                    $optionalCols['billing_day']      = (int) Carbon::parse($billingStartDate)->format('d');
                }
                foreach ($optionalCols as $c => $v) {
                    if (\Schema::hasColumn('policies', $c)) $mainRow[$c] = $v;
                }
                $mainPolicyId = (int) DB::table('policies')->insertGetId($mainRow);

                // Child products → policy_bundled rows; per-product details for all.
                foreach ($lines as $idx => $line) {
                    $pid = (int) $line['product_id'];
                    if ($idx > 0) {
                        $plan = DB::table('product_plans')->where('id', (int) $line['plan_id'])->first(['sum_assured']);
                        DB::table('policy_bundled')->insert([
                            'policy_id'     => $mainPolicyId,
                            'product_id'    => $pid,
                            'plan_name'     => (string) $line['plan_id'], // legacy stores plan_id in plan_name
                            'premium'       => (float) $line['premium'],
                            'sum_assured'   => $plan->sum_assured ?? null,
                            'frequency_mc'  => '',
                            'subtotal'      => $subtotal,
                            'final_premium' => $total,
                            'created_at'    => Carbon::now(),
                            'updated_at'    => Carbon::now(),
                        ]);
                    }
                    $this->processLineAddons($mainPolicyId, $customerId, $pid, $line);
                }

                // Mobile / Electronic devices arrive as a top-level `devices`
                // array (the instant product-5 flow + bundles send them here,
                // not per-line), so processLineAddons' per-line `device` branch
                // never fires for them. Persist each against the main policy so
                // policy_cellphone (IMEI / make / model / value) is populated on
                // create — previously these were validated but never stored.
                if (in_array(5, array_map(fn ($l) => (int) $l['product_id'], $lines), true)) {
                    foreach ($devices as $d) {
                        $this->insertDevice($mainPolicyId, $customerId, $d);
                    }
                }

                // Legacy creates a KYC stub row.
                if (\Schema::hasTable('customer_kyc') && !DB::table('customer_kyc')->where('customer_id', $customerId)->exists()) {
                    DB::table('customer_kyc')->insert(['customer_id' => $customerId, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
                }

                return ['customer_id' => $customerId, 'policy_id' => $mainPolicyId, 'policy_number' => $policyNumber];
            });
        } catch (\Throwable $e) {
            Log::error('public_bundle_create.failed', ['msg' => $e->getMessage(), 'trace' => substr($e->getTraceAsString(), 0, 800)]);
            return response()->json(['ok' => false, 'error' => 'bundle_create_failed'], 500);
        }

        Log::info('public_bundle_create.policy', [
            'policy_number' => $result['policy_number'],
            'customer'      => $result['customer_id'],
            'lines'         => count($lines),
            'total'         => $total,
        ]);

        // Notify the customer (SMS + email) that the bundle quote was created.
        // Product / plan names are aggregated across the bundle lines; $total
        // is the discounted bundle premium. Non-fatal — the service swallows
        // its own errors.
        $bundleProducts = implode(', ', array_filter(array_map(fn ($l) => $l['product_name'] ?? null, $lines)));
        $bundlePlans    = implode(', ', array_filter(array_map(fn ($l) => $l['plan_name'] ?? null, $lines)));
        (new \AlphaDirect\Services\CustomerNotificationService())
            ->notifyPolicyCreated($result['policy_id'], $result['policy_number'], $total, $bundleProducts ?: 'Bundle', $bundlePlans ?: null);

        return response()->json([
            'ok'             => true,
            'policy_number'  => $result['policy_number'], // MIS… — the DPO CompanyRef
            'amount_to_pay'  => $total,
            'subtotal'       => $subtotal,
            'discount_rate'  => $discountRatePct,
            'discount_amount'=> $discountAmount,
        ], 201);
    }

    // ── Customer / profile / KYC ─────────────────────────────────────────

    /** Dedup by customer_profile (legacy) → customer_kyc (motor) → cellphone, else create. */
    private function resolveOrCreateCustomer(array $cd, string $cellphone, int|string|null $matiIdentity = null): int
    {
        // When a fresh MATI id (or the disabled-bypass marker 0) was captured,
        // persist it on the resolved/created customer. null = "no change".
        $finalize = function (int $id) use ($matiIdentity): int {
            if ($matiIdentity !== null) {
                DB::table('customer')->where('id', $id)->update(['mati_identity' => $matiIdentity]);
            }
            return $id;
        };

        foreach ([['omang', 'omang', 'omangNumber'], ['passport', 'passport', 'passportNumber']] as [$key, $profCol, $kycCol]) {
            $val = $cd[$key] ?? null;
            if (!$val) continue;
            $p = DB::table('customer_profile')->where($profCol, $val)->first(['customer_id']);
            if ($p?->customer_id) return $finalize((int) $p->customer_id);
            $k = DB::table('customer_kyc')->where($kycCol, $val)->first(['customer_id']);
            if ($k?->customer_id) return $finalize((int) $k->customer_id);
        }
        $byPhone = DB::table('customer')->where('cellphone', $cellphone)->orderByDesc('id')->first(['id']);
        if ($byPhone) return $finalize((int) $byPhone->id);

        return (int) DB::table('customer')->insertGetId([
            'firstName'  => $cd['firstName']  ?? '',
            'middleName' => $cd['middleName'] ?? '',
            'lastName'   => $cd['lastName']   ?? '',
            'email'      => $cd['email']      ?? null,
            'cellphone'  => $cellphone,
            'mati_identity' => $matiIdentity,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    /** Identity + address onto customer_profile (legacy source of truth). */
    private function upsertProfile(int $customerId, array $cd): void
    {
        if (!\Schema::hasTable('customer_profile')) return;
        $row = [
            'gender'         => $this->genderToInt($cd['gender'] ?? null),
            'dob'            => $this->normDate($cd['dob'] ?? null),
            'omang'          => $cd['omang']    ?? null,
            'passport'       => $cd['passport'] ?? null,
            'address'        => $cd['residentialAddress'] ?? null,
            'state'          => $cd['state'] ?? null,
            'city'           => $cd['city']  ?? null,
            'maritalstatus'  => $cd['maritalStatus'] ?? null,
            'sourceOfIncome' => isset($cd['sourceOfIncome']) ? json_encode($cd['sourceOfIncome']) : null,
            'updated_at'     => Carbon::now(),
        ];
        // Persist the selected ID type (Omang / Passport) when the column
        // exists — guarded so environments without the migration stay safe.
        if (\Schema::hasColumn('customer_profile', 'id_type')) {
            $row['id_type'] = $cd['idType'] ?? null;
        }
        // KYC personal details — guarded so environments without the migration
        // stay safe. employerName reuses the existing e_name column.
        if (\Schema::hasColumn('customer_profile', 'nationality')) {
            $row['nationality'] = $cd['nationality'] ?? null;
        }
        if (\Schema::hasColumn('customer_profile', 'occupation')) {
            $row['occupation'] = $cd['occupation'] ?? null;
        }
        if (\Schema::hasColumn('customer_profile', 'occupation_level')) {
            $row['occupation_level'] = $cd['occupationLevel'] ?? null;
        }
        if (\Schema::hasColumn('customer_profile', 'country')) {
            $row['country'] = $cd['country'] ?? null;
        }
        if (\Schema::hasColumn('customer_profile', 'plot_number')) {
            $row['plot_number'] = $cd['plotNumber'] ?? null;
        }
        if (!empty($cd['employerName']) && \Schema::hasColumn('customer_profile', 'e_name')) {
            $row['e_name'] = $cd['employerName'];
        }
        // PEP declaration. pep_type only kept when the declaration is ticked.
        $isPep = filter_var($cd['isPep'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (\Schema::hasColumn('customer_profile', 'is_pep')) {
            $row['is_pep'] = $isPep ? 1 : 0;
        }
        if (\Schema::hasColumn('customer_profile', 'pep_type')) {
            $row['pep_type'] = $isPep ? ($cd['pepType'] ?? null) : null;
        }
        $isPepRelated = filter_var($cd['isPepRelated'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (\Schema::hasColumn('customer_profile', 'is_pep_related')) {
            $row['is_pep_related'] = $isPepRelated ? 1 : 0;
        }
        if (\Schema::hasColumn('customer_profile', 'pep_relationship')) {
            $row['pep_relationship'] = $isPepRelated ? ($cd['pepRelationship'] ?? null) : null;
        }
        if (\Schema::hasColumn('customer_profile', 'pep_relationship_specify')) {
            $row['pep_relationship_specify'] = $isPepRelated ? ($cd['pepRelationshipSpecify'] ?? null) : null;
        }
        $existing = DB::table('customer_profile')->where('customer_id', $customerId)->first(['id']);
        if ($existing) {
            DB::table('customer_profile')->where('id', $existing->id)->update($row);
        } else {
            $row['customer_id'] = $customerId;
            $row['created_at']  = Carbon::now();
            DB::table('customer_profile')->insert($row);
        }
    }

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

    // ── Per-product detail rows (driven by the React line add-ons) ───────

    private function processLineAddons(int $policyId, int $customerId, int $productId, array $line): void
    {
        try {
            if ($productId === 1 && !empty($line['beneficiaries'])) {
                foreach ($line['beneficiaries'] as $b) $this->insertBeneficiary($policyId, $b);
            } elseif ($productId === 2 && !empty($line['vehicle'])) {
                $this->insertVehicle($policyId, $customerId, $line['vehicle']);
                DB::table('policies')->where('id', $policyId)->update(['has_vehicle' => 1, 'updated_at' => Carbon::now()]);
            } elseif ($productId === 4 && !empty($line['spouse'])) {
                $this->insertLegalSpouse($policyId, $line['spouse']);
            } elseif ($productId === 5 && !empty($line['device'])) {
                $this->insertDevice($policyId, $customerId, $line['device']);
            } elseif ($productId === 9 && !empty($line['hcb_members'])) {
                foreach ($line['hcb_members'] as $m) $this->insertHcbMember($policyId, $m);
            }
        } catch (\Throwable $e) {
            Log::error('public_bundle_create.addon_failed', ['policy' => $policyId, 'product' => $productId, 'msg' => $e->getMessage()]);
        }
    }

    private function insertBeneficiary(int $policyId, array $b): void
    {
        DB::table('policy_beneficiary')->insert([
            'policy_id'   => $policyId,
            'relation'    => $b['relation'] ?? 'Beneficiary',
            'first_name'  => $b['firstName'] ?? '',
            'middle_name' => $b['middleName'] ?? null,
            'last_name'   => $b['lastName'] ?? '',
            'gender'      => $this->genderToInt($b['gender'] ?? null),
            'dob'         => $this->normDate($b['dob'] ?? null),
            'omang'       => $b['omang'] ?? null,
            'passport'    => $b['passport'] ?? null,
            'payment'     => isset($b['percentage']) ? (int) $b['percentage'] : null,
            'created_at'  => Carbon::now(),
            'updated_at'  => Carbon::now(),
        ]);
    }

    private function insertLegalSpouse(int $policyId, array $s): void
    {
        DB::table('policy_beneficiary')->insert([
            'policy_id'   => $policyId,
            'relation'    => 'Spouse',
            'first_name'  => $s['firstName'] ?? '',
            'middle_name' => $s['middleName'] ?? null,
            'last_name'   => $s['lastName'] ?? '',
            'gender'      => $this->genderToInt($s['gender'] ?? null),
            'dob'         => $this->normDate($s['dob'] ?? null),
            'cellphone'   => $s['phone'] ?? null,
            'email'       => $s['email'] ?? null,
            'omang'       => $s['omang'] ?? null,
            'passport'    => $s['passport'] ?? null,
            'created_at'  => Carbon::now(),
            'updated_at'  => Carbon::now(),
        ]);
    }

    private function insertVehicle(int $policyId, int $customerId, array $v): void
    {
        DB::table('vehicle')->insert([
            'customer_id'  => $customerId,
            'policy_id'    => $policyId,
            'make'         => $v['make'] ?? '',
            'model'        => $v['model'] ?? '',
            'year'         => $v['year'] ?? '',
            'purpose'      => $v['purpose'] ?? '',
            'vehiclePlate' => strtoupper($v['registration'] ?? ''),
            'is_imported'  => (($v['isImported'] ?? 'No') === 'Yes') ? 1 : 0,
            'created_at'   => Carbon::now(),
            'updated_at'   => Carbon::now(),
        ]);
    }

    private function insertDevice(int $policyId, int $customerId, array $d): void
    {
        DB::table('policy_cellphone')->insert([
            'policy_id'        => $policyId,
            'customer_id'      => $customerId,
            'device_type'      => $d['deviceType'] ?? 'Cellphone',
            'imei'             => $d['imei'] ?? '',
            'phone_value'      => $d['value'] ?? 0,
            'cell_phone_make'  => $d['make'] ?? '',
            'cell_phone_model' => $d['model'] ?? '',
            'created_at'       => Carbon::now(),
            'updated_at'       => Carbon::now(),
        ]);
    }

    private function insertHcbMember(int $policyId, array $m): void
    {
        // relation stored as the string 'spouse'/'child' — matches every
        // other writer/reader of this table (HcbCoapplicantService,
        // PublicPolicyController::detail(), the policy-detail card).
        $relation = strtolower((string) ($m['relation'] ?? 'child')) === 'spouse' ? 'spouse' : 'child';
        DB::table('hospital_Cashback_coapplicants')->insert([
            'policy_id'  => $policyId,
            'relation'   => $relation,
            'first_name' => $m['firstName'] ?? '',
            'last_name'  => $m['lastName'] ?? '',
            'gender'     => $this->genderToInt($m['gender'] ?? null),
            'dob'        => $this->normDate($m['dob'] ?? null),
            'omang'      => $m['omang'] ?? null,
            'passport'   => $m['passport'] ?? null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    /** "Male"/"1" → 1, "Female"/"0" → 0, else null. */
    private function genderToInt($g): ?int
    {
        if ($g === null || $g === '') return null;
        if (is_numeric($g)) return (int) $g === 1 ? 1 : 0;
        return strtolower((string) $g) === 'male' ? 1 : 0;
    }

    /** Accepts Y-m-d or d/m/Y, returns Y-m-d or null. */
    private function normDate($d): ?string
    {
        if (empty($d)) return null;
        try {
            return str_contains((string) $d, '/')
                ? Carbon::createFromFormat('d/m/Y', $d)->format('Y-m-d')
                : Carbon::parse($d)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Bundle discount rate — recomputed server-side so a tampered client
     * can't claim a higher tier than the cart length entitles to.
     *
     * Reads the same source as LookupController::publicBundleSettings —
     * the `Config` row keyed `bundled_products_settings` — NOT a
     * `bundle_settings` table (which does not exist in Graphite_live).
     * Falls back to the BW defaults (5/10/15/20 %) when the row is absent.
     */
    private function discountRateForCount(int $count): float
    {
        if ($count < 2) return 0.0;

        $defaults = ['two_products' => 5, 'three_products' => 10, 'four_products' => 15, 'more_than_four' => 20];
        $bundle = $defaults;
        try {
            $row = \AlphaDirect\Config::where('key', 'bundled_products_settings')->first();
            $cfg = $row ? (json_decode($row->value, true)[0] ?? null) : null;
            if (is_array($cfg)) $bundle = array_merge($defaults, $cfg);
        } catch (\Throwable $e) {
            // Config table/row unavailable — fall back to defaults.
        }

        return (float) match ($count) {
            2       => $bundle['two_products']   ?? $defaults['two_products'],
            3       => $bundle['three_products'] ?? $defaults['three_products'],
            4       => $bundle['four_products']  ?? $defaults['four_products'],
            default => $bundle['more_than_four'] ?? $defaults['more_than_four'],
        };
    }

    private function extractBearer(Request $request): ?string
    {
        $auth = $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) return trim($m[1]);
        return null;
    }

    private function normalize(string $cellphone): string
    {
        $digits = preg_replace('/\D/', '', $cellphone);
        if (str_starts_with($digits, '267') && strlen($digits) === 11) return substr($digits, 3);
        return $digits;
    }
}
