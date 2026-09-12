<?php

namespace AlphaDirect\Listeners;

use AlphaDirect\Events\PaymentEvent;
use AlphaDirect\Services\NotificationDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentEventListener
{
    public function handle(PaymentEvent $event): void
    {
        try {
            $policy = DB::table('policies')
                ->leftJoin('customer', 'customer.id', '=', 'policies.customer_id')
                ->where('policies.id', $event->policyId)
                ->first([
                    'policies.policyNumber', 'policies.added_by', 'policies.agent_id',
                    'customer.email as customerEmail', 'customer.cellphone as customerPhone',
                    'customer.firstName', 'customer.lastName',
                ]);

            if (!$policy) return;

            $data = array_merge([
                'title'         => $event->type === 'payment_received' ? 'Payment Received' : 'Payment Failed',
                'message'       => $event->type === 'payment_received'
                    ? "Payment received for policy {$policy->policyNumber}."
                    : "Payment failed for policy {$policy->policyNumber} — {$policy->firstName} {$policy->lastName}.",
                'policy_id'     => $event->policyId,
                'policy_number' => $policy->policyNumber,
            ], $event->extra);

            // Notify staff
            if ($policy->added_by) {
                NotificationDispatcher::send($policy->added_by, $event->type, $data, "/policies/{$event->policyId}");
            }

            // On failure: also notify customer via SMS + email
            if ($event->type === 'payment_failed' && $policy->customerEmail) {
                NotificationDispatcher::send(
                    $policy->added_by ?? 0,
                    $event->type,
                    $data,
                    null,
                    ['email', 'sms'],
                    ['email' => $policy->customerEmail, 'phone' => $policy->customerPhone]
                );
            }

        } catch (\Exception $e) {
            Log::error("PaymentEventListener failed: " . $e->getMessage());
        }
    }
}
