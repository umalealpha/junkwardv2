<?php

namespace AlphaDirect\Services\Bonds;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Bonds & Guarantees (product 23) issuance controls.
 *
 * UW rule, 2026-08-27:
 *   1. A Bonds transaction may only be APPROVED by EXCO — i.e. by a user
 *      holding the single `bonds-approve` permission. The approver is
 *      stamped on the action (policy_actions.exco_approved_by/_at).
 *   2. A Bonds policy may only be ISSUED once
 *        a. the action carries that EXCO approval stamp, AND
 *        b. collateral is captured and CONFIRMED on the bond schedule, AND
 *        c. (UW 2026-08-29) the collateral PROOF document on file has been
 *           approved by EXCO. Anyone may upload it; only a
 *           `bonds-approve` holder may approve it.
 *      No collateral, no bond.
 *
 * Both controls run on EVERY Bonds transaction — new business, renewal,
 * endorsement, reinstatement — because each one moves the company's
 * exposure under the bond.
 *
 * Enforcement points (all additive):
 *   - PolicyCreateController::approvePolicy        approve: 403 + stamp
 *   - UnderwritingController::decide               UW queue approve: 403 + stamp
 *   - PolicyCreateController::issuePolicy          hard gate on the action being issued
 *   - PolicyValidationRuleEngine::evaluateActions  greys out the Issue button in
 *     the UI and blocks the `policy.action:canIssue` route middleware
 *
 * Fail-safe posture differs by control on purpose. The rule-engine hook is
 * fail-OPEN (a thrown query must never freeze normal underwriting), but
 * issuePolicy()'s own check is fail-CLOSED: a bond that cannot be proven
 * approved-and-collateralised must not go on risk.
 */
final class BondsIssuanceGate
{
    /** Guarantee — the Bonds & Guarantees product. */
    public const PRODUCT_ID = 23;

    /** The one permission EXCO holds. Create it in /roles, not in a migration. */
    public const PERMISSION = 'bonds-approve';

    /** Per-request memo: policy_id => bool. */
    private static array $isBondsMemo = [];

    /**
     * Is this a Bonds policy?
     *
     * Product 23 is the Bonds product, but the BONDSANDGUARANTEES coverage
     * can in principle be written on another company line, so the presence
     * of a bond schedule counts too.
     */
    public static function isBondsPolicy(int $policyId): bool
    {
        if ($policyId <= 0) return false;
        if (array_key_exists($policyId, self::$isBondsMemo)) return self::$isBondsMemo[$policyId];

        $isBonds = false;
        try {
            $productId = (int) DB::table('policies')->where('id', $policyId)->value('product_id');
            $isBonds   = $productId === self::PRODUCT_ID;

            if (!$isBonds && Schema::hasTable('bonds_coverages')) {
                $isBonds = DB::table('bonds_coverages')
                    ->where('policy_id', $policyId)
                    ->whereNull('deleted_at')
                    ->exists();
            }
        } catch (\Throwable $e) {
            Log::warning('BondsIssuanceGate::isBondsPolicy failed', ['policy_id' => $policyId, 'err' => $e->getMessage()]);
        }

        return self::$isBondsMemo[$policyId] = $isBonds;
    }

    /**
     * Does this user hold the EXCO bond-approval permission?
     *
     * Deliberately NO role bypass — not even Super Admin. This is a
     * governance control and a technical super-user hole would defeat it.
     * Assign `bonds-approve` in /roles to whoever must approve bonds.
     */
    public static function userMayApprove(?int $userId = null): bool
    {
        try {
            $user = $userId
                ? \AlphaDirect\User::find($userId)
                : auth()->user();
            if (!$user) return false;

            return $user->hasPermissionTo(self::PERMISSION);
        } catch (\Throwable $e) {
            // Spatie throws PermissionDoesNotExist until the permission is
            // created in /roles. Until then nobody may approve a bond —
            // fail-closed, which is the intent of the control.
            Log::warning('BondsIssuanceGate::userMayApprove denied', [
                'user_id' => $userId,
                'err'     => $e->getMessage(),
            ]);
            return false;
        }
    }

    /** Stamp the EXCO approver on the action. Called right after status -> APPROVED. */
    public static function recordApproval(int $actionId, ?int $userId = null): void
    {
        try {
            DB::table('policy_actions')->where('id', $actionId)->update([
                'exco_approved_by' => $userId ?? auth()->id(),
                'exco_approved_at' => now(),
                'updated_at'       => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('BondsIssuanceGate::recordApproval failed', ['action_id' => $actionId, 'err' => $e->getMessage()]);
        }
    }

    /**
     * Everything standing between this action and issue, as operator-facing
     * one-liners. Empty array = clear to issue.
     *
     * @return array<int,string>
     */
    public static function issueBlockers(int $policyId, int $actionId): array
    {
        $blockers = [];

        // 1. EXCO approval stamp on the action being issued.
        $action     = DB::table('policy_actions')->where('id', $actionId)->first();
        $approvedBy = (int) ($action->exco_approved_by ?? 0);

        if ($approvedBy <= 0) {
            $blockers[] = 'Bonds: this transaction has not been approved by EXCO. An EXCO approver must approve it before it can be issued.';
        } elseif (!self::userMayApprove($approvedBy)) {
            // The approver has since lost the permission, so the stamp no
            // longer represents an EXCO decision.
            $blockers[] = 'Bonds: the recorded approver no longer holds EXCO bond-approval rights. The transaction must be re-approved by EXCO.';
        }

        // 2. Collateral captured and confirmed on the bond schedule.
        $collateral = self::collateralBlocker($policyId, $actionId);
        if ($collateral) $blockers[] = $collateral;

        // 3. The collateral PROOF document, approved by EXCO.
        $document = self::collateralDocumentBlocker($policyId, $actionId);
        if ($document) $blockers[] = $document;

        return $blockers;
    }

    /**
     * Collateral blocker for this policy-action, or null when it is clear.
     *
     * Rows are read for the action being issued. If replication has not
     * carried the schedule onto this action, fall back to the policy's most
     * recent live bond schedule — collateral is held against the BOND, not
     * against one transaction, and a replication gap must not read as
     * "no collateral".
     */
    public static function collateralBlocker(int $policyId, int $actionId): ?string
    {
        if (!Schema::hasTable('bonds_coverages')) {
            return 'Bonds: the bond schedule table is missing. Ask an admin to run migrations.';
        }

        $rows = DB::table('bonds_coverages')
            ->where('policy_id', $policyId)
            ->where('action_id', $actionId)
            ->whereNull('deleted_at')
            ->get();

        if ($rows->isEmpty()) {
            $rows = DB::table('bonds_coverages')
                ->where('policy_id', $policyId)
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->limit(1)
                ->get();
        }

        if ($rows->isEmpty()) {
            return 'Bonds: no bond schedule captured, so no collateral is on file. Capture the bond schedule and its collateral before issuing.';
        }

        foreach ($rows as $row) {
            $type  = trim((string) ($row->collateral_type ?? ''));
            $value = (float) ($row->collateral_value ?? 0);

            if ($type === '' || $value <= 0) {
                return 'Bonds: collateral is not captured on the bond schedule. Record the collateral type and value before issuing — a bond cannot be issued without collateral.';
            }
            if ((int) ($row->collateral_confirmed ?? 0) !== 1) {
                return 'Bonds: collateral is captured but not confirmed. An EXCO approver must confirm the collateral is held before this bond can be issued.';
            }

            $expiry = $row->collateral_expiry_date ?? null;
            if ($expiry && \Carbon\Carbon::parse($expiry)->lt(now()->startOfDay())) {
                return 'Bonds: the collateral on file expired on ' . \Carbon\Carbon::parse($expiry)->format('d M Y')
                    . '. Replace it and re-confirm before issuing.';
            }
        }

        return null;
    }

    /**
     * Collateral-DOCUMENT blocker for this policy-action, or null when clear.
     *
     * The confirmation stamp above says an EXCO approver believes the security
     * is held; this says the PROOF of it — bank guarantee, cession, deposit
     * receipt — is on file and EXCO has approved that document.
     * Anyone may upload one; only a holder of `bonds-approve` may approve it
     * (Api\V1\BondsCollateralDocumentController).
     *
     * Scope mirrors collateralBlocker(): the action being issued first, and
     * when nothing is filed against it, the policy's own approved documents —
     * collateral is held against the BOND, and a replication gap or a
     * transaction opened after the document was approved must not read as
     * "no proof". A REJECTED or PENDING document never satisfies the gate.
     */
    public static function collateralDocumentBlocker(int $policyId, int $actionId): ?string
    {
        if (!Schema::hasTable('bonds_collateral_documents')) {
            return 'Bonds: the collateral document store is missing. Ask an admin to run migrations.';
        }

        $scoped = DB::table('bonds_collateral_documents')
            ->where('policy_id', $policyId)
            ->where('action_id', $actionId)
            ->whereNull('deleted_at')
            ->get();

        $rows = $scoped->isNotEmpty()
            ? $scoped
            : DB::table('bonds_collateral_documents')
                ->where('policy_id', $policyId)
                ->whereNull('deleted_at')
                ->get();

        if ($rows->isEmpty()) {
            return 'Bonds: no collateral document has been uploaded. Attach the collateral document (bank guarantee / cession / deposit receipt) and have EXCO approve it before issuing.';
        }

        foreach ($rows as $row) {
            if (strtoupper((string) ($row->status ?? '')) === 'APPROVED') return null;
        }

        $rejected = $rows->first(fn ($r) => strtoupper((string) ($r->status ?? '')) === 'REJECTED');
        if ($rejected && $rows->every(fn ($r) => strtoupper((string) ($r->status ?? '')) === 'REJECTED')) {
            return 'Bonds: the collateral document was REJECTED by EXCO'
                . ($rejected->rejection_reason ? ' — ' . $rejected->rejection_reason : '')
                . '. Upload a replacement and obtain EXCO approval before issuing.';
        }

        return 'Bonds: EXCO approval needed — the collateral document is uploaded but not yet approved by EXCO. The policy cannot be issued until it is approved.';
    }

    /**
     * Blocking rules in PolicyValidationRuleEngine shape, so the existing
     * `policy.action:canIssue` middleware and the Policy Detail gate preview
     * pick this up with no extra plumbing.
     *
     * @return array<int,array{ruleCode:string,message:string,blocks:array<int,string>}>
     */
    public static function blockingRules(int $policyId, int $actionId): array
    {
        try {
            if ($actionId <= 0 || !self::isBondsPolicy($policyId)) return [];

            // Only an APPROVED action is a candidate for issue, and only that
            // status is reported on. This matters because
            // EnsurePolicyActionAllowed evaluates the LATEST action by id, not
            // the one the operator selected: without this, a bonds policy
            // carrying a fresh endorse QUOTE would have the Issue route blocked
            // for an older, properly approved action. issuePolicy() runs the
            // same checks fail-closed against the action actually being issued,
            // so nothing is lost by staying quiet here.
            $status = (string) DB::table('policy_actions')->where('id', $actionId)->value('status');
            if (strtoupper($status) !== 'APPROVED') return [];

            return array_map(static fn (string $msg) => [
                'ruleCode' => 'BONDS-EXCO-COLLATERAL',
                'message'  => $msg,
                'blocks'   => ['canIssue'],
            ], self::issueBlockers($policyId, $actionId));
        } catch (\Throwable $e) {
            // Fail-open here only; issuePolicy() re-checks fail-closed.
            Log::warning('BondsIssuanceGate::blockingRules threw — returning none', [
                'policy_id' => $policyId,
                'action_id' => $actionId,
                'err'       => $e->getMessage(),
            ]);
            return [];
        }
    }
}
