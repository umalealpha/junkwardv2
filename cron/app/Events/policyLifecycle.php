<?php

namespace AlphaDirect\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class policyLifecycle
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * 
     * 
     * @return void
     */
    public $policy_id;
    public $action;
    public $action_user;
    public $action_customer;
    public function __construct($policy_id,$action,$action_user = NULL,$action_customer = NULL)
    {
        $this->policy_id = $policy_id;
        $this->action = $action;
        $this->action_user = $action_user;
        $this->action_customer = $action_customer;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
