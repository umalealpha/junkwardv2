<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Services\Reinsurance\FacRegisterService;
use Illuminate\Console\Command;

/**
 * Daily premium-payment-warranty watch, plus the automatic client-paid sweep.
 *
 * Idempotent: each placement is warned once and breached once, guarded by the
 * ppw_warned_at / ppw_breached_at stamps, so re-running never re-notifies.
 */
class FacPpwWatch extends Command
{
    protected $signature = 'fac:ppw-watch {--skip-payments : only run the PPW watch, not the client-paid sweep}';

    protected $description = 'Warn on premium payment warranties falling due, flag breaches, and pick up client premiums from Graphite';

    public function handle(FacRegisterService $register): int
    {
        $ppw = $register->runPpwWatch();
        $this->info("PPW watch: {$ppw['warned']} warned, {$ppw['breached']} breached.");

        if (!$this->option('skip-payments')) {
            $sweep = $register->sweepClientPayments();
            $this->info("Client-premium sweep: {$sweep['checked']} checked, {$sweep['marked']} marked as paid.");
        }

        return self::SUCCESS;
    }
}
