<?php

namespace AlphaDirect\Listeners;

use AlphaDirect\Events\CommissionPolicyEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use AlphaDirect\Policy;
use AlphaDirect\CommissionPolicy;

class NotifyPolicyCommissionListener
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
     * @param  CommissionPolicyEvent  $event
     * @return void
     */
    public function handle(CommissionPolicyEvent $event)
    {
        //
        $data = $event->data;

        if ($data['status'] == 0) {
            $commission = new CommissionPolicy();
            $commission->commission_type = 'Fixed Sold';
            $commission->policy_id = $data['id'];
            $commission->agent_id = $data['agent_id'];
            $commission->save();
        }else{
            $commission = CommissionPolicy::where('id', $id)->first();
            $commission->commission_type = 'Fixed Activated';
            $commission->getChanges();
            $commission->save();
        }
    }
}
