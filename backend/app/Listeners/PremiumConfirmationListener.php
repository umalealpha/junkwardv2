<?php

namespace AlphaDirect\Listeners;

use AlphaDirect\Events\ClaimEvent;
use AlphaDirect\Services\Claims\PremiumConfirmationService;
use Illuminate\Support\Facades\Log;

/**
 * Raise the premium confirmation the moment a claim is registered.
 *
 * This is the second half of the CFO's flow: the claimant's form and Finance's
 * premium check go out AT THE SAME MOMENT, in parallel — not one waiting on the
 * other. Subscribed to ClaimEvent alongside the existing listeners; only acts
 * on 'claim_created'.
 *
 * Doubly dark: the ClaimEvent dispatch is itself gated by `claims_automation`
 * at both store() call sites, and the service checks `premium_confirmation`. So
 * with default flags nothing here runs at all. Never throws into claim
 * registration — a claim must always be registerable.
 */
class PremiumConfirmationListener
{
    public function handle(ClaimEvent $event): void
    {
        if ($event->type !== 'claim_created') {
            return;
        }

        try {
            app(PremiumConfirmationService::class)->raiseForClaim($event->claimId, 'claim registration');
        } catch (\Throwable $e) {
            Log::warning('[PremiumConfirmation] auto-raise on claim_created failed', [
                'claim_id' => $event->claimId,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
