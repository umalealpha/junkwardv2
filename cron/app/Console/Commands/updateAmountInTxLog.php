<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Exports\UpdatedAmountTxLogListExport;
use AlphaDirect\PaymentTransaction;
use Illuminate\Console\Command;
use Log;
use DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Http\Controllers\Admin\RealPayController;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\RealPayDummyTransactions;
use Illuminate\Database\Eloquent\Collection;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Http\Controllers\CronController;

class updateAmountInTxLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'updateAmountInTxLog:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Amount In Transaction Log';

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
        $cron->name = "updateAmountInTxLog:cron";
        $cron->start = \Carbon\Carbon::now();
        $cron->save();
        Log::info('Cron Started to update amount');

        $policies = RealPayDummyTransactions:://where('policyNumber','=','MIS2021025271')
        // ->orWhere('policyNumber','=','MIS2020008679')
        where('policyNumber','=','MIS2021010629')
        // ->orWhere('policyNumber','=','MIS2020008763')
        ->orderBy('id','desc')->get();

        if (isset($policies)) {

            $transactionList = [];

             foreach ($policies as $key => $policy) {

                // $records = DB::select(DB::raw('SELECT p.policyNumber,p.referenceNumber,p.amount,p.paymentDate,i.clientNumber,i.InstalmentReferenceNumber,i.InstalmentAmount,i.InstalmentActionDate
                //                 FROM payment_transactions as p join realpay_contract_installments as i
                //                 on p.policyNumber = i.clientNumber
                //                 where p.amount != i.InstalmentAmount and p.paymentMethod="RealPay" and p.referenceNumber=i.InstalmentReferenceNumber
                //                 and date(i.InstalmentActionDate) = p.paymentDate;'));

             /*   if ($policy->policyNumber == "MIS2021009867") {
                    $records = PaymentTransaction::join('realpay_contract_installments','realpay_contract_installments.InstalmentReferenceNumber','payment_transactions.referenceNumber')
                    ->where('realpay_contract_installments.clientNumber',$policy->policyNumber)
                    ->where('payment_transactions.amount','!=','realpay_contract_installments.InstalmentAmount')
                    ->get(array(
                        'payment_transactions.policyNumber',
                        'payment_transactions.referenceNumber',
                        'payment_transactions.amount',
                        'payment_transactions.paymentDate',
                        'realpay_contract_installments.clientNumber',
                        'realpay_contract_installments.InstalmentReferenceNumber',
                        'realpay_contract_installments.InstalmentAmount',
                        'realpay_contract_installments.InstalmentActionDate',
                    ));

                // if (isset($records) && $records->isNotEmpty()) {

                //     foreach ($records as $key => $record) {
                //         if (isset($record->InstalmentAmount)) {
                //             $paymentTransaction = PaymentTransaction::where('policyNumber',$record->policyNumber)
                //                 ->where('referenceNumber',$record->referenceNumber)
                //                 ->where('amount','!=',$record->InstalmentAmount)
                //                 ->first();

                //             if (isset($paymentTransaction)) {
                //                 $paymentTransaction->amount = $record->InstalmentAmount;
                //                 $paymentTransaction->save();

                //                 if ($paymentTransaction->save()) {
                //                     $transaction = PaymentTransaction::where('policyNumber',$record->policyNumber)
                //                         ->where('referenceNumber',$record->referenceNumber)->first();

                                    array_push($transactionList,$transaction->id);
                                }
                            }
                        }
                    }
                    echo "inside if";
                } else {*/

                    $realpayCon = new RealPayController();
                    $fetchToken = $realpayCon->clientAuth();

                    if($fetchToken['token_type'] && $fetchToken['access_token'])
                        $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
                    else
                        return null;

                    $curl = curl_init();
                    echo "bfore curl";
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/contracts/" . env('REALPAY_PRODUCT') . "?ClientNumber=" . $policy->policyNumber . "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "GET",
                        CURLOPT_HTTPHEADER => array(
                            "Content-Type: application/json",
                            "Accept: application/json",
                            "Authorization: ".$token
                        ),
                    ));

                    $response = curl_exec($curl);
                    $data = json_decode($response,true);
                    curl_close($curl);
                   #dd($data);
                    echo "before foreach";
                    if (!empty($data['ContractGetResponse']) && count($data['ContractGetResponse']) > 0) {
                        foreach ($data['ContractGetResponse'] as $key => $contract) {
                           # dd($contract);
                            // echo $key.$contract['ClientNumber'].$contract['ContractNumber'];
                            foreach ($contract['ContractInstalments'] as $key => $ins) {
                                if ($ins['InstalmentReferenceNumber'] == $policy->referenceNumber) {
                                     echo "update payment transaction started ".$policy->policyNumber ." ". $policy->referenceNumber."\n";
                                    $paymentTransaction = PaymentTransaction::where('policyNumber',$policy->policyNumber)
                                        ->where('referenceNumber',$policy->referenceNumber)
                                        ->where('amount','!=',$ins['InstalmentAmount'])
                                        ->first();
                                   # dd($paymentTransaction);
                                    if(isset($paymentTransaction)) {
                                        $paymentTransaction->amount = $ins['InstalmentAmount'];
                                        $paymentTransaction->save();
                                        echo "update payment transaction for ".$policy->policyNumber ." ". $policy->referenceNumber."\n";

                                        if ($paymentTransaction->save()) {
                                            $transaction = PaymentTransaction::where('policyNumber',$policy->policyNumber)
                                                ->where('referenceNumber',$policy->referenceNumber)->first();
                                            array_push($transactionList,$transaction->id);
                                        }
                                    }
                                }
                            }
                            echo "sleep 1";
                            sleep(1);
                        }
                    }
               // }
                //}
                echo "sleep 2";
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
              //  if(count($AdiDuplicatepolicy) > 0 || count($LegalDuplicatepolicy) > 0  ){
                    ////*************Email send new fuction **************/////
                    $cronSendMail = new CronController();
                    $hook = 'transactionlog_list';
                    $cronSendMail->AllCronMail($attachments,$hook,$cron);

                    ////*************Email send new fuction END **************/////
             //   }
            /*   $email = array();
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
                } */

                Storage::disk('s3')->delete($filePath);
            }
        }
        $cron->end = \Carbon\Carbon::now();
        $cron->save();
    }
}
