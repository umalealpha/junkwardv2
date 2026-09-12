<?php

namespace AlphaDirect\Services;

use AlphaDirect\Config;
use AlphaDirect\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who may APPROVE a No Claims Declaration.
 *
 * Design (UW, 2026-08-31): "make one role so I can assign any person who can
 * approve that". Approval is therefore role-driven but effectively user-wise —
 * the role exists once and is attached to the named individuals in /roles.
 *
 *   - Underwriting uploads (existing `policy-claims-waiver-upload` permission).
 *   - Approval requires membership of self::ROLE. Nothing else grants it:
 *     there is NO Super Admin / Manager / Admin bypass here, unlike
 *     AuthGate::canPerform. Same fail-closed posture as
 *     Services\Bonds\BondsIssuanceGate::userMayApprove() — an unconfigured
 *     role means nobody can approve, never everybody.
 *   - ANY number of people may hold the role (UW, 2026-09-07: "remove the
 *     two-person limit, we must be able to set multiple people"). A cap is
 *     only applied when one is explicitly configured — see maxApprovers().
 *
 * The role is NOT seeded by a migration — per standing instruction, rights are
 * granted through the /roles UI. Create it there with the exact name in
 * self::ROLE and assign it to every approver. Until then
 * self::configWarning() tells the UI (and the operator) what is missing.
 *
 * Role matching is forgiving about case and punctuation, and accepts the
 * aliases below, because the role is typed by hand in /roles and a typo would
 * otherwise silently lock approval out with no visible cause.
 */
class ClaimsWaiverApprovalGate
{
    /** Canonical role name to create in /roles. */
    public const ROLE = 'No Claims Declaration Approver';

    /** Accepted spellings, normalised (lowercase, alphanumerics only). */
    private const ROLE_ALIASES = [
        'noclaimsdeclarationapprover',
        'noclaimsdeclarationapproval',
        'noclaimsapprover',
        'ncdapprover',
        'claimswaiverapprover',
    ];

    /**
     * Optional permission route to the same right, for the day a permission
     * row can be created from /roles. Absent everywhere today; checked in a
     * try/catch so a missing row denies instead of throwing.
     */
    public const PERMISSION = 'policy-claims-waiver-approve';

    /**
     * No built-in cap on simultaneous approvers (0 = unlimited).
     *
     * Was 2 until 2026-09-07, when UW asked for the limit to be lifted so any
     * number of named people can be made approvers.
     */
    public const MAX_APPROVERS = 0;

    /** `config` table key that imposes a cap without a deploy. */
    private const MAX_APPROVERS_CONFIG_KEY = 'ncd.max_approvers';

    private const MODEL_TYPE = 'AlphaDirect\User';

    /**
     * Cap on simultaneous approvers, or 0 for unlimited (the default).
     *
     * A limit is applied only when someone deliberately sets one in the
     * `config` table (key self::MAX_APPROVERS_CONFIG_KEY) — read from there
     * rather than .env, because env() is unreadable once config is cached on
     * the servers. Anything unusable, absent or <= 0 means unlimited.
     */
    public static function maxApprovers(): int
    {
        try {
            $configured = (int) Config::where('key', self::MAX_APPROVERS_CONFIG_KEY)->value('value');

            return $configured > 0 ? $configured : self::MAX_APPROVERS;
        } catch (\Throwable $e) {
            return self::MAX_APPROVERS;
        }
    }

    /** Normalise a role name for comparison: lowercase, alphanumerics only. */
    private static function normalise(?string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower((string) $name)) ?? '';
    }

    /** Is this role name the approver role (any accepted spelling)? */
    public static function isApproverRole(?string $roleName): bool
    {
        $normalised = self::normalise($roleName);

        return $normalised !== '' && in_array($normalised, self::ROLE_ALIASES, true);
    }

    /**
     * Ids of every role row that counts as the approver role.
     *
     * @return array<int,int>
     */
    public static function roleIds(): array
    {
        try {
            return DB::table('roles')
                ->get(['id', 'name'])
                ->filter(fn ($r) => self::isApproverRole($r->name))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Fail-CLOSED approval check. True only for a holder of the approver role
     * (or of self::PERMISSION, where that row exists). Admin-equivalent roles
     * do NOT bypass.
     */
    public static function userMayApprove(?User $user = null): bool
    {
        $user = $user ?: auth()->user();
        if ($user === null) {
            return false;
        }

        $roleIds = self::roleIds();
        if (!empty($roleIds)) {
            try {
                $holdsRole = DB::table('model_has_roles')
                    ->whereIn('role_id', $roleIds)
                    ->where('model_type', self::MODEL_TYPE)
                    ->where('model_id', $user->id)
                    ->exists();
                if ($holdsRole) {
                    return true;
                }
            } catch (\Throwable $e) {
                // fall through to the permission check
            }
        }

        try {
            return $user->hasPermissionTo(self::PERMISSION);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Ids of the users currently holding the approver role.
     *
     * @return array<int,int>
     */
    public static function approverIds(): array
    {
        $roleIds = self::roleIds();
        if (empty($roleIds)) {
            return [];
        }

        try {
            return DB::table('model_has_roles')
                ->whereIn('role_id', $roleIds)
                ->where('model_type', self::MODEL_TYPE)
                ->pluck('model_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Display names for a set of user ids, keyed by id.
     *
     * `users` on this system has firstName / lastName, NOT the Laravel default
     * `name` — a bare pluck('name', 'id') comes back empty (or throws) and the
     * approver line renders blank, so the columns are probed before use. Falls
     * back to `name` where a deployment does have it, then to the email.
     *
     * @param  array<int,int|null>  $ids
     * @return array<int,string>
     */
    public static function userNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) {
            return [];
        }

        try {
            $columns = ['id'];
            foreach (['firstName', 'lastName', 'name', 'email'] as $candidate) {
                if (Schema::hasColumn('users', $candidate)) {
                    $columns[] = $candidate;
                }
            }

            return DB::table('users')
                ->whereIn('id', $ids)
                ->get($columns)
                ->mapWithKeys(function ($u) {
                    $full = trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? ''));
                    $label = $full !== '' ? $full : (trim((string) ($u->name ?? '')) ?: (string) ($u->email ?? ''));

                    return [(int) $u->id => $label];
                })
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * The approvers, for display on the tab ("Awaiting approval by A or B").
     *
     * @return array<int,array{id:int,name:?string}>
     */
    public static function approvers(): array
    {
        $names = self::userNames(self::approverIds());

        $out = [];
        foreach ($names as $id => $name) {
            $out[] = ['id' => $id, 'name' => $name !== '' ? $name : null];
        }
        usort($out, fn ($a, $b) => strcasecmp((string) $a['name'], (string) $b['name']));

        return $out;
    }

    /**
     * Human-readable setup gap, or null when the gate is usable. Surfaced on
     * the tab so "nobody can approve" is never a silent dead end.
     */
    public static function configWarning(): ?string
    {
        if (!Schema::hasTable('policy_claims_waiver_approvals')) {
            return 'No Claims Declaration approval storage is not migrated on this environment. Ask an admin to run: php artisan migrate';
        }
        if (empty(self::roleIds())) {
            return 'No approver role exists yet. Create the role "' . self::ROLE . '" in Roles & Permissions and assign it to the approvers.';
        }
        if (empty(self::approverIds())) {
            return 'The role "' . self::ROLE . '" exists but is not assigned to anyone, so no one can approve. Assign it in Roles & Permissions.';
        }

        return null;
    }
}
