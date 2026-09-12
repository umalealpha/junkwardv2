<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Services\Reinsurance\FacCoverageService;
use Illuminate\Console\Command;

/**
 * "Which policies should be FAC'ed and are not?"
 *
 * Driven FROM Graphite's own reinsurance computation, so a policy missing from
 * the register entirely still shows up — which is exactly the case worth finding.
 */
class FacCoverageScan extends Command
{
    protected $signature = 'fac:coverage-scan {--all : include policies that are properly covered} {--limit=5000}';

    protected $description = 'Compare the facultative cover Graphite says each policy needs against what the FAC register carries';

    public function handle(FacCoverageService $coverage): int
    {
        $rows = $coverage->scan(!$this->option('all'), (int) $this->option('limit'));

        if (!$rows) {
            $this->info('No exceptions. Every policy needing facultative cover is accounted for in the register.');
            return self::SUCCESS;
        }

        $this->table(
            ['Policy', 'Verdict', 'Required (BWP)', 'Placed (BWP)', 'Gap (BWP)', 'Lines'],
            array_map(fn ($r) => [
                $r['policyNumber'],
                $r['verdictLabel'],
                number_format($r['requiredPremium'], 2),
                number_format($r['placedPremium'], 2),
                number_format($r['gap'], 2),
                $r['placedCount'],
            ], array_slice($rows, 0, 60))
        );

        if (count($rows) > 60) {
            $this->line('… ' . (count($rows) - 60) . ' more. Use the FAC Register screen for the full list.');
        }

        $s = $coverage->scanSummary();
        $this->newLine();
        $this->warn(sprintf(
            '%d policies need facultative cover. %d covered, %d with nothing placed, %d under-placed, %d over-placed.',
            $s['policiesRequiringFac'], $s['covered'], $s['nonePlaced'], $s['underPlaced'], $s['overPlaced']
        ));
        $this->warn(sprintf(
            'Ceded premium with no placement in the register: BWP %s.',
            number_format($s['exposureUnplacedBwp'], 2)
        ));

        return self::SUCCESS;
    }
}
