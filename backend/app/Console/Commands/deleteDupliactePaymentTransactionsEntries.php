<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use PaymentTransactions;
use DB;
use AlphaDirect\Models\CronStatus;
use Log;
use PDF;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\Http\Controllers\CronController;
use AlphaDirect\PaymentTransaction;

class deleteDupliactePaymentTransactionsEntries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deleteDupliactePaymentTxLogEntries:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deleting duplicate entries from payment transactions';

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
        $cron->name = "deleteDupliactePaymentTxLogEntries:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

        Log::info('Cron Started for deleting duplicate entries from payment transactions.');

        $transactions = DB::table('payment_transactions')
        ->groupBy('policyNumber','referenceNumber')
        ->havingRaw('COUNT(*) > 1')
        ->get(array('policyNumber','referenceNumber','amount','status','paymentDate','paymentMethod',DB::raw('COUNT(*) as `count`')));

        foreach ($transactions as $key => $transaction) {
            dump("policyNumber: ".$transaction->policyNumber);
            $recordToKeep = PaymentTransaction::where('policyNumber', $transaction->policyNumber)->where('referenceNumber', $transaction->referenceNumber)->orderBy('id','desc')->first();

            if (isset($recordToKeep)) {
                $recordsToDelete = PaymentTransaction::where('policyNumber', $transaction->policyNumber)->where('referenceNumber', $transaction->referenceNumber)
                    ->where('id', '!=', $recordToKeep->id)
                    ->pluck('id');

                PaymentTransaction::whereIn('id', $recordsToDelete)->delete();
            }

            sleep(1);
        }

        $report = [
            'transactions' => $transactions,
            'title'    => 'Duplicate payment Transactons Entries'
        ];

        $date = \Carbon\Carbon::now()->timestamp;
        $path = 'policies-'.$date.'/duplicatePaymentTranEntries.pdf';
        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.duplicatePaymentTranEntries', $report)->setPaper('a3', 'landscape');
        Storage::disk('s3')->put($path, $pdf->output(), 'public');
        $attachments = array();
        array_push($attachments, $path);

        ////*************Email send new fuction **************/////
        $cronSendMail = new CronController();
        $hook = 'duplicate_payment_txLog_entries';
        $cronSendMail->AllCronMail($attachments,$hook,$cron);

        ////*************Email send new fuction END **************/////

        $cron->end = \Carbon\Carbon::now();
        $cron->save();

    }
}
