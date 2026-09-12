<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Services\SpecialistEndorse\SpecialistCoverageRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * ONE-TIME repair: restore a coverage tree wiped by a Refresh/rebuild sweep.
 *
 * WHAT HAPPENED (policy COMG2024101723 / 101723)
 * A Refresh ran 2026-05-11 11:13:58–11:14:19 and soft-deleted the children of
 * ANNIVERSARY-RENEW 14619 and RENEW 36607, then wrote a single vehicle back.
 * The policy_coverages headers survived, so both actions still render every
 * section — with no values. Verified to have hit ONLY this policy: no other
 * policy has policy_coverage_detail rows deleted inside that window.
 *
 * WHAT THIS RESTORES
 * Soft-deleted children whose deleted_at falls INSIDE the sweep window, for the
 * named actions only:
 *
 *      14619 -> motor 27 | detail 40 | extensions 47 | specified 6
 *      36607 -> motor 27 | detail  0 | extensions  0 | specified 6
 *
 * WHAT IT DELIBERATELY LEAVES DELETED
 * Anything deleted OUTSIDE the window is a genuine operator decision and stays
 * deleted — a vehicle cancelled 2025-07-09 10:03:52 and six specified items
 * removed 2026-02-02. Restoring those would put cover back on risks that were
 * deliberately removed. That is why every query is scoped by BOTH action_id and
 * the timestamp window, never by action_id alone.
 *
 * WHAT IT DOES NOT DO
 *  1. It does not set policy_actions.premium. The premium is not derivable in
 *     SQL: on three actions with identical extension sets, line-sum minus stored
 *     premium gives +1,489.32 / +217.00 / -14.00. Recompute via Rate afterwards.
 *  2. It does not rebuild 36607's detail/extension rows — that action has ZERO
 *     rows in those tables in any state, so there is nothing to un-delete. Use
 *     Refresh -> "Fill missing (keep edits)" with source 14619 after this runs.
 *
 * PREREQUISITE
 * Motor has SoftDeletes commented out (app/Models/Motor.php), so replication
 * copies deleted_at forward and renewals inherit their parent's tombstones.
 * Until that is fixed, the next Refresh re-breaks these actions.
 *
 * SAFE BY DEFAULT: dry-run unless --apply is passed. Every write is wrapped in
 * one transaction and rolled back automatically if any table's affected-row
 * count differs from what the preview measured.
 *
 *   php artisan policy:restore-sweep-damage --policy=COMG... --detect
 *                                                          # read-only scan:
 *                                                          # which actions were
 *                                                          # wiped, in what
 *                                                          # window
 *   php artisan policy:restore-sweep-damage                 # preview
 *   php artisan policy:restore-sweep-damage --apply         # perform restore
 *   php artisan policy:restore-sweep-damage --actions=14619 --apply
 */
class RestoreSweepDamage extends Command
{
    protected $signature = 'policy:restore-sweep-damage
        {--policy=101723 : Policy id (or policy number, e.g. COMG2024117100) being repaired}
        {--actions=14619,36607 : Comma-separated action ids to restore}
        {--from= : Sweep window start (default 2026-05-11 11:13:00)}
        {--to= : Sweep window end (default 2026-05-11 11:15:00)}
        {--reference=40747 : Healthy action to compare the result against}
        {--detect : Read-only scan: find wiped actions and propose the window. No --actions needed}
        {--remove-motors= : Comma-separated motor row ids to soft-delete - phantom vehicles the sweep wrote in. Dry-run unless --apply}
        {--apply : Actually write. Omit for a dry-run preview}';

    protected $description = 'Restore coverage children soft-deleted by the 2026-05-11 Refresh sweep (dry-run unless --apply)';

    /** child table => [fk column, value column or null] */
    private const CHILD_TABLES = [
        'motor'                   => ['policy_coverage_id', 'calculated_value'],
        'policy_coverage_detail'  => ['policy_coverage_id', 'calculated_value'],
        'policy_extention_detail' => ['policy_coverage_id', 'extention_calculated_value'],
        'policy_specified_items'  => ['policy_coverage_id', 'calculated_value'],
    ];

    /**
     * Rows in a single deleted_at burst at or above which the deletion reads as
     * a machine sweep rather than an operator cancellation. Shared by the
     * DELETE CLUSTERS label and the per-action WIPED flag so the two agree.
     */
    private const BURST_ROWS = 20;

    /** Memoised result of childTables(). */
    private ?array $childTablesCache = null;

    /**
     * Combined motor/COM-DOM (const) + specialist (registry) child-tables map.
     *
     * The four base entries stay in self::CHILD_TABLES so the existing motor /
     * COM-DOM behaviour is byte-for-byte identical. Specialist entries come
     * from SpecialistCoverageRegistry, which is the single source of truth for
     * those 14 tables — same splice pattern as
     * BackdatedEndorseRefresher::childTablesMap(), so the two lists can never
     * drift apart.
     *
     * Without this, a sweep that tombstoned a CAR / PAR / EAR / liability
     * coverage row was invisible to both --detect and the restore: the scan
     * reported "no soft-deleted coverage children" while the specialist rows
     * sat there deleted, and a restore silently skipped them.
     *
     * A specialist table is merged ONLY when it really carries the two columns
     * this command reads (policy_coverage_id, deleted_at). The value column is
     * the registry's first premium column, used purely for the previewed sum,
     * and is dropped to null when absent so the display degrades instead of
     * throwing on an env where the schema differs.
     */
    private function childTables(): array
    {
        if ($this->childTablesCache !== null) {
            return $this->childTablesCache;
        }

        $map = self::CHILD_TABLES;
        foreach (SpecialistCoverageRegistry::TABLES as $table => $meta) {
            if (!Schema::hasTable($table)) continue;
            if (!Schema::hasColumn($table, 'policy_coverage_id')) continue;
            // No deleted_at means the table cannot be soft-deleted, so it can
            // never be sweep damage and every query below would throw.
            if (!Schema::hasColumn($table, 'deleted_at')) continue;

            $valueCol = null;
            foreach (($meta['premium_cols'] ?? []) as $col) {
                if (Schema::hasColumn($table, $col)) { $valueCol = $col; break; }
            }
            $map[$table] = ['policy_coverage_id', $valueCol];
        }

        return $this->childTablesCache = $map;
    }

    public function handle()
    {
        $policyId  = $this->resolvePolicyId((string) $this->option('policy'));
        if ($policyId === null) {
            $this->error('Policy not found: ' . $this->option('policy'));
            return 1;
        }
        $actionIds = array_values(array_filter(array_map('intval', explode(',', (string) $this->option('actions')))));
        $from      = $this->option('from') ?: '2026-05-11 11:13:00';
        $to        = $this->option('to')   ?: '2026-05-11 11:15:00';
        $reference = (int) $this->option('reference');
        $apply     = (bool) $this->option('apply');

        // --detect never writes and never needs --actions: it is the step that
        // works out WHICH actions were wiped and in WHICH window, for a policy
        // the hardcoded defaults know nothing about.
        if ($this->option('detect')) {
            return $this->detect($policyId);
        }

        // Removal is the mirror of the restore: the same sweep that tombstoned
        // the real fleet wrote a couple of vehicles back onto actions that
        // predate them. Those rows carry the sweep's own created_at. Named by
        // id only - never by a pattern - so this can never over-delete.
        if ($this->option('remove-motors')) {
            return $this->removeMotors($policyId, (string) $this->option('remove-motors'), $apply);
        }

        if (empty($actionIds)) {
            $this->error('No --actions given.');
            return 1;
        }

        $this->line('');
        $this->info('Restore sweep damage');
        $this->line("  policy   : {$policyId}");
        $this->line('  actions  : ' . implode(', ', $actionIds));
        $this->line("  window   : {$from}  ..  {$to}");
        $this->line('  mode     : ' . ($apply ? 'APPLY (writes)' : 'DRY-RUN (no writes)'));
        $this->line('');

        // Guard: the named actions must belong to the named policy. Prevents a
        // mistyped action id from restoring rows on someone else's policy.
        $foreign = DB::table('policy_actions')
            ->whereIn('id', $actionIds)
            ->where('policy_id', '!=', $policyId)
            ->pluck('id')->all();
        if (!empty($foreign)) {
            $this->error('These actions do not belong to policy ' . $policyId . ': ' . implode(', ', $foreign));
            return 1;
        }
        $missing = array_diff($actionIds, DB::table('policy_actions')->whereIn('id', $actionIds)->pluck('id')->all());
        if (!empty($missing)) {
            $this->error('Action(s) not found: ' . implode(', ', $missing));
            return 1;
        }

        // ---------------------------------------------------------------
        // 1. Measure what will be restored, per action per table.
        // ---------------------------------------------------------------
        // Resolved ONCE: the base four plus every specialist table that really
        // carries policy_coverage_id + deleted_at (see childTables()).
        $childTables = $this->childTables();
        $plan = [];
        $rows = [];
        foreach ($actionIds as $actionId) {
            $coverageIds = DB::table('policy_coverages')->where('action_id', $actionId)->pluck('id')->all();
            $plan[$actionId]['coverage_ids'] = $coverageIds;

            foreach ($childTables as $table => [$fk, $valueCol]) {
                $q = DB::table($table)->whereIn($fk, $coverageIds ?: [0])
                    ->whereBetween('deleted_at', [$from, $to]);
                $count = (clone $q)->count();
                $value = $valueCol ? (float) (clone $q)->sum($valueCol) : 0.0;
                $plan[$actionId]['tables'][$table] = ['count' => $count, 'value' => $value];
            }

            // Coverage headers themselves, if the sweep took any.
            $headerCount = DB::table('policy_coverages')->where('action_id', $actionId)
                ->whereBetween('deleted_at', [$from, $to])->count();
            $plan[$actionId]['headers'] = $headerCount;

            foreach ($plan[$actionId]['tables'] as $table => $m) {
                $rows[] = [$actionId, $table, $m['count'], number_format($m['value'], 2)];
            }
            $rows[] = [$actionId, 'policy_coverages (headers)', $headerCount, '-'];
        }

        $this->line('TO BE RESTORED (deleted_at inside the sweep window):');
        $this->table(['action', 'table', 'rows', 'value'], $rows);

        // ---------------------------------------------------------------
        // 2. Show what will deliberately stay deleted.
        // ---------------------------------------------------------------
        $preserved = [];
        foreach ($actionIds as $actionId) {
            $coverageIds = $plan[$actionId]['coverage_ids'];
            foreach ($childTables as $table => [$fk, $valueCol]) {
                $keep = DB::table($table)->whereIn($fk, $coverageIds ?: [0])
                    ->whereNotNull('deleted_at')
                    ->where(function ($q) use ($from, $to) {
                        $q->where('deleted_at', '<', $from)->orWhere('deleted_at', '>', $to);
                    })
                    ->selectRaw('deleted_at, COUNT(*) as n')
                    ->groupBy('deleted_at')->get();
                foreach ($keep as $k) {
                    $preserved[] = [$actionId, $table, $k->deleted_at, $k->n];
                }
            }
        }
        if (!empty($preserved)) {
            $this->line('');
            $this->line('STAYING DELETED (operator decisions, outside the window):');
            $this->table(['action', 'table', 'deleted_at', 'rows'], $preserved);
        }

        $totalRows = 0;
        foreach ($plan as $p) {
            foreach ($p['tables'] as $m) { $totalRows += $m['count']; }
            $totalRows += $p['headers'];
        }

        if ($totalRows === 0) {
            $this->line('');
            $this->warn('Nothing to restore in that window. Already repaired, or the window is wrong.');
            return 0;
        }

        if (!$apply) {
            $this->line('');
            $this->warn("DRY-RUN. {$totalRows} rows would be restored. Re-run with --apply to write.");
            $this->showPremiumStanding($policyId, $actionIds, $reference);
            return 0;
        }

        // ---------------------------------------------------------------
        // 3. Backup, then restore inside one transaction.
        // ---------------------------------------------------------------
        $stamp  = $this->option('from') ? md5($from . $to) : 'sweep';
        $backup = ['policy_id' => $policyId, 'actions' => $actionIds, 'window' => [$from, $to], 'rows' => []];
        foreach ($actionIds as $actionId) {
            $coverageIds = $plan[$actionId]['coverage_ids'];
            foreach ($childTables as $table => [$fk, $valueCol]) {
                $backup['rows'][$actionId][$table] = DB::table($table)
                    ->whereIn($fk, $coverageIds ?: [0])
                    ->whereBetween('deleted_at', [$from, $to])
                    ->get()->toArray();
            }
        }
        // 'local' disk explicitly — the default disk points at a dead bucket.
        $backupPath = "restore-sweep/policy-{$policyId}-{$stamp}.json";
        Storage::disk('local')->put($backupPath, json_encode($backup, JSON_PRETTY_PRINT));
        $this->line('');
        $this->info('Backup written: storage/app/' . $backupPath);

        $applied = [];
        try {
            DB::transaction(function () use ($actionIds, $plan, $from, $to, $childTables, &$applied) {
                foreach ($actionIds as $actionId) {
                    $coverageIds = $plan[$actionId]['coverage_ids'];

                    foreach ($childTables as $table => [$fk, $valueCol]) {
                        $expected = $plan[$actionId]['tables'][$table]['count'];
                        if ($expected === 0) { continue; }

                        $affected = DB::table($table)
                            ->whereIn($fk, $coverageIds ?: [0])
                            ->whereBetween('deleted_at', [$from, $to])
                            ->update(['deleted_at' => null]);

                        if ($affected !== $expected) {
                            throw new \RuntimeException(
                                "Row-count mismatch on {$table} for action {$actionId}: expected {$expected}, updated {$affected}. Rolled back."
                            );
                        }
                        $applied[] = [$actionId, $table, $affected];
                    }

                    if ($plan[$actionId]['headers'] > 0) {
                        $affected = DB::table('policy_coverages')->where('action_id', $actionId)
                            ->whereBetween('deleted_at', [$from, $to])
                            ->update(['deleted_at' => null]);
                        $applied[] = [$actionId, 'policy_coverages', $affected];
                    }
                }
            });
        } catch (\Throwable $e) {
            $this->error('RESTORE FAILED — transaction rolled back.');
            $this->error($e->getMessage());
            Log::error('policy:restore-sweep-damage failed', [
                'policy_id' => $policyId, 'actions' => $actionIds, 'error' => $e->getMessage(),
            ]);
            return 1;
        }

        $this->line('');
        $this->info('RESTORED:');
        $this->table(['action', 'table', 'rows'], $applied);

        Log::info('policy:restore-sweep-damage applied', [
            'policy_id' => $policyId, 'actions' => $actionIds,
            'window' => [$from, $to], 'applied' => $applied, 'backup' => $backupPath,
        ]);

        // ---------------------------------------------------------------
        // 4. Verify against the healthy reference action.
        // ---------------------------------------------------------------
        $this->verify(array_merge($actionIds, [$reference]));
        $this->showPremiumStanding($policyId, $actionIds, $reference);

        $this->line('');
        $this->warn('STILL OUTSTANDING — this command does not do these:');
        $this->line('  1. 36607 has no detail/extension rows in any state. Rebuild via');
        $this->line('     Refresh -> "Fill missing (keep edits)", source 14619.');
        $this->line('  2. Premium is unchanged. Recompute via Rate — it is not derivable in SQL.');
        $this->line('  3. Motor SoftDeletes is still commented out; until fixed, the next');
        $this->line('     Refresh will re-propagate tombstones and undo this repair.');

        return 0;
    }

    /**
     * Soft-delete named motor rows that the sweep wrote in.
     *
     * Every row is verified to belong to the named policy before anything is
     * touched, the set is backed up to storage/app/restore-sweep/, and the
     * write is wrapped in a transaction that rolls back if the affected count
     * differs from the preview. Dry-run unless --apply.
     */
    private function removeMotors(int $policyId, string $csv, bool $apply): int
    {
        $ids = array_values(array_filter(array_map('intval', explode(',', $csv))));
        if (empty($ids)) {
            $this->error('No motor ids given to --remove-motors.');
            return 1;
        }

        $rows = DB::table('motor as m')
            ->join('policy_coverages as pc', 'pc.id', '=', 'm.policy_coverage_id')
            ->whereIn('m.id', $ids)
            ->get([
                'm.id', 'm.registration_no', 'm.vehicle_name', 'm.calculated_value',
                'm.deleted_at', 'm.created_at', 'pc.action_id', 'pc.policy_id',
            ]);

        $missing = array_diff($ids, $rows->pluck('id')->all());
        if (!empty($missing)) {
            $this->error('Motor row(s) not found: ' . implode(', ', $missing));
            return 1;
        }

        // Guard: a mistyped id must never soft-delete a vehicle on another policy.
        $foreign = $rows->where('policy_id', '!=', $policyId)->pluck('id')->all();
        if (!empty($foreign)) {
            $this->error('These motor rows do not belong to policy ' . $policyId . ': ' . implode(', ', $foreign));
            return 1;
        }

        $already = $rows->whereNotNull('deleted_at')->pluck('id')->all();
        if (!empty($already)) {
            $this->warn('Already deleted, will be skipped: ' . implode(', ', $already));
        }

        $target = $rows->whereNull('deleted_at');
        if ($target->isEmpty()) {
            $this->info('Nothing to remove - every named row is already deleted.');
            return 0;
        }

        $this->line('');
        $this->info('TO BE SOFT-DELETED:');
        $this->table(
            ['motor id', 'action', 'registration', 'vehicle', 'value', 'created_at'],
            $target->map(fn ($r) => [
                $r->id,
                $r->action_id,
                $r->registration_no,
                substr((string) $r->vehicle_name, 0, 28),
                number_format((float) $r->calculated_value, 2),
                $r->created_at,
            ])->all()
        );
        $this->line('  total value removed: ' . number_format((float) $target->sum('calculated_value'), 2));

        if (!$apply) {
            $this->line('');
            $this->warn('DRY-RUN. ' . $target->count() . ' row(s) would be soft-deleted. Re-run with --apply to write.');
            return 0;
        }

        $targetIds = $target->pluck('id')->all();
        $backupPath = 'restore-sweep/policy-' . $policyId . '-removed-' . md5(implode(',', $targetIds)) . '.json';
        Storage::disk('local')->put($backupPath, json_encode([
            'policy_id' => $policyId,
            'reason'    => 'phantom motor rows written by the Refresh sweep',
            'rows'      => DB::table('motor')->whereIn('id', $targetIds)->get()->toArray(),
        ], JSON_PRETTY_PRINT));
        $this->line('');
        $this->info('Backup written: storage/app/' . $backupPath);

        $stamp = date('Y-m-d H:i:s');
        try {
            DB::transaction(function () use ($targetIds, $stamp) {
                $affected = DB::table('motor')
                    ->whereIn('id', $targetIds)
                    ->whereNull('deleted_at')
                    ->update(['deleted_at' => $stamp]);

                if ($affected !== count($targetIds)) {
                    throw new \RuntimeException(
                        'Row-count mismatch: expected ' . count($targetIds) . ", soft-deleted {$affected}. Rolled back."
                    );
                }
            });
        } catch (\Throwable $e) {
            $this->error('REMOVE FAILED - transaction rolled back.');
            $this->error($e->getMessage());
            Log::error('policy:restore-sweep-damage remove-motors failed', [
                'policy_id' => $policyId, 'motor_ids' => $targetIds, 'error' => $e->getMessage(),
            ]);
            return 1;
        }

        $this->info('Soft-deleted ' . count($targetIds) . ' motor row(s) at ' . $stamp . '.');
        Log::info('policy:restore-sweep-damage remove-motors applied', [
            'policy_id' => $policyId, 'motor_ids' => $targetIds,
            'deleted_at' => $stamp, 'backup' => $backupPath,
        ]);

        $this->line('');
        $this->warn('To undo: restore the ids listed in the backup file with');
        $this->line('  policy:restore-sweep-damage --policy=' . $policyId
            . ' --actions=<action ids> --from="' . $stamp . '" --to="' . $stamp . '" --apply');

        return 0;
    }

    /** Accept either a policies.id or a policies.policyNumber. */
    private function resolvePolicyId(string $needle): ?int
    {
        $needle = trim($needle);
        if ($needle === '') { return null; }

        if (ctype_digit($needle)) {
            return DB::table('policies')->where('id', (int) $needle)->exists() ? (int) $needle : null;
        }
        $id = DB::table('policies')->where('policyNumber', $needle)->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Read-only damage scan for one policy.
     *
     * Prints, per action: live vs soft-deleted child rows, so an action whose
     * headers survived but whose children are all tombstoned (the sweep
     * signature - every section renders, no values) is obvious. Then clusters
     * every deleted_at on the policy into bursts, because a sweep deletes in
     * one burst while an operator cancellation is a lone timestamp.
     */
    private function detect(int $policyId): int
    {
        $policyNo = DB::table('policies')->where('id', $policyId)->value('policyNumber');
        $this->info("Sweep-damage scan - policy {$policyId} ({$policyNo})");
        $this->line('  read-only: nothing is written by --detect');
        $this->line('');

        $actions = DB::table('policy_actions')
            ->where('policy_id', $policyId)
            ->orderBy('effective_from')->orderBy('id')
            ->get(['id', 'transaction_type', 'status', 'effective_from', 'effective_to', 'premium', 'deleted_at']);

        if ($actions->isEmpty()) {
            $this->warn('No actions on this policy.');
            return 0;
        }

        $rows    = [];
        $suspect = [];
        $stamps  = [];   // deleted_at => ['n' => rows, 'actions' => [id => true]]

        foreach ($actions as $a) {
            // Headers in ANY state - a sweep that took the headers too would
            // otherwise hide its own children from this scan.
            $coverageIds = DB::table('policy_coverages')->where('action_id', $a->id)->pluck('id')->all();
            $ids         = $coverageIds ?: [0];

            $liveHeaders = DB::table('policy_coverages')->where('action_id', $a->id)->whereNull('deleted_at')->count();
            $live = 0;
            $dead = 0;
            // deleted_at => rows, for THIS action only. $stamps below is
            // policy-wide and drives the cluster table; this one answers
            // "did a single burst hit this action" for the flag.
            $stampsThisAction = [];

            foreach ($this->childTables() as $table => [$fk, $valueCol]) {
                $live += DB::table($table)->whereIn($fk, $ids)->whereNull('deleted_at')->count();

                $tombstones = DB::table($table)->whereIn($fk, $ids)
                    ->whereNotNull('deleted_at')
                    ->selectRaw('deleted_at, COUNT(*) as n')
                    ->groupBy('deleted_at')->get();

                foreach ($tombstones as $t) {
                    $dead += (int) $t->n;
                    $stampsThisAction[$t->deleted_at] = ($stampsThisAction[$t->deleted_at] ?? 0) + (int) $t->n;
                    $stamps[$t->deleted_at]['n'] = ($stamps[$t->deleted_at]['n'] ?? 0) + (int) $t->n;
                    $stamps[$t->deleted_at]['actions'][$a->id] = true;
                }
            }

            // The original signature: sections still render (live headers) but
            // EVERY value line underneath them is tombstoned.
            //
            // That test missed a PARTIAL wipe, which is the common case: on
            // policy COMG2024127478 action 27802 lost all 152 motor rows to one
            // burst while its policy_coverage_detail rows survived, so $live was
            // non-zero, the action was never flagged, no window was proposed,
            // and --detect reported the deletions as "most likely genuine
            // cancellations". The same blind spot hid every specialist-table
            // wipe. A single burst of tombstones under live headers is the
            // signature whether or not it took the whole tree, so flag on the
            // biggest single deleted_at instead of on $live being zero.
            //
            // BURST_ROWS mirrors the threshold the DELETE CLUSTERS table below
            // already uses to label a cluster a sweep, so the two readouts agree.
            $biggestStamp = 0;
            foreach ($stampsThisAction as $n) {
                if ($n > $biggestStamp) { $biggestStamp = $n; }
            }
            $wiped = $liveHeaders > 0
                && $dead > 0
                && ($live === 0 || $biggestStamp >= self::BURST_ROWS);
            if ($wiped) { $suspect[] = $a->id; }

            $rows[] = [
                $a->id,
                $a->transaction_type,
                $a->status,
                $a->effective_from,
                number_format((float) $a->premium, 2),
                $liveHeaders,
                $live,
                $dead,
                $wiped ? 'WIPED' : ($a->deleted_at ? 'action deleted' : ''),
            ];
        }

        $this->table(
            ['action', 'type', 'status', 'from', 'premium', 'headers', 'live rows', 'deleted rows', 'flag'],
            $rows
        );

        if (empty($stamps)) {
            $this->line('');
            $this->info('No soft-deleted coverage children on this policy - no sweep damage.');
            return 0;
        }

        // Cluster the timestamps: anything within 2 minutes of the previous one
        // is the same burst. A sweep deletes hundreds of rows inside seconds.
        ksort($stamps);
        $clusters = [];
        $cur      = null;
        foreach ($stamps as $ts => $meta) {
            $t = strtotime($ts);
            if ($cur !== null && $t - $cur['last'] <= 120) {
                $cur['last']    = $t;
                $cur['n']      += $meta['n'];
                $cur['actions'] = $cur['actions'] + ($meta['actions'] ?? []);
            } else {
                if ($cur !== null) { $clusters[] = $cur; }
                $cur = ['first' => $t, 'last' => $t, 'n' => $meta['n'], 'actions' => $meta['actions'] ?? []];
            }
        }
        $clusters[] = $cur;

        $cRows = [];
        foreach ($clusters as $c) {
            $cRows[] = [
                date('Y-m-d H:i:s', $c['first']),
                date('Y-m-d H:i:s', $c['last']),
                $c['n'],
                implode(', ', array_keys($c['actions'])),
                $c['n'] >= self::BURST_ROWS ? 'burst - looks like a sweep' : 'small - likely an operator cancellation',
            ];
        }
        $this->line('');
        $this->info('DELETE CLUSTERS (a sweep is one burst; a cancellation is a lone timestamp):');
        $this->table(['first', 'last', 'rows', 'actions touched', 'reading'], $cRows);

        // Propose the largest burst that actually overlaps a wiped action.
        $best = null;
        foreach ($clusters as $c) {
            if (!array_intersect(array_keys($c['actions']), $suspect)) { continue; }
            if ($best === null || $c['n'] > $best['n']) { $best = $c; }
        }

        $this->line('');
        if (empty($suspect)) {
            $this->warn('No action matches the sweep signature (live headers, zero live child rows).');
            $this->line('  The deletions above are most likely genuine cancellations - leave them.');
            return 0;
        }

        $this->warn('SUSPECT ACTIONS (headers render, every value line tombstoned): ' . implode(', ', $suspect));

        if ($best === null) {
            $this->line('  Could not tie a delete burst to those actions - inspect the clusters by hand.');
            return 0;
        }

        $from    = date('Y-m-d H:i:s', $best['first'] - 60);
        $to      = date('Y-m-d H:i:s', $best['last'] + 60);
        $targets = array_values(array_intersect(array_keys($best['actions']), $suspect));

        $this->line('');
        $this->info('NEXT STEP - preview (still no writes):');
        $this->line(sprintf(
            '  php artisan policy:restore-sweep-damage --policy=%d --actions=%s --from="%s" --to="%s" --reference=<HEALTHY_ACTION_ID>',
            $policyId, implode(',', $targets), $from, $to
        ));
        $this->line('  Pick --reference from an action above that still has live rows.');
        $this->line('  Add --apply only once the preview row counts look right.');

        return 0;
    }

    /** Live-row shape per action, so the repaired actions can be eyeballed against a healthy one. */
    private function verify(array $actionIds): void
    {
        $rows = [];
        foreach ($actionIds as $actionId) {
            $coverageIds = DB::table('policy_coverages')->where('action_id', $actionId)
                ->whereNull('deleted_at')->pluck('id')->all();
            $ids = $coverageIds ?: [0];

            $rows[] = [
                $actionId,
                DB::table('policy_coverage_detail')->whereIn('policy_coverage_id', $ids)->whereNull('deleted_at')->count(),
                number_format((float) DB::table('policy_coverage_detail')->whereIn('policy_coverage_id', $ids)->whereNull('deleted_at')->sum('calculated_value'), 2),
                DB::table('motor')->whereIn('policy_coverage_id', $ids)->whereNull('deleted_at')->count(),
                number_format((float) DB::table('motor')->whereIn('policy_coverage_id', $ids)->whereNull('deleted_at')->sum('calculated_value'), 2),
                DB::table('policy_extention_detail')->whereIn('policy_coverage_id', $ids)->whereNull('deleted_at')->count(),
                DB::table('policy_specified_items')->whereIn('policy_coverage_id', $ids)->whereNull('deleted_at')->count(),
            ];
        }
        $this->line('');
        $this->info('VERIFY (live rows) — repaired actions should match the reference:');
        $this->table(['action', 'detail #', 'detail value', 'vehicles', 'motor value', 'ext #', 'spec #'], $rows);
    }

    /** Stored premium vs live line-sum. Reporting only — nothing is written. */
    private function showPremiumStanding(int $policyId, array $actionIds, int $reference): void
    {
        $rows = [];
        foreach (array_merge($actionIds, [$reference]) as $actionId) {
            $a = DB::table('policy_actions')->where('id', $actionId)->first();
            if (!$a) { continue; }

            $ids = DB::table('policy_coverages')->where('action_id', $actionId)
                ->whereNull('deleted_at')->pluck('id')->all() ?: [0];

            $sum = (float) DB::table('policy_coverage_detail')->whereIn('policy_coverage_id', $ids)->whereNull('deleted_at')->sum('calculated_value')
                 + (float) DB::table('policy_extention_detail')->whereIn('policy_coverage_id', $ids)->whereNull('deleted_at')->sum('extention_calculated_value')
                 + (float) DB::table('policy_specified_items')->whereIn('policy_coverage_id', $ids)->whereNull('deleted_at')->sum('calculated_value')
                 + (float) DB::table('motor')->whereIn('policy_coverage_id', $ids)->whereNull('deleted_at')->sum('calculated_value');

            $rows[] = [
                $actionId, $a->transaction_type, $a->status, $a->effective_from,
                number_format((float) $a->premium, 2),
                number_format($sum, 2),
                number_format($sum - (float) $a->premium, 2),
            ];
        }
        $this->line('');
        $this->info('PREMIUM STANDING (reporting only — nothing written):');
        $this->table(['action', 'type', 'status', 'from', 'stored premium', 'live line sum', 'gap'], $rows);
        $this->line('  The gap is expected and is NOT a formula you can apply: healthy actions');
        $this->line('  show +1,489.32 / +217.00 / -14.00 on identical extension sets. Use Rate.');
    }
}
