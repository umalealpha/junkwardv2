<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Services\Claims\MotolinkAssessmentBridge;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * claims:motolink-sync — MotoLink (motolink.app) INBOUND assessment bridge.
 *
 * Pulls vehicle-damage assessments from motolink.app and mirrors them onto the
 * matching Graphite claim (see MotolinkAssessmentBridge). Ported from the
 * Claims Tracker's js/motolink.js.
 *
 * OFF / DARK BY DEFAULT: with no MOTOLINK_API_KEY configured (and mock off) the
 * bridge reports "not configured" and does NOTHING — no outbound HTTP, no DB
 * write, no error. Safe to schedule everywhere while unconfigured.
 *
 * Scheduled every 15 min in BOTH the backend and cron kernels (the live tick
 * runs from the cron/ twin app — see CLAUDE memory: cron/ duplicates backend/).
 *
 * Options:
 *   --dry-run  fetch + match only; write nothing to the DB.
 */
class ClaimsMotolinkSync extends Command
{
    protected $signature = 'claims:motolink-sync {--dry-run : Fetch + match only, write nothing}';

    protected $description = 'Pull motolink.app assessments and mirror them onto matching claims (no-op unless MOTOLINK_API_KEY is configured).';

    public function handle(MotolinkAssessmentBridge $bridge): int
    {
        if (!$bridge->isEnabled()) {
            $this->info('MotoLink bridge disabled — set MOTOLINK_API_BASE + MOTOLINK_API_KEY to enable. Nothing to do.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $bridge->syncOnce($dryRun);
        } catch (\Throwable $e) {
            Log::error('[motolink] sync failed', ['error' => $e->getMessage()]);
            $this->error('MotoLink sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (!empty($result['skipped'])) {
            $this->info('MotoLink bridge skipped: ' . ($result['reason'] ?? 'not configured'));
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'MotoLink sync%s: scanned=%d matched=%d updated=%d writeOffs=%d unmatched=%d errors=%d in %dms',
            $dryRun ? ' (dry-run)' : '',
            $result['scanned'], $result['matched'], $result['updated'],
            $result['writeOffs'], count($result['unmatched']), $result['errors'],
            $result['ms'] ?? 0
        ));

        return self::SUCCESS;
    }
}
