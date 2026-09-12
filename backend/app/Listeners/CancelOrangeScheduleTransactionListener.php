<?php

namespace AlphaDirect\Listeners;

use AlphaDirect\ScheduleTransaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CancelOrangeScheduleTransactionListener
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
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        $policy = $event->policyNumber;
        ScheduleTransaction::where('policy_number', $policy)
        ->where('status',0)
        ->where('payment_method','orange')
        ->update([
            'status' => 4,
            'reason' => 'Policy cancelled',
        ]);
    }
}
