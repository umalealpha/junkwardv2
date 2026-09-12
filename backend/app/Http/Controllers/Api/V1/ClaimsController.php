<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Claim;
use AlphaDirect\Customer;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Resources\V1\ClaimResource;
use AlphaDirect\Lookup;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ClaimsController extends Controller
{
    /**
     * Ensure a new_claims shadow row exists for this claim, seeding a minimal
     * valid one (mirroring store()'s dual-write) if absent, and return its id.
     *
     * Graphite's create flow dual-writes new_claims, but claims created via the
     * Claim Tracker (claims.source = 'claims-tracker') only get a `claims` row.
     * update() reads/writes several things (Classification & Allocation fields,
     * the per-type sub-claim tables) keyed off new_claims, so without this those
     * edits silently no-op for Claim-Tracker claims. Returns null if new_claims
     * is unavailable or the seed fails (caller then skips the dependent write).
     */
    private function ensureNewClaimShadow($claim): ?int
    {
        if (!Schema::hasTable('new_claims')) {
            return null;
        }
        $id = DB::table('new_claims')->where('claim_number', $claim->claim_number)->value('id');
        if ($id) {
            return (int) $id;
        }
        try {
            $cols = Schema::getColumnListing('new_claims');
            $policyRow = $claim->policy_id
                ? DB::table('policies')->where('id', $claim->policy_id)
                    ->first(['policyNumber', 'customer_id', 'agent_id'])
                : null;
            $seed = array_intersect_key([
                'claim_number'      => $claim->claim_number,
                'policyNumber'      => $policyRow->policyNumber ?? null,
                'policy_id'         => $claim->policy_id ?? null,
                'customer_id'       => $policyRow->customer_id ?? ($claim->customer_id ?? null),
                'agent_id'          => $policyRow->agent_id ?? ($claim->agent_id ?? null),
                'claim_type'        => $claim->claim_type ?? null,
                'claim_sub_type_id' => 0,
                'date_of_loss'      => now()->format('Y-m-d'),
                'created_by'        => auth()->id(),
                'claim_approved'    => 'No',
                'created_at'        => now(),
                'updated_at'        => now(),
            ], array_flip($cols));

            return (int) DB::table('new_claims')->insertGetId($seed);
        } catch (\Throwable $e) {
            Log::warning('ensureNewClaimShadow failed for claim '
                . ($claim->id ?? '?') . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Paginated claims list with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status'     => 'nullable|string',
            'claim_type' => 'nullable|string',
            'search'     => 'nullable|string|max:100',
            'per_page'   => 'nullable|integer|min:5|max:100',
            'date_from'  => 'nullable|date_format:Y-m-d',
            'date_to'    => 'nullable|date_format:Y-m-d',
            // ── Additive "All Claims" parity filters ─────────────────────────
            // Every one is optional; when absent the query behaves EXACTLY as
            // before (same select, same default id-desc ordering), so existing
            // callers are untouched.
            'month'      => 'nullable|date_format:Y-m',        // convenience over created_at
            'channel'    => 'nullable|in:broker,direct',       // policies.agent_id present = broker
            'source'     => 'nullable|string|max:40',           // claims.source origin tag
            'synced'     => 'nullable|boolean',                 // claims.external_ref present = linked to Claims Tracker
            'reversed'   => 'nullable|boolean',                 // has a voided payment (is_payment_voided IN 1,2)
            'major'      => 'nullable|boolean',                 // total reserve > P300,000
            'sort'       => 'nullable|in:claim_number,created_at,status,claim_type,id',
            'direction'  => 'nullable|in:asc,desc',
        ]);

        // Tracker columns only exist once the 2026_06_05 migration has run —
        // guard so this endpoint never 500s on an older schema.
        $hasExternalRef = $this->schemaHasColumn('claims', 'external_ref');
        $hasSource      = $this->schemaHasColumn('claims', 'source');
        $hasAllocCol    = $this->schemaHasColumn('claims', 'claim_allocated_to');

        // Domain: a claim is "MAJOR" once its (net) reserve exceeds P300,000.
        $majorThreshold = 300000;

        // Resolve handler (claim_allocated_to → users) ids matching the search
        // term, so the search box also finds claims by their allocated handler
        // (in addition to claim #, client, policy # and plate).
        $handlerIds = [];
        if (!empty($validated['search'])) {
            $st = trim(preg_replace('/\s+/', ' ', $validated['search']));
            $handlerIds = DB::table('users')
                ->where(function ($u) use ($st) {
                    $u->whereRaw("CONCAT_WS(' ', TRIM(firstName), TRIM(lastName)) LIKE ?", ["%{$st}%"])
                      ->orWhere('firstName', 'like', "%{$st}%")
                      ->orWhere('lastName', 'like', "%{$st}%");
                })
                ->pluck('id')->all();
        }

        $selectCols = [
            'claims.id', 'claims.claim_number', 'claims.claim_type', 'claims.status',
            'claims.policy_id', 'claims.customer_id', 'claims.created_at', 'claims.registered_claim',
            'claims.created_by',
        ];
        if ($hasExternalRef) { $selectCols[] = 'claims.external_ref'; }
        if ($hasSource)      { $selectCols[] = 'claims.source'; }

        $sortCol = $validated['sort'] ?? 'id';
        $sortDir = $validated['direction'] ?? 'desc';

        $claims = Claim::select($selectCols)
            ->with([
                // agent_id surfaced so the response can label Broker vs Direct.
                'policy:id,policyNumber,product_id,customer_id,agent_id',
                'policy.product:id,name',
                'customer:id,firstName,lastName,cellphone',
            ])
            ->when($validated['status'] ?? null, fn($q, $v) => $q->where('claims.status', $v))
            ->when($validated['claim_type'] ?? null, fn($q, $v) => $q->where('claims.claim_type', $v))
            ->when($validated['date_from'] ?? null, fn($q, $v) => $q->whereDate('claims.created_at', '>=', $v))
            ->when($validated['date_to'] ?? null, fn($q, $v) => $q->whereDate('claims.created_at', '<=', $v))
            ->when($validated['month'] ?? null, function ($q, $m) {
                [$y, $mo] = explode('-', $m);
                $q->whereYear('claims.created_at', (int) $y)
                  ->whereMonth('claims.created_at', (int) $mo);
            })
            ->when($validated['channel'] ?? null, function ($q, $ch) {
                $q->whereHas('policy', function ($p) use ($ch) {
                    if ($ch === 'broker') {
                        $p->whereNotNull('agent_id')->where('agent_id', '!=', 0);
                    } else {
                        $p->where(fn($x) => $x->whereNull('agent_id')->orWhere('agent_id', 0));
                    }
                });
            })
            ->when($hasSource ? ($validated['source'] ?? null) : null,
                fn($q, $v) => $q->where('claims.source', $v))
            ->when($hasExternalRef && $request->has('synced'), function ($q) use ($request) {
                $request->boolean('synced')
                    ? $q->whereNotNull('claims.external_ref')
                    : $q->whereNull('claims.external_ref');
            })
            ->when($request->has('reversed'), function ($q) use ($request) {
                $q->{$request->boolean('reversed') ? 'whereIn' : 'whereNotIn'}('claims.id', function ($sub) {
                    $sub->select('claim_id')->from('claim_reserves_coverages')
                        ->whereIn('is_payment_voided', [1, 2]);
                });
            })
            ->when($request->boolean('major'), function ($q) use ($majorThreshold) {
                $q->whereIn('claims.id', function ($sub) use ($majorThreshold) {
                    $sub->select('claim_id')->from('claim_reserves_coverages')
                        ->whereNotIn('is_payment_voided', [1, 2])
                        ->groupBy('claim_id')
                        ->havingRaw('SUM(reserve_amt) > ?', [$majorThreshold]);
                });
            })
            ->when($validated['search'] ?? null, function ($q, $search) use ($handlerIds, $hasAllocCol) {
                $search = trim(preg_replace('/\s+/', ' ', $search));
                $normalizedPlate = str_replace(' ', '', $search);
                $words = count(explode(' ', $search)) >= 2
                    ? array_values(array_filter(explode(' ', $search)))
                    : [];
                $q->where(function ($q) use ($search, $normalizedPlate, $words, $handlerIds, $hasAllocCol) {
                    $q->where('claims.claim_number', 'like', "%{$search}%")
                      ->orWhereHas('customer', function ($c) use ($search, $words) {
                          $c->where('firstName', 'like', "%{$search}%")
                            ->orWhere('lastName', 'like', "%{$search}%")
                            ->orWhereRaw("CONCAT_WS(' ', TRIM(firstName), TRIM(lastName)) LIKE ?", ["%{$search}%"]);
                          if (count($words) >= 2) {
                              $c->orWhere(fn($i) =>
                                  $i->where('firstName', 'like', "%{$words[0]}%")
                                    ->where('lastName', 'like', "%{$words[1]}%")
                              )->orWhere(fn($i) =>
                                  $i->where('firstName', 'like', "%{$words[1]}%")
                                    ->where('lastName', 'like', "%{$words[0]}%")
                              );
                          }
                      })
                      ->orWhereHas('policy', fn($p) =>
                          $p->where('policyNumber', 'like', "%{$search}%")
                      )
                      ->orWhere('claims.vehicle_plate', 'like', "%{$search}%")
                      ->orWhere('claims.vehicle_plate', 'like', "%{$normalizedPlate}%")
                      ->orWhereHas('policy.vehicle', fn($v) =>
                          $v->where('vehiclePlate', 'like', "%{$search}%")
                            ->orWhere('vehiclePlate', 'like', "%{$normalizedPlate}%")
                      )
                      ->orWhereHas('policy.riskAddress', fn($r) =>
                          $r->where('address_name', 'like', "%{$search}%")
                            ->orWhere('physical_address', 'like', "%{$search}%")
                      );
                    // Handler search — matches the allocated user by name.
                    // claim_allocated_to lives on claims (older rows) and/or
                    // new_claims (V2-registered), so cover both sources.
                    if (!empty($handlerIds)) {
                        if ($hasAllocCol) {
                            $q->orWhereIn('claims.claim_allocated_to', $handlerIds);
                        }
                        $q->orWhereIn('claims.claim_number', function ($sub) use ($handlerIds) {
                            $sub->select('claim_number')->from('new_claims')
                                ->whereIn('claim_allocated_to', $handlerIds);
                        });
                    }
                });
            })
            ->orderBy('claims.' . $sortCol, $sortDir)
            ->paginate($validated['per_page'] ?? 25);

        // Batch-load company names for customers with customer_profile.entity_type=Organisation
        // This mirrors graphiteBWV8 ClaimsController::data() logic for DOM/COM products (7,8,16,17,18,19)
        $customerIds = $claims->pluck('customer_id')->filter()->unique()->values()->toArray();
        $companyNames = [];
        if (!empty($customerIds)) {
            $profiles = DB::table('customer_profile')
                ->whereIn('customer_id', $customerIds)
                ->where('entity_type', 'Organisation')
                ->whereNotNull('company_id')
                ->get(['customer_id', 'company_id']);
            $companyIds = $profiles->pluck('company_id')->filter()->unique()->values()->toArray();
            $companies = $companyIds
                ? DB::table('companies')->whereIn('id', $companyIds)->pluck('name', 'id')->toArray()
                : [];
            foreach ($profiles as $p) {
                $companyNames[$p->customer_id] = $companies[$p->company_id] ?? null;
            }
        }

        // Products that support company name (DOM/COM and specialist)
        $companyProductIds = [7, 8, 16,17,18,20,22,23,24];

        // Claim Handler = the user the claim is ALLOCATED TO (claim_allocated_to),
        // NOT the creator. Allocation lives on claims.claim_allocated_to, or — for
        // V2-registered claims — on new_claims.claim_allocated_to (matched by
        // claim_number, the same fallback the detail page uses). Resolve both
        // sources into a claim_id → user_id map, then batch-load the names.
        $allocByClaimId = [];
        foreach ($claims as $c) {
            $aid = $c->claim_allocated_to ?? null; // null-safe: absent column → null
            if ($aid) $allocByClaimId[$c->id] = (int) $aid;
        }
        $missingNumbers = $claims->reject(fn ($c) => isset($allocByClaimId[$c->id]))
            ->pluck('claim_number')->filter()->unique()->values()->toArray();
        if (!empty($missingNumbers)) {
            $ncAlloc = DB::table('new_claims')
                ->whereIn('claim_number', $missingNumbers)
                ->whereNotNull('claim_allocated_to')
                ->pluck('claim_allocated_to', 'claim_number')
                ->toArray();
            foreach ($claims as $c) {
                if (!isset($allocByClaimId[$c->id]) && !empty($ncAlloc[$c->claim_number])) {
                    $allocByClaimId[$c->id] = (int) $ncAlloc[$c->claim_number];
                }
            }
        }
        $allocUserIds = array_values(array_unique(array_filter($allocByClaimId)));
        $handlerNames = [];
        if (!empty($allocUserIds)) {
            $handlerNames = DB::table('users')
                ->whereIn('id', $allocUserIds)
                ->get(['id', 'firstName', 'lastName'])
                ->keyBy('id')
                ->map(fn ($u) => trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')))
                ->toArray();
        }

        // Badge signals — computed ONLY for the visible page's claim ids (batch,
        // O(page size)), same pattern as the handler-name batch above.
        //   • reserve total (active coverages only) → MAJOR badge (> P300,000)
        //   • any voided payment (is_payment_voided IN 1,2) → REVERSED badge
        // Both read claim_reserves_coverages, indexed on claim_id. The reserve
        // sum is a simplification of the detail page's net-reserve calc (it
        // does not deduct salvage/subrogation/reset buckets) — good enough for
        // a MAJOR *flag*, and never used for financial display.
        $pageClaimIds = $claims->pluck('id')->filter()->values()->all();
        $reserveTotals = [];
        $reversedSet   = [];
        if (!empty($pageClaimIds)) {
            $reserveTotals = DB::table('claim_reserves_coverages')
                ->whereIn('claim_id', $pageClaimIds)
                ->whereNotIn('is_payment_voided', [1, 2])
                ->groupBy('claim_id')
                ->selectRaw('claim_id, SUM(reserve_amt) as total')
                ->pluck('total', 'claim_id')
                ->toArray();
            $reversedSet = array_flip(
                DB::table('claim_reserves_coverages')
                    ->whereIn('claim_id', $pageClaimIds)
                    ->whereIn('is_payment_voided', [1, 2])
                    ->distinct()
                    ->pluck('claim_id')
                    ->all()
            );
        }

        return response()->json([
            'data' => $claims->map(function ($c) use ($companyNames, $companyProductIds, $handlerNames, $allocByClaimId, $reserveTotals, $reversedSet, $majorThreshold, $hasExternalRef, $hasSource) {
                $productId = $c->policy->product_id ?? null;
                $companyName = ($productId && in_array($productId, $companyProductIds))
                    ? ($companyNames[$c->customer_id] ?? null)
                    : null;
                $displayName = $companyName
                    ? ucwords($companyName)
                    : trim(($c->customer->firstName ?? '') . ' ' . ($c->customer->lastName ?? ''));
                $allocId = $allocByClaimId[$c->id] ?? null;
                $handlerName = ($allocId && !empty($handlerNames[$allocId]))
                    ? $handlerNames[$allocId]
                    : 'N/A';
                $reserveTotal = (float) ($reserveTotals[$c->id] ?? 0);
                $agentId = $c->policy->agent_id ?? null;
                return [
                    'id'           => $c->id,
                    'claim_number' => $c->claim_number,
                    'claim_type'   => $c->claim_type,
                    'status'       => $c->status,
                    'registered_claim' => $c->registered_claim,
                    'created_at'   => optional($c->created_at)->toIso8601String(),
                    'claim_handler'=> $handlerName,
                    // ── Additive badge / filter signals (all real Graphite data) ──
                    // MAJOR: net reserve over P300,000.
                    'total_reserve'=> $reserveTotal,
                    'is_major'     => $reserveTotal > $majorThreshold,
                    // REVERSED: claim has at least one voided payment.
                    'has_reversal' => isset($reversedSet[$c->id]),
                    // Channel: broker-assisted (policy carries an agent) vs direct.
                    'channel'      => ($agentId && (int) $agentId !== 0) ? 'Broker' : 'Direct',
                    // Claims-Tracker link. `synced`/`source` are null when the
                    // tracker columns are absent on this schema (never fabricated).
                    'synced'       => $hasExternalRef ? !empty($c->external_ref) : null,
                    'source'       => $hasSource ? ($c->source ?? null) : null,
                    'policy' => $c->policy ? [
                        'id'            => $c->policy->id,
                        'policy_number' => $c->policy->policyNumber,
                        'product_name'  => $c->policy->product->name ?? null,
                    ] : null,
                    'customer' => $c->customer ? [
                        'id'          => $c->customer->id,
                        'name'        => $displayName ?: 'N/A',
                        'company_name'=> $companyName,
                        'is_company'  => !empty($companyName),
                        'cellphone'   => $c->customer->cellphone,
                    ] : null,
                ];
            }),
            'meta' => [
                'total'        => $claims->total(),
                'per_page'     => $claims->perPage(),
                'current_page' => $claims->currentPage(),
                'last_page'    => $claims->lastPage(),
            ],
        ]);
    }

    /**
     * Per-process Schema::hasTable cache. The dev DB is often remote (~600ms
     * round-trip), and a single call to show() issued up to seven
     * Schema::hasTable / getColumnListing queries. Caching reduces this to
     * one hit per distinct table per process lifetime.
     */
    private static array $schemaTableCache = [];

    private function schemaHasTable(string $table): bool
    {
        return self::$schemaTableCache[$table] ??= \Schema::hasTable($table);
    }

    /**
     * Per-process Schema::hasColumn cache (same rationale as the table cache).
     * Used by index() so the additive Claims-Tracker filters (source /
     * external_ref) and the handler-search join degrade to a no-op on any
     * environment where the 2026_06_05 tracker migration has not yet run —
     * rather than 500-ing on a missing column.
     */
    private static array $schemaColumnCache = [];

    private function schemaHasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        return self::$schemaColumnCache[$key] ??= \Schema::hasColumn($table, $column);
    }

    /**
     * Single claim detail — full data matching graphiteBWV8 claim_details view.
     * Returns: claim info, new_claims data, policy, customer, reserves, assessor,
     *          attachments, suppliers, invoices, activity log, complaint log.
     */
    public function show($id): JsonResponse
    {
        $id = (int) $id;
        if ($id <= 0) {
            return response()->json(['message' => 'Invalid claim id.'], 404);
        }
        try {
            return $this->showImpl($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Claim not found.'], 404);
        } catch (\Throwable $e) {
            // BUG-006: surface the actual failure to the client so the
            // detail page stops spinning forever waiting on a request
            // that's already failed server-side. Log full stack for ops.
            Log::error('claim show failed', [
                'claim_id' => $id,
                'error'    => $e->getMessage(),
                'file'     => $e->getFile(),
                'line'     => $e->getLine(),
            ]);
            return response()->json([
                'message' => 'Failed to load claim details.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Inner show() body — kept separate so the public entry point can wrap
     * it in a try/catch without indenting 700+ lines.
     */
    private function showImpl(int $id): JsonResponse
    {
        $claim = Claim::with([
            'policy:id,policyNumber,product_id,customer_id,status,premium,agent_id,agency_id',
            'policy.product:id,name',
            'customer:id,firstName,lastName,email,cellphone',
        ])->findOrFail($id);

        // New claims data (extended fields from new_claims table)
        // Link by claim_number (policy_id may be null in new_claims)
        $newClaim = DB::table('new_claims')->where('claim_number', $claim->claim_number)->first();

        // Accident details (for motor accident claims)
        $accidentDetails = DB::table('claim_accidents')->where('claim_id', $id)->first();

        // Location name
        $locationName = null;
        if ($newClaim && $newClaim->location_id) {
            $loc = DB::table('risk_address')->where('id', $newClaim->location_id)->first(['address_name', 'physical_address']);
            $locationName = $loc ? ($loc->physical_address ?? $loc->address_name) : null;
        }

        // Claim sub-type name. Some envs ship `claim_subtype` (V8) while
        // newer schemas use `claim_sub_types` (Laravel-pluralised). Probe
        // both — first hit wins — so a missing table doesn't 500 the
        // whole detail page. (BUG-006: DOMG claims frequently carry a
        // claim_sub_type_id, so an absent table here was an infinite
        // spinner trigger before this guard.)
        $claimSubTypeName = null;
        if ($newClaim && $newClaim->claim_sub_type_id) {
            // Try the known label columns directly without a getColumnListing
            // round-trip; the catch handles a missing column / missing table.
            foreach (['claim_subtype' => 'sub_type', 'claim_sub_types' => 'name'] as $cstTable => $labelCol) {
                if (!$this->schemaHasTable($cstTable)) continue;
                try {
                    $cst = DB::table($cstTable)
                        ->where('id', $newClaim->claim_sub_type_id)
                        ->first([$labelCol]);
                    if ($cst) { $claimSubTypeName = $cst->{$labelCol} ?? null; break; }
                } catch (\Throwable $e) { /* column mismatch — try the next table */ }
            }
        }

        // Allocated to (user name). V2 writes claim_allocated_to onto the
        // primary `claims` row (migration 2026_04_20_100001); legacy kept
        // it on `new_claims`. Prefer the V2 column, fall back to new_claims
        // so existing legacy claims stay visible.
        $allocatedToId = property_exists($claim, 'claim_allocated_to')
            ? $claim->claim_allocated_to
            : null;
        if (!$allocatedToId && $newClaim && $newClaim->claim_allocated_to) {
            $allocatedToId = $newClaim->claim_allocated_to;
        }
        $agentId   = $claim->policy->agent_id ?? null;
        $creatorId = $claim->created_by ?? null;
        // Batch the three user-name lookups into one round-trip — on a remote
        // dev DB (~600ms ping) the three sequential .first() calls cost ~1.8s.
        $userIds = array_filter([$allocatedToId, $agentId, $creatorId]);
        $userMap = [];
        if (!empty($userIds)) {
            $userMap = DB::table('users')
                ->whereIn('id', array_unique($userIds))
                ->get(['id', 'firstName', 'lastName'])
                ->keyBy('id')
                ->map(fn($u) => trim($u->firstName . ' ' . $u->lastName))
                ->toArray();
        }
        $allocatedTo   = $allocatedToId && isset($userMap[$allocatedToId]) ? $userMap[$allocatedToId] : null;
        $agentName     = $agentId       && isset($userMap[$agentId])       ? $userMap[$agentId]       : null;
        $createdByName = $creatorId     && isset($userMap[$creatorId])     ? $userMap[$creatorId]     : 'N/A';

        // Company name for DOM/COM policies (if customer is an Organisation)
        $companyName = null;
        $companyProductIds = [7, 8, 16, 17, 18, 19, 20, 22, 23, 24];
        if ($claim->policy && in_array($claim->policy->product_id, $companyProductIds) && $claim->customer_id) {
            $profile = DB::table('customer_profile')
                ->where('customer_id', $claim->customer_id)
                ->where('entity_type', 'Organisation')
                ->whereNotNull('company_id')
                ->first(['company_id']);
            if ($profile) {
                $company = DB::table('companies')->where('id', $profile->company_id)->first(['name']);
                $companyName = $company ? ucwords($company->name) : null;
            }
        }

        // Transaction type labels (from graphiteBWV8 ClaimsController)
        // Display labels for transaction_type ids. 91/92 are grouped under the
        // "Salvage / Subrogation" rebrand so the list view matches the Add
        // Reserve/Payment dropdown — same override the V2 lookup endpoint
        // applies (ClaimsV2Controller::TX_TYPE_LABEL_OVERRIDES).
        $txTypeLabels = [
            42 => 'Loss Reserve', 43 => 'Loss Payment', 44 => 'Reset Reserves',
            50 => 'Own Damage', 49 => 'Third Party',
            53 => 'Payment - Repair', 54 => 'Payment - Cash In Lieu', 55 => 'Payment - Salvage',
            86 => 'Claim Allocated', 89 => 'TP Liability Reserve', 90 => 'TP Liability Payment',
            91 => 'Salvage / Subrogation Reserve', 92 => 'Salvage / Subrogation Payment',
        ];

        // Reserves with coverage breakdown
        $reserves = DB::table('claim_reserves as cr')
            ->where('cr.claim_id', $id)
            ->orderByDesc('cr.id')
            ->get();
        $reserveCoverages = DB::table('claim_reserves_coverages')
            ->where('claim_id', $id)
            ->get();
        // Totals exclude voided originals (is_payment_voided=1) and their
        // reversal entries (is_payment_voided=2). Including them would
        // double-count: a P500 voided payment leaves three rows in the table
        // (the Loss Reserve, the original payment row, and the Loss Payment
        // - VOID reversal) — naively summing inflates Total Reserve by the
        // reversal's reserve_amt and Total Payment by the original's
        // payment_amt, even though the net economic effect is zero.
        $activeCoverages = $reserveCoverages->filter(function ($c) {
            $v = (int) ($c->is_payment_voided ?? 0);
            return $v !== 1 && $v !== 2;
        });

        // graphiteBWV8 parity (admin/ClaimsController.php:463-489) +
        // V2's ClaimsV2Controller::reserveHeaderTotals(). Both totals
        // deduct: subrogation/salvage buckets (stored in their own columns)
        // and the payment_amt of any coverage whose parent reserve is
        // transaction_type=44 (Reset Reserves). A reset zeroes a prior
        // reserve — counting its amount as either a fresh reserve or
        // payment double-books a transaction that was meant to cancel one.
        $resetReserveIds = $reserves
            ->filter(fn ($r) => (int) ($r->transaction_type ?? 0) === 44)
            ->pluck('id')
            ->all();
        $type44Payment = (float) $activeCoverages
            ->whereIn('reserve_id', $resetReserveIds)
            ->sum('payment_amt');

        $subrogationReserve = (float) $activeCoverages->sum('subrogation_reserve');
        $subrogationPayment = (float) $activeCoverages->sum('subrogation_payment');
        $salvageReserve     = (float) $activeCoverages->sum('salvage_reserve');
        $salvagePayment     = (float) $activeCoverages->sum('salvage_payment');

        $totalReserve = (float) $activeCoverages->sum('reserve_amt')
            - $subrogationReserve - $salvageReserve - $type44Payment;
        $totalPayment = (float) $activeCoverages->sum('payment_amt')
            - $subrogationPayment - $salvagePayment - $type44Payment;

        // Batch-load payee names (could be user IDs or supplier IDs)
        $payeeIds = $reserves->pluck('payee')->filter()->unique()->values()->toArray();
        $supplierNames = [];
        $userNames = [];
        if (!empty($payeeIds)) {
            $supplierNames = DB::table('suppliers')->whereIn('id', $payeeIds)
                ->pluck('supplierName', 'id')->toArray();
            $userNames = DB::table('users')->whereIn('id', $payeeIds)
                ->get(['id', 'firstName', 'lastName'])->keyBy('id')
                ->map(fn($u) => trim($u->firstName . ' ' . $u->lastName))->toArray();
        }

        // Sub-type lookup values (legacy Lookup::where('key','transaction_sub_type')).
        // The lookup table is named `lookup_data` on this project — `lookups`
        // (the older naming) does not exist and triggers a 500 on the claim
        // detail endpoint as soon as ANY reserve row carries a sub-type id.
        $subTypeIds = $reserves->pluck('transaction_sub_type')->filter()->unique()->values()->toArray();
        $subTypeLabels = [];
        if (!empty($subTypeIds) && \Schema::hasTable('lookup_data')) {
            $subTypeLabels = DB::table('lookup_data')->whereIn('id', $subTypeIds)
                ->pluck('value', 'id')->toArray();
            // Apply the same display rename as the lookup endpoint
            // (ClaimsV2Controller::TX_SUB_TYPE_LABEL_OVERRIDES) so the
            // transaction-list "Sub Type" column matches what the operator
            // picked from the dropdown.
            $subTypeOverrides = [
                97  => 'Salvage Reserve',
                98  => 'Subrogation Reserve',
                99  => 'Salvage Payment',
                100 => 'Subrogation Payment',
            ];
            // CAREFUL: don't reuse $id as the loop key — it shadows the
            // outer claim id and every downstream where('claim_id', $id)
            // (attachments, quotes, activity log, complaints, …) starts
            // pulling rows for claim_id=100 (the last override key).
            foreach ($subTypeOverrides as $subTypeId => $label) {
                if (isset($subTypeLabels[$subTypeId])) $subTypeLabels[$subTypeId] = $label;
            }
        }

        // Inserted-user names — legacy uses user_id, some envs only have created_by
        $insertedUserIds = $reserves->map(function ($r) {
            $row = (array) $r;
            return $row['user_id'] ?? $row['created_by'] ?? null;
        })->filter()->unique()->values()->toArray();
        $insertedUserNames = [];
        if (!empty($insertedUserIds)) {
            $insertedUserNames = DB::table('users')->whereIn('id', $insertedUserIds)
                ->get(['id', 'firstName', 'lastName'])->keyBy('id')
                ->map(fn($u) => trim($u->firstName . ' ' . $u->lastName))->toArray();
        }

        // Running balance computed per-reserve in chronological order (matches
        // legacy's Running Balance column, which walks up the same claim).
        $reservesSortedAsc = $reserves->sortBy('id')->values();
        $running = 0.0;
        $runningByReserveId = [];
        foreach ($reservesSortedAsc as $r) {
            $rowReserve = (float) $reserveCoverages->where('reserve_id', $r->id)->sum('reserve_amt');
            $rowPayment = (float) $reserveCoverages->where('reserve_id', $r->id)->sum('payment_amt');
            $running += $rowReserve - $rowPayment;
            $runningByReserveId[$r->id] = round($running, 2);
        }

        // Assessor / Assessment
        $assessment = DB::table('claim_assessment')
            ->where('claim_id', $id)
            ->first();
        $assessorName = null;
        if ($assessment && $assessment->assessor_id) {
            $assessor = DB::table('users')->where('id', $assessment->assessor_id)->first(['firstName', 'lastName']);
            $assessorName = $assessor ? trim($assessor->firstName . ' ' . $assessor->lastName) : null;
        }

        // Attachments — merge the legacy `attachment` column (serialized
        // array of paths from the admin blade uploader) with the V2
        // `file_name` column (single path from /claims-v2 uploads).
        // Without the fallback, V2-created attachments show up as name +
        // doc-type rows with no viewable files, which defeats the whole
        // Attachments tab.
        $attachments = DB::table('claim_attachments')
            ->where('claim_id', $id)
            ->orderByDesc('id')
            ->get()
            ->map(function ($a) {
                $files = [];
                if (!empty($a->attachment)) {
                    $decoded = @unserialize($a->attachment, ['allowed_classes' => false]);
                    if (is_array($decoded)) {
                        foreach ($decoded as $f) {
                            if ($f) $files[] = ['name' => basename($f), 'url' => $this->cdnUrl($f)];
                        }
                    } else {
                        $files[] = ['name' => basename($a->attachment), 'url' => $this->cdnUrl($a->attachment)];
                    }
                }
                // V2 fallback — the row was written via POST /claims-v2/
                // {id}/documents and stores a single path in file_name.
                if (empty($files) && !empty($a->file_name)) {
                    $files[] = ['name' => $a->name ?: basename($a->file_name), 'url' => $this->cdnUrl($a->file_name)];
                }
                return [
                    'id' => $a->id,
                    'name' => $a->name,
                    'documentTypeName' => $a->document_type_name ?? null,
                    'type' => $a->type ?? null,
                    'files' => $files,
                    'createdAt' => $a->created_at,
                ];
            });

        // Supplier quotes
        $quotes = DB::table('claim_quotes')
            ->where('claim_id', $id)
            ->orderByDesc('id')
            ->get()
            ->map(fn($q) => [
                'id' => $q->id,
                'supplierId' => $q->supplier_id,
                'total' => $q->total,
                'status' => $q->status,
                'selectReason' => $q->select_reason,
                'invoiceNotes' => $q->invoice_notes,
                'claimFile' => $q->claim_file ? $this->cdnUrl($q->claim_file) : null,
                'invoice' => $q->invoice ? $this->cdnUrl($q->invoice) : null,
                'createdAt' => $q->created_at,
            ]);

        // Activity log — exact subject_type match. The previous `like
        // '%Claim%'` / `like '%Policy%'` defeated indexes and forced a full
        // scan over millions of activity_log rows (ScheduleTransaction +
        // Policy alone are ~5M rows on prod-shaped data), pushing this
        // endpoint past the 30s FE timeout.
        $activityLog = DB::table('activity_log')
            ->where(function ($q) use ($id, $claim) {
                $q->where(function ($qq) use ($id) {
                    $qq->where('subject_type', 'AlphaDirect\\Claim')
                       ->where('subject_id', $id);
                });
                if ($claim->policy_id) {
                    // Policy-subjected activity that mentions the claim.
                    // The exact subject_type + subject_id narrows the set to
                    // a single policy's rows before the description LIKE runs,
                    // so the wildcard scan is cheap.
                    $q->orWhere(function ($qq) use ($claim) {
                        $qq->where('subject_type', 'AlphaDirect\\Policy')
                           ->where('subject_id', $claim->policy_id)
                           ->where('description', 'like', '%claim%');
                    });
                }
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'description' => $a->description,
                'causerId' => $a->causer_id,
                'createdAt' => $a->created_at,
            ]);

        // Batch-load causer names for activity log
        $causerIds = $activityLog->pluck('causerId')->filter()->unique()->values()->toArray();
        $causerNames = [];
        if ($causerIds) {
            $causerNames = DB::table('users')->whereIn('id', $causerIds)
                ->get(['id', 'firstName', 'lastName'])
                ->keyBy('id')
                ->map(fn($u) => trim($u->firstName . ' ' . $u->lastName))
                ->toArray();
        }

        // Complaint log — left-join users so the FE shows the operator's
        // name in the "Added By" column instead of the raw user id.
        // Mirrors graphiteBWV8 ClaimsController::getClaimComplaintLogs which
        // also resolves added_by → "{firstName} {lastName}".
        $complaints = DB::table('claim_complaint_log as cl')
            ->leftJoin('users as u', 'u.id', '=', 'cl.added_by')
            ->where('cl.claim_id', $id)
            ->orderByDesc('cl.id')
            ->select(
                'cl.id', 'cl.complaint_of', 'cl.complaint_details', 'cl.added_by', 'cl.created_at',
                'u.firstName as added_by_first', 'u.lastName as added_by_last'
            )
            ->get()
            ->map(function ($c) {
                $name = trim((string) ($c->added_by_first ?? '') . ' ' . (string) ($c->added_by_last ?? ''));
                return [
                    'id' => $c->id,
                    'complaintOf' => $c->complaint_of,
                    'complaintDetails' => $c->complaint_details,
                    // Prefer the resolved name; fall back to raw id when the
                    // user row is missing (legacy data, deleted user, etc.).
                    'addedBy' => $name !== '' ? $name : ($c->added_by !== null ? (string) $c->added_by : null),
                    'createdAt' => $c->created_at,
                ];
            });

        // Third party details (for accident claims)
        $thirdParties = DB::table('claim_accident_third_party')
            ->where('claim_id', $id)
            ->get();

        // Vehicle details (for motor claims)
        $vehicleDetails = DB::table('claim_vehicle')
            ->where('claim_id', $id)
            ->first();

        // ── Claim-type-specific payloads from the MIS extended tables ─
        // These match the tables the V2 store() dual-writes to based on
        // form_template (life, legal, hospital_cash). Legacy admin blades
        // (life.blade.php, legal.blade.php, hospital_cashback.blade.php)
        // rendered dedicated sections for these — V2 previously dumped
        // a generic 'Other Information' card that showed weather_condition
        // / fault_party even for Life claims, which confused users who
        // had entered death cause / certificate.
        $lifeDetails = $this->schemaHasTable('claim_life')
            ? DB::table('claim_life')->where('claim_id', $id)->orderByDesc('id')->first()
            : null;
        $legalDetails = $this->schemaHasTable('claim_legal')
            ? DB::table('claim_legal')->where('claim_id', $id)->orderByDesc('id')->first()
            : null;
        // Union legal claim (BONU / BOWASEWU): a legal claim filed against a
        // union member carries its own form record. When present, the detail
        // page shows the union claim's own fields instead of the generic
        // claim_legal (Legal Firm / Lawyer …) block.
        $unionLegalClaim = $this->schemaHasTable('union_legal_claims')
            ? DB::table('union_legal_claims')->where('claim_id', $id)->whereNull('deleted_at')->orderByDesc('id')->first()
            : null;
        // Has the union member paid for the month the claim relates to? Read
        // from the union's monthly payment list (Unions › Payments). Shown on
        // the claim so it is checked BEFORE the claim is processed; not a hard
        // block — Accounts may still be loading that month's list.
        $unionPaymentStatus = null;
        if ($unionLegalClaim && $this->schemaHasTable('union_member_payments')) {
            try {
                $basis = $unionLegalClaim->matter_arose_date ?: $unionLegalClaim->created_at;
                $period = \Carbon\Carbon::parse($basis)->format('Y-m');
                $member = DB::table('union_members')->where('id', $unionLegalClaim->union_member_id)->first(['id_number']);
                $unionPaymentStatus = \AlphaDirect\Http\Controllers\Api\V1\UnionPaymentsController::memberStatus(
                    (int) $unionLegalClaim->union_id,
                    (int) $unionLegalClaim->union_member_id,
                    $member->id_number ?? null,
                    $period
                ) + ['basis' => $unionLegalClaim->matter_arose_date ? 'matter_arose_date' : 'filed_date'];
            } catch (\Throwable $e) {
                \Log::warning('claim.show: union payment status lookup failed', ['claim_id' => $id, 'error' => $e->getMessage()]);
            }
        }
        $hospitalCashDetails = $this->schemaHasTable('claim_hospital_cash')
            ? DB::table('claim_hospital_cash')->where('claim_id', $id)->orderByDesc('id')->first()
            : null;

        // Motor sub-tables. Legacy's accident detail view loads drivers
        // + passenger injuries keyed by claim_id. Accident_passenger is
        // plural — one claim may have many passenger rows.
        $accidentDriver = $this->schemaHasTable('accident_driver')
            ? DB::table('accident_driver')->where('claim_id', $id)->orderByDesc('id')->first()
            : null;
        $accidentPassengers = $this->schemaHasTable('accident_passenger_injury')
            ? DB::table('accident_passenger_injury')
                ->where('claim_id', $id)
                ->get()
                ->map(fn($p) => (array) $p)
                ->values()
            : collect();

        // Legacy sometimes writes certificate/document file paths as S3
        // keys; convert to CDN URLs so the FE can render <img>/links
        // without knowing the bucket layout.
        if ($lifeDetails && !empty($lifeDetails->certificate)) {
            $lifeDetails->certificate_url = $this->cdnUrl($lifeDetails->certificate);
        }

        // DOMG/COMG claim-type specific sub-table data.
        // Legacy tables are keyed by newclaim_id; fetch the row matching
        // the sub-table dictated by claim_type so the FE Edit/View form
        // can populate its type-specific fields (e.g. Business
        // Interruption's nature_of_interruption, Burglary's
        // address_of_premises, Fidelity Guarantee's defaulting_employees_name
        // JSON). Mirrors graphiteBWV8 NewClaimController::edit(:1678) which
        // dispatches on $claims->claim_type to load the right model.
        $subClaimData = null;
        if ($newClaim && $claim->claim_type) {
            $subTableMap = [
                'BUSINESSINTERRUPTION'      => 'business_interruption',
                // graphiteBWV8 main.blade.php groups BUSINESSALLRISKS,
                // ELECTRONICEQUIPMENT and PERSONALALLRISKS onto the same
                // all_risk_and_electronic_equipment table; ALLRISK is a
                // generic alias some callers use.
                'BUSINESSALLRISKS'          => 'all_risk_and_electronic_equipment',
                'ELECTRONICEQUIPMENT'       => 'all_risk_and_electronic_equipment',
                'PERSONALALLRISKS'          => 'all_risk_and_electronic_equipment',
                'ALLRISK'                   => 'all_risk_and_electronic_equipment',
                'FIDELITYGUARANTEE'         => 'fidelity_guarantee',
                // graphiteBWV8 main.blade.php routes CONTRACTORSALLRISKS to
                // contractors_all_risks_public_liability.blade.php + the
                // contractors_all_risks_public_liability table. CARPL is a
                // V2-internal alias; CONTRACTORSALLRISKSPUBLICLIABILITY is
                // the long form some legacy seeds use.
                'CONTRACTORSALLRISKS'                => 'contractors_all_risks_public_liability',
                'CONTRACTORSALLRISKSPUBLICLIABILITY' => 'contractors_all_risks_public_liability',
                'CARPL'                              => 'contractors_all_risks_public_liability',
                // graphiteBWV8 main.blade.php routes ERECTIONALLRISK to
                // erection_all_risk.blade.php + erection_all_risk_claims.
                'ERECTIONALLRISK'                    => 'erection_all_risk_claims',
                'PLANTALLRISKS'                      => 'plant_all_risks_claims',
                'MACHINERYBREAKDOWN'                 => 'machinery_breakdown_claims',
                'MACHINERYBREAKDOWNLOSSOFPROFIT'     => 'machinery_breakdown_lop_claims',
                'DIRECTORSOFFICERSLIABILITY'         => 'directors_officers_liability_claims',
                'MARINECARGOONCEOFF'                 => 'marine_cargo_once_off_claims',
                'MARINECARGOOPENCOVER'               => 'marine_cargo_open_cover_claims',
                'MEDICALMALPRACTICE'                 => 'medical_malpractice_claims',
                'GLASS'                              => 'glass_claim',
                'LOCKSANDKEYS'                       => 'key_loss_claim',
                // MIS Key Loss ships claim_type 'Key Loss' which normalises
                // to 'KEYLOSS'; DOM/COM ships the V8 code 'LOCKSANDKEYS'.
                // Register both so dispatch works regardless of source.
                'KEYLOSS'                            => 'key_loss_claim',
                // graphiteBWV8 main.blade.php groups all six "property"
                // aliases — BUILDINGSCOMBINED, ACCIDENTALDAMAGE, HOUSEHOLDERS,
                // HOUSEOWNERS, HOUSEOWNER-BUILDINGS, HOUSEHOLDERS-CONTENTS —
                // onto property_loss_damage.blade.php + the
                // property_loss_damage table. FE normalizes the hyphenated
                // codes via replace(/[^A-Z0-9]/g, '') so we register the
                // already-stripped forms here too.
                'PROPERTYLOSSDAMAGE'        => 'property_loss_damage',
                'BUILDINGSCOMBINED'         => 'property_loss_damage',
                'ACCIDENTALDAMAGE'          => 'property_loss_damage',
                'HOUSEHOLDERS'              => 'property_loss_damage',
                'HOUSEOWNERS'               => 'property_loss_damage',
                'HOUSEOWNERBUILDINGS'       => 'property_loss_damage',
                'HOUSEHOLDERSCONTENTS'      => 'property_loss_damage',
                // graphiteBWV8 main.blade.php groups LIABILITY and
                // PUBLICLIABILITY onto the same public_liability.blade.php
                // + public_liability table.
                'PUBLICLIABILITY'           => 'public_liability',
                'LIABILITY'                 => 'public_liability',
                'WORKERSCOMPENSATION'       => 'workers_compensation',
                'STATEDBENEFITS'            => 'workers_compensation',
                'DEFECTIVEWORKMANSHIP'      => 'defective_workmanship',
                // Burglary family — V8 main.blade.php lumps THEFT and MONEY
                // onto the burglary blade + table. FE normalizes "Burglary /
                // Theft" via replace(/[^A-Z0-9]/g, '') to BURGLARYTHEFT, so
                // the legacy 'BURGLARY/THEFT' slash key never matched.
                'THEFT'                     => 'burglary',
                'BURGLARY'                  => 'burglary',
                'BURGLARYTHEFT'             => 'burglary',
                'MONEY'                     => 'burglary',
                'FIRE'                      => 'fire',
                'PROPERTYDAMAGE'            => 'fire',
                'GOODSINTRANSIT'            => 'goods_in_transit_claim',
                'TRAVELINSURANCE'           => 'travel_insurance_claim',
                'PROFESSIONALINDEMNITY'     => 'professional_indemnity_claims',
                // Mobile/Electronic Devices family — V8 main.blade.php
                // groups MOBILEELECTRONICDEVICES and OFFICECONTENTS onto the
                // same mobileAndElectronicDevices.blade.php + the
                // mobile_and_electronic_devices_claim table. Earlier V2 used
                // the wrong table name (`mobile_and_electronic_devices`) and
                // the wrong claim_type key (`MOBILEANDELECTRONICDEVICES` —
                // FE normalizes "Mobile/Electronic Devices" without the
                // "AND" so the legacy key never matched).
                'MOBILEELECTRONICDEVICES'   => 'mobile_and_electronic_devices_claim',
                'OFFICECONTENTS'            => 'mobile_and_electronic_devices_claim',
                'MOBILEANDELECTRONICDEVICES'=> 'mobile_and_electronic_devices_claim',
            ];
            $subTable = $subTableMap[preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $claim->claim_type))] ?? null;
            if ($subTable && $this->schemaHasTable($subTable)) {
                try {
                    $subClaimData = DB::table($subTable)
                        ->where('newclaim_id', $newClaim->id)
                        ->orderByDesc('id')
                        ->first();

                    // GIT contract upload: surface a clickable CDN URL
                    // alongside the raw S3 path so the FE can render a
                    // "View Contract" link instead of the wall of S3 path
                    // text. Same pattern as document_1/2/3 + KYC docs +
                    // policy schedules — all of which serve fine via
                    // CloudFront once the underlying object is uploaded
                    // through `$file->storeAs(...)` (no public ACL).
                    if ($subTable === 'goods_in_transit_claim'
                        && $subClaimData
                        && !empty($subClaimData->copy_of_contract)
                    ) {
                        $subClaimData->copy_of_contract_url = $this->cdnUrl($subClaimData->copy_of_contract);
                    }
                    // Fire — V8 stores the security-agent contract under
                    // fire_claim.contract_of_agreement (longText, S3 path).
                    // Mirror GIT's CDN-URL stitch so the FE can render a
                    // clickable link in the View read mode.
                    if ($subTable === 'fire_claim'
                        && $subClaimData
                        && !empty($subClaimData->contract_of_agreement)
                    ) {
                        $subClaimData->contract_of_agreement_url = $this->cdnUrl($subClaimData->contract_of_agreement);
                    }
                    // Contractors All Risks / Public Liability — two S3
                    // upload columns (works_claim_documentary_evidence,
                    // works_claim_bill_of_quantities). Attach CDN URLs.
                    if ($subTable === 'contractors_all_risks_public_liability' && $subClaimData) {
                        if (!empty($subClaimData->works_claim_documentary_evidence)) {
                            $subClaimData->works_claim_documentary_evidence_url = $this->cdnUrl($subClaimData->works_claim_documentary_evidence);
                        }
                        if (!empty($subClaimData->works_claim_bill_of_quantities)) {
                            $subClaimData->works_claim_bill_of_quantities_url = $this->cdnUrl($subClaimData->works_claim_bill_of_quantities);
                        }
                    }
                    // Travel Insurance — V8 travel_insurance.blade.php uploads
                    // six compulsory documents plus up to four refund-type
                    // specific documents, all stored as S3 paths on the
                    // travel_insurance_claim table. Attach CDN URLs alongside
                    // so the FE can render clickable "View Document" links.
                    if ($subTable === 'travel_insurance_claim' && $subClaimData) {
                        foreach ([
                            'compulsory_doc_proof_of_residence',
                            'compulsory_doc_claim_form',
                            'compulsory_doc_insurance_policy',
                            'compulsory_doc_detailed_letter',
                            'compulsory_doc_receipts',
                            'compulsory_doc_passport_copy',
                            'medical_dental_care_doc_1',
                            'medical_dental_care_doc_2',
                            'medical_dental_care_doc_3',
                            'claim_delayed_luggage_doc_1',
                            'claim_delayed_luggage_doc_2',
                            'claim_delayed_luggage_doc_3',
                            'claim_loss_personal_doc_doc_1',
                            'claim_loss_personal_doc_doc_2',
                            'claim_lost_luggage_doc_1',
                            'claim_lost_luggage_doc_2',
                            'claim_lost_luggage_doc_3',
                            'claim_lost_luggage_doc_4',
                            'claim_trip_cancel_doc_1',
                            'claim_trip_cancel_doc_2',
                            'claim_trip_cancel_doc_3',
                            'claim_trip_cancel_doc_4',
                            'claim_delayed_flight_doc_1',
                            'claim_delayed_flight_doc_2',
                            'claim_delayed_flight_doc_3',
                        ] as $field) {
                            if (!empty($subClaimData->$field)) {
                                $subClaimData->{$field . '_url'} = $this->cdnUrl($subClaimData->$field);
                            }
                        }
                    }
                    // Professional Indemnity — two S3 upload columns
                    // (contract_copy, investigation_findings). Attach CDN
                    // URLs alongside so the FE can render clickable links.
                    if ($subTable === 'professional_indemnity_claims' && $subClaimData) {
                        foreach (['contract_copy', 'investigation_findings'] as $field) {
                            if (!empty($subClaimData->$field)) {
                                $subClaimData->{$field . '_url'} = $this->cdnUrl($subClaimData->$field);
                            }
                        }
                    }
                    // Medical Malpractice — five S3 upload columns from
                    // Section 4 (notification_letter, patient_records,
                    // investigation_reports, correspondence,
                    // expert_legal_opinions). Attach CDN URLs alongside
                    // so the FE can render clickable links.
                    if ($subTable === 'medical_malpractice_claims' && $subClaimData) {
                        foreach ([
                            'notification_letter', 'patient_records',
                            'investigation_reports', 'correspondence',
                            'expert_legal_opinions',
                        ] as $field) {
                            if (!empty($subClaimData->$field)) {
                                $subClaimData->{$field . '_url'} = $this->cdnUrl($subClaimData->$field);
                            }
                        }
                    }
                    // Locks & Keys — one S3 upload column (police_affidavit,
                    // V2 extension). Attach CDN URL alongside so the FE
                    // can render a clickable link.
                    if ($subTable === 'key_loss_claim' && $subClaimData) {
                        foreach (['police_affidavit', 'quote_1', 'quote_2'] as $field) {
                            if (!empty($subClaimData->$field)) {
                                $subClaimData->{$field . '_url'} = $this->cdnUrl($subClaimData->$field);
                            }
                        }
                    }
                    // Glass / Windscreen — V8 four damage photos plus V2's
                    // two replacement quote uploads. Attach CDN URLs.
                    if ($subTable === 'glass_claim' && $subClaimData) {
                        foreach ([
                            'incidentFront', 'incidentBack',
                            'incidentRight', 'incidentLeft',
                            'quote_1', 'quote_2',
                        ] as $field) {
                            if (!empty($subClaimData->$field)) {
                                $subClaimData->{$field . '_url'} = $this->cdnUrl($subClaimData->$field);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning("claim sub-table `{$subTable}` read failed for claim {$id}: " . $e->getMessage());
                }
            }
        }

        // Policy action term the claim was registered against. Mirrors
        // graphiteBWV8 ClaimsController::general — the detail header shows
        // WHICH version of the policy (NEWBUSINESS / ENDORSE / RENEW …) and
        // its effective window was live when the claim was filed. Null when
        // the claim predates policy_action_id capture (column is nullable).
        $policyAction = null;
        if ($claim->policy_action_id) {
            // withTrashed(): the claim was filed against this exact term, so the
            // header must still show it even after the term was soft-deleted
            // (default find() applies the SoftDeletes scope and returns null,
            // which is why a deleted term made the badge silently disappear).
            $pa = PolicyAction::withTrashed()->find($claim->policy_action_id);
            if ($pa) {
                $policyAction = [
                    'id'               => $pa->id,
                    'transaction_type' => $pa->transaction_type,
                    'status'           => $pa->status,
                    // effective_from/to are plain date columns (no model cast),
                    // so parse defensively and emit ISO so the FE can format.
                    'effective_from'   => $pa->effective_from ? Carbon::parse($pa->effective_from)->format('Y-m-d') : null,
                    'effective_to'     => $pa->effective_to   ? Carbon::parse($pa->effective_to)->format('Y-m-d')   : null,
                    // True when the term has been soft-deleted — the FE marks the
                    // badge "Deleted" so the operator knows the term no longer exists.
                    'deleted'          => $pa->trashed(),
                ];
            }
        }

        return response()->json([
            'data' => [
                'id'              => $claim->id,
                'claim_number'    => $claim->claim_number,
                'claim_type'      => $claim->claim_type,
                'status'          => $claim->status,
                'category'        => $claim->category,
                'registered_claim'=> $claim->registered_claim,
                'created_by'      => $claim->created_by,
                'created_by_name' => $createdByName,
                'closed_note'     => $claim->closed_note,
                'created_at'      => optional($claim->created_at)->toIso8601String(),
                'updated_at'      => optional($claim->updated_at)->toIso8601String(),

                // Allocation & status info (merges new_claims + claim_accidents)
                // claim_accidents has most fields; new_claims has others. Merge with fallbacks.
                'allocated_to'          => $allocatedTo ?? ($accidentDetails->claim_allocated_to ?? null),
                'allocated_on'          => (property_exists($claim, 'claim_allocated_on') ? $claim->claim_allocated_on : null)
                                            ?: ($newClaim->claims_allocated_on ?? null)
                                            ?: ($accidentDetails->claim_allocated_on ?? null),
                // Raw ids (for the Edit modal dropdown prefill)
                'claim_allocated_to'    => $allocatedToId,
                'claim_allocated_on'    => (property_exists($claim, 'claim_allocated_on') ? $claim->claim_allocated_on : null)
                                            ?: ($newClaim->claims_allocated_on ?? null)
                                            ?: ($accidentDetails->claim_allocated_on ?? null),
                'date_of_loss'          => $newClaim->date_of_loss ?? null,
                'location'              => $locationName ?? ($accidentDetails->location ?? ($newClaim->location_id ?? null)),
                'claim_sub_type'        => ($accidentDetails->claim_sub_type ?? null) ?: ($claimSubTypeName ?? ($newClaim->claim_sub_type_id ?? null)),
                'type_of_loss'          => ($newClaim->type_of_loss ?? null) ?: ($accidentDetails->loss_type ?? null),
                'claim_reported_by'     => ($newClaim->claim_reported_by ?? null) ?: ($accidentDetails->relation ?? null),
                'is_motor_claim'        => $newClaim->is_motor_claim ?? null,
                'vehicle_plate'         => $newClaim->vehicle_plate ?? null,
                // Use `??` (null-coalesce) instead of `?:` (Elvis) for
                // numeric/boolean columns where 0 is meaningful data.
                // Elvis treats 0 as falsy and falls through to the
                // accident-side fallback, which made "P 0.00" reserves
                // and "No" DFS Complaint disappear from the detail view.
                'paid_amount'           => $newClaim->paid_amount ?? ($accidentDetails->paid_amount ?? null),
                'reserve_amount'        => $newClaim->reserve_amount ?? ($accidentDetails->reserve_amount ?? null),
                'driver_as_insured'     => $newClaim->driver_as_insured ?? null,
                'attorney_involved'     => $newClaim->attorney_involved ?? ($accidentDetails->attorney_involved ?? null),
                'co_attorney_involved'  => $newClaim->co_attorney_involved ?? ($accidentDetails->co_attorney_assigned ?? null),
                // claims.claim_sub_status (set by updateStatus for 'Open' claims)
                // takes priority; falls through to the new_claims / claim_accidents
                // values for everything else (Elvis: empty/null falls through).
                'claim_sub_status'      => ($claim->claim_sub_status ?? null) ?: ($newClaim->claim_sub_status ?? null) ?: ($accidentDetails->claim_sub_status ?? null),
                'catastrophe_loss'      => $newClaim->catastrophe_loss ?? ($accidentDetails->catastrophe_loss ?? null),
                'event_name'            => ($newClaim->event_name ?? null) ?: ($accidentDetails->event_name ?? null),
                'date_first_visited'    => ($newClaim->date_first_visited ?? null) ?: ($accidentDetails->first_visit ?? null),
                'dfs_complaint'         => $newClaim->dfs_complaint ?? ($accidentDetails->dfs_complaint ?? null),
                'reportedByBrokerAgent' => $newClaim->reportedByBrokerAgent ?? null,
                'third_party_insured_elsewhere' => $newClaim->third_party_insured_elsewhere ?? null,
                'reason'                => ($newClaim->reason ?? null) ?: ($accidentDetails->reason ?? null),
                'description_of_loss'   => ($newClaim->description_of_loss ?? null) ?: ($accidentDetails->loss_description ?? $accidentDetails->detail_of_accident ?? null),
                'recovery_involved'     => $accidentDetails->recovery_involved ?? null,
                'fault_party'           => ($accidentDetails->fault_party ?? null) ?: ($accidentDetails->party_at_fault ?? null),
                'service_representative'=> $accidentDetails->representative ?? null,
                // DOM/COM new_claims columns surfaced for the Classification &
                // Allocation card on the detail page. The motor-side
                // service_representative above remains for accident details.
                'service_representative_id'    => $newClaim->service_representative_id ?? null,
                'primary_attorney_assigned_id' => $newClaim->primary_attorney_assigned_id ?? null,
                'p_a_assigned_date'            => $newClaim->p_a_assigned_date ?? null,
                'co_attorney_assigned_id'      => $newClaim->co_attorney_assigned_id ?? null,
                'c_a_assigned_date'            => $newClaim->c_a_assigned_date ?? null,
                'location_id'                  => $newClaim->location_id ?? null,
                'weather_condition'     => $accidentDetails->event_name ?? null, // in graphite, event_name holds weather for accidents

                // Accident details (motor claims)
                'accident_details' => $accidentDetails ? [
                    'place_of_accident'  => $accidentDetails->place_of_accident ?? null,
                    'time_of_accident'   => $accidentDetails->time_of_accident ?? null,
                    'date_of_accident'   => $accidentDetails->date_of_accident ?? null,
                    'detail_of_accident' => $accidentDetails->detail_of_accident ?? null,
                    'purpose_of_trip'    => $accidentDetails->purpose_of_trip ?? null,
                    'fault_party'        => $accidentDetails->fault_party ?? ($accidentDetails->party_at_fault ?? null),
                    'third_party'        => $accidentDetails->third_party ?? null,
                ] : null,

                // Summary amounts
                'total_reserve'    => round($totalReserve, 2),
                'total_payment'    => round($totalPayment, 2),
                'balance'          => round($totalReserve - $totalPayment, 2),

                'policy' => $claim->policy ? [
                    'id'            => $claim->policy->id,
                    'policy_number' => $claim->policy->policyNumber,
                    'product_id'    => $claim->policy->product_id ?? null,
                    'product_name'  => $claim->policy->product->name ?? null,
                    // form_template is the authoritative switch for which
                    // legacy blade to mirror: the product's claim-create
                    // flow decides which form-template to use
                    // (claimTypesByPolicy:702+), and the detail view must
                    // render matching sections. Without this, the FE
                    // can't tell the difference between a Life claim
                    // (should show death cause/certificate) and a Motor
                    // Accident claim (should show driver/passengers/
                    // third parties).
                    'form_template' => $this->resolveFormTemplate($claim->policy->product_id ?? null),
                    'status'        => $claim->policy->status,
                    'premium'       => $claim->policy->premium,
                    'agent_name'    => $agentName,
                ] : null,
                // Policy action term the claim was registered against — drives
                // the "Action Term: …" header badge (mirrors graphiteBWV8).
                'policy_action' => $policyAction,
                'customer' => $claim->customer ? [
                    'id'           => $claim->customer->id,
                    'name'         => $companyName ?: trim($claim->customer->firstName . ' ' . $claim->customer->lastName),
                    'company_name' => $companyName,
                    'is_company'   => !empty($companyName),
                    'individual_name' => trim($claim->customer->firstName . ' ' . $claim->customer->lastName),
                    'email'        => $claim->customer->email,
                    'cellphone'    => $claim->customer->cellphone,
                ] : null,

                // Documents (legacy 3-slot)
                'documents' => array_values(array_filter([
                    $claim->document_1 ? ['label' => 'Document 1', 'url' => $this->cdnUrl($claim->document_1)] : null,
                    $claim->document_2 ? ['label' => 'Document 2', 'url' => $this->cdnUrl($claim->document_2)] : null,
                    $claim->document_3 ? ['label' => 'Document 3', 'url' => $this->cdnUrl($claim->document_3)] : null,
                ])),

                // Full sub-resources
                'attachments'  => $attachments->values(),
                // Reserves table — mirrors graphiteBWV8 coverageData() which
                // emits ONE ROW PER claim_reserves_coverages entry (joined to
                // its claim_reserves header for trans-type / payee / date),
                // not per-header. This matters because voiding a payment
                // inserts a NEW coverage row with is_payment_voided=2 alongside
                // flagging the original is_payment_voided=1 — they must show
                // as two separate lines in the table (yellow original + " - VOID"
                // reversal), not collapse into one.
                'reserves'     => (function () use ($reserveCoverages, $reserves, $txTypeLabels, $subTypeLabels, $supplierNames, $userNames, $insertedUserNames) {
                    $headersByReserveId = $reserves->keyBy('id');

                    return $reserveCoverages->sortBy('id')->map(function ($crc) use ($headersByReserveId, $txTypeLabels, $subTypeLabels, $supplierNames, $userNames, $insertedUserNames) {
                        $header = $headersByReserveId->get($crc->reserve_id);
                        if (!$header) return null;
                        $h  = (array) $header;
                        $rc = (array) $crc;
                        $txType     = (int) ($h['transaction_type'] ?? 0);
                        $voidedFlag = (int) ($rc['is_payment_voided'] ?? 0);

                        // graphiteBWV8 coverageData() (lines 1733-1790): each
                        // transaction_type maps to a different amount column.
                        // Mirror that matrix here so voided originals show
                        // their (-) amount and reversals show their (+) only.
                        if ($txType === 90)      { $plus = (float) ($rc['subrogation_payment'] ?? 0); }
                        elseif ($txType === 92)  { $plus = (float) ($rc['salvage_payment'] ?? 0); }
                        else                     { $plus = (float) ($rc['reserve_amt'] ?? 0); }

                        if ($txType === 89)      { $minus = (float) ($rc['subrogation_reserve'] ?? 0); }
                        elseif ($txType === 91)  { $minus = (float) ($rc['salvage_reserve'] ?? 0); }
                        else                     { $minus = (float) ($rc['payment_amt'] ?? 0); }

                        $insertedBy = $h['user_id'] ?? $h['created_by'] ?? null;
                        $payeeId    = $h['payee'] ?? null;

                        return [
                            // The displayed row's id IS the claim_reserves_coverages.id —
                            // the same id the void endpoint targets, so the FE can pass
                            // it back without indirection.
                            'id'                     => (int) $rc['id'],
                            'reserveId'              => (int) $h['id'],
                            'date'                   => $h['date'] ?? null,
                            'transactionType'        => $txType,
                            // No " - VOID" suffix here — the FE adds it from
                            // isVoided so we don't get a double-suffix when both
                            // sides decorate the label.
                            'transactionTypeName'    => $txTypeLabels[$txType] ?? ('Type ' . $txType),
                            'transactionSubType'     => $h['transaction_sub_type'] ?? null,
                            'transactionSubTypeName' => isset($h['transaction_sub_type']) ? ($subTypeLabels[$h['transaction_sub_type']] ?? null) : null,
                            'payee'                  => $payeeId,
                            // V8 payee semantics: transaction_type 86
                            // (Initial Reserves / "Claim Allocated") stores a
                            // users.id; every other type stores a suppliers.id
                            // — both in the same `payee` column. Resolve
                            // users-first for type 86 so a users.id that
                            // collides with an unrelated suppliers.id (e.g.
                            // user 286 = the claim handler vs supplier 286 =
                            // an insurer) doesn't surface the supplier name.
                            'payeeName'              => $payeeId
                                ? ($txType === 86
                                    ? ($userNames[$payeeId] ?? $supplierNames[$payeeId] ?? 'ID ' . $payeeId)
                                    : ($supplierNames[$payeeId] ?? $userNames[$payeeId] ?? 'ID ' . $payeeId))
                                : null,
                            'address'                => $h['address'] ?? null,
                            'invoiceNo'              => $h['invoice_no'] ?? null,
                            'invoiceDate'            => $h['invoice_date'] ?? null,
                            'invoiceDueDate'         => $h['invoice_due_date'] ?? null,
                            'memo'                   => $h['memo'] ?? null,
                            'description'            => $h['description'] ?? null,
                            'creditNote'             => $h['credit_note'] ?? null,
                            'includeVat'             => $h['include_vat'] ?? null,
                            'createdAt'              => $h['created_at'] ?? null,
                            'insertedBy'             => $insertedBy,
                            // Same type-86 = users.id rule as payeeName above:
                            // for the auto-seeded "Claim Allocated" row the
                            // payee is the user who registered the claim, so
                            // resolve users-first to avoid the suppliers.id
                            // collision.
                            'insertedByName'         => $insertedBy
                                ? ($insertedUserNames[$insertedBy] ?? null)
                                : ($payeeId
                                    ? ($txType === 86
                                        ? ($userNames[$payeeId] ?? $supplierNames[$payeeId] ?? null)
                                        : ($supplierNames[$payeeId] ?? $userNames[$payeeId] ?? null))
                                    : null),
                            'plusAmount'             => round($plus, 2),
                            'minusAmount'            => round($minus, 2),
                            // V8 stores the running balance per coverage row at
                            // write-time (storeReserve / voidPayment both set
                            // claim_reserves_coverages.balance), so just surface
                            // the stored value rather than recomputing.
                            'runningBalance'         => round((float) ($rc['balance'] ?? 0), 2),
                            // Three flags for the FE: isVoided drives the
                            // " - VOID" suffix on the reversal entry; wasVoided
                            // paints the original yellow; canVoid gates the
                            // Void Payment button.
                            'isVoided'               => $voidedFlag === 2,
                            'wasVoided'              => $voidedFlag === 1,
                            'canVoid'                => in_array($txType, [43, 90, 92], true) && $voidedFlag === 0,
                            'voidCrcId'              => (int) $rc['id'],
                            // Each displayed row IS a single coverage entry, so
                            // the write-off flag is read straight off this row.
                            // Drives the row-level WRITE-OFF badge.
                            'hasWriteOff'            => (int) ($rc['write_off'] ?? 0) === 1,
                            // Empty so the FE doesn't try to render a nested
                            // coverage breakdown table — each displayed row IS
                            // a single coverage entry now.
                            'coverages'              => [],
                        ];
                    })->filter()->values();
                })(),
                'assessment'   => $assessment ? [
                    'id' => $assessment->id, 'assessorName' => $assessorName,
                    'assessmentReport' => $assessment->assessment_report ? $this->cdnUrl($assessment->assessment_report) : null,
                    'quotationsParts' => $assessment->quotations_parts ? $this->cdnUrl($assessment->quotations_parts) : null,
                    'valuation' => $assessment->valuation, 'notes' => $assessment->notes,
                ] : null,
                // MotoLink (Scans.ai) assessment mirrored onto the claim by the
                // push endpoint (POST /api/claims-tracker/assessment). Null until
                // the first assessment syncs — keyed on motolink_assessment_id so
                // an un-synced claim shows nothing rather than an empty card.
                'motolink'     => $claim->motolink_assessment_id ? [
                    'assessment_id'   => $claim->motolink_assessment_id,
                    'status'          => $claim->motolink_status,
                    'final_cost'      => $claim->motolink_final_cost,
                    'make'            => $claim->motolink_make,
                    'model'           => $claim->motolink_model,
                    'registration'    => $claim->motolink_registration,
                    'vin'             => $claim->motolink_vin,
                    'total_loss'      => (bool) $claim->motolink_total_loss,
                    'write_off_alert' => (bool) $claim->motolink_write_off_alert,
                    'synced_at'       => $claim->motolink_synced_at ? Carbon::parse($claim->motolink_synced_at)->toIso8601String() : null,
                ] : null,
                'quotes'       => $quotes->values(),
                'thirdParties' => $thirdParties->values(),
                'vehicleDetails' => $vehicleDetails,
                // MIS claim-type specific payloads (life / legal / hospital_cash).
                // Non-null only when the corresponding form_template was used
                // at creation. FE renders type-specific cards based on these.
                'lifeDetails'         => $lifeDetails,
                'legalDetails'        => $legalDetails,
                'unionLegalClaim'     => $unionLegalClaim,
                'unionPaymentStatus'  => $unionPaymentStatus,
                'hospitalCashDetails' => $hospitalCashDetails,
                // Motor sub-tables for accident claims.
                'accidentDriver'      => $accidentDriver,
                'accidentPassengers'  => $accidentPassengers,
                // DOMG/COMG claim-type-specific payload (business_interruption,
                // burglary, fidelity_guarantee, property_loss_damage, etc.).
                // NULL for unknown claim types / when no row exists.
                'subClaimData' => $subClaimData,
                'activityLog'  => $activityLog->map(fn($a) => [
                    'id' => $a['id'], 'description' => $a['description'],
                    'userName' => $causerNames[$a['causerId']] ?? null, 'createdAt' => $a['createdAt'],
                ])->values(),
                'complaints'   => $complaints->values(),
            ],
        ]);
    }

    /**
     * Lookup data for claim creation form.
     */
    public function createData(): JsonResponse
    {
        return response()->json([
            'data' => [
                'claim_types' => [
                    ['id' => 'Motor', 'name' => 'Motor', 'code' => 'MOTORACCIDENT'],
                    ['id' => 'Motor Traders External', 'name' => 'Motor Traders External', 'code' => 'MOTORTRADERSEXTERNAL'],
                    ['id' => 'Motor Traders Internal', 'name' => 'Motor Traders Internal', 'code' => 'MOTORTRADERSINTERNAL'],
                    ['id' => 'Glass', 'name' => 'Glass', 'code' => 'GLASS'],
                    ['id' => 'Key Loss', 'name' => 'Key Loss', 'code' => 'LOCKSANDKEYS'],
                    ['id' => 'Fire', 'name' => 'Fire', 'code' => 'FIRE'],
                    ['id' => 'Burglary', 'name' => 'Burglary/Theft', 'code' => 'THEFT'],
                    ['id' => 'Money', 'name' => 'Money', 'code' => 'MONEY'],
                    ['id' => 'Accidental Death', 'name' => 'Accidental Death', 'code' => 'LIFE'],
                    ['id' => 'Workers Compensation', 'name' => 'Workers Compensation', 'code' => 'WORKERSCOMPENSATION'],
                    ['id' => 'Stated Benefits', 'name' => 'Stated Benefits', 'code' => 'STATEDBENEFITS'],
                    ['id' => 'Defective Workmanship', 'name' => 'Defective Workmanship', 'code' => 'DEFECTIVEWORKMANSHIP'],
                    ['id' => 'Business All Risks', 'name' => 'Business All Risks', 'code' => 'BUSINESSALLRISKS'],
                    ['id' => 'Personal All Risks', 'name' => 'Personal All Risks', 'code' => 'PERSONALALLRISKS'],
                    ['id' => 'Electronic Equipment', 'name' => 'Electronic Equipment', 'code' => 'ELECTRONICEQUIPMENT'],
                    ['id' => 'Property Damage', 'name' => 'Property Damage', 'code' => 'PROPERTYDAMAGE'],
                    ['id' => 'Accidental Damage', 'name' => 'Accidental Damage', 'code' => 'ACCIDENTALDAMAGE'],
                    ['id' => 'Buildings Combined', 'name' => 'Buildings Combined', 'code' => 'BUILDINGSCOMBINED'],
                    ['id' => 'Householders', 'name' => 'Householders', 'code' => 'HOUSEHOLDERS'],
                    ['id' => 'Houseowners', 'name' => 'Houseowners', 'code' => 'HOUSEOWNERS'],
                    ['id' => 'Houseowner Buildings', 'name' => 'Houseowner Buildings', 'code' => 'HOUSEOWNERBUILDINGS'],
                    ['id' => 'Householders Contents', 'name' => 'Householders Contents', 'code' => 'HOUSEHOLDERSCONTENTS'],
                    ['id' => 'Contractors All Risks', 'name' => 'Contractors All Risks', 'code' => 'CONTRACTORSALLRISKS'],
                    ['id' => 'Erection All Risk', 'name' => 'Erection All Risk', 'code' => 'ERECTIONALLRISK'],
                    ['id' => 'Plant All Risks', 'name' => 'Plant All Risks', 'code' => 'PLANTALLRISKS'],
                    ['id' => 'Machinery Breakdown', 'name' => 'Machinery Breakdown', 'code' => 'MACHINERYBREAKDOWN'],
                    ['id' => 'Machinery Breakdown Loss Of Profit', 'name' => 'Machinery Breakdown Loss Of Profit', 'code' => 'MACHINERYBREAKDOWNLOSSOFPROFIT'],
                    ['id' => 'Directors Officers Liability', 'name' => 'Directors & Officers Liability', 'code' => 'DIRECTORSOFFICERSLIABILITY'],
                    ['id' => 'Marine Cargo Once Off', 'name' => 'Marine Cargo Once-Off (Single Voyage)', 'code' => 'MARINECARGOONCEOFF'],
                    ['id' => 'Marine Cargo Open Cover', 'name' => 'Marine Cargo Open Cover', 'code' => 'MARINECARGOOPENCOVER'],
                    ['id' => 'Liability', 'name' => 'Liability', 'code' => 'LIABILITY'],
                    ['id' => 'Office Contents', 'name' => 'Office Contents', 'code' => 'OFFICECONTENTS'],
                    ['id' => 'Business Interruption', 'name' => 'Business Interruption', 'code' => 'BUSINESSINTERRUPTION'],
                    ['id' => 'Mobile Electronic Devices', 'name' => 'Mobile/Electronic Devices', 'code' => 'MOBILEELECTRONICDEVICES'],
                    ['id' => 'Goods In Transit', 'name' => 'Goods In Transit', 'code' => 'GOODSINTRANSIT'],
                    ['id' => 'Fidelity Guarantee', 'name' => 'Fidelity Guarantee', 'code' => 'FIDELITYGUARANTEE'],
                    ['id' => 'Travel Insurance', 'name' => 'Travel Insurance', 'code' => 'TRAVELINSURANCE'],
                    ['id' => 'Professional Indemnity', 'name' => 'Professional Indemnity', 'code' => 'PROFESSIONALINDEMNITY'],
                    ['id' => 'Medical Malpractice', 'name' => 'Medical Malpractice', 'code' => 'MEDICALMALPRACTICE'],
                    ['id' => 'Legal', 'name' => 'Legal', 'code' => 'LEGAL'],
                    ['id' => 'Hospital CashBack', 'name' => 'Hospital Cashback', 'code' => 'HOSPITALCASHBACK'],
                ],
                // Event names, loss types, reported-by come from the
                // Lookup table. On envs where those keys aren't seeded
                // (new test envs, freshly-migrated DBs) the dropdowns
                // render blank and block claim submission. Fall back to
                // hardcoded legacy defaults so the form stays usable —
                // if/when Lookup gets seeded, the DB rows win.
                'event_names' => (function () {
                    $rows = Lookup::where('key', 'motor_claim_event')
                        ->select('id', 'value as name')->get();
                    if ($rows->isNotEmpty()) return $rows;
                    return collect([
                        ['id' => 'Clear',    'name' => 'Clear'],
                        ['id' => 'Rainy',    'name' => 'Rainy'],
                        ['id' => 'Windy',    'name' => 'Windy'],
                        ['id' => 'Hailstorm','name' => 'Hailstorm'],
                        ['id' => 'Flood',    'name' => 'Flood'],
                        ['id' => 'Fire',     'name' => 'Fire'],
                        ['id' => 'Theft',    'name' => 'Theft'],
                        ['id' => 'Collision','name' => 'Collision'],
                        ['id' => 'Other',    'name' => 'Other'],
                    ]);
                })(),
                'reported_by' => (function () {
                    $rows = Lookup::where('key', 'claim_reported_by')
                        ->select('id', 'value as name')->get();
                    if ($rows->isNotEmpty()) return $rows;
                    return collect([
                        ['id' => 'Insured',        'name' => 'Insured'],
                        ['id' => 'Co-Insured',     'name' => 'Co-Insured'],
                        ['id' => 'Public Adjuster','name' => 'Public Adjuster'],
                        ['id' => 'Agent',          'name' => 'Agent'],
                        ['id' => 'Other',          'name' => 'Other'],
                    ]);
                })(),
                // ── Reported by Broker/Agent dropdown (DOM/COM Classification block) ──
                // Mirrors graphiteBWV8 NewClaimController:241-261: union of active
                // users (typed "Agent") and rows from the `agencies` table (typed
                // "Agency"). Display label = "{name} ({type})". Backend persists the
                // chosen id to new_claims.reportedByBrokerAgent.
                'agents_options' => (function () {
                    $out = collect();
                    if (\Schema::hasTable('users')) {
                        $cols = \Schema::getColumnListing('users');
                        $q = DB::table('users')
                            ->when(in_array('active', $cols, true), fn($q) => $q->where('active', 1))
                            ->when(in_array('deleted_at', $cols, true), fn($q) => $q->whereNull('deleted_at'));
                        $users = $q->select('id', 'firstName', 'lastName')->get();
                        foreach ($users as $u) {
                            $name = trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? ''));
                            if ($name === '') continue;
                            $out->push(['id' => (string) $u->id, 'name' => $name . ' (Agent)', 'type' => 'Agent']);
                        }
                    }
                    if (\Schema::hasTable('agencies')) {
                        $cols = \Schema::getColumnListing('agencies');
                        $q = DB::table('agencies')
                            ->when(in_array('deleted_at', $cols, true), fn($q) => $q->whereNull('deleted_at'));
                        $agencies = $q->select('id', 'name')->get();
                        foreach ($agencies as $a) {
                            if (!$a->name) continue;
                            $out->push(['id' => (string) $a->id, 'name' => $a->name . ' (Agency)', 'type' => 'Agency']);
                        }
                    }
                    return $out->values();
                })(),
                'loss_types' => (function () {
                    $rows = Lookup::where('key', 'motor_claim_loss_type')
                        ->select('id', 'value as name')->get();
                    if ($rows->isNotEmpty()) return $rows;
                    return collect([
                        ['id' => 'Property',  'name' => 'Property'],
                        ['id' => 'Liability', 'name' => 'Liability'],
                        ['id' => 'Both',      'name' => 'Both'],
                    ]);
                })(),
                // ── DOM/COM-only Type of Loss ──
                // Legacy V8 main.blade.php hardcodes only two options
                // (Property / Liability) for DOMG/COMG claim forms,
                // separate from the motor `loss_types` list. Matched
                // verbatim — IDs 1 and 2 mirror V8's <option value=> attrs.
                'loss_types_dom_com' => collect([
                    ['id' => '1', 'name' => 'Property'],
                    ['id' => '2', 'name' => 'Liability'],
                ]),
                // ── Attorney dropdowns (DOM/COM Classification block) ──
                // V8 main.blade.php hardcodes these lists directly in the
                // blade — no DB source. Mirroring exactly so old and new
                // claim records share the same primary_attorney_assigned_id
                // / co_attorney_assigned_id values.
                'attorneys_primary' => collect([
                    ['id' => '8413',  'name' => 'AKHEEL JINABHAI & ASSOCIATES'],
                    ['id' => '4468',  'name' => 'KELOBANG GODISANG ATTORNEYS'],
                    ['id' => '6778',  'name' => 'DESAI LAW GROUP'],
                    ['id' => '15249', 'name' => 'Legal Freedom Insurance Services (Pty) Ltd'],
                    ['id' => '38438', 'name' => 'SALBANY & TORTO ATTORNEYS'],
                    ['id' => '38528', 'name' => 'LAURENCE KHUPE ATTORNEYS'],
                    ['id' => '38529', 'name' => 'MINCHIN & KELLY BOTSWANA'],
                    ['id' => '42348', 'name' => 'COLLECTION AFRICA'],
                    ['id' => '43219', 'name' => 'WOODWARD LEGAL SERVICES'],
                    ['id' => '43281', 'name' => 'RAMALEPA ATTORNEY, NOTARIES & CONVEYANCERS'],
                    ['id' => '47318', 'name' => 'TSHEPHE LEGAL FIRM'],
                    ['id' => '47830', 'name' => 'KOLE LAW PRACTICE'],
                    ['id' => '47949', 'name' => 'JEREMIAH TLADI & CO.'],
                ]),
                'attorneys_co' => collect([
                    ['id' => '15249', 'name' => 'Legal Freedom Insurance Services (Pty) Ltd'],
                    ['id' => '39989', 'name' => 'LAERENCE KHUPE ATTORNEYS'],
                    ['id' => '43220', 'name' => 'WOODWARD LEGAL SERVICES'],
                    ['id' => '43281', 'name' => 'RAMALEPA ATTORNEY, NOTARIES & CONVEYANCERS'],
                    ['id' => '47318', 'name' => 'TSHEPHE LEGAL FIRM'],
                ]),
                'statuses' => [
                    ['id' => 'New', 'name' => 'New'],
                    ['id' => 'Pending Assessment', 'name' => 'Pending Assessment'],
                    ['id' => 'Approved', 'name' => 'Approved'],
                    ['id' => 'Rejected', 'name' => 'Rejected'],
                    ['id' => 'Closed', 'name' => 'Closed'],
                ],
                // Legacy model AlphaDirect\ClaimSubType points at the
                // `claim_subtype` table (singular). Column is `sub_type`
                // (not `name`/`value`). Legacy callers do things like:
                //   ClaimSubType::get(['sub_type'])
                //   ClaimSubType::where('claim_type','Motor accident')->get(['sub_type'])
                // A previous version of this endpoint probed for `name`
                // and `value` columns, found neither, and silently
                // returned garbage. Fixed to read `sub_type` directly.
                // claim_type is also included so the FE can filter the
                // list by the currently-selected claim_type when needed.
                // Legacy graphiteBWV8 uses TWO sources for claim sub-types,
                // one per claim-type category:
                //   1. Motor Accident → claim_subtype table (17 rows, all
                //      claim_type='Motor Accident', sub_type column)
                //      NewClaimController:288 — ClaimSubType::get(['sub_type'])
                //   2. Every other claim type → lookup table keyed by the
                //      claim_type string. NewClaimController:236 —
                //      Lookup::where('key', $claimType)->get()
                //      e.g. key='WORKMENCOMPENSATION' → Injury Claim rows,
                //           key='BURGLARY' → Burglary-specific rows.
                // V2 previously only read from claim_subtype, so any claim
                // type other than Motor Accident showed an empty dropdown.
                // This union surfaces both sources with `claim_type` on each
                // row so the FE's filteredSubTypes helper matches.
                'claim_sub_types' => (function () {
                    $rows = collect();

                    if (\Schema::hasTable('claim_subtype')) {
                        $cols = \Schema::getColumnListing('claim_subtype');
                        $nameCol = in_array('sub_type', $cols, true) ? 'sub_type'
                            : (in_array('name', $cols, true) ? 'name'
                            : (in_array('value', $cols, true) ? 'value' : null));
                        if ($nameCol) {
                            $q = DB::table('claim_subtype')
                                ->when(in_array('deleted_at', $cols, true), fn($q) => $q->whereNull('deleted_at'));
                            $selectCols = ['id', "{$nameCol} as name"];
                            if (in_array('claim_type', $cols, true)) $selectCols[] = 'claim_type';
                            $rows = $q->orderBy($nameCol)->get($selectCols);
                        }
                    }

                    // Fold lookup rows keyed by any claim_type string into the
                    // same collection. Pull claim_type keys from the coverage
                    // claim map (dom_com_coverage_claims.claim_name), since
                    // those are the strings the create form will offer.
                    // V2's Lookup model points at `lookup_data`, not `lookup`
                    // (V8 used `lookup`); guarding on the wrong table name
                    // silently skipped this whole branch on V2 envs and left
                    // the Sub Type dropdown empty for every non-Motor type.
                    if (\Schema::hasTable('lookup_data')) {
                        $claimTypeKeys = \Schema::hasTable('dom_com_coverage_claims')
                            ? DB::table('dom_com_coverage_claims')
                                ->whereNotNull('claim_name')->where('claim_name', '!=', '')
                                ->distinct()->pluck('claim_name')->map(fn($n) => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $n)))->unique()->values()->all()
                            : [];
                        // Also include the raw claim_name strings — some legacy
                        // seeds use them as lookup.key verbatim ("Motor accident")
                        // instead of the normalised form.
                        if (\Schema::hasTable('dom_com_coverage_claims')) {
                            foreach (DB::table('dom_com_coverage_claims')->whereNotNull('claim_name')->pluck('claim_name')->unique() as $n) {
                                $claimTypeKeys[] = $n;
                            }
                        }
                        $claimTypeKeys = array_values(array_unique($claimTypeKeys));

                        if (!empty($claimTypeKeys)) {
                            $lookupRows = Lookup::whereIn('key', $claimTypeKeys)
                                ->select('id', 'value as name', 'key as claim_type')
                                ->orderBy('value')->get();
                            $rows = $rows->concat($lookupRows);
                        }
                    }

                    return $rows->values();
                })(),
                // Legacy reserve form -> Trans Sub Type dropdown
                // (lookup_data key='transaction_sub_type'). Same empty-
                // Lookup fallback: reserve Add form was unusable because
                // the dependent sub-type dropdown stayed empty after
                // picking a transaction type.
                'transaction_sub_types' => (function () {
                    $rows = Lookup::where('key', 'transaction_sub_type')
                        ->select('id', 'value as name')->orderBy('value')->get();
                    if ($rows->isNotEmpty()) return $rows;
                    return collect([
                        ['id' => 'Initial Reserve',     'name' => 'Initial Reserve'],
                        ['id' => 'Supplementary',      'name' => 'Supplementary Reserve'],
                        ['id' => 'Final Reserve',      'name' => 'Final Reserve'],
                        ['id' => 'Repair Payment',     'name' => 'Payment — Repair'],
                        ['id' => 'Cash In Lieu',       'name' => 'Payment — Cash In Lieu'],
                        ['id' => 'Salvage Payment',    'name' => 'Payment — Salvage'],
                        ['id' => 'Subrogation',        'name' => 'Subrogation'],
                        ['id' => 'Credit Note',        'name' => 'Credit Note'],
                        ['id' => 'Advance Payment',    'name' => 'Advance Payment'],
                    ]);
                })(),
                // Internal users — used by the "Claim Allocated To" dropdown on
                // Create / Edit. Legacy uses User::get(['id','firstName','lastName'])
                // with NO active filter — mirror that so the list is never empty
                // just because no row has active=1. We do best-effort filtering:
                // if an `active` column exists and has ANY rows with active=1,
                // prefer those; otherwise fall back to all users. Soft-delete
                // aware.
                'internal_users' => (function () {
                    if (!\Schema::hasTable('users')) return collect();
                    $cols = \Schema::getColumnListing('users');
                    $q = DB::table('users')
                        ->when(in_array('deleted_at', $cols, true), fn($q) => $q->whereNull('deleted_at'));
                    // Try active=1 first, but if empty fall back to all
                    $rows = collect();
                    if (in_array('active', $cols, true)) {
                        $rows = (clone $q)->where('active', 1)
                            ->orderBy('firstName')->orderBy('lastName')
                            ->get(['id', 'firstName', 'lastName', 'email']);
                    }
                    if ($rows->isEmpty()) {
                        $rows = $q->orderBy('firstName')->orderBy('lastName')
                            ->get(['id', 'firstName', 'lastName', 'email']);
                    }
                    return $rows->map(fn($u) => [
                        'id'    => $u->id,
                        'name'  => trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')),
                        'email' => $u->email ?? null,
                    ])->values();
                })(),
                // Master list of Cause-of-Death options for Life / ADI claims
                // (legacy: lookup_data where key='cause_of_death', currently
                //  id=19 Accident, id=20 Natural calamity). Fallback mirrors
                //  those two legacy rows so Life claim form stays usable on
                //  envs with an unseeded lookup_data table.
                'cause_of_death_options' => (function () {
                    $rows = Lookup::where('key', 'cause_of_death')
                        ->select('id', 'value as name')->orderBy('id')->get();
                    if ($rows->isNotEmpty()) return $rows;
                    return collect([
                        ['id' => 'Accident',         'name' => 'Accident'],
                        ['id' => 'Natural calamity', 'name' => 'Natural calamity'],
                        ['id' => 'Illness',          'name' => 'Illness'],
                        ['id' => 'Other',            'name' => 'Other'],
                    ]);
                })(),
                'weather_conditions' => [
                    ['id' => 'Clear', 'name' => 'Clear'], ['id' => 'Rainy', 'name' => 'Rainy'],
                    ['id' => 'Foggy', 'name' => 'Foggy'], ['id' => 'Windy', 'name' => 'Windy'],
                    ['id' => 'Other', 'name' => 'Other'],
                ],
                // Legacy graphiteBWV8 uses Owner / Other Party — matches the
                // accident blade at newClaims/types/accident.blade.php where
                // fault_party is a radio pair with exactly those two values.
                // claim_details.blade.php reads the column back as "Owner"
                // or "Other Party" too, so the stored values must match.
                'fault_parties' => [
                    ['id' => 'Owner', 'name' => 'Owner'],
                    ['id' => 'Other Party', 'name' => 'Other Party'],
                ],
            ],
        ]);
    }

    /**
     * GET /claims/policy/{policyId}/claim-types — returns claim types based on product.
     *
     * MIS (product 3): fixed motor claim types (Accident, Glass, Key Loss)
     * DOMG/COMG (products 7, 8): claim types from dom_com_coverage_claims by coverage names
     * Other products: all claim types
     */
    public function claimTypesByPolicy(Request $request): JsonResponse
    {
        $policyId = $request->input('policy');

        if (!$policyId) {
            return response()->json(['message' => 'Policy ID or number is required.'], 422);
        }

        // Accept numeric ID or alphanumeric policyNumber
        if (is_numeric($policyId)) {
            $policy = Policy::select('id', 'product_id', 'policyNumber', 'status')->find($policyId);
        } else {
            $policy = Policy::select('id', 'product_id', 'policyNumber', 'status')
                ->where('policyNumber', $policyId)->first();
        }

        if (!$policy) {
            return response()->json(['message' => 'Policy not found.'], 404);
        }

        $productId = $policy->product_id;
        $resolvedPolicyId = $policy->id;

        // DOMG/COMG products (7, 8, 16-19) follow graphiteBWV8's NewClaimController::prosess
        // behaviour — claims can be filed against a cancelled POLICY because
        // historical terms on a now-cancelled policy still need claim cover.
        // Per-term cancel actions are filtered out separately (via the
        // transaction_type LIKE '%cancel%' filter on policy_actions below)
        // so the operator can't pick a cancel term — only the underlying
        // policy-level status check is relaxed.
        $isDomComClaim = in_array($productId, [7, 8, 16, 17, 18, 19, 20, 22, 23, 24], true);

        if ($policy->status != 1 && !$isDomComClaim) {
            $statusLabel = match((int) $policy->status) { 0 => 'not activated', 2 => 'cancelled', 3 => 'expired', default => 'not active' };
            return response()->json(['message' => "Claims can only be filed against active policies. This policy is {$statusLabel}."], 422);
        }

        // Per-product claim type maps — mirrors graphiteBWV8 processClaim()
        // where each product's blade template only accepts specific claim types.
        //
        // product_id → (label, allowed claim types, form template)
        //   1 = Accidental Death Insurance (ADI / Life)
        //   2 = Third Party Car Insurance  (motor — TP only)
        //   3 = Motor Comprehensive        (motor — full set)
        //   4 = Legal Insurance            (legal only)
        //   5 = Mobile/Cellphone Insurance (device claim only)
        //   6 = Tyre & Rim Insurance       (tyre claim only)
        //   7/8 = COMG/DOMG                (coverage-based — handled below)
        //  16/17/18/19 = Specialist        (coverage-based — handled below)
        switch ($productId) {
            case 1: // Accidental Death Insurance
                return response()->json(['data' => [
                    'product_type' => 'ADI',
                    'product_id'   => $productId,
                    'form_template' => 'life',
                    'claim_types'  => [
                        // Renamed from 'Life' -> 'Accidental Death' for
                        // product 1 (ADI) only. New ADI claims now store
                        // claim_type = 'Accidental Death' in
                        // claims/new_claims. Existing rows with
                        // claim_type = 'Life' are left intact so the
                        // legacy dispatch (blade views, FrontendPay
                        // ClaimController, admin reports — all branching
                        // on claim_type == 'Life') keeps working for
                        // them. The `code` stays 'LIFE' so V2 logic
                        // keyed on the normalised code (preg_replace
                        // [^A-Z0-9] -> upper) resolves the same value
                        // for both labels.
                        ['id' => 'Accidental Death', 'name' => 'Accidental Death', 'code' => 'LIFE'],
                    ],
                    'actions' => null,
                ]]);

            case 2: // Third Party Car Insurance
                return response()->json(['data' => [
                    'product_type' => 'TP',
                    'product_id'   => $productId,
                    'form_template' => 'vehicle',
                    'claim_types'  => [
                        // Stored claim_type is now 'Motor' (was 'Accident').
                        // `code` stays MOTORACCIDENT so the normalised-code
                        // dispatch (sub-table writes, detail view) is unchanged.
                        ['id' => 'Motor', 'name' => 'Motor', 'code' => 'MOTORACCIDENT'],
                        // Key Loss is now offered for Third Party too. `id`
                        // 'Key Loss' is stored verbatim on claims.claim_type
                        // (a valid enum member); `code` LOCKSANDKEYS drives the
                        // key_loss_claim sub-table dispatch.
                        ['id' => 'Key Loss', 'name' => 'Locks & Keys', 'code' => 'LOCKSANDKEYS'],
                    ],
                    'actions' => null,
                ]]);

            case 3: // Motor Comprehensive
                return response()->json(['data' => [
                    'product_type' => 'MotorComp',
                    'product_id'   => $productId,
                    'form_template' => 'vehicle',
                    'claim_types'  => [
                        // Stored claim_type is now 'Motor' (was 'Accident').
                        // `code` stays MOTORACCIDENT so dispatch is unchanged.
                        ['id' => 'Motor',    'name' => 'Motor',          'code' => 'MOTORACCIDENT'],
                        ['id' => 'Glass',    'name' => 'Glass / Windscreen', 'code' => 'GLASS'],
                        ['id' => 'Key Loss', 'name' => 'Locks & Keys',    'code' => 'LOCKSANDKEYS'],
                    ],
                    'actions' => null,
                ]]);

            case 4: // Legal Insurance
                return response()->json(['data' => [
                    'product_type' => 'Legal',
                    'product_id'   => $productId,
                    'form_template' => 'legal',
                    'claim_types'  => [
                        ['id' => 'Legal', 'name' => 'Legal Claim', 'code' => 'LEGAL'],
                    ],
                    'actions' => null,
                ]]);

            case 5: // Mobile / Cellphone Insurance
                return response()->json(['data' => [
                    'product_type' => 'Cellphone',
                    'product_id'   => $productId,
                    'form_template' => 'cellphone',
                    'claim_types'  => [
                        ['id' => 'Cellphone', 'name' => 'Mobile / Electronic Device', 'code' => 'CELLPHONE'],
                    ],
                    'actions' => null,
                ]]);

            case 6: // Tyre & Rim Insurance
                return response()->json(['data' => [
                    'product_type' => 'Tyre',
                    'product_id'   => $productId,
                    'form_template' => 'tyre',
                    'claim_types'  => [
                        ['id' => 'Tyre', 'name' => 'Tyre & Rim', 'code' => 'TYRE'],
                    ],
                    'actions' => null,
                ]]);

            case 9: // Hospital Cashback Insurance
                return response()->json(['data' => [
                    'product_type' => 'HospitalCash',
                    'product_id'   => $productId,
                    'form_template' => 'hospital_cash',
                    'claim_types'  => [
                        ['id' => 'hospital_cash', 'name' => 'Hospital Cashback Claim', 'code' => 'HOSPITALCASH'],
                    ],
                    'actions' => null,
                ]]);

            case 10: // Health In A Box (HIB)
                return response()->json(['data' => [
                    'product_type' => 'HIB',
                    'product_id'   => $productId,
                    'form_template' => 'hospital_cash',
                    'claim_types'  => [
                        ['id' => 'hospital_cash',     'name' => 'Hospital Stay',    'code' => 'HOSPITALCASH'],
                        // Stored claim_type is now 'Accidental Death' (was 'Life').
                        // `code` stays LIFE so the claim_life write still fires.
                        ['id' => 'Accidental Death',  'name' => 'Accidental Death', 'code' => 'LIFE'],
                    ],
                    'actions' => null,
                ]]);

            case 12: // Grouped AD Insurance
                return response()->json(['data' => [
                    'product_type' => 'GroupAD',
                    'product_id'   => $productId,
                    'form_template' => 'life',
                    'claim_types'  => [
                        // Stored claim_type is now 'Accidental Death' (was 'Life').
                        // `code` stays LIFE so the life dispatch is unchanged.
                        ['id' => 'Accidental Death', 'name' => 'Accidental Death', 'code' => 'LIFE'],
                    ],
                    'actions' => null,
                ]]);
        }

        // DOMG / COMG / Specialist (products 7, 8, 16, 17, 18, 19) — coverage-based claim types
        if (in_array($productId, [7, 8, 16, 17, 18, 19, 20, 22, 23, 24])) {
            // Term-selection dropdown. Mirrors graphiteBWV8 NewClaimController::prosess():
            //   - No status filter (claim against any historical term, including
            //     non-issued ones, as long as it isn't a cancel action).
            //   - Exclude transaction_type = 'CANCEL' AND any variant containing
            //     "cancel" (case-insensitive) — catches "Cancel Issued",
            //     "Cancel Quote", "CancelMidTerm", etc. without an enum list.
            //   - Order by effective_from ASC so the dropdown reads chronologically.
            $actions = DB::table('policy_actions')
                ->where('policy_id', $resolvedPolicyId)
                ->whereNull('deleted_at')
                ->where('transaction_type', '!=', 'CANCEL')
                ->whereRaw('LOWER(transaction_type) NOT LIKE ?', ['%cancel%'])
                ->orderBy('effective_from', 'ASC')
                ->get(['id', 'transaction_type', 'status', 'effective_from', 'effective_to']);

            // Get coverage-based claim types from dom_com_coverage_claims.
            // Coverages live on the LATEST action (newest endorsement). Since
            // the dropdown is now ordered effective_from ASC for chronological
            // display, last() — not first() — gives us the most recent term.
            $latestActionId = $actions->last()->id ?? null;
            $claimTypes = collect();

            if ($latestActionId) {
                // Get coverage_ids for this action
                $coverageIds = DB::table('policy_coverages')
                    ->where('policy_id', $resolvedPolicyId)
                    ->where('action_id', $latestActionId)
                    ->whereNull('deleted_at')
                    ->pluck('coverage_id')
                    ->toArray();

                if (!empty($coverageIds)) {
                    // Claim names from dom_com_coverage_claims when available.
                    $namedTypes = DB::table('dom_com_coverage_claims')
                        ->whereIn('coverage_id', $coverageIds)
                        ->whereNotNull('claim_name')
                        ->where('claim_name', '!=', '')
                        ->select('coverage_id', 'claim_name')
                        ->distinct()
                        ->get();
                    $coveredIds = $namedTypes->pluck('coverage_id')->unique()->all();
                    // Coverages without a dom_com_coverage_claims row fall back to
                    // their coverage display name — otherwise Workers Comp / All
                    // Risks coverages get silently dropped, leaving the operator
                    // with only the coverages that happened to be seeded.
                    $missingIds = array_values(array_diff($coverageIds, $coveredIds));
                    $fallbackTypes = collect();
                    if (!empty($missingIds)) {
                        $fallbackTypes = DB::table('tb_cvgpccoverages')
                            ->whereIn('id', $missingIds)
                            ->select('id as coverage_id', 's_CoverageName as claim_name')
                            ->get();
                    }
                    $claimTypes = $namedTypes->concat($fallbackTypes)
                        ->map(fn($c) => [
                            'id'          => $c->claim_name,
                            'name'        => $c->claim_name,
                            'coverage_id' => $c->coverage_id,
                        ])
                        ->values();
                }
            }

            // If still no types (policy has no coverages on the latest action),
            // fall back to ALL coverages on the policy regardless of action.
            if ($claimTypes->isEmpty()) {
                $claimTypes = DB::table('policy_coverages as pc')
                    ->join('tb_cvgpccoverages as cov', 'cov.id', '=', 'pc.coverage_id')
                    ->where('pc.policy_id', $resolvedPolicyId)
                    ->whereNull('pc.deleted_at')
                    ->select('cov.id as coverage_id', 'cov.s_CoverageName as claim_name')
                    ->distinct()
                    ->get()
                    ->map(fn($c) => [
                        'id'          => $c->claim_name,
                        'name'        => $c->claim_name,
                        'coverage_id' => $c->coverage_id,
                    ])
                    ->values();
            }

            $productLabel = match($productId) {
                7 => 'COMG', 8 => 'DOMG',
                16 => 'EngineeringCOM', 17 => 'SpecialistCOM',
                18 => 'EngineeringDOM', 19 => 'SpecialistDOM',
                20 => 'CommercialLiabilities',
                22 => 'Marine',
                23 => 'Guarantee',
                24 => 'Miscellaneous',
                default => 'CoverageBased',
            };

            return response()->json([
                'data' => [
                    'product_type' => $productLabel,
                    'product_id'   => $productId,
                    // Resolved numeric policy id — frontend may have sent
                    // a policyNumber (e.g. "COMG2026212962") and needs the
                    // numeric id to call /policies/{id}/risk-addresses for
                    // the DOM/COM "Select Location" dropdown.
                    'policy_id'    => $resolvedPolicyId,
                    'form_template' => 'coverage_based',
                    'claim_types'  => $claimTypes,
                    'actions'      => $actions->map(fn($a) => [
                        'id'              => $a->id,
                        'transactionType' => $a->transaction_type,
                        'status'          => $a->status,
                        'effectiveFrom'   => $a->effective_from,
                        'effectiveTo'     => $a->effective_to,
                    ]),
                ],
            ]);
        }

        // Unknown product — log and return empty list so frontend can surface
        // "no claim types configured for this product" instead of mis-matching options.
        \Log::warning("claimTypesByPolicy: no mapping for product_id={$productId} (policy={$policy->policyNumber})");
        return response()->json([
            'data' => [
                'product_type' => 'UNMAPPED',
                'product_id'   => $productId,
                'form_template' => null,
                'claim_types'  => [],
                'actions' => null,
            ],
        ]);
    }

    /**
     * GET /claims/sub-types-by-claim-type
     *   query params: claim_type (required — the selected claim_type label)
     *
     * Mirrors legacy graphiteBWV8 NewClaimController:
     *   Motor Accident → ClaimSubType::where('claim_type','Motor Accident')->get(['sub_type'])
     *   Any other     → Lookup::where('key', $claimType)->get()
     *
     * Returns the union of both matches. Tries a handful of key variants so
     * the lookup query hits regardless of whether the seeder used
     * "WORKMENCOMPENSATION", "Workmen Compensation", or "workmen_comp".
     */
    public function subTypesByClaimType(Request $request): JsonResponse
    {
        $claimType = (string) $request->query('claim_type', '');
        if ($claimType === '') return response()->json(['data' => []]);

        // Build candidate key variants to probe against lookup.key —
        // legacy seeders have been inconsistent. Probe raw, normalised,
        // and underscore forms in one query.
        $variants = array_values(array_unique([
            $claimType,
            strtoupper($claimType),
            strtolower($claimType),
            strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $claimType)),
            strtolower(preg_replace('/[^A-Za-z0-9]/', '_', $claimType)),
            ucwords(strtolower($claimType)),
        ]));

        $rows = collect();

        // 1. claim_subtype table — predominantly used for Motor Accident
        if (\Schema::hasTable('claim_subtype')) {
            $cols = \Schema::getColumnListing('claim_subtype');
            $nameCol = in_array('sub_type', $cols, true) ? 'sub_type'
                : (in_array('name', $cols, true) ? 'name'
                : (in_array('value', $cols, true) ? 'value' : null));
            if ($nameCol && in_array('claim_type', $cols, true)) {
                $hits = DB::table('claim_subtype')
                    ->when(in_array('deleted_at', $cols, true), fn($q) => $q->whereNull('deleted_at'))
                    ->whereIn('claim_type', $variants)
                    ->orderBy($nameCol)
                    ->get(['id', "{$nameCol} as name", 'claim_type']);
                $rows = $rows->concat($hits);
            }
        }

        // 2. lookup_data table keyed by claim_type — used for everything else.
        // V2's Lookup model points at `lookup_data` (V8 used `lookup`); the
        // wrong table-existence check here was returning empty results for
        // every non-Motor claim_type in V2.
        if (\Schema::hasTable('lookup_data')) {
            $hits = Lookup::whereIn('key', $variants)
                ->select('id', 'value as name', 'key as claim_type')
                ->orderBy('value')
                ->get();
            $rows = $rows->concat($hits);
        }

        return response()->json([
            'data' => $rows->values(),
            // Debug fields — let the FE show "no sub-types seeded for this
            // claim type" cleanly instead of a silently-empty dropdown.
            'probed_variants' => $variants,
            'source_count' => $rows->count(),
        ]);
    }

    /**
     * GET /claims/policy/{policyId}/claim-types-by-action/{actionId}
     * For DOMG/COMG: get claim types from coverages on a specific action.
     *
     * Accepts policyId as either a numeric DB id OR a policyNumber string
     * like "COMG2024130199". The FE deep-link uses whichever the operator
     * typed in the claim create page; both must resolve here or we blow
     * up with a TypeError (previously strict `int $policyId` hint).
     */
    public function claimTypesByAction($policyId, int $actionId): JsonResponse
    {
        // Resolve policy number → id if necessary
        if (!is_numeric($policyId)) {
            $resolvedId = DB::table('policies')->where('policyNumber', $policyId)->value('id');
            if (!$resolvedId) {
                return response()->json(['data' => [], 'error' => 'Policy not found.'], 404);
            }
            $policyId = (int) $resolvedId;
        } else {
            $policyId = (int) $policyId;
        }

        $coverageIds = DB::table('policy_coverages')
            ->where('policy_id', $policyId)
            ->where('action_id', $actionId)
            ->whereNull('deleted_at')
            ->pluck('coverage_id')
            ->toArray();

        $claimTypes = collect();

        if (!empty($coverageIds)) {
            $namedTypes = DB::table('dom_com_coverage_claims')
                ->whereIn('coverage_id', $coverageIds)
                ->whereNotNull('claim_name')
                ->where('claim_name', '!=', '')
                ->select('coverage_id', 'claim_name')
                ->distinct()
                ->get();
            $coveredIds = $namedTypes->pluck('coverage_id')->unique()->all();
            // Coverages without a dom_com_coverage_claims entry fall back to the
            // coverage display name, so Workers Comp / All Risks / etc. surface
            // even when only some coverages are seeded in dom_com_coverage_claims.
            $missingIds = array_values(array_diff($coverageIds, $coveredIds));
            $fallbackTypes = collect();
            if (!empty($missingIds)) {
                $fallbackTypes = DB::table('tb_cvgpccoverages')
                    ->whereIn('id', $missingIds)
                    ->select('id as coverage_id', 's_CoverageName as claim_name')
                    ->get();
            }
            $claimTypes = $namedTypes->concat($fallbackTypes)
                ->map(fn($c) => ['id' => $c->claim_name, 'name' => $c->claim_name, 'coverage_id' => $c->coverage_id])
                ->values();
        }

        // Final fallback if action has no coverages at all
        if ($claimTypes->isEmpty()) {
            $claimTypes = DB::table('policy_coverages as pc')
                ->join('tb_cvgpccoverages as cov', 'cov.id', '=', 'pc.coverage_id')
                ->where('pc.policy_id', $policyId)
                ->where('pc.action_id', $actionId)
                ->whereNull('pc.deleted_at')
                ->select('cov.id as coverage_id', 'cov.s_CoverageName as claim_name')
                ->distinct()
                ->get()
                ->map(fn($c) => ['id' => $c->claim_name, 'name' => $c->claim_name, 'coverage_id' => $c->coverage_id])
                ->values();
        }

        return response()->json(['data' => $claimTypes]);
    }

    /**
     * Register a new claim.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'policy_id'        => 'required',
            'claim_type'       => 'required|string',
            'category'         => 'nullable|string|max:100',
            'claim_sub_type'   => 'nullable|string|max:100',
            'registered_claim' => 'nullable|string|max:255',

            // Incident details
            'incident_date'    => 'nullable|date_format:Y-m-d',
            'incident_time'    => 'nullable|string|max:10',
            'incident_location'=> 'nullable|string|max:500',
            'incident_description' => 'nullable|string|max:2000',

            // Additional fields matching graphiteBWV8
            'type_of_loss'     => 'nullable|string|max:100',
            'event_name'       => 'nullable|string|max:200',
            'is_motor_claim'   => 'nullable|boolean',
            'vehicle_plate'    => 'nullable|string|max:50',
            'catastrophe_loss' => 'nullable|boolean',
            'attorney_involved'=> 'nullable|boolean',
            'weather_condition'=> 'nullable|string|max:50',
            'fault_party'      => 'nullable|string|max:50',
            'reason'           => 'nullable|string|max:1000',

            // Reported by
            'reported_by'      => 'nullable|string|max:100',
            'reported_date'    => 'nullable|date_format:Y-m-d',

            // Allocation — which internal user owns this claim
            'claim_allocated_to' => 'nullable|integer|exists:users,id',
            'claim_allocated_on' => 'nullable|date_format:Y-m-d',

            // ── Fields that legacy graphiteBWV8 writes to new_claims ───────
            // Accepted here so the V2 store can dual-write to `claims` +
            // `new_claims` and keep both read paths (V2 UI and legacy admin)
            // consistent. Previously these were silently dropped — the
            // legacy show merge at line 142 reads these columns and found
            // NULLs for every V2-created claim.
            'location_id'               => 'nullable|integer',
            'claim_sub_type_id'         => 'nullable|integer',
            'date_of_loss'              => 'nullable|date_format:Y-m-d',
            'description_of_loss'       => 'nullable|string|max:5000',
            'claim_reported_by'         => 'nullable|string|max:100',
            // Reported by Broker/Agent — id from the union of users + agencies
            // exposed by createData.agents_options. Stored verbatim on
            // new_claims.reportedByBrokerAgent (string keeps both kinds usable).
            'reportedByBrokerAgent'     => 'nullable|string|max:50',
            'service_representative_id' => 'nullable|integer|exists:users,id',
            'co_attorney_involved'      => 'nullable|boolean',
            'dfs_complaint'             => 'nullable|boolean',
            'reserve_amount'            => 'nullable|numeric',
            'paid_amount'               => 'nullable|numeric',
            'date_first_visited'        => 'nullable|date_format:Y-m-d',
            // Attorney assigned dropdowns (V8 DOM/COM Classification block).
            // IDs come from a hardcoded list — keep as string, not exists:.
            'primary_attorney_assigned_id' => 'nullable|string|max:50',
            'p_a_assigned_date'            => 'nullable|date_format:Y-m-d',
            'co_attorney_assigned_id'      => 'nullable|string|max:50',
            'c_a_assigned_date'            => 'nullable|date_format:Y-m-d',
            'driver_as_insured'         => 'nullable|boolean',
            'third_party_insured_elsewhere' => 'nullable|boolean',
            'tp_insured_elsewhere_email'    => 'nullable|email|max:150',

            // Documents (file uploads)
            'document_1'       => 'nullable|file|max:10240',
            'document_2'       => 'nullable|file|max:10240',
            'document_3'       => 'nullable|file|max:10240',

            // Template hint
            'form_template'    => 'nullable|string|max:30',

            // Selected policy action / term — validated below against
            // policy_actions.status so cancelled terms cannot be used.
            'policy_action_id' => 'nullable|integer',

            // ── Life ──
            'date_of_death'    => 'nullable|date_format:Y-m-d',
            'cause_of_death'   => 'nullable|string|max:255',
            'life_description' => 'nullable|string|max:2000',
            'death_certificate'=> 'nullable|file|max:10240',

            // ── Legal ──
            'legal_firm'       => 'nullable|string|max:255',
            'lawyer_name'      => 'nullable|string|max:255',
            'legal_tel'        => 'nullable|string|max:30',
            'legal_email'      => 'nullable|email|max:150',
            'legaloption'      => 'nullable|string|max:5',
            'representing_member' => 'nullable|string|max:5',
            'lawyer_tarrif'    => 'nullable|string|max:10',
            'member_name'      => 'nullable|string|max:255',
            'membership_id'    => 'nullable|string|max:50',
            'member_contact'   => 'nullable|string|max:30',
            'member_email'     => 'nullable|email|max:150',
            'lossreported_date'=> 'nullable|date_format:Y-m-d',
            'matter_relatesto' => 'nullable|string|max:10',
            'child_financial_dependent' => 'nullable|string|max:5',
            'idforchild'       => 'nullable|string|max:50',
            'child_dob'        => 'nullable|date_format:Y-m-d',
            'realestate_enquiry_from' => 'nullable|string|max:10',
            'arose_date'       => 'nullable|date_format:Y-m-d',
            'matter_quantum'   => 'nullable|string|max:255',
            'course_of_action' => 'nullable|string',
            'jurisdiction'     => 'nullable|string|max:255',
            'criminalmatter_detail' => 'nullable|string',
            'criminalmatter_charge' => 'nullable|string',

            // ── Hospital Cash ──
            'patient_name'     => 'nullable|string|max:255',
            'patient_dob'      => 'nullable|date_format:Y-m-d',
            'patient_identity_number' => 'nullable|string|max:50',
            'relationship'     => 'nullable|string|max:50',
            'relationship_other' => 'nullable|string|max:100',
            'occupation_date'  => 'nullable|date_format:Y-m-d',
            'gp_name'          => 'nullable|string|max:255',
            'gp_postal_address'=> 'nullable|string|max:500',
            'gp_cellular_no'   => 'nullable|string|max:30',
            'gp_telephone_no'  => 'nullable|string|max:30',
            'gp_fax_no'        => 'nullable|string|max:30',
            'hospital_name'    => 'nullable|string|max:255',
            'hospital_tel_fax' => 'nullable|string|max:100',
            'admitting_doctor' => 'nullable|string|max:255',
            'admitting_doctor_tel_fax' => 'nullable|string|max:100',
            'admission_date'   => 'nullable|date_format:Y-m-d',
            'admission_time'   => 'nullable|string|max:10',
            'discharge_date'   => 'nullable|date_format:Y-m-d',
            'discharge_time'   => 'nullable|string|max:10',
            'hospitalisation_type' => 'nullable|string|max:30',
            'accident_reported'=> 'nullable|string|max:100',
            'symptoms_first_appeared' => 'nullable|date_format:Y-m-d',
            'pregnancy_conception_date' => 'nullable|date_format:Y-m-d',
            'pregnancy_delivery_date' => 'nullable|date_format:Y-m-d',
            'injury_date'      => 'nullable|date_format:Y-m-d',
            'accident_circumstances' => 'nullable|string',
            'first_consultation_date' => 'nullable|date_format:Y-m-d',
            'is_medical_scheme'=> 'nullable|string|max:5',
            'medical_scheme_name' => 'nullable|string|max:255',
            'medical_aid_number'  => 'nullable|string|max:100',
            'has_other_insurance' => 'nullable|string|max:5',
            'other_insurance_company_name' => 'nullable|string|max:255',
            'other_insurance_policy_numbers' => 'nullable|string|max:255',

            // ── Motor-accident sub-table payloads (vehicle form_template) ──
            // Mirrors the validation rules on the update() endpoint so
            // MOTORACCIDENT / MOTORTRADERSEXTERNAL / MOTORTRADERSINTERNAL
            // claims can post the full V8 accident blade payload (driver,
            // passengers, third parties, recovery) at create time, not
            // only via a follow-up edit. The four sub-tables
            // (claim_accidents, accident_driver, claim_accident_passengers,
            // claim_accident_third_party) are written below after the
            // claim is created.
            'accident_details'                      => 'nullable|array',
            'accident_details.place_of_accident'    => 'nullable|string|max:500',
            'accident_details.time_of_accident'     => 'nullable|string|max:20',
            'accident_details.date_of_accident'     => 'nullable|date_format:Y-m-d',
            'accident_details.detail_of_accident'   => 'nullable|string|max:5000',
            'accident_details.purpose_of_trip'      => 'nullable|string|max:255',
            'accident_details.fault_party'          => 'nullable|string|max:50',
            'accident_details.third_party'          => 'nullable|boolean',

            'accident_driver'                       => 'nullable|array',
            'accident_driver.name'                  => 'nullable|string|max:255',
            'accident_driver.cellphone'             => 'nullable|string|max:30',
            'accident_driver.dob'                   => 'nullable|date_format:Y-m-d',
            'accident_driver.address'               => 'nullable|string|max:500',
            'accident_driver.license'               => 'nullable|string|max:100',
            'accident_driver.purpose'               => 'nullable|string|max:255',

            'accident_passengers'                   => 'nullable|array',
            'accident_passengers.*.name'            => 'nullable|string|max:255',
            'accident_passengers.*.address'         => 'nullable|string|max:500',
            'accident_passengers.*.injury'          => 'nullable|string|max:1000',

            'accident_third_parties'                       => 'nullable|array',
            'accident_third_parties.*.first_name'          => 'nullable|string|max:100',
            'accident_third_parties.*.last_name'           => 'nullable|string|max:100',
            'accident_third_parties.*.cellphone'           => 'nullable|string|max:30',
            'accident_third_parties.*.address'             => 'nullable|string|max:500',
            'accident_third_parties.*.make'                => 'nullable|string|max:100',
            'accident_third_parties.*.model'               => 'nullable|string|max:100',
            'accident_third_parties.*.registration_no'     => 'nullable|string|max:50',
            'accident_third_parties.*.damage_details'      => 'nullable|string|max:2000',
            'accident_third_parties.*.injured_name'        => 'nullable|string|max:255',
            'accident_third_parties.*.relationship'        => 'nullable|string|max:100',
            'accident_third_parties.*.hospital_name'       => 'nullable|string|max:255',
            'accident_third_parties.*.injured_details'     => 'nullable|string|max:2000',
            // Third party "insured elsewhere" sub-fields (V8 third_party_insured = '1')
            'accident_third_parties.*.first_name_insured'  => 'nullable|string|max:100',
            'accident_third_parties.*.last_name_insured'   => 'nullable|string|max:100',
            'accident_third_parties.*.cellphone_insured'   => 'nullable|string|max:30',
            'accident_third_parties.*.email_insured'       => 'nullable|email|max:150',
            'accident_third_parties.*.address_insured'     => 'nullable|string|max:500',
        ]);

        // Fold coverage-based claim-type codes that aren't ENUM members
        // (e.g. MOTORACCIDENT → Motor, LOCKSANDKEYS → Key Loss) onto the
        // canonical ENUM value so claims.claim_type persists instead of
        // coercing to '' on insert. Same call is made in update().
        if (!empty($validated['claim_type'])) {
            $validated['claim_type'] = $this->mapClaimTypeToEnum($validated['claim_type']);
        }

        // ── Goods In Transit (GIT) pre-validation ────────────────────────
        // Mirrors graphiteBWV8's required-field rules from the V8 GIT blade
        // (resources/views/admin/claims/newClaims/types/goods_in_transit.blade.php).
        // Run BEFORE the DB transaction so ValidationException returns 422
        // cleanly instead of being swallowed by the outer catch and returned
        // as 500.
        if ($this->normalizeClaimTypeKey($validated['claim_type'] ?? null) === 'GOODSINTRANSIT') {
            $gitError = $this->validateGoodsInTransitPayload($request);
            if ($gitError !== null) return $gitError;
        }

        try {
            DB::beginTransaction();

            // Resolve policy by ID or policyNumber
            $policyInput = $validated['policy_id'];
            if (is_numeric($policyInput)) {
                $policy = Policy::find($policyInput);
            } else {
                $policy = Policy::where('policyNumber', $policyInput)->first();
            }

            if (!$policy) {
                return response()->json(['message' => 'Policy not found.'], 404);
            }

            // DOMG/COMG claims may be filed against a cancelled POLICY — V8
            // parity (NewClaimController::prosess). Historical terms on a
            // now-cancelled policy still need claim cover. Per-term cancel
            // actions are blocked below by inspecting transaction_type, not
            // the policy-level status. Other products keep the strict
            // active-only guard.
            $isDomComClaim = in_array((int) $policy->product_id, [7, 8, 16, 17, 18, 19, 20, 22, 23, 24], true);

            if ($policy->status != 1 && !$isDomComClaim) {
                return response()->json(['message' => 'Claims can only be filed against active policies.'], 422);
            }

            // Block claims against a CANCEL-type policy term. For DOMG/COMG we
            // match the dropdown's filter (transaction_type LIKE '%cancel%'
            // case-insensitive) so the operator can't bypass it by sending an
            // arbitrary policy_action_id. For other products keep the V2-legacy
            // requirement that the term is ISSUED.
            if (!empty($validated['policy_action_id'])) {
                $action = DB::table('policy_actions')
                    ->where('id', $validated['policy_action_id'])
                    ->where('policy_id', $policy->id)
                    ->whereNull('deleted_at')
                    ->first(['id', 'status', 'transaction_type']);
                if (!$action) {
                    return response()->json(['message' => 'Selected policy term was not found on this policy.'], 422);
                }
                if ($isDomComClaim) {
                    $txType = strtolower((string) $action->transaction_type);
                    if ($txType === 'cancel' || str_contains($txType, 'cancel')) {
                        return response()->json([
                            'message' => "Claims cannot be filed against a cancel-type policy term ({$action->transaction_type}). Select a non-cancel term.",
                        ], 422);
                    }
                } elseif (strcasecmp((string) $action->status, 'ISSUED') !== 0) {
                    return response()->json([
                        'message' => "Claims cannot be filed against a {$action->status} policy term. Select an active (ISSUED) term.",
                    ], 422);
                }
            }

            // The plate dropdown is term-scoped on the FE; enforce the same
            // here. Only when the term actually has captured vehicles — terms
            // without any keep accepting free text (matches the FE fallback).
            if (!empty($validated['vehicle_plate'])) {
                $plateError = $this->validatePlateForPolicyAction(
                    $policy->id,
                    $validated['policy_action_id'] ?? null,
                    $validated['vehicle_plate']
                );
                if ($plateError) {
                    return response()->json(['message' => $plateError], 422);
                }
            }

            // Generate claim number: G{YEAR}{ID_PADDED}
            $latestClaim = Claim::orderBy('id', 'desc')->first(['id']);
            $nextId = $latestClaim ? $latestClaim->id + 1 : 1;
            $claimNumber = 'G' . Carbon::now()->year . str_pad($nextId, 6, '0', STR_PAD_LEFT);

            // Handle document uploads
            $documents = [];
            foreach (['document_1', 'document_2', 'document_3'] as $docField) {
                if ($request->hasFile($docField)) {
                    $file = $request->file($docField);
                    $path = $file->store(
                        "MIS/{$policy->customer_id}/Claims/{$claimNumber}",
                        's3'
                    );
                    $documents[$docField] = $path;
                }
            }

            $claim = Claim::create([
                'customer_id'      => $policy->customer_id,
                'agent_id'         => $policy->agent_id,
                'policy_id'        => $policy->id,
                // Record WHICH policy term the claim was filed against so the
                // detail header can show the "Action Term" badge (graphiteBWV8
                // parity). Validated above against this policy's policy_actions.
                'policy_action_id' => $validated['policy_action_id'] ?? null,
                'claim_type'       => $validated['claim_type'],
                'claim_sub_type'   => $validated['claim_sub_type'] ?? null,
                'claim_number'     => $claimNumber,
                // V8 parity: NewClaimController defaulted newly-registered
                // claims to 'Pending' (the badge yellow state). V2 was
                // hardcoding 'New' which left the status column visually
                // blank in the list because the legacy badge mapping had
                // no entry for 'New'. Match V8 so operators see a real
                // state on the list right after Register Claim.
                'status'           => 'Pending',
                'category'         => $validated['category'] ?? null,
                'registered_claim' => $validated['registered_claim'] ?? null,
                'incident_date'    => $validated['incident_date'] ?? null,
                'incident_time'    => $validated['incident_time'] ?? null,
                'incident_location'=> $validated['incident_location'] ?? null,
                'incident_description' => $validated['incident_description'] ?? null,
                'type_of_loss'     => $validated['type_of_loss'] ?? null,
                'event_name'       => $validated['event_name'] ?? null,
                'is_motor_claim'   => $validated['is_motor_claim'] ?? 0,
                'vehicle_plate'    => $validated['vehicle_plate'] ?? null,
                'catastrophe_loss' => $validated['catastrophe_loss'] ?? 0,
                'attorney_involved'=> $validated['attorney_involved'] ?? 0,
                'weather_condition'=> $validated['weather_condition'] ?? null,
                'fault_party'      => $validated['fault_party'] ?? null,
                'reason'           => $validated['reason'] ?? null,
                'reported_by'      => $validated['reported_by'] ?? null,
                'reported_date'    => $validated['reported_date'] ?? null,
                'form_template'    => $validated['form_template'] ?? null,
                'created_by'       => auth()->id(),
                'claim_allocated_to' => $validated['claim_allocated_to'] ?? null,
                'claim_allocated_on' => $validated['claim_allocated_on'] ?? null,
                'document_1'       => $documents['document_1'] ?? null,
                'document_2'       => $documents['document_2'] ?? null,
                'document_3'       => $documents['document_3'] ?? null,
            ]);

            // Seed an empty stage-timeline row so the claim participates in the
            // dashboard's workflow-driven sections (pipeline / leaderboard) from
            // the moment it's registered — the dashboard builds those lists only
            // from claims that have a claim_tracker_workflow row, so new claims
            // were invisible there until the first SLA edit (v5). Anchor dates are
            // ~today, so no false SLA breach. Best-effort — never block registration.
            try {
                \AlphaDirect\Models\ClaimTrackerWorkflow::firstOrCreate(['claim_id' => $claim->id]);
            } catch (\Throwable $e) {
                \Log::warning('claim workflow seed failed for claim ' . $claim->id . ': ' . $e->getMessage());
            }

            // ── Optional DOMG/COMG claim-type-specific sub-table payload ─
            // Frontend can send { sub_claim_data: { ... } } with fields
            // specific to the claim type. We route it to the matching
            // legacy sub-table after new_claims is inserted below.
            $subClaimData = $request->input('sub_claim_data');
            if ($subClaimData !== null && !is_array($subClaimData)) {
                // Accept a JSON string too (some multipart flows send
                // nested objects as stringified JSON).
                $decoded = json_decode((string) $subClaimData, true);
                $subClaimData = is_array($decoded) ? $decoded : null;
            }

            // ── Dual-write the legacy `new_claims` shadow row ────────────
            // Every claim written via V2's POST /claims needs a matching
            // row in `new_claims` keyed by claim_number. Reasons:
            //   1. ClaimsController::show() merges extended fields from
            //      new_claims (location_id, claim_sub_type_id, allocated_to
            //      fallback, etc.) — if the row is missing, the UI shows
            //      NULL for every user-entered field they expected to see.
            //      This is the "submitted one thing, see different info"
            //      bug the user reported on MIS claims.
            //   2. Legacy graphiteBWV8 admin pages (NewClaimController) read
            //      exclusively from new_claims and would otherwise be blind
            //      to V2-created claims entirely.
            //   3. DOMG/COMG sub-claim tables (business_interruption,
            //      fidelity_guarantee, etc.) link via new_claims.id, so
            //      the shadow row is a prerequisite for the DOMG/COMG
            //      claim-type extensions shipping in the follow-up commit.
            //
            // Column mapping follows legacy NewClaimController::store()
            // (backend/app/Http/Controllers/Admin/NewClaimController.php:300).
            // Wrapped in try/catch so envs that don't yet have new_claims
            // (local dev boxes on older migrations) don't block claim
            // submission. An orphan `claims` row is still readable.
            try {
                if (\Schema::hasTable('new_claims')) {
                    $newClaimRow = [
                        'policyNumber'     => $policy->policyNumber,
                        'claim_number'     => $claimNumber,
                        'policy_id'        => $policy->id,
                        'customer_id'      => $policy->customer_id,
                        'agent_id'         => $policy->agent_id,
                        'claim_type'       => $validated['claim_type'],
                        'claim_sub_type_id'=> $validated['claim_sub_type_id'] ?? 0,
                        'location_id'      => $validated['location_id'] ?? null,
                        'is_motor_claim'   => $validated['is_motor_claim'] ?? 0,
                        'vehicle_plate'    => $validated['vehicle_plate'] ?? null,
                        'co_attorney_involved' => $validated['co_attorney_involved'] ?? 0,
                        'attorney_involved'    => $validated['attorney_involved']    ?? 0,
                        // Legacy uses claim_reported_by; fall back to V2's reported_by
                        'claim_reported_by'    => $validated['claim_reported_by']
                                                  ?? $validated['reported_by'] ?? '',
                        'reportedByBrokerAgent' => $validated['reportedByBrokerAgent'] ?? null,
                        'type_of_loss'         => $validated['type_of_loss'] ?? 0,
                        // Prefer explicit date_of_loss; otherwise share incident_date
                        'date_of_loss'         => $validated['date_of_loss']
                                                  ?? $validated['incident_date']
                                                  ?? now()->format('Y-m-d'),
                        'service_representative_id' => $validated['service_representative_id'] ?? null,
                        'catastrophe_loss'     => $validated['catastrophe_loss'] ?? 0,
                        'event_name'           => $validated['event_name'] ?? null,
                        // Legacy uses description_of_loss; fall back to V2's incident_description
                        'description_of_loss'  => $validated['description_of_loss']
                                                  ?? $validated['incident_description'] ?? null,
                        'dfs_complaint'        => $validated['dfs_complaint'] ?? 0,
                        'claim_allocated_to'   => $validated['claim_allocated_to'] ?? null,
                        // Legacy column is plural (claims_allocated_on); map V2's
                        // singular claim_allocated_on onto it.
                        'claims_allocated_on'  => $validated['claim_allocated_on'] ?? null,
                        'reserve_amount'       => $validated['reserve_amount'] ?? 0,
                        'paid_amount'          => $validated['paid_amount']    ?? 0,
                        'date_first_visited'   => $validated['date_first_visited'] ?? null,
                        'primary_attorney_assigned_id' => $validated['primary_attorney_assigned_id'] ?? null,
                        'p_a_assigned_date'            => $validated['p_a_assigned_date'] ?? null,
                        'co_attorney_assigned_id'      => $validated['co_attorney_assigned_id'] ?? null,
                        'c_a_assigned_date'            => $validated['c_a_assigned_date'] ?? null,
                        'driver_as_insured'    => $validated['driver_as_insured'] ?? 0,
                        'third_party_insured_elsewhere' => $validated['third_party_insured_elsewhere'] ?? 0,
                        'tp_insured_elsewhere_email'    => $validated['tp_insured_elsewhere_email']    ?? null,
                        'created_by'           => auth()->id(),
                        'claim_approved'       => 'No',
                        'created_at'           => now(),
                        'updated_at'           => now(),
                    ];
                    // Drop any keys whose columns don't exist on this env so
                    // we don't break on older schemas. The legacy table has
                    // drifted across deploys; probing each column keeps this
                    // resilient.
                    $existing = \Schema::getColumnListing('new_claims');
                    $newClaimRow = array_intersect_key($newClaimRow, array_flip($existing));
                    $newClaimId = DB::table('new_claims')->insertGetId($newClaimRow);
                }
            } catch (\Throwable $e) {
                // Don't fail the claim submission if the shadow write hits
                // an unexpected schema drift — log and keep going. The
                // primary `claims` row is already committed above and is
                // the source of truth for reserves / assessments / docs.
                Log::warning('new_claims shadow insert failed for claim ' . $claim->id
                    . ': ' . $e->getMessage());
                $newClaimId = null;
            }

            // ── DOMG/COMG claim-type specific sub-table write ─────────────
            // Each DOMG/COMG claim type has a dedicated legacy table
            // keyed by newclaim_id. graphiteBWV8 NewClaimController
            // dispatches to a matching ::<Type>store($id, $request) method
            // per claim_type (see backend/app/Http/Controllers/Admin/
            // NewClaimController.php:440+). V2 previously had no handling
            // — the type-specific fields on the claim form (e.g. Business
            // Interruption's nature_of_interruption or Burglary's
            // address_of_premises) went nowhere.
            //
            // Generic passthrough design so we don't enumerate 100+
            // fields here. Frontend sends `sub_claim_data` with the
            // claim-type-specific fields; we filter to columns the
            // target table actually has and insert.
            $subTableMap = [
                'BUSINESSINTERRUPTION'      => 'business_interruption',
                // graphiteBWV8 main.blade.php groups BUSINESSALLRISKS,
                // ELECTRONICEQUIPMENT and PERSONALALLRISKS onto the same
                // all_risk_and_electronic_equipment table; ALLRISK is a
                // generic alias some callers use.
                'BUSINESSALLRISKS'          => 'all_risk_and_electronic_equipment',
                'ELECTRONICEQUIPMENT'       => 'all_risk_and_electronic_equipment',
                'PERSONALALLRISKS'          => 'all_risk_and_electronic_equipment',
                'ALLRISK'                   => 'all_risk_and_electronic_equipment',
                'FIDELITYGUARANTEE'         => 'fidelity_guarantee',
                // graphiteBWV8 main.blade.php routes CONTRACTORSALLRISKS to
                // contractors_all_risks_public_liability.blade.php + the
                // contractors_all_risks_public_liability table. CARPL is a
                // V2-internal alias; CONTRACTORSALLRISKSPUBLICLIABILITY is
                // the long form some legacy seeds use.
                'CONTRACTORSALLRISKS'                => 'contractors_all_risks_public_liability',
                'CONTRACTORSALLRISKSPUBLICLIABILITY' => 'contractors_all_risks_public_liability',
                'CARPL'                              => 'contractors_all_risks_public_liability',
                // graphiteBWV8 main.blade.php routes ERECTIONALLRISK to
                // erection_all_risk.blade.php + erection_all_risk_claims.
                'ERECTIONALLRISK'                    => 'erection_all_risk_claims',
                'PLANTALLRISKS'                      => 'plant_all_risks_claims',
                'MACHINERYBREAKDOWN'                 => 'machinery_breakdown_claims',
                'MACHINERYBREAKDOWNLOSSOFPROFIT'     => 'machinery_breakdown_lop_claims',
                'DIRECTORSOFFICERSLIABILITY'         => 'directors_officers_liability_claims',
                'MARINECARGOONCEOFF'                 => 'marine_cargo_once_off_claims',
                'MARINECARGOOPENCOVER'               => 'marine_cargo_open_cover_claims',
                'MEDICALMALPRACTICE'                 => 'medical_malpractice_claims',
                'GLASS'                              => 'glass_claim',
                'LOCKSANDKEYS'                       => 'key_loss_claim',
                // MIS Key Loss ships claim_type 'Key Loss' which normalises
                // to 'KEYLOSS'; DOM/COM ships the V8 code 'LOCKSANDKEYS'.
                // Register both so dispatch works regardless of source.
                'KEYLOSS'                            => 'key_loss_claim',
                // graphiteBWV8 main.blade.php groups all six "property"
                // aliases — BUILDINGSCOMBINED, ACCIDENTALDAMAGE, HOUSEHOLDERS,
                // HOUSEOWNERS, HOUSEOWNER-BUILDINGS, HOUSEHOLDERS-CONTENTS —
                // onto property_loss_damage.blade.php + the
                // property_loss_damage table. FE normalizes the hyphenated
                // codes via replace(/[^A-Z0-9]/g, '') so we register the
                // already-stripped forms here too.
                'PROPERTYLOSSDAMAGE'        => 'property_loss_damage',
                'BUILDINGSCOMBINED'         => 'property_loss_damage',
                'ACCIDENTALDAMAGE'          => 'property_loss_damage',
                'HOUSEHOLDERS'              => 'property_loss_damage',
                'HOUSEOWNERS'               => 'property_loss_damage',
                'HOUSEOWNERBUILDINGS'       => 'property_loss_damage',
                'HOUSEHOLDERSCONTENTS'      => 'property_loss_damage',
                // graphiteBWV8 main.blade.php groups LIABILITY and
                // PUBLICLIABILITY onto the same public_liability.blade.php
                // + public_liability table.
                'PUBLICLIABILITY'           => 'public_liability',
                'LIABILITY'                 => 'public_liability',
                'WORKERSCOMPENSATION'       => 'workers_compensation',
                'STATEDBENEFITS'            => 'workers_compensation',
                'DEFECTIVEWORKMANSHIP'      => 'defective_workmanship',
                // Burglary family — V8 main.blade.php lumps THEFT and MONEY
                // onto the burglary blade + table. FE normalizes "Burglary /
                // Theft" via replace(/[^A-Z0-9]/g, '') to BURGLARYTHEFT, so
                // the legacy 'BURGLARY/THEFT' slash key never matched.
                'THEFT'                     => 'burglary',
                'BURGLARY'                  => 'burglary',
                'BURGLARYTHEFT'             => 'burglary',
                'MONEY'                     => 'burglary',
                'FIRE'                      => 'fire',
                'PROPERTYDAMAGE'            => 'fire',
                'GOODSINTRANSIT'            => 'goods_in_transit_claim',
                'TRAVELINSURANCE'           => 'travel_insurance_claim',
                'PROFESSIONALINDEMNITY'     => 'professional_indemnity_claims',
                // Mobile/Electronic Devices family — V8 main.blade.php
                // groups MOBILEELECTRONICDEVICES and OFFICECONTENTS onto the
                // same mobileAndElectronicDevices.blade.php + the
                // mobile_and_electronic_devices_claim table. Earlier V2 used
                // the wrong table name (`mobile_and_electronic_devices`) and
                // the wrong claim_type key (`MOBILEANDELECTRONICDEVICES` —
                // FE normalizes "Mobile/Electronic Devices" without the
                // "AND" so the legacy key never matched).
                'MOBILEELECTRONICDEVICES'   => 'mobile_and_electronic_devices_claim',
                'OFFICECONTENTS'            => 'mobile_and_electronic_devices_claim',
                'MOBILEANDELECTRONICDEVICES'=> 'mobile_and_electronic_devices_claim',
            ];
            // Normalize claim_type to the compact map key. Legacy stores
            // human-readable strings like "Business Interruption",
            // "Burglary / Theft", "Fidelity Guarantee" in
            // dom_com_coverage_claims.claim_name; the map uses compact
            // forms ("BUSINESSINTERRUPTION"). Strip anything that isn't
            // A-Z/0-9 so both shapes match.
            $claimTypeKey = preg_replace(
                '/[^A-Z0-9]/', '', strtoupper((string) $validated['claim_type'])
            );
            $subTable = $subTableMap[$claimTypeKey] ?? null;

            if ($subTable && $newClaimId && is_array($subClaimData) && !empty($subClaimData)) {
                try {
                    if (\Schema::hasTable($subTable)) {
                        $subCols = \Schema::getColumnListing($subTable);
                        // Start from client payload filtered to real cols;
                        // then overlay the required FKs + audit stamps so
                        // the frontend can't accidentally overwrite them.
                        $row = array_intersect_key($subClaimData, array_flip($subCols));
                        if (in_array('newclaim_id', $subCols, true)) {
                            $row['newclaim_id'] = $newClaimId;
                        }
                        if (in_array('policyNumber', $subCols, true)) {
                            $row['policyNumber'] = $policy->policyNumber;
                        }
                        if (in_array('claim_sub_type_id', $subCols, true)
                            && !isset($row['claim_sub_type_id'])
                            && isset($validated['claim_sub_type_id'])) {
                            $row['claim_sub_type_id'] = $validated['claim_sub_type_id'];
                        }
                        if (in_array('created_at', $subCols, true)) $row['created_at'] = now();
                        if (in_array('updated_at', $subCols, true)) $row['updated_at'] = now();
                        DB::table($subTable)->insert($row);
                    }
                } catch (\Throwable $e) {
                    // Same policy as new_claims — log and continue.
                    // The claim already exists; we don't strand it
                    // because one extension table rejected a column.
                    Log::warning("claim sub-table `{$subTable}` insert failed for claim " . $claim->id
                        . ' (type ' . $claimTypeKey . '): ' . $e->getMessage());
                }
            }

            // ── Goods In Transit: copy_of_contract S3 upload ─────────────
            // V8 NewClaimController::GoodsInTransitStore() uploads the
            // contract PDF/image to S3 under Claims/{newclaim_id}/{uuid}{name}
            // and stores the path on goods_in_transit_claim.copy_of_contract.
            // Replicated here using $file->storeAs() (the same pattern V2
            // uses for document_1/2/3 and death_certificate uploads, which
            // ARE accessible via CloudFront). The earlier
            // `Storage::disk('s3')->put(..., 'public')` call passed the
            // public-read ACL flag, which the bucket's BlockPublicAcls
            // policy rejects — leaving an inconsistent state where the FE
            // had a path but the object wasn't actually written.
            // Only fires when carrier is contracted (FE hides the file
            // input otherwise) and a file was actually sent.
            if ($claimTypeKey === 'GOODSINTRANSIT'
                && $newClaimId
                && $request->hasFile('copy_of_contract')
            ) {
                try {
                    $file = $request->file('copy_of_contract');
                    $name = (string) \Illuminate\Support\Str::uuid()
                          . $file->getClientOriginalName();
                    $path = $file->storeAs("Claims/{$newClaimId}", $name, 's3');
                    if ($path) {
                        DB::table('goods_in_transit_claim')
                            ->where('newclaim_id', $newClaimId)
                            ->update(['copy_of_contract' => $path, 'updated_at' => now()]);
                    } else {
                        Log::warning('GIT copy_of_contract storeAs returned falsy for claim ' . $claim->id);
                    }
                } catch (\Throwable $e) {
                    Log::warning('GIT copy_of_contract upload failed for claim ' . $claim->id
                        . ': ' . $e->getMessage());
                }
            }

            // ── Fire: contract_of_agreement S3 upload ────────────────────
            // Mirrors graphiteBWV8 NewClaimController::FireStore() which
            // accepts the security-agent contract file and stores the path
            // on fire_claim.contract_of_agreement.
            if ($claimTypeKey === 'FIRE'
                && $newClaimId
                && $request->hasFile('contract_of_agreement')
            ) {
                try {
                    $file = $request->file('contract_of_agreement');
                    $name = (string) \Illuminate\Support\Str::uuid()
                          . $file->getClientOriginalName();
                    $path = $file->storeAs("Claims/{$newClaimId}/contract_of_agreement", $name, 's3');
                    if ($path) {
                        DB::table('fire_claim')
                            ->where('newclaim_id', $newClaimId)
                            ->update(['contract_of_agreement' => $path, 'updated_at' => now()]);
                    } else {
                        Log::warning('Fire contract_of_agreement storeAs returned falsy for claim ' . $claim->id);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Fire contract_of_agreement upload failed for claim ' . $claim->id
                        . ': ' . $e->getMessage());
                }
            }

            // ── Contractors All Risks / Public Liability: two S3 uploads ─
            // V8 ContractorsAllRisksPublicLiabilityStore() accepts
            // works_claim_documentary_evidence and
            // works_claim_bill_of_quantities, stores paths on the
            // contractors_all_risks_public_liability table.
            if (in_array($claimTypeKey, ['CONTRACTORSALLRISKS', 'CONTRACTORSALLRISKSPUBLICLIABILITY', 'CARPL'], true)
                && $newClaimId
            ) {
                foreach (['works_claim_documentary_evidence', 'works_claim_bill_of_quantities'] as $field) {
                    if (!$request->hasFile($field)) continue;
                    try {
                        $file = $request->file($field);
                        $name = (string) \Illuminate\Support\Str::uuid()
                              . $file->getClientOriginalName();
                        $path = $file->storeAs("Claims/{$newClaimId}/{$field}", $name, 's3');
                        if ($path) {
                            DB::table('contractors_all_risks_public_liability')
                                ->where('newclaim_id', $newClaimId)
                                ->update([$field => $path, 'updated_at' => now()]);
                        } else {
                            Log::warning("CARPL {$field} storeAs returned falsy for claim " . $claim->id);
                        }
                    } catch (\Throwable $e) {
                        Log::warning("CARPL {$field} upload failed for claim " . $claim->id . ': ' . $e->getMessage());
                    }
                }
            }

            // ── Travel Insurance: up to 25 file uploads (6 compulsory +
            // 4 refund-type groups). Each file is stored under a
            // field-scoped path so multi-file uploads don't collide on the
            // same UUID. Failure on any one file logs and continues — the
            // textual columns are already persisted.
            if ($claimTypeKey === 'TRAVELINSURANCE' && $newClaimId) {
                $travelFiles = [
                    'compulsory_doc_proof_of_residence',
                    'compulsory_doc_claim_form',
                    'compulsory_doc_insurance_policy',
                    'compulsory_doc_detailed_letter',
                    'compulsory_doc_receipts',
                    'compulsory_doc_passport_copy',
                    'medical_dental_care_doc_1',
                    'medical_dental_care_doc_2',
                    'medical_dental_care_doc_3',
                    'claim_delayed_luggage_doc_1',
                    'claim_delayed_luggage_doc_2',
                    'claim_delayed_luggage_doc_3',
                    'claim_loss_personal_doc_doc_1',
                    'claim_loss_personal_doc_doc_2',
                    'claim_lost_luggage_doc_1',
                    'claim_lost_luggage_doc_2',
                    'claim_lost_luggage_doc_3',
                    'claim_lost_luggage_doc_4',
                    'claim_trip_cancel_doc_1',
                    'claim_trip_cancel_doc_2',
                    'claim_trip_cancel_doc_3',
                    'claim_trip_cancel_doc_4',
                    'claim_delayed_flight_doc_1',
                    'claim_delayed_flight_doc_2',
                    'claim_delayed_flight_doc_3',
                ];
                foreach ($travelFiles as $field) {
                    if (!$request->hasFile($field)) continue;
                    try {
                        $file = $request->file($field);
                        $name = (string) \Illuminate\Support\Str::uuid()
                              . $file->getClientOriginalName();
                        $path = $file->storeAs("Claims/{$newClaimId}/{$field}", $name, 's3');
                        if ($path) {
                            DB::table('travel_insurance_claim')
                                ->where('newclaim_id', $newClaimId)
                                ->update([$field => $path, 'updated_at' => now()]);
                        } else {
                            Log::warning("TravelInsurance {$field} storeAs returned falsy for claim " . $claim->id);
                        }
                    } catch (\Throwable $e) {
                        Log::warning("TravelInsurance {$field} upload failed for claim " . $claim->id . ': ' . $e->getMessage());
                    }
                }
            }

            // ── Professional Indemnity: two file uploads ────────────────
            // V8 ProfessionalIndemnityStore() accepts contract_copy (only
            // when contract_in_place === '1') and investigation_findings
            // (only when own_investigation === '1'). FE hides the inputs
            // otherwise, so we just look for whatever was posted.
            if ($claimTypeKey === 'PROFESSIONALINDEMNITY' && $newClaimId) {
                foreach (['contract_copy', 'investigation_findings'] as $field) {
                    if (!$request->hasFile($field)) continue;
                    try {
                        $file = $request->file($field);
                        $name = (string) \Illuminate\Support\Str::uuid()
                              . $file->getClientOriginalName();
                        $path = $file->storeAs("Claims/{$newClaimId}/{$field}", $name, 's3');
                        if ($path) {
                            DB::table('professional_indemnity_claims')
                                ->where('newclaim_id', $newClaimId)
                                ->update([$field => $path, 'updated_at' => now()]);
                        } else {
                            Log::warning("ProfessionalIndemnity {$field} storeAs returned falsy for claim " . $claim->id);
                        }
                    } catch (\Throwable $e) {
                        Log::warning("ProfessionalIndemnity {$field} upload failed for claim " . $claim->id . ': ' . $e->getMessage());
                    }
                }
            }

            // ── Medical Malpractice: five Section-4 file uploads ────────
            // V8 MedicalMalpracticeStore() persists each file path on the
            // medical_malpractice_claims table when posted. Re-uploading
            // replaces the previously stored path.
            if ($claimTypeKey === 'MEDICALMALPRACTICE' && $newClaimId) {
                foreach ([
                    'notification_letter', 'patient_records',
                    'investigation_reports', 'correspondence',
                    'expert_legal_opinions',
                ] as $field) {
                    if (!$request->hasFile($field)) continue;
                    try {
                        $file = $request->file($field);
                        $name = (string) \Illuminate\Support\Str::uuid()
                              . $file->getClientOriginalName();
                        $path = $file->storeAs("Claims/{$newClaimId}/{$field}", $name, 's3');
                        if ($path) {
                            DB::table('medical_malpractice_claims')
                                ->where('newclaim_id', $newClaimId)
                                ->update([$field => $path, 'updated_at' => now()]);
                        } else {
                            Log::warning("MedicalMalpractice {$field} storeAs returned falsy for claim " . $claim->id);
                        }
                    } catch (\Throwable $e) {
                        Log::warning("MedicalMalpractice {$field} upload failed for claim " . $claim->id . ': ' . $e->getMessage());
                    }
                }
            }

            // ── Locks & Keys: police_affidavit upload (V2 extension) ────
            // V8 blade has this commented out; V2's existing FE form
            // already captures it. Persist on key_loss_claim.
            if (in_array($claimTypeKey, ['LOCKSANDKEYS', 'KEYLOSS'], true) && $newClaimId
                && $request->hasFile('police_affidavit')
            ) {
                try {
                    $file = $request->file('police_affidavit');
                    $name = (string) \Illuminate\Support\Str::uuid()
                          . $file->getClientOriginalName();
                    $path = $file->storeAs("Claims/{$newClaimId}/police_affidavit", $name, 's3');
                    if ($path) {
                        DB::table('key_loss_claim')
                            ->where('newclaim_id', $newClaimId)
                            ->update(['police_affidavit' => $path, 'updated_at' => now()]);
                    } else {
                        Log::warning('KeyLoss police_affidavit storeAs returned falsy for claim ' . $claim->id);
                    }
                } catch (\Throwable $e) {
                    Log::warning('KeyLoss police_affidavit upload failed for claim ' . $claim->id
                        . ': ' . $e->getMessage());
                }
            }

            // ── Locks & Keys: quote_1 / quote_2 uploads (full V8 form) ──
            // V8 admin/policy/key_loss.blade.php captures two replacement
            // quotes, each with a company name, amount and an uploaded quote
            // file. Persist the two file paths on key_loss_claim (same pattern
            // as Glass). Company/amount ship as sub_claim_data.
            if (in_array($claimTypeKey, ['LOCKSANDKEYS', 'KEYLOSS'], true) && $newClaimId) {
                foreach (['quote_1', 'quote_2'] as $field) {
                    if (!$request->hasFile($field)) continue;
                    try {
                        $file = $request->file($field);
                        $name = (string) \Illuminate\Support\Str::uuid()
                              . $file->getClientOriginalName();
                        $path = $file->storeAs("Claims/{$newClaimId}/{$field}", $name, 's3');
                        if ($path) {
                            DB::table('key_loss_claim')
                                ->where('newclaim_id', $newClaimId)
                                ->update([$field => $path, 'updated_at' => now()]);
                        } else {
                            Log::warning("KeyLoss {$field} storeAs returned falsy for claim " . $claim->id);
                        }
                    } catch (\Throwable $e) {
                        Log::warning("KeyLoss {$field} upload failed for claim " . $claim->id . ': ' . $e->getMessage());
                    }
                }
            }

            // ── Glass / Windscreen: six file uploads ────────────────────
            // V8 blade renders four "After" damage photos
            // (incidentFront/Back/Right/Left). V2 adds two replacement
            // quote uploads (quote_1, quote_2). Note: when the operator
            // picks the motor Accident sub-form instead, those same four
            // photo keys still ship — but the GLASS branch only runs
            // when claim_type is Glass, so collisions are impossible.
            if ($claimTypeKey === 'GLASS' && $newClaimId) {
                foreach ([
                    'incidentFront', 'incidentBack',
                    'incidentRight', 'incidentLeft',
                    'quote_1', 'quote_2',
                ] as $field) {
                    if (!$request->hasFile($field)) continue;
                    try {
                        $file = $request->file($field);
                        $name = (string) \Illuminate\Support\Str::uuid()
                              . $file->getClientOriginalName();
                        $path = $file->storeAs("Claims/{$newClaimId}/{$field}", $name, 's3');
                        if ($path) {
                            DB::table('glass_claim')
                                ->where('newclaim_id', $newClaimId)
                                ->update([$field => $path, 'updated_at' => now()]);
                        } else {
                            Log::warning("Glass {$field} storeAs returned falsy for claim " . $claim->id);
                        }
                    } catch (\Throwable $e) {
                        Log::warning("Glass {$field} upload failed for claim " . $claim->id . ': ' . $e->getMessage());
                    }
                }
            }

            // ── Motor-accident sub-tables (create flow) ─────────────────
            // Mirrors update()'s motor-write block at the bottom of this
            // file. Writes to four legacy tables when the FE posts the
            // nested accident_details / accident_driver / accident_passengers
            // / accident_third_parties payloads. Applies to MIS Motor
            // Accident (claim_type 'Accident' → normalised 'ACCIDENT')
            // AND DOM/COM motor codes (MOTORACCIDENT,
            // MOTORTRADERSEXTERNAL, MOTORTRADERSINTERNAL) — V8 dispatches
            // all of these to the same accident blade.
            if (in_array($claimTypeKey, [
                'MOTOR', 'ACCIDENT', 'MOTORACCIDENT',
                'MOTORTRADERSEXTERNAL', 'MOTORTRADERSINTERNAL',
            ], true)) {
                $upsertMotor1to1 = function (string $table, array $fields, int $claimId) {
                    if (!\Schema::hasTable($table) || empty($fields)) return;
                    $cols = \Schema::getColumnListing($table);
                    $row  = array_intersect_key($fields, array_flip($cols));
                    if (empty($row)) return;
                    $row['claim_id'] = $claimId;
                    if (in_array('created_at', $cols, true)) $row['created_at'] = now();
                    if (in_array('updated_at', $cols, true)) $row['updated_at'] = now();
                    DB::table($table)->insert($row);
                };
                $insertMotorMany = function (string $table, array $rows, int $claimId, array $whitelist) {
                    if (!\Schema::hasTable($table) || empty($rows)) return;
                    $cols = \Schema::getColumnListing($table);
                    foreach ($rows as $row) {
                        if (!is_array($row)) continue;
                        $row = array_intersect_key($row, array_flip($whitelist));
                        $row = array_intersect_key($row, array_flip($cols));
                        // Drop entirely-empty rows so empty repeater placeholders
                        // don't pollute the table.
                        $filtered = array_filter($row, fn($v) => $v !== null && $v !== '');
                        if (empty($filtered)) continue;
                        $row['claim_id'] = $claimId;
                        if (in_array('created_at', $cols, true)) $row['created_at'] = now();
                        if (in_array('updated_at', $cols, true)) $row['updated_at'] = now();
                        DB::table($table)->insert($row);
                    }
                };

                if (!empty($validated['accident_details'])) {
                    $upsertMotor1to1('claim_accidents', $validated['accident_details'], $claim->id);
                }
                if (!empty($validated['accident_driver'])) {
                    $upsertMotor1to1('accident_driver', $validated['accident_driver'], $claim->id);
                }
                if (!empty($validated['accident_passengers']) && is_array($validated['accident_passengers'])) {
                    $insertMotorMany('claim_accident_passengers', $validated['accident_passengers'], $claim->id,
                        ['name', 'address', 'injury']);
                }
                if (!empty($validated['accident_third_parties']) && is_array($validated['accident_third_parties'])) {
                    $insertMotorMany('claim_accident_third_party', $validated['accident_third_parties'], $claim->id,
                        ['first_name', 'last_name', 'cellphone', 'address', 'make', 'model',
                         'registration_no', 'damage_details', 'injured_name', 'relationship',
                         'hospital_name', 'injured_details',
                         'first_name_insured', 'last_name_insured', 'cellphone_insured',
                         'email_insured', 'address_insured']);
                }
            }

            // ── Save to extended tables per form_template ────────────────
            $template = $validated['form_template'] ?? null;

            // Life: claim_life
            if ($template === 'life' || in_array(strtolower($validated['claim_type']), ['life', 'accidental death'])) {
                try {
                    $certPath = null;
                    if ($request->hasFile('death_certificate')) {
                        $certPath = $request->file('death_certificate')->store(
                            "MIS/{$policy->customer_id}/Claims/{$claimNumber}", 's3'
                        );
                    }
                    DB::table('claim_life')->insert([
                        'claim_id'       => $claim->id,
                        'date_of_death'  => $request->input('date_of_death'),
                        'cause_of_death' => $request->input('cause_of_death'),
                        'certificate'    => $certPath,
                        'description'    => $request->input('life_description'),
                        'created_at'     => now(), 'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) { Log::warning('claim_life insert failed: ' . $e->getMessage()); }
            }

            // Legal: claim_legal
            if ($template === 'legal' || strtolower($validated['claim_type']) === 'legal') {
                try {
                    DB::table('claim_legal')->insert([
                        'claim_id'          => $claim->id,
                        'legal_firm'        => $request->input('legal_firm'),
                        'lawyer_name'       => $request->input('lawyer_name'),
                        'legal_tel'         => $request->input('legal_tel'),
                        'legal_email'       => $request->input('legal_email'),
                        'legaloption'       => $request->input('legaloption'),
                        'representing_member' => $request->input('representing_member'),
                        'member_name'       => $request->input('member_name'),
                        'membership_id'     => $request->input('membership_id'),
                        'member_contact'    => $request->input('member_contact'),
                        'member_email'      => $request->input('member_email'),
                        'lossreported_date' => $request->input('lossreported_date'),
                        'matter_relatesto'  => $request->input('matter_relatesto'),
                        'child_financial_dependent' => $request->input('child_financial_dependent'),
                        'idforchild'        => $request->input('idforchild'),
                        'child_dob'         => $request->input('child_dob'),
                        'realestate_enquiry_from' => $request->input('realestate_enquiry_from'),
                        'arose_date'        => $request->input('arose_date'),
                        'matter_quantum'    => $request->input('matter_quantum'),
                        'course_of_action'  => $request->input('course_of_action'),
                        'jurisdiction'      => $request->input('jurisdiction'),
                        'criminalmatter_detail' => $request->input('criminalmatter_detail'),
                        'criminalmatter_charge' => $request->input('criminalmatter_charge'),
                        'lawyer_tarrif'     => $request->input('lawyer_tarrif'),
                        'declaration'       => $request->boolean('declaration') ? 1 : 0,
                        'nofalseinfo'       => $request->boolean('nofalseinfo') ? 1 : 0,
                        'signature'         => $request->boolean('signature') ? 1 : 0,
                        'created_at'        => now(), 'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) { Log::warning('claim_legal insert failed: ' . $e->getMessage()); }
            }

            // Hospital Cash: claim_hospital_cash
            if ($template === 'hospital_cash' || in_array(strtolower($validated['claim_type']), ['hospital_cash', 'hospitalcash'])) {
                try {
                    DB::table('claim_hospital_cash')->insert([
                        'claim_id'            => $claim->id,
                        'patient_name'        => $request->input('patient_name'),
                        'patient_dob'         => $request->input('patient_dob'),
                        'patient_identity_number' => $request->input('patient_identity_number'),
                        'relationship'        => $request->input('relationship'),
                        'relationship_other'  => $request->input('relationship_other'),
                        'occupation_date'     => $request->input('occupation_date'),
                        'gp_name'             => $request->input('gp_name'),
                        'gp_postal_address'   => $request->input('gp_postal_address'),
                        'gp_cellular_no'      => $request->input('gp_cellular_no'),
                        'gp_telephone_no'     => $request->input('gp_telephone_no'),
                        'gp_fax_no'           => $request->input('gp_fax_no'),
                        'hospital_name'       => $request->input('hospital_name'),
                        'hospital_tel_fax'    => $request->input('hospital_tel_fax'),
                        'admitting_doctor'    => $request->input('admitting_doctor'),
                        'admitting_doctor_tel_fax' => $request->input('admitting_doctor_tel_fax'),
                        'admission_date'      => $request->input('admission_date'),
                        'admission_time'      => $request->input('admission_time'),
                        'discharge_date'      => $request->input('discharge_date'),
                        'discharge_time'      => $request->input('discharge_time'),
                        'hospitalisation_type'=> $request->input('hospitalisation_type'),
                        'hospitalisation_reason' => $request->input('hospitalisation_reason'),
                        'accident_reported'   => $request->input('accident_reported'),
                        'symptoms_first_appeared' => $request->input('symptoms_first_appeared'),
                        'pregnancy_conception_date' => $request->input('pregnancy_conception_date'),
                        'pregnancy_delivery_date' => $request->input('pregnancy_delivery_date'),
                        'injury_date'         => $request->input('injury_date'),
                        'accident_circumstances' => $request->input('accident_circumstances'),
                        'first_consultation_date' => $request->input('first_consultation_date'),
                        'medical_scheme'      => $request->input('is_medical_scheme'),
                        'medical_scheme_name' => $request->input('medical_scheme_name'),
                        'medical_aid_number'  => $request->input('medical_aid_number'),
                        'other_insurance'     => $request->input('has_other_insurance'),
                        'other_insurance_company_name' => $request->input('other_insurance_company_name'),
                        'other_insurance_policy_numbers' => $request->input('other_insurance_policy_numbers'),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) { Log::warning('claim_hospital_cash insert failed: ' . $e->getMessage()); }
            }

            // Cellphone / Mobile & Electronic Devices (product 5).
            // Legacy writes to mobile_and_electronic_devices keyed by
            // newclaim_id. V2 previously dropped every device field
            // silently because this template had no dispatch.
            if (($template === 'cellphone' || strtolower($validated['claim_type']) === 'cellphone')
                && \Schema::hasTable('mobile_and_electronic_devices') && isset($newClaimId)) {
                try {
                    $cols = \Schema::getColumnListing('mobile_and_electronic_devices');
                    $row = array_intersect_key([
                        'newclaim_id'                   => $newClaimId,
                        'policyNumber'                  => $policy->policyNumber,
                        'claim_sub_type_id'             => $validated['claim_sub_type_id'] ?? 0,
                        'insured_name'                  => $request->input('insured_name'),
                        'email_address'                 => $request->input('email_address'),
                        'address'                       => $request->input('address'),
                        'telephone_no'                  => $request->input('contact_number'),
                        'property_stolen_damaged'       => $request->input('damage_extent'),
                        'date_time_loss_discovered'     => $request->input('lossDate'),
                        'circumstances_loss_damage'     => $request->input('descriptionofLoss'),
                        'created_at'                    => now(),
                        'updated_at'                    => now(),
                    ], array_flip($cols));
                    DB::table('mobile_and_electronic_devices')->insert(array_filter($row, fn($v) => $v !== null));
                } catch (\Throwable $e) { Log::warning('mobile_and_electronic_devices insert failed: ' . $e->getMessage()); }
            }

            activity('Claim')
                ->performedOn($claim)
                ->causedBy(auth()->user())
                ->log('Claim Registered: ' . $claimNumber . ' - Type: ' . $validated['claim_type'] . ' (template: ' . ($template ?? 'default') . ')');

            DB::commit();

            return response()->json([
                'data' => [
                    'id'           => $claim->id,
                    'claim_number' => $claimNumber,
                    'claim_type'   => $claim->claim_type,
                    'status'       => $claim->status,
                    'policy_id'    => $claim->policy_id,
                ],
                'message' => 'Claim registered successfully.',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Claim creation failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            // Return the message (not the trace) even in prod — this is an internal
            // admin tool and "Internal server error" gives users no way to self-diagnose.
            return response()->json([
                'message' => 'Failed to register claim.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Diagnostic: return the raw linkage between claim, policy, customer
     * so testers can compare V2 data vs what legacy shows.
     */
    /**
     * GET /claims/{id}/data-dump — pure diagnostic. Returns every related
     * row in every table that holds claim detail data, so we can see
     * exactly where legacy has values that V2 isn't reading.
     */
    public function dataDump($id): JsonResponse
    {
        $id = (int) $id;
        $claim = DB::table('claims')->where('id', $id)->first();
        if (!$claim) return response()->json(['error' => 'claim not found'], 404);

        $dump = ['claim' => $claim];
        $tables = [
            'new_claims'               => 'claim_number',
            'claim_accidents'          => 'claim_id',
            'claim_reserves'           => 'claim_id',
            'claim_reserves_coverages' => 'claim_id',
            'claim_assessment'         => 'claim_id',
            'claim_vehicle'            => 'claim_id',
            'claim_accident_third_party' => 'claim_id',
            'claim_attachments'        => 'claim_id',
            'claim_life'               => 'claim_id',
            'claim_legal'              => 'claim_id',
            'claim_hospital_cash'      => 'claim_id',
            'claim_edit_log'           => 'claim_id',
        ];
        foreach ($tables as $table => $col) {
            if (!\Schema::hasTable($table)) {
                $dump[$table] = ['_skipped' => "table {$table} does not exist"];
                continue;
            }
            try {
                $val = $col === 'claim_number' ? $claim->claim_number : $id;
                $rows = DB::table($table)->where($col, $val)->get();
                $dump[$table] = [
                    'count'    => $rows->count(),
                    'key'      => "{$col}={$val}",
                    'rows'     => $rows->take(3)->toArray(),
                ];
            } catch (\Throwable $e) {
                $dump[$table] = ['_error' => $e->getMessage()];
            }
        }
        return response()->json($dump);
    }

    public function debugLinkage($id): JsonResponse
    {
        $id = (int) $id;
        $claim = DB::table('claims')->where('id', $id)->first();
        if (!$claim) return response()->json(['error' => 'Claim not found'], 404);

        $policy = $claim->policy_id
            ? DB::table('policies')->where('id', $claim->policy_id)->first()
            : null;
        $product = $policy && $policy->product_id
            ? DB::table('bundled_products')->where('id', $policy->product_id)->first()
            : null;
        $customer = $claim->customer_id
            ? DB::table('customer')->where('id', $claim->customer_id)->first(['id', 'firstName', 'lastName', 'mobileNumber', 'email'])
            : null;
        $newClaim = DB::table('new_claims')->where('claim_number', $claim->claim_number)->first();

        // Audit trail — any prior policy_id value recorded in claim_edit_log
        $history = [];
        if (\Schema::hasTable('claim_edit_log')) {
            $history = DB::table('claim_edit_log')
                ->where('claim_id', $id)
                ->where('field', 'policy_id')
                ->orderByDesc('id')
                ->limit(10)
                ->get();
        }

        // Any duplicate claim-numbers?
        $duplicates = DB::table('claims')
            ->where('claim_number', $claim->claim_number)
            ->where('id', '!=', $id)
            ->select('id', 'policy_id', 'customer_id', 'created_at')
            ->get();

        return response()->json([
            'claim' => [
                'id'           => $claim->id,
                'claim_number' => $claim->claim_number,
                'claim_type'   => $claim->claim_type,
                'policy_id'    => $claim->policy_id,
                'customer_id'  => $claim->customer_id,
                'status'       => $claim->status,
                'created_at'   => $claim->created_at,
            ],
            'policy'     => $policy,
            'product'    => $product,
            'customer'   => $customer,
            'new_claim'  => $newClaim,
            'duplicates' => $duplicates,
            'policy_change_history' => $history,
        ]);
    }

    /**
     * Re-link a claim to a different policy (fixes wrong-policy-linked claims).
     * Accepts either policy_id (numeric) or policy_number (string).
     */
    public function relinkPolicy(Request $request, $id): JsonResponse
    {
        $id = (int) $id;
        $validated = $request->validate([
            'policy_id'     => 'nullable',
            'policy_number' => 'nullable|string',
        ]);
        if (empty($validated['policy_id']) && empty($validated['policy_number'])) {
            return response()->json(['error' => 'Provide policy_id or policy_number.'], 422);
        }
        $claim = Claim::findOrFail($id);
        if ($claim->status === 'Closed') {
            return response()->json(['error' => 'Closed claims cannot be relinked. Reopen first.'], 422);
        }

        $policy = !empty($validated['policy_id']) && is_numeric($validated['policy_id'])
            ? Policy::find($validated['policy_id'])
            : Policy::where('policyNumber', $validated['policy_number'] ?? $validated['policy_id'])->first();

        if (!$policy) return response()->json(['error' => 'Target policy not found.'], 404);

        $oldPolicyId = $claim->policy_id;
        $claim->policy_id   = $policy->id;
        $claim->customer_id = $policy->customer_id;
        $claim->agent_id    = $policy->agent_id;
        $claim->save();

        // Edit log
        try {
            DB::table('claim_edit_log')->insert([
                'claim_id'   => $claim->id,
                'field'      => 'policy_id',
                'old_value'  => (string) $oldPolicyId,
                'new_value'  => (string) $policy->id,
                'updated_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) { /* log table optional */ }

        return response()->json([
            'message' => "Claim re-linked to {$policy->policyNumber}.",
            'policy'  => [
                'id'            => $policy->id,
                'policy_number' => $policy->policyNumber,
                'product_id'    => $policy->product_id,
            ],
        ]);
    }

    /**
     * Validate a claim's vehicle plate against the policy term's captured
     * vehicle list (the same list the FE dropdown shows via
     * GET /policies/{id}/vehicles?action_id=). Vehicles are snapshotted per
     * policy_actions row; rows are manually soft-deleted (the Vehicle model
     * has no SoftDeletes trait), so whereNull('deleted_at') is mandatory.
     *
     * Returns an error message when the term HAS vehicles and the plate is
     * not among them; null when the plate is valid OR the term has no
     * captured vehicles (free text stays accepted — FE fallback parity).
     */
    private function validatePlateForPolicyAction(int $policyId, ?int $actionId, string $plate): ?string
    {
        if (!$actionId) {
            // Latest-term resolution must stay in LOCKSTEP with the dropdown
            // feed (PolicyController::vehicles default) — which intentionally
            // does NOT filter deleted_at on policy_actions. Filtering here
            // would validate against a different action than the one whose
            // plates the FE showed whenever the newest action is soft-deleted,
            // 422-ing plates the dropdown itself offered. When the resolved
            // action has no live vehicle rows the empty-plates branch below
            // allows free text — matching the endpoint's all-actions fallback.
            $actionId = (int) DB::table('policy_actions')
                ->where('policy_id', $policyId)
                ->max('id');
        }
        if (!$actionId) {
            return null; // no terms at all (e.g. MIS) — nothing to check against
        }

        $plates = DB::table('vehicle')
            ->where('policy_id', $policyId)
            ->where('action_id', $actionId)
            ->whereNull('deleted_at')
            ->pluck('vehiclePlate')
            ->filter()
            ->map(fn ($p) => strtoupper(trim((string) $p)));

        if ($plates->isEmpty()) {
            return null;
        }

        if (!$plates->contains(strtoupper(trim($plate)))) {
            return "Vehicle plate '{$plate}' is not on the selected policy term. Pick a plate from that term's vehicle list.";
        }

        return null;
    }

    /**
     * Edit claim — allowed if status != Closed. Tracks all changes in claim_edit_log.
     */
    /**
     * Policy action terms selectable for a claim's Classification edit
     * dropdown. Same query/shape as claimTypesByPolicy's `actions` (exclude
     * cancel-type terms, order chronologically) so the detail-page editor
     * offers exactly what the create flow offered.
     */
    public function policyActions($id): JsonResponse
    {
        $id = (int) $id;
        $claim = Claim::findOrFail($id);
        if (!$claim->policy_id) {
            return response()->json(['data' => []]);
        }
        $actions = DB::table('policy_actions')
            ->where('policy_id', $claim->policy_id)
            ->whereNull('deleted_at')
            ->where('transaction_type', '!=', 'CANCEL')
            ->whereRaw('LOWER(transaction_type) NOT LIKE ?', ['%cancel%'])
            ->orderBy('effective_from', 'ASC')
            ->get(['id', 'transaction_type', 'status', 'effective_from', 'effective_to']);

        return response()->json([
            'data' => $actions->map(fn($a) => [
                'id'              => $a->id,
                'transactionType' => $a->transaction_type,
                'status'          => $a->status,
                'effectiveFrom'   => $a->effective_from,
                'effectiveTo'     => $a->effective_to,
            ])->values(),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $id = (int) $id;
        $claim = Claim::findOrFail($id);

        // Only editable if NOT Closed
        if ($claim->status === 'Closed') {
            return response()->json(['message' => 'Closed claims cannot be edited. Reopen first.'], 422);
        }

        // Claim-type change is allowed for all roles with no preconditions
        // (including when reserves/payments already exist).

        $validated = $request->validate([
            'claim_type'            => 'nullable|string|max:100',
            'claim_sub_type'        => 'nullable|string|max:100',
            'category'              => 'nullable|string|max:100',
            'incident_date'         => 'nullable|date_format:Y-m-d',
            'incident_time'         => 'nullable|string|max:10',
            'incident_location'     => 'nullable|string|max:500',
            'incident_description'  => 'nullable|string|max:2000',
            'type_of_loss'          => 'nullable|string|max:100',
            'event_name'            => 'nullable|string|max:200',
            'is_motor_claim'        => 'nullable|boolean',
            'vehicle_plate'         => 'nullable|string|max:50',
            'catastrophe_loss'      => 'nullable|boolean',
            'attorney_involved'     => 'nullable|boolean',
            'co_attorney_involved'  => 'nullable|boolean',
            'dfs_complaint'         => 'nullable|boolean',
            'recovery_involved'     => 'nullable|boolean',
            'third_party_insured_elsewhere' => 'nullable|boolean',
            'driver_as_insured'     => 'nullable|boolean',
            'weather_condition'     => 'nullable|string|max:50',
            'fault_party'           => 'nullable|string|max:50',
            'reason'                => 'nullable|string|max:1000',
            'reported_by'           => 'nullable|string|max:100',
            'reported_date'         => 'nullable|date_format:Y-m-d',
            'registered_claim'      => 'nullable|string|max:255',
            'claim_allocated_to'    => 'nullable|integer|exists:users,id',
            'claim_allocated_on'    => 'nullable|date_format:Y-m-d',

            // Policy action / term the claim is filed against. Editable from
            // the Claim Details > Classification card; persisted to the
            // claims.policy_action_id column by the generic writer below.
            'policy_action_id'      => 'nullable|integer',

            // ── new_claims shadow row fields ──
            'location_id'               => 'nullable|integer',
            'claim_sub_type_id'         => 'nullable|integer',
            'date_of_loss'              => 'nullable|date_format:Y-m-d',
            'description_of_loss'       => 'nullable|string|max:5000',
            'claim_reported_by'         => 'nullable|string|max:100',
            'reportedByBrokerAgent'     => 'nullable|string|max:50',
            'service_representative_id' => 'nullable|integer|exists:users,id',
            'reserve_amount'            => 'nullable|numeric',
            'paid_amount'               => 'nullable|numeric',
            'date_first_visited'        => 'nullable|date_format:Y-m-d',
            'submission_date'           => 'nullable|date_format:Y-m-d',
            'primary_attorney_assigned_id' => 'nullable|string|max:50',
            'p_a_assigned_date'            => 'nullable|date_format:Y-m-d',
            'co_attorney_assigned_id'      => 'nullable|string|max:50',
            'c_a_assigned_date'            => 'nullable|date_format:Y-m-d',
            'tp_insured_elsewhere_email'=> 'nullable|email|max:150',

            // ── MIS type-specific (same fields the create flow accepts) ──
            // Life
            'date_of_death'    => 'nullable|date_format:Y-m-d',
            'cause_of_death'   => 'nullable|string|max:255',
            'life_description' => 'nullable|string|max:2000',
            'death_certificate' => 'nullable|file|max:10240',
            // Legal
            'legal_firm'       => 'nullable|string|max:255',
            'lawyer_name'      => 'nullable|string|max:255',
            'legal_tel'        => 'nullable|string|max:30',
            'legal_email'      => 'nullable|email|max:150',
            'legaloption'      => 'nullable|string|max:5',
            'representing_member' => 'nullable|string|max:5',
            'lawyer_tarrif'    => 'nullable|string|max:10',
            'member_name'      => 'nullable|string|max:255',
            'membership_id'    => 'nullable|string|max:50',
            'member_contact'   => 'nullable|string|max:30',
            'member_email'     => 'nullable|email|max:150',
            'lossreported_date'=> 'nullable|date_format:Y-m-d',
            'matter_relatesto' => 'nullable|string|max:10',
            'matter_quantum'   => 'nullable|string|max:255',
            'course_of_action' => 'nullable|string',
            'jurisdiction'     => 'nullable|string|max:255',
            // Hospital Cash
            'patient_name'     => 'nullable|string|max:255',
            'patient_dob'      => 'nullable|date_format:Y-m-d',
            'patient_identity_number' => 'nullable|string|max:50',
            'relationship'     => 'nullable|string|max:50',
            'occupation_date'  => 'nullable|date_format:Y-m-d',
            'hospital_name'    => 'nullable|string|max:255',
            'admitting_doctor' => 'nullable|string|max:255',
            'admission_date'   => 'nullable|date_format:Y-m-d',
            'admission_time'   => 'nullable|string|max:10',
            'discharge_date'   => 'nullable|date_format:Y-m-d',
            'discharge_time'   => 'nullable|string|max:10',
            'hospitalisation_type' => 'nullable|string|max:30',
            'injury_date'      => 'nullable|date_format:Y-m-d',
            'accident_circumstances' => 'nullable|string',
            'is_medical_scheme'=> 'nullable|string|max:5',
            'medical_scheme_name' => 'nullable|string|max:255',
            'medical_aid_number'  => 'nullable|string|max:100',

            // DOMG/COMG sub-claim payload (JSON or nested object)
            'sub_claim_data'   => 'nullable',

            // ── Motor-accident sub-table payloads (vehicle form_template) ──
            // These map to the legacy tables legacy's admin.claims.claim_details
            // blade writes/reads: claim_accidents (1:1), accident_driver (1:1),
            // claim_accident_passengers (1:N), claim_accident_third_party (1:N).
            // Accepted as nested arrays so the FE can post the whole accident
            // block in a single update call instead of chaining separate
            // mutations.
            'accident_details'                      => 'nullable|array',
            'accident_details.place_of_accident'    => 'nullable|string|max:500',
            'accident_details.time_of_accident'     => 'nullable|string|max:20',
            'accident_details.date_of_accident'     => 'nullable|date_format:Y-m-d',
            'accident_details.detail_of_accident'   => 'nullable|string|max:5000',
            'accident_details.purpose_of_trip'      => 'nullable|string|max:255',
            'accident_details.fault_party'          => 'nullable|string|max:50',
            'accident_details.third_party'          => 'nullable|boolean',

            'accident_driver'                       => 'nullable|array',
            'accident_driver.name'                  => 'nullable|string|max:255',
            'accident_driver.cellphone'             => 'nullable|string|max:30',
            'accident_driver.dob'                   => 'nullable|date_format:Y-m-d',
            'accident_driver.address'               => 'nullable|string|max:500',
            'accident_driver.license'               => 'nullable|string|max:100',
            'accident_driver.purpose'               => 'nullable|string|max:255',

            'accident_passengers'                   => 'nullable|array',
            'accident_passengers.*.name'            => 'nullable|string|max:255',
            'accident_passengers.*.address'         => 'nullable|string|max:500',
            'accident_passengers.*.injury'          => 'nullable|string|max:1000',

            'accident_third_parties'                       => 'nullable|array',
            'accident_third_parties.*.first_name'          => 'nullable|string|max:100',
            'accident_third_parties.*.last_name'           => 'nullable|string|max:100',
            'accident_third_parties.*.cellphone'           => 'nullable|string|max:30',
            'accident_third_parties.*.address'             => 'nullable|string|max:500',
            'accident_third_parties.*.make'                => 'nullable|string|max:100',
            'accident_third_parties.*.model'               => 'nullable|string|max:100',
            'accident_third_parties.*.registration_no'     => 'nullable|string|max:50',
            'accident_third_parties.*.damage_details'      => 'nullable|string|max:2000',
            'accident_third_parties.*.injured_name'        => 'nullable|string|max:255',
            'accident_third_parties.*.relationship'        => 'nullable|string|max:100',
            'accident_third_parties.*.hospital_name'       => 'nullable|string|max:255',
            'accident_third_parties.*.injured_details'     => 'nullable|string|max:2000',
        ]);

        // Date of Loss locks once a claim is Approved. By that point a PO has
        // issued against the claim, so the loss date is a settled fact that must
        // not shift underneath a payment (claims-team request 2026-08-17).
        // Dropping it from $validated makes it read-only on the SERVER — for the
        // claims table AND the new_claims shadow row below — while every OTHER
        // edit on the claim still saves. (Closed is already blocked above.)
        if ($claim->status === 'Approved' && array_key_exists('date_of_loss', $validated)) {
            unset($validated['date_of_loss']);
        }

        // Fold coverage-based claim-type codes that aren't ENUM members
        // (e.g. MOTORACCIDENT → Motor, LOCKSANDKEYS → Key Loss) onto the
        // canonical ENUM value BEFORE any write, so claims.claim_type
        // actually persists instead of coercing to ''. Same call is made in
        // store() so create and edit behave identically.
        if (array_key_exists('claim_type', $validated) && $validated['claim_type'] !== null) {
            $validated['claim_type'] = $this->mapClaimTypeToEnum($validated['claim_type']);
        }

        // A changed policy term must belong to this claim's policy and must
        // not be a cancel-type term (mirrors the create-flow guard so the
        // operator can't link a claim to an arbitrary or cancelled term).
        if (array_key_exists('policy_action_id', $validated) && $validated['policy_action_id'] !== null) {
            $action = DB::table('policy_actions')
                ->where('id', $validated['policy_action_id'])
                ->where('policy_id', $claim->policy_id)
                ->whereNull('deleted_at')
                ->first(['id', 'transaction_type']);
            if (!$action) {
                return response()->json(['message' => 'Selected policy term was not found on this policy.'], 422);
            }
            $txType = strtolower((string) $action->transaction_type);
            if ($txType === 'cancel' || str_contains($txType, 'cancel')) {
                return response()->json([
                    'message' => "Claims cannot be linked to a cancel-type policy term ({$action->transaction_type}).",
                ], 422);
            }
        }

        // Term-scoped plate check (mirrors store): a changed plate must be on
        // the vehicle list of the effective term — incoming term if supplied,
        // else the claim's stored one. Terms with no captured vehicles keep
        // accepting free text. An UNCHANGED plate is never re-validated —
        // edit surfaces resubmit the whole form, and a legacy plate that
        // predates the dropdown must not block unrelated edits.
        $plateUnchanged = isset($validated['vehicle_plate'])
            && strcasecmp(trim((string) $validated['vehicle_plate']), trim((string) $claim->vehicle_plate)) === 0;
        if (!empty($validated['vehicle_plate']) && !$plateUnchanged && $claim->policy_id) {
            $effectiveActionId = $validated['policy_action_id'] ?? $claim->policy_action_id;
            $plateError = $this->validatePlateForPolicyAction(
                (int) $claim->policy_id,
                $effectiveActionId ? (int) $effectiveActionId : null,
                $validated['vehicle_plate']
            );
            if ($plateError) {
                return response()->json(['message' => $plateError], 422);
            }
        }

        // ── Goods In Transit (GIT) pre-validation on update ──────────────
        // Mirrors the create-flow rules; only fires when the EFFECTIVE
        // claim_type is GOODSINTRANSIT (request override OR existing claim).
        // Done before any DB writes so a 422 returns cleanly.
        $effectiveClaimTypeForGit = $validated['claim_type'] ?? $claim->claim_type;
        if ($this->normalizeClaimTypeKey($effectiveClaimTypeForGit) === 'GOODSINTRANSIT'
            && ($request->input('sub_claim_data') !== null || $request->hasFile('copy_of_contract'))
        ) {
            // $isCreate=false: skip the bulk required-fields check and the
            // file-required check on update. The existing GIT row already
            // has these populated; the operator may be sending only the
            // fields they changed.
            $gitError = $this->validateGoodsInTransitPayload($request, false);
            if ($gitError !== null) return $gitError;
        }

        // ── Track changes for the audit log (primary claims table only) ──
        // Legacy claim_edit_log is keyed by claims.id, so type-specific
        // table changes don't round-trip through it — they're logged via
        // the activity() log below.
        $claimColumns = \Schema::hasTable('claims') ? \Schema::getColumnListing('claims') : [];
        $claimUpdates = [];
        $changes      = [];
        foreach ($validated as $field => $newValue) {
            if ($newValue === null || !in_array($field, $claimColumns, true)) continue;
            $oldValue = $claim->$field ?? null;
            if ((string) $oldValue !== (string) $newValue) {
                $changes[] = [
                    'claim_id'    => $id,
                    'field'       => $field,
                    'old_value'   => $oldValue,
                    'new_value'   => $newValue,
                    'changed_by'  => auth()->id(),
                    'changed_by_name' => auth()->user() ? auth()->user()->firstName . ' ' . auth()->user()->lastName : null,
                    'created_at'  => now(),
                ];
                $claimUpdates[$field] = $newValue;
            }
        }

        // ── new_claims shadow row update ────────────────────────────────
        // Legacy admin pages read a lot of fields from here. Store-time we
        // dual-wrote; update-time we mirror the same field translations
        // (claim_allocated_on → claims_allocated_on, description →
        // description_of_loss, etc.) so both the V2 UI and the legacy
        // admin UI stay in lockstep with the primary claim.
        $newClaimsUpdates = [];
        if (\Schema::hasTable('new_claims')) {
            $newClaimsCols = \Schema::getColumnListing('new_claims');
            // Direct-mapping fields: key matches column name verbatim.
            $direct = [
                'claim_type', 'location_id', 'is_motor_claim', 'vehicle_plate',
                'co_attorney_involved', 'attorney_involved', 'claim_reported_by',
                'reportedByBrokerAgent',
                'claim_sub_type_id', 'type_of_loss', 'date_of_loss',
                'service_representative_id', 'catastrophe_loss', 'event_name',
                'description_of_loss', 'dfs_complaint', 'claim_allocated_to',
                'reserve_amount', 'paid_amount', 'date_first_visited', 'submission_date',
                'primary_attorney_assigned_id', 'p_a_assigned_date',
                'co_attorney_assigned_id',      'c_a_assigned_date',
                'driver_as_insured',
                'third_party_insured_elsewhere', 'tp_insured_elsewhere_email',
            ];
            foreach ($direct as $f) {
                if (array_key_exists($f, $validated) && $validated[$f] !== null
                    && in_array($f, $newClaimsCols, true)) {
                    $newClaimsUpdates[$f] = $validated[$f];
                }
            }
            // V2 singular → legacy plural name for claim-allocation date.
            if (!empty($validated['claim_allocated_on'])
                && in_array('claims_allocated_on', $newClaimsCols, true)) {
                $newClaimsUpdates['claims_allocated_on'] = $validated['claim_allocated_on'];
            }
            // Fallback: if caller gave us incident_description but not
            // description_of_loss, populate the legacy column from it.
            if (empty($newClaimsUpdates['description_of_loss'])
                && !empty($validated['incident_description'])
                && in_array('description_of_loss', $newClaimsCols, true)) {
                $newClaimsUpdates['description_of_loss'] = $validated['incident_description'];
            }
            if (!empty($newClaimsUpdates)) {
                // Upsert, not update. Claims created via the Claim Tracker
                // (claims.source = 'claims-tracker') never got a new_claims
                // shadow row — only the Graphite create flow (store()) dual-
                // writes one. A plain ->update() matched 0 rows for those
                // claims, so Classification & Allocation edits were silently
                // dropped. If the row exists, update it as before; if not,
                // seed a valid one from the primary claim + policy (mirroring
                // store()'s dual-write) and overlay the edited fields.
                $ncExists = DB::table('new_claims')
                    ->where('claim_number', $claim->claim_number)
                    ->exists();

                if ($ncExists) {
                    if (in_array('updated_at', $newClaimsCols, true)) {
                        $newClaimsUpdates['updated_at'] = now();
                    }
                    DB::table('new_claims')
                        ->where('claim_number', $claim->claim_number)
                        ->update($newClaimsUpdates);
                } else {
                    // No shadow row yet (Claim-Tracker claim). Seed a minimal
                    // valid one, then overlay the edited Classification &
                    // Allocation fields.
                    $seedId = $this->ensureNewClaimShadow($claim);
                    if ($seedId) {
                        if (in_array('updated_at', $newClaimsCols, true)) {
                            $newClaimsUpdates['updated_at'] = now();
                        }
                        DB::table('new_claims')->where('id', $seedId)->update($newClaimsUpdates);
                    }
                }
            }
        }

        // ── MIS type-specific table updates ────────────────────────────
        // Upsert (update or insert) keyed by claim_id so that an edit can
        // also seed a row that didn't exist at create time (e.g. a legacy
        // claim that predates the claim_life migration).
        $misUpdates = [];

        $lifeFields = [
            'date_of_death'    => 'date_of_death',
            'cause_of_death'   => 'cause_of_death',
            'life_description' => 'description',
        ];
        $misUpdates['claim_life'] = [];
        foreach ($lifeFields as $src => $dst) {
            if (array_key_exists($src, $validated) && $validated[$src] !== null) {
                $misUpdates['claim_life'][$dst] = $validated[$src];
            }
        }
        if ($request->hasFile('death_certificate')) {
            $policy = DB::table('policies')->where('id', $claim->policy_id)->first();
            $custId = $policy ? $policy->customer_id : 0;
            $misUpdates['claim_life']['certificate'] = $request->file('death_certificate')
                ->store("MIS/{$custId}/Claims/{$claim->claim_number}", 's3');
        }

        $legalFields = [
            'legal_firm', 'lawyer_name', 'legal_tel', 'legal_email', 'legaloption',
            'representing_member', 'lawyer_tarrif', 'member_name', 'membership_id',
            'member_contact', 'member_email', 'lossreported_date', 'matter_relatesto',
            'matter_quantum', 'course_of_action', 'jurisdiction',
        ];
        $misUpdates['claim_legal'] = [];
        foreach ($legalFields as $f) {
            if (array_key_exists($f, $validated) && $validated[$f] !== null) {
                $misUpdates['claim_legal'][$f] = $validated[$f];
            }
        }

        $hcFields = [
            'patient_name', 'patient_dob', 'patient_identity_number', 'relationship',
            'occupation_date', 'hospital_name', 'admitting_doctor',
            'admission_date', 'admission_time', 'discharge_date', 'discharge_time',
            'hospitalisation_type', 'injury_date', 'accident_circumstances',
        ];
        $misUpdates['claim_hospital_cash'] = [];
        foreach ($hcFields as $f) {
            if (array_key_exists($f, $validated) && $validated[$f] !== null) {
                $misUpdates['claim_hospital_cash'][$f] = $validated[$f];
            }
        }
        // HC has some field renames between V2 input and legacy column
        if (array_key_exists('is_medical_scheme', $validated) && $validated['is_medical_scheme'] !== null) {
            $misUpdates['claim_hospital_cash']['medical_scheme'] = $validated['is_medical_scheme'];
        }
        if (array_key_exists('medical_scheme_name', $validated) && $validated['medical_scheme_name'] !== null) {
            $misUpdates['claim_hospital_cash']['medical_scheme_name'] = $validated['medical_scheme_name'];
        }
        if (array_key_exists('medical_aid_number', $validated) && $validated['medical_aid_number'] !== null) {
            $misUpdates['claim_hospital_cash']['medical_aid_number'] = $validated['medical_aid_number'];
        }

        foreach ($misUpdates as $table => $fields) {
            if (empty($fields)) continue;
            if (!\Schema::hasTable($table)) continue;
            $cols = \Schema::getColumnListing($table);
            $row  = array_intersect_key($fields, array_flip($cols));
            if (empty($row)) continue;
            // Upsert — update if a row exists, else insert.
            $existsId = DB::table($table)->where('claim_id', $id)->value('id');
            if ($existsId) {
                if (in_array('updated_at', $cols, true)) $row['updated_at'] = now();
                DB::table($table)->where('id', $existsId)->update($row);
            } else {
                $row['claim_id']   = $id;
                if (in_array('created_at', $cols, true)) $row['created_at'] = now();
                if (in_array('updated_at', $cols, true)) $row['updated_at'] = now();
                DB::table($table)->insert($row);
            }
        }

        // ── DOMG/COMG sub-claim table update ───────────────────────────
        // Mirror of the create-flow dispatch map so 'Business Interruption'
        // edits land in business_interruption, Burglary edits in burglary,
        // etc. Keyed by newclaim_id so the FK chain stays intact.
        $subClaimData = $request->input('sub_claim_data');
        if ($subClaimData !== null && !is_array($subClaimData)) {
            $decoded = json_decode((string) $subClaimData, true);
            $subClaimData = is_array($decoded) ? $decoded : null;
        }
        if (is_array($subClaimData) && !empty($subClaimData)) {
            $subTableMap = [
                'BUSINESSINTERRUPTION'       => 'business_interruption',
                // graphiteBWV8 main.blade.php groups BUSINESSALLRISKS,
                // ELECTRONICEQUIPMENT and PERSONALALLRISKS onto the same
                // all_risk_and_electronic_equipment table; ALLRISK is a
                // generic alias some callers use.
                'BUSINESSALLRISKS'           => 'all_risk_and_electronic_equipment',
                'ELECTRONICEQUIPMENT'        => 'all_risk_and_electronic_equipment',
                'PERSONALALLRISKS'           => 'all_risk_and_electronic_equipment',
                'ALLRISK'                    => 'all_risk_and_electronic_equipment',
                'CONTRACTORSALLRISKS'                => 'contractors_all_risks_public_liability',
                'CONTRACTORSALLRISKSPUBLICLIABILITY' => 'contractors_all_risks_public_liability',
                'CARPL'                              => 'contractors_all_risks_public_liability',
                // graphiteBWV8 main.blade.php routes ERECTIONALLRISK to
                // erection_all_risk.blade.php + erection_all_risk_claims.
                'ERECTIONALLRISK'                    => 'erection_all_risk_claims',
                'PLANTALLRISKS'                      => 'plant_all_risks_claims',
                'MACHINERYBREAKDOWN'                 => 'machinery_breakdown_claims',
                'MACHINERYBREAKDOWNLOSSOFPROFIT'     => 'machinery_breakdown_lop_claims',
                'DIRECTORSOFFICERSLIABILITY'         => 'directors_officers_liability_claims',
                'MARINECARGOONCEOFF'                 => 'marine_cargo_once_off_claims',
                'MARINECARGOOPENCOVER'               => 'marine_cargo_open_cover_claims',
                'MEDICALMALPRACTICE'                 => 'medical_malpractice_claims',
                'GLASS'                              => 'glass_claim',
                'LOCKSANDKEYS'                       => 'key_loss_claim',
                // MIS Key Loss ships claim_type 'Key Loss' which normalises
                // to 'KEYLOSS'; DOM/COM ships the V8 code 'LOCKSANDKEYS'.
                // Register both so dispatch works regardless of source.
                'KEYLOSS'                            => 'key_loss_claim',
                'FIDELITYGUARANTEE'          => 'fidelity_guarantee',
                'PROPERTYLOSSDAMAGE'         => 'property_loss_damage',
                'BUILDINGSCOMBINED'          => 'property_loss_damage',
                'ACCIDENTALDAMAGE'           => 'property_loss_damage',
                'HOUSEHOLDERS'               => 'property_loss_damage',
                'HOUSEOWNERS'                => 'property_loss_damage',
                'HOUSEOWNERBUILDINGS'        => 'property_loss_damage',
                'HOUSEHOLDERSCONTENTS'       => 'property_loss_damage',
                'PUBLICLIABILITY'            => 'public_liability',
                'LIABILITY'                  => 'public_liability',
                'WORKERSCOMPENSATION'        => 'workers_compensation',
                'STATEDBENEFITS'             => 'workers_compensation',
                'DEFECTIVEWORKMANSHIP'       => 'defective_workmanship',
                'THEFT'                      => 'burglary',
                'BURGLARY'                   => 'burglary',
                'BURGLARYTHEFT'              => 'burglary',
                'MONEY'                      => 'burglary',
                'FIRE'                       => 'fire',
                'PROPERTYDAMAGE'             => 'fire',
                'GOODSINTRANSIT'             => 'goods_in_transit_claim',
                'TRAVELINSURANCE'            => 'travel_insurance_claim',
                'PROFESSIONALINDEMNITY'      => 'professional_indemnity_claims',
                'MOBILEELECTRONICDEVICES'    => 'mobile_and_electronic_devices_claim',
                'OFFICECONTENTS'             => 'mobile_and_electronic_devices_claim',
                'MOBILEANDELECTRONICDEVICES' => 'mobile_and_electronic_devices_claim',
            ];
            $effectiveType = $validated['claim_type'] ?? $claim->claim_type;
            $key = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $effectiveType));
            $subTable = $subTableMap[$key] ?? null;
            if ($subTable && \Schema::hasTable($subTable)) {
                $newclaimId = DB::table('new_claims')
                    ->where('claim_number', $claim->claim_number)
                    ->value('id');
                // Claim-Tracker claims may have no shadow row — seed one so the
                // sub-claim (e.g. glass_claim) edit has a newclaim_id to attach to.
                if (!$newclaimId) {
                    $newclaimId = $this->ensureNewClaimShadow($claim);
                }
                if ($newclaimId) {
                    $subCols = \Schema::getColumnListing($subTable);
                    $row = array_intersect_key($subClaimData, array_flip($subCols));
                    if (!empty($row)) {
                        $existsId = DB::table($subTable)->where('newclaim_id', $newclaimId)->value('id');
                        if ($existsId) {
                            if (in_array('updated_at', $subCols, true)) $row['updated_at'] = now();
                            DB::table($subTable)->where('id', $existsId)->update($row);
                        } else {
                            $row['newclaim_id'] = $newclaimId;
                            if (in_array('created_at', $subCols, true)) $row['created_at'] = now();
                            if (in_array('updated_at', $subCols, true)) $row['updated_at'] = now();
                            DB::table($subTable)->insert($row);
                        }
                    }

                    // ── GIT copy_of_contract: upload + persist path ──
                    // Use $file->storeAs() to match the pattern V2 uses
                    // for document_1/2/3 and death_certificate uploads
                    // (the bucket policy rejects public-read ACLs, which
                    // is what `Storage::disk('s3')->put(..., 'public')`
                    // tries to set). Failure logs and continues — the
                    // textual fields are already saved.
                    if ($key === 'GOODSINTRANSIT' && $request->hasFile('copy_of_contract')) {
                        try {
                            $file = $request->file('copy_of_contract');
                            $name = (string) \Illuminate\Support\Str::uuid()
                                  . $file->getClientOriginalName();
                            $path = $file->storeAs("Claims/{$newclaimId}", $name, 's3');
                            if ($path) {
                                DB::table($subTable)
                                    ->where('newclaim_id', $newclaimId)
                                    ->update(['copy_of_contract' => $path, 'updated_at' => now()]);
                            } else {
                                Log::warning('GIT copy_of_contract storeAs returned falsy for claim ' . $claim->id);
                            }
                        } catch (\Throwable $e) {
                            Log::warning('GIT copy_of_contract upload failed for claim ' . $claim->id
                                . ': ' . $e->getMessage());
                        }
                    }
                    // ── Fire contract_of_agreement: upload + persist ──
                    if ($key === 'FIRE' && $request->hasFile('contract_of_agreement')) {
                        try {
                            $file = $request->file('contract_of_agreement');
                            $name = (string) \Illuminate\Support\Str::uuid()
                                  . $file->getClientOriginalName();
                            $path = $file->storeAs("Claims/{$newclaimId}/contract_of_agreement", $name, 's3');
                            if ($path) {
                                DB::table($subTable)
                                    ->where('newclaim_id', $newclaimId)
                                    ->update(['contract_of_agreement' => $path, 'updated_at' => now()]);
                            } else {
                                Log::warning('Fire contract_of_agreement storeAs returned falsy for claim ' . $claim->id);
                            }
                        } catch (\Throwable $e) {
                            Log::warning('Fire contract_of_agreement upload failed for claim ' . $claim->id
                                . ': ' . $e->getMessage());
                        }
                    }
                    // ── CARPL: two file uploads (works_claim_*) ──
                    if (in_array($key, ['CONTRACTORSALLRISKS', 'CONTRACTORSALLRISKSPUBLICLIABILITY', 'CARPL'], true)) {
                        foreach (['works_claim_documentary_evidence', 'works_claim_bill_of_quantities'] as $field) {
                            if (!$request->hasFile($field)) continue;
                            try {
                                $file = $request->file($field);
                                $name = (string) \Illuminate\Support\Str::uuid()
                                      . $file->getClientOriginalName();
                                $path = $file->storeAs("Claims/{$newclaimId}/{$field}", $name, 's3');
                                if ($path) {
                                    DB::table($subTable)
                                        ->where('newclaim_id', $newclaimId)
                                        ->update([$field => $path, 'updated_at' => now()]);
                                } else {
                                    Log::warning("CARPL {$field} storeAs returned falsy for claim " . $claim->id);
                                }
                            } catch (\Throwable $e) {
                                Log::warning("CARPL {$field} upload failed for claim " . $claim->id . ': ' . $e->getMessage());
                            }
                        }
                    }
                    // ── Travel Insurance: up to 25 file uploads ──
                    // Mirrors the store() loop. Re-uploading a file
                    // replaces the previously persisted path; missing
                    // files preserve existing values. The textual columns
                    // were already handled by the generic sub-table
                    // update above.
                    if ($key === 'TRAVELINSURANCE') {
                        $travelFiles = [
                            'compulsory_doc_proof_of_residence',
                            'compulsory_doc_claim_form',
                            'compulsory_doc_insurance_policy',
                            'compulsory_doc_detailed_letter',
                            'compulsory_doc_receipts',
                            'compulsory_doc_passport_copy',
                            'medical_dental_care_doc_1',
                            'medical_dental_care_doc_2',
                            'medical_dental_care_doc_3',
                            'claim_delayed_luggage_doc_1',
                            'claim_delayed_luggage_doc_2',
                            'claim_delayed_luggage_doc_3',
                            'claim_loss_personal_doc_doc_1',
                            'claim_loss_personal_doc_doc_2',
                            'claim_lost_luggage_doc_1',
                            'claim_lost_luggage_doc_2',
                            'claim_lost_luggage_doc_3',
                            'claim_lost_luggage_doc_4',
                            'claim_trip_cancel_doc_1',
                            'claim_trip_cancel_doc_2',
                            'claim_trip_cancel_doc_3',
                            'claim_trip_cancel_doc_4',
                            'claim_delayed_flight_doc_1',
                            'claim_delayed_flight_doc_2',
                            'claim_delayed_flight_doc_3',
                        ];
                        foreach ($travelFiles as $field) {
                            if (!$request->hasFile($field)) continue;
                            try {
                                $file = $request->file($field);
                                $name = (string) \Illuminate\Support\Str::uuid()
                                      . $file->getClientOriginalName();
                                $path = $file->storeAs("Claims/{$newclaimId}/{$field}", $name, 's3');
                                if ($path) {
                                    DB::table($subTable)
                                        ->where('newclaim_id', $newclaimId)
                                        ->update([$field => $path, 'updated_at' => now()]);
                                } else {
                                    Log::warning("TravelInsurance {$field} storeAs returned falsy for claim " . $claim->id);
                                }
                            } catch (\Throwable $e) {
                                Log::warning("TravelInsurance {$field} upload failed for claim " . $claim->id . ': ' . $e->getMessage());
                            }
                        }
                    }
                    // ── Professional Indemnity: two file uploads ──
                    // Mirrors the store() loop. Re-uploading a file
                    // replaces the previously persisted path; missing
                    // files preserve existing values.
                    if ($key === 'PROFESSIONALINDEMNITY') {
                        foreach (['contract_copy', 'investigation_findings'] as $field) {
                            if (!$request->hasFile($field)) continue;
                            try {
                                $file = $request->file($field);
                                $name = (string) \Illuminate\Support\Str::uuid()
                                      . $file->getClientOriginalName();
                                $path = $file->storeAs("Claims/{$newclaimId}/{$field}", $name, 's3');
                                if ($path) {
                                    DB::table($subTable)
                                        ->where('newclaim_id', $newclaimId)
                                        ->update([$field => $path, 'updated_at' => now()]);
                                } else {
                                    Log::warning("ProfessionalIndemnity {$field} storeAs returned falsy for claim " . $claim->id);
                                }
                            } catch (\Throwable $e) {
                                Log::warning("ProfessionalIndemnity {$field} upload failed for claim " . $claim->id . ': ' . $e->getMessage());
                            }
                        }
                    }
                    // ── Medical Malpractice: five Section-4 file uploads ──
                    // Mirrors the store() loop.
                    if ($key === 'MEDICALMALPRACTICE') {
                        foreach ([
                            'notification_letter', 'patient_records',
                            'investigation_reports', 'correspondence',
                            'expert_legal_opinions',
                        ] as $field) {
                            if (!$request->hasFile($field)) continue;
                            try {
                                $file = $request->file($field);
                                $name = (string) \Illuminate\Support\Str::uuid()
                                      . $file->getClientOriginalName();
                                $path = $file->storeAs("Claims/{$newclaimId}/{$field}", $name, 's3');
                                if ($path) {
                                    DB::table($subTable)
                                        ->where('newclaim_id', $newclaimId)
                                        ->update([$field => $path, 'updated_at' => now()]);
                                } else {
                                    Log::warning("MedicalMalpractice {$field} storeAs returned falsy for claim " . $claim->id);
                                }
                            } catch (\Throwable $e) {
                                Log::warning("MedicalMalpractice {$field} upload failed for claim " . $claim->id . ': ' . $e->getMessage());
                            }
                        }
                    }
                    // ── Locks & Keys: police_affidavit upload (V2 ext) ──
                    if (in_array($key, ['LOCKSANDKEYS', 'KEYLOSS'], true) && $request->hasFile('police_affidavit')) {
                        try {
                            $file = $request->file('police_affidavit');
                            $name = (string) \Illuminate\Support\Str::uuid()
                                  . $file->getClientOriginalName();
                            $path = $file->storeAs("Claims/{$newclaimId}/police_affidavit", $name, 's3');
                            if ($path) {
                                DB::table($subTable)
                                    ->where('newclaim_id', $newclaimId)
                                    ->update(['police_affidavit' => $path, 'updated_at' => now()]);
                            } else {
                                Log::warning('KeyLoss police_affidavit storeAs returned falsy for claim ' . $claim->id);
                            }
                        } catch (\Throwable $e) {
                            Log::warning('KeyLoss police_affidavit upload failed for claim ' . $claim->id
                                . ': ' . $e->getMessage());
                        }
                    }
                    // ── Locks & Keys: quote_1 / quote_2 uploads (full V8 form) ──
                    // Mirrors the store() loop.
                    if (in_array($key, ['LOCKSANDKEYS', 'KEYLOSS'], true)) {
                        foreach (['quote_1', 'quote_2'] as $field) {
                            if (!$request->hasFile($field)) continue;
                            try {
                                $file = $request->file($field);
                                $name = (string) \Illuminate\Support\Str::uuid()
                                      . $file->getClientOriginalName();
                                $path = $file->storeAs("Claims/{$newclaimId}/{$field}", $name, 's3');
                                if ($path) {
                                    DB::table($subTable)
                                        ->where('newclaim_id', $newclaimId)
                                        ->update([$field => $path, 'updated_at' => now()]);
                                } else {
                                    Log::warning("KeyLoss {$field} storeAs returned falsy for claim " . $claim->id);
                                }
                            } catch (\Throwable $e) {
                                Log::warning("KeyLoss {$field} upload failed for claim " . $claim->id . ': ' . $e->getMessage());
                            }
                        }
                    }
                    // ── Glass / Windscreen: six file uploads ──
                    // Mirrors the store() loop.
                    if ($key === 'GLASS') {
                        foreach ([
                            'incidentFront', 'incidentBack',
                            'incidentRight', 'incidentLeft',
                            'quote_1', 'quote_2',
                        ] as $field) {
                            if (!$request->hasFile($field)) continue;
                            try {
                                $file = $request->file($field);
                                $name = (string) \Illuminate\Support\Str::uuid()
                                      . $file->getClientOriginalName();
                                $path = $file->storeAs("Claims/{$newclaimId}/{$field}", $name, 's3');
                                if ($path) {
                                    DB::table($subTable)
                                        ->where('newclaim_id', $newclaimId)
                                        ->update([$field => $path, 'updated_at' => now()]);
                                } else {
                                    Log::warning("Glass {$field} storeAs returned falsy for claim " . $claim->id);
                                }
                            } catch (\Throwable $e) {
                                Log::warning("Glass {$field} upload failed for claim " . $claim->id . ': ' . $e->getMessage());
                            }
                        }
                    }
                }
            }
        }

        // ── Motor-accident sub-tables ─────────────────────────────────
        // Four legacy tables feed the vehicle-form_template detail page:
        //   claim_accidents              (1:1, upsert by claim_id)
        //   accident_driver              (1:1, upsert by claim_id)
        //   claim_accident_passengers    (1:N, replace-all by claim_id)
        //   claim_accident_third_party   (1:N, replace-all by claim_id)
        // Update() previously only read from these — edits to any of
        // their fields were silently dropped. This closes the edit loop
        // for motor claims.
        $motorChanges = [];
        $upsert1to1 = function (string $table, array $fields, int $claimId) use (&$motorChanges) {
            if (!\Schema::hasTable($table) || empty($fields)) return;
            $cols = \Schema::getColumnListing($table);
            $row  = array_intersect_key($fields, array_flip($cols));
            if (empty($row)) return;
            $existsId = DB::table($table)->where('claim_id', $claimId)->value('id');
            if ($existsId) {
                if (in_array('updated_at', $cols, true)) $row['updated_at'] = now();
                DB::table($table)->where('id', $existsId)->update($row);
            } else {
                $row['claim_id'] = $claimId;
                if (in_array('created_at', $cols, true)) $row['created_at'] = now();
                if (in_array('updated_at', $cols, true)) $row['updated_at'] = now();
                DB::table($table)->insert($row);
            }
            $motorChanges[$table] = array_keys($row);
        };

        if (!empty($validated['accident_details'])) {
            $upsert1to1('claim_accidents', $validated['accident_details'], $id);
        }
        if (!empty($validated['accident_driver'])) {
            $upsert1to1('accident_driver', $validated['accident_driver'], $id);
        }

        // Multi-row tables: replace-all. The FE sends the full array the
        // user sees; we wipe existing rows and re-insert. Keeps the edit
        // shape simple (no per-row id tracking needed on the FE) and
        // matches the legacy blade's behaviour where the passenger/TP
        // repeater is saved as a group.
        $replaceMany = function (string $table, array $rows, int $claimId, array $columnWhitelist) use (&$motorChanges) {
            if (!\Schema::hasTable($table)) return;
            $cols = \Schema::getColumnListing($table);
            // Soft-delete existing rows when the table has deleted_at,
            // otherwise delete outright. Keeping the history is
            // preferable where possible.
            if (in_array('deleted_at', $cols, true)) {
                DB::table($table)->where('claim_id', $claimId)->whereNull('deleted_at')->update([
                    'deleted_at' => now(),
                    'updated_at' => in_array('updated_at', $cols, true) ? now() : null,
                ]);
            } else {
                DB::table($table)->where('claim_id', $claimId)->delete();
            }
            $inserted = 0;
            foreach ($rows as $r) {
                if (!is_array($r)) continue;
                $filtered = array_intersect_key($r, array_flip($columnWhitelist));
                $row = array_intersect_key($filtered, array_flip($cols));
                if (empty($row)) continue;
                $row['claim_id'] = $claimId;
                if (in_array('created_at', $cols, true)) $row['created_at'] = now();
                if (in_array('updated_at', $cols, true)) $row['updated_at'] = now();
                DB::table($table)->insert($row);
                $inserted++;
            }
            if ($inserted > 0) $motorChanges[$table] = ["replace-all ({$inserted} rows)"];
        };

        if (array_key_exists('accident_passengers', $validated) && is_array($validated['accident_passengers'])) {
            $replaceMany('claim_accident_passengers', $validated['accident_passengers'], $id,
                ['name', 'address', 'injury']);
        }
        if (array_key_exists('accident_third_parties', $validated) && is_array($validated['accident_third_parties'])) {
            $replaceMany('claim_accident_third_party', $validated['accident_third_parties'], $id,
                ['first_name', 'last_name', 'cellphone', 'address', 'make', 'model',
                 'registration_no', 'damage_details', 'injured_name', 'relationship',
                 'hospital_name', 'injured_details']);
        }

        if (empty($changes) && empty($newClaimsUpdates) && empty($misUpdates) && empty($subClaimData) && empty($motorChanges)) {
            return response()->json(['message' => 'No changes detected.']);
        }

        // Apply primary-claim updates
        if (!empty($claimUpdates)) {
            $claim->update($claimUpdates);
        }

        // Log primary claim changes
        if (!empty($changes)) {
            DB::table('claim_edit_log')->insert($changes);
        }

        $changedFields = array_unique(array_merge(
            array_column($changes, 'field'),
            array_keys($newClaimsUpdates),
            array_keys($motorChanges),
            ...array_values(array_map(fn($arr) => array_keys($arr), $misUpdates)),
        ));
        activity('Claim')
            ->performedOn($claim)
            ->causedBy(auth()->user())
            ->withProperties([
                'primary_changes'    => $changes,
                'new_claims_changes' => $newClaimsUpdates,
                'mis_changes'        => array_filter($misUpdates),
                'motor_changes'      => $motorChanges,
            ])
            ->log('Claim edited. Fields: ' . implode(', ', $changedFields));

        return response()->json([
            'message' => 'Claim updated. ' . count($changedFields) . ' field(s) changed.',
            'changes' => $changes,
            'new_claims_changes' => $newClaimsUpdates,
            'mis_changes'        => array_filter($misUpdates),
            'motor_changes'      => $motorChanges,
        ]);
    }

    /**
     * Get edit history for a claim.
     */
    public function editHistory($id): JsonResponse
    {
        $id = (int) $id;
        $history = DB::table('claim_edit_log')
            ->where('claim_id', $id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $history]);
    }

    /**
     * Update claim status.
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $id = (int) $id;
        $claim = Claim::findOrFail($id);

        // Sub-statuses available only while a claim sits in the 'Open' main
        // status — captured in claims.claim_sub_status. Kept in sync with the
        // OPEN_SUB_STATUSES list on the React side (ClaimDetailPage.tsx).
        $openSubStatuses = [
            'Awaiting Claim Documents',
            'Awaiting Premium Confirmation',
            'Awaiting Invoice',
            'Awaiting Assessment Report',
            'Awaiting Signed AOL',
            'Awaiting Signed Form of Release',
            'Awaiting Signed Cash In Lieu',
            'Awaiting Signed Ex-Gratia',
            'Awaiting Third Party Insurance Confirmation',
            'Awaiting KYC Compliance',
            'Awaiting Excess to be Paid',
            'Awaiting Salvage to be paid',
            'Awaiting Proof of Payment',
            "Awaiting Demand Documents from third party's insurer",
            'Subrogation on going',
            'Legal Proceedings on going',
            'Repudiation',
            'File with Finance for payment',
            'Claim withdrawn',
            'Claim below Excess',
            'Claim Under Review',
            'Inter-Departmental Assistance Pending - Underwriting / System Developers',
            'Approved',
            'Closed',
            'Re-open',
        ];

        $validated = $request->validate([
            'status'      => 'required|string|in:New,Pending,Pending Assessment,Approved,Rejected,Closed,Reopen,Open',
            'closed_note' => 'nullable|string|max:2000',
            // Required only when moving INTO 'Open'; must be one of the list above.
            'claim_sub_status' => [
                $request->input('status') === 'Open' ? 'required' : 'nullable',
                'string',
                \Illuminate\Validation\Rule::in($openSubStatuses),
            ],
        ]);

        // 'Open' is reachable from every existing status (additive — no existing
        // transition was removed). From 'Open' the claim can rejoin the normal flow.
        $transitions = [
            'New'                 => ['Pending Assessment', 'Approved', 'Rejected', 'Open'],
            'Pending'             => ['Pending Assessment', 'Approved', 'Rejected', 'Open'],
            'Pending Assessment'  => ['Approved', 'Rejected', 'Open'],
            'Approved'            => ['Closed', 'Open'],
            'Rejected'            => ['Closed', 'Reopen', 'Open'],
            'Closed'              => ['Reopen', 'Open'],
            'Reopen'              => ['Pending Assessment', 'Approved', 'Open'],
            'Open'                => ['Pending Assessment', 'Approved', 'Rejected', 'Closed', 'Reopen'],
        ];
        // A blank status (null OR empty string — shown as "Unknown" in the UI)
        // resolves to 'New' so an un-started claim can still be moved into the
        // flow. `?:` catches '' which `??` would let through.
        $fromStatus = trim((string) $claim->status) !== '' ? $claim->status : 'New';
        $allowed    = $transitions[$fromStatus] ?? [];
        if ($validated['status'] !== $fromStatus && !in_array($validated['status'], $allowed, true)) {
            return response()->json([
                'error'   => "Invalid status transition from {$fromStatus} to {$validated['status']}.",
                'allowed' => $allowed,
            ], 422);
        }

        // Reserve logic checks when closing
        if ($validated['status'] === 'Closed') {
            // Check outstanding reserve balance
            $lastBalance = DB::table('claim_reserves_coverages')
                ->where('claim_id', $id)
                ->orderByDesc('id')
                ->value('balance');

            $outstandingBalance = (float) ($lastBalance ?? 0);

            if ($outstandingBalance > 0) {
                $fmt = number_format($outstandingBalance, 2);
                return response()->json([
                    'message' => "Cannot close claim — outstanding reserve balance of P {$fmt}. Settle or reset reserves before closing.",
                ], 422);
            }

            // Check if there are reserves but no payments (reserve created but never paid)
            // Exclude voided rows (is_payment_voided in {1,2}) so a voided payment
            // doesn't masquerade as an "unpaid reserve" and block close.
            $totalReserve = (float) DB::table('claim_reserves_coverages')
                ->where('claim_id', $id)
                ->where(function ($q) { $q->whereNull('is_payment_voided')->orWhere('is_payment_voided', 0); })
                ->sum('reserve_amt');
            $totalPayment = (float) DB::table('claim_reserves_coverages')
                ->where('claim_id', $id)
                ->where(function ($q) { $q->whereNull('is_payment_voided')->orWhere('is_payment_voided', 0); })
                ->sum('payment_amt');

            if ($totalReserve > 0 && $totalPayment == 0) {
                return response()->json([
                    'message' => "Cannot close claim — reserves of P " . number_format($totalReserve, 2) . " exist but no payments have been made. Process payments or reset reserves first.",
                ], 422);
            }
        }

        $oldStatus = $claim->status;
        $updateData = [
            'status'      => $validated['status'],
            'closed_note' => $validated['closed_note'] ?? $claim->closed_note,
        ];
        if ($validated['status'] === 'Open') {
            // Capture the working sub-status the agent picked.
            $updateData['claim_sub_status'] = $validated['claim_sub_status'];
        } elseif ($oldStatus === 'Open') {
            // Leaving 'Open' — clear the working sub-status so it can't go stale.
            $updateData['claim_sub_status'] = null;
        }
        $claim->update($updateData);

        activity('Claim')
            ->performedOn($claim)
            ->causedBy(auth()->user())
            ->log("Claim status changed from {$oldStatus} to {$validated['status']}");

        return response()->json([
            'data'    => ['id' => $claim->id, 'status' => $claim->status],
            'message' => 'Claim status updated successfully.',
        ]);
    }

    /**
     * Get coverages for a policy (for claim registration + reserve creation).
     *
     * DOM/COM policies replicate policy_coverages on every endorsement via
     * PolicyAction::newPolicyAction, so a raw WHERE policy_id=X returns N
     * copies of each coverage — one per action. That made the claim reserve
     * "Coverage" dropdown show "Fire, Fire, Fire, ..." duplicates on any
     * endorsed commercial policy. Scope to the latest live action (or the
     * action_id passed by the caller) to match what the operator sees in
     * the policy UI.
     *
     * MIS products (1-6, 9, 12, 13) don't replicate per action so scoping
     * is a no-op for them.
     */
    public function policyCoverages(int $policyId, Request $request): JsonResponse
    {
        $policy = Policy::select('id', 'product_id')->find($policyId);
        $domcomProductIds = [7, 8, 16, 17, 18, 19, 20, 22, 23, 24];
        $isDomCom = $policy && in_array($policy->product_id, $domcomProductIds);

        $actionId = $request->query('action_id');
        if (!$actionId && $isDomCom) {
            // Latest ISSUED action wins; fall back to most-recent action overall
            // so freshly-quoted policies (still in QUOTE) also work.
            $actionId = PolicyAction::where('policy_id', $policyId)
                ->whereNull('deleted_at')
                ->where('status', 'ISSUED')
                ->orderByDesc('id')
                ->value('id')
                ?? PolicyAction::where('policy_id', $policyId)
                    ->whereNull('deleted_at')
                    ->orderByDesc('id')
                    ->value('id');
        }

        $coverages = PolicyCoverage::where('policy_id', $policyId)
            ->when($actionId, fn($q) => $q->where('action_id', $actionId))
            ->with('coverage:id,s_CoverageName,s_CoverageCode')
            ->get()
            ->map(fn($c) => [
                'id'             => $c->id,
                'coverage_name'  => $c->coverage->s_CoverageName ?? 'N/A',
                'coverage_code'  => $c->coverage->s_CoverageCode ?? null,
                'coverage_value' => $c->coverage_value,
                'type_string'    => $c->type_string,
                'action_id'      => $c->action_id,
            ])
            // Dedupe by coverage name — defensive for MIS policies where the same
            // coverage may appear under different types or for legacy rows that
            // slipped through without an action_id.
            ->unique('coverage_name')
            ->values();

        return response()->json(['data' => $coverages]);
    }

    private function cdnUrl(?string $path): ?string
    {
        if (empty($path)) return null;
        $path = str_replace(['\/', ' '], ['/', '%20'], $path);

        // Dev / staging boxes that don't have AWS configured get the
        // `s3` filesystem disk silently routed to local (see
        // config/filesystems.php). The file IS uploaded — to
        // storage/app/public/{path} — but a CloudFront URL would point
        // at the production S3 bucket where the object doesn't exist.
        // Detect that case and serve via the /storage symlink instead
        // (artisan storage:link must have been run).
        if (!env('AWS_BUCKET')) {
            return rtrim(env('APP_URL', 'http://localhost:8000'), '/')
                 . '/storage/' . ltrim($path, '/');
        }

        $cdn = env('AWS_CLOUDFRONT');
        if (!$cdn) return null;
        return rtrim($cdn, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Mirror of claimTypesByPolicy()'s product→form_template dispatch.
     * Returned on the claim show payload so the FE can render the right
     * detail sections (life / legal / hospital_cash / vehicle /
     * coverage_based) instead of assuming every claim is a motor claim.
     *
     * Product IDs match the legacy dispatch in claimTypesByPolicy (~line
     * 700) verbatim. Keep the two in sync.
     */
    private function resolveFormTemplate(?int $productId): ?string
    {
        return match ($productId) {
            1, 12     => 'life',          // ADI / Group AD
            2, 3      => 'vehicle',       // Third Party / Motor Comprehensive
            4         => 'legal',         // Legal Insurance
            5         => 'cellphone',     // Cellphone
            6         => 'tyre',          // Tyre & Rim
            9, 10     => 'hospital_cash', // Hospital Cashback / HIB
            7, 8, 16, 17, 18, 19 => 'coverage_based', // COMG / DOMG / Specialist
            default   => null,
        };
    }

    // =========================================================================
    //  Assessor workflow (mirrors graphiteBWV8 admin.claims.accident +
    //  ClaimsController::assessorUpload). Stores rows in `claim_assessment`.
    // =========================================================================

    /**
     * GET /claims/assessor-list — list users with Spatie role 'Accessor'.
     */
    public function assessorList(Request $request): JsonResponse
    {
        // Returns the unified assessor list for the claim Assessor tab:
        //   internal  → users with the "Accessor" role
        //   external  → suppliers with supplierType "Assessor"
        //
        // Each entry carries a `value` of the form "user:ID" / "supplier:ID" so
        // the two id-spaces never collide on the frontend, plus a `category`
        // (motor | non_motor | both | null) for client-side filtering.
        //
        // Optional ?category=motor|non_motor narrows the list to assessors
        // tagged with that category or "both" (untagged rows are always shown
        // so a half-configured DB still appoints someone).
        $category = $request->query('category');
        $category = in_array($category, ['motor', 'non_motor'], true) ? $category : null;

        $matchesCategory = function (?string $cat) use ($category): bool {
            if ($category === null) return true;
            return $cat === null || $cat === '' || $cat === 'both' || $cat === $category;
        };

        $hasUserCategory     = Schema::hasColumn('users', 'assessor_category');
        $hasSupplierCategory = Schema::hasColumn('suppliers', 'assessor_category');

        try {
            $userCols = ['id', 'firstName', 'lastName', 'email'];
            if ($hasUserCategory) $userCols[] = 'assessor_category';

            $internal = \AlphaDirect\User::role('Accessor')
                ->where('active', 1)
                ->get($userCols)
                ->map(fn($u) => [
                    'value'     => 'user:' . $u->id,
                    'id'        => $u->id,
                    'source'    => 'user',
                    'firstName' => $u->firstName,
                    'lastName'  => $u->lastName,
                    'name'      => trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')),
                    'email'     => $u->email,
                    'category'  => $hasUserCategory ? ($u->assessor_category ?? null) : null,
                ])
                ->filter(fn($a) => $matchesCategory($a['category']))
                ->values();

            $external = collect();
            if (Schema::hasTable('suppliers')) {
                $external = DB::table('suppliers')
                    ->where('supplierType', 'Assessor')
                    ->where('customer_selected', 0)
                    ->orderBy('supplierName')
                    ->get()
                    ->map(fn($s) => [
                        'value'     => 'supplier:' . $s->id,
                        'id'        => $s->id,
                        'source'    => 'supplier',
                        'firstName' => $s->supplierName,
                        'lastName'  => '',
                        'name'      => $s->supplierName,
                        'email'     => $s->email ?? null,
                        'category'  => $hasSupplierCategory ? ($s->assessor_category ?? null) : null,
                    ])
                    ->filter(fn($a) => $matchesCategory($a['category']))
                    ->values();
            }

            return response()->json(['data' => $internal->concat($external)->values()]);
        } catch (\Throwable $e) {
            \Log::error('assessorList failed: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json([
                'data'  => [],
                'error' => $e->getMessage(),
                'hint'  => 'Assessor lookup failed. Verify a user exists with the "Accessor" role (users.active=1) or a supplier with supplierType "Assessor".',
            ], 500);
        }
    }

    /**
     * GET /claims/{id}/assessor-reports — list all claim_assessment rows.
     */
    public function assessorReports($id): JsonResponse
    {
        $id = (int) $id;

        // External (supplier) assessors are resolved via a second leftJoin when
        // the column exists; COALESCE prefers the user name, then the supplier
        // name, so legacy rows (user-only) keep rendering unchanged.
        $hasSupplierFk = Schema::hasColumn('claim_assessment', 'assessor_supplier_id');
        $hasExternalFk = Schema::hasColumn('claim_assessment', 'assessor_external_id') && Schema::hasTable('assessors');

        $q = DB::table('claim_assessment as ca')
            ->leftJoin('users as u', 'u.id', '=', 'ca.assessor_id');

        if ($hasSupplierFk) {
            $q->leftJoin('suppliers as sup', 'sup.id', '=', 'ca.assessor_supplier_id');
        }
        if ($hasExternalFk) {
            $q->leftJoin('assessors as ax', 'ax.id', '=', 'ca.assessor_external_id');
        }

        // Prefer the internal user name, then the supplier name, then the
        // master-list assessor name — so legacy rows keep rendering unchanged.
        $userName = "NULLIF(TRIM(CONCAT(COALESCE(u.firstName, ''), ' ', COALESCE(u.lastName, ''))), '')";
        $parts = [$userName];
        if ($hasSupplierFk) $parts[] = 'sup.supplierName';
        if ($hasExternalFk) $parts[] = 'ax.name';
        $nameExpr = count($parts) > 1 ? 'COALESCE(' . implode(', ', $parts) . ')' : $userName;

        $select = [
            'ca.id',
            'ca.claim_id',
            'ca.policy_id',
            'ca.assessor_id',
            'ca.assessment_report',
            'ca.quotations_parts',
            'ca.valuation',
            'ca.attached_file',
            'ca.notes',
            'ca.created_at',
            DB::raw("{$nameExpr} as assessor_name"),
        ];
        if (Schema::hasColumn('claim_assessment', 'recipient_emails')) {
            $select[] = 'ca.recipient_emails';
        }

        $rows = $q->where('ca.claim_id', $id)
            ->orderByDesc('ca.id')
            ->select($select)
            ->get();

        return response()->json(['data' => $rows]);
    }

    /**
     * POST /claims/{id}/assessor-upload — create a claim_assessment row and
     * dispatch the assessor request email (optionally also to the attorney).
     *
     * Form fields:
     *   assessor          — required, "user:ID" or "supplier:ID" (the appointed assessor)
     *   recipientEmails[] — optional, email addresses to notify ("send email to the assessor")
     *   category          — optional, motor | non_motor
     *   attachFile[]      — optional, one or more files
     */
    public function assessorUpload(Request $request, $id): JsonResponse
    {
        $id = (int) $id;

        // `assessor` accepts the unified "source:id" token from the dropdown.
        // Legacy callers may still send a bare numeric user id — normalise both.
        $request->validate([
            'assessor'          => 'required|string',
            'recipientEmails'   => 'nullable|array',
            'recipientEmails.*' => 'nullable|email',
            'category'          => 'nullable|in:motor,non_motor',
            'subject'           => 'nullable|string|max:255',
            'body'              => 'nullable|string|max:10000',
            // Non-Motor: subject + body are composed server-side; only these
            // two are entered manually on the form.
            'contact_details'   => 'nullable|string|max:1000',
            'location'          => 'nullable|string|max:1000',
            // Lawyer (union claims) request-body fields — replace contact/location.
            'proposed_action'   => 'nullable|string|max:5000',
            'client_name'       => 'nullable|string|max:255',
            'client_id'         => 'nullable|string|max:100',
            'client_phone'      => 'nullable|string|max:50',
            'client_email'      => 'nullable|string|max:200',
            'attachFile'        => 'nullable|array',
            'attachFile.*'      => 'file|max:10240',
        ]);

        [$source, $assessorId] = $this->parseAssessorToken((string) $request->input('assessor'));
        if (!$assessorId) {
            return response()->json(['error' => 'Invalid assessor selection.'], 422);
        }

        // Resolve the appointed assessor's identity from the correct table.
        if ($source === 'assessor') {
            $assessor = DB::table('assessors')->where('id', $assessorId)->first(['email', 'name']);
            if (!$assessor) return response()->json(['error' => 'Selected assessor not found.'], 422);
            $assessorName  = $assessor->name;
            $assessorEmail = $assessor->email ?? null;
        } elseif ($source === 'lawyer') {
            // Union legal claims (BONU / BOWASEWU) appoint a lawyer from the
            // Lawyers master list instead of an assessor.
            $assessor = DB::table('lawyers')->where('id', $assessorId)->first(['email', 'name']);
            if (!$assessor) return response()->json(['error' => 'Selected lawyer not found.'], 422);
            $assessorName  = $assessor->name;
            $assessorEmail = $assessor->email ?? null;
        } elseif ($source === 'supplier') {
            $assessor = DB::table('suppliers')->where('id', $assessorId)->first(['email', 'supplierName']);
            if (!$assessor) return response()->json(['error' => 'Selected external assessor not found.'], 422);
            $assessorName  = $assessor->supplierName;
            $assessorEmail = $assessor->email ?? null;
        } else {
            $assessor = DB::table('users')->where('id', $assessorId)->first(['email', 'firstName', 'lastName']);
            if (!$assessor) return response()->json(['error' => 'Selected assessor not found.'], 422);
            $assessorName  = trim(($assessor->firstName ?? '') . ' ' . ($assessor->lastName ?? ''));
            $assessorEmail = $assessor->email ?? null;
        }

        $claim = Claim::with('policy')->findOrFail($id);
        if (!$claim->policy) {
            return response()->json(['error' => 'Claim has no linked policy.'], 422);
        }

        try {
            DB::beginTransaction();

            // Upload every attached file; store the resulting paths as a JSON
            // array so the reports view (which already JSON-decodes) renders
            // each as a separate download link.
            $attachedPaths    = [];
            $attachedNames    = [];   // original filenames — listed on the "Claim Document" line
            $emailAttachments = [];   // raw bytes captured up-front so they survive the S3 move
            foreach ((array) $request->file('attachFile', []) as $file) {
                if (!$file) continue;
                $name = $file->getClientOriginalName();
                // Read the bytes BEFORE any upload — the local-fallback store()
                // moves the temp file, which would invalidate a later read.
                $contents = file_get_contents($file);
                $path = "MIS/{$claim->id}/Attached/File/{$name}";
                try {
                    Storage::disk('s3')->put($path, $contents, 'public');
                } catch (\Throwable $e) {
                    Log::warning('S3 upload failed, storing locally: ' . $e->getMessage());
                    $path = $file->store("claims/{$claim->id}/assessor", 'public');
                }
                $attachedPaths[]    = $path;
                $attachedNames[]    = $name;
                $emailAttachments[] = ['data' => $contents, 'name' => $name, 'mime' => $file->getClientMimeType()];
            }

            // Recipients for the "send email to the assessor/lawyer" flow, plus the
            // appointed assessor/lawyer's own address, plus the Claims department
            // (always copied by default — overridable via config, falls back to
            // the standard claimsdept mailbox). Deduplicated, blanks removed.
            $recipients = collect($request->input('recipientEmails', []))
                ->push($assessorEmail)
                ->push(config('claims.dept_email', 'claimsdept@alphadirect.co.bw'))
                ->filter(fn($e) => is_string($e) && filter_var($e, FILTER_VALIDATE_EMAIL))
                ->unique()
                ->values();

            $row = [
                'claim_id'      => $claim->id,
                'policy_id'     => $claim->policy_id,
                'assessor_id'   => $source === 'user' ? $assessorId : null,
                'attached_file' => $attachedPaths ? json_encode($attachedPaths) : null,
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
            // Only write the new columns when the migration has been applied,
            // so the endpoint still works on a not-yet-migrated environment.
            if (Schema::hasColumn('claim_assessment', 'assessor_source'))      $row['assessor_source'] = $source;
            if (Schema::hasColumn('claim_assessment', 'assessor_supplier_id')) $row['assessor_supplier_id'] = $source === 'supplier' ? $assessorId : null;
            if (Schema::hasColumn('claim_assessment', 'assessor_external_id')) $row['assessor_external_id'] = $source === 'assessor' ? $assessorId : null;
            if (Schema::hasColumn('claim_assessment', 'recipient_emails'))     $row['recipient_emails'] = $recipients->isNotEmpty() ? json_encode($recipients->all()) : null;
            if (Schema::hasColumn('claim_assessment', 'assessor_category'))    $row['assessor_category'] = $request->input('category');

            $assessmentId = DB::table('claim_assessment')->insertGetId($row);

            // Email dispatch — best-effort (don't block response if mail fails).
            // Non-Motor: subject + body are composed server-side from the claim
            // in a fixed format; the handler only supplies Contact Details and
            // Location. Motor (legacy) keeps the typed subject/body with a
            // sensible fallback.
            // Viewable document links for the "Claim Document" line.
            $documents = [];
            foreach ($attachedPaths as $i => $p) {
                $documents[] = [
                    'name' => $attachedNames[$i] ?? basename($p),
                    'url'  => $this->cdnUrl($p),
                ];
            }

            $isLawyer   = ($source === 'lawyer');
            $roleLabel  = $isLawyer ? 'Lawyer' : 'Assessor';
            $isNonMotor = $request->input('category') !== 'motor';
            $viewData   = [];
            $body       = '';
            $useBlade   = false;
            if ($isLawyer) {
                // Union legal claims: instruct-and-assist body with the client
                // block. Subject reuses the standard claim subject line.
                $subject = $this->assessorEmailContent($claim, null, null)['subject'];
                $blank = fn($v) => trim((string) $v) !== '' ? trim((string) $v) : '-';
                $body = implode("\n", [
                    'Good day,',
                    '',
                    'Please see attached and assist the client accordingly.',
                    '',
                    $blank($request->input('proposed_action')),
                    '',
                    'Client name: ' . $blank($request->input('client_name')),
                    'Client id: ' . $blank($request->input('client_id')),
                    'Phone: ' . $blank($request->input('client_phone')),
                    'Email: ' . $blank($request->input('client_email')),
                    '',
                    'We kindly request that you confirm within 48 hours whether you have been able to make contact with the client and provide an update on the matter. Should you require any additional information or documentation to assist, please do not hesitate to reach out.',
                    '',
                    'Regards',
                ]);
            } elseif ($isNonMotor) {
                $viewData = $this->assessorEmailContent(
                    $claim,
                    $request->input('contact_details'),
                    $request->input('location')
                );
                $viewData['documents'] = $documents;
                $subject = $viewData['subject'];
                $useBlade = true;
            } else {
                $subject = trim((string) $request->input('subject', ''));
                $body    = trim((string) $request->input('body', ''));
                if ($subject === '') $subject = "Assessor Request: Claim {$claim->claim_number}";
                if ($body === '')    $body = "You have been requested as assessor for claim {$claim->claim_number}. Please log in to the portal to review the details.";
            }

            $emailedTo = [];
            foreach ($recipients as $to) {
                try {
                    if ($useBlade) {
                        // Nicely-formatted HTML email (Mail.assessorClaimRequest).
                        \Mail::send('Mail.assessorClaimRequest', $viewData, function ($m) use ($to, $subject, $emailAttachments) {
                            $m->to($to)->subject($subject)->from('insurance@alphadirect.co.bw', 'Alpha Direct');
                            // Attach the uploaded claim document(s) to the email.
                            foreach ($emailAttachments as $att) {
                                $m->attachData($att['data'], $att['name'], ['mime' => $att['mime']]);
                            }
                        });
                    } else {
                        \Mail::raw($body, function ($m) use ($to, $subject, $emailAttachments) {
                            $m->to($to)->subject($subject);
                            foreach ($emailAttachments as $att) {
                                $m->attachData($att['data'], $att['name'], ['mime' => $att['mime']]);
                            }
                        });
                    }
                    $emailedTo[] = $to;
                } catch (\Throwable $e) {
                    Log::warning("Assessor email to {$to} failed: " . $e->getMessage());
                }
            }

            activity('Claim')
                ->performedOn($claim)
                ->causedBy(auth()->user())
                ->log("{$roleLabel} appointed: {$assessorName} (claim_assessment #{$assessmentId})");

            DB::commit();

            return response()->json([
                'message' => "Request sent to {$roleLabel} successfully.",
                'data'    => [
                    'assessment_id'  => $assessmentId,
                    'assessor'       => $assessorName,
                    'files_uploaded' => count($attachedPaths),
                    'emailed'        => $emailedTo,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('assessorUpload failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Resolve the Non-Motor assessor email fields from the claim.
     *
     * Subject: "[Policy Number] [Customer Name] [Claimed Item] [Claim Type] CLAIM#: [Claim Number]"
     *   e.g. "COMG2025147005 KNOCK TOGETIT PTY B613BWH MOTOR CLAIM#: G2026004394"
     *
     * Returns the structured fields used to render the HTML email view
     * (Mail.assessorClaimRequest) so the body is nicely laid out and the
     * Claim Document renders as a link. The uploaded files are also attached.
     *
     * @return array{subject:string,insuredName:string,policyNumber:string,claimNumber:string,dateOfLoss:string,contactDetails:string,location:string}
     */
    private function assessorEmailContent(Claim $claim, ?string $contactDetails, ?string $location): array
    {
        $policyNumber = $claim->policy->policyNumber ?? null;
        $claimNumber  = $claim->claim_number ?? null;

        // Customer name — company name when the customer is an Organisation,
        // otherwise the individual's name. Mirrors ClaimsController::show.
        $customerName = null;
        if (!empty($claim->customer_id)) {
            $companyId = DB::table('customer_profile')
                ->where('customer_id', $claim->customer_id)
                ->where('entity_type', 'Organisation')
                ->whereNotNull('company_id')
                ->value('company_id');
            if ($companyId) {
                $customerName = DB::table('companies')->where('id', $companyId)->value('name');
            }
            if (empty($customerName)) {
                $cust = DB::table('customer')->where('id', $claim->customer_id)
                    ->first(['firstName', 'lastName']);
                if ($cust) {
                    $customerName = trim(($cust->firstName ?? '') . ' ' . ($cust->lastName ?? '')) ?: null;
                }
            }
        }

        // Claimed item — vehicle registration; fall back to new_claims.
        $claimedItem = $claim->vehicle_plate ?? null;
        if (empty($claimedItem) && $claimNumber) {
            $claimedItem = DB::table('new_claims')
                ->where('claim_number', $claimNumber)
                ->value('vehicle_plate');
        }

        // Date of loss — new_claims.date_of_loss → claims.incident_date.
        $dateOfLoss = $claimNumber
            ? DB::table('new_claims')->where('claim_number', $claimNumber)->value('date_of_loss')
            : null;
        if (empty($dateOfLoss)) {
            $dateOfLoss = $claim->incident_date ?? null;
        }

        $claimType = !empty($claim->claim_type) ? strtoupper(trim($claim->claim_type)) : null;

        // Subject: [Policy] [Customer] [Item] [Type] CLAIM#: [No] — blanks dropped.
        $parts = array_filter(
            [$policyNumber, $customerName, $claimedItem, $claimType],
            fn($v) => $v !== null && trim((string) $v) !== ''
        );
        $subject = trim(implode(' ', $parts));
        if ($claimNumber) {
            $subject .= ($subject !== '' ? ' ' : '') . 'CLAIM#: ' . $claimNumber;
        }

        return [
            'subject'        => $subject,
            'insuredName'    => $customerName ?: '-',
            'policyNumber'   => $policyNumber ?: '-',
            'claimNumber'    => $claimNumber ?: '-',
            'dateOfLoss'     => $dateOfLoss ?: '-',
            'contactDetails' => trim((string) $contactDetails) ?: '-',
            'location'       => trim((string) $location) ?: '-',
        ];
    }

    /**
     * Split a "user:5" / "supplier:3" assessor token into [source, id].
     * Falls back to treating a bare numeric value as a user id (legacy callers).
     * Returns [null, null] when unparseable.
     */
    private function parseAssessorToken(string $token): array
    {
        $token = trim($token);
        if (str_contains($token, ':')) {
            [$source, $rawId] = explode(':', $token, 2);
            $source = strtolower(trim($source));
            $idVal  = (int) trim($rawId);
            if (in_array($source, ['user', 'supplier', 'assessor', 'lawyer'], true) && $idVal > 0) {
                return [$source, $idVal];
            }
            return [null, null];
        }
        return ctype_digit($token) && (int) $token > 0 ? ['user', (int) $token] : [null, null];
    }

    /**
     * Normalize a claim_type input ("Goods In Transit", "goodsintransit",
     * "GOODSINTRANSIT") to the compact uppercase key used in the sub_table
     * map. Mirrors the pattern at ClaimsController::store/update.
     */
    private function normalizeClaimTypeKey(?string $claimType): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $claimType));
    }

    /**
     * Map coverage-based claim-type codes that are NOT members of the
     * `claims.claim_type` ENUM onto the closest valid ENUM value, so the
     * write persists instead of MySQL silently coercing it to '' (non-strict
     * mode). Applied on BOTH create (store) and edit (update) so the two
     * paths stay identical.
     *
     * The DOM/COM coverage list (dom_com_coverage_claims.claim_name) can ship
     * codes like 'MOTORACCIDENT' / 'DOMMOTOR' / 'LOCKSANDKEYS' that the legacy
     * ENUM never had. These fold onto the canonical members the ENUM already
     * carries ('Motor', 'Key Loss'). Anything already valid (WORKERSCOMPENSATION,
     * Accident, Glass, …) passes through unchanged.
     */
    private const CLAIM_TYPE_ENUM_ALIASES = [
        'MOTORACCIDENT' => 'Motor',
        'DOMMOTOR'      => 'Motor',
        'LOCKSANDKEYS'  => 'Key Loss',
        // Hospital Cashback dropdown ships id 'hospital_cash' (→ HOSPITALCASH);
        // the ENUM member is 'Hospital CashBack'.
        'HOSPITALCASH'  => 'Hospital CashBack',

        // Coverage-based (Engineering / COMG) claim types. The FE dropdown
        // (/claims/claim-types-by-policy) submits the spaced label as the
        // claim_type — e.g. "Plant All Risks" — but the claims.claim_type
        // ENUM members added by the 2026_07_02_120000 migration are the
        // compact UPPER codes ("PLANTALLRISKS", ...). Without these aliases
        // the spaced label is not an ENUM member, so MySQL silently coerces
        // it to '' on write (no error) and the claim type never updates.
        // Map every normalised key onto the exact ENUM member so the write
        // persists. Singular + plural keys both fold to the plural member
        // (PLANTALLRISKS) that the sub-table map is keyed on.
        'PLANTALLRISK'          => 'PLANTALLRISKS',
        'PLANTALLRISKS'         => 'PLANTALLRISKS',
        'CONTRACTORSALLRISK'    => 'CONTRACTORSALLRISKS',
        'CONTRACTORSALLRISKS'   => 'CONTRACTORSALLRISKS',
        'ERECTIONALLRISK'       => 'ERECTIONALLRISK',
        'ERECTIONALLRISKS'      => 'ERECTIONALLRISK',
        'PROFESSIONALINDEMNITY' => 'PROFESSIONALINDEMNITY',
        'MEDICALMALPRACTICE'    => 'MEDICALMALPRACTICE',
        'TRAVELINSURANCE'       => 'TRAVELINSURANCE',
        'MACHINERYBREAKDOWN'    => 'MACHINERYBREAKDOWN',
        'MACHINERYBREAKDOWNLOSSOFPROFIT' => 'MACHINERYBREAKDOWNLOSSOFPROFIT',
        // Common shorthands for the LOP coverage fold to the canonical member.
        'MACHINERYBREAKDOWNLOP' => 'MACHINERYBREAKDOWNLOSSOFPROFIT',
        'MBLOSSOFPROFIT'        => 'MACHINERYBREAKDOWNLOSSOFPROFIT',
        'MBLOP'                 => 'MACHINERYBREAKDOWNLOSSOFPROFIT',
        'DIRECTORSOFFICERSLIABILITY' => 'DIRECTORSOFFICERSLIABILITY',
        // Common shorthands for the D&O coverage fold to the canonical member.
        'DIRECTORSANDOFFICERSLIABILITY' => 'DIRECTORSOFFICERSLIABILITY',
        'DIRECTORSOFFICERS'     => 'DIRECTORSOFFICERSLIABILITY',
        'DANDO'                 => 'DIRECTORSOFFICERSLIABILITY',
        'DO'                    => 'DIRECTORSOFFICERSLIABILITY',
        'MARINECARGOONCEOFF'    => 'MARINECARGOONCEOFF',
        // Common shorthands for the Marine Cargo Once-Off coverage.
        'MARINECARGOONCEOFFSINGLEVOYAGE' => 'MARINECARGOONCEOFF',
        'MARINECARGO'           => 'MARINECARGOONCEOFF',
        'MARINECARGOSINGLEVOYAGE' => 'MARINECARGOONCEOFF',
        // The DB coverage name for product 22 is "Marine Once-Off Cover"
        // (→ MARINEONCEOFFCOVER), mirroring the Open-Cover alias below. Without
        // this the once-off claim_type is lost on write and never dispatches to
        // marine_cargo_once_off_claims.
        'MARINEONCEOFFCOVER'    => 'MARINECARGOONCEOFF',
        'MARINECARGOOPENCOVER'  => 'MARINECARGOOPENCOVER',
        'MARINEOPENCOVER'       => 'MARINECARGOOPENCOVER',
        'MARINECARGOOPEN'       => 'MARINECARGOOPENCOVER',
    ];

    private function mapClaimTypeToEnum(?string $claimType): ?string
    {
        if ($claimType === null || $claimType === '') {
            return $claimType;
        }
        $key = $this->normalizeClaimTypeKey($claimType);
        return self::CLAIM_TYPE_ENUM_ALIASES[$key] ?? $claimType;
    }

    /**
     * Validate Goods In Transit payload against V8's required-field rules.
     * Returns null when valid; a 422 JsonResponse otherwise. Caller MUST
     * check the return value before continuing into the DB transaction.
     */
    private function validateGoodsInTransitPayload(Request $request, bool $isCreate = true): ?JsonResponse
    {
        $sub = $request->input('sub_claim_data');
        if ($sub !== null && !is_array($sub)) {
            $decoded = json_decode((string) $sub, true);
            $sub = is_array($decoded) ? $decoded : [];
        }
        $sub = is_array($sub) ? $sub : [];

        $missing = [];

        // Bulk required-fields check fires only at create-time. On update
        // the frontend may send a partial payload (operator changed one
        // field), and the existing row already has the rest persisted —
        // failing the request would force them to re-enter every value.
        if ($isCreate) {
            $required = [
                'address_of_premises_loss', 'details_of_driver', 'property_last_seen',
                'date_time_of_loss', 'brief_description_incident', 'date_time_police_advised',
                'police_station_name', 'witnesses_name', 'witnesses_mobile_number',
                'total_value_of_loss', 'consignment_transported_to', 'consignment_from',
                'vehicle_registration_number', 'is_carrier_contracted',
                'carrier_has_own_GIT_ins', 'other_insurance_against_theft',
                'details_of_previous_loss_records',
            ];
            foreach ($required as $f) {
                if (!array_key_exists($f, $sub) || $sub[$f] === '' || $sub[$f] === null) {
                    $missing[] = $f;
                }
            }

            // Conditional: carrier contracted ⇒ contract file required at
            // create-time. On update we skip this — the existing contract
            // is already on file; re-uploading is optional.
            if (($sub['is_carrier_contracted'] ?? null) == '1' && !$request->hasFile('copy_of_contract')) {
                $missing[] = 'copy_of_contract';
            }
        }

        // Conditional: other insurance ⇒ details textarea required.
        // Applies to both create and update — if the operator flips the
        // toggle to Yes, they need to provide the details.
        if (($sub['other_insurance_against_theft'] ?? null) == '1'
            && empty($sub['insurance_against_theft_details'])
        ) {
            $missing[] = 'insurance_against_theft_details';
        }

        // File MIME / size guard. Mirrors V8 config/app.php accept rule
        // (PDF + images) plus a 10 MB ceiling consistent with V2's other
        // claim documents. Fires whenever a file is attached, regardless
        // of create vs update.
        if ($request->hasFile('copy_of_contract')) {
            $validator = \Validator::make(
                ['copy_of_contract' => $request->file('copy_of_contract')],
                ['copy_of_contract' => 'file|mimes:pdf,jpg,jpeg,png|max:10240']
            );
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Invalid copy_of_contract file.',
                    'errors'  => $validator->errors()->toArray(),
                ], 422);
            }
        }

        if (!empty($missing)) {
            return response()->json([
                'message' => 'Goods In Transit claim is missing required fields.',
                'errors'  => array_fill_keys($missing, ['This field is required.']),
            ], 422);
        }

        return null;
    }
}
