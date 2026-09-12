<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\HelpDeskTicket;
use AlphaDirect\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregated "/me/*" surface that backs the User Profile page.
 *
 * Returns identity, account timestamps, manager, role/permission lists,
 * and a small workload summary in a single round-trip. The frontend used
 * to read most of this from localStorage (login payload); this endpoint
 * is the source of truth so the page can show LIVE values for last_login,
 * manager, and ticket counts.
 *
 *   GET /api/v1/me/profile
 */
class MeController extends Controller
{
    public function profile(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        // Reload to guarantee fresh last_login_at / password_changed_at — the
        // cached auth user can lag behind a same-request update done in the
        // login handler.
        $user = User::find($user->id);

        $roles       = $user->roles->pluck('name')->values()->toArray();
        $permissions = $user->getAllPermissions()->pluck('name')->values()->toArray();

        // Reporting manager — optional. The column is brand-new (see
        // 2026_06_13_120000_add_profile_columns_to_users_table) so guard
        // against environments that haven't migrated yet.
        $manager = null;
        if (Schema::hasColumn('users', 'reporting_manager_id') && $user->reporting_manager_id) {
            $mgr = User::find($user->reporting_manager_id);
            if ($mgr) {
                $manager = [
                    'id'    => $mgr->id,
                    'name'  => trim($mgr->firstName . ' ' . $mgr->lastName),
                    'email' => $mgr->email,
                ];
            }
        }

        // Ticket workload — re-uses HelpDeskTicket so the counts honour the
        // same filtering rules as the Help Desk list. One small grouped
        // query, indexed columns only.
        $ticketCounts = HelpDeskTicket::query()
            ->where('reporter_id', $user->id)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');
        $totalTickets   = (int) $ticketCounts->sum();
        $openTickets    = (int) (($ticketCounts['open'] ?? 0) + ($ticketCounts['in_progress'] ?? 0));

        // Unread notifications — best-effort. If the table or column shape
        // is unexpected (older deploy), we silently return 0 rather than
        // 500ing the whole profile call.
        $unreadNotifications = 0;
        try {
            if (Schema::hasTable('notifications')) {
                $unreadNotifications = (int) DB::table('notifications')
                    ->where('notifiable_id', $user->id)
                    ->whereNull('read_at')
                    ->count();
            }
        } catch (\Throwable $e) {
            $unreadNotifications = 0;
        }

        // UW rules mapped to this user — each of the user's roles can be bound
        // to a Validation Rule Group (roles.rule_group); the masters table
        // holds the human-readable code + description. Same join the admin
        // page at /policy-validation/roles uses.
        $uwRules = [];
        try {
            if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'rule_group')) {
                $uwRules = DB::table('roles as r')
                    ->join('model_has_roles as mhr', 'mhr.role_id', '=', 'r.id')
                    ->leftJoin('tb_prvalidationrulegroupmasters as g',
                        'g.n_PrValidationRuleGroupMasters_PK', '=', 'r.rule_group')
                    ->where('mhr.model_id', $user->id)
                    ->where('mhr.model_type', \AlphaDirect\User::class)
                    ->select([
                        'r.id as role_id', 'r.name as role_name', 'r.rule_group',
                        'g.s_RuleCode as rule_group_code',
                        'g.s_RuleDesc as rule_group_desc',
                    ])
                    ->orderBy('r.id')
                    ->get()
                    ->map(fn ($row) => [
                        'role_id'         => (int) $row->role_id,
                        'role_name'       => $row->role_name,
                        'rule_group'      => $row->rule_group !== null ? (int) $row->rule_group : null,
                        'rule_group_code' => $row->rule_group_code,
                        'rule_group_desc' => $row->rule_group_desc,
                    ])
                    ->values()
                    ->all();
            }
        } catch (\Throwable $e) {
            Log::info('MeController: uw_rules lookup failed', ['error' => $e->getMessage()]);
            $uwRules = [];
        }

        // Pending approvals — count of IN_APPROVAL policy_actions that aren't
        // drafts. This is the global UW queue count (same gating the UW
        // controller applies); the page links through to /underwriting/queue
        // for the breakdown a user can act on.
        $pendingApprovals = 0;
        try {
            $pendingApprovals = (int) DB::table('policy_actions as pa')
                ->join('policies as p', 'p.id', '=', 'pa.policy_id')
                ->where('pa.status', 'IN_APPROVAL')
                ->where('p.is_draft', 0)
                ->whereNull('pa.deleted_at')
                ->count();
        } catch (\Throwable $e) {
            Log::info('MeController: pending_approvals lookup failed', ['error' => $e->getMessage()]);
            $pendingApprovals = 0;
        }

        $hasLastLogin    = Schema::hasColumn('users', 'last_login_at');
        $hasAvatar       = Schema::hasColumn('users', 'avatar_path');
        $avatarPath      = $hasAvatar ? $user->avatar_path : null;
        $avatarUrl       = MeAvatarController::presign($avatarPath);

        return response()->json([
            'data' => [
                'id'          => $user->id,
                'name'        => trim($user->firstName . ' ' . $user->lastName),
                'firstName'   => $user->firstName,
                'lastName'    => $user->lastName,
                'email'       => $user->email,
                'avatar_path' => $avatarPath,
                'avatar_url'  => $avatarUrl,
                'active'      => (bool) ($user->active ?? true),
                'roles'       => $roles,
                'role'        => $roles[0] ?? null,
                'permissions' => $permissions,
                'account'     => [
                    'created_at'          => optional($user->created_at)->toIso8601String(),
                    'last_login_at'       => $hasLastLogin ? optional($user->last_login_at)->toIso8601String() : null,
                    'password_changed_at' => optional($user->password_changed_at)->toIso8601String(),
                    'is_first_login'      => (bool) ($user->is_first_login ?? false),
                ],
                'manager'  => $manager,
                'uw_rules' => $uwRules,
                'stats'    => [
                    'tickets_raised'        => $totalTickets,
                    'tickets_open'          => $openTickets,
                    'notifications_unread'  => $unreadNotifications,
                    'pending_approvals'     => $pendingApprovals,
                ],
            ],
        ]);
    }
}
