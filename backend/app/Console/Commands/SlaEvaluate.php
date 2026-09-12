<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Services\SlaEvaluator;
use Illuminate\Console\Command;

/**
 * Scans open SLAs and fires 75%/90% warnings + breach notifications. Scheduled
 * every 5 minutes. No-ops while the SLA feature flag is off.
 */
class SlaEvaluate extends Command
{
    protected $signature = 'hd:sla-evaluate';
    protected $description = 'Evaluate Help Desk SLAs — fire warnings (75%/90%) and breach notifications.';

    public function handle(SlaEvaluator $evaluator): int
    {
        $stats = $evaluator->run();
        $this->info('SLA evaluate: ' . json_encode($stats));
        return self::SUCCESS;
    }
}
