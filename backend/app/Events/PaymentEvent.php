<?php

namespace AlphaDirect\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Payment domain event.
 *
 * Usage: event(new PaymentEvent($policyId, 'payment_received', ['amount' => 500, 'gateway' => 'DPO']))
 */
class PaymentEvent
{
    use Dispatchable, SerializesModels;

    public int    $policyId;
    public string $type;       // payment_received, payment_failed
    public array  $extra;

    public function __construct(int $policyId, string $type, array $extra = [])
    {
        $this->policyId = $policyId;
        $this->type     = $type;
        $this->extra    = $extra;
    }
}
