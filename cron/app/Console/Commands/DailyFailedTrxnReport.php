<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Events\ChargeTokenRecurrentEvent;
use AlphaDirect\Events\CreateTokenEvent;
use AlphaDirect\Events\PullAccountEvent;
use AlphaDirect\Events\SubscriptionTokenEvent;
use AlphaDirect\Events\VerifyTokenEvent;
use AlphaDirect\Http\Controllers\DpoPaymentController;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\ScheduleTransaction;
use PDF;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use stdClass;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;


class DailyFailedTrxnReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dailyfailedtrxnreport:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'daily failed trxnreport';

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
        $cron->name = "dailyfailedtrxnreport:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

            // $yesterday = now()->subDay();
            // $startDate = $yesterday->startOfDay();
            // $endDate = $yesterday->endOfDay();
            $startDate =  Carbon::parse('today')->subDay(1)->format('Y-m-d'). ' 00:00:01';
            $endDate   =  Carbon::parse('today')->subDay(1)->format('Y-m-d'). ' 23:59:59';
            $excludedStatuses = ['Success', 'SUCCESS', 'success', 1];

            $policies =   PaymentTransaction::join('policies','policies.policyNumber','payment_transactions.policyNumber')
                           ->join('customer','customer.id','policies.customer_id')
                           ->join('products','products.id','policies.product_id')
                           ->join('product_plans','product_plans.id','policies.plan_id')
                           ->whereNotIn('payment_transactions.status', $excludedStatuses)
                           ->whereBetween('payment_transactions.created_at', [$startDate, $endDate])
                           ->orderBy('payment_transactions.id', 'DESC')
                           ->get(
                               [
                                   'payment_transactions.id',
                                   'policies.id as policy_id',
                                   'policies.customer_id',
                                   'customer.cellphone as cellphone',
                                   'customer.firstName as firstName',
                                   'customer.lastName as lastName',
                                   'customer.email as email',
                                   'payment_transactions.amount as amount',
                                   'payment_transactions.referenceNumber as referenceNumber',
                                   'policies.product_id',
                                   'payment_transactions.policyNumber as policyNumber',
                                   'payment_transactions.created_at as created_at',
                                   'payment_transactions.note as note',
                                   'payment_transactions.reason as reason',
                                   'payment_transactions.status as status',
                                   'payment_transactions.paymentMethod'
                               ]
                              );

                              //dd($policies);
        if(count($policies) > 0){

       
            $report = [
                'policies' => $policies,
            
                'title'    => 'Failed Transection Reports'
            ];
            $date = \Carbon\Carbon::now()->timestamp;
            $path = 'FailedTrxnReport-'.$date.'.pdf';

            libxml_use_internal_errors(true);
            $pdf = PDF::loadView('admin.notes.FailedTransactionsReport', $report)->setPaper('a3', 'landscape');
            Storage::disk('s3')->put($path, $pdf->output(), 'public');

            $attachments = array();
            array_push($attachments, $path);
           // if(count($policies) > 0){
                ////*************Email send new fuction **************/////
                $cronSendMail = new CronController();
                $hook = 'dailyfailedtrxn_report';
                $cronSendMail->AllCronMail($attachments,$hook,$cron);
                ////*************Email send new fuction END **************/////
          //  }
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save(); 
    }
}
