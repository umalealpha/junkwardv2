<?php

namespace Modules\Cashback\Listeners;

use AlphaDirect\PaymentTransaction;
use Carbon\Carbon;
use Modules\Cashback\Events\CustomerCashbackEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Modules\Cashback\Entities\CustomerCashback;

class CustomerCashbackListener
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
     * @param CustomerCashbackEvent $event
     * @return void
     */
    public function handle(CustomerCashbackEvent $event)
    {
        $cashback  = \Modules\Cashback\Entities\Cashback::where('product_id',$event->policy->product_id)->where('plan_id',$event->policy->plan_id)
		->where('cashback_type',$event->type)->first();
        
        if($cashback){
            $addCashbackFlag = 0;
            #Commission on Subsequent Collection incentive type =8
            if($cashback->cashback_type == 8)
            {
                if($cashback->unlimited == 1)
                    $addCashbackFlag = 1;
                else {
                    $paymentCount = \Modules\Cashback\Entities\CustomerCashback::where('policy',$event->policy->policyNumber)->where('cashback_type',$cashback->cashback_type)->count();
                    if($paymentCount < $cashback->subsquent_amount)
                        $addCashbackFlag = 1;
                }
            }
            $customercashback = \Modules\Cashback\Entities\CustomerCashback::where('policy',$event->policy->policyNumber)->where('cashback_type',$cashback->cashback_type)->first();
            if($customercashback == NULL || $addCashbackFlag == 1){

                if($event->type == 7)//Commission on Instant Premium for 1 Pula
                {
                    $event->policy->premium = 1;
                } elseif($event->type == 6)//Cashback on Registration of Payment method
                {
                    $paymentCount = PaymentTransaction::where('policyNumber',$event->policy->policyNumber)->orderBy('id', 'desc')->first(array('amount'));
                    $event->policy->premium = $paymentCount->amount;
                }

                $customer = new \Modules\Cashback\Entities\CustomerCashback();
                $customer->customer_id=$event->policy->customer_id;
                $customer->policy=$event->policy->policyNumber;
                $customer->product_id=$cashback->product_id;
                $customer->cashback_type=$cashback->cashback_type;
                $customer->payment_type = $cashback->payment_type;
                $customer->cashback_value = $cashback->cashback_value;
                $customer->plan_id=$cashback->plan_id;
                $customer->premium=$event->policy->premium;

                // if($event->policy->product_id == 3 && $event->policy->premium_freq == 1){
                //     if($event->policy->billingStartDate != Carbon::parse($event->policy->created_date)->format('Y-m-d')){
                //         $customer->premium=$event->policy->first_premium;
                //     }
                // }

                if($cashback->payment_type === "per")
                {
                    $customer->reward_points = (($customer->premium * $cashback->cashback_value) /100);
                }else{
                    $customer->reward_points = $cashback->cashback_value;
                }

                $customer->reward_date=\Carbon\Carbon::now()->format('Y-m-d');
                $customer->status = $event->status;
                $customer->save();
            }
		}
    }
}






