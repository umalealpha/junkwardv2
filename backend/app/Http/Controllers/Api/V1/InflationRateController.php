<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\InflationRateMaster;
use AlphaDirect\Services\Inflation\InflationRateResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Inflation rate master CRUD — the screen UW uses to set the renewal
 * sum-insured uplift per product / coverage / sum-insured band.
 *
 * The rules written here are what `php artisan policy:inflate-buildings-si
 * --master` reads: the percent is never typed on the command line again.
 *
 * Writes go through the InflationRateMaster MODEL rather than the query builder
 * (the Lawyers/Assessors convention) on purpose — the model is Auditable, and a
 * row here moves premium on live renewals, so every edit to a percent, a band
 * or a date has to be answerable from `audits`.
 *
 * Endpoints
 *   GET    inflation-rates              list (filters: product_id, active, search)
 *   GET    inflation-rates/options      products + their sections + each section's lines
 *   GET    inflation-rates/applied      what has actually been uplifted (audit view)
 *   GET    inflation-rates/{id}         one rule
 *   GET    inflation-rates/{id}/impact  indicative "what would move" for one rule
 *   POST   inflation-rates              create
 *   PUT    inflation-rates/{id}         update
 *   DELETE inflation-rates/{id}         delete
 */
class InflationRateController extends Controller
{
    /** GET /inflation-rates */
    public function index(Request $request): JsonResponse
    {
        $query = InflationRateMaster::query()
            ->orderByDesc('is_active')
            ->orderByDesc('priority')
            ->orderBy('product_id')
            ->orderBy('id');

        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->input('product_id'));
        }

        if ($request->has('active') && $request->input('active') !== '') {
            $query->where('is_active', (int) (bool) $request->input('active'));
        }

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('coverage_name', 'like', "%{$s}%")
                ->orWhere('sub_coverage_name', 'like', "%{$s}%")
                ->orWhere('notes', 'like', "%{$s}%"));
        }

        $rules = $query->get();

        return response()->json([
            'data' => $rules->map(fn (InflationRateMaster $r) => $this->present($r))->values(),
        ]);
    }

    /** GET /inflation-rates/{id} */
    public function show(int $id): JsonResponse
    {
        $rule = InflationRateMaster::find($id);

        if (!$rule) {
            return response()->json(['message' => 'Inflation rule not found.'], 404);
        }

        return response()->json(['data' => $this->present($rule)]);
    }

    /** POST /inflation-rates */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $rule = InflationRateMaster::create($data);

        return response()->json([
            'message' => 'Inflation rule created.',
            'data'    => $this->present($rule),
        ], 201);
    }

    /** PUT /inflation-rates/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $rule = InflationRateMaster::find($id);

        if (!$rule) {
            return response()->json(['message' => 'Inflation rule not found.'], 404);
        }

        $rule->fill($this->validated($request))->save();

        return response()->json([
            'message' => 'Inflation rule updated.',
            'data'    => $this->present($rule->refresh()),
        ]);
    }

    /**
     * DELETE /inflation-rates/{id}
     *
     * A rule that has already uplifted live sums insured is NOT deleted — the
     * inflation_applied_log rows point at it and are the audit answer to "who
     * decided this policy's increase". Deactivate it instead: an inactive rule
     * never fires again but stays readable.
     */
    public function destroy(int $id): JsonResponse
    {
        $rule = InflationRateMaster::find($id);

        if (!$rule) {
            return response()->json(['message' => 'Inflation rule not found.'], 404);
        }

        $applied = DB::table('inflation_applied_log')->where('rule_id', $id)->count();

        if ($applied > 0) {
            return response()->json([
                'message' => "This rule has already uplifted {$applied} line(s) on live quotes and cannot be deleted. Switch it to inactive instead — the audit trail must keep pointing at it.",
            ], 422);
        }

        $rule->delete();

        return response()->json(['message' => 'Inflation rule deleted.']);
    }

    /**
     * GET /inflation-rates/options
     *
     * Everything the form's dropdowns need: the products that carry coverages,
     * each product's sections, and each section's lines (the Descriptions the
     * wizard shows). Picking from here writes coverage_id / sub_coverage_id —
     * an exact match — instead of a hand-typed name.
     */
    public function options(Request $request): JsonResponse
    {
        $productId = $request->filled('product_id') ? (int) $request->input('product_id') : null;

        $products = DB::table('products')
            ->whereIn('id', function ($q) {
                $q->select('product_id')->distinct()->from('product_coverage');
            })
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn ($p) => ['id' => (int) $p->id, 'name' => (string) $p->name])
            ->values();

        $sections = collect();

        if ($productId !== null) {
            // Sections the wizard actually shows for this product. s_DISPLAYTOUSER
            // matters: without it the list carries hidden/legacy master rows the
            // user has never seen on a quote.
            $sectionRows = DB::table('product_coverage as pcv')
                ->join('tb_cvgpccoverages as sec', 'sec.id', '=', 'pcv.coverage_id')
                ->where('pcv.product_id', $productId)
                ->where('sec.s_UsageType', 'PARENT')
                ->where('sec.s_DISPLAYTOUSER', '1')
                ->orderBy('sec.n_DisplaySequence')
                ->orderBy('sec.id')
                ->get(['sec.id', 'sec.s_ScreenName', 'sec.s_CoverageCode']);

            // The lines under those sections — one query for the whole product.
            $codes = $sectionRows->pluck('s_CoverageCode')->filter()->unique()->all();

            $lineRows = empty($codes) ? collect() : DB::table('tb_cvgpccoverages')
                ->whereIn('s_ParentCoverageCode', $codes)
                ->where('s_UsageType', 'CHILD')
                ->where('s_DISPLAYTOUSER', '1')
                ->orderBy('n_DisplaySequence')
                ->orderBy('id')
                ->get(['id', 's_ScreenName', 's_ParentCoverageCode'])
                ->groupBy('s_ParentCoverageCode');

            // The coverage master carries the SAME Description on many rows (a
            // dozen "Property" children under one section, sections repeated per
            // legacy variant). A dropdown must show each name ONCE, and a rule on
            // a name that covers several master rows has to match them all — so a
            // deduped entry keeps every id it stands for and exposes a single id
            // only when the name is unambiguous.
            $sections = $this->dedupeByName($sectionRows, 's_ScreenName')
                ->map(function (array $entry) use ($lineRows) {
                    $codes = collect($entry['rows'])->pluck('s_CoverageCode')->filter()->unique();

                    $children = $codes->flatMap(fn ($code) => collect($lineRows->get($code, [])));

                    return [
                        'id'         => $entry['id'],
                        'ids'        => $entry['ids'],
                        'name'       => $entry['name'],
                        'duplicates' => count($entry['ids']),
                        'codes'      => $codes->values()->all(),
                        'lines'      => $this->dedupeByName($children, 's_ScreenName')
                            ->map(fn (array $l) => [
                                'id'         => $l['id'],
                                'ids'        => $l['ids'],
                                'name'       => $l['name'],
                                'duplicates' => count($l['ids']),
                            ])->values()->all(),
                    ];
                })->values();
        }

        return response()->json([
            'products'          => $products,
            'sections'          => $sections,
            'transaction_types' => ['ANNIVERSARY-RENEW', 'RENEW'],
        ]);
    }

    /**
     * GET /inflation-rates/applied
     *
     * What actually moved. Reads inflation_applied_log — the same rows that stop
     * a re-run compounding an uplift — so UW can see per policy which rule
     * decided the increase and what the sum insured went from and to.
     */
    public function applied(Request $request): JsonResponse
    {
        $limit = min(500, max(1, (int) $request->input('limit', 100)));

        $rows = DB::table('inflation_applied_log as l')
            ->leftJoin('policies as p', 'p.id', '=', 'l.policy_id')
            ->leftJoin('inflation_rate_master as r', 'r.id', '=', 'l.rule_id')
            ->leftJoin('tb_cvgpccoverages as sub', 'sub.id', '=', 'l.coverage_id')
            ->when($request->filled('rule_id'), fn ($q) => $q->where('l.rule_id', (int) $request->input('rule_id')))
            ->when($request->filled('policy'), fn ($q) => $q->where('p.policyNumber', 'like', '%' . $request->input('policy') . '%'))
            ->orderByDesc('l.id')
            ->limit($limit)
            ->get([
                'l.id', 'l.rule_id', 'l.action_id', 'l.pct', 'l.si_before', 'l.si_after',
                'l.premium_before', 'l.premium_after', 'l.run_marker', 'l.created_at',
                'p.policyNumber', 'r.name as rule_name', 'sub.s_ScreenName as line_name',
            ]);

        return response()->json([
            'data'  => $rows->map(fn ($r) => [
                'id'             => (int) $r->id,
                'rule_id'        => (int) $r->rule_id,
                'rule_name'      => $r->rule_name,
                'policy_number'  => $r->policyNumber,
                'action_id'      => (int) $r->action_id,
                'line'           => $r->line_name,
                'pct'            => (float) $r->pct,
                'si_before'      => (float) $r->si_before,
                'si_after'       => (float) $r->si_after,
                'premium_before' => (float) $r->premium_before,
                'premium_after'  => (float) $r->premium_after,
                'run_marker'     => $r->run_marker,
                'applied_at'     => (string) $r->created_at,
            ])->values(),
            'total' => (int) DB::table('inflation_applied_log')
                ->when($request->filled('rule_id'), fn ($q) => $q->where('rule_id', (int) $request->input('rule_id')))
                ->count(),
        ]);
    }

    /**
     * GET /inflation-rates/{id}/impact
     *
     * Indicative "what would this rule move" for the screen, so a percent is
     * never saved blind.
     *
     * HONEST LIMITS — this is an ESTIMATE, and the command's dry run
     * (`--master --rule={id}`, no --apply) stays authoritative:
     *   - it counts every line THIS rule matches; where a more specific rule
     *     also matches a line, that rule wins at run time and this over-counts,
     *   - it does not apply the cancel / lapse guard or the --skip opt-outs,
     *   - premium after is the wizard formula (SI × rate / 100); a line with no
     *     rate keeps its captured premium, exactly as the command does.
     */
    public function impact(Request $request, int $id): JsonResponse
    {
        $rule = InflationRateMaster::find($id);

        if (!$rule) {
            return response()->json(['message' => 'Inflation rule not found.'], 404);
        }

        $factor = 1 + ((float) $rule->pct / 100);

        $base = fn () => $this->matchedLinesQuery($rule);

        $totals = $base()
            ->selectRaw('COUNT(*) as lines_count')
            ->selectRaw('COUNT(DISTINCT a.id) as actions_count')
            ->selectRaw('COUNT(DISTINCT p.id) as policies_count')
            ->selectRaw('COALESCE(SUM(d.coverage_value), 0) as si_before')
            ->selectRaw('COALESCE(SUM(d.calculated_value), 0) as premium_before')
            ->selectRaw('COALESCE(SUM(CASE WHEN d.rate > 0 THEN d.coverage_value * ? * d.rate / 100 ELSE d.calculated_value END), 0) as premium_after', [$factor])
            ->first();

        $sample = $base()
            ->orderBy('a.effective_from')
            ->orderBy('p.policyNumber')
            ->limit(min(200, max(1, (int) $request->input('limit', 25))))
            ->get([
                'p.policyNumber', 'p.product_id', 'a.id as action_id', 'a.effective_from',
                'sec.s_ScreenName as section', 'sub.s_ScreenName as line',
                'd.id as detail_id', 'd.coverage_value', 'd.rate', 'd.calculated_value',
            ])
            ->map(fn ($r) => [
                'policy_number'  => $r->policyNumber,
                'product_id'     => (int) $r->product_id,
                'action_id'      => (int) $r->action_id,
                'effective_from' => substr((string) $r->effective_from, 0, 10),
                'section'        => $r->section,
                'line'           => $r->line,
                'si_before'      => (float) $r->coverage_value,
                'si_after'       => round((float) $r->coverage_value * $factor, 2),
                'premium_before' => (float) $r->calculated_value,
                'premium_after'  => (float) $r->rate > 0
                    ? round(round((float) $r->coverage_value * $factor, 2) * (float) $r->rate / 100, 2)
                    : (float) $r->calculated_value,
            ])->values();

        $siBefore = (float) ($totals->si_before ?? 0);

        return response()->json([
            'rule'    => $this->present($rule),
            'summary' => [
                'policies'        => (int) ($totals->policies_count ?? 0),
                'actions'         => (int) ($totals->actions_count ?? 0),
                'lines'           => (int) ($totals->lines_count ?? 0),
                'si_before'       => round($siBefore, 2),
                'si_after'        => round($siBefore * $factor, 2),
                'premium_before'  => round((float) ($totals->premium_before ?? 0), 2),
                'premium_after'   => round((float) ($totals->premium_after ?? 0), 2),
                'already_applied' => (int) DB::table('inflation_applied_log')->where('rule_id', $id)->count(),
            ],
            'sample'  => $sample,
            'note'    => 'Estimate. Counts every line this rule matches — a more specific rule may claim some of them at run time — and does not apply the cancel/lapse guard or per-policy opt-outs. Confirm with: php artisan policy:inflate-buildings-si --master --rule=' . $id,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // Internals
    // ──────────────────────────────────────────────────────────────────

    /**
     * Collapse coverage-master rows that carry the SAME name into one entry.
     *
     * The legacy master is full of same-named siblings — a dozen "Property"
     * children under one section, the same section name repeated per variant.
     * A dropdown showing that name twelve times is unusable and, worse, invites
     * a rule pinned to ONE of the twelve ids that then silently misses the other
     * eleven on live quotes.
     *
     * So: one entry per name. `ids` keeps every master row it stands for, and
     * `id` is filled ONLY when the name is unambiguous — where it is not, the
     * rule is saved by NAME, which the resolver matches against every one of
     * them (that is what coverage_name / sub_coverage_name are for).
     *
     * Names are compared case- and whitespace-insensitively, the same rule the
     * resolver and the cron use, so "SUM  INSURED" and "Sum Insured" collapse.
     *
     * @param  \Illuminate\Support\Collection<int,object> $rows
     * @return \Illuminate\Support\Collection<int,array{id:int|null,ids:array<int,int>,name:string,rows:array<int,object>}>
     */
    private function dedupeByName($rows, string $nameColumn)
    {
        $grouped = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row->{$nameColumn} ?? ''));

            if ($name === '') {
                continue;                       // unnamed master row — nothing to show
            }

            $key = InflationRateMaster::normalise($name);

            if (!isset($grouped[$key])) {
                $grouped[$key] = ['name' => $name, 'ids' => [], 'rows' => []];
            }

            $grouped[$key]['ids'][]  = (int) $row->id;
            $grouped[$key]['rows'][] = $row;
        }

        return collect(array_values($grouped))->map(fn (array $entry) => [
            'id'   => count($entry['ids']) === 1 ? $entry['ids'][0] : null,
            'ids'  => $entry['ids'],
            'name' => $entry['name'],
            'rows' => $entry['rows'],
        ]);
    }

    /**
     * The live quote lines one rule matches — the same matchers the resolver
     * applies, expressed in SQL so the screen can count them.
     *
     * QUOTE only and policy status != 2, mirroring the command: an issued
     * anniversary is already invoiced and needs an endorsement, not a re-price.
     */
    private function matchedLinesQuery(InflationRateMaster $rule)
    {
        return DB::table('policy_coverage_detail as d')
            ->join('policy_coverages as pc', 'pc.id', '=', 'd.policy_coverage_id')
            ->join('policy_actions as a', 'a.id', '=', 'pc.action_id')
            ->join('policies as p', 'p.id', '=', 'pc.policy_id')
            ->join('tb_cvgpccoverages as sec', 'sec.id', '=', 'pc.coverage_id')
            ->leftJoin('tb_cvgpccoverages as sub', 'sub.id', '=', 'd.coverage_id')
            ->whereNull('d.deleted_at')
            ->whereNull('pc.deleted_at')
            ->whereNull('a.deleted_at')
            ->where('d.coverage_value', '>', 0)
            ->where('a.status', 'QUOTE')
            ->where('p.status', '!=', 2)
            ->when($rule->product_id, fn ($q) => $q->where('p.product_id', (int) $rule->product_id))
            ->when(trim((string) $rule->transaction_type) !== '', fn ($q) => $q->where('a.transaction_type', $rule->transaction_type))
            ->when($rule->effective_from, fn ($q) => $q->whereDate('a.effective_from', '>=', substr((string) $rule->effective_from, 0, 10)))
            ->when($rule->effective_to, fn ($q) => $q->whereDate('a.effective_from', '<=', substr((string) $rule->effective_to, 0, 10)))
            ->when($rule->coverage_id, fn ($q) => $q->where('pc.coverage_id', (int) $rule->coverage_id))
            ->when(!$rule->coverage_id && trim((string) $rule->coverage_name) !== '',
                fn ($q) => $q->where('sec.s_ScreenName', $rule->coverage_name))
            ->when($rule->sub_coverage_id, fn ($q) => $q->where('d.coverage_id', (int) $rule->sub_coverage_id))
            ->when(!$rule->sub_coverage_id && trim((string) $rule->sub_coverage_name) !== '',
                fn ($q) => $q->where('sub.s_ScreenName', $rule->sub_coverage_name))
            ->when($rule->si_from !== null, fn ($q) => $q->where('d.coverage_value', '>=', (float) $rule->si_from))
            ->when($rule->si_to !== null, fn ($q) => $q->where('d.coverage_value', '<=', (float) $rule->si_to));
    }

    /**
     * @return array<string,mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'              => 'nullable|string|max:200',
            'product_id'        => 'nullable|integer|min:1',
            'coverage_id'       => 'nullable|integer|min:1',
            'coverage_name'     => 'nullable|string|max:200',
            'sub_coverage_id'   => 'nullable|integer|min:1',
            'sub_coverage_name' => 'nullable|string|max:200',
            'si_from'           => 'nullable|numeric|min:0',
            'si_to'             => 'nullable|numeric|min:0|gte:si_from',
            'transaction_type'  => 'nullable|string|max:40',
            'effective_from'    => 'nullable|date',
            'effective_to'      => 'nullable|date|after_or_equal:effective_from',
            // ±100 and no wider: a percent outside that range applied to a live
            // sum insured is a data-entry slip, not a decision. The command
            // refuses to run on one; refuse to save one in the first place.
            'pct'               => 'required|numeric|between:-100,100',
            'priority'          => 'nullable|integer|min:0|max:999',
            'is_active'         => 'nullable|boolean',
            'notes'             => 'nullable|string|max:2000',
        ], [
            'pct.between' => 'The uplift percent must be between -100 and 100. Enter 10 for a 10% increase, not 1000.',
            'si_to.gte'   => 'The sum-insured band upper bound must not be below the lower bound.',
        ]);

        // A blank name/band/date from the form is a wildcard, and a wildcard is
        // NULL in this table — never the empty string, which would match nothing.
        foreach (['name', 'coverage_name', 'sub_coverage_name', 'transaction_type', 'notes', 'effective_from', 'effective_to'] as $key) {
            if (array_key_exists($key, $data) && trim((string) $data[$key]) === '') {
                $data[$key] = null;
            }
        }

        // An id and a name for the same level are redundant; the id wins at
        // match time, so keep the name only as a readable label of that id.
        $data['is_active'] = (int) $request->input('is_active', true);
        $data['priority']  = (int) ($data['priority'] ?? 0);

        return $data;
    }

    /**
     * @return array<string,mixed>
     */
    private function present(InflationRateMaster $rule): array
    {
        $resolver = app(InflationRateResolver::class);

        return [
            'id'                => (int) $rule->id,
            'name'              => $rule->name,
            'product_id'        => $rule->product_id ? (int) $rule->product_id : null,
            'coverage_id'       => $rule->coverage_id ? (int) $rule->coverage_id : null,
            'coverage_name'     => $rule->coverage_name,
            'sub_coverage_id'   => $rule->sub_coverage_id ? (int) $rule->sub_coverage_id : null,
            'sub_coverage_name' => $rule->sub_coverage_name,
            'si_from'           => $rule->si_from === null ? null : (float) $rule->si_from,
            'si_to'             => $rule->si_to === null ? null : (float) $rule->si_to,
            'transaction_type'  => $rule->transaction_type,
            'effective_from'    => $rule->effective_from ? substr((string) $rule->effective_from, 0, 10) : null,
            'effective_to'      => $rule->effective_to ? substr((string) $rule->effective_to, 0, 10) : null,
            'pct'               => (float) $rule->pct,
            'priority'          => (int) $rule->priority,
            'is_active'         => (bool) $rule->is_active,
            'notes'             => $rule->notes,
            'specificity'       => $rule->specificity(),
            'summary'           => $resolver->describe($rule),
            'updated_at'        => (string) $rule->updated_at,
        ];
    }
}
