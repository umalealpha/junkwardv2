<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Services\Reinsurance\TreatyRolloverException;
use AlphaDirect\Services\Reinsurance\TreatyRolloverService;
use Illuminate\Console\Command;

/**
 * CLI wrapper around TreatyRolloverService. Same behaviour as the UI
 * "Rollover" button on the treaty page — delegates to the shared service.
 *
 * Usage:
 *   php artisan treaty:rollover --source=16 --dry-run
 *   php artisan treaty:rollover --source=16
 *   php artisan treaty:rollover --source=16 --name=MUNICH_DOM_2025_2026
 *   php artisan treaty:rollover --source=17 --effective-from=2026-11-12 --effective-to=2027-11-11
 */
class RolloverTreaty extends Command
{
    protected $signature = 'treaty:rollover
                            {--source= : source treaty id to clone}
                            {--name= : new treaty name (default: auto-derive by bumping year in source name)}
                            {--number= : new treaty number (default: same as new name)}
                            {--effective-from= : new effective_from date (default: source.effective_to + 1 day)}
                            {--effective-to= : new effective_to date (default: +1 year from new effective_from)}
                            {--dry-run : print plan and roll back instead of committing}
                            {--force : override safety checks (duplicate name, overlap)}';

    protected $description = 'Clone a reinsurance_treaty + its treaty_details into a new treaty for a new period (rollover)';

    public function handle(TreatyRolloverService $service): int
    {
        $sourceId = (int) ($this->option('source') ?? 0);
        if ($sourceId <= 0) {
            $this->error('--source is required');
            return 1;
        }

        try {
            $result = $service->rollover($sourceId, [
                'name'           => $this->option('name')           ?: null,
                'number'         => $this->option('number')         ?: null,
                'effective_from' => $this->option('effective-from') ?: null,
                'effective_to'   => $this->option('effective-to')   ?: null,
                'dry_run'        => (bool) $this->option('dry-run'),
                'force'          => (bool) $this->option('force'),
                'actor'          => 'cli',
            ]);
        } catch (TreatyRolloverException $e) {
            $this->error($e->getMessage());
            return 1;
        } catch (\Throwable $e) {
            $this->error('Unexpected error: ' . $e->getMessage());
            return 2;
        }

        foreach ($result['log'] as $line) $this->line('  ' . $line);
        $this->info('');
        if ($result['dry_run']) {
            $this->warn('DRY-RUN — nothing was committed. Re-run without --dry-run to apply.');
        } else {
            $this->info("Done. New treaty id {$result['new_treaty_id']} active from {$result['effective_from']} to {$result['effective_to']}.");
            $this->info('Verify with `php artisan treaty:check`. To retire the old treaty:');
            $this->info("  UPDATE reinsurance_treaty SET status=0 WHERE id={$result['source_id']};");
        }
        return 0;
    }
}
