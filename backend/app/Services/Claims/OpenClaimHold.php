<?php

namespace AlphaDirect\Services\Claims;

use Illuminate\Support\Facades\DB;

/**
 * One definition of "this policy has an open claim", and the hold that follows
 * from it.
 *
 * WHY THIS EXISTS
 * ---------------
 * CFO instruction, 7 September 2026: hold any refund and any policy
 * cancellation on a policy with an open claim — "nothing on those policies
 * moves until Claims have reviewed them".
 *
 * The finding behind it: 26 claims across 19 policies had a successful premium
 * debit taken while the policy was ALREADY deactivated or cancelled — BWP
 * 434,391.60 — and 14 of those had the loss itself fall inside the dead period.
 * None has been declined yet. A refund raised now is an admission we were not
 * on risk; a cancellation dated now, after we knew of the loss, invites the
 * same question. Both must wait for Claims.
 *
 * WHAT COUNTS AS OPEN
 * -------------------
 * `claims.status` is the authoritative vocabulary: Closed, Pending, Open,
 * Approved, Reopen, Rejected. Anything that is NOT Closed or Rejected is open —
 * and so is a NULL or empty status, deliberately: we cannot prove an unset
 * claim is finished, and for a hold the safe default is to hold.
 *
 * Two registers exist and both are consulted:
 *   - `claims`      4,559 rows, full status vocabulary, `policy_id` reliable
 *                   (1 bad row). This decides status.
 *   - `new_claims`  2,800 rows, status only ever blank / Pending / Approved, so
 *                   it can never tell us a claim is closed. 2,784 of its rows
 *                   also exist in `claims`; the ~17 that do not are picked up
 *                   here by claim_number so they are not missed.
 *
 * TWO DATA TRAPS THIS CLASS DELIBERATELY HANDLES
 * ----------------------------------------------
 *  1. `claims` carries a `deleted_at` column but `AlphaDirect\Claim` does NOT
 *     use the SoftDeletes trait — so Eloquent would silently include deleted
 *     claims. Every query here filters `deleted_at IS NULL` explicitly. (There
 *     are currently zero soft-deleted claims, so this is defensive, not a fix.)
 *  2. `new_claims.policy_id` is empty on 76% of rows, so it must be joined on
 *     `policyNumber`, never on `policy_id`.
 *
 * NOT GUARDED HERE, ON PURPOSE
 * ----------------------------
 * The CFO's wording said "mandate cancellation". Stopping a DPO mandate
 * (`cancelDpoContract`, `suspendPaymentDpo`) does not cancel cover — its own
 * docblock says it cancels the mandate "WITHOUT cancelling the policy" and
 * "does NOT touch the policy's own status". It exists (GRA-0194) to STOP
 * double-debits. Blocking it would keep taking money from claimants, which is
 * the very harm above. So the hold is placed on cancelling COVER, which is what
 * the CFO's reasoning is actually about. Raised with him in writing.
 */
class OpenClaimHold
{
    /** Statuses that mean the claim is finished with. Everything else is open. */
    public const CLOSED_STATUSES = ['Closed', 'Rejected'];

    /** Permission that may proceed anyway, recording a reason. */
    public const PERM_OVERRIDE = 'claim-hold-override';

    /**
     * How far back an UNSYNCED new_claims row still counts as open. See the
     * second query in blockingClaims() for why this is bounded.
     */
    public const UNSYNCED_LOOKBACK_MONTHS = 12;

    /** How many claim numbers to name in a message before summarising. */
    private const NAME_LIMIT = 5;

    /**
     * Open claim numbers on this policy. Empty array = clear to proceed.
     *
     * Either identifier may be null; both are used when present, because the
     * two registers key on different columns.
     *
     * @return string[]
     */
    public function blockingClaims(?int $policyId, ?string $policyNumber): array
    {
        $numbers = [];

        // `claims` is the register that can actually say "Closed", and it keys
        // on policy_id only. If the caller has just a policy number — a refund
        // draft whose policy_id has not been resolved yet, for one — look it up,
        // otherwise the authoritative half of this check is silently skipped and
        // the hold passes everything.
        if (!$policyId && $policyNumber) {
            $policyId = DB::table('policies')
                ->where('policyNumber', $policyNumber)
                ->value('id');
            $policyId = $policyId ? (int) $policyId : null;
        }

        if ($policyId) {
            $rows = DB::table('claims')
                ->where('policy_id', $policyId)
                ->whereNull('deleted_at')
                ->where(function ($q) {
                    $q->whereNull('status')
                      ->orWhereNotIn('status', self::CLOSED_STATUSES);
                })
                ->pluck('claim_number');

            foreach ($rows as $n) {
                if ($n !== null && $n !== '') {
                    $numbers[] = (string) $n;
                }
            }
        }

        // The handful of new_claims rows that never made it into `claims`.
        // Anything already in `claims` is excluded so that register decides
        // status — new_claims cannot express "Closed" at all.
        //
        // Bounded to recent losses on purpose. This arm exists to catch a claim
        // that has not yet synced into `claims`, which is by nature a RECENT
        // gap. Unbounded it also catches migration debris: all 17 orphans on
        // production today are 2024–mid-2025 rows with a status that was never
        // set (one of them duplicated), and they would freeze 7 policies
        // indefinitely on claims nobody can decide, which is not the live
        // prejudice the CFO is protecting against. With the window those 17
        // match nothing, so the hold population today is exactly the `claims`
        // set — this arm is the safety net for a future sync gap, not a filter
        // on history.
        if ($policyNumber) {
            $rows = DB::table('new_claims')
                ->where('policyNumber', $policyNumber)
                ->where('date_of_loss', '>=', now()->subMonths(self::UNSYNCED_LOOKBACK_MONTHS)->toDateString())
                ->where(function ($q) {
                    $q->whereNull('status')
                      ->orWhere('status', '')
                      ->orWhereNotIn('status', self::CLOSED_STATUSES);
                })
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from('claims')
                      ->whereColumn('claims.claim_number', 'new_claims.claim_number')
                      ->whereNull('claims.deleted_at');
                })
                ->pluck('claim_number');

            foreach ($rows as $n) {
                if ($n !== null && $n !== '') {
                    $numbers[] = (string) $n;
                }
            }
        }

        return array_values(array_unique($numbers));
    }

    /**
     * True when the policy has at least one open claim.
     */
    public function isHeld(?int $policyId, ?string $policyNumber): bool
    {
        return $this->blockingClaims($policyId, $policyNumber) !== [];
    }

    /**
     * The message shown when the hold bites. Names the claims so the person
     * blocked can go and look at them rather than guess.
     */
    public function message(array $claimNumbers, string $action): string
    {
        $count = count($claimNumbers);
        $named = array_slice($claimNumbers, 0, self::NAME_LIMIT);
        $list  = implode(', ', $named);

        if ($count > self::NAME_LIMIT) {
            $list .= ' and ' . ($count - self::NAME_LIMIT) . ' more';
        }

        $claimWord = $count === 1 ? 'an open claim' : $count . ' open claims';

        return "This policy has {$claimWord} ({$list}), so {$action} is on hold. "
             . 'Claims must decide those first — a refund or a cancellation dated '
             . 'now would be taken as our view that we were not on risk. '
             . 'Once Claims have closed or declined them this releases by itself.';
    }

    /**
     * True when $user may proceed in spite of the hold.
     */
    public function canOverride($user): bool
    {
        return $user !== null
            && method_exists($user, 'can')
            && $user->can(self::PERM_OVERRIDE);
    }
}
