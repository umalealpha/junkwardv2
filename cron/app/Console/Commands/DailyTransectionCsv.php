<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\PaymentTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;

class DailyTransectionCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dailytransectioncsv:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily Transaction CSV';

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
        $cron->name = "dailytransectioncsv:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();

        $startDate =  Carbon::parse('today')->subDay(1)->format('Y-m-d'). ' 00:00:01';
        $endDate   =  Carbon::parse('today')->subDay(1)->format('Y-m-d'). ' 23:59:59';

        $transactions = PaymentTransaction::whereBetween('created_at', [$startDate, $endDate])->get();

        if(count($transactions) > 0){

        $results = [];


        foreach ($transactions as $transaction) {
            $results[] = [
                'id' => $transaction->id,
                'policyNumber' => $transaction->policyNumber,
                'policy_id' => $transaction->policy_id,
                'referenceNumber' => $transaction->referenceNumber,
                'amount' => $transaction->amount,
                'extra_vat' => $transaction->extra_vat,
                'status' => $transaction->status,
                'paymentDate' => $transaction->paymentDate,
                'new_payment_date' => $transaction->new_payment_date,
                'paymentMethod' => $transaction->paymentMethod,
                'cashRecipient' => $transaction->cashRecipient,
                'note' => $transaction->note,

                'payment_proof_link' => $transaction->payment_proof_link,
                'is_ledger' => $transaction->is_ledger,
                'paymentLoggedBy' => $transaction->paymentLoggedBy,
                'paymentFrequency' => $transaction->paymentFrequency,
                'numberOfInstalmentsPaid' => $transaction->numberOfInstalmentsPaid,
                'refunded_by' => $transaction->refunded_by,
                'is_refund' => $transaction->is_refund,
                'reason' => $transaction->reason,
                'created_at' => $transaction->created_at,
                'updated_at' => $transaction->updated_at,
                'payment_transaction_id' => $transaction->payment_transaction_id,

                'TransID' => $transaction->TransID,
                'payment_transactionscol' => $transaction->payment_transactionscol,
                'PnrID' => $transaction->PnrID,
                'TransactionToken' => $transaction->TransactionToken,
                'CompanyRef' => $transaction->CompanyRef
                ];
        }


        $pages = "id,policyNumber,policy_id,referenceNumber,amount,extra_vat,status,paymentDate,new_payment_date,paymentMethod,cashRecipient,note,payment_proof_link,is_ledger,paymentLoggedBy,paymentFrequency,numberOfInstalmentsPaid,refunded_by,reason,is_refund,created_at,updated_at,payment_transaction_id,TransID,payment_transactionscol,PnrID,TransactionToken,CompanyRef\n"; // use " not ' or \n not working

        // use foreach to data
        foreach ($results as $where) {
          $pages .= "{$where['id']},{$where['policyNumber']},{$where['policy_id']},{$where['referenceNumber']},{$where['amount']},{$where['extra_vat']},{$where['status']},{$where['paymentDate']},{$where['new_payment_date']},{$where['paymentMethod']},{$where['cashRecipient']},{$where['note']},{$where['payment_proof_link']},{$where['is_ledger']},{$where['paymentLoggedBy']},{$where['paymentFrequency']},{$where['numberOfInstalmentsPaid']},{$where['refunded_by']},{$where['reason']},{$where['is_refund']},{$where['created_at']},{$where['updated_at']},{$where['payment_transaction_id']},{$where['TransID']},{$where['payment_transactionscol']},{$where['PnrID']},{$where['TransactionToken']},{$where['CompanyRef']}\n";
        }



        $date = \Carbon\Carbon::now()->timestamp;
      $path = 'policies-'.$date.'_'.\Carbon\Carbon::parse("today")->subDay(1)->format("Y-m-d").'_transection_report.csv';
        Storage::disk('s3')->put($path, $pages, 'public');


        $attachments = array();
        array_push($attachments, $path);

            ////*************Email send new fuction **************/////
            $cronSendMail = new CronController();
            $hook = 'daily_transection_csv';
            $cronSendMail->AllCronMail($attachments,$hook,$cron);
            ////*************Email send new fuction END **************/////

    }

    return 1;
    }
}
