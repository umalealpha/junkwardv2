<?php

namespace AlphaDirect\Listeners;

use AlphaDirect\Models\OrangeMandate;
use AlphaDirect\ScheduleTransaction;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class TestOrangeScheduleTransactionListener
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
    public function handle($event, $amount = null)
    {
        $policy = $event->policy;

        $orangeMandate = OrangeMandate::where('mandateId',$policy->policyNumber)->first();

        if(!ScheduleTransaction::where('policy_id', $policy->id)->exists())
        {
            if($policy->product_id == 3)
            {
                if(isset($policy->premium_freq) && $policy->premium_freq == 3)
                {
                    for ($i=0; $i < 1; $i++) {
                        $scheduleData[] = [
                            'policy_id'     => $policy->id,
                            'policy_number' => $policy->policyNumber,
                            'installment'   => $i + 1,
                            'retry_count'   => 0,
                            'premium'       => isset($amount) ? $amount : $policy->premium,
                            'customer_id'   => $policy->customer_id,
                            'email'         => $policy->customer->email,
                            'execution_date'  => Carbon::parse($orangeMandate->paymentDate )->subDays(3)->addWeek($i)->format('Y-m-d'),
                            'billing_date' => Carbon::parse($orangeMandate->paymentDate )->addWeek($i)->format('Y-m-d'),
                            'status'        => 0,    //payment not initiated
                            'payment_method'=> isset($policy->payment_method) ? $policy->payment_method : 'DPO',
                            'created_at'    => Carbon::now(),  //payment not initiated
                            'updated_at'    => Carbon::now(),  //payment not initiated
                        ];
                    }
                }
                elseif(isset($policy->premium_freq) && $policy->premium_freq == 2)
                {
                    $policy->billingStartDate = Carbon::parse($policy->billingStartDate);

                    /* for ($i=0; $i < 33; $i++) {
                        if($i > 0)
                        {
                            $policy->billingStartDate = $policy->billingStartDate->addMonthsNoOverflow(9);
                        } */
                        for ($j=1; $j < 4; $j++) {
                            $scheduleData[] = [
                                'policy_id'     => $policy->id,
                                'policy_number' => $policy->policyNumber,
                                'retry_count'   => 0,
                                'premium'       => isset($amount) ? $amount : $policy->premium,
                                'customer_id'   => $policy->customer_id,
                                'email'         => $policy->customer->email,
                                'execution_date'  => Carbon::parse($orangeMandate->paymentDate)->subDays(3)->addWeek($j)->format('Y-m-d'),
                                'billing_date' => Carbon::parse($orangeMandate->paymentDate )->addWeek($j)->format('Y-m-d'),
                                'installment'   => $j,
                                'status'        => 0,    //payment not initiated
                                'payment_method'=> isset($policy->payment_method) ? $policy->payment_method : 'DPO',
                                'created_at'    => Carbon::now(),
                                'updated_at'    => Carbon::now(),
                            ];
                        }
                    /* } */
                }else{
                    for ($i=0; $i < 100; $i++) {
                        $scheduleData[] = [
                            'policy_id'     => $policy->id,
                            'policy_number' => $policy->policyNumber,
                            'installment'   => $i + 1,
                            'retry_count'   => 0,
                            'premium'       => isset($amount) ? $amount : $policy->premium,
                            'customer_id'   => $policy->customer_id,
                            'email'         => $policy->customer->email,
                            'execution_date'  => $i == 0 ? $orangeMandate->paymentDate : Carbon::parse($orangeMandate->paymentDate)->subDays(3)->addWeek($i)->format('Y-m-d'),
                            'billing_date' => Carbon::parse($orangeMandate->paymentDate )->addWeek($i)->format('Y-m-d'),
                            'status'        => 0,    //payment not initiated
                            'payment_method'=> isset($policy->payment_method) ? $policy->payment_method : 'DPO',
                            'created_at'    => Carbon::now(),  //payment not initiated
                            'updated_at'    => Carbon::now(),  //payment not initiated
                        ];
                    }
                }

            }else{
                for ($i=0; $i < 100; $i++) {
                    $scheduleData[] = [
                        'policy_id'     => $policy->id,
                        'policy_number' => $policy->policyNumber,
                        'installment'   => $i + 1,
                        'retry_count'   => 0,
                        'premium'       => isset($amount) ? $amount : $policy->premium,
                        'customer_id'   => $policy->customer_id,
                        'email'         => $policy->customer->email,
                        'execution_date'  => Carbon::parse($orangeMandate->paymentDate)->subDays(3)->addWeek($i)->format('Y-m-d'),
                        'billing_date' => Carbon::parse($orangeMandate->paymentDate )->addWeek($i)->format('Y-m-d'),
                        'status'        => 0,    //payment not initiated
                        'payment_method'=> isset($policy->payment_method) ? $policy->payment_method : 'DPO',
                        'created_at'    => Carbon::now(),  //payment not initiated
                        'updated_at'    => Carbon::now(),  //payment not initiated
                    ];
                }

            }

            foreach ($scheduleData as $data) {
                ScheduleTransaction::insert($data);
            }


            activity('Policy')
                ->performedOn($policy)
                ->log('Payment is scheduled for policy number:'.$policy->policyNumber);

        }else{
            $latestSchedule = ScheduleTransaction::where('policy_id', $policy->id)->orderBy('installment', 'desc')->value('installment');
            if(!isset($latestSchedule))
            {
                $latestSchedule  = 0;
            }

            if($policy->product_id == 3)
            {
                if(isset($policy->premium_freq) && $policy->premium_freq == 3)
                {
                    for ($i=0; $i < 1; $i++) {
                        $scheduleData[] = [
                            'policy_id'     => $policy->id,
                            'policy_number' => $policy->policyNumber,
                            'installment'   => $latestSchedule + $i + 1,
                            'retry_count'   => 0,
                            'premium'       => isset($amount) ? $amount : $policy->premium,
                            'customer_id'   => $policy->customer_id,
                            'email'         => $policy->customer->email,
                            'execution_date'  => Carbon::parse($orangeMandate->paymentDate)->subDays(3)->addWeek($i)->format('Y-m-d'),
                            'billing_date' => Carbon::parse($orangeMandate->paymentDate )->addWeek($i)->format('Y-m-d'),
                            'status'        => 0,    //payment not initiated
                            'payment_method'=> isset($policy->payment_method) ? $policy->payment_method : 'DPO',
                            'created_at'    => Carbon::now(),  //payment not initiated
                            'updated_at'    => Carbon::now(),  //payment not initiated
                        ];
                    }
                }
                elseif(isset($policy->premium_freq) && $policy->premium_freq == 2)
                {
                    $policy->billingStartDate = Carbon::parse($policy->billingStartDate);

                    /* for ($i=0; $i < 33; $i++) {
                        if($i > 0)
                        {
                            $policy->billingStartDate = $policy->billingStartDate->addMonthsNoOverflow(9);
                        } */
                        for ($j=1; $j < 4; $j++) {
                            $scheduleData[] = [
                                'policy_id'     => $policy->id,
                                'policy_number' => $policy->policyNumber,
                                'retry_count'   => 0,
                                'premium'       => isset($amount) ? $amount : $policy->premium,
                                'customer_id'   => $policy->customer_id,
                                'email'         => $policy->customer->email,
                                'execution_date'  => Carbon::parse($orangeMandate->paymentDate)->subDays(3)->addWeek($j)->format('Y-m-d'),
                                'billing_date' => Carbon::parse($orangeMandate->paymentDate )->addWeek($j)->format('Y-m-d'),
                                'installment'   => $latestSchedule + $j,
                                'status'        => 0,    //payment not initiated
                                'payment_method'=> isset($policy->payment_method) ? $policy->payment_method : 'DPO',
                                'created_at'    => Carbon::now(),
                                'updated_at'    => Carbon::now(),
                            ];
                        }
                    /* } */
                }else{
                    for ($i=0; $i < 100; $i++) {
                        $scheduleData[] = [
                            'policy_id'     => $policy->id,
                            'policy_number' => $policy->policyNumber,
                            'installment'   => $latestSchedule + $i + 1,
                            'retry_count'   => 0,
                            'premium'       => isset($amount) ? $amount : $policy->premium,
                            'customer_id'   => $policy->customer_id,
                            'email'         => $policy->customer->email,
                            'execution_date'  => $i == 0 ? $orangeMandate->paymentDate : Carbon::parse($orangeMandate->paymentDate)->subDays(3)->addWeek($i)->format('Y-m-d'),
                            'billing_date' => Carbon::parse($orangeMandate->paymentDate )->addWeek($i)->format('Y-m-d'),
                            'status'        => 0,    //payment not initiated
                            'payment_method'=> isset($policy->payment_method) ? $policy->payment_method : 'DPO',
                            'created_at'    => Carbon::now(),  //payment not initiated
                            'updated_at'    => Carbon::now(),  //payment not initiated
                        ];
                    }
                }

            }else{
                for ($i=0; $i < 100; $i++) {
                    $scheduleData[] = [
                        'policy_id'     => $policy->id,
                        'policy_number' => $policy->policyNumber,
                        'installment'   => $latestSchedule + $i + 1,
                        'retry_count'   => 0,
                        'premium'       => isset($amount) ? $amount : $policy->premium,
                        'customer_id'   => $policy->customer_id,
                        'email'         => $policy->customer->email,
                        'execution_date'  => Carbon::parse($orangeMandate->paymentDate)->subDays(3)->addWeek($i)->format('Y-m-d'),
                        'billing_date' => Carbon::parse($orangeMandate->paymentDate )->addWeek($i)->format('Y-m-d'),
                        'status'        => 0,    //payment not initiated
                        'payment_method'=> isset($policy->payment_method) ? $policy->payment_method : 'DPO',
                        'created_at'    => Carbon::now(),  //payment not initiated
                        'updated_at'    => Carbon::now(),  //payment not initiated
                    ];
                }

            }

            foreach ($scheduleData as $data) {
                ScheduleTransaction::insert($data);
            }
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  \App\Events\OrderShipped  $event
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed($event, $exception)
    {
        $policy = $event->policy;
        activity('Policy')
            ->performedOn($policy)
            ->log('Payment schedulation for policy number:'.$policy->policyNumber . ' is failed.');
    }
}
