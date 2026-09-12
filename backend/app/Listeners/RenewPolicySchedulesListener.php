<?php

namespace AlphaDirect\Listeners;

use AlphaDirect\Policy;
use AlphaDirect\ScheduleTransaction;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RenewPolicySchedulesListener
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
        $policy         = (object) $event->policy;
        $latestSchedule = ScheduleTransaction::where('policy_id', $policy->id)->orderBy('installment', 'desc')->value('installment');
        if(!isset($latestSchedule))
        {
            $latestSchedule  = 0;
        }
            $scheduleData[0] = [
                'policy_id'     => $policy->id,
                'policy_number' => $policy->policyNumber,
                'retry_count'   => 0,
                'premium'       => isset($amount) ? $amount : $policy->premium,
                'customer_id'   => $policy->customer_id,
                'email'         => $policy->email,
                'billing_date'  => Carbon::now(),
                'installment'   => $latestSchedule,
                'status'        => 2,    //payment not initiated
                'payment_method'=> isset($policy->payment_method) ? $policy->payment_method : 'DPO',
                'created_at'    => Carbon::now(),
                'updated_at'    => Carbon::now(),
            ];

        if (isset($policy->reinstate_type) && $policy->reinstate_type == 'Reinstate_arrears') {
            $amount = $policy->regular_permium;
        }

        if(isset($policy->premium_freq) && $policy->premium_freq == 2)
        {
            $policy->billingStartDate = Carbon::parse($policy->billingStartDate);

                for ($j=1; $j <= 3; $j++) {
                    $scheduleData[] = [
                        'policy_id'     => $policy->id,
                        'policy_number' => $policy->policyNumber,
                        'retry_count'   => 0,
                        'premium'       => isset($amount) ? $amount : $policy->premium,
                        'customer_id'   => $policy->customer_id,
                        'email'         => $policy->email,
                        'billing_date'  => Carbon::parse($policy->billingStartDate)->addMonthsNoOverflow($j),
                        'installment'   => $latestSchedule + $j,
                        'status'        => 0,    //payment not initiated
                        'payment_method'=> isset($policy->payment_method) ? $policy->payment_method : 'DPO',
                        'created_at'    => Carbon::now(),
                        'updated_at'    => Carbon::now(),
                    ];
                }
        }else{
            for ($i=1; $i <= 12; $i++) {
                $scheduleData[] = [
                    'policy_id'     => $policy->id,
                    'policy_number' => $policy->policyNumber,
                    'installment'   => $latestSchedule + $i,
                    'retry_count'   => 0,
                    'premium'       => isset($amount) ? $amount : $policy->premium,
                    'customer_id'   => $policy->customer_id,
                    'email'         => $policy->email,
                    'billing_date'  => Carbon::parse($policy->billingStartDate)->addMonthsNoOverflow($i)->format('Y-m-d'),
                    'status'        => 0,    //payment not initiated
                    'payment_method'=> isset($policy->payment_method) ? $policy->payment_method : 'DPO',
                    'created_at'    => Carbon::now(),  //payment not initiated
                    'updated_at'    => Carbon::now(),  //payment not initiated
                ];
            }
        }
        // dd($scheduleData);
        foreach ($scheduleData as $data) {
            ScheduleTransaction::insert($data);
        }
    }
}
