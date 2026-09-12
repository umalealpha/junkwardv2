<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Api\ClaimsTrackerController;
use AlphaDirect\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Claims Master-Data — READ-THROUGH endpoints.
 *
 * Backs the first-class Claims > Master Data screen for the Claims-Tracker ->
 * Graphite migration. These tiles read Graphite's EXISTING tables directly
 * (no mirroring, no second copy) so the data stays single-sourced:
 *
 *   - Claims Handlers            users ∩ Spatie role "Claim Handler"
 *   - Approved Brokers           agencies  (Pramod: brokers == agencies)
 *   - FAC Reinsurers             reinsurer (+ reinsurance_treaty fallback)
 *   - Panel Beaters / Glass      suppliers (+ incentive-approval flags)
 *   - System Users               users + model_has_roles (read-only list)
 *   - Claim-Type Map             DERIVED from ClaimsTrackerController::TYPE_MAP
 *
 * Assessors reuse the existing GET /assessors endpoint (category filter);
 * the tracker-only lists live in ClaimsConfigController (claims_config store).
 *
 * All routes are role-gated (Admin | Super Admin) at the route layer. Every
 * endpoint is read-only except toggleSupplierApproval (a single guarded flag
 * write on an existing suppliers row).
 */
class ClaimsMasterDataController extends Controller
{
    /** Spatie polymorphic type used across the model_has_roles rows. */
    private const USER_MODEL = 'AlphaDirect\\User';

    /** supplierType values that segment the suppliers table. */
    private const SUPPLIER_TYPES = [
        'panel_beater' => 'Motor Vehicle Accident',
        'glass'        => 'Glass',
    ];

    /**
     * GET /claims-masterdata/handlers
     * Users holding the Spatie "Claim Handler" role. ?search= filters name/email.
     */
    public function handlers(Request $request): JsonResponse
    {
        $query = DB::table('model_has_roles as mr')
            ->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->join('users as u', 'u.id', '=', 'mr.model_id')
            ->where('mr.model_type', self::USER_MODEL)
            ->where('r.name', 'Claim Handler')
            ->select('u.id', 'u.firstName', 'u.lastName', 'u.email', 'u.active')
            ->orderBy('u.firstName')->orderBy('u.lastName');

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(fn($q) => $q
                ->where('u.firstName', 'like', "%{$s}%")
                ->orWhere('u.lastName', 'like', "%{$s}%")
                ->orWhere('u.email', 'like', "%{$s}%"));
        }

        $rows = $query->get();

        return response()->json([
            'data' => $rows->map(fn($u) => [
                'id'        => $u->id,
                'firstName' => $u->firstName,
                'lastName'  => $u->lastName,
                'name'      => trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')),
                'email'     => $u->email,
                'isActive'  => (string) ($u->active ?? '') === '1',
            ])->values(),
            'meta' => ['total' => $rows->count(), 'source' => 'users ∩ role:Claim Handler'],
        ]);
    }

    /**
     * GET /claims-masterdata/brokers
     * Read-through of the agencies table (Pramod's decision: Approved Brokers
     * == agencies — confirmed by the data, which reads as real brokers /
     * selling channels). Read-only here; agencies are maintained in their own
     * admin surface.
     */
    public function brokers(Request $request): JsonResponse
    {
        $query = DB::table('agencies')->select('id', 'name', 'status')->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }
        if ($request->has('active')) {
            $query->where('status', (int) (bool) $request->input('active'));
        }

        $rows = $query->get();

        return response()->json([
            'data' => $rows->map(fn($a) => [
                'id'       => $a->id,
                'name'     => $a->name,
                'isActive' => (int) $a->status === 1,
            ])->values(),
            'meta' => ['total' => $rows->count(), 'source' => 'agencies'],
        ]);
    }

    /**
     * GET /claims-masterdata/reinsurers
     * FAC reinsurers from the `reinsurer` table. When empty (structurally, not
     * replica lag), also surfaces `reinsurance_treaty` as fallback context so
     * the tile is never blank pre-seed. reinsurer CRUD reuses the existing
     * /reinsurance/reinsurers routes.
     */
    public function reinsurers(Request $request): JsonResponse
    {
        $query = DB::table('reinsurer')
            ->select('id', 'company_name', 'email', 'cellphone')
            ->orderBy('company_name');
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(fn($q) => $q
                ->where('company_name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%"));
        }
        $rows = $query->get();

        $fallbackTreaties = [];
        if ($rows->isEmpty()) {
            $fallbackTreaties = DB::table('reinsurance_treaty')
                ->select('id', 'treaty_name', 'treaty_number', 'status')
                ->orderBy('treaty_name')
                ->get()
                ->map(fn($t) => [
                    'id'           => $t->id,
                    'treatyName'   => $t->treaty_name,
                    'treatyNumber' => $t->treaty_number,
                    'status'       => $t->status,
                ])->values();
        }

        return response()->json([
            'data' => $rows->map(fn($r) => [
                'id'          => $r->id,
                'companyName' => $r->company_name,
                'email'       => $r->email,
                'cellphone'   => $r->cellphone,
            ])->values(),
            'meta' => [
                'total'            => $rows->count(),
                'source'           => 'reinsurer',
                'usingFallback'    => $rows->isEmpty(),
                'fallbackTreaties' => $fallbackTreaties,
            ],
        ]);
    }

    /**
     * GET /claims-masterdata/suppliers?type=panel_beater|glass
     * Read-through of the suppliers table segmented by supplierType, carrying
     * the incentive-approval flags. Paginated + searchable.
     *
     * The incentive-flag columns arrive via the phase-3 migration; on an env
     * where they are not yet present the response still works and reports
     * flagsAvailable=false so the UI hides the toggle instead of erroring.
     */
    public function suppliers(Request $request): JsonResponse
    {
        $type = $request->input('type', 'panel_beater');
        $supplierType = self::SUPPLIER_TYPES[$type] ?? self::SUPPLIER_TYPES['panel_beater'];

        $flagsAvailable = Schema::hasColumn('suppliers', 'is_approved_panel_beater')
            && Schema::hasColumn('suppliers', 'is_approved_glass_supplier');

        $columns = ['id', 'supplierName', 'supplierType', 'email', 'telephone', 'supplierLocation'];
        if ($flagsAvailable) {
            $columns[] = 'is_approved_panel_beater';
            $columns[] = 'is_approved_glass_supplier';
        }

        $query = DB::table('suppliers')
            ->where('customer_selected', 0)
            ->where('supplierType', $supplierType)
            ->orderBy('supplierName')
            ->select($columns);

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(fn($q) => $q
                ->where('supplierName', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%"));
        }
        // ?approved=1 → only rows already flagged approved for this type.
        if ($flagsAvailable && $request->has('approved')) {
            $col = $type === 'glass' ? 'is_approved_glass_supplier' : 'is_approved_panel_beater';
            $query->where($col, (int) (bool) $request->input('approved'));
        }

        $results = $query->simplePaginate($request->input('per_page', 25));

        return response()->json([
            'data' => collect($results->items())->map(fn($s) => [
                'id'                      => $s->id,
                'name'                    => $s->supplierName,
                'type'                    => $s->supplierType,
                'email'                   => $s->email,
                'phone'                   => $s->telephone,
                'location'                => $s->supplierLocation,
                'isApprovedPanelBeater'   => $flagsAvailable ? (int) ($s->is_approved_panel_beater ?? 0) === 1 : null,
                'isApprovedGlassSupplier' => $flagsAvailable ? (int) ($s->is_approved_glass_supplier ?? 0) === 1 : null,
            ]),
            'meta' => [
                'current_page'   => $results->currentPage(),
                'per_page'       => $results->perPage(),
                'has_more'       => $results->hasMorePages(),
                'flagsAvailable' => $flagsAvailable,
                'category'       => $type,
                'supplierType'   => $supplierType,
            ],
        ]);
    }

    /**
     * PATCH /claims-masterdata/suppliers/{id}/approval
     * Toggle the approved panel-beater / glass supplier flag on an existing
     * suppliers row. Master-data management — role-gated (Admin|Super Admin)
     * at the route layer and NOT tied to the incentive-report feature flag
     * (that flag gates the report surface, not master-data upkeep).
     */
    public function toggleSupplierApproval(Request $request, int $id): JsonResponse
    {
        $flagsAvailable = Schema::hasColumn('suppliers', 'is_approved_panel_beater')
            && Schema::hasColumn('suppliers', 'is_approved_glass_supplier');
        if (!$flagsAvailable) {
            return response()->json([
                'message' => 'Incentive-approval columns are not present on this environment. Deploy the add_incentive_flags_to_suppliers migration first.',
            ], 422);
        }

        $request->validate([
            'is_approved_panel_beater'   => 'nullable|boolean',
            'is_approved_glass_supplier' => 'nullable|boolean',
        ]);

        $supplier = DB::table('suppliers')->where('id', $id)->first();
        if (!$supplier) {
            return response()->json(['message' => 'Supplier not found.'], 404);
        }

        $update = [];
        if ($request->has('is_approved_panel_beater')) {
            $update['is_approved_panel_beater'] = (int) $request->boolean('is_approved_panel_beater');
        }
        if ($request->has('is_approved_glass_supplier')) {
            $update['is_approved_glass_supplier'] = (int) $request->boolean('is_approved_glass_supplier');
        }
        if (empty($update)) {
            return response()->json(['message' => 'No incentive flags supplied.'], 422);
        }

        $update['updated_at'] = Carbon::now();
        DB::table('suppliers')->where('id', $id)->update($update);

        return response()->json([
            'message' => 'Supplier approval updated.',
            'data'    => [
                'id'                      => $id,
                'isApprovedPanelBeater'   => array_key_exists('is_approved_panel_beater', $update)
                    ? (bool) $update['is_approved_panel_beater'] : (bool) ($supplier->is_approved_panel_beater ?? false),
                'isApprovedGlassSupplier' => array_key_exists('is_approved_glass_supplier', $update)
                    ? (bool) $update['is_approved_glass_supplier'] : (bool) ($supplier->is_approved_glass_supplier ?? false),
            ],
        ]);
    }

    /**
     * GET /claims-masterdata/system-users
     * Read-only list of Graphite users with their Spatie roles. Paginated
     * (the users table is large). The full tracker-user role SYNC is a
     * sibling task — this tile is a read-only window.
     */
    public function systemUsers(Request $request): JsonResponse
    {
        $query = DB::table('users as u')
            ->select('u.id', 'u.firstName', 'u.lastName', 'u.email', 'u.active')
            ->orderBy('u.firstName')->orderBy('u.lastName');

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(fn($q) => $q
                ->where('u.firstName', 'like', "%{$s}%")
                ->orWhere('u.lastName', 'like', "%{$s}%")
                ->orWhere('u.email', 'like', "%{$s}%"));
        }

        $results = $query->simplePaginate($request->input('per_page', 25));

        // Batch-load roles for just the page of users.
        $ids = collect($results->items())->pluck('id')->all();
        $rolesByUser = [];
        if (!empty($ids)) {
            $roleRows = DB::table('model_has_roles as mr')
                ->join('roles as r', 'r.id', '=', 'mr.role_id')
                ->where('mr.model_type', self::USER_MODEL)
                ->whereIn('mr.model_id', $ids)
                ->select('mr.model_id', 'r.name')
                ->get();
            foreach ($roleRows as $rr) {
                $rolesByUser[$rr->model_id][] = $rr->name;
            }
        }

        return response()->json([
            'data' => collect($results->items())->map(fn($u) => [
                'id'       => $u->id,
                'name'     => trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')),
                'email'    => $u->email,
                'isActive' => (string) ($u->active ?? '') === '1',
                'roles'    => $rolesByUser[$u->id] ?? [],
            ]),
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page'     => $results->perPage(),
                'has_more'     => $results->hasMorePages(),
                'source'       => 'users + model_has_roles',
            ],
        ]);
    }

    /**
     * GET /claims-masterdata/claim-type-map
     * DERIVED from ClaimsTrackerController::TYPE_MAP (single source of truth —
     * never stored). Returns the alias->canonical map plus the de-duplicated
     * canonical type lists split motor / non-motor.
     */
    public function claimTypeMap(): JsonResponse
    {
        $map = ClaimsTrackerController::typeMap();

        // Canonical labels that are motor claim types.
        $motorCanonical = ['Accident', 'MOTORTRADERSEXTERNAL', 'MOTORTRADERSINTERNAL'];

        $canonical = array_values(array_unique(array_values($map)));
        sort($canonical);

        $motor    = array_values(array_filter($canonical, fn($c) => in_array($c, $motorCanonical, true)));
        $nonMotor = array_values(array_filter($canonical, fn($c) => !in_array($c, $motorCanonical, true)));

        $aliases = [];
        foreach ($map as $alias => $label) {
            $aliases[] = ['alias' => $alias, 'canonical' => $label, 'isMotor' => in_array($label, $motorCanonical, true)];
        }

        return response()->json([
            'data' => [
                'aliases'         => $aliases,
                'motorTypes'      => $motor,
                'nonMotorTypes'   => $nonMotor,
                'canonicalTypes'  => $canonical,
            ],
            'meta' => [
                'source'    => 'ClaimsTrackerController::TYPE_MAP (derived, not stored)',
                'aliasCount' => count($aliases),
            ],
        ]);
    }

    /**
     * GET /claims-masterdata/summary
     * Counts for the Master-Data landing tiles. Cheap COUNT queries only.
     */
    public function summary(): JsonResponse
    {
        $flagsAvailable = Schema::hasColumn('suppliers', 'is_approved_panel_beater')
            && Schema::hasColumn('suppliers', 'is_approved_glass_supplier');

        $handlers = DB::table('model_has_roles as mr')
            ->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->where('mr.model_type', self::USER_MODEL)
            ->where('r.name', 'Claim Handler')
            ->count();

        $assessors = Schema::hasTable('assessors') ? DB::table('assessors')->count() : 0;
        $motorAssessors = $assessors ? DB::table('assessors')
            ->where(fn($q) => $q->where('category', 'motor')->orWhere('category', 'both'))->count() : 0;
        $nonMotorAssessors = $assessors ? DB::table('assessors')
            ->where(fn($q) => $q->where('category', 'non_motor')->orWhere('category', 'both'))->count() : 0;

        $panelBeaters = DB::table('suppliers')->where('customer_selected', 0)
            ->where('supplierType', self::SUPPLIER_TYPES['panel_beater'])->count();
        $glass = DB::table('suppliers')->where('customer_selected', 0)
            ->where('supplierType', self::SUPPLIER_TYPES['glass'])->count();

        $configCounts = [];
        if (Schema::hasTable('claims_config')) {
            $configCounts = DB::table('claims_config')
                ->select('category', DB::raw('COUNT(*) as cnt'))
                ->groupBy('category')
                ->pluck('cnt', 'category')->toArray();
        }

        return response()->json([
            'data' => [
                'handlers'          => $handlers,
                'assessorsMotor'    => $motorAssessors,
                'assessorsNonMotor' => $nonMotorAssessors,
                'panelBeaters'      => $panelBeaters,
                'glassSuppliers'    => $glass,
                'reinsurers'        => DB::table('reinsurer')->count(),
                'brokers'           => DB::table('agencies')->count(),
                'systemUsers'       => DB::table('users')->count(),
                'config'            => $configCounts,
                'flagsAvailable'    => $flagsAvailable,
            ],
        ]);
    }
}
