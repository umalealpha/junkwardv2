<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REPAIR: undo the collateral damage a "Fill Missing (keep edits)" refresh does
 * to a target action's coverage tree.
 *
 * WHAT GOES WRONG
 * "Fill missing" is only additive at the COVERAGE level. Once a coverage exists
 * on both source and target, PolicyAction::replicateRecordsIfMissing stops
 * treating it as missing and instead SYNCS it — and the sync is blind:
 *
 *  1. policy_coverages (the header): endors_flag / status / deleted_at are
 *     overwritten with the SOURCE's values via a query-builder update
 *     (PolicyAction.php, the "Check column differences and update" block).
 *     A coverage cancelled on the source therefore gets tombstoned on the
 *     target — the whole section disappears from an ISSUED endorse.
 *     Query-builder update = no model events = NOT in `audits`.
 *
 *  2. every matched CHILD row (motor / policy_coverage_detail /
 *     policy_extention_detail / policy_specified_items) is overwritten
 *     wholesale with the source row via $existingRecord->update($dataToUpdate).
 *     The excluded list is id / fk / created_at / updated_at /
 *     previousActionIdCov / pro_rate_premium / endors_flag / policyCoverageID —
 *     `deleted_at` is NOT excluded (the exclusion was commented out on
 *     10-02-2026), so the source's tombstones AND the source's older values
 *     both land on the target. Those models ARE Auditable and $guarded is
 *     open, so every one of these writes IS in `audits` with old_values.
 *
 * So: child damage is fully reversible from `audits`; header tombstones are not
 * audited but are identifiable, because a copied tombstone has a deleted_at
 * that PREDATES the refresh while its updated_at sits INSIDE the refresh
 * window. A genuine operator cancel has the two within seconds of each other.
 *
 * NOTE this is a different signature from the 2026-05-11 rebuild sweep that
 * policy:restore-sweep-damage repairs. That one stamped deleted_at = now() on
 * the children, so it clusters by deleted_at. Fill-missing copies the SOURCE's
 * deleted_at, so a deleted_at scan finds nothing — this command clusters on
 * `audits.created_at` instead.
 *
 * A range refresh (From → To) hits EVERY action in the span, so --detect
 * defaults to the whole policy: assume more than the one action you noticed is
 * damaged until the scan says otherwise.
 *
 * SAFE BY DEFAULT: dry-run unless --apply. All writes run in one transaction.
 *
 *   php artisan policy:restore-fill-missing-damage --policy=COMG2024117100 --detect
 *   php artisan policy:restore-fill-missing-damage --policy=COMG2024117100 \
 *        --from="2026-09-01 10:00:00" --to="2026-09-01 10:20:00"
 *   php artisan policy:restore-fill-missing-damage --policy=COMG2024117100 \
 *        --from=... --to=... --actions=42587 --revive-headers --apply
 */
class RestoreFillMissingDamage extends Command
{
    protected $signature = 'policy:restore-fill-missing-damage
        {--policy= : Policy id or policy number (e.g. COMG2024117100)}
        {--actions= : Comma-separated action ids to repair. Default: every action on the policy}
        {--from= : Refresh window start "Y-m-d H:i:s" (required unless --detect)}
        {--to= : Refresh window end "Y-m-d H:i:s" (required unless --detect)}
        {--detect : Read-only scan: cluster audit bursts and list copied-in header tombstones}
        {--revive-headers : Also clear deleted_at on policy_coverages headers tombstoned by the refresh}
        {--apply : Actually write. Omit for a dry-run preview}';

    protected $description = 'Undo a "Fill Missing (keep edits)" refresh by replaying audits.old_values (dry-run unless --apply)';

    /**
     * Auditable models the fill-missing child sync can overwrite, mapped to the
     * column that ties the row back to an action. Everything except the header
     * hangs off policy_coverage_id.
     */
    private const TREE_MODELS = [
        \AlphaDirect\Models\PolicyCoverage::class        => 'action_id',
        \AlphaDirect\Models\PolicyCoverageDetail::class  => 'policy_coverage_id',
        \AlphaDirect\Models\PolicyExtentionDetails::class => 'policy_coverage_id',
        \AlphaDirect\Models\PolicySpecifiedItem::class   => 'policy_coverage_id',
        \AlphaDirect\Models\Motor::class                 => 'policy_coverage_id',
    ];

    /** Never restored from old_values — identity, ownership and wizard-owned stamps. */
    private const NEVER_RESTORE = [
        'id', 'action_id', 'policy_coverage_id', 'policy_id', 'term_id',
        'created_at', 'updated_at', 'created_by', 'updated_by',
    ];

    /** table => column list. Schema::hasColumn hits information_schema every call. */
    private array $columnCache = [];

    private function tableColumns(string $table): array
    {
        return $this->columnCache[$table] ??= Schema::getColumnListing($table);
    }

    public function handle()
    {
        $policyId = $this->resolvePolicyId((string) $this->option('policy'));
        if ($policyId === null) {
            $this->error('Policy not found: ' . $this->option('policy'));
            return 1;
        }

        if ($this->option('detect')) {
            return $this->detect($policyId);
        }

        $from = (string) $this->option('from');
        $to   = (string) $this->option('to');
        if ($from === '' || $to === '') {
            $this->error('--from and --to are required. Run --detect first to find the burst window.');
            return 1;
        }

        $actionIds = $this->resolveActionIds($policyId);
        if ($actionIds === null) {
            return 1;
        }

        $apply = (bool) $this->option('apply');

        $this->line('');
        $this->info('Restore fill-missing damage');
        $this->line("  policy   : {$policyId} (" . DB::table('policies')->where('id', $policyId)->value('policyNumber') . ')');
        $this->line('  actions  : ' . implode(', ', $actionIds));
        $this->line("  window   : {$from}  ..  {$to}");
        $this->line('  mode     : ' . ($apply ? 'APPLY (writes)' : 'DRY-RUN (no writes)'));
        $this->line('');

        // ---------------------------------------------------------------
        // 1. Pre-window state per row, from the FIRST audit inside the burst.
        // ---------------------------------------------------------------
        $plan = $this->buildRestorePlan($policyId, $actionIds, $from, $to);

        if (empty($plan['rows']) && empty($plan['headers'])) {
            $this->info('Nothing to restore — no audited tree changes for these actions inside the window.');
            return 0;
        }

        // ---------------------------------------------------------------
        // 2. Preview.
        // ---------------------------------------------------------------
        $summary = [];
        foreach ($plan['rows'] as $row) {
            $key = $row['action_id'] . '|' . $row['table'];
            $summary[$key]['action']  = $row['action_id'];
            $summary[$key]['table']   = $row['table'];
            $summary[$key]['rows']    = ($summary[$key]['rows'] ?? 0) + 1;
            $summary[$key]['fields']  = ($summary[$key]['fields'] ?? 0) + count($row['values']);
            $summary[$key]['revives'] = ($summary[$key]['revives'] ?? 0)
                + ((array_key_exists('deleted_at', $row['values']) && $row['values']['deleted_at'] === null) ? 1 : 0);
        }
        ksort($summary);

        $this->table(
            ['action', 'table', 'rows to revert', 'fields', 'of which un-delete'],
            array_map(fn ($s) => [$s['action'], $s['table'], $s['rows'], $s['fields'], $s['revives']], $summary)
        );

        if (!empty($plan['headers'])) {
            $this->line('');
            $this->warn('Copied-in HEADER tombstones (policy_coverages) — not audited, revived by fingerprint:');
            $this->table(
                ['pc id', 'action', 'coverage_id', 'risk address', 'deleted_at (copied)', 'updated_at (refresh)'],
                array_map(fn ($h) => [
                    $h['id'], $h['action_id'], $h['coverage_id'], $h['address'], $h['deleted_at'], $h['updated_at'],
                ], $plan['headers'])
            );
            if (!$this->option('revive-headers')) {
                $this->line('  (pass --revive-headers to clear these; they are listed for review either way)');
            }
        }

        if (!$apply) {
            $this->line('');
            $this->info('DRY-RUN complete — nothing written. Re-run with --apply to perform the restore.');
            return 0;
        }

        // ---------------------------------------------------------------
        // 3. Write.
        // ---------------------------------------------------------------
        $writtenRows    = 0;
        $revivedHeaders = 0;

        DB::transaction(function () use ($plan, &$writtenRows, &$revivedHeaders, $policyId, $from, $to) {
            foreach ($plan['rows'] as $row) {
                // DB::table, not the model: the values ARE the pre-refresh
                // truth, so mutators/observers must not re-derive them, and a
                // model save would re-audit every field we are putting back.
                $writtenRows += DB::table($row['table'])
                    ->where('id', $row['id'])
                    ->update($row['values']);
            }

            if ($this->option('revive-headers')) {
                foreach ($plan['headers'] as $h) {
                    $revivedHeaders += DB::table('policy_coverages')
                        ->where('id', $h['id'])
                        ->whereNotNull('deleted_at')
                        ->update(['deleted_at' => null]);
                }
            }

            // One audit row for the repair itself, so the Logs tab shows who
            // reverted what — the per-row DB::table writes above are silent.
            $policyNumber = DB::table('policies')->where('id', $policyId)->value('policyNumber');
            if ($policyNumber) {
                DB::table('audits')->insert([
                    'event'          => 'updated',
                    'auditable_type' => \AlphaDirect\Policy::class,
                    'auditable_id'   => $policyId,
                    'tags'           => 'Fill-Missing Damage Restored',
                    'old_values'     => null,
                    'new_values'     => json_encode([
                        'window'          => $from . ' .. ' . $to,
                        'rows_reverted'   => $writtenRows,
                        'headers_revived' => $revivedHeaders,
                    ]),
                    'user_id'        => null,
                    'user_type'      => \AlphaDirect\User::class,
                    'policy_id'      => $policyId,
                    'policy_number'  => $policyNumber,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }
        });

        $this->line('');
        $this->info("Restored {$writtenRows} child row(s)"
            . ($this->option('revive-headers') ? ", revived {$revivedHeaders} coverage header(s)" : '') . '.');
        $this->warn('policy_actions.premium / annual_premium are NOT touched — open the action and hit Rate '
            . 'so the banner and V2 Quote re-derive from the restored tree.');
        $this->warn('Do NOT re-run Fill Missing from the same source on this policy: it will re-copy the same '
            . 'tombstones and values straight back.');

        return 0;
    }

    /**
     * Build the per-row revert plan.
     *
     * For each audited row touched inside the window we take the EARLIEST audit
     * in that window and restore its old_values — that is the state the row was
     * in before the refresh started. Taking the earliest (not the latest) is
     * what makes a refresh that wrote a row twice still revert to one hop
     * before the burst rather than to the middle of it.
     *
     * @return array{rows: array<int, array{table:string,id:int,action_id:int,values:array}>, headers: array<int, array>}
     */
    private function buildRestorePlan(int $policyId, array $actionIds, string $from, string $to): array
    {
        $types = array_keys(self::TREE_MODELS);

        $audits = DB::table('audits')
            ->where('policy_id', $policyId)
            ->whereIn('auditable_type', $types)
            ->where('event', 'updated')
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'auditable_type', 'auditable_id', 'old_values', 'created_at']);

        // action_id per policy_coverages row, for both the header rows and the
        // children's parent lookup. Read withTrashed (raw table) — a header the
        // refresh tombstoned must still resolve or its children drop out.
        $actionByPc = DB::table('policy_coverages')
            ->whereIn('action_id', $actionIds)
            ->pluck('action_id', 'id')
            ->all();

        $rows  = [];
        $seen  = [];   // "table|id" => true, so only the earliest audit wins

        foreach ($audits as $a) {
            $model = $a->auditable_type;
            if (!isset(self::TREE_MODELS[$model])) {
                continue;
            }

            $table  = (new $model)->getTable();
            $rowId  = (int) $a->auditable_id;
            $key    = $table . '|' . $rowId;
            if (isset($seen[$key])) {
                continue;
            }

            // Scope to the requested actions. Header rows key on themselves,
            // children on their parent coverage.
            if (self::TREE_MODELS[$model] === 'action_id') {
                $actionId = $actionByPc[$rowId] ?? null;
            } else {
                $pcId     = DB::table($table)->where('id', $rowId)->value('policy_coverage_id');
                $actionId = $pcId ? ($actionByPc[$pcId] ?? null) : null;
            }
            if ($actionId === null) {
                continue;   // belongs to an action outside --actions
            }

            $old = json_decode((string) $a->old_values, true);
            if (!is_array($old) || empty($old)) {
                continue;
            }

            // Only columns that exist and are safe to put back.
            $columns = $this->tableColumns($table);
            $values  = [];
            foreach ($old as $col => $val) {
                if (in_array($col, self::NEVER_RESTORE, true)) {
                    continue;
                }
                if (!in_array($col, $columns, true)) {
                    continue;
                }
                $values[$col] = $val;
            }
            if (empty($values)) {
                continue;
            }

            $seen[$key] = true;
            $rows[] = [
                'table'     => $table,
                'id'        => $rowId,
                'action_id' => (int) $actionId,
                'values'    => $values,
            ];
        }

        return [
            'rows'    => $rows,
            'headers' => $this->copiedHeaderTombstones($actionIds, $from, $to),
        ];
    }

    /**
     * Coverage headers whose tombstone was COPIED IN by the refresh rather than
     * set by an operator.
     *
     * Fingerprint: the row was written inside the refresh window (updated_at in
     * [from, to]) but carries a deleted_at from BEFORE it — that combination is
     * only producible by the sync copying the source's older deleted_at onto a
     * row that was live a moment earlier. An operator cancel writes both
     * stamps at once, so deleted_at and updated_at land within seconds.
     *
     * The 60-second slack keeps a genuine cancel that happened to fall inside
     * the window out of the list.
     */
    private function copiedHeaderTombstones(array $actionIds, string $from, string $to): array
    {
        $rows = DB::table('policy_coverages as pc')
            ->leftJoin('risk_address as ra', 'pc.risk_address_id', '=', 'ra.id')
            ->whereIn('pc.action_id', $actionIds)
            ->whereNotNull('pc.deleted_at')
            ->whereBetween('pc.updated_at', [$from, $to])
            ->whereRaw('pc.deleted_at < DATE_SUB(pc.updated_at, INTERVAL 60 SECOND)')
            ->orderBy('pc.action_id')->orderBy('pc.id')
            ->get(['pc.id', 'pc.action_id', 'pc.coverage_id', 'pc.deleted_at', 'pc.updated_at', 'ra.address_name']);

        return $rows->map(fn ($r) => [
            'id'         => (int) $r->id,
            'action_id'  => (int) $r->action_id,
            'coverage_id' => (int) $r->coverage_id,
            'address'    => $r->address_name ?? '—',
            'deleted_at' => $r->deleted_at,
            'updated_at' => $r->updated_at,
        ])->all();
    }

    /**
     * Read-only scan. Clusters `audits.created_at` for the policy's tree models
     * into bursts (anything within 2 minutes of the previous stamp is the same
     * run), because a refresh writes hundreds of rows in seconds while ordinary
     * wizard edits are lone stamps. Prints the window to feed back in as
     * --from / --to.
     */
    private function detect(int $policyId): int
    {
        $policyNo = DB::table('policies')->where('id', $policyId)->value('policyNumber');
        $this->info("Fill-missing damage scan — policy {$policyId} ({$policyNo})");
        $this->line('  read-only: nothing is written by --detect');
        $this->line('');

        $audits = DB::table('audits')
            ->where('policy_id', $policyId)
            ->whereIn('auditable_type', array_keys(self::TREE_MODELS))
            ->where('event', 'updated')
            ->orderBy('created_at')
            ->get(['auditable_type', 'auditable_id', 'created_at']);

        if ($audits->isEmpty()) {
            $this->warn('No audited coverage-tree updates on this policy at all — either auditing was off '
                . '(AUDITING_ENABLED) or the damage predates it. Fall back to a DB point-in-time restore.');
            return 0;
        }

        $clusters = [];
        $cur      = null;
        foreach ($audits as $a) {
            $t = strtotime((string) $a->created_at);
            if ($cur === null || $t - $cur['last'] > 120) {
                if ($cur !== null) { $clusters[] = $cur; }
                $cur = ['from' => (string) $a->created_at, 'to' => (string) $a->created_at, 'last' => $t, 'n' => 0, 'models' => []];
            }
            $cur['to']   = (string) $a->created_at;
            $cur['last'] = $t;
            $cur['n']++;
            $cur['models'][class_basename($a->auditable_type)] = ($cur['models'][class_basename($a->auditable_type)] ?? 0) + 1;
        }
        if ($cur !== null) { $clusters[] = $cur; }

        // Biggest bursts first — a refresh dwarfs any hand edit.
        usort($clusters, fn ($a, $b) => $b['n'] <=> $a['n']);

        $this->table(
            ['rows', 'from', 'to', 'models touched'],
            array_map(fn ($c) => [
                $c['n'],
                $c['from'],
                $c['to'],
                implode(', ', array_map(fn ($k, $v) => "{$k}×{$v}", array_keys($c['models']), $c['models'])),
            ], array_slice($clusters, 0, 15))
        );

        $this->line('');
        $this->line('A refresh burst is the row with a large count over a few seconds/minutes.');
        $this->line('Widen it by a minute either side and pass it as --from / --to.');

        // Header tombstones across the whole policy, unbounded by window, so the
        // operator can see whether any section vanished at all.
        $allActions = DB::table('policy_actions')->where('policy_id', $policyId)->pluck('id')->all();
        $suspects = DB::table('policy_coverages as pc')
            ->leftJoin('risk_address as ra', 'pc.risk_address_id', '=', 'ra.id')
            ->whereIn('pc.action_id', $allActions)
            ->whereNotNull('pc.deleted_at')
            ->whereRaw('pc.deleted_at < DATE_SUB(pc.updated_at, INTERVAL 60 SECOND)')
            ->orderBy('pc.action_id')->orderBy('pc.id')
            ->get(['pc.id', 'pc.action_id', 'pc.coverage_id', 'pc.deleted_at', 'pc.updated_at', 'ra.address_name']);

        $this->line('');
        if ($suspects->isEmpty()) {
            $this->info('No copied-in coverage-header tombstones — every soft-deleted section on this policy was '
                . 'cancelled deliberately.');
        } else {
            $this->warn('Coverage headers whose tombstone was COPIED IN (deleted_at predates its own updated_at):');
            $this->table(
                ['pc id', 'action', 'coverage_id', 'risk address', 'deleted_at (copied)', 'updated_at (write)'],
                $suspects->map(fn ($r) => [
                    $r->id, $r->action_id, $r->coverage_id, $r->address_name ?? '—', $r->deleted_at, $r->updated_at,
                ])->all()
            );
            $this->line('  Restore these with --revive-headers once the window is confirmed.');
        }

        return 0;
    }

    private function resolveActionIds(int $policyId): ?array
    {
        $csv = trim((string) $this->option('actions'));

        if ($csv === '') {
            return DB::table('policy_actions')->where('policy_id', $policyId)->pluck('id')
                ->map(fn ($i) => (int) $i)->all();
        }

        $ids = array_values(array_filter(array_map('intval', explode(',', $csv))));
        if (empty($ids)) {
            $this->error('--actions given but no valid ids parsed.');
            return null;
        }

        // Guard: a mistyped action id must never restore rows on another policy.
        $foreign = DB::table('policy_actions')->whereIn('id', $ids)->where('policy_id', '!=', $policyId)->pluck('id')->all();
        if (!empty($foreign)) {
            $this->error('These actions do not belong to policy ' . $policyId . ': ' . implode(', ', $foreign));
            return null;
        }
        $missing = array_diff($ids, DB::table('policy_actions')->whereIn('id', $ids)->pluck('id')->map(fn ($i) => (int) $i)->all());
        if (!empty($missing)) {
            $this->error('Action(s) not found: ' . implode(', ', $missing));
            return null;
        }

        return $ids;
    }

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
}
