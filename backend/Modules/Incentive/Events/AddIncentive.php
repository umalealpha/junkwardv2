<?php

namespace Modules\Incentive\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AddIncentive
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
	public $amount;
    public $policy;
    public $type;
    public $status;
    /**
     * Create a new event instance.
     *
     * @return void
		@ policy => policy table Object
		@ Type   => Incentive Type Id
     	@ amount   => Collected Amount
     */
    public function __construct($policy,$type,$status,$amount)
    {
        $this->policy = $policy;
        $this->type = $type;
        $this->amount = $amount;
        $this->status = $status;
    }

    /**
     * Get the channels the event should be broadcast on.
     *
     * @return array
     */
    public function broadcastOn()
    {
        return [];
    }
}
