<?php

namespace Modules\Inventory\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeductStockStore
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
	public $id;
    public $user;
    public $quantity;
    /**
     * Create a new event instance.
     *
     * @return void
		@ StoresInventory id
		@ quantity   number of quantity sail
     */
    public function __construct($id, $user, $quantity=0)
    {
        $this->id = $id;
        $this->user = $user;
        $this->quantity = $quantity;
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
