<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Customer;
use AlphaDirect\Helpers\PiiMask;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\Request;
use AlphaDirect\Http\Resources\V1\CustomerResource;
use AlphaDirect\Http\Resources\V1\PolicyCollection;
use AlphaDirect\Policy;
use AlphaDirect\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    /**
     * Paginated customer list with search.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'   => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        // UAT 2026-05-26 (Arjun H5 / Prathap BUG-023): the customer list
        // was rendering "—" for ID Number / KYC Status / AML Status /
        // Policies / Premium across all 146,378 rows because the API
        // didn't return those fields. Extending the query + projection
        // to populate them. ID Number falls back from omang → passport
        // so individuals with a passport (not Botswana citizens) still
        // surface a value.
        $query = DB::table('customer as c')
            ->leftJoin('customer_profile as cp', 'cp.customer_id', '=', 'c.id')
            ->leftJoin('customer_kyc as ck', 'ck.customer_id', '=', 'c.id')
            ->select([
                'c.id', 'c.firstName', 'c.lastName', 'c.cellphone', 'c.email',
                'c.is_blocked', 'c.created_at',
                'cp.omang', 'cp.passport', 'cp.entity_type', 'cp.gender',
                'ck.status as kyc_status_raw',
            ])
            // Aggregates: active policy count + total premium per customer.
            // selectSub keeps it a single round-trip; one subquery scan
            // per page rather than N round-trips.
            ->selectSub(
                DB::table('policies')
                    ->whereColumn('policies.customer_id', 'c.id')
                    ->where('policies.status', 1)
                    ->selectRaw('COUNT(*)'),
                'active_policies'
            )
            ->selectSub(
                DB::table('policies')
                    ->whereColumn('policies.customer_id', 'c.id')
                    ->where('policies.status', 1)
                    ->selectRaw('COALESCE(SUM(premium), 0)'),
                'total_premium'
            )
            // AML rollup: same single-table EXISTS check the detail
            // endpoint uses (see show()). A correlated subquery returning
            // 0/1 is cheap with an index on customer_id and avoids the
            // multi-table join that previously made surfacing this on the
            // list expensive.
            ->selectSub(
                DB::table('customer_aml_verification_log')
                    ->whereColumn('customer_aml_verification_log.customer_id', 'c.id')
                    ->selectRaw('1')
                    ->limit(1),
                'aml_checked'
            )
            ->when($validated['search'] ?? null, function ($q, $search) {
                $search = trim(preg_replace('/\s+/', ' ', $search));
                $like = "%{$search}%";
                $words = count(explode(' ', $search)) >= 2
                    ? array_values(array_filter(explode(' ', $search)))
                    : [];
                $q->where(function ($q) use ($like, $words) {
                    $q->where('c.firstName', 'like', $like)
                      ->orWhere('c.lastName', 'like', $like)
                      ->orWhereRaw("CONCAT_WS(' ', TRIM(c.firstName), TRIM(c.lastName)) LIKE ?", [$like])
                      ->orWhereRaw("CONCAT_WS(' ', TRIM(c.firstName), TRIM(c.middleName), TRIM(c.lastName)) LIKE ?", [$like])
                      ->orWhere('c.cellphone', 'like', $like)
                      ->orWhere('c.email', 'like', $like)
                      ->orWhere('cp.omang', 'like', $like);
                    if (count($words) >= 2) {
                        $q->orWhere(fn($i) =>
                            $i->where('c.firstName', 'like', "%{$words[0]}%")
                              ->where('c.lastName', 'like', "%{$words[1]}%")
                        )->orWhere(fn($i) =>
                            $i->where('c.firstName', 'like', "%{$words[1]}%")
                              ->where('c.lastName', 'like', "%{$words[0]}%")
                        );
                    }
                });
            })
            ->orderBy('c.id', 'desc');

        $results = $query->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => collect($results->items())->map(fn($r) => [
                'id' => $r->id,
                'name' => PiiMask::ifName(trim(($r->firstName ?? '') . ' ' . ($r->lastName ?? ''))),
                'firstName' => PiiMask::ifName($r->firstName),
                'lastName' => PiiMask::ifName($r->lastName),
                'cellphone' => PiiMask::ifPhone($r->cellphone),
                'email' => PiiMask::ifEmail($r->email),
                'omang' => PiiMask::ifId($r->omang),
                'passport' => PiiMask::ifId($r->passport),
                // ID Number for the list view: prefer omang (BW citizen),
                // fall back to passport for non-citizens.
                'idNumber' => PiiMask::ifId($r->omang ?: $r->passport),
                'entityType' => $r->entity_type,
                'gender' => $r->gender,
                'kycStatus' => $r->kyc_status_raw,
                // AML status: same semantic the detail endpoint uses —
                // presence of a row in customer_aml_verification_log means
                // the customer has been screened ("cleared"); absence
                // means "not_checked".
                'amlStatus' => $r->aml_checked ? 'cleared' : 'not_checked',
                'activePolicies' => (int) ($r->active_policies ?? 0),
                'totalPremium' => (float) ($r->total_premium ?? 0),
                'isBlocked' => $r->is_blocked == '1',
                'createdAt' => $r->created_at,
            ]),
            'meta' => [
                'total' => $results->total(),
                'per_page' => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'from' => $results->firstItem(),
                'to' => $results->lastItem(),
            ],
        ]);
    }

    /**
     * POST /customers — quick-add a new customer.
     *
     * Deliberately minimal: name + contact only. Per the data-protection
     * policy we do NOT capture Omang/ID, passport, residential address or
     * bank details in this quick-create path — those are entered later on the
     * (access-gated) customer detail / KYC screens via update(). A bare
     * `customer` row is enough to appear in the list and attach a policy to.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'firstName'  => 'required|string|max:100',
            'middleName' => 'nullable|string|max:100',
            'lastName'   => 'required|string|max:100',
            'email'      => 'nullable|email|max:255',
            'cellphone'  => 'nullable|string|max:20',
        ]);

        // Soft duplicate guard — block an exact email / phone collision so the
        // list doesn't accumulate obvious duplicates. Not a hard uniqueness
        // constraint (legacy data has dupes); just catches the common re-add.
        if (!empty($validated['email'])
            && Customer::where('email', $validated['email'])->exists()) {
            return response()->json(['message' => 'A customer with this email already exists.'], 422);
        }
        if (!empty($validated['cellphone'])
            && Customer::where('cellphone', $validated['cellphone'])->exists()) {
            return response()->json(['message' => 'A customer with this phone number already exists.'], 422);
        }

        $customer = new Customer();
        $customer->firstName = $validated['firstName'];
        $customer->lastName  = $validated['lastName'];
        if (!empty($validated['middleName'])) $customer->middleName = $validated['middleName'];
        if (!empty($validated['email']))      $customer->email      = $validated['email'];
        if (!empty($validated['cellphone']))  $customer->cellphone  = $validated['cellphone'];
        $customer->is_blocked = 0;
        $customer->save();

        return response()->json([
            'data'    => ['id' => $customer->id],
            'message' => 'Customer added successfully.',
        ], 201);
    }

    /**
     * GET /customers/lookup?omang=...&passport=...&cellphone=...
     *
     * Returns the customer's profile (if any) plus the latest KYC + a
     * recent-policies summary in one shot. Used by the customer-facing
     * onboarding flow on alphaFEV2: after OCR extracts an Omang from the
     * uploaded ID, the form asks V2 whether we already know this person —
     * if so the form pre-fills name/dob/email/phone/address and the
     * customer just confirms instead of typing everything again.
     *
     * Returns 200 with `exists: false` when nothing matches (NOT 404), so
     * the caller can branch on a single field rather than try/catch HTTP
     * status codes.
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'omang'     => 'nullable|string|max:30',
            'passport'  => 'nullable|string|max:30',
            'cellphone' => 'nullable|string|max:20',
            'email'     => 'nullable|email|max:120',
        ]);

        if (empty(array_filter($validated, fn($v) => !empty($v)))) {
            return response()->json(['error' => 'Provide at least one of omang / passport / cellphone / email.'], 422);
        }

        $q = DB::table('customer');
        if (!empty($validated['omang'])) {
            $q->where(function ($qq) use ($validated) {
                $qq->where('omang', $validated['omang']);
            });
        } elseif (!empty($validated['passport'])) {
            $q->where('passport', $validated['passport']);
        } elseif (!empty($validated['cellphone'])) {
            $q->where('cellphone', $validated['cellphone']);
        } elseif (!empty($validated['email'])) {
            $q->where('email', $validated['email']);
        }

        $customer = $q->orderByDesc('id')->first();
        if (!$customer) {
            return response()->json(['exists' => false]);
        }

        // KYC + customer profile (legacy `customer_profiles` table).
        $kyc = DB::table('customer_kyc')->where('customer_id', $customer->id)->first();
        $profile = \Schema::hasTable('customer_profiles')
            ? DB::table('customer_profiles')->where('customer_id', $customer->id)->first()
            : null;

        // Recent policies — most-recent 5, just enough for the FE to show
        // a "we already insure you on X / Y / Z" hint above the form.
        $policies = DB::table('policies')
            ->where('customer_id', $customer->id)
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'policyNumber', 'status', 'product_id', 'premium', 'created_at'])
            ->map(fn($p) => [
                'id'           => $p->id,
                'policy_number'=> $p->policyNumber,
                'status'       => (int) $p->status,
                'product_id'   => $p->product_id,
                'premium'      => $p->premium,
                'created_at'   => $p->created_at,
            ]);

        return response()->json([
            'exists' => true,
            'customer' => [
                'id'         => $customer->id,
                'firstName'  => $customer->firstName ?? null,
                'middleName' => $customer->middleName ?? null,
                'lastName'   => $customer->lastName ?? null,
                'email'      => $customer->email ?? null,
                'cellphone'  => $customer->cellphone ?? null,
                'gender'     => $customer->gender ?? null,
                'dob'        => $customer->dob ?? ($profile->dob ?? null),
                'omang'      => $customer->omang ?? ($profile->omang ?? null),
                'passport'   => $customer->passport ?? ($profile->passport ?? null),
            ],
            'profile' => $profile ? [
                'address'        => $profile->address ?? null,
                'state'          => $profile->state ?? null,
                'city'           => $profile->city ?? null,
                'maritalStatus'  => $profile->maritalstatus ?? null,
                'occupation'     => $profile->occupation ?? null,
                'employerName'   => $profile->employerName ?? null,
                'sourceOfIncome' => $profile->sourceOfIncome ?? null,
            ] : null,
            'kyc' => $kyc ? [
                'omang_uploaded'        => !empty($kyc->omang),
                'passport_uploaded'     => !empty($kyc->passport),
                'driving_license_uploaded' => !empty($kyc->driving_license),
                'proof_residence_uploaded' => !empty($kyc->proof_residence),
                'compliance'            => $kyc->compliance ?? null,
            ] : null,
            'recent_policies' => $policies,
        ]);
    }

    /**
     * Customer 360 — comprehensive single-customer view.
     *
     * Returns customer profile, KYC/AML status, policies, payments,
     * claims, and a computed risk score in a single response.
     */
    public function show(int $id): JsonResponse
    {
        // ── Customer core ────────────────────────────────────────────
        // UAT 2026-05-26 (extension of Arjun H5 / Prathap BUG-023): the
        // detail page was reading `idNumber` directly off the `customer`
        // table — that column doesn't exist there. ID Number lives on
        // `customer_profile` as omang / passport. Joined here so the
        // detail page surfaces the same value the list now shows.
        $customer = DB::table('customer as c')
            ->leftJoin('customer_profile as cp', 'cp.customer_id', '=', 'c.id')
            ->where('c.id', $id)
            ->select(
                'c.id', 'c.firstName', 'c.lastName', 'c.cellphone', 'c.email',
                'c.is_blocked', 'c.created_at',
                'c.customer_category', 'c.category_reason', 'c.block_reason',
                'cp.omang', 'cp.passport', 'cp.entity_type', 'cp.gender'
            )
            ->first();

        if (!$customer) {
            return response()->json(['message' => 'Customer not found.'], 404);
        }

        // ── KYC ──────────────────────────────────────────────────────
        $kyc = DB::table('customer_kyc')->where('customer_id', $id)->first();

        $kycDocuments = [
            'omang'           => !empty($kyc->omang ?? null),
            'passport'        => !empty($kyc->passport ?? null),
            'driving_license' => !empty($kyc->driving_license ?? null),
            'proof_residence' => !empty($kyc->proof_residence ?? null),
        ];

        $docsProvided = array_filter($kycDocuments);
        if (count($docsProvided) >= 2) {
            $kycStatus = 'compliant';
        } elseif (count($docsProvided) >= 1) {
            $kycStatus = 'pending';
        } else {
            $kycStatus = 'non_compliant';
        }

        // ── AML ──────────────────────────────────────────────────────
        $amlExists = DB::table('customer_aml_verification_log')
            ->where('customer_id', $id)
            ->exists();

        $amlStatus = $amlExists ? 'cleared' : 'not_checked';

        // ── Policies ─────────────────────────────────────────────────
        $policies = DB::table('policies')
            ->leftJoin('products', 'policies.product_id', '=', 'products.id')
            ->where('policies.customer_id', $id)
            ->select(
                'policies.id',
                'policies.policyNumber as policy_number',
                'products.name as product_name',
                'policies.status',
                'policies.premium',
                'policies.billingStartDate as start_date',
                'policies.created_at'
            )
            ->orderByDesc('policies.id')
            ->get();

        $statusLabels = [
            0 => 'Pending',
            1 => 'Active',
            2 => 'Cancelled',
            3 => 'Expired',
        ];

        $activePremiumTotal = 0;
        $activeCount        = 0;
        $cancelledCount     = 0;

        $policiesFormatted = $policies->map(function ($p) use ($statusLabels, &$activePremiumTotal, &$activeCount, &$cancelledCount) {
            $statusInt = (int) $p->status;
            if ($statusInt === 1) {
                $activeCount++;
                $activePremiumTotal += (float) $p->premium;
            } elseif ($statusInt === 2) {
                $cancelledCount++;
            }

            return [
                'id'            => $p->id,
                'policy_number' => $p->policy_number,
                'product_name'  => $p->product_name,
                'status_label'  => $statusLabels[$statusInt] ?? 'Unknown',
                'premium'       => (float) $p->premium,
                'start_date'    => $p->start_date,
            ];
        });

        $policySummary = [
            'total'         => $policies->count(),
            'active'        => $activeCount,
            'cancelled'     => $cancelledCount,
            'total_premium' => 'P ' . number_format($activePremiumTotal, 2),
        ];

        // ── Payments (last 20) ───────────────────────────────────────
        $policyIds = $policies->pluck('id')->toArray();

        $payments = collect();
        if (!empty($policyIds)) {
            $payments = DB::table('payment_transactions')
                ->whereIn('policy_id', $policyIds)
                ->where(function ($q) {
                    $q->where('is_reverse', 0)->orWhereNull('is_reverse');
                })
                ->select(
                    'policyNumber as policy_number',
                    'amount',
                    'status',
                    'new_payment_date as date',
                    'paymentMethod as method'
                )
                ->orderByDesc('new_payment_date')
                ->limit(20)
                ->get();
        }

        $totalPaid      = 0;
        $lastPaymentDate = null;
        $paymentMethods  = [];

        // Aggregate across ALL successful payments (not just the 20 shown)
        if (!empty($policyIds)) {
            $paymentAgg = DB::table('payment_transactions')
                ->whereIn('policy_id', $policyIds)
                ->where('status', 'Successful')
                ->where(function ($q) {
                    $q->where('is_reverse', 0)->orWhereNull('is_reverse');
                })
                ->selectRaw('SUM(amount) as total_paid, MAX(new_payment_date) as last_date')
                ->first();

            $totalPaid       = (float) ($paymentAgg->total_paid ?? 0);
            $lastPaymentDate = $paymentAgg->last_date;

            $paymentMethods = DB::table('payment_transactions')
                ->whereIn('policy_id', $policyIds)
                ->where('status', 'Successful')
                ->where(function ($q) {
                    $q->where('is_reverse', 0)->orWhereNull('is_reverse');
                })
                ->whereNotNull('paymentMethod')
                ->distinct()
                ->pluck('paymentMethod')
                ->toArray();
        }

        $paymentsFormatted = $payments->map(fn ($p) => [
            'policy_number' => $p->policy_number,
            'amount'        => (float) $p->amount,
            'status'        => $p->status,
            'date'          => $p->date,
            'method'        => $p->method,
        ]);

        $paymentSummary = [
            'total_paid'        => 'P ' . number_format($totalPaid, 2),
            'last_payment_date' => $lastPaymentDate,
            'payment_methods'   => array_values($paymentMethods),
        ];

        // ── Claims ───────────────────────────────────────────────────
        $claims = DB::table('claims')
            ->where('customer_id', $id)
            ->select('claim_number', 'claim_type', 'status', 'created_at')
            ->orderByDesc('created_at')
            ->get();

        $openCount     = 0;
        $approvedCount = 0;
        $rejectedCount = 0;

        $claimsFormatted = $claims->map(function ($c) use (&$openCount, &$approvedCount, &$rejectedCount) {
            $status = strtolower($c->status ?? '');
            if (in_array($status, ['open', 'pending', 'submitted', 'in_progress'])) {
                $openCount++;
            } elseif (in_array($status, ['approved', 'settled', 'paid'])) {
                $approvedCount++;
            } elseif (in_array($status, ['rejected', 'declined', 'denied'])) {
                $rejectedCount++;
            }

            return [
                'claim_number' => $c->claim_number,
                'claim_type'   => $c->claim_type,
                'status'       => $c->status,
                'created_at'   => $c->created_at,
            ];
        });

        $claimSummary = [
            'total'    => $claims->count(),
            'open'     => $openCount,
            'approved' => $approvedCount,
            'rejected' => $rejectedCount,
        ];

        // ── Risk score ───────────────────────────────────────────────
        $riskScore = $this->computeRiskScore(
            $kycStatus,
            $amlStatus,
            $claims->count(),
            $activeCount,
            $totalPaid,
            $activePremiumTotal,
            $lastPaymentDate
        );

        // ── Response ─────────────────────────────────────────────────
        return response()->json([
            'data' => [
                'customer' => [
                    'id'         => $customer->id,
                    'name'       => trim(($customer->firstName ?? '') . ' ' . ($customer->lastName ?? '')),
                    // Prefer omang (BW citizen), fall back to passport for non-citizens.
                    // Mirrors the list endpoint's projection so the same "ID Number"
                    // value lights up wherever the customer appears.
                    'id_number'  => $customer->omang ?: $customer->passport,
                    'phone'      => $customer->cellphone ?? null,
                    'email'      => $customer->email ?? null,
                    'created_at' => $customer->created_at ?? null,
                ],
                'kyc_status'      => $kycStatus,
                'kyc_documents'   => $kycDocuments,
                'aml_status'      => $amlStatus,
                'policies'        => $policiesFormatted,
                'policy_summary'  => $policySummary,
                'recent_payments' => $paymentsFormatted,
                'payment_summary' => $paymentSummary,
                'claims'          => $claimsFormatted,
                'claim_summary'   => $claimSummary,
                'risk_score'      => $riskScore,
                // Admin-set risk category (graphiteBWV8 customer_category), distinct
                // from the computed risk_score above — this one is a manual
                // classification an admin can view/override, with a reason
                // (historically populated by Claims via customer_category /
                // category_reason on the shared `customer` table).
                'risk_category'        => self::RISK_CATEGORY_LABELS[(int) ($customer->customer_category ?? -1)] ?? null,
                'risk_category_reason' => $this->decodeCategoryReason($customer->category_reason ?? null),
                'is_blocked'           => (int) ($customer->is_blocked ?? 0) === 1,
                'block_reason'         => $customer->block_reason ?? null,
            ],
        ]);
    }

    /** customer_category int -> label (graphiteBWV8 parity: 0=Low, 1=Moderate, 2=High). */
    private const RISK_CATEGORY_LABELS = [0 => 'low', 1 => 'medium', 2 => 'high'];

    /**
     * category_reason is stored graphiteBWV8-style as a PHP serialize()d array
     * of reason strings (e.g. from Claims-driven auto-classification). Decode
     * defensively — native unserialize with allowed_classes disabled (no need
     * for Opis\Closure, it's just strings) — and fall back to the raw value
     * for any row that was written as plain text.
     */
    private function decodeCategoryReason(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $decoded = @unserialize($raw, ['allowed_classes' => false]);
        if (is_array($decoded)) {
            return implode(' ', array_filter($decoded, 'is_string'));
        }
        return $raw;
    }

    /** Inverse of decodeCategoryReason() — wraps the reason in the same serialized-array shape. */
    private function encodeCategoryReason(?string $reason): ?string
    {
        return $reason === null || $reason === '' ? null : serialize([$reason]);
    }

    /**
     * Compute a simple risk score based on payment gaps, claims frequency,
     * and KYC/AML status.
     */
    private function computeRiskScore(
        string $kycStatus,
        string $amlStatus,
        int    $totalClaims,
        int    $activePolicies,
        float  $totalPaid,
        float  $activePremiumTotal,
        ?string $lastPaymentDate
    ): string {
        $score = 0; // 0 = best, higher = worse

        // KYC factor
        if ($kycStatus === 'non_compliant') {
            $score += 3;
        } elseif ($kycStatus === 'pending') {
            $score += 1;
        }

        // AML factor
        if ($amlStatus === 'not_checked') {
            $score += 2;
        }

        // Claims frequency relative to active policies
        if ($activePolicies > 0 && $totalClaims > 0) {
            $ratio = $totalClaims / $activePolicies;
            if ($ratio >= 3) {
                $score += 3;
            } elseif ($ratio >= 1.5) {
                $score += 2;
            } elseif ($ratio >= 0.5) {
                $score += 1;
            }
        }

        // Payment gap — if last payment was over 60 days ago
        if ($lastPaymentDate) {
            $daysSinceLast = (int) now()->diffInDays($lastPaymentDate);
            if ($daysSinceLast > 90) {
                $score += 3;
            } elseif ($daysSinceLast > 60) {
                $score += 2;
            } elseif ($daysSinceLast > 30) {
                $score += 1;
            }
        } elseif ($activePolicies > 0) {
            // Active policies but no payments at all
            $score += 3;
        }

        if ($score >= 6) {
            return 'high';
        } elseif ($score >= 3) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Update customer + profile details. Used by both the Customer 360 page
     * and the Policy Details page's Customer tab (KYC staff / customer-edit
     * permission holders correcting an existing customer's details — see
     * `permission:customer-edit|customer-kyc-edit` on the route).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'firstName'      => 'nullable|string|max:100',
            'middleName'     => 'nullable|string|max:100',
            'lastName'        => 'nullable|string|max:100',
            'email'           => 'nullable|email|max:255',
            'cellphone'       => 'nullable|string|max:20',
            // Profile fields
            'gender'          => 'nullable|string|in:Male,Female',
            'dob'             => 'nullable|date',
            'maritalstatus'   => 'nullable|string|in:Single,Married,Divorced,Widowed',
            'omang'           => 'nullable|string|max:25',
            'passport'        => 'nullable|string|max:25',
            'address'         => 'nullable|string|max:500',
            'state'           => 'nullable|integer',
            'city'            => 'nullable|integer',
            'sourceOfIncome'  => 'nullable|string',
            // KYC personal details + PEP declarations.
            'nationality'     => 'nullable|string|max:100',
            'occupation'      => 'nullable|string|max:100',
            'occupationLevel' => 'nullable|string|in:Senior,Middle,Junior,Unemployed',
            'employerName'    => 'nullable|string|max:50',
            'country'         => 'nullable|string|max:100',
            'isPep'           => 'nullable|boolean',
            'pepType'         => 'nullable|string|max:255',
            'isPepRelated'    => 'nullable|boolean',
            'pepRelationship' => 'nullable|string|max:50',
            'pepRelationshipSpecify' => 'nullable|string|max:255',
            // Admin risk classification (graphiteBWV8 customer_category / category_reason parity).
            'riskCategory'       => 'nullable|string|in:low,medium,high',
            'riskCategoryReason' => 'nullable|string|max:1000',
        ]);

        // Update customer
        $customerFields = array_intersect_key($validated, array_flip(['firstName', 'middleName', 'lastName', 'email', 'cellphone']));
        if (array_key_exists('riskCategory', $validated)) {
            $customerFields['customer_category'] = array_flip(self::RISK_CATEGORY_LABELS)[$validated['riskCategory']];
        }
        if (array_key_exists('riskCategoryReason', $validated)) {
            $customerFields['category_reason'] = $this->encodeCategoryReason($validated['riskCategoryReason']);
        }
        // Guard against a local dev DB that hasn't caught up to the shared
        // production schema yet (graphite_dev lags prod migrations).
        $customerCols   = \Schema::getColumnListing($customer->getTable());
        $customerFields = array_intersect_key($customerFields, array_flip($customerCols));
        if (!empty($customerFields)) {
            $customer->update($customerFields);
        }

        // Update profile. `customer_profile.gender` / `.maritalstatus` are
        // INT columns (PolicyResource::maritalStatusLabel/gender read them
        // as codes: gender 1=Male else Female; maritalstatus 1=Single,
        // 2=Married, 3=Divorced, 4=Widowed) — map the form's string values
        // before writing so they round-trip correctly on read.
        $profileFields = array_intersect_key($validated, array_flip(['dob', 'omang', 'passport', 'address', 'state', 'city']));
        if (isset($validated['gender'])) {
            $profileFields['gender'] = $validated['gender'] === 'Male' ? 1 : 0;
        }
        if (isset($validated['maritalstatus'])) {
            $profileFields['maritalstatus'] = array_flip(['Single', 'Married', 'Divorced', 'Widowed'])[$validated['maritalstatus']] + 1;
        }
        if (isset($validated['sourceOfIncome'])) {
            // `customer_profile.sourceOfIncome` is read everywhere (CustomerProfile::
            // getSourceOfIncomeAttribute, PolicyResource::formatSourceOfIncome/
            // extractSourceOfIncomeKey) via json_decode — it stores either a JSON
            // string like "employment" or a JSON object for richer mobile-app
            // submissions. Writing the bare unquoted key here would round-trip to
            // null on the next read, silently wiping out the value just saved.
            $profileFields['sourceOfIncome'] = json_encode($validated['sourceOfIncome']);
        }
        // KYC personal details — employerName reuses the e_name column.
        foreach ([
            'nationality'     => 'nationality',
            'occupation'      => 'occupation',
            'occupationLevel' => 'occupation_level',
            'country'         => 'country',
            'employerName'    => 'e_name',
        ] as $in => $col) {
            if (array_key_exists($in, $validated)) {
                $profileFields[$col] = $validated[$in];
            }
        }
        // PEP declarations — sub-fields cleared unless the flag is set.
        if (array_key_exists('isPep', $validated)) {
            $isPep = (bool) $validated['isPep'];
            $profileFields['is_pep']   = $isPep ? 1 : 0;
            $profileFields['pep_type'] = $isPep ? ($validated['pepType'] ?? null) : null;
        }
        if (array_key_exists('isPepRelated', $validated)) {
            $isPepRelated = (bool) $validated['isPepRelated'];
            $profileFields['is_pep_related']           = $isPepRelated ? 1 : 0;
            $profileFields['pep_relationship']         = $isPepRelated ? ($validated['pepRelationship'] ?? null) : null;
            $profileFields['pep_relationship_specify'] = $isPepRelated ? ($validated['pepRelationshipSpecify'] ?? null) : null;
        }
        // Drop any columns the table doesn't have yet (migration not run) so the
        // update degrades gracefully instead of throwing on a missing column.
        $profileCols   = \Schema::getColumnListing((new \AlphaDirect\CustomerProfile)->getTable());
        $profileFields = array_intersect_key($profileFields, array_flip($profileCols));
        if (!empty($profileFields)) {
            // Eloquent (not DB::table) so CustomerProfile's Auditable trait
            // records old/new values — surfaces in the Policy page's Logs
            // tab (PolicyController::logs() reads the audits table by the
            // policy_number CustomerProfile::transformAudit() attaches).
            \AlphaDirect\CustomerProfile::updateOrCreate(['customer_id' => $id], $profileFields);
        }

        activity('Customer')->performedOn($customer)->causedBy(auth()->user())->log('Customer updated');

        // Bust the caches that embed this customer's data. PolicyController::show
        // serves each policy (with its eager-loaded customer + profile) from a
        // 30-min CacheService::rememberPolicy cache that is keyed by policy id /
        // number, NOT by customer — so without this, an edit here keeps showing
        // stale customer/profile details on the Policy Details > Customer tab
        // until the TTL expires. Forget every cache entry that can hold this
        // customer: the customer cache, and each of their policies by id + number.
        try {
            CacheService::forgetCustomer($id);
            $customerPolicies = DB::table('policies')
                ->where('customer_id', $id)
                ->get(['id', 'policyNumber']);
            foreach ($customerPolicies as $pol) {
                CacheService::forgetPolicy((int) $pol->id);
                if (!empty($pol->policyNumber)) {
                    \Illuminate\Support\Facades\Cache::forget("policy_number_{$pol->policyNumber}");
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Customer update cache invalidation failed: ' . $e->getMessage(), ['customer_id' => $id]);
        }

        return response()->json(['message' => 'Customer updated successfully.']);
    }

    public function policies(int $id): JsonResponse
    {
        Customer::findOrFail($id); // 404 if not found

        $policies = Policy::with(['product:id,name'])
            ->where('customer_id', $id)
            ->select('id', 'policyNumber', 'status', 'premium', 'product_id', 'created_at')
            ->orderBy('id', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => PolicyCollection::make($policies),
            'meta' => [
                'total'        => $policies->total(),
                'per_page'     => $policies->perPage(),
                'current_page' => $policies->currentPage(),
                'last_page'    => $policies->lastPage(),
            ],
        ]);
    }
}
