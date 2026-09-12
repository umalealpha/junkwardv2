<?php

namespace AlphaDirect\Listeners;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use AlphaDirect\Events\DecrementCounterIfPolicySellsEvent as DecrementCounter;
use AlphaDirect\Jobs\SendEmailMinimumInventoryJob;
use AlphaDirect\Store_inventory;

class DecrementCounterIfPolicySellsListener
{
    use Dispatchable, InteractsWithSockets, SerializesModels;


    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(DecrementCounter $event)
    {
        $data = $event->data;
            $inventory = Store_inventory::where([
                ['store_id', '=', $data['store_id']],
                ['product_id', '=', $data['product_id']],
                ['plan_id', '=', $data['plan_id']],
            ])->get(['id', 'counter', 'min_inventory'])->first();
            if($inventory != null){    
                $inventory->counter = isset($inventory->counter) ? ($inventory->counter) - 1 : 0;
                $inventory->save();
                if ($inventory->counter <= $inventory->min_inventory) {
                    SendEmailMinimumInventoryJob::dispatch($data)->delay(now()->addMinutes(3));
                }
        }
    }
}
