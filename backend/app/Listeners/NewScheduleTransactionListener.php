<?php

namespace AlphaDirect\Listeners;

use AlphaDirect\ScheduleTransaction;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NewScheduleTransactionListener
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
        if (isset($policy->new_billing_start_date)) {
            $policy->billingStartDate = $policy->new_billing_start_date;
        }
        if(str_contains($policy->billingStartDate, '/')){
            $policy->billingStartDate = Carbon::createFromFormat('d/m/Y', $policy->billingStartDate)->format('Y-m-d');
        }

        $billingStartDate = null;
        if (isset($policy->billingStart) && $policy->billingStart == 'Immediate') {
            $billingStartDate = Carbon::parse($policy->billingStartDate)->addMonth()->format('Y-m-d');
        }

        if(!ScheduleTransaction::where('policy_id', $policy->id)->exists())
        {
            if($policy->product_id == 3)
            {
                if(isset($policy->premium_freq) && $policy->premium_freq == 3)
                {
                    $scheduleData[] = [
                        'policy_id'     => $policy->id,
                        'policy_number' => $policy->policyNumber,
                        'installment'   => 1,
                        'retry_count'   => 0,
                        'premium'       => isset($amount) ? $amount : $policy->premium,
                        'customer_id'   => $policy->customer_id,
                        'email'         => $policy->customer->email,
                        'billing_date'  => Carbon::now()->format('Y-m-d'),
                        'status'        => 2,    //payment not initiated
                        'payment_method'=> isset($policy->payment_method) ? $policy->payment_method : 'DPO',
                        'created_at'    => Carbon::now(),  //payment not initiated
                        'updated_at'    => Carbon::now(),  //payment not initiated
                    ];

                    // for ($i=0; $i < 1; $i++) {
                    //     $scheduleData[] = [
                    //         'policy_id'     => $policy->id,
                    //         'policy_number' => $policy->policyNumber,
                    //         'installment'   => $i + 1,
                    //         'retry_count'   => 0,
                    //         'premium'       => isset($amount) ? $amount : $policy->premium,
                    //         'customer_id'   => $policy->customer_id,
                    //         'email'         => $policy->customer->email,
                    //         'billing_date'  => Carbon::parse($policy->billingStartDate)->addYearWithOverflow()->format('Y-m-d'),
                    //         'status'        => 0,    //payment not initiated
                    //         'payment_method'=> isset($policy->payment_method) ? $policy->payment_method : 'DPO',
                    //         'created_at'    => Carbon::now(),  //payment not initiated
                    //         'updated_at'    => Carbon::now(),  //payment not initiated
                    //     ];
                    // }
                }
                elseif(isset($policy->premium_freq) && $policy->premium_freq == 2)
                {
                    $policy->billingStartDate = Carbon::parse($policy->billingStartDate);

                    /* for ($i=0; $i < 33; $i++) {
                        if($i > 0)
                        {
                            $policy->billingStartDate = $policy->billingStartDate->addMonthsNoOverflow(9);
                        } */

                        $scheduleData[] = [
                            'policy_id'     => $policy->id,
                            'policy_number' => $policy->policyNumber,
                            'retry_count'   => 0,
                            'premium'       => isset($amount) ? $amount : $policy->premium,
                            'customer_id'   => $policy->customer_id,
                            'email'         => $policy->customer->email,
                            'billing_date'  => Carbon::now()->format('Y-m-d'),
                            'installment'   => 1,
                            'status'        => 2,    //payment not initiated
                            'payment_method'=> isset($policy->payment_method) ? $policy->payment_method : 'DPO',
                            'created_at'    => Carbon::now(),
                            'updated_at'    => Carbon::now(),
                        ];

                        for ($j=2; $j < 4; $j++) {
                            $scheduleData[] = [
                                'policy_id'     => $policy->id,
                                'policy_number' => $policy->policyNumber,
                                'retry_count'   => 0,
                                'premium'       => isset($amount) ? $amount : $policy->premium,
                                'customer_id'   => $policy->customer_id,
                                'email'         => $policy->customer->email,
                                'billing_date'  => Carbon::parse($policy->billingStartDate)->addMonthsNoOverflow($j-1),
                                'installment'   => $j,
                                'status'        => 0,    //payment not initiated
                                'payment_method'=> isset($policy->payment_method) ? $policy->payment_method : 'DPO',
                                'created_at'    => Carbon::now(),
                                'updated_at'    => Carbon::now(),
                            ];
                        }
                    /* } */
                }else{
                    for ($i=0; $i < 13; $i++) {
                        $scheduleData[] = [
                            'policy_id'     => $policy->id,
                            'policy_number' => $policy->policyNumber,
                            'installment'   => $i + 1,
                            'retry_count'   => 0,
                            'premium'       => isset($amount) ? $amount : $policy->premium,
                            'customer_id'   => $policy->customer_id,
                            'email'         => $policy->customer->email,
                            'billing_date'  => $i == 0 ? $policy->billingStartDate : Carbon::parse($policy->billingStartDate)->addMonthsNoOverflow($i)->format('Y-m-d'),
                            'status'        => 0,    //payment not initiated
                            'payment_method'=> isset($policy->payment_method) ? $policy->payment_method : 'DPO',
                            'created_at'    => Carbon::now(),  //payment not initiated
                            'updated_at'    => Carbon::now(),  //payment not initiated
                        ];
                    }
                }

            }else{
                for ($i=0; $i < 13; $i++) {
                    $scheduleData[] = [
                        'policy_id'     => $policy->id,
                        'policy_number' => $policy->policyNumber,
                        'installment'   => $i + 1,
                        'retry_count'   => 0,
                        'premium'       => isset($amount) ? $amount : $policy->premium,
                        'customer_id'   => $policy->customer_id,
                        'email'         => $policy->customer->email,
                        'billing_date'  => isset($billingStartDate) ? Carbon::parse($billingStartDate)->addMonthsNoOverflow($i)->format('Y-m-d') : Carbon::parse($policy->billingStartDate)->addMonthsNoOverflow($i)->format('Y-m-d'),
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
                            'billing_date'  => Carbon::parse($policy->billingStartDate)->addYearWithOverflow()->format('Y-m-d'),
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
                                'billing_date'  => Carbon::parse($policy->billingStartDate)->addMonthsNoOverflow($j),
                                'installment'   => $latestSchedule + $j,
                                'status'        => 0,    //payment not initiated
                                'payment_method'=> isset($policy->payment_method) ? $policy->payment_method : 'DPO',
                                'created_at'    => Carbon::now(),
                                'updated_at'    => Carbon::now(),
                            ];
                        }
                    /* } */
                }else{
                    for ($i=0; $i < 13; $i++) {
                        $scheduleData[] = [
                            'policy_id'     => $policy->id,
                            'policy_number' => $policy->policyNumber,
                            'installment'   => $latestSchedule + $i + 1,
                            'retry_count'   => 0,
                            'premium'       => isset($amount) ? $amount : $policy->premium,
                            'customer_id'   => $policy->customer_id,
                            'email'         => $policy->customer->email,
                            'billing_date'  => $i == 0 ? $policy->billingStartDate : Carbon::parse($policy->billingStartDate)->addMonthsNoOverflow($i)->format('Y-m-d'),
                            'status'        => 0,    //payment not initiated
                            'payment_method'=> isset($policy->payment_method) ? $policy->payment_method : 'DPO',
                            'created_at'    => Carbon::now(),  //payment not initiated
                            'updated_at'    => Carbon::now(),  //payment not initiated
                        ];
                    }
                }

            }else{
                for ($i=0; $i < 13; $i++) {
                    $scheduleData[] = [
                        'policy_id'     => $policy->id,
                        'policy_number' => $policy->policyNumber,
                        'installment'   => $latestSchedule + $i + 1,
                        'retry_count'   => 0,
                        'premium'       => isset($amount) ? $amount : $policy->premium,
                        'customer_id'   => $policy->customer_id,
                        'email'         => $policy->customer->email,
                        'billing_date'  =>  isset($billingStartDate) ? Carbon::parse($billingStartDate)->addMonthsNoOverflow($i)->format('Y-m-d') : Carbon::parse($policy->billingStartDate)->addMonthsNoOverflow($i)->format('Y-m-d'),
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
