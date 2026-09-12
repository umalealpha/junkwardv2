<?php

namespace AlphaDirect\Listeners;

use AlphaDirect\Events\ClaimEvent;
use AlphaDirect\Services\ClaimTrackingService;
use AlphaDirect\Services\IntegrationSettings;
use Illuminate\Support\Facades\Log;

/**
 * Auto-issue a claimant self-service tracking link when a claim is registered
 * (Claims Tracker -> Graphite, Phase 2). Mirrors ClaimFormEmailListener: it is
 * subscribed to ClaimEvent alongside the existing listeners and only acts on
 * 'claim_created'.
 *
 * Two-part behaviour, both fail-safe:
 *   - The link is always ISSUED (a durable per-claim access link) — an additive
 *     DB row that sends nothing. Reuses ClaimTrackingService::getOrCreateUsable-
 *     LinkForClaim so a re-fire never mints a duplicate.
 *   - Delivering that link (SMS/email) is SEND-GATED on the `claimant_tracking`
 *     flag. With the flag OFF the link exists but nothing is sent; with it ON
 *     the claimant is texted/emailed the link.
 *
 * Note the ClaimEvent dispatch itself is gated by `claims_automation` at both
 * store() call sites, so with the default flags nothing here runs at all — this
 * listener is completely inert until the automation pipeline is armed. Never
 * throws into claim creation.
 */
class ClaimTrackingLinkListener
{
    public function handle(ClaimEvent $event): void
    {
        if ($event->type !== 'claim_created') {
            return;
        }

        try {
            $svc = app(ClaimTrackingService::class);

            // Always issue/ensure the link (additive, sends nothing).
            $link = $svc->getOrCreateUsableLinkForClaim($event->claimId);

            // Deliver only when the claimant-tracking feature is armed.
            if (IntegrationSettings::isEnabled('claimant_tracking', false)) {
                $svc->sendLinkNotification($link);
            }
        } catch (\Throwable $e) {
            Log::warning('[ClaimTrackingLink] auto-issue on claim_created failed', [
                'claim_id' => $event->claimId,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
