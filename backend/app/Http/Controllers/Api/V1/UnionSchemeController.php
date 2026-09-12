<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Claim;
use AlphaDirect\Exports\UnionMembersExport;
use AlphaDirect\Exports\UnionMembersTemplateExport;
use AlphaDirect\Imports\UnionMembersImport;
use AlphaDirect\Models\Union;
use AlphaDirect\Models\UnionLegalClaim;
use AlphaDirect\Models\UnionMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Union Registration module — unions master config + member roster + dashboard.
 *
 * Creating a union auto-mints its group policy (product 4 = Legal) with number
 * MIS<union_code>, unless the admin supplies a policy_number override. Members
 * are registered as full customer + customer_profile records and attached to the
 * group policy.
 *
 * Union writes go through the Union Eloquent model so OwenIt Auditing records
 * create / update / status-change activity (user + old/new values). Customer /
 * policy / policy_members inserts reuse the LegalInsuranceController::createPolicy
 * pattern (schema-drift-safe array_intersect_key on each table's live columns;
 * raw DB::table so the Policy INSTANT_PREFIXES booted hook does not overwrite the
 * code-based MIS number).
 */
class UnionSchemeController extends Controller
{
    /** Legal Insurance product. */
    private const PRODUCT_ID = 4;

    /**
     * Fallback Legal plan for the group policy, used only when a union carries
     * no plan mapping (pre-`unions.plan_id` rows registered by premium alone).
     * Legal plans are product_plans WHERE product_id = 4.
     *
     * Normal path: the Register/Edit Union screen picks the Legal Insurance
     * product, the union stores plan_id, and the group policy inherits it.
     */
    private const DEFAULT_PLAN_ID = 5;

    /**
     * Mandatory import columns, as WithHeadingRow slugifies the template
     * headings. "Email Address" is deliberately absent — email is optional, so a
     * file without that column still imports. Keep in sync with
     * UnionMembersTemplateExport::headings().
     */
    private const IMPORT_HEADINGS = [
        'id_number', 'name', 'type', 'date_of_birth', 'gender', 'contact_no', 'nationality',
    ];

    // ─── Unions ──────────────────────────────────────────────────────────────

    /** List unions with roster aggregates for the Unions landing/management page. */
    public function index(Request $request): JsonResponse
    {
        $query = Union::query()->whereNull('deleted_at');

        if ($s = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($s) {
                $q->where('union_name', 'like', "%$s%")
                  ->orWhere('union_code', 'like', "%$s%")
                  ->orWhere('policy_number', 'like', "%$s%");
            });
        }
        if (($status = $request->query('status')) !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $unions = $query->orderBy('union_name')->get();

        $counts = DB::table('union_members')
            ->whereNull('deleted_at')
            ->select('union_id', DB::raw('COUNT(*) as total'), DB::raw('SUM(status = 1) as active'))
            ->groupBy('union_id')
            ->get()
            ->keyBy('union_id');

        // Plan names for the Legal Insurance Product column / edit dropdown.
        $plans = $this->legalPlansById($unions->pluck('plan_id')->all());

        $data = $unions->map(function (Union $u) use ($counts, $plans) {
            $c = $counts->get($u->id);
            $total  = (int) ($c->total  ?? 0);
            $active = (int) ($c->active ?? 0);
            $plan   = $u->plan_id ? ($plans[$u->plan_id] ?? null) : null;
            return [
                'id'                    => $u->id,
                'union_name'            => $u->union_name,
                'union_code'            => $u->union_code,
                'policy_id'             => $u->policy_id,
                'policy_number'         => $u->policy_number,
                'product_id'            => $u->product_id,
                'plan_id'               => $u->plan_id,
                'plan_name'             => $plan->name ?? null,
                'monthly_premium'       => (float) $u->monthly_premium,
                'effective_date'        => optional($u->effective_date)->format('Y-m-d'),
                'expiry_date'           => optional($u->expiry_date)->format('Y-m-d'),
                'status'                => (int) $u->status,
                'total_members'         => $total,
                'active_members'        => $active,
                'inactive_members'      => $total - $active,
                'total_monthly_premium' => round($active * (float) $u->monthly_premium, 2),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /** Register a union AND mint (or link, if overridden) its group policy. */
    public function store(Request $request): JsonResponse
    {
        $v = $request->validate([
            'union_name'      => 'required|string|max:255|unique:unions,union_name',
            'union_code'      => 'required|string|max:50|regex:/^[A-Za-z0-9]+$/|unique:unions,union_code',
            'description'     => 'nullable|string|max:1000',
            'product_id'      => 'nullable|integer',
            'policy_number'   => 'nullable|string|max:100',      // override; auto-minted when blank
            // The Legal Insurance product this union is registered under; its
            // price becomes the per-member monthly premium. `monthly_premium`
            // remains accepted for API callers that predate the mapping (the
            // unions:sync-legal-schemes command falls back to it when the plan
            // catalogue has no matching tier) — one of the two is required.
            'plan_id'         => 'required_without:monthly_premium|nullable|integer',
            'monthly_premium' => 'required_without:plan_id|nullable|numeric|gt:0',
            'effective_date'  => 'nullable|date',
            'expiry_date'     => 'nullable|date|after_or_equal:effective_date',
            'contact_person'  => 'nullable|string|max:255',
            'contact_number'  => 'nullable|string|max:50',
            'email'           => 'nullable|email|max:255',
            'address'         => 'nullable|string|max:500',
            'status'          => 'nullable|integer|in:0,1',
        ]);

        $unionCode = strtoupper($v['union_code']);
        $productId = (int) ($v['product_id'] ?? self::PRODUCT_ID);
        $status    = isset($v['status']) ? (int) $v['status'] : 1;

        // Plan wins when supplied: the premium is the plan's price, so the two
        // can never drift apart. Reject an id that is not a live Legal product
        // rather than silently falling back to the typed amount.
        $plan = null;
        if (!empty($v['plan_id'])) {
            $plan = $this->legalPlan((int) $v['plan_id']);
            if (!$plan) {
                return response()->json([
                    'error' => 'That Legal Insurance product is not available. Pick one from the list.',
                ], 422);
            }
        }
        $premium = $plan
            ? $this->planMonthlyPremium($plan)
            : round((float) $v['monthly_premium'], 2);

        if ($premium <= 0) {
            return response()->json([
                'error' => 'The selected Legal Insurance product has no price configured. Pick another product.',
            ], 422);
        }

        // Group policy number: admin override, else auto-mint MIS<code>.
        $policyNumber = !empty($v['policy_number'])
            ? strtoupper(trim($v['policy_number']))
            : 'MIS' . $unionCode;

        if (DB::table('policies')->where('policyNumber', $policyNumber)->exists()) {
            return response()->json([
                'error' => "A policy '{$policyNumber}' already exists. Choose a different code or policy number.",
            ], 422);
        }

        $effective = !empty($v['effective_date']) ? Carbon::parse($v['effective_date']) : Carbon::now();
        $expiry    = !empty($v['expiry_date']) ? Carbon::parse($v['expiry_date']) : (clone $effective)->addYear();
        $userId    = optional($request->user())->id;

        try {
            DB::beginTransaction();

            // 1. Holder customer for the group policy (the union as an entity).
            $customerId = DB::table('customer')->insertGetId($this->forTable('customer', [
                'firstName'  => $v['union_name'],
                'lastName'   => 'Union Group',
                'email'      => $v['email'] ?? null,
                'cellphone'  => $v['contact_number'] ?? null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]));

            // 2. Group policy (product 4, code-based MIS number, active).
            $policyRow = $this->forTable('policies', [
                'customer_id'      => $customerId,
                'product_id'       => $productId,
                // Group policy inherits the union's Legal product.
                'plan_id'          => $plan->id ?? self::DEFAULT_PLAN_ID,
                'policyNumber'     => $policyNumber,
                'status'           => 1,
                'premium'          => $premium,
                'annual_premium'   => round($premium * 12, 2),
                'premium_freq'     => 'monthly',
                'has_member'       => 1,
                'has_vehicle'      => 0,
                'leadSource'       => 'UnionScheme',
                'term_start_date'  => $effective->format('Y-m-d'),
                'term_end_date'    => $expiry->format('Y-m-d'),
                'expiry_date'      => $expiry->format('Y-m-d'),
                'created_at'       => Carbon::now(),
                'updated_at'       => Carbon::now(),
            ]);
            $policyId = DB::table('policies')->insertGetId($policyRow);

            // 3. Union row via the model (audited "created" event with all values).
            $union = Union::create([
                'union_name'      => $v['union_name'],
                'union_code'      => $unionCode,
                'description'     => $v['description'] ?? null,
                'product_id'      => $productId,
                'plan_id'         => $plan->id ?? null,
                'policy_id'       => $policyId,
                'policy_number'   => $policyNumber,
                'monthly_premium' => $premium,
                'effective_date'  => $effective->format('Y-m-d'),
                'expiry_date'     => $expiry->format('Y-m-d'),
                'contact_person'  => $v['contact_person'] ?? null,
                'contact_number'  => $v['contact_number'] ?? null,
                'email'           => $v['email'] ?? null,
                'address'         => $v['address'] ?? null,
                'status'          => $status,
                'created_by'      => $userId,
                'updated_by'      => $userId,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('UnionScheme.store.db_error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to create union: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'message'       => 'Union registered.',
            'id'            => $union->id,
            'policy_id'     => $policyId,
            'policy_number' => $policyNumber,
        ], 201);
    }

    /** Show one union + its group-policy panel + dashboard stats. */
    public function show(int $id): JsonResponse
    {
        $union = Union::whereNull('deleted_at')->find($id);
        if (!$union) return response()->json(['error' => 'Union not found.'], 404);

        return response()->json(['data' => $this->unionPayload($union)]);
    }

    /** Edit a union's details (audited via the model). */
    public function update(Request $request, int $id): JsonResponse
    {
        $union = Union::whereNull('deleted_at')->find($id);
        if (!$union) return response()->json(['error' => 'Union not found.'], 404);

        $v = $request->validate([
            'union_name'      => "sometimes|string|max:255|unique:unions,union_name,{$id}",
            'description'     => 'nullable|string|max:1000',
            'plan_id'         => 'sometimes|nullable|integer',
            'monthly_premium' => 'sometimes|numeric|gt:0',
            'effective_date'  => 'nullable|date',
            'expiry_date'     => 'nullable|date|after_or_equal:effective_date',
            'contact_person'  => 'nullable|string|max:255',
            'contact_number'  => 'nullable|string|max:50',
            'email'           => 'nullable|email|max:255',
            'address'         => 'nullable|string|max:500',
            'status'          => 'sometimes|integer|in:0,1',
        ]);

        // union_code and policy_number are immutable after registration (they key
        // the group policy) — silently ignored if sent.
        $fields = ['union_name', 'description', 'contact_person', 'contact_number', 'email', 'address'];
        foreach ($fields as $f) {
            if (array_key_exists($f, $v)) $union->{$f} = $v[$f];
        }
        // Switching the Legal Insurance product re-prices the union: the plan's
        // price is the premium, so an explicit monthly_premium in the same
        // request is ignored rather than allowed to contradict the mapping.
        $planChanged = false;
        if (array_key_exists('plan_id', $v)) {
            if (empty($v['plan_id'])) {
                $union->plan_id = null;
            } else {
                $plan = $this->legalPlan((int) $v['plan_id']);
                if (!$plan) {
                    return response()->json([
                        'error' => 'That Legal Insurance product is not available. Pick one from the list.',
                    ], 422);
                }
                $premium = $this->planMonthlyPremium($plan);
                if ($premium <= 0) {
                    return response()->json([
                        'error' => 'The selected Legal Insurance product has no price configured. Pick another product.',
                    ], 422);
                }
                $union->plan_id         = $plan->id;
                $union->monthly_premium = $premium;
                $planChanged            = true;
            }
        }
        if (!$planChanged && array_key_exists('monthly_premium', $v)) $union->monthly_premium = round((float) $v['monthly_premium'], 2);
        if (array_key_exists('effective_date', $v))  $union->effective_date = $v['effective_date'] ?: null;
        if (array_key_exists('expiry_date', $v))     $union->expiry_date = $v['expiry_date'] ?: null;
        if (array_key_exists('status', $v))          $union->status = (int) $v['status'];
        $union->updated_by = optional($request->user())->id;
        $union->save(); // audited

        // Keep the group policy in step: its premium always mirrors the union's,
        // and its plan follows the union's Legal product so the policy record
        // and the mapping can't disagree.
        if (($planChanged || array_key_exists('monthly_premium', $v)) && $union->policy_id) {
            $policyUpdate = [
                'premium'        => $union->monthly_premium,
                'annual_premium' => round($union->monthly_premium * 12, 2),
                'updated_at'     => Carbon::now(),
            ];
            if ($planChanged) {
                $policyUpdate['plan_id'] = $union->plan_id;
            }
            DB::table('policies')->where('id', $union->policy_id)->update($policyUpdate);
        }

        return response()->json(['message' => 'Union updated.']);
    }

    /** Activate / deactivate a union (audited status change). */
    public function setStatus(Request $request, int $id): JsonResponse
    {
        $union = Union::whereNull('deleted_at')->find($id);
        if (!$union) return response()->json(['error' => 'Union not found.'], 404);

        $v = $request->validate(['status' => 'required|integer|in:0,1']);
        $union->status = (int) $v['status'];
        $union->updated_by = optional($request->user())->id;
        $union->save(); // audited old→new status

        return response()->json(['message' => $union->status === 1 ? 'Union activated.' : 'Union deactivated.']);
    }

    /** Delete a union — blocked when members are registered (history-safe). */
    public function destroy(int $id): JsonResponse
    {
        $union = Union::whereNull('deleted_at')->find($id);
        if (!$union) return response()->json(['error' => 'Union not found.'], 404);

        $memberCount = UnionMember::where('union_id', $id)->whereNull('deleted_at')->count();
        if ($memberCount > 0) {
            return response()->json([
                'error' => "Cannot delete '{$union->union_name}' — it has {$memberCount} registered member(s). Deactivate it instead.",
            ], 422);
        }

        $union->delete(); // soft delete (audited)
        return response()->json(['message' => 'Union deleted.']);
    }

    /** Dashboard stats for one union (claims tallies stubbed → claims phase). */
    public function dashboard(int $id): JsonResponse
    {
        $union = Union::whereNull('deleted_at')->find($id);
        if (!$union) return response()->json(['error' => 'Union not found.'], 404);

        return response()->json(['data' => $this->dashboardStats($union)]);
    }

    // ─── Members ─────────────────────────────────────────────────────────────

    /** Paginated member roster with search + status filter. */
    public function members(Request $request, int $id): JsonResponse
    {
        $union = Union::whereNull('deleted_at')->find($id);
        if (!$union) return response()->json(['error' => 'Union not found.'], 404);

        $query = UnionMember::query()->where('union_id', $id)->whereNull('deleted_at');

        if ($s = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($s) {
                $q->where('member_name', 'like', "%$s%")
                  ->orWhere('id_number', 'like', "%$s%")
                  ->orWhere('member_type', 'like', "%$s%")
                  ->orWhere('contact_number', 'like', "%$s%");
            });
        }
        if (($status = $request->query('status')) !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        return response()->json($query->orderByDesc('id')->paginate((int) $request->query('per_page', 25)));
    }

    /**
     * Register a member manually: create customer + customer_profile, roster in
     * union_members, attach to the group policy. Blocks: inactive union (no new
     * registrations); duplicate ID number in ANY union (one member → one union).
     */
    public function storeMember(Request $request, int $id): JsonResponse
    {
        $union = Union::whereNull('deleted_at')->find($id);
        if (!$union) return response()->json(['error' => 'Union not found.'], 404);

        if ((int) $union->status !== 1) {
            return response()->json(['error' => 'This union is inactive and cannot accept new members.'], 422);
        }

        $v = $request->validate([
            'id_number'      => 'required|string|max:50',
            'member_name'    => 'required|string|max:255',
            'member_type'    => 'required|string|max:100',
            'date_of_birth'  => 'required|date',
            'gender'         => 'required|string|in:Male,Female,M,F',
            'contact_number' => 'required|string|max:50',
            'email'          => 'nullable|email|max:255',
            'nationality'    => 'required|string|max:100',
            'status'         => 'nullable|integer|in:0,1',
        ]);

        // One member → one union: same ID number cannot exist in ANY union.
        $existing = UnionMember::whereNull('deleted_at')->where('id_number', $v['id_number'])->first(['id', 'union_id']);
        if ($existing) {
            $where = (int) $existing->union_id === $id ? 'this union' : 'another union';
            return response()->json(['error' => "A member with ID '{$v['id_number']}' already exists in {$where}."], 422);
        }

        try {
            DB::beginTransaction();
            $member = $this->persistMember($union, [
                'id_number'      => $v['id_number'],
                'member_name'    => $v['member_name'],
                'member_type'    => $v['member_type'],
                'date_of_birth'  => Carbon::parse($v['date_of_birth'])->format('Y-m-d'),
                'gender'         => $this->genderCode($v['gender']),
                'contact_number' => $v['contact_number'],
                'email'          => $v['email'] ?? null,
                'nationality'    => $v['nationality'],
                'status'         => isset($v['status']) ? (int) $v['status'] : 1,
            ], optional($request->user())->id);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('UnionScheme.storeMember.db_error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to add member: ' . $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Member added.', 'id' => $member->id], 201);
    }

    /** Edit a member's roster fields (audited via the model). */
    public function updateMember(Request $request, int $id, int $memberId): JsonResponse
    {
        $member = UnionMember::whereNull('deleted_at')
            ->where('union_id', $id)->where('id', $memberId)->first();
        if (!$member) return response()->json(['error' => 'Member not found.'], 404);

        $v = $request->validate([
            'member_name'    => 'sometimes|string|max:255',
            'member_type'    => 'sometimes|string|max:100',
            'date_of_birth'  => 'nullable|date',
            'gender'         => 'nullable|string|in:Male,Female,M,F',
            'contact_number' => 'sometimes|string|max:50',
            'email'          => 'nullable|email|max:255',
            'nationality'    => 'sometimes|string|max:100',
            'status'         => 'sometimes|integer|in:0,1',
        ]);

        foreach (['member_name', 'member_type', 'contact_number', 'email', 'nationality'] as $col) {
            if (array_key_exists($col, $v)) $member->{$col} = $v[$col];
        }
        if (array_key_exists('gender', $v))        $member->gender = $this->genderCode($v['gender']);
        if (array_key_exists('date_of_birth', $v)) $member->date_of_birth = $v['date_of_birth'] ? Carbon::parse($v['date_of_birth'])->format('Y-m-d') : null;
        if (array_key_exists('status', $v))        $member->status = (int) $v['status'];
        $member->updated_by = optional($request->user())->id;
        $member->save(); // audited

        return response()->json(['message' => 'Member updated.']);
    }

    /** Soft-remove a member (history / any claims preserved; audited). */
    public function destroyMember(Request $request, int $id, int $memberId): JsonResponse
    {
        $member = UnionMember::whereNull('deleted_at')
            ->where('union_id', $id)->where('id', $memberId)->first();
        if (!$member) return response()->json(['error' => 'Member not found.'], 404);

        $member->status = 0;
        $member->updated_by = optional($request->user())->id;
        $member->save();   // audited status change
        $member->delete(); // soft delete

        return response()->json(['message' => 'Member removed.']);
    }

    // ─── Excel: template / export / import ────────────────────────────────────

    /** Download the blank member import template (.xlsx). */
    public function memberTemplate(int $id)
    {
        $union = Union::whereNull('deleted_at')->find($id);
        if (!$union) return response()->json(['error' => 'Union not found.'], 404);

        return Excel::download(new UnionMembersTemplateExport(), 'union_members_template.xlsx');
    }

    /** Export this union's members (.xlsx). */
    public function exportMembers(int $id)
    {
        $union = Union::whereNull('deleted_at')->find($id);
        if (!$union) return response()->json(['error' => 'Union not found.'], 404);

        $rows = UnionMember::where('union_id', $id)->whereNull('deleted_at')
            ->orderBy('member_name')->get()
            ->map(fn(UnionMember $m) => [
                $m->id_number, $m->member_name, $m->member_type,
                optional($m->date_of_birth)->format('Y-m-d'),
                $m->gender === 1 ? 'Male' : ($m->gender === 0 ? 'Female' : ''),
                $m->contact_number, $m->email, $m->nationality,
                $union->union_name, $m->status === 1 ? 'Active' : 'Inactive',
            ])->toArray();

        $file = 'union_members_' . ($union->union_code ?: $union->id) . '.xlsx';
        return Excel::download(new UnionMembersExport($rows), $file);
    }

    /**
     * Import members from an uploaded spreadsheet. Two modes:
     *   - preview (default): classify every row, persist nothing.
     *   - commit=1: persist the valid rows (mapped to this union) in one txn.
     * Returns an import summary + per-row preview.
     *
     * Row-level field requirements were removed deliberately: a row only fails
     * on a missing ID Number, or on the duplicate rules (same ID twice in the
     * file, or already registered in any union). Name / type / DOB / gender /
     * contact / nationality import exactly as supplied, blanks included.
     */
    public function importMembers(Request $request, int $id): JsonResponse
    {
        $union = Union::whereNull('deleted_at')->find($id);
        if (!$union) return response()->json(['error' => 'Union not found.'], 404);

        if ((int) $union->status !== 1) {
            return response()->json(['error' => 'This union is inactive and cannot accept member imports.'], 422);
        }

        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv,txt']);
        $commit = $request->boolean('commit');

        // A full-roster upload is thousands of rows and four inserts each; the
        // default 30s PHP limit kills it mid-transaction and the operator just
        // sees the request time out. Ask for 10 minutes (no-op where the SAPI
        // forbids it, e.g. php-fpm with request_terminate_timeout).
        @set_time_limit(600);

        // A corrupt / password-protected / mislabelled workbook makes PhpSpreadsheet
        // throw — return the reason instead of a bare 500.
        try {
            $import = new UnionMembersImport();
            $sheets = Excel::toArray($import, $request->file('file'));
        } catch (\Throwable $e) {
            Log::error('UnionScheme.importMembers.parse_error', [
                'union_id' => $id,
                'file'     => $request->file('file')->getClientOriginalName(),
                'error'    => $e->getMessage(),
            ]);
            return response()->json([
                'error' => 'Could not read that spreadsheet. Make sure it is a valid, unprotected .xlsx / .xls / .csv file.',
            ], 422);
        }

        $rows = $sheets[0] ?? [];

        if (!$rows) {
            return response()->json(['error' => 'The first sheet has no data rows below the heading row.'], 422);
        }

        // The importer is heading-driven, so a file that is not the member template
        // would silently classify every row as "required field missing". Fail fast
        // with the columns we actually need.
        $missing = array_diff(self::IMPORT_HEADINGS, array_keys($rows[0]));
        if ($missing) {
            return response()->json([
                'error' => 'This file does not match the member import template. Missing column(s): '
                    . implode(', ', array_map([$this, 'headingLabel'], $missing))
                    . '. Download the template and use those exact column headings.',
            ], 422);
        }

        // Existing ID → union_id map for the "already registered" rule, limited
        // to the IDs actually in this file. The old version plucked EVERY
        // member of EVERY union into memory, which grows with the roster rather
        // than with the upload — the wrong thing to do on a large import.
        $fileIds = collect($rows)
            ->map(fn($r) => trim((string) ($r['id_number'] ?? '')))
            ->filter()
            ->unique()
            ->values();

        $existing = collect();
        foreach ($fileIds->chunk(1000) as $chunk) {
            $existing = $existing->union(
                DB::table('union_members')->whereNull('deleted_at')
                    ->whereIn('id_number', $chunk->all())
                    ->pluck('union_id', 'id_number')
            );
        }

        $seen = [];               // id_number → first row seen (in-file dedupe)
        $preview = [];
        $valid = [];
        $counts = ['total' => 0, 'valid' => 0, 'failed' => 0, 'duplicates' => 0];

        foreach ($rows as $i => $row) {
            $counts['total']++;
            $data = [
                'id_number'   => trim((string) ($row['id_number'] ?? '')),
                'member_name' => trim((string) ($row['name'] ?? '')),
                'member_type' => trim((string) ($row['type'] ?? '')),
                'dob_raw'     => $row['date_of_birth'] ?? null,
                'gender'      => trim((string) ($row['gender'] ?? '')),
                'contact'     => trim((string) ($row['contact_no'] ?? '')),
                'email'       => trim((string) ($row['email_address'] ?? '')),
                'nationality' => trim((string) ($row['nationality'] ?? '')),
            ];

            // Per-field requirements are deliberately NOT enforced here. Union
            // rosters arrive with gaps (no DOB, no nationality, "N/A" contact
            // numbers), and rejecting the row loses the member entirely — the
            // roster is the point, the detail can be filled in later from the
            // member screen. Only the ID Number is enforced: it keys the
            // member, and the one-member-one-union duplicate rules below have
            // nothing to match on without it.
            $errors = [];
            if ($data['id_number'] === '') $errors[] = 'ID Number is required';

            // Unreadable values become null rather than an error. An unusable
            // email is dropped instead of stored, so it can't poison the
            // customer record or bounce a later mailing — but it is called out
            // on the preview row so the import isn't silently lossy.
            $dob    = $this->parseDateFlexible($data['dob_raw']);
            $gender = $this->genderCode($data['gender']);
            $email  = filter_var($data['email'], FILTER_VALIDATE_EMAIL) ? $data['email'] : null;

            $notes = [];
            if ($data['email'] !== '' && $email === null) $notes[] = 'Email ignored (not a valid address)';

            $status = 'valid';
            if ($errors) {
                $status = 'error';
                $counts['failed']++;
            } elseif (isset($seen[$data['id_number']])) {
                $status = 'duplicate';
                $errors[] = 'Duplicate ID Number within the uploaded file';
                $counts['duplicates']++;
            } elseif ($existing->has($data['id_number'])) {
                $status = 'duplicate';
                $inThis = (int) $existing[$data['id_number']] === $id;
                $errors[] = $inThis ? 'Already registered in this union' : 'Already registered under another union';
                $counts['duplicates']++;
            } else {
                $counts['valid']++;
                $seen[$data['id_number']] = true;
                $valid[] = [
                    'id_number'      => $data['id_number'],
                    // member_name is NOT NULL on union_members, so a nameless
                    // row keeps '' rather than becoming null and failing the
                    // insert the relaxed rules were meant to allow through.
                    'member_name'    => $data['member_name'],
                    'member_type'    => $data['member_type'] ?: null,
                    'date_of_birth'  => $dob,
                    'gender'         => $gender,
                    'contact_number' => $data['contact'] ?: null,
                    'email'          => $email,
                    'nationality'    => $data['nationality'] ?: null,
                    'status'         => 1,
                ];
            }

            $preview[] = [
                'row'         => $i + 2, // +1 heading, +1 to 1-index
                'id_number'   => $data['id_number'],
                'name'        => $data['member_name'],
                'type'        => $data['member_type'],
                'status'      => $status,
                // Non-blocking notes ride along with a valid row so the
                // operator can see what was dropped without it failing.
                'messages'    => array_merge($errors, $status === 'valid' ? $notes : []),
            ];
        }

        $imported = 0;
        if ($commit && $valid) {
            $userId = optional($request->user())->id;
            try {
                DB::beginTransaction();
                foreach ($valid as $d) {
                    $this->persistMember($union, $d, $userId);
                    $imported++;
                }
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('UnionScheme.importMembers.db_error', ['error' => $e->getMessage()]);
                return response()->json(['error' => 'Import failed: ' . $e->getMessage()], 500);
            }
        }

        return response()->json([
            'committed' => $commit,
            'summary'   => [
                'total'                => $counts['total'],
                'valid'                => $counts['valid'],
                'imported'             => $imported,
                'failed'               => $counts['failed'],
                'duplicates'           => $counts['duplicates'],
                'validation_errors'    => $counts['failed'],
            ],
            'preview'   => $preview,
        ]);
    }

    // ─── Legal Claims (BONU claim form filed against a member) ────────────────

    /** Relate-to label → claim_legal.matter_relatesto code. */
    private const RELATES_MAP = [
        'Main Member'         => 1,
        'Spouse/Life Partner' => 2,
        'Spouse'              => 2,
        'Child'               => 3,
        'Parents'             => 4,
    ];

    /** Type of matter label → claim_legal.realestate_enquiry_from code. */
    private const MATTER_MAP = [
        'Civil'    => 1,
        'Criminal' => 2,
        'Labor'    => 3,
        'Labour'   => 3,
    ];

    /**
     * Dedicated per-union form titles, keyed by union_code. A union not listed
     * here falls back to "{union_name} LEGAL CLAIM FORM" (see formName()).
     */
    private const UNION_FORM_NAMES = [
        'BONU'     => 'BONU LEGAL CLAIM FORM',
        'BOWASEWU' => 'BOWASEWU LEGAL CLAIM FORM',
    ];

    /** The printed form title for a union (dedicated map, else derived). */
    private function formName(Union $union): string
    {
        $code = strtoupper((string) $union->union_code);
        return self::UNION_FORM_NAMES[$code]
            ?? trim(strtoupper((string) ($union->union_name ?: 'Legal')) . ' LEGAL CLAIM FORM');
    }

    /**
     * File the BONU Legal Claim Form against a union member. Creates the standard
     * claim trio (claims + new_claims + claim_legal) so the claim shows in the
     * Claims module, records the full form in union_legal_claims, then (after the
     * DB commit) uploads any enclosed documents as claim_attachments.
     */
    public function storeLegalClaim(Request $request, int $id, int $memberId): JsonResponse
    {
        $union = Union::whereNull('deleted_at')->find($id);
        if (!$union) return response()->json(['error' => 'Union not found.'], 404);

        $member = UnionMember::whereNull('deleted_at')
            ->where('union_id', $id)->where('id', $memberId)->first();
        if (!$member) return response()->json(['error' => 'Member not found in this union.'], 404);

        $v = $request->validate([
            'region'                      => 'nullable|string|max:150',
            'claim_type'                  => 'nullable|string|max:100',
            'matter_relates_to'           => 'required|string|max:100',
            'child_financially_dependent' => 'nullable|boolean',
            'dependent_omang_passport'    => 'nullable|string|max:50',
            'dependent_dob'               => 'nullable|date',
            'matter_type'                 => 'required|string|max:50',
            'matter_arose_date'           => 'nullable|date',
            'proposed_course_of_action'   => 'nullable|string|max:5000',
            'declaration_signed'          => 'nullable|boolean',
            'signatory_name'              => 'nullable|string|max:255',
            'signed_date'                 => 'nullable|date',
            'documentation_checklist'     => 'nullable',
            'document_labels'             => 'nullable',
            'documents'                   => 'nullable|array',
            'documents.*'                 => 'file|max:10240',
        ]);

        // Multipart carries nested objects as JSON strings — decode to arrays.
        $checklist = $this->decodeJsonish($request->input('documentation_checklist'));
        $docLabels = $this->decodeJsonish($request->input('document_labels')) ?? [];

        $relatesCode = self::RELATES_MAP[$v['matter_relates_to']] ?? null;
        $matterCode  = self::MATTER_MAP[$v['matter_type']] ?? null;
        $policy      = $union->policy;
        $userId      = optional($request->user())->id;
        // Dedicated per-union form identity (BONU / BOWASEWU / …) for this record.
        $formCode    = strtoupper((string) $union->union_code);
        $formName    = $this->formName($union);
        $childDependent = $request->has('child_financially_dependent')
            ? ($request->boolean('child_financially_dependent') ? 1 : 0)
            : null;

        try {
            DB::beginTransaction();

            // Claim number: G{YEAR}{ID_PADDED} — same scheme as ClaimsController.
            $latestClaim = Claim::orderBy('id', 'desc')->first(['id']);
            $nextId      = $latestClaim ? $latestClaim->id + 1 : 1;
            $claimNumber = 'G' . Carbon::now()->year . str_pad($nextId, 6, '0', STR_PAD_LEFT);

            // 1. Standard claim (visible in the Claims module, under the member's
            //    customer). Non-fillable keys are dropped by mass-assignment.
            $claim = Claim::create([
                'customer_id'      => $member->customer_id,
                'agent_id'         => $policy->agent_id ?? null,
                'policy_id'        => $union->policy_id,
                'claim_type'       => 'Legal',
                'claim_number'     => $claimNumber,
                'status'           => 'Pending',
                'registered_claim' => Carbon::now()->format('Y-m-d'),
                'form_template'    => 'legal',
                'is_motor_claim'   => 0,
                'catastrophe_loss' => 0,
                'attorney_involved'=> 0,
                'created_by'       => $userId,
            ]);

            // 2. new_claims shadow (keyed by claim_number), drift-safe.
            if ($this->tableExists('new_claims')) {
                $newClaimRow = $this->forTable('new_claims', [
                    'policyNumber'        => $union->policy_number ?? ($policy->policyNumber ?? null),
                    'claim_number'        => $claimNumber,
                    'policy_id'           => $union->policy_id,
                    'customer_id'         => $member->customer_id,
                    'agent_id'            => $policy->agent_id ?? null,
                    'claim_type'          => 'Legal',
                    'claim_reported_by'   => '',
                    'date_of_loss'        => $v['matter_arose_date'] ?? Carbon::now()->format('Y-m-d'),
                    'description_of_loss' => $v['proposed_course_of_action'] ?? null,
                    'created_by'          => $userId,
                    'claim_approved'      => 'No',
                    'created_at'          => Carbon::now(),
                    'updated_at'          => Carbon::now(),
                ]);
                if (!empty($newClaimRow)) DB::table('new_claims')->insert($newClaimRow);
            }

            // 3. claim_legal — map BONU fields onto the legacy legal-claim table.
            if ($this->tableExists('claim_legal')) {
                $legalRow = $this->forTable('claim_legal', [
                    'claim_id'                  => $claim->id,
                    'member_name'               => $member->member_name,
                    'membership_id'             => $member->id_number,
                    'member_contact'            => $member->contact_number,
                    'member_email'              => $member->email,
                    'matter_relatesto'          => $relatesCode,
                    'child_financial_dependent' => $childDependent,
                    'idforchild'                => $v['dependent_omang_passport'] ?? null,
                    'child_dob'                 => $v['dependent_dob'] ?? null,
                    'realestate_enquiry_from'   => $matterCode,
                    'arose_date'                => $v['matter_arose_date'] ?? null,
                    'course_of_action'          => $v['proposed_course_of_action'] ?? null,
                    'declaration'               => $request->boolean('declaration_signed') ? 1 : 0,
                    'signature'                 => $request->boolean('declaration_signed') ? 1 : 0,
                    'created_at'                => Carbon::now(),
                    'updated_at'                => Carbon::now(),
                ]);
                if (!empty($legalRow)) DB::table('claim_legal')->insert($legalRow);
            }

            // 4. union_legal_claims — the full form + linkage (audited).
            $legalClaim = UnionLegalClaim::create([
                'union_id'                    => $union->id,
                'union_member_id'             => $member->id,
                'policy_id'                   => $union->policy_id,
                'customer_id'                 => $member->customer_id,
                'claim_id'                    => $claim->id,
                'claim_number'                => $claimNumber,
                'form_code'                   => $formCode,
                'form_name'                   => $formName,
                'policy_number'               => $union->policy_number ?? ($policy->policyNumber ?? null),
                'insured_name'                => $member->member_name,
                'region'                      => $v['region'] ?? null,
                'omang_passport'              => $member->id_number,
                'cellphone'                   => $member->contact_number,
                'email'                       => $member->email,
                'claim_type'                  => $v['claim_type'] ?? 'Legal',
                'matter_relates_to'           => $v['matter_relates_to'],
                'child_financially_dependent' => $childDependent,
                'dependent_omang_passport'    => $v['dependent_omang_passport'] ?? null,
                'dependent_dob'               => $v['dependent_dob'] ?? null,
                'matter_type'                 => $v['matter_type'],
                'matter_arose_date'           => $v['matter_arose_date'] ?? null,
                'proposed_course_of_action'   => $v['proposed_course_of_action'] ?? null,
                'declaration_signed'          => $request->boolean('declaration_signed') ? 1 : 0,
                'signatory_name'              => $v['signatory_name'] ?? null,
                'signed_date'                 => $v['signed_date'] ?? null,
                'documentation_checklist'     => $checklist,
                'status'                      => 'Pending',
                'created_by'                  => $userId,
                'updated_by'                  => $userId,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('UnionScheme.storeLegalClaim.db_error', ['union_id' => $id, 'member_id' => $memberId, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to file legal claim: ' . $e->getMessage()], 500);
        }

        // Enclosed documents — uploaded AFTER commit (external S3 call kept out of
        // the txn). Best-effort: a storage hiccup must not lose the filed claim.
        if ($request->hasFile('documents') && $this->tableExists('claim_attachments')) {
            foreach ($request->file('documents') as $key => $file) {
                try {
                    // Preserve the original filename (mirrors ClaimsV2Controller::
                    // storeWithOriginalName) so the Attachments tab shows the real
                    // name, not a random hash. UUID subdir avoids collisions.
                    $safe = trim(preg_replace('/[#?%&]+/', '_', preg_replace('#[/\\\\]+#', '_', $file->getClientOriginalName())));
                    if ($safe === '') $safe = 'file.' . ($file->getClientOriginalExtension() ?: 'dat');
                    $path  = $file->storeAs("MIS/{$claim->id}/Documents/" . \Illuminate\Support\Str::uuid(), $safe, 's3');
                    $label = is_array($docLabels) ? ($docLabels[$key] ?? null) : null;
                    $row   = $this->forTable('claim_attachments', [
                        'claim_id'           => $claim->id,
                        'name'               => $label ?: $file->getClientOriginalName(),
                        'attachment'         => serialize([$path]),
                        'file_name'          => $path,
                        'file_type'          => $file->getClientMimeType(),
                        'document_type_name' => $label,
                        'created_at'         => Carbon::now(),
                        'updated_at'         => Carbon::now(),
                    ]);
                    if (!empty($row)) DB::table('claim_attachments')->insert($row);
                } catch (\Throwable $e) {
                    Log::warning('UnionScheme.storeLegalClaim.attachment_failed', ['claim_id' => $claim->id, 'error' => $e->getMessage()]);
                }
            }
        }

        return response()->json([
            'message'      => 'Legal claim filed.',
            'id'           => $legalClaim->id,
            'claim_id'     => $claim->id,
            'claim_number' => $claimNumber,
        ], 201);
    }

    /** Paginated legal-claim roster for a union (read-back list). */
    public function legalClaims(Request $request, int $id): JsonResponse
    {
        $union = Union::whereNull('deleted_at')->find($id);
        if (!$union) return response()->json(['error' => 'Union not found.'], 404);

        $query = UnionLegalClaim::query()->where('union_id', $id)->whereNull('deleted_at');

        if ($s = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($s) {
                $q->where('claim_number', 'like', "%$s%")
                  ->orWhere('insured_name', 'like', "%$s%")
                  ->orWhere('omang_passport', 'like', "%$s%");
            });
        }
        if (($status = $request->query('status')) !== null && $status !== '') {
            $query->where('status', $status);
        }

        return response()->json($query->orderByDesc('id')->paginate((int) $request->query('per_page', 25)));
    }

    /** One legal claim + its enclosed documents. */
    public function showLegalClaim(int $id, int $claimId): JsonResponse
    {
        $claim = UnionLegalClaim::where('union_id', $id)->where('id', $claimId)->whereNull('deleted_at')->first();
        if (!$claim) return response()->json(['error' => 'Legal claim not found.'], 404);

        $attachments = [];
        if ($claim->claim_id && $this->tableExists('claim_attachments')) {
            $attachments = DB::table('claim_attachments')
                ->where('claim_id', $claim->claim_id)
                ->orderByDesc('id')
                ->get(['id', 'name', 'file_name', 'file_type', 'document_type_name', 'created_at'])
                ->all();
        }

        $data = $claim->toArray();
        $data['attachments'] = $attachments;
        return response()->json(['data' => $data]);
    }

    /**
     * Edit a union legal claim's own fields (the ones on its dedicated form).
     * Updates union_legal_claims and keeps the mirrored claim_legal row in step
     * so the Claims module detail stays consistent. Header identity fields
     * (member name / omang / policy) are read-only and not accepted here.
     */
    public function updateLegalClaim(Request $request, int $id, int $claimId): JsonResponse
    {
        $claim = UnionLegalClaim::where('union_id', $id)->where('id', $claimId)->whereNull('deleted_at')->first();
        if (!$claim) return response()->json(['error' => 'Legal claim not found.'], 404);

        $v = $request->validate([
            'region'                      => 'nullable|string|max:150',
            'claim_type'                  => 'nullable|string|max:100',
            'matter_relates_to'           => 'sometimes|string|max:100',
            'child_financially_dependent' => 'nullable|boolean',
            'dependent_omang_passport'    => 'nullable|string|max:50',
            'dependent_dob'               => 'nullable|date',
            'matter_type'                 => 'sometimes|string|max:50',
            'matter_arose_date'           => 'nullable|date',
            'proposed_course_of_action'   => 'nullable|string|max:5000',
        ]);

        $childDependent = $request->has('child_financially_dependent')
            ? ($request->boolean('child_financially_dependent') ? 1 : 0)
            : null;

        try {
            DB::beginTransaction();

            foreach (['region', 'claim_type', 'matter_relates_to', 'dependent_omang_passport',
                      'dependent_dob', 'matter_type', 'matter_arose_date', 'proposed_course_of_action'] as $f) {
                if (array_key_exists($f, $v)) $claim->{$f} = $v[$f] ?: null;
            }
            if ($request->has('child_financially_dependent')) {
                $claim->child_financially_dependent = $childDependent;
            }
            $claim->updated_by = optional($request->user())->id;
            $claim->save();

            // Keep the mirrored claim_legal row consistent (Claims module reads it).
            if ($claim->claim_id && $this->tableExists('claim_legal')) {
                $legalUpdate = $this->forTable('claim_legal', [
                    'matter_relatesto'          => array_key_exists('matter_relates_to', $v) ? (self::RELATES_MAP[$v['matter_relates_to']] ?? null) : null,
                    'realestate_enquiry_from'   => array_key_exists('matter_type', $v) ? (self::MATTER_MAP[$v['matter_type']] ?? null) : null,
                    'arose_date'                => $v['matter_arose_date'] ?? null,
                    'course_of_action'          => $v['proposed_course_of_action'] ?? null,
                    'child_financial_dependent' => $childDependent,
                    'idforchild'                => $v['dependent_omang_passport'] ?? null,
                    'child_dob'                 => $v['dependent_dob'] ?? null,
                    'updated_at'                => Carbon::now(),
                ]);
                // Only overwrite columns the operator actually edited (drop nulls
                // for keys not present in this request so we don't wipe values).
                $legalUpdate = array_filter($legalUpdate, fn($val) => $val !== null);
                if (!empty($legalUpdate)) {
                    DB::table('claim_legal')->where('claim_id', $claim->claim_id)->update($legalUpdate);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('UnionScheme.updateLegalClaim.db_error', ['claim_id' => $claimId, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to update legal claim: ' . $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Legal claim updated.']);
    }

    /** Decode a value that may be a JSON string (multipart) or already an array. */
    private function decodeJsonish($value): ?array
    {
        if (is_array($value)) return $value;
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }

    // ─── Schema introspection (memoised) ─────────────────────────────────────

    /**
     * These tables are read with array_intersect_key against their live columns
     * so the module survives schema drift. Done naively that costs a SHOW
     * COLUMNS / information_schema round trip PER ROW — five of them per member
     * in persistMember() — which is what made a few thousand-row import crawl
     * until the request timed out. The schema cannot change mid-request, so
     * look each one up once and reuse it.
     *
     * @var array<string,array<int,string>>
     */
    private array $schemaColumns = [];

    /** @var array<string,bool> */
    private array $schemaTables = [];

    private function columnsOf(string $table): array
    {
        if (!array_key_exists($table, $this->schemaColumns)) {
            $this->schemaColumns[$table] = DB::getSchemaBuilder()->getColumnListing($table);
        }
        return $this->schemaColumns[$table];
    }

    private function tableExists(string $table): bool
    {
        if (!array_key_exists($table, $this->schemaTables)) {
            $this->schemaTables[$table] = DB::getSchemaBuilder()->hasTable($table);
        }
        return $this->schemaTables[$table];
    }

    /** Keep only the keys that exist as columns on $table. */
    private function forTable(string $table, array $row): array
    {
        return array_intersect_key($row, array_flip($this->columnsOf($table)));
    }

    // ─── Legal Insurance product (product_plans, product_id = 4) ──────────────

    /**
     * Fetch one Legal Insurance product by id, or null when it is not a live
     * plan on product 4. Restricting by product here is what stops a caller
     * mapping a union to, say, a Motor plan by posting its id.
     */
    private function legalPlan(?int $planId): ?object
    {
        if (!$planId) return null;

        return DB::table('product_plans')
            ->where('id', $planId)
            ->where('product_id', self::PRODUCT_ID)
            ->where('status', 1)
            ->first(['id', 'name', 'premium', 'sum_assured']);
    }

    /**
     * Per-member monthly premium for a Legal plan.
     *
     * `product_plans.premium` holds the ex-VAT amount; the figure operators and
     * customers work in is the all-in one (the P49 / P75 on the plan name).
     * Same 14% BW VAT + round-to-cents as LookupController::plansByProduct,
     * which is what feeds the dropdown — the two must agree or the screen shows
     * one number and the union stores another.
     */
    private function planMonthlyPremium(object $plan): float
    {
        $factor = 1 + ((float) env('BW_VAT_PERCENT', 14) / 100);
        return round((float) $plan->premium * $factor, 2);
    }

    /** Legal plans keyed by id, for list payloads (avoids an N+1 per union). */
    private function legalPlansById(array $planIds): array
    {
        $planIds = array_values(array_filter(array_unique($planIds)));
        if (!$planIds) return [];

        try {
            return DB::table('product_plans')
                ->whereIn('id', $planIds)
                ->get(['id', 'name', 'premium'])
                ->keyBy('id')
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function unionPayload(Union $union): array
    {
        $policy = $union->policy;
        $plan   = $union->plan_id ? ($this->legalPlansById([$union->plan_id])[$union->plan_id] ?? null) : null;
        return [
            'id'              => $union->id,
            'union_name'      => $union->union_name,
            'union_code'      => $union->union_code,
            'description'     => $union->description,
            'monthly_premium' => (float) $union->monthly_premium,
            'product_id'      => $union->product_id,
            'plan_id'         => $union->plan_id,
            'plan_name'       => $plan->name ?? null,
            'policy_number'   => $union->policy_number,
            'effective_date'  => optional($union->effective_date)->format('Y-m-d'),
            'expiry_date'     => optional($union->expiry_date)->format('Y-m-d'),
            'contact_person'  => $union->contact_person,
            'contact_number'  => $union->contact_number,
            'email'           => $union->email,
            'address'         => $union->address,
            'status'          => (int) $union->status,
            'policy'          => $policy ? [
                'id'            => $policy->id,
                'policy_number' => $policy->policyNumber,
                'product_id'    => $policy->product_id,
                'premium'       => (float) $policy->premium,
                'premium_freq'  => $policy->premium_freq,
                'status'        => (int) $policy->status,
            ] : null,
            'stats'           => $this->dashboardStats($union),
        ];
    }

    private function dashboardStats(Union $union): array
    {
        $agg = DB::table('union_members')
            ->where('union_id', $union->id)
            ->whereNull('deleted_at')
            ->select(DB::raw('COUNT(*) as total'), DB::raw('SUM(status = 1) as active'))
            ->first();

        $total  = (int) ($agg->total  ?? 0);
        $active = (int) ($agg->active ?? 0);

        // Legal-claim tallies from union_legal_claims (outstanding = not Closed).
        $totalClaims = 0;
        $outstandingClaims = 0;
        if ($this->tableExists('union_legal_claims')) {
            $claimAgg = DB::table('union_legal_claims')
                ->where('union_id', $union->id)
                ->whereNull('deleted_at')
                ->selectRaw("COUNT(*) as total, SUM(status <> 'Closed') as outstanding")
                ->first();
            $totalClaims       = (int) ($claimAgg->total ?? 0);
            $outstandingClaims = (int) ($claimAgg->outstanding ?? 0);
        }

        return [
            'total_members'         => $total,
            'active_members'        => $active,
            'inactive_members'      => $total - $active,
            'monthly_premium'       => (float) $union->monthly_premium,
            'total_monthly_premium' => round($active * (float) $union->monthly_premium, 2),
            'total_claims'          => $totalClaims,
            'outstanding_claims'    => $outstandingClaims,
        ];
    }

    /**
     * Persist one member (manual add OR import row) inside the caller's txn:
     * customer + customer_profile + union_members roster + group-policy link.
     * $d: id_number, member_name, member_type, date_of_birth(Y-m-d|null),
     *     gender(int|null), contact_number, email, nationality, status(int).
     */
    private function persistMember(Union $union, array $d, ?int $userId): UnionMember
    {
        [$first, $last] = $this->splitName($d['member_name']);

        $customerId = DB::table('customer')->insertGetId($this->forTable('customer', [
            'firstName'  => $first,
            'lastName'   => $last,
            'email'      => $d['email'] ?? null,
            'cellphone'  => $d['contact_number'] ?? null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]));

        if ($this->tableExists('customer_profile')) {
            $profileRow = $this->forTable('customer_profile', [
                'customer_id' => $customerId,
                'omang'       => $d['id_number'],
                'id_type'     => 'Omang',
                'dob'         => $d['date_of_birth'] ?? null,
                'gender'      => $d['gender'] ?? null,
                'nationality' => $d['nationality'] ?? null,
                'created_at'  => Carbon::now(),
                'updated_at'  => Carbon::now(),
            ]);
            DB::table('customer_profile')->insert($profileRow);
        }

        $member = UnionMember::create([
            'union_id'       => $union->id,
            'policy_id'      => $union->policy_id,
            'customer_id'    => $customerId,
            'id_number'      => $d['id_number'],
            'member_name'    => $d['member_name'],
            'member_type'    => $d['member_type'] ?? null,
            'date_of_birth'  => $d['date_of_birth'] ?? null,
            'gender'         => $d['gender'] ?? null,
            'contact_number' => $d['contact_number'] ?? null,
            'email'          => $d['email'] ?? null,
            'nationality'    => $d['nationality'] ?? null,
            'status'         => $d['status'] ?? 1,
            'created_by'     => $userId,
            'updated_by'     => $userId,
        ]); // audited

        // Attach to the group policy (best-effort, schema-drift-safe).
        if ($union->policy_id && $this->tableExists('policy_members')) {
            $pmRow = $this->forTable('policy_members', [
                'policy_id'   => $union->policy_id,
                'customer_id' => $customerId,
                'first_name'  => $first,
                'last_name'   => $last,
                'omang'       => $d['id_number'],
                'dob'         => $d['date_of_birth'] ?? null,
                'gender'      => $d['gender'] ?? null,
                'cellphone'   => $d['contact_number'] ?? null,
                'email'       => $d['email'] ?? null,
                'created_at'  => Carbon::now(),
                'updated_at'  => Carbon::now(),
            ]);
            if (!empty($pmRow)) {
                DB::table('policy_members')->insert($pmRow);
            }
        }

        return $member;
    }

    /** Split a full name into [first, last] (last = remainder, may be ''). */
    private function splitName(string $name): array
    {
        $parts = explode(' ', trim($name), 2);
        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    /** Parse Excel serial / common date strings to Y-m-d, or null. */
    private function parseDateFlexible($value): ?string
    {
        if ($value === null || $value === '') return null;

        if (is_numeric($value)) {
            try {
                return Carbon::createFromTimestamp(((int) $value - 25569) * 86400)->format('Y-m-d');
            } catch (\Throwable $e) { /* fall through */ }
        }

        $value = trim((string) $value);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'd.m.Y', 'Y/m/d'] as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $value);
                if ($dt !== false) return $dt->format('Y-m-d');
            } catch (\Throwable $e) { /* try next */ }
        }
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** DB gender code: 1 = Male, 0 = Female (matches customer_profile). */
    private function genderCode(?string $g): ?int
    {
        return match (strtoupper((string) $g)) {
            'M', 'MALE'   => 1,
            'F', 'FEMALE' => 0,
            default       => null,
        };
    }

    /** Turn a slugified heading back into the label printed in the template. */
    private function headingLabel(string $slug): string
    {
        return match ($slug) {
            'contact_no'    => 'Contact No',
            'date_of_birth' => 'Date Of Birth',
            'id_number'     => 'ID Number',
            default         => ucwords(str_replace('_', ' ', $slug)),
        };
    }
}
