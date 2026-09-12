<?php

namespace Modules\Cashback\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerCashbackEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $policy;
    public $type;
    public $status;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($policy,$type,$status)
    {
        //
        $this->policy=$policy;
        $this->type=$type;
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
