<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Services\Reinsurance\CessionSource;
use AlphaDirect\Services\Reinsurance\RegulatoryCessionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The whole book on both bases, side by side.
 *
 * THIS IS WHAT REINSURANCE SIGNS. Until now the routing change has been argued
 * from one policy — COMG2026213751, where the legacy chain cedes 446,063,000 and
 * the regulatory mapping cedes 63,499,000. One policy is an illustration, not a
 * basis for moving what an insurer reports. This runs every action that has a
 * staged cession and states the movement across the book, by regulatory class,
 * so the sign-off is against a number that can be checked rather than trusted.
 *
 * IT CHANGES NOTHING. Reads policy_reinsurance for the legacy figures and
 * computes the regulatory allocation; with --persist it also stores the
 * regulatory rows in their own table. The live cession stays the legacy chain
 * either way — see RegulatoryCessionService::legacyEngineIsLive().
 *
 * WHY LEGACY IS READ AND NOT RECOMPUTED. The comparison has to be against the
 * figures that were actually stored and reported, not against a fresh run of the
 * legacy engine that might itself differ from what the tab shows. A difference
 * nobody can see on screen is not a difference worth reporting.
 */
class CessionReconciliation extends Command
{
    protected $signature = 'reinsurance:cession-reconciliation
                            {--action=* : Limit to these policy_actions ids}
                            {--persist : Also store the regulatory allocation for each action}
                            {--csv= : Write the per-action detail to this path}
                            {--limit=0 : Stop after this many actions (0 = all)}
                            {--include-test : Include policies flagged is_test_policy}
                            {--cutover-check : Report whether the regulatory basis is safe to switch on}
                            {--all-years : Include actions whose term falls outside the treaty year}
                            {--year=2026 : Treaty year start. 2026 means 1 Jul 2026 to 30 Jun 2027}
                            {--prune : Delete stored regulatory rows for actions outside the scope}';

    protected $description = 'Compare the legacy and regulatory cession bases across the whole book';

    public function handle(RegulatoryCessionService $service, CessionSource $source): int
    {
        if ($this->option('cutover-check')) {
            return $this->cutoverCheck($source);
        }

        if ($this->option('prune')) {
            return $this->prune();
        }

        $actionIds = $this->actionIds();

        if (! $actionIds) {
            $this->warn('No actions carry a staged cession. Nothing to reconcile.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Reconciling %d action(s)%s. The live cession is %s and this command does not change it.',
            count($actionIds),
            $this->option('all-years')
                ? ', ALL YEARS (not just the treaty year)'
                : sprintf(' in the %d/%02d treaty year', (int) $this->option('year'),
                    ((int) $this->option('year') + 1) % 100)
              . ($this->option('include-test') ? ', INCLUDING test policies' : ', test policies excluded'),
            $service->engine()
        ));
        $this->newLine();

        $rows     = [];
        $byClass  = [];
        $failures = [];
        $bar      = $this->output->createProgressBar(count($actionIds));
        $bar->start();

        foreach ($actionIds as $actionId) {
            try {
                $row = $this->reconcileOne($service, (int) $actionId);
                $rows[] = $row;

                foreach ($row['classes'] as $class => $fig) {
                    $byClass[$class]['legacy_si']     = ($byClass[$class]['legacy_si'] ?? 0) + $fig['legacy_si'];
                    $byClass[$class]['regulatory_si'] = ($byClass[$class]['regulatory_si'] ?? 0) + $fig['regulatory_si'];
                    $byClass[$class]['actions']       = ($byClass[$class]['actions'] ?? 0) + 1;
                }
            } catch (\Throwable $e) {
                // One bad action must not stop the book. A reconciliation that
                // silently skips is worse than one that reports what it could
                // not do, so failures are collected and printed.
                $failures[] = ['action' => $actionId, 'why' => $e->getMessage()];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->summary($rows, $byClass);

        if ($failures) {
            $this->newLine();
            $this->error(count($failures) . ' action(s) could not be reconciled:');
            $this->table(
                ['Action', 'Why'],
                array_map(fn ($f) => [$f['action'], mb_strimwidth($f['why'], 0, 88, '…')], $failures)
            );
        }

        if ($path = $this->option('csv')) {
            $this->writeCsv($path, $rows);
        }

        return self::SUCCESS;
    }

    /**
     * Delete stored allocations for actions this command no longer covers.
     *
     * FOUND BY THE DRY RUN, and it would have been found by nobody else. The
     * table was first populated before the treaty-year scope existed, so it
     * held 48 actions of which only 9 belonged to 2026/27. The other 39 were
     * allocated on this year's terms -- a 10,000,000 first line, a 40,000,000
     * surplus -- against policies written under different ones.
     *
     * That is not a harmless leftover. On the regulatory basis the facultative
     * coverage gap read those rows and reported 793,000,000 of facultative
     * required on COMG2025146236, whose term ran 1 to 31 MARCH 2025. Switching
     * the basis with them in place would have put that figure in front of
     * Reinsurance.
     *
     * Scoped exactly as the reconciliation is, so pruning and persisting always
     * agree about which actions belong.
     */
    private function prune(): int
    {
        $keep = $this->actionIds();

        $stale = DB::table('policy_reinsurance_regulatory')
            ->when($keep, fn ($q) => $q->whereNotIn('action_id', $keep))
            ->distinct()
            ->pluck('action_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        if (! $stale) {
            $this->info('Nothing stored outside the scope. Nothing to prune.');

            return self::SUCCESS;
        }

        $rows = DB::table('policy_reinsurance_regulatory')->whereIn('action_id', $stale)->count();

        $this->warn(sprintf(
            '%d action(s) are stored but outside the scope, carrying %s row(s).',
            count($stale),
            number_format($rows)
        ));
        $this->line('They were allocated on terms that are not theirs, and on the regulatory');
        $this->line('basis every reader would treat them as live.');
        $this->newLine();

        DB::table('policy_reinsurance_regulatory')->whereIn('action_id', $stale)->delete();

        $this->info('Deleted. ' . number_format(
            DB::table('policy_reinsurance_regulatory')->distinct()->count('action_id')
        ) . ' action(s) remain, all inside the scope.');

        return self::SUCCESS;
    }

    /**
     * Is it safe to switch the basis on?
     *
     * ONE QUESTION ONLY: has every action with a staged cession been allocated
     * on the regulatory basis? An action that has not would report nil the
     * moment the flag moves, and nil cession on a live policy understates the
     * reinsurance asset without anybody seeing an error.
     *
     * This is a pre-flight, not an approval. It says nothing about whether the
     * ROUTING has been signed off, which is the other half and the half that is
     * not ours to answer.
     */
    private function cutoverCheck(CessionSource $source): int
    {
        $this->line('<options=bold>Cutover pre-flight</>');
        $this->newLine();

        $this->line('  Live basis          : ' . $source->basis());

        // SCOPED THE SAME WAY THE PERSIST IS, or this can never pass. Counting
        // every staged action makes the out-of-year ones look missing, when they
        // are precisely the ones that must NOT be stored -- and --prune exists
        // to delete them. The first version of this check did exactly that and
        // reported 43 phantom gaps the moment pruning worked correctly.
        $inScope = $this->actionIds();
        $done    = DB::table('policy_reinsurance_regulatory')
            ->whereNull('deleted_at')
            ->when($inScope, fn ($q) => $q->whereIn('action_id', $inScope))
            ->distinct()->count('action_id');

        $this->line('  Actions in scope       : ' . number_format(count($inScope)));
        $this->line('  Allocated on regulatory: ' . number_format($done));
        $this->newLine();

        // Stale is as dangerous as missing, and less obvious: an action stored
        // from outside the scope carries an allocation computed on terms that
        // are not its own, and every reader would treat it as live.
        $stale = $inScope
            ? DB::table('policy_reinsurance_regulatory')
                ->whereNotIn('action_id', $inScope)->distinct()->count('action_id')
            : 0;

        if ($stale > 0) {
            $this->error($stale . ' action(s) are stored but outside the treaty year.');
            $this->line('Run this command with --prune before switching the basis.');
            $this->newLine();
        }

        $stored  = DB::table('policy_reinsurance_regulatory')
            ->whereNull('deleted_at')->distinct()->pluck('action_id')
            ->map(fn ($v) => (int) $v)->all();
        $missing = array_values(array_diff($inScope, $stored));

        if (! $missing) {
            $this->info('Every staged action has a regulatory allocation.');
            $this->line('Nothing would report nil on a switch. The ROUTING sign-off is a separate');
            $this->line('question and this command does not speak to it.');

            return self::SUCCESS;
        }

        $this->error(count($missing) . ' action(s) would report NIL cession if the basis were switched now.');
        $this->line('Run this command with --persist before going any further.');
        $this->newLine();
        $this->line('  ' . implode(', ', array_slice($missing, 0, 40))
            . (count($missing) > 40 ? ' … and ' . (count($missing) - 40) . ' more' : ''));

        return self::FAILURE;
    }

    /** @return int[] */
    private function actionIds(): array
    {
        if ($given = $this->option('action')) {
            return array_map('intval', $given);
        }

        $q = DB::table('policy_reinsurance_details as prid')
            ->distinct()
            ->orderBy('prid.action_id')
            ->select('prid.action_id');

        // TEST POLICIES ARE OUT BY DEFAULT. This total is what Reinsurance is
        // asked to sign; a book inflated by three demonstration policies is not
        // a figure anyone should put their name to. --include-test brings them
        // back for our own checking.
        if (! $this->option('include-test')) {
            $q->join('policy_actions as pa', 'pa.id', '=', 'prid.action_id')
              ->join('policies as p', 'p.id', '=', 'pa.policy_id')
              ->where(function ($w) {
                  $w->where('p.is_test_policy', 0)->orWhereNull('p.is_test_policy');
              });
        }

        /*
         * AND SO IS ANYTHING OUTSIDE THE TREATY YEAR.
         *
         * This was missed on the first run and it mattered: 39 of 48 actions had
         * terms that never touched 1 Jul 2026 - 30 Jun 2027. COMG2025153300 ran
         * 11 Jan to 10 Apr 2025 and expired more than a year before this treaty
         * incepted. Allocating it on 2026/27 terms -- a 10,000,000 first line, a
         * 40,000,000 surplus, this year's class limits -- produces a figure that
         * describes nothing, and the book total built from those figures
         * described nothing either.
         *
         * A policy is in scope if its term OVERLAPS the treaty year at all, not
         * if it sits wholly inside it: a policy incepting 1 Mar 2027 is written
         * under this treaty for the part of its term that falls in the year.
         */
        if (! $this->option('all-years')) {
            $start = ((int) $this->option('year')) . '-07-01';
            $end   = ((int) $this->option('year') + 1) . '-06-30';

            $q->join('policy_actions as pay', 'pay.id', '=', 'prid.action_id')
              ->join('policy_term as pt', 'pt.id', '=', 'pay.term_id')
              ->whereDate('pt.term_start_date', '<=', $end)
              ->where(function ($w) use ($start) {
                  $w->whereNull('pt.term_end_date')
                    ->orWhereDate('pt.term_end_date', '>=', $start);
              });
        }

        $ids = $q->pluck('prid.action_id')->map(fn ($v) => (int) $v)->all();

        $limit = (int) $this->option('limit');

        return $limit > 0 ? array_slice($ids, 0, $limit) : $ids;
    }

    /**
     * One action on both bases.
     *
     * @return array<string,mixed>
     */
    private function reconcileOne(RegulatoryCessionService $service, int $actionId): array
    {
        if ($this->option('persist')) {
            $service->persist($actionId, 'reinsurance:cession-reconciliation');
        }

        $allocation = $service->allocate($actionId);
        $legacy     = $service->legacyTotals($actionId);

        $regCeded = (float) ($allocation['totals']['ceded_si'] ?? 0);
        // NOT a cession -- see RegulatoryCessionService::legacyTotals(). Carried
        // for context and never subtracted from the line above.
        $legAlloc = (float) ($legacy['allocated_si'] ?? 0);

        // Per class, so the movement can be read where it actually happens
        // rather than only in one total.
        $classes = [];
        foreach ($allocation['by_mapping'] ?? [] as $class => $fig) {
            $classes[$class] = [
                'legacy_si'     => 0.0,
                'regulatory_si' => (float) ($fig['ceded_si'] ?? 0),
            ];
        }

        return [
            'action_id'      => $actionId,
            'policy'         => $this->policyNumber($actionId),
            'sum_insured'    => (float) ($allocation['totals']['sum_insured'] ?? 0),
            'legacy_allocated' => $legAlloc,
            'regulatory_ceded' => $regCeded,
            'formulas'         => (int) ($legacy['formulas'] ?? 0),
            'exceptions'     => count($allocation['exceptions']),
            'classes'        => $classes,
        ];
    }

    private function policyNumber(int $actionId): ?string
    {
        static $cache = [];

        if (array_key_exists($actionId, $cache)) {
            return $cache[$actionId];
        }

        $n = DB::table('policy_actions as pa')
            ->join('policies as p', 'p.id', '=', 'pa.policy_id')
            ->where('pa.id', $actionId)
            ->value('p.policyNumber');

        return $cache[$actionId] = $n ? (string) $n : null;
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @param  array<string,array<string,float>>  $byClass
     */
    private function summary(array $rows, array $byClass): void
    {
        $legacy = array_sum(array_column($rows, 'legacy_allocated'));
        $reg    = array_sum(array_column($rows, 'regulatory_ceded'));
        $si     = array_sum(array_column($rows, 'sum_insured'));

        $this->line('<options=bold>The book, both bases</>');
        $this->newLine();

        $this->table(
            ['', 'Sum insured', 'Regulatory cession', 'Legacy allocated'],
            [[
                number_format(count($rows)) . ' action(s)',
                number_format($si, 2),
                number_format($reg, 2),
                number_format($legacy, 2),
            ]]
        );

        $this->newLine();
        $this->line('  NO DELTA IS SHOWN, and that is deliberate. The legacy column is what the');
        $this->line('  old chain ALLOCATED across every layer and every formula -- the retained');
        $this->line('  leg alongside the ceded one, counted once per formula -- so subtracting it');
        $this->line('  from a cession measures nothing. The case for the regulatory basis is that');
        $this->line("  it reproduces Reinsurance's own working to the cent, not that it differs");
        $this->line('  from a figure nobody can define.');

        $exceptions = array_sum(array_column($rows, 'exceptions'));
        if ($exceptions) {
            $this->line("  {$exceptions} risk unit(s) carry an exception and are 100% retained.");
        }

        if ($byClass) {
            $this->newLine();
            $this->line('<options=bold>By regulatory class</>');
            $this->newLine();

            ksort($byClass);
            $this->table(
                ['Class', 'Actions', 'Regulatory ceded'],
                array_map(fn ($k, $v) => [
                    $k,
                    $v['actions'],
                    number_format($v['regulatory_si'], 2),
                ], array_keys($byClass), $byClass)
            );
        }

        // The largest movers, because a book total hides which policies caused it.
        $movers = array_filter($rows, fn ($r) => $r['sum_insured'] > 0);
        usort($movers, fn ($a, $b) => $b['sum_insured'] <=> $a['sum_insured']);

        if ($movers) {
            $this->newLine();
            $this->line('<options=bold>Largest risks</>');
            $this->newLine();

            $this->table(
                ['Action', 'Policy', 'Sum insured', 'Regulatory cession', 'Legacy allocated', 'Formulas'],
                array_map(fn ($r) => [
                    $r['action_id'],
                    $r['policy'] ?? '—',
                    number_format($r['sum_insured'], 2),
                    number_format($r['regulatory_ceded'], 2),
                    number_format($r['legacy_allocated'], 2),
                    $r['formulas'],
                ], array_slice($movers, 0, 15))
            );

            if (count($movers) > 15) {
                $this->line('  … and ' . (count($movers) - 15) . ' more. Use --csv for the full list.');
            }
        }
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function writeCsv(string $path, array $rows): void
    {
        $fh = @fopen($path, 'w');

        if ($fh === false) {
            $this->error("Could not open {$path} for writing.");

            return;
        }

        fputcsv($fh, ['action_id', 'policy', 'sum_insured', 'regulatory_ceded',
                      'legacy_allocated_not_a_cession', 'formulas', 'exceptions']);

        foreach ($rows as $r) {
            fputcsv($fh, [
                $r['action_id'],
                $r['policy'],
                number_format($r['sum_insured'], 2, '.', ''),
                number_format($r['regulatory_ceded'], 2, '.', ''),
                number_format($r['legacy_allocated'], 2, '.', ''),
                $r['formulas'],
                $r['exceptions'],
            ]);
        }

        fclose($fh);
        $this->newLine();
        $this->info("Per-action detail written to {$path}");
    }
}
