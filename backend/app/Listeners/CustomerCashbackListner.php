<?php

namespace AlphaDirect\Listeners;

use AlphaDirect\Events\CustomerCashbackEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
class CustomerCashbackListner
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
     * @param  \AlphaDirect\Events\CustomerCashbackEvent  $event
     * @return void
     */
    public function handle(CustomerCashbackEvent $event)
    {
        //
          $percent=\AlphaDirect\Lookup::where('key','customer_cashback_percentage')->first('value');
          $customerCashBack= new \AlphaDirect\Models\CustomerCashback;
          if($event->policyNumber != null){
              $policyData=\AlphaDirect\Policy::where('policyNumber',$event->policyNumber)->first();
              $customerCashBack->policy_id=$policyData->id;
              $customerCashBack->customer_id=$policyData->customer_id;
              $customerCashBack->premium=$policyData->premium;
              $customerCashBack->reward_points = ($policyData->premium) * $percent->value;
             // dd($customerCashBack->reward_points);
              $customerCashBack->reward_date=\Carbon\Carbon::now()->format('Y-m-d');
              $customerCashBack->save();
            }




        //   $customerCashBack->customer_id=$event->customer_id;
        //   $customerCashBack->policy_id = $policy_id->id;
        //   $customerCashBack->premium= $event->premium;
        //   $customerCashBack->reward_points=round(($event->premium )*0.01 , 2);
        //   $customerCashBack->reward_date=\Carbon\Carbon::now()->format('Y-m-d');
        //   $response=$customerCashBack->save();

    }
}
