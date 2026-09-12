<?php

namespace AlphaDirect\Listeners;

use AlphaDirect\ScheduleTransaction;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CancelScheduleTransactionListener
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
        $policy = $event->data['policy_number'];
        ScheduleTransaction::where('policy_number', $policy)
        /* ->whereDate('billing_date', '>', Carbon::now()) */
        ->where('status', 0)->update([
            'status' => 4,
            'reason' => 'Policy canceled',
        ]);
    }
}

