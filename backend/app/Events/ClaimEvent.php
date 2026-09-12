<?php

namespace AlphaDirect\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Generic claim domain event.
 *
 * Usage: event(new ClaimEvent($claimId, 'claim_created', ['severity' => 'high']))
 */
class ClaimEvent
{
    use Dispatchable, SerializesModels;

    public int    $claimId;
    public string $type;      // claim_created, claim_settled, claim_reopened
    public array  $extra;
    public ?int   $triggeredBy;

    public function __construct(int $claimId, string $type, array $extra = [], ?int $triggeredBy = null)
    {
        $this->claimId     = $claimId;
        $this->type        = $type;
        $this->extra       = $extra;
        $this->triggeredBy = $triggeredBy ?? (auth()->id() ?: null);
    }
}
