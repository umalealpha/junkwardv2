<?php
namespace Modules\Incentive\Listeners;

use AlphaDirect\PaymentTransaction;
use Carbon\Carbon;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Modules\Incentive\Events\AddIncentive;
class AddIncentiveFire{

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
    public function handle(AddIncentive $event)
    {
        if($event->policy->agent_id != NULL)
        {
            $incentive  = \Modules\Incentive\Entities\Incentive::where('product_id',$event->policy->product_id)->where('plan_id',$event->policy->plan_id)
            ->where('incentive_type',$event->type)->first();
    
            if($incentive){
                // $paymentAmount = PaymentTransaction::where('policyNumber',$event->policy->policyNumber)->latest('created_at')->first('amount');
                // #if payment amount is 1Pula
                // if($paymentAmount == 1){
    
                // }
                $addIncentiveFlag = 0;
                #Commission on Subsequent Collection incentive type =8
                if($incentive->incentive_type == 8)
                {
                    if($incentive->unlimited == 1)
                        $addIncentiveFlag = 1;
                    else {
                        $paymentCount = \Modules\Incentive\Entities\IncentiveAgent::where('policy',$event->policy->policyNumber)->where('incentive_type',$incentive->incentive_type)->count();
                        if($paymentCount < $incentive->subsquent_amount)
                            $addIncentiveFlag = 1;
                    }    
                }
    
                $agentIncentive = \Modules\Incentive\Entities\IncentiveAgent::where('policy',$event->policy->policyNumber)->where('incentive_type',$incentive->incentive_type)->first();
                
                if($agentIncentive == NULL || $addIncentiveFlag == 1){
    
                    if($event->type == 7) //Commission on Instant Premium for 1 Pula
                    {
                        $event->policy->first_premium = 1;
                        $event->policy->premium = 1;
                        $event->amount = 1;
                    }

                    $agent = new \Modules\Incentive\Entities\IncentiveAgent();
                    $agent->agent_id=$event->policy->agent_id;
                    $agent->policy=$event->policy->policyNumber;
                    $agent->product_id=$event->policy->product_id;
                    $agent->plan_id=$event->policy->plan_id;
                    $agent->incentive_type=$incentive->incentive_type;
                    $agent->payment_type = $incentive->payment_type;
                    $agent->incentive_value = $incentive->incentive_value;
                    $agent->status = $event->status;
                    $agent->premium = $event->policy->premium;
                    //Log::info('Premium'.$event->policy->premium);
                    //Log::info(print_r($event, true));
                    // if($event->policy->product_id == 3 && $event->policy->premium_freq == 1){
                    //     if($event->policy->billingStartDate != Carbon::parse($event->policy->created_date)->format('Y-m-d')){
                    //         $event->amount = $event->policy->first_premium;
                    //     }
                    // }
                    
                    if($incentive->payment_type === "per"){
                        $agent->amount = (($event->amount*$incentive->incentive_value) /100);
                    }
                    else{
                        $agent->amount = $incentive->incentive_value;
                    }
                    $agent->save();
                }
            }
        }
	}
}
