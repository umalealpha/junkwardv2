<?php

namespace AlphaDirect\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SendSms
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
	public $to;
	public $message;
	public $extradata; // If any data need to store ex: policyNumber in array format;
	
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($to, $message="", array $extradata=[])
    {
        $this->to=$to;
        $this->message=$message;
        $this->extradata=$extradata;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return [];
    }
}
