<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Services\Reinsurance\FacSlipService;
use Illuminate\Console\Command;

/**
 * Generate the slip document for every live slip that does not have one yet.
 *
 * Generation only. Slips are NOT emailed by this command unless
 * fac.slips.auto_send has been deliberately turned on — an outbound slip is a
 * contractual communication to a reinsurer.
 */
class FacGenerateSlips extends Command
{
    protected $signature = 'fac:generate-slips {--limit=200}';

    protected $description = 'Generate FAC slip documents for placements that do not have one yet';

    public function handle(FacSlipService $slips): int
    {
        $r = $slips->generateMissing((int) $this->option('limit'));

        $this->info("Generated {$r['generated']} slip(s). Skipped {$r['skipped']}.");
        foreach ($r['errors'] as $e) {
            $this->warn('Skipped — ' . $e);
        }

        if (config('fac.slips.auto_send')) {
            $this->warn('fac.slips.auto_send is ON — generated slips were emailed to counterparties.');
        } else {
            $this->line('Slips were generated but not sent. Send them from the FAC Settlements screen.');
        }

        return self::SUCCESS;
    }
}
