<?php

namespace AlphaDirect\Services\BackdatedEndorse;

use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Services\Ledger\InvoiceAmountSync;
use AlphaDirect\Services\SpecialistEndorse\SpecialistCoverageRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Propagate a back-dated ENDORSE's row-level data forward into every future
 * batch on the same policy (future ENDORSE + RENEW; anniversaries are
 * RENEW). Universal edit protection: per-field, per-row, per-action.
 *
 *   - DATA refresh:   every future batch (ENDORSE + RENEW)
 *   - PREMIUM recalc: only RENEW (anniversary = RENEW)
 *   - INVOICE recalc: only RENEW, via in-place ledger update + audit row
 *                     in `ledger_adjustments`. Invoice number unchanged.
 *
 * NEVER:
 *   - touches future ENDORSE pro-rata
 *   - deletes ledger / sub_ledger / invoice rows
 *   - alters invoice numbers
 *   - reverts a future-batch edit
 *   - cascades into past terms
 *   - references the deprecated 'ENDORSE-RENEW' transaction_type
 *
 * Public:
 *   refresh(PolicyAction): RefreshReport     // writes
 *   dryRun(PolicyAction): RefreshReport      // no writes
 */
class BackdatedEndorseRefresher
{
    // Downstream batch types a forward propagation must reach. ANNIVERSARY-RENEW
    // (the next-term renewal quote) is included so an anniversary gets BOTH the
    // corrected coverage tree AND a recomputed premium — without it a 6-vehicle
    // tree could carry a stale 3-vehicle premium (the GRA anniversary bug).
    private const FUTURE_TX_TYPES = ['ENDORSE', 'RENEW', 'ANNIVERSARY-RENEW'];
    private const FUTURE_STATUSES = ['QUOTE', 'ISSUED'];

    // Downstream batch types that carry a RECOMPUTABLE renewal premium. Both a
    // scheduled RENEW and an ANNIVERSARY-RENEW re-derive their premium from the
    // (now-corrected) coverage tree via calculatePremiumRenew; an ENDORSE keeps
    // its sealed pro-rata and only refreshes annual_premium.
    private const RENEW_TX_TYPES = ['RENEW', 'ANNIVERSARY-RENEW'];

    /**
     * Tables that carry premium-bearing rows. business_key columns are used
     * to match source row to target row WITHIN a policy_coverage. value_fields
     * are the columns we propagate. excess_field (per EC-6) propagates as
     * data but never as a premium delta — we still copy it.
     *
     * motor_traders + motor_traders_internal carry 14 SI + 14 premium cols
     * (one row per pc) — for simplicity, "value_fields" is built dynamically
     * by reading the row's columns at runtime.
     */
    private const CHILD_TABLES = [
        'policy_coverage_detail' => [
            'business_key' => ['coverage_id'],
            'value_fields' => ['coverage_value', 'rate', 'calculated_value'],
        ],
        'motor' => [
            'business_key' => ['registration_no'],
            'value_fields' => ['calculated_value'], // + premium_* added at runtime via MOTOR_EXTENSION_PREMIUM_COLUMNS
        ],
        'motor_traders' => [
            'business_key' => [],  // one row per pc; match by parent pc
            'value_fields' => [],  // 14 SI + 14 premium cols added at runtime
        ],
        'motor_traders_internal' => [
            'business_key' => [],
            'value_fields' => [],
        ],
        'policy_extention_detail' => [
            'business_key' => ['extentions_id', 'type'],
            'value_fields' => ['extention_coverage_value', 'extention_calculated_value', 'extention_sum_insured'],
        ],
        'policy_specified_items' => [
            'business_key' => ['specified_coverage_id', 'motor_id'],
            'value_fields' => ['sum_insured', 'rate', 'calculated_value'],
        ],
    ];

    /** Motor table extension premium columns — added to motor value_fields. */
    private const MOTOR_EXTENSION_PREMIUM_COLUMNS = [
        'premium_wreckage_removal', 'premium_window_glass', 'premium_locks_keys',
        'premium_parts_accessories', 'premium_riot_strike', 'premium_credit_shortfall',
        'premium_med_dis_passenger', 'premium_med_dis_paid_driver',
        'premium_insured_family', 'premium_medical_expenses',
        'premium_passenger_liability', 'premium_third_party_liability',
        'premium_specified_accessories', 'premium_unorthorised_passanger_liability',
        'premium_parking_facilities', 'premium_com_windscreen',
        'premium_contigent_liability',
    ];

    /** Motor Traders premium column names (14 cols). EC-6: excess fields excluded. */
    private const MT_PREMIUM_COLUMNS = [
        'loss_or_damage_calculated_value', 'third_party_liability_calculated_value',
        'medical_benefits_calculated_value', 'vehicle_lent_hire_calculated_value',
        'social_domestic_pleasure_calculated_value', 'unauthoried_use_calculated_value',
        'windscreen_calculated_value', 'contigent_liability_calculated_value',
        'wreckage_removal_calculated_value', 'loss_of_key_calculated_value',
        'Loss_of_use_of_customer_calculated_value', 'motor_cycle_motor_tricycle_calculated_value',
        'passanger_liability_respect_of_motor_calculated_value', 'special_type_vehicle_calculated_value',
    ];

    /** Motor Traders sum-insured column names (14 cols). */
    private const MT_COVERAGE_COLUMNS = [
        'loss_or_damage_coverage_value', 'third_party_liability_coverage_value',
        'medical_benefits_coverage_value', 'vehicle_lent_hire_coverage_value',
        'social_domestic_pleasure_coverage_value', 'unauthoried_use_coverage_value',
        'windscreen_coverage_value', 'contigent_liability_coverage_value',
        'wreckage_removal_coverage_value', 'loss_of_key_coverage_value',
        'Loss_of_use_of_customer_coverage_value', 'motor_cycle_motor_tricycle_coverage_value',
        'passanger_liability_respect_of_motor_coverage_value', 'special_type_vehicle_coverage_value',
    ];

    public function refresh(PolicyAction $backdated): RefreshReport
    {
        return $this->run($backdated, false);
    }

    public function dryRun(PolicyAction $backdated): RefreshReport
    {
        return $this->run($backdated, true);
    }

    private function run(PolicyAction $backdated, bool $dryRun): RefreshReport
    {
        $report = new RefreshReport();
        $report->dryRun = $dryRun;
        $report->sourceActionId = (int) $backdated->id;

        if (!$this->isBackdatedEndorse($backdated)) {
            $report->pushError('Source action is not a backdated ISSUED ENDORSE — refresh skipped.', [
                'transaction_type' => $backdated->transaction_type,
                'status' => $backdated->status,
                'effective_from' => $backdated->effective_from,
                'note' => 'No ISSUED action with later effective_from exists on this policy — nothing to propagate.',
            ]);
            return $report;
        }

        $changeSet = $this->buildChangeSet($backdated);
        // Motor section notes have no endors_flag / previousActionIdCov
        // columns, so they cannot ride the wizard-edit change set. Carry them
        // forward separately (see propagateMotorNotes) and let their presence
        // also keep a note-only refresh from short-circuiting below.
        $noteCarry = $this->buildNoteCarrySet($backdated);
        if (empty($changeSet) && empty($noteCarry)) {
            $report->pushDecision([
                'phase' => 'change_set',
                'result' => 'empty',
                'note' => 'Backdated endorse stamped no wizard edits and carries no motor notes — nothing to propagate.',
            ]);
            return $report;
        }

        $hash = $this->idempotencyHash($changeSet, $noteCarry);
        $future = $this->findFutureActions($backdated);

        foreach ($future as $target) {
            $report->targetActionsProcessed++;
            $isRenew = in_array($target->transaction_type, self::RENEW_TX_TYPES, true);

            // EC-8 idempotency: skip if (source, target, hash) already logged.
            if (!$dryRun && $this->alreadyApplied((int) $backdated->id, (int) $target->id, $hash)) {
                $report->pushDecision([
                    'phase' => 'idempotency',
                    'target_action_id' => $target->id,
                    'result' => 'skipped — already applied',
                ]);
                continue;
            }

            try {
                $delta = $this->applyToTarget($backdated, $target, $changeSet, $hash, $dryRun, $report);
                if ($isRenew) {
                    $report->renewsUpdated++;
                    $report->totalPremiumDelta += abs($delta);
                } else {
                    $report->endorsesUpdated++;
                }
            } catch (\Throwable $e) {
                $report->pushError('Failed to apply to target action ' . $target->id . ': ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        Log::info('BackdatedEndorseRefresher: ' . ($dryRun ? 'dry-run' : 'refresh') . ' done', [
            'source_action_id' => $backdated->id,
            'targets' => $report->targetActionsProcessed,
            'endorses_updated' => $report->endorsesUpdated,
            'renews_updated' => $report->renewsUpdated,
            'fields_updated' => $report->fieldsUpdated,
            'fields_skipped_protected' => $report->fieldsSkippedProtected,
            'ledger_adjustments_written' => $report->ledgerAdjustmentsWritten,
            'total_premium_delta' => $report->totalPremiumDelta,
            'idempotency_hash' => $hash,
        ]);

        return $report;
    }

    // ── Backdated detection ───────────────────────────────────────────────

    /**
     * Backdated detection is POLICY-RELATIVE, not system-today-relative.
     *
     * An ENDORSE is "backdated" iff there exists at least one other
     * ISSUED action on the same policy whose effective_from is LATER than
     * this endorse's effective_from. That later action is the one whose
     * data state needs to absorb the back-dated change — that's the
     * whole reason this engine exists.
     *
     * Example (policy 213504):
     *   - 16/06/2026 ENDORSE issued first
     *   - 01/06/2026 ENDORSE issued later (created back-dated)
     *   - 16/06 is downstream of 01/06 by effective date
     *   - So 01/06 IS backdated, regardless of system today.
     *
     * If NO future-effective-date issued action exists, the endorse is
     * the latest in the policy timeline — nothing to propagate forward.
     */
    private function isBackdatedEndorse(PolicyAction $action): bool
    {
        if ($action->transaction_type !== 'ENDORSE') return false;
        if ($action->status !== 'ISSUED') return false;
        if (empty($action->effective_from)) return false;

        // "Later in the timeline" = later (effective_from, id). A SAME-DATE
        // action with a higher id was transacted after this one and is a
        // legitimate propagation target, so this endorse is backdated relative
        // to it — the old date-only `>` said "nothing downstream" and skipped
        // propagation entirely (three 01/01/2026 endorsements, policy 120909).
        return PolicyAction::query()
            ->where('policy_id', $action->policy_id)
            ->where('id', '!=', $action->id)
            ->where('status', 'ISSUED')
            ->whereIn('transaction_type', self::FUTURE_TX_TYPES)
            ->tap(fn ($q) => PolicyAction::applyForwardWindow(
                $q,
                $action,
                \Carbon\Carbon::parse($action->effective_from)->toDateString()
            ))
            ->whereNull('deleted_at')
            ->exists();
    }

    // ── CHANGE_SET ────────────────────────────────────────────────────────

    /**
     * CHANGE_SET = every wizard-touched row in the backdated endorse's child
     * tables. Wizard-touched = previousActionIdCov = backdated.id AND
     * endors_flag = 1. Each entry carries the row's value_fields so the
     * applyToTarget step can do per-field propagation with edit protection.
     */
    private function buildChangeSet(PolicyAction $backdated): array
    {
        $set = [];
        $pcIds = DB::table('policy_coverages')
            ->where('policy_id', $backdated->policy_id)
            ->where('action_id', $backdated->id)
            ->pluck('id');
        if ($pcIds->isEmpty()) return $set;

        // pc → (coverage_id, risk_address.address_name) so target action's
        // child rows can be matched back to their parent pc via the same key.
        $pcMeta = DB::table('policy_coverages as pc')
            ->leftJoin('risk_address as ra', 'ra.id', '=', 'pc.risk_address_id')
            ->whereIn('pc.id', $pcIds)
            ->get(['pc.id', 'pc.coverage_id', 'ra.address_name']);
        $pcMetaById = [];
        foreach ($pcMeta as $m) {
            $pcMetaById[$m->id] = [
                'coverage_id' => (int) $m->coverage_id,
                'address_name' => trim((string) ($m->address_name ?? '')),
            ];
        }

        foreach ($this->childTablesMap() as $table => $meta) {
            if (!Schema::hasTable($table)) continue;
            if (!Schema::hasColumn($table, 'previousActionIdCov')) continue;
            if (!Schema::hasColumn($table, 'endors_flag')) continue;

            // NOTE: no whereNull('deleted_at') here — a vehicle REMOVED by this
            // endorse is a soft-deleted-but-still-stamped row we must carry as a
            // 'remove' op (see below), so we intentionally include deleted rows.
            $rows = DB::table($table)
                ->whereIn('policy_coverage_id', $pcIds)
                ->where('previousActionIdCov', $backdated->id)
                ->where('endors_flag', 1)
                ->get();

            $hasDeleted = Schema::hasColumn($table, 'deleted_at');

            foreach ($rows as $r) {
                $bk = [];
                foreach ($meta['business_key'] as $col) {
                    if (property_exists($r, $col)) {
                        $bk[$col] = $r->{$col};
                    }
                }
                $valueFields = $this->valueFieldsForTable($table, $r);
                $values = [];
                foreach ($valueFields as $col) {
                    if (property_exists($r, $col)) {
                        $values[$col] = $r->{$col};
                    }
                }
                $parent = $pcMetaById[$r->policy_coverage_id] ?? null;
                if (!$parent) continue;

                // Propagation op:
                //   'remove' — the endorse dropped this vehicle (motor row
                //              soft-deleted in place: deleted_at set, still
                //              endors_flag=1 / previousActionIdCov=endorse.id).
                //              Downstream batches must DROP it, not keep it.
                //   'upsert' — update the row where present, ADD it where the
                //              target batch is missing it (closes EC-9).
                // Only the `motor` table supports vehicle-level removal; every
                // other table keeps its existing 'upsert' semantics unchanged.
                $isDeleted = $hasDeleted && !empty($r->deleted_at);
                $op = ($isDeleted && $table === 'motor') ? 'remove' : 'upsert';

                $set[] = [
                    'table' => $table,
                    'pc_id' => (int) $r->policy_coverage_id,
                    'pc_key' => $parent['coverage_id'] . '|' . $parent['address_name'],
                    'row_id' => $r->id ?? null,
                    'op' => $op,
                    'business_key' => $bk,
                    'values' => $values,
                    'has_deleted' => $hasDeleted,
                ];
            }
        }
        return $set;
    }

    /** Resolve value-fields list per table, including dynamic ones. */
    private function valueFieldsForTable(string $table, $row): array
    {
        $map = $this->childTablesMap();
        $fields = $map[$table]['value_fields'] ?? [];
        if ($table === 'motor') {
            $fields = array_merge($fields, self::MOTOR_EXTENSION_PREMIUM_COLUMNS);
        }
        if ($table === 'motor_traders' || $table === 'motor_traders_internal') {
            // Propagate all 14 SI + 14 premium cols. EC-6: SI cols include
            // "excess"-style values, propagated as data but never used for
            // premium-delta computation (RENEW recalc reads from policy_actions
            // pipeline which uses calculated_value cols only).
            $fields = array_merge(self::MT_COVERAGE_COLUMNS, self::MT_PREMIUM_COLUMNS);
        }
        return $fields;
    }

    /**
     * Combined motor (const) + specialist (registry) child-tables map.
     * The motor entries stay in self::CHILD_TABLES so the existing motor
     * behaviour is byte-for-byte identical. Specialist entries come from
     * SpecialistCoverageRegistry so they live in one place across the
     * delete-cascade, replicator, and refresher paths.
     *
     * Specialist tables are merged ONLY when their endorsement columns
     * (previousActionIdCov, endors_flag) actually exist in the schema —
     * otherwise the change_set query in buildChangeSet would throw on
     * envs where the Phase-0 migration hasn't been run yet.
     */
    private function childTablesMap(): array
    {
        $map = self::CHILD_TABLES;
        foreach (SpecialistCoverageRegistry::refresherChildTables() as $table => $meta) {
            if (!Schema::hasTable($table)) continue;
            if (!Schema::hasColumn($table, 'previousActionIdCov')) continue;
            if (!Schema::hasColumn($table, 'endors_flag')) continue;
            $map[$table] = $meta;
        }
        return $map;
    }

    // ── Target discovery ──────────────────────────────────────────────────

    private function findFutureActions(PolicyAction $backdated)
    {
        return PolicyAction::query()
            ->where('policy_id', $backdated->policy_id)
            // Anchor on the EFFECTIVE date — never transaction_date (see
            // PolicyAction::forwardPropagationAnchor).
            //
            // The strict `>` on the date alone dropped every SAME-DATE target:
            // on policy 120909 three ENDORSEs share 01/01/2026, so issuing the
            // first propagated to the 01/04 and 01/07 RENEWs but never to the
            // other two endorsements — they kept an older vehicle list.
            // applyForwardWindow adds the id tie-break on the anchor date.
            ->tap(fn ($q) => PolicyAction::applyForwardWindow($q, $backdated))
            ->whereIn('transaction_type', self::FUTURE_TX_TYPES)
            ->whereIn('status', self::FUTURE_STATUSES)
            ->whereNull('deleted_at')
            ->where('id', '!=', $backdated->id)
            ->tap(fn ($q) => PolicyAction::applyChronoOrder($q))
            ->get();
    }

    // ── Apply per-target ──────────────────────────────────────────────────

    /**
     * Returns the absolute premium delta produced for RENEW targets
     * (0 for ENDORSE / no-change cases). Caller uses it for the report
     * total_premium_delta.
     */
    private function applyToTarget(
        PolicyAction $backdated,
        PolicyAction $target,
        array $changeSet,
        string $hash,
        bool $dryRun,
        RefreshReport $report
    ): float {
        // Map target pcs by the same (coverage_id|address_name) key used in
        // the change set so we can resolve "this source pc -> that target pc".
        $tgtPcs = DB::table('policy_coverages as pc')
            ->leftJoin('risk_address as ra', 'ra.id', '=', 'pc.risk_address_id')
            ->where('pc.policy_id', $target->policy_id)
            ->where('pc.action_id', $target->id)
            ->get(['pc.id', 'pc.coverage_id', 'pc.deleted_at', 'ra.address_name']);
        $tgtPcByKey = [];
        foreach ($tgtPcs as $tpc) {
            $key = $tpc->coverage_id . '|' . trim((string) ($tpc->address_name ?? ''));
            $tgtPcByKey[$key] = $tpc;
        }

        $isRenew = in_array($target->transaction_type, self::RENEW_TX_TYPES, true);
        $premiumDelta = 0.0;

        foreach ($changeSet as $entry) {
            $tgtPc = $tgtPcByKey[$entry['pc_key']] ?? null;
            if (!$tgtPc) {
                // EC-9: row doesn't exist in target action; skip.
                $report->pushDecision([
                    'phase' => 'apply',
                    'target_action_id' => $target->id,
                    'table' => $entry['table'],
                    'result' => 'skipped — target pc missing (EC-9)',
                    'source_pc_key' => $entry['pc_key'],
                ]);
                continue;
            }

            // EC-5: target pc was cancelled (soft-deleted) — skip data refresh.
            if (!empty($tgtPc->deleted_at)) {
                $report->cancelledRowsSkipped++;
                $report->pushDecision([
                    'phase' => 'apply',
                    'target_action_id' => $target->id,
                    'table' => $entry['table'],
                    'result' => 'skipped — target pc is CANCELLED (EC-5)',
                    'target_pc_id' => $tgtPc->id,
                ]);
                continue;
            }

            $op = $entry['op'] ?? 'upsert';

            // Find the matching LIVE target row by business key.
            $tgtRow = $this->findTargetRow($entry['table'], (int) $tgtPc->id, $entry['business_key']);

            // REMOVE: the endorse dropped this vehicle. Soft-delete the matching
            // live target row (idempotent — a no-op if already absent) so the
            // downstream batch stops carrying + billing the removed risk. The
            // RENEW premium recompute below reads only live rows, so the premium
            // falls automatically and a ledger_adjustments row records the drop.
            if ($op === 'remove') {
                $this->applyRemoveToTarget($backdated, $target, $entry, $tgtPc, $tgtRow, $hash, $dryRun, $report);
                continue;
            }

            // ADD-MISSING (closes EC-9): the endorse ADDED this vehicle but the
            // target batch never got it — the exact defect that silently drops
            // endorsed vehicles from renewals (under-charge). Clone the source
            // vehicle (and its per-vehicle specified items) into the target
            // coverage, keyed on registration_no. Motor only; a whole missing
            // coverage is left to the additive replicate safety net.
            if (!$tgtRow) {
                if ($entry['table'] === 'motor' && !empty($entry['business_key']['registration_no'])) {
                    $this->applyAddMissingMotor($backdated, $target, $entry, $tgtPc, $hash, $dryRun, $report);
                } else {
                    $report->pushDecision([
                        'phase' => 'apply',
                        'target_action_id' => $target->id,
                        'table' => $entry['table'],
                        'result' => 'skipped — target row missing (EC-9, deferred to additive replicate)',
                        'business_key' => $entry['business_key'],
                    ]);
                }
                continue;
            }

            // EC-5 (row-level): row was cancelled in target.
            if (!empty($tgtRow->deleted_at ?? null)) {
                $report->cancelledRowsSkipped++;
                $report->pushDecision([
                    'phase' => 'apply',
                    'target_action_id' => $target->id,
                    'table' => $entry['table'],
                    'result' => 'skipped — target row CANCELLED (EC-5)',
                    'target_row_id' => $tgtRow->id ?? null,
                ]);
                continue;
            }

            // Per-field universal edit protection.
            $fieldsUpdated = [];
            $fieldsSkipped = [];
            $prevValuesInTgt = $this->prevValuesForTargetRow($entry['table'], $tgtRow, $target);
            foreach ($entry['values'] as $col => $newVal) {
                $isEditedByTgt = $this->isFieldEditedByTarget($tgtRow, $col, $prevValuesInTgt, $target);
                if ($isEditedByTgt) {
                    $fieldsSkipped[$col] = [
                        'target_value' => $tgtRow->{$col} ?? null,
                        'source_value' => $newVal,
                        'reason' => 'edited by target batch — protected',
                    ];
                    continue;
                }
                $fieldsUpdated[$col] = [
                    'old' => $tgtRow->{$col} ?? null,
                    'new' => $newVal,
                ];
            }

            if (!empty($fieldsUpdated) && !$dryRun) {
                $update = [];
                foreach ($fieldsUpdated as $col => $vals) {
                    $update[$col] = $vals['new'];
                }
                if (Schema::hasColumn($entry['table'], 'updated_at')) {
                    $update['updated_at'] = now();
                }
                DB::table($entry['table'])->where('id', $tgtRow->id)->update($update);
            }

            $report->fieldsUpdated += count($fieldsUpdated);
            $report->fieldsSkippedProtected += count($fieldsSkipped);

            $report->pushDecision([
                'phase' => 'apply',
                'target_action_id' => $target->id,
                'target_transaction_type' => $target->transaction_type,
                'table' => $entry['table'],
                'target_row_id' => $tgtRow->id ?? null,
                'business_key' => $entry['business_key'],
                'fields_updated' => $fieldsUpdated,
                'fields_skipped_protected' => $fieldsSkipped,
            ]);

            // Log row inserts only in live mode.
            if (!$dryRun) {
                $this->insertRefreshLog(
                    sourceId: (int) $backdated->id,
                    targetId: (int) $target->id,
                    targetType: (string) $target->transaction_type,
                    table: $entry['table'],
                    businessKey: $entry['business_key'],
                    fieldsUpdated: $fieldsUpdated,
                    fieldsSkipped: $fieldsSkipped,
                    premiumRecalculated: false,
                    premiumDelta: null,
                    hash: $hash
                );
            }
        }

        // Motor section notes carry forward like the other child tables.
        // Done here (not via the change set) because policy_coverage_notes has
        // no endors_flag / previousActionIdCov edit-tracking columns.
        $this->propagateMotorNotes($backdated, $target, $tgtPcByKey, $dryRun, $hash, $report);

        // Post-row-updates: recompute totals per transaction type.
        if (!$dryRun) {
            if ($isRenew) {
                // RENEW: full recompute + ledger adjustment + audit row.
                $premiumDelta = $this->recomputeRenewAndAdjustLedger($backdated, $target, $hash, $report);
            } else {
                // ENDORSE: recompute ANNUAL only (Total Premium in V2 Quote
                // header must reflect the back-dated value updates). Pro-rata
                // (policy_actions.premium) stays SEALED — it was finalised
                // when this endorse was originally issued.
                $this->recomputeAnnualOnlyForEndorse($backdated, $target, $hash, $report);
            }
        }

        return $premiumDelta;
    }

    // ── Add / remove propagation (EC-9 closure) ───────────────────────────

    /**
     * Soft-delete a vehicle in the target batch that the endorse removed.
     * Idempotent: if the target row is already gone it records a no-op. Also
     * soft-deletes the vehicle's per-vehicle specified items (motor_id-keyed),
     * mirroring cancelMotorVehicle, so the target premium recompute (live rows
     * only) drops the removed risk. Writes an audit log row; the BILLING change
     * itself is captured by the RENEW ledger recompute + ledger_adjustments.
     */
    private function applyRemoveToTarget(
        PolicyAction $backdated,
        PolicyAction $target,
        array $entry,
        $tgtPc,
        $tgtRow,
        string $hash,
        bool $dryRun,
        RefreshReport $report
    ): void {
        if (!$tgtRow) {
            $report->pushDecision([
                'phase' => 'apply',
                'target_action_id' => $target->id,
                'target_transaction_type' => $target->transaction_type,
                'table' => $entry['table'],
                'business_key' => $entry['business_key'],
                'result' => 'remove no-op — target row already absent (idempotent)',
            ]);
            return;
        }

        $fieldsUpdated = [
            'deleted_at' => ['old' => null, 'new' => '(soft-deleted — removed by endorse)'],
        ];

        if (!$dryRun) {
            $update = ['deleted_at' => now()];
            if (Schema::hasColumn($entry['table'], 'updated_at')) {
                $update['updated_at'] = now();
            }
            DB::table($entry['table'])->where('id', $tgtRow->id)->update($update);

            // Lockstep: drop the vehicle's per-vehicle specified items so they
            // stop feeding the renewal premium (mirrors cancelMotorVehicle).
            if ($entry['table'] === 'motor'
                && Schema::hasTable('policy_specified_items')
                && Schema::hasColumn('policy_specified_items', 'deleted_at')) {
                $siUpdate = ['deleted_at' => now()];
                if (Schema::hasColumn('policy_specified_items', 'updated_at')) {
                    $siUpdate['updated_at'] = now();
                }
                DB::table('policy_specified_items')
                    ->where('policy_coverage_id', (int) $tgtPc->id)
                    ->where('motor_id', (int) $tgtRow->id)
                    ->whereNull('deleted_at')
                    ->update($siUpdate);
            }
        }

        $report->fieldsUpdated += 1;
        $report->pushDecision([
            'phase' => 'apply',
            'target_action_id' => $target->id,
            'target_transaction_type' => $target->transaction_type,
            'table' => $entry['table'],
            'target_row_id' => $tgtRow->id ?? null,
            'business_key' => $entry['business_key'],
            'result' => 'REMOVED — vehicle soft-deleted (endorse removal propagated)',
            'fields_updated' => $fieldsUpdated,
        ]);

        if (!$dryRun) {
            $this->insertRefreshLog(
                sourceId: (int) $backdated->id,
                targetId: (int) $target->id,
                targetType: (string) $target->transaction_type,
                table: $entry['table'],
                businessKey: $entry['business_key'],
                fieldsUpdated: $fieldsUpdated,
                fieldsSkipped: [],
                premiumRecalculated: false,
                premiumDelta: null,
                hash: $hash
            );
        }
    }

    /**
     * Clone a vehicle the endorse ADDED into a target batch that is missing it,
     * keyed on registration_no. Raw insert (so the Auditable Motor observer does
     * not fire mid-propagation, consistent with this engine's DB::table style)
     * plus the vehicle's per-vehicle specified items. If a soft-deleted row for
     * the same registration already exists in the target coverage it is REVIVED
     * (deleted_at cleared + values refreshed) instead of duplicated, so the add
     * is idempotent. The subsequent RENEW premium recompute then bills the added
     * risk and writes a ledger_adjustments audit row.
     */
    private function applyAddMissingMotor(
        PolicyAction $backdated,
        PolicyAction $target,
        array $entry,
        $tgtPc,
        string $hash,
        bool $dryRun,
        RefreshReport $report
    ): void {
        $src = DB::table('motor')->where('id', (int) $entry['row_id'])->first();
        if (!$src) {
            $report->pushDecision([
                'phase' => 'apply',
                'target_action_id' => $target->id,
                'table' => 'motor',
                'result' => 'add skipped — source motor row vanished',
                'business_key' => $entry['business_key'],
            ]);
            return;
        }
        $reg = trim((string) ($src->registration_no ?? ''));

        // Idempotency: a row for this registration may already exist in the
        // target coverage (possibly a soft-deleted remnant) — revive/refresh it
        // rather than insert a duplicate.
        $existing = DB::table('motor')
            ->where('policy_coverage_id', (int) $tgtPc->id)
            ->where('registration_no', $reg)
            ->first();

        $fieldsUpdated = [
            'registration_no' => ['old' => null, 'new' => $reg],
            'vehicle' => 'ADDED — endorsed vehicle carried into batch (EC-9 closed)',
        ];

        if (!$dryRun) {
            if ($existing) {
                $update = ['deleted_at' => null];
                foreach ($entry['values'] as $col => $val) {
                    $update[$col] = $val;
                }
                if (Schema::hasColumn('motor', 'updated_at')) {
                    $update['updated_at'] = now();
                }
                DB::table('motor')->where('id', $existing->id)->update($update);
            } else {
                $row = (array) $src;
                unset($row['id']);
                $row['policy_coverage_id'] = (int) $tgtPc->id;
                $row['deleted_at'] = null;
                if (Schema::hasColumn('motor', 'created_at')) {
                    $row['created_at'] = now();
                }
                if (Schema::hasColumn('motor', 'updated_at')) {
                    $row['updated_at'] = now();
                }
                $newMotorId = (int) DB::table('motor')->insertGetId($row);
                $this->copyMotorSpecifiedItems(
                    (int) $entry['row_id'],
                    (int) $src->policy_coverage_id,
                    $newMotorId,
                    (int) $tgtPc->id
                );
            }
        }

        $report->fieldsUpdated += 1;
        $report->pushDecision([
            'phase' => 'apply',
            'target_action_id' => $target->id,
            'target_transaction_type' => $target->transaction_type,
            'table' => 'motor',
            'business_key' => $entry['business_key'],
            'result' => 'ADDED — missing endorsed vehicle cloned into batch (EC-9 closed)',
            'fields_updated' => $fieldsUpdated,
        ]);

        if (!$dryRun) {
            $this->insertRefreshLog(
                sourceId: (int) $backdated->id,
                targetId: (int) $target->id,
                targetType: (string) $target->transaction_type,
                table: 'motor',
                businessKey: $entry['business_key'],
                fieldsUpdated: $fieldsUpdated,
                fieldsSkipped: [],
                premiumRecalculated: false,
                premiumDelta: null,
                hash: $hash
            );
        }
    }

    /**
     * Clone a source vehicle's per-vehicle specified items (motor_id-keyed) onto
     * a newly added target vehicle. Raw inserts, guarded by schema presence.
     */
    private function copyMotorSpecifiedItems(int $srcMotorId, int $srcPcId, int $newMotorId, int $tgtPcId): void
    {
        if (!Schema::hasTable('policy_specified_items')) return;
        if (!Schema::hasColumn('policy_specified_items', 'motor_id')) return;

        $items = DB::table('policy_specified_items')
            ->where('policy_coverage_id', $srcPcId)
            ->where('motor_id', $srcMotorId);
        if (Schema::hasColumn('policy_specified_items', 'deleted_at')) {
            $items->whereNull('deleted_at');
        }
        foreach ($items->get() as $it) {
            $row = (array) $it;
            unset($row['id']);
            $row['policy_coverage_id'] = $tgtPcId;
            $row['motor_id'] = $newMotorId;
            if (Schema::hasColumn('policy_specified_items', 'created_at')) $row['created_at'] = now();
            if (Schema::hasColumn('policy_specified_items', 'updated_at')) $row['updated_at'] = now();
            try {
                DB::table('policy_specified_items')->insert($row);
            } catch (\Throwable $e) {
                Log::warning('BackdatedEndorseRefresher: specified-item clone skipped for motor ' . $srcMotorId . ': ' . $e->getMessage());
            }
        }
    }

    // ── Issue-path bridge (single canonical billing engine) ───────────────

    /**
     * Idempotent single-target RENEW / ANNIVERSARY-RENEW billing reconciliation.
     *
     * Used by PolicyCreateController::issuePolicy so the ENDORSE-issue forward
     * propagation routes its downstream RENEW / anniversary billing through THIS
     * engine — an IN-PLACE ledger update + a ledger_adjustments audit row with
     * the invoice number PRESERVED — instead of the legacy silent
     * "soft-delete the invoice + regenerate" that wrote no audit and, before the
     * transaction_date anchoring fix, over-charged in prod.
     *
     * Idempotent because it is delta-based: if refresh() already repriced this
     * target (or the tree is unchanged) the recompute yields a zero delta and no
     * ledger row moves — so calling it after refresh() never double-processes.
     *
     * Returns the absolute premium delta applied (0.0 when nothing changed).
     */
    public function reconcileRenewTarget(PolicyAction $source, PolicyAction $target): float
    {
        if (!in_array($target->transaction_type, self::RENEW_TX_TYPES, true)) {
            return 0.0;
        }
        $report = new RefreshReport();
        // Unique per invocation so the audit-log row never collides on the
        // (source, target, hash) unique key; BILLING idempotency is guaranteed
        // separately by the zero-delta short-circuit inside the recompute.
        $hash = 'ISSUE_FWD_RECONCILE:' . $source->id . ':' . $target->id . ':' . microtime(true);
        return $this->recomputeRenewAndAdjustLedger($source, $target, $hash, $report);
    }

    // ── External recompute seams ──────────────────────────────────────────
    //
    // The renewal / annual premium recompute funnels through the canonical
    // PolicyCreateController::recomputeActionTotals recipe (via
    // PolicyAction::calculatePremiumRenew). These two thin wrappers isolate that
    // external dependency so the propagation logic above can be exercised in a
    // hermetic test (a test double overrides them with a deterministic sum)
    // without booting the full controller / schema. Production behaviour is
    // unchanged — the wrappers call exactly what the inline code called before.

    protected function runRenewPremiumRecompute(PolicyAction $target): void
    {
        PolicyAction::calculatePremiumRenew($target->id, $target->term_id, $target->policy_id);
    }

    protected function runEndorseAnnualRecompute(PolicyAction $target): void
    {
        $controllerClass = '\\AlphaDirect\\Http\\Controllers\\Api\\V1\\PolicyCreateController';
        $reflMethod = new \ReflectionMethod($controllerClass, 'recomputeActionTotals');
        $reflMethod->setAccessible(true);
        $reflMethod->invoke(null, (int) $target->policy_id, (int) $target->id);
    }

    /**
     * For future ENDORSE targets: recompute policy_actions.annual_premium
     * from the now-updated child rows (so V2 Quote / Policy Doc Total Premium
     * reflects the backdated change), while leaving policy_actions.premium
     * (pro-rata) UNCHANGED — the pro-rata was the delta at the original
     * issue time and must stay sealed.
     */
    private function recomputeAnnualOnlyForEndorse(PolicyAction $backdated, PolicyAction $target, string $hash, RefreshReport $report): void
    {
        $oldPremium       = (float) ($target->premium ?? 0);
        $oldAnnualPremium = (float) ($target->annual_premium ?? 0);

        try {
            // Delegate to the existing canonical recipe so we don't drift from
            // calculatePremium's bucket scope. recomputeActionTotals writes
            // BOTH annual_premium AND premium — we save+restore premium so
            // only annual_premium is effectively changed. (Isolated behind a
            // seam so tests can substitute a deterministic recompute.)
            $this->runEndorseAnnualRecompute($target);
        } catch (\Throwable $e) {
            $report->pushError('recomputeActionTotals failed for ENDORSE ' . $target->id . ': ' . $e->getMessage());
            return;
        }

        // Restore the pro-rata premium (sealed value). recomputeActionTotals
        // rewrote it based on current row state which we explicitly do NOT
        // want for already-issued ENDORSE downstream.
        DB::table('policy_actions')
            ->where('id', $target->id)
            ->update([
                'premium' => $oldPremium,
                'updated_at' => now(),
            ]);

        $target->refresh();
        $newAnnualPremium = (float) ($target->annual_premium ?? 0);

        $this->insertRefreshLog(
            sourceId: (int) $backdated->id,
            targetId: (int) $target->id,
            targetType: 'ENDORSE',
            table: 'policy_actions',
            businessKey: ['action_id' => $target->id],
            fieldsUpdated: [
                'annual_premium' => ['old' => $oldAnnualPremium, 'new' => $newAnnualPremium],
                'premium'        => ['old' => $oldPremium, 'new' => $oldPremium, 'note' => 'pro-rata sealed; not changed'],
            ],
            fieldsSkipped: [],
            premiumRecalculated: false,  // pro-rata not recalculated
            premiumDelta: null,
            hash: $hash
        );

        $report->pushDecision([
            'phase' => 'recompute_annual',
            'target_action_id' => $target->id,
            'target_transaction_type' => 'ENDORSE',
            'annual_premium_old' => $oldAnnualPremium,
            'annual_premium_new' => $newAnnualPremium,
            'pro_rata_preserved' => $oldPremium,
        ]);
    }

    private function findTargetRow(string $table, int $tgtPcId, array $businessKey)
    {
        $q = DB::table($table)->where('policy_coverage_id', $tgtPcId);
        if (Schema::hasColumn($table, 'deleted_at')) {
            $q->whereNull('deleted_at');
        }
        foreach ($businessKey as $col => $val) {
            if ($val === null || $val === '') {
                // motor_id can legitimately be null for non-vehicle specified items
                if ($col === 'motor_id') {
                    $q->where(function ($qq) use ($col) {
                        $qq->whereNull($col)->orWhere($col, 0);
                    });
                    continue;
                }
            }
            $q->where($col, $val);
        }
        return $q->first();
    }

    /**
     * Get the row immediately before this target action via previousActionIdCov
     * chain so we can compare per-field for edit-protection. Returns an
     * associative array of column => value, or [] if no prior row exists.
     */
    private function prevValuesForTargetRow(string $table, $tgtRow, PolicyAction $target): array
    {
        if (!Schema::hasColumn($table, 'previousActionIdCov')) return [];
        $prevActionId = $tgtRow->previousActionIdCov ?? null;
        if (!$prevActionId || (int) $prevActionId === (int) $target->id) {
            // No prior action chained — the row is new in this target.
            return [];
        }
        // Walk the action_id back via policy_coverages.action_id chain to find
        // the prior row matching this row's business key. Simplest: locate
        // any row in the same physical chain with the prior action.
        // For now, return an empty map and let isFieldEditedByTarget treat
        // "NULL prev value" + "endors_flag=1 in target" as edited.
        return [];
    }

    /**
     * Universal edit-protection: a field is "edited by target batch" iff:
     *   target row's endors_flag = 1
     *   AND target value differs from the prior row's value (via previousActionIdCov chain)
     * OR equivalently — and what we use in practice — the target row was
     * stamped by the wizard during the target action itself:
     *   tgtRow.previousActionIdCov = target.id  AND  tgtRow.endors_flag = 1
     * In that case any value we'd overwrite represents an explicit operator
     * decision and must be preserved.
     */
    private function isFieldEditedByTarget($tgtRow, string $col, array $prevValuesInTgt, PolicyAction $target): bool
    {
        $endorsFlag = (int) ($tgtRow->endors_flag ?? 0);
        $prevActId  = (int) ($tgtRow->previousActionIdCov ?? 0);
        if ($endorsFlag !== 1) return false;
        if ($prevActId !== (int) $target->id) return false;
        // Row was wizard-touched during the target action. If prev value
        // map is populated, compare per-field; otherwise the safer default
        // is "edited" (protect everything stamped by the target wizard).
        if (empty($prevValuesInTgt)) return true;
        $prev = $prevValuesInTgt[$col] ?? null;
        $now = $tgtRow->{$col} ?? null;
        return (string) $prev !== (string) $now;
    }

    // ── RENEW recalc + ledger adjustment ──────────────────────────────────

    /**
     * For ISSUED RENEW targets: recompute the renewal premium using the
     * existing PolicyAction::calculatePremiumRenew pipeline, then UPDATE
     * existing ledger / sub_ledger rows in place (NEVER delete or change
     * invoice number) and INSERT a ledger_adjustments audit row.
     *
     * Returns the absolute delta applied (used for report total).
     */
    private function recomputeRenewAndAdjustLedger(PolicyAction $backdated, PolicyAction $target, string $hash, RefreshReport $report): float
    {
        $oldPremium = (float) ($target->premium ?? 0);

        // Re-run the canonical renewal premium calc against the now-refreshed
        // row state. This MUST NOT touch the invoice or ledger directly —
        // it writes the new premium to policy_actions. (Isolated behind a seam
        // so tests can substitute a deterministic recompute.)
        try {
            $this->runRenewPremiumRecompute($target);
        } catch (\Throwable $e) {
            $report->pushError('calculatePremiumRenew failed for action ' . $target->id . ': ' . $e->getMessage());
            return 0.0;
        }

        $target->refresh();
        $newPremium = (float) ($target->premium ?? 0);
        $delta = round($newPremium - $oldPremium, 2);

        if (abs($delta) < 0.01) {
            // Zero delta — nothing to adjust on the ledger. Still log the
            // recompute happened so the audit trail is complete.
            $this->insertRefreshLog(
                sourceId: (int) $backdated->id,
                targetId: (int) $target->id,
                targetType: (string) $target->transaction_type,
                table: 'policy_actions',
                businessKey: ['action_id' => $target->id],
                fieldsUpdated: ['premium' => ['old' => $oldPremium, 'new' => $newPremium]],
                fieldsSkipped: [],
                premiumRecalculated: true,
                premiumDelta: 0.0,
                hash: $hash
            );
            return 0.0;
        }

        // ── Money columns, as the GL actually stores them ──────────────────
        //
        // This block used to read `$lr->amount` and write `update(['amount' =>
        // …])`. NEITHER policy_ledger NOR policy_subledger has an `amount`
        // column, so that UPDATE threw "Unknown column 'amount'" on EVERY run.
        // The throw was caught by the per-target catch in refresh() and filed as
        // a report error string, so the endorse-issue run still reported success
        // — meaning the premium recompute above has been landing all along while
        // the billed figure silently never moved, and ledger_adjustments (which
        // is inserted below the failing UPDATE) stayed empty in prod. That is the
        // "rate updates but invoice doesn't" gap.
        //
        // What the invoice writers actually create (Helper::generateInvoiceDomComIssued):
        //   policy_ledger    — ONE trans_type='Invoice' row holding the figure in
        //                      FOUR columns: invoice_amount, premium, due_amount,
        //                      debit. ('Invoice VAT' / 'Invoice Premium' are not
        //                      produced by this path; kept in the filter so any
        //                      legacy rows are still picked up.)
        //   policy_subledger — the GL legs, money in credit / debit (never
        //                      `amount`), linked by policy_id + action_id. There
        //                      is no `ledger_id` column, so the old cascade could
        //                      not have matched a row even without the throw.
        // Delegated to the SINGLE in-place invoice writer shared with the Refresh
        // Endorsement path (InvoiceAmountSync), so the two propagation paths can
        // no longer drift. It updates the real money columns in place, is scoped
        // by policy_id + action_id, and discards nothing — see that class for the
        // full column map and why one ratio is used for every leg.
        $sync = InvoiceAmountSync::syncToPremium((int) $target->policy_id, (int) $target->id, $newPremium);

        if (!$sync['ok'] && $sync['reason'] === 'no_base') {
            // No billed base to scale from, so the VAT split cannot be inferred.
            // Do NOT guess at the GL — surface it for a manual credit note.
            $report->pushError(
                'Ledger not adjusted for action ' . $target->id . ': the existing invoice totals '
                . $sync['old_total'] . ', cannot rescale the legs safely. Premium was recomputed to '
                . $newPremium . ' — the invoice needs a manual credit note.'
            );
            return $delta;
        }

        // One audit row per ledger row and per GL leg, exactly as before.
        foreach ($sync['ledger'] as $row) {
            DB::table('ledger_adjustments')->insert([
                'source_action_id' => $backdated->id,
                'target_action_id' => $target->id,
                'target_ledger_id' => $row['id'],
                'target_sub_ledger_id' => null,
                'old_amount' => $row['old'],
                'new_amount' => $row['new'],
                'delta_amount' => round($row['new'] - $row['old'], 2),
                'reason' => 'BACKDATED_REFRESH',
                'applied_by_user_id' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $report->ledgerAdjustmentsWritten++;
        }

        foreach ($sync['legs'] as $leg) {
            DB::table('ledger_adjustments')->insert([
                'source_action_id' => $backdated->id,
                'target_action_id' => $target->id,
                'target_ledger_id' => $sync['primary_ledger_id'],
                'target_sub_ledger_id' => $leg['id'],
                'old_amount' => $leg['old'],
                'new_amount' => $leg['new'],
                'delta_amount' => round($leg['new'] - $leg['old'], 2),
                'reason' => 'BACKDATED_REFRESH',
                'applied_by_user_id' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $report->ledgerAdjustmentsWritten++;
        }

        $this->insertRefreshLog(
            sourceId: (int) $backdated->id,
            targetId: (int) $target->id,
            targetType: (string) $target->transaction_type,
            table: 'policy_actions',
            businessKey: ['action_id' => $target->id],
            fieldsUpdated: ['premium' => ['old' => $oldPremium, 'new' => $newPremium]],
            fieldsSkipped: [],
            premiumRecalculated: true,
            premiumDelta: $delta,
            hash: $hash
        );

        return $delta;
    }

    // ── Logging ───────────────────────────────────────────────────────────

    private function alreadyApplied(int $sourceId, int $targetId, string $hash): bool
    {
        if (!Schema::hasTable('backdated_endorse_refresh_log')) return false;
        return DB::table('backdated_endorse_refresh_log')
            ->where('source_action_id', $sourceId)
            ->where('target_action_id', $targetId)
            ->where('idempotency_hash', $hash)
            ->exists();
    }

    private function insertRefreshLog(
        int $sourceId,
        int $targetId,
        string $targetType,
        string $table,
        array $businessKey,
        array $fieldsUpdated,
        array $fieldsSkipped,
        bool $premiumRecalculated,
        ?float $premiumDelta,
        string $hash
    ): void {
        if (!Schema::hasTable('backdated_endorse_refresh_log')) return;
        // insertOrIgnore, NOT insert: the table has a UNIQUE key on
        // (source_action_id, target_action_id, idempotency_hash) which is the
        // EC-8 idempotency MARKER (see alreadyApplied). A single applyToTarget
        // legitimately logs several rows under that one key (a per-row change +
        // the premium recompute), so a plain insert throws a duplicate-key on
        // the second row and aborts the whole target mid-way — leaving the
        // ledger updated but the audit half-written. Ignoring the duplicate
        // keeps the idempotency marker and never derails the billing flow; the
        // authoritative billing audit lives in ledger_adjustments regardless.
        DB::table('backdated_endorse_refresh_log')->insertOrIgnore([
            'source_action_id' => $sourceId,
            'target_action_id' => $targetId,
            'target_transaction_type' => $targetType,
            'table_name' => $table,
            'row_business_key' => json_encode($businessKey),
            'fields_updated' => json_encode($fieldsUpdated),
            'fields_skipped_protected' => json_encode($fieldsSkipped),
            'premium_recalculated' => $premiumRecalculated,
            'premium_delta' => $premiumDelta,
            'idempotency_hash' => $hash,
            'applied_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function idempotencyHash(array $changeSet, array $noteCarry = []): string
    {
        $signature = [];
        foreach ($changeSet as $row) {
            $signature[] = [
                'table' => $row['table'],
                'pc_id' => $row['pc_id'],
                'op' => $row['op'] ?? 'upsert',
                'bk' => $row['business_key'],
                'values' => $row['values'],
            ];
        }
        // Fold the note carry set in so a note-only edit produces a distinct
        // hash (otherwise an empty change set hashes to a constant and the
        // EC-8 idempotency guard would skip every note refresh after the first).
        foreach ($noteCarry as $note) {
            $signature[] = [
                'table' => 'policy_coverage_notes',
                'pc_id' => $note['pc_id'],
                'bk' => ['registration_no' => $note['registration_no']],
                'values' => ['note' => $note['note']],
            ];
        }
        usort($signature, function ($a, $b) {
            return strcmp(json_encode($a), json_encode($b));
        });
        return hash('sha256', json_encode($signature));
    }

    /**
     * Per-vehicle motor notes on the backdated endorse, keyed by registration
     * (motor ids differ per action). Used both to keep a note-only refresh
     * alive past the empty-change-set guard and to vary the idempotency hash
     * by note content.
     */
    private function buildNoteCarrySet(PolicyAction $backdated): array
    {
        $set = [];
        $pcIds = DB::table('policy_coverages')
            ->where('policy_id', $backdated->policy_id)
            ->where('action_id', $backdated->id)
            ->whereNull('deleted_at')
            ->pluck('id');
        if ($pcIds->isEmpty()) return $set;

        $notes = DB::table('policy_coverage_notes')
            ->whereIn('policy_coverage_id', $pcIds)
            ->where('motor_id', '>', 0)
            ->whereNull('deleted_at')
            ->get(['policy_coverage_id', 'motor_id', 'note']);

        foreach ($notes as $n) {
            $reg = DB::table('motor')->where('id', $n->motor_id)->value('registration_no');
            $reg = trim((string) ($reg ?? ''));
            if ($reg === '') continue;
            $set[] = [
                'pc_id' => (int) $n->policy_coverage_id,
                'registration_no' => $reg,
                'note' => (string) ($n->note ?? ''),
            ];
        }
        return $set;
    }

    /**
     * Carry per-vehicle motor notes (policy_coverage_notes.motor_id > 0) from
     * the backdated ENDORSE into a target batch, the same way the other child
     * tables propagate. Mapped by registration_no because motor ids differ
     * per action. Cancelled (soft-deleted) target coverages / motors are
     * skipped (EC-5). Idempotent: a target note already equal to the source
     * is left untouched.
     */
    private function propagateMotorNotes(
        PolicyAction $backdated,
        PolicyAction $target,
        array $tgtPcByKey,
        bool $dryRun,
        string $hash,
        RefreshReport $report
    ): void {
        $srcPcs = DB::table('policy_coverages as pc')
            ->leftJoin('risk_address as ra', 'ra.id', '=', 'pc.risk_address_id')
            ->where('pc.policy_id', $backdated->policy_id)
            ->where('pc.action_id', $backdated->id)
            ->whereNull('pc.deleted_at')
            ->get(['pc.id', 'pc.coverage_id', 'ra.address_name']);

        foreach ($srcPcs as $spc) {
            $key = $spc->coverage_id . '|' . trim((string) ($spc->address_name ?? ''));
            $tgtPc = $tgtPcByKey[$key] ?? null;
            if (!$tgtPc || !empty($tgtPc->deleted_at)) {
                continue;
            }

            $srcNotes = DB::table('policy_coverage_notes')
                ->where('policy_coverage_id', $spc->id)
                ->where('motor_id', '>', 0)
                ->whereNull('deleted_at')
                ->get();
            if ($srcNotes->isEmpty()) {
                continue;
            }

            $hasBenefits = Schema::hasColumn('policy_coverage_notes', 'benefits_note');

            foreach ($srcNotes as $sn) {
                $srcMotor = DB::table('motor')->where('id', $sn->motor_id)->first(['registration_no']);
                $reg = $srcMotor ? trim((string) ($srcMotor->registration_no ?? '')) : '';
                if ($reg === '') {
                    continue;
                }

                // Target motor by registration (live rows only — a cancelled
                // vehicle's note must not be revived).
                $tgtMotor = DB::table('motor')
                    ->where('policy_coverage_id', $tgtPc->id)
                    ->where('registration_no', $reg)
                    ->whereNull('deleted_at')
                    ->first(['id']);
                if (!$tgtMotor) {
                    continue;
                }

                $tgtNote = DB::table('policy_coverage_notes')
                    ->where('policy_coverage_id', $tgtPc->id)
                    ->where('motor_id', $tgtMotor->id)
                    ->whereNull('deleted_at')
                    ->first();

                $newNote     = $sn->note;
                $newBenefits = $hasBenefits && property_exists($sn, 'benefits_note') ? $sn->benefits_note : null;

                if ($tgtNote) {
                    $changed = ((string) ($tgtNote->note ?? '') !== (string) ($newNote ?? ''))
                        || ($hasBenefits && (string) ($tgtNote->benefits_note ?? '') !== (string) ($newBenefits ?? ''));
                    if (!$changed) {
                        continue;
                    }
                    $fieldsUpdated = ['note' => ['old' => $tgtNote->note ?? null, 'new' => $newNote]];
                    if ($hasBenefits) {
                        $fieldsUpdated['benefits_note'] = ['old' => $tgtNote->benefits_note ?? null, 'new' => $newBenefits];
                    }
                    if (!$dryRun) {
                        $upd = ['note' => $newNote, 'updated_at' => now()];
                        if ($hasBenefits) {
                            $upd['benefits_note'] = $newBenefits;
                        }
                        DB::table('policy_coverage_notes')->where('id', $tgtNote->id)->update($upd);
                    }
                } else {
                    $fieldsUpdated = ['note' => ['old' => null, 'new' => $newNote]];
                    if (!$dryRun) {
                        $insert = [
                            'policy_coverage_id' => $tgtPc->id,
                            'motor_id'           => $tgtMotor->id,
                            'note'               => $newNote,
                            'created_at'         => now(),
                            'updated_at'         => now(),
                        ];
                        if ($hasBenefits) {
                            $insert['benefits_note'] = $newBenefits;
                        }
                        DB::table('policy_coverage_notes')->insert($insert);
                    }
                }

                $report->fieldsUpdated += count($fieldsUpdated);
                $report->pushDecision([
                    'phase' => 'apply_notes',
                    'target_action_id' => $target->id,
                    'target_transaction_type' => $target->transaction_type,
                    'table' => 'policy_coverage_notes',
                    'target_pc_id' => $tgtPc->id,
                    'registration_no' => $reg,
                    'fields_updated' => $fieldsUpdated,
                ]);

                if (!$dryRun) {
                    $this->insertRefreshLog(
                        sourceId: (int) $backdated->id,
                        targetId: (int) $target->id,
                        targetType: (string) $target->transaction_type,
                        table: 'policy_coverage_notes',
                        businessKey: ['registration_no' => $reg],
                        fieldsUpdated: $fieldsUpdated,
                        fieldsSkipped: [],
                        premiumRecalculated: false,
                        premiumDelta: null,
                        hash: $hash
                    );
                }
            }
        }
    }
}
