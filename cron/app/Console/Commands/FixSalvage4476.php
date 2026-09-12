<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;

/**
 * Cron-only, ZERO-ARGUMENT wrapper around claims:fix-salvage-miscat, hardcoded
 * to claim 4476 with --apply.
 *
 * WHY: the correction has to run via the cron portal (no production shell
 * access), and the portal runs a bare command name — it could not pass
 * "--claim=4476 --apply". This wrapper bakes those in so the portal entry is
 * just "claims:fix-salvage-4476" with no arguments.
 *
 * It simply delegates to the generic command, so ALL of that command's safety
 * is inherited unchanged: scoped to the single claim, idempotent (a second run
 * finds nothing to correct and is a no-op), void-entangled rows skipped, and a
 * full before/after image written to the Laravel log.
 *
 * ONE-OFF: after it has run and claim 4476 is verified good, disable the portal
 * entry (status = 0). Do NOT generalise this wrapper — the later all-claims
 * sweep uses claims:fix-salvage-miscat with an explicit claim filter.
 */
class FixSalvage4476 extends Command
{
    protected $signature = 'claims:fix-salvage-4476';

    protected $description = 'One-off: correct salvage/subrogation miscategorisation on claim 4476 (wraps claims:fix-salvage-miscat --claim=4476 --apply)';

    public function handle(): int
    {
        return $this->call('claims:fix-salvage-miscat', [
            '--claim' => 4476,
            '--apply' => true,
        ]);
    }
}
