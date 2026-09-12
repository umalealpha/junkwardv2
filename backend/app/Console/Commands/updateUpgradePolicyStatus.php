<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Policy;
use AlphaDirect\Models\PolicyUpgradeMotorcomp;
use AlphaDirect\ScheduleTransaction;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Storage;
use PDF;
use Log;
use DB;
use AlphaDirect\Http\Controllers\CronController;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
class updateUpgradePolicyStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'updateUpgradePolicyStatus:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'update Upgrade Policy Status';

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
        $cron->name = "updateUpgradePolicyStatus:cron";
        $cron->start = Carbon::now();
        $cron->save();
        Log::info('update Upgrade Policy Status started');

        $policiesMotor=DB::table('policy_upgrade_motorcomp')
        ->join('policies', 'policies.id', '=', 'policy_upgrade_motorcomp.policy_id')
        ->where('policy_upgrade_motorcomp.old_payment_type', '=', 'DPO')
        ->get(array('policies.id',
        'policies.premium'));
        foreach($policiesMotor as $policy){

            $policyId = $policy->id;
            $policyPremium = $policy->premium;

            $schedulePolicies = ScheduleTransaction::where('policy_id', $policyId)->where('status', 0)->get(array('id'));
            foreach($schedulePolicies as $schedulePolicy){
                $sId = $schedulePolicy->id;

                $policyScheduleTransaction = ScheduleTransaction::where('id', $sId)->first();
                $policyScheduleTransaction->premium = $policyPremium;
                $policyScheduleTransaction->save();
            }
            

            $policyUpgradeMotorcomp = PolicyUpgradeMotorcomp::where('policy_id', $policy->id)->first();
            $policyUpgradeMotorcomp->new_payment_type = 'DPO';
            $policyUpgradeMotorcomp->new_premium = $policyPremium;
            $policyUpgradeMotorcomp->save();
            sleep(1);
        }
        Log::info('update Upgrade Policy Status ended');
        return 0;
    }
}
