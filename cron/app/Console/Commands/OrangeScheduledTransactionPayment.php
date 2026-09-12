<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Http\Controllers\Payment\VCS\PaymentController;
use AlphaDirect\ScheduleTransaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Log;
use AlphaDirect\Models\CronStatus;
class OrangeScheduledTransactionPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'OrangeScheduledTransactionPayment:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Orange Scheduled Transaction Payment';

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
        $cron->name = "OrangeScheduledTransactionPayment:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        // $paymentController = new PaymentController();
        // $transaction = $paymentController->getScheduledTransactionForOrange();
        // dd($transaction);
        #$date = Carbon::today()->addDays(2)->format('Y-m-d');
       $date = Carbon::today()->format('Y-m-d');
        //dd($date);
        $scheduledTransaction = ScheduleTransaction::where('payment_method','orange')->whereDate('billing_date', $date)->where('status','=','0')->get();
        # dd($scheduledTransaction);
        $this->info('Running...');
        if (isset($scheduledTransaction)) {
            foreach ($scheduledTransaction as $key => $transaction) {

                $this->info('Running scheduled...');
                $orangeCon = new PaymentController();
                $token = $orangeCon->orangeAccess();
                Log::info('Cron Started to execute orange order');
                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://api.orange.com/autodebitorderobw/beta/orders',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => '[
                        {
                            "mandateID":  "'. $transaction->policy_number .'",
                            "amount": "'. $transaction->premium .'",
                            "currency": "BWP",
                            "executionDate": "'. $transaction->billing_date .'"
                        }
                    ]',
                    CURLOPT_HTTPHEADER => array(
                        'NotificationURL: https://graphite.alphadirect.co.bw/api/orangeMandateNotification',
                        'creditorId: uwPtRrxSQs',
                        'Authorization: Bearer '.$token,
                        'Content-Type: application/json;charset=utf-8',
                        'Accept: application/json;charset=utf-8',
                        'X-OAPI-Application-Id: sYIWHJAv5WzPCN4O',
                        'X-OAPI-Offer-Data: country=BW'
                    ),
                ));

                $response = curl_exec($curl);
                Log::info($response);
                Log::info('Orange cron run for '. $transaction->policy_number . ' for amount ' . $transaction->premium .' for billing date '. $transaction->billing_date .' mandate id is ' . $transaction->policy_number);
                curl_close($curl);

            }
        }
         $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
