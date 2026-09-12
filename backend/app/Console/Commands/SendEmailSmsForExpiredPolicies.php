<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Http\Controllers\ExcelImportController;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Models\ExpiredPoliciesImportJobs;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Log;

class SendEmailSmsForExpiredPolicies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendEmailSmsForExpiredPolicies:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sending email and sms for expired policies';

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
        $cron->name = "sendEmailSmsForExpiredPolicies:cron";
        $cron->start = \Carbon\Carbon::now();
        // $cron->save();

        Log::info('Cron Started for sending email and sms for expired policies.');

        $todayDate = Carbon::now()->format('Y-m-d');
        // ->where('policyNumber','MIS2021003168')
        ExpiredPoliciesImportJobs::where('can_expired',1)->whereDate('expiry_date','<=',$todayDate)->chunkById(100, function($expired_policies)
        {
            dump("chunk - ". count($expired_policies));
            foreach ($expired_policies as $key => $policy) {
                $policy_data = Policy::with('customer')->where('policyNumber', $policy->policyNumber)->first();
                if ($policy->new_premium >= $policy->old_premium) {
                    if (isset($policy_data) && !isset($expired_policies->renew_sms_sent) && !isset($expired_policies->renew_email_sent)) {
                        $email_data = [
                            'email'=>$policy_data->customer->email,
                            'cellphone'=>$policy_data->customer->cellphone,
                            'customer_id'=>$policy_data->customer_id,
                            'policyNumber'=>$policy_data->policyNumber,
                            'policy_id' =>$policy_data->id
                        ];

                        $excelController = new ExcelImportController();
                        $sendsmsemail = $excelController->sendIntimationForRenewSmsEmail($email_data);
                    }
                }

                sleep(1);
            }
        });
    }
}
