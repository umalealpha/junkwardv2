<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\EmailSMSLogs;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Services\AlphaDirectNotificationService;
use AlphaDirect\PaymentSmsData;
use Carbon\Carbon;
use Illuminate\Console\Command;
use AlphaDirect\Policy;
use AlphaDirect\Models\CronStatus;

class SendSuccessPaymentSMS extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendsuccesspaymentsms:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send success payment sms';

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
     * @return mixed
     */
    public function handle()
    {
        $cron = new CronStatus();
        $cron->name = "sendsuccesspaymentsms:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        $data = PaymentSmsData::where('sms_sent',0)->get();
        if(count($data) > 0){
            foreach ($data as $key=>$d){
                $customerData = Policy::Join('customer','customer.id','=','policies.customer_id')
                    ->where('policies.policyNumber',$d->policyNumber)
                    ->first();

                if($customerData->cellphone){
                    // WhatsApp → Email → SMS priority
                    app(AlphaDirectNotificationService::class)->paymentSuccess(
                        ucwords($d->customer_name),
                        $d->policyNumber,
                        $d->premium,
                        $customerData->cellphone,
                        $customerData->email ?? null,
                        $customerData->customer_id ?? null
                    );

                    $data = [
                        'customer_id'=>$customerData->customer_id,
                        'log_type'=>'sms',
                        'content_type'=>'policy_pending_payment',
                        'last_sent_date'=>Carbon::now()->format('Y-m-d'),
                    ];

                    $log = EmailSMSLogs::addLog($data);

                    $update = PaymentSmsData::where('id',$d->id)->first();
                    $update->sms_sent = 1;
                    $update->sms_sent_date = Carbon::now()->format('Y-m-d');
                    $update->save();
                }
            }
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
