<?php

namespace AlphaDirect\Listeners;

use AlphaDirect\Events\policyLifecycle;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class policyLifecycleListner
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
     * @param  \AlphaDirect\Events\policyLifecycle  $event
     * @return void
     */
    public function handle(policyLifecycle $event)
    {
        $policy=\AlphaDirect\Policy::where('id',$event->policy_id)->first();
        $newPolicy=new \AlphaDirect\Models\PolicyLifecycle();
        $newPolicy->policy_id=$policy->id;
        $newPolicy->term_start_date= \Carbon\Carbon::now();
        $newPolicy->term_end_date= \Carbon\Carbon::now()->addYear();
        $newPolicy->premium=$policy->premium;
        $newPolicy->frequency=$policy->premium_freq;
        $newPolicy->first_premium=$policy->first_premium;
        $newPolicy->action_user= !empty($event->action_user)?$event->action_user:null;
        $newPolicy->action_customer= !empty($event->action_customer)?$event->action_customer:null;
        $newPolicy->billing_start_date=$policy->billingStartDate;
        $newPolicy->policy_documents=$policy->policyDocument;
        $newPolicy->policyActivatedDate=$policy->policyActivatedDate;
        $newPolicy->trans_type=$policy->trans_type;
        $newPolicy->status=$policy->status;
        $newPolicy->action=$event->action;
        //dd($newPolicy);
        $newPolicy->save();
    }
}
