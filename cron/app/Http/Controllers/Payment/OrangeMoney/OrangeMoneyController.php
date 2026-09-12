<?php

namespace AlphaDirect\Http\Controllers\Payment\OrangeMoney;

use AlphaDirect\Customer;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Exports\MotorCompExport;
use AlphaDirect\Exports\OrangeTransactionsExport;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\OrangeTransactions;
use AlphaDirect\OrangeUploadLogs;
use AlphaDirect\PaymentSchedule;
use AlphaDirect\PaymentSmsData;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\PolicyActivateCancelledDate;
use AlphaDirect\Transaction;
use AlphaDirect\VcsNewTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use function GuzzleHttp\json_decode;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Http\Client\Exception;
use Redirect;
use Response;
use Session;
use File;
use auth;
use DB;
use Validator;
use DateTime;

class OrangeMoneyController extends Controller
{

    public function webPayIntiliazer(Request $request, $policyNumber, $amount)
    {

        $current_timestamp = Carbon::now()->timestamp; // Produces something like 1552296328 

        $urlValue = \Config::get('values.graphite_url');

        $client = new \GuzzleHttp\Client();
        $url = "https://api.orange.com/orange-money-webpay/dev/v1/webpayment";

        $requestContent = [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer e1A7Me0JJbhsI9tfWtAuFgCvYLVF',

            ],
            'json' => [
                'merchant_key' => 'e700ce37',
                'currency' => 'OUV',
                'order_id' => $policyNumber . $current_timestamp,
                'amount' => $amount,
                'return_url' => $urlValue . 'api/orangeAccepted/' . $policyNumber,
                'cancel_url' => $urlValue . 'api/orangeDeclined/' . $policyNumber,
                'notif_url' => $urlValue . 'api/orangeCallBack/' . $policyNumber . '/' . $amount,
                'lang' => 'en',
                'reference' => 'Alpha Direct Insurance Company',
            ],
        ];

        try {

            $apiRequest = $client->request('POST', $url, $requestContent);

            $response = json_decode($apiRequest->getBody());

            return Redirect::to($response->payment_url);
        } catch (Exception $re) {
            // For handling exception.

            return response()->json(['message' => 'Error with transaction occured'], 401);
        }
    }

    public function chatbotPayIntiliazer(Request $request, $policyNumber, $amount)
    {

        $current_timestamp = Carbon::now()->timestamp; // Produces something like 1552296328 
        $urlValue = \Config::get('values.graphite_url');

        $client = new \GuzzleHttp\Client();
        $url = "https://api.orange.com/orange-money-webpay/dev/v1/webpayment";

        $requestContent = [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer e1A7Me0JJbhsI9tfWtAuFgCvYLVF',
            ],
            'json' => [
                'merchant_key' => 'e700ce37',
                'currency' => 'OUV',
                'order_id' => $policyNumber . $current_timestamp,
                'amount' => $amount,
                'return_url' => $urlValue . 'chatbot/orangeSuccess/' . $policyNumber,
                'cancel_url' => $urlValue . 'chatbot/orangeDeclined/' . $policyNumber,
                'notif_url' => $urlValue . 'api/orangeCallBack/' . $policyNumber . '/' . $amount,
                'lang' => 'en',
                'reference' => 'Alpha Direct Insurance Company',
            ],
        ];

        try {

            $apiRequest = $client->request('POST', $url, $requestContent);

            $response = json_decode($apiRequest->getBody());

            return response()->json(['code' => 200, 'paymentMethod' => 'Orange', 'link' => $response->payment_url], 200);
        } catch (Exception $re) {
            // For handling exception.

            return response()->json(['message' => 'Error with transaction occured'], 401);
        }


    }

    public function orangeMoneyAccepted(Request $request)
    {

        $policy = Policy::where('policyNumber', $request->policyNumber)->first();

        return Redirect::route('admin.policy.edit', $policy->id)->with('success', 'Payment Recieved');
    }

    public function orangeMoneyDeclined(Request $request)
    {

        $policy = Policy::where('policyNumber', $request->policyNumber)->first();

        return Redirect::route('admin.policy.edit', $policy->id)->with('error', 'Orange payment failed');
    }

    public function chatbotOrangeMoneyAccepted(Request $request)
    {
        $policy = Policy::where('policyNumber', $request->policyNumber)->first();
        $urlValue = \Config::get('values.graphite_url');

        $client = new \GuzzleHttp\Client();
        $url = $urlValue . "chatbot/vcsAccepted";


        $requestContent = [

            'form_params' => [
                'code' => '200',
                'policyNumber' => $request->policyNumber,
            ],
        ];

        try {

            $apiRequest = $client->request('POST', $url, $requestContent);

            $response = $apiRequest->getBody()->getContents();


            return response()->json(['code' => 200, 'paymentMethod' => 'VCS']);

        } catch (Exception $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }

        $response = $request->getBody('response');

        return $response;
    }

    public function chatbotOrangeMoneyDeclined(Request $request)
    {
        $policy = Policy::where('policyNumber', $request->policyNumber)->first();

        return response()->json(['code' => 400, 'description' => 'Error has occured'], 400);
    }

    public function orangeCallback(Request $request)
    {

        $policy = Policy::where('policyNumber', $request->policyNumber)->first();

        try {

            $orangeTransaction = new OrangeTransactions();
            $orangeTransaction->status = $request->status;
            $orangeTransaction->policyNumber = $request->policyNumber;
            $orangeTransaction->notif_token = $request->notif_token;
            $orangeTransaction->txnid = $request->txnid;
            $orangeTransaction->amount = $request->amount;
            $orangeTransaction->save();

            $transaction = new Transaction();
            $transaction->orangeTransaction_id = $orangeTransaction->id;
            $transaction->transactionType = 'Orange'; //request->transactionType;
            $transaction->policyNumber = $request->policyNumber; //request->policyNumber;
            $transaction->customer_id = $policy->customer_id; //request->transactionType;
            $transaction->policyNumber = $request->policyNumber; //request->policyNumber;
            $transaction->amount = $request->amount;
            $transaction->status = $request->status;
            $transaction->save();
        } catch (Exception $re) {

            return response()->json(["message" => "Error with orange callback url"]);
        }
    }

    public function getCSV(){
        return view('admin.orange_money.getcsv');
    }

    public function uploadCSV(Request $request)
    {
        $date = Carbon::now()->format('Y-m-d');

        if ($request->input('submit') != null && $request->hasFile('csv_file')) {

            $file = $request->file('csv_file');

            $filename = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $tempPath = $file->getRealPath();
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();

            $valid_extension = array("csv");
            $maxFileSize = 2097152;

            if (in_array(strtolower($extension), $valid_extension)) {

                if ($fileSize <= $maxFileSize) {
                    $location = 'uploads';
                    $file->move($location, $filename);
                    $filepath = public_path($location . "/" . $filename);
                    $file = fopen($filepath, "r");
                    $importData_arr = array();
                    $i = 0;

                    while (($filedata = fgetcsv($file, 1000, ",")) !== FALSE) {
                        $num = count($filedata);

                        if($i == 0){
                            $i++;
                            continue;
                        }
                        for ($c = 0; $c < $num; $c++) {
                            $importData_arr[$i][] = $filedata [$c];
                        }
                        $i++;
                    }
                    fclose($file);

                    foreach ($importData_arr as $key=>$importData) {
                        if(count(array_filter($importData)) != 0){
                            $insertData = array(
                                "orangeTransaction_id" => $importData[1],
                                "referenceNumber" => $importData[6],
                                "transactionType" => $importData[5],
                                "policyNumber" => $importData[4],
                                "status" => 'Success',
                                "paymentDescription" => $importData[5],
                                "amount" => $importData[7],
                                "created_at" => (new DateTime())->format('Y-m-d'));
                            $response = Transaction::insertData($insertData);

                            if($response['status'] == 'Success'){
                                DB::table('policies')
                                    ->where('policyNumber',$response['policyNumber'])
                                    ->update([
                                        'status' => 1
                                    ]);
                            }

                            $importData_arr[$key][8] = $response['status'].'-'.$response['message'];
                            $importData_arr[$key][9] = $response['policyNumber'];
                        }
                    }

                    $handle = fopen($filename, 'w+');
                    fputcsv($handle, array('Payment Date','MSISDN', 'Firstname', 'Surname','Policy Number','Policy Type','Reference Number','Amount Paid','Status-Description'));
                    foreach ($importData_arr as $importData) {
                        if(count(array_filter($importData)) != 0) {
                            fputcsv($handle, array($importData[0], $importData[1], $importData[2], $importData[3], $importData[9], $importData[5], $importData[6], $importData[7], $importData[8]));
                        }
                    }

                    fclose($handle);

                    if (File::exists($filepath)) {
                        File::delete($filepath);
                    }

                    $headers = array(
                        'Content-Type' => 'text/csv',
                    );


                    return Response::download($filename, 'updated_'.$filename, $headers)->deleteFileAfterSend(true);

                } else {
                    return Redirect::back()->with('error', 'File too large. File must be less than 2MB.');
                }
            } else {
                return Redirect::back()->with('error', 'Invalid File Extension.');
            }
        }else{
            return Redirect::back()->with('error', 'Please provide CSV file to upload.');
        }
    }

    public function uploadSMSData(Request $request)
    {
        $date = Carbon::now()->format('Y-m-d');

        if ($request->input('submit') != null && $request->hasFile('csv_file')) {

            $file = $request->file('csv_file');

            $filename = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $tempPath = $file->getRealPath();
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();

            $valid_extension = array("csv");
            $maxFileSize = 2097152;

            if (in_array(strtolower($extension), $valid_extension)) {

                if ($fileSize <= $maxFileSize) {
                    $location = 'uploads';
                    $file->move($location, $filename);
                    $filepath = public_path($location . "/" . $filename);
                    $file = fopen($filepath, "r");
                    $importData_arr = array();
                    $i = 0;

                    while (($filedata = fgetcsv($file, 1000, ",")) !== FALSE) {
                        $num = count($filedata);

                        if($i == 0){
                            $i++;
                            continue;
                        }
                        for ($c = 0; $c < $num; $c++) {
                            $importData_arr[$i][] = $filedata [$c];
                        }
                        $i++;
                    }
                    fclose($file);

                    foreach ($importData_arr as $key=>$importData) {
                        if(count(array_filter($importData)) != 0){
                            $insertData = array(
                                "customer_name" => $importData[1],
                                "policyNumber" => $importData[0],
                                "agent_name" => $importData[2],
                                "product" => $importData[3],
                                "premium" => $importData[4],
                                "sms_sent" => '0'
                            );
                            $response = PaymentSmsData::insert($insertData);
                        }
                    }
                    return Redirect::back()->with('success', 'Data import successfull');
                } else {
                    return Redirect::back()->with('error', 'File too large. File must be less than 2MB.');
                }
            } else {
                return Redirect::back()->with('error', 'Invalid File Extension.');
            }
        }else{
            return Redirect::back()->with('error', 'Please provide CSV file to upload.');
        }
    }

    public function getSMSData(Request $request){
        return view('admin.billing.getSmsData');
    }

    public function addCustomerTransaction(Request $request){
        try{
            if($request->policyNumber != null && $request->reference_number != null && $request->amount != null && $request->payment_date){
                $policy = Policy::where('policyNumber',$request->policyNumber)->first(array('id','policyNumber','customer_id','status'));
                if($policy != null){
                    $checkExist = $this->checkTransaction($policy->policyNumber,$request->reference_number,'orangeMoney');
                    if($checkExist == 0){
                        $payment = new PaymentTransaction();
                        $payment->policyNumber = $request->policyNumber;
                        $payment->referenceNumber = $request->reference_number;
                        $payment->amount = $request->amount;
                        $payment->status = 'Success';
                        $payment->paymentDate = Carbon::parse($request->payment_date)->format('Y-m-d');
                        $payment->paymentMethod = 'orangeMoney';
                        $payment->is_ledger = 0;
                        $payment->paymentLoggedBy = auth()->user()->id;
                        $payment->save();

                        $transaction = new Transaction();
                        $transaction->orangeTransaction_id = $payment->id;
                        $transaction->referenceNumber = $payment->referenceNumber;
                        $transaction->transactionType = 'orangeMoney';
                        $transaction->customer_id = $policy->customer_id;
                        $transaction->policyNumber = $policy->policyNumber;
                        $transaction->status = 'Success';
                        $transaction->paymentDescription = 'Customer logged payment';
                        $transaction->save();

                        if($payment->save() && $transaction->save()){
                            $policy->status = 1;
                            $policy->save();
                        }
                        return response()->json(['message'=>'Thank you, your payment details has been logged successfully'], 200);
                    }else{
                        return response()->json(['message'=>'You have already logged this Orange Money transaction'], 402);
                    }
                }else{
                    return response()->json(['message'=>'Policy data not found'], 401);
                }
            }else{
                return response()->json(['message'=>'Please provide all the data'], 401);
            }
        }catch(\Exception $ex){
            return response()->json($ex->getMessage(), 401);
        }
    }

    public function addCustomerTransactionOrange(Request $request){
        try{
            if($request->policyNumber != null && $request->reference_number != null && $request->amount != null && $request->payment_date){
                $policy = Policy::where('policyNumber',$request->policyNumber)->first(array('id','policyNumber','customer_id','status'));
                if($policy != null){
                    $checkExist = $this->checkTransaction($policy->policyNumber,$request->reference_number,'orangeMoney');
                    if($checkExist == 0){
                        $payment = new PaymentTransaction();
                        $payment->policyNumber = $request->policyNumber;
                        $payment->referenceNumber = $request->reference_number;
                        $payment->amount = $request->amount;
                        $payment->status = 'Success';
                        $payment->paymentDate = Carbon::parse($request->payment_date)->format('Y-m-d');
                        $payment->paymentMethod = 'orangeMoney';
                        $payment->is_ledger = 0;
                        $payment->paymentLoggedBy = auth()->user()->id;
                        $payment->save();

                        $transaction = new Transaction();
                        $transaction->orangeTransaction_id = $payment->id;
                        $transaction->referenceNumber = $payment->referenceNumber;
                        $transaction->transactionType = 'orangeMoney';
                        $transaction->customer_id = $policy->customer_id;
                        $transaction->policyNumber = $policy->policyNumber;
                        $transaction->status = 'Success';
                        $transaction->paymentDescription = 'Customer logged payment';
                        $transaction->save();

                        if($payment->save() && $transaction->save()){
                            $policy->status = 1;
                            $policy->save();
                        }
                        return Redirect::back()->with('success', 'Transaction uploaded successfully');
                    }else{
                        return Redirect::back()->with('error', 'Transaction already exists with this reference number');
                    }
                }else{
                    return Redirect::back()->with('error', 'Policy data not found');
                }
            }else{
                return Redirect::back()->with('error', 'Please provide all the data');
            }
        }catch(\Exception $ex){
            return response()->json($ex->getMessage(), 401);
        }
    }

    public function checkTransaction($policyNumber,$ref,$method){
        try{
            $transaction = Transaction::where('policyNumber',$policyNumber)->where('referenceNumber',$ref)->count();
            $pay_trans = PaymentTransaction::where('policyNumber',$policyNumber)->where('referenceNumber',$ref)->where('status','SUCCESS')->count();
            if($transaction != 0 && $pay_trans == 0){
                $payment = new PaymentTransaction();
                $payment->policyNumber = $policyNumber;
                $payment->referenceNumber = $ref;
                $payment->amount = $transaction->amount;
                $payment->status = 'SUCCESS';
                //$payment->paymentDate = $importData[0];
                $payment->paymentMethod = 'orangeMoney';
                $payment->is_ledger = 0;
                $payment->save();
            }
            return $transaction + $pay_trans;
        }catch(\Exception $ex){
            return $ex->getMessage();
        }
    }

    public function addSchedule($policyNumber){
        try{
            $checkPolicy = PaymentSchedule::checkPolicy($policyNumber);
            if($checkPolicy == true){
                $checkSchedule = PaymentSchedule::checkSchedule($policyNumber);
                if($checkSchedule== false){
                    $addSchedule = PaymentSchedule::addSchedule($policyNumber);
                    return true;
                }
            }

        }catch(\Exception $ex){
            return false;
        }
    }

    public function orangeTransactionExport(Request $request){
        return Excel::download(new OrangeTransactionsExport($request->all()), 'Orange_Transactions.xlsx');
    }

    public function logTransactionPage(){
        try{
            return view('admin.orange_money.log_transaction');
        }catch(\Exception $ex){
            return Redirect::back()->with('error', $ex->getMessage().' '.$ex->getLine());
        }
    }
}
