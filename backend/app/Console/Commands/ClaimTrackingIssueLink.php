<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Services\ClaimTrackingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Issue a claimant self-service tracking link for a claim (Claims Tracker ->
 * Graphite Phase 2). Explicit operator action, so it runs regardless of the
 * `claimant_tracking` runtime flag — a link can be minted and tested before
 * the public surface is armed.
 *
 * Prints the opaque token and the full deep-link URL. The link is time-limited
 * (default 90 days, matching the tracker) and revocable.
 *
 * Usage:
 *   php artisan claims:issue-tracking-link 12345
 *   php artisan claims:issue-tracking-link 12345 --ttl-days=30
 *   php artisan claims:issue-tracking-link CLM-000123 --by=ops@alphadirect.co.bw
 */
class ClaimTrackingIssueLink extends Command
{
    protected $signature = 'claims:issue-tracking-link
        {claim : Claim id, claim_number or external_ref}
        {--ttl-days= : Link lifetime in days (default 90)}
        {--by= : Who issued the link (audit label)}';

    protected $description = 'Issue a claimant self-service tracking link for a claim.';

    public function handle(ClaimTrackingService $svc): int
    {
        $ref = (string) $this->argument('claim');

        $claimId = ctype_digit($ref)
            ? (int) $ref
            : (int) (DB::table('claims')
                ->where('claim_number', $ref)
                ->orWhere('external_ref', $ref)
                ->orderByDesc('id')
                ->value('id'));

        if (!$claimId || !DB::table('claims')->where('id', $claimId)->exists()) {
            $this->error("No claim found for: {$ref}");
            return self::FAILURE;
        }

        $ttl = $this->option('ttl-days') !== null ? (int) $this->option('ttl-days') : null;
        $by  = $this->option('by') ?: 'command';

        $link = $svc->issueLink($claimId, $by, $ttl, 'command');

        $url = rtrim((string) config('app.url'), '/') . '/claim-status/' . $link->token;

        $this->info('Claim tracking link issued.');
        $this->line('  claim_id : ' . $claimId);
        $this->line('  token    : ' . $link->token);
        $this->line('  expires  : ' . optional($link->expires_at)->toDateTimeString());
        $this->line('  url      : ' . $url);

        return self::SUCCESS;
    }
}
