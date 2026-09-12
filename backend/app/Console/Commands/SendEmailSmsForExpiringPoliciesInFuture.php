<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Http\Controllers\ExcelImportController;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Models\ExpiredPoliciesImportJobs;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Log;

class SendEmailSmsForExpiringPoliciesInFuture extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendEmailSmsForExpiringPoliciesInFuture:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sending email and sms for expiring policies in future';

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

        Log::info('Cron Started for sending email and sms for expiring policies in future.');

        $todayDate = Carbon::now()->format('Y-m-d');
        ExpiredPoliciesImportJobs::where('policyNumber','MIS2023020007')->whereDate('expiry_date','>',$todayDate)->chunkById(100, function($expired_policies)
        {
            dump("chunk - ". count($expired_policies));
            foreach ($expired_policies as $key => $policy) {
                $policy_data = Policy::with('customer')->where('policyNumber', $policy->policyNumber)->first();
                if (isset($policy_data)) {
                    $diffDays =  \Carbon\Carbon::createFromTimeStamp(strtotime($policy->expiry_date))->diffInDays();
                    // if ($policy->new_premium >= $policy->old_premium) {
                        // if ($diffDays == 3 || $diffDays == 7 || $diffDays == 15 || $diffDays == 30){
                            $email_data = [
                                'email'=>$policy_data->customer->email,
                                'cellphone'=>$policy_data->customer->cellphone,
                                'customer_id'=>$policy_data->customer_id,
                                'policyNumber'=>$policy_data->policyNumber,
                                'policy_id' =>$policy_data->id,
                                'amount' => $policy->new_premium
                            ];

                            $excelController = new ExcelImportController();
                            $sendsmsemail = $excelController->sendRenewIntimationSmsEmail($email_data);
                        // }
                    // }
                }

                sleep(1);
            }
        });
    }
}
