<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\ClaimFnol;
use AlphaDirect\Models\ClaimTrackerWorkflow;
use AlphaDirect\Services\ClaimSla\ClaimSlaService;
use AlphaDirect\Services\Reinsurance\CessionSource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ClaimsV2Controller extends Controller
{
    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Return the claims table name that actually exists.
     */
    private function claimsTable(): string
    {
        // `claims` is the authoritative table — claim_reserves.claim_id,
        // claim_attachments.claim_id, etc. all FK to claims.id. `new_claims`
        // is a parallel table with its own id sequence that only some of
        // the form-config endpoints need. Prefer `claims`; only fall back
        // to `new_claims` on envs where `claims` doesn't exist.
        try {
            DB::table('claims')->limit(1)->first();
            return 'claims';
        } catch (\Exception $e) {
            return 'new_claims';
        }
    }

    /**
     * Header totals for the Reserves tab, V8-parity.
     *
     * Matches graphiteBWV8 ClaimsController::show (lines 449-488). On top of
     * the naive SUM(reserve_amt) / SUM(payment_amt) we also subtract:
     *   • subrogation_reserve / subrogation_payment columns (separate buckets)
     *   • salvage_reserve / salvage_payment columns (separate buckets)
     *   • payment_amt of any row whose parent claim_reserves.transaction_type
     *     is 44 (Reset Reserves) — Reset rows zero out a prior reserve, they
     *     shouldn't add to either header total
     *   • payment_amt of any row with is_payment_voided = 1 — voided payments
     *     are already reversed by their is_payment_voided=2 partner row, so
     *     the original needs to be backed out of both totals
     *
     * Returns floats: ['totalReserve', 'totalPayment', 'balance'].
     */
    private function reserveHeaderTotals($claimIds): array
    {
        $isList = is_array($claimIds) || $claimIds instanceof \Illuminate\Support\Collection;
        $ids = $isList ? collect($claimIds)->all() : [$claimIds];
        if (empty($ids)) {
            return $isList ? [] : ['totalReserve' => 0.0, 'totalPayment' => 0.0, 'balance' => 0.0];
        }

        $base = DB::table('claim_reserves_coverages as crc')
            ->whereIn('crc.claim_id', $ids)
            ->groupBy('crc.claim_id')
            ->select(
                'crc.claim_id',
                DB::raw('SUM(COALESCE(crc.reserve_amt, 0)) as total_reserve'),
                DB::raw('SUM(COALESCE(crc.payment_amt, 0)) as total_payment'),
                DB::raw('SUM(COALESCE(crc.subrogation_reserve, 0)) as subrogation_reserve'),
                DB::raw('SUM(COALESCE(crc.subrogation_payment, 0)) as subrogation_payment'),
                DB::raw('SUM(COALESCE(crc.salvage_reserve, 0)) as salvage_reserve'),
                DB::raw('SUM(COALESCE(crc.salvage_payment, 0)) as salvage_payment'),
                DB::raw('SUM(CASE WHEN crc.is_payment_voided = 1 THEN COALESCE(crc.payment_amt, 0) ELSE 0 END) as voided_payment')
            )
            ->get()
            ->keyBy('claim_id');

        $type44 = DB::table('claim_reserves_coverages as crc')
            ->join('claim_reserves as cr', 'cr.id', '=', 'crc.reserve_id')
            ->whereIn('crc.claim_id', $ids)
            ->where('cr.transaction_type', 44)
            ->groupBy('crc.claim_id')
            ->select('crc.claim_id', DB::raw('SUM(COALESCE(crc.payment_amt, 0)) as type44_payment'))
            ->get()
            ->keyBy('claim_id');

        $compute = function ($claimId) use ($base, $type44) {
            $b   = $base->get($claimId);
            $t44 = (float) ($type44->get($claimId)->type44_payment ?? 0);
            $totalReserve = (float) ($b->total_reserve ?? 0);
            $totalPayment = (float) ($b->total_payment ?? 0);
            $voided       = (float) ($b->voided_payment ?? 0);
            $payment = $totalPayment
                - (float) ($b->subrogation_payment ?? 0)
                - (float) ($b->salvage_payment ?? 0)
                - $t44
                - $voided;
            $reserve = $totalReserve
                - (float) ($b->subrogation_reserve ?? 0)
                - (float) ($b->salvage_reserve ?? 0)
                - $t44
                - $voided;
            return ['totalReserve' => $reserve, 'totalPayment' => $payment, 'balance' => $reserve - $payment];
        };

        if ($isList) {
            $out = [];
            foreach ($ids as $cid) {
                $out[$cid] = $compute($cid);
            }
            return $out;
        }
        return $compute($ids[0]);
    }

    /**
     * Build a CDN URL from an S3 path.
     */
    private function cdnUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }
        $cdn = config('filesystems.disks.s3.cdn_url') ?: env('AWS_CLOUDFRONT');
        if (!$cdn) {
            return null;
        }
        $path = str_replace(['\/', ' '], ['/', '%20'], $path);
        return rtrim($cdn, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Store an uploaded file while PRESERVING its original filename.
     *
     * Every read path (show/listDocuments/uploadDocument response) derives the
     * displayed + downloaded filename from basename() of the stored path, so
     * Laravel's default store() — which assigns a random 40-char hashName —
     * made documents unidentifiable. We instead place each file in its own
     * unique sub-folder (a UUID) and keep the original filename as the object
     * name, so basename() returns exactly what the user uploaded while the UUID
     * folder guarantees uniqueness (same-named files never collide).
     *
     * The name is lightly sanitised for S3/URL safety only: path separators and
     * URL-breaking characters (# ? % &) are replaced. Spaces are preserved —
     * cdnUrl() already encodes them to %20. Backward compatible: existing rows
     * keep their old stored paths untouched; only new uploads get clean names.
     */
    private function storeWithOriginalName(\Illuminate\Http\UploadedFile $file, string $dir): string
    {
        $original = $file->getClientOriginalName();
        $safe = preg_replace('#[/\\\\]+#', '_', $original);   // no path separators
        $safe = preg_replace('/[#?%&]+/', '_', $safe);        // no URL-breaking chars
        $safe = trim($safe);
        if ($safe === '') {
            $safe = 'file.' . ($file->getClientOriginalExtension() ?: 'dat');
        }
        return $file->storeAs($dir . '/' . (string) \Illuminate\Support\Str::uuid(), $safe, 's3');
    }

    /**
     * Allowed status transitions.
     */
    private function allowedTransitions(): array
    {
        return [
            'New'                => ['Pending Assessment', 'Approved', 'Rejected'],
            // V8/V2-default state for newly-registered claims; same exits
            // as 'New' so legacy and current claims share one transition map.
            'Pending'            => ['Pending Assessment', 'Approved', 'Rejected'],
            'Pending Assessment' => ['Approved', 'Rejected'],
            'Approved'           => ['Closed'],
            'Rejected'           => ['Closed', 'Reopen'],
            'Closed'             => ['Reopen'],
            'Reopen'             => ['Pending Assessment', 'Approved'],
        ];
    }

    /**
     * Convert a stdClass / array row to camelCase keys.
     */
    private function camelRow($row): array
    {
        $arr = (array) $row;
        $out = [];
        foreach ($arr as $key => $value) {
            $out[lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))))] = $value;
        }
        return $out;
    }

    /**
     * Batch-convert a collection of rows to camelCase.
     */
    private function camelRows($rows): array
    {
        return array_map(fn($r) => $this->camelRow($r), is_array($rows) ? $rows : $rows->toArray());
    }

    // ─── 1. index ────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status'     => 'nullable|string|max:50',
            'claim_type' => 'nullable|string|max:100',
            'search'     => 'nullable|string|max:100',
            'date_from'  => 'nullable|date_format:Y-m-d',
            'date_to'    => 'nullable|date_format:Y-m-d',
            'per_page'   => 'nullable|integer|min:5|max:100',
            'page'       => 'nullable|integer|min:1',
        ]);

        $table   = $this->claimsTable();
        $perPage = (int) ($validated['per_page'] ?? 30);
        $page    = (int) ($validated['page'] ?? 1);

        $query = DB::table($table)
            ->select(
                "{$table}.id",
                "{$table}.claim_number",
                "{$table}.claim_type",
                "{$table}.status",
                "{$table}.policy_id",
                "{$table}.customer_id",
                "{$table}.created_at"
            );

        // If new_claims has policyNumber column use it; otherwise join policies
        if ($table === 'new_claims') {
            $query->addSelect("{$table}.policyNumber");
        }

        // Search: claim_number, policyNumber, customer name
        if (!empty($validated['search'])) {
            $s = trim(preg_replace('/\s+/', ' ', $validated['search']));
            $words = count(explode(' ', $s)) >= 2
                ? array_values(array_filter(explode(' ', $s)))
                : [];
            $query->where(function ($q) use ($s, $table, $words) {
                $q->where("{$table}.claim_number", 'like', "%{$s}%");
                // Join policies for policyNumber search
                if ($table !== 'new_claims') {
                    $q->orWhereExists(function ($sub) use ($s, $table) {
                        $sub->select(DB::raw(1))
                            ->from('policies')
                            ->whereColumn('policies.id', "{$table}.policy_id")
                            ->where('policies.policyNumber', 'like', "%{$s}%");
                    });
                } else {
                    $q->orWhere("{$table}.policyNumber", 'like', "%{$s}%");
                }
                // Customer name search
                $q->orWhereExists(function ($sub) use ($s, $table, $words) {
                    $sub->select(DB::raw(1))
                        ->from('customer')
                        ->whereColumn('customer.id', "{$table}.customer_id")
                        ->where(function ($inner) use ($s, $words) {
                            $inner->where('customer.firstName', 'like', "%{$s}%")
                                  ->orWhere('customer.lastName', 'like', "%{$s}%")
                                  ->orWhereRaw("CONCAT_WS(' ', TRIM(customer.firstName), TRIM(customer.lastName)) LIKE ?", ["%{$s}%"]);
                            if (count($words) >= 2) {
                                $inner->orWhere(fn($i) =>
                                    $i->where('customer.firstName', 'like', "%{$words[0]}%")
                                      ->where('customer.lastName', 'like', "%{$words[1]}%")
                                )->orWhere(fn($i) =>
                                    $i->where('customer.firstName', 'like', "%{$words[1]}%")
                                      ->where('customer.lastName', 'like', "%{$words[0]}%")
                                );
                            }
                        });
                });
            });
        }

        // Filters
        if (!empty($validated['status'])) {
            $query->where("{$table}.status", $validated['status']);
        }
        if (!empty($validated['claim_type'])) {
            $query->where("{$table}.claim_type", $validated['claim_type']);
        }
        if (!empty($validated['date_from'])) {
            $query->where("{$table}.created_at", '>=', $validated['date_from'] . ' 00:00:00');
        }
        if (!empty($validated['date_to'])) {
            $query->where("{$table}.created_at", '<=', $validated['date_to'] . ' 23:59:59');
        }

        $query->orderBy("{$table}.id", 'desc');

        // Total count
        $total = $query->count();

        // Paginate
        $rows = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();

        // Batch-load policy numbers and customer names
        $policyIds   = $rows->pluck('policy_id')->filter()->unique()->values()->all();
        $customerIds = $rows->pluck('customer_id')->filter()->unique()->values()->all();

        $policies = [];
        if ($policyIds) {
            $policies = DB::table('policies')
                ->whereIn('id', $policyIds)
                ->select('id', 'policyNumber')
                ->get()
                ->keyBy('id');
        }

        $customers = [];
        if ($customerIds) {
            $customers = DB::table('customer')
                ->whereIn('id', $customerIds)
                ->select('id', 'firstName', 'lastName')
                ->get()
                ->keyBy('id');
        }

        // Reserve totals per claim — V8-parity (subrogation / salvage /
        // transaction_type=44 / voided rows all backed out).
        $claimIds = $rows->pluck('id')->all();
        $reserveTotals = $claimIds ? $this->reserveHeaderTotals($claimIds) : [];

        $data = $rows->map(function ($row) use ($policies, $customers, $reserveTotals, $table) {
            $pol = $policies[$row->policy_id] ?? null;
            $cust = $customers[$row->customer_id] ?? null;
            $res = $reserveTotals[$row->id] ?? ['totalReserve' => 0.0, 'totalPayment' => 0.0, 'balance' => 0.0];

            $policyNumber = ($table === 'new_claims' && !empty($row->policyNumber))
                ? $row->policyNumber
                : ($pol ? $pol->policyNumber : null);

            return [
                'id'            => $row->id,
                'claimNumber'   => $row->claim_number,
                'claimType'     => $row->claim_type,
                'status'        => $row->status,
                'policyId'      => $row->policy_id,
                'policyNumber'  => $policyNumber,
                'customerId'    => $row->customer_id,
                'customerName'  => $cust ? trim($cust->firstName . ' ' . $cust->lastName) : null,
                'totalReserve'  => $res['totalReserve'],
                'totalPayment'  => $res['totalPayment'],
                'createdAt'     => $row->created_at,
            ];
        })->values()->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'total'       => $total,
                'perPage'     => $perPage,
                'currentPage' => $page,
                'lastPage'    => (int) ceil($total / $perPage),
            ],
        ]);
    }

    // ─── 2. show ─────────────────────────────────────────────────────────────

    public function show(int $id): JsonResponse
    {
        $table = $this->claimsTable();

        $claim = DB::table($table)->where('id', $id)->first();
        if (!$claim) {
            return response()->json(['message' => 'Claim not found.'], 404);
        }

        // Policy info
        $policy = null;
        if ($claim->policy_id) {
            $policy = DB::table('policies')
                ->where('id', $claim->policy_id)
                ->select('id', 'policyNumber', 'product_id', 'customer_id', 'agent_id', 'status', 'premium')
                ->first();
        }

        // Customer info
        $customer = null;
        $custId = $claim->customer_id ?? ($policy ? $policy->customer_id : null);
        if ($custId) {
            $customer = DB::table('customer')
                ->where('id', $custId)
                ->select('id', 'firstName', 'lastName', 'email', 'cellphone')
                ->first();
        }

        // Reserves summary — V8-parity totals (subrogation / salvage /
        // transaction_type=44 / voided rows all backed out).
        $totals = $this->reserveHeaderTotals($id);

        // Documents
        $documents = DB::table('claim_attachments')
            ->where('claim_id', $id)
            ->select('id', 'name', 'file_name', 'file_type', 'created_at')
            ->get()
            ->map(fn($d) => [
                'id'       => $d->id,
                'name'     => $d->name,
                'fileName' => $d->file_name,
                'fileType' => $d->file_type,
                'url'      => $this->cdnUrl($d->file_name),
                'createdAt' => $d->created_at,
            ])->values()->all();

        // Assessment
        $assessment = DB::table('claim_assessment')
            ->where('claim_id', $id)
            ->first();

        $claimData = $this->camelRow($claim);
        $claimData['policy'] = $policy ? [
            'id'           => $policy->id,
            'policyNumber' => $policy->policyNumber,
            'productId'    => $policy->product_id,
            'status'       => $policy->status,
            'premium'      => $policy->premium,
        ] : null;
        $claimData['customer'] = $customer ? [
            'id'        => $customer->id,
            'name'      => trim($customer->firstName . ' ' . $customer->lastName),
            'email'     => $customer->email,
            'cellphone' => $customer->cellphone,
        ] : null;
        $claimData['reservesSummary'] = [
            'totalReserve' => $totals['totalReserve'],
            'totalPayment' => $totals['totalPayment'],
            'balance'      => $totals['balance'],
        ];
        $claimData['documents'] = $documents;
        $claimData['assessment'] = $assessment ? $this->camelRow($assessment) : null;

        return response()->json(['data' => $claimData]);
    }

    // ─── 3. store ────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'policy_id'        => 'required|integer',
            // graphiteBWV8 stores the action term picked at claim creation as
            // claims.policy_action_id; the Reserves tab uses it later to scope
            // the coverage list. Optional — null falls back to all coverages.
            'policy_action_id' => 'nullable|integer',
            'claim_type'       => 'required|string|max:100',
            'date_of_loss'     => 'nullable|date_format:Y-m-d',
            'description'      => 'nullable|string|max:5000',
            'category'         => 'nullable|string|max:100',
            'registered_claim' => 'nullable|string|max:255',
            'documents'        => 'nullable|array|max:5',
            'documents.*'      => 'file|max:10240',
        ]);

        // Verify policy exists
        $policy = DB::table('policies')
            ->where('id', $validated['policy_id'])
            ->select('id', 'policyNumber', 'customer_id', 'agent_id', 'status')
            ->first();

        if (!$policy) {
            return response()->json(['message' => 'Policy not found.'], 422);
        }

        // Block claims against inactive cover. policies.status: 0 = deactivated/
        // pending, 1 = activated, 2 = cancelled, 3 = expired/lapsed (fraud/leakage
        // guard — QA finding C4).
        //
        // FLAG-GATED, DEFAULT OFF. Live data: ~23–30 claims/quarter are currently
        // registered against CANCELLED policies and processed (0 against expired),
        // and date_of_loss is frequently null at registration — so we cannot tell a
        // legitimate in-cover-period late claim from genuine leakage. A blanket hard
        // block would stop that active flow and risk rejecting valid claims. So the
        // guard only enforces when CLAIMS_BLOCK_DEAD_POLICY=true; enable it
        // deliberately once Claims/Finance have validated the affected cases.
        if (config('claims.block_dead_policy', false) && in_array((int) $policy->status, [2, 3], true)) {
            return response()->json([
                'message' => 'Cannot register a claim against a cancelled or expired policy.',
            ], 422);
        }

        $table = $this->claimsTable();

        try {
            DB::beginTransaction();

            // Generate claim number
            $latestId = DB::table($table)->max('id') ?? 0;
            $nextId = $latestId + 1;
            $claimNumber = 'G' . date('Y') . str_pad($nextId, 6, '0', STR_PAD_LEFT);

            $now = Carbon::now();

            $insertData = [
                'policy_id'        => $policy->id,
                'policy_action_id' => $validated['policy_action_id'] ?? null,
                'customer_id'      => $policy->customer_id,
                'agent_id'         => $policy->agent_id,
                'claim_type'       => $validated['claim_type'],
                'claim_number'     => $claimNumber,
                'status'           => 'New',
                'category'         => $validated['category'] ?? null,
                'registered_claim' => $validated['registered_claim'] ?? null,
                'created_by'       => auth()->id(),
                'created_at'       => $now,
                'updated_at'       => $now,
            ];

            if ($table === 'new_claims') {
                $insertData['policyNumber'] = $policy->policyNumber;
                $insertData['date_of_loss'] = $validated['date_of_loss'] ?? null;
                $insertData['note'] = $validated['description'] ?? null;
                $insertData['claim_approved'] = 'No';
                $insertData['is_motor_claim'] = 0;
                $insertData['co_attorney_involved'] = 0;
                $insertData['attorney_involved'] = 0;
                $insertData['claim_reported_by'] = '';
                $insertData['claim_sub_type_id'] = 0;
                $insertData['type_of_loss'] = 0;
                $insertData['date_of_loss'] = $validated['date_of_loss'] ?? $now->format('Y-m-d');
                $insertData['catastrophe_loss'] = 0;
                $insertData['dfs_complaint'] = 0;
            } else {
                $insertData['note'] = $validated['description'] ?? null;
            }

            // Strip any keys that don't exist on this env's claims table
            // (e.g. policy_action_id is recently-added and may not exist on
            // the new_claims fallback path). Mirrors how attachment uploads
            // defensively trim to the actual column set.
            $tableCols  = \Schema::getColumnListing($table);
            $insertData = array_intersect_key($insertData, array_flip($tableCols));

            $claimId = DB::table($table)->insertGetId($insertData);

            // Seed an empty stage-timeline row so a just-registered claim appears
            // in the dashboard's workflow-driven sections immediately (v5 — new
            // claims otherwise only show once an SLA stage is first edited).
            // Best-effort — never block registration.
            try {
                ClaimTrackerWorkflow::firstOrCreate(['claim_id' => $claimId]);
            } catch (\Throwable $e) {
                \Log::warning('claim workflow seed failed for claim ' . $claimId . ': ' . $e->getMessage());
            }

            // Handle file uploads
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $path = $this->storeWithOriginalName($file, "MIS/{$claimId}/Documents");
                    DB::table('claim_attachments')->insert([
                        'claim_id'   => $claimId,
                        'name'       => $file->getClientOriginalName(),
                        'file_name'  => $path,
                        'file_type'  => $file->getClientMimeType(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            // Initial Reserves entry. Mirrors graphiteBWV8 NewClaimController::store
            // (line 420+) which seeds a single transaction_type=86 ("Initial Reserves")
            // header on every freshly registered claim with the auth'd user as
            // payee. The reserves blade later filters this row out of the Add
            // form's transaction-type dropdown but keeps it visible in the table
            // as the opening balance ledger row.
            $initialReserveId = DB::table('claim_reserves')->insertGetId([
                'claim_id'         => $claimId,
                'date'             => $now->format('Y-m-d'),
                'product_id'       => $policy->product_id ?? null,
                'transaction_type' => 86,
                'payee'            => auth()->id(),
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
            DB::table('claim_reserves_coverages')->insert([
                'reserve_id'    => $initialReserveId,
                'claim_id'      => $claimId,
                'product_id'    => $policy->product_id ?? null,
                'coverage_id'   => 0,
                'coverage_name' => null,
                'reserve_amt'   => 0,
                'payment_amt'   => 0,
                'balance'       => 0,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);

            // Log activity
            $this->logActivity($claimId, $table, "Claim Registered: {$claimNumber} — Type: {$validated['claim_type']}");

            DB::commit();

            // Claims automation: gated at DISPATCH so the event and all its
            // listeners stay fully dormant until switched on. Source of truth is
            // the runtime toggle (Admin > Integrations), audited and per-env;
            // defaults off. Never break the response.
            if (\AlphaDirect\Services\IntegrationSettings::isEnabled('claims_automation', false)) {
                try {
                    event(new \AlphaDirect\Events\ClaimEvent($claimId, 'claim_created', [
                        'claim_type' => $validated['claim_type'],
                        'source'     => 'graphite',
                    ]));
                } catch (\Throwable $e) {
                    \Log::warning('claim_created event dispatch failed', [
                        'claim_id' => $claimId, 'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'data' => [
                    'id'           => $claimId,
                    'claimNumber'  => $claimNumber,
                    'claimType'    => $validated['claim_type'],
                    'status'       => 'New',
                    'policyId'     => $policy->id,
                    'policyNumber' => $policy->policyNumber,
                ],
                'message' => 'Claim registered successfully.',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ClaimsV2 store failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Failed to register claim.',
                'error'   => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    // ─── 4. update ───────────────────────────────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $table = $this->claimsTable();
        $claim = DB::table($table)->where('id', $id)->first();

        if (!$claim) {
            return response()->json(['message' => 'Claim not found.'], 404);
        }
        if ($claim->status === 'Closed') {
            return response()->json(['message' => 'Cannot update a closed claim.'], 422);
        }

        $allowed = [
            'claim_type', 'category', 'registered_claim', 'note',
            'date_of_loss', 'claim_reported_by', 'event_name',
            'claim_allocated_to', 'reason', 'description',
        ];

        $updateData = [];
        foreach ($allowed as $field) {
            if ($request->has($field)) {
                $updateData[$field] = $request->input($field);
            }
        }

        if (empty($updateData)) {
            return response()->json(['message' => 'No fields to update.'], 422);
        }

        $updateData['updated_at'] = Carbon::now();

        DB::table($table)->where('id', $id)->update($updateData);

        $this->logActivity($id, $table, 'Claim updated: ' . implode(', ', array_keys($updateData)));

        return response()->json([
            'data'    => ['id' => $id],
            'message' => 'Claim updated successfully.',
        ]);
    }

    // ─── 5. updateStatus ─────────────────────────────────────────────────────

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $table = $this->claimsTable();
        $claim = DB::table($table)->where('id', $id)->first();

        if (!$claim) {
            return response()->json(['message' => 'Claim not found.'], 404);
        }

        $validated = $request->validate([
            'status'      => 'required|string',
            'closed_note' => 'nullable|string|max:2000',
        ]);

        $newStatus  = $validated['status'];
        $oldStatus  = $claim->status;
        $allowed    = $this->allowedTransitions();

        if (!isset($allowed[$oldStatus]) || !in_array($newStatus, $allowed[$oldStatus])) {
            return response()->json([
                'message' => "Cannot transition from '{$oldStatus}' to '{$newStatus}'.",
                'allowedTransitions' => $allowed[$oldStatus] ?? [],
            ], 422);
        }

        // Require note for Closed/Rejected
        if (in_array($newStatus, ['Closed', 'Rejected']) && empty($validated['closed_note'])) {
            return response()->json([
                'message' => 'A closed_note is required when closing or rejecting a claim.',
            ], 422);
        }

        $update = [
            'status'     => $newStatus,
            'updated_at' => Carbon::now(),
        ];
        if (!empty($validated['closed_note'])) {
            $update['closed_note'] = $validated['closed_note'];
        }
        if ($newStatus === 'Reopen') {
            $update['reopen_claim_sub_status'] = $oldStatus;
        }

        DB::table($table)->where('id', $id)->update($update);

        $this->logActivity($id, $table, "Claim status changed from {$oldStatus} to {$newStatus}");

        return response()->json([
            'data'    => ['id' => $id, 'status' => $newStatus, 'previousStatus' => $oldStatus],
            'message' => 'Claim status updated successfully.',
        ]);
    }

    // ─── 6. reserves ─────────────────────────────────────────────────────────

    public function reserves(int $claimId): JsonResponse
    {
        $reserves = DB::table('claim_reserves')
            ->where('claim_id', $claimId)
            ->orderBy('id', 'asc') // chronological for running-balance accumulation
            ->get();

        if ($reserves->isEmpty()) {
            return response()->json([
                'data'    => [],
                'totals'  => ['totalReserve' => 0, 'totalPayment' => 0, 'balance' => 0],
            ]);
        }

        $reserveIds = $reserves->pluck('id')->all();
        $coverages  = DB::table('claim_reserves_coverages')
            ->whereIn('reserve_id', $reserveIds)
            ->orderBy('id', 'asc')
            ->get()
            ->groupBy('reserve_id');

        // Lookup name resolution (transaction_type / transaction_sub_type
        // both live in lookup_data keyed by the lookup id). Batch-load so
        // we don't hammer the DB per row.
        $typeIds = $reserves->pluck('transaction_type')->filter()->unique()->all();
        $subIds  = $reserves->pluck('transaction_sub_type')->filter()->unique()->all();
        $lookupNames = [];
        if ($typeIds || $subIds) {
            $lookupNames = DB::table('lookup_data')
                ->whereIn('id', array_merge($typeIds, $subIds))
                ->pluck('value', 'id')
                ->all();
        }

        // Payee resolution: graphiteBWV8 stores the user_id for type 86
        // (Initial Reserves) and the supplier_id for everything else, both
        // in the same `payee` column. Batch-load both sources so the FE
        // doesn't see a raw numeric id where a name was expected.
        $payeeIds = $reserves->pluck('payee')->filter(fn($v) => is_numeric($v))->map(fn($v) => (int) $v)->unique()->all();
        $supplierNames = $payeeIds ? DB::table('suppliers')->whereIn('id', $payeeIds)->pluck('supplierName', 'id')->all() : [];
        $userNames     = $payeeIds
            ? DB::table('users')->whereIn('id', $payeeIds)->get(['id','firstName','lastName'])
                ->mapWithKeys(fn($u) => [$u->id => trim($u->firstName . ' ' . $u->lastName)])->all()
            : [];

        // Inserted-by user. graphiteBWV8 doesn't track this on claim_reserves
        // directly, but the V2 frontend interface expects it. We approximate
        // by reading the payee for type-86 rows (creator) and falling back
        // to the claim creator for other rows so the column never goes blank.
        $claim = DB::table($this->claimsTable())->where('id', $claimId)->first(['created_by']);
        $claimCreatorName = null;
        if ($claim && $claim->created_by) {
            $u = DB::table('users')->where('id', $claim->created_by)->first(['firstName','lastName']);
            $claimCreatorName = $u ? trim($u->firstName . ' ' . $u->lastName) : null;
        }

        // Aggregate void-log lookups so we can flag rows that have logs
        // (canVoid=false on already-voided rows; voidCrcId points at the
        // log so the FE can hit getVoidPaymentInfo for the modal).
        $allCovIds = $coverages->flatten(1)->pluck('id')->all();
        $voidLogIds = $allCovIds
            ? DB::table('claim_void_payment_logs')
                ->whereIn('claim_reserves_coverages_id', $allCovIds)
                ->pluck('claim_reserves_coverages_id')
                ->unique()->all()
            : [];
        $voidLogIdSet = array_flip($voidLogIds);

        $totalReserve = 0;
        $totalPayment = 0;
        $finalBalance = 0;

        $data = $reserves->map(function ($res) use (
            $coverages, $lookupNames, $supplierNames, $userNames,
            $claimCreatorName, $voidLogIdSet, &$totalReserve, &$totalPayment, &$finalBalance
        ) {
            $covs = $coverages->get($res->id, collect([]));
            $txType = (int) $res->transaction_type;

            // Per-row plus/minus derivation mirrors graphiteBWV8 coverageData
            // (line 1733+): each transaction_type maps to a different amount
            // column. Sum across all this header's coverage rows.
            $plus = 0; $minus = 0; $rowBalance = 0; $hasVoidedFlag = false; $voidEntryFlag = false;
            $hasWriteOff = false; // true if any coverage in this reserve is a write-off
            $covList = [];
            foreach ($covs as $c) {
                $reserveAmt   = (float) ($c->reserve_amt ?? 0);
                $paymentAmt   = (float) ($c->payment_amt ?? 0);
                $subResAmt    = (float) ($c->subrogation_reserve ?? 0);
                $subPayAmt    = (float) ($c->subrogation_payment ?? 0);
                $salResAmt    = (float) ($c->salvage_reserve ?? 0);
                $salPayAmt    = (float) ($c->salvage_payment ?? 0);
                $rowBalance   = (float) ($c->balance ?? 0); // last coverage's balance == row's running balance

                if ($txType === 90)      { $plus  += $subPayAmt; }
                elseif ($txType === 92)  { $plus  += $salPayAmt; }
                else                     { $plus  += $reserveAmt; }

                if ($txType === 89)      { $minus += $subResAmt; }
                elseif ($txType === 91)  { $minus += $salResAmt; }
                else                     { $minus += $paymentAmt; }

                $totalReserve += $reserveAmt + $subResAmt + $salResAmt;
                $totalPayment += $paymentAmt + $subPayAmt + $salPayAmt;

                $voided = (int) ($c->is_payment_voided ?? 0);
                if ($voided === 1) $hasVoidedFlag = true;
                if ($voided === 2) $voidEntryFlag = true;
                if ((int) ($c->write_off ?? 0) === 1) $hasWriteOff = true;

                $covList[] = [
                    'id'                  => $c->id,
                    'coverageId'          => (int) ($c->coverage_id ?? 0),
                    'coverageName'        => $c->coverage_name,
                    'reserveAmt'          => $reserveAmt,
                    'paymentAmt'          => $paymentAmt,
                    'subrogationReserve'  => $subResAmt,
                    'subrogationPayment'  => $subPayAmt,
                    'salvageReserve'      => $salResAmt,
                    'salvagePayment'      => $salPayAmt,
                    'balance'             => (float) ($c->balance ?? 0),
                    'isPaymentVoided'     => $voided,
                    'writeOff'            => (bool) ($c->write_off ?? 0),
                ];
            }
            $finalBalance = $rowBalance;

            // canVoid: only payment-style rows are voidable, only when not
            // already voided and not themselves a voided/reversal entry.
            // Mirrors graphiteBWV8 coverageData actions column logic.
            $isPaymentType = in_array($txType, [43, 90, 92], true);
            $voidCrcId = null;
            if ($covList) {
                // V8 voids one coverage at a time using its claim_reserves_coverages.id;
                // surface the first non-voided payment row so the FE has a target.
                foreach ($covs as $c) {
                    if ((int) ($c->is_payment_voided ?? 0) === 0) {
                        $voidCrcId = $c->id;
                        break;
                    }
                }
            }
            $canVoid = $isPaymentType && !$hasVoidedFlag && !$voidEntryFlag && $voidCrcId !== null;

            // Payee name resolution: type 86 (Initial Reserves) stores user_id;
            // every other type stores supplier_id. Same column, different table.
            $payeeRaw  = $res->payee;
            $payeeName = null;
            if (is_numeric($payeeRaw)) {
                $pid = (int) $payeeRaw;
                $payeeName = $txType === 86
                    ? ($userNames[$pid] ?? null)
                    : ($supplierNames[$pid] ?? $userNames[$pid] ?? null);
            } else {
                $payeeName = $payeeRaw ?: null;
            }

            return [
                'id'                     => $res->id,
                'claimId'                => $res->claim_id,
                'date'                   => $res->date,
                'transactionType'        => $txType,
                'transactionTypeName'    => $lookupNames[$txType] ?? null,
                'transactionSubType'     => $res->transaction_sub_type !== null ? (int) $res->transaction_sub_type : null,
                'transactionSubTypeName' => isset($res->transaction_sub_type) ? ($lookupNames[(int) $res->transaction_sub_type] ?? null) : null,
                'payee'                  => $res->payee !== null ? (string) $res->payee : null,
                'payeeName'              => $payeeName,
                'address'                => $res->address ?? null,
                'invoiceNo'              => $res->invoice_no ?? null,
                'invoiceDate'            => $res->invoice_date ?? null,
                'invoiceDueDate'         => $res->invoice_due_date ?? null,
                'memo'                   => $res->memo ?? null,
                'description'            => $res->description ?? null,
                'creditNote'             => (int) ($res->credit_note ?? 0),
                'includeVat'             => (bool) ($res->include_vat ?? 0),
                'createdAt'              => $res->created_at ?? null,
                'insertedBy'             => null,
                'insertedByName'         => $payeeName ?: $claimCreatorName,
                'plusAmount'             => $plus,
                'minusAmount'            => $minus,
                'runningBalance'         => $rowBalance,
                // V8's coverageData appends " - VOID" when is_payment_voided=2
                // (the reversal entry); preserve that hint for the FE.
                'isVoided'               => $voidEntryFlag,
                'canVoid'                => $canVoid,
                'voidCrcId'              => $voidCrcId,
                'hasWriteOff'            => $hasWriteOff,
                'hasVoidLog'             => $covList && array_intersect_key($voidLogIdSet, array_flip(array_column($covList, 'id'))) !== [],
                'coverages'              => $covList,
            ];
        })->values()->all();

        // Frontend renders newest-first; sort here so the FE doesn't need
        // to flip the order it received (chronological is only useful for
        // accumulating the running balance).
        $data = array_reverse($data);

        return response()->json([
            'data'   => $data,
            'totals' => [
                'totalReserve' => $totalReserve,
                'totalPayment' => $totalPayment,
                'balance'      => $finalBalance,
            ],
        ]);
    }

    // ─── 7. storeReserve ─────────────────────────────────────────────────────

    public function storeReserve(Request $request, int $claimId): JsonResponse
    {
        // The V2 FE sends transaction_sub_type as a JSON number (the lookup id),
        // while the legacy lookup-fallback path sends a string label. Normalise
        // to a string up front so the nullable|string rule accepts both — without
        // this, a numeric id fails with "The transaction sub type must be a string"
        // on every reserve/payment submit. storeReserve persists it as-is.
        if ($request->filled('transaction_sub_type')) {
            $request->merge([
                'transaction_sub_type' => (string) $request->input('transaction_sub_type'),
            ]);
        }

        $validated = $request->validate([
            'transaction_type'     => 'required|integer',
            // Lookup fallback uses string IDs (e.g. "Initial Reserve") when the
            // lookup_data table is empty, so this can arrive as either an int
            // or a string. Relaxed to nullable|string|max:100 — storeReserve
            // stringifies on insert regardless.
            'transaction_sub_type' => 'nullable|string|max:100',
            'date'                 => 'nullable|date_format:Y-m-d',
            'payee'                => 'nullable|string|max:255',
            'address'              => 'nullable|string|max:500',
            'invoice_no'           => 'nullable|string|max:100',
            'invoice_date'         => 'nullable|date_format:Y-m-d',
            'invoice_due_date'     => 'nullable|date_format:Y-m-d',
            'memo'                 => 'nullable|string|max:500',
            'description'          => 'nullable|string|max:1000',
            'credit_note'          => 'nullable|boolean',
            'include_vat'          => 'nullable|boolean',
            'product_id'           => 'nullable|integer',
            'coverages'            => 'required|array|min:1',
            // coverage_id of 0 means "coverage not matched against policy_coverages"
            // — still valid for reserve tracking. Accept 0+.
            'coverages.*.coverage_id'   => 'required|integer|min:0',
            'coverages.*.coverage_name' => 'required|string|max:255',
            'coverages.*.amount'        => 'required|numeric|min:0.01',
            // Per-coverage write-off flag. Only honoured for Loss Reserve (42)
            // + "Claim Expense" sub-type — enforced server-side below so a
            // crafted request can't flag write-offs on other transaction types.
            'coverages.*.write_off'     => 'nullable|boolean',
            // Recipient address(es) for the write-off email, entered on the
            // form. Accepts one or many; each must be a valid email. When
            // omitted, NotificationDispatcher falls back to the configured
            // department mailboxes.
            'write_off_emails'          => 'nullable|array',
            'write_off_emails.*'        => 'email',
        ]);

        $table = $this->claimsTable();
        $claim = DB::table($table)->where('id', $claimId)->first();
        if (!$claim) {
            return response()->json(['message' => 'Claim not found.'], 404);
        }

        // Mirror graphiteBWV8: the reserves blade hides the Add button
        // when the claim is Closed. Enforce the same rule on the backend
        // so direct API hits can't bypass it.
        if (in_array($claim->status, ['Closed'], true)) {
            return response()->json(['message' => 'Cannot add reserves to a closed claim.'], 422);
        }

        try {
            DB::beginTransaction();

            $now = Carbon::now();

            // Determine product_id from the policy if not supplied
            $productId = $validated['product_id'] ?? null;
            if (!$productId && $claim->policy_id) {
                $policy = DB::table('policies')->where('id', $claim->policy_id)->select('product_id')->first();
                $productId = $policy ? $policy->product_id : null;
            }

            $reserveId = DB::table('claim_reserves')->insertGetId([
                'claim_id'            => $claimId,
                'date'                => $validated['date'] ?? $now->format('Y-m-d'),
                'product_id'          => $productId,
                'transaction_type'    => $validated['transaction_type'],
                'transaction_sub_type'=> $validated['transaction_sub_type'] ?? null,
                'payee'               => $validated['payee'] ?? null,
                'address'             => $validated['address'] ?? null,
                'invoice_no'          => $validated['invoice_no'] ?? null,
                'invoice_date'        => $validated['invoice_date'] ?? null,
                'invoice_due_date'    => $validated['invoice_due_date'] ?? null,
                'memo'                => $validated['memo'] ?? null,
                'description'         => $validated['description'] ?? null,
                'credit_note'         => !empty($validated['credit_note']) ? 1 : 0,
                'include_vat'         => !empty($validated['include_vat']) ? 1 : 0,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);

            // Get latest balance for this claim
            $lastCov = DB::table('claim_reserves_coverages')
                ->where('claim_id', $claimId)
                ->orderBy('id', 'desc')
                ->select('balance')
                ->first();
            $balance = $lastCov ? (float) $lastCov->balance : 0;

            $txType    = (int) $validated['transaction_type'];
            $subTypeId = is_numeric($validated['transaction_sub_type'] ?? null) ? (int) $validated['transaction_sub_type'] : null;

            // Write-off is only valid on a Loss Reserve (42) logged against the
            // "Claim Expense" sub-type. Resolve the sub-type's label from
            // lookup_data and gate on it so the per-coverage write_off flag
            // can't be set for any other transaction/sub-type combination.
            $writeOffEligible = false;
            if ($txType === 42 && $subTypeId !== null && \Schema::hasTable('lookup_data')) {
                $subLabel = DB::table('lookup_data')->where('id', $subTypeId)->value('value');
                $writeOffEligible = $subLabel !== null && strcasecmp(trim($subLabel), 'Claim Expense') === 0;
            }
            $writeOffUserId = optional($request->user())->id;
            $writtenOffCoverages = []; // coverage names flagged this submission — notified post-commit

            // Guard: a claimed item can only be written off once. Reject the
            // whole submission (rollback + 422) if any coverage flagged for
            // write-off has already been written off on a prior reserve for
            // this claim. Matched on coverage_id when known, with a
            // coverage_name fallback for unmatched (coverage_id = 0) rows.
            if ($writeOffEligible) {
                $requested = array_values(array_filter(
                    $validated['coverages'],
                    fn($c) => !empty($c['write_off'])
                ));
                if ($requested) {
                    $existing = DB::table('claim_reserves_coverages')
                        ->where('claim_id', $claimId)
                        ->where('write_off', 1)
                        ->get(['coverage_id', 'coverage_name']);
                    $existingIds = $existing->where('coverage_id', '>', 0)
                        ->pluck('coverage_id')->map(fn($v) => (int) $v)->unique()->all();
                    $existingNames = $existing
                        ->pluck('coverage_name')->map(fn($v) => mb_strtolower(trim((string) $v)))
                        ->filter()->unique()->all();
                    $dupes = [];
                    foreach ($requested as $c) {
                        $cid  = (int) $c['coverage_id'];
                        $name = mb_strtolower(trim((string) $c['coverage_name']));
                        $isDupe = ($cid > 0 && in_array($cid, $existingIds, true))
                            || ($name !== '' && in_array($name, $existingNames, true));
                        if ($isDupe) $dupes[] = $c['coverage_name'];
                    }
                    if ($dupes) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'The following item(s) have already been written off and cannot be written off again: '
                                . implode(', ', array_unique($dupes)) . '.',
                        ], 422);
                    }
                }
            }

            foreach ($validated['coverages'] as $cov) {
                $amt = (float) $cov['amount'];
                // Mirror graphiteBWV8 ClaimsController::storeReserve (line 220+):
                // each transaction_type writes to a different amount column,
                // and a handful of sub-types flip the sign for type 42/43.
                $row = [
                    'reserve_id'    => $reserveId,
                    'claim_id'      => $claimId,
                    'product_id'    => $productId,
                    'coverage_id'   => $cov['coverage_id'],
                    'coverage_name' => $cov['coverage_name'],
                    'reserve_amt'         => 0,
                    'payment_amt'         => 0,
                    'subrogation_reserve' => 0,
                    'subrogation_payment' => 0,
                    'salvage_reserve'     => 0,
                    'salvage_payment'     => 0,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];

                // Sub-types 53/54/55 ("Salvage", "Subrogation", "Excess/Deductible")
                // act as overrides on the headline 42/43 columns: when paired
                // with type 43 they bank back to the reserve column; when
                // paired with type 42 they bleed out via the payment column.
                $flipsLossSign = $subTypeId !== null && in_array($subTypeId, [53, 54, 55], true);

                if ($txType === 43 || $txType === 44) {
                    // Loss Payment / Reset Reserves
                    if ($txType === 43 && $flipsLossSign) {
                        $row['reserve_amt'] = $amt;  $balance += $amt;
                    } else {
                        $row['payment_amt'] = $amt;  $balance -= $amt;
                    }
                } elseif ($txType === 89) {
                    // TP Liability Reserve
                    $row['subrogation_reserve'] = $amt;  $balance += $amt;
                } elseif ($txType === 90) {
                    // TP Liability Payment
                    $row['subrogation_payment'] = $amt;  $balance -= $amt;
                } elseif ($txType === 91) {
                    // Salvage Reserve
                    $row['salvage_reserve'] = $amt;  $balance += $amt;
                } elseif ($txType === 92) {
                    // Salvage Payment
                    $row['salvage_payment'] = $amt;  $balance -= $amt;
                } else {
                    // Default: 42 (Loss Reserve), 86 (Initial Reserves), or anything else.
                    if ($txType === 42 && $flipsLossSign) {
                        $row['payment_amt'] = $amt;  $balance -= $amt;
                    } else {
                        $row['reserve_amt'] = $amt;  $balance += $amt;
                    }
                }

                $row['balance'] = $balance;

                // Per-coverage write-off flag (gated on type 42 + Claim Expense).
                $covWriteOff = $writeOffEligible && !empty($cov['write_off']);
                $row['write_off']    = $covWriteOff ? 1 : 0;
                $row['write_off_at'] = $covWriteOff ? $now : null;
                $row['write_off_by'] = $covWriteOff ? $writeOffUserId : null;
                if ($covWriteOff) {
                    $writtenOffCoverages[] = $cov['coverage_name'];
                }

                DB::table('claim_reserves_coverages')->insert($row);
            }

            $this->logActivity($claimId, $table, "Reserve entry created (ID: {$reserveId}, type: {$txType})");

            DB::commit();

            // Write-off notifications fire only after the reserve is safely
            // committed. Each flagged coverage notifies Finance + Underwriting
            // (in-app + email) for the specific claimed item on this policy.
            // Wrapped so a notification failure never fails the saved reserve.
            if ($writtenOffCoverages) {
                try {
                    $policyNumber = $claim->policy_id
                        ? DB::table('policies')->where('id', $claim->policy_id)->value('policyNumber')
                        : null;
                    $claimNumber = $claim->claim_number ?? null;
                    $writeOffEmails = array_values(array_filter($validated['write_off_emails'] ?? []));
                    // Subject mirrors the claim-review format:
                    // "WRITE OFF - {Policy} {Customer} {Item} {Type} CLAIM#: {Number}".
                    $writeOffSubject  = $this->buildClaimSubject($claim, 'WRITE OFF');
                    $writeOffCustomer = $this->buildCustomerName($claim);
                    foreach ($writtenOffCoverages as $coverageName) {
                        \AlphaDirect\Services\NotificationDispatcher::claimWriteOff(
                            $claimId,
                            $claimNumber,
                            $policyNumber,
                            $coverageName,
                            $writeOffEmails,
                            $writeOffSubject,
                            $claim->claim_type ?? null,
                            $writeOffCustomer
                        );
                        $this->logActivity($claimId, $table, "Coverage \"{$coverageName}\" declared a write-off (reserve ID: {$reserveId}).");
                    }
                } catch (\Throwable $e) {
                    Log::error('ClaimsV2 write-off notification failed', ['claim_id' => $claimId, 'error' => $e->getMessage()]);
                }
            }

            return response()->json([
                'data'    => ['reserveId' => $reserveId, 'balance' => $balance],
                'message' => 'Reserve created successfully.',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ClaimsV2 storeReserve failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to create reserve.',
                'error'   => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    // ─── 8. voidPayment ──────────────────────────────────────────────────────

    public function voidPayment(Request $request, int $claimId, int $reserveId): JsonResponse
    {
        $validated = $request->validate([
            'reason_for_void' => 'required|string|max:1000',
        ]);

        // $reserveId here is actually the claim_reserves_coverages.id being voided
        $coverage = DB::table('claim_reserves_coverages')->where('id', $reserveId)->first();
        if (!$coverage || (int) $coverage->claim_id !== $claimId) {
            return response()->json(['message' => 'Reserve coverage entry not found.'], 404);
        }

        if (!empty($coverage->is_payment_voided) && (int) $coverage->is_payment_voided === 1) {
            return response()->json(['message' => 'Payment is already voided.'], 422);
        }

        try {
            DB::beginTransaction();

            $now = Carbon::now();

            // Mark original as voided
            DB::table('claim_reserves_coverages')
                ->where('id', $reserveId)
                ->update([
                    'is_payment_voided' => 1,
                    'updated_at'        => $now,
                ]);

            // Insert reversal entry (is_payment_voided = 2 means "voided entry")
            DB::table('claim_reserves_coverages')->insert([
                'claim_id'          => $coverage->claim_id,
                'reserve_id'        => $coverage->reserve_id,
                'product_id'        => $coverage->product_id ?? null,
                'coverage_id'       => $coverage->coverage_id,
                'coverage_name'     => $coverage->coverage_name,
                'payment_amt'       => 0,
                'reserve_amt'       => ($coverage->reserve_amt ?? 0) + ($coverage->payment_amt ?? 0),
                'balance'           => ($coverage->balance ?? 0) + ($coverage->payment_amt ?? 0),
                'is_payment_voided' => 2,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);

            // Log in void payment logs
            DB::table('claim_void_payment_logs')->insert([
                'claim_reserves_coverages_id' => $reserveId,
                'claim_id'                    => $claimId,
                'payment_void_by'             => auth()->id(),
                'payment_void_date'           => $now->format('Y-m-d'),
                'amount'                      => $coverage->payment_amt ?? 0,
                'reason_for_void'             => $validated['reason_for_void'],
                'created_at'                  => $now,
                'updated_at'                  => $now,
            ]);

            $this->logActivity($claimId, $this->claimsTable(), "Payment voided (coverage entry ID: {$reserveId})");

            DB::commit();

            return response()->json([
                'message' => 'Payment voided successfully.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ClaimsV2 voidPayment failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to void payment.',
                'error'   => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    // ─── 8b. voidPaymentInfo ─────────────────────────────────────────────────

    /**
     * GET /claims-v2/{id}/reserves/{crcId}/void-info
     *
     * Mirrors graphiteBWV8 ClaimsController::getVoidPaymentInfo (line 1804).
     * Returns the most-recent void log row for a given claim_reserves_coverages
     * id, plus the user who performed the void. The FE Void Info modal calls
     * this to populate "Payment Voided By" / "Reason For Void" / "Payment Date".
     */
    public function voidPaymentInfo(int $claimId, int $crcId): JsonResponse
    {
        $log = DB::table('claim_void_payment_logs')
            ->where('claim_reserves_coverages_id', $crcId)
            ->where('claim_id', $claimId)
            ->orderBy('id', 'desc')
            ->first();

        if (!$log) {
            return response()->json(['message' => 'Void log not found.'], 404);
        }

        $userName = null;
        if ($log->payment_void_by) {
            $u = DB::table('users')->where('id', $log->payment_void_by)->first(['firstName','lastName']);
            if ($u) $userName = trim($u->firstName . ' ' . $u->lastName);
        }

        $claimNumber = DB::table($this->claimsTable())->where('id', $claimId)->value('claim_number');

        return response()->json([
            'data' => [
                'id'                       => $log->id,
                'claimReservesCoveragesId' => $log->claim_reserves_coverages_id,
                'claimId'                  => $log->claim_id,
                'paymentVoidById'          => $log->payment_void_by,
                'paymentVoidByName'        => $userName,
                'paymentVoidDate'          => $log->payment_void_date,
                'amount'                   => (float) ($log->amount ?? 0),
                'reasonForVoid'            => $log->reason_for_void,
                'claimNumber'              => $claimNumber,
            ],
        ]);
    }

    // ─── 8d. reserveCoverages ────────────────────────────────────────────────

    /** Lower bound of the synthetic coverage_id band used for JSON-held specialist items. */
    public const SPECIALIST_ITEM_ID_BASE = 1_000_000_000;

    /**
     * Stable synthetic coverage_id for a Plant All Risk insured item.
     *
     * PAR items live in par_coverages.insured_items JSON with no id of their
     * own, so the id that round-trips through claim_reserves_coverages is
     * derived from the item description: crc32 of the whitespace-collapsed,
     * lower-cased text, mapped into [1,000,000,000 … 1,999,999,999]. Fits
     * int(11); clear of real coverage ids and the 9,0xx,xxx union band.
     */
    public static function parItemCoverageId(string $description): int
    {
        $norm = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $description)));
        // sprintf('%u') keeps crc32 unsigned on every platform.
        $crc  = (int) sprintf('%u', crc32($norm));
        return self::SPECIALIST_ITEM_ID_BASE + ($crc % 1_000_000_000);
    }

    /**
     * GET /claims-v2/{id}/reserves/coverages
     *
     * Returns the full coverage list for a claim's policy, pre-aggregated with
     * any reserve / payment activity already booked under this claim, ready for
     * the Reserves tab to render as a pre-loaded allocation table.
     *
     * Mirrors graphiteBWV8 ClaimsController::show (lines 491-739): for DOM/COM
     * products (7, 8, 16-19) the response is grouped by risk_address_name and
     * includes both base coverages and policy_extention_detail rows; for every
     * other product the result is a flat list of policy_coverages joined to
     * tb_cvgpccoverages for the display name.
     *
     * The coverage list itself is scoped by claims.policy_action_id when set,
     * mirroring V8's `.when($claimActionId)` filter; null falls back to the
     * full policy coverage list.
     */
    public function reserveCoverages(int $claimId): JsonResponse
    {
        $table = $this->claimsTable();
        $claim = DB::table($table)->where('id', $claimId)->first();
        if (!$claim) {
            return response()->json(['message' => 'Claim not found.'], 404);
        }

        $policy = DB::table('policies')->where('id', $claim->policy_id)->first(['id', 'product_id']);
        if (!$policy) {
            return response()->json(['data' => ['isGrouped' => false, 'rows' => [], 'productId' => null, 'policyActionId' => null]]);
        }

        $cols           = \Schema::getColumnListing($table);
        $policyActionId = in_array('policy_action_id', $cols, true) ? ($claim->policy_action_id ?? null) : null;
        $productId      = (int) ($policy->product_id ?? 0);
        // graphiteBWV8 routes products 7/8/16-19 through the risk-address
        // grouped layout; everything else gets a flat list. The list comes
        // straight from V2's existing policyCoverages helper which already
        // had this product set hardcoded.
        $domcomProductIds = [7, 8, 16,17,18,20,22,23,24];
        $isDomCom         = in_array($productId, $domcomProductIds, true);
        // MIS products (Motor / personal-line schedules without policy_extention
        // splits): graphiteBWV8 ClaimsController::show (lines 457-459 and
        // 1242-1245) reads coverages straight off the `product_coverage`
        // template, distinct by coverage_id, and attaches per-coverage
        // SUM(reserve_amt)/SUM(payment_amt) from claim_reserves_coverages.
        // No risk_address grouping, no extension/specified rows, no limit.
        $misProductIds = [1, 2, 3, 4, 5, 6, 9, 10];
        $isMis         = in_array($productId, $misProductIds, true);

        // Union legal claims are their OWN claim type with their OWN coverage —
        // NOT product 4's shared "Legal Expenses". When this claim was filed
        // against a union member, we return that union's dedicated coverage
        // ("BONU Legal Expenses" / "BOWASEWU Legal Expenses") below and skip the
        // product-coverage template entirely. Genuine retail Legal policies (no
        // union_legal_claims row) still get the product-4 coverage as before.
        $unionForm = \Schema::hasTable('union_legal_claims')
            ? DB::table('union_legal_claims')
                ->where('claim_id', $claimId)
                ->whereNull('deleted_at')
                ->first(['union_id', 'form_code'])
            : null;

        // Claimed items already written off on this claim. A claimed item can
        // only be written off once, so the FE disables the Write Off checkbox
        // for these rows (and storeReserve rejects any repeat attempt server
        // side). Matched on coverage_id when known, with a coverage_name
        // fallback for unmatched (coverage_id = 0) rows.
        $writtenOffRows = DB::table('claim_reserves_coverages')
            ->where('claim_id', $claimId)
            ->where('write_off', 1)
            ->get(['coverage_id', 'coverage_name']);
        $writtenOffIds = $writtenOffRows->where('coverage_id', '>', 0)
            ->pluck('coverage_id')->map(fn($v) => (int) $v)->unique()->all();
        $writtenOffNames = $writtenOffRows
            ->pluck('coverage_name')->map(fn($v) => mb_strtolower(trim((string) $v)))
            ->filter()->unique()->all();
        $isWrittenOff = function (int $covId, ?string $name) use ($writtenOffIds, $writtenOffNames): bool {
            if ($covId > 0 && in_array($covId, $writtenOffIds, true)) return true;
            $n = mb_strtolower(trim((string) $name));
            return $n !== '' && in_array($n, $writtenOffNames, true);
        };

        // ── Union legal claim: its own coverage (not product 4's) ──────────────
        // BONU / BOWASEWU claims are their own claim type with their own
        // coverage. Return a single union-scoped coverage — "BONU Legal
        // Expenses" / "BOWASEWU Legal Expenses" — with a stable, collision-free
        // synthetic coverage_id (9,000,000 + union_id; real coverage ids top out
        // ~69k and stored crc ids ~450k). Reserves/payments book against this
        // union coverage; product 4's template is bypassed entirely.
        if ($unionForm) {
            $unionCovId = 9000000 + (int) $unionForm->union_id;
            $unionCovNm = strtoupper((string) $unionForm->form_code) . ' Legal Expenses';

            $agg = DB::table('claim_reserves_coverages')
                ->where('claim_id', $claimId)
                ->where('coverage_id', $unionCovId)
                ->where(function ($q) { $q->whereNull('is_payment_voided')->orWhere('is_payment_voided', 0); })
                ->selectRaw('SUM(COALESCE(reserve_amt,0)) as reserveAmt, SUM(COALESCE(payment_amt,0)) as paymentAmt')
                ->first();
            $reserveAmt = (float) ($agg->reserveAmt ?? 0);
            $paymentAmt = (float) ($agg->paymentAmt ?? 0);

            $rows = [[
                'policyCoverageId' => $unionCovId,
                'coverageId'       => $unionCovId,
                'coverageName'     => $unionCovNm,
                'coverageCode'     => null,
                'coverageLimit'    => null,
                'riskAddressId'    => null,
                'riskAddressName'  => null,
                'source'           => 'union',
                'reserveAmt'       => $reserveAmt,
                'paymentAmt'       => $paymentAmt,
                'balance'          => $reserveAmt - $paymentAmt,
                'writtenOff'       => $isWrittenOff($unionCovId, $unionCovNm),
            ]];

            // Surface any reserves already booked under a different coverage_id
            // for this claim so outstanding money is never hidden.
            $orphans = DB::table('claim_reserves_coverages')
                ->where('claim_id', $claimId)
                ->where('coverage_id', '!=', $unionCovId)
                ->where(function ($q) { $q->whereNull('is_payment_voided')->orWhere('is_payment_voided', 0); })
                ->select('coverage_id', 'coverage_name',
                    DB::raw('SUM(COALESCE(reserve_amt,0)) as reserveAmt'),
                    DB::raw('SUM(COALESCE(payment_amt,0)) as paymentAmt'))
                ->groupBy('coverage_id', 'coverage_name')
                ->get();
            foreach ($orphans as $o) {
                $rA = (float) $o->reserveAmt; $pA = (float) $o->paymentAmt;
                if ($rA - $pA <= 0.0) continue;
                $oid = (int) $o->coverage_id;
                $rows[] = [
                    'policyCoverageId' => $oid,
                    'coverageId'       => $oid,
                    'coverageName'     => ($o->coverage_name !== null && $o->coverage_name !== '') ? $o->coverage_name : 'Prior reserve',
                    'coverageCode'     => null,
                    'coverageLimit'    => null,
                    'riskAddressId'    => null,
                    'riskAddressName'  => null,
                    'source'           => 'prior-term',
                    'reserveAmt'       => $rA,
                    'paymentAmt'       => $pA,
                    'balance'          => $rA - $pA,
                    'writtenOff'       => $isWrittenOff($oid, $o->coverage_name),
                    'priorTerm'        => true,
                ];
            }

            return response()->json([
                'data' => [
                    'isGrouped'      => false,
                    'productId'      => $productId,
                    'policyActionId' => $policyActionId,
                    'rows'           => $rows,
                    'groups'         => null,
                ],
            ]);
        }

        if ($isMis) {
            // V8 parity: distinct (name, coverage_id) pairs from product_coverage.
            // groupBy via subquery to stay safe under ONLY_FULL_GROUP_BY.
            $misCoverages = DB::table('product_coverage')
                ->where('product_id', $productId)
                ->select('name', 'coverage_id')
                ->get()
                ->unique('coverage_id')
                ->values();

            // Per-coverage aggregation. Voided rows (is_payment_voided in {1,2})
            // excluded so a voided payment doesn't double-deduct from balance.
            $aggsRaw = DB::table('claim_reserves_coverages')
                ->where('claim_id', $claimId)
                ->where(function ($q) {
                    $q->whereNull('is_payment_voided')->orWhere('is_payment_voided', 0);
                })
                ->select(
                    'coverage_id',
                    DB::raw('SUM(COALESCE(reserve_amt,0)) as reserveAmt'),
                    DB::raw('SUM(COALESCE(payment_amt,0)) as paymentAmt')
                )
                ->groupBy('coverage_id')
                ->get()
                ->keyBy('coverage_id');

            $rows = $misCoverages->map(function ($c) use ($aggsRaw, $isWrittenOff) {
                $covId      = (int) $c->coverage_id;
                $agg        = $aggsRaw->get($covId);
                $reserveAmt = (float) ($agg->reserveAmt ?? 0);
                $paymentAmt = (float) ($agg->paymentAmt ?? 0);
                // policyCoverageId mirrors coverage_id so rowKeyOf() on the FE
                // produces a stable, unique key without a real pc.id.
                return [
                    'policyCoverageId' => $covId,
                    'coverageId'       => $covId,
                    'coverageName'     => $c->name ?? 'N/A',
                    'coverageCode'     => null,
                    'coverageLimit'    => null,
                    'riskAddressId'    => null,
                    'riskAddressName'  => null,
                    'source'           => 'base',
                    'reserveAmt'       => $reserveAmt,
                    'paymentAmt'       => $paymentAmt,
                    'balance'          => $reserveAmt - $paymentAmt,
                    'writtenOff'       => $isWrittenOff($covId, $c->name ?? null),
                ];
            })->values()->all();

            // Prior-term (orphaned) reserves — see the non-MIS branch below for
            // the full rationale. For MIS the grid comes from the product-level
            // `product_coverage` template (coverage_id is stable across terms),
            // so orphans are rare here, but still surface any outstanding reserve
            // whose coverage_id is not in the template so the money stays payable.
            $usedMisIds = $misCoverages->pluck('coverage_id')->map(fn($v) => (int) $v)->all();
            $orphanMis = DB::table('claim_reserves_coverages')
                ->where('claim_id', $claimId)
                ->where(function ($q) {
                    $q->whereNull('is_payment_voided')->orWhere('is_payment_voided', 0);
                })
                ->whereNotIn('coverage_id', $usedMisIds ?: [0])
                ->select(
                    'coverage_id',
                    'coverage_name',
                    DB::raw('SUM(COALESCE(reserve_amt,0)) as reserveAmt'),
                    DB::raw('SUM(COALESCE(payment_amt,0)) as paymentAmt')
                )
                ->groupBy('coverage_id', 'coverage_name')
                ->get();
            foreach ($orphanMis as $agg) {
                $reserveAmt = (float) $agg->reserveAmt;
                $paymentAmt = (float) $agg->paymentAmt;
                if ($reserveAmt - $paymentAmt <= 0.0) continue;
                $covId = (int) $agg->coverage_id;
                $rows[] = [
                    'policyCoverageId' => $covId,
                    'coverageId'       => $covId,
                    'coverageName'     => ($agg->coverage_name !== null && $agg->coverage_name !== '') ? $agg->coverage_name : 'Prior-term reserve',
                    'coverageCode'     => null,
                    'coverageLimit'    => null,
                    'riskAddressId'    => null,
                    'riskAddressName'  => null,
                    'source'           => 'prior-term',
                    'reserveAmt'       => $reserveAmt,
                    'paymentAmt'       => $paymentAmt,
                    'balance'          => $reserveAmt - $paymentAmt,
                    'writtenOff'       => $isWrittenOff($covId, $agg->coverage_name),
                    'priorTerm'        => true,
                ];
            }

            return response()->json([
                'data' => [
                    'isGrouped'      => false,
                    'productId'      => $productId,
                    'policyActionId' => $policyActionId,
                    'rows'           => $rows,
                    'groups'         => null,
                ],
            ]);
        }

        // ── Base coverages from policy_coverage_detail ───────────────────────
        // Mirrors graphiteBWV8 ClaimsController::show (lines 525-568): join
        // policy_coverages → policy_coverage_detail → tb_cvgpccoverages via
        // pcd.coverage_id (so s_CoverageName resolves to the line item like
        // "Estimated Annual Carry", not the parent product). Motor coverages
        // (pc.coverage_id 22 / 27) additionally LEFT JOIN motor for the
        // vehicle name + plate. The display name is built server-side via
        // CASE so the FE renders it as-is.
        $baseCoverages = DB::table('policy_coverages as pc')
            ->leftJoin('policy_coverage_detail as pcd', 'pc.id', '=', 'pcd.policy_coverage_id')
            ->leftJoin('tb_cvgpccoverages as cvg', 'pcd.coverage_id', '=', 'cvg.id')
            ->leftJoin('motor', function ($join) {
                $join->on('motor.policy_coverage_id', '=', 'pc.id')
                    ->whereIn('pc.coverage_id', [22, 27]);
            })
            ->leftJoin('risk_address as ra', 'pc.risk_address_id', '=', 'ra.id')
            ->where('pc.policy_id', $policy->id)
            ->whereNull('pc.deleted_at')
            ->when($policyActionId, fn($q, $a) => $q->where('pc.action_id', $a))
            ->select(
                'pc.id as policy_coverage_id',
                // Motor coverages key off motor.id; everything else off pcd.id.
                // Mirrors graphiteBWV8 ClaimsController.php:541. This is the id
                // that gets stored in claim_reserves_coverages.coverage_id and
                // round-trips back to drive the per-coverage SUM aggregation.
                DB::raw("CASE WHEN pc.coverage_id IN (22, 27) THEN motor.id ELSE pcd.id END as coverage_id"),
                DB::raw("CASE
                    WHEN pc.coverage_id = 22 THEN CONCAT('Commercial Motor - ', COALESCE(motor.vehicle_name, ''), ' (', COALESCE(motor.registration_no, ''), ')')
                    WHEN pc.coverage_id = 27 THEN CONCAT('Personal Motor - ', COALESCE(motor.vehicle_name, ''), ' (', COALESCE(motor.registration_no, ''), ')')
                    WHEN cvg.s_ParentCoverageCode = 'STATEDBENEFITS' THEN CONCAT(cvg.s_ParentCoverageCode, ' - ', COALESCE(pcd.coverage_value_string, cvg.s_CoverageName))
                    ELSE CONCAT(COALESCE(cvg.s_ParentCoverageCode, ''), ' - ', COALESCE(cvg.s_CoverageName, ''))
                END as coverage_name"),
                'cvg.s_CoverageCode as coverage_code',
                DB::raw("CASE
                    WHEN pc.coverage_id IN (22, 27) THEN motor.coverage_value_main
                    WHEN cvg.s_ParentCoverageCode = 'THEFT' AND (pcd.coverage_value IS NULL OR pcd.coverage_value = 0) THEN pcd.ratefactor_value
                    WHEN cvg.s_ParentCoverageCode = 'STATEDBENEFITS' THEN COALESCE(CAST(REPLACE(pcd.ratefactor_AnnualWages, ',', '') AS DECIMAL(15,2)), 0)
                    ELSE pcd.coverage_value
                END as coverage_limit"),
                'pc.risk_address_id',
                'ra.address_name as risk_address_name',
                DB::raw("'base' as source")
            )
            ->get()
            // Drop rows that lack a resolvable name. These are usually legacy
            // detail rows that exist for rate calculation only, or a coverage
            // with NO policy_coverage_detail at all (specialist covers such as
            // Plant All Risk keep their schedule elsewhere) — the LEFT JOIN then
            // yields one null row whose CASE name is ' - '. trim() strips the
            // surrounding spaces, so compare against '-' (the old ' - ' test
            // could never match and let a blank "-" row with coverage_id 0
            // through to the grid).
            ->filter(function ($c) {
                $n = trim((string) ($c->coverage_name ?? ''));
                return $n !== '' && $n !== '-';
            });

        // ── Extensions (DOM/COM only) ────────────────────────────────────────
        // policy_extention_detail FKs back to policy_coverages.id. graphiteBWV8
        // composes the display name as `{ParentCoverageCode} Extension - {ScreenName}`
        // (line 604). We mirror that here so the row reads e.g.
        // "GOODSINTRANSIT Extension - Debris Removal".
        $extensionCoverages = collect();
        if ($isDomCom && \Schema::hasTable('policy_extention_detail')) {
            $extensionCoverages = DB::table('policy_extention_detail as ped')
                ->join('policy_coverages as pc', 'ped.policy_coverage_id', '=', 'pc.id')
                ->leftJoin('risk_address as ra', 'pc.risk_address_id', '=', 'ra.id')
                ->where('pc.policy_id', $policy->id)
                ->whereNull('pc.deleted_at')
                ->whereNull('ped.deleted_at')
                ->when($policyActionId, fn($q, $a) => $q->where('pc.action_id', $a))
                ->select(
                    'ped.id as policy_coverage_id',
                    DB::raw('ped.s_SubCoverageID as coverage_id'),
                    DB::raw("CONCAT(COALESCE(ped.s_ParentCoverageCode, ''), ' Extension - ', COALESCE(ped.custom_name, ped.s_ScreenName, ped.s_CoverageName))  as coverage_name"),
                    'ped.s_CoverageCode as coverage_code',
                    'ped.extention_sum_insured as coverage_limit',
                    'pc.risk_address_id',
                    'ra.address_name as risk_address_name',
                    DB::raw("'extension' as source")
                )
                ->get();
        }

        // ── Motor extensions (hardCoded_extension_details) ───────────────────
        // Motor comprehensive / motor-trader extensions (Third party liability,
        // Window glass, Wreckage removal, Locks & keys, …) live in
        // hardCoded_extension_details keyed by coverage_id (15,16,22,27); their
        // VALUES live in the motor / motor_traders / motor_traders_internal
        // tables keyed by hed.key. graphiteBWV8 Admin\ClaimsController.php
        // (:575-683) is the canonical query — the React reserves grid was
        // missing it entirely, so motor extensions (e.g. Third party liability)
        // never showed. Ported here. Trader tables are optional per-env, so we
        // only join / reference the ones that exist.
        $motorExtensionCoverages = collect();
        if (\Schema::hasTable('hardCoded_extension_details') && \Schema::hasTable('motor')) {
            $hasTraders  = \Schema::hasTable('motor_traders');
            $hasInternal = \Schema::hasTable('motor_traders_internal');

            $motorCoverageIds = [22, 27];
            if ($hasTraders)  $motorCoverageIds[] = 15;
            if ($hasInternal) $motorCoverageIds[] = 16;

            // coverage_limit CASE — only reference trader tables that are joined.
            $limitBranches = [];
            if ($hasTraders) {
                $limitBranches[] = "WHEN hed.coverage_id = 15 THEN CASE
                        WHEN hed.key = 'vehicle_lent_hire_coverage_value' THEN motor_traders.vehicle_lent_hire_coverage_value
                        WHEN hed.key = 'social_domestic_pleasure_coverage_value' THEN motor_traders.social_domestic_pleasure_coverage_value
                        WHEN hed.key = 'unauthoried_use_coverage_value' THEN motor_traders.unauthoried_use_coverage_value
                        WHEN hed.key = 'windscreen_coverage_value' THEN motor_traders.windscreen_coverage_value
                        WHEN hed.key = 'contigent_liability_coverage_value' THEN motor_traders.contigent_liability_coverage_value
                        WHEN hed.key = 'wreckage_removal_coverage_value' THEN motor_traders.wreckage_removal_coverage_value
                        WHEN hed.key = 'loss_of_key_coverage_value' THEN motor_traders.loss_of_key_coverage_value
                        WHEN hed.key = 'Loss_of_use_of_customer_coverage_value' THEN motor_traders.Loss_of_use_of_customer_coverage_value
                        WHEN hed.key = 'motor_cycle_motor_tricycle_coverage_value' THEN motor_traders.motor_cycle_motor_tricycle_coverage_value
                        WHEN hed.key = 'passanger_liability_respect_of_motor_coverage_value' THEN motor_traders.passanger_liability_respect_of_motor_coverage_value
                        WHEN hed.key = 'special_type_vehicle_coverage_value' THEN motor_traders.special_type_vehicle_coverage_value
                        WHEN hed.key = 'loss_or_damage_coverage_value' THEN motor_traders.loss_or_damage_coverage_value
                        WHEN hed.key = 'third_party_liability_coverage_value' THEN motor_traders.third_party_liability_coverage_value
                        WHEN hed.key = 'medical_benefits_coverage_value' THEN motor_traders.medical_benefits_coverage_value
                    END";
            }
            if ($hasInternal) {
                $limitBranches[] = "WHEN hed.coverage_id = 16 THEN CASE
                        WHEN hed.key = 'vehicle_lent_hire_coverage_value' THEN motor_traders_internal.vehicle_lent_hire_coverage_value
                        WHEN hed.key = 'social_domestic_pleasure_coverage_value' THEN motor_traders_internal.social_domestic_pleasure_coverage_value
                        WHEN hed.key = 'unauthoried_use_coverage_value' THEN motor_traders_internal.unauthoried_use_coverage_value
                        WHEN hed.key = 'windscreen_coverage_value' THEN motor_traders_internal.windscreen_coverage_value
                        WHEN hed.key = 'contigent_liability_coverage_value' THEN motor_traders_internal.contigent_liability_coverage_value
                        WHEN hed.key = 'wreckage_removal_coverage_value' THEN motor_traders_internal.wreckage_removal_coverage_value
                        WHEN hed.key = 'loss_of_key_coverage_value' THEN motor_traders_internal.loss_of_key_coverage_value
                        WHEN hed.key = 'Loss_of_use_of_customer_coverage_value' THEN motor_traders_internal.Loss_of_use_of_customer_coverage_value
                        WHEN hed.key = 'motor_cycle_motor_tricycle_coverage_value' THEN motor_traders_internal.motor_cycle_motor_tricycle_coverage_value
                        WHEN hed.key = 'passanger_liability_respect_of_motor_coverage_value' THEN motor_traders_internal.passanger_liability_respect_of_motor_coverage_value
                        WHEN hed.key = 'special_type_vehicle_coverage_value' THEN motor_traders_internal.special_type_vehicle_coverage_value
                        WHEN hed.key = 'loss_or_damage_coverage_value' THEN motor_traders_internal.loss_or_damage_coverage_value
                        WHEN hed.key = 'third_party_liability_coverage_value' THEN motor_traders_internal.third_party_liability_coverage_value
                        WHEN hed.key = 'medical_benefits_coverage_value' THEN motor_traders_internal.medical_benefits_coverage_value
                    END";
            }
            $limitBranches[] = "WHEN hed.coverage_id = 22 THEN CASE
                        WHEN hed.key = 'passenger_liability' THEN motor.passenger_liability
                        WHEN hed.key = 'unorthorised_passanger_liability' THEN motor.unorthorised_passanger_liability
                        WHEN hed.key = 'parking_facilities' THEN motor.parking_facilities
                        WHEN hed.key = 'com_windscreen' THEN motor.com_windscreen
                        WHEN hed.key = 'riot_strike' THEN motor.riot_strike
                        WHEN hed.key = 'locks_keys' THEN motor.locks_keys
                        WHEN hed.key = 'credit_shortfall' THEN motor.credit_shortfall
                        WHEN hed.key = 'third_party_liability' THEN motor.third_party_liability
                        WHEN hed.key = 'wreckage_removal' THEN motor.wreckage_removal
                        WHEN hed.key = 'window_glass' THEN motor.window_glass
                    END";
            $limitBranches[] = "WHEN hed.coverage_id = 27 THEN CASE
                        WHEN hed.key = 'wreckage_removal' THEN motor.wreckage_removal
                        WHEN hed.key = 'window_glass' THEN motor.window_glass
                        WHEN hed.key = 'locks_keys' THEN motor.locks_keys
                        WHEN hed.key = 'parts_accessories' THEN motor.parts_accessories
                        WHEN hed.key = 'audio_accessories' THEN motor.audio_accessories
                        WHEN hed.key = 'riot_strike' THEN motor.riot_strike
                        WHEN hed.key = 'car_hire_theft' THEN motor.car_hire_theft
                        WHEN hed.key = 'credit_shortfall' THEN motor.credit_shortfall
                        WHEN hed.key = 'insured_driver' THEN motor.insured_driver
                        WHEN hed.key = 'insured_family' THEN motor.insured_family
                        WHEN hed.key = 'medical_expenses' THEN motor.medical_expenses
                        WHEN hed.key = 'passenger_liability' THEN motor.passenger_liability
                        WHEN hed.key = 'third_party_liability' THEN motor.third_party_liability
                        WHEN hed.key = 'specified_accessories' THEN motor.specified_accessories
                    END";
            $limitCaseSql = 'CASE ' . implode("\n", $limitBranches) . ' ELSE NULL END';

            $nameCaseSql = "CASE
                    WHEN hed.coverage_id IN (15,16) AND hed.id IN (57,58,59,60,61,62) THEN hed.name
                    WHEN hed.coverage_id = 15 THEN CONCAT('Motor Traders External Extension - ', hed.name, ' - ', COALESCE(motor.vehicle_name, ''), ' (', COALESCE(motor.registration_no, ''), ')')
                    WHEN hed.coverage_id = 16 THEN CONCAT('Motor Traders Internal Extension - ', hed.name, ' - ', COALESCE(motor.vehicle_name, ''), ' (', COALESCE(motor.registration_no, ''), ')')
                    WHEN hed.coverage_id = 22 THEN CONCAT('Commercial Motor Extension - ', hed.name, ' - ', COALESCE(motor.vehicle_name, ''), ' (', COALESCE(motor.registration_no, ''), ')')
                    WHEN hed.coverage_id = 27 THEN CONCAT('Personal Motor Extension - ', hed.name, ' - ', COALESCE(motor.vehicle_name, ''), ' (', COALESCE(motor.registration_no, ''), ')')
                    ELSE hed.name
                END";

            $mQ = DB::table('policy_coverages as pc')
                ->leftJoin('risk_address as ra', 'ra.id', '=', 'pc.risk_address_id')
                ->leftJoin('hardCoded_extension_details as hed', 'hed.coverage_id', '=', 'pc.coverage_id')
                ->leftJoin('motor', function ($join) {
                    $join->on('motor.policy_coverage_id', '=', 'pc.id')->whereIn('pc.coverage_id', [22, 27]);
                });
            if ($hasTraders) {
                $mQ->leftJoin('motor_traders', function ($join) {
                    $join->on('motor_traders.policy_coverage_id', '=', 'pc.id')->where('pc.coverage_id', 15);
                });
            }
            if ($hasInternal) {
                $mQ->leftJoin('motor_traders_internal', function ($join) {
                    $join->on('motor_traders_internal.policy_coverage_id', '=', 'pc.id')->where('pc.coverage_id', 16);
                });
            }

            $motorExtensionCoverages = $mQ
                ->where('pc.policy_id', $policy->id)
                ->whereIn('pc.coverage_id', $motorCoverageIds)
                ->whereNull('pc.deleted_at')
                ->when($policyActionId, fn($q, $a) => $q->where('pc.action_id', $a))
                ->select(
                    'pc.id as policy_coverage_id',
                    // hed.id — same id space graphiteBWV8 stores in
                    // claim_reserves_coverages.coverage_id for motor extensions.
                    DB::raw('hed.id as coverage_id'),
                    DB::raw("$nameCaseSql as coverage_name"),
                    DB::raw('CAST(NULL AS CHAR) as coverage_code'),
                    DB::raw("$limitCaseSql as coverage_limit"),
                    'pc.risk_address_id',
                    'ra.address_name as risk_address_name',
                    DB::raw("'extension' as source")
                )
                ->get()
                // The hed left-join yields a single NULL row when a motor
                // coverage has no hardcoded extensions defined; drop those.
                ->filter(fn($c) => $c->coverage_id !== null)
                ->values();
        }

        
        // ── Specified items (DOM/COM only) ───────────────────────────────────
        $specifiedItems = collect();
        if ($isDomCom && \Schema::hasTable('policy_specified_items')) {
            $specifiedItems = DB::table('policy_specified_items as psi')
                ->join('policy_coverages as pc', 'psi.policy_coverage_id', '=', 'pc.id')
                ->leftJoin('risk_address as ra', 'pc.risk_address_id', '=', 'ra.id')
                ->where('pc.policy_id', $policy->id)
                ->whereNull('pc.deleted_at')
                ->whereNull('psi.deleted_at')
                ->when($policyActionId, fn($q, $a) => $q->where('pc.action_id', $a))
                ->select(
                    'psi.id as policy_coverage_id',
                    DB::raw('psi.specified_coverage_id as coverage_id'),
                    DB::raw("COALESCE(psi.custom_name, 'Specified Item') as coverage_name"),
                    DB::raw("CAST(NULL AS CHAR) as coverage_code"),
                    'psi.sum_insured as coverage_limit',
                    'pc.risk_address_id',
                    'ra.address_name as risk_address_name',
                    DB::raw("'specified' as source")
                )
                ->get();
        }

        // ── Specialist schedule items: Plant All Risk (par_coverages) ────────
        // Engineering PAR policies keep their insured-item schedule as a JSON
        // array on par_coverages.insured_items (one row per policy_coverages
        // row / action), NOT as policy_coverage_detail rows — so the base query
        // above yields nothing for them and a PAR claim had no item to reserve
        // against (prod claim G2026005319 / COMG2026212367, Sept 2026: 168
        // plant items, grid showed only the Specified Item row).
        //
        // Items carry no id and the array is re-written on every endorsement
        // (new items appended, existing order preserved), so an array index is
        // not a stable key. The coverage_id stored on claim_reserves_coverages
        // is instead derived from the item itself: crc32 of the normalised
        // description mapped into a reserved [1,000,000,000 … 1,999,999,999]
        // band. That fits int(11), sits clear of real ids (~475k) and the
        // union synthetic band (9,0xx,xxx), and survives endorsements as long
        // as the description is unchanged. Aggregation keys on
        // (coverage_id | coverage_name), so a crc collision only merges totals
        // if the names are identical too.
        $parItems = collect();
        if (\Schema::hasTable('par_coverages')) {
            $parHasSoftDelete = \Schema::hasColumn('par_coverages', 'deleted_at');
            $toAmount = function ($v): ?float {
                if ($v === null || $v === '' || is_array($v)) return null;
                if (is_numeric($v)) return (float) $v;
                $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) $v));
                return is_numeric($clean) ? (float) $clean : null;
            };

            $parRows = DB::table('par_coverages as par')
                ->join('policy_coverages as pc', 'par.policy_coverage_id', '=', 'pc.id')
                ->leftJoin('risk_address as ra', 'pc.risk_address_id', '=', 'ra.id')
                ->where('pc.policy_id', $policy->id)
                ->whereNull('pc.deleted_at')
                ->when($parHasSoftDelete, fn($q) => $q->whereNull('par.deleted_at'))
                ->when($policyActionId, fn($q, $a) => $q->where('pc.action_id', $a))
                ->orderBy('pc.action_id')
                ->orderBy('par.id')
                ->get([
                    'pc.id as policy_coverage_id',
                    'par.insured_items',
                    'pc.risk_address_id',
                    'ra.address_name as risk_address_name',
                ]);

            foreach ($parRows as $par) {
                $items = json_decode((string) $par->insured_items, true);
                if (!is_array($items)) continue;
                foreach ($items as $item) {
                    if (!is_array($item)) continue;
                    // Descriptions arrive as "Make Model FLEETNO\tREGISTRATION";
                    // collapse whitespace so tabs never leak into the grid or
                    // the stored coverage_name.
                    $desc = trim((string) preg_replace('/\s+/u', ' ', (string) ($item['description'] ?? '')));
                    if ($desc === '') continue;
                    $parItems->push((object) [
                        'policy_coverage_id' => $par->policy_coverage_id,
                        'coverage_id'        => self::parItemCoverageId($desc),
                        // claim_reserves_coverages.coverage_name is varchar(200);
                        // clamp here so the stored name round-trips exactly.
                        'coverage_name'      => mb_substr('Plant All Risk - ' . $desc, 0, 200),
                        'coverage_code'      => 'PLANTALLRISKS',
                        'coverage_limit'     => $toAmount($item['sum_insured'] ?? null),
                        'risk_address_id'    => $par->risk_address_id,
                        'risk_address_name'  => $par->risk_address_name,
                        'source'             => 'specialist',
                    ]);
                }
            }

            // A schedule can legitimately list the same description several
            // times (e.g. "Sany SCP35C6 Forklift" ×4 with no registration).
            // Those share one synthetic id + name, so their reserve/payment
            // totals would aggregate onto every copy. Collapse identical items
            // within one par row into a single line whose limit is the summed
            // sum insured — the name stays count-free so the key survives a
            // later endorsement that adds a fifth unit.
            $parItems = $parItems
                ->groupBy(fn($c) => $c->policy_coverage_id . '|' . $c->coverage_id . '|' . $c->coverage_name)
                ->map(function ($grp) {
                    $first = $grp->first();
                    $limits = $grp->pluck('coverage_limit')->filter(fn($v) => $v !== null);
                    $first->coverage_limit = $limits->isEmpty() ? null : (float) $limits->sum();
                    return $first;
                })
                ->values();

            // Without an action filter every historical par_coverages row of
            // the policy is read, so each item repeats once per action. Keep
            // only the latest occurrence of each (coverage_id | name) pair.
            if (!$policyActionId) {
                $parItems = $parItems->reverse()
                    ->unique(fn($c) => $c->coverage_id . '|' . $c->coverage_name)
                    ->reverse()
                    ->values();
            }
        }

        $allCoverages = $baseCoverages
            ->concat($extensionCoverages)
            ->concat($motorExtensionCoverages)
            ->concat($specifiedItems)
            ->concat($parItems);

        // ── Per-coverage reserve/payment aggregation ─────────────────────────
        // Mirror graphiteBWV8 (ClaimsController.php :829-832): SUM by coverage_id
        // + claim_id across claim_reserves_coverages so the table can show
        // "Reserve Created" / "Payment Created" / "Balance" pre-populated.
        // Voided rows (is_payment_voided in {1,2}) are excluded so a voided
        // payment doesn't double-deduct from balance.
        // Group by coverage_id AND coverage_name. Motor extensions store the
        // same coverage_id (hed.id) for every vehicle, with the vehicle encoded
        // only in coverage_name — so grouping on coverage_id alone made one
        // vehicle's reserve/payment total appear on every vehicle row sharing
        // that extension. Keying on (coverage_id | coverage_name) attributes
        // the totals to the specific row they were entered against.
        $aggsRaw = DB::table('claim_reserves_coverages')
            ->where('claim_id', $claimId)
            ->where(function ($q) {
                $q->whereNull('is_payment_voided')->orWhere('is_payment_voided', 0);
            })
            ->select(
                'coverage_id',
                'coverage_name',
                DB::raw('SUM(COALESCE(reserve_amt,0)) as reserveAmt'),
                DB::raw('SUM(COALESCE(payment_amt,0)) as paymentAmt')
            )
            ->groupBy('coverage_id', 'coverage_name')
            ->get()
            ->keyBy(fn ($a) => (int) $a->coverage_id . '|' . (string) $a->coverage_name);

        $rows = $allCoverages->values()->map(function ($c, $idx) use ($aggsRaw, $isWrittenOff) {
            $covId      = (int) ($c->coverage_id ?? 0);
            // Match totals on (coverage_id | coverage_name) so per-vehicle motor
            // extensions get their own reserve/payment figures, not the shared
            // coverage_id sum.
            $agg        = $aggsRaw->get($covId . '|' . (string) ($c->coverage_name ?? ''));
            $reserveAmt = (float) ($agg->reserveAmt ?? 0);
            $paymentAmt = (float) ($agg->paymentAmt ?? 0);
            return [
                // Stable per-row unique id (its index within this response).
                // Motor extensions repeat the same coverageId (hed.id) across
                // every vehicle on the policy, and share the same
                // policyCoverageId, so the FE cannot key allocations on
                // source+policyCoverageId+coverageId alone — every vehicle row
                // collided, so one input filled all of them and submitting
                // created a duplicate reserve per row. rowUid gives each row a
                // distinct key. The stored coverage_id is still coverageId.
                'rowUid'           => $idx,
                'policyCoverageId' => (int) $c->policy_coverage_id,
                'coverageId'       => $covId,
                'coverageName'     => $c->coverage_name ?? 'N/A',
                'coverageCode'     => $c->coverage_code ?? null,
                'coverageLimit'    => $c->coverage_limit !== null ? (float) $c->coverage_limit : null,
                'riskAddressId'    => $c->risk_address_id !== null ? (int) $c->risk_address_id : null,
                'riskAddressName'  => $c->risk_address_name ?? null,
                'source'           => $c->source,
                'reserveAmt'       => $reserveAmt,
                'paymentAmt'       => $paymentAmt,
                'balance'          => $reserveAmt - $paymentAmt,
                'writtenOff'       => $isWrittenOff($covId, $c->coverage_name ?? null),
            ];
        })->values()->all();

        // ── Prior-term (orphaned) reserves ───────────────────────────────────
        // A reserve is stored against the coverage_id of the policy term/action
        // it was booked under. When the policy renews and the claim advances to
        // a newer action, that reserve's coverage is no longer in the
        // current-action grid above, so its outstanding balance would silently
        // disappear from the payable list (it still counts in the header totals,
        // so the claim shows a Balance that cannot be paid). Surface any such
        // aggregation group — one that no current-term row consumed and that has
        // a positive outstanding balance — as an extra payable row. It carries
        // the ORIGINAL coverage_id/coverage_name so a Loss Payment posted against
        // it aggregates straight back into the same group.
        $usedKeys = [];
        foreach ($rows as $r) {
            $usedKeys[(int) $r['coverageId'] . '|' . (string) $r['coverageName']] = true;
        }
        foreach ($aggsRaw as $key => $agg) {
            if (isset($usedKeys[$key])) continue;
            $reserveAmt = (float) $agg->reserveAmt;
            $paymentAmt = (float) $agg->paymentAmt;
            if ($reserveAmt - $paymentAmt <= 0.0) continue; // nothing outstanding
            $rows[] = [
                'rowUid'           => 'orphan-' . $agg->coverage_id . '-' . md5((string) $agg->coverage_name),
                'policyCoverageId' => 0,
                'coverageId'       => (int) $agg->coverage_id,
                'coverageName'     => $agg->coverage_name,
                'coverageCode'     => null,
                'coverageLimit'    => null,
                'riskAddressId'    => null,
                'riskAddressName'  => null,
                'source'           => 'prior-term',
                'reserveAmt'       => $reserveAmt,
                'paymentAmt'       => $paymentAmt,
                'balance'          => $reserveAmt - $paymentAmt,
                'writtenOff'       => $isWrittenOff((int) $agg->coverage_id, $agg->coverage_name),
                'priorTerm'        => true,
            ];
        }

        // ── Group by risk address for DOM/COM ────────────────────────────────
        // Returned shape mirrors graphiteBWV8's $groupedRiskAddCoverages
        // (associative array keyed by risk_address_name). Non-DOM/COM
        // products skip grouping and consume `rows` directly.
        $groups = null;
        if ($isDomCom) {
            $tmp = [];
            foreach ($rows as $row) {
                $key = $row['riskAddressName'] ?: 'Unassigned';
                $tmp[$key] = $tmp[$key] ?? [];
                $tmp[$key][] = $row;
            }
            $groups = $tmp;
        }

        return response()->json([
            'data' => [
                'isGrouped'      => $isDomCom,
                'productId'      => $productId,
                'policyActionId' => $policyActionId,
                'rows'           => $rows,
                'groups'         => $groups,
            ],
        ]);
    }

    // ─── 8c. reservesLookups ─────────────────────────────────────────────────

    /**
     * GET /claims-v2/lookups/reserve-types
     *
     * Returns transaction_type rows from lookup_data, optionally filtering
     * out 'Initial Reserves' (id=86) which graphiteBWV8 hides from the Add
     * form because it's reserved for the auto-seeded opening row.
     */
    public function reserveTransactionTypes(Request $request): JsonResponse
    {
        $excludeInitial = filter_var($request->query('exclude_initial', '1'), FILTER_VALIDATE_BOOL);
        if (!\Schema::hasTable('lookup_data')) {
            return response()->json(['data' => []]);
        }
        $rows = DB::table('lookup_data')
            ->where('key', 'transaction_type')
            ->when($excludeInitial, fn($q) => $q->where('value', '!=', 'Initial Reserves'))
            ->orderBy('id')
            ->get(['id', 'value'])
            ->map(fn($r) => [
                'id'   => (int) $r->id,
                'name' => self::TX_TYPE_LABEL_OVERRIDES[(int) $r->id] ?? $r->value,
            ])
            ->values();
        return response()->json(['data' => $rows]);
    }

    /**
     * Display-only relabelling of lookup_data transaction_type / sub_type rows.
     * IDs stay stable so writes / reads against claim_reserves continue to
     * work; only the human-readable name is overridden where business has
     * asked us to group salvage and subrogation together as one option.
     */
    private const TX_TYPE_LABEL_OVERRIDES = [
        91 => 'Salvage / Subrogation Reserve',
        92 => 'Salvage / Subrogation Payment',
    ];
    private const TX_SUB_TYPE_LABEL_OVERRIDES = [
        97  => 'Salvage Reserve',
        98  => 'Subrogation Reserve',
        99  => 'Salvage Payment',
        100 => 'Subrogation Payment',
    ];

    /**
     * GET /claims-v2/lookups/reserve-sub-types
     *
     * Returns transaction_sub_type rows. The FE filters by `for_type` query
     * param to mirror graphiteBWV8 TransactionType() JS (lines 6289-6390),
     * which only shows certain sub-types per transaction type.
     */
    public function reserveTransactionSubTypes(Request $request): JsonResponse
    {
        $forType = (int) $request->query('for_type', 0);
        if (!\Schema::hasTable('lookup_data')) {
            return response()->json(['data' => []]);
        }
        $rows = DB::table('lookup_data')
            ->where('key', 'transaction_sub_type')
            ->orderBy('id')
            ->get(['id', 'value'])
            ->map(fn($r) => [
                'id'   => (int) $r->id,
                'name' => self::TX_SUB_TYPE_LABEL_OVERRIDES[(int) $r->id] ?? $r->value,
            ]);

        // graphiteBWV8 sub-type filtering matrix (general.blade.php :6289-6377):
        //   42 / 43 / 44 (loss reserve / payment / reset) -> hide ALL of [93,94,95,96,97,98,99,100];
        //                                                    only the standard 49-56 set is visible
        //   89 (TP Liability Reserve)                     -> only 93, 94
        //   90 (TP Liability Payment)                     -> only 95, 96
        //   91 (Salvage Reserve)                          -> only 97, 98
        //   92 (Salvage Payment)                          -> only 99, 100
        //   86 (Initial Reserves)                         -> no sub-type field (not user-pickable)
        $standardHide = [93, 94, 95, 96, 97, 98, 99, 100];
        $onlyIds = match ($forType) {
            89      => [93, 94],
            90      => [95, 96],
            91      => [97, 98],
            92      => [99, 100],
            default => null,
        };
        if (is_array($onlyIds)) {
            $rows = $rows->whereIn('id', $onlyIds);
        } elseif (in_array($forType, [42, 43, 44], true)) {
            $rows = $rows->whereNotIn('id', $standardHide);
        }
        return response()->json(['data' => $rows->values()]);
    }

    /**
     * GET /claims-v2/lookups/payees
     *
     * Returns the supplier list used by the Reserves Add-form payee dropdown.
     * Mirrors graphiteBWV8 reserves.blade.php where $transPayees = Supplier::all().
     */
    public function reservePayees(): JsonResponse
    {
        if (!\Schema::hasTable('suppliers')) {
            return response()->json(['data' => []]);
        }
        $rows = DB::table('suppliers')
            ->orderBy('supplierName')
            ->get(['id', 'supplierName', 'address'])
            ->map(fn($r) => [
                'id'      => (int) $r->id,
                'name'    => $r->supplierName,
                'address' => $r->address ?? null,
            ])
            ->values();
        return response()->json(['data' => $rows]);
    }

    // ─── 9. documents ────────────────────────────────────────────────────────

    public function documents(int $claimId): JsonResponse
    {
        // Select specific columns so we can return the legacy
        // document-type pair (type / document_type_name) alongside the
        // V2 file metadata. Legacy admin pages wrote these columns and
        // the 'Attachments' tab in the legacy blade showed them — we
        // need to surface them in V2 so users don't see a dumbed-down
        // list that's missing the type info.
        $cols = \Schema::getColumnListing('claim_attachments');
        $docs = DB::table('claim_attachments')
            ->where('claim_id', $claimId)
            ->when(in_array('deleted_at', $cols, true), fn($q) => $q->whereNull('deleted_at'))
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($d) use ($cols) {
                // Legacy sometimes serialized multiple file paths into
                // `attachment` as a PHP serialized array; V2 always writes
                // a single file_name. Handle both: if `attachment` is a
                // serialized array, surface the first path as the url;
                // otherwise use file_name.
                $primaryPath = $d->file_name ?? null;
                if (empty($primaryPath) && !empty($d->attachment)) {
                    try {
                        $arr = @unserialize($d->attachment, ['allowed_classes' => false]);
                        if (is_array($arr) && count($arr)) $primaryPath = $arr[0];
                    } catch (\Throwable $e) { /* ignore */ }
                }
                return [
                    'id'              => $d->id,
                    'name'            => $d->name ?? null,
                    'fileName'        => $primaryPath,
                    'fileType'        => $d->file_type ?? null,
                    // Legacy doc-type metadata: `type` is the lookup key,
                    // `document_type_name` is the human-readable label
                    // (e.g. 'Police Report', 'Medical Certificate').
                    'docType'         => in_array('type', $cols, true) ? ($d->type ?? null) : null,
                    'docTypeName'     => in_array('document_type_name', $cols, true) ? ($d->document_type_name ?? null) : null,
                    'url'             => $this->cdnUrl($primaryPath),
                    'createdAt'       => $d->created_at ?? null,
                ];
            })->values()->all();

        return response()->json(['data' => $docs]);
    }

    // ─── 10. uploadDocument ──────────────────────────────────────────────────

    public function uploadDocument(Request $request, int $claimId): JsonResponse
    {
        // Accept either the legacy multi-file upload shape (files[]) or a
        // single-file shape (file). Also accept optional doc-type metadata
        // so the Attachments tab in V2 hits parity with the legacy blade
        // (name + Document Type dropdown + file picker).
        //
        // Per-file cap is 40 MB — comfortably under the infra ceiling (nginx
        // client_max_body_size 50M + php.ini upload_max_filesize/post_max_size
        // 50M) so multipart overhead never pushes a near-limit upload past
        // post_max_size. validate() throws a 422 with these messages on
        // failure and returns normally on success, so no manual error check
        // is needed (a prior refactor left a dangling $validator->fails()
        // here with no Validator::make(), which 500'd every valid upload).
        $request->validate([
            'files'               => 'nullable|array|max:10',
            'files.*'             => 'file|max:40960',
            'file'                => 'nullable|file|max:40960',
            'name'                => 'nullable|string|max:255',
            'type'                => 'nullable|string|max:100',
            'document_type_name'  => 'nullable|string|max:255',
        ], [
            'files.*.max' => 'Each file is too large. The maximum allowed size is 40 MB.',
            'file.max'    => 'The file is too large. The maximum allowed size is 40 MB.',
        ]);

        $table = $this->claimsTable();
        $claim = DB::table($table)->where('id', $claimId)->first();
        if (!$claim) {
            return response()->json(['message' => 'Claim not found.'], 404);
        }

        // Normalise to an array regardless of how the FE sent it.
        $files = $request->hasFile('files')
            ? $request->file('files')
            : ($request->hasFile('file') ? [$request->file('file')] : []);

        if (empty($files)) {
            return response()->json(['message' => 'At least one file is required.'], 422);
        }

        // Resolve doc-type label: prefer the explicit document_type_name
        // the user typed; fall back to looking up the file_type key in
        // the Lookup table so the label stays in sync with the dropdown.
        $typeKey  = $request->input('type');
        $typeName = $request->input('document_type_name');
        if (!$typeName && $typeKey) {
            $lookup = DB::table('lookup_data')
                ->where('key', 'file_type')
                ->where('value', $typeKey)
                ->first(['value']);
            if ($lookup) $typeName = $lookup->value;
        }

        $groupName = $request->input('name');
        $now   = Carbon::now();
        $cols  = \Schema::getColumnListing('claim_attachments');

        // Upload every file to S3 first so we can serialize all paths into
        // one row. This mirrors graphiteBWV8's attachmentUpload (one
        // claim_attachments record per "attachment group" with all file
        // paths PHP-serialized into the `attachment` column). Previously
        // V2 wrote one row per file which fragmented multi-file uploads
        // into multiple rows with no obvious link back to the row metadata.
        $paths = [];
        foreach ($files as $file) {
            $paths[] = $this->storeWithOriginalName($file, "MIS/{$claimId}/Documents");
        }

        $row = [
            'claim_id'   => $claimId,
            'name'       => $groupName ?: $files[0]->getClientOriginalName(),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // Serialize the array of S3 paths into the `attachment` column, the
        // same way graphiteBWV8's ClaimsController::attachmentUpload does.
        // ClaimsController::show() reads this back via @unserialize() and
        // emits one entry per path under the row's `files` array.
        if (in_array('attachment', $cols, true)) {
            $row['attachment'] = serialize($paths);
        }
        // Some envs of this table also have `file_name` (added later for
        // V2 single-file writes). Keep parity by populating it with the
        // first path so older read paths that only look at `file_name`
        // still surface at least one file.
        if (in_array('file_name', $cols, true)) {
            $row['file_name'] = $paths[0];
        }
        if (in_array('file_type', $cols, true)) {
            $row['file_type'] = $files[0]->getClientMimeType();
        }
        if (in_array('type', $cols, true) && $typeKey) {
            $row['type'] = $typeKey;
        }
        if (in_array('document_type_name', $cols, true) && $typeName) {
            $row['document_type_name'] = $typeName;
        }

        $row   = array_intersect_key($row, array_flip($cols));
        $docId = DB::table('claim_attachments')->insertGetId($row);

        return response()->json([
            'data' => [
                'id'          => $docId,
                'name'        => $row['name'] ?? $files[0]->getClientOriginalName(),
                'docType'     => $typeKey,
                'docTypeName' => $typeName,
                // Surface every uploaded file so the FE can render them
                // immediately without waiting for a refetch round-trip.
                'files'       => array_map(fn($p) => [
                    'name' => basename($p),
                    'url'  => $this->cdnUrl($p),
                ], $paths),
            ],
            'message' => count($paths) . ' file(s) uploaded successfully.',
        ], 201);
    }

    // ─── Review Notes ────────────────────────────────────────────────────────

    /**
     * GET /claims/{id}/review-notes — list a claim's review notes (newest
     * first) with author, recipients and attachments. Read shape mirrors the
     * complaint-log block in ClaimsController::show.
     */
    public function reviewNotes(int $claimId): JsonResponse
    {
        $notes = DB::table('claim_review_notes')
            ->where('claim_id', $claimId)
            ->orderByDesc('id')
            ->get();

        if ($notes->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $noteIds = $notes->pluck('id')->all();

        $recipients = DB::table('claim_review_note_recipients')
            ->whereIn('review_note_id', $noteIds)
            ->get()
            ->groupBy('review_note_id');

        $files = DB::table('claim_review_note_files')
            ->whereIn('review_note_id', $noteIds)
            ->get()
            ->groupBy('review_note_id');

        // @mentions (claims_mentions feature) — additive. Wrapped so a missing
        // table (flag never armed / migration not run on this env) can never
        // break the review-notes list.
        $mentions = collect();
        try {
            $mentions = DB::table('claim_note_mentions')
                ->whereIn('review_note_id', $noteIds)
                ->get()
                ->groupBy('review_note_id');
        } catch (\Throwable $e) {
            Log::warning('reviewNotes: mention load skipped: ' . $e->getMessage());
        }

        $data = $notes->map(fn($n) => [
            'id'         => $n->id,
            'title'      => $n->title,
            'note'       => $n->note,
            'priority'   => $n->priority ?? null,
            'createdBy'  => $n->created_by_name ?: ($n->created_by !== null ? (string) $n->created_by : null),
            'createdAt'  => $n->created_at,
            'recipients' => collect($recipients->get($n->id, []))->map(fn($r) => [
                'name'       => $r->name ?: $r->email,
                'email'      => $r->email,
                'notifiedAt' => $r->notified_at,
            ])->values(),
            'files'      => collect($files->get($n->id, []))->map(fn($f) => [
                'name' => $f->name,
                'url'  => $this->cdnUrl($f->file_path),
            ])->values(),
            // Parsed @mentions for UI highlight/notify. resolved=false = the
            // handle didn't map to a Graphite user.
            'mentions'   => collect($mentions->get($n->id, []))->map(fn($m) => [
                'handle'   => $m->handle,
                'userId'   => $m->mentioned_user_id !== null ? (int) $m->mentioned_user_id : null,
                'resolved' => $m->mentioned_user_id !== null,
            ])->values(),
        ]);

        return response()->json(['data' => $data->values()]);
    }

    /**
     * POST /claims/{id}/review-notes — log a review note, tag recipients,
     * attach files, then notify each recipient (in-app + email with a note
     * preview and a "View Review" deep-link). Multipart form:
     *   note               (required) note body
     *   title              (optional) short subject
     *   recipient_ids[]    (optional) picked internal user ids
     *   recipient_emails[] (optional) free-text / external emails
     *   files[]            (optional) attachments (≤10, ≤40 MB each)
     *
     * Notification is best-effort and runs after the DB commit, so a mail
     * outage never blocks the note from being saved.
     */
    public function storeReviewNote(Request $request, int $claimId): JsonResponse
    {
        $priorityValues = array_column((array) config('claims_comment_status.priorities', []), 'value');
        $validated = $request->validate([
            'note'               => 'required|string',
            // Comment priority (claims_comment_status feature) — additive &
            // optional. Drives the unread-@mention reminder tick threshold.
            'priority'           => ['nullable', 'string', \Illuminate\Validation\Rule::in($priorityValues)],
            'recipient_ids'      => 'nullable|array',
            'recipient_ids.*'    => 'integer|exists:users,id',
            'recipient_emails'   => 'nullable|array',
            'recipient_emails.*' => 'email',
            'files'              => 'nullable|array|max:10',
            'files.*'            => 'file|max:40960',
        ], [
            'files.*.max' => 'Each file is too large. The maximum allowed size is 40 MB.',
        ]);

        $table = $this->claimsTable();
        $claim = DB::table($table)->where('id', $claimId)->first();
        if (!$claim) {
            return response()->json(['message' => 'Claim not found.'], 404);
        }

        $user = $request->user();
        $authorName = $user
            ? (trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? '')) ?: ($user->email ?? null))
            : null;
        $now = Carbon::now();

        // Resolve recipients: picked users (name + email from users) plus any
        // free-text emails. De-duped by email so a user picked twice — or once
        // as a user and once as a raw email — is only notified once.
        $recipients = [];
        $userIds = array_values(array_unique($validated['recipient_ids'] ?? []));
        if (!empty($userIds)) {
            $users = DB::table('users')->whereIn('id', $userIds)
                ->get(['id', 'firstName', 'lastName', 'email']);
            foreach ($users as $u) {
                if (empty($u->email)) continue;
                $recipients[] = [
                    'user_id' => (int) $u->id,
                    'email'   => $u->email,
                    'name'    => trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')) ?: $u->email,
                ];
            }
        }
        foreach (array_unique($validated['recipient_emails'] ?? []) as $email) {
            $recipients[] = ['user_id' => null, 'email' => $email, 'name' => null];
        }
        $seen = [];
        $recipients = array_values(array_filter($recipients, function ($r) use (&$seen) {
            $k = strtolower($r['email']);
            if (isset($seen[$k])) return false;
            $seen[$k] = true;
            return true;
        }));

        $noteId = null;
        $storedFiles = [];
        DB::transaction(function () use (&$noteId, &$storedFiles, $claimId, $claim, $validated, $authorName, $user, $now, $recipients, $request) {
            $noteId = DB::table('claim_review_notes')->insertGetId([
                'claim_id'        => $claimId,
                'policy_id'       => $claim->policy_id ?? null,
                'note'            => $validated['note'],
                'priority'        => $validated['priority'] ?? null,
                'created_by'      => optional($user)->id,
                'created_by_name' => $authorName,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);

            foreach ($recipients as $r) {
                DB::table('claim_review_note_recipients')->insert([
                    'review_note_id' => $noteId,
                    'user_id'        => $r['user_id'],
                    'email'          => $r['email'],
                    'name'           => $r['name'],
                    'created_at'     => $now,
                ]);
            }

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $this->storeWithOriginalName($file, "MIS/{$claimId}/ReviewNotes");
                    DB::table('claim_review_note_files')->insert([
                        'review_note_id' => $noteId,
                        'name'           => $file->getClientOriginalName(),
                        'file_path'      => $path,
                        'mime'           => $file->getClientMimeType(),
                        'size'           => $file->getSize(),
                        'created_at'     => $now,
                    ]);
                    $storedFiles[] = $path;
                }
            }
        });

        $extras = [];
        if (count($recipients)) $extras[] = count($recipients) . ' recipient(s) tagged';
        if (count($storedFiles)) $extras[] = count($storedFiles) . ' attachment(s)';
        $this->logActivity(
            $claimId,
            $table,
            'Review note logged'
                . ($extras ? ' (' . implode(', ', $extras) . ')' : '')
                . '.'
        );

        // Notify recipients — best-effort, post-commit.
        try {
            $claimNumber = $claim->claim_number ?? null;
            // The "View Review" link must open the React SPA, not the API host.
            // Prefer REACT_SPA_URL (the staff Graphite app); fall back to
            // FRONTEND_URL then app.url. config('app.url') is the backend origin
            // (e.g. localhost:8000), so it's the last resort only.
            $base = rtrim((string) (env('REACT_SPA_URL') ?: env('FRONTEND_URL') ?: config('app.url')), '/');
            $reviewUrl = "{$base}/claims/{$claimId}?tab=reviewNotes";
            $preview = \Illuminate\Support\Str::limit(trim($validated['note']), 600);

            // Attachments are intentionally NOT emailed — they're stored on the
            // note and visible in the Claim Review tab only.
            $notifiedEmails = \AlphaDirect\Services\NotificationDispatcher::claimReviewNote(
                $claimId,
                $claimNumber,
                $reviewUrl,
                [
                    'author_name'     => $authorName ?: 'A claim handler',
                    'subject'         => $this->buildReviewSubject($claim),
                    'claim_reference' => $this->buildClaimReference($claim),
                    'note_preview'    => $preview,
                    // Surfaced on the in-app bell so users see claim type + policy
                    // at a glance, not just the claim number (claims-team #4).
                    'claim_type'      => $claim->claim_type ?? null,
                    'policy_number'   => optional(\AlphaDirect\Policy::find($claim->policy_id))->policyNumber,
                    // Client/company name on the bell — v5: show the name, not just the number.
                    'customer_name'   => $this->buildCustomerName($claim),
                ],
                $recipients
            );

            if (!empty($notifiedEmails)) {
                DB::table('claim_review_note_recipients')
                    ->where('review_note_id', $noteId)
                    ->whereIn('email', $notifiedEmails)
                    ->update(['notified_at' => Carbon::now()]);
            }
        } catch (\Throwable $e) {
            Log::warning("storeReviewNote: notification dispatch failed for claim {$claimId}: " . $e->getMessage());
        }

        // @mentions (Phase-3) — behind the `claims_mentions` flag (default OFF).
        // Best-effort + post-commit: parsing/persisting a mention must never
        // block or fail the note save, and when the flag is OFF this is a no-op
        // so storeReviewNote behaves exactly as before.
        try {
            if ($noteId && \AlphaDirect\Services\IntegrationSettings::isEnabled('claims_mentions', false)) {
                $this->captureClaimNoteMentions($claimId, (int) $noteId, $validated['note'], $claim, $user, $authorName);
            }
        } catch (\Throwable $e) {
            Log::warning("storeReviewNote: mention capture failed for claim {$claimId}: " . $e->getMessage());
        }

        return response()->json([
            'message' => 'Review note logged.',
            'data'    => ['id' => $noteId],
        ], 201);
    }

    // ─── Comment status + sub-reason (claims_comment_status feature) ─────────

    /**
     * GET /claims/{id}/comment-status — the claim's current "comment status" +
     * "Awaiting — what?" sub-reason, plus the canonical option lists (so the
     * frontend selectors and server validation share one source of truth). The
     * read is not flag-gated (it degrades to nulls for a claim that has none):
     * only the reminder SENDS are gated by `claims_comment_status`. Additive —
     * a missing claim_comment_statuses table can never break the response.
     */
    public function commentStatus(int $claimId): JsonResponse
    {
        $row = null;
        try {
            $row = DB::table('claim_comment_statuses')->where('claim_id', $claimId)->first();
        } catch (\Throwable $e) {
            Log::warning("commentStatus: read skipped for claim {$claimId}: " . $e->getMessage());
        }

        return response()->json([
            'data' => [
                'commentStatus'    => $row->comment_status ?? null,
                'commentSubReason' => $row->comment_sub_reason ?? null,
                'updatedBy'        => $row->updated_by_name ?? null,
                'updatedAt'        => $row->updated_at ?? null,
            ],
            'options' => [
                'statuses'           => array_values((array) config('claims_comment_status.statuses', [])),
                'awaitingSubReasons' => array_values((array) config('claims_comment_status.awaiting_sub_reasons', [])),
                'subReasonStatus'    => config('claims_comment_status.sub_reason_status', 'Awaiting'),
                'priorities'         => array_values((array) config('claims_comment_status.priorities', [])),
            ],
        ]);
    }

    /**
     * PUT /claims/{id}/comment-status — set/clear the comment status + sub-reason
     * (upsert, 1:1 with the claim). RBAC: permission:claim-edit (route). The
     * sub-reason is only accepted when the status is the sub-reason status
     * ("Awaiting"); it is cleared otherwise. Both may be null to reset.
     */
    public function setCommentStatus(Request $request, int $claimId): JsonResponse
    {
        $statuses   = array_values((array) config('claims_comment_status.statuses', []));
        $subReasons = array_values((array) config('claims_comment_status.awaiting_sub_reasons', []));
        $subReasonStatus = (string) config('claims_comment_status.sub_reason_status', 'Awaiting');

        $validated = $request->validate([
            'comment_status'     => ['nullable', 'string', \Illuminate\Validation\Rule::in($statuses)],
            'comment_sub_reason' => ['nullable', 'string', \Illuminate\Validation\Rule::in($subReasons)],
        ]);

        $table = $this->claimsTable();
        $claim = DB::table($table)->where('id', $claimId)->first();
        if (!$claim) {
            return response()->json(['message' => 'Claim not found.'], 404);
        }

        $status    = $validated['comment_status'] ?? null;
        // Sub-reason only applies to the Awaiting status; drop it otherwise.
        $subReason = ($status === $subReasonStatus) ? ($validated['comment_sub_reason'] ?? null) : null;

        $user       = $request->user();
        $authorName = $user
            ? (trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? '')) ?: ($user->email ?? null))
            : null;
        $now = Carbon::now();

        // updateOrInsert applies these values on both insert and update, so
        // created_at is intentionally omitted (it would be reset on every
        // update); updated_at is the meaningful audit stamp here.
        DB::table('claim_comment_statuses')->updateOrInsert(
            ['claim_id' => $claimId],
            [
                'comment_status'     => $status,
                'comment_sub_reason' => $subReason,
                'updated_by'         => optional($user)->id,
                'updated_by_name'    => $authorName,
                'updated_at'         => $now,
            ]
        );

        $this->logActivity(
            $claimId,
            $table,
            'Comment status set to ' . ($status ?: 'none')
                . ($subReason ? " ({$subReason})" : '') . '.'
        );

        return response()->json([
            'message' => 'Comment status updated.',
            'data'    => [
                'commentStatus'    => $status,
                'commentSubReason' => $subReason,
                'updatedBy'        => $authorName,
                'updatedAt'        => $now->toIso8601String(),
            ],
        ]);
    }

    /**
     * Parse @mentions from a review note, resolve them to Graphite users, persist
     * each to claim_note_mentions (resolved rows carry the user id; unresolved
     * handles are kept with a NULL user id), then notify the mentioned users
     * IN-APP ONLY — never by email, so this can never cause an email storm.
     *
     * Only ever called when the `claims_mentions` flag is enabled. All work is
     * wrapped by the caller so a failure never affects the note itself.
     */
    private function captureClaimNoteMentions(int $claimId, int $noteId, string $noteText, object $claim, $author, ?string $authorName): void
    {
        $handles = \AlphaDirect\Services\ClaimMentionParser::parse($noteText);
        if (empty($handles)) {
            return;
        }

        // Candidate users — staff scale; note creation is low-frequency and the
        // whole path is flag-gated, so a full staff scan here is acceptable.
        $users = DB::table('users')
            ->select('id', 'firstName', 'lastName', 'email')
            ->get()
            ->map(fn($u) => [
                'id'        => (int) $u->id,
                'firstName' => $u->firstName,
                'lastName'  => $u->lastName,
                'email'     => $u->email,
            ])
            ->all();

        $resolved = \AlphaDirect\Services\ClaimMentionParser::resolve($handles, $users);
        $now = Carbon::now();

        $matchedByHandle = [];
        foreach ($resolved['matches'] as $m) {
            $matchedByHandle[$m['handle']] = (int) $m['userId'];
            DB::table('claim_note_mentions')->insert([
                'review_note_id'    => $noteId,
                'claim_id'          => $claimId,
                'handle'            => $m['handle'],
                'mentioned_user_id' => (int) $m['userId'],
                'created_at'        => $now,
            ]);
        }
        foreach ($resolved['unknown'] as $handle) {
            DB::table('claim_note_mentions')->insert([
                'review_note_id'    => $noteId,
                'claim_id'          => $claimId,
                'handle'            => $handle,
                'mentioned_user_id' => null,
                'created_at'        => $now,
            ]);
        }

        // Notify mentioned users — IN-APP ONLY (no email channel), fail-safe.
        $authorId    = (int) (optional($author)->id ?? 0);
        $claimNumber = $claim->claim_number ?? ('#' . $claimId);
        $who         = $authorName ?: 'A claim handler';
        $action      = "/claims/{$claimId}?tab=reviewNotes";
        // Bell metadata (v5 — client name + type + policy on every claim
        // notification, not just the review note). Resolved once, not per recipient.
        $mentionClaimType = $claim->claim_type ?? null;
        $mentionPolicyNo  = !empty($claim->policy_id)
            ? DB::table('policies')->where('id', $claim->policy_id)->value('policyNumber')
            : null;
        $mentionCustomer  = $this->buildCustomerName($claim);

        foreach (\AlphaDirect\Services\ClaimMentionParser::matchedUserIds($resolved) as $userId) {
            if ($userId === $authorId) {
                continue; // don't ping the author about their own note
            }
            try {
                \AlphaDirect\Services\NotificationDispatcher::send(
                    $userId,
                    'claim_mention',
                    [
                        'title'         => 'You were mentioned on a claim',
                        'message'       => "{$who} mentioned you in a review note on claim {$claimNumber}.",
                        'claim_id'      => $claimId,
                        'claim_number'  => $claim->claim_number ?? null,
                        'claim_type'    => $mentionClaimType,
                        'policy_number' => $mentionPolicyNo,
                        'customer_name' => $mentionCustomer,
                    ],
                    $action,
                    ['in_app'] // in-app only — never email; no notification spray
                );

                // Link the just-created in-app notification back to the mention
                // row so the priority-reminder tick (claims_comment_status) can
                // tell whether this mention is still unread (via notifications.
                // read_at). Best-effort + column-guarded — a missing column /
                // notifications row never affects the mention notify above.
                try {
                    $notificationId = DB::connection('mysql_system')->table('notifications')
                        ->where('user_id', $userId)
                        ->where('type', 'claim_mention')
                        ->orderByDesc('id')
                        ->value('id');
                    DB::table('claim_note_mentions')
                        ->where('review_note_id', $noteId)
                        ->where('mentioned_user_id', $userId)
                        ->update(['notification_id' => $notificationId, 'notified_at' => $now]);
                } catch (\Throwable $e) {
                    Log::warning("captureClaimNoteMentions: notification link skipped for user {$userId} on claim {$claimId}: " . $e->getMessage());
                }
            } catch (\Throwable $e) {
                Log::warning("captureClaimNoteMentions: notify failed for user {$userId} on claim {$claimId}: " . $e->getMessage());
            }
        }
    }

    /**
     * Build the claim-review notification subject:
     *   CLAIM REVIEW - [Policy Number] [Customer Name] [Claimed Item] [Claim Type] CLAIM#: [Claim Number]
     * e.g. "CLAIM REVIEW - COMG2025147005 KNOCK TOGETIT PTY B613BWH MOTOR CLAIM#: G2026004394".
     */
    private function buildReviewSubject(object $claim): string
    {
        return $this->buildClaimSubject($claim, 'CLAIM REVIEW');
    }

    /**
     * Build a claim notification subject of the form:
     *   {PREFIX} - [Policy Number] [Customer Name] [Claimed Item] [Claim Type] CLAIM#: [Claim Number]
     * e.g. "WRITE OFF - COMG2025147005 KNOCK TOGETIT PTY B613BWH MOTOR CLAIM#: G2026004394".
     *
     * Each component is best-effort: a missing piece is dropped and the spaces
     * collapsed, so a partial claim never produces "{PREFIX} -   MOTOR …".
     * $claim is the raw `claims` row (has policy_id, customer_id, vehicle_plate,
     * claim_type, claim_number).
     */
    private function buildClaimSubject(object $claim, string $prefix): string
    {
        $reference = $this->buildClaimReference($claim);
        return $reference !== ''
            ? rtrim($prefix) . ' - ' . $reference
            : rtrim($prefix) . ' -';
    }

    /**
     * Build the claim reference line shared by email subjects and in-app
     * notifications:
     *   [Policy Number] [Customer Name] [Claimed Item] [Claim Type] CLAIM#: [Claim Number]
     * e.g. "COMG2025147005 KNOCK TOGETIT PTY B613BWH MOTOR CLAIM#: G2026004394".
     *
     * Each component is best-effort: a missing piece is dropped and the spaces
     * collapsed, so a partial claim never produces double spaces. $claim is the
     * raw `claims` row (has policy_id, customer_id, vehicle_plate, claim_type,
     * claim_number).
     */
    /**
     * Customer display name for a raw claims row — company name when the
     * customer is an Organisation, else the individual's first+last name.
     * Shared by buildClaimReference and the notification bell so both use
     * one source of truth. Returns null when no name can be resolved.
     */
    private function buildCustomerName(object $claim): ?string
    {
        if (empty($claim->customer_id)) {
            return null;
        }
        $companyId = DB::table('customer_profile')
            ->where('customer_id', $claim->customer_id)
            ->where('entity_type', 'Organisation')
            ->whereNotNull('company_id')
            ->value('company_id');
        if ($companyId) {
            $name = DB::table('companies')->where('id', $companyId)->value('name');
            if (!empty($name)) {
                return $name;
            }
        }
        $cust = DB::table('customer')->where('id', $claim->customer_id)
            ->first(['firstName', 'lastName']);
        if ($cust) {
            return trim(($cust->firstName ?? '') . ' ' . ($cust->lastName ?? '')) ?: null;
        }
        return null;
    }

    private function buildClaimReference(object $claim): string
    {
        // Policy number
        $policyNumber = !empty($claim->policy_id)
            ? DB::table('policies')->where('id', $claim->policy_id)->value('policyNumber')
            : null;

        // Customer name — company name when the customer is an Organisation,
        // otherwise the individual's name. Mirrors ClaimsController::show.
        $customerName = $this->buildCustomerName($claim);

        // Claimed item — vehicle registration for motor; fall back to new_claims.
        $claimedItem = $claim->vehicle_plate ?? null;
        if (empty($claimedItem) && !empty($claim->claim_number)) {
            $claimedItem = DB::table('new_claims')
                ->where('claim_number', $claim->claim_number)
                ->value('vehicle_plate');
        }

        $claimType = !empty($claim->claim_type) ? strtoupper(trim($claim->claim_type)) : null;

        $parts = array_filter(
            [$policyNumber, $customerName, $claimedItem, $claimType],
            fn($v) => $v !== null && trim((string) $v) !== ''
        );

        $reference = $parts ? implode(' ', $parts) : '';
        if (!empty($claim->claim_number)) {
            $reference = trim($reference . ' CLAIM#: ' . $claim->claim_number);
        }
        return $reference;
    }

    /**
     * GET /claims-v2/lookups/file-types — lookup options for the
     * Attachments tab's Document Type dropdown. Mirrors legacy
     * Lookup::where('key','file_type') which seeds values like
     * 'Police Report', 'Medical Certificate', 'Death Certificate',
     * 'Invoice', 'Quotation', etc.
     */
    public function fileTypeLookup(): JsonResponse
    {
        // Fallback mirrors the actual graphiteBWV8 lookup_data rows for
        // key=file_type, which only has two entries: 'Invoice' and
        // 'Documents'. The DB query below still wins when rows exist; the
        // fallback only kicks in on fresh dev DBs where the lookup table
        // hasn't been seeded.
        $fallback = collect(['Invoice', 'Documents'])
            ->map(fn($v) => ['id' => $v, 'name' => $v])->values();

        if (!\Schema::hasTable('lookup_data')) {
            return response()->json(['data' => $fallback]);
        }
        $rows = DB::table('lookup_data')
            ->where('key', 'file_type')
            ->orderBy('value')
            ->get(['value'])
            ->map(fn($r) => ['id' => $r->value, 'name' => $r->value])
            ->values();

        return response()->json(['data' => $rows->isNotEmpty() ? $rows : $fallback]);
    }

    // ─── 11. deleteDocument ──────────────────────────────────────────────────

    public function deleteDocument(int $claimId, int $docId): JsonResponse
    {
        $doc = DB::table('claim_attachments')
            ->where('id', $docId)
            ->where('claim_id', $claimId)
            ->first();

        if (!$doc) {
            return response()->json(['message' => 'Document not found.'], 404);
        }

        // Collect every S3 path stored on this row. The legacy `attachment`
        // column holds a PHP-serialized array of paths (graphiteBWV8 style);
        // the V2-era `file_name` column holds a single path. Either or both
        // may be set on a given row depending on how/when it was created.
        $paths = [];
        if (!empty($doc->attachment ?? null)) {
            $decoded = @unserialize($doc->attachment, ['allowed_classes' => false]);
            if (is_array($decoded)) {
                foreach ($decoded as $p) { if ($p) $paths[] = $p; }
            } else {
                $paths[] = $doc->attachment;
            }
        }
        if (!empty($doc->file_name ?? null) && !in_array($doc->file_name, $paths, true)) {
            $paths[] = $doc->file_name;
        }

        foreach ($paths as $p) {
            try {
                Storage::disk('s3')->delete($p);
            } catch (\Exception $e) {
                Log::warning('Failed to delete S3 file', ['path' => $p, 'error' => $e->getMessage()]);
            }
        }

        DB::table('claim_attachments')->where('id', $docId)->delete();

        return response()->json(['message' => 'Document deleted successfully.']);
    }

    // ─── 11b. closingDocuments ───────────────────────────────────────────────

    /**
     * GET /claims-v2/{id}/closing-documents — return the 3-slot closing docs
     * + closed_note as stored on the claims row. Mirrors the legacy blade's
     * "Closing Document Details" section so the FE can render existing values
     * even if the user opens the tab before re-uploading.
     */
    public function closingDocuments(int $claimId): JsonResponse
    {
        $table = $this->claimsTable();
        $cols  = \Schema::getColumnListing($table);
        $claim = DB::table($table)->where('id', $claimId)->first();
        if (!$claim) {
            return response()->json(['message' => 'Claim not found.'], 404);
        }

        $get = fn(string $c) => in_array($c, $cols, true) ? ($claim->{$c} ?? null) : null;

        return response()->json([
            'data' => [
                'document1'    => $get('document_1'),
                'document1Url' => $this->cdnUrl($get('document_1')),
                'document2'    => $get('document_2'),
                'document2Url' => $this->cdnUrl($get('document_2')),
                'document3'    => $get('document_3'),
                'document3Url' => $this->cdnUrl($get('document_3')),
                'closedNote'   => $get('closed_note'),
            ],
        ]);
    }

    // ─── 11c. uploadClosingDocuments ─────────────────────────────────────────

    /**
     * POST /claims-v2/{id}/closing-documents — upload the 3 fixed closing
     * documents + closing note. Mirrors the legacy admin blade's
     * `closeClaimStore` endpoint (graphiteBWV8 ClaimsController:5015) but
     * doesn't flip status to Closed — status transitions go through
     * /claims-v2/{id}/status which has its own transition validation.
     *
     * Each of document_1/2/3 is optional and replaces only the slot that's
     * sent, so the FE can save partial uploads (e.g. "I have Document 1
     * ready, will add 2/3 later"). closed_note is also optional and
     * replaces whatever's on the row.
     */
    public function uploadClosingDocuments(Request $request, int $claimId): JsonResponse
    {
        // 40 MB per file (see uploadDocument) — under the 50 MB server ceiling.
        // Manual validation so an over-limit file returns a clear message
        // instead of validate()'s generic "The given data was invalid."
        $validator = Validator::make($request->all(), [
            'document_1'  => 'nullable|file|max:40960',
            'document_2'  => 'nullable|file|max:40960',
            'document_3'  => 'nullable|file|max:40960',
            'closed_note' => 'nullable|string|max:5000',
        ], [
            'document_1.max' => 'The file is too large. The maximum allowed size is 40 MB.',
            'document_2.max' => 'The file is too large. The maximum allowed size is 40 MB.',
            'document_3.max' => 'The file is too large. The maximum allowed size is 40 MB.',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $table = $this->claimsTable();
        $cols  = \Schema::getColumnListing($table);
        $claim = DB::table($table)->where('id', $claimId)->first();
        if (!$claim) {
            return response()->json(['message' => 'Claim not found.'], 404);
        }

        $update = ['updated_at' => Carbon::now()];

        foreach (['document_1', 'document_2', 'document_3'] as $slot) {
            if (!$request->hasFile($slot)) continue;
            if (!in_array($slot, $cols, true)) continue;

            // Best-effort: clean up the previous file in S3 before
            // overwriting the path. Failure is non-fatal — the new path
            // overwrites the column either way.
            $prev = $claim->{$slot} ?? null;
            if (!empty($prev)) {
                try { Storage::disk('s3')->delete($prev); }
                catch (\Throwable $e) { Log::warning("Failed to delete previous closing doc {$slot}", ['path' => $prev, 'error' => $e->getMessage()]); }
            }

            $update[$slot] = $this->storeWithOriginalName($request->file($slot), "MIS/{$claimId}/Closing/{$slot}");
        }

        if ($request->has('closed_note') && in_array('closed_note', $cols, true)) {
            $update['closed_note'] = $request->input('closed_note');
        }

        // Nothing actually sent (just the timestamp) — surface an error so
        // the FE doesn't think a no-op write succeeded.
        if (count($update) <= 1) {
            return response()->json(['message' => 'No closing document or note provided.'], 422);
        }

        DB::table($table)->where('id', $claimId)->update($update);

        // Re-read so we can return CDN URLs on the freshly written paths.
        $fresh = DB::table($table)->where('id', $claimId)->first();
        $get   = fn(string $c) => in_array($c, $cols, true) ? ($fresh->{$c} ?? null) : null;

        return response()->json([
            'data' => [
                'document1'    => $get('document_1'),
                'document1Url' => $this->cdnUrl($get('document_1')),
                'document2'    => $get('document_2'),
                'document2Url' => $this->cdnUrl($get('document_2')),
                'document3'    => $get('document_3'),
                'document3Url' => $this->cdnUrl($get('document_3')),
                'closedNote'   => $get('closed_note'),
            ],
            'message' => 'Closing documents updated successfully.',
        ]);
    }

    // ─── 12. assessment ──────────────────────────────────────────────────────

    public function assessment(int $claimId): JsonResponse
    {
        $assessment = DB::table('claim_assessment')
            ->where('claim_id', $claimId)
            ->first();

        if (!$assessment) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $this->camelRow($assessment)]);
    }

    // ─── 13. storeAssessment ─────────────────────────────────────────────────

    public function storeAssessment(Request $request, int $claimId): JsonResponse
    {
        $validated = $request->validate([
            'assessor_name'     => 'nullable|string|max:255',
            'assessor_company'  => 'nullable|string|max:255',
            'assessment_date'   => 'nullable|date_format:Y-m-d',
            'assessment_notes'  => 'nullable|string|max:5000',
            'assessment_amount' => 'nullable|numeric|min:0',
            'assessment_status' => 'nullable|string|max:50',
            'report'            => 'nullable|file|max:10240',
        ]);

        $now = Carbon::now();
        $existing = DB::table('claim_assessment')->where('claim_id', $claimId)->first();

        // Handle report file upload
        $reportPath = $existing->report ?? null;
        if ($request->hasFile('report')) {
            $reportPath = $this->storeWithOriginalName($request->file('report'), "MIS/{$claimId}/Assessment");
        }

        $data = [
            'claim_id'          => $claimId,
            'assessor_name'     => $validated['assessor_name'] ?? ($existing->assessor_name ?? null),
            'assessor_company'  => $validated['assessor_company'] ?? ($existing->assessor_company ?? null),
            'assessment_date'   => $validated['assessment_date'] ?? ($existing->assessment_date ?? null),
            'assessment_notes'  => $validated['assessment_notes'] ?? ($existing->assessment_notes ?? null),
            'assessment_amount' => $validated['assessment_amount'] ?? ($existing->assessment_amount ?? null),
            'assessment_status' => $validated['assessment_status'] ?? ($existing->assessment_status ?? null),
            'report'            => $reportPath,
            'updated_at'        => $now,
        ];

        if ($existing) {
            DB::table('claim_assessment')->where('claim_id', $claimId)->update($data);
            $message = 'Assessment updated successfully.';
        } else {
            $data['created_at'] = $now;
            DB::table('claim_assessment')->insert($data);
            $message = 'Assessment created successfully.';
        }

        $this->logActivity($claimId, $this->claimsTable(), $existing ? 'Assessment updated' : 'Assessment created');

        return response()->json([
            'data'    => $this->camelRow($data),
            'message' => $message,
        ], $existing ? 200 : 201);
    }

    // ─── 14. thirdParties ────────────────────────────────────────────────────

    public function thirdParties(int $claimId): JsonResponse
    {
        $parties = DB::table('claim_accident_third_party')
            ->where('claim_id', $claimId)
            ->orderBy('id', 'asc')
            ->get();

        return response()->json(['data' => $this->camelRows($parties)]);
    }

    // ─── 15. storeThirdParty ─────────────────────────────────────────────────

    public function storeThirdParty(Request $request, int $claimId): JsonResponse
    {
        $validated = $request->validate([
            'name'              => 'nullable|string|max:255',
            'id_number'         => 'nullable|string|max:50',
            'phone'             => 'nullable|string|max:30',
            'email'             => 'nullable|string|email|max:255',
            'vehicle_reg'       => 'nullable|string|max:50',
            'vehicle_make'      => 'nullable|string|max:100',
            'vehicle_model'     => 'nullable|string|max:100',
            'insurance_company' => 'nullable|string|max:255',
            'insurance_policy'  => 'nullable|string|max:100',
            'description'       => 'nullable|string|max:2000',
        ]);

        $now = Carbon::now();

        $insertData = array_merge($validated, [
            'claim_id'   => $claimId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $id = DB::table('claim_accident_third_party')->insertGetId($insertData);

        return response()->json([
            'data'    => array_merge(['id' => $id], $insertData),
            'message' => 'Third party added successfully.',
        ], 201);
    }

    // ─── 16. deleteThirdParty ────────────────────────────────────────────────

    public function deleteThirdParty(int $claimId, int $tpId): JsonResponse
    {
        $deleted = DB::table('claim_accident_third_party')
            ->where('id', $tpId)
            ->where('claim_id', $claimId)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Third party record not found.'], 404);
        }

        return response()->json(['message' => 'Third party deleted successfully.']);
    }

    // ─── 17. quotes ──────────────────────────────────────────────────────────

    public function quotes(int $claimId): JsonResponse
    {
        $quotes = DB::table('claim_quotes')
            ->where('claim_id', $claimId)
            ->orderBy('id', 'desc')
            ->get();

        // Batch-load supplier names
        $supplierIds = $quotes->pluck('supplier_id')->filter()->unique()->values()->all();
        $suppliers = [];
        if ($supplierIds) {
            // Try suppliers table first, fallback to repair_centers
            $suppliers = DB::table('suppliers')
                ->whereIn('id', $supplierIds)
                ->select('id', 'supplierName as name')
                ->get()
                ->keyBy('id')
                ->toArray();

            if (empty($suppliers)) {
                $suppliers = DB::table('repair_centers')
                    ->whereIn('id', $supplierIds)
                    ->select('id', 'name')
                    ->get()
                    ->keyBy('id')
                    ->toArray();
            }
        }

        $data = $quotes->map(function ($q) use ($suppliers) {
            $row = $this->camelRow($q);
            $sup = $suppliers[$q->supplier_id ?? 0] ?? null;
            $row['supplierName'] = $sup ? ($sup->name ?? $sup->supplierName ?? null) : null;
            return $row;
        })->values()->all();

        return response()->json(['data' => $data]);
    }

    // ─── 18. storeQuote ──────────────────────────────────────────────────────

    public function storeQuote(Request $request, int $claimId): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'nullable|integer',
            'amount'      => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:2000',
            'status'      => 'nullable|string|max:50',
            'quote_date'  => 'nullable|date_format:Y-m-d',
            'document'    => 'nullable|file|max:10240',
        ]);

        $now = Carbon::now();

        $docPath = null;
        if ($request->hasFile('document')) {
            $docPath = $this->storeWithOriginalName($request->file('document'), "MIS/{$claimId}/Quotes");
        }

        $id = DB::table('claim_quotes')->insertGetId([
            'claim_id'    => $claimId,
            'supplier_id' => $validated['supplier_id'] ?? null,
            'amount'      => $validated['amount'] ?? null,
            'description' => $validated['description'] ?? null,
            'status'      => $validated['status'] ?? 'Pending',
            'quote_date'  => $validated['quote_date'] ?? $now->format('Y-m-d'),
            'document'    => $docPath,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        return response()->json([
            'data'    => ['id' => $id],
            'message' => 'Quote added successfully.',
        ], 201);
    }

    // ─── 19. acceptQuote ─────────────────────────────────────────────────────

    public function acceptQuote(int $claimId, int $quoteId): JsonResponse
    {
        $quote = DB::table('claim_quotes')
            ->where('id', $quoteId)
            ->where('claim_id', $claimId)
            ->first();

        if (!$quote) {
            return response()->json(['message' => 'Quote not found.'], 404);
        }

        $now = Carbon::now();

        // Mark all other quotes for this claim as Rejected, this one as Accepted
        DB::table('claim_quotes')
            ->where('claim_id', $claimId)
            ->where('id', '!=', $quoteId)
            ->update(['status' => 'Rejected', 'updated_at' => $now]);

        DB::table('claim_quotes')
            ->where('id', $quoteId)
            ->update(['status' => 'Accepted', 'updated_at' => $now]);

        $this->logActivity($claimId, $this->claimsTable(), "Quote #{$quoteId} accepted");

        return response()->json([
            'data'    => ['id' => $quoteId, 'status' => 'Accepted'],
            'message' => 'Quote accepted successfully.',
        ]);
    }

    // ─── 20. timeline ────────────────────────────────────────────────────────

    public function timeline(int $claimId): JsonResponse
    {
        $table = $this->claimsTable();

        // Activity log entries
        $activities = DB::table('activity_log')
            ->where('subject_id', $claimId)
            ->where('subject_type', 'like', '%Claim%')
            ->orderBy('created_at', 'desc')
            ->select('id', 'description', 'causer_id', 'causer_type', 'properties', 'created_at')
            ->limit(200)
            ->get()
            ->map(fn($a) => [
                'type'        => 'activity',
                'id'          => $a->id,
                'description' => $a->description,
                'causerId'    => $a->causer_id,
                'properties'  => $a->properties ? json_decode($a->properties, true) : null,
                'createdAt'   => $a->created_at,
            ]);

        // Reserve entries as timeline events
        $reserves = DB::table('claim_reserves')
            ->where('claim_id', $claimId)
            ->orderBy('created_at', 'desc')
            ->select('id', 'transaction_type', 'date', 'payee', 'description', 'created_at')
            ->get()
            ->map(fn($r) => [
                'type'            => 'reserve',
                'id'              => $r->id,
                'description'     => 'Reserve entry: ' . ($r->description ?? 'Transaction type ' . $r->transaction_type),
                'transactionType' => $r->transaction_type,
                'payee'           => $r->payee,
                'date'            => $r->date,
                'createdAt'       => $r->created_at,
            ]);

        // Merge and sort by created_at desc
        $timeline = $activities->concat($reserves)
            ->sortByDesc('createdAt')
            ->values()
            ->all();

        return response()->json(['data' => $timeline]);
    }

    // ─── 21. dashboard ───────────────────────────────────────────────────────

    public function dashboard(Request $request): JsonResponse
    {
        $table = $this->claimsTable();

        // Open claims count (anything not Closed)
        $openCount = DB::table($table)->where('status', '!=', 'Closed')->count();

        // Total reserves — exclude voided originals (=1) and reversal entries (=2)
        // so a voided payment doesn't double-count in the dashboard totals.
        $totals = DB::table('claim_reserves_coverages')
            ->where(function ($q) {
                $q->whereNull('is_payment_voided')->orWhere('is_payment_voided', 0);
            })
            ->select(
                DB::raw('SUM(COALESCE(reserve_amt, 0)) as totalReserve'),
                DB::raw('SUM(COALESCE(payment_amt, 0)) as totalPayment')
            )
            ->first();

        // Claims by status
        $byStatus = DB::table($table)
            ->groupBy('status')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->get()
            ->map(fn($r) => ['status' => $r->status, 'count' => (int) $r->count])
            ->values()->all();

        // Claims by type
        $byType = DB::table($table)
            ->groupBy('claim_type')
            ->select('claim_type as claimType', DB::raw('COUNT(*) as count'))
            ->get()
            ->map(fn($r) => ['claimType' => $r->claimType, 'count' => (int) $r->count])
            ->values()->all();

        // Top 5 highest value claims — exclude voided rows here too so the
        // leaderboard reflects net reserves, not double-counted entries.
        $topClaims = DB::table('claim_reserves_coverages')
            ->where(function ($q) {
                $q->whereNull('is_payment_voided')->orWhere('is_payment_voided', 0);
            })
            ->groupBy('claim_id')
            ->select(
                'claim_id',
                DB::raw('SUM(COALESCE(reserve_amt, 0)) as totalReserve')
            )
            ->orderByDesc('totalReserve')
            ->limit(5)
            ->get();

        $topClaimIds = $topClaims->pluck('claim_id')->all();
        $claimInfo = [];
        if ($topClaimIds) {
            $claimInfo = DB::table($table)
                ->whereIn('id', $topClaimIds)
                ->select('id', 'claim_number', 'claim_type', 'status')
                ->get()
                ->keyBy('id');
        }

        $topClaimsData = $topClaims->map(function ($r) use ($claimInfo) {
            $info = $claimInfo[$r->claim_id] ?? null;
            return [
                'claimId'      => $r->claim_id,
                'claimNumber'  => $info ? $info->claim_number : null,
                'claimType'    => $info ? $info->claim_type : null,
                'status'       => $info ? $info->status : null,
                'totalReserve' => (float) $r->totalReserve,
            ];
        })->values()->all();

        return response()->json([
            'data' => [
                'openClaimsCount' => $openCount,
                'totalReserve'    => (float) ($totals->totalReserve ?? 0),
                'totalPayment'    => (float) ($totals->totalPayment ?? 0),
                'balance'         => (float) (($totals->totalReserve ?? 0) - ($totals->totalPayment ?? 0)),
                'byStatus'        => $byStatus,
                'byType'          => $byType,
                'topClaims'       => $topClaimsData,
            ],
        ]);
    }

    /**
     * Normalise a name for FAC matching: upper-case + strip everything that is
     * not a letter or digit, so "M P Mining (Pty) Ltd" and "MP MINING PTY LTD"
     * collapse to the same key — beats the legacy tracker's exact-match variant
     * splits while still deriving FAC from the same client-name basis.
     */
    private function normFacName($name): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $name));
    }

    /**
     * FAC (facultative) client master as a normalised-name lookup set, sourced
     * from claims_config (category = 'fac_clients'). A claim is FAC when its
     * insured/client name matches this list — the SAME basis the legacy tracker
     * uses (isFACClaim), NOT a customer_type value. Empty when the list has not
     * been seeded, so FAC derivation degrades to 0 rather than 500ing.
     *
     * @return array<string,bool>
     */
    private function facClientSet(): array
    {
        static $set = null;
        if ($set !== null) { return $set; }
        $set = [];
        try {
            foreach (DB::table('claims_config')
                ->where('category', 'fac_clients')
                ->where('is_active', 1)
                ->pluck('label') as $n) {
                $k = $this->normFacName($n);
                if ($k !== '') { $set[$k] = true; }
            }
        } catch (\Throwable $e) {
            $set = [];
        }
        return $set;
    }

    /**
     * Customer IDs whose insured name matches the FAC (facultative) master —
     * ORG-AWARE. FAC clients are almost all COMPANIES, whose name comes from
     * customer_profile (Organisation) -> companies (the same source the list's
     * clientName uses), NOT customer.firstName+lastName. A customer is FAC when
     * EITHER its person name OR its company name matches the master. Scoped to
     * customers that actually appear on claims (bounded) and cached per request.
     *
     * @return array<int,bool> [customer_id => true]
     */
    private function facCustomerIds(): array
    {
        static $ids = null;
        if ($ids !== null) { return $ids; }
        $ids = [];
        $set = $this->facClientSet();
        if (empty($set)) { return $ids; }
        try {
            $custIds = DB::table($this->claimsTable())
                ->whereNotNull('customer_id')->distinct()->pluck('customer_id')->all();
            if (empty($custIds)) { return $ids; }

            // Person-name match (customer.firstName + lastName).
            foreach (DB::table('customer')->whereIn('id', $custIds)->get(['id', 'firstName', 'lastName']) as $cu) {
                $k = $this->normFacName(trim(($cu->firstName ?? '') . ' ' . ($cu->lastName ?? '')));
                if ($k !== '' && isset($set[$k])) { $ids[(int) $cu->id] = true; }
            }

            // Company-name match (Organisation profiles -> companies) — the FAC case.
            $profiles = DB::table('customer_profile')
                ->whereIn('customer_id', $custIds)
                ->where('entity_type', 'Organisation')
                ->whereNotNull('company_id')
                ->get(['customer_id', 'company_id']);
            $companyIds   = $profiles->pluck('company_id')->filter()->unique()->values()->all();
            $companyNames = $companyIds
                ? DB::table('companies')->whereIn('id', $companyIds)->pluck('name', 'id')->all()
                : [];
            foreach ($profiles as $p) {
                $k = $this->normFacName($companyNames[$p->company_id] ?? '');
                if ($k !== '' && isset($set[$k])) { $ids[(int) $p->customer_id] = true; }
            }
        } catch (\Throwable $e) {
            $ids = [];
        }
        return $ids;
    }

    /**
     * Which of the given claims are FAC — [claim_id => true]. Org-aware via
     * facCustomerIds() (person OR company name), matching the tracker's basis.
     *
     * @param  \Illuminate\Support\Collection $claims rows carrying id + customer_id
     * @return array<int,bool>
     */
    private function facByClaim($claims): array
    {
        $facCust = $this->facCustomerIds();
        if (empty($facCust)) { return []; }
        $out = [];
        foreach ($claims as $c) {
            if (!empty($c->customer_id) && isset($facCust[(int) $c->customer_id])) {
                $out[(int) $c->id] = true;
            }
        }
        return $out;
    }

    // ─── 21b. trackerDashboard (Claims Tracker Dashboard 1:1 replica feed) ────

    /**
     * GET /api/v1/claims-v2/tracker-dashboard?month=YYYY-MM
     *
     * Additive, read-only aggregate feed returning — in ONE call — everything a
     * 1:1 replica of the legacy Claims Tracker Dashboard needs.
     *
     * Design rules honoured:
     *   • Every stage on-time / delayed / days-late number comes from the shipped
     *     claims SLA engine (ClaimSlaService over claim_tracker_workflow). Nothing
     *     SLA-related is invented here.
     *   • Reserve / payment totals use the SAME claim_reserves_coverages source +
     *     voided-row exclusion as dashboard() above (whereNull|=0).
     *   • is_major = net reserve > P300,000; channel/handler/synced mirror the
     *     ClaimsController@index rules.
     *   • Anything Graphite genuinely cannot source is returned null and named in
     *     the top-level `gaps` array — never fabricated.
     *
     * @param ClaimSlaService $sla method-injected (no constructor change; keeps this additive)
     */
    public function trackerDashboard(Request $request, ClaimSlaService $sla): JsonResponse
    {
        $validated = $request->validate([
            'month' => 'nullable|date_format:Y-m',
        ]);
        $month = $validated['month'] ?? null;

        $gaps  = [];
        $table = $this->claimsTable();
        $majorThreshold = 300000;

        // Optional columns — degrade gracefully on older schemas (never 500).
        $hasReported = $this->trackerHasColumn($table, 'reported_date');
        $hasRegNo    = $this->trackerHasColumn($table, 'registered_claim');
        $hasAlloc    = $this->trackerHasColumn($table, 'claim_allocated_to');
        // Exclude soft-deleted claims so the dashboard TOTAL matches the "All
        // Claims" list it drills into — trackerList and ClaimsController::dashboard
        // already filter deleted_at. Zero impact today (no deletes) but prevents
        // the total from exceeding its own list after the first soft-delete.
        $hasDeleted  = $this->trackerHasColumn($table, 'deleted_at');

        // Per-claim "claim month" anchor = reported_date (loss reported) else
        // created_at (registered into Graphite). created_at is always present so
        // the COALESCE is never null.
        $dateParts = [];
        if ($hasReported) { $dateParts[] = "{$table}.reported_date"; }
        $dateParts[] = "{$table}.created_at";
        $dateExpr = 'COALESCE(' . implode(', ', $dateParts) . ')';

        // ── availableMonths (all claims, most-recent first) ───────────────────
        $availableMonths = DB::table($table)
            ->when($hasDeleted, fn ($q) => $q->whereNull("{$table}.deleted_at"))
            ->select(DB::raw("DISTINCT DATE_FORMAT($dateExpr, '%Y-%m') as ym"))
            ->orderBy('ym', 'desc')
            ->pluck('ym')
            ->filter()
            ->values()
            ->all();

        // ── Filtered claim set ────────────────────────────────────────────────
        $selectCols = ["{$table}.id", "{$table}.claim_number", "{$table}.claim_type", "{$table}.status", "{$table}.customer_id", "{$table}.policy_id"];
        if ($hasRegNo) { $selectCols[] = "{$table}.registered_claim"; }
        if ($hasAlloc) { $selectCols[] = "{$table}.claim_allocated_to"; }

        $claims = DB::table($table)
            ->when($hasDeleted, fn ($q) => $q->whereNull("{$table}.deleted_at"))
            ->when($month, fn ($q) => $q->whereRaw("DATE_FORMAT($dateExpr, '%Y-%m') = ?", [$month]))
            ->select($selectCols)
            ->get();

        $claimsById  = $claims->keyBy('id');
        $filteredIds = $claims->pluck('id')->all();
        $isClosed    = fn ($c) => strcasecmp((string) ($c->status ?? ''), 'Closed') === 0;

        // FAC (facultative) by client-name match over ALL month claims — not just
        // those carrying a workflow row — so the KPI matches the tracker's basis.
        // Empty master (unseeded claims_config) → no FAC, i.e. ships inert.
        $isFacByClaim = $this->facByClaim($claims);

        // ── Reserve / payment per claim (voided rows excluded, dashboard parity) ─
        $reserveByClaim = [];
        $paidByClaim    = [];
        if (!empty($filteredIds)) {
            DB::table('claim_reserves_coverages')
                ->whereIn('claim_id', $filteredIds)
                ->where(function ($q) {
                    $q->whereNull('is_payment_voided')->orWhere('is_payment_voided', 0);
                })
                ->groupBy('claim_id')
                ->select(
                    'claim_id',
                    DB::raw('SUM(COALESCE(reserve_amt, 0)) as reserve'),
                    DB::raw('SUM(COALESCE(payment_amt, 0)) as paid')
                )
                ->get()
                ->each(function ($r) use (&$reserveByClaim, &$paidByClaim) {
                    $reserveByClaim[$r->claim_id] = (float) $r->reserve;
                    $paidByClaim[$r->claim_id]    = (float) $r->paid;
                });
        }
        $totalReserve = array_sum($reserveByClaim);
        $totalPaid    = array_sum($paidByClaim);
        $majorClaims  = 0;
        foreach ($reserveByClaim as $cid => $amt) {
            if ($amt > $majorThreshold) { $majorClaims++; }
        }

        // ── Supplier-compliance availability (panel-beater / glass supplier) ──
        // REAL source when present: the accepted quote's supplier + its approved
        // flags (identical signal to ClaimsIncentiveReportController).
        $supplierComplianceAvailable = false;
        try {
            $supplierComplianceAvailable = Schema::hasTable('claim_quotes')
                && Schema::hasColumn('suppliers', 'is_approved_panel_beater')
                && Schema::hasColumn('suppliers', 'is_approved_glass_supplier');
        } catch (\Throwable $e) {
            $supplierComplianceAvailable = false;
        }

        $supplierMap = []; // claim_id => ['routed'=>bool,'pb'=>bool,'glass'=>bool]
        if ($supplierComplianceAvailable && !empty($filteredIds)) {
            DB::table('claim_quotes as cq')
                ->join('suppliers as s', 's.id', '=', 'cq.supplier_id')
                ->where('cq.status', 'Accepted')
                ->whereIn('cq.claim_id', $filteredIds)
                ->select('cq.claim_id', 's.is_approved_panel_beater', 's.is_approved_glass_supplier')
                ->get()
                ->each(function ($r) use (&$supplierMap) {
                    $supplierMap[$r->claim_id] = [
                        'routed' => true,
                        'pb'     => (int) ($r->is_approved_panel_beater ?? 0) === 1,
                        'glass'  => (int) ($r->is_approved_glass_supplier ?? 0) === 1,
                    ];
                });
        }

        // ── Tracked claims: run the SLA engine per claim (only claims with a
        //    claim_tracker_workflow row participate in SLA aggregates) ──────────
        $records  = [];
        $facCount = count($isFacByClaim);
        if (!empty($filteredIds)) {
            $workflows = ClaimTrackerWorkflow::whereIn('claim_id', $filteredIds)->get();
            foreach ($workflows as $wf) {
                $claim = $claimsById->get($wf->claim_id);
                if (!$claim) { continue; }

                $anchor = $sla->startAnchor($wf, $claim);
                $eval   = $sla->evaluate($wf, (string) ($claim->claim_type ?? ''), $anchor);
                $class  = $eval['class'];

                $statusByCol = [];
                $breachedStageCount = 0;
                $maxVar = null;
                $worstLabel = null;
                $worstDeadline = null;
                foreach ($eval['stages'] as $s) {
                    $statusByCol[$s['key']] = $s['status'];
                    if ($s['status'] === 'breached') { $breachedStageCount++; }
                    $v = (int) $s['variance_working_days'];
                    if (in_array($s['status'], ['missed', 'breached'], true)) {
                        if ($maxVar === null || $v > $maxVar) {
                            $maxVar = $v;
                            $worstLabel = $s['label'];
                            $worstDeadline = $s['due_date'];
                        }
                    }
                }
                $daysLate    = $maxVar !== null ? max(0, $maxVar) : 0;
                $anyBreached = $eval['breached'] || $daysLate > 0;

                $records[] = [
                    'claimId'        => (int) $wf->claim_id,
                    'claimNumber'    => $claim->claim_number,
                    'claimType'      => $claim->claim_type,
                    'class'          => $class,
                    'category'       => in_array($class, ['motor', 'glass', 'lock_and_key'], true) ? 'M' : 'NM',
                    'statusByCol'    => $statusByCol,
                    'wf'             => $wf,
                    'daysLate'       => $daysLate,
                    'anyBreached'    => $anyBreached,
                    'completedSla'   => (bool) $eval['completed'],
                    'breachedStages' => $breachedStageCount,
                    'worstLabel'     => $worstLabel ?: $eval['class_label'],
                    'worstDeadline'  => $worstDeadline ?: ($eval['overall_due_date'] ?? null),
                    'overallStatus'  => $eval['overall_status'],
                    'subType'        => $wf->non_motor_sub_type,
                    'isFac'          => $isFacByClaim[(int) $wf->claim_id] ?? false,
                    'isMajor'        => (float) ($reserveByClaim[$wf->claim_id] ?? 0) > $majorThreshold,
                ];
            }
        }

        // ── Handler resolution (claim_allocated_to, new_claims fallback) ──────
        $handlerByClaim = [];
        foreach ($records as $r) {
            $claim = $claimsById->get($r['claimId']);
            $aid   = $hasAlloc ? ($claim->claim_allocated_to ?? null) : null;
            if ($aid) { $handlerByClaim[$r['claimId']] = (int) $aid; }
        }
        $missingNumbers = [];
        foreach ($records as $r) {
            if (!isset($handlerByClaim[$r['claimId']]) && $r['claimNumber']) {
                $missingNumbers[] = $r['claimNumber'];
            }
        }
        if (!empty($missingNumbers)) {
            $ncAlloc = DB::table('new_claims')
                ->whereIn('claim_number', array_values(array_unique($missingNumbers)))
                ->whereNotNull('claim_allocated_to')
                ->pluck('claim_allocated_to', 'claim_number')
                ->toArray();
            foreach ($records as $r) {
                if (!isset($handlerByClaim[$r['claimId']]) && !empty($ncAlloc[$r['claimNumber']])) {
                    $handlerByClaim[$r['claimId']] = (int) $ncAlloc[$r['claimNumber']];
                }
            }
        }
        $handlerIds   = array_values(array_unique(array_filter($handlerByClaim)));
        $handlerNames = [];
        if (!empty($handlerIds)) {
            $handlerNames = DB::table('users')
                ->whereIn('id', $handlerIds)
                ->get(['id', 'firstName', 'lastName'])
                ->keyBy('id')
                ->map(fn ($u) => trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')))
                ->toArray();
        }
        $handlerName = fn ($cid) => (!empty($handlerByClaim[$cid]) && !empty($handlerNames[$handlerByClaim[$cid]]))
            ? $handlerNames[$handlerByClaim[$cid]]
            : 'N/A';

        // ── KPIs ──────────────────────────────────────────────────────────────
        $total      = $claims->count();
        $completed  = $claims->filter($isClosed)->count();
        $inProgress = $total - $completed;
        $delayed    = collect($records)->where('anyBreached', true)->count();
        $overdueStages = array_sum(array_column($records, 'breachedStages'));
        $lateRecords   = array_values(array_filter($records, fn ($r) => $r['daysLate'] > 0));
        $avgDaysLate   = !empty($lateRecords)
            ? (int) round(array_sum(array_column($lateRecords, 'daysLate')) / count($lateRecords))
            : 0;

        // ── registrationTiles ─────────────────────────────────────────────────
        $registered   = $total; // present in the claims table
        $unregistered = 0;
        if ($hasRegNo) {
            $unregistered = $claims->filter(fn ($c) => empty($c->registered_claim))->count();
        }
        $fnolOpen = 0;
        try {
            if (Schema::hasTable('claim_fnol')) {
                $fnolQ = ClaimFnol::where('status', ClaimFnol::STATUS_OPEN);
                if ($month) {
                    $fnolQ->whereRaw("DATE_FORMAT(COALESCE(loss_date, created_at), '%Y-%m') = ?", [$month]);
                }
                $fnolOpen = $fnolQ->count();
            } else {
                $gaps[] = 'registrationTiles.fnolOpen (claim_fnol table not present)';
            }
        } catch (\Throwable $e) {
            $gaps[] = 'registrationTiles.fnolOpen (claim_fnol not queryable)';
        }

        // ── slaBreaches (top 12) + overdueClaims (top 10) ─────────────────────
        usort($lateRecords, fn ($a, $b) => $b['daysLate'] <=> $a['daysLate']);
        $topIds = array_slice(array_map(fn ($r) => $r['claimId'], $lateRecords), 0, 12);

        // Client names (with org company fallback) for the late rows only.
        $lateClaimModels = collect();
        if (!empty($topIds)) {
            $lateClaimModels = \AlphaDirect\Claim::with([
                'customer:id,firstName,lastName',
                'policy:id,product_id,customer_id',
            ])->whereIn('id', $topIds)->get()->keyBy('id');
        }
        $companyProductIds = [7, 8, 16, 17, 18, 20, 22, 23, 24];
        $orgCustomerIds = [];
        foreach ($topIds as $cid) {
            $cm = $lateClaimModels->get($cid);
            $pid = $cm?->policy->product_id ?? null;
            if ($pid && in_array($pid, $companyProductIds) && $cm?->customer_id) {
                $orgCustomerIds[] = $cm->customer_id;
            }
        }
        $companyNames = [];
        if (!empty($orgCustomerIds)) {
            $profiles = DB::table('customer_profile')
                ->whereIn('customer_id', array_unique($orgCustomerIds))
                ->where('entity_type', 'Organisation')
                ->whereNotNull('company_id')
                ->get(['customer_id', 'company_id']);
            $companyIds = $profiles->pluck('company_id')->filter()->unique()->all();
            $companies  = $companyIds
                ? DB::table('companies')->whereIn('id', $companyIds)->pluck('name', 'id')->toArray()
                : [];
            foreach ($profiles as $p) {
                $companyNames[$p->customer_id] = $companies[$p->company_id] ?? null;
            }
        }
        $clientName = function ($cid) use ($lateClaimModels, $companyNames, $companyProductIds) {
            $cm  = $lateClaimModels->get($cid);
            if (!$cm) { return 'N/A'; }
            $pid = $cm->policy->product_id ?? null;
            $company = ($pid && in_array($pid, $companyProductIds)) ? ($companyNames[$cm->customer_id] ?? null) : null;
            if ($company) { return ucwords($company); }
            $name = trim(($cm->customer->firstName ?? '') . ' ' . ($cm->customer->lastName ?? ''));
            return $name !== '' ? $name : 'N/A';
        };

        $slaBreaches = [];
        foreach (array_slice($lateRecords, 0, 12) as $r) {
            $slaBreaches[] = [
                'claimId'     => $r['claimId'],
                'claimNumber' => $r['claimNumber'],
                'category'    => $r['category'],
                'clientName'  => $clientName($r['claimId']),
                'stageInfo'   => $r['worstLabel'] . ' · ' . $r['daysLate'] . 'd late',
                'daysLate'    => $r['daysLate'],
                'deadline'    => $r['worstDeadline'],
            ];
        }

        // Stage-abbreviation → workflow-column maps (SLA-scored flag per the
        // claims_sla config matrix). Unscored stages exist as columns but have no
        // SLA deadline, so their on-time/delayed cannot be computed.
        $motorStages = [
            ['abbr' => 'AA',  'col' => 'assessor_allotment_date', 'label' => 'Assessor Allotment', 'scored' => true],
            ['abbr' => 'FU',  'col' => 'file_uploaded_to_gt',     'label' => 'File Upload',         'scored' => false],
            ['abbr' => 'PA',  'col' => 'physical_assessment',     'label' => 'Physical Assessment', 'scored' => true],
            ['abbr' => 'QR',  'col' => 'quote_request_date',      'label' => 'Quote Request',       'scored' => true],
            ['abbr' => 'QF',  'col' => 'quote_finalisation',      'label' => 'Quote Finalisation',  'scored' => true],
            ['abbr' => 'AR',  'col' => 'assessment_report_date',  'label' => 'Assessment Report',   'scored' => false],
            ['abbr' => 'POG', 'col' => 'po_generation_date',      'label' => 'PO Generation',       'scored' => false],
            ['abbr' => 'POI', 'col' => 'po_issue_date',           'label' => 'PO Issue',            'scored' => true],
        ];
        $glassStages = [
            ['abbr' => 'QR',        'col' => 'quote_request_date', 'label' => 'Quote Request',      'scored' => false],
            ['abbr' => 'QF',        'col' => 'quote_finalisation', 'label' => 'Quote Finalisation', 'scored' => false],
            ['abbr' => 'POG',       'col' => 'po_generation_date', 'label' => 'PO Generation',      'scored' => false],
            ['abbr' => 'POI',       'col' => 'po_issue_date',      'label' => 'PO Issue',           'scored' => false],
            ['abbr' => 'Completed', 'col' => 'job_end_date',       'label' => 'Completed',          'scored' => true],
        ];

        // ── overdueClaims (top 10) ────────────────────────────────────────────
        $stageChips = function ($record, $stages) {
            $chips = [];
            foreach ($stages as $st) {
                if ($st['scored'] && isset($record['statusByCol'][$st['col']])) {
                    $status  = $record['statusByCol'][$st['col']];
                    $onTime  = !in_array($status, ['missed', 'breached'], true);
                } else {
                    $onTime = null; // unscored stage — no SLA deadline
                }
                $chips[] = ['abbr' => $st['abbr'], 'onTime' => $onTime];
            }
            return $chips;
        };
        $overdueClaims = [];
        foreach (array_slice($lateRecords, 0, 10) as $r) {
            $stages = $r['class'] === 'motor' ? $motorStages : $glassStages; // NM has no fixed abbr set → uses glass-style scored Completed
            if (!in_array($r['class'], ['motor', 'glass', 'lock_and_key'], true)) {
                $stages = []; // non-motor: no tracker abbreviation chip set
            }
            $overdueClaims[] = [
                'claimId'     => $r['claimId'],
                'claimNumber' => $r['claimNumber'],
                'category'    => $r['category'],
                'isMajor'     => $r['isMajor'],
                'isFac'       => $r['isFac'],
                'client'      => $clientName($r['claimId']),
                'handler'     => $handlerName($r['claimId']),
                'stage'       => $r['worstLabel'],
                'stageChips'  => $stageChips($r, $stages),
                'status'      => $r['overallStatus'],
                'daysLate'    => $r['daysLate'],
                'deadline'    => $r['worstDeadline'],
            ];
        }

        // ── typeSummary + typeTotals ─────────────────────────────────────────
        $typeAgg = [];
        foreach ($claims as $c) {
            $t = $c->claim_type ?: 'Unspecified';
            $typeAgg[$t] ??= ['claimType' => $t, 'count' => 0, 'inProgress' => 0, 'completed' => 0, 'reserve' => 0.0, 'paid' => 0.0];
            $typeAgg[$t]['count']++;
            if ($isClosed($c)) { $typeAgg[$t]['completed']++; } else { $typeAgg[$t]['inProgress']++; }
            $typeAgg[$t]['reserve'] += (float) ($reserveByClaim[$c->id] ?? 0);
            $typeAgg[$t]['paid']    += (float) ($paidByClaim[$c->id] ?? 0);
        }
        $typeSummary = array_values($typeAgg);
        usort($typeSummary, fn ($a, $b) => $b['count'] <=> $a['count']);
        $typeTotals = [
            'count'      => array_sum(array_column($typeSummary, 'count')),
            'inProgress' => array_sum(array_column($typeSummary, 'inProgress')),
            'completed'  => array_sum(array_column($typeSummary, 'completed')),
            'reserve'    => array_sum(array_column($typeSummary, 'reserve')),
            'paid'       => array_sum(array_column($typeSummary, 'paid')),
        ];

        // ── pipeline ──────────────────────────────────────────────────────────
        $buildPipeline = function (array $stages, array $subset) use (&$gaps) {
            $out = [];
            foreach ($stages as $st) {
                $count = 0; $onTime = 0; $delayed = 0; $scored = $st['scored'];
                foreach ($subset as $r) {
                    $done = !empty($r['wf']->{$st['col']});
                    if (!$done) { continue; }
                    $count++;
                    if ($scored && isset($r['statusByCol'][$st['col']])) {
                        $status = $r['statusByCol'][$st['col']];
                        if ($status === 'met')    { $onTime++; }
                        if ($status === 'missed') { $delayed++; }
                    }
                }
                $out[] = [
                    'stage'   => $st['label'],
                    'count'   => $count,
                    'onTime'  => $scored ? $onTime : null,
                    'delayed' => $scored ? $delayed : null,
                    'pct'     => ($scored && $count > 0) ? round($onTime / $count * 100, 1) : null,
                ];
            }
            return $out;
        };
        $motorSubset = array_values(array_filter($records, fn ($r) => $r['class'] === 'motor'));
        $glassSubset = array_values(array_filter($records, fn ($r) => in_array($r['class'], ['glass', 'lock_and_key'], true)));
        $nmSubset    = array_values(array_filter($records, fn ($r) => $r['class'] === 'non_motor'));

        $nmByType = [];
        foreach ($nmSubset as $r) {
            $st = $r['subType'] ?: 'Unspecified';
            $nmByType[$st] = ($nmByType[$st] ?? 0) + 1;
        }
        $nmByTypeOut = [];
        foreach ($nmByType as $st => $cnt) {
            $nmByTypeOut[] = ['subType' => $st, 'count' => $cnt];
        }
        usort($nmByTypeOut, fn ($a, $b) => $b['count'] <=> $a['count']);

        $pipeline = [
            'motor'    => $buildPipeline($motorStages, $motorSubset),
            'glass'    => $buildPipeline($glassStages, $glassSubset),
            'nonMotor' => [
                'inProgress' => collect($nmSubset)->where('completedSla', false)->count(),
                'resolved'   => collect($nmSubset)->where('completedSla', true)->count(),
                'breached'   => collect($nmSubset)->where('anyBreached', true)->count(),
                'byType'     => $nmByTypeOut,
            ],
        ];

        // ── leaderboard ───────────────────────────────────────────────────────
        $lb = [];
        foreach ($records as $r) {
            $hid = $handlerByClaim[$r['claimId']] ?? 0;
            $lb[$hid] ??= ['hid' => $hid, 'total' => 0, 'completed' => 0, 'onTime' => 0,
                           'motorRouted' => 0, 'motorApproved' => 0, 'glassRouted' => 0, 'glassApproved' => 0];
            $lb[$hid]['total']++;
            if ($r['completedSla'])  { $lb[$hid]['completed']++; }
            if (!$r['anyBreached'])  { $lb[$hid]['onTime']++; }

            $sup = $supplierMap[$r['claimId']] ?? null;
            if ($r['class'] === 'motor' && $sup) {
                $lb[$hid]['motorRouted']++;
                if ($sup['pb']) { $lb[$hid]['motorApproved']++; }
            }
            if ($r['class'] === 'glass' && $sup) {
                $lb[$hid]['glassRouted']++;
                if ($sup['glass']) { $lb[$hid]['glassApproved']++; }
            }
        }
        $leaderboard = [];
        foreach ($lb as $row) {
            $hid        = $row['hid'];
            $onTimeRate = $row['total'] > 0 ? round($row['onTime'] / $row['total'] * 100, 1) : 0.0;
            $pbPct    = ($supplierComplianceAvailable && $row['motorRouted'] > 0)
                ? round($row['motorApproved'] / $row['motorRouted'] * 100, 1) : null;
            $glassPct = ($supplierComplianceAvailable && $row['glassRouted'] > 0)
                ? round($row['glassApproved'] / $row['glassRouted'] * 100, 1) : null;
            $pbMet    = $pbPct !== null && $pbPct >= 90;
            $glassMet = $glassPct !== null && $glassPct >= 80;
            $overall  = round(0.4 * $onTimeRate + 0.3 * ($pbPct ?? 0) + 0.3 * ($glassPct ?? 0), 1);
            $leaderboard[] = [
                'handler'        => $hid ? ($handlerNames[$hid] ?? ('User #' . $hid)) : 'Unassigned',
                'total'          => $row['total'],
                'completed'      => $row['completed'],
                'onTimeRate'     => $onTimeRate,
                'panelBeaterPct' => $pbPct,
                'panelBeaterMet' => $pbMet,
                'glassPct'       => $glassPct,
                'glassMet'       => $glassMet,
                'overallScore'   => $overall,
                'insufficient'   => $row['total'] < 5, // frontend shows "—"
            ];
        }
        usort($leaderboard, fn ($a, $b) => $b['overallScore'] <=> $a['overallScore']);
        foreach ($leaderboard as $i => &$row) { $row = ['rank' => $i + 1] + $row; }
        unset($row);

        // ── gaps (fields Graphite cannot fully source) ────────────────────────
        if (!$supplierComplianceAvailable) {
            $gaps[] = 'leaderboard.panelBeaterPct / panelBeaterMet (suppliers.is_approved_panel_beater or claim_quotes unavailable)';
            $gaps[] = 'leaderboard.glassPct / glassMet (suppliers.is_approved_glass_supplier or claim_quotes unavailable)';
        }
        $gaps[] = 'pipeline.motor[FU, AR, POG].onTime/delayed/pct — these stages have no SLA deadline in the claims_sla config matrix (counts are real, scoring is null)';
        $gaps[] = 'pipeline.glass[QR, QF, POG, POI].onTime/delayed/pct — glass class has a single job_end SLA deadline; intermediate stages are unscored (counts are real, scoring is null)';

        // ── channelSplit (Broker vs Direct, month-scoped) ─────────────────────
        // Broker = the claim's policy carries a non-zero agent_id — the exact
        // rule trackerList() uses (agent_id && agent_id != 0 → Broker else
        // Direct). Sourced via the same policies leftJoin, month-scoped like the
        // rest of the dashboard. SELECT-only; policy join missing → degrade to 0.
        $datePartsC = [];
        if ($hasReported) { $datePartsC[] = 'c.reported_date'; }
        $datePartsC[] = 'c.created_at';
        $dateExprC = 'COALESCE(' . implode(', ', $datePartsC) . ')';
        // Prefer the stored claims.channel; fall back to the agent_id heuristic for
        // rows synced before channel was persisted (keeps the split aligned with
        // trackerList's channel column).
        $hasChannelD = $this->trackerHasColumn($table, 'channel');
        $brokerCount = 0;
        $directCount = 0;
        try {
            $channelRow = DB::table("{$table} as c")
                ->leftJoin('policies as p', 'p.id', '=', 'c.policy_id')
                ->when($hasDeleted, fn ($q) => $q->whereNull('c.deleted_at'))
                ->when($month, fn ($q) => $q->whereRaw("DATE_FORMAT($dateExprC, '%Y-%m') = ?", [$month]))
                ->select(
                    DB::raw($hasChannelD
                        ? "SUM(CASE WHEN c.channel = 'Broker' OR (c.channel IS NULL AND p.agent_id IS NOT NULL AND p.agent_id <> 0) THEN 1 ELSE 0 END) as broker_cnt"
                        : "SUM(CASE WHEN p.agent_id IS NOT NULL AND p.agent_id <> 0 THEN 1 ELSE 0 END) as broker_cnt"),
                    DB::raw('COUNT(*) as total_cnt')
                )
                ->first();
            $brokerCount = (int) ($channelRow->broker_cnt ?? 0);
            $directCount = max(0, (int) ($channelRow->total_cnt ?? 0) - $brokerCount);
        } catch (\Throwable $e) {
            $gaps[] = 'channelSplit (policies join unavailable → 0/0)';
        }
        $channelSplit = [
            ['channel' => 'Broker', 'count' => $brokerCount],
            ['channel' => 'Direct', 'count' => $directCount],
        ];

        // ── monthlyVolume (whole-book trend, last 12 months, NOT month-scoped) ─
        // A trend, so it deliberately ignores the dashboard's month filter.
        // Bucketed on COALESCE(reported_date, created_at) — the same anchor
        // availableMonths uses — chronological ascending, most-recent 12 buckets.
        // Months with no claims are simply absent (gaps), never invented.
        $monthlyVolume = [];
        try {
            $monthlyVolume = DB::table($table)
                ->when($hasDeleted, fn ($q) => $q->whereNull("{$table}.deleted_at"))
                ->select(
                    DB::raw("DATE_FORMAT($dateExpr, '%Y-%m') as ym"),
                    DB::raw('COUNT(*) as cnt')
                )
                ->groupBy('ym')
                ->orderBy('ym', 'desc')
                ->limit(12)
                ->get()
                ->filter(fn ($r) => !empty($r->ym))
                ->sortBy('ym')
                ->map(fn ($r) => ['month' => (string) $r->ym, 'count' => (int) $r->cnt])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            $gaps[] = 'monthlyVolume (trend unavailable → [])';
        }

        // ── syncTiles (external-ref presence, month-scoped like the KPIs) ─────
        // synced = claims WITH a non-empty external_ref; pending = without.
        // failed = 0 — no sync-attempt / failed signal exists in the schema
        // today, so it is never invented. Missing column → 0 synced, all pending.
        $hasExternalRef = $this->trackerHasColumn($table, 'external_ref');
        $syncSynced  = 0;
        $syncPending = 0;
        if ($hasExternalRef) {
            try {
                $syncRow = DB::table($table)
                    ->when($hasDeleted, fn ($q) => $q->whereNull("{$table}.deleted_at"))
                    ->when($month, fn ($q) => $q->whereRaw("DATE_FORMAT($dateExpr, '%Y-%m') = ?", [$month]))
                    ->select(
                        DB::raw("SUM(CASE WHEN external_ref IS NOT NULL AND external_ref <> '' THEN 1 ELSE 0 END) as synced"),
                        DB::raw('COUNT(*) as total')
                    )
                    ->first();
                $syncSynced  = (int) ($syncRow->synced ?? 0);
                $syncPending = max(0, (int) ($syncRow->total ?? 0) - $syncSynced);
            } catch (\Throwable $e) {
                $gaps[] = 'syncTiles (external_ref not queryable → 0 synced)';
            }
        } else {
            $syncPending = $total; // no external_ref column → nothing synced yet
            $gaps[] = 'syncTiles.synced (external_ref column not present → 0 synced)';
        }
        $syncTiles = [
            'synced'  => $syncSynced,
            'pending' => $syncPending,
            'failed'  => 0, // no failed-sync signal exists today
        ];

        return response()->json([
            'data' => [
                'month'           => $month,
                'availableMonths' => $availableMonths,
                'kpis' => [
                    'total'        => $total,
                    'inProgress'   => $inProgress,
                    'overdueStages'=> (int) $overdueStages,
                    'delayed'      => $delayed,
                    'completed'    => $completed,
                    'avgDaysLate'  => $avgDaysLate,
                    'totalReserve' => round($totalReserve, 2),
                    'totalPaid'    => round($totalPaid, 2),
                    'majorClaims'  => $majorClaims,
                    'facClaims'    => $facCount,
                    'facAvailable' => true, // FAC = insured on the fac_clients master (org-aware client-name match)
                ],
                'registrationTiles' => [
                    'registered'   => $registered,
                    'fnolOpen'     => $fnolOpen,
                    'unregistered' => $unregistered,
                ],
                'slaBreaches'   => $slaBreaches,
                'typeSummary'   => $typeSummary,
                'typeTotals'    => $typeTotals,
                'pipeline'      => $pipeline,
                'overdueClaims' => $overdueClaims,
                'leaderboard'   => $leaderboard,
                'channelSplit'  => $channelSplit,
                'monthlyVolume' => $monthlyVolume,
                'syncTiles'     => $syncTiles,
                'gaps'          => $gaps,
            ],
        ]);
    }

    // ─── 21b. trackerList ─────────────────────────────────────────────────────

    /**
     * GET /api/v1/claims-v2/tracker-list
     *
     * A 1:1 replica feed of the legacy Claims Tracker "All Claims" list:
     * paginated rows carrying the same identity/channel/handler/reserve signals
     * as ClaimsController::index, PLUS the per-stage SLA chips + overall status +
     * days-late derived the same way trackerDashboard() derives them (via
     * ClaimSlaService::startAnchor + ::evaluate over a claim_tracker_workflow row).
     *
     * ADDITIVE + READ-ONLY. Touches no live claim/financial tables; writes
     * nothing. The existing /claims (ClaimsController::index) and /claims-v2
     * (ClaimsV2Controller::index) list methods are left completely untouched.
     *
     * PERFORMANCE: in the common case (no SLA-derived filter/sort — the default
     * reportedDate-desc landing) the SQL filters + LIMIT/OFFSET run first and the
     * SLA engine is evaluated for the CURRENT PAGE ONLY. When a filter/sort that
     * depends on a derived field is requested (status / stage / decision, or a
     * sort on overallStatus / currentStage / daysLate / reserve / clientName /
     * handler) the SQL-filtered candidate set is enriched + evaluated, then
     * filtered / sorted / paginated in PHP — the only correct way to page a
     * derived column. Either way only claims with a workflow row get SLA numbers;
     * the rest fall back to claims.status (never invented).
     */
    public function trackerList(Request $request, ClaimSlaService $sla): JsonResponse
    {
        $validated = $request->validate([
            'search'     => 'nullable|string|max:100',
            'status'     => 'nullable|string|max:60',
            'decision'   => 'nullable|in:pending,approved,repudiated,reversed',
            'sync'       => 'nullable|in:synced,pending,failed',
            'channel'    => 'nullable|in:Broker,Direct',
            'claim_type' => 'nullable|string|max:120',
            'stage'      => 'nullable|string|max:120',
            'month'      => 'nullable|date_format:Y-m',
            'sort'       => 'nullable|in:claimNumber,reportedDate,overallStatus,currentStage,daysLate,reserve,claimType,clientName,handler',
            'direction'  => 'nullable|in:asc,desc',
            'page'       => 'nullable|integer|min:1',
            'per_page'   => 'nullable|integer|in:15,25,50',
        ]);

        $gaps  = [];
        $table = $this->claimsTable();
        $majorThreshold = 300000;

        $perPage = (int) ($validated['per_page'] ?? 15);
        $page    = (int) ($validated['page'] ?? 1);
        $sortKey = $validated['sort'] ?? 'reportedDate';
        $dir     = $validated['direction'] ?? 'desc';

        // Optional columns — degrade gracefully on older schemas (never 500).
        $hasReported    = $this->trackerHasColumn($table, 'reported_date');
        $hasPlate       = $this->trackerHasColumn($table, 'vehicle_plate');
        $hasExternalRef = $this->trackerHasColumn($table, 'external_ref');
        $hasSource      = $this->trackerHasColumn($table, 'source');
        $hasAlloc       = $this->trackerHasColumn($table, 'claim_allocated_to');
        $hasRegNo       = $this->trackerHasColumn($table, 'registered_claim');
        $hasDeletedAt   = $this->trackerHasColumn($table, 'deleted_at');
        $hasChannel     = $this->trackerHasColumn($table, 'channel');

        // "Claim month" anchor = reported_date (loss reported) else created_at.
        $dateParts = [];
        if ($hasReported) { $dateParts[] = "c.reported_date"; }
        $dateParts[] = "c.created_at";
        $dateExpr = 'COALESCE(' . implode(', ', $dateParts) . ')';

        // ── Comment-status → tone map (Claims Tracker vocabulary) ──────────────
        $subReasonStatus = (string) config('claims_comment_status.sub_reason_status', 'Awaiting');
        $toneMap = [
            'Repudiated'           => 'danger',
            'Recovery & Legal'     => 'danger',
            'System Issue'         => 'danger',
            'Awaiting'             => 'warning',
            'Outstanding Premiums' => 'warning',
            'Management Review'    => 'warning',
            'Claim Below Excess'   => 'warning',
            'Claim Closed'         => 'success',
            'Claim Withdrawn'      => 'success',
            'File with Accounts'   => 'navy',
        ];

        // ── Stage-abbreviation chip sets (mirror trackerDashboard) ─────────────
        // Motor: AA,FU,PA,QR,QF,AR,POG,POI · Glass/Lock&Key: QR,QF,POG,POI.
        $motorChipStages = [
            ['abbr' => 'AA',  'col' => 'assessor_allotment_date'],
            ['abbr' => 'FU',  'col' => 'file_uploaded_to_gt'],
            ['abbr' => 'PA',  'col' => 'physical_assessment'],
            ['abbr' => 'QR',  'col' => 'quote_request_date'],
            ['abbr' => 'QF',  'col' => 'quote_finalisation'],
            ['abbr' => 'AR',  'col' => 'assessment_report_date'],
            ['abbr' => 'POG', 'col' => 'po_generation_date'],
            ['abbr' => 'POI', 'col' => 'po_issue_date'],
        ];
        $glassChipStages = [
            ['abbr' => 'QR',  'col' => 'quote_request_date'],
            ['abbr' => 'QF',  'col' => 'quote_finalisation'],
            ['abbr' => 'POG', 'col' => 'po_generation_date'],
            ['abbr' => 'POI', 'col' => 'po_issue_date'],
        ];
        $chipsFor = function (string $class, array $statusByCol) use ($motorChipStages, $glassChipStages) {
            if ($class === 'motor') {
                $set = $motorChipStages;
            } elseif (in_array($class, ['glass', 'lock_and_key'], true)) {
                $set = $glassChipStages;
            } else {
                return []; // non-motor: no fixed tracker abbreviation set
            }
            $out = [];
            foreach ($set as $st) {
                // Only SLA-SCORED stages appear in statusByCol; unscored/unreached
                // stages get onTime=null (mirrors trackerDashboard's stageChips).
                $onTime = array_key_exists($st['col'], $statusByCol)
                    ? !in_array($statusByCol[$st['col']], ['missed', 'breached'], true)
                    : null;
                $out[] = ['abbr' => $st['abbr'], 'onTime' => $onTime];
            }
            return $out;
        };

        // ── Per-claim SLA derivation (same rules as trackerDashboard) ──────────
        // Org-aware FAC set (customer_id => true) — same basis as the dashboard.
        $facCust = $this->facCustomerIds();
        $evalLite = function ($wf, $baseRow) use ($sla, $facCust) {
            $claimType = (string) ($baseRow->claim_type ?? '');
            $class     = $sla->resolveClass($claimType);
            $category  = in_array($class, ['motor', 'glass', 'lock_and_key'], true) ? 'M' : 'NM';

            // No workflow row → no SLA numbers (never invented). currentStage +
            // overallStatus fall back to the coarse claims.status.
            if (!$wf) {
                $status = trim((string) ($baseRow->status ?? ''));
                return [
                    'class'         => $class,
                    'category'      => $category,
                    'currentStage'  => $status !== '' ? $status : 'N/A',
                    'overallStatus' => $status !== '' ? $status : 'Unknown',
                    'daysLate'      => 0,
                    'statusByCol'   => [],
                    'isFac'         => isset($facCust[(int) ($baseRow->customer_id ?? 0)]),
                    'subType'       => null,
                    'hasWf'         => false,
                ];
            }

            $anchor = $sla->startAnchor($wf, $baseRow);
            $eval   = $sla->evaluate($wf, $claimType, $anchor);

            $statusByCol = [];
            $maxVar = null;
            $anyStageBreach = false;
            foreach ($eval['stages'] as $s) {
                $statusByCol[$s['key']] = $s['status'];
                if (in_array($s['status'], ['missed', 'breached'], true)) {
                    $anyStageBreach = true;
                    $v = (int) $s['variance_working_days'];
                    if ($maxVar === null || $v > $maxVar) { $maxVar = $v; }
                }
            }
            $daysLate = $maxVar !== null ? max(0, $maxVar) : 0;

            // currentStage = first stage not yet completed (the one awaited);
            // 'Completed' when every stage carries a completion date.
            $currentStage = 'Completed';
            foreach ($eval['stages'] as $s) {
                if (empty($s['completed_date'])) { $currentStage = $s['label']; break; }
            }
            if (empty($eval['stages'])) { $currentStage = $eval['class_label']; }

            // overallStatus in the tracker vocabulary.
            $overallRaw = $eval['overall_status'];
            if ($eval['completed']) {
                $base = $overallRaw === 'missed' ? 'Complete - Late' : 'Complete - On Time';
            } elseif ($overallRaw === 'breached') {
                $base = 'Overdue';               // open + past the overall due date
            } elseif ($anyStageBreach) {
                $base = 'Delayed';               // an intermediate stage slipped
            } else {
                $base = 'In Progress';
            }
            $overall = $category === 'NM' ? 'NM: ' . $base : $base;

            return [
                'class'         => $class,
                'category'      => $category,
                'currentStage'  => $currentStage,
                'overallStatus' => $overall,
                'daysLate'      => $daysLate,
                'statusByCol'   => $statusByCol,
                'isFac'         => isset($facCust[(int) ($baseRow->customer_id ?? 0)]),
                'subType'       => $wf->non_motor_sub_type,
                'hasWf'         => true,
            ];
        };

        // ── Handler-name search ids (same as ClaimsController::index) ──────────
        $handlerSearchIds = [];
        if (!empty($validated['search'])) {
            $st = trim(preg_replace('/\s+/', ' ', $validated['search']));
            $handlerSearchIds = DB::table('users')
                ->where(function ($u) use ($st) {
                    $u->whereRaw("CONCAT_WS(' ', TRIM(firstName), TRIM(lastName)) LIKE ?", ["%{$st}%"])
                      ->orWhere('firstName', 'like', "%{$st}%")
                      ->orWhere('lastName', 'like', "%{$st}%");
                })
                ->pluck('id')->all();
        }

        // ── Base query builder (all SQL-doable filters) ────────────────────────
        $applyFilters = function ($q) use (
            $table, $validated, $request, $hasExternalRef, $hasAlloc, $hasReported, $hasPlate,
            $handlerSearchIds, $majorThreshold, $hasDeletedAt, $hasChannel
        ) {
            // Hide soft-deleted claims (the "Del" action is a reversible
            // soft-delete — it stamps claims.deleted_at, never destroys the row).
            if ($hasDeletedAt) {
                $q->whereNull('c.deleted_at');
            }

            // Channel — prefer the stored claims.channel; fall back to the agent_id
            // heuristic for rows synced before channel was persisted.
            if (!empty($validated['channel'])) {
                $wantBroker = $validated['channel'] === 'Broker';
                if ($hasChannel) {
                    $q->where(function ($x) use ($wantBroker) {
                        if ($wantBroker) {
                            $x->where('c.channel', 'Broker')
                              ->orWhere(fn ($y) => $y->whereNull('c.channel')
                                  ->whereNotNull('p.agent_id')->where('p.agent_id', '!=', 0));
                        } else {
                            $x->where('c.channel', 'Direct')
                              ->orWhere(fn ($y) => $y->whereNull('c.channel')
                                  ->where(fn ($z) => $z->whereNull('p.agent_id')->orWhere('p.agent_id', 0)));
                        }
                    });
                } elseif ($wantBroker) {
                    $q->whereNotNull('p.agent_id')->where('p.agent_id', '!=', 0);
                } else {
                    $q->where(fn ($x) => $x->whereNull('p.agent_id')->orWhere('p.agent_id', 0));
                }
            }

            // Claim type — plain type OR the NMSUB:<subtype> non-motor sub-type form.
            if (!empty($validated['claim_type'])) {
                $ct = $validated['claim_type'];
                if (str_starts_with($ct, 'NMSUB:')) {
                    $sub = trim(substr($ct, 6));
                    $q->whereIn('c.id', function ($sub2) use ($sub) {
                        $sub2->select('claim_id')->from('claim_tracker_workflow')
                             ->where('non_motor_sub_type', $sub);
                    });
                } else {
                    $q->where('c.claim_type', $ct);
                }
            }

            // Month — reported/created month (reported_date preferred when present).
            if (!empty($validated['month'])) {
                [$y, $mo] = explode('-', $validated['month']);
                if ($hasReported) {
                    $q->where(function ($x) use ($y, $mo) {
                        $x->where(function ($a) use ($y, $mo) {
                            $a->whereYear('c.reported_date', (int) $y)->whereMonth('c.reported_date', (int) $mo);
                        })->orWhere(function ($z) use ($y, $mo) {
                            $z->whereNull('c.reported_date')
                              ->whereYear('c.created_at', (int) $y)->whereMonth('c.created_at', (int) $mo);
                        });
                    });
                } else {
                    $q->whereYear('c.created_at', (int) $y)->whereMonth('c.created_at', (int) $mo);
                }
            }

            // Sync — external_ref present = synced; not-synced = pending (no
            // attempt counter exists, so 'failed' can never match — see gaps).
            if (!empty($validated['sync']) && $hasExternalRef) {
                if ($validated['sync'] === 'synced') {
                    $q->whereNotNull('c.external_ref');
                } elseif ($validated['sync'] === 'pending') {
                    $q->whereNull('c.external_ref');
                } else { // failed
                    $q->whereRaw('1 = 0');
                }
            }

            // MAJOR / FAC come in via the status special values.
            $statusSpecial = $validated['status'] ?? null;
            if ($statusSpecial === 'MAJOR') {
                $q->whereIn('c.id', function ($sub) use ($majorThreshold) {
                    $sub->select('claim_id')->from('claim_reserves_coverages')
                        ->whereNotIn('is_payment_voided', [1, 2])
                        ->groupBy('claim_id')
                        ->havingRaw('SUM(reserve_amt) > ?', [$majorThreshold]);
                });
            } elseif ($statusSpecial === 'FAC') {
                // FAC = the claim's insured is on the fac_clients master (org-aware:
                // person OR company name) — same basis as the dashboard KPI, not a
                // customer_type value. Empty master → no FAC claims.
                $facCustIds = array_keys($this->facCustomerIds());
                if (empty($facCustIds)) {
                    $q->whereRaw('1 = 0');
                } else {
                    $q->whereIn('c.customer_id', $facCustIds);
                }
            }

            // Search — claim#, customer name, policy#, plate, handler.
            if (!empty($validated['search'])) {
                $search = trim(preg_replace('/\s+/', ' ', $validated['search']));
                $normalizedPlate = str_replace(' ', '', $search);
                $words = count(explode(' ', $search)) >= 2
                    ? array_values(array_filter(explode(' ', $search)))
                    : [];
                $q->where(function ($w) use ($search, $normalizedPlate, $words, $handlerSearchIds, $hasAlloc, $hasPlate) {
                    $w->where('c.claim_number', 'like', "%{$search}%")
                      ->orWhereExists(function ($sub) use ($search, $words) {
                          $sub->select(DB::raw(1))->from('customer')
                              ->whereColumn('customer.id', 'c.customer_id')
                              ->where(function ($inner) use ($search, $words) {
                                  $inner->where('customer.firstName', 'like', "%{$search}%")
                                        ->orWhere('customer.lastName', 'like', "%{$search}%")
                                        ->orWhereRaw("CONCAT_WS(' ', TRIM(customer.firstName), TRIM(customer.lastName)) LIKE ?", ["%{$search}%"]);
                                  if (count($words) >= 2) {
                                      $inner->orWhere(fn ($i) => $i->where('customer.firstName', 'like', "%{$words[0]}%")->where('customer.lastName', 'like', "%{$words[1]}%"))
                                            ->orWhere(fn ($i) => $i->where('customer.firstName', 'like', "%{$words[1]}%")->where('customer.lastName', 'like', "%{$words[0]}%"));
                                  }
                              });
                      })
                      ->orWhere('p.policyNumber', 'like', "%{$search}%");
                    if ($hasPlate) {
                        $w->orWhere('c.vehicle_plate', 'like', "%{$search}%")
                          ->orWhere('c.vehicle_plate', 'like', "%{$normalizedPlate}%");
                    }
                    if (!empty($handlerSearchIds)) {
                        if ($hasAlloc) {
                            $w->orWhereIn('c.claim_allocated_to', $handlerSearchIds);
                        }
                        $w->orWhereIn('c.claim_number', function ($sub) use ($handlerSearchIds) {
                            $sub->select('claim_number')->from('new_claims')
                                ->whereIn('claim_allocated_to', $handlerSearchIds);
                        });
                    }
                });
            }

            return $q;
        };

        $selectCols = [
            'c.id', 'c.claim_number', 'c.claim_type', 'c.status', 'c.customer_id', 'c.policy_id', 'c.created_at',
            'p.agent_id as agent_id', 'p.product_id as product_id', 'p.policyNumber as policy_number',
        ];
        if ($hasReported)    { $selectCols[] = 'c.reported_date'; }
        if ($hasPlate)       { $selectCols[] = 'c.vehicle_plate'; }
        if ($hasExternalRef) { $selectCols[] = 'c.external_ref'; }
        if ($hasSource)      { $selectCols[] = 'c.source'; }
        if ($hasAlloc)       { $selectCols[] = 'c.claim_allocated_to'; }
        if ($hasRegNo)       { $selectCols[] = 'c.registered_claim'; }
        if ($hasChannel)     { $selectCols[] = 'c.channel'; }

        $newBase = fn () => $applyFilters(
            DB::table("{$table} as c")
                ->leftJoin('policies as p', 'p.id', '=', 'c.policy_id')
                ->select($selectCols)
        );

        // ── availableMonths + filterOptions (cheap, whole-book) ────────────────
        $availableMonths = DB::table("{$table} as c")
            ->select(DB::raw("DISTINCT DATE_FORMAT($dateExpr, '%Y-%m') as ym"))
            ->orderBy('ym', 'desc')->pluck('ym')->filter()->values()->all();

        $claimTypeOptions = DB::table("{$table} as c")
            ->select('c.claim_type')->distinct()->whereNotNull('c.claim_type')
            ->where('c.claim_type', '!=', '')->orderBy('c.claim_type')
            ->pluck('claim_type')->values()->all();

        $nonMotorSubTypes = [];
        try {
            $nonMotorSubTypes = DB::table('claim_tracker_workflow')
                ->select('non_motor_sub_type')->distinct()
                ->whereNotNull('non_motor_sub_type')->where('non_motor_sub_type', '!=', '')
                ->orderBy('non_motor_sub_type')->pluck('non_motor_sub_type')->values()->all();
        } catch (\Throwable $e) {
            $gaps[] = 'filterOptions.nonMotorSubTypes (claim_tracker_workflow not queryable)';
        }

        $stageOptions = ['Completed'];
        foreach ((array) config('claims_sla.matrix', []) as $def) {
            foreach ((array) ($def['stages'] ?? []) as $st) {
                if (!empty($st['label'])) { $stageOptions[] = $st['label']; }
            }
        }
        $stageOptions = array_values(array_unique($stageOptions));

        // ── Enrichment-map builders (shared by both paths) ─────────────────────
        $reserveMap = function (array $ids) {
            $out = [];
            if (empty($ids)) { return $out; }
            DB::table('claim_reserves_coverages')
                ->whereIn('claim_id', $ids)
                ->whereNotIn('is_payment_voided', [1, 2])
                ->groupBy('claim_id')
                ->selectRaw('claim_id, SUM(reserve_amt) as total')
                ->get()->each(function ($r) use (&$out) { $out[$r->claim_id] = (float) $r->total; });
            return $out;
        };
        $decisionMap = function (array $ids) use (&$gaps) {
            $out = [];
            if (empty($ids)) { return $out; }
            try {
                // Latest row wins (id desc): active (reversed_at null) surfaces its
                // decision_status; an all-reversed latest → 'reversed'.
                foreach (
                    DB::table('claim_decisions')->whereIn('claim_id', $ids)
                        ->orderByDesc('id')->get(['claim_id', 'decision_status', 'reversed_at']) as $d
                ) {
                    if (array_key_exists($d->claim_id, $out)) { continue; }
                    $out[$d->claim_id] = $d->reversed_at === null ? $d->decision_status : 'reversed';
                }
            } catch (\Throwable $e) {
                $gaps[] = 'decision (claim_decisions not queryable)';
            }
            return $out;
        };
        $workflowMap = function (array $ids) {
            if (empty($ids)) { return collect(); }
            return ClaimTrackerWorkflow::whereIn('claim_id', $ids)->get()->keyBy('claim_id');
        };
        $commentMap = function (array $ids) use ($toneMap, $subReasonStatus, &$gaps) {
            $out = [];
            if (empty($ids)) { return $out; }
            try {
                DB::table('claim_comment_statuses')->whereIn('claim_id', $ids)->get()
                    ->each(function ($r) use (&$out, $toneMap, $subReasonStatus) {
                        $status = $r->comment_status ?? null;
                        if (empty($status)) { $out[$r->claim_id] = null; return; }
                        $text = $status;
                        if ($status === $subReasonStatus && !empty($r->comment_sub_reason)) {
                            $text = $status . ': ' . $r->comment_sub_reason;
                        }
                        $out[$r->claim_id] = ['text' => $text, 'tone' => $toneMap[$status] ?? 'navy'];
                    });
            } catch (\Throwable $e) {
                $gaps[] = 'comment (claim_comment_statuses not queryable)';
            }
            return $out;
        };
        // Handler names (claim_allocated_to → users, new_claims fallback).
        $handlerMap = function ($rows) use ($hasAlloc) {
            $byClaim = [];
            if ($hasAlloc) {
                foreach ($rows as $r) {
                    if (!empty($r->claim_allocated_to)) { $byClaim[$r->id] = (int) $r->claim_allocated_to; }
                }
            }
            $missing = [];
            foreach ($rows as $r) {
                if (!isset($byClaim[$r->id]) && !empty($r->claim_number)) { $missing[] = $r->claim_number; }
            }
            if (!empty($missing)) {
                $nc = DB::table('new_claims')
                    ->whereIn('claim_number', array_values(array_unique($missing)))
                    ->whereNotNull('claim_allocated_to')
                    ->pluck('claim_allocated_to', 'claim_number')->all();
                foreach ($rows as $r) {
                    if (!isset($byClaim[$r->id]) && !empty($nc[$r->claim_number])) {
                        $byClaim[$r->id] = (int) $nc[$r->claim_number];
                    }
                }
            }
            $hids  = array_values(array_unique(array_filter($byClaim)));
            $names = $hids
                ? DB::table('users')->whereIn('id', $hids)->get(['id', 'firstName', 'lastName'])
                    ->keyBy('id')->map(fn ($u) => trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')))->all()
                : [];
            return [$byClaim, $names];
        };
        // Client names with DOM/COM organisation-company fallback (same as index).
        $companyProductIds = [7, 8, 16, 17, 18, 19, 20, 22, 23, 24];
        $clientMap = function ($rows) use ($companyProductIds) {
            $custIds = collect($rows)->pluck('customer_id')->filter()->unique()->values()->all();
            $customers = $custIds
                ? DB::table('customer')->whereIn('id', $custIds)->get(['id', 'firstName', 'lastName'])->keyBy('id')
                : collect();
            $companyNames = [];
            if ($custIds) {
                $profiles = DB::table('customer_profile')
                    ->whereIn('customer_id', $custIds)->where('entity_type', 'Organisation')
                    ->whereNotNull('company_id')->get(['customer_id', 'company_id']);
                $companyIds = $profiles->pluck('company_id')->filter()->unique()->values()->all();
                $companies  = $companyIds
                    ? DB::table('companies')->whereIn('id', $companyIds)->pluck('name', 'id')->all()
                    : [];
                foreach ($profiles as $p) { $companyNames[$p->customer_id] = $companies[$p->company_id] ?? null; }
            }
            return [$customers, $companyNames];
        };

        // ── Final row assembler ────────────────────────────────────────────────
        $buildRow = function ($row, array $lite, float $reserve, $decision, $comment, string $handlerName, string $clientName)
            use ($chipsFor, $hasPlate, $hasExternalRef, $hasReported, $majorThreshold, $hasChannel) {
            $reportedRaw = ($hasReported && !empty($row->reported_date)) ? $row->reported_date : $row->created_at;
            $reportedDate = null;
            if (!empty($reportedRaw)) {
                try { $reportedDate = Carbon::parse($reportedRaw)->toDateString(); } catch (\Throwable $e) { $reportedDate = null; }
            }
            $agentId = $row->agent_id ?? null;
            return [
                'claimId'         => (int) $row->id,
                'claimNumber'     => $row->claim_number,
                'clientName'      => $clientName,
                'channel'         => ($hasChannel && !empty($row->channel))
                    ? $row->channel
                    : (($agentId && (int) $agentId !== 0) ? 'Broker' : 'Direct'),
                'handler'         => $handlerName,
                'reportedDate'    => $reportedDate,
                'claimType'       => $row->claim_type,
                'nonMotorSubType' => $lite['subType'] ?? null,
                'plate'           => $hasPlate ? ($row->vehicle_plate ?? null) : null,
                'currentStage'    => $lite['currentStage'],
                'stageChips'      => $chipsFor($lite['class'], $lite['statusByCol']),
                'overallStatus'   => $lite['overallStatus'],
                'category'        => $lite['category'],
                'daysLate'        => (int) $lite['daysLate'],
                'reserve'         => round($reserve, 2),
                'comment'         => $comment,
                'decision'        => $decision,
                'isMajor'         => $reserve > $majorThreshold,
                'isFac'           => (bool) $lite['isFac'],
                'synced'          => $hasExternalRef ? !empty($row->external_ref) : false,
                'syncFailed'      => false,
            ];
        };

        // Does this request need derived-field filtering/sorting (→ full path)?
        $slaSortKeys = ['overallStatus', 'currentStage', 'daysLate', 'reserve', 'clientName', 'handler'];
        $statusPlain = ($validated['status'] ?? null);
        $statusIsDerived = $statusPlain !== null && !in_array($statusPlain, ['MAJOR', 'FAC'], true);
        $needsFull = $statusIsDerived
            || !empty($validated['stage'])
            || !empty($validated['decision'])
            || in_array($sortKey, $slaSortKeys, true);

        if (!$needsFull) {
            // ── SIMPLE PATH — SQL filter + sort + paginate, SLA for the page only ─
            $total = (clone $newBase())->count('c.id');

            $orderExpr = match ($sortKey) {
                'claimNumber' => 'c.claim_number',
                'claimType'   => 'c.claim_type',
                default       => null, // reportedDate
            };
            $q = $newBase();
            if ($orderExpr) {
                $q->orderBy($orderExpr, $dir);
            } else {
                // Newest-REGISTERED first: order by creation, not the loss/reported
                // date, so a freshly logged claim tops the list (claims-team ask).
                $q->orderBy('c.created_at', $dir);
            }
            $rows = $q->orderBy('c.id', 'desc')
                ->offset(($page - 1) * $perPage)->limit($perPage)->get();

            $ids       = $rows->pluck('id')->all();
            $reserves  = $reserveMap($ids);
            $decisions = $decisionMap($ids);
            $comments  = $commentMap($ids);
            $workflows = $workflowMap($ids);
            [$handlerByClaim, $handlerNames] = $handlerMap($rows);
            [$customers, $companyNames]      = $clientMap($rows);

            $data = [];
            foreach ($rows as $row) {
                $lite = $evalLite($workflows->get($row->id), $row);
                $reserve = (float) ($reserves[$row->id] ?? 0);
                $handlerName = (!empty($handlerByClaim[$row->id]) && !empty($handlerNames[$handlerByClaim[$row->id]]))
                    ? $handlerNames[$handlerByClaim[$row->id]] : 'N/A';
                $pid = $row->product_id ?? null;
                $company = ($pid && in_array($pid, $companyProductIds)) ? ($companyNames[$row->customer_id] ?? null) : null;
                if ($company) {
                    $clientName = ucwords($company);
                } else {
                    $cust = $customers->get($row->customer_id ?? null);
                    $nm = $cust ? trim(($cust->firstName ?? '') . ' ' . ($cust->lastName ?? '')) : '';
                    $clientName = $nm !== '' ? $nm : 'N/A';
                }
                $data[] = $buildRow($row, $lite, $reserve, $decisions[$row->id] ?? null, $comments[$row->id] ?? null, $handlerName, $clientName);
            }
        } else {
            // ── FULL PATH — enrich the SQL-filtered candidates, then filter /
            //    sort / paginate on the derived fields in PHP ──────────────────
            $allRows   = $newBase()->get();
            $ids       = $allRows->pluck('id')->all();
            $reserves  = $reserveMap($ids);
            $decisions = $decisionMap($ids);
            $workflows = $workflowMap($ids);
            [$handlerByClaim, $handlerNames] = $handlerMap($allRows);
            [$customers, $companyNames]      = $clientMap($allRows);

            $clientNameOf = function ($row) use ($customers, $companyNames, $companyProductIds) {
                $pid = $row->product_id ?? null;
                $company = ($pid && in_array($pid, $companyProductIds)) ? ($companyNames[$row->customer_id] ?? null) : null;
                if ($company) { return ucwords($company); }
                $cust = $customers->get($row->customer_id ?? null);
                $nm = $cust ? trim(($cust->firstName ?? '') . ' ' . ($cust->lastName ?? '')) : '';
                return $nm !== '' ? $nm : 'N/A';
            };

            $records = [];
            foreach ($allRows as $row) {
                $lite    = $evalLite($workflows->get($row->id), $row);
                $reserve = (float) ($reserves[$row->id] ?? 0);
                $handlerName = (!empty($handlerByClaim[$row->id]) && !empty($handlerNames[$handlerByClaim[$row->id]]))
                    ? $handlerNames[$handlerByClaim[$row->id]] : 'N/A';
                $reportedRaw = ($hasReported && !empty($row->reported_date)) ? $row->reported_date : $row->created_at;
                $reportedTs = 0;
                if (!empty($reportedRaw)) {
                    try { $reportedTs = Carbon::parse($reportedRaw)->timestamp; } catch (\Throwable $e) { $reportedTs = 0; }
                }
                $createdTs = 0;
                if (!empty($row->created_at)) {
                    try { $createdTs = Carbon::parse($row->created_at)->timestamp; } catch (\Throwable $e) { $createdTs = 0; }
                }
                $records[] = [
                    'row'         => $row,
                    'lite'        => $lite,
                    'reserve'     => $reserve,
                    'decision'    => $decisions[$row->id] ?? null,
                    'isMajor'     => $reserve > $majorThreshold,
                    'handlerName' => $handlerName,
                    'clientName'  => $clientNameOf($row),
                    'reportedTs'  => $reportedTs,
                    'createdTs'   => $createdTs,
                ];
            }

            // Derived-field filters.
            if ($statusIsDerived) {
                $needle = mb_strtolower(trim($statusPlain));
                $records = array_values(array_filter($records, fn ($r) => str_contains(mb_strtolower((string) $r['lite']['overallStatus']), $needle)));
            }
            if (!empty($validated['stage'])) {
                $stageNeedle = trim($validated['stage']);
                $records = array_values(array_filter($records, fn ($r) => strcasecmp((string) $r['lite']['currentStage'], $stageNeedle) === 0));
            }
            if (!empty($validated['decision'])) {
                $dv = $validated['decision'];
                $records = array_values(array_filter($records, fn ($r) => $dv === 'pending' ? $r['decision'] === null : $r['decision'] === $dv));
            }

            // Sort.
            $sortVal = function (array $rec) use ($sortKey) {
                switch ($sortKey) {
                    case 'claimNumber':   return (string) $rec['row']->claim_number;
                    case 'claimType':     return (string) $rec['row']->claim_type;
                    case 'overallStatus': return (string) $rec['lite']['overallStatus'];
                    case 'currentStage':  return (string) $rec['lite']['currentStage'];
                    case 'daysLate':      return (int) $rec['lite']['daysLate'];
                    case 'reserve':       return (float) $rec['reserve'];
                    case 'clientName':    return mb_strtolower((string) $rec['clientName']);
                    case 'handler':       return mb_strtolower((string) $rec['handlerName']);
                    default:              return (int) $rec['createdTs']; // newest-registered first
                }
            };
            usort($records, function ($a, $b) use ($sortVal, $dir) {
                $va = $sortVal($a); $vb = $sortVal($b);
                $cmp = (is_int($va) || is_float($va)) ? ($va <=> $vb) : strcmp((string) $va, (string) $vb);
                if ($cmp === 0) { $cmp = $a['row']->id <=> $b['row']->id; }
                return $dir === 'asc' ? $cmp : -$cmp;
            });

            $total     = count($records);
            $pageSlice = array_slice($records, ($page - 1) * $perPage, $perPage);
            $pageIds   = array_map(fn ($r) => $r['row']->id, $pageSlice);
            $comments  = $commentMap($pageIds);

            $data = [];
            foreach ($pageSlice as $rec) {
                $data[] = $buildRow(
                    $rec['row'], $rec['lite'], $rec['reserve'], $rec['decision'],
                    $comments[$rec['row']->id] ?? null, $rec['handlerName'], $rec['clientName']
                );
            }
        }

        // ── Union legal claims: show the union's form as the Type ──────────────
        // A legal claim filed against a union member is stored with claim_type
        // 'Legal' (the ENUM), but the list should read "BONU Legal" /
        // "BOWASEWU Legal" so operators can tell union claims apart. Display-only
        // override — the stored claim_type ENUM and the form_template routing are
        // untouched. One batch lookup keyed by the page's claim ids.
        if (!empty($data) && \Schema::hasTable('union_legal_claims')) {
            $pageClaimIds = array_values(array_filter(array_map(fn ($r) => $r['claimId'] ?? null, $data)));
            if ($pageClaimIds) {
                $unionForms = DB::table('union_legal_claims')
                    ->whereIn('claim_id', $pageClaimIds)
                    ->whereNull('deleted_at')
                    ->pluck('form_code', 'claim_id'); // claim_id => 'BONU' / 'BOWASEWU'
                if ($unionForms->isNotEmpty()) {
                    foreach ($data as &$rowOut) {
                        $code = $unionForms[$rowOut['claimId']] ?? null;
                        if ($code) {
                            $rowOut['claimType'] = strtoupper((string) $code) . ' Legal'; // "BONU Legal" / "BOWASEWU Legal"
                        }
                    }
                    unset($rowOut);
                }
            }
        }

        // ── gaps (fields Graphite genuinely cannot source) ─────────────────────
        $gaps[] = 'syncFailed / sync=failed — Graphite has no per-claim sync-attempt counter; a not-synced claim is reported as pending (never failed).';
        if (!$hasReported) {
            $gaps[] = 'reportedDate — claims.reported_date column absent on this schema; created_at used as the report date.';
        }
        if (!$hasPlate) {
            $gaps[] = 'plate — claims.vehicle_plate column absent on this schema (returned null).';
        }
        $gaps[] = 'stageChips[onTime]=null for unscored/unreached stages (glass QR/QF/POG/POI have no per-stage SLA deadline); non-motor claims carry no stage-chip set.';
        $gaps = array_values(array_unique($gaps));

        return response()->json([
            'data' => $data,
            'meta' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) max(1, ceil($total / $perPage)),
            ],
            'availableMonths' => $availableMonths,
            'filterOptions'   => [
                'stages'           => $stageOptions,
                'claimTypes'       => $claimTypeOptions,
                'nonMotorSubTypes' => $nonMotorSubTypes,
            ],
            'gaps' => $gaps,
        ]);
    }

    /**
     * Soft-delete a claim (the Claims Tracker replica's "Del" action).
     *
     * REVERSIBLE by design — stamps claims.deleted_at (+ who) so the row drops
     * out of the tracker list, but never destroys the claim or its linked
     * reserves / payments / history. Role-gated at the route (Admin / Super Admin
     * / Claims Manager) exactly like the legacy tracker's delete. Idempotent.
     */
    public function softDelete(Request $request, $id): JsonResponse
    {
        $table = $this->claimsTable();
        if (!$this->trackerHasColumn($table, 'deleted_at')) {
            return response()->json(['message' => 'Soft-delete is not available on this schema.'], 422);
        }

        // A reason is mandatory — controlled delete is audited (who / when / why).
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $claim = DB::table($table)->where('id', (int) $id)->first(['id', 'claim_number', 'deleted_at']);
        if (!$claim) {
            return response()->json(['message' => 'Claim not found.'], 404);
        }
        if (!empty($claim->deleted_at)) {
            return response()->json(['message' => 'Claim is already deleted.', 'claimId' => (int) $claim->id]);
        }

        $update = ['deleted_at' => Carbon::now()];
        if ($this->trackerHasColumn($table, 'deleted_by')) {
            $update['deleted_by'] = optional($request->user())->id;
        }
        DB::table($table)->where('id', (int) $claim->id)->update($update);

        $this->logActivity((int) $claim->id, $table, 'Claim soft-deleted: ' . ($claim->claim_number ?? $claim->id) . ' — reason: ' . $validated['reason']);

        return response()->json(['message' => 'Claim deleted.', 'claimId' => (int) $claim->id]);
    }

    /**
     * Restore a soft-deleted claim (undo of softDelete). Same role gate.
     */
    public function restore(Request $request, $id): JsonResponse
    {
        $table = $this->claimsTable();
        if (!$this->trackerHasColumn($table, 'deleted_at')) {
            return response()->json(['message' => 'Soft-delete is not available on this schema.'], 422);
        }

        $claim = DB::table($table)->where('id', (int) $id)->first(['id', 'claim_number']);
        if (!$claim) {
            return response()->json(['message' => 'Claim not found.'], 404);
        }

        $update = ['deleted_at' => null];
        if ($this->trackerHasColumn($table, 'deleted_by')) {
            $update['deleted_by'] = null;
        }
        DB::table($table)->where('id', (int) $claim->id)->update($update);

        $this->logActivity((int) $claim->id, $table, 'Claim restored: ' . ($claim->claim_number ?? $claim->id));

        return response()->json(['message' => 'Claim restored.', 'claimId' => (int) $claim->id]);
    }

    // ─── 21c. trackerIncentive ─────────────────────────────────────────────────

    /**
     * GET /api/v1/claims-v2/tracker-incentive?month=YYYY-M
     *
     * A 1:1 replica of the legacy Claims Tracker "Incentive Report": two
     * per-handler supplier-compliance tables (panel-beater + glass), optionally
     * month-scoped. ADDITIVE + READ-ONLY (SELECT only) — it never writes and
     * leaves ClaimsIncentiveReportController::report and every list method alone.
     *
     * Supplier-compliance signal (identical to trackerDashboard's leaderboard and
     * ClaimsIncentiveReportController): the supplier that "handled" a claim is the
     * supplier on the claim's ACCEPTED quote (claim_quotes.status = 'Accepted');
     * "approved" reads that supplier's is_approved_panel_beater /
     * is_approved_glass_supplier flag. ONLY claims with a routed supplier (an
     * accepted-quote supplier) are counted — a claim with no supplier is excluded.
     *
     * Eligibility (tracker rules):
     *   - Panel-Beater table: MOTOR claims (UPPER(claim_type) LIKE 'MOTOR%') whose
     *     policy group is MIS or DOM (policyNumber prefix MIS%/ADH% -> MIS,
     *     DOMG% -> DOM). approved = routed supplier is_approved_panel_beater = 1.
     *   - Glass table: claim_type IN ('Glass','Lock & Key'). approved = routed
     *     supplier is_approved_glass_supplier = 1.
     *
     * Per handler: total routed claims, approved, nonApproved = total-approved,
     * pct = round(approved/total*100), qualifies = pct >= threshold (90 panel /
     * 80 glass). Handler resolved via claim_allocated_to -> users (new_claims
     * fallback), 'Unassigned' when none — the same resolver as trackerDashboard.
     * Month scoping on COALESCE(reported_date, created_at), same as trackerList.
     */
    public function trackerIncentive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => 'nullable|date_format:Y-m',
        ]);
        $month = $validated['month'] ?? null;

        $gaps  = [];
        $table = $this->claimsTable();

        // Optional columns — degrade gracefully on older schemas (never 500).
        $hasReported = $this->trackerHasColumn($table, 'reported_date');
        $hasAlloc    = $this->trackerHasColumn($table, 'claim_allocated_to');

        // "Claim month" anchor = reported_date (loss reported) else created_at
        // (created_at is always present so the COALESCE is never null).
        $dateParts = [];
        if ($hasReported) { $dateParts[] = "c.reported_date"; }
        $dateParts[] = "c.created_at";
        $dateExpr = 'COALESCE(' . implode(', ', $dateParts) . ')';

        // Policy group from the policy-number prefix (reuse trackerList's rule).
        // MIS%/ADH% -> MIS, DOMG% -> DOM; anything else -> OTHER (never counted
        // as panel-beater, which requires MIS or DOM).
        $groupCase = "CASE
            WHEN UPPER(p.policyNumber) LIKE 'MIS%' OR UPPER(p.policyNumber) LIKE 'ADH%' THEN 'MIS'
            WHEN UPPER(p.policyNumber) LIKE 'DOMG%' THEN 'DOM'
            ELSE 'OTHER' END";

        // Supplier-compliance availability (identical guard to trackerDashboard).
        // Without claim_quotes + the two supplier approval flags there is no
        // routing/approval signal at all — report null overalls + gaps, never 0.
        $supplierComplianceAvailable = false;
        try {
            $supplierComplianceAvailable = Schema::hasTable('claim_quotes')
                && Schema::hasColumn('suppliers', 'is_approved_panel_beater')
                && Schema::hasColumn('suppliers', 'is_approved_glass_supplier');
        } catch (\Throwable $e) {
            $supplierComplianceAvailable = false;
        }

        if (!$supplierComplianceAvailable) {
            $gaps[] = 'panelBeater / glass (claim_quotes table or suppliers.is_approved_panel_beater / is_approved_glass_supplier unavailable — supplier-compliance cannot be sourced)';
            return response()->json([
                'data' => [
                    'month'           => $month,
                    'availableMonths' => [],
                    'panelBeater'     => [
                        'threshold' => 90, 'label' => 'MIS/DOM Motor Claims',
                        'totalClaims' => 0, 'approvedCount' => 0, 'overallPct' => null, 'handlers' => [],
                    ],
                    'glass' => [
                        'threshold' => 80, 'label' => 'Glass Claims',
                        'totalClaims' => 0, 'approvedCount' => 0, 'overallPct' => null, 'handlers' => [],
                    ],
                ],
                'gaps' => $gaps,
            ]);
        }

        // ── availableMonths (routed claims only, most-recent first) ────────────
        $availableMonths = DB::table("{$table} as c")
            ->join('claim_quotes as cq', function ($j) {
                $j->on('cq.claim_id', '=', 'c.id')->where('cq.status', '=', 'Accepted');
            })
            ->join('suppliers as s', 's.id', '=', 'cq.supplier_id')
            ->select(DB::raw("DISTINCT DATE_FORMAT($dateExpr, '%Y-%m') as ym"))
            ->orderBy('ym', 'desc')
            ->pluck('ym')
            ->filter()
            ->values()
            ->all();

        // No month selected → the UI shows a "select a month" empty state, so skip
        // the (potentially very large) full-history routed-facts join and return
        // only the month list + empty buckets. Avoids scanning the whole accepted-
        // quote history on every page open (claim_quotes is heavily duplicated).
        if (!$month) {
            return response()->json([
                'data' => [
                    'month'           => null,
                    'availableMonths' => $availableMonths,
                    'panelBeater'     => ['threshold' => 90, 'label' => 'MIS/DOM Motor Claims', 'totalClaims' => 0, 'approvedCount' => 0, 'overallPct' => 0, 'handlers' => []],
                    'glass'           => ['threshold' => 80, 'label' => 'Glass Claims', 'totalClaims' => 0, 'approvedCount' => 0, 'overallPct' => 0, 'handlers' => []],
                ],
                'gaps' => $gaps,
            ]);
        }

        // ── Routed claim facts (one row per claim x accepted-quote supplier) ───
        $selectCols = [
            'c.id',
            'c.claim_number as claim_number',
            DB::raw('UPPER(TRIM(c.claim_type)) as ct'),
            DB::raw("$groupCase as grp"),
            's.is_approved_panel_beater as pb',
            's.is_approved_glass_supplier as glass',
        ];
        if ($hasAlloc) { $selectCols[] = 'c.claim_allocated_to as allocated_to'; }

        $rows = DB::table("{$table} as c")
            ->leftJoin('policies as p', 'p.id', '=', 'c.policy_id')
            ->join('claim_quotes as cq', function ($j) {
                $j->on('cq.claim_id', '=', 'c.id')->where('cq.status', '=', 'Accepted');
            })
            ->join('suppliers as s', 's.id', '=', 'cq.supplier_id')
            ->when($month, fn ($q) => $q->whereRaw("DATE_FORMAT($dateExpr, '%Y-%m') = ?", [$month]))
            ->orderBy('cq.id')
            ->select($selectCols)
            ->get();

        // Collapse to one fact per claim — ordered by cq.id so "first row wins" is
        // deterministic (the earliest accepted quote per claim; stable across
        // refetches). A claim should carry a single accepted quote in practice.
        $claims = [];
        foreach ($rows as $r) {
            if (!isset($claims[$r->id])) { $claims[$r->id] = $r; }
        }

        // ── Handler resolution (claim_allocated_to, new_claims fallback) ───────
        $handlerByClaim = [];
        foreach ($claims as $id => $r) {
            $aid = $hasAlloc ? ($r->allocated_to ?? null) : null;
            if ($aid) { $handlerByClaim[$id] = (int) $aid; }
        }
        $missingNumbers = [];
        foreach ($claims as $id => $r) {
            if (!isset($handlerByClaim[$id]) && $r->claim_number) {
                $missingNumbers[] = $r->claim_number;
            }
        }
        if (!empty($missingNumbers)) {
            $ncAlloc = DB::table('new_claims')
                ->whereIn('claim_number', array_values(array_unique($missingNumbers)))
                ->whereNotNull('claim_allocated_to')
                ->pluck('claim_allocated_to', 'claim_number')
                ->toArray();
            foreach ($claims as $id => $r) {
                if (!isset($handlerByClaim[$id]) && !empty($ncAlloc[$r->claim_number])) {
                    $handlerByClaim[$id] = (int) $ncAlloc[$r->claim_number];
                }
            }
        }
        $handlerIds   = array_values(array_unique(array_filter($handlerByClaim)));
        $handlerNames = [];
        if (!empty($handlerIds)) {
            $handlerNames = DB::table('users')
                ->whereIn('id', $handlerIds)
                ->get(['id', 'firstName', 'lastName'])
                ->keyBy('id')
                ->map(fn ($u) => trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')))
                ->toArray();
        }
        // hid 0 -> 'Unassigned'; known id with empty name -> 'User #<id>' (never
        // collapse two distinct handlers into one row).
        $handlerLabel = function ($cid) use ($handlerByClaim, $handlerNames) {
            $hid = $handlerByClaim[$cid] ?? 0;
            if (!$hid) { return 'Unassigned'; }
            $name = $handlerNames[$hid] ?? '';
            return $name !== '' ? $name : ('User #' . $hid);
        };

        // ── Bucket routed claims into the two eligibility tables ───────────────
        $panelBucket = []; // handler label => ['total'=>int,'approved'=>int]
        $glassBucket = [];
        foreach ($claims as $id => $r) {
            $label   = $handlerLabel($id);
            $ct      = (string) $r->ct; // already UPPER(TRIM())
            $isMotor = str_starts_with($ct, 'MOTOR');
            $isGlass = in_array($ct, ['GLASS', 'LOCK & KEY'], true);

            // Panel-beater: MOTOR + policy group MIS or DOM.
            if ($isMotor && in_array($r->grp, ['MIS', 'DOM'], true)) {
                $panelBucket[$label] ??= ['total' => 0, 'approved' => 0];
                $panelBucket[$label]['total']++;
                if ((int) ($r->pb ?? 0) === 1) { $panelBucket[$label]['approved']++; }
            }

            // Glass: claim_type Glass or Lock & Key.
            if ($isGlass) {
                $glassBucket[$label] ??= ['total' => 0, 'approved' => 0];
                $glassBucket[$label]['total']++;
                if ((int) ($r->glass ?? 0) === 1) { $glassBucket[$label]['approved']++; }
            }
        }

        // ── Shape one table (handlers sorted by total desc + summary tiles) ────
        $buildTable = function (array $bucket, int $threshold, string $label) {
            $handlers      = [];
            $totalClaims   = 0;
            $approvedCount = 0;
            foreach ($bucket as $name => $b) {
                $total       = (int) $b['total'];
                $approved    = (int) $b['approved'];
                $nonApproved = $total - $approved;
                $pct         = $total > 0 ? round($approved / $total * 100) : 0;
                $handlers[]  = [
                    'handler'     => $name,
                    'total'       => $total,
                    'approved'    => $approved,
                    'nonApproved' => $nonApproved,
                    'pct'         => $pct,
                    'qualifies'   => $pct >= $threshold,
                ];
                $totalClaims   += $total;
                $approvedCount += $approved;
            }
            usort($handlers, fn ($a, $b) => $b['total'] <=> $a['total']);
            $overallPct = $totalClaims > 0 ? round($approvedCount / $totalClaims * 100, 1) : 0;

            return [
                'threshold'     => $threshold,
                'label'         => $label,
                'totalClaims'   => $totalClaims,
                'approvedCount' => $approvedCount,
                'overallPct'    => $overallPct,
                'handlers'      => $handlers,
            ];
        };

        return response()->json([
            'data' => [
                'month'           => $month,
                'availableMonths' => $availableMonths,
                'panelBeater'     => $buildTable($panelBucket, 90, 'MIS/DOM Motor Claims'),
                'glass'           => $buildTable($glassBucket, 80, 'Glass Claims'),
            ],
            'gaps' => $gaps,
        ]);
    }

    /**
     * Per-process Schema::hasColumn cache for trackerDashboard's optional-column
     * guards (mirrors ClaimsController's schemaHasColumn). Keeps the endpoint from
     * 500-ing on schemas where reported_date / registered_claim / claim_allocated_to
     * are absent.
     */
    private static array $trackerColumnCache = [];

    private function trackerHasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        return self::$trackerColumnCache[$key] ??= Schema::hasColumn($table, $column);
    }

    // ─── 22. formConfig ──────────────────────────────────────────────────────

    public function formConfig(Request $request): JsonResponse
    {
        $claimType = $request->query('claim_type');

        $configs = $this->allFormConfigs();

        if ($claimType) {
            $key = strtoupper($claimType);
            if (!isset($configs[$key])) {
                return response()->json(['message' => "No config found for type: {$claimType}"], 404);
            }
            return response()->json(['data' => ['claimType' => $key, 'fields' => $configs[$key]]]);
        }

        // Return all
        $data = [];
        foreach ($configs as $type => $fields) {
            $data[] = ['claimType' => $type, 'fields' => $fields];
        }
        return response()->json(['data' => $data]);
    }

    // ─── 23. claimTypes ──────────────────────────────────────────────────────

    public function claimTypes(): JsonResponse
    {
        return response()->json([
            'data' => [
                ['code' => 'MOTORACCIDENT',         'name' => 'Motor Accident'],
                ['code' => 'FIRE',                   'name' => 'Fire'],
                ['code' => 'BURGLARY/THEFT',         'name' => 'Burglary / Theft'],
                ['code' => 'ALLRISK',                'name' => 'All Risk'],
                ['code' => 'WORKERSCOMPENSATION',    'name' => 'Workers Compensation'],
                ['code' => 'BUSINESSINTERRUPTION',   'name' => 'Business Interruption'],
                ['code' => 'GOODSINTRANSIT',         'name' => 'Goods In Transit'],
                ['code' => 'FIDELITYGUARANTEE',      'name' => 'Fidelity Guarantee'],
                ['code' => 'GLASS',                  'name' => 'Glass'],
                ['code' => 'KEYLOSS',                'name' => 'Key Loss'],
                ['code' => 'PUBLICLIABILITY',        'name' => 'Public Liability'],
                ['code' => 'PROPERTYLOSSDAMAGE',     'name' => 'Property Loss / Damage'],
                ['code' => 'ACCIDENTALDAMAGE',       'name' => 'Accidental Damage'],
                ['code' => 'MOBILEELECTRONIC',       'name' => 'Mobile / Electronic Devices'],
                ['code' => 'TRAVELINSURANCE',        'name' => 'Travel Insurance'],
                ['code' => 'ERECTIONALLRISK',        'name' => 'Erection All Risk'],
                ['code' => 'PLANTALLRISK',           'name' => 'Plant All Risk'],
                ['code' => 'PROFESSIONALINDEMNITY',  'name' => 'Professional Indemnity'],
            ],
        ]);
    }

    /**
     * API Access — read-only registry of Sanctum personal access tokens, the
     * genuine "who can call the Graphite API" source. Additive; backs the
     * Claims → Admin → API Access screen (a concept-level port of the Claims
     * Tracker's "API Access Monitor"). Route-gated to Admin | Super Admin.
     *
     * Returns token METADATA only — never the token secret (which is stored
     * hashed anyway). The Tracker's IP whitelist ("Trusted Sources") and its
     * per-request /api/* access log have no Graphite equivalent, so this
     * exposes the real token registry + usage timestamps instead.
     */
    public function apiAccess(Request $request): JsonResponse
    {
        if (!Schema::hasTable('personal_access_tokens')) {
            return response()->json([
                'source'    => 'sanctum_personal_access_tokens',
                'available' => false,
                'stats'     => null,
                'tokens'    => [],
                'note'      => 'Sanctum personal_access_tokens table not found on this environment.',
            ]);
        }

        $hasExpiresAt = Schema::hasColumn('personal_access_tokens', 'expires_at');
        $limit = min(max((int) $request->query('limit', 200), 1), 1000);
        $now   = Carbon::now();

        $cols = [
            't.id', 't.name', 't.abilities', 't.last_used_at', 't.created_at',
            // owner NAME only — the owner's email is deliberately NOT exposed
            // here. This screen lists EVERY system API token (global Sanctum
            // PATs, not claims-scoped) to claims admins; surfacing every token
            // owner's email address to that audience is unnecessary PII (DPA).
            // The name is enough to identify who holds a token.
            'u.name as owner_name',
        ];
        if ($hasExpiresAt) {
            $cols[] = 't.expires_at';
        }

        $rows = DB::table('personal_access_tokens as t')
            ->leftJoin('users as u', function ($join) {
                $join->on('u.id', '=', 't.tokenable_id')
                     ->where('t.tokenable_type', 'LIKE', '%User');
            })
            ->select($cols)
            ->orderByRaw('COALESCE(t.last_used_at, t.created_at) DESC')
            ->limit($limit)
            ->get();

        $tokens = $rows->map(function ($r) use ($hasExpiresAt) {
            $abilities = [];
            if (!empty($r->abilities)) {
                $decoded = json_decode($r->abilities, true);
                if (is_array($decoded)) {
                    $abilities = $decoded;
                }
            }
            $expiresAt = $hasExpiresAt ? ($r->expires_at ?? null) : null;
            $expired   = $expiresAt ? Carbon::parse($expiresAt)->isPast() : false;

            return [
                'id'           => (int) $r->id,
                'name'         => $r->name,
                'owner'        => $r->owner_name ?? null,
                'abilities'    => $abilities,
                'last_used_at' => $r->last_used_at ? Carbon::parse($r->last_used_at)->toIso8601String() : null,
                'expires_at'   => $expiresAt ? Carbon::parse($expiresAt)->toIso8601String() : null,
                'created_at'   => $r->created_at ? Carbon::parse($r->created_at)->toIso8601String() : null,
                'expired'      => $expired,
            ];
        })->values();

        // Aggregate stats across ALL tokens (not just the returned page).
        $total     = DB::table('personal_access_tokens')->count();
        $used24h   = DB::table('personal_access_tokens')->where('last_used_at', '>=', $now->copy()->subDay())->count();
        $used7d    = DB::table('personal_access_tokens')->where('last_used_at', '>=', $now->copy()->subDays(7))->count();
        $neverUsed = DB::table('personal_access_tokens')->whereNull('last_used_at')->count();

        $expiredCount = 0;
        $activeCount  = $total;
        if ($hasExpiresAt) {
            $expiredCount = DB::table('personal_access_tokens')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', $now)
                ->count();
            $activeCount = $total - $expiredCount;
        }

        return response()->json([
            'source'    => 'sanctum_personal_access_tokens',
            'available' => true,
            'stats'     => [
                'total_tokens'    => $total,
                'active_tokens'   => $activeCount,
                'expired_tokens'  => $expiredCount,
                'used_24h'        => $used24h,
                'used_7d'         => $used7d,
                'never_used'      => $neverUsed,
                'supports_expiry' => $hasExpiresAt,
            ],
            'tokens'    => $tokens,
            'note'      => 'Graphite authenticates its API with Sanctum bearer tokens; this lists token metadata only (never the secret). The Claims Tracker\'s IP whitelist and per-request access log have no Graphite equivalent.',
        ]);
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  Private helpers
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Log a claim activity to the activity_log table.
     */
    private function logActivity(int $claimId, string $table, string $description): void
    {
        try {
            $subjectType = ($table === 'new_claims')
                ? 'AlphaDirect\\NewClaim'
                : 'AlphaDirect\\Claim';

            DB::table('activity_log')->insert([
                'log_name'     => 'Claim',
                'description'  => $description,
                'subject_id'   => $claimId,
                'subject_type' => $subjectType,
                'causer_id'    => auth()->id(),
                'causer_type'  => 'AlphaDirect\\User',
                'properties'   => json_encode([]),
                'created_at'   => Carbon::now(),
                'updated_at'   => Carbon::now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log claim activity', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Form field configurations for all 18 claim types.
     */
    private function allFormConfigs(): array
    {
        return [
            'MOTORACCIDENT' => [
                ['name' => 'driver_name',           'label' => 'Driver Name',             'type' => 'text',     'required' => true],
                ['name' => 'driver_license',        'label' => 'Driver License #',        'type' => 'text',     'required' => false],
                ['name' => 'accident_location',     'label' => 'Accident Location',       'type' => 'text',     'required' => true],
                ['name' => 'police_station',        'label' => 'Police Station',          'type' => 'text',     'required' => false],
                ['name' => 'police_ref',            'label' => 'Police Reference #',      'type' => 'text',     'required' => false],
                ['name' => 'vehicle_drivable',      'label' => 'Vehicle Drivable?',       'type' => 'select',   'required' => false, 'options' => ['Yes', 'No']],
                ['name' => 'damage_description',    'label' => 'Damage Description',      'type' => 'textarea', 'required' => false],
                ['name' => 'third_party_involved',  'label' => 'Third Party Involved?',   'type' => 'select',   'required' => false, 'options' => ['Yes', 'No']],
            ],

            'FIRE' => [
                ['name' => 'fire_location',         'label' => 'Fire Location',           'type' => 'text',     'required' => true],
                ['name' => 'fire_cause',            'label' => 'Cause of Fire',           'type' => 'text',     'required' => false],
                ['name' => 'fire_date',             'label' => 'Date of Fire',            'type' => 'date',     'required' => true],
                ['name' => 'fire_brigade_called',   'label' => 'Fire Brigade Called?',    'type' => 'select',   'required' => false, 'options' => ['Yes', 'No']],
                ['name' => 'fire_brigade_ref',      'label' => 'Fire Brigade Ref #',      'type' => 'text',     'required' => false],
                ['name' => 'police_station',        'label' => 'Police Station',          'type' => 'text',     'required' => false],
                ['name' => 'police_ref',            'label' => 'Police Reference #',      'type' => 'text',     'required' => false],
                ['name' => 'damage_description',    'label' => 'Damage Description',      'type' => 'textarea', 'required' => true],
                ['name' => 'estimated_loss',        'label' => 'Estimated Loss Amount',   'type' => 'number',   'required' => false],
            ],

            'BURGLARY/THEFT' => [
                ['name' => 'incident_location',     'label' => 'Incident Location',       'type' => 'text',     'required' => true],
                ['name' => 'incident_date',         'label' => 'Date of Incident',        'type' => 'date',     'required' => true],
                ['name' => 'incident_time',         'label' => 'Time of Incident',        'type' => 'text',     'required' => false],
                ['name' => 'items_stolen',          'label' => 'Items Stolen/Damaged',    'type' => 'textarea', 'required' => true],
                ['name' => 'entry_method',          'label' => 'Method of Entry',         'type' => 'text',     'required' => false],
                ['name' => 'police_station',        'label' => 'Police Station',          'type' => 'text',     'required' => true],
                ['name' => 'police_ref',            'label' => 'Police Reference #',      'type' => 'text',     'required' => true],
                ['name' => 'estimated_value',       'label' => 'Estimated Value',         'type' => 'number',   'required' => false],
                ['name' => 'witnesses',             'label' => 'Witnesses',               'type' => 'textarea', 'required' => false],
            ],

            'ALLRISK' => [
                ['name' => 'item_description',      'label' => 'Item Description',        'type' => 'text',     'required' => true],
                ['name' => 'item_value',            'label' => 'Item Value',              'type' => 'number',   'required' => false],
                ['name' => 'incident_location',     'label' => 'Incident Location',       'type' => 'text',     'required' => true],
                ['name' => 'incident_date',         'label' => 'Date of Incident',        'type' => 'date',     'required' => true],
                ['name' => 'description',           'label' => 'Description of Loss',     'type' => 'textarea', 'required' => true],
                ['name' => 'police_station',        'label' => 'Police Station',          'type' => 'text',     'required' => false],
                ['name' => 'police_ref',            'label' => 'Police Reference #',      'type' => 'text',     'required' => false],
            ],

            'WORKERSCOMPENSATION' => [
                ['name' => 'employee_name',         'label' => 'Employee Name',           'type' => 'text',     'required' => true],
                ['name' => 'employee_id',           'label' => 'Employee ID',             'type' => 'text',     'required' => false],
                ['name' => 'date_of_injury',        'label' => 'Date of Injury',          'type' => 'date',     'required' => true],
                ['name' => 'time_of_injury',        'label' => 'Time of Injury',          'type' => 'text',     'required' => false],
                ['name' => 'location_of_injury',    'label' => 'Location of Injury',      'type' => 'text',     'required' => true],
                ['name' => 'nature_of_injury',      'label' => 'Nature of Injury',        'type' => 'textarea', 'required' => true],
                ['name' => 'body_part_affected',    'label' => 'Body Part Affected',      'type' => 'text',     'required' => false],
                ['name' => 'medical_provider',      'label' => 'Medical Provider',        'type' => 'text',     'required' => false],
                ['name' => 'hospitalized',          'label' => 'Hospitalized?',           'type' => 'select',   'required' => false, 'options' => ['Yes', 'No']],
                ['name' => 'days_off_work',         'label' => 'Days Off Work',           'type' => 'number',   'required' => false],
                ['name' => 'witness_name',          'label' => 'Witness Name',            'type' => 'text',     'required' => false],
            ],

            'BUSINESSINTERRUPTION' => [
                ['name' => 'cause_of_interruption', 'label' => 'Cause of Interruption',   'type' => 'text',     'required' => true],
                ['name' => 'interruption_start',    'label' => 'Interruption Start Date', 'type' => 'date',     'required' => true],
                ['name' => 'interruption_end',      'label' => 'Interruption End Date',   'type' => 'date',     'required' => false],
                ['name' => 'business_location',     'label' => 'Business Location',       'type' => 'text',     'required' => true],
                ['name' => 'estimated_loss',        'label' => 'Estimated Revenue Loss',  'type' => 'number',   'required' => false],
                ['name' => 'description',           'label' => 'Description',             'type' => 'textarea', 'required' => true],
            ],

            'GOODSINTRANSIT' => [
                ['name' => 'carrier_name',          'label' => 'Carrier Name',            'type' => 'text',     'required' => true],
                ['name' => 'vehicle_reg',           'label' => 'Vehicle Registration',    'type' => 'text',     'required' => false],
                ['name' => 'origin',                'label' => 'Origin Location',         'type' => 'text',     'required' => true],
                ['name' => 'destination',           'label' => 'Destination',             'type' => 'text',     'required' => true],
                ['name' => 'goods_description',     'label' => 'Goods Description',       'type' => 'textarea', 'required' => true],
                ['name' => 'goods_value',           'label' => 'Goods Value',             'type' => 'number',   'required' => false],
                ['name' => 'incident_date',         'label' => 'Date of Incident',        'type' => 'date',     'required' => true],
                ['name' => 'incident_location',     'label' => 'Incident Location',       'type' => 'text',     'required' => false],
                ['name' => 'cause_of_loss',         'label' => 'Cause of Loss',           'type' => 'text',     'required' => true],
                ['name' => 'police_ref',            'label' => 'Police Reference #',      'type' => 'text',     'required' => false],
            ],

            'FIDELITYGUARANTEE' => [
                ['name' => 'employee_name',         'label' => 'Employee Name',           'type' => 'text',     'required' => true],
                ['name' => 'employee_position',     'label' => 'Employee Position',       'type' => 'text',     'required' => false],
                ['name' => 'date_discovered',       'label' => 'Date Discovered',         'type' => 'date',     'required' => true],
                ['name' => 'nature_of_loss',        'label' => 'Nature of Loss',          'type' => 'textarea', 'required' => true],
                ['name' => 'estimated_amount',      'label' => 'Estimated Amount',        'type' => 'number',   'required' => false],
                ['name' => 'police_station',        'label' => 'Police Station',          'type' => 'text',     'required' => false],
                ['name' => 'police_ref',            'label' => 'Police Reference #',      'type' => 'text',     'required' => false],
                ['name' => 'internal_investigation','label' => 'Internal Investigation?', 'type' => 'select',   'required' => false, 'options' => ['Yes', 'No']],
            ],

            'GLASS' => [
                ['name' => 'glass_type',            'label' => 'Type of Glass',           'type' => 'text',     'required' => true],
                ['name' => 'glass_location',        'label' => 'Location of Glass',       'type' => 'text',     'required' => true],
                ['name' => 'cause_of_damage',       'label' => 'Cause of Damage',         'type' => 'text',     'required' => true],
                ['name' => 'incident_date',         'label' => 'Date of Incident',        'type' => 'date',     'required' => true],
                ['name' => 'estimated_cost',        'label' => 'Estimated Replacement Cost', 'type' => 'number','required' => false],
                ['name' => 'description',           'label' => 'Description',             'type' => 'textarea', 'required' => false],
            ],

            'KEYLOSS' => [
                ['name' => 'key_type',              'label' => 'Type of Key',             'type' => 'select',   'required' => true, 'options' => ['Vehicle', 'Property', 'Other']],
                ['name' => 'loss_location',         'label' => 'Location of Loss',        'type' => 'text',     'required' => true],
                ['name' => 'loss_date',             'label' => 'Date of Loss',            'type' => 'date',     'required' => true],
                ['name' => 'description',           'label' => 'Description',             'type' => 'textarea', 'required' => true],
                ['name' => 'police_station',        'label' => 'Police Station',          'type' => 'text',     'required' => false],
                ['name' => 'police_ref',            'label' => 'Police Reference #',      'type' => 'text',     'required' => false],
                ['name' => 'replacement_cost',      'label' => 'Estimated Replacement Cost', 'type' => 'number','required' => false],
            ],

            'PUBLICLIABILITY' => [
                ['name' => 'claimant_name',         'label' => 'Claimant Name',           'type' => 'text',     'required' => true],
                ['name' => 'claimant_contact',      'label' => 'Claimant Contact',        'type' => 'text',     'required' => false],
                ['name' => 'incident_date',         'label' => 'Date of Incident',        'type' => 'date',     'required' => true],
                ['name' => 'incident_location',     'label' => 'Incident Location',       'type' => 'text',     'required' => true],
                ['name' => 'nature_of_claim',       'label' => 'Nature of Claim',         'type' => 'textarea', 'required' => true],
                ['name' => 'injury_or_damage',      'label' => 'Injury / Damage Details', 'type' => 'textarea', 'required' => false],
                ['name' => 'witnesses',             'label' => 'Witnesses',               'type' => 'textarea', 'required' => false],
                ['name' => 'estimated_amount',      'label' => 'Estimated Claim Amount',  'type' => 'number',   'required' => false],
            ],

            'PROPERTYLOSSDAMAGE' => [
                ['name' => 'property_address',      'label' => 'Property Address',        'type' => 'text',     'required' => true],
                ['name' => 'property_type',         'label' => 'Property Type',           'type' => 'text',     'required' => false],
                ['name' => 'incident_date',         'label' => 'Date of Incident',        'type' => 'date',     'required' => true],
                ['name' => 'cause_of_loss',         'label' => 'Cause of Loss/Damage',    'type' => 'text',     'required' => true],
                ['name' => 'damage_description',    'label' => 'Damage Description',      'type' => 'textarea', 'required' => true],
                ['name' => 'estimated_loss',        'label' => 'Estimated Loss Amount',   'type' => 'number',   'required' => false],
                ['name' => 'police_station',        'label' => 'Police Station',          'type' => 'text',     'required' => false],
                ['name' => 'police_ref',            'label' => 'Police Reference #',      'type' => 'text',     'required' => false],
            ],

            'ACCIDENTALDAMAGE' => [
                ['name' => 'item_description',      'label' => 'Item Description',        'type' => 'text',     'required' => true],
                ['name' => 'incident_date',         'label' => 'Date of Incident',        'type' => 'date',     'required' => true],
                ['name' => 'incident_location',     'label' => 'Incident Location',       'type' => 'text',     'required' => true],
                ['name' => 'how_damage_occurred',   'label' => 'How Damage Occurred',     'type' => 'textarea', 'required' => true],
                ['name' => 'estimated_repair',      'label' => 'Estimated Repair Cost',   'type' => 'number',   'required' => false],
                ['name' => 'item_still_usable',     'label' => 'Item Still Usable?',      'type' => 'select',   'required' => false, 'options' => ['Yes', 'No']],
            ],

            'MOBILEELECTRONIC' => [
                ['name' => 'device_type',           'label' => 'Device Type',             'type' => 'text',     'required' => true],
                ['name' => 'device_make',           'label' => 'Device Make',             'type' => 'text',     'required' => true],
                ['name' => 'device_model',          'label' => 'Device Model',            'type' => 'text',     'required' => true],
                ['name' => 'serial_number',         'label' => 'Serial / IMEI Number',    'type' => 'text',     'required' => false],
                ['name' => 'incident_date',         'label' => 'Date of Incident',        'type' => 'date',     'required' => true],
                ['name' => 'incident_type',         'label' => 'Incident Type',           'type' => 'select',   'required' => true, 'options' => ['Theft', 'Damage', 'Loss', 'Other']],
                ['name' => 'incident_location',     'label' => 'Incident Location',       'type' => 'text',     'required' => false],
                ['name' => 'description',           'label' => 'Description',             'type' => 'textarea', 'required' => true],
                ['name' => 'police_station',        'label' => 'Police Station',          'type' => 'text',     'required' => false],
                ['name' => 'police_ref',            'label' => 'Police Reference #',      'type' => 'text',     'required' => false],
            ],

            'TRAVELINSURANCE' => [
                ['name' => 'travel_destination',    'label' => 'Travel Destination',      'type' => 'text',     'required' => true],
                ['name' => 'departure_date',        'label' => 'Departure Date',          'type' => 'date',     'required' => true],
                ['name' => 'return_date',           'label' => 'Return Date',             'type' => 'date',     'required' => false],
                ['name' => 'claim_reason',          'label' => 'Claim Reason',            'type' => 'select',   'required' => true, 'options' => ['Cancellation', 'Medical', 'Lost Luggage', 'Delay', 'Other']],
                ['name' => 'incident_date',         'label' => 'Date of Incident',        'type' => 'date',     'required' => true],
                ['name' => 'incident_location',     'label' => 'Incident Location',       'type' => 'text',     'required' => false],
                ['name' => 'description',           'label' => 'Description',             'type' => 'textarea', 'required' => true],
                ['name' => 'medical_provider',      'label' => 'Medical Provider (if applicable)', 'type' => 'text', 'required' => false],
                ['name' => 'estimated_amount',      'label' => 'Estimated Claim Amount',  'type' => 'number',   'required' => false],
            ],

            'ERECTIONALLRISK' => [
                ['name' => 'project_name',          'label' => 'Project Name',            'type' => 'text',     'required' => true],
                ['name' => 'project_location',      'label' => 'Project Location',        'type' => 'text',     'required' => true],
                ['name' => 'contractor_name',       'label' => 'Contractor Name',         'type' => 'text',     'required' => false],
                ['name' => 'incident_date',         'label' => 'Date of Incident',        'type' => 'date',     'required' => true],
                ['name' => 'nature_of_damage',      'label' => 'Nature of Damage',        'type' => 'textarea', 'required' => true],
                ['name' => 'cause_of_loss',         'label' => 'Cause of Loss',           'type' => 'text',     'required' => true],
                ['name' => 'equipment_affected',    'label' => 'Equipment Affected',      'type' => 'textarea', 'required' => false],
                ['name' => 'estimated_loss',        'label' => 'Estimated Loss Amount',   'type' => 'number',   'required' => false],
                ['name' => 'third_party_involved',  'label' => 'Third Party Involved?',   'type' => 'select',   'required' => false, 'options' => ['Yes', 'No']],
            ],

            'PLANTALLRISK' => [
                ['name' => 'plant_description',     'label' => 'Plant / Machinery Description', 'type' => 'text',  'required' => true],
                ['name' => 'plant_serial',          'label' => 'Serial Number',           'type' => 'text',     'required' => false],
                ['name' => 'plant_location',        'label' => 'Plant Location',          'type' => 'text',     'required' => true],
                ['name' => 'incident_date',         'label' => 'Date of Incident',        'type' => 'date',     'required' => true],
                ['name' => 'nature_of_damage',      'label' => 'Nature of Damage',        'type' => 'textarea', 'required' => true],
                ['name' => 'cause_of_loss',         'label' => 'Cause of Loss',           'type' => 'text',     'required' => true],
                ['name' => 'operator_name',         'label' => 'Operator Name',           'type' => 'text',     'required' => false],
                ['name' => 'estimated_repair',      'label' => 'Estimated Repair Cost',   'type' => 'number',   'required' => false],
            ],

            'PROFESSIONALINDEMNITY' => [
                ['name' => 'claimant_name',         'label' => 'Claimant Name',           'type' => 'text',     'required' => true],
                ['name' => 'claimant_contact',      'label' => 'Claimant Contact',        'type' => 'text',     'required' => false],
                ['name' => 'date_of_allegation',    'label' => 'Date of Allegation',      'type' => 'date',     'required' => true],
                ['name' => 'nature_of_allegation',  'label' => 'Nature of Allegation',    'type' => 'textarea', 'required' => true],
                ['name' => 'service_provided',      'label' => 'Service Provided',        'type' => 'text',     'required' => false],
                ['name' => 'date_service_rendered',  'label' => 'Date Service Rendered',  'type' => 'date',     'required' => false],
                ['name' => 'estimated_amount',      'label' => 'Estimated Claim Amount',  'type' => 'number',   'required' => false],
                ['name' => 'legal_action_taken',    'label' => 'Legal Action Taken?',     'type' => 'select',   'required' => false, 'options' => ['Yes', 'No']],
                ['name' => 'description',           'label' => 'Description',             'type' => 'textarea', 'required' => true],
            ],
        ];
    }

    /**
     * GET /claims/{id}/reinsurance
     *
     * Read-only view of the claim's reinsurance exposure. Not in legacy
     * graphiteBWV8 — new V2 feature driven by the CFO's Finding 6.
     *
     * Pro-rates the underlying policy's cession (cached per policy-action)
     * against the claim's current reserve + payment totals. This gives the
     * claims handler a "if we pay out P X, roughly P Y comes back from
     * reinsurer Z" snapshot without needing a full cession/bordereaux module.
     *
     * READ THROUGH CessionSource, so it follows whichever basis is live rather
     * than naming policy_reinsurance. Both the action it picks and the figures
     * it reads come from the seam; picking on one basis and reading on the
     * other would report a cession that belongs to neither.
     *
     * NOTE: This is derived from the policy's proportional split. Auto-
     * adjustment for event_limit / cash_loss_advise / fac-specific
     * policies isn't modelled. The numbers are an indicative view, not
     * a settlement figure.
     */
    public function reinsurance(int $claimId): JsonResponse
    {
        $claim = DB::table('claims')->where('id', $claimId)->first([
            'id', 'claim_number', 'policy_id', 'total_reserve', 'total_payment', 'balance',
        ]);
        if (!$claim || !$claim->policy_id) {
            return response()->json(['message' => 'Claim or linked policy not found.'], 404);
        }

        $policy = DB::table('policies')->where('id', $claim->policy_id)->first(['id', 'policyNumber', 'product_id']);

        // Pick the latest action that has RI rows — legacy engine only writes
        // per-action snapshots so we match the most recent one. Read through
        // the cession seam so the action and the figures below agree about
        // which basis is live; on legacy this is the query it always was.
        $cession       = app(CessionSource::class);
        $latestActionId = $cession->latestAllocatedActionFor((int) $claim->policy_id);

        if (!$latestActionId) {
            return response()->json([
                'claim'          => [
                    'id' => $claim->id, 'claim_number' => $claim->claim_number,
                    'total_reserve' => (float) ($claim->total_reserve ?? 0),
                    'total_payment' => (float) ($claim->total_payment ?? 0),
                    'balance'       => (float) ($claim->balance ?? 0),
                ],
                'policy'         => [
                    'id' => $policy->id ?? null,
                    'policy_number' => $policy->policyNumber ?? null,
                ],
                'has_allocation' => false,
                'message'        => 'No reinsurance allocation recorded for this policy. Policy is bound 100% net-retained OR the engine has not been run. Recalculate from the Policy → Reinsurance tab.',
                'breakdown'      => [],
                'totals'         => null,
            ]);
        }

        // Aggregate per treaty + type. The policy may split across multiple
        // treaties and (net retention vs quota share vs surplus vs fac).
        $rows = collect($cession->allocationTotalsForAction(
            (int) $claim->policy_id,
            (int) $latestActionId
        ));

        // Aggregate totals across all rows — so we can express the claim-
        // level ceded/net split.
        $totalPremium = (float) $rows->sum('premium_sum');
        $totalReserve = (float) ($claim->total_reserve ?? 0);
        $totalPayment = (float) ($claim->total_payment ?? 0);

        $breakdown = $rows->map(function ($r) use ($totalPremium, $totalReserve, $totalPayment) {
            $pct = $totalPremium > 0 ? ((float) $r->premium_sum) / $totalPremium : 0;
            return [
                'treaty_id'            => $r->treaty_id,
                'treaty_name'          => $r->treaty_name,
                'type_name'            => $r->type_name,
                'type_code'            => $r->type_code,
                'policy_premium_share' => round((float) $r->premium_sum, 2),
                'policy_si_share'      => round((float) $r->si_sum, 2),
                'share_pct'            => round($pct * 100, 2),                          // % of policy premium
                'est_reserve_ceded'    => round($totalReserve * $pct, 2),                 // pro-rata of current reserve
                'est_payment_ceded'    => round($totalPayment * $pct, 2),                 // pro-rata of current payments
                'is_retained'          => $r->type_code === 'NETRETENTION',
            ];
        });

        $retained = $breakdown->where('is_retained', true);
        $ceded    = $breakdown->where('is_retained', false);

        return response()->json([
            'claim' => [
                'id' => $claim->id, 'claim_number' => $claim->claim_number,
                'total_reserve' => $totalReserve,
                'total_payment' => $totalPayment,
                'balance'       => (float) ($claim->balance ?? 0),
            ],
            'policy' => [
                'id' => $policy->id ?? null,
                'policy_number' => $policy->policyNumber ?? null,
                'product_id'    => $policy->product_id ?? null,
            ],
            'source_action_id' => $latestActionId,
            'has_allocation'   => true,
            'breakdown'        => $breakdown->values(),
            'totals'           => [
                'policy_premium_total' => round($totalPremium, 2),
                'net_retention_pct'    => round($retained->sum('share_pct'), 2),
                'ceded_pct'            => round($ceded->sum('share_pct'), 2),
                'est_reserve_retained' => round($retained->sum('est_reserve_ceded'), 2),
                'est_reserve_ceded'    => round($ceded->sum('est_reserve_ceded'), 2),
                'est_payment_retained' => round($retained->sum('est_payment_ceded'), 2),
                'est_payment_ceded'    => round($ceded->sum('est_payment_ceded'), 2),
            ],
            'note' => 'Estimated split — based on the policy\'s proportional allocation. Does not apply event_limit / cash_loss_advise / fac-specific overrides. Use the reinsurance ledger for actual recoverable figures.',
        ]);
    }
}
