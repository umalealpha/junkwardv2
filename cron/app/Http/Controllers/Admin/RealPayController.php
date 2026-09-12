<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Accounts;
use AlphaDirect\BankBranches;
use AlphaDirect\Banks;
use AlphaDirect\Http\Controllers\Payment\VCS\PaymentController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Models\OneTimePaymentURL;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\PolicyPremiumReratingLog;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\PolicyStatusLogs;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayWebHookResponses;
use AlphaDirect\UpdateRealpayClientContract;
use AlphaDirect\UpdateRealpayContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Redirect;
use Log;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Http\Controllers\frontendPay\ClaimController;
use AlphaDirect\Policy;
use AlphaDirect\RealpayContractDetails;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\RealpayLogs;
use AlphaDirect\RealpayCancelRequests;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\Transaction;
use AlphaDirect\VcsNewTransaction;
use AlphaDirect\VcsTransaction;
use http\Env\Response;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\PaymentTransaction;
use DB;
use DateTime;
use Storage;
use Illuminate\Support\Carbon;
use PHPUnit\Exception;
use Yajra\DataTables\DataTables;
use AlphaDirect\User;
use AlphaDirect\Http\Controllers\FrontendPay\CustomerController;
use AlphaDirect\Models\PolicyDiscountSurcharge;
use AlphaDirect\Models\PolicyRenewal;
use AlphaDirect\RealpayTransactionDummyData;

class RealPayController extends Controller
{
    public function eventLogs(){
        return view('admin.accounts.realpaylogs');
    }
    public function eventData(){
        $data = RealpayLogs::orderBy('id','desc')->get();
        return DataTables::of($data)
            ->addColumn('policy_id',function($data) {
                $policyNumber = Policy::where('id',$data->policy_id)->first(array('policyNumber'));
                if($policyNumber)
                    return $policyNumber->policyNumber;
                else
                    return 'N/A';
            })
            ->addColumn('event',function($data) {
                if($data->event == 1)
                    return "Create new contract";
                elseif($data->event == 2)
                    return 'Cancel existing contract';
                else
                    return '-';
            })
            ->addColumn('status',function($data) {
                if($data->status == 1)
                    $status = '<span class="kt-font-bold kt-font-success">Success</span>';
                elseif($data->status == 2)
                    $status = '<span class="kt-font-bold kt-font-danger">Failed</span>';
                else
                    $status = '<span class="kt-font-bold kt-font-primary">Processing</span>';

                return $status;
            })
            ->rawColumns(['policy_id','status'])
            ->make(true);
    }

    public function editInstalment(){
        try{
            return view('admin.Realpay.editInstalment');
        }catch(\Exception $exception){

        }
    }

    public function getInstalmentData(Request $request){
//        try{
//            $fetchToken = $this->clientAuth();
//            if($fetchToken['token_type'] && $fetchToken['access_token'])
//                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
//            else
//                return null;
//

//
//            $curl = curl_init();
//
//            curl_setopt_array($curl, array(
//                CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/instalments/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
//                CURLOPT_RETURNTRANSFER => true,
//                CURLOPT_ENCODING => "",
//                CURLOPT_MAXREDIRS => 10,
//                CURLOPT_TIMEOUT => 0,
//                CURLOPT_FOLLOWLOCATION => true,
//                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//                CURLOPT_CUSTOMREQUEST => "GET",
//                CURLOPT_POSTFIELDS =>"{\r\n  \"InstalmentGetRequest\": [\r\n    {\r\n
//                            \"ClientNumber\": \"MIS2020001320\",\r\n
//                           \"ContractSequence\": 1011163369,\r\n
//                           \"ContractNumber\": \"1320\",\r\n
//                           \"InstalmentSequence\": 1011163369023,\r\n
//                           }\r\n
//                           ]\r\n
//                           }",
//                CURLOPT_HTTPHEADER => array(
//                    "Content-Type: application/json",
//                    "Accept: application/json",
//                    "Authorization: ".$token
//                ),
//            ));
//
//            $response = curl_exec($curl);
//            $data = json_decode($response,true);
//
//            dd($data);
//
//        }catch(\Exception $exception){
//
//        }

        $fetchToken = $this->clientAuth();
        if($fetchToken['token_type'] && $fetchToken['access_token'])
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;

        $curl = curl_init();

        $clientNum = $request->client_number;
        $contractNumber = $request->contract_number;
        $conSeq = $request->contract_sequence;

        if(strlen($conSeq) ==14){
            $conSeq = substr($conSeq,0,10);
            $insSeq = substr($conSeq,10,13);
        }else{
            return Redirect::back()->with('error', 'Invalid contract sequence number!');
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/instalments/".env('REALPAY_PRODUCT')."?ClientNumber=".$clientNum."&ContractNumber=".$contractNumber.'&ContractSequence='.$conSeq.'&InstalmentSequence='.$insSeq."env('REALPAY_MERCHANT')".env('REALPAY_VERSION'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_POSTFIELDS => array(),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Accept: application/json",
                "Authorization: ".$token
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $data = json_decode($response,true);

        if($data && $data['InstalmentGetResponse'] != null){
            $ins = $data['InstalmentGetResponse'][0];

            return view('admin.Realpay.instalmentData',compact('ins'));
        }else{
            return Redirect::back()->with('error', 'No data found for the information!');
        }

    }

    public function updateInstalmentData(Request $request){
        try{
            $fetchToken = $this->clientAuth();

            if($fetchToken['token_type'] != ''  && $fetchToken['access_token'] != ''){
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            }else{
                return Redirect::back()->with('error', 'Auth key not found');
            }

            $curl = curl_init();

            $contractSeq = $request->ContractSequence;
            $clientNum = $request->clientNumber;
            $contractNumber = $request->contractNumber;
            $insSeq = $request->InstalmentSequence;
            $tracking = 44;
            $amnt = $request->insAmount;
            $insStatus = $request->instalmentStatus;

            $date = \Carbon::parse($request->instalmentDate)->format('Y-m-d');

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/instalments/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "PUT",
                CURLOPT_POSTFIELDS =>"{\r\n  \"InstalmentPutRequest\": [\r\n    {\r\n
                            \"ClientNumber\": \"$clientNum\",\r\n
                           \"ContractSequence\": $contractSeq,\r\n
                           \"ContractNumber\": \"$contractNumber\",\r\n
                           \"InstalmentSequence\": $insSeq,\r\n
                           \"InstalmentActionDate\": \"$date\",\r\n
                           \"TrackingCode\": \"$tracking\",\r\n
                           \"InstalmentAmount\": $amnt,\r\n
                           \"InstalmentStatus\": \"$insStatus\",\r\n
                           \"DebitSequenceType\": \"OOFF\",\r\n
                           }\r\n
                           ]\r\n
                           }",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: ".$token
                ),
            ));

            $response = curl_exec($curl);
            $data = json_decode($response,true);

            dd($data);
        }catch(\Exception $ex){

        }
    }

    public function logEvent($policyId,$event){
        try{
            if($event == 1){
                $log = new RealpayLogs();
                $log->policy_id = $policyId;
                $log->event = $event;
                $log->status = 0;
                $log->save();
                return true;
            }
            if($event == 2){
                $client = RealpayPaymentRequest::where('policy_id',$policyId)->first(array('status'));
                if($client){
                    $log = new RealpayLogs();
                    $log->policy_id = $policyId;
                    // if($client->status == 1){
                        $log->event = $event;
                        $log->status = 0;
                        $log->save();
                        return true;
                    // }
                }
            }

        }catch(\Exception $ex){
            return false;
        }
    }

    public function newContractRequests(){
        if(Auth::user()->hasPermissionTo('account-list')){
            return view('admin.accounts.newrequest');
        }
        else{
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    public function requestData(){
        $data = RealpayPaymentRequest::orderBy('id','desc')->get();
        return DataTables::of($data)
            ->addColumn('policy_id',function($data) {
                $policyNumber = Policy::where('id',$data->policy_id)->first(array('policyNumber'));
                if($policyNumber && $policyNumber->policyNumber){
                    return $policyNumber->policyNumber;
                }else{
                    return 'N/A';
                }

            })
            ->addColumn('premium_freq',function($data) {
                switch($data->premium_freq){
                    case '1':
                        return 'Monthly';
                    case '2':
                        return 'Three Instalments';
                    case '3':
                        return 'Annual';
                    default:
                        return 'N/A';
                }

            })
            ->addColumn('clientNumber',function($data) {
                if($data->clientNumber)
                    return $data->clientNumber;
                else
                    return '-';
            })
            ->addColumn('clientCreated',function($data) {
                if($data->clientCreated != null){
                    if($data->clientCreated == 0)
                        return  $status = '<span class="kt-font-bold kt-font-info">Processing</span>';
                    elseif($data->clientCreated == 1)
                        return  $status = '<span class="kt-font-bold kt-font-success">Yes</span>';
                    elseif($data->clientCreated == 2)
                        return  $status = '<span class="kt-font-bold kt-font-danger">No</span>';
                    else
                        return '-';
                }else{
                    return '-';
                }
            })->addColumn('contractCreated',function($data) {
                if($data->contractCreated != null) {
                    if ($data->contractCreated == 0)
                        return $status = '<span class="kt-font-bold kt-font-info">Processing</span>';
                    elseif ($data->contractCreated == 1)
                        return $status = '<span class="kt-font-bold kt-font-success">Yes</span>';
                    elseif ($data->contractCreated == 2)
                        return $status = '<span class="kt-font-bold kt-font-danger">No</span>';
                    else
                        return '-';
                }else{
                    return '-';
                }
            })
            ->addColumn('first_premium_wvat',function($data) {

                $policyNumber = Policy::where('id',$data->policy_id)->first();
                if($policyNumber && $policyNumber->first_premium_wvat && $policyNumber->premium_freq == 1){
                    return sprintf ("%.2f", $policyNumber->first_premium_wvat);
                }else{
                    return '-';
                }

            })
            ->addColumn('premium',function($data) {

                $policyNumber = Policy::where('id',$data->policy_id)->first(array('premium','vat_percent'));
                if($policyNumber && $policyNumber->premium){
                    return sprintf ("%.2f", $policyNumber->premium);
                }else{
                    return '-';
                }

            })
            ->addColumn('frequency',function($data) {

                $policyNumber = Policy::where('id',$data->policy_id)->first();
                if($policyNumber && $policyNumber->premium_freq == 1){
                    return "Monthly";
                }elseif($policyNumber && $policyNumber->premium_freq == 2){
                    return '3 Installments';
                }elseif($policyNumber && $policyNumber->premium_freq == 3){
                    return 'Yearly';
                }else{
                    return "Monthly";
                }

            })
            ->addColumn('action',function($data) {
                if($data->contract != null) {
                    $actions = '<a href="' . route('admin.view-installments', $data->policy_id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View Installments">
                                <i class="flaticon-eye"></i>
                            </a>';
                }else{
                    $actions = '<a href="' . route('admin.view-error', $data->policy_id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View Errors">
                                <i class="flaticon-eye"></i>
                            </a>';
                    //return '-';
                }
                return $actions;
            })

            ->addColumn('response',function($data) {
//               $res = '';
//
//               if($data->response != '-' || $data->response != null){
//                   $data = unserialize($data->response);
//
//                   if($data['ClientCreated'] == 1)
//                       $res .= '<span>Client Created</span><br>';
//                   else
//                       $res .= '<span>Client Not Created</span><br>';
//
//                   if($data['ContractCreated'] == 1)
//                       $res .= '<span>Contract Created</span><br>';
//                   else
//                       $res .= '<span>Contract Not Created</span><br>';

                return $data->response;
//               }else{
//                   $res = '<span>No data found</span>';
//                   return $res;
//               }
//           return $res;

            })
            ->addColumn('status',function($data) {
                if($data->status == 1)
                    $status = '<span class="kt-font-bold kt-font-success">Success</span>';
                elseif($data->status == 2)
                    $status = '<span class="kt-font-bold kt-font-danger">Failed</span>';
                elseif($data->status == 0)
                    $status = '<span class="kt-font-bold kt-font-primary">Processing</span>';
                else
                    $status = '<span class="kt-font-bold kt-font-primary">N/A</span>';

                return $status;
            })
            ->rawColumns(['clientCreated','contractCreated','action','policy_id','status','premium','first_premium_wvat','frequency','response'])
            ->make(true);
    }
    public function cancelContractRequests(){
        if(Auth::user()->hasPermissionTo('account-list')){
            return view('admin.accounts.cancelrequest');
        }
        else{
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
    public function cancelData(){
        $data = RealpayCancelRequests::get();
        return DataTables::of($data)
            ->addColumn('policy_id',function($data) {
                $policyNumber = Policy::where('id',$data->policy_id)->first(array('policyNumber'));
                if($policyNumber){
                    return $policyNumber->policyNumber;
                }else{
                    return '-';
                }
            })
            ->addColumn('cancel_status',function($data) {
                if($data->cancel_status == null)
                    $status = 'Failed';
                elseif($data->cancel_status == 2)
                    $status = 'Failed';
                elseif($data->cancel_status == 1)
                    $status = 'Request processed';
                else
                    $status = 'Request not processed';

                return $status;
            })
            ->rawColumns(['policy_id','cancel_status'])
            ->make(true);
    }

    public function clientAuth(){
        try{
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://realpaycollect.com:4448/rpp/rpws/oauth/token",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "grant_type=client_credentials",
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Basic QW5lcTR4d1ZpVWJLS0VhUUpGVjI5QS4uOkxmUVp1aGFLZF9CYkFqUnZXTXp6b1EuLg==",
                    "Content-Type: application/x-www-form-urlencoded"
                ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            return json_decode($response,true);
        }catch(\Exception $e){
            return response()->json(['Status' => 'Failed','Description'=>$e->getMessage()], 401);
        }
    }

    public function storeNewClient(Request $request){
        try{
            $clientNumber = $this->cancelRealpayContract($request->policyID);

            if($clientNumber != null){
                $can = RealpayCancelRequests::where('policy_id',$request->policyID)->first();
                if (isset($can)) {
                    $can->cancel_status = 1;
                    $can->save();
                }
                // else {
                //     return response()->json(['error'=>'failed','message' => 'Failed to cancel contract','status' => '401']);
                // }

                $trans = Transaction::where('realPayTransaction_id',$request->policyID)
                    ->orderBy('id', 'DESC')
                    ->first();
                if($trans != null){
                    $trans->status = "CANCELLED";
                    $trans->save();
                }
            }else{
                $can = RealpayCancelRequests::where('policy_id',$request->policyID)->first();
                if (isset($can)) {
                    $can->cancel_status = 2;
                    $can->save();
                }
                // else {
                //     return response()->json(['error'=>'failed','message' => 'Failed to cancel contract','status' => '401']);
                // }

            }

            //\Illuminate\Support\Facades\DB::beginTransaction();
            $policy = Policy::where('id',$request->policyID)->orderBy('id','DESC')->first();
            $customer = Customer::where('id', $policy->customer_id)->with('profile')->first();
            $profile = CustomerProfile::where('customer_id',$customer->id)->first();
            $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();

            if($customerBanking == null){
                $customerBanking = new CustomerBanking();
            }

            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            if($profile->omang != null){
                $id = $profile->omang;
                $idType = 'I';
            }else{
                $id = $profile->passport;
                $idType = 'P';
            }
            $curl = curl_init();

            $checkClient = $this->checkClientExists($policy->id);

            if($checkClient == false){
                curl_setopt_array($curl, array(
                    CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/clients/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS =>"{\r\n  \"ClientPostRequest\": [\r\n    {\r\n
           \"ClientNumber\": \"$policy->policyNumber\",\r\n
           \"ClientName\": \"$customer->firstName $customer->lastName\",\r\n
           \"IDType\": \"$idType\",\r\n
           \"IDNumber\": \"$id\",\r\n
           \"CellphoneNumber\": \"$customer->cellphone\",\r\n
           \"EMail\": \"$customer->email\",\r\n
           \"BankCode\": \"$request->BankCode\",\r\n
           \"BranchCode\": \"$request->BranchCode\",\r\n
           \"AccountType\": \"$request->accountType\",\r\n
           \"AccountNumber\": \"$request->accountNumber\",\r\n
           \"AccountHolderName\": \"$customer->firstName $customer->lastName\",\r\n
           \"EmployeeGroupCode\": \"OT\",\r\n
           }\r\n
           ]\r\n
           }",
                    CURLOPT_HTTPHEADER => array(
                        "Content-Type: application/json",
                        "Accept: application/json",
                        "Authorization: ".$token
                    ),
                ));

                $response = curl_exec($curl);
                $data = json_decode($response,true);

                curl_close($curl);
            }else{
                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/clients/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "PUT",
                    CURLOPT_POSTFIELDS =>"{\r\n  \"ClientPutRequest\": [\r\n    {\r\n
                            \"ClientNumber\": \"$policy->policyNumber\",\r\n
                           \"ClientName\": \"$customer->firstName $customer->lastName\",\r\n
                           \"IDType\": \"$idType\",\r\n
                           \"IDNumber\": \"$id\",\r\n
                           \"CellphoneNumber\": \"$customer->cellphone\",\r\n
                           \"EMail\": \"$customer->email\",\r\n
                           \"BankCode\": \"$request->bankCode\",\r\n
                           \"BranchCode\": \"$request->branchCode\",\r\n
                           \"AccountType\": \"$request->accountType\",\r\n
                           \"AccountNumber\": \"$request->accountNumber\",\r\n
                           \"AccountHolderName\": \"$customer->firstName $customer->lastName\",\r\n
                           \"EmployeeGroupCode\": \"OT\",\r\n
                           }\r\n
                           ]\r\n
                           }",
                    CURLOPT_HTTPHEADER => array(
                        "Content-Type: application/json",
                        "Accept: application/json",
                        "Authorization: ".$token
                    ),
                ));

                $response = curl_exec($curl);
                $data = json_decode($response,true);

                curl_close($curl);
            }

            //if(!empty($data['ClientPostResponse'][0]['Successful']) && empty($data['ClientPostResponse'][0]['Failed'])){
            $customerBanking->bankName = $request->BankName;
            $customerBanking->branchCode = $request->BranchCode;
            $customerBanking->accountType = $request->accountType;
            $customerBanking->accountNumber = $request->accountNumber;
            $customerBanking->billing = "RealPay";
            $customerBanking->billing_day = $request->billing_day;
            $customerBanking->billingStartDate = $this->setDate($request->billing_day);
            $customerBanking->save();


            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $policy = Policy::where('id',$policy->id)->first();

            if($policy->product_id != 3)
                $policy->first_premium_wvat = 0;

            $firstBillingDate = ($request->first_collection_date) ? $request->first_collection_date : $request->billingDay;
            $firstCollectionAmount = ($request->first_premium) ? $request->first_premium : $request->premium;
            $numberOfInstallments = '12';
            $frequency = 'MNTH';
            $premium = $request->premium;

//                if($policy->premium_freq == 1 && $policy->first_premium_wvat > 0){
//                    $premium = $policy->premium;
//                    $now = new DateTime();
//                    $firstBillingDate = $now->format('Y-m-d');
//                    $firstCollectionAmount = $policy->first_premium_wvat;
//                    $numberOfInstallments = '99';
//                }

//                if($policy->premium_freq == 1 && $policy->first_premium_wvat == 0){
//                    $premium = $policy->premium;
//                }

            if($request->frequency != null){
//                    $premium = $policy->premium;
//                    $now = new DateTime();
//                    $policy->billingStartDate = $now->format('Y-m-d');

                if($request->frequency == 2){
                    $numberOfInstallments = '3';
                }
                elseif($request->frequency == 3){
                    $frequency = 'YEAR';
                    $numberOfInstallments = '1';
                }
                elseif($request->frequency == 1){
                    $numberOfInstallments = '12';
                }
                else{
                    if($policy->quoteNumber) {
                        $quote = MotorComprehensiveQuotes::where('quoteNumber', $policy->quoteNumber)->first(array('premiumMonthly'));
                        $premium = $quote->premiumMonthly;
                        $numberOfInstallments = '12';
                        $policy->premium_freq = 1;
                        $policy->save();
                    }else{
                        return null;
                    }
                }
            }

            $billing_day = \Carbon\Carbon::createFromFormat('Y-m-d', $request->billingDay)->format('d');

            if($billing_day == 31 || $billing_day == 30 || $billing_day == 29){
                $billing_day = 99;
            }

            $contractNumber = RealpayClientContracts::getContractNumber($policy->id);

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/contracts/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS =>"{\r\n
            \"ContractPostRequest\": [\r\n
                {\r\n
                      \"ClientNumber\": \"$policy->policyNumber\",\r\n
                      \"ContractNumber\": \"$contractNumber\",\r\n
                      \"FrequencyCode\": \"$frequency\",\r\n
                      \"CollectionDay\": \"$billing_day\",\r\n
                      \"TrackingCode\": \"44\",\r\n
                      \"FirstCollectionDate\": \"$firstBillingDate\",\r\n
                      \"FirstCollectionAmount\": \"$firstCollectionAmount\",\r\n
                      \"InstalmentStartDate\": \"$request->billingDay\",\r\n
                      \"InstalmentAmount\": $premium,\r\n
                      \"NumberOfInstalments\": \"$numberOfInstallments\",\r\n
                      \"CTCPercentage\": 1\r\n
                      }\r\n
                 ]\r\n}",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: " . $token
                ),
            ));
            $response = curl_exec($curl);
            $data = json_decode($response, true);

            curl_close($curl);

            $update = RealpayPaymentRequest::where('policy_id', $policy->id)->first();
            if (sizeof($data['ContractPostResponse'][0]['Successful']) > 0 && sizeof($data['ContractPostResponse'][0]['Failed']) == 0) {
                if(sizeof($data['ContractPostResponse'][0]['Successful'][0]['ContractInstalments']) > 0){
                    $contract = $this->storeContractDetails($data['ContractPostResponse'][0]['Successful'][0]);
                    $installments = $this->storeInstallments($data['ContractPostResponse'][0]['Successful'][0]);
                }

                $logData = [
                    'policy_id'=>$policy->id,
                    'client_number'=>$policy->policyNumber,
                    'contract_number'=>$contractNumber,
                    //'rate_id'=>$data['rate_id'],
                    'status'=>1,
                ];

                $addLog = RealpayClientContracts::addLog($logData);

                if($contractNumber != null) {
                    $Table = (new RealpayClientContracts())->getTable();
                    DB::table($Table)->where('client_number', $policy->policyNumber)
                        ->where('contract_number', '!=',$contractNumber)
                        ->update(array('status' => 0));
                }

                //\Illuminate\Support\Facades\DB::commit();
                if (isset($request->returnBlade) && $request->returnBlade == 'edit') {
                    return Redirect::route('admin.policy.edit',$request->policyID)->with('success', 'Client added successfully on realpay');
                } elseif (isset($request->returnBlade) && $request->returnBlade == 'view') {
                    return Redirect::route('admin.policy.policyView',$request->policyID)->with('success', 'Client added successfully on realpay');
                } else{
                    return Redirect::route('admin.getCustomer')->with('success', 'Client added successfully on realpay');
                }

            } else {

                if($data['ContractPostResponse'][0]['Failed'][0]['Failures'][0]['FailureCode'] == 'TAK1'){
                    $logData = [
                        'policy_id'=>$policy->id,
                        'client_number'=>$policy->policyNumber,
                        'contract_number'=>$contractNumber,
                        'status'=>1,
                    ];

                    $addLog = RealpayClientContracts::addLog($logData);

                    if($contractNumber != null) {
                        $Table = (new RealpayClientContracts())->getTable();
                        DB::table($Table)->where('client_number', $policy->policyNumber)
                            ->where('contract_number', '!=',$contractNumber)
                            ->update(array('status' => 0));
                    }
                }

                //\Illuminate\Support\Facades\DB::rollback();
                if (isset($request->returnBlade) && $request->returnBlade == 'edit') {
                    return Redirect::route('admin.policy.edit',$request->policyID)->with('error', $data['ContractPostResponse'][0]['Failed'][0]['Failures'][0]['FailureDescription']);
                } elseif (isset($request->returnBlade) && $request->returnBlade == 'view') {
                    return Redirect::route('admin.policy.policyView',$request->policyID)->with('error', $data['ContractPostResponse'][0]['Failed'][0]['Failures'][0]['FailureDescription']);
                } else{
                    return Redirect::route('admin.getCustomer')->with('error', $data['ContractPostResponse'][0]['Failed'][0]['Failures'][0]['FailureDescription']);
                }
            }
//            }else{
//                \Illuminate\Support\Facades\DB::rollback();
//                return Redirect::route('admin.getCustomer')->with('error', $data['ClientPostResponse'][0]['Failed'][0]['Failures'][0]['FailureDescription']);
//            }
        }catch(\Exception $e){
            //\Illuminate\Support\Facades\DB::rollback();
            if (isset($request->returnBlade) && $request->returnBlade == 'edit') {
                return Redirect::route('admin.policy.edit',$request->policyID)->with('error', $e->getMessage());
            } elseif (isset($request->returnBlade) && $request->returnBlade == 'view') {
                return Redirect::route('admin.policy.policyView',$request->policyID)->with('error', $e->getMessage());
            } else{
                return Redirect::route('admin.getCustomer')->with('error', $e->getMessage());
            }
        }
    }

    public function setDate($day)
    {
        if($day != 99){
            $current_timestamp = \Carbon\Carbon::now()->timestamp;
            $newDate = date("d", $current_timestamp);
            $mnth = (int)date("m", $current_timestamp);
            $yr = (int)date("Y", $current_timestamp);

            $v = cal_days_in_month(CAL_GREGORIAN, $mnth, $yr);

            $days = $newDate - $day;
            if ($days < 0) {
                $date = Carbon::now()->addDays(abs($days))->format('Y-m-d');
                return $date;
            } elseif ($days > 0) {
                $date = Carbon::now()->addDays($v - abs($days))->format('Y-m-d');  /*env('AVERAGE_DAYS')*/
                return $date;
            } else {
                $date = Carbon::now()->format('Y-m-d');
                return $date;
            }
        }else{
            return \Carbon\Carbon::now()->endOfMonth()->toDateString();
        }
    }

    public function getCustomer(Request $request){
        try{
            return view('admin.Realpay.customerInfo');
        }catch(\Exception $e){

        }
    }

    public function fetchCustomer(Request $request){
        try{
            $policy = Policy::where('policyNumber',$request->policyNumber)->first(array('id','customer_id','policyNumber'));
            $customer = Customer::join('customer_profile', 'customer_profile.customer_id', 'customer.id')
                ->where('customer.id',$policy->customer_id)
                ->first();

            $banks = Banks::get(array('id','bank_number','bank_name'));
            $branches = BankBranches::get(array('branch_id','name'));

            $id = '-';
            if($customer->omang && $customer->passport == null){
                $id = 'Omang: '.$customer->omang.' - '.'Passport: N/A';
            }else{
                $id = $id = 'Omang: N/A'.' - '.'Passport: '.$customer->passport;
            }

            if (isset($request->returnBlade)) {
                $returnBlade = $request->returnBlade;
            } else {
                $returnBlade = null;
            }

            return view('admin.accounts.addRealPayClient',compact('customer','banks','branches','policy','id','returnBlade'));
        }catch(\Exception $ex){

        }
    }

    public function addReratingPaymentRealpay(Request $request)
    {
        //update banking details
        $banking = CustomerBanking::where('policy_id',$request->policy_id)
            ->orderBy('id','DESC')
            ->first();

        $controller = new PolicyController();

        $policyRerate = PolicyPremiumReratingLog::where('ratings_id',$request->rate_id)->first();

        $vehicle = array(
            'policy_id'=>$request->policy_id,
            'estimated_value'=>$policyRerate->sum_assured,
            'make'=>$policyRerate->make,
            'model'=>$policyRerate->model,
            'year'=>$policyRerate->manufacturing_year,
            'is_imported'=>($policyRerate->japnese_import == 'Yes') ? 1 : 0,
        );

        $checkClientExist = $this->checkClientExists($request->policy_id);

        if($checkClientExist == false){
            $clientNumber = $this->addClientRealpay($request,$request->policy_id);
            if($clientNumber != null){
                $createContract = $this->storeClientContractRealpay($request->policy_id,$request->all());

                $rerateTable = (new PolicyPremiumReratingLog())->getTable();
                $update1 = \Illuminate\Support\Facades\DB::table($rerateTable)
                    ->where('ratings_id','=',$request->rate_id)
                    ->update(array('status' => 1,'payment_status'=>1));

                $update2 = \Illuminate\Support\Facades\DB::table($rerateTable)
                    ->where('ratings_id','!=',$request->rate_id)
                    ->where('policy_number','=',$policyRerate->policy_number)
                    ->update(array('status' => 2,'payment_status'=>0));

                $updateVehicle = $controller->updateVehicleInfomation($vehicle);

                return Redirect::route('admin.policy.edit', $request->policy_id)->with('success', 'Client Updated Successfully');
            }else {
                return Redirect::back()->with('error', 'Problem adding client on realpay');
            }
        }elseif($checkClientExist == true){
            $createContract = $this->storeClientContractRealpay($request->policy_id, $request->all());

            $rerateTable = (new PolicyPremiumReratingLog())->getTable();
            $update = \Illuminate\Support\Facades\DB::table($rerateTable)
                ->where('ratings_id','=',$request->rate_id)
                ->update(array('status' => 1,'payment_status'=>1));

            $update = \Illuminate\Support\Facades\DB::table($rerateTable)
                ->where('ratings_id','!=',$request->rate_id)
                ->where('policy_number','=',$policyRerate->policy_number)
                ->update(array('status' => 2,'payment_status'=>0));

            $updateVehicle = $controller->updateVehicleInfomation($vehicle);

            return Redirect::route('admin.policy.edit', $request->policy_id)->with('success', 'Client Updated Successfully');
        }else{
            return Redirect::back()->with('error', 'Unable to process realpay payment');
        }
    }

    public function checkClientExists($policy_id)
    {
        $policy = Policy::where('id',$policy_id)->first(array('policyNumber'));
        $fetchToken = $this->clientAuth();
        if($fetchToken['token_type'] && $fetchToken['access_token'])
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;

        $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

        if (!isset($customerBanking)) {
            $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
        }

        $url = '';
        if (isset($customerBanking) && $customerBanking->bankName == 12) {
            $url = env('REALPAY_BASE_URL').'/maintain/clients/'.env('REALPAY_FNB_PRODUCT')."?ClientNumber=".$policy->policyNumber."&BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION');
        } else {
            $url = env('REALPAY_BASE_URL').'/maintain/clients/'.env('REALPAY_PRODUCT')."?ClientNumber=".$policy->policyNumber."&BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION');
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
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

        if(empty($data['ClientGetResponse'])){
            return false;
        }else{
            return true;
        }
    }

    public function updateClientContract($id,$data){
        $log = new UpdateRealpayContract();
        $log->rate_id = $data['rate_id'];
        $log->new_date = $data['billingDay'];
        $log->frequency = $data['frequency'];
        $log->first_collection_date = $data['first_collection_date'];
        $log->first_premium = $data['first_premium'];
        $log->premium = $data['premium'];
        $log->policyNumber = $data['policyNumber'];
        $log->bank = $data['bank'];
        $log->branch = $data['branches'];
        $log->account_type = $data['AccountType'];
        $log->account_number = $data['AccountNumber'];
        $log->status = 0;
        $log->save();

        return true;
    }

    public function addClientRealpay(Request $request,$policy_id){
        try{

            $policy = Policy::where('id',$policy_id)->first();
            $customer = Customer::where('id', $policy->customer_id)->with('profile')->first();
            $profile = CustomerProfile::where('customer_id',$customer->id)->first();

            if($profile == null){
                $profile = new CustomerProfile();
            }

            $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
            $customerBanking->billing = "RealPay";
            $customerBanking->billingCell = $customer->cellphone;
            $customerBanking->bankName = $request->bank;
            $customerBanking->branchCode = $request->branches;
            $customerBanking->accountType = $request->AccountType;
            $customerBanking->accountNumber = $request->AccountNumber;
            $customerBanking->save();

            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            if($profile->omang != null){
                $id = $profile->omang;
                $idType = 'I';
            }else{
                $id = $profile->passport;
                $idType = 'P';
            }
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/clients/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS =>"{\r\n  \"ClientPostRequest\": [\r\n    {\r\n
           \"ClientNumber\": \"$policy->policyNumber\",\r\n
           \"ClientName\": \"$customer->firstName $customer->lastName\",\r\n
           \"IDType\": \"$idType\",\r\n
           \"IDNumber\": \"$id\",\r\n
           \"CellphoneNumber\": \"$customer->cellphone\",\r\n
           \"EMail\": \"$customer->email\",\r\n
           \"BankCode\": \"$request->bank\",\r\n
           \"BranchCode\": \"$request->branches\",\r\n
           \"AccountType\": \"$request->AccountType\",\r\n
           \"AccountNumber\": \"$request->AccountNumber\",\r\n
           \"AccountHolderName\": \"$customer->firstName $customer->lastName\",\r\n
           \"EmployeeGroupCode\": \"OT\",\r\n
           }\r\n
           ]\r\n
           }",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: ".$token
                ),
            ));

            $response = curl_exec($curl);
            $data = json_decode($response,true);
            curl_close($curl);

            return $policy->policyNumber;

        }catch(\Exception $ex){
            return null;
        }
    }

    public function checkClientContractExists($policy_id)
    {
        $policy = Policy::where('id',$policy_id)->first();

        $fetchToken = $this->clientAuth();
        if($fetchToken['token_type'] && $fetchToken['access_token'])
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/contracts/".env('REALPAY_PRODUCT')."?ClientNumber=".$policy->policyNumber."&ContractNumber=".$policy->id."&BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
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

        //empty($data['ContractGetResponse'][0]['ContractInstalments']) ---check the number of instalments
        //dd(count($data['ContractGetResponse'][0]['ContractInstalments']));

        return $data['ContractGetResponse'];

    }

    public function storeClientContractRealpay($policyId,$data){
        try{

            $fetchToken = $this->clientAuth();

            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                dd('Please clear cache and config');
            //return null;

            $policy = Policy::where('id',$policyId)->first();
            $rerate = PolicyPremiumReratingLog::where('policy_number',$policy->policyNumber)->first();

            $firstCollectionAmount = ($data['first_premium'] != null) ? $data['first_premium'] : $data['premium'];

            $frequency = 'MNTH';

            $billing_day = \Carbon\Carbon::createFromFormat('Y-m-d', $data['billingDay'])->format('d');

            $premium = $data['premium'];

            if($data['frequency'] == 2){
                $numberOfInstallments = '3';
                //$premium = $data['premium'];
            }
            elseif($data['frequency'] == 3){
                $frequency = 'YEAR';
                $numberOfInstallments = '99';
                //$premium = $rerate->annual_ins;
            }
            else{
                $numberOfInstallments = '99';
                //$premium = $rerate->month_ins;
            }

            if(($billing_day == 31 || $billing_day == 30 || $billing_day == 29) && $data['frequency'] == 1){
                $billing_day = 99;
            }

            $billingDate = $data['billingDay'];

            $contractNumber = RealpayClientContracts::getContractNumber($policy->id);

            if(isset($data['first_collection_date']) && $data['first_collection_date'] != null)
                $firstCollectionDate = $data['first_collection_date'];
            else
                $firstCollectionDate = $billingDate;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/contracts/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS =>"{\r\n
            \"ContractPostRequest\": [\r\n
                {\r\n
                      \"ClientNumber\": \"$policy->policyNumber\",\r\n
                      \"ContractNumber\": \"$contractNumber\",\r\n
                      \"FrequencyCode\": \"$frequency\",\r\n
                      \"CollectionDay\": \"$billing_day\",\r\n
                      \"TrackingCode\": \"44\",\r\n
                      \"FirstCollectionDate\": \"$firstCollectionDate\",\r\n
                      \"FirstCollectionAmount\": \"$firstCollectionAmount\",\r\n
                      \"InstalmentStartDate\": \"$billingDate\",\r\n
                      \"InstalmentAmount\": $premium,\r\n
                      \"NumberOfInstalments\": \"$numberOfInstallments\",\r\n
                      \"CTCPercentage\": 1\r\n
                      }\r\n
                 ]\r\n}",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: " . $token
                ),
            ));
            $response = curl_exec($curl);
            $resdata = json_decode($response, true);


            curl_close($curl);

            $logData = [
                'policy_id'=>$policy->id,
                'client_number'=>$policy->policyNumber,
                'contract_number'=>$contractNumber,
                'rate_id'=>$data['rate_id'],
                'status'=>1,
            ];

            $rate_id = $data['rate_id'];

            $addLog = RealpayClientContracts::addLog($logData);

            //update customer banking details here
            $banking = CustomerBanking::where('policy_id',$policy->id)->first();
            $banking->bankName = $data['bank'];
            $banking->branchCode = $data['branches'];
            $banking->accountType = $data['AccountType'];
            $banking->billingStartDate = $rerate->new_date;
            $banking->billing_day = $billing_day;
            $banking->billing = "RealPay";
            $banking->accountNumber = $data['AccountNumber'];
            $banking->save();

            $policy->premium_freq = $data['frequency'];
            $policy->first_premium_wvat = $data['first_premium'];
            $policy->premium = $data['premium'];
            $policy->billingStartDate = $billingDate;
            $policy->policyDocument = null;
            $policy->sum_assured = $rerate->sum_assured;
            $policy->save();

            $rerateTable = (new RealpayClientContracts())->getTable();
            $update = \Illuminate\Support\Facades\DB::table($rerateTable)
                ->where('policy_id','=',$policy->id)
                ->where('rate_id','!=',$rate_id)
                ->orWhere('rate_id','=',null)
                ->update(array('status' => 0));

            $update = DB::select(DB::raw('SET SQL_SAFE_UPDATES = 0;
                    UPDATE realpay_client_contracts SET status = 0
                    where ((rate_id != '.$rate_id.' || rate_id !=null) AND policy_id = '.$policy->id.');')
            );

            if(sizeof($data['ContractPostResponse'][0]['Successful'][0]['ContractInstalments']) > 0){
                $contract = $this->storeContractDetails($data['ContractPostResponse'][0]['Successful'][0]);
                $installments = $this->storeInstallments($data['ContractPostResponse'][0]['Successful'][0]);
            }

            return true;

//            if (sizeof($data['ContractPostResponse'][0]['Successful']) > 0 && sizeof($data['ContractPostResponse'][0]['Failed']) == 0) {
//
//                //update customer banking details here
//                $banking = CustomerBanking::where('policy_id',$policy->id)->first();
//                $banking->bankName = $data['bank'];
//                $banking->branchCode = $data['branches'];
//                $banking->accountType = $data['AccountType'];
//                $banking->billingStartDate = $rerate->new_date;
//                $banking->billing_day = $billing_day;
//                $banking->billing = "RealPay";
//                $banking->accountNumber = $data['AccountNumber'];
//                $banking->save();
//
//                $policy->premium_freq = $data['frequency'];
//                $policy->first_premium = $data['first_premium'];
//                $policy->premium = $data['premium'];
//                $policy->billingStartDate = $billingDate;
//                $policy->policyDocument = null;
//                $policy->save();
//
//                $rerateTable = (new RealpayClientContracts())->getTable();
//                $update = \Illuminate\Support\Facades\DB::table($rerateTable)
//                    ->where('policy_id','=',$policy->id)
//                    ->where('ratings_id','!=',$rate_id)
//                    ->update(array('status' => 0));
//
//                if(sizeof($data['ContractPostResponse'][0]['Successful'][0]['ContractInstalments']) > 0){
//                    $contract = $this->storeContractDetails($data['ContractPostResponse'][0]['Successful'][0]);
//                    $installments = $this->storeInstallments($data['ContractPostResponse'][0]['Successful'][0]);
//                }
//                return true;
//            } else {
//                return false;
//            }
        }catch(\Exception $e){
            return null;
        }
    }

    public function addClient(Request $request){
        try{
            $banks = Banks::get(array('bank_number','bank_name'));
            return view('admin.accounts.addRealPayClient',compact('banks'));
        }catch(\Exception $ex){

        }
    }

    public function createClient($policy_id)
    {
        try{
            $policy = Policy::where('id',$policy_id)->first();
            $customer = Customer::where('id', $policy->customer_id)->with('profile')->first();
            $profile = CustomerProfile::where('customer_id',$customer->id)->first();

            $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

            if (!isset($customerBanking)) {
                $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
            }

            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            if($profile->omang != null){
                $id = $profile->omang;
                $idType = 'I';
            }else{
                $id = $profile->passport;
                $idType = 'P';
            }

            $url = '';
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $url = env('REALPAY_BASE_URL')."/maintain/clients/".env('REALPAY_FNB_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION');
            } else {
                $url = env('REALPAY_BASE_URL')."/maintain/clients/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION');
            }

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS =>"{\r\n  \"ClientPostRequest\": [\r\n    {\r\n
           \"ClientNumber\": \"$policy->policyNumber\",\r\n
           \"ClientName\": \"$customer->firstName $customer->lastName\",\r\n
           \"IDType\": \"$idType\",\r\n
           \"IDNumber\": \"$id\",\r\n
           \"CellphoneNumber\": \"$customer->cellphone\",\r\n
           \"EMail\": \"$customer->email\",\r\n
           \"BankCode\": \"$customerBanking->bankName\",\r\n
           \"BranchCode\": \"$customerBanking->branchCode\",\r\n
           \"AccountType\": \"$customerBanking->accountType\",\r\n
           \"AccountNumber\": \"$customerBanking->accountNumber\",\r\n
           \"AccountHolderName\": \"$customer->firstName $customer->lastName\",\r\n
           \"EmployeeGroupCode\": \"OT\",\r\n
           }\r\n
           ]\r\n
           }",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: ".$token
                ),
            ));

            $response = curl_exec($curl);
            $data = json_decode($response,true);
            curl_close($curl);

            $update = RealpayPaymentRequest::where('policy_id',$policy_id)->first();
            if(!empty($data['ClientPostResponse'][0]['Successful']) && empty($data['ClientPostResponse'][0]['Failed'])){
                $update->clientNumber = $policy->policyNumber;
                $update->client_response_sequence = $data['APIResponse']['CallSequence'];
                $update->response = 1;
                $update->clientCreated = 1;
                $update->save();
                return $policy->policyNumber;

            }else{
                $update->status = 2;
                $update->clientCreated = 2;
                $update->contractCreated = 2;
                $update->client_response_sequence = $data['APIResponse']['CallSequence'];
                //$update->response = serialize($data['ContractPostResponse'][0]['Failed'][0]['Failures']);
                $update->save();

                $log = RealpayLogs::where('policy_id',$policy_id)->orderBy('id', 'DESC')->first();
                $log->status = 2;
                $log->save();

                return null;
            }
        }catch(\Exception $e){
            $update = RealpayPaymentRequest::where('policy_id',$policy_id)->first();
            $update->clientCreated = 2;
            $update->contractCreated = 2;
            $update->status = 2;
            $update->save();

            $log = RealpayLogs::where('policy_id',$policy_id)->orderBy('id', 'DESC')->first();
            $log->status = 2;
            $log->save();
            return null;
        }
    }

    public function cancelRealpayContracts()
    {
        $realpayClientContract = RealpayClientContracts::where('status',0)->orderBy('id','DESC')->first();

        $fetchToken = $this->clientAuth();
        if($fetchToken['token_type'] && $fetchToken['access_token'])
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;

        $curl = curl_init();

        if($realpayClientContract){
            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/contracts/" . env('REALPAY_PRODUCT') . "?ClientNumber=" . $realpayClientContract->client_number . "&ContractNumber=" . $realpayClientContract->contract_number . "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
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
                    "Authorization: " . $token
                ),
            ));

            $response = curl_exec($curl);
            $data = json_decode($response, true);

            if (!empty($data['ContractGetResponse']) && count($data['ContractGetResponse'][0]['ContractInstalments']) > 0) {

                foreach ($data['ContractGetResponse'][0]['ContractInstalments'] as $key => $ins) {
                    if ($ins != null && $ins['InstalmentStatus'] == "A") {

                        $clientNum = $data['ContractGetResponse'][0]['ClientNumber'];
                        $contractSeq = $data['ContractGetResponse'][0]['ContractSequence'];
                        $contractNumber = $data['ContractGetResponse'][0]['ContractNumber'];
                        $insSeq = (int)$ins['InstalmentSequence'];

                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                            CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/instalments/" . env('REALPAY_PRODUCT') . "?BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => "",
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => "PUT",
                            CURLOPT_POSTFIELDS => "{\r\n  \"InstalmentPutRequest\": [\r\n    {\r\n
                            \"ClientNumber\": \"$clientNum\",\r\n
                           \"ContractSequence\": $contractSeq,\r\n
                           \"ContractNumber\": \"$contractNumber\",\r\n
                           \"InstalmentSequence\": $insSeq,\r\n
                           \"InstalmentStatus\": \"I\",\r\n
                           \"DebitSequenceType\": \"OOFF\",\r\n
                           }\r\n
                           ]\r\n
                           }",
                            CURLOPT_HTTPHEADER => array(
                                "Content-Type: application/json",
                                "Accept: application/json",
                                "Authorization: " . $token
                            ),
                        ));

                        $response = curl_exec($curl);
                        $resdata = json_decode($response, true);
                    }
                }
            }

            $realpayClientContract->status = 2;
            $realpayClientContract->save();

            return $clientNum;
        }
//        else{
//            Log::info('Data not found for '.);
//        }

    }

    public function addClientContract($policyId)
    {
        try{
            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $policy = Policy::where('id',$policyId)->first();

            $policy->policyActivatedDate = Carbon::now()->format("Y-m-d");
            $policy->save();
            if($policy->product_id != 3)
                $policy->first_premium_wvat = 0;

            $firstBillingDate = '';
            $firstCollectionAmount = '';
            $numberOfInstallments = '99';
            $frequency = 'MNTH';

            if($policy->premium_freq == 1 && $policy->first_premium_wvat > 0){
                $premium = $policy->premium;
                $now = new DateTime();
                // $firstBillingDate = $now->format('Y-m-d');
                // $firstCollectionAmount = $policy->first_premium_wvat;
                $numberOfInstallments = '99';
                if($policy->BillingStart == "Later"){
                    $firstBillingDate = \Carbon\Carbon::parse($policy->billingStartDate)->format('Y-m-d');
                    $firstCollectionAmount = $policy->first_premium_wvat;
                }else{
                    $firstBillingDate = $now->format('Y-m-d');
                    $firstCollectionAmount = $policy->first_premium_wvat;
                }
            }

            if($policy->premium_freq == 1 && $policy->first_premium_wvat == 0){
                $premium = $policy->premium;
                if($policy->BillingStart == "Later"){
                    $firstBillingDate = \Carbon\Carbon::parse($policy->billingStartDate)->format('Y-m-d');
                    $firstCollectionAmount = $policy->premium;
                }else{
                    $firstBillingDate = $now->format('Y-m-d');
                    $firstCollectionAmount = $policy->premium;
                }
            }

            if($policy->premium_freq != 1 && ($policy->premium > 0 || $policy->premium != null) && $policy->billingStartDate){
                $premium = $policy->premium;
                $now = new DateTime();
                $policy->billingStartDate = $now->format('Y-m-d');

                if($policy->premium_freq == 2){
                    $numberOfInstallments = '3';
                }
                elseif($policy->premium_freq == 3){
                    $frequency = 'YEAR';
                    $numberOfInstallments = '99';
                }
                elseif($policy->premium_freq == 1){
                    $numberOfInstallments = '99';
                }
                else{
                    if($policy->quoteNumber) {
                        $quote = MotorComprehensiveQuotes::where('quoteNumber', $policy->quoteNumber)->first(array('premiumMonthly'));
                        $premium = $quote->premiumMonthly;
                        $numberOfInstallments = '99';
                        $policy->premium_freq = 1;
                        $policy->save();
                    }else{
                        return null;
                    }
                }
            }

            $contractNumber = RealpayClientContracts::getContractNumber($policy->id);
            if($policy->billing_day == 31 && $policy->premium_freq != 2){
                $policy->billing_day = 99;
            }

            $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

            if (!isset($customerBanking)) {
                $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
            }

            $url = '';
            $trackingCode = "44";
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $trackingCode = "B3";
                $url = env('REALPAY_BASE_URL')."/maintain/contracts/".env('REALPAY_FNB_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION');
            } else {
                $url = env('REALPAY_BASE_URL')."/maintain/contracts/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION');
            }

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS =>"{\r\n
            \"ContractPostRequest\": [\r\n
                {\r\n
                      \"ClientNumber\": \"$policy->policyNumber\",\r\n
                      \"ContractNumber\": \"$contractNumber\",\r\n
                      \"FrequencyCode\": \"$frequency\",\r\n
                      \"CollectionDay\": \"$policy->billing_day\",\r\n
                      \"TrackingCode\": \"$trackingCode\",\r\n
                      \"FirstCollectionDate\": \"$firstBillingDate\",\r\n
                      \"FirstCollectionAmount\": \"$firstCollectionAmount\",\r\n
                      \"InstalmentStartDate\": \"$policy->billingStartDate\",\r\n
                      \"InstalmentAmount\": $premium,\r\n
                      \"NumberOfInstalments\": \"$numberOfInstallments\",\r\n
                      \"CTCPercentage\": 1\r\n
                      }\r\n
                 ]\r\n}",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: " . $token
                ),
            ));
            $response = curl_exec($curl);
            $data = json_decode($response, true);
            curl_close($curl);


            $update = RealpayPaymentRequest::where('policy_id', $policyId)->first();
            if (sizeof($data['ContractPostResponse'][0]['Successful']) > 0 && sizeof($data['ContractPostResponse'][0]['Failed']) == 0) {
                $update->contract = $contractNumber;
                $update->contract_response_sequence = $data['APIResponse']['CallSequence'];
                $update->status = 1;
                $update->contractCreated = 1;
                $update->save();

                $log = RealpayLogs::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
                $log->status = 1;
                $log->save();

                if(sizeof($data['ContractPostResponse'][0]['Successful'][0]['ContractInstalments']) > 0){
                    $contract = $this->storeContractDetails($data['ContractPostResponse'][0]['Successful'][0]);
                    $installments = $this->storeInstallments($data['ContractPostResponse'][0]['Successful'][0]);
                }

                $logData = [
                    'policy_id'=>$policy->id,
                    'client_number'=>$policy->policyNumber,
                    'contract_number'=>$contractNumber,
                    'status'=>1,
                ];
                $addLog = RealpayClientContracts::addLog($logData);

                return $policy->policyNumber;
            } else {
                $update->status = 2;
                $update->contractCreated = 2;
                $update->contract_response_sequence = $data['APIResponse']['CallSequence'];
                $update->response = serialize($data['ContractPostResponse'][0]['Failed'][0]['Failures']);
                $update->save();

                $log = RealpayLogs::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
                $log->status = 2;
                $log->save();
                return null;
            }
        }catch(\Exception $e){
            $update = RealpayPaymentRequest::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
            $update->status = 2;
            $update->contractCreated = 2;
            $update->save();

            $log = RealpayLogs::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
            $log->status = 2;
            $log->save();
            return null;
        }
    }

    public function addContract(){
        return view('admin.Realpay.addClientContract');
    }

    /*public function addContractManually(Request $request)
    {
        $policyId = $request->policy_number;
        try{
            $date = $this->setDate($request->billingDay);

            $policy = Policy::where('policyNumber',$request->policy_number)->first();
            if($policy == null)
                return Redirect::back()->with('error', 'Policy not found with policy number: '.$request->policy_number);


            if($request->frequency == 2){
                $frequency = 'MNTH';
                $numberOfInstallments = '3';
            }
            elseif($request->frequency == 3){
                $frequency = 'YEAR';
                $numberOfInstallments = '99';
            }
            elseif($request->frequency == 1){
                $frequency = 'MNTH';
                $numberOfInstallments = '99';
            }

            $now = new DateTime();
            $firstBillingDate = $now->format('Y-m-d');

            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return Redirect::back()->with('error', 'Auth error');

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/contracts/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS =>"{\r\n
            \"ContractPostRequest\": [\r\n
                {\r\n
                      \"ClientNumber\": \"$request->policyNumber\",\r\n
                      \"ContractNumber\": \"$policy->id\",\r\n
                      \"FrequencyCode\": \"$frequency\",\r\n
                      \"CollectionDay\": \"$policy->billingDay\",\r\n
                      \"TrackingCode\": \"44\",\r\n
                      \"FirstCollectionDate\": \"$firstBillingDate\",\r\n
                      \"FirstCollectionAmount\": \"$request->first_premium\",\r\n
                      \"InstalmentStartDate\": \"$date\",\r\n
                      \"InstalmentAmount\": $request->premium,\r\n
                      \"NumberOfInstalments\": \"$numberOfInstallments\",\r\n
                      \"CTCPercentage\": 1\r\n
                      }\r\n
                 ]\r\n}",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: " . $token
                ),
            ));
            $response = curl_exec($curl);
            $data = json_decode($response, true);
            curl_close($curl);

            dd($data);

//            $log = new RealpayLogs();
//            $log->policy_id = $policyId;
//            $log->event = 1;
//            $log->save();
//
//            $payRequest = new RealpayPaymentRequest();
//            $payRequest->policy_id = $policy->id;
//            $payRequest->first_premium = $request->first_premium;
//            $payRequest->premium = $request->premium;
//            $payRequest->billing_day = $request->billingDay;
//            $payRequest->billing_date = $date;
//            $payRequest->first_premium_contract = null;
//            $payRequest->contract = null;
//            $payRequest->status = 0;
//            $payRequest->frequency = $request->frequency;
//            $payRequest->save();

//            $update = RealpayPaymentRequest::where('policy_id', $policyId)->first();
            if (sizeof($data['ContractPostResponse'][0]['Successful']) > 0 && sizeof($data['ContractPostResponse'][0]['Failed']) == 0) {

//                $update->contract = $policy->id;
//                $update->contract_response_sequence = $data['APIResponse']['CallSequence'];
//                $update->status = 1;
//                $update->contractCreated = 1;
//                $update->save();
//
//                $log = RealpayLogs::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
//                $log->status = 1;
//                $log->save();

                if(sizeof($data['ContractPostResponse'][0]['Successful'][0]['ContractInstalments']) > 0){
                    $contract = $this->storeContractDetails($data['ContractPostResponse'][0]['Successful'][0]);
                    $installments = $this->storeInstallments($data['ContractPostResponse'][0]['Successful'][0]);
                }

                return $policy->policyNumber;
            } else {
//                $update->status = 2;
//                $update->contractCreated = 2;
//                $update->contract_response_sequence = $data['APIResponse']['CallSequence'];
//                $update->response = serialize($data['ContractPostResponse'][0]['Failed'][0]['Failures']);
//                $update->save();
//
//                $log = RealpayLogs::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
//                $log->status = 2;
//                $log->save();
                return Redirect::back()->with('error', 'Failed to add payment on realpay');
            }
        }catch(\Exception $e){
//            $update = RealpayPaymentRequest::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
//            $update->status = 2;
//            $update->contractCreated = 2;
//            $update->save();
//
//            $log = RealpayLogs::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
//            $log->status = 2;
//            $log->save();

            return Redirect::back()->with('error', $e->getMessage().' '.$e->getLine());
        }
    }*/

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
        }catch(\Http\Client\Exception $ex){
            return false;
        }
    }

    public function storeInstallments($data){
        try{
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

            }else{
                return false;
            }
        }catch(\Http\Client\Exception $ex){
            return false;
        }
    }

    public function cancelRealpayContract($policyId)
    {
        $banking = CustomerBanking::where('policy_id', $policyId)->first();
        $policy = Policy::where('id',$policyId)->first();

        $clientContracts = RealpayClientContracts::where('policy_id',$policyId)
            ->orderBy('id','desc')
            ->get();

        if ($clientContracts->isEmpty()) {
            $clientContracts = RealpayPaymentRequest::where('policy_id',$policyId)->get();
            if(!$clientContracts->isEmpty()){
                foreach ($clientContracts as $key => $contract) {
                    $contract->contract_number = $contract->contract;
                }
            }
        }


        if($clientContracts != null){

            $now = new DateTime();
            $now->format('Y-m-d');
            $Token = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
            $fetchToken = $Token->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                $token = null;

            $successResult = 0;
            $contractFailed = array();

            if (isset($clientContracts)) {
                foreach ($clientContracts as $key => $contract) {
                    $url = "https://realpaycollect.com:4448/rpp/rpws/maintain/contracts/FNBNDOBW?ClientNumber=".$policy->policyNumber."&ContractNumber=".$contract->contract_number."&BeneficiaryUser=16244&Version=v1";
                    // dd(env('REALPAY_BASE_URL')."/maintain/contracts/".env('REALPAY_PRODUCT')."?ClientNumber=".$policy->policyNumber."&ContractNumber=".$contract->contract_number."&BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'));
                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "DELETE",
                        CURLOPT_POSTFIELDS =>"{\r\n  \"ClientPutRequest\": [\r\n    {\r\n      \"ClientNumber\": \"L00012\",\r\n      \"ContractNumber\": \"C1603\",\r\n      \"FrequencyCode\": \"MNTH\",\r\n      \"CollectionDay\": 25,\r\n      \"TrackingCode\": \"03\",\r\n      \"FirstCollectionDate\": \"YYYY-MM-DD HH24:MI\",\r\n      \"FirstCollectionAmount\": 123.45,\r\n      \"InstalmentStartDate\": \"YYYY-MM-DD HH24:MI\",\r\n      \"InstalmentAmount\": 123.45,\r\n      \"NumberOfInstalments\": 1,\r\n      \"CTCPercentage\": 1\r\n    }\r\n  ]\r\n}",
                        CURLOPT_HTTPHEADER => array(
                            "Content-Type: application/json",
                            "Accept: application/json",
                            "Authorization: ".$token
                        ),
                    ));

                    $response = curl_exec($curl);
                    $data = json_decode($response, true);
                    $success = sizeof($data['ContractDeleteResponse'][0]['Successful']) > 0;
                    curl_close($curl);
                    // dd($data);
                    // dd($data,$policy->policyNumber,$contract->contract_number);
                    if($success == true){
                        // $update = $this->actionAfterCancellingContract($policyId);
                        $update = $this->actionAfterCancellingContract($contract->contract_number);
                        $can = RealpayCancelRequests::where('policy_id',$policyId)->where('contract',$contract->contract_number)->first();
                        if (!isset($can)) {
                            $can = new RealpayCancelRequests();
                            $can->policy_id = $policyId;
                            $can->leftout_premium_contract = null;
                            $can->contract = $contract->contract_number;
                            $can->cancel_status = 0;
                            $can->save();
                        }
                    }
                    else{

                        $url_new = "https://realpaycollect.com:4448/rpp/rpws/maintain/contracts/RTFNBBW?ClientNumber=".$policy->policyNumber."&ContractNumber=".$contract->contract_number."&BeneficiaryUser=16244&Version=v1";
                        // dd(env('REALPAY_BASE_URL')."/maintain/contracts/".env('REALPAY_PRODUCT')."?ClientNumber=".$policy->policyNumber."&ContractNumber=".$contract->contract_number."&BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'));
                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                            CURLOPT_URL => $url_new,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => "",
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => "DELETE",
                            CURLOPT_POSTFIELDS =>"{\r\n  \"ClientPutRequest\": [\r\n    {\r\n      \"ClientNumber\": \"L00012\",\r\n      \"ContractNumber\": \"C1603\",\r\n      \"FrequencyCode\": \"MNTH\",\r\n      \"CollectionDay\": 25,\r\n      \"TrackingCode\": \"03\",\r\n      \"FirstCollectionDate\": \"YYYY-MM-DD HH24:MI\",\r\n      \"FirstCollectionAmount\": 123.45,\r\n      \"InstalmentStartDate\": \"YYYY-MM-DD HH24:MI\",\r\n      \"InstalmentAmount\": 123.45,\r\n      \"NumberOfInstalments\": 1,\r\n      \"CTCPercentage\": 1\r\n    }\r\n  ]\r\n}",
                            CURLOPT_HTTPHEADER => array(
                                "Content-Type: application/json",
                                "Accept: application/json",
                                "Authorization: ".$token
                            ),
                        ));

                        $response = curl_exec($curl);
                        $data = json_decode($response, true);
                        $success = sizeof($data['ContractDeleteResponse'][0]['Successful']) > 0;
                        curl_close($curl);
                        // dd($data);
                        // dd($data,$policy->policyNumber,$contract->contract_number);
                        if($success == true){
                            // $update = $this->actionAfterCancellingContract($policyId);
                            $update = $this->actionAfterCancellingContract($contract->contract_number);
                            $can = RealpayCancelRequests::where('policy_id',$policyId)->where('contract',$contract->contract_number)->first();
                            if (!isset($can)) {
                                $can = new RealpayCancelRequests();
                                $can->policy_id = $policyId;
                                $can->leftout_premium_contract = null;
                                $can->contract = $contract->contract_number;
                                $can->cancel_status = 0;
                                $can->save();
                            }
                        }
                        else{
                            $successResult ++;
                        }

                    }

                }
            }

            if($successResult == 0){
                return $policy->policyNumber;
            }
            else{
                return null;
            }
        }else{
            return $policy->policyNumber;
        }

    }

    public function getBankResponseCodes($resCode){
        try{
            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL')."/general/bank_responses/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
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
            $resp = $data['BankResponseGetResponse'][0]['Products'][0]['BankResponses'];
            $status = $data['APIResponse']['Status'];
            foreach($resp as $res){
                if($res['ResponseCode'] == $resCode){
                    return $res['ResponseDescription'];
                }
            }
            return 'No Code Description Found';

        } catch (\Exception $e) {

        }
    }

    public function getClient(Request $request){
        try{
            return view('admin.Realpay.getClient');
        }catch(\Http\Client\Exception $e){
            return Redirect::back()->with('error', $e->getMessage());
        }
    }

//    public function updateInstallment(Request $request){
//        try {
//            $data = $request->all();
//            $clientNumber = $data['InstalmentGetResponse'][0]['ClientNumber'];
//            $contractNumber = $data['InstalmentGetResponse'][0]['ContractNumber'];
//            $instalmentReferenceNumber = $data['InstalmentGetResponse'][0]['InstalmentReferenceNumber'];
//            $status = $data['InstalmentGetResponse'][0]['InstalmentStatus'];
//            $actionDate = $data['InstalmentGetResponse'][0]['InstalmentActionDate'];
//            $trackingCode = $data['InstalmentGetResponse'][0]['TrackingCode'];
//            $amount = $data['InstalmentGetResponse'][0]['InstalmentAmount'];
//            $sequence = $data['InstalmentGetResponse'][0]['InstalmentSequence'];
//            $instalmentResponse = null;
//
//            $check = Policy::where('id',$contractNumber)
//                ->where('policyNumber',$clientNumber)
//                ->orWhere('policyNumber',$contractNumber)
//                ->first(array('id','policyNumber','customer_id'));
//            $pc = new PolicyController();
//            if($check != null){
//                if(array_key_exists('ResponseCode', $data['InstalmentGetResponse'][0])){
//                    if (isset($data['InstalmentGetResponse'][0]['ResponseCode']) && $data['InstalmentGetResponse'][0]['ResponseCode'] != '00')
//                        $instalmentResponse = $this->getBankResponseCodes($data['InstalmentGetResponse'][0]['ResponseCode']);
//                    else
//                        $instalmentResponse = 'Success';
//                }
//
//                if($clientNumber != null
//                    && $contractNumber != null
//                    && $instalmentReferenceNumber  != null
//                    && $status  != null
//                    && $actionDate  != null
//                    && $trackingCode  != null
//                    && $amount  != null
//                    && $sequence != null) {
//                    $update = RealpayContractInstallments::where('clientNumber', $clientNumber)
//                        ->where('contractNumber', $contractNumber)
//                        ->where('InstalmentReferenceNumber', $instalmentReferenceNumber)
//                        ->where('InstalmentSequence', $sequence)
//                        ->first();
//
//                    if ($update != null) {
//                        $update->InstalmentActionDate = $actionDate;
//                        $update->TrackingCode = $trackingCode;
//                        $update->InstalmentAmount = $amount;
//                        $update->InstalmentStatus = $status;
//                        $update->instalmentResponse = $instalmentResponse;
//                        $update->save();
//
//                        $policy = Policy::where('id', $contractNumber)->orWhere('policyNumber',$contractNumber)->first();
//                        $curStatus = $policy->status;
//
//                        if ($curStatus != 1 && $status == 'S') {
//                            $pc = new PolicyController();
//                            $update = $pc->updatePolicyDates($policy->policyNumber,1);
//                        }
//
//                        if ($status == 'S') { //Keep the status unchanged even if payment is failed: given by KAMLESH to KARTHIK
//                            $policy->status = 1;
//                            $policy->save();
//
//                            $update = $pc->updatePolicyDates($policy->polciNumber,1);
//                        } elseif ($status == 'F') {
//                            $policy->status = $curStatus;
//                            $policy->save();
//                        } else {
//                            $policy->status = $curStatus;
//                            $policy->save();
//                        }
//                    } else {
//                        $policy = Policy::where('id', $contractNumber)->orWhere('policyNumber',$contractNumber)->first();
//                        $curStatus = $policy->status;
//                        if ($policy != null) { //Keep the status unchanged even if payment is failed: given by KAMLESH to KARTHIK
//                            if ($status == 'S') {
//                                $policy->status = 1;
//                                $policy->save();
//
//                                $update = $pc->updatePolicyDates($policy->polciNumber,1);
//                            } elseif ($status == 'F') {
//                                $policy->status = $curStatus;
//                                $policy->save();
//                            } else {
//                                $policy->status = $curStatus;
//                                $policy->save();
//                            }
//                        }
//                    }
//
//                    $trans = Transaction::where('policyNumber', $clientNumber)->first();
//
//                    if ($trans != null) {
//                        $trans = new Transaction();
//                    }
//
//                    $trans->amount = $amount;
//
//                    if ($status == 'S') {
//                        $trans->status = 'SUCCESS';
//                        $trans->save();
//                    } elseif ($status == 'W') {
//                        $trans->status = 'PROCESSING';
//                        $trans->save();
//                    } elseif ($status == 'F') {
//                        $trans->status = 0;
//                        $trans->save();
//                    } else {
//                        $trans->status = $status;
//                        $trans->save();
//                    }
//
//                    $paid = 0;
//
//                    if ($status == 'S' || $status == 'F') {
//                        if ($status == 'S') {
//                            $status = 'SUCCESS';
//                            $paid = 1;
//                            $templateId = 10;
//                        }
//
//                        if ($status == 'F') {
//                            $status = 'FAILED';
//                            $paid = 0;
//                            $templateId = 11;
//                        }
//
//
//                        //update payment transactions table
//                        $paymentData['policyNumber'] = $clientNumber;
//                        $paymentData['referenceNumber'] = $instalmentReferenceNumber;
//                        $paymentData['amount'] = $amount;
//                        $paymentData['status'] = $status;
//                        $paymentData['paymentDate'] = \Carbon\Carbon::parse($actionDate)->format('Y-m-d');
//                        $paymentData['paymentMethod'] = 'RealPay';
//                        $paymentData['numberOfInstalmentsPaid'] = $paid;
//                        $paymentData['note'] = 'TRANSACTION ' . $status;
//
//                        $policyController = new PolicyController();
//                        $saveData = $policyController->updatePaymentTransactions($paymentData);
//
//                        if($check && $check->customer_id && $saveData == true){
//                            if($check->customer_id) {
//                                $customer = Customer::where('id', $check->customer_id)->first(array('cellphone'));
//                                if($customer && $customer->cellphone){
//                                    $messaging = new SmsMessaging();
//                                    $response = $messaging->sendPaymentStatusSMS($templateId, $customer->cellphone, $amount, $clientNumber);
//                                }
//                            }
//                        }
//                    }
//
//                    if ($status == 'S' || $status == 'SUCCESS') {
//                        $s = 'SUCCESS'; //.' '.'('.$status.'-'.$data['InstalmentGetResponse'][0]['ResponseCode'].')';
//                    } elseif ($status == 'W') {
//                        $s = 'PROCESSING'; //.' '.'('.$status.'-'.$data['InstalmentGetResponse'][0]['ResponseCode'].')';
//                    } elseif ($status == 'A') {
//                        $s = 'ACTIVE'; //.' '.'('.$status.'-'.$data['InstalmentGetResponse'][0]['ResponseCode'].')';
//                    } elseif ($status == 'D') {
//                        $s = 'DISPUTED'; //. '('.$status.'-'.$data['InstalmentGetResponse'][0]['ResponseCode'].')';
//                    } elseif ($status == 'E') {
//                        $s = 'ERROR'; //. '('.$status.'-'.$data['InstalmentGetResponse'][0]['ResponseCode'].')';
//                    } elseif ($status == 'R') {
//                        $s = 'RETRY'; //. '('.$status.'-'.$data['InstalmentGetResponse'][0]['ResponseCode'].')';
//                    } elseif ($status == 'I') {
//                        $s = 'CANCELLED'; //. '('.$status.'-'.$data['InstalmentGetResponse'][0]['ResponseCode'].')';
//                    } elseif ($status == 'F' || $status == 'FAILED') {
//                        $s = 'FAILED' . '(' . $status . '-' . $data['InstalmentGetResponse'][0]['ResponseCode'] . ')';
//                    } else {
//                        $s = 'Status Not Found' . '(' . $status . ')';
//                    }
//
//                    $webHookLog = new RealpayWebHookResponses();
//                    $webHookLog->policyNumber = $clientNumber;
//                    $webHookLog->instalmentSequence = $sequence;
//                    $webHookLog->instalmentActionDate = \Carbon\Carbon::parse($actionDate)->format('Y-m-d');
//                    $webHookLog->instalmentRefNumber = $instalmentReferenceNumber;
//
//                    if (array_key_exists('ResponseCode', $data['InstalmentGetResponse'][0]))
//                        $webHookLog->bankResponse = $this->getBankResponseCodes($data['InstalmentGetResponse'][0]['ResponseCode']);
//
//                    $webHookLog->status = $s;
//                    $webHookLog->save();
//
//                    return response()->json(['Status' => 'Success','Description'=>'Instalment Updated Successfully'], 200);
//                }else{
//                    return response()->json(['Status' => 'Failed', 'description' => 'Empty value provided'], 401);
//                }
//            }else{
//                $webHookLog = new RealpayWebHookResponses();
//                $webHookLog->policyNumber = $clientNumber;
//                $webHookLog->instalmentSequence = $sequence;
//                $webHookLog->instalmentActionDate = \Carbon\Carbon::parse($actionDate)->format('Y-m-d');
//                $webHookLog->instalmentRefNumber = $instalmentReferenceNumber;
//
//                if(array_key_exists('ResponseCode', $data['InstalmentGetResponse'][0]))
//                    $webHookLog->bankResponse = $this->getBankResponseCodes($data['InstalmentGetResponse'][0]['ResponseCode']);
//
//                $webHookLog->status = $status;
//                $webHookLog->save();
//            }
//
//
//
//        } catch (\Exception $e) {
//            return response()->json(['Status' => 'Failed', 'description' => $e->getMessage().' '.$e->getLine()], 401);
//        }
//    }

    public function updateInstallment(Request $request){
        try {
            $data = $request->all();
            $clientNumber = $data['InstalmentGetResponse'][0]['ClientNumber'];
            $contractNumber = $data['InstalmentGetResponse'][0]['ContractNumber'];
            $instalmentReferenceNumber = $data['InstalmentGetResponse'][0]['InstalmentReferenceNumber'];
            $status = $data['InstalmentGetResponse'][0]['InstalmentStatus'];
            $actionDate = $data['InstalmentGetResponse'][0]['InstalmentActionDate'];
            $trackingCode = $data['InstalmentGetResponse'][0]['TrackingCode'];
            $amount = $data['InstalmentGetResponse'][0]['InstalmentAmount'];
            $sequence = $data['InstalmentGetResponse'][0]['InstalmentSequence'];
            $instalmentResponse = null;

            $check = Policy::where('id',$contractNumber)
                ->where('policyNumber',$clientNumber)
                ->orWhere('policyNumber',$contractNumber)
                ->first(array('id','policyNumber','customer_id'));
            $pc = new PolicyController();
            if($check != null){
                if(array_key_exists('ResponseCode', $data['InstalmentGetResponse'][0])){
                    if (isset($data['InstalmentGetResponse'][0]['ResponseCode']) && $data['InstalmentGetResponse'][0]['ResponseCode'] != '00')
                        $instalmentResponse = $this->getBankResponseCodes($data['InstalmentGetResponse'][0]['ResponseCode']);
                    else
                        $instalmentResponse = 'Success';
                }

                if($clientNumber != null
                    && $contractNumber != null
                    && $instalmentReferenceNumber  != null
                    && $status  != null
                    && $actionDate  != null
                    && $trackingCode  != null
                    && $amount  != null
                    && $sequence != null) {
                    $update = RealpayContractInstallments::where('clientNumber', $clientNumber)
                        ->where('contractNumber', $contractNumber)
                        ->where('InstalmentReferenceNumber', $instalmentReferenceNumber)
                        ->where('InstalmentSequence', $sequence)
                        ->first();

                    if ($update != null) {
                        $update->InstalmentActionDate = $actionDate;
                        $update->TrackingCode = $trackingCode;
                        $update->InstalmentAmount = $amount;
                        $update->InstalmentStatus = $status;
                        $update->instalmentResponse = $instalmentResponse;
                        $update->save();

                        $clientContracts = RealpayClientContracts::where('contract_number',$contractNumber)
                            ->orderBy('id','desc')
                            ->first();

                        if($clientContracts == null){
                            $policy = Policy::where('id', $contractNumber)->orWhere('policyNumber',$contractNumber)->first();
                        }else{
                            $policy = Policy::where('id',$clientContracts->policy_id)->first();
                        }

                        $curStatus = $policy->status;

                        if ($curStatus != 1 && $status == 'S') {
                            $pc = new PolicyController();
                            $update = $pc->updatePolicyDates($policy->policyNumber,1);
                        }

                        if ($curStatus = 2 && $status == 'S') {
                            $policy->status = $curStatus;
                            $policy->save();
                        }

                        else if ($curStatus = 1 && $status == 'S') { //Keep the status unchanged even if payment is failed: given by KAMLESH to KARTHIK
                            $policy->status = 1;
                            $policy->save();

                            $update = $pc->updatePolicyDates($policy->polciNumber,1);
                        } elseif ($status == 'F') {
                            $policy->status = $curStatus;
                            $policy->save();
                        } else {
                            $policy->status = $curStatus;
                            $policy->save();
                        }
                    } else {
                        $policy = Policy::where('id', $contractNumber)->orWhere('policyNumber',$contractNumber)->first();
                        $curStatus = $policy->status;
                        if ($policy != null) { //Keep the status unchanged even if payment is failed: given by KAMLESH to KARTHIK
                            if ($curStatus = 2 && $status == 'S') {
                                $policy->status = $curStatus;
                                $policy->save();
                            }

                            else if ($curStatus = 1 && $status == 'S') {
                                $policy->status = 1;
                                $policy->save();

                                $update = $pc->updatePolicyDates($policy->polciNumber,1);
                            } elseif ($status == 'F') {
                                $policy->status = $curStatus;
                                $policy->save();
                            } else {
                                $policy->status = $curStatus;
                                $policy->save();
                            }
                        }
                    }

                    $trans = Transaction::where('policyNumber', $clientNumber)->first();

                    if ($trans == null) {
                        $trans = new Transaction();
                    }

                    $trans->amount = $amount;

                    if ($status == 'S') {
                        $trans->status = 'SUCCESS';
                        $trans->save();
                    } elseif ($status == 'W') {
                        $trans->status = 'PROCESSING';
                        $trans->save();
                    } elseif ($status == 'F') {
                        $trans->status = 0;
                        $trans->save();
                    } else {
                        $trans->status = $status;
                        $trans->save();
                    }

                    $paid = 0;

                    if ($status == 'S' || $status == 'F') {
                        if ($status == 'S') {
                            $status = 'SUCCESS';
                            $paid = 1;
                            $templateId = 10;
                        }

                        if ($status == 'F') {
                            $status = 'FAILED';
                            $paid = 0;
                            $templateId = 11;
                        }


                        //update payment transactions table
                        $paymentData['policyNumber'] = $clientNumber;
                        // GRA-0203 (2026-08-28): pass the resolved policy id. updatePaymentTransactions
                        // reads $data['policy_id']; omitting it threw "Undefined array key" mid-apply and
                        // 401-ed a SUCCESSFUL collection with no payment_transaction written. $check is
                        // the policy resolved at the top of this method and is non-null in this branch.
                        $paymentData['policy_id'] = $check->id ?? null;
                        $paymentData['referenceNumber'] = $instalmentReferenceNumber;
                        $paymentData['amount'] = $amount;
                        $paymentData['status'] = $status;
                        $paymentData['paymentDate'] = \Carbon\Carbon::parse($actionDate)->format('Y-m-d');
                        $paymentData['paymentMethod'] = 'RealPay';
                        $paymentData['numberOfInstalmentsPaid'] = $paid;
                        $paymentData['note'] = 'TRANSACTION ' . $status;

                        $policyController = new PolicyController();
                        $saveData = $policyController->updatePaymentTransactions($paymentData);

                        if($check && $check->customer_id && $saveData == true){
                            if($check->customer_id) {
                                $customer = Customer::where('id', $check->customer_id)->first(array('cellphone','firstName','lastName'));
                                if($customer && $customer->cellphone){
                                    $messaging = new SmsMessaging();
                                    if($status=='FAILED') {;
                                        $c = new PolicyController();
                                        $oneTime = $c->generateSendPaymentURL($clientNumber, 'Realpay', 'repay',$amount);

                                        if ($oneTime != null) {
                                            $payment = $messaging->sendOneTimePaymentLink(34, $customer->cellphone, $oneTime, $clientNumber, $customer->firstName . ' ' . $customer->lastName);
                                        }
                                    }

                                    $response = $messaging->sendPaymentStatusSMS($templateId, $customer->cellphone, $amount, $clientNumber);
                                }

                                $oneTime = OneTimePaymentURL::where('policyNumber',$clientNumber)->orderBy('id','desc')->first();

                                if($customer && $customer->email && $oneTime){
                                    $data = new \stdClass();
                                    $data->user_id = $oneTime->id;
                                    $data->hook = 'one_time_payment';
                                    $data->customer_id = $customer->id;
                                    $data->attachment = null;
                                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                                    $markdown = new MailTemplate($data);
                                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                                    event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,null,['hook' => $data->hook]));
                                    // $sent = \Illuminate\Support\Facades\Mail::to($customer->email)->send(new MailTemplate($data));
                                }
                            }
                        }
                    }

                    if ($status == 'S' || $status == 'SUCCESS') {
                        $s = 'SUCCESS'; //.' '.'('.$status.'-'.$data['InstalmentGetResponse'][0]['ResponseCode'].')';
                    } elseif ($status == 'W') {
                        $s = 'PROCESSING'; //.' '.'('.$status.'-'.$data['InstalmentGetResponse'][0]['ResponseCode'].')';
                    } elseif ($status == 'A') {
                        $s = 'ACTIVE'; //.' '.'('.$status.'-'.$data['InstalmentGetResponse'][0]['ResponseCode'].')';
                    } elseif ($status == 'D') {
                        $s = 'DISPUTED'; //. '('.$status.'-'.$data['InstalmentGetResponse'][0]['ResponseCode'].')';
                    } elseif ($status == 'E') {
                        $s = 'ERROR'; //. '('.$status.'-'.$data['InstalmentGetResponse'][0]['ResponseCode'].')';
                    } elseif ($status == 'R') {
                        $s = 'RETRY'; //. '('.$status.'-'.$data['InstalmentGetResponse'][0]['ResponseCode'].')';
                    } elseif ($status == 'I') {
                        $s = 'CANCELLED'; //. '('.$status.'-'.$data['InstalmentGetResponse'][0]['ResponseCode'].')';
                    } elseif ($status == 'F' || $status == 'FAILED') {
                        $s = 'FAILED' . '(' . $status . '-' . $data['InstalmentGetResponse'][0]['ResponseCode'] . ')';
                    } else {
                        $s = 'Status Not Found' . '(' . $status . ')';
                    }

                    $webHookLog = new RealpayWebHookResponses();
                    $webHookLog->policyNumber = $clientNumber;
                    $webHookLog->instalmentSequence = $sequence;
                    $webHookLog->instalmentActionDate = \Carbon\Carbon::parse($actionDate)->format('Y-m-d');
                    $webHookLog->instalmentRefNumber = $instalmentReferenceNumber;
                    $webHookLog->installmentAmount = $amount;

                    if (array_key_exists('ResponseCode', $data['InstalmentGetResponse'][0]))
                        $webHookLog->bankResponse = $this->getBankResponseCodes($data['InstalmentGetResponse'][0]['ResponseCode']);

                    $webHookLog->status = $s;
                    $webHookLog->save();

                    return response()->json(['Status' => 'Success','Description'=>'Instalment Updated Successfully'], 200);
                }else{
                    return response()->json(['Status' => 'Failed', 'description' => 'Empty value provided'], 401);
                }
            }else{
                $webHookLog = new RealpayWebHookResponses();
                $webHookLog->policyNumber = $clientNumber;
                $webHookLog->instalmentSequence = $sequence;
                $webHookLog->instalmentActionDate = \Carbon\Carbon::parse($actionDate)->format('Y-m-d');
                $webHookLog->instalmentRefNumber = $instalmentReferenceNumber;
                $webHookLog->installmentAmount = $amount;

                if(array_key_exists('ResponseCode', $data['InstalmentGetResponse'][0]))
                    $webHookLog->bankResponse = $this->getBankResponseCodes($data['InstalmentGetResponse'][0]['ResponseCode']);

                $webHookLog->status = $status;
                $webHookLog->save();
            }



        } catch (\Exception $e) {
            return response()->json(['Status' => 'Failed', 'description' => $e->getMessage().' '.$e->getLine()], 401);
        }
    }

    public function fetchClient(Request $request){
        try{
            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/clients/".env('REALPAY_PRODUCT')."?ClientNumber=".$request->clientNumber."&BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
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
            $clientInfo = $data['ClientGetResponse'][0];


            $b = $this->getRealPayBanks();
            $banks = $b['BanksGetResponse'][0]['Products'][0]['Banks'];

            if($data['APIResponse']['Status'] != 'ERROR'){
                return view('admin.Realpay.clientInfo',compact('clientInfo','banks'));
            }else{
                return Redirect::back()->with('error', $data['APIResponse']['Error']);
            }
        }catch(\Http\Client\Exception $e){
            return Redirect::back()->with('error', $e->getMessage());
        }
    }

    public function getRealPayBanks(){
        try{
            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL').'/general/banks/FNBNDOBW?BeneficiaryUser=16244&Version=v1',
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
            return $data;
        }catch(\Mockery\Exception $e){
            return $e->getMessage();
        }
    }

    public function updateClient(Request $request){
        try{

            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/clients/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "PUT",
                CURLOPT_POSTFIELDS =>"{\r\n  \"ClientPutRequest\": [\r\n    {\r\n
                            \"ClientNumber\": \"$request->ClientNumber2\",\r\n
                           \"ClientName\": \"$request->ClientName\",\r\n
                           \"IDType\": \"$request->IDType\",\r\n
                           \"IDNumber\": \"$request->IDNumber\",\r\n
                           \"CellphoneNumber\": \"$request->CellphoneNumber\",\r\n
                           \"EMail\": \"$request->EMail\",\r\n
                           \"BankCode\": $request->BankCode,\r\n
                           \"BranchCode\": $request->BranchCode,\r\n
                           \"AccountType\": $request->AccountType,\r\n
                           \"AccountNumber\": $request->AccountNumber,\r\n
                           \"AccountHolderName\": \"$request->AccountHolderName\",\r\n
                           \"EmployeeGroupCode\": \"$request->employeeType\",\r\n
                           }\r\n
                           ]\r\n
                           }",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: ".$token
                ),
            ));

            $response = curl_exec($curl);
            $data = json_decode($response,true);

            if(!empty($data['ClientPutResponse'][0]['Successful']) && empty($data['ClientPutResponse'][0]['Failed'])){
                $policy = Policy::where('policyNumber',$request->ClientNumber)->first(array('id'));
                if($policy && $policy->id){
                    $banking = CustomerBanking::where('policy_id',$policy->id)->first();
                    $banking->accountNumber = $request->AccountNumber;
                    $banking->bankName = $request->BankCode;
                    $banking->branchCode = $request->BranchCode;
                    $banking->accountType = $request->AccountType;
                    $banking->save();

                }
                return Redirect::route('admin.getClient')->with('success', 'Client Updated Successfully');
            }else{
                return Redirect::route('admin.getClient')->with('error', 'Unable to update client on RealPay');
            }

        }catch(\Exception $e){
            return Redirect::route('admin.getClient')->with('error', $e->getMessage());
        }
    }

    public function updateClientRealpay($data){
        try{

            $policy = Policy::where('id',$data['policy_id'])->first(array('id','customer_id','policyNumber'));
            $customer = Customer::where('id',$policy->customer_id)->orderBy('id','desc')->first();
            $profile = CustomerProfile::where('customer_id',$policy->customer_id)->orderBy('id','desc')->first();
            $IDType = ($profile->omang != null) ? "I" : "P";
            $IDNumber = ($profile->omang != null) ? $profile->omang : $profile->passport;

            $BankCode = $data['bankName'];
            $BranchCode = $data['branchCode'];
            $AccountType = $data['bankAccountType'];
            $AccountNumber= $data['accountNumber'];;

            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                dd('Please clear cache');
            //return null;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/clients/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "PUT",
                CURLOPT_POSTFIELDS =>"{\r\n  \"ClientPutRequest\": [\r\n    {\r\n
                            \"ClientNumber\": \"$policy->policyNumber\",\r\n
                           \"ClientName\": \"$customer->firstName $customer->lastName\",\r\n
                           \"IDType\": \"$IDType\",\r\n
                           \"IDNumber\": \"$IDNumber\",\r\n
                           \"CellphoneNumber\": \"$customer->cellphone\",\r\n
                           \"EMail\": \"$customer->email\",\r\n
                           \"BankCode\": $BankCode,\r\n
                           \"BranchCode\": $BranchCode,\r\n
                           \"AccountType\": $AccountType,\r\n
                           \"AccountNumber\": $AccountNumber,\r\n
                           \"AccountHolderName\": \"$customer->firstName $customer->lastName\",\r\n
                           \"EmployeeGroupCode\": \"OT\",\r\n
                           }\r\n
                           ]\r\n
                           }",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: ".$token
                ),
            ));

            $response = curl_exec($curl);
            $res = json_decode($response,true);

            if(!empty($res['ClientPutResponse'][0]['Successful']) && empty($res['ClientPutResponse'][0]['Failed'])){
//                if($policy && $policy->id){
//                    $banking = CustomerBanking::where('policy_id',$policy->id)->first();
//                    $banking->accountNumber = $request->AccountNumber;
//                    $banking->bankName = $request->BankCode;
//                    $banking->branchCode = $request->BranchCode;
//                    $banking->accountType = $request->AccountType;
//                    $banking->save();
//
//                }
                return true;
            }else{
                return null;
            }

        }catch(\Exception $e){
            dd($e->getMessage().' '.$e->getLine());
            return false;
        }
    }

    public function actionAfterCancellingContract($contractId){
        try{
            $instalments = RealpayContractInstallments::where('contractNumber',$contractId)
                ->where('InstalmentStatus','A')
                ->get();
            if($instalments != null){
                foreach ($instalments as $ins){
                    $update = RealpayContractInstallments::where('InstalmentReferenceNumber',$ins->InstalmentReferenceNumber)
                        ->where('InstalmentSequence',$ins->InstalmentSequence)
                        ->first();
                    $update->InstalmentStatus = 'I';
                    $update->save();
                }
                return true;
            }
        }catch(\Exception $ex){

        }
    }

    public function viewInstallments($contractId){
        try{
            return view('admin.accounts.installments',compact('contractId'));
        }catch(\Exception $ex){

        }
    }

    public function viewError($contractId){
        try{
            $data = RealpayPaymentRequest::where('policy_id',$contractId)->first();
            if($data && $data->response){
                $error = unserialize($data->response);
                return view('admin.accounts.error',compact('data','error'));
            }else{
                return Redirect::back()->with('error', 'Unable to open error page.');
            }
        }catch(\Exception $ex){
            return Redirect::back()->with('error', $ex->getMessage());
        }
    }

    public function getContractInstallments(Request $request){
        try{
            $policy = Policy::where('id',$request->policy_id)->first(array('id','policyNumber'));
            $clientContracts = RealpayClientContracts::where('policy_id',$request->policy_id)->orderBy('id','desc')->first();

            if($clientContracts == null){
                $data = RealpayContractInstallments::where('contractNumber',$request->policy_id)->get();
            }else{
                $data = RealpayContractInstallments::where('contractNumber',$clientContracts->contract_number)->get();
            }

            return DataTables::of($data)
                ->editColumn('action', function ($data) {

                    switch ($data->InstalmentStatus) {
                        case 'S':
                            $actions = '<span class="kt-font-bold kt-font-success">Success</span>';
                            break;
                        case 'W':
                            $actions = '<span class="kt-font-bold kt-font-info">Processing</span>';
                            break;

                        case 'F':
                            $d = $data->toArray();
                            $actions = '<a href="' . route('admin.updateStatus',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'R']) . '" class="btn btn-sm btn-elevate btn-warning btn-elevate" title="Cancel Instalment">
                                <span class="kt-opacity-11" id="">Retry</span>
                            </a>';
                            break;
                        case 'R':
                            $d = $data->toArray();
                            $actions = '<a href="' . route('admin.updateStatus',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'I']) . '" class="btn btn-sm btn-elevate btn-danger btn-elevate" title="Cancel Instalment">
                                <span class="kt-opacity-11" id="">Cancel</span>
                            </a>';
                            break;
                        case 'A':
                            $d = $data->toArray();
                            $actions = '<a href="' . route('admin.updateStatus',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'I']) . '" value="'.$d['InstalmentReferenceNumber'].'" class="btn btn-sm btn-elevate btn-danger btn-elevate confirm-cancel" title="Cancel Instalment">
                                <span class="kt-opacity-11" id="">Cancel</span>
                            </a>';
                            break;
                        case 'I':
                            $actions = '<span class="kt-font-bold kt-font-info">Cancelled</span>';
                            break;
                        case 'E':
                            $d = $data->toArray();
                            $actions = '<a href="' . route('admin.updateStatus',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'R']) . '" class="btn btn-sm btn-elevate btn-warning btn-elevate" title="Cancel Instalment">
                                <span class="kt-opacity-11" id="">Retry</span>
                            </a>';
                            break;
                        default :
                            $actions = '<span class="kt-font-bold kt-font-danger">Status not found</span>';
                            break;
                    }
                    return $actions;
                })
                ->editColumn('InstalmentStatus', function ($data) {
                    switch ($data->InstalmentStatus){
                        case 'S':
                            $status =  '<span class="kt-font-bold kt-font-success">Success</span>';
                            break;
                        case 'W':
                            $status =  '<span class="kt-font-bold kt-font-info">Processing</span>';
                            break;

                        case 'F':
                            $status =  '<span class="kt-font-bold kt-font-danger">Failed</span>';
                            break;
                        case 'R':
                            $status =  '<span class="kt-font-bold kt-font-info">Retry</span>';
                            break;
                        case 'A':
                            $status =  '<span class="kt-font-bold kt-font-info">Active</span>';
                            break;
                        case 'I':
                            $status =  '<span class="kt-font-bold kt-font-info">Cancelled</span>';
                            break;
                        case 'E':
                            $status =  '<span class="kt-font-bold kt-font-warning">Error</span>';
                            break;
                        default :
                            $status =  '<span class="kt-font-bold kt-font-danger">Status not found</span>';
                            break;
                    }

                    return $status;
                })

                ->rawColumns(['InstalmentStatus','action'])
                ->make(true);
        }catch(\Exception $ex){
            dd($ex->getMessage().' '.$ex->getLine());
        }
    }

    public function updateInstalmentStatusRealpay($seq,$s,$reason = null){
        try{
            $ref = (int)$seq;
            $fetchToken = $this->clientAuth();

            if($fetchToken['token_type'] != ''  && $fetchToken['access_token'] != ''){
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            }else{
                return Redirect::back()->with('error', 'Auth key not found');
            }
            $ins = RealpayContractInstallments::where('InstalmentReferenceNumber',$ref)->first();
            if($ins != null){
                $ins->InstalmentStatus = $s;
                $ins->note = $reason;
                $ins->save();

                $con = RealpayContractDetails::where('ClientNumber',$ins->clientNumber)
                    ->where('ContractNumber',$ins->contractNumber)
                    ->first(array('ContractSequence'));

                if($con == null)
                    return Redirect::back()->with('error', 'Contract data not found');

            }else{
                return Redirect::back()->with('error', 'Instalment data not found');
            }

            $curl = curl_init();

            $contractSeq = $con->ContractSequence;
            $clientNum = $ins->clientNumber;
            $contractNumber = $ins->contractNumber;
            $insSeq = $ins->InstalmentSequence;
            $tracking = $ins->TrackingCode;
            $amnt = $ins->InstalmentAmount;
            $insStatus = $s;

            if($s == 'R')
                $t = Carbon::now()->timestamp;
            else
                $t = Carbon::parse($ins->InstalmentActionDate)->timestamp;

            $date = date('Y-m-d',$t);


            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/instalments/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "PUT",
                CURLOPT_POSTFIELDS =>"{\r\n  \"InstalmentPutRequest\": [\r\n    {\r\n
                            \"ClientNumber\": \"$clientNum\",\r\n
                           \"ContractSequence\": $contractSeq,\r\n
                           \"ContractNumber\": \"$contractNumber\",\r\n
                           \"InstalmentSequence\": $insSeq,\r\n
                           \"InstalmentActionDate\": \"$date\",\r\n
                           \"TrackingCode\": \"$tracking\",\r\n
                           \"InstalmentAmount\": $amnt,\r\n
                           \"InstalmentStatus\": \"$insStatus\",\r\n
                           \"DebitSequenceType\": \"OOFF\",\r\n
                           }\r\n
                           ]\r\n
                           }",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: ".$token
                ),
            ));

            $response = curl_exec($curl);
            $data = json_decode($response,true);

            if(!empty($data['InstalmentPutResponse'][0]['Successful']) && empty($data['InstalmentPutResponse'][0]['Failed'])){
                if($s == 'R') {
                    $ins->retry_count = $ins->retry_count + 1;
                    $ins->save();
                }

                return Redirect::back()->with('success', 'Instalment updated successfully');
            }else{
                return Redirect::back()->with('error', 'Something went wrong');
            }

        }catch(\Exception $e){
            return Redirect::back()->with('error', $e->getMessage());
        }
    }

    public function realPayReportForWebHook(){
        $failed = RealpayWebHookResponses::orderBy('id','DESC')->get();

        foreach($failed as $f){

            $datetime1 = new DateTime($f->InstalmentActionDate);
            $datetime2 = new DateTime($f->webhook_resp_date);

            $f['webhook_response_in_days'] = $datetime1->diff($datetime2)->d;

        }
        return DataTables::of($failed)
            ->make(true);
    }

    public function webHookData(){
        return view('admin.accounts.realpay_webhook_report');
    }

    public function failedPayments(Request $request){
        try{
            if($request->customerId != null){
                $customerData = Policy::join('customer', 'customer.id', 'policies.customer_id')
                    ->where('policies.customer_id',$request->customerId)
                    ->where('policies.status', '!=', 2)
                    ->get(array('policies.id'));

                $paymentData = array();
                $policyData = array();

                if(count($customerData) > 0){
                    foreach($customerData as $cd){

                        $banking = CustomerBanking::where('policy_id',$cd->id)->first(array('id','billing','policy_id'));

                        if($banking && $banking->billing == null){
                            $payment = PaymentTransaction::where('policyNumber', $customerData->policyNumber)->where('paymentMethod', 'DPO')->orderBy('id', 'desc')->exists();

                            if($payment)
                            {
                                $banking->billing = 'DPO';
                            }

                        }

                        if($banking && $banking->billing == null){
                            $banking->billing = 'VCS';
                            $banking->save();
                        }

                        $policy = Policy::where('id',$cd->id)
                            ->first(array('id','customer_id','product_id','policyNumber','first_premium_wvat','premium_freq','premium','billingStartDate'));
                        switch($banking->billing){
                            case 'RealPay':
                                $realpay = RealpayPaymentRequest::where('policy_id',$banking->policy_id)
                                    ->orderBy('id','DESC')
                                    ->first();
                                if($realpay){
                                    if($realpay->status == 2){
                                        $policy['payment_data'] = $banking;
                                        $policy['trans_ref']    = null;
                                        array_push($policyData, $policy);
                                    }
                                }else{
                                    $policy['payment_data'] = $banking;
                                    $policy['trans_ref']    = null;
                                    array_push($policyData, $policy);
                                }
                                break;
                            case 'VCS':
                                $vcs = VcsTransaction::where('policyNumber',$policy->policyNumber)->first();

                                if($vcs == null || $vcs->status != 'SUCCESS'){
                                    $trans = Transaction::where('policyNumber',$policy->policyNumber)
                                        ->orderBy('id','DESC')
                                        ->first();

                                    if($trans && $trans->status == 0){
                                        $policy['payment_data'] = $banking;

                                        if($trans && $trans->referenceNumber)
                                            $policy['trans_ref'] = $trans->referenceNumber;
                                        else
                                            $policy['trans_ref'] = null;

                                        array_push($policyData, $policy);
                                    }
                                }

                                break;
                            case 'DPO':

                                $trans = PaymentTransaction::where('policyNumber', $policy->policyNumber)->where('paymentMethod', 'DPO')->first();

                                if($trans && $trans->status == 'FAILED'){
                                    $policy['payment_data'] = $banking;

                                    if($trans && $trans->referenceNumber)
                                        $policy['trans_ref'] = $trans->referenceNumber;
                                    else
                                        $policy['trans_ref'] = null;

                                    array_push($policyData, $policy);
                                }


                            default :
//                              $vcs = VcsNewTransaction::where('policyNumber',$policy->policyNumber)
//                                  ->orderBy('created_at','DESC')
//                                  ->first();
                                $vcs = VcsTransaction::where('policyNumber',$policy->policyNumber)->first();
                                if($vcs == null || ($vcs && $vcs->status != 'Success')){
                                    $policy['payment_data'] = $banking;
                                    array_push($policyData, $policy);
                                }
//                              else($vcs && $vcs->status != 'Success'){
//                                  $policy['payment_data'] = $banking;
//                                  array_push($policyData, $policy);
//                              }

                                break;


                        }
                    }
                    return response()->json(['Status' => 'Success','PolicyData'=>$policyData], 200);
                }else{
                    return response()->json(['Status' => 'Success','Message'=>'No policy found for this customer'], 401);
                }
            }else{
                return response()->json(['Status' => 'Failed','Message'=>'Customer ID not found'], 401);
            }
        }catch(\Exception $ex){
            return response()->json(['Status' => 'Failed','Message'=>$ex->getMessage()], 401);
        }
    }

    public function updateBankingInfo(Request $request){
        try{
            if($request->policy_id != null){
                $banking = CustomerBanking::where('policy_id',$request->policy_id)->first();
                $policy = Policy::where('id',$request->policy_id)->first();
                $policy->billingStartDate = \Carbon\Carbon::now()->format('Y-m-d');
                $policy->billing_day = \Carbon\Carbon::now()->format('d');
                $policy->billing_day = \Carbon\Carbon::now()->format('d');
                $policy->first_premium_wvat = 0;
                $policy->save();

                if($banking){
                    $banking->bankName = $request->bankName;
                    $banking->branchCode = $request->branchCode;
                    $banking->accountType = $request->bankAccountType;
                    $banking->accountNumber = $request->accountNumber;
                    $banking->billingStartDate = \Carbon\Carbon::now()->format('Y-m-d');
                    $banking->billing_day = \Carbon\Carbon::now()->format('d');
                    $banking->billing = "RealPay";
                    $banking->save();
                }

                if($banking->save()){

                    $checkLog = RealpayLogs::where('policy_id',$request->policy_id)->first();
                    if($checkLog !=  null){
                        $checkLog->status = 0;
                        $checkLog->save();
                    }else{
                        $addLog = $this->logEvent($request->policy_id,1);
                        $responseArr = array('ClientCreated'=>0,'ContractCreated'=>0);
                        $stringArr = \Opis\Closure\serialize($responseArr);


                        $checkRequests = RealpayPaymentRequest::where('policy_id',$request->policy_id)->first();
                        if($checkRequests != null){
                            $checkRequests->status = 0;
                            $checkRequests->save();
                        }else{
                            $payRequest = new RealpayPaymentRequest();
                            $payRequest->policy_id = $policy->id;
                            $payRequest->first_premium = $policy->first_premium;
                            $payRequest->premium = $policy->premium;
                            $payRequest->billing_day = $policy->billing_day;
                            $payRequest->billing_date = $policy->billingStartDate;
                            $payRequest->first_premium_contract = null;
                            $payRequest->contract = null;
                            $payRequest->status = 0;
                            $payRequest->response = $stringArr;
                            $payRequest->frequency = $policy->premium_freq;
                            $payRequest->save();
                        }

                    }
                    return response()->json(['Status' => 'True','Message'=>"Success"], 200);
                }else{
                    return response()->json(['Status' => 'Failed','Message'=>"Policy ID can not be null"], 401);
                }
            }else{
                return response()->json(['Status' => 'Failed','Message'=>"Policy ID can not be null"], 401);
            }
        }catch(\Exception $e){
            return response()->json(['Status' => 'Failed','Message'=>$e->getMessage()], 401);
        }
    }
    public function updateRealPayBillingDate(Request $request){
        try{
            if($request->policyNumber != null && $request->updateBillingDay != null){
                if($request->updateBillingDay >= 1 && $request->updateBillingDay <= 31){
                    $policy = Policy::where('policyNumber',$request->policyNumber)->first(array('id','policyNumber'));
                    if($policy != null && $policy->id != null && $policy->policyNumber != null){
                        $banking = CustomerBanking::where('policy_id',$policy->id)->first();
                        if($banking != null && $banking->billing != null){
                            if($banking->billing != null && $banking->billing == "RealPay"){
                                $realpay = RealpayLogs::where('policy_id',$policy->id)
                                    ->where('event',1)
                                    ->where('status',1)
                                    ->first();
                                if($realpay != null){
                                    if($realpay->status != null && $realpay->status == 1){
                                        $contractData = $this->getInstallments($policy);
                                        $instalments = $contractData['InstalmentGetResponse'];
                                        if($instalments != null){
                                            foreach($instalments as $key=>$ins){

                                                $timestamp = strtotime($ins['InstalmentActionDate']);

                                                $day = date('d', $timestamp);
                                                $month = date('m', $timestamp);
                                                $year = date('Y', $timestamp);
                                                $date = $year.'-'.$month.'-'.$day;

                                                if($ins['InstalmentSequence'] == 1 && $ins['InstalmentStatus'] == 'A'){
                                                    $check = $this->checkProRataPremiumInvolved($policy);

                                                    if($check == true){
                                                        $update = $this->updateInstallmentDateRP($ins,$ins['InstalmentActionDate']);
                                                    }else{
                                                        if($request->updateBillingDay > $day){
                                                            $updatedDate = $year.'-'.$month.'-'.$request->updateBillingDay;
                                                            $update = $this->updateInstallmentDateRP($ins,$updatedDate);
                                                        }elseif($request->updateBillingDay < $day){
                                                            $dif =  $request->updateBillingDay - $day;
                                                            $date = \Carbon\Carbon::createFromFormat('Y-m-d', $date);
                                                            if($dif > 0){
                                                                $daysToAdd = $dif;
                                                                $updatedDate = $date->addDays($daysToAdd);
                                                                $update = $this->updateInstallmentDateRP($ins,$updatedDate);
                                                            }elseif($dif < 0){
                                                                $nDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                                                                $daysToAdd = $nDays + $dif;
                                                                $updatedDate = $date->addDays($daysToAdd);
                                                                $update = $this->updateInstallmentDateRP($ins,$updatedDate);
                                                            }else{
                                                                return response()->json(['Status' => 'Failed','Message'=>"Please choose a diffrent day than existing one"], 401);
                                                            }
                                                        }elseif($request->updateBillingDay == $day){
                                                            return response()->json(['Status' => 'Failed','Message'=>"Please choose a diffrent day than existing one"], 401);
                                                        }else{
                                                            return response()->json(['Status' => 'Failed','Message'=>"Invalid day selected"], 401);
                                                        }
                                                    }


                                                }else{

                                                    if($request->updateBillingDay > $day){
                                                        $updatedDate = $year.'-'.$month.'-'.$request->updateBillingDay;
                                                        $update = $this->updateInstallmentDateRP($ins,$updatedDate);
                                                    }elseif($request->updateBillingDay < $day){
                                                        $dif =  $request->updateBillingDay - $day;
                                                        $date = \Carbon\Carbon::createFromFormat('Y-m-d', $date);
                                                        if($dif > 0){
                                                            $daysToAdd = $dif;
                                                            $updatedDate = $date->addDays($daysToAdd);
                                                            $update = $this->updateInstallmentDateRP($ins,$updatedDate);
                                                        }elseif($dif < 0){
                                                            $nDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                                                            $daysToAdd = $nDays + $dif;
                                                            $updatedDate = $date->addDays($daysToAdd);
                                                            $update = $this->updateInstallmentDateRP($ins,$updatedDate);
                                                        }else{
                                                            return response()->json(['Status' => 'Failed','Message'=>"Please choose a diffrent day than existing one"], 401);
                                                        }
                                                    }elseif($request->updateBillingDay == $day){
                                                        return response()->json(['Status' => 'Failed','Message'=>"Please choose a diffrent day than existing one"], 401);
                                                    }else{
                                                        return response()->json(['Status' => 'Failed','Message'=>"Invalid day selected"], 401);
                                                    }

                                                }
                                            }
                                            return response()->json(['Status' => 'Success','Message'=>"Billing date has been updated"], 200);
                                        }else{
                                            return response()->json(['Status' => 'Failed','Message'=>"No active instalments found for policy number ".$policy->policyNumber." on RealPay"], 401);
                                        }
                                    }else{
                                        return response()->json(['Status' => 'Failed','Message'=>"Payment was not found on RealPay"], 401);
                                    }
                                }else{
                                    return response()->json(['Status' => 'Failed','Message'=>"No data found on RealPay"], 401);
                                }
                            }else{
                                return response()->json(['Status' => 'Failed','Message'=>"Policy does not have contract with RealPay"], 401);
                            }
                        }else{
                            return response()->json(['Status' => 'Failed','Message'=>"Banking details not found for this policy"], 401);
                        }
                    }else{
                        return response()->json(['Status' => 'Failed','Message'=>"Policy data not found"], 401);
                    }
                }else{
                    return response()->json(['Status' => 'Failed','Message'=>"Please provide valid date parameter"], 401);
                }
            }else{
                return response()->json(['Status' => 'Failed','Message'=>"Please provide all the parameters"], 401);
            }
        }catch (\Exception $e){
            return response()->json(['Status' => 'Failed','Message'=>$e->getMessage()], 401);
        }

    }

    public function checkProRataPremiumInvolved($policy){
        try{
            $policyData = Policy::where('id',$policy->id)->first();
            if($policyData != null){
                if($policyData->first_premium_wvat != null){
                    if($policyData->premium_freq == 1 && ($policyData->first_premium_wvat != $policyData->premium) ){
                        return true;
                    }else{
                        return false;
                    }
                }else{
                    return false;
                }

            }else{
                return null;
            }
        }catch(\Exception $e){
            return null;
        }
    }

//    public function updateRealPayBillingDate(Request $request){
//        try{
//            if($request->policyNumber || $request->updateBillingDay){
//                $policy = Policy::where('policyNumber',$request->policyNumber)->first(array('id','policyNumber'));
//                if($policy && $policy->id != null){
//                    $banking = CustomerBanking::where('policy_id',$policy->id)->first(array('policy_id','billing'));
//                    if($banking && $banking->billing != null){
//                        if($banking->billing == "RealPay"){
//                            $contractData = $this->getInstallments($policy);
//                            $instalments = $contractData['InstalmentGetResponse'];
//                            if($instalments != null){
//                                $activeIns = 0;
//                                $updatedDate = '';
//                                foreach($instalments as $key=>$ins){
//                                    if($ins['InstalmentStatus'] == 'A'){
//                                        $activeIns = $activeIns + 1;
//                                        if($activeIns == 1){
//                                            $timestamp = strtotime($ins['InstalmentActionDate']);
//
//                                            $day = date('d', $timestamp);
//                                            $month = date('m', $timestamp);
//                                            $year = date('Y', $timestamp);
//                                            $date = $year.'-'.$month.'-'.$day;
//
//                                            if((1 <= $request->updateBillingDay) && ($request->updateBillingDay <= 31) && $request->updateBillingDay > $day){
//                                                $updatedDate = $year.'-'.$month.'-'.$request->updateBillingDay;
//                                            }elseif((1 <= $request->updateBillingDay) && ($request->updateBillingDay <= 31) && $request->updateBillingDay < $day){
//                                                $dif =  $request->updateBillingDay - $day;
//                                                $date = \Carbon\Carbon::createFromFormat('Y-m-d', $date);
//                                                if($dif > 0){
//                                                    $daysToAdd = $dif;
//                                                    $updatedDate = $date->addDays($daysToAdd);
//                                                }elseif($dif < 0){
//                                                    $nDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
//                                                    $daysToAdd = $nDays + $dif;
//                                                    $updatedDate = $date->addDays($daysToAdd);
//                                                }else{
//                                                    return response()->json(['Status' => 'Failed','Message'=>"Please choose a diffrent day than existing one"], 401);
//                                                }
//                                            }else{
//                                                return response()->json(['Status' => 'Failed','Message'=>"Invalid day selected"], 401);
//                                            }
//                                        }
//
//                                        //update installment data
//                                        $update = $this->updateInstallmentDateRP($ins,$updatedDate);
//
//                                        if($update == null)
//                                            return response()->json(['Status' => 'Failed','Message'=>"Something went wrong"], 401);
//
//
//
//                                    }
//                                }
//                                if($activeIns != 0)
//                                    return response()->json(['Status' => 'Success','Message'=>"Billing date has been updated for ".$activeIns." instalment of client ".$policy->policyNumber], 200);
//                                else
//                                    return response()->json(['Status' => 'Failed','Message'=>"No active installments found for client".$policy->policyNumber], 401);
//                            }else{
//                                return response()->json(['Status' => 'Failed','Message'=>"Installment data not found on Realpay for ".$policy->policyNumber], 401);
//                            }
//                        }else{
//                            return response()->json(['Status' => 'Failed','Message'=>"Billing method is ".$banking->billing], 401);
//                        }
//                    }else{
//                        return response()->json(['Status' => 'Failed','Message'=>"Policy banking data not found"], 401);
//                    }
//                }else{
//                    return response()->json(['Status' => 'Failed','Message'=>"Policy data not found"], 401);
//                }
//            }else{
//                return response()->json(['Status' => 'Failed','Message'=>"Request Data is empty"], 401);
//            }
//        }catch(\Exception $e){
//            return response()->json(['Status' => 'Failed','Message'=>$e->getMessage()], 401);
//        }
//    }

    public function getInstallments($policy){
        try{
            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/instalments/".env('REALPAY_PRODUCT')."?ClientNumber=".$policy->policyNumber."&ContractNumber=".$policy->id."&BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
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
            return $data;
        }catch(\Exception $e){
            return null;
        }
    }

    public function updateInstallmentDateRP($ins,$date){
        try{
            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();

            $contractSeq = $ins['ContractSequence'];
            $clientNum = $ins['ClientNumber'];
            $contractNumber = $ins['ContractNumber'];
            $insSeq = $ins['InstalmentSequence'];
            $tracking = $ins['TrackingCode'];
            $amnt = $ins['InstalmentAmount'];
            $insStatus = $ins['InstalmentStatus'];

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/instalments/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "PUT",
                CURLOPT_POSTFIELDS =>"{\r\n  \"InstalmentPutRequest\": [\r\n    {\r\n
                            \"ClientNumber\": \"$clientNum\",\r\n
                           \"ContractSequence\": \"$contractSeq\",\r\n
                           \"ContractNumber\": \"$contractNumber\",\r\n
                           \"InstalmentSequence\": \"$insSeq\",\r\n
                           \"InstalmentActionDate\": \"$date\",\r\n
                           \"TrackingCode\": \"$tracking\",\r\n
                           \"InstalmentAmount\": \"$amnt\",\r\n
                           \"InstalmentStatus\": \"$insStatus\",\r\n
                           \"DebitSequenceType\": \"OOFF\",\r\n
                           }\r\n
                           ]\r\n
                           }",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: ".$token
                ),
            ));

            $response = curl_exec($curl);
            $data = json_decode($response,true);

            $insdata = RealpayContractInstallments::where('clientNumber',$clientNum)
                ->where('contractNumber',$contractNumber)
                ->where('InstalmentSequence',$insSeq)
                ->first();
            if($insdata != null){
                $insdata->InstalmentActionDate = $date;
                $insdata->save();
            }

            return $data;

        }catch(\Exception $e){
            return null;
        }
    }
    public function updateInstallmentPremiumRP($ins){
        try{
            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();

            $contractSeq = $ins['ContractSequence'];
            $clientNum = $ins['ClientNumber'];
            $contractNumber = $ins['ContractNumber'];
            $insSeq = $ins['InstalmentSequence'];
            $tracking = $ins['TrackingCode'];
            $amnt = $ins['InstalmentAmount'];
            $insStatus = $ins['InstalmentStatus'];
            $date= $ins['InstalmentActionDate'];

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/instalments/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "PUT",
                CURLOPT_POSTFIELDS =>"{\r\n  \"InstalmentPutRequest\": [\r\n    {\r\n
                            \"ClientNumber\": \"$clientNum\",\r\n
                           \"ContractSequence\": \"$contractSeq\",\r\n
                           \"ContractNumber\": \"$contractNumber\",\r\n
                           \"InstalmentSequence\": \"$insSeq\",\r\n
                           \"InstalmentActionDate\": \"$date\",\r\n
                           \"TrackingCode\": \"$tracking\",\r\n
                           \"InstalmentAmount\": \"$amnt\",\r\n
                           \"InstalmentStatus\": \"$insStatus\",\r\n
                           \"DebitSequenceType\": \"OOFF\",\r\n
                           }\r\n
                           ]\r\n
                           }",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: ".$token
                ),
            ));

            $response = curl_exec($curl);
            $data = json_decode($response,true);

            $insdata = RealpayContractInstallments::where('clientNumber',$clientNum)
                ->where('contractNumber',$contractNumber)
                ->where('InstalmentSequence',$insSeq)
                ->first();
            if($insdata != null){
                $insdata->InstalmentAmount = $amnt;
                $insdata->save();
            }

            return $data;

        }catch(\Exception $e){
            return null;
        }
    }

    public function updateRealPayBankigDetails(Request $request){
        try{
            if($request->policyNumber != null){
                if($request->bankCode != null && $request->branchCode != null && $request->accountType != null && $request->accountNumber != null){
                    $policy = Policy::where('policyNumber',$request->policyNumber)->first();

                    if($policy && $policy->customer_id != null){
                        $customer = Customer::where('id', $policy->customer_id)->with('profile')->first();
                        $profile = CustomerProfile::where('customer_id',$customer->id)->first();
                        $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

                        if($customerBanking->billing != 'RealPay'){
                            return response()->json(['Status'=>'Failed','Message'=>'Record not found on Realpay'],401);
                        }

                        $fetchToken = $this->clientAuth();
                        if($fetchToken['token_type'] && $fetchToken['access_token'])
                            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
                        else
                            return response()->json(['Status'=>'Failed','Message'=>'Client auth failed at RealPay'],401);

                        if($profile->omang != null){
                            $id = $profile->omang;
                            $idType = 'I';
                        }else{
                            $id = $profile->passport;
                            $idType = 'P';
                        }
                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                            CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/clients/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => "",
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => "PUT",
                            CURLOPT_POSTFIELDS =>"{\r\n  \"ClientPutRequest\": [\r\n    {\r\n
                            \"ClientNumber\": \"$policy->policyNumber\",\r\n
                           \"ClientName\": \"$customer->firstName $customer->lastName\",\r\n
                           \"IDType\": \"$idType\",\r\n
                           \"IDNumber\": \"$id\",\r\n
                           \"CellphoneNumber\": \"$customer->cellphone\",\r\n
                           \"EMail\": \"$customer->email\",\r\n
                           \"BankCode\": \"$request->bankCode\",\r\n
                           \"BranchCode\": \"$request->branchCode\",\r\n
                           \"AccountType\": \"$request->accountType\",\r\n
                           \"AccountNumber\": \"$request->accountNumber\",\r\n
                           \"AccountHolderName\": \"$customer->firstName $customer->lastName\",\r\n
                           \"EmployeeGroupCode\": \"OT\",\r\n
                           }\r\n
                           ]\r\n
                           }",
                            CURLOPT_HTTPHEADER => array(
                                "Content-Type: application/json",
                                "Accept: application/json",
                                "Authorization: ".$token
                            ),
                        ));

                        $response = curl_exec($curl);
                        $data = json_decode($response,true);

                        curl_close($curl);

                        if(!empty($data['ClientPutResponse'][0]['Successful']) && empty($data['ClientPutResponse'][0]['Failed'])){

                            $customerBanking->bankName = $request->bankCode;
                            $customerBanking->branchCode = $request->branchCode;
                            $customerBanking->accountType = $request->accountType;
                            $customerBanking->accountNumber = $request->accountNumber;
                            $customerBanking->save();

                            return response()->json(['Status'=>'Success','Message'=>'Customer bank information update successfully'],200);
                        }else{
                            return response()->json(['Status'=>'Failed','Message'=>$data['ClientPutResponse'][0]['Failed'][0]['Failures'][0]['FailureDescription']],401);
                        }

                    }else{
                        return response()->json(['Status'=>'Failed','Message'=>'Customer not found with respect to policy number'],401);
                    }
                }else{
                    return response()->json(['Status'=>'Failed','Message'=>'Insufficient parameter data provided'],401);
                }
            }else{
                return response()->json(['Status'=>'Failed','Message'=>'Policy Number not found'],401);
            }
        }catch(\Exception $e){
            return response()->json(['Status'=>'Failed','Message'=>$e->getMessage()],401);
        }
    }

    public function splitRealPayBillingPremium(Request $request){
        try{
            if($request->policyNumber && $request->updateBillingDay){
                $policy = Policy::where('policyNumber',$request->policyNumber)->first(array('id','policyNumber'));
                if($policy && $policy->id){
                    $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();
                    if($customerBanking && $customerBanking->billing == "RealPay"){
                        $realpay = RealpayLogs::where('policy_id',$policy->id)
                            ->where('event',1)
                            ->where('status',1)
                            ->first();
                        if($realpay != null){
                            $contractData = $this->getInstallments($policy);
                            $instalments = $contractData['InstalmentGetResponse'];
                            if($instalments != null){
                                if($instalments != null){
                                    foreach($instalments as $key=>$ins){
                                        $update = $this->updateInstallmentPremiumRP($ins);
                                        $add = $this->addInstalmentRP($ins,$request->updateBillingDay);

                                        if($update == null || $add == null)
                                            return response()->json(['Status'=>'Failed','Message'=>"something went wrong"],401);
                                    }
                                    return response()->json(['Status'=>'Failed','Message'=>"Instalments updated successfully"],200);
                                }else{
                                    return response()->json(['Status'=>'Failed','Message'=>"No instalments found"],401);
                                }
                            }else{
                                return response()->json(['Status'=>'Failed','Message'=>"No instalments found"],401);
                            }
                        }else{
                            return response()->json(['Status'=>'Failed','Message'=>"Payment details not found"],401);
                        }
                    }else{
                        return response()->json(['Status'=>'Failed','Message'=>"Payment method is not RealPay for the policy number provided"],401);
                    }
                }else{
                    return response()->json(['Status'=>'Failed','Message'=>"Policy data not found"],401);
                }
            }else{
                return response()->json(['Status'=>'Failed','Message'=>"Insufficient data provided"],401);
            }
        }catch(\Exception $e){
            return response()->json(['Status'=>'Failed','Message'=>$e->getMessage()],401);
        }
    }

    public function addInstalmentRP($ins,$updateDay){
        try{

            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $timestamp = strtotime($ins['InstalmentActionDate']);

            $day = date('d', $timestamp);
            $month = date('m', $timestamp);
            $year = date('Y', $timestamp);
            $date = $year.'-'.$month.'-'.$day;

            $updatedDate = '';

            if((1 <= $updateDay) && ($updateDay <= 31) && $updateDay > $day){
                $updatedDate = $year.'-'.$month.'-'.$updateDay;
            }elseif((1 <= $updateDay) && ($updateDay <= 31) && $updateDay < $day){
                $dif =  $updateDay - $day;
                $date = \Carbon\Carbon::createFromFormat('Y-m-d', $date);
                if($dif > 0){
                    $daysToAdd = $dif;
                    $updatedDate = $date->addDays($daysToAdd);
                }elseif($dif < 0){
                    $nDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                    $daysToAdd = $nDays + $dif;
                    $updatedDate = $date->addDays($daysToAdd);
                }else{
                    return response()->json(['Status' => 'Failed','Message'=>"Please choose a diffrent day than existing one"], 401);
                }
            }else{
                return response()->json(['Status' => 'Failed','Message'=>"Invalid day selected"], 401);
            }

            $curl = curl_init();
            $date = $updatedDate;
            $contractSeq = $ins['ContractSequence'];
            $clientNum = $ins['ClientNumber'];
            $contractNumber = $ins['ContractNumber'];
            $insSeq = $ins['InstalmentSequence'];
            $tracking = $ins['TrackingCode'];
            $amnt = $ins['InstalmentAmount']/2;
            $insStatus = $ins['InstalmentStatus'];

            //CURLOPT_URL => "https://realpaycollect.com:4448/rpt/rpws/maintain/instalments/FNBNDOBW?BeneficiaryUser=16244&Version=v1",

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/instalments/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS =>"{\r\n  \"InstalmentPostRequest\": [\r\n    {\r\n
                            \"ClientNumber\": \"$clientNum\",\r\n
                           \"ContractSequence\": \"$contractSeq\",\r\n
                           \"ContractNumber\": \"$contractNumber\",\r\n
                           \"InstalmentSequence\": \"$insSeq\",\r\n
                           \"InstalmentActionDate\": \"$updatedDate\",\r\n
                           \"TrackingCode\": \"$tracking\",\r\n
                           \"InstalmentAmount\": \"$amnt\",\r\n
                           \"InstalmentStatus\": \"$insStatus\",\r\n
                           \"DebitSequenceType\": \"OOFF\",\r\n
                           }\r\n
                           ]\r\n
                           }",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: ".$token
                ),
            ));

            $response = curl_exec($curl);
            $data = json_decode($response,true);
            $success = sizeof($data['InstalmentPostResponse'][0]['Successful']) > 0;
            $d = $data['InstalmentPostResponse'][0]['Successful'];
            curl_close($curl);

            if($success == true){
                $inst = new RealpayContractInstallments();
                $inst->clientNumber = $d['ClientNumber'];
                $inst->contractNumber = $d['ContractNumber'];
                $inst->InstalmentReferenceNumber = $d['InstalmentReferenceNumber'];
                $inst->InstalmentSequence = $d['InstalmentSequence'];
                $inst->CTCAmount = "0";
                $inst->InstalmentActionDate = $d['InstalmentActionDate'];
                $inst->TrackingCode = $d['TrackingCode'];
                $inst->InstalmentAmount = $d['InstalmentAmount'];
                $inst->InstalmentStatus = $d['InstalmentStatus'];
                $inst->save();

                return 1;
            }
            else{
                return null;
            }

        }catch(\Exception $e){

        }
    }

    public function procesRealPayPayment(Request $request){
        try{
            $id = $request->policyId;
            $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
            $client = $realpay->createClient($id);
            $contract = $realpay->addClientContract($id);
            dd($contract);
        }catch(\Http\Client\Exception $e){
            dd($e->getMessage());
        }
    }

    public function processRealPayContract(Request $request){
        try{

            if($request->Code == 123456) {
                $id = $request->policyId;
                $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                $contract = $realpay->addClientContract($id);
                dd($contract);
            }else{
                dd('invalid code');
            }
        }catch(\Exception $e){
            dd($e->getMessage());
        }
    }

    public function addInstalmentManually($id){
        try{
            $realPay = RealpayContractDetails::where('ContractNumber',$id)->first();
            return view('admin.Realpay.addInstalment',compact('realPay'));
        }catch(\Exception $ex){

        }
    }

    public function storeNewInstalment(Request $request){
        try{
            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/instalments/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS =>"{\r\n  \"InstalmentPostRequest\": [\r\n    {\r\n
                            \"ClientNumber\": \"$request->clientNumber\",\r\n
                           \"ContractSequence\": $request->contractSequence,\r\n
                           \"ContractNumber\": $request->contractNumber,\r\n
                           \"InstalmentActionDate\": \"$request->instalmentDate\",\r\n
                           \"TrackingCode\": \"44\",\r\n
                           \"InstalmentAmount\": $request->instalmentAmount,\r\n
                           \"DebitSequenceType\": \"OOFF\",\r\n
                           }\r\n
                           ]\r\n
                           }",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: ".$token
                ),
            ));

            $response = curl_exec($curl);
            $data = json_decode($response,true);
            if(!empty($data['InstalmentPostResponse'][0]['Successful']) && empty($data['InstalmentPostResponse'][0]['Failed'])){
                $d = $data['InstalmentPostResponse'][0]['Successful'][0];
                $inst = new RealpayContractInstallments();
                $inst->clientNumber = $d['ClientNumber'];
                $inst->contractNumber = $d['ContractNumber'];
                $inst->InstalmentReferenceNumber = $d['InstalmentReferenceNumber'];
                $inst->InstalmentSequence = $d['InstalmentSequence'];
                $inst->CTCAmount = "0";
                $inst->InstalmentActionDate = $d['InstalmentActionDate'];
                $inst->TrackingCode = $d['TrackingCode'];
                $inst->InstalmentAmount = $d['InstalmentAmount'];
                $inst->InstalmentStatus = $d['InstalmentStatus'];
                $inst->save();
                return Redirect::back()->with('success', 'Instalment added successfully');
            }else{
                return Redirect::back()->with('error', 'Something went wrong');
            }
        }catch(\Exception $e){
            return Redirect::back()->with('error', $e->getMessage());
        }
    }

    public function editContract(){
        return view('admin.Realpay.editContract');
    }

    public function getContractDetailsFromRealpay(Request $request){
        try{
            dd($request->all());
        }catch(\Exception $ex){

        }
    }

    public function contractUpdateLogs(){
        try{
            return view('admin.Realpay.updateContractLogs');
        }catch(\Exception $ex){

        }
    }

    public function getLogData(){
        $data = UpdateRealpayClientContract::orderBy('id','DESC')->get();

        return DataTables::of($data)
            ->addColumn('action',function($data) {
                if($data->action == 1)
                    $status = 'Update Premium';
                elseif($data->action == 2)
                    $status = 'Update Billing Date';
                elseif($data->action == 3)
                    $status = 'Update both (premium & billing day)';
                else
                    $status = 'N/A';

                return $status;
            })
            ->addColumn('status',function($data) {
                if($data->action == 1)
                    $status = '<p style="color:green">Successfull</p>';
                elseif($data->action == 2)
                    $status = '<p style="color:red">Failed</p>';
                elseif($data->action == 3)
                    $status = '<p style="color:blue">In Progress</p>';
                elseif($data->action == 0)
                    $status = '<p style="color:lightseagreen">Pending</p>';
                else
                    $status = 'N/A';

                return $status;
            })
            ->addColumn('billing_day',function($data) {
                if($data->billing_day == '99')
                    $billing_day = 'Last day of the month';
                else
                    $billing_day = $data->billing_day;

                return $billing_day;
            })
            ->rawColumns(['action','billing_day','status'])
            ->make(true);
    }

    public function logUpdateDataRealpay(Request $request){
        try {
            $data = new UpdateRealpayClientContract();
            $data->product = $request->product;
            $data->client_number = $request->client_number;
            $data->contract_number = $request->contract_number;
            $data->premium = $request->premium;
            $data->action = $request->action;
            $data->billing_day = $request->billing_day;
            $data-> status= 0;
            $data->save();

            return Redirect::back()->with('success', 'Data saved successfully');

        }catch(\Exception $ex){
            return Redirect::back()->with('error', $ex->getMessage());
        }
    }

    public function updateContract(){
        try{

            $data = UpdateRealpayClientContract::where('status',0)
                ->orderBy('id','desc')
                ->first();

            $fetchToken = $this->clientAuth();
            if ($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'] . ' ' . $fetchToken['access_token'];
            else
                dd('Please clear cache and config');

            if($data != null){

                $data->status = 3;
                $data->save();

                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/contracts/" . $data->product . "?ClientNumber=" . $data->client_number . "&ContractNumber=" . $data->contract_number . "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
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
                        "Authorization: " . $token
                    ),
                ));

                $response = curl_exec($curl);
                $details = json_decode($response, true);

                $contractData = $details['ContractGetResponse'];

                if(!empty($contractData) && !empty($contractData[0]['ContractInstalments'])){
                    foreach($contractData[0]['ContractInstalments'] as $key=>$ins){
                        $update = $this->updateRealpayInstalment($data,$contractData[0],$ins);
                    }
                }else{
                    $data->status = 2;
                    $data->save();
                }

                if($update != null){
                    $data->status = 1;
                    $data->save();
                }else{
                    $data->status = 2;
                    $data->save();
                }

            }

        }catch(\Exception $ex){
            return Redirect::back()->with('error', $ex->getMessage());
        }
    }

    public function updateRealpayInstalment($data,$contractData,$ins){
        try{

            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();

            $contractSeq = $contractData['ContractSequence'];
            $clientNum = $contractData['ClientNumber'];
            $contractNumber = $contractData['ContractNumber'];
            $insSeq = $ins['InstalmentSequence'];
            $tracking = $ins['TrackingCode'];
            $amnt = ($data['premium'] != null) ? $data['premium'] : $ins['InstalmentAmount'];
            $insStatus = $ins['InstalmentStatus'];
            $date = ($data['billing_day'] != null) ? $this->getDate($data['billing_day'],$ins['InstalmentActionDate']) : $ins['InstalmentActionDate'];

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/instalments/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "PUT",
                CURLOPT_POSTFIELDS =>"{\r\n  \"InstalmentPutRequest\": [\r\n    {\r\n
                            \"ClientNumber\": \"$clientNum\",\r\n
                           \"ContractSequence\": \"$contractSeq\",\r\n
                           \"ContractNumber\": \"$contractNumber\",\r\n
                           \"InstalmentSequence\": \"$insSeq\",\r\n
                           \"InstalmentActionDate\": \"$date\",\r\n
                           \"TrackingCode\": \"$tracking\",\r\n
                           \"InstalmentAmount\": \"$amnt\",\r\n
                           \"InstalmentStatus\": \"$insStatus\",\r\n
                           \"DebitSequenceType\": \"OOFF\",\r\n
                           }\r\n
                           ]\r\n
                           }",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: ".$token
                ),
            ));

            $response = curl_exec($curl);
            $data = json_decode($response,true);

            $insdata = RealpayContractInstallments::where('clientNumber',$clientNum)
                ->where('contractNumber',$contractNumber)
                ->where('InstalmentSequence',$insSeq)
                ->first();
            if($insdata != null){
                $insdata->InstalmentAmount = $amnt;
                $insdata->save();
            }

            return $data;

        }catch(\Exception $e){
            return null;
        }
    }

    public function getDate($day,$date){
        $month = \Carbon\Carbon::parse($date)->format('m');
        $year = \Carbon\Carbon::parse($date)->format('Y');

        if($day == 99){
            $day = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        }

        return $year.'-'.$month.'-'.$day;
    }

    public function checkBankBranchesRealPay(){
        try{
            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL').'/general/banks/FNBNDOBW?BeneficiaryUser=16244&Version=v1',
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

            if($data['APIResponse']['Status']){
                if(count($banks = $data['BanksGetResponse'][0]['Products'][0]['Banks']) > 0){
                    foreach ($banks = $data['BanksGetResponse'][0]['Products'][0]['Banks'] as $bank){
                        foreach($bank['Branches'] as $branches){
                            $bak = BankBranches::where('branch_id',$branches['BranchCode'])->first();
                            if($bak == null){
                                $add = new BankBranches();
                                $add->name = $branches['BranchName'];
                                $add->bank_id = $bank['BankCode'];
                                $add->branch_id = $branches['BranchCode'];
                                $add->save();
                            }
                        }

                        $checkBank = Banks::where('bank_number',$bank['BankCode'])->first();
                        if($checkBank == null){
                            $add = new Banks();
                            $add->bank_number = $bank['BankCode'];
                            $add->bank_name = $bank['BankName'];
                            $add->save();
                        }
                    }
                }

            }
        }catch(\Exception $e){

        }
    }

    public function getContractDetails(Request $request){
        try{
            return view('admin.Realpay.getContractDetails');
        }catch(\Exception $e){
            return \Illuminate\Support\Facades\Redirect::back()->with('error',$e->getMessage());
        }
    }

    public function fetchRealpayContractDetails(Request $request){
        try{
            \Illuminate\Support\Facades\DB::beginTransaction();
            $policy = Policy::where('policyNumber',$request->policyNumber)->first(array('id','policyNumber','premium_freq','billing_day'));
            $insSeq = substr($request->ref_num,0,10);
            if($policy) {
                $fetchToken = $this->clientAuth();
                if ($fetchToken['token_type'] && $fetchToken['access_token'])
                    $token = $fetchToken['token_type'] . ' ' . $fetchToken['access_token'];
                else
                    dd('Please clear cache and config');

                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/contracts/" . $request->product . "?ClientNumber=" . $request->clientNumber . "&ContractNumber=" . $request->contractNumber . "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
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
                        "Authorization: " . $token
                    ),
                ));

                $response = curl_exec($curl);
                $details = json_decode($response, true);

                $contractData = $details['ContractGetResponse'];

                if($contractData != null && !empty($contractData)){

                    $rlpayClientContracts = RealpayClientContracts::where('client_number',$request->clientNumber)
                        ->where('policy_id',$policy->id)
                        ->where('contract_number',$request->contractNumber)
                        ->get();
                    if (json_decode($rlpayClientContracts) == NULL) {
                        $data = [
                            "policy_id"=> $policy->id,
                            "client_number"=> $request->clientNumber,
                            "contract_number"=> $request->contractNumber,
                            "rate_id"=> NULL,
                            "status"=> 1,
                        ];
                        $addRealpayClientContracts = RealpayClientContracts::addLog($data);
                    }

                    $data = $contractData[0];
                    $log = RealpayLogs::where('policy_id',$policy->id)->orderBy('id','desc')
                        ->first();
                    if($log != null){
                        $log->status = 1;
                        $log->save();
                    }else{
                        $addL = new RealpayLogs();
                        $addL->policy_id = $data['ContractNumber'];
                        $addL->event = 1;
                        $addL->status = 1;
                        $addL->save();
                    }

                    $req = RealpayPaymentRequest::where('clientNumber',$policy->policyNumber)
                        ->orderBy('id','desc')
                        ->first();
                    if($req != null){
                        $req->status = 1;
                        $req->save();
                    }else{
                        $addR = new RealpayPaymentRequest();
                        $addR->policy_id = $data['ContractNumber'];
                        $addR->clientNumber = $data['ClientNumber'];
                        $addR->client_response_sequence = $data['ContractNumber'];
                        $addR->clientCreated = 1;
                        $addR->contractCreated = 1;
                        $addR->first_premium = $data['ContractInstalments'][0]['InstalmentAmount'];
                        $addR->premium = $data['ContractInstalments'][2]['InstalmentAmount'];
                        $addR->billing_day = $policy->billing_day;
                        $addR->billing_date = $data['InstalmentStartDate'];
                        $addR->first_premium_contract = '';
                        $addR->contract = $data['ContractNumber'];
                        $addR->contract_response_sequence = '';
                        $addR->frequency = $policy->premium_freq;
                        $addR->response = 1;
                        $addR->status = 1;
                        $addR->save();
                    }
                    $contracts = RealpayContractDetails::where('ContractSequence',$data['ContractSequence'])->first();

                    if($contracts == null){
                        $addC = new RealpayContractDetails();
                        $addC->ContractSequence = $data['ContractSequence'];
                        $addC->ClientNumber = $data['ClientNumber'];
                        $addC->ContractNumber = $data['ContractNumber'];
                        $addC->CTCPercentage = $data['CTCPercentage'];
                        $addC->InstalmentStartDate = $data['InstalmentStartDate'];
                        $addC->TrackingCode = $data['TrackingCode'];
                        $addC->NumberOfInstalments = $data['NumberOfInstalments'];
                        $addC->FrequencyCode = $policy->premium_freq;
                        $addC->CollectionDay = $policy->billing_day;
                        $addC->status = null;
                        $addC->save();
                    }

                    $ins = RealpayContractInstallments::where('clientNumber',$policy->policyNumber)->count();
                    if($ins == 0 || $ins == null){
                        $storeIns = $this->storeInstallments($data);
                    }

                    \Illuminate\Support\Facades\DB::commit();
                    return redirect()->route('admin.view-installments', $policy->id);
                }else{
                    \Illuminate\Support\Facades\DB::rollBack();
                    return \Illuminate\Support\Facades\Redirect::back()->with('error','Data found empty');
                }

            } else {
                \Illuminate\Support\Facades\DB::rollBack();
                return \Illuminate\Support\Facades\Redirect::back()->with('error','Insufficient Realpay Log Data');
            }
        }catch(\Exception $e){
            \Illuminate\Support\Facades\DB::rollBack();
            return \Illuminate\Support\Facades\Redirect::back()->with('error',$e->getMessage());
        }
    }

    /*public function fetchRealpayContractDetails(Request $request){
        try{
            \Illuminate\Support\Facades\DB::beginTransaction();
            $policy = Policy::where('policyNumber',$request->policyNumber)->first(array('id','policyNumber','premium_freq','billing_day'));
            $insSeq = substr($request->ref_num,0,10);
            if($policy) {
                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => "https://realpaycollect.com:4448/rpp/rpws/oauth/token",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => "grant_type=client_credentials",
                    CURLOPT_HTTPHEADER => array(
                        "Authorization: Basic QW5lcTR4d1ZpVWJLS0VhUUpGVjI5QS4uOkxmUVp1aGFLZF9CYkFqUnZXTXp6b1EuLg==",
                        "Content-Type: application/x-www-form-urlencoded"
                    ),
                ));

                $response = curl_exec($curl);

                curl_close($curl);
                $fetchToken = json_decode($response,true);

                if ($fetchToken['token_type'] && $fetchToken['access_token'])
                    $token = $fetchToken['token_type'] . ' ' . $fetchToken['access_token'];
                else
                    dd('Please clear cache and config');

                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => "https://realpaycollect.com:4448/rpp/rpws/maintain/contracts/" . $request->product . "?ClientNumber=" . $request->clientNumber . "&ContractNumber=" . $request->contractNumber . "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
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
                        "Authorization: " . $token
                    ),
                ));

                $response = curl_exec($curl);
                $details = json_decode($response, true);
                dd($details);
                $contractData = $details['ContractGetResponse'];

                if($contractData != null && !empty($contractData)){

                    $rlpayClientContracts = RealpayClientContracts::where('client_number',$request->clientNumber)
                        ->where('policy_id',$policy->id)
                        ->where('contract_number',$request->contractNumber)
                        ->get();
                    if (json_decode($rlpayClientContracts) == NULL) {
                        $data = [
                            "policy_id"=> $policy->id,
                            "client_number"=> $request->clientNumber,
                            "contract_number"=> $request->contractNumber,
                            "rate_id"=> NULL,
                            "status"=> 1,
                        ];
                        $addRealpayClientContracts = RealpayClientContracts::addLog($data);
                    }

                    $data = $contractData[0];
                    $log = RealpayLogs::where('policy_id',$policy->id)->orderBy('id','desc')
                        ->first();
                    if($log != null){
                        $log->status = 1;
                        $log->save();
                    }else{
                        $addL = new RealpayLogs();
                        $addL->policy_id = $data['ContractNumber'];
                        $addL->event = 1;
                        $addL->status = 1;
                        $addL->save();
                    }

                    $req = RealpayPaymentRequest::where('clientNumber',$policy->policyNumber)
                        ->orderBy('id','desc')
                        ->first();
                    if($req != null){
                        $req->status = 1;
                        $req->save();
                    }else{
                        $addR = new RealpayPaymentRequest();
                        $addR->policy_id = $data['ContractNumber'];
                        $addR->clientNumber = $data['ClientNumber'];
                        $addR->client_response_sequence = $data['ContractNumber'];
                        $addR->clientCreated = 1;
                        $addR->contractCreated = 1;
                        $addR->first_premium = $data['ContractInstalments'][0]['InstalmentAmount'];
                        $addR->premium = $data['ContractInstalments'][2]['InstalmentAmount'];
                        $addR->billing_day = $policy->billing_day;
                        $addR->billing_date = $data['InstalmentStartDate'];
                        $addR->first_premium_contract = '';
                        $addR->contract = $data['ContractNumber'];
                        $addR->contract_response_sequence = '';
                        $addR->frequency = $policy->premium_freq;
                        $addR->response = 1;
                        $addR->status = 1;
                        $addR->save();
                    }
                    $contracts = RealpayContractDetails::where('ContractSequence',$data['ContractSequence'])->first();

                    if($contracts == null){
                        $addC = new RealpayContractDetails();
                        $addC->ContractSequence = $data['ContractSequence'];
                        $addC->ClientNumber = $data['ClientNumber'];
                        $addC->ContractNumber = $data['ContractNumber'];
                        $addC->CTCPercentage = $data['CTCPercentage'];
                        $addC->InstalmentStartDate = $data['InstalmentStartDate'];
                        $addC->TrackingCode = $data['TrackingCode'];
                        $addC->NumberOfInstalments = $data['NumberOfInstalments'];
                        $addC->FrequencyCode = $policy->premium_freq;
                        $addC->CollectionDay = $policy->billing_day;
                        $addC->status = null;
                        $addC->save();
                    }

                    $ins = RealpayContractInstallments::where('clientNumber',$policy->policyNumber)->count();
                    if($ins == 0 || $ins == null){
                        $storeIns = $this->storeInstallments($data);
                    }

                    \Illuminate\Support\Facades\DB::commit();
                    return redirect()->route('admin.view-installments', $policy->id);
                }else{
                    \Illuminate\Support\Facades\DB::rollBack();
                    return \Illuminate\Support\Facades\Redirect::back()->with('error','Data found empty');
                }

            } else {
                \Illuminate\Support\Facades\DB::rollBack();
                return \Illuminate\Support\Facades\Redirect::back()->with('error','Insufficient Realpay Log Data');
            }
        }catch(\Exception $e){
            \Illuminate\Support\Facades\DB::rollBack();
            return \Illuminate\Support\Facades\Redirect::back()->with('error',$e->getMessage().'-'.$e->getLine());
        }
    }*/

    public function getRealpayData(){
        try{
            $instalments = RealpayContractInstallments::whereBetween(\Illuminate\Support\Facades\DB::raw('date(InstalmentActionDate)') , [\Carbon\Carbon::parse('2021-03-01')
                ->format('Y-m-d')  , Carbon::parse('2021-05-31') //As per the requirement data for: March, APril and MAy
            ->format('Y-m-d') ])
                ->get();
            foreach($instalments as $key=>$ins) {
                $this->updateRealpayPaymentStatus($ins);
            }

            Log::info('Update Complete');
            return 1;
        }catch(\Exception $ex){
            Log::error($ex->getMessage());
        }
    }

    public function updateRealpayPaymentStatus($ins){
        try{
            $contract = RealpayContractDetails::where('ClientNumber',$ins->clientNumber)->first();
            if($contract != null){

                $fetchToken = $this->clientAuth();
                if($fetchToken['token_type'] && $fetchToken['access_token'])
                    $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
                else
                    Log::error($ins->clientNumber.' : Failed to get token');

                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/instalments/".env('REALPAY_PRODUCT')."?ClientNumber=".$contract->ClientNumber."&ContractNumber=".$contract->ContractNumber."&ContractSequence=".$contract->ContractSequence."&InstalmentSequence=".$ins->InstalmentSequence."&BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
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

                if($data != null){
                    if($data && $data['APIResponse']['Status'] == 'SUCCESS') {
                        $status = $data['InstalmentGetResponse'][0]['InstalmentStatus'];
                        $amount = $data['InstalmentGetResponse'][0]['InstalmentAmount'];
                        $update = RealpayContractInstallments::where('clientNumber', $data['InstalmentGetResponse'][0]['ClientNumber'])
                            ->where('contractNumber', $data['InstalmentGetResponse'][0]['ContractNumber'])
                            ->where('InstalmentReferenceNumber', $data['InstalmentGetResponse'][0]['InstalmentReferenceNumber'])
                            ->where('InstalmentSequence', $data['InstalmentGetResponse'][0]['InstalmentSequence'])
                            ->first();
                        if($update != null){
                            $update->InstalmentStatus = $status;
                            $update->InstalmentAmount = $amount;
                            $update->save();

                            $policy = Policy::where('policyNumber',$data['InstalmentGetResponse'][0]['ClientNumber'])->first('status','premium');

                            if($status == 'S' || $status == 'A'){
                                $policy->status = '1';
                                $policy->save();
                            }elseif($status == 'I'){
                                $policy->status = '2';
                                $policy->save();
                            }elseif($status == 'F'){

                            }else{
                                Log::error('Instalment status  '.$status .'for '.$ins->clientNumber);
                            }

                        }else{
                            Log::error('Instalment details not found on database for '.$ins->clientNumber);
                        }
                    }
                }else{
                    Log::error('Instalment details not found on Realpay_contracts for '.$ins->clientNumber.' '.$data['APIResponse']['CallSequence']);
                }
            }else{
                Log::error('Contract details not found on Realpay_contracts for '.$ins->clientNumber);
            }
        }catch(\Exception $ex){
            Log::error($ins->clientNumber.' '.$ex->getMessage());
        }
    }

    public function getFrequencyChange($policyNumber){
        $policy = Policy::where('policyNumber',$policyNumber)->first(array('premium_freq'));
        $update = UpdateRealpayContract::where('policyNumber',$policyNumber)->orderBy('id','desc')->first(array('frequency'));

        if($policy->premium_freq == 1 && $update->frequency == 1){
            return 11;
        }elseif($policy->premium_freq == 1 && $update->frequency == 2){
            return 12;
        }elseif($policy->premium_freq == 1 && $update->frequency == 3){
            return 13;
        }elseif($policy->premium_freq == 2 && $update->frequency == 1){
            return 21;
        }elseif($policy->premium_freq == 2 && $update->frequency == 2){
            return 22;
        }elseif($policy->premium_freq == 2 && $update->frequency == 3){
            return 23;
        }elseif($policy->premium_freq == 3 && $update->frequency == 1){
            return 31;
        }elseif($policy->premium_freq == 3 && $update->frequency == 2){
            return 32;
        }elseif($policy->premium_freq == 3 && $update->frequency == 3){
            return 33;
        }else{
            return null;
        }

    }

    public function updateRealpayClientDetails($data){
        if($data['policyNumber'] != null){
            if($data['bankCode'] != null && $data['branchCode'] != null && $data['accountType'] != null && $data['accountNumber'] != null){
                $policy = Policy::where('policyNumber',$data['policyNumber'])->first();
                $bankCode = $data['bankCode'];
                $branchCode = $data['branchCode'];
                $accountType = $data['accountType'];
                $accountNumber = $data['accountNumber'];
                if($policy && $policy->customer_id != null){
                    $customer = Customer::where('id', $policy->customer_id)->with('profile')->first();
                    $profile = CustomerProfile::where('customer_id',$customer->id)->first();
                    $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

                    if($customerBanking->billing != 'RealPay'){
                        return response()->json(['Status'=>'Failed','Message'=>'Record not found on Realpay'],401);
                    }

                    $fetchToken = $this->clientAuth();
                    if($fetchToken['token_type'] && $fetchToken['access_token'])
                        $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
                    else
                        return response()->json(['Status'=>'Failed','Message'=>'Client auth failed at RealPay'],401);

                    if($profile->omang != null){
                        $id = $profile->omang;
                        $idType = 'I';
                    }else{
                        $id = $profile->passport;
                        $idType = 'P';
                    }
                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/clients/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "PUT",
                        CURLOPT_POSTFIELDS =>"{\r\n  \"ClientPutRequest\": [\r\n    {\r\n
                            \"ClientNumber\": \"$policy->policyNumber\",\r\n
                           \"ClientName\": \"$customer->firstName $customer->lastName\",\r\n
                           \"IDType\": \"$idType\",\r\n
                           \"IDNumber\": \"$id\",\r\n
                           \"CellphoneNumber\": \"$customer->cellphone\",\r\n
                           \"EMail\": \"$customer->email\",\r\n
                           \"BankCode\": \"$bankCode\",\r\n
                           \"BranchCode\": \"$branchCode\",\r\n
                           \"AccountType\": \"$accountType\",\r\n
                           \"AccountNumber\": \"$accountNumber\",\r\n
                           \"AccountHolderName\": \"$customer->firstName $customer->lastName\",\r\n
                           \"EmployeeGroupCode\": \"OT\",\r\n
                           }\r\n
                           ]\r\n
                           }",
                        CURLOPT_HTTPHEADER => array(
                            "Content-Type: application/json",
                            "Accept: application/json",
                            "Authorization: ".$token
                        ),
                    ));

                    $response = curl_exec($curl);
                    $data = json_decode($response,true);

                    curl_close($curl);

                    if(!empty($data['ClientPutResponse'][0]['Successful']) && empty($data['ClientPutResponse'][0]['Failed'])){

                        $customerBanking->bankName = $bankCode;
                        $customerBanking->branchCode = $branchCode;
                        $customerBanking->accountType = $accountType;
                        $customerBanking->accountNumber = $accountNumber;
                        $customerBanking->save();

                        return response()->json(['Status'=>'Success','Message'=>'Customer bank information update successfully'],200);
                    }else{
                        return response()->json(['Status'=>'Failed','Message'=>$data['ClientPutResponse'][0]['Failed'][0]['Failures'][0]['FailureDescription']],401);
                    }

                }else{
                    return response()->json(['Status'=>'Failed','Message'=>'Customer not found with respect to policy number'],401);
                }
            }else{
                return response()->json(['Status'=>'Failed','Message'=>'Insufficient parameter data provided'],401);
            }
        }else{
            return response()->json(['Status'=>'Failed','Message'=>'Policy Number not found'],401);
        }
    }

    public function addContractInstalments(Request $request){
        try{

        }catch(\Exception $ex){

        }
    }

    public function updateClientContractNumber(Request $request){
        try {
            //  dd($request->all());
            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/clients/" . env('REALPAY_PRODUCT') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
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
            $clientData = json_decode($response,true);
            curl_close($curl);

            if (isset($clientData['ClientGetResponse']) && $clientData['ClientGetResponse'] != NULL) {
                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/contracts/" . env('REALPAY_PRODUCT') . "?ClientNumber=" . $request->clientNumber . "&ContractNumber=" . $request->contractNumber ."&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
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
                $contractData = json_decode($response,true);
                curl_close($curl);

                if (isset($contractData['ContractGetResponse']) && $contractData['ContractGetResponse'] != NULL) {

                    $installmentData = RealpayContractInstallments::where('contractNumber',$request->contractNumber)->get();
                    if (json_decode($installmentData) != NULL) {
                        $update = RealpayPaymentRequest::where('policy_id', $request->policy_id)->first();
                    }else {
                        $contract = $this->storeContractDetails($contractData['ContractGetResponse'][0]);
                        $installments = $this->storeInstallments($contractData['ContractGetResponse'][0]);
                        $update = RealpayPaymentRequest::where('policy_id', $request->policy_id)->first();
                    }
                    if (isset($update)) {
                        $update->clientNumber = $request->clientNumber;
                        $update->contract = $request->contractNumber;
                        $updateSts = $update->save();
                        return Redirect::route('admin.policy.edit', $request->policy_id)->with('success', 'Client and Contract Number update successfully');
                    } else {
                        return Redirect::route('admin.policy.edit', $request->policy_id)->with('error', 'Failed to update Client and Contract Number');
                    }
                }else {
                    return Redirect::route('admin.policy.edit', $request->policy_id)->with('error', 'Contract Number Not Found. Failed to update Client and Contract Number');
                }
            } else {
                return Redirect::route('admin.policy.edit', $request->policy_id)->with('error', 'Client Number Not Found. Failed to update Client and Contract Number');
            }
        } catch (\Exception $ex) {
            return $ex;
        }
    }


    public function getUpdatedContractInstallments(Request $request){
        try{
            $policy = Policy::where('id',$request->policy_id)->first(array('id','policyNumber'));
            $clientContracts = RealpayPaymentRequest::where('policy_id', $request->policy_id)->orderBy('id','desc')->first();

            if($clientContracts == null){
                $data = RealpayContractInstallments::where('contractNumber',$request->policy_id)->get();
            }else{
                $data = RealpayContractInstallments::where('contractNumber',$clientContracts->contract)->get();
            }

            return DataTables::of($data)
                ->editColumn('action', function ($data) {

                    switch ($data->InstalmentStatus) {
                        case 'S':
                            $actions = '<span class="kt-font-bold kt-font-success">Success</span>';
                            break;
                        case 'W':
                            $actions = '<span class="kt-font-bold kt-font-info">Processing</span>';
                            break;

                        case 'F':
                            $d = $data->toArray();
                            $actions = '<a href="' . route('admin.updateStatus',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'R']) . '" class="btn btn-sm btn-elevate btn-warning btn-elevate" title="Cancel Instalment">
                                <span class="kt-opacity-11" id="">Retry</span>
                            </a>';
                            break;
                        case 'R':
                            $d = $data->toArray();
                            $actions = '<a href="' . route('admin.updateStatus',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'I']) . '" class="btn btn-sm btn-elevate btn-danger btn-elevate" title="Cancel Instalment">
                                <span class="kt-opacity-11" id="">Cancel</span>
                            </a>';
                            break;
                        case 'A':
                            $d = $data->toArray();
                            $actions = '<a href="' . route('admin.updateStatus',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'I']) . '" value="'.$d['InstalmentReferenceNumber'].'" class="btn btn-sm btn-elevate btn-danger btn-elevate confirm-cancel" title="Cancel Instalment">
                                <span class="kt-opacity-11" id="">Cancel</span>
                            </a>';
                            break;
                        case 'I':
                            $actions = '<span class="kt-font-bold kt-font-info">Cancelled</span>';
                            break;
                        case 'E':
                            $d = $data->toArray();
                            $actions = '<a href="' . route('admin.updateStatus',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'R']) . '" class="btn btn-sm btn-elevate btn-warning btn-elevate" title="Cancel Instalment">
                                <span class="kt-opacity-11" id="">Retry</span>
                            </a>';
                            break;
                        default :
                            $actions = '<span class="kt-font-bold kt-font-danger">Status not found</span>';
                            break;
                    }
                    return $actions;
                })
                ->editColumn('InstalmentStatus', function ($data) {
                    switch ($data->InstalmentStatus){
                        case 'S':
                            $status =  '<span class="kt-font-bold kt-font-success">Success</span>';
                            break;
                        case 'W':
                            $status =  '<span class="kt-font-bold kt-font-info">Processing</span>';
                            break;

                        case 'F':
                            $status =  '<span class="kt-font-bold kt-font-danger">Failed</span>';
                            break;
                        case 'R':
                            $status =  '<span class="kt-font-bold kt-font-info">Retry</span>';
                            break;
                        case 'A':
                            $status =  '<span class="kt-font-bold kt-font-info">Active</span>';
                            break;
                        case 'I':
                            $status =  '<span class="kt-font-bold kt-font-info">Cancelled</span>';
                            break;
                        case 'E':
                            $status =  '<span class="kt-font-bold kt-font-warning">Error</span>';
                            break;
                        default :
                            $status =  '<span class="kt-font-bold kt-font-danger">Status not found</span>';
                            break;
                    }

                    return $status;
                })

                ->rawColumns(['InstalmentStatus','action'])
                ->make(true);
        }catch(\Exception $ex){
            dd($ex->getMessage().' '.$ex->getLine());
        }
    }


    public function updateClientContractNumberApi(Request $request){
        try {
            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/clients/" . env('REALPAY_PRODUCT') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
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
            $clientData = json_decode($response,true);
            curl_close($curl);

            if (isset($clientData['ClientGetResponse']) && $clientData['ClientGetResponse'] != NULL) {
                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/contracts/" . env('REALPAY_PRODUCT') . "?ClientNumber=" . $request->clientNumber . "&ContractNumber=" . $request->contractNumber ."&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
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
                $contractData = json_decode($response,true);
                curl_close($curl);

                if (isset($contractData['ContractGetResponse']) && $contractData['ContractGetResponse'] != NULL) {

                    $installmentData = RealpayContractInstallments::where('contractNumber',$request->contractNumber)->get();
                    if (json_decode($installmentData) != NULL) {
                        $update = RealpayPaymentRequest::where('policy_id', $request->policy_id)->first();
                    }else {
                        $contract = $this->storeContractDetails($contractData['ContractGetResponse'][0]);
                        $installments = $this->storeInstallments($contractData['ContractGetResponse'][0]);
                        $update = RealpayPaymentRequest::where('policy_id', $request->policy_id)->first();
                    }
                    if (isset($update)) {
                        $update->clientNumber = $request->clientNumber;
                        $update->contract = $request->contractNumber;
                        $updateSts = $update->save();
                        return response()->json(['Status'=>'Success','Message'=>'Client and Contract Number update successfully'],200);
                    } else {
                        return response()->json(['Status'=>'Failed','Message'=>'Failed to update Client and Contract Number'],201);
                    }

                }else {
                    return response()->json(['Status'=>'Failed','Message'=>'Contract Number Not Found. Failed to update Client and Contract Number'],201);
                }
            } else {
                return response()->json(['Status'=>'Failed','Message'=>'Client Number Not Found. Failed to update Client and Contract Number'],201);
            }
        } catch (\Exception $ex) {
            return $ex;
        }
    }


    public function getClientContractDetails(Request $request){
        try {
            // $contractData = RealpayContractDetails::where('ClientNumber',$request->contractNumber)->get();
            $contractData = RealpayContractInstallments::where('contractNumber',$request->contractNumber)->get();
            // $contractData = json_decode($contractData);
            // dd($contractData);
            if (json_decode($contractData) != NULL) {
                // dd("if");
                // $data = RealpayContractInstallments::where('contractNumber',$contractData->ContractNumber)->get();
            } else {
                $fetchToken = $this->clientAuth();
                if($fetchToken['token_type'] && $fetchToken['access_token'])
                    $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
                else
                    return null;

                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/clients/" . env('REALPAY_PRODUCT') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
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
                $clientData = json_decode($response,true);
                curl_close($curl);
                // dd($clientData);
                if (isset($clientData['ClientGetResponse']) && $clientData['ClientGetResponse'] != NULL) {
                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/contracts/" . env('REALPAY_PRODUCT') . "?ClientNumber=" . $request->clientNumber ."&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
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

                    // dd($request->clientNumber);
                    if (isset($data['ContractGetResponse']) && $data['ContractGetResponse'] != NULL) {

                        $contract = $this->storeContractDetails($data['ContractGetResponse'][0]);
                        $installments = $this->storeInstallments($data['ContractGetResponse'][0]);

                    }
                }

                $contractData = RealpayContractInstallments::where('contractNumber',$request->contractNumber)->get();
                // $contractData = json_decode(json_encode($contractData));

                // return DataTables::of($contractData)
                // ->make(true);
            }


            // dd($contractData);
            return DataTables::of($contractData)
                ->editColumn('action', function ($contractData) {

                    switch ($contractData->InstalmentStatus) {
                        case 'S':
                            $actions = '<span class="kt-font-bold kt-font-success">Success</span>';
                            break;
                        case 'W':
                            $actions = '<span class="kt-font-bold kt-font-info">Processing</span>';
                            break;

                        case 'F':
                            $d = $contractData->toArray();
                            $actions = '<a href="' . route('admin.updateStatus',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'R']) . '" class="btn btn-sm btn-elevate btn-warning btn-elevate" title="Cancel Instalment">
                                <span class="kt-opacity-11" id="">Retry</span>
                            </a>';
                            break;
                        case 'R':
                            $d = $contractData->toArray();
                            $actions = '<a href="' . route('admin.updateStatus',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'I']) . '" class="btn btn-sm btn-elevate btn-danger btn-elevate" title="Cancel Instalment">
                                <span class="kt-opacity-11" id="">Cancel</span>
                            </a>';
                            break;
                        case 'A':
                            $d = $contractData->toArray();
                            $actions = '<a href="' . route('admin.updateStatus',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'I']) . '" value="'.$d['InstalmentReferenceNumber'].'" class="btn btn-sm btn-elevate btn-danger btn-elevate confirm-cancel" title="Cancel Instalment">
                                <span class="kt-opacity-11" id="">Cancel</span>
                            </a>';
                            break;
                        case 'I':
                            $actions = '<span class="kt-font-bold kt-font-info">Cancelled</span>';
                            break;
                        case 'E':
                            $d = $contractData->toArray();
                            $actions = '<a href="' . route('admin.updateStatus',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'R']) . '" class="btn btn-sm btn-elevate btn-warning btn-elevate" title="Cancel Instalment">
                                <span class="kt-opacity-11" id="">Retry</span>
                            </a>';
                            break;
                        default :
                            $actions = '<span class="kt-font-bold kt-font-danger">Status not found</span>';
                            break;
                    }
                    return $actions;
                })
                ->editColumn('InstalmentStatus', function ($contractData) {
                    switch ($contractData->InstalmentStatus){
                        case 'S':
                            $status =  '<span class="kt-font-bold kt-font-success">Success</span>';
                            break;
                        case 'W':
                            $status =  '<span class="kt-font-bold kt-font-info">Processing</span>';
                            break;

                        case 'F':
                            $status =  '<span class="kt-font-bold kt-font-danger">Failed</span>';
                            break;
                        case 'R':
                            $status =  '<span class="kt-font-bold kt-font-info">Retry</span>';
                            break;
                        case 'A':
                            $status =  '<span class="kt-font-bold kt-font-info">Active</span>';
                            break;
                        case 'I':
                            $status =  '<span class="kt-font-bold kt-font-info">Cancelled</span>';
                            break;
                        case 'E':
                            $status =  '<span class="kt-font-bold kt-font-warning">Error</span>';
                            break;
                        default :
                            $status =  '<span class="kt-font-bold kt-font-danger">Status not found</span>';
                            break;
                    }

                    return $status;
                })

                ->rawColumns(['InstalmentStatus','action'])
                ->make(true);


        } catch (\Exception $ex) {
            return $ex;
        }
    }


    public function getContractInfo(Request $request){
        try {
            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/clients/" . env('REALPAY_PRODUCT') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
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
            $clientData = json_decode($response,true);
            curl_close($curl);
            // dd($clientData);
            if (isset($clientData['ClientGetResponse']) && $clientData['ClientGetResponse'] != NULL) {
                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/contracts/" . env('REALPAY_PRODUCT') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
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
                $contractData = json_decode($response,true);
                curl_close($curl);
                // dd($contractData);
                if (isset($contractData['ContractGetResponse']) && $contractData['ContractGetResponse'] != NULL) {
                    return response()->json(['Status'=>'Success', 'contracts'=>$contractData['ContractGetResponse'],'Message'=>'Client and Contract Number update successfully'],200);
                } else {
                    return response()->json(['Status'=>'Failed','Message'=>'Contract Number not found'],201);
                }
            } else {
                return response()->json(['Status'=>'Failed','Message'=>'Contract Number not found'],201);
            }
        } catch (\Exception $ex) {
            return $ex;
        }
    }

    public function CancelRealpayPaymentContract(Request $request){
        try{
            $policyId = $request->policyID;

            $checkPayment = $this->checkPaymentMethod($policyId);

            if (isset($checkPayment)) {

                //Cancel existing payment if exists

                switch ($checkPayment){
                    case 'RealPay' :
                        $clientNumber = $this->cancelRealpayContract($policyId);

                        if($clientNumber != null){
                            $can = RealpayCancelRequests::where('policy_id',$policyId)->first();
                            if (isset($can)) {
                                $can->cancel_status = 1;
                                $can->save();
                            }
                            // else {
                            //     return response()->json(['error'=>'failed','message' => 'Failed to cancel contract','status' => '401']);
                            // }

                            $trans = Transaction::where('realPayTransaction_id',$policyId)
                                ->orderBy('id', 'DESC')
                                ->first();
                            if($trans != null){
                                $trans->status = "CANCELLED";
                                $trans->save();
                            }
                        }else{
                            $can = RealpayCancelRequests::where('policy_id',$policyId)->first();
                            if (isset($can)) {
                                $can->cancel_status = 2;
                                $can->save();
                            }
                            // else {
                            //     return response()->json(['error'=>'failed','message' => 'Failed to cancel contract','status' => '401']);
                            // }
                        }

                        break;
                    case 'VCS' :

                        break;
                    case 'DPO' :
                        break;
                    default:
                        break;
                }

                return response()->json(['success'=>'success','message' => 'Contract cancelled successfully','status' => '200']);

            } else {
                return response()->json(['error'=>'failed','message' => 'Payment not found','status' => '401']);
            }

        }catch(\Exception $ex){
            // dd($ex);
            return response()->json(['error'=>'failed','payment'=>null,'status' => '401','message' => 'Failed to cancel contract']);
        }
    }


    public function logRealpayPayment(Request $request){
        try{
            $policyId = $request->policyID;

            $checkPayment = $this->checkPaymentMethod($policyId);

            if (isset($checkPayment)) {
                $policy = Policy::where('id',$policyId)->first(array('policyNumber'));
                //Cancel existing payment if exists

                switch ($checkPayment){
                    case 'RealPay' :
                        $clientNumber = $this->cancelRealpayContract($policyId);
                        if($clientNumber != null){
                            $can = RealpayCancelRequests::where('policy_id',$policyId)->first();
                            if (isset($can)) {
                                $can->cancel_status = 1;
                                $can->save();
                            } else {
                                // $can = new RealpayCancelRequests();
                                // $can->policy_id = $policyId;
                                // $can->leftout_premium_contract = null;
                                // $can->contract = $contract->contract_number;
                                // $can->cancel_status = 0;
                                // $can->save();
                                return response()->json(['error'=>'failed','message' => 'Failed to cancel contract','status' => '401']);
                            }

                            $trans = Transaction::where('realPayTransaction_id',$policyId)
                                ->orderBy('id', 'DESC')
                                ->first();
                            if($trans != null){
                                $trans->status = "CANCELLED";
                                $trans->save();
                            }
                        }else{
                            $can = RealpayCancelRequests::where('policy_id',$policyId)->first();
                            if (isset($can)) {
                                $can->cancel_status = 2;
                                $can->save();
                            } else {
                                return response()->json(['error'=>'failed','message' => 'Failed to cancel contract','status' => '401']);
                            }

                        }

                        break;
                    case 'VCS' :
                        $transctionsRow = Transaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'desc')->first();
                        if ($transctionsRow && $transctionsRow->referenceNumber) {
                            $referenceNumber = $transctionsRow->referenceNumber;
                            $vcs = new PaymentController;
                            $vcs->suspendTransactionOnVCS($referenceNumber);
                        }
                        break;
                    case 'DPO' :
                        break;
                    default:
                        break;
                }

                // $policy = Policy::where('id',$policyId)->first();

                // $policy->premium_freq = $request->frequency;
                // $policy->billingStartDate = $request->billingDate;
                // $policy->save();

                $addPayment = $this->addRealpayPayment($request);
                // dd($addPayment['status']);
                // return $addPayment;
                // dd($addPayment);
                return $addPayment;

            } else {
                return response()->json(['error'=>'failed','message' => 'Payment not found','status' => '401']);
            }

        }catch(\Exception $ex){
            //  dd($ex);
            return response()->json(['success'=>1,'payment'=>null,'status' => '401','message' => 'Failed to cancel contract']);
        }
    }

    public function addRealpayPayment($request){
        try{
            $policy = Policy::where('id',$request->policyID)->orderBy('id','DESC')->first();
            $customer = Customer::where('id', $policy->customer_id)->with('profile')->first();
            $profile = CustomerProfile::where('customer_id',$customer->id)->first();
            $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
            if($customerBanking == null){
                $customerBanking = new CustomerBanking();
            }

            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            if($profile->omang != null){
                $id = $profile->omang;
                $idType = 'I';
            }else{
                $id = $profile->passport;
                $idType = 'P';
            }
            $curl = curl_init();

            $checkClient = $this->checkClientExists($policy->id);

            if($checkClient == false){
                curl_setopt_array($curl, array(
                    CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/clients/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS =>"{\r\n  \"ClientPostRequest\": [\r\n    {\r\n
                    \"ClientNumber\": \"$policy->policyNumber\",\r\n
                    \"ClientName\": \"$customer->firstName $customer->lastName\",\r\n
                    \"IDType\": \"$idType\",\r\n
                    \"IDNumber\": \"$id\",\r\n
                    \"CellphoneNumber\": \"$customer->cellphone\",\r\n
                    \"EMail\": \"$customer->email\",\r\n
                    \"BankCode\": \"$request->BankCode\",\r\n
                    \"BranchCode\": \"$request->BranchCode\",\r\n
                    \"AccountType\": \"$request->accountType\",\r\n
                    \"AccountNumber\": \"$request->accountNumber\",\r\n
                    \"AccountHolderName\": \"$customer->firstName $customer->lastName\",\r\n
                    \"EmployeeGroupCode\": \"OT\",\r\n
                    }\r\n
                    ]\r\n
                    }",
                    CURLOPT_HTTPHEADER => array(
                        "Content-Type: application/json",
                        "Accept: application/json",
                        "Authorization: ".$token
                    ),
                ));

                $response = curl_exec($curl);
                $data = json_decode($response,true);

                curl_close($curl);
            }else{

                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/clients/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "PUT",
                    CURLOPT_POSTFIELDS =>"{\r\n  \"ClientPutRequest\": [\r\n    {\r\n
                            \"ClientNumber\": \"$policy->policyNumber\",\r\n
                           \"ClientName\": \"$customer->firstName $customer->lastName\",\r\n
                           \"IDType\": \"$idType\",\r\n
                           \"IDNumber\": \"$id\",\r\n
                           \"CellphoneNumber\": \"$customer->cellphone\",\r\n
                           \"EMail\": \"$customer->email\",\r\n
                           \"BankCode\": \"$request->BankCode\",\r\n
                           \"BranchCode\": \"$request->branchCode\",\r\n
                           \"AccountType\": \"$request->accountType\",\r\n
                           \"AccountNumber\": \"$request->accountNumber\",\r\n
                           \"AccountHolderName\": \"$customer->firstName $customer->lastName\",\r\n
                           \"EmployeeGroupCode\": \"OT\",\r\n
                           }\r\n
                           ]\r\n
                           }",
                    CURLOPT_HTTPHEADER => array(
                        "Content-Type: application/json",
                        "Accept: application/json",
                        "Authorization: ".$token
                    ),
                ));

                $response = curl_exec($curl);
                $data = json_decode($response,true);

                curl_close($curl);
            }

            //if(!empty($data['ClientPostResponse'][0]['Successful']) && empty($data['ClientPostResponse'][0]['Failed'])){
            $customerBanking->bankName = $request->BankCode;
            $customerBanking->branchCode = $request->BranchCode;
            $customerBanking->accountType = $request->accountType;
            $customerBanking->accountNumber = $request->accountNumber;
            $customerBanking->billing = "RealPay";
            $customerBanking->billing_day = $request->billing_day;
            $customerBanking->billingStartDate = $this->setDate($request->billing_day);
            // dd($customerBanking);
            $customerBanking->save();
            // dd($customerBanking->save());
            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;
            // dd($token,$policy->id);
            $policy = Policy::where('id',$policy->id)->first();
            // dd($policy,$token,$policy->id);
            if($policy->product_id != 3)
                $policy->first_premium_wvat = 0;

            $firstBillingDate = $request->first_collection_date;
            $firstCollectionAmount = $request->first_premium;
            $numberOfInstallments = '99';
            $frequency = 'MNTH';
            $premium = $request->premium;


            if($request->frequency != null){

                if($request->frequency == 2){
                    $numberOfInstallments = '3';
                }
                elseif($request->frequency == 3){
                    $frequency = 'YEAR';
                    $numberOfInstallments = '99';
                }
                elseif($request->frequency == 1){
                    $numberOfInstallments = '99';
                }
                else{
                    if($policy->quoteNumber) {
                        $quote = MotorComprehensiveQuotes::where('quoteNumber', $policy->quoteNumber)->first(array('premiumMonthly'));
                        $premium = $quote->premiumMonthly;
                        $numberOfInstallments = '99';
                        $policy->premium_freq = 1;
                        $policy->save();
                    }else{
                        return null;
                    }
                }
            }
            // dd($request->all(),$request->frequency,$numberOfInstallments);
            $billing_day = '';
            if ($request->billingDay != NULL) {
                $billing_day = \Carbon\Carbon::createFromFormat('Y-m-d', $request->billingDay)->format('d');
            }

            if($billing_day == 31 || $billing_day == 30 || $billing_day == 29){
                $billing_day = 99;
            }

            $contractNumber = RealpayClientContracts::getContractNumber($policy->id);

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/contracts/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS =>"{\r\n
            \"ContractPostRequest\": [\r\n
                {\r\n
                      \"ClientNumber\": \"$policy->policyNumber\",\r\n
                      \"ContractNumber\": \"$contractNumber\",\r\n
                      \"FrequencyCode\": \"$frequency\",\r\n
                      \"CollectionDay\": \"$billing_day\",\r\n
                      \"TrackingCode\": \"44\",\r\n
                      \"FirstCollectionDate\": \"$firstBillingDate\",\r\n
                      \"FirstCollectionAmount\": \"$firstCollectionAmount\",\r\n
                      \"InstalmentStartDate\": \"$request->billingDay\",\r\n
                      \"InstalmentAmount\": $premium,\r\n
                      \"NumberOfInstalments\": \"$numberOfInstallments\",\r\n
                      \"CTCPercentage\": 1\r\n
                      }\r\n
                 ]\r\n}",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: " . $token
                ),
            ));
            $response = curl_exec($curl);
            $data = json_decode($response, true);

            curl_close($curl);

            $update = RealpayPaymentRequest::where('policy_id', $policy->id)->first();
            if (sizeof($data['ContractPostResponse'][0]['Successful']) > 0 && sizeof($data['ContractPostResponse'][0]['Failed']) == 0) {
                if(sizeof($data['ContractPostResponse'][0]['Successful'][0]['ContractInstalments']) > 0){
                    $contract = $this->storeContractDetails($data['ContractPostResponse'][0]['Successful'][0]);
                    $installments = $this->storeInstallments($data['ContractPostResponse'][0]['Successful'][0]);
                }

                $logData = [
                    'policy_id'=>$policy->id,
                    'client_number'=>$policy->policyNumber,
                    'contract_number'=>$contractNumber,
                    //'rate_id'=>$data['rate_id'],
                    'status'=>1,
                ];

                $addLog = RealpayClientContracts::addLog($logData);

                if($contractNumber != null) {
                    $Table = (new RealpayClientContracts())->getTable();
                    DB::table($Table)->where('client_number', $policy->policyNumber)
                        ->where('contract_number', '!=',$contractNumber)
                        ->update(array('status' => 0));
                }

                return response()->json(['status' => '200', 'message' => 'Client added successfully on realpay'], 200);

            } else {

                if($data['ContractPostResponse'][0]['Failed'][0]['Failures'][0]['FailureCode'] == 'TAK1'){
                    $logData = [
                        'policy_id'=>$policy->id,
                        'client_number'=>$policy->policyNumber,
                        'contract_number'=>$contractNumber,
                        'status'=>1,
                    ];

                    $addLog = RealpayClientContracts::addLog($logData);

                    if($contractNumber != null) {
                        $Table = (new RealpayClientContracts())->getTable();
                        DB::table($Table)->where('client_number', $policy->policyNumber)
                            ->where('contract_number', '!=',$contractNumber)
                            ->update(array('status' => 0));
                    }
                }

                return response()->json(['status' => '401', 'message' => $data['ContractPostResponse'][0]['Failed'][0]['Failures'][0]['FailureDescription']], 401);

            }

        }catch(\Exception $ex){
            return null;
        }
    }

    public function checkPaymentMethod($policyId){
        try{
            $banking = CustomerBanking::where('policy_id',$policyId)
                ->orderBy('id','desc')
                ->first(array('billing'));

            $bankingBill = '';
            if($banking && $banking->billing){
                $bankingBill = $banking->billing;
                return $bankingBill;
            }
            else{
                $policy = Policy::where('id',$policyId)->orderBy('id','desc')->first(array('policyNumber'));
                $paymentTrans = PaymentTransaction::where('policyNumber',$policy->policyNumber)->orderBy('id','desc')->first(array('paymentMethod'));
                $bankingBill = $paymentTrans->paymentMethod;
                return $bankingBill;
            }

        }catch(\Exception $ex){
            return null;
        }
    }

    public function logRealpayPaymentForPolicyRenewal(Request $request){
        try{
            if (isset($request->policy_id)) {
                $request['policyID'] = $request->policy_id;
            }

            // $request['BankName'] = $request->bankName;
            // $request['BranchCode'] = $request->branchCode;
            // $request['billingDay'] = $request->billing_day;


            $policyId = $request->policyID;

            $checkPayment = $this->checkPaymentMethod($policyId);

            if (isset($checkPayment)) {

                //Cancel existing payment if exists

                switch ($checkPayment){
                    case 'RealPay' :
                        $clientNumber = $this->cancelRealpayContract($policyId);

                        if($clientNumber != null){
                            $can = RealpayCancelRequests::where('policy_id',$policyId)->first();
                            if (isset($can)) {
                                $can->cancel_status = 1;
                                $can->save();
                            }
                            // else {
                            //     return response()->json(['error'=>'failed','message' => 'Failed to cancel contract','status' => '401']);
                            // }

                            $trans = Transaction::where('realPayTransaction_id',$policyId)
                                ->orderBy('id', 'DESC')
                                ->first();
                            if($trans != null){
                                $trans->status = "CANCELLED";
                                $trans->save();
                            }
                        }else{
                            $can = RealpayCancelRequests::where('policy_id',$policyId)->first();
                            if (isset($can)) {
                                $can->cancel_status = 2;
                                $can->save();
                            }
                            // else {
                            //     return response()->json(['error'=>'failed','message' => 'Failed to cancel contract','status' => '401']);
                            // }

                        }

                        break;
                    case 'VCS' :
                        break;
                    case 'DPO' :
                        break;
                    default:
                        break;
                }

                // if ($checkPayment == 'RealPay') {

                    $addPayment = $this->addRealpayPaymentForPolicyRenewal($request);

                // } elseif ($checkPayment == 'VCS') {

                //     # code...

                // } elseif ($checkPayment == 'DPO') {

                //     # code...

                // }
                // dd($addPayment);
                // return $addPayment;
                return $addPayment;

            } else {
                return response()->json(['error'=>'failed','message' => 'Payment not found','status' => '401']);
            }

        }catch(\Exception $ex){
            // dd($ex);
            return response()->json(['success'=>1,'payment'=>null,'status' => '401','message' => 'Failed to cancel contract']);
        }
    }

    public function addRealpayPaymentForPolicyRenewal($request){

        try{
            $policy = Policy::where('id',$request->policyID)->orderBy('id','DESC')->first();
            $customer = Customer::where('id', $policy->customer_id)->with('profile')->first();
            $profile = CustomerProfile::where('customer_id',$customer->id)->first();
            $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
            if (!isset($customerBanking)) {
                $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();
            }
            $renewal = PolicyRenewal::where('policyNumber',$policy->policyNumber)->orderBy('id', 'desc')->first();
            $data = PolicyDiscountSurcharge::where('policy_id',$policy->id)->orderBy('id','desc')->first('new_value');
            if (isset($data)) {
                $request['premium'] = $data->new_value;
            } elseif (isset($renewal) && isset($renewal->new_premium)) {
                $request['premium'] = $renewal->new_premium;
            } else {
                $request['premium'] = $policy->premium;
            }

            if (!isset($request->first_collection_date)) {
                $request['first_collection_date'] = Carbon::now()->format('Y-m-d');
            }

            if (!isset($request->billingDay)) {
                $request['billingDay'] = Carbon::now()->format('Y-m-d');
            }

            if (!isset($request->first_premium)) {
                $request['first_premium'] = $request->premium;
            }

            if (!isset($request->billing_day)) {
                $request['billing_day'] = Carbon::now()->format('d');
            }

            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            if($profile->omang != null){
                $id = $profile->omang;
                $idType = 'I';
            }else{
                $id = $profile->passport;
                $idType = 'P';
            }
            $curl = curl_init();

            $checkClient = $this->checkClientExists($policy->id);

            if($checkClient == false){
                curl_setopt_array($curl, array(
                    CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/clients/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS =>"{\r\n  \"ClientPostRequest\": [\r\n    {\r\n
           \"ClientNumber\": \"$policy->policyNumber\",\r\n
           \"ClientName\": \"$customer->firstName $customer->lastName\",\r\n
           \"IDType\": \"$idType\",\r\n
           \"IDNumber\": \"$id\",\r\n
           \"CellphoneNumber\": \"$customer->cellphone\",\r\n
           \"EMail\": \"$customer->email\",\r\n
           \"BankCode\": \"$request->BankName\",\r\n
           \"BranchCode\": \"$request->BranchCode\",\r\n
           \"AccountType\": \"$request->accountType\",\r\n
           \"AccountNumber\": \"$request->accountNumber\",\r\n
           \"AccountHolderName\": \"$customer->firstName $customer->lastName\",\r\n
           \"EmployeeGroupCode\": \"OT\",\r\n
           }\r\n
           ]\r\n
           }",
                    CURLOPT_HTTPHEADER => array(
                      "Content-Type: application/json",
                        "Accept: application/json",
                        "Authorization: ".$token
                    ),
                ));

                $response = curl_exec($curl);
                $data = json_decode($response,true);
                curl_close($curl);
            }else{

                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/clients/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "PUT",
                    CURLOPT_POSTFIELDS =>"{\r\n  \"ClientPutRequest\": [\r\n    {\r\n
                            \"ClientNumber\": \"$policy->policyNumber\",\r\n
                           \"ClientName\": \"$customer->firstName $customer->lastName\",\r\n
                           \"IDType\": \"$idType\",\r\n
                           \"IDNumber\": \"$id\",\r\n
                           \"CellphoneNumber\": \"$customer->cellphone\",\r\n
                           \"EMail\": \"$customer->email\",\r\n
                           \"BankCode\": \"$request->BankName\",\r\n
                           \"BranchCode\": \"$request->BranchCode\",\r\n
                           \"AccountType\": \"$request->accountType\",\r\n
                           \"AccountNumber\": \"$request->accountNumber\",\r\n
                           \"AccountHolderName\": \"$customer->firstName $customer->lastName\",\r\n
                           \"EmployeeGroupCode\": \"OT\",\r\n
                           }\r\n
                           ]\r\n
                           }",
                    CURLOPT_HTTPHEADER => array(
                        "Content-Type: application/json",
                        "Accept: application/json",
                        "Authorization: ".$token
                    ),
                ));

                $response = curl_exec($curl);
                $data = json_decode($response,true);

                curl_close($curl);
            }
            // dd($data);

            if (isset($data) && isset($data['ClientPutResponse'][0]['Failed']) && isset($data['ClientPutResponse'][0]['Failed'][0]['Failures'][0]['FailureDescription'])) {
                return response()->json(['status' => '401', 'message' => $data['ClientPutResponse'][0]['Failed'][0]['Failures'][0]['FailureDescription']], 401);
            }

            $updateClient = RealpayPaymentRequest::where('policy_id', $policy->id)->first();

            if (isset($updateClient)) {
                if(!empty($data['ClientPutResponse'][0]['Successful']) && empty($data['ClientPutResponse'][0]['Failed'])){
                    $updateClient->clientNumber = $policy->policyNumber;
                    $updateClient->client_response_sequence = $data['APIResponse']['CallSequence'];
                    $updateClient->response = 1;
                    $updateClient->clientCreated = 1;
                    $updateClient->status = 1;
                    $updateClient->save();
                }
            } else {
                if(!empty($data['ClientPostResponse'][0]['Successful']) && empty($data['ClientPostResponse'][0]['Failed'])){

                    $payRequest = new RealpayPaymentRequest();
                    $payRequest->policy_id = $policy->id;
                    $payRequest->first_premium = $policy->leftout_premium;
                    $payRequest->premium = $policy->premium;
                    $payRequest->billing_day = $policy->billing_day;
                    $payRequest->billing_date = $policy->billingStartDate;
                    $payRequest->first_premium_contract = null;
                    $payRequest->contract = null;
                    $payRequest->status = 1;
                    $payRequest->response = 1;
                    $payRequest->frequency = $policy->premium_freq;
                    $payRequest->clientCreated = 1;
                    $payRequest->contractCreated = 0;
                    $payRequest->save();
                }
            }

            // dd($customerBanking,$request->policyID);
            //if(!empty($data['ClientPostResponse'][0]['Successful']) && empty($data['ClientPostResponse'][0]['Failed'])){
            if (isset($customerBanking)) {
                $customerBanking->bankName = $request->BankName;
                $customerBanking->branchCode = $request->BranchCode;
                $customerBanking->accountType = $request->accountType;
                $customerBanking->accountNumber = $request->accountNumber;
                $customerBanking->billing = "RealPay";
                $customerBanking->billing_day = $request->billing_day;
                $customerBanking->billingStartDate = $this->setDate($request->billing_day);
                $customerBanking->save();
            }

            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $policy = Policy::where('id',$policy->id)->first();
            // dd($policy,$token,$policy->id);
            if($policy->product_id != 3)
                $policy->first_premium_wvat = 0;

            $firstBillingDate = $request->first_collection_date;
            $firstCollectionAmount = $request->first_premium;
            $numberOfInstallments = '12';
            $frequency = 'MNTH';
            $premium = $request->premium;


            if($request->frequency != null){

                $data = [
                    'trans_type' => $request->trans_type,
                    'frequency' => $request->frequency,
                    'first_premium' => $request->first_premium,
                    'premium' => $request->premium,
                    'id' => $policy->id,
                    'quoteNumber' => $policy->quoteNumber,
                    'reinstate_type' => $request->reinstate_type,
                    'rerate_premium' => $request->rerate_premium
                ];

                if ($request->request_type == 'Convert Policy Frequency') {
                    $premium = $request->newConvertedPremium;
                    $firstCollectionAmount = $request->newConvertedPremium;

                    if($request->frequency == 2){
                        $numberOfInstallments = '3';
                    }
                    elseif($request->frequency == 3){
                        $frequency = 'YEAR';
                        $numberOfInstallments = '1';
                    }
                    elseif($request->frequency == 1){
                        $numberOfInstallments = '12';
                    }
                } else {
                    $getPremium = $this->calculatePremiumForPolicy($data);
                    // dd($getPremium);

                    if (isset($getPremium)) {

                        $premium = $getPremium['premium'];
                        $firstCollectionAmount = $getPremium['first_premium'];

                        if($request->frequency == 2){
                            $numberOfInstallments = '3';
                            if (isset($request->reinstate_type) && $request->reinstate_type == 'Reinstate_arrears') {
                                $numberOfInstallments = '4';
                            }
                        }
                        elseif($request->frequency == 3){
                            $frequency = 'YEAR';
                            $numberOfInstallments = '1';
                            if (isset($request->reinstate_type) && $request->reinstate_type == 'Reinstate_arrears') {
                                $numberOfInstallments = '2';
                            }
                        }
                        elseif($request->frequency == 1){
                            $numberOfInstallments = '12';
                            if (isset($request->reinstate_type) && $request->reinstate_type == 'Reinstate_arrears') {
                                $numberOfInstallments = '13';
                            }
                        }
                        else{
                            if($policy->quoteNumber) {
                                $quote = MotorComprehensiveQuotes::where('quoteNumber', $policy->quoteNumber)->first(array('premiumMonthly'));
                                $premium = $quote->premiumMonthly;
                                $numberOfInstallments = '12';
                                $policy->premium_freq = 1;
                                $policy->save();

                                if (isset($request->reinstate_type) && $request->reinstate_type == 'Reinstate_arrears') {
                                    $numberOfInstallments = '13';
                                }
                            }else{
                                return null;
                            }
                        }
                    }
                }

            }


            // dd($premium,$request->all());
            // dd($request->all(),$request->frequency,$numberOfInstallments);
            $billing_day = '';
            if ($request->billingDay != NULL) {
                $billing_day = \Carbon\Carbon::createFromFormat('Y-m-d', $request->billingDay)->format('d');
            }

            if($billing_day == 31 || $billing_day == 30 || $billing_day == 29){
                $billing_day = 99;
            }

            $firstcollDate = isset($request->first_collection_date) ? $request->first_collection_date : $request->billingDay;
            $contractNumber = RealpayClientContracts::getContractNumber($policy->id);

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/contracts/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS =>"{\r\n
            \"ContractPostRequest\": [\r\n
                {\r\n
                      \"ClientNumber\": \"$policy->policyNumber\",\r\n
                      \"ContractNumber\": \"$contractNumber\",\r\n
                      \"FrequencyCode\": \"$frequency\",\r\n
                      \"CollectionDay\": \"$billing_day\",\r\n
                      \"TrackingCode\": \"44\",\r\n
                      \"FirstCollectionDate\": \"$firstcollDate\",\r\n
                      \"FirstCollectionAmount\": \"$firstCollectionAmount\",\r\n
                      \"InstalmentStartDate\": \"$request->billingDay\",\r\n
                      \"InstalmentAmount\": $premium,\r\n
                      \"NumberOfInstalments\": \"$numberOfInstallments\",\r\n
                      \"CTCPercentage\": 1\r\n
                      }\r\n
                 ]\r\n}",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: " . $token
                ),
            ));
            $response = curl_exec($curl);
            $data = json_decode($response, true);

            curl_close($curl);

            $update = RealpayPaymentRequest::where('policy_id', $policy->id)->first();
            $installmentStatus = null;

            if (sizeof($data['ContractPostResponse'][0]['Successful']) > 0  && isset($data['ContractPostResponse'][0]['Successful'][0]['ContractInstalments'])) {
                if ($data['ContractPostResponse'][0]['Successful'][0]['ContractInstalments'][0]['InstalmentStatus'] == 'S') {
                    $installmentStatus =  'Successfull';
                }
            }
            if (sizeof($data['ContractPostResponse'][0]['Successful']) > 0 && sizeof($data['ContractPostResponse'][0]['Failed']) == 0) {
                if (isset($update)) {
                    $update->contract = $contractNumber;
                    $update->contract_response_sequence = $data['APIResponse']['CallSequence'];
                    $update->status = 1;
                    $update->contractCreated = 1;
                    $update->save();
                }
                if(sizeof($data['ContractPostResponse'][0]['Successful'][0]['ContractInstalments']) > 0){
                    $contract = $this->storeContractDetails($data['ContractPostResponse'][0]['Successful'][0]);
                    $installments = $this->storeInstallments($data['ContractPostResponse'][0]['Successful'][0]);
                }

                $logData = [
                    'policy_id'=>$policy->id,
                    'client_number'=>$policy->policyNumber,
                    'contract_number'=>$contractNumber,
                    //'rate_id'=>$data['rate_id'],
                    'status'=>1,
                ];

                $addLog = RealpayClientContracts::addLog($logData);

                if($contractNumber != null) {
                    $Table = (new RealpayClientContracts())->getTable();
                    DB::table($Table)->where('client_number', $policy->policyNumber)
                        ->where('contract_number', '!=',$contractNumber)
                        ->update(array('status' => 0));
                }

                return response()->json(['status' => '200', 'message' => 'Client added successfully on realpay', "instalmentStatus" => $installmentStatus, 'premium' => $premium, 'first_premium' => $firstCollectionAmount], 200);

            } else {

                if($data['ContractPostResponse'][0]['Failed'][0]['Failures'][0]['FailureCode'] == 'TAK1'){
                    $logData = [
                        'policy_id'=>$policy->id,
                        'client_number'=>$policy->policyNumber,
                        'contract_number'=>$contractNumber,
                        'status'=>1,
                    ];

                    $addLog = RealpayClientContracts::addLog($logData);

                    if($contractNumber != null) {
                        $Table = (new RealpayClientContracts())->getTable();
                        DB::table($Table)->where('client_number', $policy->policyNumber)
                            ->where('contract_number', '!=',$contractNumber)
                            ->update(array('status' => 0));
                    }
                }

                return response()->json(['status' => '401', 'message' => $data['ContractPostResponse'][0]['Failed'][0]['Failures'][0]['FailureDescription']], 401);

            }

        }catch(\Exception $ex){
            return response()->json(['success'=>'false','message' => $ex->getMessage().' '.$ex->getLine()],401);
        }
    }

    public function getCustomerCancelContract()
    {
        return view('admin.Realpay.getCustomerCancelContract');
    }
    public function cancelOldcreateNewContractView(Request $request){
        try{
            $policy = Policy::where('policyNumber',$request->policyNumber)->first(array('id','customer_id','policyNumber'));
            $customer = Customer::join('customer_profile', 'customer_profile.customer_id', 'customer.id')
                ->where('customer.id',$policy->customer_id)
                ->first();

            $banks = Banks::get(array('id','bank_number','bank_name'));
            $branches = BankBranches::get(array('branch_id','name'));

            $id = '-';
            if($customer->omang && $customer->passport == null){
                $id = 'Omang: '.$customer->omang.' - '.'Passport: N/A';
            }else{
                $id = $id = 'Omang: N/A'.' - '.'Passport: '.$customer->passport;
            }

            return view('admin.Realpay.cancelOldCreateNewContract',compact('customer','banks','branches','policy','id'));
        }catch(\Exception $e){
            return \Illuminate\Support\Facades\Redirect::back()->with('error',$e->getMessage());
        }
    }

    public function cancelOldcreateNewContract(Request $request){
        try {
            $contract = $this->logRealpayPayment($request);
            if ($contract->getData()->status == 200) {
                // return redirect('admin/realpay/getCustomer')->with('success', 'Successfully created new contract');

                // return Redirect::route('admin.Realpay.getCustomerCancelContract')->with('success', 'Successfully created new contract');
                return Redirect::back()->with('success', 'Successfully created new contract');
            } else {
                // return redirect('admin/realpay/getCustomer')->with('error', 'Failed to create new contract '.$contract->getData()->message);

                // return Redirect::route('admin.Realpay.getCustomerCancelContract')->with('error', 'Failed to create new contract '.$contract->getData()->message);
                return Redirect::back()->with('error', 'Failed to create new contract '.$contract->getData()->message);
            }
        } catch (\Exception $ex) {
            // return redirect('admin/realpay/getCustomer')->with('error',$ex->getMessage());

            // return Redirect::route('admin.Realpay.getCustomerCancelContract')->with('error',$ex->getMessage());
            return \Illuminate\Support\Facades\Redirect::back()->with('error',$ex->getMessage());
        }
    }


    public function changePreminumFrequencyPolicy(Request $request){
        try {
            $contract = $this->logRealpayPayment($request);
            // dd($contract);
            if ($contract->getData()->status == 200) {
                return Redirect::back()->with('success', 'Successfully created new contract');
            } else {
                return Redirect::back()->with('error', $contract->getData()->message);
            }

        } catch (\Exception $ex) {
            return \Illuminate\Support\Facades\Redirect::back()->with('error',$ex->getMessage());
        }
    }


    public function getClientContractList($id )
    {
        try {

            $client = RealpayPaymentRequest::where('policy_id', $id)->first(array('clientNumber'));
            if (isset($client)) {
            //     $fetchToken = $this->clientAuth();
            //     // dd($fetchToken);
            //     if($fetchToken['token_type'] && $fetchToken['access_token'])
            //         $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            //     else
            //         return null;

            //     $curl = curl_init();

            //     curl_setopt_array($curl, array(
            //         CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/clients/" . env('REALPAY_PRODUCT') . "?ClientNumber=" . $client->clientNumber . "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
            //         CURLOPT_RETURNTRANSFER => true,
            //         CURLOPT_ENCODING => "",
            //         CURLOPT_MAXREDIRS => 10,
            //         CURLOPT_TIMEOUT => 0,
            //         CURLOPT_FOLLOWLOCATION => true,
            //         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            //         CURLOPT_CUSTOMREQUEST => "GET",
            //         CURLOPT_HTTPHEADER => array(
            //             "Content-Type: application/json",
            //             "Accept: application/json",
            //             "Authorization: ".$token
            //         ),
            //     ));

            //     $response = curl_exec($curl);
            //     $clientData = json_decode($response,true);
            //     curl_close($curl);
            //    //dd($clientData);
            //     if (isset($clientData['ClientGetResponse']) && $clientData['ClientGetResponse'] != NULL) {
            //         $curl = curl_init();

            //         curl_setopt_array($curl, array(
            //             CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/contracts/" . env('REALPAY_PRODUCT') . "?ClientNumber=" . $client->clientNumber . "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
            //             CURLOPT_RETURNTRANSFER => true,
            //             CURLOPT_ENCODING => "",
            //             CURLOPT_MAXREDIRS => 10,
            //             CURLOPT_TIMEOUT => 0,
            //             CURLOPT_FOLLOWLOCATION => true,
            //             CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            //             CURLOPT_CUSTOMREQUEST => "GET",
            //             CURLOPT_HTTPHEADER => array(
            //                 "Content-Type: application/json",
            //                 "Accept: application/json",
            //                 "Authorization: ".$token
            //             ),
            //         ));

            //         $response = curl_exec($curl);
            //         $contractData = json_decode($response,true);
            //         curl_close($curl);
            //     //    dd($contractData);

                    $realpayContracts = RealpayClientContracts::where('policy_id',$id)->get();

                    return DataTables::of($realpayContracts)

                        ->editColumn('status', function ($realpayContracts) {
                            $realpayInstallments = RealpayContractInstallments::where('contractNumber',$realpayContracts->contract_number)->get();
                            foreach ($realpayInstallments as $key => $contractInstl) {
                                $status = '';
                                if ($contractInstl['InstalmentStatus'] == 'I') {
                                    $status =  '<span class="kt-font-bold kt-font-danger">Cancelled</span>';
                                } else {
                                    $status =  '<span class="kt-font-bold kt-font-info">Active</span>';
                                }
                            }
                            return $status;
                        })

                        // ->editColumn('first_collection_amount', function ($contractData) {
                        //     $first_collection_amount = '';
                        //     if (isset($contractData['FirstCollectionAmount'])) {
                        //         $first_collection_amount =  $contractData['FirstCollectionAmount'];
                        //     } else {
                        //         $first_collection_amount =  'N/A';
                        //     }
                        //     return $first_collection_amount;
                        // })

                        // ->editColumn('first_collection_date', function ($contractData) {
                        //     $first_collection_date = '';
                        //     if (isset($contractData['FirstCollectionDate'])) {
                        //         $first_collection_date =  $contractData['FirstCollectionDate'];
                        //     } else {
                        //         $first_collection_date =  'N/A';
                        //     }
                        //     return $first_collection_date;
                        // })

                        ->rawColumns(['status'])
                        ->make(true);
                // }
            }

        } catch (\Exception $ex) {
            return $ex;
        }
    }


    public function getRealpayClientContractDetails($policyId)
    {
        try {
            $client = RealpayPaymentRequest::where('policy_id', $policyId)->first(array('clientNumber'));
            if (isset($client)) {
                $fetchToken = $this->clientAuth();
                if($fetchToken['token_type'] && $fetchToken['access_token'])
                    $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
                else
                    return null;

                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/clients/" . env('REALPAY_PRODUCT') . "?ClientNumber=" . $client->clientNumber . "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
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
                $clientData = json_decode($response,true);
                curl_close($curl);
                // dd($clientData);
                if (isset($clientData['ClientGetResponse']) && $clientData['ClientGetResponse'] != NULL) {
                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/contracts/" . env('REALPAY_PRODUCT') . "?ClientNumber=" . $client->clientNumber . "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
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
                    $contractData = json_decode($response,true);
                    curl_close($curl);
                    // dd($contractData);
                    return $contractData;
                } else {
                    return null;
                }
            }
        } catch (\Exception $ex) {
            return $ex;
        }
    }


    public function getCalculatedPreminumForRenewPolicy(Request $request)
    {
        try {
            if (isset($request->premium) && isset($request->paymentAmount) && isset($request->paymentFreq)) {
                $new_premium = NULL;
                $amount = NULL;
                if ($request->paymentFreq == 1) {
                    $policy = new PolicyController();
                    $new_premium = $policy->getMonthlyPrem(3,$request->premium);
                    $new_premium = round($new_premium,2);
                    // dd($new_premium);
                    if ($new_premium != $request->paymentAmount) {
                        $remainingAmt = $new_premium - $request->paymentAmount;
                        $amount = $remainingAmt + $new_premium;
                        $amount = round($amount,2);
                    }
                    return response()->json(['success'=>'success','message' => 'Premium fetched successfully','status' => '200', 'new_premium' => $new_premium, 'amount' => $amount]);

                } elseif ($request->paymentFreq == 2) {
                    $new_premium = $request->premium / 3;
                    $new_premium = round($new_premium,2);
                    return response()->json(['success'=>'success','message' => 'Premium fetched successfully','status' => '200', 'new_premium' => $new_premium, 'amount' => $amount]);

                } elseif ($request->paymentFreq == 3) {
                    $new_premium = $request->premium;
                    return response()->json(['success'=>'success','message' => 'Premium fetched successfully','status' => '200', 'new_premium' => $new_premium, 'amount' => $amount]);

                } else {
                    return response()->json(['error'=>'failed','message' => 'Payment frequency not found','status' => '401']);
                }
            } else {
                return response()->json(['error'=>'failed','message' => 'Data not found','status' => '401']);
            }
        } catch (Exception $ex) {
            return response()->json(['error'=>'failed','message' => $ex->getMessage(),'status' => '401']);
        }
    }


    public function getPreminumForRealpayRenewPolicy(Request $request)
    {
        try {
            if (isset($request->premium) && isset($request->paymentFreq)) {
                $new_premium = NULL;
                if ($request->paymentFreq == 1) {
                    $policy = new PolicyController();
                    $new_premium = $policy->getMonthlyPrem(3,$request->premium);
                    $new_premium = round($new_premium,2);

                    return response()->json(['success'=>'success','message' => 'Premium fetched successfully','status' => '200', 'new_premium' => $new_premium]);

                } elseif ($request->paymentFreq == 2) {
                    $new_premium = $request->premium / 3;
                    $new_premium = round($new_premium,2);
                    return response()->json(['success'=>'success','message' => 'Premium fetched successfully','status' => '200', 'new_premium' => $new_premium]);

                } elseif ($request->paymentFreq == 3) {
                    $new_premium = $request->premium;
                    return response()->json(['success'=>'success','message' => 'Premium fetched successfully','status' => '200', 'new_premium' => $new_premium]);

                } else {
                    return response()->json(['error'=>'failed','message' => 'Payment frequency not found','status' => '401']);
                }
            } else {
                return response()->json(['error'=>'failed','message' => 'Data not found','status' => '401']);
            }
        } catch (Exception $ex) {
            return response()->json(['error'=>'failed','message' => $ex->getMessage(),'status' => '401']);
        }
    }


    public function getCalculatedPreminumForRenewPolicyAPI(Request $request)
    {
        try {
            $data = PolicyDiscountSurcharge::where('policy_id',$request->policy_id)->orderBy('id','desc')->first('new_value');
            if (isset($data)) {
                $request['new_value'] = $data->new_value;
            } else {
                $policy = Policy::where('id',$request->policy_id)->first();
                if (isset($policy)) {
                    $request['new_value'] = $policy->premium;
                } else {
                    $request['new_value'] = NULL;
                }
            }

            // if (isset($data)) {
                $new_premium = NULL;
                // $amount = NULL;
                if ($request->paymentFreq == 1) {
                    $policy = new PolicyController();
                    $new_premium = $policy->getMonthlyPrem(3,$request->new_value);
                    $new_premium = round($new_premium,2);
                    // dd($new_premium);
                    // if ($new_premium != $request->paymentAmount) {
                    //     $remainingAmt = $new_premium - $request->paymentAmount;
                    //     $amount = $remainingAmt + $new_premium;
                    //     $amount = round($amount,2);
                    // }
                    return response()->json(['success'=>'success','message' => 'Premium fetched successfully','status' => '200', 'new_premium' => $new_premium]);

                } elseif ($request->paymentFreq == 2) {
                    $new_premium = $request->new_value / 3;
                    $new_premium = round($new_premium,2);
                    return response()->json(['success'=>'success','message' => 'Premium fetched successfully','status' => '200', 'new_premium' => $new_premium]);

                } elseif ($request->paymentFreq == 3) {
                    $new_premium = $request->new_value;
                    return response()->json(['success'=>'success','message' => 'Premium fetched successfully','status' => '200', 'new_premium' => $new_premium]);

                } else {
                    return response()->json(['error'=>'failed','message' => 'Payment frequency not found','status' => '401']);
                }
            // } else {
            //     return response()->json(['error'=>'failed','message' => 'Data not found','status' => '401']);
            // }
        } catch (Exception $ex) {
            return response()->json(['error'=>'failed','message' => $ex->getMessage(),'status' => '401']);
        }
    }


    public function updateClientNumber(Request $request)
    {
        // try {
            if (isset($request->clientNumber) && isset($request->contractNumber)) {
                $fetchToken = $this->clientAuth();
                // dd($fetchToken);
                if($fetchToken['token_type'] && $fetchToken['access_token'])
                    $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
                else
                    return null;

                // $curl = curl_init();

                // curl_setopt_array($curl, array(
                //     CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/clients/" . env('REALPAY_PRODUCT') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
                //     CURLOPT_RETURNTRANSFER => true,
                //     CURLOPT_ENCODING => "",
                //     CURLOPT_MAXREDIRS => 10,
                //     CURLOPT_TIMEOUT => 0,
                //     CURLOPT_FOLLOWLOCATION => true,
                //     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                //     CURLOPT_CUSTOMREQUEST => "GET",
                //     CURLOPT_HTTPHEADER => array(
                //         "Content-Type: application/json",
                //         "Accept: application/json",
                //         "Authorization: ".$token
                //     ),
                // ));

                // $response = curl_exec($curl);
                // $clientData = json_decode($response,true);
                // curl_close($curl);
                // // dd($clientData);
                // if (isset($clientData['ClientGetResponse']) && $clientData['ClientGetResponse'] != NULL) {
                //     $curl = curl_init();

                //     curl_setopt_array($curl, array(
                //         CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/contracts/" . env('REALPAY_PRODUCT') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
                //         CURLOPT_RETURNTRANSFER => true,
                //         CURLOPT_ENCODING => "",
                //         CURLOPT_MAXREDIRS => 10,
                //         CURLOPT_TIMEOUT => 0,
                //         CURLOPT_FOLLOWLOCATION => true,
                //         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                //         CURLOPT_CUSTOMREQUEST => "GET",
                //         CURLOPT_HTTPHEADER => array(
                //             "Content-Type: application/json",
                //             "Accept: application/json",
                //             "Authorization: ".$token
                //         ),
                //     ));

                //     $response = curl_exec($curl);
                //     $contractData = json_decode($response,true);
                    // curl_close($curl);
                    // // dd($clientData,$contractData);

                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/instalments/".env('REALPAY_PRODUCT')."?ClientNumber=".$request->clientNumber."&ContractNumber=".$request->contractNumber. "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'GET',
                        CURLOPT_POSTFIELDS => array(),
                        CURLOPT_HTTPHEADER => array(
                            "Content-Type: application/json",
                            "Accept: application/json",
                            "Authorization: ".$token
                        ),
                    ));

                    $response = curl_exec($curl);
                    $data = json_decode($response,true);
                    curl_close($curl);

                if (isset($data['InstalmentGetResponse']) && $data['InstalmentGetResponse'] != NULL) {

                    $policy = Policy::where('id',$request->policy_id)->first();
                    if (isset($policy)) {
                        if (isset($data['InstalmentGetResponse']) && $data['InstalmentGetResponse'] != NULL) {
                            $client = RealpayPaymentRequest::where('policy_id', $request->policy_id)->first();
                            if (isset($client)) {
                                $client->clientNumber = $data['InstalmentGetResponse'][0]['ClientNumber'];
                                $client->save();
                            } else {
                                $newClient = new RealpayPaymentRequest();
                                $newClient->clientNumber = $data['InstalmentGetResponse'][0]['ClientNumber'];
                                $newClient->contract = null;
                                $newClient->policy_id = $policy->id;
                                $newClient->first_premium = $policy->leftout_premium;
                                $newClient->premium = $policy->premium;
                                $newClient->billing_day = $policy->billing_day;
                                $newClient->billing_date = $policy->billingStartDate;
                                $newClient->first_premium_contract = null;
                                $newClient->status = 1;
                                $newClient->response = 1;
                                $newClient->frequency = $policy->premium_freq;
                                $newClient->clientCreated = 1;
                                $newClient->contractCreated = 0;
                                $newClient->save();
                            }
                        } else {
                            return Redirect::back()->with('error', 'Client not found');
                        }

                        // foreach ($data['InstalmentGetResponse'] as $key => $contract) {
                            $updateClient = RealpayPaymentRequest::where('policy_id', $request->policy_id)->first();
                            if (isset($updateClient)) {
                                $updateClient->contract = $data['InstalmentGetResponse'][0]['ContractNumber'];
                                $updateClient->save();
                            } else {
                                return Redirect::back()->with('error', 'Failed to update Client number');
                            }

                            $clientContracts = RealpayClientContracts::where('contract_number', $data['InstalmentGetResponse'][0]['ContractNumber'])->first();
                            if (!isset($clientContracts)) {
                                $logData = [
                                    'policy_id'=>$policy->id,
                                    'client_number'=>$policy->policyNumber,
                                    'contract_number'=> $data['InstalmentGetResponse'][0]['ContractNumber'],
                                    'status'=>1,
                                ];

                                $addLog = RealpayClientContracts::addLog($logData);
                            }

                            // $realpayContractDetails = RealpayContractDetails::where('ClientNumber',$contract['ClientNumber'])->where('ContractNumber',$contract['ContractNumber'])->first();
                            // if (!isset($realpayContractDetails)) {
                            //     $contractDetails = $this->storeContractDetails($contract);
                            // }

                            $realpayInstallment = RealpayContractInstallments::where('clientNumber', $data['InstalmentGetResponse'][0]['ClientNumber'])->where('contractNumber', $data['InstalmentGetResponse'][0]['ContractNumber'])->first();
                            if (!isset($realpayInstallment)) {
                                // $installments = $this->storeInstallments($contract);
                                foreach($data['InstalmentGetResponse'] as $d){
                                    $new = new RealpayContractInstallments();
                                    $new->clientNumber = $d['ClientNumber'];
                                    $new->contractNumber = $d['ContractNumber'];
                                    $new->InstalmentReferenceNumber = $d['InstalmentReferenceNumber'];
                                    $new->InstalmentSequence = $d['InstalmentSequence'];
                                    $new->CTCAmount = $d['CTCAmount'];
                                    $new->InstalmentActionDate = $d['InstalmentActionDate'];
                                    $new->TrackingCode = $d['TrackingCode'];
                                    $new->InstalmentAmount = $d['InstalmentAmount'];
                                    $new->InstalmentStatus = $d['InstalmentStatus'];
                                    $new->save();

                                }
                            }

                        // }

                        return Redirect::back()->with('success', 'Successfully updated client number');
                    } else {
                        return Redirect::back()->with('error', 'Policy not found');
                    }
                } else {
                    return Redirect::back()->with('error', 'Client number and Contract number not found');
                }
            } else {
                return Redirect::back()->with('error', 'Client number and Contract number not found');
            }

        // } catch (\Exception $ex) {
        //     return Redirect::back()->with('error', $ex->getMessage());
        // }
    }


    public function getReinstatePremium($data)
    {
        try {
            if($data['frequency'] != null){

                if($data['frequency'] == 2){
                    // $numberOfInstallments = '3';
                    $premium = $data['premium'] / 3;
                    $premium = round($premium,2);

                    $first_premium = $premium;

                    if (isset($data['reinstate_type']) && $data['reinstate_type'] == 'reinstate_with_arrears') {
                        $first_premium = $data['first_premium'];
                    }
                }
                elseif($data['frequency'] == 3){
                    // $frequency = 'YEAR';
                    // $numberOfInstallments = '1';

                    $premium = $data['premium'];
                    $first_premium = $premium;

                    if (isset($data['reinstate_type']) && $data['reinstate_type'] == 'reinstate_with_arrears') {
                        $first_premium = $data['first_premium'];
                    }
                }
                elseif($data['frequency'] == 1){
                    // $numberOfInstallments = '12';

                    $policyCon = new PolicyController();
                    $premium = $policyCon->getMonthlyPrem(3,$data['premium']);
                    $premium = round($premium,2);
                    $first_premium = $premium;

                    if (isset($data['reinstate_type']) && $data['reinstate_type'] == 'reinstate_with_arrears') {
                        $first_premium = $data['first_premium'];
                    }
                }

                $premiumData = [
                    'premium' => $premium,
                    'first_premium' => $first_premium
                ];

                return $premiumData;
            } else {
                $premiumData = null;

                return $premiumData;
            }

        } catch (\Exception $ex) {
            return response()->json(['error'=>'failed','message' => $ex->getMessage(),'status' => '401']);
        }
    }

    public function getReratePremium($data)
    {
        try {
            if($data['frequency'] != null){

                if($data['frequency'] == 2){
                    // $numberOfInstallments = '3';
                    $premium = $data['rerate_premium'];
                    $first_premium = $data['first_premium'];
                }
                elseif($data['frequency'] == 3){
                    // $frequency = 'YEAR';
                    // $numberOfInstallments = '1';
                    $premium = $data['rerate_premium'];
                    $first_premium = $premium;
                }
                elseif($data['frequency'] == 1){
                    // $numberOfInstallments = '12';
                    $premium = $data['rerate_premium'];
                    $first_premium = $data['first_premium'];
                }

                $premiumData = [
                    'premium' => $premium,
                    'first_premium' => $first_premium
                ];

                return $premiumData;
            } else {
                $premiumData = null;

                return $premiumData;
            }

        } catch (\Exception $ex) {
            return response()->json(['error'=>'failed','message' => $ex->getMessage(),'status' => '401']);
        }
    }

    public function getRenewPremium($data)
    {
        try {
            if($data['frequency'] != null){

                if($data['frequency'] == 2){
                    // $numberOfInstallments = '3';
                    $premium = $data['premium'] / 3;
                    $premium = round($premium,2);
                    $first_premium = $premium;
                }
                elseif($data['frequency'] == 3){
                    // $frequency = 'YEAR';
                    // $numberOfInstallments = '1';
                    $premium = $data['premium'];
                    $first_premium = $premium;
                }
                elseif($data['frequency'] == 1){
                    // $numberOfInstallments = '12';
                    $policyCon = new PolicyController();
                    $premium = $policyCon->getMonthlyPrem(3,$data['premium']);
                    $premium = round($premium,2);
                    $first_premium = $premium;
                }

                $premiumData = [
                    'premium' => $premium,
                    'first_premium' => $first_premium
                ];

                return $premiumData;
            } else {
                $premiumData = null;

                return $premiumData;
            }

        } catch (\Exception $ex) {
            return response()->json(['error'=>'failed','message' => $ex->getMessage(),'status' => '401']);
        }
    }

    public function getPayReinstatePremium($data)
    {
        try {

            if ($data['reinstate_type'] == 'reinstate_fresh') {
                $discSurData = PolicyDiscountSurcharge::where('policy_id',$data['id'])->orderBy('id','desc')->first('new_value');
                $reinstate_premium = MotorComprehensiveQuotes::where('quoteNumber',$data['quoteNumber'])->orderBy('id','desc')->first();

                if (isset($discSurData)) {

                    $total_premium = $discSurData->new_value;

                    if ($data['frequency'] == 1) {
                        $policyCon = new PolicyController();
                        $premium = $policyCon->getMonthlyPrem(3,$total_premium);
                        $premium = round($premium,2);
                        $first_premium = $premium;
                    } elseif ($data['frequency'] == 2) {
                        $premium = $total_premium / 3;
                        $premium = round($premium,2);
                        $first_premium = $premium;
                    } elseif ($data['frequency'] == 3) {
                        $premium = $total_premium;
                        $first_premium = $premium;
                    } else {
                        $premium = null;
                        $first_premium = null;
                    }

                } elseif (isset($premium)) {
                    if ($data['frequency'] == 1) {
                        $premium = $premium->premiumMonthly;
                        $first_premium = $premium;
                    } elseif ($data['frequency'] == 2) {
                        $premium = $premium->premium3Inst;
                        $first_premium = $premium;
                    } elseif ($data['frequency'] == 3) {
                        $premium = $premium->premiumAnnually;
                        $first_premium = $premium;
                    } else {
                        $premium = null;
                        $first_premium = null;
                    }
                } elseif (isset($data['premium'])) {
                    $premium = $data['premium'];
                    $first_premium = $premium;
                }  else {
                    $premium = null;
                }
            } else {
                $policyCon = new PolicyController();
                $balance = $policyCon->getPolicyBalance($data['id']);
                if($balance->getStatusCode() == 200){
                    $first_premium = $balance->getData()->balance;
                    $first_premium = number_format(abs($first_premium), 2, '.', '');
                }else{
                    $first_premium = null;
                }

                $policy = Policy::where('id',$data['id'])->orderBy('id','desc')->first();
                $premium = $policy->premium;
            }


            // if($data['frequency'] == 2){
            //     // $numberOfInstallments = '3';
            //     $premium = $reinstate_premium;
            //     $first_premium = $premium;
            // }
            // elseif($data['frequency'] == 3){
            //     // $frequency = 'YEAR';
            //     // $numberOfInstallments = '1';
            //     $premium = $reinstate_premium;
            //     $first_premium = $premium;

            // }
            // elseif($data['frequency'] == 1){
            //     // $numberOfInstallments = '12';
            //     $premium = $reinstate_premium;
            //     $first_premium = $premium;
            // } else {
            //     // $numberOfInstallments = '12';
            //     $premium = $reinstate_premium;
            //     $first_premium = $premium;
            // }


                $premiumData = [
                    'premium' => $premium,
                    'first_premium' => $first_premium
                ];

                return $premiumData;
            // } else {
                // $premiumData = null;

                // return $premiumData;
            // }

        } catch (\Exception $ex) {
            return response()->json(['error'=>'failed','message' => $ex->getMessage(),'status' => '401']);
        }
    }

    public function calculatePremiumForPolicy($data)
    {
        try {
            switch ($data['trans_type']) {
                case 'renew':
                    $premium = $this->getRenewPremium($data);
                    return $premium;
                    break;

                case 'pay_renew':
                    $premium = $this->getRenewPremium($data);
                    return $premium;
                    break;

                case 'reinstate':
                    $premium = $this->getReinstatePremium($data);
                    return $premium;
                    break;


                case 'pay_reinstate':
                    $premium = $this->getPayReinstatePremium($data);
                    return $premium;
                    break;


                case 'rerate':
                    $premium = $this->getReratePremium($data);
                    return $premium;
                    break;

                default:
                    $premium = $this->getRenewPremium($data);
                    return $premium;
                    break;
            }
        } catch (\Exception $ex) {
            return response()->json(['error'=>'failed','message' => $ex->getMessage(),'status' => '401']);
        }
    }

    //  public function renewPolicyRealpayPayment(Request $request){
    //     try {

    //         $policy = Policy::where('policyNumber',$request->policyNumber)->first();
    //         if (isset($policy)) {
    //             $clientNumber = $this->cancelRealpayContract($policy->id);
    //             if (isset($clientNumber)) {
    //                 $createContractPayment = $this->addClientContract($policy->id);
    //             } else {
    //                 return response()->json([
    //                     'success'=>false,
    //                     'message'=>'Failed to cancel realpay contract',
    //                 ],401);
    //             }
    //         } else {
    //             return response()->json([
    //                 'success'=>false,
    //                 'message'=>'Policy number not found',
    //             ],401);
    //         }
    //     } catch (\Exception $ex) {
    //         return $ex;
    //     }
    // }

//    protected function updateVATRealPay(){
//        try{
//            $banking = CustomerBanking::join('policies','policies.id','customer_banking.policy_id')
//                ->where('policies.product_id',3)
//                ->where('policies.status','!=',2)
//                ->where('customer_banking.billing','RealPay')
//                ->get(array(
//                        'policies.id as policy_id',
//                        'policies.policyNumber',
//                        'policies.premium',
//                    )
//                );
//
//
//            if($banking != null && count($banking) > 0){
//                foreach($banking as $detail){
//                    if($detail->policy_id != null && $detail->premium != null){
//                        $realpayContract = RealpayContractDetails::where('ContractNumber',$detail->policy_id)
//                            ->orderBy('id','DESC')
//                            ->first(array('ContractSequence','ClientNumber','ContractNumber'));
//                        if($realpayContract != null) {
//
//                            $fetchToken = $this->clientAuth();
//                            if ($fetchToken['token_type'] && $fetchToken['access_token'])
//                                $token = $fetchToken['token_type'] . ' ' . $fetchToken['access_token'];
//                            else
//                                return null;
//
//                            $curl = curl_init();
//
//                            curl_setopt_array($curl, array(
//                                CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/contracts/" . env('REALPAY_PRODUCT') . "?ClientNumber=" . $realpayContract->ClientNumber . "&ContractNumber=" . $realpayContract->ContractNumber . "&ContractSequence=" . $realpayContract->ContractSequence . "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
//                                CURLOPT_RETURNTRANSFER => true,
//                                CURLOPT_ENCODING => "",
//                                CURLOPT_MAXREDIRS => 10,
//                                CURLOPT_TIMEOUT => 0,
//                                CURLOPT_FOLLOWLOCATION => true,
//                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//                                CURLOPT_CUSTOMREQUEST => "GET",
//                                CURLOPT_HTTPHEADER => array(
//                                    "Content-Type: application/json",
//                                    "Accept: application/json",
//                                    "Authorization: " . $token
//                                ),
//                            ));
//
//                            $response = curl_exec($curl);
//                            $details = json_decode($response, true);
//                            if (array_key_exists("ContractGetResponse", $details)){
//                                $data = $details['ContractGetResponse'][0]['ContractInstalments'];
//                                foreach($data as $d){
//                                    if($d['InstalmentStatus'] == 'A'){
//
//                                        $old = $detail->premium;
//                                        $value12 = round(12/100*$old,2);
//                                        $core = round($old - $value12,2);
//                                        $value14 = round(14/100 * $core,2);
//
//                                        $newValue = round($core + $value14,2);
//
//                                        $diff = round($newValue - $old,2);
//
//                                        $var = round(($diff/$core)*100,2);
//
//                                        $curl = curl_init();
//
//                                        $contractSeq = $realpayContract->ContractSequence;
//                                        $clientNum = $realpayContract->ClientNumber;
//                                        $contractNumber = $realpayContract->ContractNumber;
//                                        $insSeq = $d['InstalmentSequence'];
//                                        $tracking = $d['TrackingCode'];
//                                        $amnt = $newValue; //Add your amount manipulation code here
//                                        $insStatus = $d['InstalmentStatus'];
//                                        $date = $d['InstalmentActionDate'];
//
//                                        curl_setopt_array($curl, array(
//                                            CURLOPT_URL => env('REALPAY_BASE_URL')."/maintain/instalments/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION'),
//                                            CURLOPT_RETURNTRANSFER => true,
//                                            CURLOPT_ENCODING => "",
//                                            CURLOPT_MAXREDIRS => 10,
//                                            CURLOPT_TIMEOUT => 0,
//                                            CURLOPT_FOLLOWLOCATION => true,
//                                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//                                            CURLOPT_CUSTOMREQUEST => "PUT",
//                                            CURLOPT_POSTFIELDS =>"{\r\n  \"InstalmentPutRequest\": [\r\n    {\r\n
//                            \"ClientNumber\": \"$clientNum\",\r\n
//                           \"ContractSequence\": $contractSeq,\r\n
//                           \"ContractNumber\": \"$contractNumber\",\r\n
//                           \"InstalmentSequence\": $insSeq,\r\n
//                           \"InstalmentActionDate\": \"$date\",\r\n
//                           \"TrackingCode\": \"$tracking\",\r\n
//                           \"InstalmentAmount\": $amnt,\r\n
//                           \"InstalmentStatus\": \"$insStatus\",\r\n
//                           \"DebitSequenceType\": \"OOFF\",\r\n
//                           }\r\n
//                           ]\r\n
//                           }",
//                                            CURLOPT_HTTPHEADER => array(
//                                                "Content-Type: application/json",
//                                                "Accept: application/json",
//                                                "Authorization: ".$token
//                                            ),
//                                        ));
//
//                                        $response = curl_exec($curl);
//                                        $data = json_decode($response,true);
//                                    }
//                                }
//                                DB::table('vat_change_log')->insert(
//                                    array(
//                                        'payment_method' => "RealPay",
//                                        'policyNumber' => $clientNum,
//                                        'old_value' => $detail->premium,
//                                        'new_value' => $amnt,
//                                        'variance' => $var,
//                                        'Message' => $clientNum,
//                                        'status' => "Success",
//                                    )
//                                );
//                            }
//
//                        }else{
//                            DB::table('vat_change_log')->insert(
//                                array(
//                                    'payment_method' => "RealPay",
//                                    'Message' => "RealPay contract not found",
//                                    'status' => "Failed",
//                                )
//                            );
//                        }
//                    }else{
//                        DB::table('vat_change_log')->insert(
//                            array(
//                                'payment_method' => "RealPay",
//                                'Message' => "Policy id not found. Data id = ".$detail->id,
//                                'status' => "Failed",
//                            )
//                        );
//                    }
//                }
//            }else{
//                DB::table('vat_change_log')->insert(
//                    array(
//                        'payment_method' => "RealPay",
//                        'Message' => "DATA not found",
//                        'status' => "Failed",
//                    )
//                );
//            }
//        }catch(\Exception $ex){
//            DB::table('vat_change_log')->insert(
//                array(
//                    'payment_method' => "RealPay",
//                    'Message' => $ex->getMessage(),
//                    'status' => "Failed",
//                )
//            );
//        }
//    }

    public function clientAuthForInstantProduct(){
        try{
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://realpaycollect.com:4448/rpp/rpws/oauth/token",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "grant_type=client_credentials",
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Basic VklfMTBENUFWUnRqdnlMZ3VraXZKQS4uOmlKOTdTa2g0aWtRdEZYMTNMZjdCRkEuLg==",
                    "Content-Type: application/x-www-form-urlencoded"
                ),
            ));

            $response = curl_exec($curl);
            curl_close($curl);
            return json_decode($response,true);
        }catch(\Exception $e){
            return response()->json(['Status' => 'Failed','Description'=>$e->getMessage()], 401);
        }
    }

    public function cancelRealpayContractsForInstProduct($policyId)
    {
        $banking = CustomerBanking::where('policy_id', $policyId)->first();
        $policy = Policy::where('id',$policyId)->first();

        $clientContracts = RealpayClientContracts::where('policy_id',$policyId)
            ->orderBy('id','desc')
            ->get();

        if ($clientContracts->isEmpty()) {
            $clientContracts = RealpayPaymentRequest::where('policy_id',$policyId)->get();
            if(!$clientContracts->isEmpty()){
                foreach ($clientContracts as $key => $contract) {
                    $contract->contract_number = $contract->contract;
                }
            }
        }

        if($clientContracts != null){
            $now = new DateTime();
            $now->format('Y-m-d');
            $Token = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
            $fetchToken = $Token->clientAuthForInstantProduct();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                $token = null;

            $successResult = 0;
            $contractFailed = array();

            if (isset($clientContracts)) {
                foreach ($clientContracts as $key => $contract) {
                    $url = "https://realpaycollect.com:4448/rpp/rpws/maintain/contracts/FNBNDOBW?ClientNumber=".$policy->policyNumber."&ContractNumber=".$contract->contract_number."&BeneficiaryUser=24936&Version=v1";
                    // dd(env('REALPAY_START_BASE_URL')."/maintain/contracts/".env('REALPAY_START_PRODUCT')."?ClientNumber=".$policy->policyNumber."&ContractNumber=".$contract->contract_number."&BeneficiaryUser=".env('REALPAY_START_MERCHANT')."&Version=".env('REALPAY_START_VERSION'));
                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "DELETE",
                        CURLOPT_POSTFIELDS =>"{\r\n  \"ClientPutRequest\": [\r\n    {\r\n      \"ClientNumber\": \"L00012\",\r\n      \"ContractNumber\": \"C1603\",\r\n      \"FrequencyCode\": \"MNTH\",\r\n      \"CollectionDay\": 25,\r\n      \"TrackingCode\": \"03\",\r\n      \"FirstCollectionDate\": \"YYYY-MM-DD HH24:MI\",\r\n      \"FirstCollectionAmount\": 123.45,\r\n      \"InstalmentStartDate\": \"YYYY-MM-DD HH24:MI\",\r\n      \"InstalmentAmount\": 123.45,\r\n      \"NumberOfInstalments\": 1,\r\n      \"CTCPercentage\": 1\r\n    }\r\n  ]\r\n}",
                        CURLOPT_HTTPHEADER => array(
                            "Content-Type: application/json",
                            "Accept: application/json",
                            "Authorization: ".$token
                        ),
                    ));

                    $response = curl_exec($curl);
                    $data = json_decode($response, true);
                    $success = sizeof($data['ContractDeleteResponse'][0]['Successful']) > 0;
                    curl_close($curl);

                    // dd($data,$policy->policyNumber,$contract->contract_number);
                    if($success == true){
                        // $update = $this->actionAfterCancellingContract($policyId);
                        $update = $this->actionAfterCancellingContract($contract->contract_number);
                        $can = RealpayCancelRequests::where('policy_id',$policy->id)->where('contract',$contract->contract_number)->first();
                        if (!isset($can)) {
                            $can = new RealpayCancelRequests();
                            $can->policy_id = $policy->id;
                            $can->leftout_premium_contract = null;
                            $can->contract = $contract->contract_number;
                            $can->cancel_status = 0;
                            $can->save();
                        }
                    }
                    else{
                        $url_new = "https://realpaycollect.com:4448/rpp/rpws/maintain/contracts/RTFNBBW?ClientNumber=".$policy->policyNumber."&ContractNumber=".$contract->contract_number."&BeneficiaryUser=24936&Version=v1";
                        // dd(env('REALPAY_START_BASE_URL')."/maintain/contracts/".env('REALPAY_START_PRODUCT')."?ClientNumber=".$policy->policyNumber."&ContractNumber=".$contract->contract_number."&BeneficiaryUser=".env('REALPAY_START_MERCHANT')."&Version=".env('REALPAY_START_VERSION'));
                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                            CURLOPT_URL => $url_new,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => "",
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => "DELETE",
                            CURLOPT_POSTFIELDS =>"{\r\n  \"ClientPutRequest\": [\r\n    {\r\n      \"ClientNumber\": \"L00012\",\r\n      \"ContractNumber\": \"C1603\",\r\n      \"FrequencyCode\": \"MNTH\",\r\n      \"CollectionDay\": 25,\r\n      \"TrackingCode\": \"03\",\r\n      \"FirstCollectionDate\": \"YYYY-MM-DD HH24:MI\",\r\n      \"FirstCollectionAmount\": 123.45,\r\n      \"InstalmentStartDate\": \"YYYY-MM-DD HH24:MI\",\r\n      \"InstalmentAmount\": 123.45,\r\n      \"NumberOfInstalments\": 1,\r\n      \"CTCPercentage\": 1\r\n    }\r\n  ]\r\n}",
                            CURLOPT_HTTPHEADER => array(
                                "Content-Type: application/json",
                                "Accept: application/json",
                                "Authorization: ".$token
                            ),
                        ));

                        $response = curl_exec($curl);
                        $data = json_decode($response, true);
                        $success = sizeof($data['ContractDeleteResponse'][0]['Successful']) > 0;
                        curl_close($curl);

                        // dd($data,$policy->policyNumber,$contract->contract_number);
                        if($success == true){
                            // $update = $this->actionAfterCancellingContract($policyId);
                            $update = $this->actionAfterCancellingContract($contract->contract_number);
                            $can = RealpayCancelRequests::where('policy_id',$policy->id)->where('contract',$contract->contract_number)->first();
                            if (!isset($can)) {
                                $can = new RealpayCancelRequests();
                                $can->policy_id = $policy->id;
                                $can->leftout_premium_contract = null;
                                $can->contract = $contract->contract_number;
                                $can->cancel_status = 0;
                                $can->save();
                            }
                        }
                        else{
                            $successResult ++;
                        }
                    }

                }
            }

            if($successResult == 0){
                return $policy->policyNumber;
            }
            else{
                return null;
            }
        }else{
            return $policy->policyNumber;
        }
    }

    public function checkClientExistsForMotorComp($policy_id)
    {
        $policy = Policy::where('id',$policy_id)->first(array('policyNumber'));
        $fetchToken = $this->clientAuthForMotorComp();
        if($fetchToken['token_type'] && $fetchToken['access_token'])
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => env('REALPAY_START_BASE_URL').'/maintain/clients/'.env('REALPAY_START_PRODUCT')."?ClientNumber=".$policy->policyNumber."&BeneficiaryUser=".env('REALPAY_START_MERCHANT')."&Version=".env('REALPAY_START_VERSION'),
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

        if(empty($data['ClientGetResponse'])){
            return false;
        }else{
            return true;
        }
    }

    public function clientAuthForMotorComp(){
        try{
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_START_BASE_URL')."/oauth/token",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "grant_type=client_credentials",
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Basic ".env('START_CLIENT_AUTH'),
                    "Content-Type: application/x-www-form-urlencoded"
                ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            return json_decode($response,true);
        }catch(\Exception $e){
            return response()->json(['Status' => 'Failed','Description'=>$e->getMessage()], 401);
        }
    }

    public function createClientForMotorComp($policy_id)
    {
        try{
            $policy = Policy::where('id',$policy_id)->first();
            $customer = Customer::where('id', $policy->customer_id)->with('profile')->first();
            $profile = CustomerProfile::where('customer_id',$customer->id)->first();

            $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

            if (!isset($customerBanking)) {
                $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
            }

            $fetchToken = $this->clientAuthForMotorComp();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            if($profile->omang != null){
                $id = $profile->omang;
                $idType = 'I';
            }else{
                $id = $profile->passport;
                $idType = 'P';
            }
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_START_BASE_URL')."/maintain/clients/".env('REALPAY_START_PRODUCT')."?BeneficiaryUser=".env('REALPAY_START_MERCHANT')."&Version=".env('REALPAY_START_VERSION'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS =>"{\r\n  \"ClientPostRequest\": [\r\n    {\r\n
           \"ClientNumber\": \"$policy->policyNumber\",\r\n
           \"ClientName\": \"$customer->firstName $customer->lastName\",\r\n
           \"IDType\": \"$idType\",\r\n
           \"IDNumber\": \"$id\",\r\n
           \"CellphoneNumber\": \"$customer->cellphone\",\r\n
           \"EMail\": \"$customer->email\",\r\n
           \"BankCode\": \"$customerBanking->bankName\",\r\n
           \"BranchCode\": \"$customerBanking->branchCode\",\r\n
           \"AccountType\": \"$customerBanking->accountType\",\r\n
           \"AccountNumber\": \"$customerBanking->accountNumber\",\r\n
           \"AccountHolderName\": \"$customer->firstName $customer->lastName\",\r\n
           \"EmployeeGroupCode\": \"OT\",\r\n
           }\r\n
           ]\r\n
           }",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: ".$token
                ),
            ));

            $response = curl_exec($curl);
            $data = json_decode($response,true);
            curl_close($curl);

            $update = RealpayPaymentRequest::where('policy_id',$policy_id)->first();
            if(!empty($data['ClientPostResponse'][0]['Successful']) && empty($data['ClientPostResponse'][0]['Failed'])){
                $update->clientNumber = $policy->policyNumber;
                $update->client_response_sequence = $data['APIResponse']['CallSequence'];
                $update->response = 1;
                $update->clientCreated = 1;
                $update->save();
                return $policy->policyNumber;

            }else{
                $update->status = 2;
                $update->clientCreated = 2;
                $update->contractCreated = 2;
                $update->client_response_sequence = $data['APIResponse']['CallSequence'];
                //$update->response = serialize($data['ContractPostResponse'][0]['Failed'][0]['Failures']);
                $update->save();

                $log = RealpayLogs::where('policy_id',$policy_id)->orderBy('id', 'DESC')->first();
                $log->status = 2;
                $log->save();

                return null;
            }
        }catch(\Exception $e){
            $update = RealpayPaymentRequest::where('policy_id',$policy_id)->first();
            $update->clientCreated = 2;
            $update->contractCreated = 2;
            $update->status = 2;
            $update->save();

            $log = RealpayLogs::where('policy_id',$policy_id)->orderBy('id', 'DESC')->first();
            $log->status = 2;
            $log->save();
            return null;
        }
    }

    public function addClientContractForMotorComp($policyId)
    {
        try{
            $fetchToken = $this->clientAuthForMotorComp();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $policy = Policy::where('id',$policyId)->first();

            $policy->policyActivatedDate = Carbon::now()->format("Y-m-d");
            $policy->save();
            if($policy->product_id != 3)
                $policy->first_premium_wvat = 0;

            $firstBillingDate = '';
            $firstCollectionAmount = '';
            $numberOfInstallments = '99';
            $frequency = 'MNTH';

            if($policy->premium_freq == 1 && $policy->first_premium_wvat > 0){
                $premium = $policy->premium;
                $now = new DateTime();
                $firstBillingDate = $now->format('Y-m-d');
                $firstCollectionAmount = $policy->first_premium_wvat;
                $numberOfInstallments = '99';
            }

            if($policy->premium_freq == 1 && $policy->first_premium_wvat == 0){
                $premium = $policy->premium;
            }

            if($policy->premium_freq != 1 && ($policy->premium > 0 || $policy->premium != null) && $policy->billingStartDate){
                $premium = $policy->premium;
                $now = new DateTime();
                $policy->billingStartDate = $now->format('Y-m-d');

                if($policy->premium_freq == 2){
                    $numberOfInstallments = '3';
                }
                elseif($policy->premium_freq == 3){
                    $frequency = 'YEAR';
                    $numberOfInstallments = '99';
                }
                elseif($policy->premium_freq == 1){
                    $numberOfInstallments = '99';
                }
                else{
                    if($policy->quoteNumber) {
                        $quote = MotorComprehensiveQuotes::where('quoteNumber', $policy->quoteNumber)->first(array('premiumMonthly'));
                        $premium = $quote->premiumMonthly;
                        $numberOfInstallments = '99';
                        $policy->premium_freq = 1;
                        $policy->save();
                    }else{
                        return null;
                    }
                }
            }

            $contractNumber = RealpayClientContracts::getContractNumber($policy->id);
            if($policy->billing_day == 31 && $policy->premium_freq != 2){
                $policy->billing_day = 99;
            }

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_START_BASE_URL')."/maintain/contracts/".env('REALPAY_START_PRODUCT')."?BeneficiaryUser=".env('REALPAY_START_MERCHANT')."&Version=".env('REALPAY_START_VERSION'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS =>"{\r\n
            \"ContractPostRequest\": [\r\n
                {\r\n
                      \"ClientNumber\": \"$policy->policyNumber\",\r\n
                      \"ContractNumber\": \"$contractNumber\",\r\n
                      \"FrequencyCode\": \"$frequency\",\r\n
                      \"CollectionDay\": \"$policy->billing_day\",\r\n
                      \"TrackingCode\": \"44\",\r\n
                      \"FirstCollectionDate\": \"$firstBillingDate\",\r\n
                      \"FirstCollectionAmount\": \"$firstCollectionAmount\",\r\n
                      \"InstalmentStartDate\": \"$policy->billingStartDate\",\r\n
                      \"InstalmentAmount\": $premium,\r\n
                      \"NumberOfInstalments\": \"$numberOfInstallments\",\r\n
                      \"CTCPercentage\": 1\r\n
                      }\r\n
                 ]\r\n}",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: " . $token
                ),
            ));
            $response = curl_exec($curl);
            $data = json_decode($response, true);
            curl_close($curl);


            $update = RealpayPaymentRequest::where('policy_id', $policyId)->first();
            if (sizeof($data['ContractPostResponse'][0]['Successful']) > 0 && sizeof($data['ContractPostResponse'][0]['Failed']) == 0) {
                $update->contract = $contractNumber;
                $update->contract_response_sequence = $data['APIResponse']['CallSequence'];
                $update->status = 1;
                $update->contractCreated = 1;
                $update->save();

                $log = RealpayLogs::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
                $log->status = 1;
                $log->save();

                if(sizeof($data['ContractPostResponse'][0]['Successful'][0]['ContractInstalments']) > 0){
                    $contract = $this->storeContractDetails($data['ContractPostResponse'][0]['Successful'][0]);
                    $installments = $this->storeInstallments($data['ContractPostResponse'][0]['Successful'][0]);
                }

                $logData = [
                    'policy_id'=>$policy->id,
                    'client_number'=>$policy->policyNumber,
                    'contract_number'=>$contractNumber,
                    'status'=>1,
                ];
                $addLog = RealpayClientContracts::addLog($logData);

                return $policy->policyNumber;
            } else {
                $update->status = 2;
                $update->contractCreated = 2;
                $update->contract_response_sequence = $data['APIResponse']['CallSequence'];
                $update->response = serialize($data['ContractPostResponse'][0]['Failed'][0]['Failures']);
                $update->save();

                $log = RealpayLogs::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
                $log->status = 2;
                $log->save();
                return null;
            }
        }catch(\Exception $e){
            $update = RealpayPaymentRequest::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
            $update->status = 2;
            $update->contractCreated = 2;
            $update->save();

            $log = RealpayLogs::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
            $log->status = 2;
            $log->save();
            return null;
        }
    }

    public function checkClientExistsForInstantProduct($policy_id)
    {
        $policy = Policy::where('id',$policy_id)->first(array('policyNumber'));
        $fetchToken = $this->clientAuthForInstantProduct();
        if($fetchToken['token_type'] && $fetchToken['access_token'])
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;


        $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

        if (!isset($customerBanking)) {
            $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
        }

        $url = '';
        if (isset($customerBanking) && $customerBanking->bankName == 12) {
            $url = env('REALPAY_START_BASE_URL').'/maintain/clients/'.env('REALPAY_FNB_PRODUCT')."?ClientNumber=".$policy->policyNumber."&BeneficiaryUser=".env('REALPAY_START_MERCHANT')."&Version=".env('REALPAY_START_VERSION');
        } else {
            $url = env('REALPAY_START_BASE_URL').'/maintain/clients/'.env('REALPAY_START_PRODUCT')."?ClientNumber=".$policy->policyNumber."&BeneficiaryUser=".env('REALPAY_START_MERCHANT')."&Version=".env('REALPAY_START_VERSION');
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
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

        if(empty($data['ClientGetResponse'])){
            return false;
        }else{
            return true;
        }
    }

    public function createClientForInstantProduct($policy_id)
    {
        try{
            $policy = Policy::where('id',$policy_id)->first();
            $customer = Customer::where('id', $policy->customer_id)->with('profile')->first();
            $profile = CustomerProfile::where('customer_id',$customer->id)->first();

            $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

            if (!isset($customerBanking)) {
                $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
            }

            $fetchToken = $this->clientAuthForInstantProduct();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            if($profile->omang != null){
                $id = $profile->omang;
                $idType = 'I';
            }else{
                $id = $profile->passport;
                $idType = 'P';
            }

            $url = '';
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $url = env('REALPAY_START_BASE_URL')."/maintain/clients/".env('REALPAY_FNB_PRODUCT')."?BeneficiaryUser=".env('REALPAY_START_MERCHANT')."&Version=".env('REALPAY_START_VERSION');
            } else {
                $url = env('REALPAY_START_BASE_URL')."/maintain/clients/".env('REALPAY_START_PRODUCT')."?BeneficiaryUser=".env('REALPAY_START_MERCHANT')."&Version=".env('REALPAY_START_VERSION');
            }

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS =>"{\r\n  \"ClientPostRequest\": [\r\n    {\r\n
           \"ClientNumber\": \"$policy->policyNumber\",\r\n
           \"ClientName\": \"$customer->firstName $customer->lastName\",\r\n
           \"IDType\": \"$idType\",\r\n
           \"IDNumber\": \"$id\",\r\n
           \"CellphoneNumber\": \"$customer->cellphone\",\r\n
           \"EMail\": \"$customer->email\",\r\n
           \"BankCode\": \"$customerBanking->bankName\",\r\n
           \"BranchCode\": \"$customerBanking->branchCode\",\r\n
           \"AccountType\": \"$customerBanking->accountType\",\r\n
           \"AccountNumber\": \"$customerBanking->accountNumber\",\r\n
           \"AccountHolderName\": \"$customer->firstName $customer->lastName\",\r\n
           \"EmployeeGroupCode\": \"OT\",\r\n
           }\r\n
           ]\r\n
           }",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: ".$token
                ),
            ));

            $response = curl_exec($curl);
            $data = json_decode($response,true);
            curl_close($curl);

            $update = RealpayPaymentRequest::where('policy_id',$policy_id)->first();
            if(!empty($data['ClientPostResponse'][0]['Successful']) && empty($data['ClientPostResponse'][0]['Failed'])){
                $update->clientNumber = $policy->policyNumber;
                $update->client_response_sequence = $data['APIResponse']['CallSequence'];
                $update->response = 1;
                $update->clientCreated = 1;
                $update->save();
                return $policy->policyNumber;

            }else{
                $update->status = 2;
                $update->clientCreated = 2;
                $update->contractCreated = 2;
                $update->client_response_sequence = $data['APIResponse']['CallSequence'];
                //$update->response = serialize($data['ContractPostResponse'][0]['Failed'][0]['Failures']);
                $update->save();

                $log = RealpayLogs::where('policy_id',$policy_id)->orderBy('id', 'DESC')->first();
                $log->status = 2;
                $log->save();

                return null;
            }
        }catch(\Exception $e){
            $update = RealpayPaymentRequest::where('policy_id',$policy_id)->first();
            $update->clientCreated = 2;
            $update->contractCreated = 2;
            $update->status = 2;
            $update->save();

            $log = RealpayLogs::where('policy_id',$policy_id)->orderBy('id', 'DESC')->first();
            $log->status = 2;
            $log->save();

            Log::info($e->getMessage(). " " .$e->getLine());
            return null;
        }
    }

    public function addClientContractForInstantProduct($policyId)
    {
        try{
            $fetchToken = $this->clientAuthForInstantProduct();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $policy = Policy::where('id',$policyId)->first();

            $policy->policyActivatedDate = Carbon::now()->format("Y-m-d");
            $policy->save();


            $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

            if (!isset($customerBanking)) {
                $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
            }

            if($policy->product_id != 3)
                $policy->first_premium_wvat = 0;


            $numberOfInstallments = '99';
            $frequency = 'MNTH';
            $premium = $policy->premium;

            $now = new DateTime();
            $firstBillingDate = \Carbon\Carbon::now()->addDays(1)->format('Y-m-d');
            $installmentStartDate = \Carbon\Carbon::parse($customerBanking->billingStartDate)->format('Y-m-d');

            if (isset($policy->first_premium)) {
                $firstCollectionAmount = $policy->first_premium;
            } else {
                $firstCollectionAmount = $policy->premium;
            }

            $billing_day = '';
            if ($customerBanking->billing_day != NULL) {
                $billing_day = $customerBanking->billing_day;
            }

            if($billing_day == 31 || $billing_day == 30 || $billing_day == 29){
                $billing_day = 99;
            }

            $contractNumber = RealpayClientContracts::getContractNumber($policy->id);

            $url = '';
            $trackingCode = "44";
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $trackingCode = "B3";
                $url = env('REALPAY_START_BASE_URL')."/maintain/contracts/".env('REALPAY_FNB_PRODUCT')."?BeneficiaryUser=".env('REALPAY_START_MERCHANT')."&Version=".env('REALPAY_START_VERSION');
            } else {
                $url = env('REALPAY_START_BASE_URL')."/maintain/contracts/".env('REALPAY_START_PRODUCT')."?BeneficiaryUser=".env('REALPAY_START_MERCHANT')."&Version=".env('REALPAY_START_VERSION');
            }

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS =>"{\r\n
            \"ContractPostRequest\": [\r\n
                {\r\n
                      \"ClientNumber\": \"$policy->policyNumber\",\r\n
                      \"ContractNumber\": \"$contractNumber\",\r\n
                      \"FrequencyCode\": \"$frequency\",\r\n
                      \"CollectionDay\": \"$billing_day\",\r\n
                      \"TrackingCode\": \"$trackingCode\",\r\n
                      \"FirstCollectionDate\": \"$firstBillingDate\",\r\n
                      \"FirstCollectionAmount\": \"$firstCollectionAmount\",\r\n
                      \"InstalmentStartDate\": \"$installmentStartDate\",\r\n
                      \"InstalmentAmount\": $premium,\r\n
                      \"NumberOfInstalments\": \"$numberOfInstallments\",\r\n
                      \"CTCPercentage\": 1\r\n
                      }\r\n
                 ]\r\n}",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: " . $token
                ),
            ));
            $response = curl_exec($curl);
            $data = json_decode($response, true);
            curl_close($curl);

            $update = RealpayPaymentRequest::where('policy_id', $policyId)->first();
            if (sizeof($data['ContractPostResponse'][0]['Successful']) > 0 && sizeof($data['ContractPostResponse'][0]['Failed']) == 0) {
                $update->contract = $contractNumber;
                $update->contract_response_sequence = $data['APIResponse']['CallSequence'];
                $update->status = 1;
                $update->contractCreated = 1;
                $update->save();

                $log = RealpayLogs::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
                $log->status = 1;
                $log->save();

                if(sizeof($data['ContractPostResponse'][0]['Successful'][0]['ContractInstalments']) > 0){
                    $contract = $this->storeContractDetails($data['ContractPostResponse'][0]['Successful'][0]);
                    $installments = $this->storeInstallments($data['ContractPostResponse'][0]['Successful'][0]);
                }

                $logData = [
                    'policy_id'=>$policy->id,
                    'client_number'=>$policy->policyNumber,
                    'contract_number'=>$contractNumber,
                    'status'=>1,
                ];
                $addLog = RealpayClientContracts::addLog($logData);

                return $policy->policyNumber;
            } else {
                $update->status = 2;
                $update->contractCreated = 2;
                $update->contract_response_sequence = $data['APIResponse']['CallSequence'];
                $update->response = serialize($data['ContractPostResponse'][0]['Failed'][0]['Failures']);
                $update->save();

                $log = RealpayLogs::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
                $log->status = 2;
                $log->save();
                return null;
            }
        }catch(\Exception $e){
            $update = RealpayPaymentRequest::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
            $update->status = 2;
            $update->contractCreated = 2;
            $update->save();

            $log = RealpayLogs::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
            $log->status = 2;
            $log->save();
            Log::info($e->getMessage(). " " .$e->getLine());
            return null;
        }
    }


    public function getContractInfoForMotorComp(Request $request){
        try {
            $fetchToken = $this->clientAuthForMotorComp();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            if (isset($request->policy_id)) {
                $policy = Policy::where('id',$request->policy_id)->first();
            } else {
                $policy = Policy::where('policyNumber',$request->policy_number)->first();
            }

            $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

            if (!isset($customerBanking)) {
                $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
            }

            $url = '';
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $url = env('REALPAY_START_BASE_URL') . "/maintain/clients/" . env('REALPAY_FNB_PRODUCT') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . env('REALPAY_START_MERCHANT') . "&Version=" . env('REALPAY_START_VERSION');
            } else {
                $url = env('REALPAY_START_BASE_URL') . "/maintain/clients/" . env('REALPAY_START_PRODUCT') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . env('REALPAY_START_MERCHANT') . "&Version=" . env('REALPAY_START_VERSION');
            }

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
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
            $clientData = json_decode($response,true);
            curl_close($curl);
            // dd($clientData);
            if (isset($clientData['ClientGetResponse']) && $clientData['ClientGetResponse'] != NULL) {
                $url = '';
                if (isset($customerBanking) && $customerBanking->bankName == 12) {
                    $url = env('REALPAY_START_BASE_URL') . "/maintain/contracts/" . env('REALPAY_FNB_PRODUCT') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . env('REALPAY_START_MERCHANT') . "&Version=" . env('REALPAY_START_VERSION');
                } else {
                    $url = env('REALPAY_START_BASE_URL') . "/maintain/contracts/" . env('REALPAY_START_PRODUCT') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . env('REALPAY_START_MERCHANT') . "&Version=" . env('REALPAY_START_VERSION');
                }

                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => $url,
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
                $contractData = json_decode($response,true);
                curl_close($curl);
                // dd($contractData);
                if (isset($contractData['ContractGetResponse']) && $contractData['ContractGetResponse'] != NULL) {
                    return $contractData['ContractGetResponse'];
                } else {
                    return null;
                }
            } else {
                return null;
            }
        } catch (\Exception $ex) {
            return $ex;
        }
    }

    public function updateRealpayInstallmentData($ins){
        try{
            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;


            $policy = Policy::where('id',$ins['policy_id'])->first();
            $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

            if (!isset($customerBanking)) {
                $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
            }

            $url = '';
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $url = env('REALPAY_BASE_URL')."/maintain/instalments/".env('REALPAY_FNB_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION');
            } else {
                $url = env('REALPAY_BASE_URL')."/maintain/instalments/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION');
            }

            $ins['clientNumber'] = $ins['realpay_client_number'];
            $ins['contractNumber'] = $ins['realpay_contract_number'];

            $getIns = $this->getRealpayInstallments($ins);
            if (!isset($getIns) || !isset($getIns['InstalmentGetResponse'])) {
                return response()->json(['status' => 'error', 'message' => 'Installments not found'], 401);
            }

            foreach ($getIns['InstalmentGetResponse'] as $key => $installment) {

                $contractSeq = $installment['ContractSequence'];
                $clientNum = $installment['ClientNumber'];
                $contractNumber = $installment['ContractNumber'];
                $insSeq = $installment['InstalmentSequence'];
                $tracking = $installment['TrackingCode'];
                $amnt = isset($ins['realpay_installment_premium']) ? $ins['realpay_installment_premium'] : $installment['InstalmentAmount'];
                $insStatus = $installment['InstalmentStatus'];
                $instDate = isset($ins['realpay_installment_date']) ? Carbon::parse($ins['realpay_installment_date'])->format('Y-m-d') : Carbon::parse($installment['InstalmentActionDate'])->format('Y-m-d');

                if ($insStatus == 'A' && $insSeq == $ins['realpay_installment_number']) {

                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "PUT",
                        CURLOPT_POSTFIELDS =>"{\r\n  \"InstalmentPutRequest\": [\r\n    {\r\n
                                    \"ClientNumber\": \"$clientNum\",\r\n
                                \"ContractSequence\": \"$contractSeq\",\r\n
                                \"ContractNumber\": \"$contractNumber\",\r\n
                                \"InstalmentSequence\": \"$insSeq\",\r\n
                                \"InstalmentActionDate\": \"$instDate\",\r\n
                                \"TrackingCode\": \"$tracking\",\r\n
                                \"InstalmentAmount\": \"$amnt\",\r\n
                                \"InstalmentStatus\": \"$insStatus\",\r\n
                                \"DebitSequenceType\": \"OOFF\",\r\n
                                }\r\n
                                ]\r\n
                                }",
                        CURLOPT_HTTPHEADER => array(
                            "Content-Type: application/json",
                            "Accept: application/json",
                            "Authorization: ".$token
                        ),
                    ));

                    $response = curl_exec($curl);
                    $data = json_decode($response,true);

                    $insdata = RealpayContractInstallments::where('clientNumber',$clientNum)
                        ->where('contractNumber',$contractNumber)
                        ->where('InstalmentSequence',$insSeq)
                        ->first();
                    if($insdata != null){
                        $insdata->InstalmentActionDate = $instDate;
                        $insdata->save();
                    }

                    activity('Realpay Installments')
                    ->performedOn($insdata)
                    ->log('Updated Realpay Installment Date');
                }

                sleep(1);
            }

            return response()->json(['status' => 'success', 'message' => 'Realpay Installments updated successfully'], 200);

        }catch(\Exception $e){
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 401);
        }
    }

    public function updateRealpayAllInstallmentData($ins){
        try{
            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;


            $policy = Policy::where('id',$ins['policy_id'])->first();
            $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

            if (!isset($customerBanking)) {
                $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
            }

            $url = '';
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $url = env('REALPAY_BASE_URL')."/maintain/instalments/".env('REALPAY_FNB_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION');
            } else {
                $url = env('REALPAY_BASE_URL')."/maintain/instalments/".env('REALPAY_PRODUCT')."?BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION');
            }

            $ins['clientNumber'] = $ins['realpay_client_number'];
            $ins['contractNumber'] = $ins['realpay_contract_number'];

            $getIns = $this->getRealpayInstallments($ins);
            if (!isset($getIns) || !isset($getIns['InstalmentGetResponse'])) {
                return response()->json(['status' => 'error', 'message' => 'Installments not found'], 401);
            }

            $countActive = 0;
            foreach ($getIns['InstalmentGetResponse'] as $key => $installment) {
                $contractSeq = $installment['ContractSequence'];
                $clientNum = $installment['ClientNumber'];
                $contractNumber = $installment['ContractNumber'];
                $insSeq = $installment['InstalmentSequence'];
                $tracking = $installment['TrackingCode'];
                $amnt = isset($ins['realpay_installment_premium']) ? $ins['realpay_installment_premium'] : $installment['InstalmentAmount'];
                $insStatus = $installment['InstalmentStatus'];
                $instDate = isset($ins['realpay_installment_date']) ? Carbon::parse($ins['realpay_installment_date'])->addMonthsNoOverflow($countActive)->format('Y-m-d') : Carbon::parse($installment['InstalmentActionDate'])->format('Y-m-d');

                if ($insStatus == 'A') {

                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "PUT",
                        CURLOPT_POSTFIELDS =>"{\r\n  \"InstalmentPutRequest\": [\r\n    {\r\n
                                    \"ClientNumber\": \"$clientNum\",\r\n
                                \"ContractSequence\": \"$contractSeq\",\r\n
                                \"ContractNumber\": \"$contractNumber\",\r\n
                                \"InstalmentSequence\": \"$insSeq\",\r\n
                                \"InstalmentActionDate\": \"$instDate\",\r\n
                                \"TrackingCode\": \"$tracking\",\r\n
                                \"InstalmentAmount\": \"$amnt\",\r\n
                                \"InstalmentStatus\": \"$insStatus\",\r\n
                                \"DebitSequenceType\": \"OOFF\",\r\n
                                }\r\n
                                ]\r\n
                                }",
                        CURLOPT_HTTPHEADER => array(
                            "Content-Type: application/json",
                            "Accept: application/json",
                            "Authorization: ".$token
                        ),
                    ));

                    $response = curl_exec($curl);
                    $data = json_decode($response,true);

                    $insdata = RealpayContractInstallments::where('clientNumber',$clientNum)
                        ->where('contractNumber',$contractNumber)
                        ->where('InstalmentSequence',$insSeq)
                        ->first();
                    if($insdata != null){
                        $insdata->InstalmentActionDate = $instDate;
                        $insdata->save();
                    }

                    $countActive++;

                    activity('Realpay Installments')
                    ->performedOn($insdata)
                    ->log('Updated Realpay Installment Date');
                }

                sleep(1);
            }

            return response()->json(['status' => 'success', 'message' => 'Realpay Installments updated successfully'], 200);

        }catch(\Exception $e){
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 401);
        }
    }

    public function updateRealpayInstallmentForInstantProduct($ins){
        try{
            $fetchToken = $this->clientAuthForInstantProduct();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $policy = Policy::where('id',$ins['policy_id'])->first();
            $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

            if (!isset($customerBanking)) {
                $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
            }

            $url = '';
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $url = env('REALPAY_START_BASE_URL')."/maintain/instalments/".env('REALPAY_FNB_PRODUCT')."?BeneficiaryUser=".env('REALPAY_START_MERCHANT')."&Version=".env('REALPAY_START_VERSION');
            } else {
                $url = env('REALPAY_START_BASE_URL')."/maintain/instalments/".env('REALPAY_START_PRODUCT')."?BeneficiaryUser=".env('REALPAY_START_MERCHANT')."&Version=".env('REALPAY_START_VERSION');
            }


            $ins['clientNumber'] = $ins['realpay_client_number'];
            $ins['contractNumber'] = $ins['realpay_contract_number'];

            $getIns = $this->getRealpayInstallmentsForInstantProduct($ins);
            if (!isset($getIns) || !isset($getIns['InstalmentGetResponse'])) {
                return response()->json(['status' => 'error', 'message' => 'Installments not found'], 401);
            }

            foreach ($getIns['InstalmentGetResponse'] as $key => $installment) {

                $contractSeq = $installment['ContractSequence'];
                $clientNum = $installment['ClientNumber'];
                $contractNumber = $installment['ContractNumber'];
                $insSeq = $installment['InstalmentSequence'];
                $tracking = $installment['TrackingCode'];
                $amnt = isset($ins['realpay_installment_premium']) ? $ins['realpay_installment_premium'] : $installment['InstalmentAmount'];
                $insStatus = $installment['InstalmentStatus'];
                $instDate = isset($ins['realpay_installment_date']) ? Carbon::parse($ins['realpay_installment_date'])->format('Y-m-d') : Carbon::parse($installment['InstalmentActionDate'])->format('Y-m-d');

                if ($insStatus == 'A' && $insSeq == $ins['realpay_installment_number']) {

                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "PUT",
                        CURLOPT_POSTFIELDS =>"{\r\n  \"InstalmentPutRequest\": [\r\n    {\r\n
                                    \"ClientNumber\": \"$clientNum\",\r\n
                                \"ContractSequence\": \"$contractSeq\",\r\n
                                \"ContractNumber\": \"$contractNumber\",\r\n
                                \"InstalmentSequence\": \"$insSeq\",\r\n
                                \"InstalmentActionDate\": \"$instDate\",\r\n
                                \"TrackingCode\": \"$tracking\",\r\n
                                \"InstalmentAmount\": \"$amnt\",\r\n
                                \"InstalmentStatus\": \"$insStatus\",\r\n
                                \"DebitSequenceType\": \"OOFF\",\r\n
                                }\r\n
                                ]\r\n
                                }",
                        CURLOPT_HTTPHEADER => array(
                            "Content-Type: application/json",
                            "Accept: application/json",
                            "Authorization: ".$token
                        ),
                    ));

                    $response = curl_exec($curl);
                    $data = json_decode($response,true);

                    $insdata = RealpayContractInstallments::where('clientNumber',$clientNum)
                        ->where('contractNumber',$contractNumber)
                        ->where('InstalmentSequence',$insSeq)
                        ->first();
                    if($insdata != null){
                        $insdata->InstalmentActionDate = $instDate;
                        $insdata->save();
                    }

                    activity('Realpay Installments')
                    ->performedOn($insdata)
                    ->log('Updated Realpay Installment Date');
                }

                sleep(1);
            }

            return response()->json(['status' => 'success', 'message' => 'Realpay Installments updated successfully'], 200);

        }catch(\Exception $e){
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 401);
        }
    }

    public function updateRealpayAllInstallmentForInstantProduct($ins){
        try{
            $fetchToken = $this->clientAuthForInstantProduct();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $policy = Policy::where('id',$ins['policy_id'])->first();
            $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

            if (!isset($customerBanking)) {
                $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
            }

            $url = '';
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $url = env('REALPAY_START_BASE_URL')."/maintain/instalments/".env('REALPAY_FNB_PRODUCT')."?BeneficiaryUser=".env('REALPAY_START_MERCHANT')."&Version=".env('REALPAY_START_VERSION');
            } else {
                $url = env('REALPAY_START_BASE_URL')."/maintain/instalments/".env('REALPAY_START_PRODUCT')."?BeneficiaryUser=".env('REALPAY_START_MERCHANT')."&Version=".env('REALPAY_START_VERSION');
            }

            $ins['clientNumber'] = $ins['realpay_client_number'];
            $ins['contractNumber'] = $ins['realpay_contract_number'];

            $getIns = $this->getRealpayInstallmentsForInstantProduct($ins);
            if (!isset($getIns) || !isset($getIns['InstalmentGetResponse'])) {
                return response()->json(['status' => 'error', 'message' => 'Installments not found'], 401);
            }

            $countActive = 0;
            foreach ($getIns['InstalmentGetResponse'] as $key => $installment) {
                $contractSeq = $installment['ContractSequence'];
                $clientNum = $installment['ClientNumber'];
                $contractNumber = $installment['ContractNumber'];
                $insSeq = $installment['InstalmentSequence'];
                $tracking = $installment['TrackingCode'];
                $amnt = isset($ins['realpay_installment_premium']) ? $ins['realpay_installment_premium'] : $installment['InstalmentAmount'];
                $insStatus = $installment['InstalmentStatus'];
                $instDate = isset($ins['realpay_installment_date']) ? Carbon::parse($ins['realpay_installment_date'])->addMonthsNoOverflow($countActive)->format('Y-m-d') : Carbon::parse($installment['InstalmentActionDate'])->format('Y-m-d');

                if ($insStatus == 'A') {

                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "PUT",
                        CURLOPT_POSTFIELDS =>"{\r\n  \"InstalmentPutRequest\": [\r\n    {\r\n
                                    \"ClientNumber\": \"$clientNum\",\r\n
                                \"ContractSequence\": \"$contractSeq\",\r\n
                                \"ContractNumber\": \"$contractNumber\",\r\n
                                \"InstalmentSequence\": \"$insSeq\",\r\n
                                \"InstalmentActionDate\": \"$instDate\",\r\n
                                \"TrackingCode\": \"$tracking\",\r\n
                                \"InstalmentAmount\": \"$amnt\",\r\n
                                \"InstalmentStatus\": \"$insStatus\",\r\n
                                \"DebitSequenceType\": \"OOFF\",\r\n
                                }\r\n
                                ]\r\n
                                }",
                        CURLOPT_HTTPHEADER => array(
                            "Content-Type: application/json",
                            "Accept: application/json",
                            "Authorization: ".$token
                        ),
                    ));

                    $response = curl_exec($curl);
                    $data = json_decode($response,true);

                    $insdata = RealpayContractInstallments::where('clientNumber',$clientNum)
                        ->where('contractNumber',$contractNumber)
                        ->where('InstalmentSequence',$insSeq)
                        ->first();
                    if($insdata != null){
                        $insdata->InstalmentActionDate = $instDate;
                        $insdata->save();
                    }

                    $countActive++;

                    activity('Realpay Installments')
                    ->performedOn($insdata)
                    ->log('Updated Realpay Installment Date');
                }

                sleep(1);
            }

            return response()->json(['status' => 'success', 'message' => 'Realpay Installments updated successfully'], 200);

        }catch(\Exception $e){
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 401);
        }
    }

    public function realpayReconsiliationTxLogCheck($record)
    {
        try {
            $paymentTx = PaymentTransaction::where('policyNumber',$record->clientNumber)->where('referenceNumber',$record->InstalmentReferenceNumber)->first();

            if (isset($paymentTx)) {

                if ($paymentTx->amount != $record->installmentAmount) {
                    $paymentTx->amount = $record->installmentAmount;
                }

                if ($paymentTx->status != $record->currentStatus) {
                    $paymentTx->status = $record->currentStatus;
                }

                $paymentTx->save();

                $saveData = RealpayTransactionDummyData::where('id',$record->id)->first();
                $saveData->is_imported = 2; // entry updated or entry present
                $saveData->save();

            } else {


                $paid = 0;

                if ($record->currentStatus == 'S') {
                    $record->currentStatus = 'SUCCESS';
                    $paid = 1;
                }

                if ($record->currentStatus == 'F') {
                    $record->currentStatus = 'FAILED';
                    $paid = 0;
                }

                $paymentData['policyNumber'] = $record->clientNumber;
                $paymentData['policy_id'] = $record->policy_id;
                $paymentData['referenceNumber'] = $record->InstalmentReferenceNumber;
                $paymentData['amount'] = $record->installmentAmount;
                $paymentData['status'] = $record->currentStatus;
                $paymentData['paymentDate'] = \Carbon\Carbon::parse($record->installmentDate)->format('Y-m-d');
                $paymentData['paymentMethod'] = 'RealPay';
                $paymentData['numberOfInstalmentsPaid'] = $paid;
                $paymentData['note'] = 'TRANSACTION ' . $record->currentStatus;

                $policyController = new PolicyController();
                $saveEntry = $policyController->updatePaymentTransactions($paymentData);

                $saveData = RealpayTransactionDummyData::where('id',$record->id)->first();
                $saveData->is_imported = 1; // entry added
                $saveData->save();
            }

        } catch (\Exception $ex) {
            return response()->json(['error'=>'failed','message' => $ex->getMessage(),'status' => '401']);
        }
    }

    public function getRealpayInstallments($ins){
        try{
            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $policy = Policy::where('id',$ins['policy_id'])->first();
            $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

            if (!isset($customerBanking)) {
                $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
            }

            $url = '';
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $url = env('REALPAY_BASE_URL')."/maintain/instalments/".env('REALPAY_FNB_PRODUCT')."?ClientNumber=".$ins['clientNumber']."&ContractNumber=".$ins['contractNumber']. "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION');
            } else {
                $url = env('REALPAY_BASE_URL')."/maintain/instalments/".env('REALPAY_PRODUCT')."?ClientNumber=".$ins['clientNumber']."&ContractNumber=".$ins['contractNumber']. "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION');
            }

            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_POSTFIELDS => array(),
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: ".$token
                ),
            ));

            $response = curl_exec($curl);
            $data = json_decode($response,true);
            curl_close($curl);

            return $data;
        }catch(\Exception $e){
            return null;
        }
    }

    public function getRealpayInstallmentsForInstantProduct($ins){
        try{
            $fetchToken = $this->clientAuthForInstantProduct();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $policy = Policy::where('id',$ins['policy_id'])->first();
            $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

            if (!isset($customerBanking)) {
                $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
            }

            $url = '';
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $url = env('REALPAY_START_BASE_URL')."/maintain/instalments/".env('REALPAY_FNB_PRODUCT')."?ClientNumber=".$ins['clientNumber']."&ContractNumber=".$ins['contractNumber']. "&BeneficiaryUser=" . env('REALPAY_START_MERCHANT') . "&Version=" . env('REALPAY_START_VERSION');
            } else {
                $url = env('REALPAY_START_BASE_URL')."/maintain/instalments/".env('REALPAY_START_PRODUCT')."?ClientNumber=".$ins['clientNumber']."&ContractNumber=".$ins['contractNumber']. "&BeneficiaryUser=" . env('REALPAY_START_MERCHANT') . "&Version=" . env('REALPAY_START_VERSION');
            }

            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_POSTFIELDS => array(),
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: ".$token
                ),
            ));

            $response = curl_exec($curl);
            $data = json_decode($response,true);
            curl_close($curl);

            return $data;
        }catch(\Exception $e){
            return null;
        }
    }

    public function contractIsActive($policy_id = null, $clientNumber = null): bool
    {
        $activeInstallments = RealpayContractInstallments::where('ClientNumber', $clientNumber)
            ->where('InstalmentStatus', 'A')
            ->exists();

        $getContract = null;
        if ($activeInstallments == false) {
            $getContract = $this->checkMotorContractActive($policy_id, $clientNumber) ??
                           $this->checkInstantContractActive($policy_id, $clientNumber);

            if ($getContract && is_array($getContract)) {
                $activeInstallments = $this->hasActiveApiInstallments($getContract);
            }
        }

        return $activeInstallments;
    }

    public function checkMotorContractActive($policy_id,$clientNumber)
    {
        $policy = Policy::where('id',$policy_id)->first();

        $fetchToken = $this->clientAuth();
        if($fetchToken['token_type'] && $fetchToken['access_token'])
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;

        $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

        if (!isset($customerBanking)) {
            $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
        }

		$url = '';
        if (isset($customerBanking) && $customerBanking->bankName == 12) {
            $url = env('REALPAY_BASE_URL')."/maintain/contracts/".env('REALPAY_FNB_PRODUCT')."?ClientNumber=".$clientNumber."&BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION');
        } else {
            $url = env('REALPAY_BASE_URL')."/maintain/contracts/".env('REALPAY_PRODUCT')."?ClientNumber=".$clientNumber."&BeneficiaryUser=".env('REALPAY_MERCHANT')."&Version=".env('REALPAY_VERSION');
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
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

        return $data['ContractGetResponse'] ?? null;

    }

    public function checkInstantContractActive($policy_id,$clientNumber)
    {
        $policy = Policy::where('id',$policy_id)->first();

        $fetchToken = $this->clientAuthForMotorComp();
        if($fetchToken['token_type'] && $fetchToken['access_token'])
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;

        $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

        if (!isset($customerBanking)) {
            $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
        }

		$url = '';
        if (isset($customerBanking) && $customerBanking->bankName == 12) {
            $url = env('REALPAY_START_BASE_URL')."/maintain/contracts/".env('REALPAY_FNB_PRODUCT')."?ClientNumber=".$clientNumber."&BeneficiaryUser=".env('REALPAY_START_MERCHANT')."&Version=".env('REALPAY_START_VERSION');
        } else {
            $url = env('REALPAY_START_BASE_URL')."/maintain/contracts/".env('REALPAY_START_PRODUCT')."?ClientNumber=".$clientNumber."&BeneficiaryUser=".env('REALPAY_START_MERCHANT')."&Version=".env('REALPAY_START_VERSION');
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
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

        return $data['ContractGetResponse'] ?? null;

    }

    public function hasActiveApiInstallments(array $contractResponses): bool
    {
        foreach ($contractResponses as $contract) {
            if (!isset($contract['ContractInstalments']) || !is_array($contract['ContractInstalments'])) {
                continue;
            }

            foreach ($contract['ContractInstalments'] as $installment) {
                if (isset($installment['InstalmentStatus']) && $installment['InstalmentStatus'] === 'A') {
                    return true;
                }
            }
        }

        return false;
    }

    public function getInstallmentsFromContractSequence($record){
        try{

            $ContractSequence = $record->contractsequence;
            $product = $record->product;
            $beneficiarynumber = $record->beneficiarynumber;

            $fetchToken = $this->clientAuth();
            if($fetchToken['token_type'] && $fetchToken['access_token'])
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://realpaycollect.com:4448/rpp/rpws/maintain/instalments/".$product."?ContractSequence=".$ContractSequence."&BeneficiaryUser=".$beneficiarynumber."&Version=v1",
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

            return $data;
        }catch(\Exception $e){
            return null;
        }
    }
}
