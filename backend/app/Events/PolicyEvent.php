<?php

namespace AlphaDirect\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Generic policy domain event — fired on lifecycle actions.
 * The listener routes to NotificationDispatcher + audit logging.
 *
 * Usage: event(new PolicyEvent($policyId, 'policy_activated', ['premium' => 1200]))
 */
class PolicyEvent
{
    use Dispatchable, SerializesModels;

    public int    $policyId;
    public string $type;       // policy_activated, policy_approved, policy_cancelled, etc.
    public array  $extra;
    public ?int   $triggeredBy;

    public function __construct(int $policyId, string $type, array $extra = [], ?int $triggeredBy = null)
    {
        $this->policyId    = $policyId;
        $this->type        = $type;
        $this->extra       = $extra;
        $this->triggeredBy = $triggeredBy ?? (auth()->id() ?: null);
    }
}
