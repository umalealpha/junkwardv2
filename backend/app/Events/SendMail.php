<?php

namespace AlphaDirect\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SendMail
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
	public $to;
    public $subject;
	public $textContent;
	public $htmlContent;
	public $attachment;
	public $extradata;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(
		$to,
		string $subject,
		$textContent='',
		$htmlContent="",
		$attachment='',
		array $extradata=[]
	)
    {
        $this->to = $to;
        $this->subject = $subject;
	    $this->textContent = $textContent;
	    $this->htmlContent = $htmlContent;
	    $this->attachment = $attachment;
	    $this->extradata = $extradata;
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
