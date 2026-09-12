<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Policy;
use AlphaDirect\PolicyTerm;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Log;
class CreateTermsForAllMotorCompPolicies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'createTermsForAllMotorCompPolicies:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Terms For All Motor Comp Policies';

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
        $cron->name = "createTermsForAllMotorCompPolicies:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

        Log::info('Cron Started for adding terms to motor comp policies.');
        // ->where('policyNumber','MIS2020007141')
        Policy::where('product_id', 3)->where('status', 1)->chunkById(100, function($policies){
            Log::info("chunk - ". count($policies));
            foreach ($policies as $key => $policy) {
                $policyController = new PolicyController();
                $policy_term = PolicyTerm::where('policy_id',$policy->id)->first();

                if (!isset($policy_term)) {
                    $addTerms = $policyController->addTermsToMotorCompPoliciesIfNotExists($policy->policyNumber);
                }

                $policyTerm = PolicyTerm::where('policy_id',$policy->id)->orderBy('id','desc')->first();
                if (isset($policyTerm)) {
                    $policy->expiry_date = $policyTerm->term_end_date;
                    $policy->save();
                }

                activity('Policy')
                ->performedOn($policy)
                ->log('Policy term has been created.');
            }
        });

        Log::info('Cron finish for adding terms to motor comp policies.');

        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
