<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Services\Reinsurance\RegulatoryCessionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Run the regulatory-mapping cession for a policy, and show it against the
 * legacy figures.
 *
 * This is the reconciliation step RI-12 asks for before the engine is switched
 * over: "run COMG2026213751 and agree it against the restated workbook. That
 * policy is the one every figure has been tested on."
 *
 *   php artisan reinsurance:regulatory-cession COMG2026213751
 *   php artisan reinsurance:regulatory-cession --action=12345 --json
 *
 * READ ONLY. It publishes nothing and changes no stored figure.
 */
class RegulatoryCessionReport extends Command
{
    protected $signature = 'reinsurance:regulatory-cession
                            {policy? : Policy number, e.g. COMG2026213751}
                            {--action= : A policy_actions id, instead of a policy number}
                            {--json : Emit the whole comparison as JSON}
                            {--diagnose : Report coverage codes that resolve to more than one group or class}
                            {--check-write : Report whether the connected database will accept writes}
                            {--source : Compare the two possible sources of sum insured for an action}
                            {--persist : Store the regulatory allocation. Writes ONLY to policy_reinsurance_regulatory}';

    protected $description = 'Allocate a policy on the regulatory mapping basis and compare it against the legacy cession';

    public function handle(RegulatoryCessionService $service): int
    {
        if ($this->option('source')) {
            return $this->compareSources();
        }

        if ($this->option('check-write')) {
            return $this->checkWrite();
        }

        if ($this->option('diagnose')) {
            return $this->diagnose();
        }

        if ($this->option('persist')) {
            return $this->persist($service);
        }

        $actionId = $this->resolveActionId();
        if ($actionId === null) {
            return self::FAILURE;
        }

        $c = $service->compare($actionId);

        if ($this->option('json')) {
            $this->line((string) json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Regulatory cession — action {$actionId}");
        $this->line('Class limits: ' . json_encode(config('reinsurance.class_limits')));
        $this->line('Live engine : ' . config('reinsurance.engine') . '  (this report never changes it)');
        $this->newLine();

        if (empty($c['units'])) {
            $this->warn('No mapped coverages on this action. Either it carries no coverages, or');
            $this->warn('reinsurance_group_coverage.regulatory_mapping has not been populated for them.');

            return self::SUCCESS;
        }

        $this->table(
            ['Mapping', 'Route', 'Risks', 'Sum insured', 'Ceded', 'Retained', 'Unplaced'],
            array_map(fn ($m) => [
                $m['mapping'] !== '' ? $m['mapping'] : '(unmapped)',
                $m['treaty_route'],
                $m['risks'] ?? 1,
                number_format((float) $m['sum_insured'], 2),
                number_format((float) $m['ceded_si'], 2),
                number_format((float) $m['retained_si'], 2),
                number_format((float) $m['unplaced_si'], 2),
            ], $c['by_mapping'])
        );

        $this->newLine();
        if (! $c['legacy_published']) {
            $this->warn('The legacy engine has published nothing for this action, so there is');
            $this->warn('nothing to compare against — the deltas below are the whole figure,');
            $this->warn('not a difference. Run the policy through Compute first.');
        }

        // TWO COLUMNS, NOT THREE, AND NO DELTA. What the legacy chain stored is
        // the total it ALLOCATED across every layer and formula -- retention,
        // cession, surplus and facultative together -- so it cannot be
        // subtracted from a cession. It is shown for context and labelled for
        // what it is.
        $this->table(
            ['', 'Regulatory cession', 'Legacy allocated (not a cession)'],
            [
                [
                    'Sum insured',
                    number_format($c['regulatory']['ceded_si'], 2),
                    number_format($c['legacy']['allocated_si'], 2),
                ],
                [
                    'Premium',
                    number_format($c['regulatory']['ceded_premium'], 2),
                    number_format($c['legacy']['allocated_premium'], 2),
                ],
            ]
        );

        $this->newLine();
        $this->line(sprintf(
            '  The legacy figure spans %d formula(s) over %d layer type(s). Each formula',
            $c['legacy']['formulas'],
            $c['legacy']['layer_types']
        ));
        $this->line('  writes its own full set of layer rows, so the total counts the retained');
        $this->line('  leg alongside the ceded one and counts both once per formula.');

        if ($c['exceptions']) {
            $this->newLine();
            $this->warn(count($c['exceptions']) . ' line(s) need a human:');
            foreach ($c['exceptions'] as $e) {
                $this->line(sprintf(
                    '  %-16s %-22s %s',
                    $e['mapping'] !== '' ? $e['mapping'] : '(unmapped)',
                    $e['group'] ?? '—',
                    $e['exception']
                ));
            }
        }

        return self::SUCCESS;
    }

    /**
     * Whether the connected database will actually accept a write.
     *
     * The host name is not evidence. A replica is often named for the cluster it
     * serves, and a connection that reads perfectly well can reject every INSERT
     * with nothing more than an error at the point of no return — halfway through
     * a configuration script, with some rows written and some not.
     *
     * Read-only: it reads two server variables and opens a transaction it always
     * rolls back.
     */
    private function checkWrite(): int
    {
        $conn = DB::connection();

        $this->info('Connection check');
        $this->newLine();
        $this->line('  driver   : ' . $conn->getDriverName());
        $this->line('  host     : ' . config('database.connections.' . $conn->getName() . '.host'));
        $this->line('  database : ' . $conn->getDatabaseName());

        try {
            $v = DB::selectOne('SELECT @@read_only AS ro, @@innodb_read_only AS iro, @@hostname AS h');
            $this->line('  hostname : ' . ($v->h ?? '?'));
            $this->line('  read_only: ' . $v->ro . '   innodb_read_only: ' . $v->iro);

            if ((int) $v->ro === 1 || (int) $v->iro === 1) {
                $this->newLine();
                $this->error('REPLICA — this server will reject writes.');

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->warn('  could not read the server flags: ' . $e->getMessage());
        }

        // The flags can lie where permissions, not the server, are the limit. The
        // only proof is an actual write, so do one and roll it back.
        try {
            DB::beginTransaction();
            DB::statement('CREATE TEMPORARY TABLE ri_write_probe (id INT)');
            DB::statement('INSERT INTO ri_write_probe (id) VALUES (1)');
            DB::statement('DROP TEMPORARY TABLE ri_write_probe');
            DB::rollBack();

            $this->newLine();
            $this->info('WRITABLE — a test write succeeded and was rolled back.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            try {
                DB::rollBack();
            } catch (\Throwable $ignored) {
            }

            $this->newLine();
            $this->error('NOT WRITABLE — ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * The two places a sum insured can come from, side by side.
     *
     * policy_coverage_detail is the policy AS CAPTURED. policy_reinsurance_details
     * is the STAGED figure the legacy engine actually cedes from — the motor path
     * reads prid.n_SumInsured, never the coverage value — and the two do not
     * always agree, because motor sum insured arrives through the vehicle rather
     * than through the coverage row.
     *
     * Which of the two the regulatory read should use is the whole question
     * behind a policy total that will not reconcile to Reinsurance's workbook.
     */
    private function compareSources(): int
    {
        $actionId = $this->resolveActionId();
        if ($actionId === null) {
            return self::FAILURE;
        }

        $staged = DB::select("
            SELECT g.group_code                       AS group_code,
                   gmap.regulatory_mapping            AS mapping,
                   COUNT(*)                           AS rows_,
                   COUNT(DISTINCT prid.pocoverage_detail_id) AS details,
                   SUM(COALESCE(prid.n_SumInsured,0)) AS si,
                   MAX(once.si_once)                  AS si_once
            FROM policy_reinsurance_details prid
            LEFT JOIN reinsurance_group g ON g.id = prid.group_id
            LEFT JOIN (
                SELECT group_id, MIN(regulatory_mapping) AS regulatory_mapping
                FROM reinsurance_group_coverage
                GROUP BY group_id
            ) gmap ON gmap.group_id = prid.group_id
            -- The staged table holds one row per LAYER, so the same sum insured
            -- repeats once per formula on the group. Counting it once per
            -- coverage row is what makes it comparable to a policy total.
            LEFT JOIN (
                SELECT group_id, SUM(si) AS si_once
                FROM (
                    SELECT group_id, pocoverage_detail_id, risk_address_id,
                           MAX(COALESCE(n_SumInsured,0)) AS si
                    FROM policy_reinsurance_details
                    WHERE action_id = ?
                    GROUP BY group_id, pocoverage_detail_id, risk_address_id
                ) y
                GROUP BY group_id
            ) once ON once.group_id = prid.group_id
            WHERE prid.action_id = ?
            GROUP BY g.group_code, gmap.regulatory_mapping
            ORDER BY si_once DESC
        ", [$actionId, $actionId]);

        $this->info("Staged reinsurance rows — policy_reinsurance_details, action {$actionId}");
        $this->newLine();

        if (! $staged) {
            $this->warn('No staged rows. This action has not been through Compute, so the');
            $this->warn('legacy engine has nothing to cede from either.');

            return self::SUCCESS;
        }

        $total = 0.0;
        $once  = 0.0;
        $this->table(
            ['Group', 'Mapping', 'Rows', 'Details', 'SI (raw sum)', 'SI (once per row)'],
            array_map(function ($r) use (&$total, &$once) {
                $total += (float) $r->si;
                $once  += (float) $r->si_once;

                return [
                    $r->group_code ?? '(no group)',
                    $r->mapping ?? '(unmapped)',
                    $r->rows_,
                    $r->details,
                    number_format((float) $r->si, 2),
                    number_format((float) $r->si_once, 2),
                ];
            }, $staged)
        );

        $captured = DB::selectOne("
            SELECT SUM(COALESCE(cvgsm.coverage_value,0)) AS si
            FROM policy_coverages cvgm
            JOIN policy_coverage_detail cvgsm ON cvgsm.policy_coverage_id = cvgm.id
            WHERE cvgm.action_id = ?
              AND cvgm.deleted_at IS NULL
              AND cvgsm.deleted_at IS NULL
        ", [$actionId]);

        $this->newLine();
        $this->line('  staged   (policy_reinsurance_details) : ' . number_format($total, 2));
        $this->line('  captured (policy_coverage_detail)     : '
            . number_format((float) ($captured->si ?? 0), 2));

        return self::SUCCESS;
    }

    /**
     * Store the regulatory allocation for one action, and show what landed.
     *
     * THIS DOES NOT CUT ANYTHING OVER. It writes to
     * policy_reinsurance_regulatory and nowhere else; policy_reinsurance is
     * untouched and the application still reports the legacy figures. What it
     * buys is that the two bases can be compared on rows that actually exist,
     * so the movement Reinsurance is being asked to sign off is evidenced
     * rather than recomputed on demand.
     */
    private function persist(RegulatoryCessionService $service): int
    {
        $actionId = $this->resolveActionId();
        if ($actionId === null) {
            return self::FAILURE;
        }

        $r = $service->persist($actionId, 'reinsurance:regulatory-cession --persist');

        $this->info("Stored the regulatory allocation for action {$actionId}.");
        $this->line('policy_reinsurance is untouched. The live cession is still the legacy chain.');
        $this->newLine();

        $this->line(sprintf(
            '  %d row(s) across %d risk unit(s)%s',
            $r['rows'],
            $r['units'],
            $r['exceptions'] ? sprintf(', %d carrying an exception', $r['exceptions']) : ''
        ));
        $this->newLine();

        $layers = $service->storedLayers($actionId);
        if (! $layers) {
            $this->warn('Nothing was stored. That is a fault unless the action has no risks.');

            return self::SUCCESS;
        }

        $this->table(
            ['Layer', 'Rows', 'Sum insured', 'Premium'],
            array_map(fn ($k, $v) => [
                $k,
                $v['rows'],
                number_format($v['sum_insured'], 2),
                number_format($v['premium'], 2),
            ], array_keys($layers), $layers)
        );

        $this->line('  Total sum insured : ' . number_format($r['sum_insured'], 2));
        $this->line('  Ceded             : ' . number_format($r['ceded'], 2));

        return self::SUCCESS;
    }

    /**
     * How many times the staged cession rows repeat.
     *
     * policy_reinsurance_details is the table the treaty cedes from, and its
     * rows are duplicated: 408,724 of them across 3,567 distinct
     * (action, group, risk address, coverage detail, formula) combinations when
     * this was written, a factor of 114.
     *
     * THE REGULATORY READ SURVIVES IT because risksFor() takes MAX per unit
     * rather than SUM, so the repetition collapses. The legacy chain does not,
     * which is the whole of why the two bases disagree about a policy's sum
     * insured -- on COMG2026213718 legacy holds 820,544 against a true 410,272,
     * exactly double, because that action's rows appear twice.
     *
     * Reported here rather than fixed. Deleting rows from the table the live
     * cession is computed from is not a systems decision, and the duplication
     * may be doing work elsewhere that is not visible from this side.
     */
    private function reportStagedDuplication(): void
    {
        $d = DB::selectOne("
            SELECT COUNT(*) AS raw,
                   COUNT(DISTINCT CONCAT_WS('|', action_id, group_id,
                       COALESCE(risk_address_id, 0), COALESCE(pocoverage_detail_id, 0),
                       COALESCE(formula_id, 0))) AS distinct_units
            FROM policy_reinsurance_details
        ");

        $raw   = (int) ($d->raw ?? 0);
        $units = (int) ($d->distinct_units ?? 0);

        if ($units === 0) {
            return;
        }

        $factor = $raw / $units;

        if ($factor < 1.01) {
            $this->info('Staged cession rows are not duplicated.');
            $this->newLine();

            return;
        }

        $this->warn(sprintf(
            'Staged cession rows repeat %.1f times: %s rows over %s distinct units.',
            $factor,
            number_format($raw),
            number_format($units)
        ));
        $this->line('The regulatory read takes MAX per unit so it is unaffected. The legacy chain');
        $this->line('sums them, which is why the two bases disagree on sum insured -- and it is a');
        $this->line('data fault to raise with Operations, not something to delete from here.');
        $this->newLine();
    }

    /**
     * Groups whose coverage rows disagree on the regulatory class.
     *
     * THIS USED TO CHECK COVERAGES, AND IT WAS A FALSE ALARM. It reported every
     * coverage code sitting in more than one group with more than one class — 34
     * of them, Comprehensive across Motor and Property among them — as though
     * that were a fault. It is not. Reinsurance's mapping, annexed 17 August, is
     * keyed on (coverage, group) PAIRS: Comprehensive in MOTOR_COM is Motor and
     * Comprehensive in MOTOR_TRADERS_COM_EXT is Property, deliberately, because
     * the class belongs to the GROUP and not to the sub-coverage. The read
     * reaches a class through the group and joins on the pair, so a shared
     * coverage name double counts nothing.
     *
     * What genuinely cannot be routed is a GROUP holding two classes: the read
     * takes the group's class, and there would be no single answer to take. That
     * is what this reports now.
     */
    private function diagnose(): int
    {
        // Given a policy or action, report the coverages ON IT that reach no
        // group at all. Those contribute nothing to the regulatory read, so they
        // are the difference between our total and Reinsurance's.
        if ($this->argument('policy') || $this->option('action')) {
            return $this->diagnoseAction();
        }

        $this->reportStagedDuplication();

        $rows = DB::select("
            SELECT rg.group_code                          AS group_code,
                   COUNT(DISTINCT gc.regulatory_mapping)  AS classes_,
                   COUNT(*)                               AS rows_,
                   GROUP_CONCAT(DISTINCT gc.regulatory_mapping) AS classes
            FROM reinsurance_group_coverage gc
            JOIN reinsurance_group rg ON rg.id = gc.group_id
            WHERE COALESCE(NULLIF(TRIM(gc.regulatory_mapping), ''), '') <> ''
            GROUP BY rg.group_code
            HAVING classes_ > 1
            ORDER BY rows_ DESC
        ");

        if (! $rows) {
            $this->info('Every group carries exactly one regulatory class. Nothing can be routed');
            $this->info('two ways.');

            return self::SUCCESS;
        }

        $this->error(count($rows) . ' group(s) hold more than one regulatory class.');
        $this->line("A group's class decides how its risks route, so a group with two cannot be");
        $this->line('routed at all. Settle these in the mapping before relying on the cession.');
        $this->newLine();

        $this->table(
            ['Group', 'Classes', 'Coverage rows', 'Class(es)'],
            array_map(fn ($r) => [
                $r->group_code,
                $r->classes_,
                $r->rows_,
                // GROUP_CONCAT has no portable ORDER BY across MySQL and sqlite,
                // so sort here rather than in the query.
                implode(', ', $this->sorted((string) $r->classes)),
            ], $rows)
        );

        return self::SUCCESS;
    }

    /** @return string[] */
    private function sorted(string $csv): array
    {
        $parts = array_filter(array_map('trim', explode(',', $csv)), 'strlen');
        sort($parts);

        return array_values(array_unique($parts));
    }

    /**
     * Coverages on one action that reach no reinsurance group.
     *
     * A coverage whose code matches no reinsurance_group_coverage row has no
     * class, so it is invisible to the regulatory basis — it does not report as
     * retained, it does not report at all. That is worse than a wrong class, and
     * it is the first thing to check when a policy total is short.
     */
    private function diagnoseAction(): int
    {
        $actionId = $this->resolveActionId();
        if ($actionId === null) {
            return self::FAILURE;
        }

        $rows = DB::select("
            SELECT tc.s_CoverageCode                        AS coverage,
                   COUNT(*)                                 AS rows_,
                   SUM(COALESCE(cvgsm.coverage_value, 0))   AS si,
                   MAX(parent.s_CoverageCode)               AS parent_code,
                   -- Where the OTHER sub-coverages of the same parent sit. A
                   -- sub-coverage belongs where its siblings belong, so this is
                   -- the answer rather than a guess.
                   MAX((
                       SELECT GROUP_CONCAT(DISTINCT g2.group_code)
                       FROM policy_coverage_detail d2
                       JOIN tb_cvgpccoverages t2           ON t2.id = d2.coverage_id
                       JOIN reinsurance_group_coverage gc2 ON gc2.coverage_name = t2.s_CoverageCode
                       JOIN reinsurance_group g2           ON g2.id = gc2.group_id
                       WHERE d2.policy_coverage_id = cvgm.id
                         AND d2.deleted_at IS NULL
                   ))                                       AS sibling_groups,
                   MAX((
                       SELECT GROUP_CONCAT(DISTINCT gc3.regulatory_mapping)
                       FROM policy_coverage_detail d3
                       JOIN tb_cvgpccoverages t3           ON t3.id = d3.coverage_id
                       JOIN reinsurance_group_coverage gc3 ON gc3.coverage_name = t3.s_CoverageCode
                       WHERE d3.policy_coverage_id = cvgm.id
                         AND d3.deleted_at IS NULL
                   ))                                       AS sibling_classes
            FROM policy_coverages cvgm
            JOIN policy_coverage_detail cvgsm ON cvgsm.policy_coverage_id = cvgm.id
            JOIN tb_cvgpccoverages tc         ON tc.id = cvgsm.coverage_id
            LEFT JOIN tb_cvgpccoverages parent ON parent.id = cvgm.coverage_id
            LEFT JOIN policy_actions pa ON pa.id = cvgm.action_id
            LEFT JOIN policies pol      ON pol.id = pa.policy_id
            LEFT JOIN reinsurance_group_coverage gc
                ON gc.coverage_name = tc.s_CoverageCode
            LEFT JOIN reinsurance_group g
                ON g.id = gc.group_id
               AND (g.product_id IS NULL OR pol.product_id IS NULL OR g.product_id = pol.product_id)
            WHERE cvgm.action_id = ?
              AND cvgm.deleted_at IS NULL
              AND cvgsm.deleted_at IS NULL
              AND g.id IS NULL
            GROUP BY tc.s_CoverageCode
            ORDER BY si DESC
        ", [$actionId]);

        $this->info("Coverages on action {$actionId} that reach no reinsurance group");
        $this->newLine();

        if (! $rows) {
            $this->info('None — every coverage on this action resolves to a group.');

            return self::SUCCESS;
        }

        $total = 0.0;
        $this->table(
            ['Coverage code', 'Rows', 'Sum insured', 'Sits under', 'Siblings are grouped as', 'Class'],
            array_map(function ($r) use (&$total) {
                $total += (float) $r->si;

                return [
                    $r->coverage,
                    $r->rows_,
                    number_format((float) $r->si, 2),
                    $r->parent_code ?? '—',
                    mb_strimwidth((string) ($r->sibling_groups ?? '—'), 0, 34, '…'),
                    mb_strimwidth((string) ($r->sibling_classes ?? '—'), 0, 18, '…'),
                ];
            }, $rows)
        );

        $this->newLine();
        $this->warn(count($rows) . ' coverage code(s) unreachable, carrying '
            . number_format($total, 2) . ' of sum insured.');
        $this->line('Each needs a reinsurance_group_coverage row, or it will never cede.');

        return self::SUCCESS;
    }

    /** The action to report on — given directly, or the latest on a policy number. */
    private function resolveActionId(): ?int
    {
        if ($this->option('action')) {
            return (int) $this->option('action');
        }

        $policyNo = $this->argument('policy');
        if (! $policyNo) {
            $this->error('Give a policy number, or --action=<id>.');

            return null;
        }

        // policies.policyNumber, not policy_number — the same column
        // FacRegisterService::lookupPolicy() reads.
        $row = DB::selectOne("
            SELECT pa.id
            FROM policy_actions pa
            JOIN policies p ON p.id = pa.policy_id
            WHERE p.policyNumber = ?
              AND pa.deleted_at IS NULL
            ORDER BY pa.id DESC
            LIMIT 1
        ", [trim($policyNo)]);

        if (! $row) {
            $this->error("No policy action found for {$policyNo}.");

            return null;
        }

        return (int) $row->id;
    }
}
