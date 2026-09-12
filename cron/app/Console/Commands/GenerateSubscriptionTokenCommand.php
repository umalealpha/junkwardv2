<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Events\ChargeTokenRecurrentEvent;
use AlphaDirect\Events\CreateTokenEvent;
use AlphaDirect\Events\PullAccountEvent;
use AlphaDirect\Events\SubscriptionTokenEvent;
use AlphaDirect\Http\Controllers\DpoPaymentController;
use AlphaDirect\Policy;
use AlphaDirect\ScheduleTransaction;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use AlphaDirect\Models\CronStatus;

class GenerateSubscriptionTokenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policy:generateSubscriptionToken {id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collect activated policies whose payment method is dpo and proceed to generate subscription token.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $cron = new CronStatus();
        $cron->name = "policy:generateSubscriptionToken";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $transId = $this->argument('id');

        if(isset($transId) && ScheduleTransaction::where('id', $transId)->exists())
        {
            $policies = ScheduleTransaction::where('id', $transId)->get();
        }else{
            $policies = ScheduleTransaction::getInactiveScheduledTransactions();
        }
        $this->info('Running...');

        $dpo = new DpoPaymentController();

        // dd($policies);
        if(count($policies) > 0)
        {
            foreach($policies as $policy)
            {
                $this->info('Policy Number:'. $policy->policy_number );
                DB::beginTransaction();
                try{
                    //add valiation for one time payment in

                        $createToken = CreateTokenEvent::dispatch($policy);
                        $createToken = isset($createToken[0]) ? $createToken[0] : null;

                    if(isset($createToken) && $createToken['status'] == 1)
                    {
                            $data = [
                                'policy_number'    => $policy->policy_number,
                                "customer_id"      => $createToken['customer_id'],
                                "amount"           => $createToken['amount'],
                                "dpo_error_code"   => $createToken['dpo_error_code'],
                                "reason"           => $createToken['reason'],
                                "token"            => $createToken['token'],
                                "TransactionToken" => $createToken['TransactionToken'],
                                "TransID"          => $createToken['TransID'],
                                "api_name"         => 'subscriptionToken',
                                "response_json"    => $createToken['response_json'],
                                "request_json"     => $createToken['request_json'],
                                "CompanyRef"       => $policy->policy_number.'/'.$policy->installment.'/'.$policy->retry_count,
                                "email"            => $createToken['email'] ,
                            ];

                            $subscriptionTokenEvent = SubscriptionTokenEvent::dispatch($data);
                            $subscriptionTokenEvent = isset($subscriptionTokenEvent[0]) ? $subscriptionTokenEvent[0] : null;

                            if(isset($subscriptionTokenEvent) && $subscriptionTokenEvent['status'] == 1)
                            {
                                $data['subscriptionToken']        = $subscriptionTokenEvent['subscriptionToken'];
                                $data['customerToken']            = $subscriptionTokenEvent['customerToken'];
                                      $policy->subscription_token = $subscriptionTokenEvent['subscriptionToken'];
                                      $policy->customer_token     = $subscriptionTokenEvent['customerToken'];
                                      $policy->token              = $data['TransactionToken'];
                                    //   $policy->status             = 1;    // payment in progress                                                                                //in progress
                                    //   $policy->reason             = isset($subscriptionTokenEvent['reason']) ? $subscriptionTokenEvent['reason'] : null;  //in progress
                                $policy->save();

                                $this->info('Subscription Token generated for policy: '. $policy->policy_number);
                                 activity('Policy')
                                ->performedOn($policy)
                                ->log('Subscription Token generated for policy: '. $policy->policy_number. ' successfully');
                            }else //failed
                            {
                                $policy->retry_count  = $policy->retry_count + 1; //0 to 4
                                $policy->billing_date = $dpo->nextBillingDate($policy->retry_count, $policy->billing_date); // 2 days, 5 days, 15 days, 30 days retry payment
                                $policy->status       = 3; //failed
                                $policy->reason       = 'failed to generate subscription token';
                                $policy->save();

                                $this->error('Failed to create subscription token for policy: '. $policy->policy_number );

                                activity('Policy')
                                ->performedOn($policy)
                                ->log('Failed to create subscription token for policy: '. $policy->policy_number);
                            }
                    }else{

                        $policy->retry_count  = $policy->retry_count + 1; //0 to 4
                        $policy->billing_date = $dpo->nextBillingDate($policy->retry_count, $policy->billing_date); // 2 days, 5 days, 15 days, 30 days retry payment
                        $policy->status       = 3; //failed
                        $policy->reason       = 'missing required tokens';
                        $policy->save();

                        activity('Policy')
                        ->performedOn($policy)
                        ->log('Policy is rescheduled for payment:'. $policy->policy_number);

                        $this->error('Failed to create token for policy to proceed payment: '. $policy->policy_number );
                    }
                    DB::commit();
                }catch(Exception $ex)
                {
                    $this->error($ex->getMessage());
                    DB::rollback();
                }
            }
        }else{
            $this->info('No policies are there to proceed');
        }
         $cron->end = \Carbon\Carbon::now();
         $cron->save(); 
    }
}
