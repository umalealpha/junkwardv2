<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Accounts;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Models\OneTimePaymentURL;
use AlphaDirect\Models\PolicyRenewal;
use AlphaDirect\Models\SMSEmailLog;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Models\SMSEmailLogs;
use AlphaDirect\PolicyPremiumReratingLog;
use AlphaDirect\PolicyRenew;
use AlphaDirect\RealpayClientContracts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scheme;
use Response;
use AlphaDirect\AgentLogins;
use AlphaDirect\Claim;
use AlphaDirect\ClaimReservesCoverage;
use AlphaDirect\Config;
use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\DeviceMakeModel;
use AlphaDirect\FinancialInterest;
use AlphaDirect\Http\Controllers\ConfigController;
use AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController;
use AlphaDirect\Lookup;
use AlphaDirect\MotorCompCustomerKYCInspectionPayment;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\PaymentSchedule;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\PolicyActivateCancelledDate;
use AlphaDirect\PolicyCoverCancelNote;
use AlphaDirect\Quote;
use AlphaDirect\Admin\SentPolicyDocuments1;
use AlphaDirect\BankBranches;
use AlphaDirect\Banks;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Documents;
use AlphaDirect\FactorSubType;
use AlphaDirect\Helper;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\PolicyBeneficiary;
use AlphaDirect\Product;
use AlphaDirect\QuoteSettings;
use AlphaDirect\QuotesForPromotion;
use AlphaDirect\RealpayCancelRequests;
use AlphaDirect\RealpayCobtractInstallments;
use AlphaDirect\RealpayContractDetails;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\RealpayFailedTransEmails;
use AlphaDirect\RealpayLogs;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\RealpayWebHookResponses;
use AlphaDirect\Region;
use AlphaDirect\RegionLicense;
use AlphaDirect\sentPolicyDocuments;
use AlphaDirect\Stores;
use AlphaDirect\testy;
use AlphaDirect\Transaction;
use AlphaDirect\UpdateRealpayContract;
use AlphaDirect\VATChanges;
use AlphaDirect\VcsNewTransaction;
use AlphaDirect\Vehicle;
use AlphaDirect\VehicleMake;
use App\Calculationlog;
use Http\Client\Exception;
use Illuminate\Http\Request;
use Carbon\Carbon;
use AlphaDirect\User;
use AlphaDirect\Role;
use AlphaDirect\Notifications;
use AlphaDirect\KYC;
use AlphaDirect\Policy;
use AlphaDirect\GlassClaim;
use AlphaDirect\OTP;
use Hash;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;
use PhpOffice\PhpSpreadsheet\Calculation\Calculation;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use DateTime;
use Validator;
use Session;
use DateInterval;
use Redirect;
use Mail;
use PDF;
use Auth;
use DB;
use AlphaDirect\Http\Controllers\Payment\RealPay\RealPayController;
use Yajra\DataTables\DataTables;
use File;
use Log;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Label\Font\NotoSans;
use Endroid\QrCode\Label\Alignment\LabelAlignmentCenter;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Builder\Builder;
use NumberFormatter;
use Illuminate\Support\Str;
use infobip\api\client\SendMultipleTextualSmsAdvanced;
use infobip\api\configuration\BasicAuthConfiguration;
use infobip\api\model\Destination;
use infobip\api\model\sms\mt\send\Message;
use infobip\api\model\sms\mt\send\textual\SMSAdvancedTextualRequest;


class AccountsController extends Controller
{

    public function clientAuth(){
        try{
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.base_url')."/oauth/token",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "grant_type=client_credentials",
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Basic ".config('realpay.client_auth'),
                    "Content-Type: application/x-www-form-urlencoded"
                ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            return json_decode($response,true);
        }catch(Exception $e){
            return response()->json(['Status' => 'Failed','Description'=>$e->getMessage()], 401);
        }
    }

    /**
     * Show a list of all the Accounts.
     *
     * @return View
     */

    function checkMail(Request $request)
    {
        $policy = RealpayLogs::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('today')
            ->format('Y-m-d')  , Carbon::parse('today')
            ->format('Y-m-d') ])
            ->where('status',2)
            ->get(array('id','policy_id','created_at'));

        $data = [
            'policy'=>$policy
        ];

        $date = Carbon::parse('today')->format('Y-m-d');
        $path = 'PolicyPayments/Realpay/Failed/'.$date.'/realpay_failed_trans.pdf';
        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('failed_tranxs', $data);
        Storage::disk('s3')->put($path, $pdf->output(), 'public');

        $attachments = array();
        array_push($attachments, $path);

        $email = RealpayFailedTransEmails::get(array('email'));

        if(count($email) > 0) {
            foreach($email as $d){
                if($d->email){
                    $data = new \stdClass();
                    $data->user_id = null;
                    $data->hook = 'realpay_failed_transactions';
                    $data->customer_id = null;
                    $data->attachment = $attachments;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                    event(new \AlphaDirect\Events\SendMail($d->email,$emailTemplate->subject,"",$html,$attachments,['hook' => $data->hook]));
                  //  $sent = \Illuminate\Support\Facades\Mail::to($d->email)->send(new MailTemplate($data));
                }
            }
        }
    }

//    public function sendMailgunEmail(){
//        # Instantiate the client.
//        $mgClient = Mailgun::create('PRIVATE_API_KEY', 'https://API_HOSTNAME');
//        $domain = "YOUR_DOMAIN_NAME";
//        $params = array(
//            'from'    => 'Excited User <YOU@YOUR_DOMAIN_NAME>',
//            'to'      => 'bob@example.com',
//            'subject' => 'Hello',
//            'text'    => 'Testing some Mailgun awesomness!'
//        );
//
//# Make the call to the client.
//        $mgClient->messages()->send($domain, $params);
//    }


    public function testEMail(Request $request){

        try{
            $log = new SMSEmailLogs();
            $log->type = 'Email';
            $log->content = serialize($request->all());

            $data = $request->all();

            $log->status = $data['event-data']['event'];
            $log->to_email = $data['event-data']['recipient'];
            $log->hook = $data['event-data']['message']['headers']['subject'];
            if(!empty($data['event-data']['message']['attachments'])) {
                $log->attachments = serialize($data['event-data']['message']['attachments']);
            }

            $log->save();

            return response()->json(
                [
                    'status'=>'Success',
                    'Message'=>'Response logged successfully!'
                ],200
            );
        }catch(\Exception $ex){
            return response()->json(
                [
                    'status'=>'Failed',
                    'message'=>$ex->getMessage().' '.$ex->getLine()
                ],406
            );
        }
    }

    public function uuid(){
        $current_timestamp = Carbon::now()->timestamp;
        return $current_timestamp;
    }
    public function generateQRCode($policyID){
        try{
            $result = Builder::create()
                ->writer(new PngWriter())
                ->writerOptions([])
                ->data(env('QRCODE_URL').base64_encode($policyID)) //base64 encoding
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
                ->size(205)
                ->margin(5)
                ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
                ->build();

            header('Content-Type: '.$result->getMimeType());

            // echo $result->getString();
            $path = 'images/qrcode'.$policyID.'.png';

            // Save it to a file
            $result->saveToFile($path);

            // Generate a data URI to include image data inline (i.e. inside an <img> tag)
            $dataUri = $result->getDataUri();

            return $path;
        }catch(\Exception $e){
            return null;
        }
    }

    public function index(Request $request)
    {

        $fetchToken = $this->clientAuth();
        if($fetchToken['token_type'] && $fetchToken['access_token'])
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;

        dd($token);

//        $policyController = new PolicyController();
//        $link = $policyController->generateSendPaymentURL('MIS2021002987',null,'renew');
//        dd($link);

//        $transaction                          = new PaymentTransaction();
//        $transaction->policyNumber            = "MIS2019000060";
//        $transaction->referenceNumber         = "Xhm-200202";
//        $transaction->TransactionToken        = "Success";
//        $transaction->amount                  = 252;
//        $transaction->paymentDate             = Carbon::today()->format('Y-m-d H:i:s');
//        $transaction->paymentMethod           = 'RealPay';
//        $transaction->note                    = 'payment Success';
//        $transaction->numberOfInstalmentsPaid = 1;
//        $transaction->status                  = 'Success' ;
//        $transaction->save();
//
//
//        $transaction['policyNumber']            = "MIS2019000060";
//        $transaction['referenceNumber']         = "Xhm-200202";
//        $transaction['TransactionToken']        = "Success";
//        $transaction['amount']                  = 252;
//        $transaction['paymentDate']             = Carbon::today()->format('Y-m-d H:i:s');
//        $transaction['paymentMethod']           = 'RealPay';
//        $transaction['note']                    = 'payment Success';
//        $transaction['numberOfInstalmentsPaid'] = 1;
//        $transaction['status']                  = 'Success' ;
//
//        $transaction                          = new PaymentTransaction($transaction);




//        $imageArray = array("omang", "omangBack", "passport","driving_license","proof_residence","proof_income","performed_by");
//        $value = "proof_residence";
//        dd(in_array($value,$imageArray));


        /*$data = DB::select(DB::raw('SELECT policyNumber,referenceNumber,amount,paymentDate as last_payment_date,paymentMethod,ROW_NUMBER() OVER (PARTITION BY policyNumber ORDER BY paymentDate DESC) AS latest_record
            FROM payment_transactions where policyNumber in (select policyNumber from policy_renewals) AND paymentMethod = "RealPay";'
        ));

dd($data);

        if(count($data) > 0){
            foreach($data as $key=>$d){
                $update = PolicyRenewal::where('policyNumber',$d->policyNumber)->first();
                $update->paymentMethod = $d->paymentMethod;
                $update->lastPaymentstatus = $d->status;
                $update->lastPaymentDate = $d->paymentDate;
                $update->save();
            }
        }*/

        if (auth::user()->hasPermissionTo('account-list')) {
            return view('admin.accounts.index');
        } else {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    public function checkEmailAPI($name)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => "http://apilayer.net/api/check?access_key=5f25a37e51bac33040ca69f1238a4529&email=".$name."&smtp=1&format=1",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        $data = json_decode($response,true);


        curl_close($curl);

        if($data['smtp_check'] == true)
            return 'Yes';
        else
            return 'No';
    }


    public function storeContractDetails($data){
        try{
            $saveData = new RealpayContractDetails();
            $saveData->ContractSequence = $data['ContractSequence'];
            $saveData->ClientNumber = $data['ClientNumber'];
            $saveData->ContractNumber = $data['ContractNumber'];
            $saveData->CTCPercentage = $data['CTCPercentage'];
            $saveData->InstalmentStartDate = $data['InstalmentStartDate'];
            $saveData->TrackingCode = $data['TrackingCode'];
            $saveData->FrequencyCode = $data['FrequencyCode'];
            $saveData->CollectionDay = $data['CollectionDay'];
            $saveData->NumberOfInstalments = $data['NumberOfInstalments'];
            $saveData->save();

            return true;
        }catch(Exception $ex){
            return false;
        }
    }

    public function storeInstallments($data){
       try{
           //dd($data['ContractInstalments']);
           if($data != null){
                foreach($data['ContractInstalments'] as $d){
                    $new = new RealpayContractInstallments();
                    $new->clientNumber = $data['ClientNumber'];
                    $new->contractNumber = $data['ContractNumber'];
                    $new->InstalmentReferenceNumber = $d['InstalmentReferenceNumber'];
                    $new->InstalmentSequence = $d['InstalmentSequence'];
                    $new->CTCAmount = $d['CTCAmount'];
                    $new->InstalmentActionDate = $d['InstalmentActionDate'];
                    $new->TrackingCode = $d['TrackingCode'];
                    $new->InstalmentAmount = $d['InstalmentAmount'];
                    $new->InstalmentStatus = $d['InstalmentStatus'];
                    $new->save();

                }
                return true;

           }
       }catch(Exception $ex){

       }
    }

    /*
     * Pass data through ajax call
     */
    /**
     * @return mixed
     */
    public function data()
    {
        $accounts = Accounts::get(array('id','account_name', 'account_num','branch_name', 'branch_code'));
        return DataTables::of($accounts)


            ->addColumn('actions',function($accounts) {
                $actions = '';
                if(Auth::user()->can('account-edit')){
                    $actions .= '<a href="'. route('admin.accounts.edit', $accounts->id) .'" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                }else{
                    $actions .= '<a href="'. route('admin.accounts.edit', $accounts->id) .'" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                                <i class="flaticon-eye"></i>
                            </a>';
                }
                if(Auth::user()->can('account-delete')) {
                    $actions .= '<a href="" value="' . $accounts->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
                }
                return $actions;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }


    /**
     * Show a page to create Accounts.
     *
     * @return View
     */

    public function isImageValid($image){
        $data = Image::make($image)->exif();
        if ($data != null) {
            $exif = exif_read_data($image, 0, true);
            if ($exif) {
                if (array_key_exists('EXIF', $exif)) {
                    if (array_key_exists('DateTimeOriginal', $exif['EXIF'])) {
                        $DateTime = \Carbon::parse($exif['EXIF']['DateTimeOriginal'])->timestamp;
                    } elseif (array_key_exists('aTime' || 'DateTime', $exif['EXIF'])) {
                        $DateTime = \Carbon::parse($exif['EXIF']['aTime' || 'DateTime'])->timestamp;
                    } else {
                        return false;
                    }
                    $createdDateTime = Carbon::createFromTimestamp($DateTime);
                    $uploadDateTime = Carbon::createFromTimestamp(Carbon::now()->timestamp);
                    $diff_in_hours = $createdDateTime->diffInHours($uploadDateTime);
                    if ($diff_in_hours > 24)
                        return false;
                    elseif ($diff_in_hours < 24)
                        return true;
                    else
                        return false;
                } elseif (array_key_exists('FILE', $exif)) {
                    $DateTime = $exif['FILE']['FileDateTime'];
                    $createdDateTime = Carbon::createFromTimestamp($DateTime);
                    $uploadDateTime = Carbon::createFromTimestamp(Carbon::now()->timestamp);
                    $diff_in_hours = $createdDateTime->diffInHours($uploadDateTime);
                    if ($diff_in_hours > 24)
                        return false;
                    elseif ($diff_in_hours < 24)
                        return true;
                    else
                        return false;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function create(Request $request){
        if(Auth::user()->hasPermissionTo('account-create')){
            return view('admin.accounts.create');
        }
        else{
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }

    }


    /**
     * Show a page to edit specific Account.
     *
     * @return View
     */
    public function edit($id){
        if(Auth::user()->hasPermissionTo('account-edit')){
            $accounts = Accounts::where('id',$id)->first();
            return view('admin.accounts.edit',compact('accounts'));
        }
        elseif(Auth::user()->hasPermissionTo('account-list')){
            $accounts = Accounts::where('id',$id)->first();
            return view('admin.accounts.view',compact('accounts'));
        }else{
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }

    }

    /**
     * method to store accounts data from create page.
     *
     * @return View
     */
    public function store(Request $request){

        $account = new Accounts();
        $account->account_name = $request->get('account_name');
        $account->account_num = $request->get('account_num');
        $account->branch_name = $request->get('branch_name');
        $account->branch_code = $request->get('branch_code');
        $account->save();

        activity('Create')
            ->performedOn($account)
            ->causedBy(User::where('id',Auth()->user()->id)->first())
            ->log('Account has been created');

        return Redirect::route('admin.accounts.index')->with('success', 'Account Created Successfully');
    }


    /**
     * method to update accounts data from edit page.
     *
     * @return View
     */
    public function update(Request $request,$id  )
    {
        $account = Accounts::where('id', $id)->first();
        $account->account_name = $request->get('account_name');
        $account->account_num = $request->get('account_num');
        $account->branch_name = $request->get('branch_name');
        $account->branch_code = $request->get('branch_code');
        $account->getChanges();
        if($account->save()) {
            // Redirect to the home page with success menu
            activity('Account')
                ->performedOn($account)
                ->causedBy(User::where('id',Auth()->user()->id)->first())
                ->log('Account Updated');
            return Redirect::route('admin.accounts.index')->with('success', 'Account Updated Successfully');
        }else{
            return redirect()->back()->with('error', 'Something Went Wrong');
        }
    }


    /**
     * method to return modal body for confirm-delete.
     *
     * @return View
     */
    public function getModalDelete(Request $request)
    {

        $body = 'Are you sure you want to delete the Account ?';
        return response()->json(['status'=>'success', 'id'=>$request->get('id'), 'body'=>$body]);

    }

    /**
     * method to delete Account.
     *
     * @return View
     */
    public function destroy($id){
        try{


            $account = Accounts::where('id',$id)->delete();

            return Redirect::route('admin.accounts.index')->with('success', 'Account Deleted Successfully');

        }catch(Exception $e){
            return Redirect::route('admin.accounts.index')->with('error', 'Something Went Wrong');
        }

    }

    public function cancelRealpayContract($policyId){
        $banking = CustomerBanking::where('policy_id', $policyId)->first();
        $update =  RealpayCancelRequests::where('policy_id',$policyId)->first();
        $update->cancel_status = 1;
        $update->save();

        $RealPayController = new \AlphaDirect\Http\Controllers\Payment\RealPay\RealPayController();
        $installments = $RealPayController->getInstallments($banking);

//            $is_merged = $banking->merge_ref;
//
//            if ($is_merged){
//                $data = Policy::where('policyNumber', $banking->client_number)->first();
//                if($data)
//                    $amountToDeduct = $data->premium + $data->vat;
//            }


        if ($installments != null) {
            foreach ($installments as $i) {
                $xml = '';
                $xml .= '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
                      <soap:Body xmlns:ns1="http://realpay_trlp/RP_WS.wsdl">
                         <ns1:editInstallmentsElement>
                            <ns1:pUsername>intgalpha</ns1:pUsername>
                            <ns1:pPassword>61efb93ac14777d47a59d400fdfff45b</ns1:pPassword>
                            <ns1:pMerchantNumber>16244</ns1:pMerchantNumber>
                            <ns1:pRequestdata>
                                <ns1:installmentReferenceNumber>' . $i['refNum'] . '</ns1:installmentReferenceNumber>
                                <ns1:ctcAmount></ns1:ctcAmount>
                                <ns1:actionDate></ns1:actionDate>
                                <ns1:tracking></ns1:tracking><ns1:status>I</ns1:status>
                                <ns1:installmentAmount></ns1:installmentAmount>
                                </ns1:pRequestdata>
                            </ns1:editInstallmentsElement>
                        </soap:Body>
                        </soap:Envelope>';

                $options = [
                    'headers' => [
                        'Content-Type' => 'application/soap+xml',
                    ],
                    'body' => $xml,
                ];

                $client = new \GuzzleHttp\Client();
                $apiRequest = $client->request('POST', 'https://www.realpaycollect.com:7774/RP_WS-RP_WS-WS/RP_WSSoap12HttpPort', $options);
            }
            $response = $apiRequest->getBody()->getContents();
        }
    }

    public function getFinancialInterests(){
        try{
            $financialInterests = FinancialInterest::where('bank_name','!=',null)->get(array('id','bank_name'));
            return response()->json(['Status' => 'Success','Banks'=>$financialInterests], 200);
        }catch(\Exception $ex){
            return response()->json(['Status' => 'Failed','Description'=>$ex->getMessage()], 401);
        }
    }
}
