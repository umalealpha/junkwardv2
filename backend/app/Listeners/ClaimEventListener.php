<?php

namespace AlphaDirect\Listeners;

use AlphaDirect\Events\ClaimEvent;
use AlphaDirect\Services\NotificationDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClaimEventListener
{
    public function handle(ClaimEvent $event): void
    {
        try {
            $claim = DB::table('new_claims')
                ->where('id', $event->claimId)
                ->first(['id', 'claim_number', 'policy_id', 'created_by']);

            if (!$claim) return;

            $data = array_merge([
                'title'        => NotificationDispatcher::typeTitle($event->type) ?? ucwords(str_replace('_', ' ', $event->type)),
                'message'      => "Claim {$claim->claim_number} — " . ucwords(str_replace('_', ' ', $event->type)),
                'claim_id'     => $claim->id,
                'claim_number' => $claim->claim_number,
                'policy_id'    => $claim->policy_id,
            ], $event->extra);

            // Notify claim creator
            if ($claim->created_by) {
                NotificationDispatcher::send(
                    $claim->created_by,
                    $event->type,
                    $data,
                    "/claims/{$claim->id}"
                );
            }

            // Also notify via policy event chain (agent, staff)
            if ($claim->policy_id) {
                NotificationDispatcher::policyEvent($claim->policy_id, $event->type, $data);
            }

        } catch (\Exception $e) {
            Log::error("ClaimEventListener failed: " . $e->getMessage(), [
                'claim_id' => $event->claimId,
                'type'     => $event->type,
            ]);
        }
    }
}
