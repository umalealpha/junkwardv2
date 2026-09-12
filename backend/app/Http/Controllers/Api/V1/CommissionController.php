<?php
namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommissionController extends Controller
{
    // =========================================================================
    //  1. Commission Rules CRUD
    // =========================================================================

    /**
     * GET /commission/rules — List all commission rules (paginated, with product name).
     */
    public function rules(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'     => 'nullable|string|max:100',
            'product_id' => 'nullable|integer',
            'status'     => 'nullable|integer|in:0,1',
            'per_page'   => 'nullable|integer|min:5|max:100',
        ]);

        $query = DB::table('commission_rules')
            ->when($validated['product_id'] ?? null, fn($q, $v) => $q->where('commission_rules.product_id', $v))
            ->when(isset($validated['status']), fn($q) => $q->where('commission_rules.status', $validated['status']))
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where('commission_rules.rule_name', 'like', "%{$search}%");
            })
            ->orderBy('commission_rules.id', 'desc');

        $results = $query->paginate($validated['per_page'] ?? 25);

        // Batch-load product names
        $items = collect($results->items());
        $productIds = $items->pluck('product_id')->filter()->unique()->values()->toArray();
        $productMap = [];
        if (!empty($productIds)) {
            $productMap = DB::table('products')
                ->whereIn('id', $productIds)
                ->pluck('name', 'id')
                ->toArray();
        }

        return response()->json([
            'data' => $items->map(fn($r) => [
                'id'              => $r->id,
                'productId'       => $r->product_id,
                'productName'     => $productMap[$r->product_id] ?? null,
                'ruleName'        => $r->rule_name,
                'commissionType'  => $r->commission_type,
                'commissionValue' => $r->commission_value,
                'conditions'      => json_decode($r->conditions ?? '{}', true),
                'priority'        => $r->priority ?? 0,
                'status'          => $r->status,
                'createdAt'       => $r->created_at,
                'updatedAt'       => $r->updated_at,
            ]),
            'meta' => [
                'total'        => $results->total(),
                'per_page'     => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page'    => $results->lastPage(),
                'from'         => $results->firstItem(),
                'to'           => $results->lastItem(),
            ],
        ]);
    }

    /**
     * POST /commission/rules — Create a new commission rule.
     */
    public function storeRule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'       => 'nullable|integer|exists:products,id',
            'rule_name'        => 'required|string|max:200',
            'commission_type'  => 'required|string|in:amount,percentage',
            'commission_value' => 'required|numeric|min:0',
            'conditions'       => 'nullable',
            'priority'         => 'nullable|integer|min:0',
            'status'           => 'nullable|integer|in:0,1',
        ]);

        // Accept conditions as object or JSON string
        if (isset($data['conditions']) && is_array($data['conditions'])) {
            $data['conditions'] = json_encode($data['conditions']);
        }

        $data['status']     = $data['status'] ?? 1;
        $data['priority']   = $data['priority'] ?? 0;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('commission_rules')->insertGetId($data);

        return response()->json(['message' => 'Commission rule created.', 'data' => ['id' => $id]], 201);
    }

    /**
     * PUT /commission/rules/{id} — Update a commission rule.
     */
    public function updateRule(Request $request, int $id): JsonResponse
    {
        $exists = DB::table('commission_rules')->where('id', $id)->exists();
        if (!$exists) {
            return response()->json(['message' => 'Commission rule not found.'], 404);
        }

        $data = $request->validate([
            'product_id'       => 'nullable|integer|exists:products,id',
            'rule_name'        => 'required|string|max:200',
            'commission_type'  => 'required|string|in:amount,percentage',
            'commission_value' => 'required|numeric|min:0',
            'conditions'       => 'nullable',
            'priority'         => 'nullable|integer|min:0',
            'status'           => 'nullable|integer|in:0,1',
        ]);

        // Accept conditions as object or JSON string
        if (isset($data['conditions']) && is_array($data['conditions'])) {
            $data['conditions'] = json_encode($data['conditions']);
        }

        $data['status']     = $data['status'] ?? 1;
        $data['priority']   = $data['priority'] ?? 0;
        $data['updated_at'] = now();

        DB::table('commission_rules')->where('id', $id)->update($data);

        return response()->json(['message' => 'Commission rule updated.']);
    }

    /**
     * DELETE /commission/rules/{id} — Soft-delete (set status=0) or hard-delete a rule.
     */
    public function deleteRule(int $id): JsonResponse
    {
        $rule = DB::table('commission_rules')->where('id', $id)->first();
        if (!$rule) {
            return response()->json(['message' => 'Commission rule not found.'], 404);
        }

        // Soft-delete: set status to 0
        DB::table('commission_rules')->where('id', $id)->update([
            'status'     => 0,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Commission rule deactivated.']);
    }

    // =========================================================================
    //  2. Commission Targets CRUD
    // =========================================================================

    /**
     * GET /commission/targets — List all targets with optional product/agent filter.
     */
    public function targets(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'nullable|integer',
            'agent_id'   => 'nullable|integer',
            'agency_id'  => 'nullable|integer',
            'status'     => 'nullable|integer|in:0,1',
            'search'     => 'nullable|string|max:100',
            'per_page'   => 'nullable|integer|min:5|max:100',
        ]);

        $query = DB::table('commission_targets')
            ->when($validated['product_id'] ?? null, fn($q, $v) => $q->where('product_id', $v))
            ->when($validated['agent_id'] ?? null, fn($q, $v) => $q->where('agent_id', $v))
            ->when($validated['agency_id'] ?? null, fn($q, $v) => $q->where('agency_id', $v))
            ->when(isset($validated['status']), fn($q) => $q->where('status', $validated['status']))
            ->when($validated['search'] ?? null, fn($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('id', 'desc');

        $results = $query->paginate($validated['per_page'] ?? 25);

        // Batch-load product names, agent names, agency names
        $items = collect($results->items());

        $productIds = $items->pluck('product_id')->filter()->unique()->values()->toArray();
        $agentIds   = $items->pluck('agent_id')->filter()->unique()->values()->toArray();
        $agencyIds  = $items->pluck('agency_id')->filter()->unique()->values()->toArray();

        $productMap = !empty($productIds) ? DB::table('products')->whereIn('id', $productIds)->pluck('name', 'id')->toArray() : [];
        $agentMap   = !empty($agentIds) ? DB::table('agents')->whereIn('id', $agentIds)->select('id', DB::raw("CONCAT(firstName, ' ', lastName) as name"))->pluck('name', 'id')->toArray() : [];
        $agencyMap  = !empty($agencyIds) ? DB::table('agencies')->whereIn('id', $agencyIds)->pluck('name', 'id')->toArray() : [];

        return response()->json([
            'data' => $items->map(fn($r) => [
                'id'            => $r->id,
                'name'          => $r->name,
                'targetType'    => $r->target_type,
                'targetValue'   => $r->target_value,
                'periodType'    => $r->period_type,
                'periodDays'    => $r->period_days ?? null,
                'bonusType'     => $r->bonus_type ?? null,
                'bonusValue'    => $r->bonus_value ?? null,
                'productId'     => $r->product_id ?? null,
                'productName'   => $productMap[$r->product_id] ?? null,
                'agentId'       => $r->agent_id ?? null,
                'agentName'     => $agentMap[$r->agent_id] ?? null,
                'agencyId'      => $r->agency_id ?? null,
                'agencyName'    => $agencyMap[$r->agency_id] ?? null,
                'effectiveFrom' => $r->effective_from,
                'effectiveTo'   => $r->effective_to ?? null,
                'status'        => $r->status,
                'createdAt'     => $r->created_at,
                'updatedAt'     => $r->updated_at,
            ]),
            'meta' => [
                'total'        => $results->total(),
                'per_page'     => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page'    => $results->lastPage(),
                'from'         => $results->firstItem(),
                'to'           => $results->lastItem(),
            ],
        ]);
    }

    /**
     * POST /commission/targets — Create a new target.
     */
    public function storeTarget(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:200',
            'target_type'    => 'required|string|in:policy_count,premium_amount',
            'target_value'   => 'required|numeric|min:0',
            'period_type'    => 'required|string|in:monthly,quarterly,bimonthly,annual,custom',
            'period_days'    => 'nullable|integer|min:1|required_if:period_type,custom',
            'bonus_type'     => 'nullable|string|in:amount,percentage',
            'bonus_value'    => 'nullable|numeric|min:0',
            'product_id'     => 'nullable|integer|exists:products,id',
            'agent_id'       => 'nullable|integer',
            'agency_id'      => 'nullable|integer',
            'effective_from' => 'required|date',
            'effective_to'   => 'nullable|date|after_or_equal:effective_from',
            'status'         => 'nullable|integer|in:0,1',
        ]);

        $data['status']     = $data['status'] ?? 1;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('commission_targets')->insertGetId($data);

        return response()->json(['message' => 'Commission target created.', 'data' => ['id' => $id]], 201);
    }

    /**
     * PUT /commission/targets/{id} — Update a target.
     */
    public function updateTarget(Request $request, int $id): JsonResponse
    {
        $exists = DB::table('commission_targets')->where('id', $id)->exists();
        if (!$exists) {
            return response()->json(['message' => 'Commission target not found.'], 404);
        }

        $data = $request->validate([
            'name'           => 'required|string|max:200',
            'target_type'    => 'required|string|in:policy_count,premium_amount',
            'target_value'   => 'required|numeric|min:0',
            'period_type'    => 'required|string|in:monthly,quarterly,bimonthly,annual,custom',
            'period_days'    => 'nullable|integer|min:1|required_if:period_type,custom',
            'bonus_type'     => 'nullable|string|in:amount,percentage',
            'bonus_value'    => 'nullable|numeric|min:0',
            'product_id'     => 'nullable|integer|exists:products,id',
            'agent_id'       => 'nullable|integer',
            'agency_id'      => 'nullable|integer',
            'effective_from' => 'required|date',
            'effective_to'   => 'nullable|date|after_or_equal:effective_from',
            'status'         => 'nullable|integer|in:0,1',
        ]);

        $data['status']     = $data['status'] ?? 1;
        $data['updated_at'] = now();

        DB::table('commission_targets')->where('id', $id)->update($data);

        return response()->json(['message' => 'Commission target updated.']);
    }

    /**
     * DELETE /commission/targets/{id} — Soft-delete (set status=0).
     */
    public function deleteTarget(int $id): JsonResponse
    {
        $target = DB::table('commission_targets')->where('id', $id)->first();
        if (!$target) {
            return response()->json(['message' => 'Commission target not found.'], 404);
        }

        DB::table('commission_targets')->where('id', $id)->update([
            'status'     => 0,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Commission target deactivated.']);
    }

    // =========================================================================
    //  3. Commission Ledger / Report
    // =========================================================================

    /**
     * GET /commission/ledger — List commission ledger entries (paginated, with summary).
     */
    public function ledger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id'   => 'nullable|integer',
            'agency_id'  => 'nullable|integer',
            'status'     => 'nullable|string|in:pending,approved,paid,held,cancelled',
            'entry_type' => 'nullable|string|max:50',
            'date_from'  => 'nullable|date',
            'date_to'    => 'nullable|date',
            'search'     => 'nullable|string|max:100',
            'per_page'   => 'nullable|integer|min:5|max:100',
        ]);

        $baseQuery = DB::table('commission_ledger')
            ->when($validated['agent_id'] ?? null, fn($q, $v) => $q->where('agent_id', $v))
            ->when($validated['agency_id'] ?? null, fn($q, $v) => $q->where('agency_id', $v))
            ->when($validated['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($validated['entry_type'] ?? null, fn($q, $v) => $q->where('entry_type', $v))
            ->when($validated['date_from'] ?? null, fn($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($validated['date_to'] ?? null, fn($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('policy_id', 'like', "%{$search}%")
                      ->orWhere('reference', 'like', "%{$search}%");
                });
            });

        // Summary totals (computed from the filtered set, before pagination)
        $summaryQuery = (clone $baseQuery);
        $summary = $summaryQuery->selectRaw("
            SUM(CASE WHEN entry_type = 'earned' THEN commission_amount ELSE 0 END) as total_earned,
            SUM(CASE WHEN entry_type = 'clawback' THEN commission_amount ELSE 0 END) as total_clawback,
            SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END) as total_pending,
            SUM(CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END) as total_paid
        ")->first();

        $results = $baseQuery->orderBy('id', 'desc')->paginate($validated['per_page'] ?? 25);

        // Batch-load policy numbers and agent names
        $items    = collect($results->items());
        $policyIds = $items->pluck('policy_id')->filter()->unique()->values()->toArray();
        $agentIds  = $items->pluck('agent_id')->filter()->unique()->values()->toArray();

        $policyMap = !empty($policyIds) ? DB::table('policies')->whereIn('id', $policyIds)->pluck('policy_number', 'id')->toArray() : [];
        $agentMap  = !empty($agentIds) ? DB::table('agents')->whereIn('id', $agentIds)->select('id', DB::raw("CONCAT(firstName, ' ', lastName) as name"))->pluck('name', 'id')->toArray() : [];

        return response()->json([
            'data' => $items->map(fn($r) => [
                'id'           => $r->id,
                'policyId'     => $r->policy_id,
                'policyNumber' => $policyMap[$r->policy_id] ?? null,
                'agentId'      => $r->agent_id,
                'agentName'    => $agentMap[$r->agent_id] ?? null,
                'agencyId'     => $r->agency_id ?? null,
                'entryType'    => $r->entry_type,
                'amount'       => $r->commission_amount,
                'status'       => $r->status,
                'reference'    => $r->reference ?? null,
                'notes'        => $r->notes ?? null,
                'approvedBy'   => $r->approved_by ?? null,
                'approvedAt'   => $r->approved_at ?? null,
                'paidAt'       => $r->paid_at ?? null,
                'createdAt'    => $r->created_at,
                'updatedAt'    => $r->updated_at,
            ]),
            'summary' => [
                'totalEarned'   => (float) ($summary->total_earned ?? 0),
                'totalClawback' => (float) ($summary->total_clawback ?? 0),
                'totalPending'  => (float) ($summary->total_pending ?? 0),
                'totalPaid'     => (float) ($summary->total_paid ?? 0),
            ],
            'meta' => [
                'total'        => $results->total(),
                'per_page'     => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page'    => $results->lastPage(),
                'from'         => $results->firstItem(),
                'to'           => $results->lastItem(),
            ],
        ]);
    }

    /**
     * POST /commission/ledger/{id}/approve — Approve a pending ledger entry.
     */
    public function approveLedgerEntry(int $id): JsonResponse
    {
        $entry = DB::table('commission_ledger')->where('id', $id)->first();
        if (!$entry) {
            return response()->json(['message' => 'Ledger entry not found.'], 404);
        }
        if ($entry->status !== 'pending') {
            return response()->json(['message' => 'Only pending entries can be approved.'], 422);
        }

        DB::table('commission_ledger')->where('id', $id)->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'updated_at'  => now(),
        ]);

        return response()->json(['message' => 'Ledger entry approved.']);
    }

    /**
     * POST /commission/ledger/bulk-approve — Approve multiple ledger entries by IDs.
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $ids = $data['ids'];

        // Only approve entries that are currently pending
        $updated = DB::table('commission_ledger')
            ->whereIn('id', $ids)
            ->where('status', 'pending')
            ->update([
                'status'      => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'updated_at'  => now(),
            ]);

        return response()->json([
            'message'  => "{$updated} entries approved.",
            'approved' => $updated,
            'total'    => count($ids),
        ]);
    }

    // =========================================================================
    // =========================================================================
    //  3b. Run Calculation & Test Rules
    // =========================================================================

    /**
     * POST /commission/run — Trigger commission calculation.
     * Runs synchronously for small batches (≤100), returns results immediately.
     * Shows exactly which rules matched which policies.
     */
    public function runCalculation(Request $request): JsonResponse
    {
        $days  = min((int) $request->input('days', 7), 365);
        $limit = min((int) $request->input('limit', 50), 500);
        $dryRun = (bool) $request->input('dry_run', false);

        $since = now()->subDays($days);
        $query = DB::table('policies as p')
            ->leftJoin('commission_ledger as cl', function ($j) {
                $j->on('cl.policy_id', '=', 'p.id')->where('cl.entry_type', '=', 'earned');
            })
            ->whereNull('cl.id')
            ->where('p.status', 1)
            ->where('p.agent_id', '>', 0)
            ->where('p.created_at', '>=', $since)
            ->select('p.id as policy_id', 'p.policyNumber', 'p.agent_id', 'p.product_id', 'p.premium', 'p.customer_id', 'p.created_at')
            ->orderBy('p.id')
            ->limit($limit);

        $policies = $query->get();

        // Batch-load product names
        $productMap = DB::table('products')->pluck('name', 'id')->toArray();

        $results = [];
        $matched = 0;
        $totalAmount = 0;

        foreach ($policies as $policy) {
            $rules = DB::table('commission_rules')
                ->where(function ($q) use ($policy) {
                    $q->where('product_id', $policy->product_id)->orWhereNull('product_id');
                })
                ->where('status', 1)
                ->orderByDesc('priority')
                ->get();

            $matchedRule = null;
            $failReasons = [];

            foreach ($rules as $rule) {
                $conditions = json_decode($rule->conditions ?? '{}', true) ?: [];
                $passed = true;
                $reasons = [];

                if (!empty($conditions['kyc_required'])) {
                    $ok = DB::table('customer_kyc')->where('customer_id', $policy->customer_id)->where('compliance', 1)->exists();
                    if (!$ok) { $passed = false; $reasons[] = 'KYC not compliant'; }
                }
                if ($passed && !empty($conditions['preinspection_required'])) {
                    $ok = DB::table('vehicle')->where('policy_id', $policy->policy_id)->where('compliance', 1)->exists();
                    if (!$ok) { $passed = false; $reasons[] = 'Preinspection missing'; }
                }
                if ($passed && !empty($conditions['min_active_months'])) {
                    $months = now()->diffInMonths($policy->created_at);
                    if ($months < $conditions['min_active_months']) { $passed = false; $reasons[] = "Active {$months}mo < required {$conditions['min_active_months']}mo"; }
                }

                if ($passed) {
                    $matchedRule = $rule;
                    break;
                }
                $failReasons[] = ['rule' => $rule->rule_name, 'reasons' => $reasons];
            }

            $amount = 0;
            if ($matchedRule) {
                $premium = (float) $policy->premium;
                $amount = $matchedRule->commission_type === 'percentage'
                    ? round($premium * (float) $matchedRule->commission_value / 100, 2)
                    : (float) $matchedRule->commission_value;
                $matched++;
                $totalAmount += $amount;

                if (!$dryRun) {
                    $agencyId = DB::table('users')->where('id', $policy->agent_id)->value('agency_id');
                    DB::table('commission_ledger')->insert([
                        'policy_id' => $policy->policy_id, 'agent_id' => $policy->agent_id,
                        'agency_id' => $agencyId, 'rule_id' => $matchedRule->id,
                        'entry_type' => 'earned', 'commission_amount' => $amount,
                        'premium_amount' => $premium, 'commission_type' => $matchedRule->commission_type,
                        'status' => 'pending', 'qualifying_date' => \Carbon\Carbon::parse($policy->created_at)->toDateString(),
                        'cooling_period_end' => \Carbon\Carbon::parse($policy->created_at)->toDateString(),
                        'payment_gate_met' => 0,
                        'kyc_compliant' => DB::table('customer_kyc')->where('customer_id', $policy->customer_id)->where('compliance', 1)->exists() ? 1 : 0,
                        'preinspection_compliant' => 0,
                        'calculated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }

            $results[] = [
                'policy_id'     => $policy->policy_id,
                'policy_number' => $policy->policyNumber,
                'product'       => $productMap[$policy->product_id] ?? "Product #{$policy->product_id}",
                'premium'       => (float) $policy->premium,
                'agent_id'      => $policy->agent_id,
                'matched_rule'  => $matchedRule ? $matchedRule->rule_name : null,
                'commission'    => $amount,
                'status'        => $matchedRule ? ($dryRun ? 'would_create' : 'created') : 'no_match',
                'fail_reasons'  => $matchedRule ? [] : $failReasons,
            ];
        }

        // Track the run
        DB::table('commission_runs')->insert([
            'triggered_by' => auth()->id(),
            'days' => $days,
            'status' => 'completed',
            'policies_found' => $policies->count(),
            'policies_processed' => $policies->count(),
            'commissions_created' => $dryRun ? 0 : $matched,
            'stats' => json_encode(['matched' => $matched, 'total_amount' => $totalAmount, 'dry_run' => $dryRun]),
            'started_at' => now(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'summary' => [
                'dry_run'     => $dryRun,
                'days'        => $days,
                'total'       => $policies->count(),
                'matched'     => $matched,
                'no_match'    => $policies->count() - $matched,
                'total_commission' => round($totalAmount, 2),
            ],
            'results' => $results,
        ]);
    }

    /**
     * GET /commission/runs — History of calculation runs.
     */
    public function runs(): JsonResponse
    {
        $runs = DB::table('commission_runs')->orderByDesc('id')->limit(20)->get();
        return response()->json(['data' => $runs]);
    }

    //  4. Dashboard Stats
    // =========================================================================

    /**
     * GET /commission/dashboard — Summary cards for the commission dashboard.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd   = now()->endOfMonth()->toDateString();

        $totalPending = DB::table('commission_ledger')
            ->where('status', 'pending')
            ->sum('commission_amount');

        $totalApproved = DB::table('commission_ledger')
            ->where('status', 'approved')
            ->sum('commission_amount');

        $totalPaidThisMonth = DB::table('commission_ledger')
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$monthStart, $monthEnd])
            ->sum('commission_amount');

        $totalClawbackThisMonth = DB::table('commission_ledger')
            ->where('entry_type', 'clawback')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('commission_amount');

        $openFraudAlerts = DB::table('commission_fraud_alerts')
            ->whereIn('status', ['open', 'reviewing'])
            ->count();

        // Top 5 agents by earned commission this month
        $topAgents = DB::table('commission_ledger')
            ->where('entry_type', 'earned')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->select('agent_id', DB::raw('SUM(commission_amount) as total'))
            ->groupBy('agent_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // Batch-load agent names for top agents
        $agentIds = $topAgents->pluck('agent_id')->filter()->unique()->values()->toArray();
        $agentMap = !empty($agentIds)
            ? DB::table('agents')->whereIn('id', $agentIds)->select('id', DB::raw("CONCAT(firstName, ' ', lastName) as name"))->pluck('name', 'id')->toArray()
            : [];

        return response()->json([
            'data' => [
                'totalPending'          => (float) $totalPending,
                'totalApproved'         => (float) $totalApproved,
                'totalPaidThisMonth'    => (float) $totalPaidThisMonth,
                'totalClawbackThisMonth'=> (float) $totalClawbackThisMonth,
                'openFraudAlerts'       => $openFraudAlerts,
                'topAgents'             => $topAgents->map(fn($r) => [
                    'agentId'   => $r->agent_id,
                    'agentName' => $agentMap[$r->agent_id] ?? null,
                    'total'     => (float) $r->total,
                ])->values(),
            ],
        ]);
    }

    // =========================================================================
    //  5. Fraud Alerts
    // =========================================================================

    /**
     * GET /commission/fraud-alerts — List fraud alerts (paginated).
     */
    public function fraudAlerts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status'     => 'nullable|string|in:open,reviewing,resolved,dismissed',
            'alert_type' => 'nullable|string|max:50',
            'severity'   => 'nullable|string|in:low,medium,high,critical',
            'agent_id'   => 'nullable|integer',
            'date_from'  => 'nullable|date',
            'date_to'    => 'nullable|date',
            'search'     => 'nullable|string|max:100',
            'per_page'   => 'nullable|integer|min:5|max:100',
        ]);

        $query = DB::table('commission_fraud_alerts')
            ->when($validated['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($validated['alert_type'] ?? null, fn($q, $v) => $q->where('alert_type', $v))
            ->when($validated['severity'] ?? null, fn($q, $v) => $q->where('severity', $v))
            ->when($validated['agent_id'] ?? null, fn($q, $v) => $q->where('agent_id', $v))
            ->when($validated['date_from'] ?? null, fn($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($validated['date_to'] ?? null, fn($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                      ->orWhere('alert_type', 'like', "%{$search}%");
                });
            })
            ->orderBy('id', 'desc');

        $results = $query->paginate($validated['per_page'] ?? 25);

        // Batch-load agent names and policy numbers
        $items     = collect($results->items());
        $agentIds  = $items->pluck('agent_id')->filter()->unique()->values()->toArray();
        $policyIds = $items->pluck('policy_id')->filter()->unique()->values()->toArray();

        $agentMap  = !empty($agentIds) ? DB::table('agents')->whereIn('id', $agentIds)->select('id', DB::raw("CONCAT(firstName, ' ', lastName) as name"))->pluck('name', 'id')->toArray() : [];
        $policyMap = !empty($policyIds) ? DB::table('policies')->whereIn('id', $policyIds)->pluck('policy_number', 'id')->toArray() : [];

        return response()->json([
            'data' => $items->map(fn($r) => [
                'id'             => $r->id,
                'agentId'        => $r->agent_id,
                'agentName'      => $agentMap[$r->agent_id] ?? null,
                'policyId'       => $r->policy_id ?? null,
                'policyNumber'   => $policyMap[$r->policy_id] ?? null,
                'alertType'      => $r->alert_type,
                'severity'       => $r->severity,
                'description'    => $r->description,
                'status'         => $r->status,
                'reviewedBy'     => $r->reviewed_by ?? null,
                'reviewedAt'     => $r->reviewed_at ?? null,
                'resolutionNote' => $r->resolution_note ?? null,
                'createdAt'      => $r->created_at,
                'updatedAt'      => $r->updated_at,
            ]),
            'meta' => [
                'total'        => $results->total(),
                'per_page'     => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page'    => $results->lastPage(),
                'from'         => $results->firstItem(),
                'to'           => $results->lastItem(),
            ],
        ]);
    }

    /**
     * POST /commission/fraud-alerts/{id}/review — Update alert status + resolution note.
     */
    public function reviewFraudAlert(Request $request, int $id): JsonResponse
    {
        $alert = DB::table('commission_fraud_alerts')->where('id', $id)->first();
        if (!$alert) {
            return response()->json(['message' => 'Fraud alert not found.'], 404);
        }

        $data = $request->validate([
            'status'          => 'required|string|in:reviewing,resolved,dismissed',
            'resolution_note' => 'nullable|string|max:2000',
        ]);

        $update = [
            'status'     => $data['status'],
            'updated_at' => now(),
        ];

        if (!empty($data['resolution_note'])) {
            $update['resolution_note'] = $data['resolution_note'];
        }

        if (in_array($data['status'], ['resolved', 'dismissed'])) {
            $update['reviewed_by'] = auth()->id();
            $update['reviewed_at'] = now();
        }

        DB::table('commission_fraud_alerts')->where('id', $id)->update($update);

        return response()->json(['message' => 'Fraud alert updated.']);
    }

    // =========================================================================
    //  6. Agent Commission Summary
    // =========================================================================

    /**
     * GET /commission/agent/{agentId}/summary — Agent-specific commission summary.
     */
    public function agentSummary(Request $request, int $agentId): JsonResponse
    {
        $agent = DB::table('agents')->where('id', $agentId)->first();
        if (!$agent) {
            return response()->json(['message' => 'Agent not found.'], 404);
        }

        $monthStart   = now()->startOfMonth()->toDateString();
        $monthEnd     = now()->endOfMonth()->toDateString();
        $quarterStart = now()->firstOfQuarter()->toDateString();
        $quarterEnd   = now()->lastOfQuarter()->toDateString();

        // Commission totals
        $totalEarnedAllTime = DB::table('commission_ledger')
            ->where('agent_id', $agentId)
            ->where('entry_type', 'earned')
            ->sum('commission_amount');

        $totalEarnedThisMonth = DB::table('commission_ledger')
            ->where('agent_id', $agentId)
            ->where('entry_type', 'earned')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('commission_amount');

        $totalEarnedThisQuarter = DB::table('commission_ledger')
            ->where('agent_id', $agentId)
            ->where('entry_type', 'earned')
            ->whereBetween('created_at', [$quarterStart, $quarterEnd])
            ->sum('commission_amount');

        // Status-based totals
        $statusTotals = DB::table('commission_ledger')
            ->where('agent_id', $agentId)
            ->selectRaw("
                SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END) as total_pending,
                SUM(CASE WHEN status = 'approved' THEN commission_amount ELSE 0 END) as total_approved,
                SUM(CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END) as total_paid
            ")
            ->first();

        // Active policies count for this agent
        $activePolicies = DB::table('policies')
            ->where('agent_id', $agentId)
            ->where('status', 'active')
            ->count();

        // Cancellation rate: cancelled / total policies
        $totalPolicies = DB::table('policies')
            ->where('agent_id', $agentId)
            ->count();
        $cancelledPolicies = DB::table('policies')
            ->where('agent_id', $agentId)
            ->where('status', 'cancelled')
            ->count();
        $cancellationRate = $totalPolicies > 0 ? round(($cancelledPolicies / $totalPolicies) * 100, 2) : 0;

        // KYC compliance rate: customers with approved KYC / total customers for agent
        $agentCustomerIds = DB::table('policies')
            ->where('agent_id', $agentId)
            ->pluck('customer_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $kycCompliance = 0;
        if (!empty($agentCustomerIds)) {
            $totalCustomers = count($agentCustomerIds);
            $compliantCustomers = DB::table('customers')
                ->whereIn('id', $agentCustomerIds)
                ->where('kyc_status', 'approved')
                ->count();
            $kycCompliance = $totalCustomers > 0 ? round(($compliantCustomers / $totalCustomers) * 100, 2) : 0;
        }

        // Target achievements: match agent targets and compute progress
        $targets = DB::table('commission_targets')
            ->where('status', 1)
            ->where(function ($q) use ($agentId) {
                $q->where('agent_id', $agentId)
                  ->orWhereNull('agent_id'); // global targets also apply
            })
            ->where('effective_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', now());
            })
            ->get();

        $targetAchievements = $targets->map(function ($target) use ($agentId) {
            $achieved = 0;

            // Determine period range
            $periodStart = $this->getTargetPeriodStart($target);
            $periodEnd   = now()->toDateString();

            if ($target->target_type === 'policy_count') {
                $query = DB::table('policies')
                    ->where('agent_id', $agentId)
                    ->whereBetween('created_at', [$periodStart, $periodEnd]);
                if ($target->product_id) {
                    $query->where('product_id', $target->product_id);
                }
                $achieved = $query->count();
            } elseif ($target->target_type === 'premium_amount') {
                $query = DB::table('commission_ledger')
                    ->where('agent_id', $agentId)
                    ->where('entry_type', 'earned')
                    ->whereBetween('created_at', [$periodStart, $periodEnd]);
                $achieved = $query->sum('commission_amount');
            }

            $progress = $target->target_value > 0 ? round(($achieved / $target->target_value) * 100, 2) : 0;

            return [
                'targetId'    => $target->id,
                'name'        => $target->name,
                'targetType'  => $target->target_type,
                'targetValue' => $target->target_value,
                'achieved'    => (float) $achieved,
                'progress'    => min($progress, 100),
                'periodType'  => $target->period_type,
                'bonusType'   => $target->bonus_type ?? null,
                'bonusValue'  => $target->bonus_value ?? null,
            ];
        })->values();

        return response()->json([
            'data' => [
                'agentId'               => $agentId,
                'agentName'             => trim(($agent->firstName ?? '') . ' ' . ($agent->lastName ?? '')),
                'totalEarnedAllTime'    => (float) $totalEarnedAllTime,
                'totalEarnedThisMonth'  => (float) $totalEarnedThisMonth,
                'totalEarnedThisQuarter'=> (float) $totalEarnedThisQuarter,
                'totalPending'          => (float) ($statusTotals->total_pending ?? 0),
                'totalApproved'         => (float) ($statusTotals->total_approved ?? 0),
                'totalPaid'             => (float) ($statusTotals->total_paid ?? 0),
                'activePolicies'        => $activePolicies,
                'cancellationRate'      => $cancellationRate,
                'kycComplianceRate'     => $kycCompliance,
                'targetAchievements'    => $targetAchievements,
            ],
        ]);
    }

    // =========================================================================
    //  Helpers
    // =========================================================================

    /**
     * Compute the start date for a target's current period.
     */
    private function getTargetPeriodStart(object $target): string
    {
        return match ($target->period_type) {
            'monthly'   => now()->startOfMonth()->toDateString(),
            'quarterly' => now()->firstOfQuarter()->toDateString(),
            'bimonthly' => now()->startOfMonth()->subMonth((now()->month - 1) % 2)->toDateString(),
            'annual'    => now()->startOfYear()->toDateString(),
            'custom'    => now()->subDays($target->period_days ?? 30)->toDateString(),
            default     => now()->startOfMonth()->toDateString(),
        };
    }
}
