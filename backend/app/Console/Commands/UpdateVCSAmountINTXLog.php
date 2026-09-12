<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Exports\UpdatedAmountTxLogListExport;
use AlphaDirect\PaymentTransaction;
use Log;
use DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\VCSDummyTransactions;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;

class UpdateVCSAmountINTXLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'UpdateVCSAmountINTXLog:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update VCS Amount IN Transaction Log';

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
        $cron->name = "UpdateVCSAmountINTXLog:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started to update amount');

        $policies = VCSDummyTransactions::orderBy('id','desc')->get();

        if (isset($policies)) {
            $transactionList = [];
            foreach ($policies as $key => $policy) {

                // $records = DB::select(DB::raw('SELECT p.policyNumber,p.referenceNumber,p.amount,p.paymentDate,v.policyNumber,v.originalReferenceNumber,v.amount as InstalmentAmount,v.created_at
                //             FROM payment_transactions as p join vcs_new_transactions as v
                //             on p.policyNumber = v.policyNumber
                //             where p.amount != v.amount and p.paymentMethod="VCS" and p.referenceNumber=v.originalReferenceNumber
                //             and date(v.created_at) = p.paymentDate;'));

                $records = PaymentTransaction::join('vcs_new_transactions','vcs_new_transactions.originalReferenceNumber','payment_transactions.referenceNumber')
                        ->where('vcs_new_transactions.policyNumber',$policy->policyNumber)
                        ->where('payment_transactions.amount','!=','vcs_new_transactions.amount')
                        ->get(array(
                            'payment_transactions.policyNumber',
                            'payment_transactions.referenceNumber',
                            'payment_transactions.amount',
                            'payment_transactions.paymentDate',
                            'vcs_new_transactions.policyNumber',
                            'vcs_new_transactions.originalReferenceNumber',
                            'vcs_new_transactions.amount as InstalmentAmount',
                            'vcs_new_transactions.created_at',
                        ));

                if (isset($records) && $records->isNotEmpty()) {

                    foreach ($records as $key => $record) {
                        if (isset($record->InstalmentAmount)) {
                            $paymentTransaction = PaymentTransaction::where('policyNumber',$record->policyNumber)
                                ->where('referenceNumber',$record->referenceNumber)
                                ->where('amount','!=',$record->InstalmentAmount)
                                ->first();

                            if (isset($paymentTransaction)) {
                                $paymentTransaction->amount = $record->InstalmentAmount;
                                $paymentTransaction->save();

                                if ($paymentTransaction->save()) {
                                    $transaction = PaymentTransaction::where('policyNumber',$record->policyNumber)
                                        ->where('referenceNumber',$record->referenceNumber)->first();

                                    array_push($transactionList,$transaction->id);
                                }
                            }
                        }
                    }

                }

                sleep(1);
            }


            if (isset($transactionList) && $transactionList > 0) {
                $date = \Carbon\Carbon::now()->timestamp;
                $filePath = 'TransactionLogListExport-'.$date.'.xls';
                Excel::store(new UpdatedAmountTxLogListExport($transactionList), $filePath,'s3');

                $data = [
                    'transactionList'=>$transactionList,
                ];

                $attachments = array();
                array_push($attachments, $filePath);
               // if(count($policy) > 0){
                    ////*************Email send new fuction **************/////
                    $cronSendMail = new CronController();
                    $hook = 'transactionlog_list';
                    $cronSendMail->AllCronMail($attachments,$hook,$cron);
                    ////*************Email send new fuction END **************/////
           //     }
           /*     $email = array();
                if(env('APP_STATUS') == 'Production') {
                    $email = array(
                        'kkatolkar@alphadirect.co.bw',
                        'sshah@alphadirect.co.bw',
                        'aprasad@alphadirect.co.bw'
                    );
                }else{
                    $email = array('aprasad@alphadirect.co.bw');
                }

                if(count($email) > 0) {
                    foreach($email as $d){
                        if($d){
                            $data = new \stdClass();
                            $data->user_id = null;
                            $data->hook = 'transactionlog_list';
                            $data->customer_id = null;
                            $data->attachment = $attachments;
                            $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                            $markdown = new MailTemplate($data);
                            $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                            event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data->hook]));

                        }
                    }
                    $cron->mail_send = 1;
                    $cron->save();
                }  */

                Storage::disk('s3')->delete($filePath);
            }
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
