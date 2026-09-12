<?php

namespace AlphaDirect\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\Models\DpoRefundExcel;
use AlphaDirect\Http\Controllers\DpoPaymentController;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Models\ExcelImportActivity;
use AlphaDirect\ExcelImportForPolicy;
use AlphaDirect\Policy;
use Maatwebsite\Excel\Facades\Excel;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Redirect;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Mail;
use Illuminate\Cache\NullStore;
use AlphaDirect\Models\SMSEmailLogs;
use AlphaDirect\Events\ExcelImportForPolicyActivate; 
use Illuminate\Support\Arr;
use AlphaDirect\Activation;
use AlphaDirect\Helper;
use AlphaDirect\Product;
use AlphaDirect\Exports\ActivationCodeStore;
use AlphaDirect\Mail\ActivationCodeMail;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use Carbon\Carbon;
use AlphaDirect\Exports\ExcelImportStoreForActivation;
use AlphaDirect\Exports\ExcelImportDpoRefundStatus;

class DpoRefundExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public  $data;
    public $timeout = 600;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
             
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(\AlphaDirect\Events\DpoRefundExcelEvent $data)
    {
        //Log::info('Dpo Refund started');
        $excels =    DpoRefundExcel::where('status', null)->get();
        if(count($excels)> 0){

            foreach($excels as $trxnref){
                $trxn = PaymentTransaction::where('referenceNumber',$trxnref->dpo_ref)
                ->where('paymentMethod','DPO')->whereIn('status',['Success','SUCCESS','success','1'])
                ->where('is_refund','!=',1)->first();
                if($trxn != null){
                       $TransactionToken = $trxnref->dpo_ref;
                      
                       $refund =    $this->getDpoTransactionsforExcel($TransactionToken);
                      //Log::alert($refund);
                    if($refund == true){
                        $trxn->is_refund = 1;
                        $trxn->refunded_by = $data->data;
                        $trxn->reason = "Dpo Refund By Excel";
                        $trxn->save();
                        $trxnref->policy_id = $trxn->policy_id;
                        $trxnref->status = 1;  //refund success//
                        $trxnref->save();

                    }else{
                        $trxnref->policy_id = $trxn->policy_id;
                        $trxnref->status = 0;     //no refund//
                        $trxnref->save();
                    }

                }else{
                    $trxnref->status = 2;     //no transaction found//
                    $trxnref->save();

                }

            }
            //excel export;
            $date = \Carbon\Carbon::now()->timestamp;
            $filePath = 'excel_perform_file-'.$date.'.xls';
            $exportData =  Excel::store(new ExcelImportDpoRefundStatus(), $filePath,'s3');
            
            $new = ExcelImportActivity::where('id', $data->id)->first();
            $new->excel_perform_file = $filePath;
            $new->added_by = $data->data;
            $new->status = 3;
            $new->save();
           // $url = \AlphaDirect\Helper::getCloudFrontURL($filePath);
           DpoRefundExcel::truncate();


            //delete table;
        }
        //Log::info('Dpo Refund End');
        return true;
    }
    public function xml2array ( $xmlObject, $out = array () )
	{
		foreach ( (array) $xmlObject as $index => $node )
		$out[$index] = ( is_object ( $node ) ) ? $this->xml2array ( $node ) : $node;
		return $out;
	}
    public function getDpoTransactionsforExcel($TransactionToken)
    {
    try {
                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://secure.3gdirectpay.com/API/v7/',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS =>'<?xml version="1.0" encoding="utf-8"?>
                    <API3G>
                    <Request>getTransactionByRef</Request>
                    <CompanyToken>'.env('COMPANY_TOKEN').'</CompanyToken>
                    <TransactionToken>'.$TransactionToken.'</TransactionToken>
                    </API3G>',
                    CURLOPT_HTTPHEADER => array(
                        'Content-Type: application/xml'
                    ),
                    ));
                    
                    $response = curl_exec($curl);
                    
                    curl_close($curl);
                    $result = simplexml_load_string($response);
                    $getDpo     = $this->xml2array($result);

                    if($getDpo['Code'] == "000")
                    {
                        
                      
                        //$Amount = $getDpo['Transactions']['Transaction']['TransactionFinalAmount'];
                        $Amount = $getDpo['Transactions']['Transaction']['TransactionAmount'];
                        $refund = $this->DpoTransactionsRefund( $TransactionToken,$Amount);
                       // Log::alert($refund);
                        if($refund == true){
                            return true;
                        }else{
                            return false;   
                        }
                    
                    }else{
                        return false;  
                    }

        } catch (\Exception $ex) {
            return redirect()->back()->withError($ex->getMessage() . ' ' . $ex->getLine());
        }
    }
    public function DpoTransactionsRefund($TransactionToken,$Amount)
    {
        try {
                    $url =   "https://secure.3gdirectpay.com/API/v6/";
                    $xml =    '<?xml version="1.0" encoding="utf-8"?>
                                        <API3G>
                                        <Request>refundToken</Request>
                                        <CompanyToken>'.env('COMPANY_TOKEN').'</CompanyToken>
                                        <TransactionToken>'.$TransactionToken.'</TransactionToken>
                                        <refundAmount>'.$Amount.'</refundAmount>
                                        <TransactionCurrency>BWP</TransactionCurrency>
                                        <refundDetails>Refund by Excel</refundDetails>
                                        </API3G>';
                        
                        $curl = curl_init($url);
                        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: text/xml"));
                        curl_setopt($curl, CURLOPT_POST, true);
                        curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);
                        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                        $result = curl_exec($curl);

                        curl_close($curl);
                        $final_result = simplexml_load_string($result);
                        $dpoarray     = $this->xml2array($final_result);

                        if($dpoarray['Result'] == "000")
                        {
                          return true;
                        }else{
                            return false;
                        }

        } catch (\Exception $ex) {
            return redirect()->back()->withError($ex->getMessage() . ' ' . $ex->getLine());
        }
    }
}
