<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Services\Reinsurance\FacAutoEntryService;
use Illuminate\Console\Command;

/**
 * Raise the next FAC line for instalment policies that already carry a live
 * placement, from the slip's own terms.
 *
 * Rows land as DRAFT. They do not count towards the payable until somebody
 * confirms them.
 */
class FacAutoEntries extends Command
{
    protected $signature = 'fac:auto-entries {--lookback= : days of policy transactions to consider}';

    protected $description = 'Raise draft FAC placements for new instalments on policies that already carry a placement';

    public function handle(FacAutoEntryService $service): int
    {
        $r = $service->run($this->option('lookback') ? (int) $this->option('lookback') : null);

        $this->info("Considered {$r['considered']} policy transactions. Created {$r['created']} draft placement(s).");

        foreach ($r['skipped'] as $s) {
            $this->warn('Skipped — ' . $s);
        }

        return self::SUCCESS;
    }
}
