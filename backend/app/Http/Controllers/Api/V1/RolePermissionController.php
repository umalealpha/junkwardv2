<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Roles & Permissions management API.
 * Uses Spatie Permission tables (roles, permissions, model_has_roles, role_has_permissions).
 */
class RolePermissionController extends Controller
{
    public function roles(): JsonResponse
    {
        $roles = DB::table('roles')->orderBy('name')->get(['id', 'name', 'guard_name', 'created_at']);

        // Batch-load permission counts and user counts
        $roleIds = $roles->pluck('id')->toArray();
        $permCounts = DB::table('role_has_permissions')
            ->whereIn('role_id', $roleIds)
            ->selectRaw('role_id, COUNT(*) as cnt')
            ->groupBy('role_id')
            ->pluck('cnt', 'role_id')->toArray();
        $userCounts = DB::table('model_has_roles')
            ->where('model_type', 'AlphaDirect\\User')
            ->whereIn('role_id', $roleIds)
            ->selectRaw('role_id, COUNT(*) as cnt')
            ->groupBy('role_id')
            ->pluck('cnt', 'role_id')->toArray();

        return response()->json([
            'data' => $roles->map(fn($r) => [
                'id'              => $r->id,
                'name'            => $r->name,
                'guardName'       => $r->guard_name,
                'permissionCount' => $permCounts[$r->id] ?? 0,
                'userCount'       => $userCounts[$r->id] ?? 0,
                'createdAt'       => $r->created_at,
            ]),
        ]);
    }

    public function roleDetail(int $id): JsonResponse
    {
        $role = DB::table('roles')->where('id', $id)->first();
        if (!$role) return response()->json(['message' => 'Role not found.'], 404);

        $permissions = DB::table('role_has_permissions as rp')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('rp.role_id', $id)
            ->pluck('p.name')
            ->toArray();

        $users = DB::table('model_has_roles as mr')
            ->join('users as u', 'u.id', '=', 'mr.model_id')
            ->where('mr.role_id', $id)
            ->where('mr.model_type', 'AlphaDirect\\User')
            ->select('u.id', 'u.firstName', 'u.lastName', 'u.email')
            ->get();

        return response()->json([
            'data' => [
                'id'          => $role->id,
                'name'        => $role->name,
                'permissions' => $permissions,
                'users'       => $users,
            ],
        ]);
    }

    public function permissions(): JsonResponse
    {
        $perms = DB::table('permissions')->orderBy('name')->get(['id', 'name', 'guard_name']);

        // Group by module prefix (e.g., "policy-list" → "policy")
        $grouped = $perms->groupBy(fn($p) => explode('-', $p->name)[0] ?? 'other');

        return response()->json([
            'data'    => $perms,
            'grouped' => $grouped->map(fn($items, $module) => [
                'module'      => $module,
                'permissions' => $items->pluck('name')->values(),
            ])->values(),
        ]);
    }

    public function storeRole(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ]);

        $roleId = DB::table('roles')->insertGetId([
            'name'       => $data['name'],
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (!empty($data['permissions'])) {
            $this->syncPermissions($roleId, $data['permissions']);
        }

        return response()->json(['message' => 'Role created.', 'data' => ['id' => $roleId]], 201);
    }

    public function updateRole(Request $request, int $id): JsonResponse
    {
        $role = DB::table('roles')->where('id', $id)->first();
        if (!$role) return response()->json(['message' => 'Role not found.'], 404);

        $data = $request->validate([
            'name'        => "required|string|max:100|unique:roles,name,{$id}",
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ]);

        DB::table('roles')->where('id', $id)->update([
            'name' => $data['name'], 'updated_at' => now(),
        ]);

        if (isset($data['permissions'])) {
            $this->syncPermissions($id, $data['permissions']);
        }

        return response()->json(['message' => 'Role updated.']);
    }

    public function destroyRole(int $id): JsonResponse
    {
        DB::table('role_has_permissions')->where('role_id', $id)->delete();
        DB::table('model_has_roles')->where('role_id', $id)->delete();
        DB::table('roles')->where('id', $id)->delete();
        $this->flushPermissionCache();
        return response()->json(['message' => 'Role deleted.']);
    }

    /**
     * Additive: attach a role to a user without removing any other roles.
     * Idempotent — calling twice with the same payload is a no-op.
     *
     * The previous implementation deleted ALL existing roles before
     * inserting the new one. That locked admins out of the admin UI as
     * soon as they switched themselves to a Grade role for RBAC testing
     * (Super Admin / Admin / Manager all got wiped in one click). Use
     * removeRole below to explicitly drop a role.
     */
    public function assignRole(Request $request, int $userId, ?int $roleId = null): JsonResponse
    {
        // Accept role id either from URL path (REST style:
        // POST /users/{id}/roles/{roleId}) OR from request body (legacy:
        // POST /users/{id}/assign-role with {"role_id": N}).
        if ($roleId === null) {
            $data = $request->validate(['role_id' => 'required|integer|exists:roles,id']);
            $roleId = (int) $data['role_id'];
        } else {
            // Validate that the path-supplied id exists
            if (!DB::table('roles')->where('id', $roleId)->exists()) {
                return response()->json(['message' => 'Role not found.'], 404);
            }
        }

        $exists = DB::table('model_has_roles')
            ->where('role_id', $roleId)
            ->where('model_id', $userId)
            ->where('model_type', 'AlphaDirect\\User')
            ->exists();

        // No Claims Declaration approvers are UNCAPPED (UW, 2026-09-07 — the
        // old two-person limit was removed so any number of named people can
        // approve). A cap applies only if one is deliberately configured; see
        // ClaimsWaiverApprovalGate::maxApprovers(), where 0 means unlimited.
        // This is the only place the role gets attached, so the check belongs
        // here rather than at approval time — hitting it must be visible when
        // someone is being added, not silently at the point of approval.
        if (!$exists) {
            $roleName = DB::table('roles')->where('id', $roleId)->value('name');
            if (\AlphaDirect\Services\ClaimsWaiverApprovalGate::isApproverRole($roleName)) {
                $cap = \AlphaDirect\Services\ClaimsWaiverApprovalGate::maxApprovers();
                if ($cap > 0 && \count(\AlphaDirect\Services\ClaimsWaiverApprovalGate::approverIds()) >= $cap) {
                    return response()->json([
                        'message' => "Only {$cap} users may hold \"{$roleName}\" at a time. Remove an existing approver first.",
                    ], 422);
                }
            }

            DB::table('model_has_roles')->insert([
                'role_id'    => $roleId,
                'model_type' => 'AlphaDirect\\User',
                'model_id'   => $userId,
            ]);

            // Who may approve a No Claims Declaration is itself an auditable
            // event: the grant is the control, so it belongs on the log next
            // to the approvals it enables.
            $this->logApproverRoleChange($userId, $roleName, true);
        }

        $this->flushPermissionCache();

        return response()->json(['message' => 'Role attached.']);
    }

    /**
     * Detach a single role from a user. Does NOT touch other roles.
     */
    public function removeRole(int $userId, int $roleId): JsonResponse
    {
        $roleName = DB::table('roles')->where('id', $roleId)->value('name');

        $removed = DB::table('model_has_roles')
            ->where('role_id', $roleId)
            ->where('model_id', $userId)
            ->where('model_type', 'AlphaDirect\\User')
            ->delete();

        // Losing the approver role is as auditable as gaining it.
        if ($removed) {
            $this->logApproverRoleChange($userId, $roleName, false);
        }

        $this->flushPermissionCache();

        return response()->json(['message' => 'Role detached.']);
    }

    /**
     * List the user's current roles. Marks each row with `is_grade` so
     * the UI can render Grade-* roles distinctly (those are the validation
     * rule groupings vs. functional roles like Super Admin).
     */
    public function userRoles(int $userId): JsonResponse
    {
        $rows = DB::table('model_has_roles as mhr')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->where('mhr.model_id', $userId)
            ->where('mhr.model_type', 'AlphaDirect\\User')
            ->orderBy('r.name')
            ->get([
                'r.id',
                'r.name',
                'r.guard_name',
                'r.rule_group',
            ])
            ->map(function ($r) {
                $r->is_grade = !empty($r->rule_group);
                return $r;
            });

        return response()->json(['data' => $rows]);
    }

    /**
     * Record a No Claims Declaration approver being added or removed.
     *
     * Only that role is logged here — it is the one whose membership decides
     * whether a policy document can be signed off, so "who made X an approver,
     * and when" has to be answerable. Never fatal: an audit write must not be
     * able to fail the role change itself.
     */
    private function logApproverRoleChange(int $userId, ?string $roleName, bool $granted): void
    {
        if (!\AlphaDirect\Services\ClaimsWaiverApprovalGate::isApproverRole($roleName)) {
            return;
        }

        try {
            $subject = \AlphaDirect\User::find($userId);
            // Same column-safe lookup as the tab: `users` has firstName/lastName.
            $names = \AlphaDirect\Services\ClaimsWaiverApprovalGate::userNames([$userId]);
            $entry = activity('Policy Claims Waiver')->causedBy(auth()->user())
                ->withProperties([
                    'user_id'   => $userId,
                    'user_name' => $names[$userId] ?? null,
                    'role'      => $roleName,
                ]);
            if ($subject) {
                $entry = $entry->performedOn($subject);
            }
            $entry->log($granted
                ? 'No Claims Declaration approver added'
                : 'No Claims Declaration approver removed');
        } catch (\Throwable $e) {
            \Log::warning('logApproverRoleChange failed: ' . $e->getMessage());
        }
    }

    // =========================================================================
    //  Roles Under Roles
    // =========================================================================

    public function getUnderRoles(int $roleId): JsonResponse
    {
        $roleUnderRoles = DB::table('role_under_roles')
            ->where('role_id', $roleId)
            ->first();

        if (!$roleUnderRoles) {
            return response()->json(['data' => ['under_role_ids' => []]]);
        }

        // The legacy Blade roles page saved the checkbox values as strings
        // (["3","5"]), while the React page compares against numeric role ids,
        // so nothing showed as ticked. Normalise to ints before returning.
        $underRoleIds = array_values(array_map(
            'intval',
            array_filter((array) (json_decode($roleUnderRoles->under_roles_ids, true) ?? []), 'is_numeric')
        ));

        return response()->json(['data' => ['under_role_ids' => $underRoleIds]]);
    }

    public function storeUnderRoles(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
            // 'present', NOT 'required': an empty array is a legitimate save —
            // it means "this role has no under-roles". 'required' rejects [],
            // so saving a role with nothing ticked on the Under Roles tab 422'd
            // with "The given data was invalid." AFTER the permissions PUT had
            // already succeeded — the grant applied, the user still saw an error.
            'under_role_ids' => 'present|array',
            'under_role_ids.*' => 'integer|exists:roles,id',
        ]);

        DB::table('role_under_roles')->updateOrInsert(
            ['role_id' => $data['role_id']],
            ['under_roles_ids' => json_encode($data['under_role_ids']), 'updated_at' => now()]
        );

        return response()->json(['message' => 'Roles Under Roles updated.']);
    }

    private function syncPermissions(int $roleId, array $permissionNames): void
    {
        DB::table('role_has_permissions')->where('role_id', $roleId)->delete();

        $permIds = DB::table('permissions')->whereIn('name', $permissionNames)->pluck('id');

        $rows = $permIds->map(fn($pid) => ['permission_id' => $pid, 'role_id' => $roleId])->toArray();
        if (!empty($rows)) {
            DB::table('role_has_permissions')->insert($rows);
        }

        $this->flushPermissionCache();
    }

    /**
     * Invalidate Spatie's permission cache. Required because this controller
     * writes the RBAC pivot tables with raw DB::table() queries, which (unlike
     * Spatie's Eloquent methods e.g. $role->syncPermissions()) never fire the
     * model events that flush the cache. Without this, a freshly granted
     * permission/role stays invisible to the `permission:` / `role:` middleware
     * for up to the cache TTL (24h) even though the grant is in the DB.
     */
    private function flushPermissionCache(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
