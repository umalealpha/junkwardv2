<?php
namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\Reinsurance\TreatyRolloverException;
use AlphaDirect\Services\Reinsurance\TreatyRolloverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReinsuranceApiController extends Controller
{
    /**
     * Formula payload keys that belong to reinsurance_formula_details, not to the
     * reinsurance_formula header row. Kept in one place so store and update agree.
     */
    private const FORMULA_DETAIL_KEYS = [
        'group_name', 'operator', 'vehicle_type', 'si_allocation', 'percentage', 'datefrom', 'dateto',
    ];

    // ─── Reinsurance Types ────────────────────────────────────────────────────

    public function types(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'   => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = DB::table('reinsurance_type')
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where('type_name', 'like', "%{$search}%")
                  ->orWhere('type_code', 'like', "%{$search}%");
            })
            ->orderBy('id', 'desc');

        $results = $query->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => collect($results->items())->map(fn($r) => [
                'id'              => $r->id,
                'typeCode'        => $r->type_code,
                'typeName'        => $r->type_name,
                'typeDescription' => $r->type_description ?? null,
                'status'          => $r->status,
                'createdAt'       => $r->created_at,
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

    public function showType(int $id): JsonResponse
    {
        $r = DB::table('reinsurance_type')->where('id', $id)->first();
        if (!$r) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        return response()->json([
            'id'              => $r->id,
            'typeCode'        => $r->type_code,
            'typeName'        => $r->type_name,
            'typeDescription' => $r->type_description ?? null,
            'status'          => $r->status,
            'createdAt'       => $r->created_at,
        ]);
    }

    public function storeType(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type_code'        => 'required|string|max:50|unique:reinsurance_type,type_code',
            'type_name'        => 'required|string|max:100|unique:reinsurance_type,type_name',
            'type_description' => 'required|string|max:500',
            'status'           => 'nullable|integer',
        ]);
        $data['status']     = $data['status'] ?? 1;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('reinsurance_type')->insertGetId($data);

        return response()->json(['message' => 'Reinsurance type created.', 'data' => ['id' => $id]], 201);
    }

    public function updateType(Request $request, int $id): JsonResponse
    {
        $exists = DB::table('reinsurance_type')->where('id', $id)->exists();
        if (!$exists) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'type_code'        => ['required', 'string', 'max:50', Rule::unique('reinsurance_type', 'type_code')->ignore($id)],
            'type_name'        => ['required', 'string', 'max:100', Rule::unique('reinsurance_type', 'type_name')->ignore($id)],
            'type_description' => 'required|string|max:500',
            'status'           => 'nullable|integer',
        ]);
        $data['status']     = $data['status'] ?? 1;
        $data['updated_at'] = now();

        DB::table('reinsurance_type')->where('id', $id)->update($data);

        return response()->json(['message' => 'Reinsurance type updated.']);
    }

    public function destroyType(int $id): JsonResponse
    {
        $deleted = DB::table('reinsurance_type')->where('id', $id)->delete();
        if (!$deleted) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        return response()->json(['message' => 'Deleted.']);
    }

    // ─── Reinsurance Treaties ─────────────────────────────────────────────────

    public function treaties(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'   => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = DB::table('reinsurance_treaty')
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where('treaty_name', 'like', "%{$search}%")
                  ->orWhere('treaty_number', 'like', "%{$search}%");
            })
            ->orderBy('id', 'desc');

        $results = $query->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => collect($results->items())->map(fn($r) => [
                'id'                   => $r->id,
                'treatyName'           => $r->treaty_name ?? null,
                'treatyNumber'         => $r->treaty_number ?? null,
                'effectiveFrom'        => $r->effective_from ?? null,
                'effectiveTo'          => $r->effective_to ?? null,
                'provisionalCommission'=> $r->provisional_commission ?? null,
                'proportionalShare'    => $r->proportional_share ?? null,
                'cashLossAdvise'       => $r->cash_loss_advise ?? null,
                'eventLimit'           => $r->event_limit ?? null,
                'exclusions'           => $r->exclusions ?? null,
                'formulaAttached'      => array_map('intval', DB::table('reinsurance_treaty_details')->where('treaty_id', $r->id)->pluck('formula_attached')->toArray()),
                'status'               => $r->status ?? null,
                'createdAt'            => $r->created_at ?? null,
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

    public function showTreaty(int $id): JsonResponse
    {
        $r = DB::table('reinsurance_treaty')->where('id', $id)->first();
        if (!$r) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        // Get formula_attached from reinsurance_treaty_details (as integers)
        $formulaAttached = array_map('intval', DB::table('reinsurance_treaty_details')
            ->where('treaty_id', $id)
            ->pluck('formula_attached')
            ->toArray());

        return response()->json([
            'id'                    => $r->id,
            'treatyName'            => $r->treaty_name ?? null,
            'treatyNumber'          => $r->treaty_number ?? null,
            'effectiveFrom'         => $r->effective_from ?? null,
            'effectiveTo'           => $r->effective_to ?? null,
            'provisionalCommission' => $r->provisional_commission ?? null,
            'proportionalShare'     => $r->proportional_share ?? null,
            'cashLossAdvise'        => $r->cash_loss_advise ?? null,
            'eventLimit'            => $r->event_limit ?? null,
            'exclusions'            => $r->exclusions ?? null,
            'formulaAttached'       => $formulaAttached,
            'status'                => $r->status ?? null,
            'createdAt'             => $r->created_at ?? null,
        ]);
    }

    public function storeTreaty(Request $request): JsonResponse
    {
        $data = $request->validate([
            'treaty_name'            => 'required|string|max:200',
            'treaty_number'          => 'required|string|max:100',
            'effective_from'         => 'required|date',
            'effective_to'           => 'required|date|after_or_equal:effective_from',
            'provisional_commission' => 'required|numeric|min:0|max:100',
            'proportional_share'     => 'required|numeric|min:0|max:100',
            'cash_loss_advise'       => 'required|numeric|min:0',
            'event_limit'            => 'required|numeric|min:0',
            'exclusions'             => 'nullable|string|max:2000',
            'formula_attached'       => 'nullable|array',
            'formula_attached.*'     => 'nullable|integer',
            'status'                 => 'nullable|integer',
        ]);
        $data['status']     = $data['status'] ?? 1;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $formulaAttached = $data['formula_attached'] ?? [];
        unset($data['formula_attached']);

        $id = DB::table('reinsurance_treaty')->insertGetId($data);

        // Save formula attachments to reinsurance_treaty_details
        foreach ($formulaAttached as $formulaId) {
            DB::table('reinsurance_treaty_details')->insert([
                'treaty_id'        => $id,
                'formula_attached' => $formulaId,
            ]);
        }

        return response()->json(['message' => 'Treaty created.', 'data' => ['id' => $id]], 201);
    }

    public function updateTreaty(Request $request, int $id): JsonResponse
    {
        $exists = DB::table('reinsurance_treaty')->where('id', $id)->exists();
        if (!$exists) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'treaty_name'            => 'required|string|max:200',
            'treaty_number'          => 'required|string|max:100',
            'effective_from'         => 'required|date',
            'effective_to'           => 'required|date|after_or_equal:effective_from',
            'provisional_commission' => 'required|numeric|min:0|max:100',
            'proportional_share'     => 'required|numeric|min:0|max:100',
            'cash_loss_advise'       => 'required|numeric|min:0',
            'event_limit'            => 'required|numeric|min:0',
            'exclusions'             => 'nullable|string|max:2000',
            'formula_attached'       => 'nullable|array',
            'formula_attached.*'     => 'nullable|integer',
            'status'                 => 'nullable|integer',
        ]);
        $data['status']     = $data['status'] ?? 1;
        $data['updated_at'] = now();

        $formulaAttached = $data['formula_attached'] ?? [];
        unset($data['formula_attached']);

        DB::table('reinsurance_treaty')->where('id', $id)->update($data);

        // Delete existing formula attachments and save new ones
        DB::table('reinsurance_treaty_details')->where('treaty_id', $id)->delete();
        foreach ($formulaAttached as $formulaId) {
            DB::table('reinsurance_treaty_details')->insert([
                'treaty_id'        => $id,
                'formula_attached' => $formulaId,
            ]);
        }

        return response()->json(['message' => 'Treaty updated.']);
    }

    public function destroyTreaty(int $id): JsonResponse
    {
        $deleted = DB::table('reinsurance_treaty')->where('id', $id)->delete();
        if (!$deleted) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        // Also remove related reinsurance_treaty_details rows
        DB::table('reinsurance_treaty_details')->where('treaty_id', $id)->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    /**
     * Clone an existing treaty (and its treaty_details) into a new period.
     * Delegates to TreatyRolloverService so CLI + UI share the exact same
     * behaviour. Returns full log array so the UI can show a dry-run preview
     * before the user commits.
     *
     * POST /api/v1/reinsurance/treaties/{id}/rollover
     *
     * Body (all optional except id in path):
     *   name            string   new treaty_name; default auto-bump year
     *   number          string   new treaty_number; default = name
     *   effective_from  Y-m-d    default source.effective_to + 1 day
     *   effective_to    Y-m-d    default +1 year -1 day from new from
     *   dry_run         bool     default false
     *   force           bool     default false
     */
    public function rolloverTreaty(Request $request, int $id, TreatyRolloverService $service): JsonResponse
    {
        $data = $request->validate([
            'name'           => 'nullable|string|max:100',
            'number'         => 'nullable|string|max:100',
            'effective_from' => 'nullable|date_format:Y-m-d',
            'effective_to'   => 'nullable|date_format:Y-m-d',
            'dry_run'        => 'nullable|boolean',
            'force'          => 'nullable|boolean',
        ]);

        try {
            $result = $service->rollover($id, [
                'name'           => $data['name']           ?? null,
                'number'         => $data['number']         ?? null,
                'effective_from' => $data['effective_from'] ?? null,
                'effective_to'   => $data['effective_to']   ?? null,
                'dry_run'        => (bool) ($data['dry_run'] ?? false),
                'force'          => (bool) ($data['force']   ?? false),
                'actor'          => Auth::id() ?? 'api',
            ]);
        } catch (TreatyRolloverException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $result['dry_run']
                ? 'Dry-run complete. No changes committed.'
                : "Treaty rolled over. New treaty id {$result['new_treaty_id']}.",
            'data' => $result,
        ]);
    }

    // ─── Reinsurance Formulas ─────────────────────────────────────────────────

    public function formulas(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'   => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = DB::table('reinsurance_formula as rf')
            ->leftJoin('reinsurance_type as rt', 'rt.id', '=', 'rf.reinsurance_type_id')
            ->leftJoin('reinsurance_formula_details as rfd', 'rfd.formula_id', '=', 'rf.id')
            ->select([
                'rf.id', 'rf.formula_code', 'rf.formula_name',
                'rf.product_id', 'rf.reinsurance_type_id', 'rf.type_id',
                'rf.s_FormulaType', 'rf.status', 'rf.created_at',
                'rfd.group_id', 'rfd.operator', 'rfd.vehicle_type',
                'rfd.si_allocation', 'rfd.percentage', 'rfd.date_from', 'rfd.date_to',
                'rt.type_name as reinsurance_type',
            ])
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where('rf.formula_name', 'like', "%{$search}%")
                  ->orWhere('rf.formula_code', 'like', "%{$search}%");
            })
            ->orderBy('rf.id', 'desc');

        $results = $query->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => collect($results->items())->map(fn($r) => [
                'id'              => $r->id,
                'formulaCode'     => $r->formula_code,
                'formulaName'     => $r->formula_name,
                'productId'       => $r->product_id,
                'reinsuranceTypeId' => $r->reinsurance_type_id,
                'typeId'          => $r->type_id,
                'sFormulaType'    => $r->s_FormulaType,
                'reinsuranceType' => $r->reinsurance_type,
                'status'          => $r->status,
                'groupId'         => $r->group_id,
                'operator'        => (string)$r->operator,
                'vehicleType'     => $r->vehicle_type,
                'siAllocation'    => $r->si_allocation,
                'percentage'      => $r->percentage,
                'dateFrom'        => $r->date_from,
                'dateTo'          => $r->date_to,
                'createdAt'       => $r->created_at,
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

    public function showFormula(int $id): JsonResponse
    {
        $r = DB::table('reinsurance_formula as rf')
            ->leftJoin('reinsurance_formula_details as rfd', 'rfd.formula_id', '=', 'rf.id')
            ->where('rf.id', $id)
            ->select('rf.*', 'rfd.group_id', 'rfd.operator', 'rfd.vehicle_type', 'rfd.si_allocation', 'rfd.percentage', 'rfd.date_from', 'rfd.date_to')
            ->first();
        if (!$r) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        return response()->json([
            'id'                => $r->id,
            'formulaCode'       => $r->formula_code,
            'formulaName'       => $r->formula_name,
            'productId'         => $r->product_id,
            'reinsuranceTypeId' => $r->reinsurance_type_id,
            'typeId'            => $r->type_id,
            'sFormulaType'      => $r->s_FormulaType,
            'status'            => $r->status,
            'groupId'           => $r->group_id,
            'operator'          => (string)$r->operator,
            'vehicleType'       => $r->vehicle_type,
            'siAllocation'      => $r->si_allocation,
            'percentage'        => $r->percentage,
            'dateFrom'          => $r->date_from,
            'dateTo'            => $r->date_to,
            'createdAt'         => $r->created_at,
        ]);
    }

    /**
     * s_FormulaType values the cession engine dispatches on, keyed by every spelling a
     * client might post — the display label as served by formulaTypes(), the id itself,
     * and either spelling of facultative placement.
     *
     * Keys are uppercased with non-letters stripped, so casing, spaces, hyphens and
     * underscores all fold away before lookup.
     */
    private const FORMULA_TYPE_ALIASES = [
        'TSI'                  => 'TSI',
        'OTHER'                => 'OTHER',
        'SURPLUS'              => 'SURPLUS',
        'FACULTATIVE'          => 'FACULTATIVE',
        'FACULATIVE'           => 'FACULTATIVE',
        'FACULTATIVEPLACEMENT' => 'FACULATIVEPLACEMENT',
        'FACULATIVEPLACEMENT'  => 'FACULATIVEPLACEMENT',
    ];

    /**
     * Fold a submitted formula type to the token the engine compares against.
     *
     * The engine's dispatch uses PHP's ==, which is case-sensitive, so a formula stored
     * as 'Surplus' or 'Facultative Placement' matches no branch and its layer silently
     * computes nothing. Normalising on write keeps the stored value canonical for every
     * consumer, not just the cession run.
     *
     * An unrecognised value is passed through untouched rather than guessed at, so bad
     * input stays visible instead of being coerced into the wrong layer.
     */
    private static function canonicalFormulaType($raw): ?string
    {
        if ($raw === null || $raw === '') {
            return $raw === '' ? '' : null;
        }

        $key = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $raw));

        return self::FORMULA_TYPE_ALIASES[$key] ?? $raw;
    }

    public function storeFormula(Request $request): JsonResponse
    {
        $data = $request->validate([
            'formula_name'         => 'required|string|max:200',
            'formula_code'         => 'required|string|max:100|unique:reinsurance_formula,formula_code',
            'product_id'           => 'nullable|integer',
            'reinsurance_type_id'  => 'nullable|integer',
            'type_id'              => 'nullable|integer',
            's_FormulaType'        => 'nullable|string|max:100',
            'status'               => 'nullable|integer',
            'group_name'           => 'nullable|integer',
            'operator'             => 'nullable|integer|between:1,9',
            'vehicle_type'         => 'nullable|string|max:100',
            'si_allocation'        => 'nullable|string|max:100',
            'percentage'           => 'nullable|string|max:100',
            'datefrom'             => 'nullable|date',
            'dateto'               => 'nullable|date',
        ]);

        $data['status']        = $data['status'] ?? 1;
        $data['s_FormulaType'] = self::canonicalFormulaType($data['s_FormulaType'] ?? null);
        $data['created_at']    = now();
        $data['updated_at']    = now();

        // reinsurance_formula holds the header only. Group, operator, limits and
        // dates live on reinsurance_formula_details — passing them to the header
        // insert fails with "Unknown column 'group_name'".
        $detail  = array_intersect_key($data, array_flip(self::FORMULA_DETAIL_KEYS));
        $formula = array_diff_key($data, array_flip(self::FORMULA_DETAIL_KEYS));

        $id = DB::transaction(function () use ($formula, $detail) {
            $newId = DB::table('reinsurance_formula')->insertGetId($formula);

            if (array_filter($detail, fn ($v) => $v !== null && $v !== '')) {
                DB::table('reinsurance_formula_details')->insert([
                    'formula_id'    => $newId,
                    'group_id'      => $detail['group_name'] ?? null,
                    'operator'      => $detail['operator'] ?? null,
                    'vehicle_type'  => $detail['vehicle_type'] ?? null,
                    'si_allocation' => $detail['si_allocation'] ?? null,
                    'percentage'    => $detail['percentage'] ?? null,
                    'date_from'     => $detail['datefrom'] ?? null,
                    'date_to'       => $detail['dateto'] ?? null,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }

            return $newId;
        });

        return response()->json(['message' => 'Formula created.', 'data' => ['id' => $id]], 201);
    }

    public function updateFormula(Request $request, int $id): JsonResponse
    {
        $exists = DB::table('reinsurance_formula')->where('id', $id)->exists();
        if (!$exists) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'formula_name'        => 'required|string|max:200',
            'formula_code'        => ['required', 'string', 'max:100', Rule::unique('reinsurance_formula', 'formula_code')->ignore($id)],
            'product_id'          => 'nullable|integer',
            'reinsurance_type_id' => 'nullable|integer',
            'type_id'             => 'nullable|integer',
            's_FormulaType'       => 'nullable|string|max:100',
            'status'              => 'nullable|integer',
            'group_name'          => 'nullable|integer',
            'operator'            => 'nullable|integer|between:1,9',
            'vehicle_type'        => 'nullable|string|max:100',
            'si_allocation'       => 'nullable|string|max:100',
            'percentage'          => 'nullable|string|max:100',
            'datefrom'            => 'nullable|date',
            'dateto'              => 'nullable|date',
        ]);

        $data['status']        = $data['status'] ?? 1;
        $data['s_FormulaType'] = self::canonicalFormulaType($data['s_FormulaType'] ?? null);
        $data['updated_at']    = now();

        DB::transaction(function () use ($data, $id) {
            // Update main formula table — header columns only.
            DB::table('reinsurance_formula')->where('id', $id)->update([
                'formula_name'        => $data['formula_name'],
                'formula_code'        => $data['formula_code'],
                'product_id'          => $data['product_id'],
                'reinsurance_type_id' => $data['reinsurance_type_id'],
                'type_id'             => $data['type_id'],
                's_FormulaType'       => $data['s_FormulaType'],
                'status'              => $data['status'],
                'updated_at'          => now(),
            ]);

            // Update or create detail record
            $detailData = [
                'group_id'      => $data['group_name'] ?? null,
                'operator'      => $data['operator'] ?? null,
                'vehicle_type'  => $data['vehicle_type'] ?? null,
                'si_allocation' => $data['si_allocation'] ?? null,
                'percentage'    => $data['percentage'] ?? null,
                'date_from'     => $data['datefrom'] ?? null,
                'date_to'       => $data['dateto'] ?? null,
                'updated_at'    => now(),
            ];

            $detailExists = DB::table('reinsurance_formula_details')->where('formula_id', $id)->exists();
            if ($detailExists) {
                DB::table('reinsurance_formula_details')->where('formula_id', $id)->update($detailData);
            } else {
                $detailData['formula_id'] = $id;
                $detailData['created_at'] = now();
                DB::table('reinsurance_formula_details')->insert($detailData);
            }
        });

        return response()->json(['message' => 'Formula updated.']);
    }

    public function destroyFormula(int $id): JsonResponse
    {
        // Clear the detail row too — otherwise it is orphaned and the RI calc can
        // still join it via a recycled formula_id.
        $deleted = DB::transaction(function () use ($id) {
            $n = DB::table('reinsurance_formula')->where('id', $id)->delete();
            if ($n) {
                DB::table('reinsurance_formula_details')->where('formula_id', $id)->delete();
            }
            return $n;
        });

        if (!$deleted) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        return response()->json(['message' => 'Deleted.']);
    }

    // ─── Coverage Groups ──────────────────────────────────────────────────────

    public function coverageGroups(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'   => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = DB::table('reinsurance_group')
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where('group_name', 'like', "%{$search}%")
                  ->orWhere('group_code', 'like', "%{$search}%");
            })
            ->orderBy('id', 'desc');

        $results = $query->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => collect($results->items())->map(fn($r) => [
                'id'        => $r->id,
                'groupCode' => $r->group_code ?? null,
                'groupName' => $r->group_name ?? null,
                'productId' => $r->product_id ?? null,
                'status'    => $r->status ?? null,
                'createdAt' => $r->created_at ?? null,
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

    public function showGroup(int $id): JsonResponse
    {
        $r = DB::table('reinsurance_group')->where('id', $id)->first();
        if (!$r) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        // Return the STORED coverage rows verbatim. The previous version LEFT
        // JOINed product_coverage and returned COALESCE(pc.name, rgc.coverage_name)
        // as coverage_name, which broke the edit form two ways:
        //   1. product_coverage has multiple rows per coverage_id (the join has
        //      no product filter), so each saved row fanned out into several
        //      duplicates (e.g. 2 rows for group 30 became 8).
        //   2. It overwrote the stored variant name ("Comprehensive") with a
        //      product-coverage label ("Motor Claim" / "motor_value"), so the
        //      edit screen could no longer match a saved motor-trader variant
        //      back to its row (fixed coverage id 15/16/22 + the variant NAME).
        // The edit form takes its DISPLAY names from the product-coverage /
        // hardcoded-variant endpoints, not from here — it only needs the raw
        // stored coverage_name to re-select saved rows. So return rgc.* directly.
        $coverages = DB::table('reinsurance_group_coverage')
            ->where('group_id', $id)
            ->get(['id', 'coverage_id', 'coverage_name', 'si_premium', 'ri_limit', 'limit_value']);
        return response()->json([
            'id'        => $r->id,
            'groupCode' => $r->group_code ?? null,
            'groupName' => $r->group_name ?? null,
            'productId' => $r->product_id ?? null,
            'status'    => $r->status ?? null,
            'createdAt' => $r->created_at ?? null,
            'coverages' => $coverages,
        ]);
    }

    public function productCoverages(int $id): JsonResponse
    {
        // Return all product coverages - frontend will try to load sub-coverages for ALL codes
        // (matching the old project's hybrid approach: try dynamic first, fall back to hardcoded)
        $coverages = DB::table('product_coverage as pc')
            ->leftJoin('tb_cvgpccoverages as cov', 'pc.coverage_id', '=', 'cov.id')
            ->where('pc.product_id', $id)
            ->select(
                'pc.coverage_id',
                'pc.name',
                DB::raw("COALESCE(cov.s_CoverageCode, pc.name) as coverage_code"),
                DB::raw("COALESCE(cov.s_UsageType, 'MAIN') as usage_type")
            )
            ->orderBy(DB::raw("COALESCE(cov.s_CoverageCode, pc.name)"), 'asc')
            ->get();

        // Hardcoded coverage types - will be used as fallback if no sub-coverages exist
        $hardcodedCoverages = ['COMMERCIALMOTOR', 'MOTORTRADERSEXTERNAL', 'MOTORTRADERSINTERNAL'];

        $result = [];
        foreach ($coverages as $cov) {
            $coverageData = [
                'coverage_id' => $cov->coverage_id,
                'name' => $cov->name,
                'code' => $cov->coverage_code,
                'usage_type' => $cov->usage_type,
                'is_hardcoded' => in_array($cov->coverage_code, $hardcodedCoverages),
            ];

            $result[] = $coverageData;
        }

        return response()->json(['data' => $result]);
    }

    public function getProductSubCoverages(string $coverageCode): JsonResponse
    {
        // Same approach as old project: query for CHILD coverages
        // GET /api/v1/reinsurance/sub-coverages/{coverageCode}
        $subCoverages = DB::table('tb_cvgpccoverages')
            ->select('id', 's_ScreenName as name')
            ->where('s_ParentCoverageCode', $coverageCode)
            ->where('s_UsageType', 'CHILD')
            ->whereNull('policy_id')
            ->get();

        return response()->json(['data' => $subCoverages]);
    }

    public function getHardcodedCoverages(string $coverageCode): JsonResponse
    {
        // Return hardcoded coverage variants based on coverage code
        // GET /api/v1/reinsurance/hardcoded-coverages/{coverageCode}
        $hardcoded = [];

        if ($coverageCode === 'COMMERCIALMOTOR') {
            $hardcoded = [
                ['id' => 'comm_comprehensive', 'name' => 'Comprehensive'],
                ['id' => 'comm_third_party', 'name' => 'Third Party Only'],
                ['id' => 'comm_fire_theft', 'name' => 'Third Party Fire and Theft'],
            ];
        } elseif ($coverageCode === 'MOTORTRADERSEXTERNAL') {
            $hardcoded = [
                ['id' => 'mtext_comprehensive', 'name' => 'Comprehensive'],
                ['id' => 'mtext_third_party', 'name' => 'Third Party Only'],
                ['id' => 'mtext_fire_theft', 'name' => 'Third Party Fire and Theft'],
            ];
        } elseif ($coverageCode === 'MOTORTRADERSINTERNAL') {
            $hardcoded = [
                ['id' => 'mtint_comprehensive', 'name' => 'Comprehensive'],
                ['id' => 'mtint_third_party', 'name' => 'Third Party Only'],
                ['id' => 'mtint_fire_theft', 'name' => 'Third Party Fire and Theft'],
            ];
        }

        return response()->json(['data' => $hardcoded]);
    }

    /**
     * Build the reinsurance_group_coverage rows to persist from the submitted
     * coverages, mirroring the old project's ReInsuranceGroupCoverage/Edit.php:
     *   - only coverages with an SI/Premium set (old FIELD1 != "");
     *   - numeric ids stored with their canonical s_CoverageCode as coverage_name
     *     (the RI calc joins gd.coverage_name = tc.s_CoverageCode);
     *   - hardcoded motor variants (string ids) mapped to their fixed integer
     *     coverage_id — Motor Traders Ext = 15, Int = 16, Commercial Motor = 22 —
     *     keeping the Comprehensive / Third-Party variant name.
     * Rows without a resolvable coverage id are skipped.
     */
    private function buildGroupCoverageRows(array $coverages): array
    {
        $motorMap = ['mtext_' => 15, 'mtint_' => 16, 'comm_' => 22];

        $numericIds = array_values(array_filter(array_map(
            fn ($c) => is_numeric($c['coverage_id'] ?? null) ? (int) $c['coverage_id'] : null,
            $coverages
        )));
        $codeMap = $numericIds
            ? DB::table('tb_cvgpccoverages')->whereIn('id', $numericIds)->pluck('s_CoverageCode', 'id')->toArray()
            : [];

        $rows = [];
        foreach ($coverages as $cov) {
            // Old-project FIELD1 != "" — only store coverages with an SI/Premium set.
            $siPremium = $cov['si_premium'] ?? null;
            if ($siPremium === null || $siPremium === '') {
                continue;
            }

            $rawId = (string) ($cov['coverage_id'] ?? '');
            if (is_numeric($rawId)) {
                $coverageId   = (int) $rawId;
                $coverageName = $codeMap[$coverageId] ?? ($cov['coverage_name'] ?? '');
            } else {
                // Hardcoded motor / commercial-motor variant → fixed integer id + variant name.
                $coverageId = null;
                foreach ($motorMap as $prefix => $mappedId) {
                    if (strncmp($rawId, $prefix, strlen($prefix)) === 0) {
                        $coverageId = $mappedId;
                        break;
                    }
                }
                if ($coverageId === null) {
                    continue; // unknown non-numeric id — skip
                }
                $coverageName = $cov['coverage_name'] ?? '';
            }

            $rows[] = [
                'coverage_id'   => $coverageId,
                'coverage_name' => $coverageName,
                'si_premium'    => $siPremium,
                'ri_limit'      => $cov['ri_limit'] ?? null,
                'limit_value'   => (($cov['ri_limit'] ?? null) == 3) ? ($cov['limit_value'] ?? null) : null,
            ];
        }

        return $rows;
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group_code'                => 'required|string|max:50|unique:reinsurance_group,group_code',
            'group_name'                => 'required|string|max:200|unique:reinsurance_group,group_name',
            'product_id'                => 'nullable|integer',
            'status'                    => 'nullable|integer',
            'coverages'                 => 'nullable|array',
            'coverages.*.coverage_id'   => 'required',
            'coverages.*.coverage_name' => 'required|string',
            'coverages.*.si_premium'    => 'nullable|integer',
            'coverages.*.ri_limit'      => 'nullable|integer',
            'coverages.*.limit_value'   => 'nullable|string',
        ], [
            'group_code.unique' => 'This group code already exists.',
            'group_name.unique' => 'This group name already exists.',
        ]);
        $rows = $this->buildGroupCoverageRows($data['coverages'] ?? []);
        unset($data['coverages']);
        $data['status']     = $data['status'] ?? 1;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::transaction(function () use ($data, $rows) {
            $newId = DB::table('reinsurance_group')->insertGetId($data);
            foreach ($rows as $row) {
                DB::table('reinsurance_group_coverage')->insert(array_merge($row, [
                    'group_id'   => $newId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
            return $newId;
        });

        return response()->json(['message' => 'Coverage group created.', 'data' => ['id' => $id]], 201);
    }

    public function updateGroup(Request $request, int $id): JsonResponse
    {
        $exists = DB::table('reinsurance_group')->where('id', $id)->exists();
        if (!$exists) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'group_code'                => ['required', 'string', 'max:50', Rule::unique('reinsurance_group', 'group_code')->ignore($id)],
            'group_name'                => ['required', 'string', 'max:200', Rule::unique('reinsurance_group', 'group_name')->ignore($id)],
            'product_id'                => 'nullable|integer',
            'status'                    => 'nullable|integer',
            'coverages'                 => 'nullable|array',
            'coverages.*.coverage_id'   => 'required',
            'coverages.*.coverage_name' => 'required|string',
            'coverages.*.si_premium'    => 'nullable|integer',
            'coverages.*.ri_limit'      => 'nullable|integer',
            'coverages.*.limit_value'   => 'nullable|string',
        ], [
            'group_code.unique' => 'This group code already exists.',
            'group_name.unique' => 'This group name already exists.',
        ]);
        $rows = $this->buildGroupCoverageRows($data['coverages'] ?? []);
        unset($data['coverages']);
        $data['status']     = $data['status'] ?? 1;
        $data['updated_at'] = now();

        DB::transaction(function () use ($id, $data, $rows) {
            DB::table('reinsurance_group')->where('id', $id)->update($data);

            // Only rewrite the coverage mapping when we actually have rows to write.
            // An empty list almost always means the client submitted before the
            // coverages finished loading — deleting would wipe the group's config.
            if (!empty($rows)) {
                DB::table('reinsurance_group_coverage')->where('group_id', $id)->delete();
                foreach ($rows as $row) {
                    DB::table('reinsurance_group_coverage')->insert(array_merge($row, [
                        'group_id'   => $id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                }
            }
        });

        return response()->json(['message' => 'Coverage group updated.']);
    }

    public function destroyGroup(int $id): JsonResponse
    {
        $deleted = DB::table('reinsurance_group')->where('id', $id)->delete();
        if (!$deleted) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        // Also remove related coverage rows
        DB::table('reinsurance_group_coverage')->where('group_id', $id)->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    // ─── Form Lookups ─────────────────────────────────────────────────────────

    public function formLookups(): JsonResponse
    {
        $types = DB::table('reinsurance_type')
            ->where('status', 1)
            ->select('id', 'type_name')
            ->orderBy('type_name')
            ->get();

        $products = DB::table('products')
            ->where('status', 1)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $formulas = DB::table('reinsurance_formula')
            ->where('status', 1)
            ->select('id', 'formula_name')
            ->orderBy('formula_name')
            ->get();

        $groups = DB::table('reinsurance_group')
            ->select('id', 'group_name')
            ->orderBy('group_name')
            ->get();

        // Formula category (lookup_data key reinsurance_formula_key): 35 = Motor,
        // 36 = Non-Motor. The cession calc splits a class per coverage detail only
        // when this is Motor, so a formula saved without it silently stops ceding
        // per vehicle.
        $formulaKeys = DB::table('lookup_data')
            ->where('key', 'reinsurance_formula_key')
            ->where('status', 1)
            ->select('id', 'value as name')
            ->orderBy('id')
            ->get();

        // Formula types — hardcoded as per old Graphite (x-select in add.blade.php).
        //
        // 'id' is what the cession engine dispatches on; 'name' is display only. The two
        // differ for every type except TSI, so a client that posts back 'name' saves a
        // value no layer branch matches — see canonicalFormulaType(), which folds the
        // label back to the id on write.
        $formulaTypes = collect([
            ['id' => 'TSI', 'name' => 'TSI'],
            ['id' => 'SURPLUS', 'name' => 'Surplus'],
            ['id' => 'FACULTATIVE', 'name' => 'Facultative'],
            ['id' => 'FACULATIVEPLACEMENT', 'name' => 'Facultative Placement'],
            ['id' => 'OTHER', 'name' => 'Other'],
        ]);

        return response()->json([
            'types'        => $types,
            'products'     => $products,
            'formulas'     => $formulas,
            'groups'       => $groups,
            'formulaTypes' => $formulaTypes,
            'formulaKeys'  => $formulaKeys,
        ]);
    }

    public function initializeFormulaNames(): JsonResponse
    {
        // Initialize formula names for known formulas (2-33)
        $formulaData = [
            2 => ['name' => 'Proportional - Quota Share', 'code' => 'PROP_QS'],
            3 => ['name' => 'Excess of Loss - Per Risk', 'code' => 'XOL_PR'],
            4 => ['name' => 'Excess of Loss - Catastrophe', 'code' => 'XOL_CAT'],
            13 => ['name' => 'Motor Traders', 'code' => 'MOT_TRADERS'],
            16 => ['name' => 'Commercial - All Risks', 'code' => 'COM_AR'],
            17 => ['name' => 'Engineering', 'code' => 'ENG'],
            18 => ['name' => 'Marine & Cargo', 'code' => 'MAR_CARGO'],
            20 => ['name' => 'Proportional - Surplus', 'code' => 'PROP_SURP'],
            21 => ['name' => 'Stop Loss', 'code' => 'STOP_LOSS'],
            22 => ['name' => 'Aggregate Excess', 'code' => 'AGG_EXC'],
            23 => ['name' => 'Automatic Facultative', 'code' => 'AUTO_FAC'],
            32 => ['name' => 'Special Risk', 'code' => 'SPEC_RISK'],
            33 => ['name' => 'Reinsurance Pool', 'code' => 'POOL'],
        ];

        $updated = 0;
        // Update known formulas with proper names and codes
        foreach ($formulaData as $id => $data) {
            $rows = DB::table('reinsurance_formula')
                ->where('id', $id)
                ->update([
                    'formula_name' => $data['name'],
                    'formula_code' => $data['code'],
                ]);
            $updated += $rows;
        }

        // For all other formulas without a formula_name, use formula_code as formula_name
        $orphanFormulas = DB::table('reinsurance_formula')
            ->whereNull('formula_name')
            ->orWhere('formula_name', '')
            ->get(['id', 'formula_code']);

        foreach ($orphanFormulas as $formula) {
            if ($formula->formula_code) {
                DB::table('reinsurance_formula')
                    ->where('id', $formula->id)
                    ->update(['formula_name' => $formula->formula_code]);
                $updated++;
            }
        }

        return response()->json([
            'message' => 'Formula names and codes initialized',
            'updated' => $updated
        ]);
    }
}
