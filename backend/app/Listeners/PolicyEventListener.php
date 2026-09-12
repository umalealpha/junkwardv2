<?php

namespace AlphaDirect\Listeners;

use AlphaDirect\Events\PolicyEvent;
use AlphaDirect\Services\NotificationDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles all PolicyEvent dispatches:
 *  - Sends notifications via NotificationDispatcher
 *  - Logs policy lifecycle audit trail
 *  - Triggers commission calculation on activation
 *  - Triggers clawback on cancellation
 */
class PolicyEventListener
{
    public function handle(PolicyEvent $event): void
    {
        try {
            // 1. Notification dispatch (in-app + email to agent/staff)
            NotificationDispatcher::policyEvent($event->policyId, $event->type, $event->extra);

            // 2. Audit trail — uses existing policy_lifecycle table schema from graphiteBWV8
            $policy = DB::table('policies')->where('id', $event->policyId)->first([
                'id', 'policyActivatedDate', 'premium_freq',
            ]);
            $action = DB::table('policy_action')->where('policy_id', $event->policyId)
                ->orderByDesc('id')->first(['status', 'transaction_type']);
            $term = DB::table('policy_term')->where('policy_id', $event->policyId)
                ->orderByDesc('id')->first(['term_start_date', 'term_end_date']);

            $userName = $event->triggeredBy
                ? DB::table('users')->where('id', $event->triggeredBy)->value('name')
                : null;

            DB::connection('mysql_system')->table('policy_lifecycle')->insert([
                'policy_id'          => $event->policyId,
                'action'             => $event->type,
                'action_user'        => $userName,
                'status'             => $action->status ?? null,
                'trans_type'         => $action->transaction_type ?? null,
                'term_start_date'    => $term->term_start_date ?? null,
                'term_end_date'      => $term->term_end_date ?? null,
                'premium'            => $event->extra['premium'] ?? null,
                'frequency'          => $policy->premium_freq ?? null,
                'policyActivatedDate'=> $policy->policyActivatedDate ?? null,
                'written_by'         => 'v2',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            // 3. Type-specific side effects
            match ($event->type) {
                'policy_activated' => $this->onActivated($event),
                'policy_cancelled' => $this->onCancelled($event),
                'policy_renewed'   => $this->onRenewed($event),
                default            => null,
            };

        } catch (\Exception $e) {
            Log::error("PolicyEventListener failed: " . $e->getMessage(), [
                'policy_id' => $event->policyId,
                'type'      => $event->type,
            ]);
        }
    }

    private function onActivated(PolicyEvent $event): void
    {
        // Trigger commission calculation for this policy
        $policy = DB::table('policies')->where('id', $event->policyId)->first(['agent_id', 'product_id']);
        if ($policy && $policy->agent_id) {
            event(new \AlphaDirect\Events\CommissionPolicyEvent([
                'policy_id'  => $event->policyId,
                'agent_id'   => $policy->agent_id,
                'product_id' => $policy->product_id,
            ]));
        }
    }

    private function onCancelled(PolicyEvent $event): void
    {
        // Commission clawback is handled by commission:calculate cron (step 4)
        // Here we just log the cancellation reason
        Log::info("Policy {$event->policyId} cancelled", $event->extra);
    }

    private function onRenewed(PolicyEvent $event): void
    {
        // Renew payment schedules
        $policy = DB::table('policies')->where('id', $event->policyId)->first(['id']);
        if ($policy) {
            event(new \AlphaDirect\Events\RenewPolicySchedulesEvent($policy->id));
        }
    }
}
