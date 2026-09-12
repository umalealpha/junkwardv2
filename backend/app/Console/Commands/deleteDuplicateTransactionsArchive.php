<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\CustomerBanking;
use AlphaDirect\Ledger;
use AlphaDirect\Models\PaymentTransactionArchiveSonali as PaymentTransactionArchive;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\Region;
use AlphaDirect\SubLedger;
use Carbon\Carbon;
use Http\Client\Exception;
use Illuminate\Console\Command;
use AlphaDirect\Mail\LedgerDailyReport;
use AlphaDirect\Mail\SendPO;
use Illuminate\Support\Facades\Mail;
use AlphaDirect\Models\CronStatus;
use DB;
use Log;
use PDF;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\Http\Controllers\CronController;

class deleteDuplicateTransactionsArchive extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deleteDuplicateTransactionsArchive:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $cron->name  = "deleteDuplicateTransactionsLive:cron";
        $cron->start = \Carbon\Carbon::now();
        $today = Carbon::today();
        //$now = Carbon::now()->toDateTimeString();
        $dt  = Carbon::now();
        $now = $dt->toDateString();
        /*$today = '2023-11-20';//Carbon::today();
        $now   = '2024-01-20';*/
        $toDayDate = date('d');
        $invoices = array();

       
        ini_set('max_execution_time', 0);
        try{
            $duplicateTransactions = DB::select(DB::raw("SELECT COUNT(referenceNumber),policy_id,referenceNumber FROM graphite_archive.payment_transactions_sonali where referenceNumber != '' GROUP BY policy_id,referenceNumber HAVING COUNT(*) > 1"));
            if(count($duplicateTransactions) > 0)
            {
                foreach ($duplicateTransactions as $key => $value) {
                    $getData = PaymentTransactionArchive::where('referenceNumber', $value->referenceNumber)
                    ->where('policy_id', $value->policy_id)
                    ->get(array('id'));
                    foreach ($getData as  $gValue) {
                        $deleteEntry = PaymentTransactionArchive::where('id',$gValue->id)->delete();
                        sleep(1);
                    }
                    
                }
            }
            /*$report = [
            'transactions' => $duplicateTransactions,
            'title'    => 'Duplicate payment Transactons Entries'
            ];

            $date = \Carbon\Carbon::now()->timestamp;
            $path = 'policies-'.$date.'/duplicatePaymentTranEntries.pdf';
            libxml_use_internal_errors(true);
            $pdf = PDF::loadView('admin.notes.duplicatePaymentTranEntries', $report)->setPaper('a3', 'landscape');
            Storage::disk('s3')->put($path, $pdf->output(), 'public');
            $attachments = array();
            array_push($attachments, $path);

            
            $cronSendMail = new CronController();
            $hook = 'duplicate_payment_txLog_entries';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);

            

            $cron->end = \Carbon\Carbon::now();
            $cron->save();*/
            //return 0;
        }
        catch(Exception $e){
            //\Illuminate\Support\Facades\DB::rollBack();
            return $e->getMessage()
            ;
        }
        
    }
}
