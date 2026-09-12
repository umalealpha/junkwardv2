<?php

namespace AlphaDirect\Listeners\Modules\Inventory\Listeners;

use AlphaDirect\Events\Modules\Inventory\Events\AddIncentive;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class AddIncentiveFire
{
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
     * @param  \AlphaDirect\Events\Modules\Inventory\Events\AddIncentive  $event
     * @return void
     */
    public function handle(AddIncentive $event)
    {
        //
    }
}
