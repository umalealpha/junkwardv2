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

class DailyNGeniusTransectionReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dailyngeniustransectionreport:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'daily ngenius transection report';

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
        $cron->name = "dailyngeniustransectionreport:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
       
        $policies =   PaymentTransaction::join('policies','policies.policyNumber','payment_transactions.policyNumber')
                    ->join('customer','customer.id','policies.customer_id')
                    ->join('products','products.id','policies.product_id')
                    ->join('product_plans','product_plans.id','policies.plan_id')
                    ->where('payment_transactions.paymentMethod','N-Genius')
                    ->orderBy('payment_transactions.id', 'DESC')
                    ->whereBetween('payment_transactions.created_at', [Carbon::parse('today')->subDay(1)->format('Y-m-d')  , Carbon::parse('today')->subDay(1)->format('Y-m-d') . ' 23:59:59' ])
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
                            'payment_transactions.status as status'
                        ]
                       );
            /* ------------------------------ */
            $report = [
                'policies'=>$policies,
                'successTransactions' => $policies->where('status', 'SUCCESS')   //2=success
                                        ->count(),
                'failedTransactions' => $policies->where('status', '!=', 'SUCCESS')   //1=failed
                                        ->count()
            ];
            $date = \Carbon\Carbon::now()->timestamp;
            $path = 'paymentByNGenius-'.$date.'.pdf';
    
            libxml_use_internal_errors(true);
            $pdf = PDF::loadView('admin.notes.ngeniusTransactionsReport', $report)->setPaper('a3', 'landscape');
            Storage::disk('s3')->put($path, $pdf->output(), 'public');
    
            $attachments = array();
            array_push($attachments, $path);
            if(count($policies) > 0){
                ////*************Email send new fuction **************/////
                $cronSendMail = new CronController();
                $hook = 'ngenius_transaction_today';
                $cronSendMail->AllCronMail($attachments,$hook,$cron);
                ////*************Email send new fuction END **************/////
            }
        //Storage::disk('s3')->delete($path);
        $cron->end = \Carbon\Carbon::now();
        $cron->save(); 
    }
}
