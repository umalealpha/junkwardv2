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
use AlphaDirect\Http\Controllers\Payment\RealPay\RealPayController as RealPayRealPayController;
use AlphaDirect\Imports\RealpayTransactionsImport;
use AlphaDirect\Models\ExpiredPoliciesImportJobs;
use AlphaDirect\Models\PolicyDiscountSurcharge;
use AlphaDirect\Models\PolicyRenewal;
use AlphaDirect\Models\RealpayTransactionsExcelData;
use AlphaDirect\PaymentVendor;
use AlphaDirect\PolicyTerm;
use AlphaDirect\RealpayTransactionDummyData;
use Illuminate\Support\Facades\Artisan;
use Maatwebsite\Excel\Facades\Excel;

use function PHPUnit\Framework\isEmpty;


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
                elseif($data->event == 3)
                    return 'Update Installment Data';
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
//            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
//                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
//            else
//                return null;
//

//
//            $curl = curl_init();
//
//            curl_setopt_array($curl, array(
//                CURLOPT_URL => config('realpay.base_url')."/maintain/instalments/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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
        if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
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
            CURLOPT_URL => config('realpay.base_url')."/maintain/instalments/".config('realpay.product')."?ClientNumber=".$clientNum."&ContractNumber=".$contractNumber.'&ContractSequence='.$conSeq.'&InstalmentSequence='.$insSeq."config('realpay.merchant')".config('realpay.version'),
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
                CURLOPT_URL => config('realpay.base_url')."/maintain/instalments/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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

    public function clientAuthForMotorComp(){
        try{
            $curl = curl_init();

            // curl_setopt_array($curl, array(
            //     CURLOPT_URL => config('realpay.start.base_url')."/oauth/token",
            //     CURLOPT_RETURNTRANSFER => true,
            //     CURLOPT_ENCODING => "",
            //     CURLOPT_MAXREDIRS => 10,
            //     CURLOPT_TIMEOUT => 0,
            //     CURLOPT_FOLLOWLOCATION => true,
            //     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            //     CURLOPT_CUSTOMREQUEST => "POST",
            //     CURLOPT_POSTFIELDS => "grant_type=client_credentials",
            //     CURLOPT_HTTPHEADER => array(
            //         "Authorization: Basic ".config('realpay.start.client_auth'),
            //         "Content-Type: application/x-www-form-urlencoded"
            //     ),
            // ));

            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.start.base_url').'/oauth/token?grant_type=client_credentials',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_HTTPHEADER => array(
                  'Authorization: Basic '.config('realpay.start.client_auth'),
                ),
              ));

            $response = curl_exec($curl);
            $curlErr  = curl_error($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            $decoded = json_decode($response, true);
            if (!is_array($decoded) || empty($decoded['access_token'])) {
                \Illuminate\Support\Facades\Log::error('RealPay clientAuthForMotorComp: token fetch failed', [
                    'has_base_url'    => !empty(config('realpay.start.base_url')),
                    'has_client_auth' => !empty(config('realpay.start.client_auth')),
                    'http_code'       => $httpCode,
                    'curl_error'      => $curlErr,
                    'body'            => is_string($response) ? substr($response, 0, 500) : null,
                ]);
            }
            return $decoded;
        }catch(\Exception $e){
            return response()->json(['Status' => 'Failed','Description'=>$e->getMessage()], 401);
        }
    }

    public function clientAuth(){
        try{
            $curl = curl_init();

            // curl_setopt_array($curl, array(
            //     CURLOPT_URL => config('realpay.base_url')."/oauth/token",
            //     CURLOPT_RETURNTRANSFER => true,
            //     CURLOPT_ENCODING => "",
            //     CURLOPT_MAXREDIRS => 10,
            //     CURLOPT_TIMEOUT => 0,
            //     CURLOPT_FOLLOWLOCATION => true,
            //     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            //     CURLOPT_CUSTOMREQUEST => "POST",
            //     CURLOPT_POSTFIELDS => "grant_type=client_credentials",
            //     CURLOPT_HTTPHEADER => array(
            //         "Authorization: Basic ".config('realpay.client_auth'),
            //         "Content-Type: application/x-www-form-urlencoded"
            //     ),
            // ));
            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.base_url').'/oauth/token?grant_type=client_credentials',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_HTTPHEADER => array(
                  'Authorization: Basic '.config('realpay.client_auth'),
                ),
              ));

            $response = curl_exec($curl);
            $curlErr  = curl_error($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            $data = json_decode($response,true);
            if (!is_array($data) || empty($data['access_token'])) {
                \Illuminate\Support\Facades\Log::error('RealPay clientAuth: token fetch failed', [
                    'has_base_url'    => !empty(config('realpay.base_url')),
                    'has_client_auth' => !empty(config('realpay.client_auth')),
                    'http_code'       => $httpCode,
                    'curl_error'      => $curlErr,
                    'body'            => is_string($response) ? substr($response, 0, 500) : null,
                ]);
            }
            return $data;
        }catch(\Exception $e){
            return response()->json(['Status' => 'Failed','Description'=>$e->getMessage()], 401);
        }
    }

    public function storeNewClient(Request $request){
        try{

            $clientNumber = $this->cancelRealpayContract($request->policyID);

            // if($clientNumber == null) {
                $clientNumber = $this->cancelRealpayContractsForInstProduct($request->policyID);
            // }

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
            $customerBanking = $this->resolvePolicyCustomerBanking($policy);

            if($customerBanking == null){
                $customerBanking = new CustomerBanking();
            }

            if(isset($request->BankCode)){
                $request['BankName'] = $request->BankCode;
            }

            if ($policy->product_id == 3) {
                $fetchToken = $this->clientAuth();
            } else {
                $fetchToken = $this->clientAuthForMotorComp();
            }

            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
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

            $url = '';
            $checkClient = '';

            if ($policy->product_id == 3) {
                $checkClient = $this->checkClientExists($policy->id);

                if (isset($request->BankName) && $request->BankName == 12) {
                    $url = config('realpay.base_url')."/maintain/clients/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
                } else {
                    $url = config('realpay.base_url')."/maintain/clients/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
                }

            } else {
                $checkClient = $this->checkClientExistsForMotorComp($policy->id);

                if (isset($request->BankName) && $request->BankName == 12) {
                    $url =config('realpay.start.base_url')."/maintain/clients/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
                } else {
                    $url =config('realpay.start.base_url')."/maintain/clients/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
                }

            }

            if($checkClient == false){
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
                    CURLOPT_URL => $url,
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
            // dd($data);
            //if(!empty($data['ClientPostResponse'][0]['Successful']) && empty($data['ClientPostResponse'][0]['Failed'])){
            $customerBanking->bankName = $request->BankName;
            $customerBanking->branchCode = $request->BranchCode;
            $customerBanking->accountType = $request->accountType;
            $customerBanking->accountNumber = $request->accountNumber;
            $customerBanking->billing = "RealPay";
            $customerBanking->billing_day = $request->billing_day;
            $customerBanking->billingStartDate = $this->setDate($request->billing_day);
            $customerBanking->save();


            if ($policy->product_id == 3) {
                $fetchToken = $this->clientAuth();
            } else {
                $fetchToken = $this->clientAuthForMotorComp();
            }

            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
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

            $url = '';
            $trackingCode = "44";
            if ($policy->product_id == 3) {
                if (isset($request->BankName) && $request->BankName == 12) {
                    $trackingCode = "B3";
                    $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
                } else {
                    $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
                }

            } else {
                if (isset($request->BankName) && $request->BankName == 12) {
                    $trackingCode = "B3";
                    $url =config('realpay.start.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
                } else {
                    $url =config('realpay.start.base_url')."/maintain/contracts/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
                }
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

            $v = (int) date('t', mktime(0, 0, 0, $mnth, 1, $yr));

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

    public function checkClientExistsForMotorComp($policy_id)
    {
        $policy = Policy::where('id',$policy_id)->first(array('policyNumber'));
        $fetchToken = $this->clientAuthForMotorComp();
        if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;

        $customerBanking = $this->resolvePolicyCustomerBanking($policy);

        $url = '';
        if (isset($customerBanking) && $customerBanking->bankName == 12) {
            $url = config('realpay.start.base_url').'/maintain/clients/'.config('realpay.fnb_product')."?ClientNumber=".$policy->policyNumber."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
        } else {
            $url = config('realpay.start.base_url').'/maintain/clients/'.config('realpay.start.product')."?ClientNumber=".$policy->policyNumber."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
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

    /**
     * Resolve the CustomerBanking row to use when registering a RealPay
     * client/contract for a policy.
     *
     * GRA — "customer already has a RealPay policy, payment for a different
     * policy doesn't work": checkClientExists[ForInstantProduct],
     * createClient[ForInstantProduct] and addClientContract[ForInstantProduct]
     * each looked up CustomerBanking by policy_id, and — when this policy had
     * no banking row of its own yet — silently fell back to the customer's
     * MOST RECENT banking row from ANY of their policies (orderBy id desc,
     * scoped only by customer_id). For a customer with two policies on two
     * different banks, that meant policy B's RealPay client/contract got
     * registered with policy A's bank/branch/account details — wrong bank
     * chosen (BankCode/fnb_product vs start.product), wrong account debited,
     * or a mismatched product bucket causing RealPay to reject the request.
     *
     * Fix: keep the same-customer fallback (customers legitimately reuse one
     * bank account across policies), but the moment we borrow another
     * policy's row, clone it into a NEW row scoped to THIS policy_id so the
     * borrow happens once and is durably recorded — every subsequent call
     * (and anyone auditing customer_banking for this policy) sees an
     * accurate, policy-scoped row instead of re-deriving an ambiguous one.
     */
    private function resolvePolicyCustomerBanking($policy)
    {
        $customerBanking = CustomerBanking::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first();
        if ($customerBanking) {
            return $customerBanking;
        }

        $borrowed = CustomerBanking::where('customer_id', $policy->customer_id)->orderBy('id', 'DESC')->first();
        if (!$borrowed) {
            return null;
        }

        $clone = $borrowed->replicate();
        $clone->policy_id = $policy->id;
        $clone->save();

        Log::warning('realpay.customer_banking.borrowed_from_other_policy', [
            'policy_id'          => $policy->id,
            'borrowed_from_id'   => $borrowed->id,
            'borrowed_policy_id' => $borrowed->policy_id,
            'new_banking_id'     => $clone->id,
        ]);

        return $clone;
    }

    public function checkClientExists($policy_id)
    {
        $policy = Policy::where('id',$policy_id)->first(array('policyNumber'));
        $fetchToken = $this->clientAuth();
        if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;

        $customerBanking = $this->resolvePolicyCustomerBanking($policy);

        $url = '';
        if (isset($customerBanking) && $customerBanking->bankName == 12) {
            $url = config('realpay.base_url').'/maintain/clients/'.config('realpay.fnb_product')."?ClientNumber=".$policy->policyNumber."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
        } else {
            $url = config('realpay.base_url').'/maintain/clients/'.config('realpay.product')."?ClientNumber=".$policy->policyNumber."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
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
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
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
                CURLOPT_URL => config('realpay.base_url')."/maintain/clients/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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
        if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => config('realpay.base_url')."/maintain/contracts/".config('realpay.product')."?ClientNumber=".$policy->policyNumber."&ContractNumber=".$policy->id."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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

    public function checkInstClientContractExists($policy_id)
    {
        $policy = Policy::where('id',$policy_id)->first();

        $fetchToken = $this->clientAuthForMotorComp();
        if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => config('realpay.start.base_url')."/maintain/contracts/".config('realpay.start.product')."?ClientNumber=".$policy->policyNumber."&ContractNumber=".$policy->id."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version'),
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

            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
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
                CURLOPT_URL => config('realpay.base_url')."/maintain/contracts/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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

    public function createClientForMotorComp($policy_id)
    {
        try{
            $policy = Policy::where('id',$policy_id)->first();
            $customer = Customer::where('id', $policy->customer_id)->with('profile')->first();
            $profile = CustomerProfile::where('customer_id',$customer->id)->first();

            $customerBanking = $this->resolvePolicyCustomerBanking($policy);

            $fetchToken = $this->clientAuthForMotorComp();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
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
                CURLOPT_URL => config('realpay.start.base_url')."/maintain/clients/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version'),
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

    public function createClient($policy_id)
    {
        try{
            $policy = Policy::where('id',$policy_id)->first();
            $customer = Customer::where('id', $policy->customer_id)->with('profile')->first();
            $profile = CustomerProfile::where('customer_id',$customer->id)->first();

            $customerBanking = $this->resolvePolicyCustomerBanking($policy);

            $fetchToken = $this->clientAuth();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
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
                $url = config('realpay.base_url')."/maintain/clients/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
            } else {
                $url = config('realpay.base_url')."/maintain/clients/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
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
                $failure     = $data['ClientPostResponse'][0]['Failed'][0]['Failures'][0] ?? null;
                $failureCode = $failure['FailureCode'] ?? null;
                $failureDesc = strtoupper((string)($failure['FailureDescription'] ?? ''));

                // A "DUPLICATE CLIENT" is NOT a real failure — the client
                // already exists on RealPay and can be reused for the contract.
                // Adopt it (clientCreated=1) so activation proceeds to contract
                // creation instead of marking the whole request failed. This is
                // idempotent — no new client is created on RealPay. (RealPay's
                // duplicate code mirrors the contract side; we also match on the
                // description to be resilient to gateway wording.)
                if ($failureCode === 'TAK1'
                    || strpos($failureDesc, 'DUPLICATE') !== false
                    || strpos($failureDesc, 'ALREADY EXIST') !== false) {
                    Log::info('RealPay createClient: duplicate client — adopting existing client on RealPay', [
                        'policy_id' => $policy_id,
                    ]);
                    $update->clientNumber = $policy->policyNumber;
                    $update->client_response_sequence = $data['APIResponse']['CallSequence'] ?? null;
                    $update->response = 1;
                    $update->clientCreated = 1;
                    $update->save();
                    return $policy->policyNumber;
                }

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
        if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;

        $curl = curl_init();

        if($realpayClientContract){
            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.base_url') . "/maintain/contracts/" . config('realpay.product') . "?ClientNumber=" . $realpayClientContract->client_number . "&ContractNumber=" . $realpayClientContract->contract_number . "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version'),
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
                            CURLOPT_URL => config('realpay.base_url') . "/maintain/instalments/" . config('realpay.product') . "?BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version'),
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

    public function addClientContractForMotorComp($policyId)
    {
        try{
            $fetchToken = $this->clientAuthForMotorComp();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
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
                CURLOPT_URL => config('realpay.start.base_url')."/maintain/contracts/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version'),
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

    public function addClientContract($policyId)
    {
        try{
            $fetchToken = $this->clientAuth();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $policy = Policy::where('id',$policyId)->first();

            $policy->policyActivatedDate = Carbon::now()->format("Y-m-d");
            $policy->save();

            // ── Idempotency guard (RealPay double-debit prevention) ──────────
            // If a live contract already exists on the RealPay portal for this
            // policy, adopt it instead of POSTing a fresh create. A second
            // create is rejected by RealPay with FailureCode TAK1 ("DUPLICATE
            // CONTRACT") and, before this guard, left contractCreated=2 /
            // policy inactive while RealPay kept debiting the already-live
            // contract. Reconciling is idempotent — it makes only GET calls and
            // creates nothing new on RealPay.
            $existingContract = $this->getExistingRealpayContract($policy->id, $policy->policyNumber);
            if (is_array($existingContract) && !empty($existingContract)) {
                Log::info('RealPay addClientContract: live contract already on portal — adopting instead of creating', [
                    'policy_id' => $policy->id,
                ]);
                $synced = $this->fetchAndStoreRealpayContract($policy, $existingContract);
                if ($synced > 0) {
                    return $policy->policyNumber;
                }
                Log::warning('RealPay addClientContract: existing contract found but adopt synced 0 rows — proceeding to create', [
                    'policy_id' => $policy->id,
                ]);
            }

            if($policy->product_id != 3)
                $policy->first_premium_wvat = 0;

            $firstBillingDate = '';
            $firstCollectionAmount = '';
            $numberOfInstallments = '99';
            $frequency = 'MNTH';

            if($policy->premium_freq == 1 && $policy->first_premium_wvat > 0){
                $premium = $policy->premium;
                $now = new DateTime();
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
                $now = new DateTime();
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

                if($policy->BillingStart == "Later"){
                    $firstBillingDate = \Carbon\Carbon::parse($policy->billingStartDate)->format('Y-m-d');
                    $firstCollectionAmount = $policy->premium;
                }else{
                    $firstBillingDate = $now->format('Y-m-d');
                    $firstCollectionAmount = $policy->premium;
                }

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

            $customerBanking = $this->resolvePolicyCustomerBanking($policy);

            $url = '';
            $trackingCode = "44";
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $trackingCode = "B3";
                $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
            } else {
                $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
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
                $failureCode = $data['ContractPostResponse'][0]['Failed'][0]['Failures'][0]['FailureCode'] ?? null;

                // TAK1 = "DUPLICATE CONTRACT". The contract already exists on
                // RealPay (and is being debited). This is NOT a hard failure —
                // reconcile by adopting the existing contract so the policy
                // activates and the debit surfaces locally. Idempotent: only
                // GET calls, creates nothing new on RealPay. TAK26 ("INVALID
                // BANK") and every other code stay a genuine failure below.
                if ($failureCode === 'TAK1') {
                    Log::info('RealPay addClientContract: TAK1 DUPLICATE CONTRACT — reconciling existing contract', [
                        'policy_id' => $policy->id,
                    ]);
                    $synced = $this->fetchAndStoreRealpayContract($policy);
                    if ($synced > 0) {
                        return $policy->policyNumber;
                    }
                    // Reconcile could not locate a live contract to adopt — do
                    // not swallow the failure; fall through to the failed path.
                    Log::warning('RealPay addClientContract: TAK1 but no live contract found to adopt — marking failed', [
                        'policy_id' => $policy->id,
                    ]);
                }

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

    public function addRealPayClientContract($policyId)
    {
        try{
            $fetchToken = $this->clientAuth();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
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
            $numberOfInstallments = '14';
            $frequency = 'MNTH';

            if($policy->premium_freq == 1 && $policy->first_premium_wvat > 0){
                $premium = $policy->premium;
                $now = new DateTime();
                $firstBillingDate = $now->format('Y-m-d');
                $firstCollectionAmount = $policy->first_premium_wvat;
                $numberOfInstallments = '14';
            }

            if($policy->premium_freq == 1 && $policy->first_premium_wvat == 0){
                $premium = $policy->premium;
                $now = new DateTime();
                $firstBillingDate = $now->format('Y-m-d');
                $firstCollectionAmount = $policy->premium;
            }

            if($policy->premium_freq != 1 && ($policy->premium > 0 || $policy->premium != null) && $policy->billingStartDate){
                $premium = $policy->premium;
                $now = new DateTime();
                $policy->billingStartDate = $now->format('Y-m-d');
                $firstBillingDate = $now->format('Y-m-d');
                $firstCollectionAmount = $policy->premium;

                if($policy->premium_freq == 2){
                    $numberOfInstallments = '3';
                }
                elseif($policy->premium_freq == 3){
                    $frequency = 'YEAR';
                    $numberOfInstallments = '1';
                }
                elseif($policy->premium_freq == 1){
                    $numberOfInstallments = '14';
                }
                else{
                    if($policy->quoteNumber) {
                        $quote = MotorComprehensiveQuotes::where('quoteNumber', $policy->quoteNumber)->first(array('premiumMonthly'));
                        $premium = $quote->premiumMonthly;
                        $numberOfInstallments = '14';
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

            $customerBanking = $this->resolvePolicyCustomerBanking($policy);

            $policy->billingStartDate = Carbon::now()->format("Y-m-d");
            $firstCollectionAmount = $premium;

            $url = '';
            $trackingCode = "44";
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $trackingCode = "B3";
                $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
            } else {
                $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
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
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return Redirect::back()->with('error', 'Auth error');

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.base_url')."/maintain/contracts/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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

    public function storeClientContractDetails($data){
        try{
            $saveData = new RealpayClientContracts();
            $saveData->policy_id = $data['policy_id'];
            $saveData->client_number = $data['ClientNumber'];
            $saveData->contract_number = $data['ContractNumber'];
            $saveData->rate_id = null;
            $saveData->status = 0;
            $saveData->save();

            return true;
        }catch(\Http\Client\Exception $ex){
            return false;
        }
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
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                $token = null;

            $successResult = 0;
            $contractFailed = array();

            if (isset($clientContracts)) {
                foreach ($clientContracts as $key => $contract) {
                    // dd($contract->contract_number);

                    $url = '';
                    if (isset($banking) && $banking->bankName == 12) {
                       $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?ClientNumber=".$policy->policyNumber."&ContractNumber=".$contract->contract_number."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
                    } else {
                       $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.product')."?ClientNumber=".$policy->policyNumber."&ContractNumber=".$contract->contract_number."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
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
                    ///dd($data,$policy->policyNumber,$contract->contract_number);
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

            if (isset(auth()->user()->id)) {
                activity('Realpay Contract')
                ->performedOn($policy)
                ->causedBy(User::where('id', auth()->user()->id)->first())
                ->log('Cancelled realpay contract');
            } else {
                activity('Realpay Contract')
                ->performedOn($policy)
                ->log('Cancelled realpay contract');
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
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.base_url')."/general/bank_responses/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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

    public function getClientType($clientNumber)
    {
        $prefix = strtoupper(substr($clientNumber, 0, 3)); // First 3 chars

        return match ($prefix) {
            'DOM' => 'DOM',
            'COM' => 'COM',
            'MIS' => 'MIS',
            default => 'Unknown',
        };
    }

    /**
     * Hand a RealPay instalment result to the mandate layer.
     *
     * Bookkeeping only. AlphaDirect\Services\RealPayMandateService records the
     * payload, marks the mandate active on a confirmed collection
     * (InstalmentStatus == 'S'), and records — without marking anything
     * successful — a failed or still-pending one. It never writes policy.status:
     * activation stays in updateInstallment() below, so there remains exactly one
     * code path that can set a policy live.
     *
     * Failure-isolated. A bookkeeping problem must not turn this webhook's 200
     * into a 500 — RealPay retries on non-2xx and the retry would fail the same
     * way, which is how the webhook buffer filled up in GRA-0054.
     *
     * @param  array  $payload  the full request body as received
     */
    private function applyMandateInstalmentOutcome(
        $clientNumber,
        $contractNumber,
        $rawInstalmentStatus,
        $instalmentReferenceNumber,
        $sequence,
        $amount,
        $actionDate,
        array $payload
    ): void {
        try {
            app(\AlphaDirect\Services\RealPayMandateService::class)->applyInstalmentOutcome([
                'client_number'        => $clientNumber,
                'contract_number'      => $contractNumber,
                'instalment_status'    => $rawInstalmentStatus,
                'instalment_reference' => $instalmentReferenceNumber,
                'sequence'             => $sequence,
                'amount'               => $amount,
                'action_date'          => $actionDate,
                'payload'              => $payload['InstalmentGetResponse'][0] ?? $payload,
            ]);
        } catch (\Throwable $e) {
            Log::error('RealPay mandate bookkeeping failed in updateInstallment', [
                'client_number'   => $clientNumber,
                'contract_number' => $contractNumber,
                'status'          => $rawInstalmentStatus,
                'error'           => $e->getMessage(),
                'line'            => $e->getLine(),
            ]);
        }
    }

    /**
     * Does this delivery carry the minimum needed to record the instalment?
     *
     * REPLACES the old inline gate, which required all eight extracted fields
     * — including TrackingCode and InstalmentSequence — before it would touch
     * anything. Two ways that silently dropped a collected debit:
     *
     *   1. TrackingCode is OPTIONAL metadata in RealPay's payload (it is
     *      absent/empty on a number of instalment notifications). An 'S' with
     *      no TrackingCode failed the gate, so the installment row was never
     *      updated, the payment was never written, and the policy was never
     *      activated — while the customer had already been debited. Nothing
     *      needs TrackingCode to record a payment, so it is no longer required.
     *   2. `!= null` is a LOOSE comparison. In PHP `0 != null` is false, so an
     *      InstalmentSequence or InstalmentAmount of integer 0 also failed the
     *      gate. Presence is now tested with `!== null` plus a non-empty-string
     *      check, so a legitimate zero passes.
     *
     * Required: the idempotency key (InstalmentReferenceNumber), the outcome
     * (InstalmentStatus), the amount, the action date, and at least one of
     * client/contract number so RealpayPaymentRecorder can resolve the policy.
     *
     * @return bool true when updateInstallment() may proceed to record.
     */
    private function realpayDeliveryHasMinimumFields(
        $clientNumber,
        $contractNumber,
        $instalmentReferenceNumber,
        $status,
        $actionDate,
        $amount
    ): bool {
        $present = static fn ($v): bool => $v !== null && trim((string) $v) !== '';

        return $present($instalmentReferenceNumber)
            && $present($status)
            && $present($amount)
            && $present($actionDate)
            && ($present($clientNumber) || $present($contractNumber));
    }

    /**
     * A delivery arrived without the fields needed to record it.
     *
     * The old behaviour was `return response()->json([...], 401)`. That is not
     * a 5xx, so ProcessWebhookBuffer's original `$statusCode < 500` check
     * marked the buffered webhook 'processed' and consumed it — a debited
     * customer with no payment row, no retry and no trace. The drain now only
     * accepts 2xx, but a 401 still left NOTHING on the exception ledger, so the
     * scheduled `realpay:reconcile-reflection --source=exceptions` report was
     * blind to it. That is why realpay_reflection_exceptions was empty while
     * gaps existed.
     *
     * A settling delivery ('S'/'F') is therefore ledgered before the caller
     * returns a retryable 500.
     */
    private function recordIncompleteRealpayDelivery(
        $clientNumber,
        $contractNumber,
        $rawInstalmentStatus,
        $instalmentReferenceNumber,
        $sequence,
        $amount,
        $actionDate,
        array $payload = []
    ): void {
        try {
            if (!in_array(strtoupper((string) $rawInstalmentStatus), ['S', 'F'], true)) {
                // 'W'/'A'/'R' etc. are not an outcome — an incomplete pending
                // notification is not a lost payment and must not raise noise.
                return;
            }

            app(\AlphaDirect\Services\RealpayPaymentRecorder::class)->recordException([
                'client_number'        => $clientNumber,
                'contract_number'      => $contractNumber,
                'instalment_reference' => $instalmentReferenceNumber,
                'sequence'             => $sequence,
                'instalment_status'    => $rawInstalmentStatus,
                'amount'               => $amount,
                'action_date'          => $actionDate,
                'payload'              => $payload['InstalmentGetResponse'][0] ?? $payload,
            ], \AlphaDirect\RealpayReflectionException::REASON_WRITE_FAILED,
                'Delivery missing fields required to record the instalment (reference/status/amount/action date, and a client or contract number).');
        } catch (\Throwable $e) {
            Log::error('RealPay incomplete-delivery ledgering failed', [
                'client_number' => $clientNumber,
                'reference'     => $instalmentReferenceNumber,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reflect a RealPay instalment outcome into payment_transactions.
     *
     * Thin adapter onto AlphaDirect\Services\RealpayPaymentRecorder so both
     * branches of updateInstallment() below share one writer. The recorder is
     * idempotent on InstalmentReferenceNumber, so the every-minute buffer
     * replay, a RealPay redelivery and a reconciliation run all converge on the
     * same single payment row.
     *
     * @return array{ok: bool, outcome: string, reason: ?string, payment_id: ?int, policy_id: ?int}
     */
    private function reflectRealpayPayment(
        $clientNumber,
        $contractNumber,
        $rawInstalmentStatus,
        $instalmentReferenceNumber,
        $sequence,
        $amount,
        $actionDate,
        $trackingCode = null,
        $instalmentResponse = null,
        array $payload = []
    ): array {
        try {
            return app(\AlphaDirect\Services\RealpayPaymentRecorder::class)->record([
                'client_number'        => $clientNumber,
                'contract_number'      => $contractNumber,
                'instalment_reference' => $instalmentReferenceNumber,
                'sequence'             => $sequence,
                'instalment_status'    => $rawInstalmentStatus,
                'amount'               => $amount,
                'action_date'          => $actionDate,
                'tracking_code'        => $trackingCode,
                'instalment_response'  => $instalmentResponse,
                'payload'              => $payload['InstalmentGetResponse'][0] ?? $payload,
                'resolved_by'          => 'webhook',
            ]);
        } catch (\Throwable $e) {
            // The recorder handles its own failures; reaching here means
            // something outside it broke. Report unapplied so the buffered
            // webhook is retried rather than consumed.
            Log::error('RealPay payment reflection threw outside the recorder', [
                'client_number' => $clientNumber,
                'reference'     => $instalmentReferenceNumber,
                'error'         => $e->getMessage(),
                'line'          => $e->getLine(),
            ]);

            return ['ok' => false, 'outcome' => 'write_failed', 'reason' => $e->getMessage(), 'payment_id' => null, 'policy_id' => null];
        }
    }

    /**
     * A RealPay instalment arrived that we could not attach to any policy.
     *
     * Both branches below used to write a RealpayWebHookResponses row and then
     * return nothing — an implicit HTTP 200 that told the buffer drain the
     * delivery had been applied. For a collected instalment ('S') that is a
     * customer debited with no payment record and no open item anywhere. This
     * puts it on the exception ledger and reports it unapplied, so it is
     * retried and, if it keeps failing, is visible in the reconciliation report.
     *
     * @return bool true when the caller must treat the delivery as unapplied
     */
    private function recordUnresolvedRealpayInstallment(
        $clientNumber,
        $contractNumber,
        $rawInstalmentStatus,
        $instalmentReferenceNumber,
        $sequence,
        $amount,
        $actionDate,
        array $payload = []
    ): bool {
        $settles = in_array(strtoupper((string) $rawInstalmentStatus), ['S', 'F'], true);

        try {
            app(\AlphaDirect\Services\RealpayPaymentRecorder::class)->recordException([
                'client_number'        => $clientNumber,
                'contract_number'      => $contractNumber,
                'instalment_reference' => $instalmentReferenceNumber,
                'sequence'             => $sequence,
                'instalment_status'    => $rawInstalmentStatus,
                'amount'               => $amount,
                'action_date'          => $actionDate,
                'payload'              => $payload['InstalmentGetResponse'][0] ?? $payload,
            ], \AlphaDirect\RealpayReflectionException::REASON_POLICY_UNRESOLVED,
               'updateInstallment could not resolve a policy for this ClientNumber/ContractNumber.');
        } catch (\Throwable $e) {
            Log::error('RealPay unresolved-instalment ledger write failed', [
                'client_number' => $clientNumber,
                'reference'     => $instalmentReferenceNumber,
                'error'         => $e->getMessage(),
            ]);
        }

        Log::error('[REALPAY] instalment webhook could not be attached to a policy', [
            'client_number'   => $clientNumber,
            'contract_number' => $contractNumber,
            'reference'       => $instalmentReferenceNumber,
            'sequence'        => $sequence,
            'status'          => $rawInstalmentStatus,
            'amount'          => $amount,
            'settles'         => $settles,
        ]);

        return true;
    }

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

            // RealPay's raw InstalmentStatus letter ('S' / 'F' / 'W' / …).
            // `$status` is overwritten with 'SUCCESS' / 'FAILED' further down
            // this method, so the mandate hook takes its copy here.
            $rawInstalmentStatus = $status;

            $clientNumberType = $this->getClientType($clientNumber);

            if (in_array($clientNumberType, ['DOM', 'COM'])) {
                // Use RealpayTransactionsExcelData for DOM/COM
                // $check = RealpayTransactionsExcelData::where('clientnumber', $clientNumber)->first();
                $check = RealpayClientContracts::where('client_number', $clientNumber)->where('contract_number', $contractNumber)->orderBy('id', 'desc')->first();
                
                if ($check) {
                    $check->domgcomg = $check->client_number;
                } else {
                    $check = RealpayContractDetails::where('ClientNumber', $clientNumber)
                        ->where('ContractNumber', $contractNumber)
                        ->orderBy('id', 'desc')
                        ->first();
                
                    if ($check) {
                        $check->domgcomg = $check->ClientNumber;
                    }
                }

                $pc = new PolicyController();
                if($check != null){
                    if(array_key_exists('ResponseCode', $data['InstalmentGetResponse'][0])){
                        if (isset($data['InstalmentGetResponse'][0]['ResponseCode']) && $data['InstalmentGetResponse'][0]['ResponseCode'] != '00')
                            $instalmentResponse = $this->getBankResponseCodes($data['InstalmentGetResponse'][0]['ResponseCode']);
                        else
                            $instalmentResponse = 'Success';
                    }

                    // TrackingCode and InstalmentSequence are deliberately NOT required
                    // here — see realpayDeliveryHasMinimumFields(). Requiring them
                    // dropped collected debits whose payload omitted TrackingCode,
                    // and (via loose `!= null`) any sequence/amount of integer 0.
                    if($this->realpayDeliveryHasMinimumFields(
                        $clientNumber, $contractNumber, $instalmentReferenceNumber,
                        $status, $actionDate, $amount)) {
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
                                $policy = Policy::where('id', $contractNumber)->orWhere('policyNumber',$contractNumber)->orWhere('policyNumber',$check->domgcomg)->first();
                            }else{
                                $policy = Policy::where('id',$clientContracts->policy_id)->first();
                            }

                            $curStatus = $policy->status;

                            if ($curStatus == 0 && $status == 'S') {
                                if($policy->product_id!=3 && $policy->product_id!=5){
                                $policy->status = 1;
                            }else if($policy->product_id==3 || $policy->product_id==5){
                                $chk=PolicyController::checkMotorpolicyStatus($policy->product_id,$policy->id,2);
                                $policy->status             =  $chk;
                                }
                                $policy->save();
                                $update = $pc->updatePolicyDates($policy->policyNumber,1);
                            }
                            elseif ($curStatus != 1 && $status == 'S') {
                                $pc = new PolicyController();
                                $policy->status = $curStatus;
                                $policy->save();
                                $update = $pc->updatePolicyDates($policy->policyNumber,2);
                            }
                            elseif ($status == 'S') { //Keep the status unchanged even if payment is failed: given by KAMLESH to KARTHIK
                                if($policy->product_id!=3 && $policy->product_id!=5){
                                $policy->status = 1;
                                }else if($policy->product_id==3 || $policy->product_id==5){
                                $chk=PolicyController::checkMotorpolicyStatus($policy->product_id,$policy->id,2);
                                $policy->status             =  $chk;
                                }
                                $policy->save();
                                $update = $pc->updatePolicyDates($policy->policyNumber,1);
                            } elseif ($status == 'F') {
                                $policy->status = $curStatus;
                                $policy->save();
                            } else {
                                $policy->status = $curStatus;
                                $policy->save();
                            }

                        } else {
                            // $curStatus was read off $policy one line BEFORE the
                            // `if ($policy != null)` guard below, so an
                            // unresolvable policy threw a TypeError out of the
                            // whole branch and the collection was never recorded.
                            // Read it inside the guard, and resolve on
                            // clientNumber / $check as well before giving up.
                            $policy = Policy::where('id', $contractNumber)
                                ->orWhere('policyNumber', $contractNumber)
                                ->orWhere('policyNumber', $clientNumber)
                                ->orWhere('policyNumber', $check->domgcomg)
                                ->first();

                            if ($policy == null && !empty($check->policy_id)) {
                                $policy = Policy::where('id', $check->policy_id)->first();
                            }

                            if ($policy != null) { //Keep the status unchanged even if payment is failed: given by KAMLESH to KARTHIK
                                $curStatus = $policy->status;
                                if ($curStatus == 0 && $status == 'S') {
                                    if($policy->product_id!=3 && $policy->product_id!=5){
                                    $policy->status = 1;
                                    }else if($policy->product_id==3 || $policy->product_id==5){
                                    $chk=PolicyController::checkMotorpolicyStatus($policy->product_id,$policy->id,2);
                                    $policy->status             =  $chk;
                                    }
                                    $policy->save();
                                    $update = $pc->updatePolicyDates($policy->policyNumber,1);
                                }
                                elseif ($curStatus != 1 && $status == 'S') {
                                    $pc = new PolicyController();
                                    $policy->status = $curStatus;
                                    $policy->save();
                                    $update = $pc->updatePolicyDates($policy->policyNumber,2);
                                }
                                elseif ($status == 'S') {
                                    if($policy->product_id!=3 && $policy->product_id!=5){
                                    $policy->status = 1;
                                    }else if($policy->product_id==3 || $policy->product_id==5){
                                        $chk=PolicyController::checkMotorpolicyStatus($policy->product_id,$policy->id,2);
                                        $policy->status             =  $chk;
                                    }
                                    $policy->save();
                                    $update = $pc->updatePolicyDates($policy->policyNumber,1);
                                } elseif ($status == 'F') {
                                    $policy->status = $curStatus;
                                    $policy->save();
                                } else {
                                    $policy->status = $curStatus;
                                    $policy->save();
                                }
                            }
                        }

                        $trans = Transaction::where('policyNumber', $clientNumber)->orWhere('policyNumber',$check->domgcomg)->first();

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


                            // Reflect the debit into payment_transactions through
                            // the single idempotent writer. See
                            // AlphaDirect\Services\RealpayPaymentRecorder — it
                            // resolves the policy itself (rather than trusting
                            // the raw ClientNumber), writes atomically, and on
                            // failure records a realpay_reflection_exceptions
                            // row so a debited customer is never left with no
                            // record anywhere.
                            $reflection = $this->reflectRealpayPayment(
                                $clientNumber, $contractNumber, $rawInstalmentStatus,
                                $instalmentReferenceNumber, $sequence, $amount, $actionDate,
                                $trackingCode, $instalmentResponse, $data
                            );
                            $saveData = $reflection['ok'];

                            // A failed reflection must NOT be reported to the
                            // buffer drain as applied — a non-2xx keeps the
                            // buffered webhook for retry instead of consuming it.
                            if (!$reflection['ok']) {
                                return response()->json([
                                    'Status'      => 'Failed',
                                    'Description' => 'Instalment received but the Graphite payment could not be recorded: ' . ($reflection['reason'] ?? $reflection['outcome']),
                                ], 500);
                            }

                            $policyUpdate = Policy::where('id',$contractNumber)
                            ->orWhere('policyNumber',$clientNumber)
                            ->orWhere('policyNumber',$contractNumber)
                            ->orWhere('policyNumber',$check->domgcomg)
                            ->first();

                            // CFO 11 PM #10 audit — every RealPay webhook outcome must
                            // surface in the policy's activity log so Finance / Ops can
                            // reconcile "when did RealPay report this debit?". DOM/COM
                            // branch insertion point. Wrapped in try/catch so an audit
                            // failure never breaks the webhook write path (audit is a
                            // side concern; main flow must complete).
                            try {
                                if (isset($policyUpdate) && $policyUpdate) {
                                    activity('RealPay payment ' . strtolower((string) $status))
                                        ->performedOn($policyUpdate)
                                        ->log('RealPay webhook: ' . $status
                                            . ' on installment ' . $instalmentReferenceNumber
                                            . ' seq ' . $sequence
                                            . ' contract ' . $contractNumber
                                            . ' amount P ' . number_format((float) $amount, 2, '.', ''));
                                }
                            } catch (\Throwable $auditEx) {
                                \Log::warning('CFO-10 audit failed in updateInstallment (DOM/COM)', [
                                    'error'          => $auditEx->getMessage(),
                                    'policy_id'      => $policyUpdate->id ?? null,
                                    'instalment_ref' => $instalmentReferenceNumber ?? null,
                                    'status'         => $status ?? null,
                                ]);
                            }

                            if (isset($policyUpdate) && $status == 'SUCCESS' && isset($contractNumber) && $sequence == 1) {
                                if($policyUpdate->policyActivatedDate == null){
                                    $policyUpdate->policyActivatedDate = Carbon::now()->format('Y-m-d');
                                }
                                if($policyUpdate->expiry_date == null){
                                    $policyUpdate->expiry_date = Carbon::now()->addYear()->format('Y-m-d');
                                }
                                $policyUpdate->save();

                                $term = PolicyTerm::where('policy_id',$policyUpdate->id)->where('trans_type','NEW BUSINESS')->where('status','Deactive')->where('term_end_date','>',Carbon::now()->format('Y-m-d'))->orderBy('id','desc')->first();
                                if (isset($term)) {
                                    $term->status = 'Active';
                                    $term->save();
                                }
                            }

                            // Customer notification is a side concern, isolated
                            // from the financial write above. The payment is
                            // already committed at this point; letting an SMS /
                            // email / template failure escape would return a
                            // retryable 500 for a delivery that WAS applied,
                            // so the buffer would re-drive it and eventually
                            // park a correctly-recorded payment as 'failed'.
                            try {
                            if($policyUpdate && $policyUpdate->customer_id && $saveData == true){
                                if($policyUpdate->customer_id) {
                                    $customer = Customer::where('id', $policyUpdate->customer_id)->first(array('cellphone','firstName','lastName'));
                                    if($customer && $customer->cellphone){
                                        $messaging = new SmsMessaging();
                                        if($status=='FAILED') {;
                                            $c = new PolicyController();
                                            $oneTime = $c->generateSendPaymentURL($check->domgcomg, 'Realpay', 'repay',$amount);

                                            if ($oneTime != null) {
                                                $payment = $messaging->sendOneTimePaymentLink(34, $customer->cellphone, $oneTime, $check->domgcomg, $customer->firstName . ' ' . $customer->lastName);
                                            }
                                        }

                                        $response = $messaging->sendPaymentStatusSMS($templateId, $customer->cellphone, $amount, $check->domgcomg);
                                    }

                                    $oneTime = OneTimePaymentURL::where('policyNumber',$check->domgcomg)->orderBy('id','desc')->first();

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
                            } catch (\Throwable $notifyEx) {
                                Log::error('RealPay webhook customer notification failed (payment already recorded)', [
                                    'policy'    => $check->domgcomg ?? null,
                                    'reference' => $instalmentReferenceNumber,
                                    'error'     => $notifyEx->getMessage(),
                                ]);
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
                        $webHookLog->policyNumber = $check->domgcomg;
                        $webHookLog->instalmentSequence = $sequence;
                        $webHookLog->instalmentActionDate = \Carbon\Carbon::parse($actionDate)->format('Y-m-d');
                        $webHookLog->instalmentRefNumber = $instalmentReferenceNumber;
                        $webHookLog->installmentAmount = $amount;

                        if (array_key_exists('ResponseCode', $data['InstalmentGetResponse'][0]))
                            $webHookLog->bankResponse = $this->getBankResponseCodes($data['InstalmentGetResponse'][0]['ResponseCode']);

                        $webHookLog->status = $s;
                        $webHookLog->save();

                        // Mandate bookkeeping (DOM/COM branch). Runs after all of
                        // the policy and instalment work above and changes none
                        // of it — see applyMandateInstalmentOutcome().
                        $this->applyMandateInstalmentOutcome(
                            $clientNumber, $contractNumber, $rawInstalmentStatus,
                            $instalmentReferenceNumber, $sequence, $amount, $actionDate, $data
                        );

                        return response()->json(['Status' => 'Success','Description'=>'Instalment Updated Successfully'], 200);
                    }else{
                        // Incomplete delivery. Previously a bare 401: not a 5xx, so the
                        // buffer drain consumed it, and nothing was ledgered — a debited
                        // customer with no payment row and no trace to reconcile from.
                        // Now ledgered (settling statuses only) and reported as a
                        // retryable 500 so the delivery is retried, not discarded.
                        $this->recordIncompleteRealpayDelivery(
                            $clientNumber, $contractNumber, $rawInstalmentStatus,
                            $instalmentReferenceNumber, $sequence, $amount, $actionDate, $data
                        );

                        return response()->json([
                            'Status'      => 'Failed',
                            'Description' => 'Instalment delivery is missing fields required to record it — logged for reconciliation.',
                        ], 500);
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

                    // Unattached DOM/COM instalment. Previously this fell out of
                    // the method returning nothing — an implicit 200 that let the
                    // buffer drain consume a possibly-collected debit.
                    $this->recordUnresolvedRealpayInstallment(
                        $clientNumber, $contractNumber, $rawInstalmentStatus,
                        $instalmentReferenceNumber, $sequence, $amount, $actionDate, $data
                    );

                    return response()->json([
                        'Status'      => 'Failed',
                        'Description' => 'No RealPay client/contract record matched this instalment — logged for reconciliation.',
                    ], 500);
                }


            } else {
                // Use Policy for MIS
                $check = Policy::where('id', $contractNumber)
                    ->orWhere('policyNumber', $clientNumber)
                    ->orWhere('policyNumber', $contractNumber)
                    ->first(['id', 'policyNumber', 'customer_id']);

                    // $check = Policy::where('id',$contractNumber)
                    //     ->orWhere('policyNumber',$clientNumber)
                    //     ->orWhere('policyNumber',$contractNumber)
                    //     ->first(array('id','policyNumber','customer_id'));

                    $pc = new PolicyController();
                    if($check != null){
                        if(array_key_exists('ResponseCode', $data['InstalmentGetResponse'][0])){
                            if (isset($data['InstalmentGetResponse'][0]['ResponseCode']) && $data['InstalmentGetResponse'][0]['ResponseCode'] != '00')
                                $instalmentResponse = $this->getBankResponseCodes($data['InstalmentGetResponse'][0]['ResponseCode']);
                            else
                                $instalmentResponse = 'Success';
                        }

                        // TrackingCode and InstalmentSequence are deliberately NOT required
                        // here — see realpayDeliveryHasMinimumFields(). Requiring them
                        // dropped collected debits whose payload omitted TrackingCode,
                        // and (via loose `!= null`) any sequence/amount of integer 0.
                        if($this->realpayDeliveryHasMinimumFields(
                            $clientNumber, $contractNumber, $instalmentReferenceNumber,
                            $status, $actionDate, $amount)) {
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

                                if ($curStatus == 0 && $status == 'S') {
                                    if($policy->product_id!=3 && $policy->product_id!=5){
                                    $policy->status = 1;
                                }else if($policy->product_id==3 || $policy->product_id==5){
                                    $chk=PolicyController::checkMotorpolicyStatus($policy->product_id,$policy->id,2);
                                    $policy->status             =  $chk;
                                    }
                                    $policy->save();
                                    $update = $pc->updatePolicyDates($policy->policyNumber,1);
                                }
                                elseif ($curStatus != 1 && $status == 'S') {
                                    $pc = new PolicyController();
                                    $policy->status = $curStatus;
                                    $policy->save();
                                    $update = $pc->updatePolicyDates($policy->policyNumber,2);
                                }
                                elseif ($status == 'S') { //Keep the status unchanged even if payment is failed: given by KAMLESH to KARTHIK
                                    if($policy->product_id!=3 && $policy->product_id!=5){
                                    $policy->status = 1;
                                    }else if($policy->product_id==3 || $policy->product_id==5){
                                    $chk=PolicyController::checkMotorpolicyStatus($policy->product_id,$policy->id,2);
                                    $policy->status             =  $chk;
                                    }
                                    $policy->save();
                                    $update = $pc->updatePolicyDates($policy->policyNumber,1);
                                } elseif ($status == 'F') {
                                    $policy->status = $curStatus;
                                    $policy->save();
                                } else {
                                    $policy->status = $curStatus;
                                    $policy->save();
                                }

                            } else {
                                // Resolve on clientNumber too, and fall back to
                                // $check. For a MIS policy the RealPay
                                // ClientNumber IS the policy number, so a lookup
                                // on contractNumber alone missed it whenever the
                                // contract number was not the policy id — and
                                // $curStatus was then read off a null $policy,
                                // one line BEFORE the `if ($policy != null)`
                                // guard. That threw a TypeError out of the whole
                                // branch, so the collection was never recorded.
                                // $check was already resolved above and holds
                                // the right policy, so it is the last resort.
                                $policy = Policy::where('id', $contractNumber)
                                    ->orWhere('policyNumber', $contractNumber)
                                    ->orWhere('policyNumber', $clientNumber)
                                    ->first();

                                if ($policy == null && $check != null) {
                                    $policy = Policy::where('id', $check->id)->first();
                                }

                                if ($policy != null) { //Keep the status unchanged even if payment is failed: given by KAMLESH to KARTHIK
                                    $curStatus = $policy->status;
                                    if ($curStatus == 0 && $status == 'S') {
                                        if($policy->product_id!=3 && $policy->product_id!=5){
                                        $policy->status = 1;
                                        }else if($policy->product_id==3 || $policy->product_id==5){
                                        $chk=PolicyController::checkMotorpolicyStatus($policy->product_id,$policy->id,2);
                                        $policy->status             =  $chk;
                                        }
                                        $policy->save();
                                        $update = $pc->updatePolicyDates($policy->policyNumber,1);
                                    }
                                    elseif ($curStatus != 1 && $status == 'S') {
                                        $pc = new PolicyController();
                                        $policy->status = $curStatus;
                                        $policy->save();
                                        $update = $pc->updatePolicyDates($policy->policyNumber,2);
                                    }
                                    elseif ($status == 'S') {
                                        if($policy->product_id!=3 && $policy->product_id!=5){
                                        $policy->status = 1;
                                        }else if($policy->product_id==3 || $policy->product_id==5){
                                            $chk=PolicyController::checkMotorpolicyStatus($policy->product_id,$policy->id,2);
                                            $policy->status             =  $chk;
                                        }
                                        $policy->save();
                                        $update = $pc->updatePolicyDates($policy->policyNumber,1);
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


                                // Same single idempotent writer as the DOM/COM
                                // branch. This is the branch MIS / instant
                                // products run on, and the branch where
                                // `$paymentData['policyNumber'] = $clientNumber`
                                // dropped payments whenever the RealPay
                                // ClientNumber was not literally a policyNumber.
                                $reflection = $this->reflectRealpayPayment(
                                    $clientNumber, $contractNumber, $rawInstalmentStatus,
                                    $instalmentReferenceNumber, $sequence, $amount, $actionDate,
                                    $trackingCode, $instalmentResponse, $data
                                );
                                $saveData = $reflection['ok'];

                                if (!$reflection['ok']) {
                                    return response()->json([
                                        'Status'      => 'Failed',
                                        'Description' => 'Instalment received but the Graphite payment could not be recorded: ' . ($reflection['reason'] ?? $reflection['outcome']),
                                    ], 500);
                                }

                                $policyUpdate = Policy::where('id',$contractNumber)
                                ->orWhere('policyNumber',$clientNumber)
                                ->orWhere('policyNumber',$contractNumber)
                                ->first();

                                // CFO 11 PM #10 audit — parallel insertion to DOM/COM
                                // branch above; non-DOM/COM client types reach this code
                                // path. Same try/catch isolation: audit failure must not
                                // break the webhook write.
                                try {
                                    if (isset($policyUpdate) && $policyUpdate) {
                                        activity('RealPay payment ' . strtolower((string) $status))
                                            ->performedOn($policyUpdate)
                                            ->log('RealPay webhook: ' . $status
                                                . ' on installment ' . $instalmentReferenceNumber
                                                . ' seq ' . $sequence
                                                . ' contract ' . $contractNumber
                                                . ' amount P ' . number_format((float) $amount, 2, '.', ''));
                                    }
                                } catch (\Throwable $auditEx) {
                                    \Log::warning('CFO-10 audit failed in updateInstallment (non-DOM/COM)', [
                                        'error'          => $auditEx->getMessage(),
                                        'policy_id'      => $policyUpdate->id ?? null,
                                        'instalment_ref' => $instalmentReferenceNumber ?? null,
                                        'status'         => $status ?? null,
                                    ]);
                                }

                                if (isset($policyUpdate) && $status == 'SUCCESS' && isset($contractNumber) && $sequence == 1) {
                                    if($policyUpdate->policyActivatedDate == null){
                                        $policyUpdate->policyActivatedDate = Carbon::now()->format('Y-m-d');
                                    }
                                    if($policyUpdate->expiry_date == null){
                                        $policyUpdate->expiry_date = Carbon::now()->addYear()->format('Y-m-d');
                                    }
                                    $policyUpdate->save();

                                    $term = PolicyTerm::where('policy_id',$policyUpdate->id)->where('trans_type','NEW BUSINESS')->where('status','Deactive')->where('term_end_date','>',Carbon::now()->format('Y-m-d'))->orderBy('id','desc')->first();
                                    if (isset($term)) {
                                        $term->status = 'Active';
                                        $term->save();
                                    }
                                }

                                // Same isolation as the DOM/COM branch: the
                                // payment is committed, so a notification
                                // failure must not make the delivery look
                                // unapplied and send it back round the buffer.
                                try {
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

                                // Failed recurring collection -> notify the customer (MIS only).
                                // Sits outside the `$saveData == true` block above so the SMS
                                // does not depend on updatePaymentTransactions() having written
                                // a row. The notifier drops non-MIS policy numbers and is keyed
                                // on (RealPay, instalment ref + sequence), so the every-minute
                                // ProcessWebhookBuffer replay of this same debit sends nothing.
                                if ($status == 'FAILED') {
                                    app(\AlphaDirect\Services\MisRecurringPaymentFailureNotifier::class)->notify(
                                        (string) (($check && $check->policyNumber) ? $check->policyNumber : $clientNumber),
                                        \AlphaDirect\Services\MisRecurringPaymentFailureNotifier::GATEWAY_REALPAY,
                                        \AlphaDirect\Services\MisRecurringPaymentFailureNotifier::realpayEventReference($instalmentReferenceNumber, $sequence)
                                    );
                                }
                                } catch (\Throwable $notifyEx) {
                                    Log::error('RealPay webhook customer notification failed (payment already recorded)', [
                                        'policy'    => $clientNumber,
                                        'reference' => $instalmentReferenceNumber,
                                        'error'     => $notifyEx->getMessage(),
                                    ]);
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

                            // Mandate bookkeeping (MIS / MIB / BUN / ZAIS branch —
                            // the path the instant and South African products run
                            // on). Same contract as the DOM/COM call above.
                            $this->applyMandateInstalmentOutcome(
                                $clientNumber, $contractNumber, $rawInstalmentStatus,
                                $instalmentReferenceNumber, $sequence, $amount, $actionDate, $data
                            );

                            return response()->json(['Status' => 'Success','Description'=>'Instalment Updated Successfully'], 200);
                        }else{
                            // Incomplete delivery. Previously a bare 401: not a 5xx, so the
                            // buffer drain consumed it, and nothing was ledgered — a debited
                            // customer with no payment row and no trace to reconcile from.
                            // Now ledgered (settling statuses only) and reported as a
                            // retryable 500 so the delivery is retried, not discarded.
                            $this->recordIncompleteRealpayDelivery(
                                $clientNumber, $contractNumber, $rawInstalmentStatus,
                                $instalmentReferenceNumber, $sequence, $amount, $actionDate, $data
                            );

                            return response()->json([
                                'Status'      => 'Failed',
                                'Description' => 'Instalment delivery is missing fields required to record it — logged for reconciliation.',
                            ], 500);
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

                        // Unattached MIS / instant instalment — the branch the
                        // affected policies run on. Same treatment as DOM/COM
                        // above: ledger it and report unapplied so the delivery
                        // is retried instead of silently consumed.
                        $this->recordUnresolvedRealpayInstallment(
                            $clientNumber, $contractNumber, $rawInstalmentStatus,
                            $instalmentReferenceNumber, $sequence, $amount, $actionDate, $data
                        );

                        return response()->json([
                            'Status'      => 'Failed',
                            'Description' => 'No policy matched this instalment — logged for reconciliation.',
                        ], 500);
                    }

            }

        } catch (\Throwable $e) {
            // \Throwable, not \Exception: a null-property dereference or a type
            // error inside this method is an Error, which \Exception does not
            // catch — it escaped to the framework and produced a 500 that the
            // old buffer drain then mis-read. Everything is caught here and
            // reported as a retryable 500 so the delivery is never consumed.
            Log::error('RealPay updateInstallment failed', [
                'client_number'   => $data['InstalmentGetResponse'][0]['ClientNumber'] ?? null,
                'contract_number' => $data['InstalmentGetResponse'][0]['ContractNumber'] ?? null,
                'reference'       => $data['InstalmentGetResponse'][0]['InstalmentReferenceNumber'] ?? null,
                'status'          => $data['InstalmentGetResponse'][0]['InstalmentStatus'] ?? null,
                'error'           => $e->getMessage(),
                'line'            => $e->getLine(),
                'file'            => $e->getFile(),
            ]);

            // A collected instalment that threw partway through must leave a
            // trace — otherwise this is exactly the "debited, no transaction"
            // case with nothing to reconcile from.
            try {
                $ins = $data['InstalmentGetResponse'][0] ?? [];
                if (!empty($ins['InstalmentReferenceNumber'])) {
                    app(\AlphaDirect\Services\RealpayPaymentRecorder::class)->recordException([
                        'client_number'        => $ins['ClientNumber'] ?? null,
                        'contract_number'      => $ins['ContractNumber'] ?? null,
                        'instalment_reference' => $ins['InstalmentReferenceNumber'],
                        'sequence'             => $ins['InstalmentSequence'] ?? null,
                        'instalment_status'    => $ins['InstalmentStatus'] ?? null,
                        'amount'               => $ins['InstalmentAmount'] ?? null,
                        'action_date'          => $ins['InstalmentActionDate'] ?? null,
                        'payload'              => $ins,
                    ], \AlphaDirect\RealpayReflectionException::REASON_EXCEPTION,
                       $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
                }
            } catch (\Throwable $ledgerEx) {
                Log::error('RealPay updateInstallment: exception ledger write also failed', [
                    'error' => $ledgerEx->getMessage(),
                ]);
            }

            return response()->json(['Status' => 'Failed', 'description' => $e->getMessage().' '.$e->getLine()], 500);
        }
    }

    public function fetchClient(Request $request){
        try{
            $fetchToken = $this->clientAuth();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.base_url')."/maintain/clients/".config('realpay.product')."?ClientNumber=".$request->clientNumber."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.base_url').'/general/banks/FNBNDOBW?BeneficiaryUser=16244&Version=v1',
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
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.base_url')."/maintain/clients/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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
            $AccountNumber= $data['accountNumber'];

            $fetchToken = $this->clientAuth();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                dd('Please clear cache');
            //return null;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.base_url')."/maintain/clients/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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
                $error = unserialize($data->response, ['allowed_classes' => false]);
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
            $clientContracts = RealpayClientContracts::where('policy_id',$request->policy_id)->where('status',1)->orderBy('id','desc')->first();

            if($clientContracts == null){
                $data = RealpayContractInstallments::where('contractNumber',$request->policy_id)->get();
            }else{
                $data = RealpayContractInstallments::where('contractNumber',$clientContracts->contract_number)->get();
            }

            return DataTables::of($data)
                ->editColumn('action', function ($data) {
                    $policyProd = Policy::where('policyNumber',$data->clientNumber)->first('product_id');

                    switch ($data->InstalmentStatus) {
                        case 'S':
                            $actions = '<span class="kt-font-bold kt-font-success">Success</span>';
                            break;
                        case 'W':
                            $actions = '<span class="kt-font-bold kt-font-info">Processing</span>';
                            break;

                        case 'F':
                            $d = $data->toArray();
                            if ($policyProd->product_id == 3) {
                                $actions = '<a href="' . route('admin.updateStatus',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'R']) . '" class="btn btn-sm btn-elevate btn-warning btn-elevate" title="Cancel Instalment">
                                <span class="kt-opacity-11" id="">Retry</span>
                                </a>';
                            } else {
                                $actions = '<a href="' . route('admin.updateStatusForInstantProduct',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'R']) . '" class="btn btn-sm btn-elevate btn-warning btn-elevate" title="Cancel Instalment">
                                <span class="kt-opacity-11" id="">Retry</span>
                                </a>';
                            }
                            break;
                        case 'R':
                            $d = $data->toArray();
                            if ($policyProd->product_id == 3) {
                                $actions = '<a href="' . route('admin.updateStatus',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'I']) . '" class="btn btn-sm btn-elevate btn-danger btn-elevate" title="Cancel Instalment">
                                <span class="kt-opacity-11" id="">Cancel</span>
                                </a>';
                            } else {
                                $actions = '<a href="' . route('admin.updateStatusForInstantProduct',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'I']) . '" class="btn btn-sm btn-elevate btn-danger btn-elevate" title="Cancel Instalment">
                                <span class="kt-opacity-11" id="">Cancel</span>
                                </a>';
                            }
                            break;
                        case 'A':
                            $d = $data->toArray();
                            if ($policyProd->product_id == 3) {
                                $actions = '<a href="' . route('admin.updateStatus',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'I']) . '" value="'.$d['InstalmentReferenceNumber'].'" class="btn btn-sm btn-elevate btn-danger btn-elevate confirm-cancel" title="Cancel Instalment">
                                <span class="kt-opacity-11" id="">Cancel</span>
                                </a>';
                            } else {
                                $actions = '<a href="' . route('admin.updateStatusForInstantProduct',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'I']) . '" value="'.$d['InstalmentReferenceNumber'].'" class="btn btn-sm btn-elevate btn-danger btn-elevate confirm-cancel" title="Cancel Instalment">
                                <span class="kt-opacity-11" id="">Cancel</span>
                                </a>';
                            }
                            break;
                        case 'I':
                            $actions = '<span class="kt-font-bold kt-font-info">Cancelled</span>';
                            break;
                        case 'E':
                            $d = $data->toArray();
                            if ($policyProd->product_id == 3) {
                                $actions = '<a href="' . route('admin.updateStatus',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'R']) . '" class="btn btn-sm btn-elevate btn-warning btn-elevate" title="Cancel Instalment">
                                <span class="kt-opacity-11" id="">Retry</span>
                                </a>';
                            } else {
                                $actions = '<a href="' . route('admin.updateStatusForInstantProduct',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'R']) . '" class="btn btn-sm btn-elevate btn-warning btn-elevate" title="Cancel Instalment">
                                <span class="kt-opacity-11" id="">Retry</span>
                                </a>';
                            }
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


            $request = new Request();
            $request['clientNumber'] = isset($ins->clientNumber) ? $ins->clientNumber : null;

            $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
            $getcontract = $realpay->getContractInfoForMotorComp($request);

            if (!isset($getcontract)) {
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
                    CURLOPT_URL => config('realpay.base_url')."/maintain/instalments/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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

            } else{
                $Token = $this->clientAuthForMotorComp();

                if($Token['token_type'] != ''  && $Token['access_token'] != ''){
                    $fetch_token = $Token['token_type'].' '.$Token['access_token'];
                }else{
                    return Redirect::back()->with('error', 'Auth key not found');
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
                    CURLOPT_URL => config('realpay.start.base_url')."/maintain/instalments/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version'),
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
                        "Authorization: ".$fetch_token
                    ),
                ));

                $response = curl_exec($curl);
                $data = json_decode($response,true);

            }

            if(!empty($data['InstalmentPutResponse'][0]['Successful']) && empty($data['InstalmentPutResponse'][0]['Failed'])){
                if($s == 'R') {
                    $ins->retry_count = $ins->retry_count + 1;
                    $ins->save();
                }


                activity('Realpay Installments')
                ->performedOn($ins)
                ->causedBy(User::where('id', auth()->user()->id)->first())
                ->log('Updated Realpay Installment status');

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
                    $vendors = PaymentVendor::where('status', '1')->get();
                    if ($vendors == NULL)
                    {
                       $paymentvender = [];
                    }
                    else
                    {
                       $paymentvender = $vendors;
                    }
                    return response()->json(['Status' => 'Success','PolicyData'=>$policyData,'paymentvender'=>$paymentvender], 200);
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
                                                                $nDays = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
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
                                                            $nDays = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
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
//                                                    $nDays = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
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
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.base_url')."/maintain/instalments/".config('realpay.product')."?ClientNumber=".$policy->policyNumber."&ContractNumber=".$policy->id."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
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
                CURLOPT_URL => config('realpay.base_url')."/maintain/instalments/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
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
                CURLOPT_URL => config('realpay.base_url')."/maintain/instalments/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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
                        if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
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
                            CURLOPT_URL => config('realpay.base_url')."/maintain/clients/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
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
                    $nDays = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
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
                CURLOPT_URL => config('realpay.base_url')."/maintain/instalments/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.base_url')."/maintain/instalments/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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
                    CURLOPT_URL => config('realpay.base_url') . "/maintain/contracts/" . $data->product . "?ClientNumber=" . $data->client_number . "&ContractNumber=" . $data->contract_number . "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version'),
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
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
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
                CURLOPT_URL => config('realpay.base_url')."/maintain/instalments/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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
            $day = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
        }

        return $year.'-'.$month.'-'.$day;
    }

    public function checkBankBranchesRealPay(){
        try{
            $fetchToken = $this->clientAuth();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.base_url').'/general/banks/FNBNDOBW?BeneficiaryUser=16244&Version=v1',
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
                    CURLOPT_URL => config('realpay.base_url') . "/maintain/contracts/" . $request->product . "?ClientNumber=" . $request->clientNumber . "&ContractNumber=" . $request->contractNumber . "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version'),
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
                    CURLOPT_URL => "https://realpaycollect.com:4448/rpp/rpws/maintain/contracts/" . $request->product . "?ClientNumber=" . $request->clientNumber . "&ContractNumber=" . $request->contractNumber . "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version'),
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
                if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                    $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
                else
                    Log::error($ins->clientNumber.' : Failed to get token');

                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => config('realpay.base_url')."/maintain/instalments/".config('realpay.product')."?ClientNumber=".$contract->ClientNumber."&ContractNumber=".$contract->ContractNumber."&ContractSequence=".$contract->ContractSequence."&InstalmentSequence=".$ins->InstalmentSequence."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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

                            // NOTE: tx-creation intentionally NOT done here. This method
                            // (updateRealpayPaymentStatus) is invoked only by getRealpayData()
                            // whose date window is hardcoded to 2021 and is not scheduled, so a
                            // tx-build here cannot fix current collections and would risk a bulk
                            // LLM/customer-confirmation blast. The canonical source->tx healing
                            // for RealPay is the full-book `UpdateTxLogFromRealpay:cron` backstop
                            // (idempotent upsert by referenceNumber) — rely on that instead.

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
                    if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
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
                        CURLOPT_URL => config('realpay.base_url')."/maintain/clients/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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

            $policy = Policy::where('id', $request->policy_id)->first();

            if ($policy->product_id == 3) {
                $fetchToken = $this->clientAuth();
            } else {
                $fetchToken = $this->clientAuthForMotorComp();
            }

            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $url = '';
            if ($policy->product_id == 3) {
                $url = config('realpay.base_url')."/maintain/clients/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
            } else {
                $url =config('realpay.start.base_url')."/maintain/clients/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
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

            if (isset($clientData['ClientGetResponse']) && $clientData['ClientGetResponse'] != NULL) {
                $url = '';
                if ($policy->product_id == 3) {
                    $url = config('realpay.base_url') . "/maintain/contracts/" . config('realpay.product') . "?ClientNumber=" . $request->clientNumber . "&ContractNumber=" . $request->contractNumber ."&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version');
                } else {
                    $url = config('realpay.start.base_url') . "/maintain/contracts/" . config('realpay.start.product') . "?ClientNumber=" . $request->clientNumber . "&ContractNumber=" . $request->contractNumber ."&BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version');
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
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.base_url') . "/maintain/clients/" . config('realpay.product') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version'),
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
                    CURLOPT_URL => config('realpay.base_url') . "/maintain/contracts/" . config('realpay.product') . "?ClientNumber=" . $request->clientNumber . "&ContractNumber=" . $request->contractNumber ."&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version'),
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
                if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                    $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
                else
                    return null;

                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => config('realpay.base_url') . "/maintain/clients/" . config('realpay.product') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version'),
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
                        CURLOPT_URL => config('realpay.base_url') . "/maintain/contracts/" . config('realpay.product') . "?ClientNumber=" . $request->clientNumber ."&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version'),
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

    public function getContractInfoForMotorComp(Request $request){
        try {
            $fetchToken = $this->clientAuthForMotorComp();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;


            if (isset($request->policy_id)) {
                $policy = Policy::where('id',$request->policy_id)->first();
            } else {
                $policy = Policy::where('policyNumber',$request->policy_number)->first();
            }

            $customerBanking = $this->resolvePolicyCustomerBanking($policy);

            $url = '';
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $url = config('realpay.start.base_url') . "/maintain/clients/" . config('realpay.fnb_product') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version');
            } else {
                $url = config('realpay.start.base_url') . "/maintain/clients/" . config('realpay.start.product') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version');
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
            //dd($clientData);
            if (isset($clientData['ClientGetResponse']) && $clientData['ClientGetResponse'] != NULL) {

                $url = '';
                if (isset($customerBanking) && $customerBanking->bankName == 12) {
                    $url = config('realpay.start.base_url') . "/maintain/contracts/" . config('realpay.fnb_product') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version');
                } else {
                    $url = config('realpay.start.base_url') . "/maintain/contracts/" . config('realpay.start.product') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version');
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

    public function getContractInfo(Request $request){
        try {
            $fetchToken = $this->clientAuth();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $policy = Policy::where('policyNumber',$request->policy_number)->first();
            $customerBanking = $this->resolvePolicyCustomerBanking($policy);

            $url = '';
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $url = config('realpay.base_url') . "/maintain/clients/" . config('realpay.fnb_product') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version');
            } else {
                $url = config('realpay.base_url') . "/maintain/clients/" . config('realpay.product') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version');
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
                    $url = config('realpay.base_url') . "/maintain/contracts/" . config('realpay.fnb_product') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version');
                } else {
                    $url = config('realpay.base_url') . "/maintain/contracts/" . config('realpay.product') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version');
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

                        // if($clientNumber == null) {
                            $clientNumber = $this->cancelRealpayContractsForInstProduct($policyId);
                        // }

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
            //Log::info('1: '.json_encode($checkPayment));

            if (isset($checkPayment)) {
                $policy = Policy::where('id',$policyId)->first(array('policyNumber'));
                //Cancel existing payment if exists

                switch ($checkPayment){
                    case 'RealPay' :
                        $clientNumber = $this->cancelRealpayContract($policyId);

                        // if($clientNumber == null) {
                            $clientNumber = $this->cancelRealpayContractsForInstProduct($policyId);
                        // }

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
            //Log::info('2: '.json_encode($request));
            $policy = Policy::where('id',$request->policyID)->orderBy('id','DESC')->first();
            $customer = Customer::where('id', $policy->customer_id)->with('profile')->first();
            $profile = CustomerProfile::where('customer_id',$customer->id)->first();

            $customerBanking = $this->resolvePolicyCustomerBanking($policy);

            if($customerBanking == null){
                $customerBanking = new CustomerBanking();
            }

            if ($policy->product_id == 3) {
                $fetchToken = $this->clientAuth();
            } else {
                $fetchToken = $this->clientAuthForMotorComp();
            }

            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
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


            if (isset($request->bankName)) {
                $request['BankCode'] = $request->bankName;
            }

            if (isset($request->branchCode)) {
                $request['BranchCode'] = $request->branchCode;
            }

            if (isset($request->bankAccountType)) {
                $request['accountType'] = $request->bankAccountType;
            }

            if (!isset($request->billingDay)) {
                $request['billingDay'] = Carbon::now()->format('Y-m-d');
            }


            $curl = curl_init();

            $url = '';
            $checkClient = '';

            if ($policy->product_id == 3) {
                $checkClient = $this->checkClientExists($policy->id);

                if (isset($request->BankCode) && $request->BankCode == 12) {
                    $url = config('realpay.base_url')."/maintain/clients/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
                } else {
                    $url = config('realpay.base_url')."/maintain/clients/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
                }
            } else {
                $checkClient = $this->checkClientExistsForMotorComp($policy->id);

                if (isset($request->BankCode) && $request->BankCode == 12) {
                    $url = config('realpay.start.base_url')."/maintain/clients/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
                } else {
                    $url = config('realpay.start.base_url')."/maintain/clients/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
                }

            }
            //Log::info('3: '.json_encode($url));

            if($checkClient == false){
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
                    CURLOPT_URL => $url,
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


            if ($policy->product_id == 3) {
                $fetchToken = $this->clientAuth();
            } else {
                $fetchToken = $this->clientAuthForMotorComp();
            }

            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
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

            $url = '';
            $trackingCode = "44";
            if ($policy->product_id == 3) {
                if (isset($request->BankCode) && $request->BankCode == 12) {
                    $trackingCode = "B3";
                    $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
                } else {
                    $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
                }
            } else {
                if (isset($request->BankCode) && $request->BankCode == 12) {
                    $trackingCode = "B3";
                    $url = config('realpay.start.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
                } else {
                    $url = config('realpay.start.base_url')."/maintain/contracts/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
                }
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

                        // if($clientNumber == null) {
                            $clientNumber = $this->cancelRealpayContractsForInstProduct($policyId);
                        // }

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
            $customerBanking = $this->resolvePolicyCustomerBanking($policy);
            $renewal = PolicyRenewal::where('policyNumber',$policy->policyNumber)->orderBy('id', 'desc')->first();
            $data = PolicyDiscountSurcharge::where('policy_id',$policy->id)->orderBy('id','desc')->first('new_value');

            // $expired_policies_import = ExpiredPoliciesImportJobs::where('policyNumber',$policy->policyNumber)->where('is_renewed',0)->where('renew_completed',0)->where('can_expired',0)->first();

            // if (isset($expired_policies_import)) {
            //     $request['premium'] = $expired_policies_import->new_premium;
            // } else {
                if (isset($data)) {
                    $request['premium'] = $data->new_value;
                } elseif (isset($renewal) && isset($renewal->new_premium)) {
                    $request['premium'] = $renewal->new_premium;
                } else {
                    $request['premium'] = $policy->premium;
                }
            // }


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

            if ($policy->product_id == 3) {
                $fetchToken = $this->clientAuth();
            } else {
                $fetchToken = $this->clientAuthForMotorComp();
            }

            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
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

            $url = '';
            $checkClient = '';

            if ($policy->product_id == 3) {
                $checkClient = $this->checkClientExists($policy->id);

                if (isset($request->BankName) && $request->BankName == 12) {
                    $url = config('realpay.base_url')."/maintain/clients/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
                } else {
                    $url = config('realpay.base_url')."/maintain/clients/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
                }

            } else {
                $checkClient = $this->checkClientExistsForMotorComp($policy->id);

                if (isset($request->BankName) && $request->BankName == 12) {
                    $url =config('realpay.start.base_url')."/maintain/clients/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
                } else {
                    $url =config('realpay.start.base_url')."/maintain/clients/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
                }
            }

            if($checkClient == false){
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
                    CURLOPT_URL => $url,
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

            if ($policy->product_id == 3) {
                $fetchToken = $this->clientAuth();
            } else {
                $fetchToken = $this->clientAuthForMotorComp();
            }

            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
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


            if($request->frequency != null && $policy->product_id == 3){

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
                    if (isset($request->name) && $request->name == "Reinstate") {
                        $premium = $request->premium;
                        $firstCollectionAmount = $request->first_premium;

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

            if($policy->product_id == 3){
                $firstcollDate = isset($request->first_collection_date) ? $request->first_collection_date : $request->billingDay;
            } else {
                $firstcollDate = $request->billingDay;
            }

            $contractNumber = RealpayClientContracts::getContractNumber($policy->id);

            $url = '';
            $trackingCode = "44";
            if ($policy->product_id == 3) {
                if (isset($request->BankName) && $request->BankName == 12) {
                    $trackingCode = "B3";
                    $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
                } else {
                    $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
                }
            } else {
                if (isset($request->BankName) && $request->BankName == 12) {
                    $trackingCode = "B3";
                    $url = config('realpay.start.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
                } else {
                    $url = config('realpay.start.base_url')."/maintain/contracts/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
                }
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
            Log::info("cancel real".$contract);
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

    public function storeAndGetContractInfo($id)
    {
        try {
            $client = RealpayPaymentRequest::where('policy_id', $id)->first(array('clientNumber'));
            $realpayContracts = null;
            $realpayContracts = RealpayClientContracts::where('policy_id',$id)->get();

            if (!isset($realpayContracts)) {

                $policy = Policy::where('id',$id)->first();

                $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();

                $request = new Request();
                $request['clientNumber'] = isset($policy->policyNumber) ? $policy->policyNumber : null;

                $getcontract = $realpay->getContractInfoForMotorComp($request);

                if (!isset($getcontract) && $policy->product_id == 3) {
                    $fetchToken = $this->clientAuth();

                    if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                        $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
                    else
                        return null;

                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => config('realpay.base_url') . "/maintain/clients/" . config('realpay.product') . "?ClientNumber=" . $policy->policyNumber . "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version'),
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

                    if (isset($clientData['ClientGetResponse'][0]) && $clientData['ClientGetResponse'][0] != NULL) {
                        $payRequest = new RealpayPaymentRequest();
                        $payRequest->policy_id = $policy->id;
                        $payRequest->clientNumber = $policy->policyNumber;
                        $payRequest->client_response_sequence = $clientData['APIResponse']['CallSequence'];
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

                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                            CURLOPT_URL => config('realpay.base_url') . "/maintain/contracts/" . config('realpay.product') . "?ClientNumber=" . $policy->policyNumber . "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version'),
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
                            foreach ($contractData['ContractGetResponse'] as $key => $contract) {
                                $update = RealpayPaymentRequest::where('policy_id', $id)->first();
                                if (isset($update)) {
                                    $update->contract = $contract['ContractNumber'];
                                    $update->contract_response_sequence = $contractData['APIResponse']['CallSequence'];
                                    $update->status = 1;
                                    $update->contractCreated = 1;
                                    $update->save();
                                }
                                if(sizeof($contract['ContractInstalments']) > 0){
                                    $contract['policy_id'] = $id;
                                    $contractStore = $this->storeClientContractDetails($contract);

                                    $realpayContractDetails = RealpayContractDetails::where('ClientNumber',$contract['ClientNumber'])->get();
                                    if (!isset($realpayContractDetails)) {
                                        $contractStore = $this->storeContractDetails($contract);
                                    }

                                    $realpayInstal = RealpayContractInstallments::where('clientNumber',$contract['ClientNumber'])->get();
                                    if (!isset($realpayInstal)) {
                                        $installments = $this->storeInstallments($contract);
                                    }
                                }
                            }
                        }
                    }

                } else {
                    $fetchToken = $this->clientAuthForInstantProduct();

                    if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                        $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
                    else
                        return null;

                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => config('realpay.start.base_url') . "/maintain/clients/" . config('realpay.start.product') . "?ClientNumber=" . $policy->policyNumber . "&BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version'),
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

                    if (isset($clientData['ClientGetResponse'][0]) && $clientData['ClientGetResponse'][0] != NULL) {
                        $payRequest = new RealpayPaymentRequest();
                        $payRequest->policy_id = $policy->id;
                        $payRequest->clientNumber = $policy->policyNumber;
                        $payRequest->client_response_sequence = $clientData['APIResponse']['CallSequence'];
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

                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                            CURLOPT_URL => config('realpay.start.base_url') . "/maintain/contracts/" . config('realpay.start.product') . "?ClientNumber=" . $policy->policyNumber . "&BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version'),
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
                            foreach ($contractData['ContractGetResponse'] as $key => $contract) {
                                $update = RealpayPaymentRequest::where('policy_id', $id)->first();
                                if (isset($update)) {
                                    $update->contract = $contract['ContractNumber'];
                                    $update->contract_response_sequence = $contractData['APIResponse']['CallSequence'];
                                    $update->status = 1;
                                    $update->contractCreated = 1;
                                    $update->save();
                                }
                                if(sizeof($contract['ContractInstalments']) > 0){
                                    $contract['policy_id'] = $id;
                                    $contractStore = $this->storeClientContractDetails($contract);

                                    $realpayContractDetails = RealpayContractDetails::where('ClientNumber',$contract['ClientNumber'])->get();
                                    if (!isset($realpayContractDetails)) {
                                        $contractStore = $this->storeContractDetails($contract);
                                    }

                                    $realpayInstal = RealpayContractInstallments::where('clientNumber',$contract['ClientNumber'])->get();
                                    if (!isset($realpayInstal)) {
                                        $installments = $this->storeInstallments($contract);
                                    }
                                }
                            }
                        }
                    }
                }

                $realpayContracts = RealpayClientContracts::where('policy_id',$id)->get();
            }

            return $realpayContracts;


        } catch (\Exception $ex) {
            return $ex;
        }
    }


    public function getClientContractList($id )
    {
        try {
            $client = RealpayPaymentRequest::where('policy_id', $id)->first(array('clientNumber'));
            $realpayContracts = null;
            $realpayContracts = RealpayClientContracts::where('policy_id',$id)->get();

            if (!isset($realpayContracts)) {

                $policy = Policy::where('id',$id)->first();

                $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();

                $request = new Request();
                $request['clientNumber'] = isset($policy->policyNumber) ? $policy->policyNumber : null;

                $getcontract = $realpay->getContractInfoForMotorComp($request);

                if (!isset($getcontract) && $policy->product_id == 3) {
                    $fetchToken = $this->clientAuth();

                    if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                        $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
                    else
                        return null;

                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => config('realpay.base_url') . "/maintain/clients/" . config('realpay.product') . "?ClientNumber=" . $policy->policyNumber . "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version'),
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

                    if (isset($clientData['ClientGetResponse'][0]) && $clientData['ClientGetResponse'][0] != NULL) {
                        $payRequest = new RealpayPaymentRequest();
                        $payRequest->policy_id = $policy->id;
                        $payRequest->clientNumber = $policy->policyNumber;
                        $payRequest->client_response_sequence = $clientData['APIResponse']['CallSequence'];
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

                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                            CURLOPT_URL => config('realpay.base_url') . "/maintain/contracts/" . config('realpay.product') . "?ClientNumber=" . $policy->policyNumber . "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version'),
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
                            foreach ($contractData['ContractGetResponse'] as $key => $contract) {
                                $update = RealpayPaymentRequest::where('policy_id', $id)->first();
                                if (isset($update)) {
                                    $update->contract = $contract['ContractNumber'];
                                    $update->contract_response_sequence = $contractData['APIResponse']['CallSequence'];
                                    $update->status = 1;
                                    $update->contractCreated = 1;
                                    $update->save();
                                }
                                if(sizeof($contract['ContractInstalments']) > 0){
                                    $contract['policy_id'] = $id;
                                    $contractStore = $this->storeClientContractDetails($contract);

                                    $realpayContractDetails = RealpayContractDetails::where('ClientNumber',$contract['ClientNumber'])->get();
                                    if (!isset($realpayContractDetails)) {
                                        $contractStore = $this->storeContractDetails($contract);
                                    }

                                    $realpayInstal = RealpayContractInstallments::where('clientNumber',$contract['ClientNumber'])->get();
                                    if (!isset($realpayInstal)) {
                                        $installments = $this->storeInstallments($contract);
                                    }
                                }
                            }
                        }
                    }

                } else {
                    $fetchToken = $this->clientAuthForInstantProduct();

                    if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                        $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
                    else
                        return null;

                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => config('realpay.start.base_url') . "/maintain/clients/" . config('realpay.start.product') . "?ClientNumber=" . $policy->policyNumber . "&BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version'),
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

                    if (isset($clientData['ClientGetResponse'][0]) && $clientData['ClientGetResponse'][0] != NULL) {
                        $payRequest = new RealpayPaymentRequest();
                        $payRequest->policy_id = $policy->id;
                        $payRequest->clientNumber = $policy->policyNumber;
                        $payRequest->client_response_sequence = $clientData['APIResponse']['CallSequence'];
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

                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                            CURLOPT_URL => config('realpay.start.base_url') . "/maintain/contracts/" . config('realpay.start.product') . "?ClientNumber=" . $policy->policyNumber . "&BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version'),
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
                            foreach ($contractData['ContractGetResponse'] as $key => $contract) {
                                $update = RealpayPaymentRequest::where('policy_id', $id)->first();
                                if (isset($update)) {
                                    $update->contract = $contract['ContractNumber'];
                                    $update->contract_response_sequence = $contractData['APIResponse']['CallSequence'];
                                    $update->status = 1;
                                    $update->contractCreated = 1;
                                    $update->save();
                                }
                                if(sizeof($contract['ContractInstalments']) > 0){
                                    $contract['policy_id'] = $id;
                                    $contractStore = $this->storeClientContractDetails($contract);

                                    $realpayContractDetails = RealpayContractDetails::where('ClientNumber',$contract['ClientNumber'])->get();
                                    if (!isset($realpayContractDetails)) {
                                        $contractStore = $this->storeContractDetails($contract);
                                    }

                                    $realpayInstal = RealpayContractInstallments::where('clientNumber',$contract['ClientNumber'])->get();
                                    if (!isset($realpayInstal)) {
                                        $installments = $this->storeInstallments($contract);
                                    }
                                }
                            }
                        }
                    }
                }

                $realpayContracts = RealpayClientContracts::where('policy_id',$id)->get();
            }


            return DataTables::of($realpayContracts)

            ->editColumn('status', function ($realpayContracts) {
                $realpayInstallments = RealpayContractInstallments::where('contractNumber',$realpayContracts->contract_number)->get();
                $status = '';
                foreach ($realpayInstallments as $key => $contractInstl) {
                    if ($contractInstl['InstalmentStatus'] == 'I') {
                        $status =  '<span class="kt-font-bold kt-font-danger">Cancelled</span>';
                    } else {
                        $status =  '<span class="kt-font-bold kt-font-info">Active</span>';
                    }
                }
                return $status;
            })

            ->addColumn('actions', function ($realpayContracts) {
                $realpayInstallments = RealpayContractInstallments::where('contractNumber',$realpayContracts->contract_number)->get();
                $status = '';
                $actions = '-';
                foreach ($realpayInstallments as $key => $contractInstl) {
                    if ($contractInstl['InstalmentStatus'] == 'I') {
                        $status =  'Cancelled';
                    } else {
                        $status =  'Active';
                    }
                }

                if ($status != 'Cancelled') {
                    $policyProd = Policy::where('id',$realpayContracts->policy_id)->first('product_id');
                    if ($policyProd->product_id == 3) {
                        $actions = '<a href="' . route('admin.cancelContract',$realpayContracts->id) . '" class="btn btn-sm btn-elevate btn-danger btn-elevate" title="Cancel Contract">
                            <span class="kt-opacity-11" id="">Cancel</span>
                        </a>';
                    } else {
                        $actions = '<a href="' . route('admin.cancelContractForInsProd',$realpayContracts->id) . '" class="btn btn-sm btn-elevate btn-danger btn-elevate" title="Cancel Contract">
                            <span class="kt-opacity-11" id="">Cancel</span>
                        </a>';
                    }
                }

                return $actions;
            })

            ->rawColumns(['status','actions'])
            ->make(true);

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
                if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                    $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
                else
                    return null;

                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => config('realpay.base_url') . "/maintain/clients/" . config('realpay.product') . "?ClientNumber=" . $client->clientNumber . "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version'),
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
                        CURLOPT_URL => config('realpay.base_url') . "/maintain/contracts/" . config('realpay.product') . "?ClientNumber=" . $client->clientNumber . "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version'),
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

                    if ($new_premium != $request->paymentAmount) {
                        $remainingAmt = $request->premium - $request->paymentAmount;
                        $new_premium = $remainingAmt / 2;
                        $new_premium = round($new_premium,2);
                    }
                    return response()->json(['success'=>'success','message' => 'Premium fetched successfully','status' => '200', 'new_premium' => $new_premium]);

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
            $policy = Policy::where('id',$request->policy_id)->first();
            if ($policy->product_id == 3) {
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
            } else {
                if (isset($request->premium) && $request->premium != "") {
                    $new_premium = $request->premium;
                } else {
                    $new_premium = $policy->premium;
                }

                return response()->json(['success'=>'success','message' => 'Premium fetched successfully','status' => '200', 'new_premium' => $new_premium]);
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

    public function updateRealpayClientNumber(Request $request)
    {
        try {
            $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();

            // $request = new Request();
            // $request['clientNumber'] = isset($request->clientNumber) ? $request->clientNumber : null;

            $getcontract = $realpay->getContractInfoForMotorComp($request);

            $policy = Policy::where('id',$request->policy_id)->first();
            $customerBanking = CustomerBanking::where('policy_id',$request->policy_id)->orderBy('id', 'DESC')->first();

            if (!isset($customerBanking)) {
                $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();
            }

            $request['bankName'] = isset($customerBanking->bankName) ? $customerBanking->bankName : null;

            if (!isset($getcontract)) {
                $updateClientNumber = $this->updateClientNumber($request);
            } else {
                $updateClientNumber = $this->updateClientNumberForInstantProduct($request);
            }

            if ($updateClientNumber->getData()->status == 200) {
                return Redirect::back()->with('success', 'Successfully updated client number');
            } else {
                return Redirect::back()->with('error', $updateClientNumber->getData()->message);
            }

        } catch (\Exception $ex) {
            return Redirect::back()->with('error', $ex->getMessage());
        }
    }

    public function updateClientNumber(Request $request)
    {
        // try {
            if (isset($request->clientNumber) && isset($request->contractNumber)) {
                $fetchToken = $this->clientAuth();
                // dd($fetchToken);
                if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                    $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
                else
                    return null;

                // $curl = curl_init();

                // curl_setopt_array($curl, array(
                //     CURLOPT_URL => config('realpay.base_url') . "/maintain/clients/" . config('realpay.product') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version'),
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
                //         CURLOPT_URL => config('realpay.base_url') . "/maintain/contracts/" . config('realpay.product') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version'),
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

                    $url = '';
                    if ($request->bankName == 12) {
                       $url = config('realpay.base_url')."/maintain/instalments/".config('realpay.fnb_product')."?ClientNumber=".$request->clientNumber."&ContractNumber=".$request->contractNumber. "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version');
                    } else {
                       $url = config('realpay.base_url')."/maintain/instalments/".config('realpay.product')."?ClientNumber=".$request->clientNumber."&ContractNumber=".$request->contractNumber. "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version');
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
                            return response()->json(['error'=>'failed','message' => 'Client not found','status' => '401']);
                        }

                        // foreach ($data['InstalmentGetResponse'] as $key => $contract) {
                            $updateClient = RealpayPaymentRequest::where('policy_id', $request->policy_id)->first();
                            if (isset($updateClient)) {
                                $updateClient->contract = $data['InstalmentGetResponse'][0]['ContractNumber'];
                                $updateClient->save();
                            } else {
                                return response()->json(['error'=>'failed','message' => 'Failed to update Client number','status' => '401']);
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

                            // $realpayInstallment = RealpayContractInstallments::where('clientNumber', $data['InstalmentGetResponse'][0]['ClientNumber'])->where('contractNumber', $data['InstalmentGetResponse'][0]['ContractNumber'])->first();
                            // if (!isset($realpayInstallment)) {
                            //     // $installments = $this->storeInstallments($contract);
                            //     foreach($data['InstalmentGetResponse'] as $d){
                            //         $new = new RealpayContractInstallments();
                            //         $new->clientNumber = $d['ClientNumber'];
                            //         $new->contractNumber = $d['ContractNumber'];
                            //         $new->InstalmentReferenceNumber = $d['InstalmentReferenceNumber'];
                            //         $new->InstalmentSequence = $d['InstalmentSequence'];
                            //         $new->CTCAmount = isset($d['CTCAmount']) ? $d['CTCAmount'] : null;
                            //         $new->InstalmentActionDate = $d['InstalmentActionDate'];
                            //         $new->TrackingCode = $d['TrackingCode'];
                            //         $new->InstalmentAmount = $d['InstalmentAmount'];
                            //         $new->InstalmentStatus = $d['InstalmentStatus'];
                            //         $new->save();

                            //     }
                            // }
                            foreach($data['InstalmentGetResponse'] as $d){
                                $realpayInstallment = RealpayContractInstallments::where('clientNumber', $d['ClientNumber'])->where('contractNumber', $d['ContractNumber'])->where('InstalmentReferenceNumber', $d['InstalmentReferenceNumber'])->first();
                                if (!isset($realpayInstallment)) {
                                    $new = new RealpayContractInstallments();
                                    $new->clientNumber = $d['ClientNumber'];
                                    $new->contractNumber = $d['ContractNumber'];
                                    $new->InstalmentReferenceNumber = $d['InstalmentReferenceNumber'];
                                    $new->InstalmentSequence = $d['InstalmentSequence'];
                                    $new->CTCAmount = isset($d['CTCAmount']) ? $d['CTCAmount'] : null;
                                    $new->InstalmentActionDate = $d['InstalmentActionDate'];
                                    $new->TrackingCode = $d['TrackingCode'];
                                    $new->InstalmentAmount = $d['InstalmentAmount'];
                                    $new->InstalmentStatus = $d['InstalmentStatus'];
                                    $new->save();
                                } else {
                                    $realpayInstallment->clientNumber = $d['ClientNumber'];
                                    $realpayInstallment->contractNumber = $d['ContractNumber'];
                                    $realpayInstallment->InstalmentReferenceNumber = $d['InstalmentReferenceNumber'];
                                    $realpayInstallment->InstalmentSequence = $d['InstalmentSequence'];
                                    $realpayInstallment->CTCAmount = isset($d['CTCAmount']) ? $d['CTCAmount'] : null;
                                    $realpayInstallment->InstalmentActionDate = $d['InstalmentActionDate'];
                                    $realpayInstallment->TrackingCode = $d['TrackingCode'];
                                    $realpayInstallment->InstalmentAmount = $d['InstalmentAmount'];
                                    $realpayInstallment->InstalmentStatus = $d['InstalmentStatus'];
                                    $realpayInstallment->save();
                                }

                                if ($d['InstalmentStatus'] == 'S' || $d['InstalmentStatus'] == 'F') {
                                    $status = $d['InstalmentStatus'];
                                    if ($d['InstalmentStatus'] == 'S') {
                                        $status = 'SUCCESS';
                                    } elseif ($d['InstalmentStatus'] == 'F') {
                                        $status = 'FAILED';
                                    }

                                    $paymentData['policyNumber'] = $policy->policyNumber;
                                    $paymentData['policy_id'] = $policy->id;
                                    $paymentData['referenceNumber'] = $d['InstalmentReferenceNumber'];
                                    $paymentData['amount'] = $d['InstalmentAmount'];
                                    $paymentData['status'] = $status;
                                    $paymentData['paymentDate'] = \Carbon\Carbon::parse($d['InstalmentActionDate'])->format('Y-m-d');
                                    $paymentData['paymentMethod'] = 'RealPay';
                                    $paymentData['numberOfInstalmentsPaid'] = $d['InstalmentSequence'];
                                    $paymentData['note'] = 'TRANSACTION ' . $status;
                                    $paymentData['send_sms_email'] = 1;

                                    $policyController = new PolicyController();
                                    $saveEntry = $policyController->updatePaymentTransactions($paymentData);
                                }
                            }

                        // }

                        return response()->json(['success'=>'success','message' => 'Successfully updated client number','status' => '200']);
                    } else {
                        return response()->json(['error'=>'failed','message' => 'Policy not found','status' => '401']);
                    }
                } else {
                    return response()->json(['error'=>'failed','message' => 'Client number and Contract number not found','status' => '401']);
                }
            } else {
                return response()->json(['error'=>'failed','message' => 'Client number and Contract number not found','status' => '401']);
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

    public function cancelClientContract($id)
    {
        try {
            $cancelContract = $this->cancelSingleRealpayContract($id);
            return Redirect::back()->with('success', 'Contract cancelled Successfully');
        } catch (\Exception $ex) {
            return Redirect::back()->with('error', $ex->getMessage());
        }
    }

    public function realpayReconsiliationTxLogCheck($record)
    {
        try {
            $paymentTx = PaymentTransaction::where('policyNumber',$record->clientNumber)->where('referenceNumber',$record->InstalmentReferenceNumber)->first();

            if (isset($paymentTx)) {

                if ($paymentTx->amount != $record->collectedAmount) {
                    $paymentTx->amount = $record->collectedAmount;
                }

            //     if ($paymentTx->status != $record->currentStatus) {
            //         $paymentTx->status = $record->currentStatus;
            //     }

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

                if(str_contains($record->clientNumber, '/')){
                    $policyNumber = strtok($record->clientNumber, '/');
                } else {
                    $policyNumber = $record->clientNumber;
                }

                $paymentData['policyNumber'] = $policyNumber;
                $paymentData['policy_id'] = $record->policy_id;
                $paymentData['referenceNumber'] = $record->InstalmentReferenceNumber;
                $paymentData['amount'] = $record->collectedAmount;
                $paymentData['status'] = $record->currentStatus;
                $paymentData['paymentDate'] = \Carbon\Carbon::parse($record->installmentDate)->format('Y-m-d');
                $paymentData['paymentMethod'] = 'RealPay';
                $paymentData['numberOfInstalmentsPaid'] = $paid;
                $paymentData['note'] = 'TRANSACTION ' . $record->currentStatus;
                $paymentData['send_sms_email'] = 1;

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
//                                CURLOPT_URL => config('realpay.base_url') . "/maintain/contracts/" . config('realpay.product') . "?ClientNumber=" . $realpayContract->ClientNumber . "&ContractNumber=" . $realpayContract->ContractNumber . "&ContractSequence=" . $realpayContract->ContractSequence . "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version'),
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
//                                            CURLOPT_URL => config('realpay.base_url')."/maintain/instalments/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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

            // curl_setopt_array($curl, array(
            //     CURLOPT_URL => config('realpay.start.base_url')."/oauth/token",
            //     CURLOPT_RETURNTRANSFER => true,
            //     CURLOPT_ENCODING => "",
            //     CURLOPT_MAXREDIRS => 10,
            //     CURLOPT_TIMEOUT => 0,
            //     CURLOPT_FOLLOWLOCATION => true,
            //     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            //     CURLOPT_CUSTOMREQUEST => "POST",
            //     CURLOPT_POSTFIELDS => "grant_type=client_credentials",
            //     CURLOPT_HTTPHEADER => array(
            //         "Authorization: Basic ".config('realpay.start.client_auth'),
            //         "Content-Type: application/x-www-form-urlencoded"
            //     ),
            // ));

            // Guard against missing config: when these env values are empty the
            // request goes out with an empty Basic header / bad URL and RealPay
            // rejects it, surfacing only as a generic "Could not authenticate".
            $baseUrl    = config('realpay.start.base_url');
            $clientAuth = config('realpay.start.client_auth');
            if (empty($baseUrl) || empty($clientAuth)) {
                Log::error('RealPay START auth: missing credentials/config', [
                    'has_base_url'    => !empty($baseUrl),
                    'has_client_auth' => !empty($clientAuth),
                ]);
                curl_close($curl);
                return null;
            }

            curl_setopt_array($curl, array(
                CURLOPT_URL => $baseUrl.'/oauth/token?grant_type=client_credentials',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_HTTPHEADER => array(
                  'Authorization: Basic '.$clientAuth
                ),
              ));

            $response = curl_exec($curl);
            $curlErrNo = curl_errno($curl);
            $curlError = curl_error($curl);
            $httpCode  = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            // Transport-level failure (timeout, DNS, SSL, connection refused).
            if ($response === false || $curlErrNo !== 0) {
                Log::error('RealPay START auth: transport failure', [
                    'curl_errno' => $curlErrNo,
                    'curl_error' => $curlError,
                    'http_code'  => $httpCode,
                ]);
                return null;
            }

            $data = json_decode($response, true);

            // Authenticated but no usable token (bad creds, HTTP 4xx/5xx, or an
            // unexpected body). Log the reason instead of silently returning null.
            if (!is_array($data) || empty($data['access_token']) || empty($data['token_type'])) {
                Log::error('RealPay START auth: no access_token in response', [
                    'http_code' => $httpCode,
                    'response'  => is_string($response) ? substr($response, 0, 500) : null,
                ]);
                return null;
            }

            return $data;
        }catch(\Exception $e){
            Log::error('RealPay START auth: exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    public function checkClientExistsForInstantProduct($policy_id)
    {
        $policy = Policy::where('id',$policy_id)->first(array('policyNumber'));
        $fetchToken = $this->clientAuthForInstantProduct();
        if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;


        $customerBanking = $this->resolvePolicyCustomerBanking($policy);

        $url = '';
        if (isset($customerBanking) && $customerBanking->bankName == 12) {
            $url = config('realpay.start.base_url').'/maintain/clients/'.config('realpay.fnb_product')."?ClientNumber=".$policy->policyNumber."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
        } else {
            $url = config('realpay.start.base_url').'/maintain/clients/'.config('realpay.start.product')."?ClientNumber=".$policy->policyNumber."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
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

    public function addRealpayPaymentForInstantProduct(Request $request)
    {
        $mandateService = app(\AlphaDirect\Services\RealPayMandateService::class);
        $mandate = null;

        try {
            $policy = Policy::where('policyNumber',$request->policyNumber)->first();

            // Local duplicate-contract guard.
            //
            // redoPaymentFromStart() already asks RealPay whether a contract
            // exists (getExistingRealpayContract / contractIsActive). Those are
            // remote calls: when RealPay is unreachable AND the local instalment
            // cache is empty, contractIsActive() returns false and a second live
            // contract gets created — two debits and a refund. This guard asks
            // our own mandate records, which still answer in that window.
            //
            // It refuses only on the unambiguous case by default (a mandate that
            // has already collected). See config/realpay.php 'mandate'.
            if (isset($policy)) {
                $guard = $mandateService->guardContractCreation((int) $policy->id);
                if ($guard['block']) {
                    return response()->json([
                        'status'  => '401',
                        'message' => 'This policy already has an active RealPay debit-order mandate. Cancel the existing contract before creating another one.',
                    ], 401);
                }
            }

            $customer = Customer::where('id', $policy->customer_id)->with('profile')->first();
            $profile = CustomerProfile::where('customer_id',$customer->id)->first();
            $customerBanking = CustomerBanking::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();

            if ($policy->product_id == 3) {
                $fetchToken = $this->clientAuth();
            } else {
                $fetchToken = $this->clientAuthForInstantProduct();
            }

            //Log::info("1".$fetchToken);
            //dd($fetchToken,'soali');
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token'])){

                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            }
            else
            {
                return null;
            }

            if($profile->omang != null){
                $id = $profile->omang;
                $idType = 'I';
            }else{
                $id = $profile->passport;
                $idType = 'P';
            }

            if (isset($request->billing_date)) {
                $request['billing_day'] = \Carbon\Carbon::createFromFormat('d/m/Y', $request->billing_date)->format('d');
                $request['billingDay'] = \Carbon\Carbon::createFromFormat('d/m/Y', $request->billing_date)->format('Y-m-d');
            }

            $url = '';
            $checkClient = '';
            if ($policy->product_id == 3) {
                $checkClient = $this->checkClientExists($policy->id);
                //Log::info("1".$checkClient);

                if (isset($request->bankName) && $request->bankName == 12) {
                    $url = config('realpay.base_url')."/maintain/clients/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
                } else {
                    $url = config('realpay.base_url')."/maintain/clients/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
                }

            } else {
                $checkClient = $this->checkClientExistsForInstantProduct($policy->id);

                if (isset($request->bankName) && $request->bankName == 12) {
                    $url = config('realpay.start.base_url')."/maintain/clients/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
                } else {
                    $url = config('realpay.start.base_url')."/maintain/clients/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
                }

            }

            $curl = curl_init();

            if($checkClient == false){
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
                    \"BankCode\": \"$request->bankName\",\r\n
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
            }else{

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
                    CURLOPT_POSTFIELDS =>"{\r\n  \"ClientPutRequest\": [\r\n    {\r\n
                            \"ClientNumber\": \"$policy->policyNumber\",\r\n
                           \"ClientName\": \"$customer->firstName $customer->lastName\",\r\n
                           \"IDType\": \"$idType\",\r\n
                           \"IDNumber\": \"$id\",\r\n
                           \"CellphoneNumber\": \"$customer->cellphone\",\r\n
                           \"EMail\": \"$customer->email\",\r\n
                           \"BankCode\": \"$request->bankName\",\r\n
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
                //Log::info("2".json_decode($response));

                $data = json_decode($response,true);

                curl_close($curl);
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

            if (isset($data) && isset($data['ClientPutResponse'][0]['Failed']) && isset($data['ClientPutResponse'][0]['Failed'][0]['Failures'][0]['FailureDescription'])) {
                return response()->json(['status' => '401', 'message' => $data['ClientPutResponse'][0]['Failed'][0]['Failures'][0]['FailureDescription']], 401);
            }

            if (isset($customerBanking)) {
                $customerBanking->bankName = $request->bankName;
                $customerBanking->branchCode = $request->branchCode;
                $customerBanking->accountType = $request->accountType;
                $customerBanking->accountNumber = $request->accountNumber;
                $customerBanking->billing = "RealPay";
                $customerBanking->billing_day = $request->billing_day;
                $customerBanking->billingStartDate = $this->setDate($request->billing_day);
                $customerBanking->save();
            }

            if ($policy->product_id == 3) {
                $fetchToken = $this->clientAuth();
            } else {
                $fetchToken = $this->clientAuthForInstantProduct();
            }

            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $policy = Policy::where('id',$policy->id)->first();
            // dd($policy,$token,$policy->id);
            if($policy->product_id != 3)
                $policy->first_premium_wvat = 0;

            $firstBillingDate = $request->first_collection_date;

            if (isset($policy->first_premium)) {
                $firstCollectionAmount = $policy->first_premium;
            } else {
                $firstCollectionAmount = $policy->premium;
            }

            $numberOfInstallments = '99';
            $frequency = 'MNTH'; //"QURT",[ WEEK, FRTN, MNTH, QURT, MIAN, YEAR, ADHO, OOFF ]

                if($policy->premium_freq == 2){
                    $frequency = 'QURT';
                    if($policy->product_id == 3 ){
                       $numberOfInstallments = '3';
                       $firstCollectionAmount = $policy->premium;
                    }
                }

            $premium = $policy->premium;

            $billing_day = '';
            if ($request->billingDay != NULL) {
                $billing_day = \Carbon\Carbon::createFromFormat('Y-m-d', $request->billingDay)->format('d');
            }

            if($billing_day == 31 || $billing_day == 30 || $billing_day == 29){
                $billing_day = 99;
            }

            if (isset($request->requestType) && $request->requestType == 'redoPayment') {
                $firstcollDate = Carbon::now()->addDays(1)->format("Y-m-d");
            } else {
                $firstcollDate = isset($request->first_collection_date) ? $request->first_collection_date : $request->billingDay;
            }

            $contractNumber = RealpayClientContracts::getContractNumber($policy->id);

            // dd($billing_day,$request->billingDay);

            $url = '';
            $trackingCode = "44";
            if ($policy->product_id == 3) {
                if (isset($request->bankName) && $request->bankName == 12) {
                    $trackingCode = "B3";
                    $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
                } else {
                    $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
                }
            } else {
                if (isset($request->bankName) && $request->bankName == 12) {
                    $trackingCode = "B3";
                    $url = config('realpay.start.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
                } else {
                    $url = config('realpay.start.base_url')."/maintain/contracts/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
                }
            }

            // Claim the mandate row for this attempt BEFORE the RealPay call, so
            // a failure has somewhere to be recorded. Reuses the row when this
            // contract number already has one, and the unique index on
            // (policy_id, contract_number) means two concurrent submits produce
            // one mandate rather than two.
            $mandate = $mandateService->claim($policy, (string) $contractNumber, [
                'tracking_code'         => $trackingCode,
                'collection_day'        => is_numeric($billing_day) ? (int) $billing_day : null,
                'frequency_code'        => $frequency,
                'collection_amount'     => is_numeric($premium) ? $premium : null,
                'first_collection_date' => $firstcollDate,
            ]);

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
                    // The rows above were just marked inactive; keep their
                    // mandates truthful too, or a stale `registered` row makes
                    // the reuse guard refuse a legitimate re-contract later.
                    $mandateService->cancelSupersededMandates(
                        (int) $policy->id,
                        (string) $contractNumber,
                        'Superseded by contract ' . $contractNumber
                    );
                }
                // RealPay accepted the ContractPostRequest, so the mandate is a
                // registered authority to collect. NOT active: that needs a
                // successful collection, which arrives on the instalment webhook
                // and is applied by applyMandateInstalmentOutcome().
                $mandateService->markRegistered($mandate, [
                    'provider_reference' => isset($data['APIResponse']['CallSequence'])
                        ? (string) $data['APIResponse']['CallSequence']
                        : null,
                    'provider_payload'   => $data['ContractPostResponse'][0]['Successful'][0] ?? null,
                ]);

                return response()->json(['status' => '200', 'message' => 'Client added successfully on realpay', "instalmentStatus" => $installmentStatus, 'premium' => $premium, 'first_premium' => $firstCollectionAmount], 200);

            } else {
                // RealPay refused the contract. The mandate must never be left
                // looking successfully set up: `failed` is terminal, so a retry
                // takes a fresh row with an incremented contract-number suffix.
                $mandateService->markFailed(
                    $mandate,
                    'ContractPostRequest failed: ' . json_encode($data['ContractPostResponse'][0]['Failed'] ?? $data)
                );

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
            // A cURL timeout, an auth failure or a malformed RealPay response all
            // land here. None of them is a mandate we may treat as set up, so the
            // claimed row is closed off as failed rather than left dangling in
            // `pending`, where the reuse guard would read it as a live journey.
            $mandateService->markFailed($mandate, 'Exception during RealPay contract creation: ' . $ex->getMessage());

            return response()->json(['status'=>'401','message' => $ex->getMessage().' '.$ex->getLine()],401);
        }

    }

    public function realpayBankListView(){
        $banks      = Banks::all();
        return view('admin.accounts.fetchBankList',compact('banks'));
    }

    public function fetchRealpayBankList()
    {
        $banks      = Banks::where('selected_bank',1)->get();

        return DataTables::of($banks)

        ->make(true);
    }

    public function seletRealpayBank(Request $request)
    {
        try {
            $update = Banks::where('id',$request->bankId)->first();
            if (isset($update) && $request->submitBtn == 'add') {
                $update->selected_bank = 1;
                $update->save();
                return Redirect()->back()->with('success', 'Record added successfully');
            } else if (isset($update) && $request->submitBtn == 'remove') {
                $update->selected_bank = 0;
                $update->save();
                return Redirect()->back()->with('success', 'Record removed successfully');
            }else {
                return Redirect()->back()->with('error', 'Record not found.');
            }
        } catch (\Exception $ex) {
            return Redirect()->back()->with('error', $ex->getMessage());
        }
    }


    public function rpGetBanksForInstantProduct()
    {
        try {

            $banks = Banks::where('selected_bank',1)->get();

            return response()->json(['code' => 200, 'banks' => $banks], 200);
        } catch (TeacherNotFoundException $e) {

            return response()->json('An error has occured with RealPay: getting banks', 400);
        }
    }

    public function rpGetBranchesForInstantProduct(Request $request)
    {
        try {
            $branches = BankBranches::where('bank_id', $request->bank_id)->get();
            return response()->json(['code' => 200, 'branches' => $branches], 200);
        } catch (TeacherNotFoundException $e) {

            return response()->json('An error has occured with RealPay get branches', 400);
        }
    }

    public function getRealpayInstallments($ins){
        try{
            $fetchToken = $this->clientAuth();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $policy = Policy::where('id',$ins['policy_id'])->first();
            $customerBanking = $this->resolvePolicyCustomerBanking($policy);

            $url = '';
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $url = config('realpay.base_url')."/maintain/instalments/".config('realpay.fnb_product')."?ClientNumber=".$ins['clientNumber']."&ContractNumber=".$ins['contractNumber']. "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version');
            } else {
                $url = config('realpay.base_url')."/maintain/instalments/".config('realpay.product')."?ClientNumber=".$ins['clientNumber']."&ContractNumber=".$ins['contractNumber']. "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version');
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

    /**
     * Did RealPay accept an instalment PUT?
     *
     * The four updateRealpay*Installment* methods below used to ignore the
     * response entirely and write the new date into
     * realpay_contract_installments regardless, which made our cache an
     * optimistic record rather than a true one: a rejected PUT left Graphite
     * claiming a schedule RealPay had never taken, and the customer kept being
     * debited on the old day with nothing anywhere to show it (MIS2026213635).
     *
     * The shape is the one updateRealpayInstalmentData (line ~4554),
     * updateRealpayContractsRerate and UpdatePremiumRealpay already check —
     * `Successful` populated and `Failed` empty. A transport failure decodes to
     * null and is a rejection, not a silent success.
     */
    private function instalmentPutAccepted($data): bool
    {
        if (!is_array($data)) {
            return false;
        }

        $response = $data['InstalmentPutResponse'][0] ?? null;

        if (!is_array($response)) {
            return false;
        }

        return !empty($response['Successful']) && empty($response['Failed']);
    }

    public function updateRealpayInstallmentData($ins){
        try{
            // Set by the instalment loop below when RealPay rejects a PUT.
            $instalmentPutFailed = false;
            $fetchToken = $this->clientAuth();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;


            $policy = Policy::where('id',$ins['policy_id'])->first();
            $customerBanking = $this->resolvePolicyCustomerBanking($policy);

            $url = '';
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $url = config('realpay.base_url')."/maintain/instalments/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
            } else {
                $url = config('realpay.base_url')."/maintain/instalments/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
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

                    // Only record the new date locally when RealPay actually
                    // took it. This block used to write the row regardless, so
                    // a rejected PUT left our cache claiming a schedule RealPay
                    // had never accepted — the policy read "collects on the
                    // 28th" while the customer was still debited on the 18th,
                    // and nothing downstream could tell the difference. The
                    // response shape is the one three other call sites in this
                    // file already check.
                    $putOk = $this->instalmentPutAccepted($data);

                    if (! $putOk) {
                        $instalmentPutFailed = true;

                        \Illuminate\Support\Facades\Log::error('[REALPAY BILLING DATE] instalment PUT rejected — local schedule left unchanged', [
                            'client_number'   => $clientNum,
                            'contract_number' => $contractNumber,
                            'sequence'        => $insSeq,
                            'target_date'     => $instDate,
                            'response'        => $data,
                        ]);
                    }

                    $insdata = RealpayContractInstallments::where('clientNumber',$clientNum)
                        ->where('contractNumber',$contractNumber)
                        ->where('InstalmentSequence',$insSeq)
                        ->first();
                    if($insdata != null && $putOk){
                        $insdata->InstalmentActionDate = $instDate;
                        $insdata->save();
                    }

                    if ($insdata != null) {
                        activity('Realpay Installments')
                        ->performedOn($insdata)
                        ->log($putOk
                            ? 'Updated Realpay Installment Date to ' . $instDate
                            : 'RealPay REJECTED the Installment Date change to ' . $instDate);
                    }
                }

                sleep(1);
            }

            if ($instalmentPutFailed) {
                // At least one instalment was not moved on RealPay's side, so
                // the caller must not record this job as done — the customer
                // is still on the old schedule.
                return response()->json(['status' => 'error', 'message' => 'RealPay rejected one or more instalment updates'], 401);
            }

            return response()->json(['status' => 'success', 'message' => 'Realpay Installments updated successfully'], 200);

        }catch(\Exception $e){
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 401);
        }
    }

    public function updateRealpayAllInstallmentData($ins){
        try{
            // Set by the instalment loop below when RealPay rejects a PUT.
            $instalmentPutFailed = false;
            $fetchToken = $this->clientAuth();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $policy = Policy::where('id',$ins['policy_id'])->first();
            $customerBanking = $this->resolvePolicyCustomerBanking($policy);

            $url = '';
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $url = config('realpay.base_url')."/maintain/instalments/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
            } else {
                $url = config('realpay.base_url')."/maintain/instalments/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
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

                    // Only record the new date locally when RealPay actually
                    // took it. This block used to write the row regardless, so
                    // a rejected PUT left our cache claiming a schedule RealPay
                    // had never accepted — the policy read "collects on the
                    // 28th" while the customer was still debited on the 18th,
                    // and nothing downstream could tell the difference. The
                    // response shape is the one three other call sites in this
                    // file already check.
                    $putOk = $this->instalmentPutAccepted($data);

                    if (! $putOk) {
                        $instalmentPutFailed = true;

                        \Illuminate\Support\Facades\Log::error('[REALPAY BILLING DATE] instalment PUT rejected — local schedule left unchanged', [
                            'client_number'   => $clientNum,
                            'contract_number' => $contractNumber,
                            'sequence'        => $insSeq,
                            'target_date'     => $instDate,
                            'response'        => $data,
                        ]);
                    }

                    $insdata = RealpayContractInstallments::where('clientNumber',$clientNum)
                        ->where('contractNumber',$contractNumber)
                        ->where('InstalmentSequence',$insSeq)
                        ->first();
                    if($insdata != null && $putOk){
                        $insdata->InstalmentActionDate = $instDate;
                        $insdata->save();
                    }

                    $countActive++;

                    if ($insdata != null) {
                        activity('Realpay Installments')
                        ->performedOn($insdata)
                        ->log($putOk
                            ? 'Updated Realpay Installment Date to ' . $instDate
                            : 'RealPay REJECTED the Installment Date change to ' . $instDate);
                    }
                }

                sleep(1);
            }

            if ($instalmentPutFailed) {
                // At least one instalment was not moved on RealPay's side, so
                // the caller must not record this job as done — the customer
                // is still on the old schedule.
                return response()->json(['status' => 'error', 'message' => 'RealPay rejected one or more instalment updates'], 401);
            }

            return response()->json(['status' => 'success', 'message' => 'Realpay Installments updated successfully'], 200);

        }catch(\Exception $e){
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 401);
        }
    }

    public function cancelRealpayContractForInstantProduct($clientContracts,$policy)
    {
        if($clientContracts != null && isset($policy)){
            $now = new DateTime();
            $now->format('Y-m-d');
            $Token = new \AlphaDirect\Http\Controllers\Admin\RealPayController();

            if ($policy->product_id == 3) {
                $fetchToken = $this->clientAuth();
            } else {
                $fetchToken = $this->clientAuthForInstantProduct();
            }

            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                $token = null;

            $successResult = 0;
            $contractFailed = array();

            if (isset($clientContracts)) {
                foreach ($clientContracts as $key => $contract) {

                    $url = '';
                    if ($policy->product_id == 3) {
                        $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.product')."?ClientNumber=".$policy->policyNumber."&ContractNumber=".$contract->contract_number."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
                    } else {
                        $url = config('realpay.start.base_url')."/maintain/contracts/".config('realpay.start.product')."?ClientNumber=".$policy->policyNumber."&ContractNumber=".$contract->contract_number."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
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

            activity('Realpay Contract')
            ->performedOn($policy)
            // ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Cancelled realpay contract');

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

    public function getRealpayInstallmentsForInstantProduct($ins){
        try{
            $fetchToken = $this->clientAuthForInstantProduct();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $policy = Policy::where('id',$ins['policy_id'])->first();
            $customerBanking = $this->resolvePolicyCustomerBanking($policy);

            $url = '';
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $url = config('realpay.start.base_url')."/maintain/instalments/".config('realpay.fnb_product')."?ClientNumber=".$ins['clientNumber']."&ContractNumber=".$ins['contractNumber']. "&BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version');
            } else {
                $url = config('realpay.start.base_url')."/maintain/instalments/".config('realpay.start.product')."?ClientNumber=".$ins['clientNumber']."&ContractNumber=".$ins['contractNumber']. "&BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version');
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

    public function updateRealpayInstallmentForInstantProduct($ins){
        try{
            // Set by the instalment loop below when RealPay rejects a PUT.
            $instalmentPutFailed = false;
            $fetchToken = $this->clientAuthForInstantProduct();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;


            $policy = Policy::where('id',$ins['policy_id'])->first();
            $customerBanking = $this->resolvePolicyCustomerBanking($policy);

            $url = '';
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $url = config('realpay.start.base_url')."/maintain/instalments/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
            } else {
                $url = config('realpay.start.base_url')."/maintain/instalments/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
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

                    // Only record the new date locally when RealPay actually
                    // took it. This block used to write the row regardless, so
                    // a rejected PUT left our cache claiming a schedule RealPay
                    // had never accepted — the policy read "collects on the
                    // 28th" while the customer was still debited on the 18th,
                    // and nothing downstream could tell the difference. The
                    // response shape is the one three other call sites in this
                    // file already check.
                    $putOk = $this->instalmentPutAccepted($data);

                    if (! $putOk) {
                        $instalmentPutFailed = true;

                        \Illuminate\Support\Facades\Log::error('[REALPAY BILLING DATE] instalment PUT rejected — local schedule left unchanged', [
                            'client_number'   => $clientNum,
                            'contract_number' => $contractNumber,
                            'sequence'        => $insSeq,
                            'target_date'     => $instDate,
                            'response'        => $data,
                        ]);
                    }

                    $insdata = RealpayContractInstallments::where('clientNumber',$clientNum)
                        ->where('contractNumber',$contractNumber)
                        ->where('InstalmentSequence',$insSeq)
                        ->first();
                    if($insdata != null && $putOk){
                        $insdata->InstalmentActionDate = $instDate;
                        $insdata->save();
                    }

                    if ($insdata != null) {
                        activity('Realpay Installments')
                        ->performedOn($insdata)
                        ->log($putOk
                            ? 'Updated Realpay Installment Date to ' . $instDate
                            : 'RealPay REJECTED the Installment Date change to ' . $instDate);
                    }
                }

                sleep(1);
            }

            if ($instalmentPutFailed) {
                // At least one instalment was not moved on RealPay's side, so
                // the caller must not record this job as done — the customer
                // is still on the old schedule.
                return response()->json(['status' => 'error', 'message' => 'RealPay rejected one or more instalment updates'], 401);
            }

            return response()->json(['status' => 'success', 'message' => 'Realpay Installments updated successfully'], 200);

        }catch(\Exception $e){
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 401);
        }
    }

    public function updateRealpayAllInstallmentForInstantProduct($ins){
        try{
            // Set by the instalment loop below when RealPay rejects a PUT.
            $instalmentPutFailed = false;
            $fetchToken = $this->clientAuthForInstantProduct();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;


            $policy = Policy::where('id',$ins['policy_id'])->first();
            $customerBanking = $this->resolvePolicyCustomerBanking($policy);

            $url = '';
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $url = config('realpay.start.base_url')."/maintain/instalments/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
            } else {
                $url = config('realpay.start.base_url')."/maintain/instalments/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
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

                    // Only record the new date locally when RealPay actually
                    // took it. This block used to write the row regardless, so
                    // a rejected PUT left our cache claiming a schedule RealPay
                    // had never accepted — the policy read "collects on the
                    // 28th" while the customer was still debited on the 18th,
                    // and nothing downstream could tell the difference. The
                    // response shape is the one three other call sites in this
                    // file already check.
                    $putOk = $this->instalmentPutAccepted($data);

                    if (! $putOk) {
                        $instalmentPutFailed = true;

                        \Illuminate\Support\Facades\Log::error('[REALPAY BILLING DATE] instalment PUT rejected — local schedule left unchanged', [
                            'client_number'   => $clientNum,
                            'contract_number' => $contractNumber,
                            'sequence'        => $insSeq,
                            'target_date'     => $instDate,
                            'response'        => $data,
                        ]);
                    }

                    $insdata = RealpayContractInstallments::where('clientNumber',$clientNum)
                        ->where('contractNumber',$contractNumber)
                        ->where('InstalmentSequence',$insSeq)
                        ->first();
                    if($insdata != null && $putOk){
                        $insdata->InstalmentActionDate = $instDate;
                        $insdata->save();
                    }

                    $countActive++;

                    if ($insdata != null) {
                        activity('Realpay Installments')
                        ->performedOn($insdata)
                        ->log($putOk
                            ? 'Updated Realpay Installment Date to ' . $instDate
                            : 'RealPay REJECTED the Installment Date change to ' . $instDate);
                    }
                }

                sleep(1);
            }

            if ($instalmentPutFailed) {
                // At least one instalment was not moved on RealPay's side, so
                // the caller must not record this job as done — the customer
                // is still on the old schedule.
                return response()->json(['status' => 'error', 'message' => 'RealPay rejected one or more instalment updates'], 401);
            }

            return response()->json(['status' => 'success', 'message' => 'Realpay Installments updated successfully'], 200);

        }catch(\Exception $e){
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 401);
        }
    }

    public function getContractInfoForInstantProduct(Request $request){
        try {
            $fetchToken = $this->clientAuthForInstantProduct();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $policy = Policy::where('policyNumber',$request->policy_number)->first();
            $customerBanking = $this->resolvePolicyCustomerBanking($policy);

            $url = '';
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $url = config('realpay.start.base_url') . "/maintain/clients/" . config('realpay.fnb_product') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version');
            } else {
                $url = config('realpay.start.base_url') . "/maintain/clients/" . config('realpay.start.product') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version');
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

            if (isset($clientData['ClientGetResponse']) && $clientData['ClientGetResponse'] != NULL) {

                $url = '';
                if (isset($customerBanking) && $customerBanking->bankName == 12) {
                    $url = config('realpay.start.base_url') . "/maintain/contracts/" . config('realpay.fnb_product') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version');
                } else {
                    $url = config('realpay.start.base_url') . "/maintain/contracts/" . config('realpay.start.product') . "?ClientNumber=" . $request->clientNumber . "&BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version');
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

    public function updateInstalmentStatusForInstantProduct($seq,$s,$reason = null){
        try{
            $ref = (int)$seq;
            $fetchToken = $this->clientAuthForInstantProduct();

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
                CURLOPT_URL => config('realpay.start.base_url')."/maintain/instalments/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version'),
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

                activity('Realpay Installments')
                ->performedOn($ins)
                ->causedBy(User::where('id', auth()->user()->id)->first())
                ->log('Updated Realpay Installment status');

                return Redirect::back()->with('success', 'Instalment updated successfully');
            }else{
                return Redirect::back()->with('error', 'Something went wrong');
            }

        }catch(\Exception $e){
            return Redirect::back()->with('error', $e->getMessage());
        }
    }

    public function cancelClientContractInstantProduct($id)
    {
        try {
            $cancelContract = $this->cancelSingleRealpayContractForInstant($id);
            return Redirect::back()->with('success', 'Contract cancelled Successfully');
        } catch (\Exception $ex) {
            return Redirect::back()->with('error', $ex->getMessage());
        }
    }

    public function updateClientNumberForInstantProduct(Request $request)
    {
        // try {
            if (isset($request->clientNumber) && isset($request->contractNumber)) {
                $fetchToken = $this->clientAuthForInstantProduct();
                // dd($fetchToken);
                if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                    $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
                else
                    return null;

                    $url = '';
                    if ($request->bankName == 12) {
                        $url = config('realpay.start.base_url')."/maintain/instalments/".config('realpay.fnb_product')."?ClientNumber=".$request->clientNumber."&ContractNumber=".$request->contractNumber. "&BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version');
                    } else {
                        $url = config('realpay.start.base_url')."/maintain/instalments/".config('realpay.start.product')."?ClientNumber=".$request->clientNumber."&ContractNumber=".$request->contractNumber. "&BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version');
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
                            return response()->json(['error'=>'failed','message' => 'Client not found','status' => '401']);
                        }

                        // foreach ($data['InstalmentGetResponse'] as $key => $contract) {
                            $updateClient = RealpayPaymentRequest::where('policy_id', $request->policy_id)->first();
                            if (isset($updateClient)) {
                                $updateClient->contract = $data['InstalmentGetResponse'][0]['ContractNumber'];
                                $updateClient->save();
                            } else {
                                return response()->json(['error'=>'failed','message' => 'Failed to update Client number','status' => '401']);
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

                            // $realpayInstallment = RealpayContractInstallments::where('clientNumber', $data['InstalmentGetResponse'][0]['ClientNumber'])->where('contractNumber', $data['InstalmentGetResponse'][0]['ContractNumber'])->first();
                            // if (!isset($realpayInstallment)) {
                            //     // $installments = $this->storeInstallments($contract);
                            //     foreach($data['InstalmentGetResponse'] as $d){
                            //         $new = new RealpayContractInstallments();
                            //         $new->clientNumber = $d['ClientNumber'];
                            //         $new->contractNumber = $d['ContractNumber'];
                            //         $new->InstalmentReferenceNumber = $d['InstalmentReferenceNumber'];
                            //         $new->InstalmentSequence = $d['InstalmentSequence'];
                            //         $new->CTCAmount = isset($d['CTCAmount']) ? $d['CTCAmount'] : 0;
                            //         $new->InstalmentActionDate = $d['InstalmentActionDate'];
                            //         $new->TrackingCode = $d['TrackingCode'];
                            //         $new->InstalmentAmount = $d['InstalmentAmount'];
                            //         $new->InstalmentStatus = $d['InstalmentStatus'];
                            //         $new->save();

                            //     }
                            // }

                            foreach($data['InstalmentGetResponse'] as $d){
                                $realpayInstallment = RealpayContractInstallments::where('clientNumber', $d['ClientNumber'])->where('contractNumber', $d['ContractNumber'])->where('InstalmentReferenceNumber', $d['InstalmentReferenceNumber'])->first();
                                if (!isset($realpayInstallment)) {
                                    $new = new RealpayContractInstallments();
                                    $new->clientNumber = $d['ClientNumber'];
                                    $new->contractNumber = $d['ContractNumber'];
                                    $new->InstalmentReferenceNumber = $d['InstalmentReferenceNumber'];
                                    $new->InstalmentSequence = $d['InstalmentSequence'];
                                    $new->CTCAmount = isset($d['CTCAmount']) ? $d['CTCAmount'] : null;
                                    $new->InstalmentActionDate = $d['InstalmentActionDate'];
                                    $new->TrackingCode = $d['TrackingCode'];
                                    $new->InstalmentAmount = $d['InstalmentAmount'];
                                    $new->InstalmentStatus = $d['InstalmentStatus'];
                                    $new->save();
                                } else {
                                    $realpayInstallment->clientNumber = $d['ClientNumber'];
                                    $realpayInstallment->contractNumber = $d['ContractNumber'];
                                    $realpayInstallment->InstalmentReferenceNumber = $d['InstalmentReferenceNumber'];
                                    $realpayInstallment->InstalmentSequence = $d['InstalmentSequence'];
                                    $realpayInstallment->CTCAmount = isset($d['CTCAmount']) ? $d['CTCAmount'] : null;
                                    $realpayInstallment->InstalmentActionDate = $d['InstalmentActionDate'];
                                    $realpayInstallment->TrackingCode = $d['TrackingCode'];
                                    $realpayInstallment->InstalmentAmount = $d['InstalmentAmount'];
                                    $realpayInstallment->InstalmentStatus = $d['InstalmentStatus'];
                                    $realpayInstallment->save();
                                }

                                if ($d['InstalmentStatus'] == 'S' || $d['InstalmentStatus'] == 'F') {
                                    $status = $d['InstalmentStatus'];
                                    if ($d['InstalmentStatus'] == 'S') {
                                        $status = 'SUCCESS';
                                    } elseif ($d['InstalmentStatus'] == 'F') {
                                        $status = 'FAILED';
                                    }

                                    $paymentData['policyNumber'] = $policy->policyNumber;
                                    $paymentData['policy_id'] = $policy->id;
                                    $paymentData['referenceNumber'] = $d['InstalmentReferenceNumber'];
                                    $paymentData['amount'] = $d['InstalmentAmount'];
                                    $paymentData['status'] = $status;
                                    $paymentData['paymentDate'] = \Carbon\Carbon::parse($d['InstalmentActionDate'])->format('Y-m-d');
                                    $paymentData['paymentMethod'] = 'RealPay';
                                    $paymentData['numberOfInstalmentsPaid'] = $d['InstalmentSequence'];
                                    $paymentData['note'] = 'TRANSACTION ' . $status;
                                    $paymentData['send_sms_email'] = 1;

                                    $policyController = new PolicyController();
                                    $saveEntry = $policyController->updatePaymentTransactions($paymentData);
                                }
                            }


                        // }

                        return response()->json(['success'=>'success','message' => 'Successfully updated client number','status' => '200']);
                    } else {
                        return response()->json(['error'=>'failed','message' => 'Policy not found','status' => '401']);
                    }
                } else {
                    return response()->json(['error'=>'failed','message' => 'Client number and Contract number not found','status' => '401']);
                }
            } else {
                return response()->json(['error'=>'failed','message' => 'Client number and Contract number not found','status' => '401']);
            }

        // } catch (\Exception $ex) {
        //     return Redirect::back()->with('error', $ex->getMessage());
        // }

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
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                $token = null;

            $successResult = 0;
            $contractFailed = array();

            if (isset($clientContracts)) {
                foreach ($clientContracts as $key => $contract) {

                    $installment = RealpayContractInstallments::where('clientNumber',$policy->policyNumber)->where('contractNumber',$contract->contract_number)->where('TrackingCode','B3')->get();

                    $url = '';
                    if ($installment->isNotEmpty() && isset($banking) && $banking->bankName == 12) {
                        $url = config('realpay.start.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?ClientNumber=".$policy->policyNumber."&ContractNumber=".$contract->contract_number."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
                    } else {
                        $url = config('realpay.start.base_url')."/maintain/contracts/".config('realpay.start.product')."?ClientNumber=".$policy->policyNumber."&ContractNumber=".$contract->contract_number."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
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

    public function createClientForInstantProduct($policy_id)
    {
        try{
            $policy = Policy::where('id',$policy_id)->first();
            $customer = Customer::where('id', $policy->customer_id)->with('profile')->first();
            $profile = CustomerProfile::where('customer_id',$customer->id)->first();

            $customerBanking = $this->resolvePolicyCustomerBanking($policy);

            $fetchToken = $this->clientAuthForInstantProduct();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
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
                CURLOPT_URL => config('realpay.start.base_url')."/maintain/clients/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version'),
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
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $policy = Policy::where('id',$policyId)->first();

            $policy->policyActivatedDate = Carbon::now()->format("Y-m-d");
            $policy->save();


            $customerBanking = $this->resolvePolicyCustomerBanking($policy);

            if($policy->product_id != 3)
                $policy->first_premium_wvat = 0;


            $numberOfInstallments = '99';
            $frequency = 'MNTH';
            $premium = $policy->premium;

            $now = new DateTime();


            $firstBillingDate = \Carbon\Carbon::now()->addDays(1)->format('Y-m-d');
            $installmentStartDate = \Carbon\Carbon::now()->addDays(1)->format('Y-m-d');//\Carbon\Carbon::parse($customerBanking->billingStartDate)->format('Y-m-d');

            if (isset($policy->first_premium)) {
                $firstCollectionAmount = $policy->first_premium;
            } else {
                $firstCollectionAmount = $policy->premium;
            }

            $billing_day = '';
            if ($customerBanking->billing_day != NULL) {
                // $billing_day = $customerBanking->billing_day;
                $billing_day = \Carbon\Carbon::now()->addDays(1)->format('d');
            }

            if($billing_day == 31 || $billing_day == 30 || $billing_day == 29){
                $billing_day = 99;
            }

            $contractNumber = RealpayClientContracts::getContractNumber($policy->id);

            $url = '';
            $trackingCode = "44";
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $trackingCode = "B3";
                $url = config('realpay.start.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
            } else {
                $url = config('realpay.start.base_url')."/maintain/contracts/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
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
                if (isset($log)) {
                    $log->status = 1;
                    $log->save();
                }

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
                // dd($data);
                $update->status = 2;
                $update->contractCreated = 2;
                $update->contract_response_sequence = $data['APIResponse']['CallSequence'];
                $update->response = serialize($data['ContractPostResponse'][0]['Failed'][0]['Failures']);
                $update->save();

                $log = RealpayLogs::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
                if (isset($log)) {
                    $log->status = 2;
                    $log->save();
                }
                return null;
            }
        }catch(\Exception $e){
            $update = RealpayPaymentRequest::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
            $update->status = 2;
            $update->contractCreated = 2;
            $update->save();

            $log = RealpayLogs::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
            if (isset($log)) {
                $log->status = 2;
                $log->save();
            }
            Log::info($e->getMessage(). " " .$e->getLine());
            return null;
        }
    }

    public function retrieveClient($policyNumber)
    {
        try{
            $fetchToken = $this->clientAuth();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;


            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.base_url').'/maintain/clients/'.config('realpay.product')."?ClientNumber=".$policyNumber."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version'),
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

            return $data['ClientGetResponse'];
        }catch(\Exception $e){
            return null;
        }
    }

    public function storeNewRealpayInstallmentForMotor($ins){
        try{
            $fetchToken = $this->clientAuthForMotorComp();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;


            $clientNumber = $ins['clientNumber'];
            $contractNumber = $ins['contractNumber'];
            $instalmentDate = Carbon::parse($ins['instalmentDate'])->format('Y-m-d');
            $instalmentAmount = $ins['instalmentAmount'];
            $contractSequence = $ins['contractSequence'];

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.start.base_url')."/maintain/instalments/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS =>"{\r\n  \"InstalmentPostRequest\": [\r\n    {\r\n
                            \"ClientNumber\": \"$clientNumber\",\r\n
                           \"ContractNumber\": \"$contractNumber\",\r\n
                           \"ContractSequence\": $contractSequence,\r\n
                           \"InstalmentActionDate\": \"$instalmentDate\",\r\n
                           \"TrackingCode\": \"44\",\r\n
                           \"InstalmentAmount\": $instalmentAmount,\r\n
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

                activity('Create')
                ->performedOn($inst)
                ->log('Instalment added successfully');

                return response()->json(['type'=>'success','message' => 'Instalment added successfully','status' => '200']);
            }else{
                $failDetail = $data['InstalmentPostResponse'][0]['Failed'] ?? ($data['Message'] ?? $data);
                \Illuminate\Support\Facades\Log::error('RealPay addInstallment failed', ['clientNumber'=>$clientNumber,'contractNumber'=>$contractNumber,'contractSequence'=>$contractSequence,'response'=>$data]);
                return response()->json(['type'=>'error','message' => 'RealPay rejected the instalment: ' . (is_string($failDetail) ? $failDetail : json_encode($failDetail)),'status' => '401']);
            }
        }catch(\Exception $e){
            // dd($e);
            return response()->json(['type'=>'error','message' => $e->getMessage(),'status' => '401']);
        }
    }

    public function storeNewRealpayInstallment($ins){
        try{
            $fetchToken = $this->clientAuth();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;


            $clientNumber = $ins['clientNumber'];
            $contractNumber = $ins['contractNumber'];
            $instalmentDate = Carbon::parse($ins['instalmentDate'])->format('Y-m-d');
            $instalmentAmount = $ins['instalmentAmount'];
            $contractSequence = $ins['contractSequence'];

            $policy = Policy::where('id',$ins['policy_id'])->first();
            $customerBanking = $this->resolvePolicyCustomerBanking($policy);

            $url = '';
            $trackingCode = "44";
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $trackingCode = "B3";
                $url = config('realpay.base_url')."/maintain/instalments/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
            } else {
                $url = config('realpay.base_url')."/maintain/instalments/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
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
                CURLOPT_POSTFIELDS =>"{\r\n  \"InstalmentPostRequest\": [\r\n    {\r\n
                            \"ClientNumber\": \"$clientNumber\",\r\n
                           \"ContractNumber\": \"$contractNumber\",\r\n
                           \"ContractSequence\": $contractSequence,\r\n
                           \"InstalmentActionDate\": \"$instalmentDate\",\r\n
                           \"TrackingCode\": \"$trackingCode\",\r\n
                           \"InstalmentAmount\": $instalmentAmount,\r\n
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

                activity('Create')
                ->performedOn($inst)
                ->log('Instalment added successfully');

                return response()->json(['type'=>'success','message' => 'Instalment added successfully','status' => '200']);
            }else{
                $failDetail = $data['InstalmentPostResponse'][0]['Failed'] ?? ($data['Message'] ?? $data);
                \Illuminate\Support\Facades\Log::error('RealPay addInstallment failed', ['clientNumber'=>$clientNumber,'contractNumber'=>$contractNumber,'contractSequence'=>$contractSequence,'response'=>$data]);
                return response()->json(['type'=>'error','message' => 'RealPay rejected the instalment: ' . (is_string($failDetail) ? $failDetail : json_encode($failDetail)),'status' => '401']);
            }
        }catch(\Exception $e){
            // dd($e);
            return response()->json(['type'=>'error','message' => $e->getMessage(),'status' => '401']);
        }
    }

    public function storeNewInstallmentForInstantProduct($ins){
        try{
            $fetchToken = $this->clientAuthForInstantProduct();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;


            $clientNumber = $ins['clientNumber'];
            $contractNumber = $ins['contractNumber'];
            $instalmentDate = Carbon::parse($ins['instalmentDate'])->format('Y-m-d');
            $instalmentAmount = $ins['instalmentAmount'];
            $contractSequence = $ins['contractSequence'];

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.start.base_url')."/maintain/instalments/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS =>"{\r\n  \"InstalmentPostRequest\": [\r\n    {\r\n
                            \"ClientNumber\": \"$clientNumber\",\r\n
                           \"ContractNumber\": \"$contractNumber\",\r\n
                           \"ContractSequence\": $contractSequence,\r\n
                           \"InstalmentActionDate\": \"$instalmentDate\",\r\n
                           \"TrackingCode\": \"44\",\r\n
                           \"InstalmentAmount\": $instalmentAmount,\r\n
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

                activity('Create')
                ->performedOn($inst)
                ->log('Instalment added successfully');

                return response()->json(['type'=>'success','message' => 'Instalment added successfully','status' => '200']);
            }else{
                $failDetail = $data['InstalmentPostResponse'][0]['Failed'] ?? ($data['Message'] ?? $data);
                \Illuminate\Support\Facades\Log::error('RealPay addInstallment failed', ['clientNumber'=>$clientNumber,'contractNumber'=>$contractNumber,'contractSequence'=>$contractSequence,'response'=>$data]);
                return response()->json(['type'=>'error','message' => 'RealPay rejected the instalment: ' . (is_string($failDetail) ? $failDetail : json_encode($failDetail)),'status' => '401']);
            }
        }catch(\Exception $e){
            // dd($e);
            return response()->json(['type'=>'error','message' => $e->getMessage(),'status' => '401']);
        }
    }

    public function getRealpayTransactionsView()
    {
        return view('admin.Realpay.fetchRealpayTransaction');
    }

    public function fetchRealpayTransactions(Request $request)
    {
        try {
            $request->validate([
                'policyNumber' => 'required|',
                'clientNumber' => 'required|',
            ]);

            $excuteCommand = Artisan::call('AddRealpayWebhookInTransactionLog:cron', [
                'policyNumber'=>$request->policyNumber, 'clientNumber'=>$request->clientNumber
                // 'data' => ['policyNumber'=>$request->policyNumber, 'clientNumber'=>$request->clientNumber],
            ]);

            return redirect()->back()->with('success', 'Realpay transactions fetched successfully');
        } catch(\Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function retrieveClientForInstantProduct($policyNumber)
    {
        try{
            $fetchToken = $this->clientAuthForInstantProduct();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;


            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.start.base_url').'/maintain/clients/'.config('realpay.start.product')."?ClientNumber=".$policyNumber."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version'),
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

            return $data['ClientGetResponse'];
        }catch(\Exception $e){
            return null;
        }
    }

    public function cancelSingleRealpayContract($id)
    {
        $realpayContracts = RealpayClientContracts::where('id',$id)->first();
        if(isset($realpayContracts)){
            $policy = Policy::where('id',$realpayContracts->policy_id)->first();

            $request = new Request();
            $request['clientNumber'] = $policy->policyNumber ? $policy->policyNumber : null;
            $request['policy_number'] = $policy->policyNumber ? $policy->policyNumber : null;

            $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
            $getcontract = $realpay->getContractInfo($request);

            if (isset($getcontract) && $getcontract->getData()->Status == 'Success') {
                $now = new DateTime();
                $now->format('Y-m-d');
                $Token = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                $fetchToken = $Token->clientAuth();
                if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                    $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
                else
                    $token = null;

                $successResult = 0;
                $contractFailed = array();

                    $customerBanking = $this->resolvePolicyCustomerBanking($policy);
                    $policyNumber = $policy->policyNumber;
                    $ContractNumber = $realpayContracts->contract_number;

                    $url = '';
                    $trackingCode = "44";
                    if (isset($customerBanking) && $customerBanking->bankName == 12) {
                        $trackingCode = "B3";
                        $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?ClientNumber=".$policyNumber."&ContractNumber=".$ContractNumber."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
                    } else {
                        $url =  config('realpay.base_url')."/maintain/contracts/".config('realpay.product')."?ClientNumber=".$policyNumber."&ContractNumber=".$ContractNumber."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');

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
                    CURLOPT_CUSTOMREQUEST => 'DELETE',
                    CURLOPT_HTTPHEADER => array(
                        'Authorization: ' . $token,
                    ),
                    ));

                $response = curl_exec($curl);
                $data = json_decode($response, true);
                $success = sizeof($data['ContractDeleteResponse'][0]['Successful']) > 0;
                curl_close($curl);
                // dd($data);
            } else {
                $now = new DateTime();
                $now->format('Y-m-d');
                $fetch_token = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                $f_token = $fetch_token->clientAuthForInstantProduct();
                if($f_token['token_type'] && $f_token['access_token'])
                    $FetchToken = $f_token['token_type'].' '.$f_token['access_token'];
                else
                    $FetchToken = null;

                $successResult = 0;
                $contractFailed = array();

                // dd($contract->contract_number);
                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => config('realpay.start.base_url')."/maintain/contracts/".config('realpay.start.product')."?ClientNumber=".$policy->policyNumber."&ContractNumber=".$realpayContracts->contract_number."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version'),
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
                        "Authorization: ".$FetchToken
                    ),
                ));

                $response = curl_exec($curl);
                $data = json_decode($response, true);
                $success = sizeof($data['ContractDeleteResponse'][0]['Successful']) > 0;
                curl_close($curl);

            }


            // dd($data,$policy->policyNumber,$contract->contract_number);
            if($success == true){
                // $update = $this->actionAfterCancellingContract($policyId);
                $update = $this->actionAfterCancellingContract($realpayContracts->contract_number);
                $can = RealpayCancelRequests::where('policy_id',$policy->id)->where('contract',$realpayContracts->contract_number)->first();
                if (!isset($can)) {
                    $can = new RealpayCancelRequests();
                    $can->policy_id = $policy->id;
                    $can->leftout_premium_contract = null;
                    $can->contract = $realpayContracts->contract_number;
                    $can->cancel_status = 0;
                    $can->save();
                }
            }
            else{
                $successResult ++;
            }



            if (isset(auth()->user()->id)) {
                activity('Realpay Contract')
                ->performedOn($policy)
                ->causedBy(User::where('id', auth()->user()->id)->first())
                ->log('Cancelled realpay contract');
            } else {
                activity('Realpay Contract')
                ->performedOn($policy)
                ->log('Cancelled realpay contract');
            }

            if($successResult == 0){
                return $policy->policyNumber;
            }
            else{
                return null;
            }
        }else{
            return null;
        }

    }

    public function cancelSingleRealpayContractForInstant($id)
    {
        $realpayContracts = RealpayClientContracts::where('id',$id)->first();

        if(isset($realpayContracts)){

            $policy = Policy::where('id',$realpayContracts->policy_id)->first();
            $realPayPayment = RealpayPaymentRequest::where('contract',$realpayContracts->contract_number)->first();
            $realpayContractInstallments = RealpayContractInstallments::where('contractNumber',$id)->first();
            $RealpayContractDetails  = RealpayContractDetails::where('ClientNumber',$policy->policyNumber)
            ->where('ContractNumber',$realpayContracts->contract_number)->first();

            $customerBanking = $this->resolvePolicyCustomerBanking($policy);

            $now = new DateTime();
            $now->format('Y-m-d');
            $Token = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
            $fetchToken = $Token->clientAuthForInstantProduct();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                $token = null;

            $successResult = 0;

            // dd($contract->contract_number);
            $customerBanking = $this->resolvePolicyCustomerBanking($policy);
            $policyNumber = $policy->policyNumber;
            $ContractNumber = $RealpayContractDetails->ContractNumber;

            $url = '';
            $trackingCode = "44";
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $trackingCode = "B3";
                $url = config('realpay.start.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?ClientNumber=".$policyNumber."&ContractNumber=".$ContractNumber."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
            } else {
                $url =  config('realpay.start.base_url')."/maintain/contracts/".config('realpay.start.product')."?ClientNumber=".$policyNumber."&ContractNumber=".$ContractNumber."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');

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
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => array(
                'Authorization: ' . $token,
            ),
            ));


            $response = curl_exec($curl);
            $data = json_decode($response, true);
            //dd($data,$token,config('realpay.start.base_url')."/maintain/contracts/".config('realpay.start.product')."?ContractNumber=".$policyNumber."&ContractNumber=".$ContractSequence."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version'));
            $success = sizeof($data['ContractDeleteResponse'][0]['Successful']) > 0;
            curl_close($curl);

            if($success == true){
                // $update = $this->actionAfterCancellingContract($policyId);
                $update = $this->actionAfterCancellingContract($realpayContracts->contract_number);
                $can = RealpayCancelRequests::where('policy_id',$policy->id)->where('contract',$realpayContracts->contract_number)->first();
                if (!isset($can)) {
                    $can = new RealpayCancelRequests();
                    $can->policy_id = $policy->id;
                    $can->leftout_premium_contract = null;
                    $can->contract = $realpayContracts->contract_number;
                    $can->cancel_status = 0;
                    $can->save();
                }
            }
            else{
                $successResult ++;
            }



            if (isset(auth()->user()->id)) {
                activity('Realpay Contract')
                ->performedOn($policy)
                ->causedBy(User::where('id', auth()->user()->id)->first())
                ->log('Cancelled realpay contract');
            } else {
                activity('Realpay Contract')
                ->performedOn($policy)
                ->log('Cancelled realpay contract');
            }

            if($successResult == 0){
                return $policy->policyNumber;
            }
            else{
                return null;
            }
        }else{
            return null;
        }

    }

    public function addAutoRenewContractForMotorComp($policyId)
    {
        try{
            $policy = Policy::where('id',$policyId)->first();
            $customer = Customer::where('id', $policy->customer_id)->with('profile')->first();
            $profile = CustomerProfile::where('customer_id',$customer->id)->first();

            $customerBanking = $this->resolvePolicyCustomerBanking($policy);

            $fetchToken = $this->clientAuthForMotorComp();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
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

            $checkClient = $this->checkClientExistsForMotorComp($policy->id);

            if($checkClient == false){
                curl_setopt_array($curl, array(
                    CURLOPT_URL => config('realpay.start.base_url')."/maintain/clients/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version'),
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
            }else{

                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => config('realpay.start.base_url')."/maintain/clients/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version'),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "PUT",
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

            $fetchToken = $this->clientAuthForMotorComp();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $policy = Policy::where('id',$policyId)->first();

            if($policy->product_id != 3)
                $policy->first_premium_wvat = 0;

            $firstBillingDate = '';
            $firstCollectionAmount = '';
            $numberOfInstallments = '12';
            $frequency = 'MNTH';
            $premium = $policy->premium;
            $firstCollectionAmount = $policy->premium;

            $firstBillingDate = Carbon::now()->format('Y-m-d');
            $policy->billingStartDate = Carbon::now()->format('Y-m-d');

            if($policy->premium_freq == 2){
                $numberOfInstallments = '3';
            }
            elseif($policy->premium_freq == 3){
                $frequency = 'YEAR';
                $numberOfInstallments = '1';
            }
            elseif($policy->premium_freq == 1){
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

            $contractNumber = RealpayClientContracts::getContractNumber($policy->id);
            if($policy->billing_day == 31 && $policy->premium_freq != 2){
                $policy->billing_day = 99;
            }

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.start.base_url')."/maintain/contracts/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version'),
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

                if($contractNumber != null) {
                    $Table = (new RealpayClientContracts())->getTable();
                    DB::table($Table)->where('client_number', $policy->policyNumber)
                        ->where('contract_number', '!=',$contractNumber)
                        ->update(array('status' => 0));
                }

                return $policy->policyNumber;
            } else {
                $update->status = 2;
                $update->contractCreated = 2;
                $update->contract_response_sequence = $data['APIResponse']['CallSequence'];
                $update->response = serialize($data['ContractPostResponse'][0]['Failed'][0]['Failures']);
                $update->save();

                return null;
            }
        }catch(\Exception $e){
            $update = RealpayPaymentRequest::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
            $update->status = 2;
            $update->contractCreated = 2;
            $update->save();

            return null;
        }
    }

    public function getRealpayInstallmentsWithContractSequence($ins){
        try{
            $fetchToken = $this->clientAuth();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.base_url')."/maintain/instalments/".config('realpay.product')."?ClientNumber=".$ins['clientNumber']."&ContractNumber=".$ins['contractNumber']."&ContractSequence=".$ins['contractSequence']."&InstalmentSequence=".$ins['instalmentSequence']. "&BeneficiaryUser=" . config('realpay.merchant') . "&Version=" . config('realpay.version'),
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

            if (!isset($data) || empty($data['InstalmentGetResponse'])) {
                $fetchToken = $this->clientAuthForInstantProduct();
                if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                    $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
                else
                    return null;

                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => config('realpay.start.base_url')."/maintain/instalments/".config('realpay.start.product')."?ClientNumber=".$ins['clientNumber']."&ContractNumber=".$ins['contractNumber']."&ContractSequence=".$ins['contractSequence']."&InstalmentSequence=".$ins['instalmentSequence']. "&BeneficiaryUser=" . config('realpay.start.merchant') . "&Version=" . config('realpay.start.version'),
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

            }
            return $data;
        }catch(\Exception $e){
            return null;
        }
    }

    public function getClientPolicyNumberView()
    {
        return view('admin.Realpay.getClientPolicyNumberView');
    }

    public function fetchCustomerDetails(Request $request){
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

            $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
            $getClient = $realpay->retrieveClient($policy->policyNumber);
            if (isEmpty($getClient)) {
                $getClient = $realpay->retrieveClientForInstantProduct($policy->policyNumber);
            }

            if (isset($getClient)) {
                $clientDetails = $getClient[0];
                $branches = BankBranches::where('bank_id',$clientDetails['BankCode'])->get(array('branch_id','name'));
            } else {
                $clientDetails = null;
            }

            // dd($clientDetails);
            return view('admin.Realpay.addRealPayContractWithExistingClient',compact('customer','banks','branches','policy','id','returnBlade','clientDetails'));
        }catch(\Exception $ex){
            return redirect()->back()->with('error', $ex->getMessage());

        }
    }

    public function realpayPayment($policy)
    {
        // dd($policy);
        $log = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
        $addLog = $log->logEvent($policy->id, 1);
        $responseArr = array('ClientCreated' => 0, 'ContractCreated' => 0);
        $stringArr = \Opis\Closure\serialize($responseArr);

        $transaction = new Transaction();
        $transaction->policyNumber = $policy->policyNumber;
        $transaction->amount = $policy->premium;
        $transaction->customer_id = $policy->customer_id;
        $transaction->realPayTransaction_id = $policy->id;
        $transaction->referenceNumber = $policy->policyNumber;
        $transaction->status = "PENDING";
        $transaction->save();

        if ($addLog == true) {
            $payRequest = new RealpayPaymentRequest();
            $payRequest->policy_id = $policy->id;
            // $payRequest->first_premium = $policy->leftout_premium;
            $payRequest->first_premium = $policy->first_premium;
            $payRequest->premium = $policy->premium;
            $payRequest->billing_day = $policy->billing_day;
            $payRequest->billing_date = $policy->billingStartDate;
            $payRequest->first_premium_contract = null;
            $payRequest->contract = null;
            $payRequest->status = 0;
            $payRequest->response = $stringArr;
            $payRequest->frequency = $policy->premium_freq;
            $payRequest->clientCreated = 0;
            $payRequest->contractCreated = 0;
            $payRequest->save();

            return response()->json(['status' => '200', 'message' => 'Payment successful', 'PolicyNumber' => $policy->policyNumber], 200);
        } else {
            return response()->json(['status' => '401', 'message' => 'Payment log unsuccessful'], 401);
        }
    }

    public function addContractForInstantActivatePolicy($policyId)
    {
        try{
            $fetchToken = $this->clientAuth();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
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
                $firstBillingDate = Carbon::now()->addDays(1)->format("Y-m-d");
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

            $customerBanking = $this->resolvePolicyCustomerBanking($policy);

            $firstBillingDate = Carbon::now()->addDays(1)->format("Y-m-d");

            $url = '';
            $trackingCode = "44";
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $trackingCode = "B3";
                $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
            } else {
                $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
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

    public function addContractForInstantProductInstantActivate($policyId)
    {
        try{
            $fetchToken = $this->clientAuthForInstantProduct();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $policy = Policy::where('id',$policyId)->first();

            $policy->policyActivatedDate = Carbon::now()->format("Y-m-d");
            $policy->save();


            $customerBanking = $this->resolvePolicyCustomerBanking($policy);

            if($policy->product_id != 3)
                $policy->first_premium_wvat = 0;


            $numberOfInstallments = '99';
            $frequency = 'MNTH';
            $premium = $policy->premium;

            $now = new DateTime();
            $firstBillingDate = Carbon::now()->addDays(1)->format("Y-m-d");
            // $installmentStartDate = \Carbon\Carbon::now()->addDays(1)->format('Y-m-d');//\Carbon\Carbon::parse($customerBanking->billingStartDate)->format('Y-m-d');

            if (isset($policy->first_premium)) {
                $firstCollectionAmount = $policy->first_premium;
            } else {
                $firstCollectionAmount = $policy->premium;
            }

            $billing_day = '';
            if ($customerBanking->billing_day != NULL) {
                // $billing_day = $customerBanking->billing_day;
                $billing_day = \Carbon\Carbon::now()->addDays(1)->format('d');
            }

            if($billing_day == 31 || $billing_day == 30 || $billing_day == 29){
                $billing_day = 99;
            }

            $contractNumber = RealpayClientContracts::getContractNumber($policy->id);

            $url = '';
            $trackingCode = "44";
            if (isset($customerBanking) && $customerBanking->bankName == 12) {
                $trackingCode = "B3";
                $url = config('realpay.start.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
            } else {
                $url = config('realpay.start.base_url')."/maintain/contracts/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
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
                if (isset($log)) {
                    $log->status = 1;
                    $log->save();
                }

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
                // dd($data);
                $update->status = 2;
                $update->contractCreated = 2;
                $update->contract_response_sequence = $data['APIResponse']['CallSequence'];
                $update->response = serialize($data['ContractPostResponse'][0]['Failed'][0]['Failures']);
                $update->save();

                $log = RealpayLogs::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
                if (isset($log)) {
                    $log->status = 2;
                    $log->save();
                }
                return null;
            }
        }catch(\Exception $e){
            $update = RealpayPaymentRequest::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
            $update->status = 2;
            $update->contractCreated = 2;
            $update->save();

            $log = RealpayLogs::where('policy_id',$policyId)->orderBy('id', 'DESC')->first();
            if (isset($log)) {
                $log->status = 2;
                $log->save();
            }
            Log::info($e->getMessage(). " " .$e->getLine());
            return null;
        }
    }

    public function retrieveContract($data)
    {
        $policy = Policy::where('policyNumber',$data->clientNumber)->first();

        $customerBanking = $this->resolvePolicyCustomerBanking($policy);

        // if ($policy->product_id == 3) {
            $fetchToken = $this->clientAuth();
        // }
        // else {
        //     $fetchToken = $this->clientAuthForMotorComp();
        // }

        if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;

        $url = '';

        if (isset($customerBanking->bankName) && $customerBanking->bankName == 12) {
            $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?ClientNumber=".$data->clientNumber."&ContractNumber=".$data->contractNumber."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
        } elseif ($customerBanking->bankName == null || $customerBanking->bankName == '') {
            $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?ClientNumber=".$data->clientNumber."&ContractNumber=".$data->contractNumber."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
        }  else {
            $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.product')."?ClientNumber=".$data->clientNumber."&ContractNumber=".$data->contractNumber."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
        }
        // if ($customerBanking->product_id == 3) {
        //     $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?ClientNumber=".$data->clientNumber."&ContractNumber=".$data->contractNumber."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
        // }
        // else {
        //     $url = config('realpay.start.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?ClientNumber=".$data->clientNumber."&ContractNumber=".$data->contractNumber."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
        // }

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

        return $data['ContractGetResponse'];

    }

    public function retrieveContractForInstant($data)
    {
        $policy = Policy::where('policyNumber',$data->clientNumber)->first();

        $customerBanking = $this->resolvePolicyCustomerBanking($policy);

        // if ($policy->product_id == 3) {
            $fetchToken = $this->clientAuthForMotorComp();
        // }

        if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;

        $url = '';

        if (isset($customerBanking->bankName) && $customerBanking->bankName == 12) {
            $url =config('realpay.start.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?ClientNumber=".$data->clientNumber."&ContractNumber=".$data->contractNumber."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
        } elseif ($customerBanking->bankName == null || $customerBanking->bankName == '') {
            $url =config('realpay.start.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?ClientNumber=".$data->clientNumber."&ContractNumber=".$data->contractNumber."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
        } else {
            $url =config('realpay.start.base_url')."/maintain/contracts/".config('realpay.start.product')."?ClientNumber=".$data->clientNumber."&ContractNumber=".$data->contractNumber."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
        }
        // if ($policy->product_id == 3) {
        //     $url = config('realpay.start.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?ClientNumber=".$data->clientNumber."&ContractNumber=".$data->contractNumber."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
        // }

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

        return $data['ContractGetResponse'];

    }

    public function addRealtimeContract($contract)
    {
        try{
        $data = $contract[0];

        $policy = Policy::where('policyNumber',$data['ClientNumber'])->first();

        if ($policy->product_id == 3) {
            $fetchToken = $this->clientAuth();
        } else {
            $fetchToken = $this->clientAuthForMotorComp();
        }

        if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;

            $firstBillingDate = '';
            $firstCollectionAmount = '';
            $numberOfInstallments = '12';
            $frequency = 'MNTH';

            if($policy->premium_freq != null){

                if($policy->premium_freq == 2){
                    $numberOfInstallments = '3';
                }
                elseif($policy->premium_freq == 3){
                    $frequency = 'YEAR';
                    $numberOfInstallments = '1';
                }
                elseif($policy->premium_freq == 1){
                    $numberOfInstallments = '14';
                }
            }

            $premium = $policy->premium;
            $instalmentDate = Carbon::now()->format("Y-m-d");

            // $realpayInstallment = RealpayContractInstallments::where('clientNumber',$data['ClientNumber'])->where('contractNumber',$data['ContractNumber'])
            // ->whereDate('InstalmentActionDate','>=',Carbon::now())->first();

            // if (isset($realpayInstallment)) {
            //     $premium = $realpayInstallment->InstalmentAmount;
            //     $instalmentDate = Carbon::parse($realpayInstallment->InstalmentActionDate)->format("Y-m-d");;
            // } else {
            //     foreach ($data['ContractInstalments'] as $key => $contractInstl) {
            //         if ($contractInstl['InstalmentActionDate'] >= Carbon::now()) {
            //             $premium = $contractInstl['InstalmentAmount'];
            //             $instalmentDate = $contractInstl['InstalmentActionDate'];
            //             break;
            //         }
            //     }
            // }

            // $contractNumber = RealpayClientContracts::getContractNumberFromContract($data['ContractNumber'],$policy->id);

            $contractNumber = RealpayClientContracts::getContractNumber($policy->id);

            $customerBanking = $this->resolvePolicyCustomerBanking($policy);

            $billing_day = Carbon::parse($policy->billingStartDate)->format('d');
            // dd($billing_day,$policy->billingStartDate,$policy);
            if ($billing_day != NULL) {
                $billing_day = $billing_day;
                // $billing_day = \Carbon\Carbon::now()->addDays(1)->format('d');
                // dd($billing_day);
            }

            if($billing_day == 31 || $billing_day == 30 || $billing_day == 29){
                $billing_day = 99;
            }

            $url = '';
            $trackingCode = "44";
            // $trackingCode = "B3";
            // if ($policy->product_id == 3) {
            //     $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
            // } else {
            //     $url = config('realpay.start.base_url')."/maintain/contracts/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
            // }

            $checkClient = $this->checkMotorCompClientExists($policy->id);

            $fetchToken = $this->clientAuth();

            if (isset($checkClient[0]['BankCode']) && $checkClient[0]['BankCode'] == 12) {
                $trackingCode = "B3";
                $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
            } else {
                $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
            }

            if (empty($checkClient)) {
                $checkClient = $this->checkInstProdClientExists($policy->id);

                $fetchToken = $this->clientAuthForMotorComp();

                if (isset($checkClient[0]['BankCode']) && $checkClient[0]['BankCode'] == 12) {
                    $trackingCode = "B3";
                    $url = config('realpay.start.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
                } else {
                    $url = config('realpay.start.base_url')."/maintain/contracts/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
                }
            }

            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            // if ($policy->product_id == 3) {
            //     if (isset($checkClient[0]['BankCode']) && $checkClient[0]['BankCode'] == 12) {
            //         $trackingCode = "B3";
            //         $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
            //     } else {
            //         $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.product')."?BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
            //     }

            // } else {
            //     if (isset($checkClient[0]['BankCode']) && $checkClient[0]['BankCode'] == 12) {
            //         $trackingCode = "B3";
            //         $url = config('realpay.start.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
            //     } else {
            //         $url = config('realpay.start.base_url')."/maintain/contracts/".config('realpay.start.product')."?BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
            //     }

            // }

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
                      \"FirstCollectionDate\": \"$instalmentDate\",\r\n
                      \"FirstCollectionAmount\": \"$premium\",\r\n
                      \"InstalmentStartDate\": \"$instalmentDate\",\r\n
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

            if (!isset($customerBanking->bankName)) {
                $customerBanking->bankName = $checkClient[0]['BankCode'];
            }

            if (!isset($customerBanking->branchCode)) {
                $customerBanking->branchCode = $checkClient[0]['BranchCode'];
            }

            if (!isset($customerBanking->accountType)) {
                $customerBanking->accountType = $checkClient[0]['AccountType'];
            }

            if (!isset($customerBanking->accountNumber)) {
                $customerBanking->accountNumber = $checkClient[0]['AccountNumber'];
            }



            $customerBanking->billing = "RealPay";
            // $customerBanking->billing_day = $request->billing_day;
            // $customerBanking->billingStartDate = $this->setDate($request->billing_day);
            $customerBanking->save();

            $addRealpayPayment = RealpayPaymentRequest::where('policy_id', $policy->id)->first();
            if (!isset($addRealpayPayment)) {
                $addRealpayPayment = new RealpayPaymentRequest();
                $addRealpayPayment->policy_id = $policy->id;
                $addRealpayPayment->first_premium = $policy->leftout_premium;
                $addRealpayPayment->premium = $policy->premium;
                $addRealpayPayment->billing_day = $policy->billing_day;
                $addRealpayPayment->billing_date = $policy->billingStartDate;
                $addRealpayPayment->first_premium_contract = null;
                $addRealpayPayment->contract = null;
                $addRealpayPayment->status = 1;
                $addRealpayPayment->response = 1;
                $addRealpayPayment->frequency = $policy->premium_freq;
                $addRealpayPayment->clientCreated = 1;
                $addRealpayPayment->contractCreated = 0;
                $addRealpayPayment->save();
            }

            $update = RealpayPaymentRequest::where('policy_id', $policy->id)->first();

            if (sizeof($data['ContractPostResponse'][0]['Successful']) > 0 && sizeof($data['ContractPostResponse'][0]['Failed']) == 0) {
                $update->contract = $contractNumber;
                $update->contract_response_sequence = $data['APIResponse']['CallSequence'];
                $update->status = 1;
                $update->contractCreated = 1;
                $update->save();

                $log = RealpayLogs::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();
                if (isset($log)) {
                    $log->status = 1;
                    $log->save();
                }

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

                $log = RealpayLogs::where('policy_id',$policy->id)->orderBy('id', 'DESC')->first();
                if (isset($log)) {
                    $log->status = 2;
                    $log->save();
                }
                return null;
            }
        }catch(\Exception $e){
            Log::info($e->getMessage(). " " .$e->getLine());
            return null;
        }
    }


    public function cancelSpecificRealpayContract($datas)
    {
        $policyId = $datas['policyId'];
        $policy = Policy::where('id',$policyId)->first();

        if(isset($datas['clientNumber']) && isset($datas['contractNumber'])){

            $now = new DateTime();
            $now->format('Y-m-d');
            $Token = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
            $fetchToken = $Token->clientAuth();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                $token = null;

            $successResult = 0;
            $contractFailed = array();

            $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.product')."?ClientNumber=".$datas['clientNumber']."&ContractNumber=".$datas['contractNumber']."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');


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
                $update = $this->actionAfterCancellingContract($datas['contractNumber']);
                $can = RealpayCancelRequests::where('policy_id',$policyId)->where('contract',$datas['contractNumber'])->first();
                if (!isset($can)) {
                    $can = new RealpayCancelRequests();
                    $can->policy_id = $policyId;
                    $can->leftout_premium_contract = null;
                    $can->contract = $datas['contractNumber'];
                    $can->cancel_status = 0;
                    $can->save();
                }
            }
            else{
                $successResult ++;
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

    public function cancelSpecificRealpayContractForInstantProd($datas)
    {
        $policyId = $datas['policyId'];
        $policy = Policy::where('id',$policyId)->first();

        if(isset($datas['clientNumber']) && isset($datas['contractNumber'])){

            $now = new DateTime();
            $now->format('Y-m-d');
            $Token = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
            $fetchToken = $Token->clientAuthForInstantProduct();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                $token = null;

            $successResult = 0;
            $contractFailed = array();


            $url = config('realpay.start.base_url')."/maintain/contracts/".config('realpay.start.product')."?ClientNumber=".$datas['clientNumber']."&ContractNumber=".$datas['contractNumber']."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');

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
                $update = $this->actionAfterCancellingContract($datas['contractNumber']);
                $can = RealpayCancelRequests::where('policy_id',$policy->id)->where('contract',$datas['contractNumber'])->first();
                if (!isset($can)) {
                    $can = new RealpayCancelRequests();
                    $can->policy_id = $policy->id;
                    $can->leftout_premium_contract = null;
                    $can->contract = $datas['contractNumber'];
                    $can->cancel_status = 0;
                    $can->save();
                }
            }
            else{
                $successResult ++;
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

    public function checkInstProdClientExists($policy_id)
    {
        $policy = Policy::where('id',$policy_id)->first(array('policyNumber'));
        $fetchToken = $this->clientAuthForMotorComp();
        if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;

        $customerBanking = $this->resolvePolicyCustomerBanking($policy);

        $url = '';
        if (isset($customerBanking) && $customerBanking->bankName == 12) {
            $url = config('realpay.start.base_url').'/maintain/clients/'.config('realpay.fnb_product')."?ClientNumber=".$policy->policyNumber."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
        } else {
            $url = config('realpay.start.base_url').'/maintain/clients/'.config('realpay.start.product')."?ClientNumber=".$policy->policyNumber."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
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

        return $data['ClientGetResponse'];
    }

    public function checkMotorCompClientExists($policy_id)
    {
        $policy = Policy::where('id',$policy_id)->first(array('policyNumber'));
        $fetchToken = $this->clientAuth();
        if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;

        $customerBanking = $this->resolvePolicyCustomerBanking($policy);

        $url = '';
        if (isset($customerBanking) && $customerBanking->bankName == 12) {
            $url = config('realpay.base_url').'/maintain/clients/'.config('realpay.fnb_product')."?ClientNumber=".$policy->policyNumber."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
        } else {
            $url = config('realpay.base_url').'/maintain/clients/'.config('realpay.product')."?ClientNumber=".$policy->policyNumber."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
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

        return $data['ClientGetResponse'];
    }

    public function realpayTransactionsExcel(Request $request)
    {
        // if (!Auth::user()->hasPermissionTo('policy-pay_with_dpo'))
        // {
        //     return response()->json([ 'status' => 500, 'message'=> 'Sorry! You do not have permission to access this page!']);
        // }

        return view('admin.Realpay.realpayTransactionImport');
    }

    public function getRealpayTransactionImport(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required',
            ]);

            $data =  Excel::import(new RealpayTransactionsImport, $request->file('file')->store('files'));

            // Artisan::call('importRealpayTransactions:cron');

            return redirect()->back()->withSuccess('Excel imported successfully.');

        } catch (\Exception $ex) {
            return redirect()->back()->withError($ex->getMessage() . ' ' . $ex->getLine());
        }
    }


    public function getInstallmentsFromContractSequence($record){
        try{

            $ContractSequence = $record->contractsequence;
            $product = $record->product;
            $beneficiarynumber = $record->beneficiarynumber;

            $fetchToken = $this->clientAuth();
            if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
            else
                return null;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => config('realpay.base_url')."/maintain/instalments/".$product."?ContractSequence=".$ContractSequence."&BeneficiaryUser=".$beneficiarynumber."&Version=".config('realpay.version'),
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


    public function contractIsActive($policy_id = null, $clientNumber = null): bool
    {
        // RealPay is the source of truth. The local realpay_contract_installments
        // table is only a cache and can keep stale InstalmentStatus='A' rows after
        // a cancellation — the cancel flow does not reset them for every tracking
        // code (e.g. '44'). Trusting the cache first produced a false "Realpay
        // Contract is active already." that permanently blocked reprocessing even
        // though RealPay reports the installments as inactive. So query the API
        // first and only fall back to the local cache when RealPay is unreachable.
        $getContract = $this->checkMotorContractActive($policy_id, $clientNumber) ??
                       $this->checkInstantContractActive($policy_id, $clientNumber);

        if (is_array($getContract)) {
            return $this->hasActiveApiInstallments($getContract);
        }

        // RealPay could not be reached / returned no contract — stay conservative
        // and trust the local cache so we never create a duplicate live contract.
        return RealpayContractInstallments::where('ClientNumber', $clientNumber)
            ->where('InstalmentStatus', 'A')
            ->exists();
    }

    public function checkMotorContractActive($policy_id,$clientNumber)
    {
        $policy = Policy::where('id',$policy_id)->first();

        $fetchToken = $this->clientAuth();
        if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;

        $customerBanking = $this->resolvePolicyCustomerBanking($policy);

		$url = '';
        if (isset($customerBanking) && $customerBanking->bankName == 12) {
            $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?ClientNumber=".$clientNumber."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
        } else {
            $url = config('realpay.base_url')."/maintain/contracts/".config('realpay.product')."?ClientNumber=".$clientNumber."&BeneficiaryUser=".config('realpay.merchant')."&Version=".config('realpay.version');
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
        if(is_array($fetchToken) && !empty($fetchToken['token_type']) && !empty($fetchToken['access_token']))
            $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
        else
            return null;

        $customerBanking = $this->resolvePolicyCustomerBanking($policy);

		$url = '';
        if (isset($customerBanking) && $customerBanking->bankName == 12) {
            $url = config('realpay.start.base_url')."/maintain/contracts/".config('realpay.fnb_product')."?ClientNumber=".$clientNumber."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
        } else {
            $url = config('realpay.start.base_url')."/maintain/contracts/".config('realpay.start.product')."?ClientNumber=".$clientNumber."&BeneficiaryUser=".config('realpay.start.merchant')."&Version=".config('realpay.start.version');
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

    /**
     * Return the contract(s) that already exist on the RealPay portal for this
     * policy, or null if none. Read-only (GET) — tries the motor then the
     * instant/START environment.
     */
    public function getExistingRealpayContract($policy_id, $clientNumber)
    {
        $resp = $this->checkMotorContractActive($policy_id, $clientNumber) ??
                $this->checkInstantContractActive($policy_id, $clientNumber);

        if (!is_array($resp) || empty($resp)) {
            return null;
        }

        // Only an active ('A') contract counts as "already on the portal"; a
        // cancelled one should still allow a fresh contract to be created.
        $active = array_values(array_filter(
            $resp,
            fn($c) => isset($c['ContractStatus']) && $c['ContractStatus'] === 'A'
        ));

        return !empty($active) ? $active : null;
    }

    /**
     * Fetch the contract that already exists on the RealPay portal and persist it
     * locally so it shows in our DB / system, instead of trying to recreate it
     * (which fails with "DUPLICATE CONTRACT ALREADY EXIST"). Idempotent: clears
     * the cached rows for each client/contract then re-inserts from the
     * authoritative API response, and mirrors the create success-path writes so
     * the debit order surfaces in the system. Returns the number of contracts
     * synced (0 if RealPay has none).
     */
    public function fetchAndStoreRealpayContract($policy, $resp = null)
    {
        $clientNumber = $policy->policyNumber;
        // Reuse a contract already fetched by the caller to avoid a second
        // (slow) round-trip to RealPay.
        if ($resp === null) {
            $resp = $this->getExistingRealpayContract($policy->id, $clientNumber);
        }

        if ($resp === null || !is_array($resp) || empty($resp)) {
            Log::info('RealPay sync: no existing contract on portal', ['policy_id' => $policy->id]);
            return 0;
        }

        // The Graphite DB connection has high per-query latency, so we batch all
        // writes (one INSERT for ~99 installments instead of one save() each)
        // otherwise the request blows PHP's max execution time.
        $now            = \Carbon\Carbon::now();
        $synced         = 0;
        $lastContractNo = null;
        $contractRows   = [];
        $installRows    = [];

        foreach ($resp as $c) {
            if (empty($c['ContractNumber'])) {
                continue;
            }
            $clientNo   = $c['ClientNumber'] ?? $clientNumber;
            $contractNo = $c['ContractNumber'];

            // Idempotent refresh of the local cache (also clears any stale /
            // duplicate rows from earlier partial attempts).
            RealpayContractInstallments::where('clientNumber', $clientNo)
                ->where('contractNumber', $contractNo)->delete();
            RealpayContractDetails::where('ClientNumber', $clientNo)
                ->where('ContractNumber', $contractNo)->delete();

            $contractRows[] = [
                'ContractSequence'    => $c['ContractSequence'] ?? null,
                'ClientNumber'        => $clientNo,
                'ContractNumber'      => $contractNo,
                'CTCPercentage'       => $c['CTCPercentage'] ?? null,
                'InstalmentStartDate' => $c['InstalmentStartDate'] ?? null,
                'TrackingCode'        => $c['TrackingCode'] ?? null,
                'NumberOfInstalments' => $c['NumberOfInstalments'] ?? null,
                'FrequencyCode'       => $c['FrequencyCode'] ?? null,
                'CollectionDay'       => $c['CollectionDay'] ?? null,
                'status'              => 1,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];

            foreach (($c['ContractInstalments'] ?? []) as $d) {
                $installRows[] = [
                    'policy_id'                  => $policy->id,
                    'clientNumber'               => $clientNo,
                    'contractNumber'             => $contractNo,
                    'InstalmentReferenceNumber'  => $d['InstalmentReferenceNumber'] ?? null,
                    'InstalmentSequence'         => $d['InstalmentSequence'] ?? null,
                    'CTCAmount'                  => $d['CTCAmount'] ?? null,
                    'InstalmentActionDate'       => $d['InstalmentActionDate'] ?? null,
                    'TrackingCode'               => $d['TrackingCode'] ?? null,
                    'InstalmentAmount'           => $d['InstalmentAmount'] ?? null,
                    'InstalmentStatus'           => $d['InstalmentStatus'] ?? null,
                    'created_at'                 => $now,
                    'updated_at'                 => $now,
                ];
            }

            // The row the system reads to show an active RealPay contract.
            RealpayClientContracts::updateOrCreate(
                ['policy_id' => $policy->id, 'contract_number' => $contractNo],
                ['client_number' => $clientNo, 'status' => 1]
            );

            $lastContractNo = $contractNo;
            $synced++;
        }

        if (!empty($contractRows)) {
            RealpayContractDetails::insert($contractRows);
        }
        // Chunk the installment insert so we never exceed the placeholder limit.
        foreach (array_chunk($installRows, 200) as $chunk) {
            RealpayContractInstallments::insert($chunk);
        }

        if ($synced > 0) {
            // Mirror the create success-path so the debit order surfaces.
            $pr = RealpayPaymentRequest::where('policy_id', $policy->id)->orderBy('id', 'desc')->first();
            if ($pr) {
                $pr->contract = $lastContractNo;
                $pr->contractCreated = 1;
                $pr->status = 1;
                $pr->save();
            }

            $banking = CustomerBanking::where('policy_id', $policy->id)->orderBy('id', 'desc')->first();
            if ($banking) {
                $banking->billing = 'RealPay';
                $banking->save();
            }

            Log::info('RealPay sync: stored existing contract(s)', [
                'policy_id' => $policy->id,
                'synced'    => $synced,
                'contract'  => $lastContractNo,
            ]);
        }

        return $synced;
    }


    /**
     * Portal contract lookup that distinguishes "RealPay says this policy has no
     * contracts" from "RealPay could not be reached".
     *
     * getExistingRealpayContract() collapses both cases into null. That is fine
     * for a read-only sync, but not for the duplicate guard: "nothing to cancel,
     * go ahead and create" and "I cannot tell, do not create" are opposite
     * decisions and the guard has to know which one it is looking at.
     *
     * Probes the platform the policy belongs to first (product_id 3 = legacy
     * motor, everything else = START) and falls back to the other, because a
     * handful of policies have contracts on the platform they were not created
     * on. `reachable` is true as soon as either probe returns a decoded
     * ContractGetResponse — an empty array included.
     *
     * @return array{reachable: bool, contracts: array<int, array>}
     */
    public function fetchPortalContracts($policy_id, $clientNumber): array
    {
        $policy  = Policy::where('id', $policy_id)->first();
        $isMotor = isset($policy) && $policy->product_id == 3;

        $probes = $isMotor
            ? ['checkMotorContractActive', 'checkInstantContractActive']
            : ['checkInstantContractActive', 'checkMotorContractActive'];

        $reachable = false;
        $contracts = [];

        foreach ($probes as $probe) {
            try {
                $resp = $this->{$probe}($policy_id, $clientNumber);
            } catch (\Throwable $e) {
                Log::warning('[REALPAY DUPLICATE GUARD] portal probe threw', [
                    'policy_id' => $policy_id,
                    'probe'     => $probe,
                    'error'     => $e->getMessage(),
                ]);
                continue;
            }

            if (!is_array($resp)) {
                // Auth failed, or the body carried no ContractGetResponse. Not
                // an answer — try the other platform.
                continue;
            }

            $reachable = true;

            foreach ($resp as $contract) {
                if (is_array($contract)) {
                    $contracts[] = $contract;
                }
            }

            if (!empty($contracts)) {
                // A real answer. The second probe would only cost another
                // auth + GET against a platform this policy does not use.
                break;
            }
        }

        return ['reachable' => $reachable, 'contracts' => $contracts];
    }

    /**
     * The contract numbers in a portal response that can still take money.
     *
     * A contract counts as live when RealPay marks the contract itself active
     * ('A') OR when it still carries an active instalment. The two checks
     * already in this file disagree — getExistingRealpayContract() reads
     * ContractStatus, hasActiveApiInstallments() reads InstalmentStatus — and
     * for a duplicate guard the safe reading is the union: either one debits
     * the customer.
     *
     * @param  array<int, array> $contracts
     * @return array<int, string>
     */
    public function activeContractNumbers(array $contracts): array
    {
        $numbers = [];

        foreach ($contracts as $contract) {
            if (!is_array($contract) || empty($contract['ContractNumber'])) {
                continue;
            }

            $live = (($contract['ContractStatus'] ?? null) === 'A');

            if (!$live) {
                foreach (($contract['ContractInstalments'] ?? []) as $instalment) {
                    if (is_array($instalment) && ($instalment['InstalmentStatus'] ?? null) === 'A') {
                        $live = true;
                        break;
                    }
                }
            }

            if ($live) {
                $numbers[(string) $contract['ContractNumber']] = (string) $contract['ContractNumber'];
            }
        }

        return array_values($numbers);
    }

    /**
     * DELETE one contract on RealPay and report precisely whether it worked.
     *
     * cancelRealpayContract*() already send this request, but they read
     * $data['ContractDeleteResponse'][0]['Successful'] straight off the decoded
     * body: on PHP 8 a transport error (null $data) turns that into a TypeError
     * inside sizeof(), and any other shape silently counts as "cancel failed"
     * with nothing logged about why. The duplicate guard has to be able to say
     * "the cancellation failed, so I am not creating a contract", so it needs a
     * call that never throws and always explains itself.
     *
     * The product code is the one the contract was created under: the create
     * path picks config('realpay.fnb_product') when the customer banks with
     * bank 12, and the platform product otherwise. When the first code is
     * rejected the other is retried — a cancel that misses because we guessed
     * the wrong product code is exactly the duplicate this change exists to
     * prevent.
     *
     * @return array{ok: bool, message: string, attempts: array<int, array>}
     */
    public function deleteRealpayContractByNumber($policy, $contractNumber): array
    {
        $contractNumber = (string) $contractNumber;

        if (!isset($policy) || $contractNumber === '') {
            return ['ok' => false, 'message' => 'missing policy or contract number', 'attempts' => []];
        }

        $isMotor = $policy->product_id == 3;

        try {
            $fetchToken = $isMotor ? $this->clientAuth() : $this->clientAuthForInstantProduct();
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'RealPay auth threw: ' . $e->getMessage(), 'attempts' => []];
        }

        if (!is_array($fetchToken) || empty($fetchToken['token_type']) || empty($fetchToken['access_token'])) {
            return ['ok' => false, 'message' => 'could not authenticate with RealPay to cancel the contract', 'attempts' => []];
        }

        $token = $fetchToken['token_type'] . ' ' . $fetchToken['access_token'];

        $baseUrl  = $isMotor ? config('realpay.base_url') : config('realpay.start.base_url');
        $merchant = $isMotor ? config('realpay.merchant') : config('realpay.start.merchant');
        $version  = $isMotor ? config('realpay.version')  : config('realpay.start.version');

        $banking     = $this->resolvePolicyCustomerBanking($policy);
        $platformPrd = $isMotor ? config('realpay.product') : config('realpay.start.product');
        $fnbPrd      = config('realpay.fnb_product');

        // Most likely product code first, then the alternate.
        $products = (isset($banking) && $banking->bankName == 12 && !empty($fnbPrd))
            ? [$fnbPrd, $platformPrd]
            : [$platformPrd, $fnbPrd];

        $attempts = [];

        foreach (array_values(array_unique(array_filter($products))) as $product) {
            $url = $baseUrl . '/maintain/contracts/' . $product
                 . '?ClientNumber=' . $policy->policyNumber
                 . '&ContractNumber=' . $contractNumber
                 . '&BeneficiaryUser=' . $merchant
                 . '&Version=' . $version;

            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => "",
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => "DELETE",
                CURLOPT_HTTPHEADER     => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: " . $token,
                ),
            ));

            $response  = curl_exec($curl);
            $curlError = curl_error($curl);
            $httpCode  = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($response === false || $response === null) {
                $attempts[] = ['product' => $product, 'http' => $httpCode, 'error' => $curlError ?: 'no response'];
                continue;
            }

            $data       = json_decode($response, true);
            $successful = $data['ContractDeleteResponse'][0]['Successful'] ?? null;
            $ok         = is_array($successful) ? count($successful) > 0 : (bool) $successful;

            $attempts[] = [
                'product' => $product,
                'http'    => $httpCode,
                'ok'      => $ok,
                'error'   => $ok ? null : ($data['ContractDeleteResponse'][0]['Unsuccessful'] ?? $response),
            ];

            if ($ok) {
                return [
                    'ok'       => true,
                    'message'  => 'contract ' . $contractNumber . ' cancelled on RealPay',
                    'attempts' => $attempts,
                ];
            }
        }

        return [
            'ok'       => false,
            'message'  => 'RealPay did not confirm cancellation of contract ' . $contractNumber,
            'attempts' => $attempts,
        ];
    }

}

