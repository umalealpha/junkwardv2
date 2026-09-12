<?php

namespace AlphaDirect\Services\BackdatedEndorse;

use AlphaDirect\Models\Motor;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Models\PolicySpecifiedItem;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Runs the "Refresh Endorsement" rebuild: push a source action's coverage tree
 * forward into every downstream action (later RENEW / ANNIVERSARY-RENEW /
 * ENDORSE) by clearing each target's tree and re-replicating the FULL set from
 * the source, then regenerating RENEW premiums + invoices.
 *
 * Extracted out of PolicyCreateController::refreshEndorse so the exact same
 * logic can run inside a queued job (RefreshEndorseJob). For a large policy
 * (hundreds of coverages × thousands of child rows × several downstream
 * actions) the rebuild runs for minutes — far longer than a web-server /
 * load-balancer request timeout — so it MUST run off the HTTP request or the
 * gateway kills the connection and leaves a half-rebuilt tree.
 *
 * Resilience: each downstream action is rebuilt in its OWN try/catch, so one
 * action that fails to rebuild is logged and skipped instead of aborting the
 * whole refresh. Combined with the per-coverage / per-child guards in
 * PolicyAction::replicateRecordsIfMissing, a single bad row can never drop the
 * rest of the refresh.
 */
class EndorseRefreshRunner
{
    // Invoice rows are no longer discarded/regenerated here — they are corrected
    // in place by InvoiceAmountSync, which owns the trans_type list.

    /**
     * Rebuild every downstream action from $source.
     *
     * When $until is supplied the rebuild is BOUNDED — only actions effective
     * on/before $until are targeted, instead of every downstream action to the
     * end of the policy. This backs the "Refresh Endorsement Range" tool, where
     * an operator refreshes a specific span of batches (from → to) rather than
     * the whole tail. With $until = null the behaviour is unchanged (all
     * downstream actions), so existing callers are unaffected.
     *
     * MODES:
     *   - 'rebuild' (default): the destructive rebuild described above —
     *     hard-clear each target's tree and re-copy the source's in full.
     *   - 'fill_missing': NON-DESTRUCTIVE. Only ADD rows the target is missing
     *     from the source (missing coverages + their children, and vehicles
     *     missing under a coverage the target already has). Never hard-deletes,
     *     never reprices (pro-rata / billed premium stays sealed), never touches
     *     the ledger. Because it only inserts, the target's own additions AND
     *     every later endorse batch in the span are left intact — so a chain of
     *     dated endorses is safe to run this over. Backs the "Fill missing (keep
     *     edits)" button; existing rebuild callers are unaffected (default arg).
     *   - 'fill_missing_reprice': identical additive engine to 'fill_missing',
     *     except that an ALREADY-ISSUED endorse target is REPRICED as well — its
     *     recomputed pro-rata is kept and its existing invoice is moved to it in
     *     place (InvoiceAmountSync: nothing discarded, invoice number preserved,
     *     GL legs rescaled). Use it when the appended cover must actually be
     *     charged on a batch that is already out; the alternative operators were
     *     reaching for was to UN-ISSUE the batch, which zeroes the policy status
     *     and re-stamps the policy dates on re-issue. Everything else — the rows
     *     it adds, the edits it protects, RENEW handling — is unchanged.
     *   - 'coverage_forward': COVERAGE-SELECTIVE add. Copy ONLY the coverages in
     *     $coverageIds (+ their full child tree, motors, vehicles, specialist
     *     rows and notes) from the source into each target that lacks them.
     *     Idempotent; leaves every other coverage untouched.
     *   - 'coverage_drop': COVERAGE-SELECTIVE remove. Soft-delete the coverages
     *     in $coverageIds (+ their child tree) from each target so the recompute
     *     drops them from the premium. Audit trail preserved (no hard delete).
     *   - 'fill_missing_backward': the only mode where data moves BACKWARD in
     *     time. $source is the LATER known-good action (typically an ISSUED
     *     RENEW) and the single target is the EARLIER action named by $until
     *     (typically a back-dated ENDORSE that opened with a thin tree). Same
     *     additive engine as fill_missing, plus:
     *       · protectTargetEdits — a row the TARGET action itself edited is left
     *         exactly as the operator left it, and appended rows carry no
     *         pro-rata from the source;
     *       · $forceSeal — the RENEW/ANNIVERSARY repricing branch is suppressed
     *         for EVERY transaction type, so no premium, invoice or ledger row
     *         moves. An earlier batch must never be re-billed for cover that
     *         started after it.
     *     Anything CANCELLED on the target stays cancelled (soft-deleted
     *     coverages match via withTrashed, child rows via
     *     childRowRemovedInTarget, vehicles via fillMissingMotorVehicles).
     *     Exactly ONE action is written — a span backwards is not meaningful.
     *
     * For BOTH coverage_* modes the premium/invoice finalisation is identical to
     * fill_missing: RENEW / ANNIVERSARY-RENEW targets are repriced to the new
     * tree total and their RENEW invoice regenerated; every other target keeps
     * its sealed pro-rata premium and its ledger is left untouched.
     *
     * $coverageIds is only read by the two coverage_* modes; the other modes
     * ignore it, so existing callers (which omit it) are unaffected.
     *
     * When $onlyTarget is true the span is collapsed to a SINGLE action: only
     * $until itself is refreshed (the intermediate batches between the source
     * and $until are left untouched). This backs the "Refresh To Only" button,
     * for when a single downstream action needs the source pushed into it
     * without rebuilding the whole from → to span. Requires $until; ignored
     * when $until is null. Existing range callers pass false and are unaffected.
     *
     * @param  callable|null      $onProgress  fn(int $done, int $total, PolicyAction $later)
     * @param  PolicyAction|null  $until       inclusive upper bound (by effective_from)
     * @param  string             $mode        'rebuild' | 'fill_missing' | 'fill_missing_reprice' | 'coverage_forward' | 'coverage_drop' | 'fill_missing_backward'
     * @param  bool               $onlyTarget  refresh ONLY $until, not the span
     * @param  array<int>         $coverageIds coverage_ids acted on by coverage_* modes
     * @return array{refreshed:int, renew_invoices:int, failed:int, total:int}
     */
    /**
     * The TARGET actions a fill/rebuild would touch for a given source and
     * optional upper bound: every non-deleted action whose effective_from is
     * on/after the source's forward-propagation anchor and (when $until is
     * given) on/before $until's effective_from, EXCLUDING the source itself.
     *
     * Read-only — backs the confirm-dialog "N action(s) will refresh" count.
     * MUST mirror the $downstream selection inside run() exactly (kept as a
     * separate copy there so the execution path is untouched); if that window
     * ever changes, change it here too or the previewed count will drift.
     *
     * @return \Illuminate\Support\Collection<int, PolicyAction>
     */
    public static function targetsFor(int $policyId, PolicyAction $source, ?PolicyAction $until = null)
    {
        return PolicyAction::query()
            ->where('policy_id', $policyId)
            // Anchor on the source's EFFECTIVE date — never transaction_date
            // (see PolicyAction::forwardPropagationAnchor). Same-date actions
            // are separated by id (applyForwardWindow).
            ->tap(fn ($q) => PolicyAction::applyForwardWindow($q, $source))
            ->when($until, fn ($q) => PolicyAction::applyUpperBound($q, $until))
            ->where('id', '!=', $source->id)
            ->whereNull('deleted_at')
            ->tap(fn ($q) => PolicyAction::applyChronoOrder($q))
            ->get();
    }

    /**
     * The single TARGET of a BACKWARD fill: the EARLIER action named by
     * $until, which the LATER $source is pulled back into.
     *
     * Every other mode walks FORWARD (applyForwardWindow), so it would return
     * no targets at all for this direction. The chronology guard is
     * applyUpperBound($source) — the same (effective_from, id) comparison the
     * forward window uses, so a same-date lower-id action is a valid target
     * while the source itself is excluded by the id filter.
     *
     * Returns an empty collection when $until is missing, is the source, is
     * deleted, belongs to another policy, or is NOT earlier than the source —
     * a backward fill must never run forward by accident. The controller guards
     * the direction too; this is the guard for the CLI / job path.
     *
     * @return \Illuminate\Support\Collection<int, PolicyAction>
     */
    public static function backwardTargetFor(int $policyId, PolicyAction $source, ?PolicyAction $until = null)
    {
        if (!$until || (int) $until->id === (int) $source->id) {
            return collect();
        }

        return PolicyAction::query()
            ->where('policy_id', $policyId)
            ->where('id', $until->id)
            ->where('id', '!=', $source->id)
            ->whereNull('deleted_at')
            ->tap(fn ($q) => PolicyAction::applyUpperBound($q, $source))
            ->get();
    }

    public function run(int $policyId, PolicyAction $source, ?callable $onProgress = null, ?PolicyAction $until = null, string $mode = 'rebuild', bool $onlyTarget = false, array $coverageIds = []): array
    {
        // Normalise the coverage filter once so the per-target methods can trust it.
        $coverageIds = array_values(array_unique(array_filter(array_map('intval', $coverageIds))));
        // Long-running loop — keep memory flat by not buffering the query log.
        DB::connection()->disableQueryLog();

        // TARGETS selected BY DATE ("as per date"): every batch effective
        // on/after the source — the later RENEW batches and the
        // ANNIVERSARY-RENEW quote — regardless of transaction_type. When an
        // upper bound is given, stop at actions effective on/before it so only
        // the requested from → to span is rebuilt.
        //
        // SAME-DATE actions are ordered by policy_actions.id (operator rule,
        // 2026-08-06): the plain `>=` used before both reached BACKWARD into
        // same-date lower-id actions (a refresh from the 3rd of three 01/01
        // endorsements overwrote the 1st and the anniversary) and left the walk
        // order between them undefined. applyForwardWindow keeps only the
        // higher ids on the anchor date; applyChronoOrder walks them oldest →
        // newest so the chain is 1 → 2 → 3.
        //
        // BACKWARD fill is the exception: its target sits BEFORE the source, so
        // the forward window would exclude it entirely. Select the named To
        // action directly instead — exactly one target, since a span backwards
        // is not a meaningful operation.
        $downstream = $mode === 'fill_missing_backward'
            ? self::backwardTargetFor($policyId, $source, $until)
            : PolicyAction::query()
            ->where('policy_id', $policyId)
            // Anchor on the source's EFFECTIVE date — never transaction_date
            // (see PolicyAction::forwardPropagationAnchor).
            ->tap(fn ($q) => PolicyAction::applyForwardWindow($q, $source))
            ->when($until, fn ($q) => PolicyAction::applyUpperBound($q, $until))
            // Single-target mode: collapse the span to the To action alone, so
            // only that one batch is refreshed and the intermediate batches are
            // left untouched.
            ->when($onlyTarget && $until, fn ($q) => $q->where('id', $until->id))
            ->where('id', '!=', $source->id)
            ->whereNull('deleted_at')
            ->tap(fn ($q) => PolicyAction::applyChronoOrder($q))
            ->get();

        $total          = $downstream->count();
        $refreshed      = 0;
        $renewInvoices  = 0;
        $failed         = 0;
        $done           = 0;

        foreach ($downstream as $later) {
            try {
                // ── Per-vehicle cancels, FIRST and for every FORWARD mode ──
                // A vehicle cancelled on the source must be off cover in every
                // later batch, whatever that batch's transaction_type is. No
                // mode below can do this on its own: additive modes only ADD
                // rows, and rebuild only omits the vehicle from the rows it
                // re-copies — neither reduces a batch that already carries a
                // LIVE copy of the vehicle (see
                // PolicyAction::propagateCancelledMotorRows). Running it here,
                // before the mode's row work and before any repricing, means
                // the premium + invoice finalisation further down sees the
                // reduced tree and moves the money with it.
                //
                // Order-safe: rebuild wipes and re-copies the tree afterwards,
                // and replication refuses to carry a source-cancelled row, so
                // the vehicle does not come back. Idempotent, so a batch that
                // never had the vehicle is a no-op.
                //
                // NEVER in BACKWARD fill. That mode's target is EARLIER than
                // the source (backwardTargetFor applies an upper bound against
                // it), so $later is a misnomer there and a cancel dated after
                // the target does not apply to it: the vehicle was genuinely on
                // risk for the whole of that earlier period and the customer
                // paid for it. Propagating would soft-delete the motor row plus
                // its premium-bearing policy_specified_items and its notes off
                // an ISSUED batch, leaving the schedule short against an
                // invoice that $forceSeal deliberately keeps — and breaking the
                // mode's append-only, no-money-moves contract. This is the
                // failure propagateCancelledMotorRows' own docblock records as
                // the reason it was removed once before.
                if ($mode !== 'fill_missing_backward') {
                    PolicyAction::propagateCancelledMotorRows((int) $source->id, (int) $later->id);
                }

                if ($mode === 'coverage_forward') {
                    // COVERAGE-SELECTIVE add: copy only $coverageIds into the
                    // target (idempotent), then reprice RENEW/ANNIVERSARY only.
                    $this->forwardCoveragesIntoTarget($policyId, (int) $later->id, $source, $coverageIds);
                    $refreshed++;
                } elseif ($mode === 'coverage_drop') {
                    // COVERAGE-SELECTIVE remove: soft-delete $coverageIds from the
                    // target, then reprice RENEW/ANNIVERSARY only (invoice down to
                    // the new lower total — no separate credit note).
                    $this->dropCoveragesFromTarget($policyId, (int) $later->id, $coverageIds);
                    $refreshed++;
                } elseif ($mode === 'fill_missing_backward') {
                    // BACKWARD: pull the later source back into this earlier
                    // target. Same additive engine as fill_missing, with the
                    // premium/ledger SEALED whatever the target's type.
                    $this->fillMissingIntoTarget($policyId, (int) $later->id, $source, true);
                    $refreshed++;
                } elseif ($mode === 'fill_missing' || $mode === 'fill_missing_reprice') {
                    // NON-DESTRUCTIVE: only ADD rows the target is missing from
                    // the source — never hard-delete, keeps the target's own edits
                    // AND every later endorse batch in the span intact.
                    // An un-issued ENDORSE target keeps its recomputed pro-rata;
                    // RENEW / ANNIVERSARY-RENEW targets are repriced + re-invoiced
                    // (see finaliseTargetPremium). 'fill_missing_reprice' also
                    // reprices an ALREADY-ISSUED endorse target and moves its
                    // invoice with it, so the batch never has to be un-issued.
                    $this->fillMissingIntoTarget(
                        $policyId,
                        (int) $later->id,
                        $source,
                        false,
                        $mode === 'fill_missing_reprice'
                    );
                    $refreshed++;
                } else {
                    // Rebuild-to-match-source: clear the target's replicated
                    // coverage tree, then re-copy the FULL set from the source.
                    // Additive replication alone cannot REDUCE an over-stated batch
                    // RENEW because it never removes the stale / duplicate rows
                    // inflating it — only a clean rebuild makes the target identical
                    // to the operator-selected source. NOTE: this discards any manual
                    // edits made directly on the target action (operator-confirmed).
                    // Captured BEFORE the rebuild so an ISSUED non-RENEW target can be
                    // resealed to its true billed figure (see resyncRebuiltTargetTotals).
                    $sealedPremium = (float) ($later->premium ?? 0);
                    $this->hardDeleteActionCoverageTree((int) $later->id);
                    PolicyAction::newPolicyActionReplace($later, $source->id);
                    $refreshed++;

                    if ($later->transaction_type !== 'RENEW') {
                        // The rebuild replaced every VALUE in the target's tree, but
                        // policy_actions.premium / annual_premium were left at their
                        // pre-rebuild figures — and those two columns ARE the Rate
                        // banner / V2 Quote total. So a rebuilt ENDORSE quote showed
                        // the source's coverages against the OLD totals: "refresh
                        // ran but the values still mismatch". Re-derive them from
                        // the rebuilt tree (fill_missing already did this via
                        // finaliseTargetPremium; rebuild never did).
                        $this->resyncRebuiltTargetTotals($policyId, (int) $later->id, $later, $sealedPremium);
                    }

                    if ($later->transaction_type === 'RENEW') {
                        // Pure replica: carry the operator-selected source's ISSUED
                        // premium VERBATIM (a full-period source may be a manual UW
                        // figure the tree wouldn't re-derive). Only an ENDORSE source
                        // recomputes from the rebuilt tree.
                        PolicyAction::setRenewPremiumFromSource($later->id, $source);

                        // Then move the invoice to that premium IN PLACE. The old
                        // soft-delete + regenerate discarded billing rows and issued
                        // a new invoice number, and left the policy_subledger legs
                        // behind to stack duplicates on every run.
                        if ($this->syncTargetInvoice($policyId, (int) $later->id, $later)) {
                            $renewInvoices++;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // One downstream action failing must not drop the rest.
                $failed++;
                Log::error("EndorseRefreshRunner: rebuild failed for action {$later->id}", [
                    'policy_id' => $policyId,
                    'source_id' => $source->id,
                    'error'     => $e->getMessage(),
                ]);
            }

            $done++;
            if ($onProgress) {
                $onProgress($done, $total, $later);
            }
        }

        return [
            'refreshed'      => $refreshed,
            'renew_invoices' => $renewInvoices,
            'failed'         => $failed,
            'total'          => $total,
        ];
    }

    /**
     * Hard-delete an action's replicated coverage tree (raw DELETEs that bypass
     * SoftDeletes) so a clean rebuild can re-copy the full set from source.
     * Moved verbatim from PolicyCreateController::hardDeleteActionCoverageTree.
     */
    public function hardDeleteActionCoverageTree(int $actionId): void
    {
        // These are RAW deletes: the rows do not come back. The rebuild re-copies
        // the tree from source immediately afterwards, but if that copy fails or
        // half-completes (one bad child row used to abort the whole tree) the
        // action is left stripped with nothing to restore from — and the rebuild
        // runs unattended in a detached worker against ISSUED RENEW /
        // ANNIVERSARY-RENEW targets too. So snapshot every row first.
        $this->backupActionCoverageTree($actionId);

        DB::transaction(function () use ($actionId) {
            $pcIds = DB::table('policy_coverages')->where('action_id', $actionId)->pluck('id')->all();

            $purgeIn = function (string $table, string $col, array $values) {
                if (!Schema::hasTable($table) || count($values) === 0) return;
                DB::table($table)->whereIn($col, $values)->delete(); // raw DELETE — bypasses SoftDeletes
            };
            $purge = function (string $table, string $col, $val) {
                if (!Schema::hasTable($table)) return;
                DB::table($table)->where($col, $val)->delete();
            };

            if (!empty($pcIds)) {
                $purgeIn('policy_coverage_detail',   'policy_coverage_id', $pcIds);
                $purgeIn('policy_extention_detail',  'policy_coverage_id', $pcIds);
                $purgeIn('policy_specified_items',   'policy_coverage_id', $pcIds);
                $purgeIn('motor',                    'policy_coverage_id', $pcIds);
                $purgeIn('motor_traders',            'policy_coverage_id', $pcIds);
                $purgeIn('motor_traders_internal',   'policy_coverage_id', $pcIds);
                $purgeIn('policy_coverage_notes',    'policy_coverage_id', $pcIds);
                $purgeIn('policy_coverage_entities', 'policy_coverage_id', $pcIds);
                $purgeIn('policy_coverages_data',    'policyCoverageID',   $pcIds);
                foreach (\AlphaDirect\Services\SpecialistEndorse\SpecialistCoverageRegistry::tableNames() as $t) {
                    $purgeIn($t, 'policy_coverage_id', $pcIds);
                }
            }

            // Per-action top-level rows the replicator also rewrites. Purged
            // (not kept) so vehicle replication — which appends every source
            // plate without de-duping — can't leave duplicate vehicles behind.
            $purge('policy_coverages',   'action_id', $actionId);
            $purge('risk_address',       'action_id', $actionId);
            $purge('vehicle',            'action_id', $actionId);
            $purge('policy_beneficiary', 'action_id', $actionId);
            $purge('policy_cellphone',   'action_id', $actionId);
        });
    }

    /**
     * Snapshot an action's whole coverage tree to disk before a rebuild purges
     * it, so a failed or wrong rebuild is recoverable row-for-row.
     *
     * Written to the `local` disk (storage/app, on EFS in live) — never the
     * default disk, which points at a dead legacy bucket. One JSON file per
     * rebuild, named by action id + timestamp so repeat rebuilds do not
     * overwrite each other's snapshots.
     *
     * Best-effort: a backup failure must never block the refresh the operator
     * asked for, so it logs and returns rather than throwing.
     */
    private function backupActionCoverageTree(int $actionId): void
    {
        try {
            $pcIds = DB::table('policy_coverages')->where('action_id', $actionId)->pluck('id')->all();

            $byCoverage = [
                'policy_coverage_detail'   => 'policy_coverage_id',
                'policy_extention_detail'  => 'policy_coverage_id',
                'policy_specified_items'   => 'policy_coverage_id',
                'motor'                    => 'policy_coverage_id',
                'motor_traders'            => 'policy_coverage_id',
                'motor_traders_internal'   => 'policy_coverage_id',
                'policy_coverage_notes'    => 'policy_coverage_id',
                'policy_coverage_entities' => 'policy_coverage_id',
                'policy_coverages_data'    => 'policyCoverageID',
            ];
            foreach (\AlphaDirect\Services\SpecialistEndorse\SpecialistCoverageRegistry::tableNames() as $t) {
                $byCoverage[$t] = 'policy_coverage_id';
            }

            $byAction = [
                'policy_coverages'   => 'action_id',
                'risk_address'       => 'action_id',
                'vehicle'            => 'action_id',
                'policy_beneficiary' => 'action_id',
                'policy_cellphone'   => 'action_id',
            ];

            $snapshot = ['action_id' => $actionId, 'taken_at' => now()->toDateTimeString(), 'tables' => []];
            $rowCount = 0;

            foreach ($byAction as $table => $col) {
                if (!Schema::hasTable($table)) continue;
                $rows = DB::table($table)->where($col, $actionId)->get()->toArray();
                if ($rows) {
                    $snapshot['tables'][$table] = $rows;
                    $rowCount += count($rows);
                }
            }
            if (!empty($pcIds)) {
                foreach ($byCoverage as $table => $col) {
                    if (!Schema::hasTable($table)) continue;
                    $rows = DB::table($table)->whereIn($col, $pcIds)->get()->toArray();
                    if ($rows) {
                        $snapshot['tables'][$table] = $rows;
                        $rowCount += count($rows);
                    }
                }
            }

            $path = 'refresh-rebuild-backup/action-' . $actionId . '-' . now()->format('Ymd-His') . '.json';
            Storage::disk('local')->put($path, json_encode($snapshot, JSON_PRETTY_PRINT));

            Log::info('EndorseRefreshRunner: coverage tree backed up before rebuild', [
                'action_id' => $actionId, 'rows' => $rowCount, 'path' => 'storage/app/' . $path,
            ]);
        } catch (\Throwable $e) {
            Log::error('EndorseRefreshRunner: pre-rebuild backup FAILED — rebuild continuing', [
                'action_id' => $actionId, 'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * NON-DESTRUCTIVE "fill missing" for one target action.
     *
     * Adds every row the target is MISSING from the source without ever
     * deleting: whole coverages (+ their children), risk addresses, vehicles,
     * specialist coverage rows and motor notes come across via the additive
     * replicateRecordsIfMissing path (newPolicyActionReplace). A separate pass
     * fills vehicles missing under a coverage the target ALREADY has — the one
     * gap in the coverage-level "if missing" match (see fillMissingMotorVehicles).
     *
     * Premium handling depends on the target's transaction type AND on whether
     * it has actually been billed:
     *   - ENDORSE still at QUOTE / APPROVED: nothing is invoiced, so the
     *     recomputed pro-rata is KEPT. This is what removes the un-issue
     *     workaround — the appended cover moves the Rate banner / V2 Quote Total
     *     on the downstream quote without the batch being taken back to zero.
     *   - ENDORSE already ISSUED: premium is SEALED by default. We recompute
     *     annual_premium so the added coverages show in the V2 Quote / policy doc
     *     totals, then restore policy_actions.premium to its pre-fill pro-rata
     *     value so the billed figure never moves. The ledger is untouched.
     *     $repriceIssued lifts that seal — see below.
     *   - RENEW / ANNIVERSARY-RENEW: the added coverages are PRICED — the
     *     recomputed full-period premium is KEPT (not resealed) and the RENEW
     *     invoice value is refreshed to it (existing invoice rows soft-deleted and
     *     regenerated from the new premium). No other ledger rows are touched.
     *
     * $repriceIssued (mode 'fill_missing_reprice') prices the appended cover on
     * an ALREADY-ISSUED endorse target too: the recomputed pro-rata is kept and
     * the batch's existing invoice is moved to it IN PLACE (InvoiceAmountSync —
     * nothing discarded, invoice number preserved, GL legs rescaled with it).
     * Opt-in because it moves billed money; the operator's alternative was to
     * un-issue the batch, which is destructive (it zeroes policy status and
     * re-stamps the policy dates on re-issue). Ignored when $forceSeal is set.
     *
     * $forceSeal (BACKWARD fill) keeps the target's billed premium and ledger
     * sealed WHATEVER its transaction type. Pulling a later action's cover back
     * into an earlier batch must never re-bill that batch — it was never on risk
     * for cover that started after it — so the RENEW / ANNIVERSARY repricing
     * branch is suppressed. Forward callers pass false and are unaffected.
     */
    private function fillMissingIntoTarget(int $policyId, int $targetId, PolicyAction $source, bool $forceSeal = false, bool $repriceIssued = false): void
    {
        $target = PolicyAction::find($targetId);
        if (!$target) {
            return;
        }

        // For a mid-term ENDORSE target, fill-missing only ADDS coverage data and
        // must never reprice the already-issued pro-rata batch — its premium stays
        // sealed. For a full-period RENEW / ANNIVERSARY-RENEW target the newly-
        // added coverages MUST be priced: keep the recomputed premium and refresh
        // the invoice value to it (see finaliseTargetPremium).
        $sealedPremium = (float) ($target->premium ?? 0);

        // Additive tree fill: inserts only rows absent from the target, so the
        // target's own additions (and later endorse batches) are left as-is.
        //
        // protectTargetEdits = TRUE — this is what makes the mode live up to its
        // "keep edits" name. replicateRecordsIfMissing only matches child rows by
        // BUSINESS KEY (coverage_id / registration_no / …) and then overwrites
        // their value columns from the source, so without this flag a fill would
        // silently revert the target batch's own operator edits — the opposite of
        // what this mode promises. With it on, a row the target itself edited
        // (endors_flag = 1 AND previousActionIdCov = target action) is left
        // exactly as the operator left it, and appended rows carry no pro-rata
        // from the source.
        PolicyAction::newPolicyActionReplace($target, (int) $source->id, true);

        // Coverage-level "if missing" matching treats a coverage the target
        // already has as "found" and never descends into its children — so a
        // vehicle the source added under an existing motor coverage is not
        // copied by the step above. Fill those, then carry per-vehicle notes.
        $this->fillMissingMotorVehicles((int) $source->id, $targetId);
        PolicyAction::replicateMotorNotesIfMissing((int) $source->id, $targetId, true);

        $this->finaliseTargetPremium($policyId, $targetId, $target, $sealedPremium, $forceSeal, $repriceIssued);
    }

    /**
     * Shared premium/invoice finalisation for a target after a structural change
     * (fill-missing, coverage-forward or coverage-drop). Recompute the tree
     * totals, then:
     *   - RENEW / ANNIVERSARY-RENEW: KEEP the recomputed full-period premium and
     *     refresh the RENEW invoice value to it (existing invoice rows soft-
     *     deleted and regenerated). No other ledger rows are touched, and DROP
     *     needs no credit note — the invoice simply falls to the new lower total.
     *   - ENDORSE target NOT yet issued (QUOTE / APPROVED): nothing is billed,
     *     so the recomputed pro-rata premium STANDS. No ledger row is written.
     *   - ENDORSE target already ISSUED: premium is SEALED — annual_premium is
     *     recomputed so added/removed coverages show in the V2 Quote / policy
     *     totals, then policy_actions.premium is restored to its pre-change
     *     pro-rata value so the billed figure never moves. The ledger is left
     *     untouched. $repriceIssued lifts this seal and moves the invoice with
     *     the premium instead (mode 'fill_missing_reprice').
     *
     * $sealedPremium MUST be captured from $target->premium BEFORE the structural
     * change so the restore below is the true pre-change billed figure.
     */
    private function finaliseTargetPremium(int $policyId, int $targetId, PolicyAction $target, float $sealedPremium, bool $forceSeal = false, bool $repriceIssued = false): void
    {
        // $forceSeal (BACKWARD fill) suppresses the repricing branch for EVERY
        // transaction type — see fillMissingIntoTarget's docblock.
        //
        // $repriceIssued ('fill_missing_reprice') extends the repricing branch
        // to an ALREADY-ISSUED endorse target: its recomputed pro-rata is kept
        // and its invoice moved in place, instead of the premium being resealed.
        // Opt-in, because it moves billed money — see fillMissingIntoTarget.
        //
        // ENDORSE is part of that test, not just ISSUED. recomputeActionTotals
        // only converts $annual into a pro-rata figure for an ENDORSE; on any
        // other type $billable stays $annual. On an ISSUED CANCEL that means the
        // negative refund becomes a positive full-year premium, and because a
        // cancel carries a Credit Note rather than an 'Invoice' row the sync
        // below reports 'no_invoice' and raises a BRAND-NEW full-annual invoice
        // against a cancelled policy. RENEW / ANNIVERSARY-RENEW keep their own
        // branch above, where premium == annual really is the billed figure.
        $repriceTarget = !$forceSeal
            && (in_array($target->transaction_type, ['RENEW', 'ANNIVERSARY-RENEW'], true)
                || ($repriceIssued
                    && $target->status === 'ISSUED'
                    && $target->transaction_type === 'ENDORSE'));

        // Recompute totals. recomputeActionTotals sets BOTH annual_premium and
        // premium to the current (added/removed-coverage-inclusive) tree sum;
        // for a non-ENDORSE target premium == annual, the correct full-period
        // billed figure.
        $this->recomputeActionTotals($policyId, $targetId);

        if ($repriceTarget) {
            // RENEW / ANNIVERSARY-RENEW: KEEP the recomputed premium and move the
            // invoice to it IN PLACE.
            //
            // This used to soft-delete the invoice rows and regenerate them. That
            // broke two operator rules at once: it DISCARDED billing rows, and the
            // regenerated invoice carried a NEW invoice number — while the
            // policy_subledger GL legs were never discarded with them, so each
            // regeneration stacked another set of duplicate legs on the action.
            // InvoiceAmountSync rescales the existing rows and their legs in place
            // instead: nothing is deleted, the invoice number is preserved, and it
            // is the same writer the endorse-issue path uses so the two can no
            // longer disagree.
            $this->syncTargetInvoice($policyId, $targetId, $target);

            DB::table('policy_actions')->where('id', $targetId)->update(['updated_at' => now()]);
            return;
        }

        // NOT YET BILLED (a QUOTE / APPROVED endorse target): there is no
        // invoiced figure to protect, so KEEP the recomputed pro-rata premium.
        //
        // Resealing here is what made "Fill missing" look like it only half
        // worked: the appended coverages showed up on the downstream batch but
        // policy_actions.premium — which IS the Rate banner / V2 Quote Total —
        // stayed at its pre-fill figure, so the only way to move the number was
        // to UN-ISSUE the batch and re-rate it by hand (which zeroes the policy
        // and re-stamps its dates). The rebuild path already keeps the
        // recompute for an unissued target (resyncRebuiltTargetTotals); this is
        // the same rule for the additive modes.
        //
        // $forceSeal (BACKWARD fill) still seals whatever the status — an
        // earlier batch must never be re-rated for cover that started after it.
        //
        // Scoped to a QUOTE ENDORSE, which is the same rule the rebuild path
        // states at resyncRebuiltTargetTotals, and for the same two reasons:
        //
        //   status === 'QUOTE', not !== 'ISSUED' — a LAPSED or NTU target WAS
        //   issued and billed once, so its premium has a ledger figure behind
        //   it and must be resealed, not overwritten from the tree.
        //
        //   transaction_type === 'ENDORSE' — recomputeActionTotals only
        //   converts $annual to a pro-rata figure for an ENDORSE; every other
        //   type keeps $billable = $annual. calculatePremium stores premium as
        //   pro-rata for ENDORSE *and* CANCEL, and a cancel's pro-rata is
        //   NEGATIVE, so keeping the recompute on a CANCEL quote turns a
        //   -2,412.64 refund into a +28,951.68 charge on the Rate banner and
        //   the V2 Quote Total, and bills a customer who is owed money. The
        //   target query is not filtered by transaction_type, so a CANCEL,
        //   LAPSED or NTU action inside the refresh span reaches this method.
        //
        // Anything that is not a QUOTE ENDORSE falls through to the seal below,
        // i.e. back to the pre-additive-mode behaviour.
        if (!$forceSeal && $target->status === 'QUOTE' && $target->transaction_type === 'ENDORSE') {
            DB::table('policy_actions')->where('id', $targetId)->update(['updated_at' => now()]);
            return;
        }

        // ISSUED ENDORSE (or any other billed target) — restore the sealed
        // pro-rata / billed premium; the ledger is left untouched. Use
        // 'fill_missing_reprice' when the billed figure must move with the
        // appended cover.
        DB::table('policy_actions')->where('id', $targetId)->update([
            'premium'    => $sealedPremium,
            'updated_at' => now(),
        ]);
    }

    /**
     * Re-derive a REBUILT non-RENEW target's action-level totals from its new
     * tree, so the Rate banner / V2 Quote total agrees with the coverages the
     * rebuild just copied in.
     *
     * Without this the rebuild was structurally correct but numerically stale:
     * only the RENEW branch wrote premium (setRenewPremiumFromSource), so an
     * ENDORSE / ANNIVERSARY-RENEW target kept the premium + annual_premium it
     * had BEFORE the rebuild while every coverage under it came from the source.
     *
     * QUOTE target: nothing is billed yet, so the recompute STANDS — for an
     * ENDORSE quote recomputeActionTotals also re-stamps the per-line pro-rata
     * off the new tree, which is exactly what the operator wants to see.
     *
     * ISSUED target: the premium is already invoiced, so only annual_premium may
     * move — premium is restored to its pre-rebuild value. No ledger row is
     * touched here (an ISSUED RENEW's invoice is handled by the caller's RENEW
     * branch, which this method deliberately never runs for).
     */
    private function resyncRebuiltTargetTotals(int $policyId, int $targetId, PolicyAction $target, float $sealedPremium): void
    {
        $this->recomputeActionTotals($policyId, $targetId);

        if ($target->status === 'QUOTE') {
            return; // nothing billed — keep the freshly derived figures
        }

        DB::table('policy_actions')->where('id', $targetId)->update([
            'premium'    => $sealedPremium,
            'updated_at' => now(),
        ]);
    }

    /**
     * Move a refreshed RENEW / ANNIVERSARY-RENEW target's invoice to its current
     * premium, IN PLACE, via the shared InvoiceAmountSync writer.
     *
     * Discards nothing and keeps the invoice number — see InvoiceAmountSync for
     * the column map and the single-ratio rule. Where the target has NO invoice
     * yet, one is raised through the normal issue-time writer; that is a creation,
     * not a re-creation, and generateInvoiceDomComIssued only acts on ISSUED
     * actions, so an anniversary QUOTE is correctly left un-invoiced.
     *
     * @return bool whether this target's billing was touched
     */
    private function syncTargetInvoice(int $policyId, int $targetId, PolicyAction $target): bool
    {
        $premium = (float) (DB::table('policy_actions')->where('id', $targetId)->value('premium') ?? 0);

        try {
            $sync = \AlphaDirect\Services\Ledger\InvoiceAmountSync::syncToPremium($policyId, $targetId, $premium);
        } catch (\Throwable $e) {
            Log::error("EndorseRefreshRunner: invoice sync failed for action {$targetId}: " . $e->getMessage());
            return false;
        }

        if ($sync['ok']) {
            return true;
        }

        if ($sync['reason'] === 'no_invoice') {
            // A refund is a credit note, never an invoice. Raising one here for a
            // nil or negative premium would bill a customer who is owed money —
            // and 'no_invoice' is exactly what a refund action reports, because
            // its billing sits in a Credit Note rather than an 'Invoice' row.
            // Belt-and-braces alongside the transaction_type test in
            // finaliseTargetPremium: no caller of this method should be able to
            // create a positive invoice out of a non-positive premium.
            if ($premium <= 0) {
                Log::warning(
                    "EndorseRefreshRunner: action {$targetId} has no invoice and a premium of "
                    . "{$premium} — refusing to raise one; a refund needs a credit note."
                );
                return false;
            }

            // Nothing billed for this action yet — raise the first invoice.
            try {
                \AlphaDirect\Helper::generateInvoiceDomComIssued($policyId, $targetId, $target->effective_from);
                return true;
            } catch (\Throwable $e) {
                Log::warning("EndorseRefreshRunner: first invoice raise failed for action {$targetId}: " . $e->getMessage());
                return false;
            }
        }

        if ($sync['reason'] === 'no_base') {
            // Existing invoice totals zero, so the VAT split can't be inferred.
            // Never guess at the GL — leave it for a manual credit note.
            Log::warning(
                "EndorseRefreshRunner: invoice for action {$targetId} totals {$sync['old_total']}, "
                . "cannot rescale to {$premium} — needs a manual credit note."
            );
        }

        return false; // 'no_change' and 'no_base' both leave billing untouched
    }

    /**
     * Invoke PolicyCreateController::recomputeActionTotals (private, static) for
     * one action. Single copy of the reflection call shared by the fill/rebuild
     * finalisers; a failure is logged and swallowed so one un-summable action
     * can't abort the refresh.
     */
    private function recomputeActionTotals(int $policyId, int $actionId): void
    {
        try {
            $refl = new \ReflectionMethod(
                '\\AlphaDirect\\Http\\Controllers\\Api\\V1\\PolicyCreateController',
                'recomputeActionTotals'
            );
            $refl->setAccessible(true);
            $refl->invoke(null, $policyId, $actionId);
        } catch (\Throwable $e) {
            Log::warning("EndorseRefreshRunner: annual recompute failed for action {$actionId}: " . $e->getMessage());
        }
    }

    /**
     * COVERAGE-SELECTIVE FORWARD for one target.
     *
     * Copy ONLY the coverages in $coverageIds (matched by master coverage_id)
     * from the source into the target, with their full child tree — coverage
     * details, extensions, specified items, motor rows (+ traders int/ext),
     * coverages_data, entities, notes and specialist coverage tables — plus the
     * `vehicle` rows for any forwarded motor coverage. Idempotent: a coverage the
     * target already has (coverage_id + risk-address name) is not duplicated; a
     * vehicle already present under a matched coverage is skipped. Every OTHER
     * coverage on the target is left untouched.
     *
     * Reuses the same replication helpers newPolicyActionReplace uses, scoped via
     * the additive $onlyCoverageIds filter on replicateRecordsIfMissing /
     * replicateSpecialistCoveragesIfMissing / fillMissingMotorVehicles. Premium
     * is finalised through the shared finaliseTargetPremium (RENEW/ANNIVERSARY
     * repriced + invoice refreshed; every other target's premium stays sealed).
     */
    private function forwardCoveragesIntoTarget(int $policyId, int $targetId, PolicyAction $source, array $coverageIds): void
    {
        if (empty($coverageIds)) {
            return;
        }

        $target = PolicyAction::find($targetId);
        if (!$target) {
            return;
        }

        $sealedPremium = (float) ($target->premium ?? 0);
        $srcId = (int) $source->id;
        $term  = $target->term_id;
        $override = ['term_id' => $term, 'row_type' => 'OLD'];

        // Risk addresses first (idempotent by address_name) so a forwarded
        // coverage can re-point at the target's own risk row.
        PolicyAction::replicateRecordsIfMissing($srcId, $targetId, 'AlphaDirect\Models\RiskAddress', 'action_id', ['term_id' => $term], 'address_name');

        // Selected coverages + child tree (scoped to $coverageIds).
        PolicyAction::replicateRecordsIfMissing($srcId, $targetId, 'AlphaDirect\Models\PolicyCoverage', 'action_id', $override, 'coverage', [
            'extentionDetail' => 'policy_coverage_id',
            'coverageDetail'  => 'policy_coverage_id',
            'specifedItems'   => 'policy_coverage_id',
            'entities'        => 'policy_coverage_id',
            'note'            => 'policy_coverage_id',
            'motorInternal'   => 'policy_coverage_id',
            'motorExteranal'  => 'policy_coverage_id',
        ], $coverageIds);
        PolicyAction::replicateRecordsIfMissing($srcId, $targetId, 'AlphaDirect\Models\PolicyCoverage', 'action_id', $override, 'coverage', [
            'motor' => 'policy_coverage_id',
        ], $coverageIds);
        // Fidelity Guarantee detail. Must use `coverageDataFidelity` (FK
        // `policyCoverageID`), NOT `policyCoveragesData` whose default FK
        // `policy_coverage_id` doesn't exist on the table — the insert branch
        // throws "Unknown column" and is silently swallowed, so Fidelity never
        // carried forward on endorse refresh. See PolicyAction::replicateRecords.
        PolicyAction::replicateRecordsIfMissing($srcId, $targetId, 'AlphaDirect\Models\PolicyCoverage', 'action_id', $override, 'coverage', [
            'coverageDataFidelity' => 'policyCoverageID',
        ], $coverageIds);

        PolicyAction::replicateSpecialistCoveragesIfMissing($srcId, $targetId, $coverageIds);

        // Carry vehicle rows (motor coverages) by plate for the forwarded
        // coverages, then fill any vehicle missing under a matched coverage, then
        // per-vehicle notes (motor rows now exist to map onto).
        $this->forwardVehiclesForCoverages($srcId, $targetId, $coverageIds);
        $this->fillMissingMotorVehicles($srcId, $targetId, $coverageIds);
        PolicyAction::replicateMotorNotesIfMissing($srcId, $targetId);

        $this->finaliseTargetPremium($policyId, $targetId, $target, $sealedPremium);
    }

    /**
     * COVERAGE-SELECTIVE DROP for one target.
     *
     * SOFT-delete (never hard-delete) the target's coverages matching
     * $coverageIds and their child rows across the same table set
     * hardDeleteActionCoverageTree lists, scoped to the matching policy_coverage
     * ids only. Soft delete so recomputeActionTotals drops them from the sum
     * (every sum is whereNull('deleted_at')) while the audit trail is preserved.
     * Premium is finalised through the shared finaliseTargetPremium — for
     * RENEW/ANNIVERSARY the invoice simply falls to the new lower total (no
     * separate credit note); every other target keeps its sealed premium.
     */
    private function dropCoveragesFromTarget(int $policyId, int $targetId, array $coverageIds): void
    {
        if (empty($coverageIds)) {
            return;
        }

        $target = PolicyAction::find($targetId);
        if (!$target) {
            return;
        }

        $sealedPremium = (float) ($target->premium ?? 0);

        $pcIds = DB::table('policy_coverages')
            ->where('action_id', $targetId)
            ->whereIn('coverage_id', $coverageIds)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        if (!empty($pcIds)) {
            DB::transaction(function () use ($pcIds) {
                $now = now();
                $soft = function (string $table, string $col, array $values) use ($now) {
                    if (!Schema::hasTable($table) || count($values) === 0) return;
                    if (!Schema::hasColumn($table, 'deleted_at')) return;
                    DB::table($table)->whereIn($col, $values)->whereNull('deleted_at')->update(['deleted_at' => $now]);
                };

                $soft('policy_coverage_detail',   'policy_coverage_id', $pcIds);
                $soft('policy_extention_detail',  'policy_coverage_id', $pcIds);
                $soft('policy_specified_items',   'policy_coverage_id', $pcIds);
                $soft('motor',                    'policy_coverage_id', $pcIds);
                $soft('motor_traders',            'policy_coverage_id', $pcIds);
                $soft('motor_traders_internal',   'policy_coverage_id', $pcIds);
                $soft('policy_coverage_notes',    'policy_coverage_id', $pcIds);
                $soft('policy_coverage_entities', 'policy_coverage_id', $pcIds);
                $soft('policy_coverages_data',    'policyCoverageID',   $pcIds);
                foreach (\AlphaDirect\Services\SpecialistEndorse\SpecialistCoverageRegistry::tableNames() as $t) {
                    $soft($t, 'policy_coverage_id', $pcIds);
                }
                // Soft-delete the coverage rows LAST — recompute keys the whole
                // sum off active policy_coverages, so this alone drops the total.
                $soft('policy_coverages', 'id', $pcIds);
            });
        }

        $this->finaliseTargetPremium($policyId, $targetId, $target, $sealedPremium);
    }

    /**
     * Copy the `vehicle` rows (action-keyed, by plate) for the forwarded motor
     * coverages. The vehicle table has no direct coverage link, so plates are
     * resolved from the source `motor` rows under the selected coverages. Each
     * vehicle is re-pointed at the target action's matching risk address (by
     * address_name) and deduped by (target action + plate). Idempotent.
     */
    private function forwardVehiclesForCoverages(int $sourceActionId, int $targetActionId, array $coverageIds): void
    {
        if ($sourceActionId <= 0 || $targetActionId <= 0 || $sourceActionId === $targetActionId || empty($coverageIds)) {
            return;
        }

        $srcPcIds = DB::table('policy_coverages')
            ->where('action_id', $sourceActionId)
            ->whereIn('coverage_id', $coverageIds)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();
        if (empty($srcPcIds)) {
            return;
        }

        $plates = Motor::whereIn('policy_coverage_id', $srcPcIds)
            ->whereNull('deleted_at')
            ->pluck('registration_no')
            ->map(fn ($p) => trim((string) $p))
            ->filter(fn ($p) => $p !== '')
            ->unique()
            ->values()
            ->all();
        if (empty($plates)) {
            return;
        }

        $sourceVehicles = Vehicle::where('action_id', $sourceActionId)
            ->whereNull('deleted_at')
            ->whereIn('vehiclePlate', $plates)
            ->get();

        foreach ($sourceVehicles as $sv) {
            try {
                // Re-point risk_id at the target action's matching risk address.
                $toRiskId = null;
                if ($sv->risk_id) {
                    $fromRisk = RiskAddress::find($sv->risk_id);
                    if ($fromRisk) {
                        $toRisk = RiskAddress::Action($targetActionId)->AddressName($fromRisk->address_name)->first();
                        $toRiskId = $toRisk?->id;
                    }
                }

                $exists = Vehicle::where('action_id', $targetActionId)
                    ->where('vehiclePlate', $sv->vehiclePlate)
                    ->whereNull('deleted_at')
                    ->when($toRiskId, fn ($q) => $q->where('risk_id', $toRiskId))
                    ->exists();
                if ($exists) {
                    continue;
                }

                $clone = $sv->replicate();
                $clone->action_id = $targetActionId;
                if ($toRiskId) {
                    $clone->risk_id = $toRiskId;
                }
                $clone->save();
            } catch (\Throwable $e) {
                Log::warning("EndorseRefreshRunner(coverage_forward): vehicle clone skipped for vehicle {$sv->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Copy vehicles (motor rows) the source has under a coverage but the target
     * is missing, matched by (coverage_id + risk-address name) at the coverage
     * level and by registration_no at the vehicle level. Each cloned vehicle
     * carries its per-vehicle specified items (motor_id-keyed). Per-vehicle
     * NOTES are carried by the caller's replicateMotorNotesIfMissing pass, which
     * runs after this so the new motor rows exist to map onto.
     *
     * Only fills coverages present in BOTH actions — a coverage the target
     * lacks entirely is already handled by newPolicyActionReplace. Idempotent:
     * a registration already on the target coverage is skipped — including one
     * that was CANCELLED (soft-deleted) there, which stays cancelled.
     *
     * $onlyCoverageIds (coverage-selective forward) restricts the fill to source
     * coverages whose master coverage_id is in the list; empty = no filter, so
     * the existing fill_missing caller is unaffected.
     */
    private function fillMissingMotorVehicles(int $sourceActionId, int $targetActionId, array $onlyCoverageIds = []): void
    {
        if ($sourceActionId <= 0 || $targetActionId <= 0 || $sourceActionId === $targetActionId) {
            return;
        }

        $pcKey = static function ($row): string {
            $name = strtolower(trim((string) ($row->risk_address_name ?? '')));
            return (int) $row->coverage_id . '|' . $name;
        };

        $sourceCoverages = DB::table('policy_coverages as pc')
            ->leftJoin('risk_address as ra', 'pc.risk_address_id', '=', 'ra.id')
            ->where('pc.action_id', $sourceActionId)
            ->whereNull('pc.deleted_at')
            ->when(!empty($onlyCoverageIds), fn ($q) => $q->whereIn('pc.coverage_id', $onlyCoverageIds))
            ->get(['pc.id', 'pc.coverage_id', 'ra.address_name as risk_address_name']);
        if ($sourceCoverages->isEmpty()) {
            return;
        }

        $targetCoverages = DB::table('policy_coverages as pc')
            ->leftJoin('risk_address as ra', 'pc.risk_address_id', '=', 'ra.id')
            ->where('pc.action_id', $targetActionId)
            ->whereNull('pc.deleted_at')
            ->get(['pc.id', 'pc.coverage_id', 'ra.address_name as risk_address_name']);
        $targetByKey = [];
        foreach ($targetCoverages as $tpc) {
            $targetByKey[$pcKey($tpc)] = (int) $tpc->id;
        }

        foreach ($sourceCoverages as $spc) {
            $targetPcId = $targetByKey[$pcKey($spc)] ?? null;
            if (!$targetPcId) {
                continue; // whole coverage — handled by newPolicyActionReplace
            }

            // Registrations already ON the target coverage — LIVE **or
            // CANCELLED**.
            //
            // A per-vehicle Cancel on the target endorse soft-deletes the motor
            // row. Reading only live rows made that vehicle look "missing", so
            // this pass cloned the source's live copy back in: the vehicle the
            // operator cancelled on the endorse came back as a brand-new,
            // chargeable row on every Refresh. A cancellation on the target is
            // the operator's decision — treat the registration as present and
            // leave it cancelled. DB::table, not the model, so the SoftDeletes
            // scope can't hide the trashed rows again.
            $existingRegs   = [];
            $cancelledRegs  = [];
            foreach (
                DB::table('motor')
                    ->where('policy_coverage_id', $targetPcId)
                    ->get(['registration_no', 'deleted_at'])
                as $tm
            ) {
                $reg = strtolower(trim((string) ($tm->registration_no ?? '')));
                if ($reg !== '') {
                    $existingRegs[$reg] = true;
                    if (!empty($tm->deleted_at)) {
                        $cancelledRegs[$reg] = true;
                    }
                }
            }

            $sourceMotors = Motor::where('policy_coverage_id', $spc->id)->whereNull('deleted_at')->get();
            foreach ($sourceMotors as $sm) {
                $reg = strtolower(trim((string) ($sm->registration_no ?? '')));
                if ($reg === '' || isset($existingRegs[$reg])) {
                    if (isset($cancelledRegs[$reg])) {
                        Log::info('EndorseRefreshRunner(fill_missing): vehicle is CANCELLED on the target batch — not re-added', [
                            'target_action'   => $targetActionId,
                            'target_coverage' => $targetPcId,
                            'registration'    => $sm->registration_no,
                        ]);
                    }
                    continue; // blank, already present, or cancelled here
                }

                try {
                    $clone = $sm->replicate();
                    $clone->policy_coverage_id = $targetPcId;
                    // APPEND-ONLY: the clone still carries the SOURCE action's
                    // previousActionIdCov, and writeLineLevelProRata's motor loop
                    // skips any row not stamped with the action being rated — so
                    // the appended vehicle rated at 0 and the batch kept its old
                    // premium. Stamp it onto THIS batch (PolicyAction::
                    // stampAppendedChildRow) so it rates as a normal addition.
                    PolicyAction::stampAppendedChildRow($clone, $targetActionId);
                    $clone->save();
                    $newMotorId = (int) $clone->id;
                } catch (\Throwable $e) {
                    Log::warning("EndorseRefreshRunner(fill_missing): vehicle clone skipped for motor {$sm->id}: " . $e->getMessage());
                    continue;
                }

                // Per-vehicle specified items (motor_id-keyed, cover 22/27).
                $items = PolicySpecifiedItem::where('policy_coverage_id', $spc->id)
                    ->where('motor_id', $sm->id)
                    ->get();
                foreach ($items as $it) {
                    try {
                        $ic = $it->replicate();
                        $ic->policy_coverage_id = $targetPcId;
                        $ic->motor_id = $newMotorId;
                        // Same stamp as the vehicle above — without it the
                        // specified-item loop skips the row and the accessory
                        // never reaches the batch's pro-rata.
                        PolicyAction::stampAppendedChildRow($ic, $targetActionId);
                        $ic->save();
                    } catch (\Throwable $e) {
                        Log::warning("EndorseRefreshRunner(fill_missing): specified-item copy skipped for motor {$sm->id}: " . $e->getMessage());
                    }
                }
            }
        }
    }
}
