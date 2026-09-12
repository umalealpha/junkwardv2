<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Configure the 2026/27 regulatory mapping, through the framework connection.
 *
 * The equivalent scripts in backend/database/manual/ read RI_DB_* from the
 * environment and are run by hand. This does the same three steps using the
 * application's own connection, so it is repeatable, needs no credentials passed
 * around, and can be run by anyone who can run artisan.
 *
 * THE MAPPING TABLE LIVES IN config/reinsurance.php, not here. It was in the
 * script and is now in one place, so the command and anything else that needs it
 * read the same table and cannot drift apart.
 *
 * THREE STEPS, IN ORDER, EACH SAFE TO REPEAT:
 *
 *   mapping      set regulatory_mapping on every group's coverage rows
 *   engineering  put the Engineering coverages into ENGINEERING_AND_BI_COM
 *   orphans      place sub-coverages that reach no group into their siblings' groups
 *
 * DRY RUN BY DEFAULT. Nothing is written without --apply, and every step reports
 * what it would do first.
 *
 *   php artisan reinsurance:configure-mapping
 *   php artisan reinsurance:configure-mapping --apply
 *   php artisan reinsurance:configure-mapping --step=mapping --apply
 */
class ConfigureRegulatoryMapping extends Command
{
    protected $signature = 'reinsurance:configure-mapping
                            {--step=all : all, mapping, engineering or orphans}
                            {--apply : Write. Without it nothing is changed}';

    protected $description = 'Configure the 2026/27 regulatory mapping — dry run unless --apply';

    /** Coverages that belong in the Engineering group, with the authority for each. */
    private const ENGINEERING = [
        'PLANTALLRISKS'      => 'RI-10 point 12, 24 Aug 2026',
        'MACHINERYBREAKDOWN' => 'RI-10 point 12, 24 Aug 2026',
        'ERECTIONALLRISKS'   => 'Reinsurance, 31 Aug 2026',
    ];

    private bool $apply = false;

    public function handle(): int
    {
        $this->apply = (bool) $this->option('apply');
        $step        = (string) $this->option('step');

        $this->line('connection : ' . DB::connection()->getName()
            . '  database: ' . DB::connection()->getDatabaseName());
        $this->line('mode       : ' . ($this->apply ? 'APPLY' : 'DRY RUN'));
        $this->newLine();

        if (! in_array($step, ['all', 'mapping', 'engineering', 'orphans'], true)) {
            $this->error("Unknown step '{$step}'. Use all, mapping, engineering or orphans.");

            return self::FAILURE;
        }

        $rc = self::SUCCESS;

        if ($step === 'all' || $step === 'mapping') {
            $rc = max($rc, $this->stepMapping());
        }
        if ($step === 'all' || $step === 'engineering') {
            $rc = max($rc, $this->stepEngineering());
        }
        if ($step === 'all' || $step === 'orphans') {
            $rc = max($rc, $this->stepOrphans());
        }

        if (! $this->apply) {
            $this->newLine();
            $this->warn('DRY RUN — nothing was written. Re-run with --apply.');
        }

        return $rc;
    }

    // ─────────────────────────────────────────────────────────── step 1

    /**
     * Set regulatory_mapping on every group's coverage rows.
     *
     * A group the table does not know is a STOP, not a warning: leaving it
     * unmapped means its whole class retains silently, and nobody finds out until
     * a cession comes up short.
     */
    private function stepMapping(): int
    {
        $this->info('Step 1 — regulatory mapping by group');

        $map     = (array) config('reinsurance.group_mappings', []);
        $unmapped = (array) config('reinsurance.unmapped_groups', []);

        $groups = DB::select('
            SELECT g.id, g.group_code, COUNT(gc.id) AS rows_
            FROM reinsurance_group g
            LEFT JOIN reinsurance_group_coverage gc ON gc.group_id = g.id
            GROUP BY g.id, g.group_code
            ORDER BY g.group_code
        ');

        $unknown = [];
        foreach ($groups as $g) {
            if (! isset($map[$g->group_code]) && ! isset($unmapped[$g->group_code])) {
                $unknown[] = $g->group_code . ' (' . $g->rows_ . ' rows)';
            }
        }

        if ($unknown) {
            $this->error('ABORTED — group(s) neither mapped nor deliberately skipped:');
            foreach ($unknown as $u) {
                $this->line('  ' . $u);
            }
            $this->line("Add each to 'group_mappings' or 'unmapped_groups' in config/reinsurance.php.");

            return self::FAILURE;
        }

        $changed = 0;
        $rows    = [];

        foreach ($groups as $g) {
            if (isset($unmapped[$g->group_code]) || (int) $g->rows_ === 0) {
                continue;
            }

            $want = $map[$g->group_code];
            $off  = DB::table('reinsurance_group_coverage')
                ->where('group_id', $g->id)
                ->where(function ($q) use ($want) {
                    $q->whereNull('regulatory_mapping')->orWhere('regulatory_mapping', '!=', $want);
                })
                ->count();

            if ($off === 0) {
                continue;
            }

            $rows[] = [$g->group_code, $want, $off];
            $changed += $off;

            if ($this->apply) {
                DB::table('reinsurance_group_coverage')
                    ->where('group_id', $g->id)
                    ->update(['regulatory_mapping' => $want]);
            }
        }

        if (! $rows) {
            $this->line('  Every group already carries its class. Nothing to do.');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->table(['Group', 'Class', 'Rows to set'], $rows);
        $this->line('  ' . $changed . ' row(s) ' . ($this->apply ? 'updated' : 'would be updated'));
        $this->line('  Reversible: UPDATE reinsurance_group_coverage SET regulatory_mapping = NULL;');
        $this->newLine();

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────── step 2

    /**
     * Put the Engineering coverages into ENGINEERING_AND_BI_COM, which exists and
     * is empty — so every Engineering coverage currently routes nowhere and
     * reports as retained by omission rather than by decision.
     *
     * Contractors All Risks is deliberately absent: 24 August put it under
     * Property and the 31 August wording reads as Engineering. Both classes are
     * layered and run the identical cascade, so no cession figure turns on it —
     * only the class the return reads, which is not ours to pick.
     */
    private function stepEngineering(): int
    {
        $this->info('Step 2 — Engineering group');

        $group = DB::table('reinsurance_group')->where('group_code', 'ENGINEERING_AND_BI_COM')->first();
        if (! $group) {
            $this->error('  ENGINEERING_AND_BI_COM does not exist. Create it first.');

            return self::FAILURE;
        }

        $existing = DB::table('reinsurance_group_coverage')
            ->where('group_id', $group->id)->pluck('coverage_name')
            ->map(fn ($c) => strtoupper((string) $c))->all();

        $rows = [];
        $plan = [];

        foreach (self::ENGINEERING as $code => $authority) {
            $cvg = DB::table('tb_cvgpccoverages')->where('s_CoverageCode', $code)->first();

            if (! $cvg) {
                $rows[] = [$code, '—', $authority, 'no such coverage code'];
                continue;
            }
            if (in_array(strtoupper($code), $existing, true)) {
                $rows[] = [$code, $cvg->id, $authority, 'already in the group'];
                continue;
            }

            $plan[] = ['id' => $cvg->id, 'code' => $code];
            $rows[] = [$code, $cvg->id, $authority, 'INSERT'];
        }

        $this->table(['Coverage', 'Id', 'Authority', 'Action'], $rows);

        if (! $plan) {
            $this->line('  Nothing to insert.');
            $this->newLine();

            return self::SUCCESS;
        }

        if ($this->apply) {
            $ids = [];
            DB::transaction(function () use ($plan, $group, &$ids) {
                foreach ($plan as $row) {
                    $ids[] = DB::table('reinsurance_group_coverage')->insertGetId([
                        'group_id'           => $group->id,
                        'coverage_id'        => $row['id'],
                        'coverage_name'      => $row['code'],
                        'si_premium'         => '',
                        'ri_limit'           => '',
                        'limit_value'        => '',
                        'regulatory_mapping' => 'Engineering',
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);
                }
            });
            $this->line('  inserted row ids: ' . implode(',', $ids));
            $this->line('  ROLLBACK: DELETE FROM reinsurance_group_coverage WHERE id IN ('
                . implode(',', $ids) . ');');
        } else {
            $this->line('  ' . count($plan) . ' row(s) would be inserted.');
        }

        $this->newLine();

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────── step 3

    /**
     * Place sub-coverages that reach no group into the groups their SIBLINGS
     * occupy.
     *
     * A sub-coverage with no group has no class, so it does not report as ceded,
     * does not report as retained, does not report at all. The class is not
     * chosen here: it is read from where the other children of the same parent
     * already sit. A code whose siblings span two different classes is reported
     * and skipped.
     */
    private function stepOrphans(): int
    {
        $this->info('Step 3 — sub-coverages reaching no group');

        $orphans = DB::select("
            SELECT tc.s_CoverageCode                             AS code,
                   MAX(parent.s_CoverageCode)                    AS parent_code,
                   SUM(COALESCE(cvgsm.coverage_value, 0))        AS si,
                   GROUP_CONCAT(DISTINCT sib.group_id)           AS group_ids,
                   GROUP_CONCAT(DISTINCT sib.group_code)         AS group_codes,
                   COUNT(DISTINCT sib.regulatory_mapping)        AS class_count,
                   GROUP_CONCAT(DISTINCT sib.regulatory_mapping) AS classes
            FROM policy_coverages cvgm
            JOIN policy_coverage_detail cvgsm  ON cvgsm.policy_coverage_id = cvgm.id
            JOIN tb_cvgpccoverages tc          ON tc.id = cvgsm.coverage_id
            LEFT JOIN tb_cvgpccoverages parent ON parent.id = cvgm.coverage_id
            LEFT JOIN reinsurance_group_coverage own ON own.coverage_name = tc.s_CoverageCode
            LEFT JOIN (
                SELECT DISTINCT d.policy_coverage_id, g.id AS group_id,
                       g.group_code, gc.regulatory_mapping
                FROM policy_coverage_detail d
                JOIN tb_cvgpccoverages t           ON t.id = d.coverage_id
                JOIN reinsurance_group_coverage gc ON gc.coverage_name = t.s_CoverageCode
                JOIN reinsurance_group g           ON g.id = gc.group_id
                WHERE d.deleted_at IS NULL
            ) sib ON sib.policy_coverage_id = cvgm.id
            WHERE cvgm.deleted_at IS NULL
              AND cvgsm.deleted_at IS NULL
              AND own.id IS NULL
            GROUP BY tc.s_CoverageCode
            HAVING group_ids IS NOT NULL
            ORDER BY si DESC
        ");

        if (! $orphans) {
            $this->line('  Every sub-coverage in use reaches a group. Nothing to do.');
            $this->newLine();

            return self::SUCCESS;
        }

        $rows = [];
        $plan = [];

        foreach ($orphans as $o) {
            if ((int) $o->class_count > 1) {
                $rows[] = [$o->code, $o->parent_code ?? '?', number_format((float) $o->si, 2),
                    'SKIPPED — siblings span ' . $o->classes];
                continue;
            }

            foreach (array_filter(explode(',', (string) $o->group_ids)) as $gid) {
                $plan[] = ['code' => $o->code, 'group_id' => (int) $gid, 'mapping' => $o->classes];
            }

            $rows[] = [$o->code, $o->parent_code ?? '?', number_format((float) $o->si, 2),
                'joins ' . $o->group_codes . ' as ' . $o->classes];
        }

        $this->table(['Coverage', 'Sits under', 'Sum insured', 'Action'], $rows);

        if (! $plan) {
            $this->line('  Nothing to insert.');
            $this->newLine();

            return self::SUCCESS;
        }

        if ($this->apply) {
            $ids = [];
            DB::transaction(function () use ($plan, &$ids) {
                foreach ($plan as $row) {
                    $cvgId = DB::table('tb_cvgpccoverages')
                        ->where('s_CoverageCode', $row['code'])->value('id');

                    $ids[] = DB::table('reinsurance_group_coverage')->insertGetId([
                        'group_id'           => $row['group_id'],
                        'coverage_id'        => $cvgId,
                        'coverage_name'      => $row['code'],
                        'si_premium'         => '',
                        'ri_limit'           => '',
                        'limit_value'        => '',
                        'regulatory_mapping' => $row['mapping'],
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);
                }
            });
            $this->line('  ' . count($ids) . ' row(s) inserted.');
            $this->line('  ROLLBACK: DELETE FROM reinsurance_group_coverage WHERE id IN ('
                . implode(',', $ids) . ');');
        } else {
            $this->line('  ' . count($plan) . ' row(s) would be inserted.');
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
