<?php

namespace AlphaDirect\Services\Cancel;

use AlphaDirect\Ledger;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Policy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * PERMANENT "nothing renews after a cancel" enforcement (GRA-0132 follow-up).
 *
 * Once a CANCEL is ISSUED, cover has ended. Any RENEW / ANNIVERSARY-RENEW that
 * sits after that cancel is wrong: it re-opens cover that no longer exists,
 * invoices the customer for it, and — because it becomes the latest ISSUED
 * action — HIDES the cancel from every "is this policy cancelled?" test in the
 * codebase, so the policy then renews forever.
 *
 * The auto-renew crons already refuse to CREATE new renewals on a cancelled
 * policy. This service handles the other half: REMOVING renewals that already
 * exist when the cancel is issued (typically an ANNIVERSARY-RENEW quote or a
 * future RENEW batch the cron raised in advance, both created BEFORE the
 * cancel) and sweeping up anything any other path writes later.
 *
 * SINGLE SOURCE OF TRUTH — called from three places:
 *   1. Api/V1/PolicyCreateController::issuePolicy  (V2 issue path)
 *   2. Http/Livewire/Policy/Submit::issuePolicy    (legacy issue path)
 *   3. Console/Commands/CleanupTransactionsAfterCancel (nightly safety net)
 *
 * ── What gets removed ────────────────────────────────────────────────────
 * A live RENEW / ANNIVERSARY-RENEW action (any status) on a DOM/COM or
 * specialist policy that is BOTH:
 *   - on a policy whose operative ISSUED CANCEL — the LATEST-DATED one by
 *     (effective_from, id) — has NOT been cleared by an ISSUED REINSTATE /
 *     REISSUE that comes after it in that same chronology (a RENEW never
 *     clears a cancel), and
 *   - "after" that cancel — created after it (id >) OR covering a period that
 *     starts after it (effective_from > cancel.effective_from). Both tests are
 *     needed: the cron stacks renewals AFTER a cancel (higher id, caught by the
 *     first), while the anniversary quote is raised BEFORE the cancel for a
 *     future period (lower id, caught by the second).
 *
 * ── What is never touched ────────────────────────────────────────────────
 *   - REINSTATE / REISSUE — the only transactions that may legitimately follow
 *     a cancel; they are how a cancel is undone.
 *   - ENDORSE / EXTENSION-COVER / NEWBUSINESS / the CANCEL itself.
 *   - The renewal period the cancel falls INSIDE (effective_from == or < the
 *     cancel date) — that is the period being cancelled and refunded.
 *   - Any action whose invoice carries a Payment / Refund / Credit Note, or
 *     whose invoice is no longer Pending. Those are SKIPPED and reported so
 *     Finance reverses the invoice first — an allocated payment is never
 *     orphaned. Same guard as PolicyCreateController::deleteRenew.
 *
 * Everything is SOFT-delete (deleted_at only): the action, its full replicated
 * coverage tree and its invoice rows stay in the database for audit. Every
 * removal writes an activity-log entry and a Laravel log line.
 *
 * The invoice is discarded in BOTH halves — the policy_ledger rows (by
 * action_id) and the policy_subledger GL legs (by trans_ref = invoice_no, they
 * carry no action link). Dropping only the ledger half left the cancelled
 * period sitting on the sub-ledger, and a later re-issue then stacked a second
 * set of legs on top. See discardActionInvoices().
 *
 * This file is deployed VERBATIM to both apps (backend/ and cron/) — keep the
 * two copies identical. It carries no backend-only dependency: the specialist
 * table list falls back to an inlined copy when SpecialistCoverageRegistry is
 * absent (the cron app has no Services/SpecialistEndorse).
 */
class PostCancelCleanupService
{
    /** DOM (7) / COM (8). */
    public const DOMCOM_PRODUCTS = [7, 8];

    /**
     * Specialist products. Mirrors SPECIALIST_PRODUCTS in
     * RenewAnnualSpecialistPolicies / SpecialistMonthlyAutoRenew /
     * SpecialistQuaterlyAutoRenew — all four lists (backend + cron app) now
     * carry the same eight ids.
     */
    public const SPECIALIST_PRODUCTS = [16, 17, 18, 19, 20, 22, 23, 24];

    /** Only renewal transactions are auto-removed. */
    public const REMOVABLE_TYPES = ['RENEW', 'ANNIVERSARY-RENEW'];

    /** Transactions that CLEAR a cancel — never removed, and they stop the sweep. */
    public const CANCEL_CLEARING_TYPES = ['REINSTATE', 'REISSUE'];

    /** Invoice ledger rows discarded alongside a removed action. */
    private const INVOICE_TYPES = ['Invoice', 'Invoice VAT', 'Invoice Premium'];

    /** Products this rule applies to. */
    public static function products(): array
    {
        return array_merge(self::DOMCOM_PRODUCTS, self::SPECIALIST_PRODUCTS);
    }

    public static function appliesTo($productId): bool
    {
        return in_array((int) $productId, self::products(), true);
    }

    /**
     * Sweep one policy.
     *
     * @param  bool $apply  false = report only, write nothing (used by the
     *                      command's dry-run preview).
     * @return array{policy_id:int,cancel_action_id:?int,removed:array,skipped:array,invoices_discarded:int,subledger_discarded:int,reason:?string}
     */
    public function cleanupPolicy(int $policyId, bool $apply = true): array
    {
        $report = [
            'policy_id'          => $policyId,
            'cancel_action_id'   => null,
            'removed'            => [],
            'skipped'            => [],
            'invoices_discarded' => 0,
            'subledger_discarded' => 0,
            'reason'             => null,
        ];

        $policy = Policy::find($policyId);
        if (!$policy) {
            $report['reason'] = 'policy not found';
            return $report;
        }
        if (!self::appliesTo($policy->product_id)) {
            $report['reason'] = 'product out of scope';
            return $report;
        }

        $cancel = $this->latestUnclearedCancel($policyId);
        if (!$cancel) {
            $report['reason'] = 'no un-cleared ISSUED CANCEL';
            return $report;
        }
        $report['cancel_action_id'] = (int) $cancel->id;

        $targets = $this->findRenewalsAfter($policyId, $cancel);
        if ($targets->isEmpty()) {
            $report['reason'] = 'nothing after the cancel';
            return $report;
        }

        foreach ($targets as $action) {
            if ($this->hasPaymentActivity($policyId, (int) $action->id)) {
                $report['skipped'][] = [
                    'action_id'        => (int) $action->id,
                    'transaction_type' => $action->transaction_type,
                    'status'           => $action->status,
                    'effective_from'   => $action->effective_from,
                    'reason'           => 'invoice has payment/credit activity — reverse the invoice first',
                ];
                continue;
            }

            if (!$apply) {
                $report['removed'][] = $this->describe($action);
                continue;
            }

            try {
                $discarded = 0;
                $subDiscarded = 0;
                DB::transaction(function () use ($policyId, $action, &$discarded, &$subDiscarded) {
                    // Invoice discard + action cascade must be ATOMIC, or a
                    // cascade failure leaves the invoice gone but the action
                    // still in the dropdown (the half-done state deleteRenew
                    // was fixed for on specialist products).
                    ['ledger' => $discarded, 'subledger' => $subDiscarded] =
                        $this->discardActionInvoices($policyId, (int) $action->id);

                    $this->softDeleteActionCascade($action);
                });

                $report['invoices_discarded'] += $discarded;
                $report['subledger_discarded'] += $subDiscarded;
                $report['removed'][] = $this->describe($action);

                $this->logRemoval($policy, $cancel, $action, $discarded, $subDiscarded);
            } catch (\Throwable $e) {
                $report['skipped'][] = [
                    'action_id'        => (int) $action->id,
                    'transaction_type' => $action->transaction_type,
                    'status'           => $action->status,
                    'effective_from'   => $action->effective_from,
                    'reason'           => 'error: ' . $e->getMessage(),
                ];
                Log::error('PostCancelCleanup: failed to remove action', [
                    'policy_id' => $policyId,
                    'action_id' => $action->id,
                    'error'     => $e->getMessage(),
                    'trace'     => $e->getTraceAsString(),
                ]);
            }
        }

        return $report;
    }

    /**
     * Convenience wrapper for the issue paths: sweep the policy the CANCEL
     * that was just issued belongs to. No-op (and never throws) for any other
     * transaction type — safe to call unconditionally after an issue.
     */
    public function cleanupAfterIssuedCancel(PolicyAction $cancelAction): array
    {
        if ($cancelAction->transaction_type !== 'CANCEL' || $cancelAction->status !== 'ISSUED') {
            return [
                'policy_id'          => (int) $cancelAction->policy_id,
                'cancel_action_id'   => null,
                'removed'            => [],
                'skipped'            => [],
                'invoices_discarded'  => 0,
                'subledger_discarded' => 0,
                'reason'             => 'not an issued cancel',
            ];
        }

        return $this->cleanupPolicy((int) $cancelAction->policy_id, true);
    }

    /**
     * The operative ISSUED CANCEL on the policy, unless a REINSTATE / REISSUE
     * comes after it (which legitimately clears the cancel and re-opens cover —
     * renewals from then on are correct and must be left alone).
     *
     * Both "latest" and "after" are (effective_from, id) chronology, id only
     * breaking a same-date tie. Cover ends on a date, so the cancel that decides
     * it is the LATEST-DATED one, not the newest-created: a cancel back-dated to
     * March, booked after an April cancel was voided, ends cover in April. The
     * same holds for the revival test — a REINSTATE effective BEFORE the cancel
     * date reopened an earlier gap, it did not undo this cancel, so it must not
     * clear it. A RENEW never clears a cancel at all (CANCEL_CLEARING_TYPES).
     *
     * Chronology is inlined rather than using PolicyAction::applyForwardWindow
     * so this file stays dependency-free and byte-identical in both apps — the
     * cron app's PolicyAction has no such helper.
     *
     * Only an ISSUED cancel counts. A cancel still in QUOTE has not ended cover,
     * so the policy keeps renewing until UW issues it.
     */
    private function latestUnclearedCancel(int $policyId)
    {
        $cancel = PolicyAction::where('policy_id', $policyId)
            ->where('status', 'ISSUED')
            ->where('transaction_type', 'CANCEL')
            ->whereNull('deleted_at')
            ->orderBy('effective_from', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();

        if (!$cancel) return null;

        $cleared = PolicyAction::where('policy_id', $policyId)
            ->where('status', 'ISSUED')
            ->whereIn('transaction_type', self::CANCEL_CLEARING_TYPES)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($cancel) {
                // Chronologically after the cancel: later effective date, or the
                // same date with a higher id.
                if (empty($cancel->effective_from)) {
                    $q->where('id', '>', $cancel->id);
                    return;
                }
                $q->whereDate('effective_from', '>', $cancel->effective_from)
                  ->orWhere(function ($same) use ($cancel) {
                      $same->whereDate('effective_from', '=', $cancel->effective_from)
                           ->where('id', '>', $cancel->id);
                  });
            })
            ->exists();

        return $cleared ? null : $cancel;
    }

    /**
     * Live renewals that fall after the cancel — either created after it
     * (stacked by the cron) or covering a period that starts after it (raised
     * in advance, e.g. the ANNIVERSARY-RENEW quote). Status is deliberately
     * NOT filtered: a QUOTE / APPROVED renewal sitting past the cancel date is
     * just as wrong as an ISSUED one, and leaving it lets an operator issue it
     * by hand tomorrow.
     */
    private function findRenewalsAfter(int $policyId, PolicyAction $cancel)
    {
        return PolicyAction::where('policy_id', $policyId)
            ->whereIn('transaction_type', self::REMOVABLE_TYPES)
            ->whereNull('deleted_at')
            ->where('id', '!=', $cancel->id)
            ->where(function ($q) use ($cancel) {
                $q->where('id', '>', $cancel->id);
                if (!empty($cancel->effective_from)) {
                    $q->orWhere('effective_from', '>', $cancel->effective_from);
                }
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * Never discard an invoice that has had payment / credit activity, or whose
     * invoice row is no longer Pending. Same guard as
     * PolicyCreateController::deleteRenew — an allocated payment must go
     * through Reverse Invoice first, never be orphaned by an auto-cleanup.
     */
    private function hasPaymentActivity(int $policyId, int $actionId): bool
    {
        return Ledger::where('policy_id', $policyId)
                ->where('action_id', $actionId)
                ->whereIn('trans_type', ['Payment', 'Refund', 'Credit Note'])
                ->whereNull('deleted_at')
                ->exists()
            || Ledger::where('policy_id', $policyId)
                ->where('action_id', $actionId)
                ->where('trans_type', 'Invoice')
                ->whereNull('deleted_at')
                ->where('status', '!=', 'Pending')
                ->exists();
    }

    private function describe(PolicyAction $action): array
    {
        return [
            'action_id'        => (int) $action->id,
            'transaction_type' => $action->transaction_type,
            'status'           => $action->status,
            'effective_from'   => $action->effective_from,
            'effective_to'     => $action->effective_to,
            'premium'          => $action->premium,
        ];
    }

    /**
     * Discard the action's invoice — BOTH halves.
     *
     * An invoice is not one row: it is up to 3 policy_ledger rows (Invoice,
     * Invoice VAT, Invoice Premium, keyed by action_id) plus its policy_subledger
     * GL legs, which carry NO action_id link of their own — they are found by
     * trans_ref = invoice_no. Discarding only the ledger half left the GL legs
     * live, so the cancelled period stayed on the sub-ledger and a later re-issue
     * stacked a second set on top. Mirrors
     * PolicyCreateController::discardActionInvoices, including its capability
     * check: policy_subledger has no deleted_at in every environment, so fall
     * back to a hard delete there.
     *
     * Invoice numbers must be read BEFORE the ledger side is soft-deleted.
     *
     * @return array{ledger:int, subledger:int}
     */
    private function discardActionInvoices(int $policyId, int $actionId): array
    {
        $now = now();

        $invoiceNos = Ledger::where('policy_id', $policyId)
            ->where('action_id', $actionId)
            ->where('trans_type', 'Invoice')
            ->whereNull('deleted_at')
            ->whereNotNull('invoice_no')
            ->where('invoice_no', '!=', '')
            ->pluck('invoice_no')
            ->unique()
            ->values()
            ->all();

        $ledgerDiscarded = Ledger::where('policy_id', $policyId)
            ->where('action_id', $actionId)
            ->whereIn('trans_type', self::INVOICE_TYPES)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now]);

        $subDiscarded = 0;
        if (!empty($invoiceNos) && Schema::hasTable('policy_subledger')) {
            $sub = DB::table('policy_subledger')
                ->where('policy_id', $policyId)
                ->whereIn('trans_ref', $invoiceNos)
                // Rows written by the V2 paths carry action_id; legacy rows may
                // not, and a NULL-action row under the same invoice number is
                // still part of this invoice.
                ->where(function ($q) use ($actionId) {
                    $q->where('action_id', $actionId)->orWhereNull('action_id');
                });

            $subDiscarded = Schema::hasColumn('policy_subledger', 'deleted_at')
                ? (clone $sub)->whereNull('deleted_at')->update(['deleted_at' => $now])
                : $sub->delete();
        }

        return ['ledger' => $ledgerDiscarded, 'subledger' => $subDiscarded];
    }

    private function logRemoval(Policy $policy, PolicyAction $cancel, PolicyAction $action, int $discarded, int $subDiscarded = 0): void
    {
        $message = sprintf(
            'Auto-removed %s action id=%d (%s → %s) — policy cancelled by action id=%d effective %s. %d invoice row(s) and %d sub-ledger leg(s) discarded.',
            $action->transaction_type, $action->id,
            $action->effective_from, $action->effective_to,
            $cancel->id, $cancel->effective_from, $discarded, $subDiscarded
        );

        try {
            $log = activity('Post-Cancel Cleanup')->performedOn($policy);
            if (auth()->check()) {
                $log = $log->causedBy(auth()->user());
            }
            $log->log($message);
        } catch (\Throwable $e) {
            // Activity log must never break the cleanup itself.
            Log::warning('PostCancelCleanup: activity log failed: ' . $e->getMessage());
        }

        Log::info('PostCancelCleanup: ' . $message, [
            'policy_id' => $policy->id,
            'action_id' => $action->id,
            'cancel_id' => $cancel->id,
        ]);
    }

    /**
     * Soft-delete an action row and every action-versioned child row.
     * Behaviourally identical to PolicyCreateController::softDeleteActionCascade
     * and CleanupRenewAfterCancel::softDeleteActionCascade — soft-delete where
     * the table has deleted_at, hard-delete the few that don't.
     */
    public function softDeleteActionCascade(PolicyAction $action): void
    {
        $aid   = $action->id;
        $stamp = ['deleted_at' => now(), 'updated_at' => now()];
        $pcIds = DB::table('policy_coverages')->where('action_id', $aid)->pluck('id');

        $softOrHard = function (string $table, string $col, $val) use ($stamp) {
            if (!Schema::hasTable($table)) return;
            if (Schema::hasColumn($table, 'deleted_at')) {
                DB::table($table)->where($col, $val)->update($stamp);
            } else {
                DB::table($table)->where($col, $val)->delete();
            }
        };
        $softOrHardIn = function (string $table, string $col, $values) use ($stamp) {
            if (!Schema::hasTable($table) || count($values) === 0) return;
            if (Schema::hasColumn($table, 'deleted_at')) {
                DB::table($table)->whereIn($col, $values)->update($stamp);
            } else {
                DB::table($table)->whereIn($col, $values)->delete();
            }
        };

        if ($pcIds->isNotEmpty()) {
            $ids = $pcIds->all();
            $softOrHardIn('policy_coverage_detail',   'policy_coverage_id', $ids);
            $softOrHardIn('policy_extention_detail',  'policy_coverage_id', $ids);
            $softOrHardIn('policy_specified_items',   'policy_coverage_id', $ids);
            $softOrHardIn('motor',                    'policy_coverage_id', $ids);
            $softOrHardIn('motor_traders',            'policy_coverage_id', $ids);
            $softOrHardIn('motor_traders_internal',   'policy_coverage_id', $ids);
            $softOrHardIn('policy_coverage_notes',    'policy_coverage_id', $ids);
            $softOrHardIn('policy_coverage_entities', 'policy_coverage_id', $ids);
            $softOrHardIn('policy_coverages_data',    'policyCoverageID',   $ids);
            foreach (self::specialistTables() as $t) {
                $softOrHardIn($t, 'policy_coverage_id', $ids);
            }
        }

        $softOrHard('policy_coverages',   'action_id', $aid);
        $softOrHard('risk_address',       'action_id', $aid);
        $softOrHard('vehicle',            'action_id', $aid);
        $softOrHard('policy_beneficiary', 'action_id', $aid);
        $softOrHard('policy_cellphone',   'action_id', $aid);

        if (Schema::hasColumn('policy_actions', 'deleted_at')) {
            $action->update(['deleted_at' => now()]);
        } else {
            $action->delete();
        }
    }

    /**
     * The ten specialist coverage tables. Reads SpecialistCoverageRegistry when
     * it exists (backend app); falls back to the same canonical list inlined —
     * the cron app has no Services/SpecialistEndorse, exactly as
     * PolicyAction::replicateSpecialistCoveragesIfMissing does there.
     */
    private static function specialistTables(): array
    {
        $registry = \AlphaDirect\Services\SpecialistEndorse\SpecialistCoverageRegistry::class;
        if (class_exists($registry)) {
            return $registry::tableNames();
        }

        return [
            'car_coverages',
            'par_coverages',
            'ear_coverages',
            'travel_coverages',
            'medical_malpractice_coverages',
            'machinery_breakdown_coverages',
            'professional_indemnity_coverages',
            'marine_directors_officers_coverages',
            'marine_cargo_once_off_coverages',
            'marine_cargo_open_coverages',
        ];
    }
}
